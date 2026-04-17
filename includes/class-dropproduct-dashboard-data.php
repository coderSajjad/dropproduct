<?php
/**
 * Dashboard Data Provider
 *
 * Centralised, performance-optimised data layer for every dashboard widget.
 * All expensive queries are cached with 5-minute transients to support
 * stores with 1,000+ products.
 *
 * @package DropProduct
 * @since   1.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DropProduct_Dashboard_Data
{
    /** Transient TTL in seconds (5 minutes). */
    const CACHE_TTL = 300;

    /**
     * Delete every dashboard transient — called when cache bust is requested.
     */
    public static function flush_cache()
    {
        $keys = array(
            'dpd_financials',
            'dpd_security',
            'dpd_orders',
            'dpd_inventory',
            'dpd_readiness',
        );
        foreach ( $keys as $key ) {
            delete_transient( $key );
        }
    }

    /* ═══════════════════════════════════════════════════
       A. Financial Insights
       ═══════════════════════════════════════════════════ */

    /**
     * Return financial KPIs calculated from _dropproduct_cost_price meta.
     *
     * Metrics:
     *  - inventory_value   : SUM( cost × stock_qty )
     *  - potential_profit  : SUM( (price − cost) × stock_qty )
     *  - avg_margin        : AVG( (price − cost) / price × 100 )
     *  - tracked_products  : number of products with cost > 0
     *
     * @param bool $force Bypass transient cache.
     * @return array
     */
    public static function get_financials( $force = false )
    {
        if ( ! $force ) {
            $cached = get_transient( 'dpd_financials' );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        global $wpdb;

        // Use direct SQL for performance — avoids loading WC_Product objects.
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        $row = $wpdb->get_row( "
            SELECT
                COALESCE( SUM(
                    CAST( cost.meta_value AS DECIMAL(12,2) )
                    * GREATEST( CAST( COALESCE( stock.meta_value, '0' ) AS DECIMAL(10,0) ), 0 )
                ), 0 ) AS inventory_value,

                COALESCE( SUM(
                    ( CASE WHEN CAST( COALESCE( sale.meta_value, '0' ) AS DECIMAL(12,2) ) > 0
                           THEN CAST( sale.meta_value AS DECIMAL(12,2) )
                           ELSE CAST( COALESCE( reg.meta_value, '0' ) AS DECIMAL(12,2) )
                      END
                      - CAST( cost.meta_value AS DECIMAL(12,2) )
                    )
                    * GREATEST( CAST( COALESCE( stock.meta_value, '0' ) AS DECIMAL(10,0) ), 0 )
                ), 0 ) AS potential_profit,

                COALESCE( AVG(
                    CASE
                        WHEN ( CASE WHEN CAST( COALESCE( sale.meta_value, '0' ) AS DECIMAL(12,2) ) > 0
                                    THEN CAST( sale.meta_value AS DECIMAL(12,2) )
                                    ELSE CAST( COALESCE( reg.meta_value, '0' ) AS DECIMAL(12,2) )
                               END ) > 0
                        THEN (
                            ( CASE WHEN CAST( COALESCE( sale.meta_value, '0' ) AS DECIMAL(12,2) ) > 0
                                   THEN CAST( sale.meta_value AS DECIMAL(12,2) )
                                   ELSE CAST( COALESCE( reg.meta_value, '0' ) AS DECIMAL(12,2) )
                              END
                              - CAST( cost.meta_value AS DECIMAL(12,2) )
                            )
                            / ( CASE WHEN CAST( COALESCE( sale.meta_value, '0' ) AS DECIMAL(12,2) ) > 0
                                     THEN CAST( sale.meta_value AS DECIMAL(12,2) )
                                     ELSE CAST( COALESCE( reg.meta_value, '0' ) AS DECIMAL(12,2) )
                                END )
                        ) * 100
                        ELSE NULL
                    END
                ), 0 ) AS avg_margin,

                COUNT( DISTINCT p.ID ) AS tracked_products

            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} dp
                    ON dp.post_id = p.ID
                   AND dp.meta_key = '_dropproduct_product'
                   AND dp.meta_value = '1'
            INNER JOIN {$wpdb->postmeta} cost
                    ON cost.post_id = p.ID
                   AND cost.meta_key = '_dropproduct_cost_price'
                   AND cost.meta_value != ''
                   AND CAST( cost.meta_value AS DECIMAL(12,2) ) > 0
            LEFT  JOIN {$wpdb->postmeta} sale
                    ON sale.post_id = p.ID AND sale.meta_key = '_sale_price'
            LEFT  JOIN {$wpdb->postmeta} reg
                    ON reg.post_id = p.ID  AND reg.meta_key  = '_regular_price'
            LEFT  JOIN {$wpdb->postmeta} stock
                    ON stock.post_id = p.ID AND stock.meta_key = '_stock'
            WHERE p.post_type   = 'product'
              AND p.post_status IN ( 'publish', 'draft' )
        " );
        // phpcs:enable

        $data = array(
            'inventory_value'  => $row ? round( (float) $row->inventory_value,  2 ) : 0,
            'potential_profit' => $row ? round( (float) $row->potential_profit, 2 ) : 0,
            'avg_margin'       => $row ? round( (float) $row->avg_margin,       1 ) : 0,
            'tracked_products' => $row ? (int)   $row->tracked_products              : 0,
        );

        set_transient( 'dpd_financials', $data, self::CACHE_TTL );
        return $data;
    }

    /* ═══════════════════════════════════════════════════
       B. Security / Order Shield
       ═══════════════════════════════════════════════════ */

    /**
     * Return Order Shield stats for the security widget.
     *
     * @param bool $force Bypass transient cache.
     * @return array
     */
    public static function get_security_stats( $force = false )
    {
        if ( ! $force ) {
            $cached = get_transient( 'dpd_security' );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dropproduct_fraud_log';

        // Check table exists before querying.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );

        if ( ! $exists ) {
            $data = array(
                'total_blocked'   => 0,
                'total_on_hold'   => 0,
                'total_allowed'   => 0,
                'recent_threats'  => array(),
                'shield_enabled'  => false,
            );
            set_transient( 'dpd_security', $data, self::CACHE_TTL );
            return $data;
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $counts = $wpdb->get_results( "
            SELECT final_action, COUNT(*) AS cnt
            FROM {$table}
            GROUP BY final_action
        " );

        $tally = array( 'BLOCK' => 0, 'ON_HOLD' => 0, 'ALLOW' => 0 );
        foreach ( (array) $counts as $row ) {
            $tally[ $row->final_action ] = (int) $row->cnt;
        }

        $recent = $wpdb->get_results( "
            SELECT ip_address, email, final_action, risk_score, triggered_rules, created_at
            FROM {$table}
            WHERE final_action IN ('BLOCK','ON_HOLD')
            ORDER BY created_at DESC
            LIMIT 5
        " );
        // phpcs:enable

        $threats = array();
        foreach ( (array) $recent as $row ) {
            $rules    = json_decode( $row->triggered_rules, true );
            $threats[] = array(
                'ip'      => esc_html( $row->ip_address ),
                'email'   => esc_html( $row->email ),
                'action'  => esc_html( $row->final_action ),
                'score'   => (int) $row->risk_score,
                'rules'   => is_array( $rules ) ? array_map( 'esc_html', $rules ) : array(),
                'time'    => esc_html( $row->created_at ),
            );
        }

        $shield_cfg     = get_option( 'dropproduct_fraud_shield_settings', array() );
        $shield_enabled = ! empty( $shield_cfg['enabled'] );

        $data = array(
            'total_blocked'  => $tally['BLOCK'],
            'total_on_hold'  => $tally['ON_HOLD'],
            'total_allowed'  => $tally['ALLOW'],
            'recent_threats' => $threats,
            'shield_enabled' => $shield_enabled,
        );

        set_transient( 'dpd_security', $data, self::CACHE_TTL );
        return $data;
    }

    /* ═══════════════════════════════════════════════════
       C. Urgent Orders (Pending / Processing)
       ═══════════════════════════════════════════════════ */

    /**
     * Return the 10 most recent pending/processing orders.
     *
     * Uses wc_get_orders() for full HPOS compatibility.
     *
     * @param bool $force Bypass transient cache.
     * @return array
     */
    public static function get_urgent_orders( $force = false )
    {
        if ( ! $force ) {
            $cached = get_transient( 'dpd_orders' );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        $orders = wc_get_orders(array(
            'status'  => array( 'wc-pending', 'wc-processing' ),
            'limit'   => 10,
            'orderby' => 'date',
            'order'   => 'ASC', // oldest first = most urgent
        ));

        $data = array();
        foreach ( $orders as $order ) {
            $customer = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
            if ( ! $customer ) {
                $customer = $order->get_billing_email();
            }

            $date_created = $order->get_date_created();
            $time_diff    = $date_created instanceof WC_DateTime
                ? human_time_diff( $date_created->getTimestamp(), current_time( 'timestamp' ) )
                : '—';

            $data[] = array(
                'id'        => $order->get_id(),
                'customer'  => esc_html( $customer ),
                'status'    => esc_html( wc_get_order_status_name( $order->get_status() ) ),
                'status_key'=> esc_html( $order->get_status() ),
                'total'     => wc_price( $order->get_total() ),
                'time_ago'  => esc_html( $time_diff ),
                'edit_url'  => esc_url( get_edit_post_link( $order->get_id() ) ?: admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) ),
            );
        }

        set_transient( 'dpd_orders', $data, self::CACHE_TTL );
        return $data;
    }

    /* ═══════════════════════════════════════════════════
       D. Inventory Alerts
       ═══════════════════════════════════════════════════ */

    /**
     * Return inventory alert groups: low stock, ghost products, out-of-stock.
     *
     * @param bool $force     Bypass transient cache.
     * @param int  $threshold Low-stock threshold (default 5 units).
     * @return array
     */
    public static function get_inventory_alerts( $force = false, $threshold = 5 )
    {
        if ( ! $force ) {
            $cached = get_transient( 'dpd_inventory' );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        global $wpdb;

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        // Low stock: published products where _stock > 0 AND _stock < threshold.
        $low_stock = $wpdb->get_results( $wpdb->prepare( "
            SELECT p.ID, p.post_title,
                   CAST( s.meta_value AS UNSIGNED ) AS qty
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} s
                ON s.post_id = p.ID AND s.meta_key = '_stock'
            WHERE p.post_type   = 'product'
              AND p.post_status = 'publish'
              AND CAST( s.meta_value AS UNSIGNED ) > 0
              AND CAST( s.meta_value AS UNSIGNED ) < %d
            ORDER BY qty ASC
            LIMIT 20
        ", $threshold ) );

        // Out of stock: products with _stock_status = outofstock.
        $out_of_stock = $wpdb->get_results( "
            SELECT p.ID, p.post_title
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} ss
                ON ss.post_id = p.ID AND ss.meta_key = '_stock_status' AND ss.meta_value = 'outofstock'
            WHERE p.post_type   = 'product'
              AND p.post_status = 'publish'
            ORDER BY p.post_modified DESC
            LIMIT 20
        " );

        // Ghost products: published, no thumbnail OR no regular_price OR no excerpt.
        $ghost = $wpdb->get_results( "
            SELECT DISTINCT p.ID, p.post_title
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} thumb
                ON thumb.post_id = p.ID AND thumb.meta_key = '_thumbnail_id'
            LEFT JOIN {$wpdb->postmeta} price
                ON price.post_id = p.ID AND price.meta_key = '_regular_price'
            WHERE p.post_type   = 'product'
              AND p.post_status = 'publish'
              AND (
                    thumb.meta_id IS NULL
                    OR thumb.meta_value = ''
                    OR price.meta_id IS NULL
                    OR price.meta_value = ''
                    OR TRIM( p.post_excerpt ) = ''
              )
            LIMIT 20
        " );
        // phpcs:enable

        $format = function( $rows ) {
            $out = array();
            foreach ( (array) $rows as $row ) {
                $out[] = array(
                    'id'       => (int) $row->ID,
                    'title'    => esc_html( $row->post_title ),
                    'edit_url' => esc_url( get_edit_post_link( $row->ID ) ),
                    'qty'      => isset( $row->qty ) ? (int) $row->qty : null,
                );
            }
            return $out;
        };

        $data = array(
            'low_stock'    => $format( $low_stock ),
            'out_of_stock' => $format( $out_of_stock ),
            'ghost'        => $format( $ghost ),
            'threshold'    => (int) $threshold,
        );

        set_transient( 'dpd_inventory', $data, self::CACHE_TTL );
        return $data;
    }

    /* ═══════════════════════════════════════════════════
       E. Store Readiness
       ═══════════════════════════════════════════════════ */

    /**
     * Return store readiness checklist items.
     *
     * @param bool $force Bypass transient cache.
     * @return array
     */
    public static function get_store_readiness( $force = false )
    {
        if ( ! $force ) {
            $cached = get_transient( 'dpd_readiness' );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        // 1. Payment gateway active?
        $gateways        = WC()->payment_gateways()->get_available_payment_gateways();
        $payment_ok      = ! empty( $gateways );

        // 2. Shipping zones configured?
        $zones           = WC_Shipping_Zones::get_zones();
        $shipping_ok     = ! empty( $zones );

        // 3. Tax enabled?
        $tax_ok          = wc_tax_enabled();

        // 4. At least 10 published products?
        $product_count   = (int) wp_count_posts( 'product' )->publish;
        $products_ok     = $product_count >= 10;

        // 5. WooCommerce currency set?
        $currency_ok     = ! empty( get_woocommerce_currency() );

        // 6. Store address filled?
        $address_ok      = ! empty( get_option( 'woocommerce_store_city' ) );

        $items = array(
            array(
                'key'    => 'payment',
                'label'  => __( 'Payment gateway active', 'dropproduct' ),
                'done'   => $payment_ok,
                'fix_url'=> admin_url( 'admin.php?page=wc-settings&tab=checkout' ),
            ),
            array(
                'key'    => 'shipping',
                'label'  => __( 'Shipping zone configured', 'dropproduct' ),
                'done'   => $shipping_ok,
                'fix_url'=> admin_url( 'admin.php?page=wc-settings&tab=shipping' ),
            ),
            array(
                'key'    => 'tax',
                'label'  => __( 'Tax rules enabled', 'dropproduct' ),
                'done'   => $tax_ok,
                'fix_url'=> admin_url( 'admin.php?page=wc-settings&tab=tax' ),
            ),
            array(
                'key'    => 'products',
                'label'  => sprintf(
                    /* translators: %d: current product count */
                    __( 'First 10 products live (%d published)', 'dropproduct' ),
                    $product_count
                ),
                'done'   => $products_ok,
                'fix_url'=> admin_url( 'admin.php?page=dropproduct' ),
            ),
            array(
                'key'    => 'currency',
                'label'  => __( 'Currency configured', 'dropproduct' ),
                'done'   => $currency_ok,
                'fix_url'=> admin_url( 'admin.php?page=wc-settings&tab=general' ),
            ),
            array(
                'key'    => 'address',
                'label'  => __( 'Store address set', 'dropproduct' ),
                'done'   => $address_ok,
                'fix_url'=> admin_url( 'admin.php?page=wc-settings&tab=general' ),
            ),
        );

        $done_count = count( array_filter( $items, fn( $i ) => $i['done'] ) );

        $data = array(
            'items'      => $items,
            'done_count' => $done_count,
            'total'      => count( $items ),
            'percent'    => (int) round( $done_count / count( $items ) * 100 ),
        );

        set_transient( 'dpd_readiness', $data, self::CACHE_TTL );
        return $data;
    }
}
