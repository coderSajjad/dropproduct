<?php
/**
 * Ultimate Order Shield — Core Anti-Fraud Engine
 *
 * Flow: on_checkout_submit → collect_data → pre_checks
 *       → risk_scoring → decision → action → logging
 *
 * @package DropProduct
 * @since   1.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class DropProduct_Fraud_Shield
 */
class DropProduct_Fraud_Shield {

    // ──────────────────────────────────────────────────────────
    //  Constants
    // ──────────────────────────────────────────────────────────

    const OPTION_KEY            = 'dropproduct_fraud_shield';
    const FAILED_PREFIX         = 'dpshield_fp_';
    const SESSION_HOLD_KEY      = 'dpshield_hold';

    // ──────────────────────────────────────────────────────────
    //  Properties
    // ──────────────────────────────────────────────────────────

    /** @var array Cached settings */
    private $cfg;

    /** @var DropProduct_Fraud_Logger */
    private $logger;

    // ──────────────────────────────────────────────────────────
    //  Bootstrap
    // ──────────────────────────────────────────────────────────

    /**
     * @param DropProduct_Fraud_Logger $logger Injected logger instance.
     */
    public function __construct( DropProduct_Fraud_Logger $logger ) {
        $this->logger = $logger;
        $this->cfg    = $this->load_settings();
    }

