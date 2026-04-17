<?php
/**
 * Activity Logger
 *
 * Creates and manages the dropproduct_activity_log table.
 * Logs upload, publish, and delete events for DropProduct products.
 *
 * @package DropProduct
 * @since   1.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DropProduct_Activity_Logger
{
    /** DB table name (without prefix). */
    const TABLE = 'dropproduct_activity_log';

    /** Supported action types. */
    const ACTION_UPLOAD  = 'upload';
    const ACTION_PUBLISH = 'publish';
    const ACTION_DELETE  = 'delete';
    const ACTION_EDIT    = 'edit';

    /**
     * Create the activity log table if it does not exist.
     * Safe to call on every boot (uses dbDelta).
     */
    public static function create_table()
    {
        global $wpdb;

        $table      = $wpdb->prefix . self::TABLE;
        $charset    = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            action_type VARCHAR(20)     NOT NULL DEFAULT '',
            product_id  BIGINT UNSIGNED NOT NULL DEFAULT 0,
            product_name VARCHAR(255)   NOT NULL DEFAULT '',
            session_id   VARCHAR(20)   NOT NULL DEFAULT '',
            user_id      BIGINT UNSIGNED NOT NULL DEFAULT 0,
            details      TEXT           NOT NULL,
            created_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_action  (action_type),
            KEY idx_session (session_id),
            KEY idx_created (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Write a log entry.
     *
     * @param string $action     One of the ACTION_* constants.
     * @param int    $product_id WC product ID.
     * @param string $name       Product title.
     * @param string $session_id The session_id tag on the product.
     * @param string $details    Optional extra context.
     */
    public static function log( $action, $product_id, $name = '', $session_id = '', $details = '' )
    {
        global $wpdb;

        if ( ! $name && $product_id ) {
            $name = get_the_title( $product_id ) ?: '';
        }

        $wpdb->insert(
            $wpdb->prefix . self::TABLE,
            array(
                'action_type'  => sanitize_key( $action ),
                'product_id'   => (int) $product_id,
                'product_name' => sanitize_text_field( $name ),
                'session_id'   => sanitize_text_field( $session_id ),
                'user_id'      => (int) get_current_user_id(),
                'details'      => sanitize_text_field( $details ),
                'created_at'   => current_time( 'mysql' ),
            ),
            array( '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
        );
    }

    /**
     * Retrieve paginated log entries with an optional action filter.
     *
     * @param  int    $limit          Max rows.
     * @param  int    $offset         Row offset.
     * @param  string $action_filter  Filter by action type or '' for all.
     * @return array{ rows: object[], total: int }
     */
    public static function get_logs( $limit = 20, $offset = 0, $action_filter = '' )
    {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;
        $where = $action_filter
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ? $wpdb->prepare( 'WHERE action_type = %s', $action_filter )
            : '';

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
        // phpcs:enable

        return array( 'rows' => (array) $rows, 'total' => $total );
    }

    /**
     * Delete a single log entry.
     *
     * @param int $id Log entry ID.
     * @return bool
     */
    public static function delete_entry( $id )
    {
        global $wpdb;

        return (bool) $wpdb->delete(
            $wpdb->prefix . self::TABLE,
            array( 'id' => (int) $id ),
            array( '%d' )
        );
    }

    /**
     * Truncate the entire log table.
     */
    public static function clear_all()
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}" . self::TABLE );
    }

    /**
     * Return all distinct session IDs (for the session dropdown).
     *
     * @return string[] Array of session_id strings, newest first.
     */
    public static function get_sessions()
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_col(
            "SELECT DISTINCT session_id
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_dropproduct_session_id'
               AND meta_value != ''
             ORDER BY meta_value DESC
             LIMIT 50"
        );
    }
}
