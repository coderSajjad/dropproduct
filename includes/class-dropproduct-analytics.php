<?php
/**
 * DropProduct Analytics
 *
 * Handles sales analytics and reporting for DropProduct-created products.
 *
 * Data sources
 * ------------
 * Primary: WooCommerce's reporting lookup tables (`wc_order_product_lookup`,
 * `wc_order_stats`, `wc_customer_lookup`). These are indexed, pre-aggregated,
 * and — importantly — independent of where orders are physically stored, so
 * they work identically with legacy post storage and with HPOS.
 *
 * Fallback: `wc_get_orders()` line-item iteration, used when the lookup tables
 * are absent or unpopulated (WooCommerce Analytics disabled, or the backfill
 * scheduler has not run yet). Slower, but always correct.
 *
 * Earlier drafts of this class queried `oi.product_id`, `oi.quantity` and `oi.total`
 * on `wc_woocommerce_order_items` — none of which are columns on that table —
 * and joined orders through `wp_posts`. Every query failed outright, so the
 * whole Analytics screen reported zeros on every install.
 *
 * @package DropProduct
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DropProduct_Analytics class
 */
class DropProduct_Analytics {

	/** Transient holding the cached DropProduct product ID list. */
	const IDS_TRANSIENT = 'dropproduct_product_ids';

	/** How long that list stays cached, in seconds. */
	const IDS_TTL = 300;

	/** Upper bound on how many product IDs the fallback path will track. */
	const MAX_TRACKED_PRODUCTS = 2000;

	/** Upper bound on orders scanned by the fallback path per report. */
	const MAX_FALLBACK_ORDERS = 5000;

	/** Order statuses counted as revenue. */
	const COUNTED_STATUSES = array( 'completed', 'processing', 'pending' );

	/**
	 * Per-request memo of the product ID list.
	 *
	 * @var int[]|null
	 */
	private $product_ids = null;

	/**
	 * Per-request memo of lookup-table availability.
	 *
	 * @var bool|null
	 */
	private $lookup_available = null;

	/**
	 * Per-request memo of fallback aggregates, keyed by "start|end".
	 *
	 * @var array
	 */
	private $fallback_cache = array();

	/**
	 * Get analytics data for a date range
	 *
	 * @param string $start_date Start date (YYYY-MM-DD).
	 * @param string $end_date End date (YYYY-MM-DD).
	 * @return array Analytics data.
	 */
	public function get_analytics_data( $start_date = null, $end_date = null ) {
		$now = (int) current_time( 'timestamp' );

		if ( null === $start_date ) {
			$start_date = wp_date( 'Y-m-d', $now - ( 30 * DAY_IN_SECONDS ) );
		}
		if ( null === $end_date ) {
			$end_date = wp_date( 'Y-m-d', $now );
		}

		return array(
			'summary'          => $this->get_summary_metrics( $start_date, $end_date ),
			'sales_over_time'  => $this->get_sales_over_time( $start_date, $end_date ),
			'top_products'     => $this->get_top_products( $start_date, $end_date ),
			'sales_by_country' => $this->get_sales_by_country( $start_date, $end_date ),
			'sales_by_channel' => $this->get_sales_by_channel( $start_date, $end_date ),
			'conversion_rate'  => $this->get_conversion_metrics( $start_date, $end_date ),
			// Surfaced so the UI can warn when figures come from the slower,
			// bounded fallback path rather than WooCommerce's lookup tables.
			'data_source'      => $this->lookup_tables_available() ? 'lookup' : 'orders',
		);
	}

	/* ═══════════════════════════════════════════════════
	   Reports
	   ═══════════════════════════════════════════════════ */