    /**
     * Register WooCommerce hooks.
     * Called externally so construction is always cheap.
     */
    public function register_hooks() {
        if ( ! $this->is_enabled() ) {
            return;
        }

        // Inject honeypot + timestamp fields into the checkout form.
        add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'output_hidden_fields' ), 5 );

        // Primary validation — fires before order is created.
        add_action( 'woocommerce_checkout_process', array( $this, 'process_checkout' ) );

        // Post-creation — apply ON_HOLD status when needed.
        add_action( 'woocommerce_checkout_create_order', array( $this, 'on_create_order' ), 10, 2 );

        // Track failed payment attempts.
        add_action( 'woocommerce_order_status_failed', array( $this, 'track_failed_payment' ) );

        // Conditionally disable COD.
        add_filter( 'woocommerce_available_payment_gateways', array( $this, 'maybe_disable_cod' ) );

        // AJAX handlers for the admin panel.
        add_action( 'wp_ajax_dropproduct_save_fraud_settings', array( $this, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_dropproduct_fraud_delete_log',    array( $this, 'ajax_delete_log'    ) );
        add_action( 'wp_ajax_dropproduct_fraud_clear_logs',    array( $this, 'ajax_clear_logs'    ) );
    }

    // ──────────────────────────────────────────────────────────
    //  Settings
    // ──────────────────────────────────────────────────────────

    /**
     * Load settings merged with defaults.
     */
    public function load_settings() {
        return wp_parse_args(
            (array) get_option( self::OPTION_KEY, array() ),
            $this->defaults()
        );
    }

    public function is_enabled() {
        return ! empty( $this->cfg['enabled'] );
    }

    private function defaults() {
        return array(
            'enabled'                   => true,
            'block_threshold'           => 70,
            'review_threshold'          => 40,
            'action_mode'               => 'block',     // 'block' | 'hold'
            'max_orders_per_ip'         => 3,
            'failed_payment_threshold'  => 5,
            'checkout_time_threshold'   => 5,           // seconds
            'enable_ip_country_check'   => true,
            'enable_cod_restriction'    => true,
            'cod_restriction_threshold' => 40,
            'disposable_domains'        => implode( "\n", $this->default_disposable_domains() ),
            'blacklist'                 => '',
        );
    }

    public function get_settings() {
        return $this->cfg;
    }

    // ──────────────────────────────────────────────────────────
    //  WooCommerce Hooks
    // ──────────────────────────────────────────────────────────

    /**
     * Output honeypot field (invisible to real users) and timestamp field.
     * The timestamp JS is inlined to record when the page loaded.
     */
    public function output_hidden_fields() {
        echo '<div style="display:none!important;position:absolute;left:-9999px;" aria-hidden="true">';
        echo '<input type="text" name="_dpshield_hp" id="_dpshield_hp" tabindex="-1" autocomplete="off" value="" />';
        echo '</div>';
        echo '<input type="hidden" name="_dpshield_ts" id="_dpshield_ts" value="" />';
        echo '<script>document.getElementById("_dpshield_ts").value=Math.floor(Date.now()/1000);</script>';
    }

    /**
     * Main checkout validation.
     * Runs before the order is created — can add WC error notices to abort.
     */
    public function process_checkout() {
        $data    = $this->collect_data();
        $reasons = array();

        // ── 1. Instant pre-checks ──────────────────────────────
        if ( 'BLOCK' === $this->run_pre_checks( $data, $reasons ) ) {
            $this->execute_block( $data, 999, $reasons );
            return;
        }

        // ── 2. Risk scoring ────────────────────────────────────
        $score   = 0;
        $reasons = array();

        $score += $this->score_disposable_email(   $data, $reasons );
        $score += $this->score_ip_velocity(        $data, $reasons );
        $score += $this->score_repeated_data(      $data, $reasons );
        $score += $this->score_ip_country_mismatch($data, $reasons );
        $score += $this->score_failed_payments(    $data, $reasons );
        $score += $this->score_checkout_speed(     $data, $reasons );

        // Card testing: too many failed payments → instant block.
        $failed_threshold = (int) $this->cfg['failed_payment_threshold'];
        if ( $data['failed_payment_attempts'] >= $failed_threshold && $failed_threshold > 0 ) {
            $reasons[] = 'card_testing_block';
            $this->execute_block( $data, $score, $reasons );
            return;
        }

        // ── 3. Decision ────────────────────────────────────────
        $block_threshold  = (int) $this->cfg['block_threshold'];
        $review_threshold = (int) $this->cfg['review_threshold'];

        if ( $score >= $block_threshold ) {
            if ( 'hold' === $this->cfg['action_mode'] ) {
                // Store for on_create_order — let the order be created then hold it.
                WC()->session->set( self::SESSION_HOLD_KEY, array(
                    'score'   => $score,
                    'reasons' => $reasons,
                ) );
            } else {
                $this->execute_block( $data, $score, $reasons );
            }
        } elseif ( $score >= $review_threshold ) {
            WC()->session->set( self::SESSION_HOLD_KEY, array(
                'score'   => $score,
                'reasons' => $reasons,
            ) );
        } else {
            // ALLOW — log clean pass.
            $this->logger->log( array(
                'order_id'        => 0,
                'ip_address'      => $data['ip_address'],
                'email'           => $data['email'],
                'risk_score'      => $score,
                'triggered_rules' => $reasons,
                'final_action'    => 'ALLOW',
            ) );
        }
    }

    /**
     * Fires when WooCommerce creates the order object.
     * If a hold was requested, set the order on-hold and add notes.
     *
     * @param WC_Order $order The new order.
     * @param array    $data  Posted checkout data.
     */
    public function on_create_order( $order, $data ) {
        $hold = WC()->session->get( self::SESSION_HOLD_KEY );
        if ( ! $hold ) {
            return;
        }

        WC()->session->__unset( self::SESSION_HOLD_KEY );

        $score   = (int) $hold['score'];
        $reasons = (array) $hold['reasons'];

        $order->update_status( 'on-hold' );

        $note = sprintf(
            /* translators: %1$d = risk score, %2$s = rules list */
            __( '⚠️ Order Shield: Suspicious order (risk score: %1$d). Triggers: %2$s', 'dropproduct' ),
            $score,
            implode( ', ', array_map( array( $this, 'rule_label' ), $reasons ) )
        );
        $order->add_order_note( $note, false, false ); // Private note.

        if ( in_array( 'ip_country_mismatch', $reasons, true ) ) {
            $order->add_order_note(
                __( '⚠️ Warning: IP geolocation does not match the Billing Address country.', 'dropproduct' ),
                false,
                false
            );
        }

        $this->logger->log( array(
            'order_id'        => $order->get_id(),
            'ip_address'      => $order->get_customer_ip_address(),
            'email'           => $order->get_billing_email(),
            'risk_score'      => $score,
            'triggered_rules' => $reasons,
            'final_action'    => 'ON_HOLD',
        ) );
    }

    /**
     * Increment the failed-payment counter for the order's IP.
     *
     * @param int $order_id WC order ID.
     */
    public function track_failed_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        $key     = self::FAILED_PREFIX . md5( $order->get_customer_ip_address() );
        $current = (int) get_transient( $key );
        set_transient( $key, $current + 1, HOUR_IN_SECONDS );
    }

    /**
     * Remove COD from available gateways when the current IP looks risky.
     *
     * @param array $gateways Available payment gateways.
     * @return array
     */
    public function maybe_disable_cod( $gateways ) {
        if ( empty( $this->cfg['enable_cod_restriction'] ) || ! is_checkout() ) {
            return $gateways;
        }

        $threshold = (int) $this->cfg['cod_restriction_threshold'];
        $ip        = $this->get_ip();
        $score     = 0;

        // Quick lightweight score using IP velocity + failed payments only.
        if ( $this->count_recent_orders_by_ip( $ip, HOUR_IN_SECONDS ) >= (int) $this->cfg['max_orders_per_ip'] ) {
            $score += 30;
        }
        if ( $this->get_failed_attempt_count( $ip ) >= (int) $this->cfg['failed_payment_threshold'] ) {
            $score += 25;
        }

        if ( $score >= $threshold ) {
            unset( $gateways['cod'] );
        }

        return $gateways;
    }

    // ──────────────────────────────────────────────────────────
    //  Data Collection
    // ──────────────────────────────────────────────────────────

    private function collect_data() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        return array(
            'email'                   => isset( $_POST['billing_email'] )      ? sanitize_email( wp_unslash( $_POST['billing_email'] ) )      : '',
            'phone'                   => isset( $_POST['billing_phone'] )      ? sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) )  : '',
            'billing_name'            => trim( implode( ' ', array_filter( array(
                isset( $_POST['billing_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_first_name'] ) ) : '',
                isset( $_POST['billing_last_name'] )  ? sanitize_text_field( wp_unslash( $_POST['billing_last_name'] ) )  : '',
            ) ) ) ),
            'billing_country'         => isset( $_POST['billing_country'] )    ? sanitize_text_field( wp_unslash( $_POST['billing_country'] ) ) : '',
            'ip_address'              => $this->get_ip(),
            'checkout_time'           => isset( $_POST['_dpshield_ts'] )       ? absint( $_POST['_dpshield_ts'] ) : 0,
            'honeypot'                => isset( $_POST['_dpshield_hp'] )       ? sanitize_text_field( wp_unslash( $_POST['_dpshield_hp'] ) )   : '',
            'failed_payment_attempts' => $this->get_failed_attempt_count( $this->get_ip() ),
        );
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    // ──────────────────────────────────────────────────────────
    //  Pre-Checks (instant action)
    // ──────────────────────────────────────────────────────────

    private function run_pre_checks( $data, &$reasons ) {
        // Honeypot.
        if ( ! empty( $data['honeypot'] ) ) {
            $reasons[] = 'honeypot';
            return 'BLOCK';
        }

        // Blacklist.
        foreach ( $this->get_blacklist_items() as $item ) {
            if ( empty( $item ) ) {
                continue;
            }
            $haystack = strtolower( $data['billing_name'] . ' ' . $data['phone'] . ' ' . $data['email'] );
            if ( stripos( $haystack, strtolower( $item ) ) !== false ) {
                $reasons[] = 'blacklisted';
                return 'BLOCK';
            }
        }

        return 'CONTINUE';
    }

    // ──────────────────────────────────────────────────────────
    //  Risk Scoring Rules
    // ──────────────────────────────────────────────────────────

    private function score_disposable_email( $data, &$reasons ) {
        if ( empty( $data['email'] ) ) {
            return 0;
        }
        $pos = strrpos( $data['email'], '@' );
        if ( false === $pos ) {
            return 0;
        }
        $domain = strtolower( substr( $data['email'], $pos + 1 ) );
        if ( in_array( $domain, $this->get_disposable_domains(), true ) ) {
            $reasons[] = 'disposable_email';
            return 40;
        }
        return 0;
    }

    private function score_ip_velocity( $data, &$reasons ) {
        $max   = (int) $this->cfg['max_orders_per_ip'];
        $count = $this->count_recent_orders_by_ip( $data['ip_address'], HOUR_IN_SECONDS );
        if ( $count >= $max ) {
            $reasons[] = 'ip_velocity';
            return 30;
        }
        return 0;
    }

    private function score_repeated_data( $data, &$reasons ) {
        $scored = false;
        if ( ! empty( $data['phone'] ) && $this->field_seen_before( '_billing_phone', $data['phone'] ) ) {
            $scored = true;
        }
        if ( ! empty( $data['email'] ) && $this->field_seen_before( '_billing_email', $data['email'] ) ) {
            $scored = true;
        }
        if ( $scored ) {
            $reasons[] = 'repeated_contact';
            return 25;
        }
        return 0;
    }

    private function score_ip_country_mismatch( $data, &$reasons ) {
        if ( empty( $this->cfg['enable_ip_country_check'] ) || empty( $data['billing_country'] ) ) {
            return 0;
        }
        $ip_country = $this->get_ip_country( $data['ip_address'] );
        if ( $ip_country && strtoupper( $ip_country ) !== strtoupper( $data['billing_country'] ) ) {
            $reasons[] = 'ip_country_mismatch';
            return 20;
        }
        return 0;
    }

    private function score_failed_payments( $data, &$reasons ) {
        $threshold = (int) $this->cfg['failed_payment_threshold'];
        if ( $threshold > 0 && $data['failed_payment_attempts'] >= $threshold ) {
            $reasons[] = 'excessive_failed_payments';
            return 25;
        }
        return 0;
    }

    private function score_checkout_speed( $data, &$reasons ) {
        $threshold = (int) $this->cfg['checkout_time_threshold'];
        if ( empty( $data['checkout_time'] ) || $threshold <= 0 ) {
            return 0;
        }
        $elapsed = time() - $data['checkout_time'];
        if ( $elapsed >= 0 && $elapsed < $threshold ) {
            $reasons[] = 'checkout_too_fast';
            return 20;
        }
        return 0;
    }

    // ──────────────────────────────────────────────────────────
    //  Actions
    // ──────────────────────────────────────────────────────────

    private function execute_block( $data, $score, $reasons ) {
        $this->logger->log( array(
            'order_id'        => 0,
            'ip_address'      => $data['ip_address'],
            'email'           => $data['email'],
            'risk_score'      => $score,
            'triggered_rules' => $reasons,
            'final_action'    => 'BLOCK',
        ) );

        wc_add_notice(
            __( 'Unable to process your order. Please contact support.', 'dropproduct' ),
            'error'
        );
    }

    // ──────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────

    /**
     * Get the real visitor IP, respecting common proxy headers.
     */
    private function get_ip() {
        $keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' );
        foreach ( $keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
                $ip  = trim( $ips[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    /**
     * Resolve IP to ISO country code using WC_Geolocation (local db, no API).
     * Falls back to the Cloudflare header when WC is unavailable.
     */
    private function get_ip_country( $ip ) {
        if ( class_exists( 'WC_Geolocation' ) ) {
            $geo = WC_Geolocation::geolocate_ip( $ip, false, false );
            if ( ! empty( $geo['country'] ) ) {
                return strtoupper( $geo['country'] );
            }
        }
        if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
            return strtoupper( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) );
        }
        return '';
    }

    private function get_failed_attempt_count( $ip ) {
        return (int) get_transient( self::FAILED_PREFIX . md5( $ip ) );
    }

    /**
     * Count WC orders placed from an IP within the given time window.
     */
    private function count_recent_orders_by_ip( $ip, $period_seconds ) {
        global $wpdb;

        $since = gmdate( 'Y-m-d H:i:s', time() - (int) $period_seconds );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(p.ID)
               FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
              WHERE p.post_type     = 'shop_order'
                AND p.post_date_gmt >= %s
                AND pm.meta_key    = '_customer_ip_address'
                AND pm.meta_value  = %s",
            $since,
            $ip
        ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        return (int) $count;
    }

    /**
     * Check whether a meta field value has appeared in ≥2 past orders.
     */
    private function field_seen_before( $meta_key, $value ) {
        if ( empty( $value ) ) {
            return false;
        }
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
            $meta_key,
            $value
        ) );

        return $count >= 2;
    }

    private function get_disposable_domains() {
        $raw = isset( $this->cfg['disposable_domains'] ) ? $this->cfg['disposable_domains'] : '';
        return array_values( array_filter( array_map( 'trim', explode( "\n", strtolower( $raw ) ) ) ) );
    }

    private function get_blacklist_items() {
        $raw = isset( $this->cfg['blacklist'] ) ? $this->cfg['blacklist'] : '';
        return array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );
    }

    /**
     * Human-readable label for a rule identifier.
     */
    private function rule_label( $rule ) {
        $labels = array(
            'honeypot'                  => 'Honeypot triggered',
            'blacklisted'               => 'Blacklisted contact',
            'disposable_email'          => 'Disposable email',
            'ip_velocity'               => 'IP velocity exceeded',
            'repeated_contact'          => 'Repeated email/phone',
            'ip_country_mismatch'       => 'IP/country mismatch',
            'excessive_failed_payments' => 'Failed payments exceeded',
            'checkout_too_fast'         => 'Checkout too fast',
            'card_testing_block'        => 'Card testing detected',
        );
        return isset( $labels[ $rule ] ) ? $labels[ $rule ] : $rule;
    }

    private function default_disposable_domains() {
        return array(
            'mailinator.com', 'yopmail.com', 'guerrillamail.com', 'trashmail.com',
            'fakeinbox.com', 'throwam.com', 'spam4.me', 'maildrop.cc',
            'temp-mail.org', 'guerrillamailblock.com', 'grr.la', 'sharklasers.com',
            'mailnesia.com', 'dispostable.com', 'tempr.email', 'tempinbox.com',
            'tempmail.com', 'temporary-mail.net', 'mailnull.com', 'spamgourmet.com',
        );
    }

    // ──────────────────────────────────────────────────────────
    //  AJAX Handlers (admin only)
    // ──────────────────────────────────────────────────────────

    public function ajax_save_settings() {
        check_ajax_referer( 'dpshield_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dropproduct' ) ) );
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $settings = array(
            'enabled'                   => ! empty( $_POST['enabled'] ),
            'block_threshold'           => min( 200, max( 0, absint( $_POST['block_threshold'] ?? 70 ) ) ),
            'review_threshold'          => min( 200, max( 0, absint( $_POST['review_threshold'] ?? 40 ) ) ),
            'action_mode'               => in_array( $_POST['action_mode'] ?? '', array( 'block', 'hold' ), true ) ? sanitize_text_field( wp_unslash( $_POST['action_mode'] ) ) : 'block',
            'max_orders_per_ip'         => max( 1, absint( $_POST['max_orders_per_ip'] ?? 3 ) ),
            'failed_payment_threshold'  => max( 1, absint( $_POST['failed_payment_threshold'] ?? 5 ) ),
            'checkout_time_threshold'   => max( 0, absint( $_POST['checkout_time_threshold'] ?? 5 ) ),
            'enable_ip_country_check'   => ! empty( $_POST['enable_ip_country_check'] ),
            'enable_cod_restriction'    => ! empty( $_POST['enable_cod_restriction'] ),
            'cod_restriction_threshold' => max( 0, absint( $_POST['cod_restriction_threshold'] ?? 40 ) ),
            'disposable_domains'        => sanitize_textarea_field( wp_unslash( $_POST['disposable_domains'] ?? '' ) ),
            'blacklist'                 => sanitize_textarea_field( wp_unslash( $_POST['blacklist'] ?? '' ) ),
        );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        update_option( self::OPTION_KEY, $settings );
        $this->cfg = $settings;

        wp_send_json_success( array( 'message' => __( 'Settings saved!', 'dropproduct' ) ) );
    }

    public function ajax_delete_log() {
        check_ajax_referer( 'dpshield_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dropproduct' ) ) );
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $id = absint( $_POST['log_id'] ?? 0 );
        if ( $id ) {
            $this->logger->delete_log( $id );
        }
        wp_send_json_success();
    }

    public function ajax_clear_logs() {
        check_ajax_referer( 'dpshield_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dropproduct' ) ) );
        }
        $this->logger->clear_logs();
        wp_send_json_success( array( 'message' => __( 'All logs cleared.', 'dropproduct' ) ) );
    }
}
