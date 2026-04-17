<?php
/**
 * Dashboard AJAX Handler
 *
 * Registers and handles all wp_ajax_* endpoints for the Dashboard widgets.
 * Each endpoint calls the matching DropProduct_Dashboard_Data method and
 * sends a JSON response.
 *
 * @package DropProduct
 * @since   1.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DropProduct_Dashboard
{
    /** Nonce action shared by all dashboard AJAX calls. */
    const NONCE_ACTION = 'dropproduct_dashboard';

    /* ── Auth ─────────────────────────────────────────── */

    private function verify()
    {
        if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'dropproduct' ) ), 403 );
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dropproduct' ) ), 403 );
        }
    }

    private function force(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return ! empty( $_POST['force'] );
    }

    /* ── Endpoints ────────────────────────────────────── */

    public function handle_financials()
    {
        $this->verify();
        wp_send_json_success( DropProduct_Dashboard_Data::get_financials( $this->force() ) );
    }

    public function handle_security()
    {
        $this->verify();
        wp_send_json_success( DropProduct_Dashboard_Data::get_security_stats( $this->force() ) );
    }

    public function handle_orders()
    {
        $this->verify();
        wp_send_json_success( DropProduct_Dashboard_Data::get_urgent_orders( $this->force() ) );
    }

    public function handle_inventory()
    {
        $this->verify();
        wp_send_json_success( DropProduct_Dashboard_Data::get_inventory_alerts( $this->force() ) );
    }

    public function handle_readiness()
    {
        $this->verify();
        wp_send_json_success( DropProduct_Dashboard_Data::get_store_readiness( $this->force() ) );
    }

    /** Flush all dashboard transients and return a success flag. */
    public function handle_flush_cache()
    {
        $this->verify();
        DropProduct_Dashboard_Data::flush_cache();
        wp_send_json_success( array( 'message' => __( 'Cache cleared.', 'dropproduct' ) ) );
    }
}