	/**
	 * Get summary metrics (total sales, orders, avg value, growth).
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array Summary data.
	 */
	private function get_summary_metrics( $start_date, $end_date ) {
		$totals = $this->query_totals( $start_date, $end_date );

		$total_sales  = (float) $totals['sales'];
		$total_orders = (int) $totals['orders'];

		$average_order_value = $total_orders > 0 ? $total_sales / $total_orders : 0;

		// Previous window of equal length, ending the day before this one starts.
		$start_ts = $this->day_start( $start_date );
		$end_ts   = $this->day_end( $end_date );
		$span     = max( DAY_IN_SECONDS, $end_ts - $start_ts );

		$prev_end   = wp_date( 'Y-m-d', $start_ts - DAY_IN_SECONDS );
		$prev_start = wp_date( 'Y-m-d', $start_ts - DAY_IN_SECONDS - $span );

		$prev_totals      = $this->query_totals( $prev_start, $prev_end );
		$prev_total_sales = (float) $prev_totals['sales'];

		$growth = $prev_total_sales > 0
			? ( ( $total_sales - $prev_total_sales ) / $prev_total_sales ) * 100
			: 0;

		return array(
			'total_sales'         => round( $total_sales, 2 ),
			'total_orders'        => $total_orders,
			'average_order_value' => round( $average_order_value, 2 ),
			// Conversion rate needs session/impression data that WooCommerce
			// does not record. Reported as null so the UI can show "—" rather
			// than the invented 2.35% this used to return.
			'conversion_rate'     => null,
			'growth'              => round( $growth, 1 ),
			'growth_label'        => $growth >= 0 ? '↑' : '↓',
			'growth_type'         => $growth >= 0 ? 'positive' : 'negative',
		);
	}

	/**
	 * Total revenue and distinct order count for a range.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array{sales: float, orders: int}
	 */
	private function query_totals( $start_date, $end_date ) {
		if ( ! $this->lookup_tables_available() ) {
			$agg = $this->fallback_aggregate( $start_date, $end_date );
			return array(
				'sales'  => $agg['sales'],
				'orders' => count( $agg['order_ids'] ),
			);
		}

		global $wpdb;

		$sql = $this->lookup_select(
			'SUM( pl.product_net_revenue ) AS sales, COUNT( DISTINCT pl.order_id ) AS orders'
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $this->range_args( $start_date, $end_date ) ) );

