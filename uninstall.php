<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * Removes every artefact DropProduct creates: options, custom tables,
 * transients, and the post meta written onto products.
 *
 * Product posts themselves are deliberately left alone — deleting a store's
 * catalogue on plugin removal would be destructive and unexpected.
 *
 * @package DropProduct
 * @since   1.0.0
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

/**
 * Delete all DropProduct data for the current site.
 *
 * @since 1.2.0
 */
function dropproduct_uninstall_site() {
    global $wpdb;

    // ── Options ───────────────────────────────────────────
    $options = array(
        'dropproduct_settings',
        'dropproduct_fraud_shield',
        'dropproduct_fraud_db_version',
        'dropproduct_activity_db_version',
        'dropproduct_wc_notice_snoozed_until',
    );

    foreach ($options as $option) {
        delete_option($option);
    }

    // ── Transients ────────────────────────────────────────
    $transients = array(
        'dpd_financials',
        'dpd_security',
        'dpd_orders',
        'dpd_inventory',
        'dpd_readiness',
        'dropproduct_product_ids',
        'dropproduct_lookup_ready',
        'dropproduct_just_activated',
    );

    foreach ($transients as $transient) {
        delete_transient($transient);
    }

    // Order Shield failed-payment counters are keyed by an IP hash, so they
    // have to be matched by prefix rather than listed.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_dpshield_fp_') . '%',
            $wpdb->esc_like('_transient_timeout_dpshield_fp_') . '%'
        )
    );

    // ── Post meta ─────────────────────────────────────────
    $meta_keys = array(
        '_dropproduct_product',
        '_dropproduct_session_id',
        '_dropproduct_cost_price',
    );

    foreach ($meta_keys as $meta_key) {
        $wpdb->delete($wpdb->postmeta, array('meta_key' => $meta_key), array('%s'));
    }

    // ── Custom tables ─────────────────────────────────────
    $tables = array(
        $wpdb->prefix . 'dropproduct_fraud_log',
        $wpdb->prefix . 'dropproduct_activity_log',
    );

    foreach ($tables as $table) {
        // Table names cannot be parameterised; both values are built from the
        // trusted $wpdb->prefix plus a hardcoded suffix.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

if (is_multisite()) {
    $site_ids = get_sites(array(
        'fields' => 'ids',
        'number' => 0,
    ));

    foreach ($site_ids as $site_id) {
        switch_to_blog($site_id);
        dropproduct_uninstall_site();
        restore_current_blog();
    }
} else {
    dropproduct_uninstall_site();
}
