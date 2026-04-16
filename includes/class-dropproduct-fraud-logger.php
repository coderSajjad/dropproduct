<?php
/**
 * Ultimate Order Shield — Fraud Logger
 *
 * Creates a custom {prefix}dropproduct_fraud_log table and exposes
 * a clean API for inserting, querying, and clearing log entries.
 *
 * @package DropProduct
 * @since   1.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class DropProduct_Fraud_Logger
 */
class DropProduct_Fraud_Logger {

    const TABLE_SUFFIX      = 'dropproduct_fraud_log';
    const DB_VERSION_OPTION = 'dropproduct_fraud_db_version';
    const DB_VERSION        = '1.0';

    // ──────────────────────────────────────────────────────────
    //  Table Management
    // ──────────────────────────────────────────────────────────

    /**
     * Create the log table (idempotent — only runs when DB version changes).
     */
    public static function create_table() {
        global $wpdb;

        if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
            return;
        }

        $table_name      = $wpdb->prefix . self::TABLE_SUFFIX;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id           bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id     bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            ip_address   varchar(100)        NOT NULL DEFAULT '',
            email        varchar(200)        NOT NULL DEFAULT '',
            risk_score   smallint(6)         NOT NULL DEFAULT 0,
            triggered_rules longtext         NOT NULL,
            final_action varchar(20)         NOT NULL DEFAULT '',
            created_at   datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY ip_idx   (ip_address(40)),
            KEY action_idx (final_action)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    // ──────────────────────────────────────────────────────────
    //  Write
    // ──────────────────────────────────────────────────────────

    /**
     * Insert a fraud check record.
     *
     * @param array $entry {
     *     @type int    $order_id        WC order ID (0 if blocked before creation).
     *     @type string $ip_address      Customer IP.
     *     @type string $email           Customer email.
     *     @type int    $risk_score      Computed risk score.
     *     @type string $triggered_rules JSON-encoded array of rule identifiers.
     *     @type string $final_action    BLOCK | ON_HOLD | ALLOW.
     * }
     */
    public function log( array $entry ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $wpdb->prefix . self::TABLE_SUFFIX,
            array(
                'order_id'        => (int) $entry['order_id'],
                'ip_address'      => sanitize_text_field( $entry['ip_address'] ),
                'email'           => sanitize_email( $entry['email'] ),
                'risk_score'      => (int) $entry['risk_score'],
                'triggered_rules' => wp_json_encode( isset( $entry['triggered_rules'] ) && is_array( $entry['triggered_rules'] )
                    ? $entry['triggered_rules']
                    : json_decode( $entry['triggered_rules'], true ) ?? []
                ),
                'final_action'    => sanitize_text_field( $entry['final_action'] ),
                'created_at'      => current_time( 'mysql', true ),
            ),
            array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
        );
    }

    // ──────────────────────────────────────────────────────────
    //  Read
    // ──────────────────────────────────────────────────────────

    /**
     * Get paginated log entries.
     *
     * @param int    $limit         Rows to return.
     * @param int    $offset        Rows to skip.
     * @param string $action_filter Filter by final_action ('', 'BLOCK', 'ON_HOLD', 'ALLOW').
     * @return array { rows: [], total: int }
     */
    public function get_logs( $limit = 50, $offset = 0, $action_filter = '' ) {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_SUFFIX;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( $action_filter ) {
            $rows  = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE final_action = %s ORDER BY id DESC LIMIT %d OFFSET %d",
                $action_filter, (int) $limit, (int) $offset
            ) );
            $total = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE final_action = %s",
                $action_filter
            ) );
        } else {
            $rows  = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM `{$table}` ORDER BY id DESC LIMIT %d OFFSET %d",
                (int) $limit, (int) $offset
            ) );
            $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        return compact( 'rows', 'total' );
    }

    /**
     * Get summary counts grouped by final_action.
     *
     * @return array { BLOCK: int, ON_HOLD: int, ALLOW: int, total: int }
     */
    public function get_summary() {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_SUFFIX;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT final_action, COUNT(*) as cnt FROM `{$table}` GROUP BY final_action"
        );

        $summary = array( 'BLOCK' => 0, 'ON_HOLD' => 0, 'ALLOW' => 0, 'total' => 0 );

        foreach ( $rows as $row ) {
            if ( isset( $summary[ $row->final_action ] ) ) {
                $summary[ $row->final_action ] = (int) $row->cnt;
            }
            $summary['total'] += (int) $row->cnt;
        }

        return $summary;
    }

    // ──────────────────────────────────────────────────────────
    //  Delete
    // ──────────────────────────────────────────────────────────

    /**
     * Delete a single log entry by ID.
     *
     * @param int $id Row ID.
     */
    public function delete_log( $id ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->delete(
            $wpdb->prefix . self::TABLE_SUFFIX,
            array( 'id' => (int) $id ),
            array( '%d' )
        );
    }

    /**
     * Truncate the entire log table.
     */
    public function clear_logs() {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query( "TRUNCATE TABLE `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }
}
