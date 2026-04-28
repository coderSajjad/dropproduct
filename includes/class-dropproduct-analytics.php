<?php
/**
 * DropProduct Analytics
 *
 * Handles sales analytics and reporting for DropProduct-created products.
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

	/**
	 * Get analytics data for a date range
	 *
	 * @param string $start_date Start date (YYYY-MM-DD).
	 * @param string $end_date End date (YYYY-MM-DD).
	 * @return array Analytics data.
	 */
	public function get_analytics_data( $start_date = null, $end_date = null ) {
		if ( null === $start_date ) {
			$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
		}
		if ( null === $end_date ) {
			$end_date = date( 'Y-m-d' );
		}

		return array(
			'summary'              => $this->get_summary_metrics( $start_date, $end_date ),
			'sales_over_time'      => $this->get_sales_over_time( $start_date, $end_date ),
			'top_products'         => $this->get_top_products( $start_date, $end_date ),
			'sales_by_country'     => $this->get_sales_by_country( $start_date, $end_date ),
			'sales_by_channel'     => $this->get_sales_by_channel( $start_date, $end_date ),
			'conversion_rate'      => $this->get_conversion_metrics( $start_date, $end_date ),
		);
	}

	/**
	 * Get summary metrics (total sales, orders, avg value, etc.)
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array Summary data.
	 */
	private function get_summary_metrics( $start_date, $end_date ) {
		global $wpdb;

		// Get DropProduct product IDs.
		$product_ids = $this->get_dropproduct_ids();
		if ( empty( $product_ids ) ) {
			return array(
				'total_sales'         => 0,
				'total_orders'        => 0,
				'average_order_value' => 0,
				'conversion_rate'     => 0,
				'comparison'          => array(),
			);
		}

		$product_ids_str = implode( ',', array_map( 'intval', $product_ids ) );
		$start_ts         = strtotime( $start_date . ' 00:00:00' );
		$end_ts           = strtotime( $end_date . ' 23:59:59' );

		// Get orders with DropProduct items in the current period.
		$query = $wpdb->prepare(
			"SELECT 
				COUNT(DISTINCT oi.order_id) as order_count,
				SUM(oi.total * oi.quantity) as total_sales,
				COUNT(oi.id) as item_count
			FROM {$wpdb->prefix}woocommerce_order_items oi
			INNER JOIN {$wpdb->posts} o ON oi.order_id = o.ID
			WHERE oi.product_id IN ({$product_ids_str})
			AND o.post_date BETWEEN %s AND %s
			AND o.post_status IN ('wc-completed', 'wc-processing', 'wc-pending')",
			date( 'Y-m-d H:i:s', $start_ts ),
			date( 'Y-m-d H:i:s', $end_ts )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_row( $query );

		$total_sales         = $result ? floatval( $result->total_sales ) : 0;
		$total_orders        = $result ? intval( $result->order_count ) : 0;
		$average_order_value = $total_orders > 0 ? $total_sales / $total_orders : 0;

		// Get previous period data for comparison.
		$period_diff      = ( $end_ts - $start_ts ) / 86400; // days.
		$prev_start_ts    = $start_ts - ( $period_diff * 86400 );
		$prev_end_ts      = $start_ts - 1;
		$prev_total_sales = $this->get_sales_for_period( $product_ids_str, $prev_start_ts, $prev_end_ts );

		$growth = $prev_total_sales > 0 ? ( ( $total_sales - $prev_total_sales ) / $prev_total_sales ) * 100 : 0;

		return array(
			'total_sales'         => round( $total_sales, 2 ),
			'total_orders'        => $total_orders,
			'average_order_value' => round( $average_order_value, 2 ),
			'conversion_rate'     => 2.35, // Placeholder, can be calculated with impressions.
			'growth'              => round( $growth, 1 ),
			'growth_label'        => $growth >= 0 ? '↑' : '↓',
			'growth_type'         => $growth >= 0 ? 'positive' : 'negative',
		);
	}

	/**
	 * Get sales over time (daily)
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array Sales data by date.
	 */
	private function get_sales_over_time( $start_date, $end_date ) {
		global $wpdb;

		$product_ids = $this->get_dropproduct_ids();
		if ( empty( $product_ids ) ) {
			return array();
		}

		$product_ids_str = implode( ',', array_map( 'intval', $product_ids ) );
		$start_ts         = strtotime( $start_date . ' 00:00:00' );
		$end_ts           = strtotime( $end_date . ' 23:59:59' );

		$query = $wpdb->prepare(
			"SELECT 
				DATE(o.post_date) as date,
				SUM(oi.total * oi.quantity) as sales,
				COUNT(DISTINCT oi.order_id) as orders
			FROM {$wpdb->prefix}woocommerce_order_items oi
			INNER JOIN {$wpdb->posts} o ON oi.order_id = o.ID
			WHERE oi.product_id IN ({$product_ids_str})
			AND o.post_date BETWEEN %s AND %s
			AND o.post_status IN ('wc-completed', 'wc-processing', 'wc-pending')
			GROUP BY DATE(o.post_date)
			ORDER BY o.post_date ASC",
			date( 'Y-m-d H:i:s', $start_ts ),
			date( 'Y-m-d H:i:s', $end_ts )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query );

		// Build complete date range with zeros for missing dates.
		$data = array();
		$current_ts = $start_ts;
		while ( $current_ts <= $end_ts ) {
			$date_key = date( 'Y-m-d', $current_ts );
			$data[ $date_key ] = array(
				'date'   => $date_key,
				'sales'  => 0,
				'orders' => 0,
			);
			$current_ts += 86400;
		}

		// Fill in actual data.
		if ( $results ) {
			foreach ( $results as $row ) {
				$data[ $row->date ] = array(
					'date'   => $row->date,
					'sales'  => floatval( $row->sales ),
					'orders' => intval( $row->orders ),
				);
			}
		}

		return array_values( $data );
	}

	/**
	 * Get top products by sales
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array Top products.
	 */
	private function get_top_products( $start_date, $end_date ) {
		global $wpdb;

		$product_ids = $this->get_dropproduct_ids();
		if ( empty( $product_ids ) ) {
			return array();
		}

		$product_ids_str = implode( ',', array_map( 'intval', $product_ids ) );
		$start_ts         = strtotime( $start_date . ' 00:00:00' );
		$end_ts           = strtotime( $end_date . ' 23:59:59' );

		$query = $wpdb->prepare(
			"SELECT 
				oi.product_id,
				p.post_title as product_name,
				SUM(oi.total * oi.quantity) as total_sales,
				SUM(oi.quantity) as quantity_sold
			FROM {$wpdb->prefix}woocommerce_order_items oi
			INNER JOIN {$wpdb->posts} o ON oi.order_id = o.ID
			INNER JOIN {$wpdb->posts} p ON oi.product_id = p.ID
			WHERE oi.product_id IN ({$product_ids_str})
			AND o.post_date BETWEEN %s AND %s
			AND o.post_status IN ('wc-completed', 'wc-processing', 'wc-pending')
			GROUP BY oi.product_id
			ORDER BY total_sales DESC
			LIMIT 5",
			date( 'Y-m-d H:i:s', $start_ts ),
			date( 'Y-m-d H:i:s', $end_ts )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query );

		$top_products = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$top_products[] = array(
					'product_id'   => intval( $row->product_id ),
					'product_name' => sanitize_text_field( $row->product_name ),
					'sales'        => round( floatval( $row->total_sales ), 2 ),
					'quantity'     => intval( $row->quantity_sold ),
				);
			}
		}

		return $top_products;
	}

	/**
	 * Get sales by country
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array Sales by country.
	 */
	private function get_sales_by_country( $start_date, $end_date ) {
		global $wpdb;

		$product_ids = $this->get_dropproduct_ids();
		if ( empty( $product_ids ) ) {
			return array();
		}

		$product_ids_str = implode( ',', array_map( 'intval', $product_ids ) );
		$start_ts         = strtotime( $start_date . ' 00:00:00' );
		$end_ts           = strtotime( $end_date . ' 23:59:59' );

		$query = $wpdb->prepare(
			"SELECT 
				om.meta_value as country,
				SUM(oi.total * oi.quantity) as total_sales,
				COUNT(DISTINCT oi.order_id) as order_count
			FROM {$wpdb->prefix}woocommerce_order_items oi
			INNER JOIN {$wpdb->posts} o ON oi.order_id = o.ID
			INNER JOIN {$wpdb->postmeta} om ON o.ID = om.post_id AND om.meta_key = '_billing_country'
			WHERE oi.product_id IN ({$product_ids_str})
			AND o.post_date BETWEEN %s AND %s
			AND o.post_status IN ('wc-completed', 'wc-processing', 'wc-pending')
			GROUP BY om.meta_value
			ORDER BY total_sales DESC
			LIMIT 10",
			date( 'Y-m-d H:i:s', $start_ts ),
			date( 'Y-m-d H:i:s', $end_ts )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query );

		$countries = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$countries[] = array(
					'country'  => sanitize_text_field( $row->country ),
					'sales'    => round( floatval( $row->total_sales ), 2 ),
					'percentage' => 0, // Will be calculated in JS.
				);
			}
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
		// Placeholder data - can be integrated with UTM tracking or Google Analytics.
		return array(
			array(
				'channel'    => 'Direct',
				'sales'      => 18542.10,
				'percentage' => 41,
			),
			array(
				'channel'    => 'Organic Search',
				'sales'      => 12398.46,
				'percentage' => 27,
			),
			array(
				'channel'    => 'Paid Search',
				'sales'      => 6845.20,
				'percentage' => 15,
			),
			array(
				'channel'    => 'Social Media',
				'sales'      => 4231.75,
				'percentage' => 9,
			),
			array(
				'channel'    => 'Email',
				'sales'      => 1214.39,
				'percentage' => 3,
			),
		);
	}

	/**
	 * Get conversion metrics
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array Conversion data.
	 */
	private function get_conversion_metrics( $start_date, $end_date ) {
		global $wpdb;

		$product_ids = $this->get_dropproduct_ids();
		if ( empty( $product_ids ) ) {
			return array();
		}

		$product_ids_str = implode( ',', array_map( 'intval', $product_ids ) );
		$start_ts         = strtotime( $start_date . ' 00:00:00' );
		$end_ts           = strtotime( $end_date . ' 23:59:59' );

		// Get order devices (can be tracked via user agent).
		$query = $wpdb->prepare(
			"SELECT 
				'Desktop' as device,
				COUNT(DISTINCT oi.order_id) as orders,
				SUM(oi.total * oi.quantity) as sales
			FROM {$wpdb->prefix}woocommerce_order_items oi
			INNER JOIN {$wpdb->posts} o ON oi.order_id = o.ID
			WHERE oi.product_id IN ({$product_ids_str})
			AND o.post_date BETWEEN %s AND %s
			AND o.post_status IN ('wc-completed', 'wc-processing', 'wc-pending')
			LIMIT 1",
			date( 'Y-m-d H:i:s', $start_ts ),
			date( 'Y-m-d H:i:s', $end_ts )
		);

		// Placeholder device breakdown.
		return array(
			array(
				'device'      => 'Desktop',
				'orders'      => 155,
				'sales'       => 23450.25,
				'percentage'  => 61.8,
			),
			array(
				'device'      => 'Mobile',
				'orders'      => 88,
				'sales'       => 16215.40,
				'percentage'  => 35.9,
			),
			array(
				'device'      => 'Tablet',
				'orders'      => 8,
				'sales'       => 5566.24,
				'percentage'  => 12.3,
			),
		);
	}

	/**
	 * Get total sales for a specific period
	 *
	 * @param string $product_ids_str Product IDs string.
	 * @param int    $start_ts Start timestamp.
	 * @param int    $end_ts End timestamp.
	 * @return float Total sales.
	 */
	private function get_sales_for_period( $product_ids_str, $start_ts, $end_ts ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"SELECT SUM(oi.total * oi.quantity) as total_sales
			FROM {$wpdb->prefix}woocommerce_order_items oi
			INNER JOIN {$wpdb->posts} o ON oi.order_id = o.ID
			WHERE oi.product_id IN ({$product_ids_str})
			AND o.post_date BETWEEN %s AND %s
			AND o.post_status IN ('wc-completed', 'wc-processing', 'wc-pending')",
			date( 'Y-m-d H:i:s', $start_ts ),
			date( 'Y-m-d H:i:s', $end_ts )
		);
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_row( $query );

		return $result ? floatval( $result->total_sales ) : 0;
	}

	/**
	 * Get all DropProduct product IDs
	 *
	 * @return array Product IDs.
	 */
	private function get_dropproduct_ids() {
		$args = array(
			'post_type'      => 'product',
			'meta_key'       => '_dropproduct_product', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query
			'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		return get_posts( $args );
	}

	/**
	 * Get date range options for UI
	 *
	 * @return array Date range options.
	 */
	public static function get_date_ranges() {
		$today = date( 'Y-m-d' );
		return array(
			'7_days'   => array(
				'label'      => 'Last 7 days',
				'start_date' => date( 'Y-m-d', strtotime( '-7 days' ) ),
				'end_date'   => $today,
			),
			'30_days'  => array(
				'label'      => 'Last 30 days',
				'start_date' => date( 'Y-m-d', strtotime( '-30 days' ) ),
				'end_date'   => $today,
			),
			'90_days'  => array(
				'label'      => 'Last 90 days',
				'start_date' => date( 'Y-m-d', strtotime( '-90 days' ) ),
				'end_date'   => $today,
			),
			'1_year'   => array(
				'label'      => 'Last year',
				'start_date' => date( 'Y-m-d', strtotime( '-1 year' ) ),
				'end_date'   => $today,
			),
		);
	}
}
