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

    /**
     * Ceiling on orders fetched when measuring IP velocity.
     *
     * Only the count relative to a small threshold matters, so there is no
     * reason to pull an unbounded result set for an abusive IP.
     */
    const MAX_VELOCITY_SCAN = 50;

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
        // Admin AJAX handlers are registered unconditionally.
        //
        // They must stay available even when the shield is switched off,
        // otherwise saving `enabled = 0` would remove the very endpoint
        // needed to switch it back on, permanently locking the settings form.
        $this->register_admin_hooks();

        if ( ! $this->is_enabled() ) {
            return;
        }

        // Inject honeypot + timestamp fields into the checkout form.
        add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'output_hidden_fields' ), 5 );

        // Primary validation — fires before order is created.
        add_action( 'woocommerce_checkout_process', array( $this, 'process_checkout' ) );

        // Post-creation — apply ON_HOLD status when needed.
        //
        // Hooked to `checkout_order_processed` rather than `checkout_create_order`:
        // the latter runs before the order has an ID, so update_status() would be
        // overwritten by the gateway and add_order_note() would be discarded.
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_order_processed' ), 10, 3 );

        // ── Blocks / Store API checkout ────────────────────────
        //
        // The block-based Checkout fires none of the hooks above, so without
        // these the shield was inert on any store using the modern checkout —
        // which is the WooCommerce default.
        add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'process_store_api_checkout' ), 10, 2 );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'on_store_api_order_processed' ), 10, 1 );

        // Track failed payment attempts.
        add_action( 'woocommerce_order_status_failed', array( $this, 'track_failed_payment' ) );

        // Conditionally disable COD.
        add_filter( 'woocommerce_available_payment_gateways', array( $this, 'maybe_disable_cod' ) );
    }

    /**
     * Register the admin-only AJAX endpoints.
     *
     * Split out of register_hooks() so it can run regardless of whether the
     * shield itself is enabled. Guarded against double registration because
     * register_hooks() may fire more than once on unusual boot orders.
     *
     * @since 1.2.0
     */
    private function register_admin_hooks() {
        if ( ! has_action( 'wp_ajax_dropproduct_save_fraud_settings', array( $this, 'ajax_save_settings' ) ) ) {
            add_action( 'wp_ajax_dropproduct_save_fraud_settings', array( $this, 'ajax_save_settings' ) );
            add_action( 'wp_ajax_dropproduct_fraud_delete_log',    array( $this, 'ajax_delete_log'    ) );
            add_action( 'wp_ajax_dropproduct_fraud_clear_logs',    array( $this, 'ajax_clear_logs'    ) );
        }
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
            // Off by default. Only turn this on when the store genuinely sits
            // behind a reverse proxy or CDN, otherwise visitors can spoof their
            // own IP address and bypass every IP-based rule below.
            'trust_proxy_headers'       => false,
            'trusted_proxies'           => '',
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
     * Run every rule and decide what should happen.
     *
     * Storage- and transport-agnostic: it takes a normalised data array and
     * returns a verdict, so the classic checkout and the Store API (Blocks)
     * checkout apply identical rules rather than each reimplementing them.
     *
     * @since 1.2.0
     * @param array $data Normalised checkout data from collect_data().
     * @return array{action: string, score: int, reasons: string[]}
     */
    private function evaluate( array $data ) {
        $reasons = array();

        // ── 1. Instant pre-checks ──────────────────────────────
        if ( 'BLOCK' === $this->run_pre_checks( $data, $reasons ) ) {
            return array(
                'action'  => 'BLOCK',
                'score'   => 999,
                'reasons' => $reasons,
            );
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
        if ( $failed_threshold > 0 && $data['failed_payment_attempts'] >= $failed_threshold ) {
            $reasons[] = 'card_testing_block';
            return array(
                'action'  => 'BLOCK',
                'score'   => $score,
                'reasons' => $reasons,
            );
        }

        // ── 3. Decision ────────────────────────────────────────
        $block_threshold  = (int) $this->cfg['block_threshold'];
        $review_threshold = (int) $this->cfg['review_threshold'];

        if ( $score >= $block_threshold ) {
            $action = ( 'hold' === $this->cfg['action_mode'] ) ? 'ON_HOLD' : 'BLOCK';
        } elseif ( $score >= $review_threshold ) {
            $action = 'ON_HOLD';
        } else {
            $action = 'ALLOW';
        }

        return array(
            'action'  => $action,
            'score'   => $score,
            'reasons' => $reasons,
        );
    }

    /**
     * Main checkout validation for the classic (shortcode) checkout.
     *
     * Runs before the order is created — can add WC error notices to abort.
     */
    public function process_checkout() {
        $data    = $this->collect_data();
        $verdict = $this->evaluate( $data );

        if ( 'BLOCK' === $verdict['action'] ) {
            $this->execute_block( $data, $verdict['score'], $verdict['reasons'] );
            return;
        }

        if ( 'ON_HOLD' === $verdict['action'] ) {
            $this->flag_for_hold( $verdict['score'], $verdict['reasons'] );
            return;
        }

        // ALLOW — log clean pass.
        $this->logger->log( array(
            'order_id'        => 0,
            'ip_address'      => $data['ip_address'],
            'email'           => $data['email'],
            'risk_score'      => $verdict['score'],
            'triggered_rules' => $verdict['reasons'],
            'final_action'    => 'ALLOW',
        ) );
    }

    /**
     * Checkout validation for the Blocks / Store API checkout.
     *
     * The block-based Checkout is the WooCommerce default and does not fire
     * `woocommerce_checkout_process` at all, so earlier drafts of Order Shield
     * ran no checks whatsoever on a modern store while still reporting itself
     * as active. This hook is the Store API equivalent: the order exists and
     * is populated, but has not yet been paid for.
     *
     * Two rules cannot apply here — the honeypot and the checkout-speed timer
     * both need fields injected into the classic form. Every server-side rule
     * (blacklist, disposable email, IP velocity, repeated contact, IP/country
     * mismatch, failed payments, card testing) applies normally.
     *
     * @since 1.2.0
     * @param WC_Order         $order   Draft order built from the request.
     * @param \WP_REST_Request $request The Store API request.
     *
     * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException When the order is blocked.
     */
    public function process_store_api_checkout( $order, $request = null ) {
        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $data    = $this->collect_data( $order );
        $verdict = $this->evaluate( $data );

        if ( 'BLOCK' === $verdict['action'] ) {
            $this->logger->log( array(
                'order_id'        => $order->get_id(),
                'ip_address'      => $data['ip_address'],
                'email'           => $data['email'],
                'risk_score'      => $verdict['score'],
                'triggered_rules' => $verdict['reasons'],
                'final_action'    => 'BLOCK',
            ) );

            throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
                'dropproduct_order_blocked',
                esc_html__( 'Unable to process your order. Please contact support.', 'dropproduct' ),
                403
            );
        }

        if ( 'ON_HOLD' === $verdict['action'] ) {
            $this->flag_for_hold( $verdict['score'], $verdict['reasons'] );
            return;
        }

        $this->logger->log( array(
            'order_id'        => $order->get_id(),
            'ip_address'      => $data['ip_address'],
            'email'           => $data['email'],
            'risk_score'      => $verdict['score'],
            'triggered_rules' => $verdict['reasons'],
            'final_action'    => 'ALLOW',
        ) );
    }

    /**
     * Record that the order about to be created should be held for review.
     *
     * @since 1.2.0
     * @param int      $score   Risk score.
     * @param string[] $reasons Triggered rule identifiers.
     */
    private function flag_for_hold( $score, $reasons ) {
        if ( ! function_exists( 'WC' ) || ! WC()->session ) {
            return;
        }

        WC()->session->set( self::SESSION_HOLD_KEY, array(
            'score'   => (int) $score,
            'reasons' => (array) $reasons,
        ) );
    }

    /**
     * Fires after WooCommerce has created and saved the order.
     *
     * At this point the order has a real ID, so update_status() persists and
     * add_order_note() is stored. Running earlier (on checkout_create_order)
     * meant the status was overwritten by the gateway and notes were dropped.
     *
     * @since 1.2.0 Renamed from on_create_order() and re-hooked.
     *
     * @param int      $order_id Order ID.
     * @param array    $posted   Posted checkout data.
     * @param WC_Order $order    The saved order object.
     */
    public function on_order_processed( $order_id, $posted, $order = null ) {
        if ( ! $order instanceof WC_Order ) {
            $order = wc_get_order( $order_id );
        }

        $this->apply_hold_if_flagged( $order );
    }

    /**
     * Store API equivalent of on_order_processed().
     *
     * @since 1.2.0
     * @param WC_Order $order The placed order.
     */
    public function on_store_api_order_processed( $order ) {
        $this->apply_hold_if_flagged( $order );
    }

    /**
     * Move an order to on-hold when the checkout run flagged it for review.
     *
     * @since 1.2.0
     * @param WC_Order|false|null $order The saved order.
     */
    private function apply_hold_if_flagged( $order ) {
        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $hold = ( function_exists( 'WC' ) && WC()->session ) ? WC()->session->get( self::SESSION_HOLD_KEY ) : null;
        if ( ! $hold ) {
            return;
        }

        WC()->session->__unset( self::SESSION_HOLD_KEY );

        $score   = (int) $hold['score'];
        $reasons = (array) $hold['reasons'];

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

        // Set the hold last, so the notes above are already attached when the
        // status-change email fires. update_status() saves the order itself.
        $order->update_status( 'on-hold', '', true );

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
        if ( empty( $this->cfg['enable_cod_restriction'] ) ) {
            return $gateways;
        }

        // is_checkout() is false during Store API requests, so on a Blocks
        // checkout this used to bail before the restriction could apply.
        $is_store_api = defined( 'REST_REQUEST' ) && REST_REQUEST;

        if ( ! $is_store_api && ! is_checkout() ) {
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

    /**
     * Build the normalised data array the scoring rules operate on.
     *
     * @param WC_Order|null $order When supplied (Store API / Blocks checkout),
     *                             billing details are read from the order
     *                             instead of $_POST. The honeypot and timing
     *                             fields do not exist on that checkout, so they
     *                             come back empty and their rules score zero.
     * @return array
     */
    private function collect_data( $order = null ) {
        $ip = $this->get_ip();

        if ( $order instanceof WC_Order ) {
            return array(
                'email'                   => sanitize_email( $order->get_billing_email() ),
                'phone'                   => sanitize_text_field( $order->get_billing_phone() ),
                'billing_name'            => trim( implode( ' ', array_filter( array(
                    sanitize_text_field( $order->get_billing_first_name() ),
                    sanitize_text_field( $order->get_billing_last_name() ),
                ) ) ) ),
                'billing_country'         => sanitize_text_field( $order->get_billing_country() ),
                // Prefer the IP already recorded on the order; fall back to the
                // request when the order has not captured one.
                'ip_address'              => $order->get_customer_ip_address() ? $order->get_customer_ip_address() : $ip,
                'checkout_time'           => 0,
                'honeypot'                => '',
                'failed_payment_attempts' => $this->get_failed_attempt_count(
                    $order->get_customer_ip_address() ? $order->get_customer_ip_address() : $ip
                ),
            );
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing
        return array(
            'email'                   => isset( $_POST['billing_email'] )      ? sanitize_email( wp_unslash( $_POST['billing_email'] ) )      : '',
            'phone'                   => isset( $_POST['billing_phone'] )      ? sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) )  : '',
            'billing_name'            => trim( implode( ' ', array_filter( array(
                isset( $_POST['billing_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_first_name'] ) ) : '',
                isset( $_POST['billing_last_name'] )  ? sanitize_text_field( wp_unslash( $_POST['billing_last_name'] ) )  : '',
            ) ) ) ),
            'billing_country'         => isset( $_POST['billing_country'] )    ? sanitize_text_field( wp_unslash( $_POST['billing_country'] ) ) : '',
            'ip_address'              => $ip,
            'checkout_time'           => isset( $_POST['_dpshield_ts'] )       ? absint( $_POST['_dpshield_ts'] ) : 0,
            'honeypot'                => isset( $_POST['_dpshield_hp'] )       ? sanitize_text_field( wp_unslash( $_POST['_dpshield_hp'] ) )   : '',
            'failed_payment_attempts' => $this->get_failed_attempt_count( $ip ),
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
        if ( ! empty( $data['phone'] ) && $this->field_seen_before( 'billing_phone', $data['phone'] ) ) {
            $scored = true;
        }
        if ( ! empty( $data['email'] ) && $this->field_seen_before( 'billing_email', $data['email'] ) ) {
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
     * Get the visitor IP.
     *
     * Proxy headers (X-Forwarded-For, Client-IP, CF-Connecting-IP) are supplied
     * by the client and can be set to anything. Trusting them unconditionally —
     * as this method used to — let an attacker send a different Client-IP on
     * every request and walk straight past IP velocity limits, failed-payment
     * counting, and the COD restriction. It also let them inflate someone
     * else's counters.
     *
     * They are therefore only consulted when the site is explicitly configured
     * as sitting behind a reverse proxy, and only when REMOTE_ADDR — which the
     * client cannot forge — matches a trusted proxy address.
     *
     * @return string Validated IP, or '0.0.0.0' when none could be determined.
     */
    private function get_ip() {
        $remote_addr = isset( $_SERVER['REMOTE_ADDR'] )
            ? trim( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) )
            : '';

        if ( ! filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
            $remote_addr = '';
        }

        if ( ! $this->proxy_is_trusted( $remote_addr ) ) {
            return $remote_addr ? $remote_addr : '0.0.0.0';
        }

        // Behind a trusted proxy: the forwarded headers are now meaningful.
        $keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP' );

        foreach ( $keys as $key ) {
            if ( empty( $_SERVER[ $key ] ) ) {
                continue;
            }

            $ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );

            // Left-most entry is the originating client.
            $ip = trim( $ips[0] );

            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }

        return $remote_addr ? $remote_addr : '0.0.0.0';
    }

    /**
     * Whether the connecting address is a reverse proxy we trust.
     *
     * Off by default: a direct-to-origin store must never honour these headers.
     * Enable via the Order Shield settings, or by filtering the proxy list.
     *
     * @param string $remote_addr Validated REMOTE_ADDR.
     * @return bool
     */
    private function proxy_is_trusted( $remote_addr ) {
        if ( '' === $remote_addr ) {
            return false;
        }

        $enabled = ! empty( $this->cfg['trust_proxy_headers'] );

        /**
         * Filter whether forwarded-for headers are honoured for this request.
         *
         * @since 1.2.0
         * @param bool   $enabled     Whether the site is behind a trusted proxy.
         * @param string $remote_addr The connecting address.
         */
        $enabled = (bool) apply_filters( 'dropproduct_trust_proxy_headers', $enabled, $remote_addr );

        if ( ! $enabled ) {
            return false;
        }

        $proxies = $this->get_trusted_proxies();

        // An empty allowlist means "any proxy" — appropriate when the origin is
        // firewalled so that only the CDN can reach it, which is the normal
        // Cloudflare/managed-host setup.
        if ( empty( $proxies ) ) {
            return true;
        }

        foreach ( $proxies as $proxy ) {
            if ( $this->ip_matches( $remote_addr, $proxy ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Trusted proxy addresses / CIDR ranges from settings.
     *
     * @return string[]
     */
    private function get_trusted_proxies() {
        $raw = isset( $this->cfg['trusted_proxies'] ) ? $this->cfg['trusted_proxies'] : '';

        $items = array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) $raw ) ) ) );

        /**
         * Filter the trusted proxy allowlist.
         *
         * @since 1.2.0
         * @param string[] $items IP addresses or CIDR ranges.
         */
        return (array) apply_filters( 'dropproduct_trusted_proxies', $items );
    }

    /**
     * Match an IP against a literal address or a CIDR range.
     *
     * Supports both IPv4 and IPv6.
     *
     * @param string $ip    Address to test.
     * @param string $range Literal address or CIDR notation.
     * @return bool
     */
    private function ip_matches( $ip, $range ) {
        if ( false === strpos( $range, '/' ) ) {
            return $ip === $range;
        }

        list( $subnet, $bits ) = explode( '/', $range, 2 );

        $ip_bin     = @inet_pton( $ip );      // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        $subnet_bin = @inet_pton( $subnet );  // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

        // Both must parse, and both must be the same family (v4 vs v6).
        if ( false === $ip_bin || false === $subnet_bin || strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
            return false;
        }

        $bits = (int) $bits;
        $max  = strlen( $ip_bin ) * 8;

        if ( $bits < 0 || $bits > $max ) {
            return false;
        }

        $whole_bytes    = intdiv( $bits, 8 );
        $remainder_bits = $bits % 8;

        if ( $whole_bytes > 0 && 0 !== substr_compare( $ip_bin, substr( $subnet_bin, 0, $whole_bytes ), 0, $whole_bytes ) ) {
            return false;
        }

        if ( 0 === $remainder_bits ) {
            return true;
        }

        $mask = ~( ( 1 << ( 8 - $remainder_bits ) ) - 1 ) & 0xFF;

        return ( ord( $ip_bin[ $whole_bytes ] ) & $mask ) === ( ord( $subnet_bin[ $whole_bytes ] ) & $mask );
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
     *
     * Uses wc_get_orders() rather than a direct wp_posts/postmeta query. The
     * plugin declares HPOS compatibility, and under HPOS orders live in
     * wp_wc_orders — the old query matched nothing there, so IP velocity
     * scoring silently never fired on any store with HPOS enabled.
     *
     * @param int|string $ip             Customer IP.
     * @param int        $period_seconds Look-back window.
     * @return int
     */
    private function count_recent_orders_by_ip( $ip, $period_seconds ) {
        if ( empty( $ip ) || '0.0.0.0' === $ip ) {
            return 0;
        }

        $since = time() - (int) $period_seconds;

        $orders = wc_get_orders( array(
            'limit'               => self::MAX_VELOCITY_SCAN,
            'type'                => 'shop_order',
            'status'              => 'any',
            'customer_ip_address' => $ip,
            'date_created'        => '>' . $since,
            'return'              => 'ids',
        ) );

        return count( (array) $orders );
    }

    /**
     * Check whether a billing field value has appeared in ≥2 past orders.
     *
     * @param string $field Order field: 'billing_phone' or 'billing_email'.
     * @param string $value Value to look for.
     * @return bool
     */
    private function field_seen_before( $field, $value ) {
        if ( empty( $value ) ) {
            return false;
        }

        // wc_get_orders() routes to the active order data store, so this works
        // under both HPOS and legacy post storage. The previous version read
        // postmeta directly and returned 0 on every HPOS store.
        $orders = wc_get_orders( array(
            'limit'  => 2,
            'type'   => 'shop_order',
            'status' => 'any',
            $field   => $value,
            'return' => 'ids',
        ) );

        return count( (array) $orders ) >= 2;
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
            'trust_proxy_headers'       => ! empty( $_POST['trust_proxy_headers'] ),
            'trusted_proxies'           => sanitize_textarea_field( wp_unslash( $_POST['trusted_proxies'] ?? '' ) ),
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
