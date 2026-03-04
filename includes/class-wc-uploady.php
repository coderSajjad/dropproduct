<?php

/**
 * Core plugin orchestrator.
 *
 * Loads dependencies, creates service instances, and registers
 * all hooks via the loader.
 *
 * @package WooCommerce_Uploady
 * @since   1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_Uploady
 *
 * @since 1.0.0
 */
class WC_Uploady
{

    /**
     * Hook loader.
     *
     * @var WC_Uploady_Loader
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
    }

    /**
     * Require all class files.
     */
    private function load_dependencies()
    {
        $dir = WC_UPLOADY_PLUGIN_DIR . 'includes/';

        require_once $dir . 'class-wc-uploady-loader.php';
        require_once $dir . 'class-wc-uploady-admin.php';
        require_once $dir . 'class-wc-uploady-ajax.php';
        require_once $dir . 'class-wc-uploady-product-service.php';
        require_once $dir . 'class-wc-uploady-grouping-engine.php';

        $this->loader = new WC_Uploady_Loader();
    }

    /**
     * Register admin-side hooks.
     */
    private function define_admin_hooks()
    {
        $admin = new WC_Uploady_Admin();

        $this->loader->add_action('admin_menu', $admin, 'add_menu_page');
        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_scripts');
    }

    /**
     * Register AJAX hooks.
     */
    private function define_ajax_hooks()
    {
        $product_service = new WC_Uploady_Product_Service();
        $grouping_engine = new WC_Uploady_Grouping_Engine();
        $ajax            = new WC_Uploady_Ajax($product_service, $grouping_engine);

        $this->loader->add_action('wp_ajax_wc_uploady_upload_images', $ajax, 'handle_upload_images');
        $this->loader->add_action('wp_ajax_wc_uploady_upload_single_image', $ajax, 'handle_upload_single_image');
        $this->loader->add_action('wp_ajax_wc_uploady_create_products', $ajax, 'handle_create_products');
        $this->loader->add_action('wp_ajax_wc_uploady_update_product', $ajax, 'handle_update_product');
        $this->loader->add_action('wp_ajax_wc_uploady_publish_all', $ajax, 'handle_publish_all');
        $this->loader->add_action('wp_ajax_wc_uploady_delete_product', $ajax, 'handle_delete_product');
        $this->loader->add_action('wp_ajax_wc_uploady_load_products', $ajax, 'handle_load_products');
    }

    /**
     * Execute the loader to register all hooks with WordPress.
     */
    public function run()
    {
        $this->loader->run();
    }
}
