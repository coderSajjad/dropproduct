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
     * Execute the loader to register all hooks with WordPress.
     */
    public function run()
    {
        $this->loader->run();
    }
}