		return array(
			'sales'  => $row ? (float) $row->sales : 0.0,
			'orders' => $row ? (int) $row->orders : 0,
		);
	}

	/**
	 * Get sales over time (daily), zero-filled across the whole range.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array Sales data by date.
	 */
	private function get_sales_over_time( $start_date, $end_date ) {
		$rows = array();

		if ( $this->lookup_tables_available() ) {
			global $wpdb;

			$sql = $this->lookup_select(
				'DATE( pl.date_created ) AS date,
				 SUM( pl.product_net_revenue ) AS sales,
				 COUNT( DISTINCT pl.order_id ) AS orders',
				'GROUP BY DATE( pl.date_created ) ORDER BY date ASC'
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$results = $wpdb->get_results( $wpdb->prepare( $sql, $this->range_args( $start_date, $end_date ) ) );

			foreach ( (array) $results as $row ) {
				$rows[ $row->date ] = array(
					'sales'  => (float) $row->sales,
					'orders' => (int) $row->orders,
				);
			}
		} else {
			$agg = $this->fallback_aggregate( $start_date, $end_date );
			foreach ( $agg['by_date'] as $date => $bucket ) {
				$rows[ $date ] = array(
					'sales'  => (float) $bucket['sales'],
					'orders' => count( $bucket['order_ids'] ),
				);
			}
		}

		// Zero-fill so the chart has a continuous x-axis.
		$data       = array();
		$current_ts = $this->day_start( $start_date );
		$end_ts     = $this->day_end( $end_date );

		while ( $current_ts <= $end_ts ) {
			$key          = wp_date( 'Y-m-d', $current_ts );
			$data[]       = array(
				'date'   => $key,
				'sales'  => isset( $rows[ $key ] ) ? round( $rows[ $key ]['sales'], 2 ) : 0,
				'orders' => isset( $rows[ $key ] ) ? $rows[ $key ]['orders'] : 0,
			);
			$current_ts += DAY_IN_SECONDS;
		}

		return $data;
	}

	/**
	 * Get top products by sales.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array Top products.
	 */
	private function get_top_products( $start_date, $end_date ) {
		$top = array();

		if ( $this->lookup_tables_available() ) {
			global $wpdb;

			$sql = $this->lookup_select(
				'pl.product_id,
				 p.post_title AS product_name,
				 SUM( pl.product_net_revenue ) AS total_sales,
				 SUM( pl.product_qty ) AS quantity_sold',
				'GROUP BY pl.product_id, p.post_title ORDER BY total_sales DESC LIMIT 5'
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$results = $wpdb->get_results( $wpdb->prepare( $sql, $this->range_args( $start_date, $end_date ) ) );

			foreach ( (array) $results as $row ) {
				$top[] = array(
					'product_id'   => (int) $row->product_id,
					'product_name' => (string) $row->product_name,
					'sales'        => round( (float) $row->total_sales, 2 ),
					'quantity'     => (int) $row->quantity_sold,
				);
			}

			return $top;
		}

		$agg      = $this->fallback_aggregate( $start_date, $end_date );
		$products = $agg['by_product'];

		uasort(
			$products,
			static function ( $a, $b ) {
				return $b['sales'] <=> $a['sales'];
			}
		);

		foreach ( array_slice( $products, 0, 5, true ) as $product_id => $bucket ) {
			$top[] = array(
				'product_id'   => (int) $product_id,
				'product_name' => (string) $bucket['name'],
				'sales'        => round( (float) $bucket['sales'], 2 ),
				'quantity'     => (int) $bucket['qty'],
			);
		}

		return $top;
	}

	/**
	 * Get sales by billing country.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array Sales by country.
	 */
	private function get_sales_by_country( $start_date, $end_date ) {
		$countries = array();

		if ( $this->lookup_tables_available() ) {
			global $wpdb;

			$sql = $this->lookup_select(
				'cl.country AS country,
				 SUM( pl.product_net_revenue ) AS total_sales,
				 COUNT( DISTINCT pl.order_id ) AS order_count',
				"GROUP BY cl.country HAVING country IS NOT NULL AND country != ''
				 ORDER BY total_sales DESC LIMIT 10",
				true
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$results = $wpdb->get_results( $wpdb->prepare( $sql, $this->range_args( $start_date, $end_date ) ) );

			foreach ( (array) $results as $row ) {
				$countries[] = array(
					'country'    => (string) $row->country,
					'sales'      => round( (float) $row->total_sales, 2 ),
					'percentage' => 0, // Calculated client-side.
				);
			}

			return $countries;
		}

		$agg     = $this->fallback_aggregate( $start_date, $end_date );
		$buckets = $agg['by_country'];

		arsort( $buckets );

		foreach ( array_slice( $buckets, 0, 10, true ) as $code => $sales ) {
			$countries[] = array(
				'country'    => (string) $code,
				'sales'      => round( (float) $sales, 2 ),
				'percentage' => 0,
			);
		}

		return $countries;
	}

	/**
	 * Get sales by channel (Direct, Organic, etc.)
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array Sales by channel.
	 */
	private function get_sales_by_channel( $start_date, $end_date ) {
		/**
		 * Filter sales-by-channel data.
		 *
		 * DropProduct does not track referral channels itself, so this returns
		 * an empty set and the widget renders its "no data" state. Attribution
		 * add-ons (UTM trackers, analytics integrations) can populate it here.
		 *
		 * Previously this method returned hardcoded sample figures, which were
		 * displayed to users as if they were real store data.
		 *
		 * @since 1.2.0
		 * @param array  $channels   List of { channel, sales, percentage }.
		 * @param string $start_date Range start (Y-m-d).
		 * @param string $end_date   Range end (Y-m-d).
		 */
		return apply_filters( 'dropproduct_analytics_sales_by_channel', array(), $start_date, $end_date );
	}

	/**
	 * Get conversion metrics
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array Conversion data.
	 */
	private function get_conversion_metrics( $start_date, $end_date ) {
		/**
		 * Filter the device / conversion breakdown.
		 *
		 * WooCommerce does not store a device type on orders, so DropProduct
		 * has nothing to report and the widget renders its "no data" state.
		 *
		 * Previously this method returned hardcoded sample figures (whose
		 * percentages summed to 110%), presented to users as real store data.
		 *
		 * @since 1.2.0
		 * @param array  $devices    List of { device, orders, sales, percentage }.
		 * @param string $start_date Range start (Y-m-d).
		 * @param string $end_date   Range end (Y-m-d).
		 */
		return apply_filters( 'dropproduct_analytics_conversion_metrics', array(), $start_date, $end_date );
	}

	/* ═══════════════════════════════════════════════════
	   Lookup-table query builder
	   ═══════════════════════════════════════════════════ */

	/**
	 * Build a SELECT against the WooCommerce lookup tables.
	 *
	 * DropProduct products are identified by joining post meta on the product
	 * ID rather than by an IN() list, so there is no cap on catalogue size.
	 * Products remain in wp_posts under HPOS — only orders move — so this join
	 * is valid in both storage modes.
	 *
	 * The returned SQL contains two %s placeholders (range start, range end)
	 * and must be passed through $wpdb->prepare() with range_args().
	 *
	 * @param string $select       Columns to select.
	 * @param string $tail         GROUP BY / ORDER BY / LIMIT clause.
	 * @param bool   $join_country Whether to join the customer lookup table.
	 * @return string SQL with placeholders.
	 */
	private function lookup_select( $select, $tail = '', $join_country = false ) {
		global $wpdb;

		$statuses     = $this->status_in_clause();
		$country_join = $join_country
			? "LEFT JOIN {$wpdb->prefix}wc_customer_lookup cl ON cl.customer_id = os.customer_id"
			: '';

		return "SELECT {$select}
			FROM {$wpdb->prefix}wc_order_product_lookup pl
			INNER JOIN {$wpdb->prefix}wc_order_stats os
				ON os.order_id = pl.order_id
			INNER JOIN {$wpdb->posts} p
				ON p.ID = pl.product_id
			INNER JOIN {$wpdb->postmeta} dp
				ON dp.post_id = pl.product_id
			   AND dp.meta_key = '_dropproduct_product'
			   AND dp.meta_value = '1'
			{$country_join}
			WHERE pl.date_created BETWEEN %s AND %s
			  AND os.status IN ({$statuses})
			{$tail}";
	}

	/**
	 * Bound parameters for a lookup_select() query.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function range_args( $start_date, $end_date ) {
		return array(
			wp_date( 'Y-m-d H:i:s', $this->day_start( $start_date ) ),
			wp_date( 'Y-m-d H:i:s', $this->day_end( $end_date ) ),
		);
	}

	/**
	 * Quoted, comma-separated status list for a SQL IN() clause.
	 *
	 * Values come from a hardcoded constant, so quoting them directly is safe.
	 *
	 * @return string
	 */
	private function status_in_clause() {
		$prefixed = array_map(
			static function ( $status ) {
				return "'wc-" . $status . "'";
			},
			self::COUNTED_STATUSES
		);

		return implode( ',', $prefixed );
	}

	/**
	 * Whether WooCommerce's reporting lookup tables exist and hold data.
	 *
	 * Cached for a minute: a store that has just enabled Analytics will pick up
	 * the faster path shortly after the backfill scheduler runs.
	 *
	 * @return bool
	 */
	private function lookup_tables_available() {
		if ( null !== $this->lookup_available ) {
			return $this->lookup_available;
		}

		$cached = get_transient( 'dropproduct_lookup_ready' );

		if ( false !== $cached ) {
			$this->lookup_available = ( 'yes' === $cached );
			return $this->lookup_available;
		}

		global $wpdb;

		$product_table = $wpdb->prefix . 'wc_order_product_lookup';
		$stats_table   = $wpdb->prefix . 'wc_order_stats';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$have_tables = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ( %s, %s )',
				$product_table,
				$stats_table
			)
		);

		$ready = false;

		if ( 2 === $have_tables ) {
			// An existing but empty table means the backfill has not run, in
			// which case the lookup path would silently report zero sales.
			$ready = (bool) $wpdb->get_var( "SELECT 1 FROM `{$product_table}` LIMIT 1" );
		}
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		set_transient( 'dropproduct_lookup_ready', $ready ? 'yes' : 'no', MINUTE_IN_SECONDS );

		$this->lookup_available = $ready;

		return $this->lookup_available;
	}

	/* ═══════════════════════════════════════════════════
	   Fallback path (no lookup tables)
	   ═══════════════════════════════════════════════════ */

	/**
	 * Aggregate order line items for a range using the WooCommerce CRUD API.
	 *
	 * Used when the reporting lookup tables are unavailable. wc_get_orders() is
	 * storage-agnostic, so this works under both HPOS and legacy post storage.
	 * One pass produces every figure the reports need.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array{sales: float, order_ids: array, by_date: array, by_product: array, by_country: array}
	 */
	private function fallback_aggregate( $start_date, $end_date ) {
		$cache_key = $start_date . '|' . $end_date;

		if ( isset( $this->fallback_cache[ $cache_key ] ) ) {
			return $this->fallback_cache[ $cache_key ];
		}

		$agg = array(
			'sales'      => 0.0,
			'order_ids'  => array(),
			'by_date'    => array(),
			'by_product' => array(),
			'by_country' => array(),
		);

		$tracked = array_flip( $this->get_dropproduct_ids() );

		if ( empty( $tracked ) ) {
			$this->fallback_cache[ $cache_key ] = $agg;
			return $agg;
		}

		$orders = wc_get_orders(
			array(
				'limit'        => self::MAX_FALLBACK_ORDERS,
				'status'       => self::COUNTED_STATUSES,
				'type'         => 'shop_order',
				'date_created' => $this->day_start( $start_date ) . '...' . $this->day_end( $end_date ),
				'orderby'      => 'date',
				'order'        => 'DESC',
			)
		);

		foreach ( (array) $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$created = $order->get_date_created();
			if ( ! $created ) {
				continue;
			}

			$date_key = $created->date( 'Y-m-d' );
			$country  = $order->get_billing_country();
			$order_id = $order->get_id();

			foreach ( $order->get_items() as $item ) {
				$product_id = $item->get_product_id();

				// Only DropProduct-created products count toward these reports.
				if ( ! isset( $tracked[ $product_id ] ) ) {
					continue;
				}

				// get_total() is the line total for the whole line — it already
				// accounts for quantity, so it must not be multiplied again.
				$line_total = (float) $item->get_total();
				$qty        = (int) $item->get_quantity();
				$name       = $item->get_name();

				$agg['sales']                 += $line_total;
				$agg['order_ids'][ $order_id ] = true;

				if ( ! isset( $agg['by_date'][ $date_key ] ) ) {
					$agg['by_date'][ $date_key ] = array(
						'sales'     => 0.0,
						'order_ids' => array(),
					);
				}
				$agg['by_date'][ $date_key ]['sales']                 += $line_total;
				$agg['by_date'][ $date_key ]['order_ids'][ $order_id ] = true;

				if ( ! isset( $agg['by_product'][ $product_id ] ) ) {
					$agg['by_product'][ $product_id ] = array(
						'name'  => $name,
						'sales' => 0.0,
						'qty'   => 0,
					);
				}
				$agg['by_product'][ $product_id ]['sales'] += $line_total;
				$agg['by_product'][ $product_id ]['qty']   += $qty;

				if ( $country ) {
					if ( ! isset( $agg['by_country'][ $country ] ) ) {
						$agg['by_country'][ $country ] = 0.0;
					}
					$agg['by_country'][ $country ] += $line_total;
				}
			}
		}

		$this->fallback_cache[ $cache_key ] = $agg;

		return $agg;
	}

	/* ═══════════════════════════════════════════════════
	   Helpers
	   ═══════════════════════════════════════════════════ */

	/**
	 * Site-timezone timestamp for 00:00:00 on a date.
	 *
	 * @param string $date Y-m-d.
	 * @return int
	 */
	private function day_start( $date ) {
		$ts = strtotime( $date . ' 00:00:00' );
		return false === $ts ? (int) current_time( 'timestamp' ) : $ts;
	}

	/**
	 * Site-timezone timestamp for 23:59:59 on a date.
	 *
	 * @param string $date Y-m-d.
	 * @return int
	 */
	private function day_end( $date ) {
		$ts = strtotime( $date . ' 23:59:59' );
		return false === $ts ? (int) current_time( 'timestamp' ) : $ts;
	}

	/**
	 * Get all DropProduct product IDs
	 *
	 * Only the fallback path needs this; the lookup path filters with a join.
	 *
	 * @return array Product IDs.
	 */
	private function get_dropproduct_ids() {
		// Memoised per request, then cached, because the fallback aggregate can
		// be requested for several ranges in one page load.
		if ( null !== $this->product_ids ) {
			return $this->product_ids;
		}

		$cached = get_transient( self::IDS_TRANSIENT );

		if ( is_array( $cached ) ) {
			$this->product_ids = $cached;
			return $this->product_ids;
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'draft' ),
			'meta_key'       => '_dropproduct_product', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query
			'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query
			// Bounded so the fallback membership set stays a sane size on large
			// catalogues. Previously unlimited.
			'posts_per_page' => self::MAX_TRACKED_PRODUCTS,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		);

		$ids = array_map( 'intval', (array) get_posts( $args ) );

		set_transient( self::IDS_TRANSIENT, $ids, self::IDS_TTL );

		$this->product_ids = $ids;

		return $this->product_ids;
	}

	/**
	 * Invalidate the cached product ID list.
	 *
	 * Hooked to product create/publish/delete so analytics never lags behind
	 * the catalogue by more than one action.
	 *
	 * @since 1.2.0
	 */
	public static function flush_ids_cache() {
		delete_transient( self::IDS_TRANSIENT );
	}

	/**
	 * Get date range options for UI
	 *
	 * @return array Date range options.
	 */
	public static function get_date_ranges() {
		// Built from site time, not server time, so the ranges line up with the
		// dates merchants see everywhere else in WooCommerce.
		$now   = (int) current_time( 'timestamp' );
		$today = wp_date( 'Y-m-d', $now );

		$offsets = array(
			'7_days'  => array( __( 'Last 7 days', 'dropproduct' ), 7 ),
			'30_days' => array( __( 'Last 30 days', 'dropproduct' ), 30 ),
			'90_days' => array( __( 'Last 90 days', 'dropproduct' ), 90 ),
			'1_year'  => array( __( 'Last year', 'dropproduct' ), 365 ),
		);

		$ranges = array();

		foreach ( $offsets as $key => $spec ) {
			list( $label, $days ) = $spec;

			$ranges[ $key ] = array(
				'label'      => $label,
				'start_date' => wp_date( 'Y-m-d', $now - ( $days * DAY_IN_SECONDS ) ),
				'end_date'   => $today,
			);
		}

		return $ranges;
	}
}
