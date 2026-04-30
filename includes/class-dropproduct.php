<?php

/**
 * Core plugin orchestrator.
 *
 * Loads dependencies, creates service instances, and registers
 * all hooks via the loader.
 *
 * @package DropProduct
 * @since   1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class DropProduct
 *
 * @since 1.0.0
 */
class DropProduct
{

    /**
     * Hook loader.
     *
     * @var DropProduct_Loader
     */
    private $loader;

    /**
     * Constructor — loads dependencies and defines hooks.
     */
    public function __construct()
    {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_ajax_hooks();
        $this->define_fraud_shield_hooks();
        $this->define_dashboard_hooks();
        $this->define_analytics_hooks();
        $this->define_activity_hooks();
    }

    /**
     * Require all class files.
     */
    private function load_dependencies()
    {
        $dir = DROPPRODUCT_PLUGIN_DIR . 'includes/';

        require_once $dir . 'class-dropproduct-loader.php';
        require_once $dir . 'class-dropproduct-admin.php';
        require_once $dir . 'class-dropproduct-ajax.php';
        require_once $dir . 'class-dropproduct-product-service.php';
        require_once $dir . 'class-dropproduct-grouping-engine.php';
        require_once $dir . 'class-dropproduct-settings.php';
        require_once $dir . 'class-dropproduct-fraud-logger.php';
        require_once $dir . 'class-dropproduct-fraud-shield.php';
        require_once $dir . 'class-dropproduct-dashboard-data.php';
        require_once $dir . 'class-dropproduct-dashboard.php';
        require_once $dir . 'class-dropproduct-activity-logger.php';
        require_once $dir . 'class-dropproduct-analytics.php';

        $this->loader = new DropProduct_Loader();
    }

    /**
     * Register admin-side hooks.
     */
    private function define_admin_hooks()
    {
        $admin = new DropProduct_Admin();

        $this->loader->add_action('admin_menu', $admin, 'add_menu_page');
        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_scripts');
        $this->loader->add_action('in_admin_header', $admin, 'suppress_other_notices');
    }

    /**
     * Register AJAX hooks.
     */
    private function define_ajax_hooks()
    {
        $product_service = new DropProduct_Product_Service();
        $grouping_engine = new DropProduct_Grouping_Engine();
        $ajax            = new DropProduct_Ajax($product_service, $grouping_engine);

        // Upload & product AJAX.
        $this->loader->add_action('wp_ajax_dropproduct_upload_images', $ajax, 'handle_upload_images');
        $this->loader->add_action('wp_ajax_dropproduct_upload_single_image', $ajax, 'handle_upload_single_image');
        $this->loader->add_action('wp_ajax_dropproduct_create_products', $ajax, 'handle_create_products');
        $this->loader->add_action('wp_ajax_dropproduct_update_product', $ajax, 'handle_update_product');
        $this->loader->add_action('wp_ajax_dropproduct_publish_all', $ajax, 'handle_publish_all');
        $this->loader->add_action('wp_ajax_dropproduct_publish_single', $ajax, 'handle_publish_single');
        $this->loader->add_action('wp_ajax_dropproduct_bulk_price_adjust', $ajax, 'handle_bulk_price_adjust');
        $this->loader->add_action('wp_ajax_dropproduct_delete_product', $ajax, 'handle_delete_product');
        $this->loader->add_action('wp_ajax_dropproduct_load_products', $ajax, 'handle_load_products');

        // Bulk editing.
        $this->loader->add_action( 'wp_ajax_dropproduct_bulk_update', $ajax, 'handle_bulk_update' );
        $this->loader->add_action( 'wp_ajax_dropproduct_duplicate',   $ajax, 'handle_duplicate_product' );

        // SEO custom field handler (hooked into free plugin's extension point).
        $this->loader->add_action( 'dropproduct_update_custom_field', $ajax, 'handle_custom_field_update', 10, 3 );

        // Settings AJAX.
        $settings = new DropProduct_Settings();
        $this->loader->add_action('wp_ajax_dropproduct_save_settings', $settings, 'handle_save_settings');
    }

    /**
     * Bootstrap the Fraud Shield — creates the DB table (once),
     * instantiates the classes, registers WC hooks, and exposes globals
     * so the admin page view can reference both instances.
     *
     * @since 1.0.2
     */
    private function define_fraud_shield_hooks()
    {
        // Ensure log table exists (idempotent).
        DropProduct_Fraud_Logger::create_table();

        $fraud_logger = new DropProduct_Fraud_Logger();
        $fraud_shield = new DropProduct_Fraud_Shield($fraud_logger);

        // Make instances available to admin page templates.
        $GLOBALS['dropproduct_fraud_logger_instance'] = $fraud_logger;
        $GLOBALS['dropproduct_fraud_shield_instance'] = $fraud_shield;

        // Register front-end WC hooks (only when WC is active).
        add_action('woocommerce_loaded', array($fraud_shield, 'register_hooks'));
    }

    /**
     * Register Dashboard AJAX endpoints.
     *
     * @since 1.0.3
     */
    private function define_dashboard_hooks()
    {
        $dashboard = new DropProduct_Dashboard();

        $this->loader->add_action('wp_ajax_dropproduct_dashboard_financials',  $dashboard, 'handle_financials');
        $this->loader->add_action('wp_ajax_dropproduct_dashboard_security',    $dashboard, 'handle_security');
        $this->loader->add_action('wp_ajax_dropproduct_dashboard_orders',      $dashboard, 'handle_orders');
        $this->loader->add_action('wp_ajax_dropproduct_dashboard_inventory',   $dashboard, 'handle_inventory');
        $this->loader->add_action('wp_ajax_dropproduct_dashboard_readiness',   $dashboard, 'handle_readiness');
        $this->loader->add_action('wp_ajax_dropproduct_dashboard_flush_cache', $dashboard, 'handle_flush_cache');
    }

    /**
     * Register Analytics AJAX endpoints.
     *
    * @since 1.1.0
     */
    private function define_analytics_hooks()
    {
        $analytics = new DropProduct_Analytics();

        $this->loader->add_action('wp_ajax_dropproduct_get_analytics', $this, 'ajax_get_analytics');
    }

    /**
     * AJAX handler for analytics data.
     *
    * @since 1.1.0
     */
    public function ajax_get_analytics()
    {
        check_ajax_referer('dropproduct_analytics_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'dropproduct')), 403);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $range = isset($_POST['range']) ? sanitize_key($_POST['range']) : '30_days';

        $date_ranges = DropProduct_Analytics::get_date_ranges();
        if (!isset($date_ranges[$range])) {
            $range = '30_days';
        }

        $dates = $date_ranges[$range];
        $analytics = new DropProduct_Analytics();
        $data = $analytics->get_analytics_data($dates['start_date'], $dates['end_date']);

        wp_send_json_success($data);
    }

    /**
     * Bootstrap the Activity Logger — create the DB table and register
     * all session/activity AJAX endpoints.
     *
     * @since 1.0.3
     */
    private function define_activity_hooks()
    {
        // Ensure table exists (idempotent via dbDelta).
        DropProduct_Activity_Logger::create_table();

        // Expose the logger globally so AJAX handlers can call it via $GLOBALS.
        $GLOBALS['dropproduct_activity_logger'] = new DropProduct_Activity_Logger();

        // AJAX: get sessions list for the dropdown.
        $this->loader->add_action( 'wp_ajax_dropproduct_get_sessions',      $this, 'ajax_get_sessions' );
        // AJAX: get activity log entries.
        $this->loader->add_action( 'wp_ajax_dropproduct_get_activity_log',  $this, 'ajax_get_activity_log' );
        // AJAX: delete a single activity log entry.
        $this->loader->add_action( 'wp_ajax_dropproduct_delete_activity',   $this, 'ajax_delete_activity' );
        // AJAX: clear all activity log entries.
        $this->loader->add_action( 'wp_ajax_dropproduct_clear_activity',    $this, 'ajax_clear_activity' );
    }

    /* ── Activity AJAX handlers (thin wrappers) ───────── */

    private function verify_ajax() {
        check_ajax_referer( 'dropproduct_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dropproduct' ) ), 403 );
        }
    }

    public function ajax_get_sessions() {
        $this->verify_ajax();
        wp_send_json_success( DropProduct_Activity_Logger::get_sessions() );
    }

    public function ajax_get_activity_log() {
        $this->verify_ajax();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $filter = isset( $_POST['filter'] ) ? sanitize_key( $_POST['filter'] ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $page   = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
        $limit  = 20;
        $offset = ( $page - 1 ) * $limit;
        wp_send_json_success( DropProduct_Activity_Logger::get_logs( $limit, $offset, $filter ) );
    }

    public function ajax_delete_activity() {
        $this->verify_ajax();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $id = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;
        if ( ! $id ) { wp_send_json_error(); }
        DropProduct_Activity_Logger::delete_entry( $id );
        wp_send_json_success();
    }

    public function ajax_clear_activity() {
        $this->verify_ajax();
        DropProduct_Activity_Logger::clear_all();
        wp_send_json_success();
    }

    /**
     * Execute the loader to register all hooks with WordPress.
     */
    public function run()
    {
        $this->loader->run();
    }
}
