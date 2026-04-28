<?php

/**
 * Admin page handler.
 *
 * Registers a top-level admin menu page and a Settings sub-menu,
 * enqueues assets conditionally, and renders the admin page templates.
 *
 * @package DropProduct
 * @since   1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class DropProduct_Admin
 *
 * @since 1.0.0
 */
class DropProduct_Admin
{

    /**
     * Hook suffix for the main DropProduct page.
     *
     * @var string
     */
    private $hook_suffix = '';

    /**
     * Hook suffix for the Settings page.
     *
     * @var string
     */
    private $settings_hook_suffix = '';

    /**
     * Hook suffix for the Order Shield page.
     *
     * @var string
     */
    private $fraud_shield_hook_suffix = '';

    /**
     * Hook suffix for the Dashboard page.
     *
     * @var string
     */
    private $dashboard_hook_suffix = '';

    /**
     * Hook suffix for the Analytics page.
     *
     * @var string
     */
    private $analytics_hook_suffix = '';

    /**
     * Register a top-level admin menu page and a Settings sub-menu.
     *
     * @since 1.0.0
     */
    public function add_menu_page()
    {
        $this->hook_suffix = add_menu_page(
            __('DropProduct', 'dropproduct'),
            __('DropProduct', 'dropproduct'),
            'manage_woocommerce',
            'dropproduct',
            array($this, 'render_page'),
            'dashicons-upload',
            56
        );

        // "DropProduct" submenu item that duplicates the top-level page.
        add_submenu_page(
            'dropproduct',
            __('DropProduct', 'dropproduct'),
            __('Upload', 'dropproduct'),
            'manage_woocommerce',
            'dropproduct',
            array($this, 'render_page')
        );

        // Settings sub-menu.
        $this->settings_hook_suffix = add_submenu_page(
            'dropproduct',
            __('DropProduct Settings', 'dropproduct'),
            __('Settings', 'dropproduct'),
            'manage_woocommerce',
            'dropproduct-settings',
            array($this, 'render_settings_page')
        );

        // Order Shield sub-menu.
        $this->fraud_shield_hook_suffix = add_submenu_page(
            'dropproduct',
            __('Ultimate Order Shield', 'dropproduct'),
            '🛡️ ' . __('Order Shield', 'dropproduct'),
            'manage_woocommerce',
            'dropproduct-fraud-shield',
            array($this, 'render_fraud_shield_page')
        );

        // Dashboard sub-menu.
        $this->dashboard_hook_suffix = add_submenu_page(
            'dropproduct',
            __('DropProduct Dashboard', 'dropproduct'),
            '📊 ' . __('Dashboard', 'dropproduct'),
            'manage_woocommerce',
            'dropproduct-dashboard',
            array($this, 'render_dashboard_page')
        );

        // Sales Analytics sub-menu.
        $this->analytics_hook_suffix = add_submenu_page(
            'dropproduct',
            __('Sales Analytics', 'dropproduct'),
            '📈 ' . __('Sales Analytics', 'dropproduct'),
            'manage_woocommerce',
            'dropproduct-analytics',
            array($this, 'render_analytics_page')
        );
    }

    /**
     * Enqueue admin styles — only on DropProduct pages.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public function enqueue_styles($hook_suffix)
    {
        if (! $this->is_dropproduct_page($hook_suffix)) {
            return;
        }

        wp_enqueue_style(
            'dropproduct-admin',
            DROPPRODUCT_PLUGIN_URL . 'assets/css/admin-dropproduct.css',
            array(),
            DROPPRODUCT_VERSION
        );
    }

    /**
     * Enqueue admin scripts — only on DropProduct pages.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public function enqueue_scripts($hook_suffix)
    {
        if (! $this->is_dropproduct_page($hook_suffix)) {
            return;
        }

        // Settings page only needs the settings script.
        if ($hook_suffix === $this->settings_hook_suffix) {
            wp_enqueue_script(
                'dropproduct-settings',
                DROPPRODUCT_PLUGIN_URL . 'assets/js/admin-dropproduct-settings.js',
                array('jquery'),
                DROPPRODUCT_VERSION,
                true
            );

            wp_localize_script('dropproduct-settings', 'dropProductSettings', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('dropproduct_settings_nonce'),
                'i18n'    => array(
                    'saving'       => __('Saving…', 'dropproduct'),
                    'saved'        => __('Settings saved!', 'dropproduct'),
                    'networkError' => __('Network error. Please try again.', 'dropproduct'),
                ),
            ));

            return; // Don't load the main grid script on the settings page.
        }

        // Fraud Shield page assets.
        if ($hook_suffix === $this->fraud_shield_hook_suffix) {
            wp_enqueue_style(
                'dropproduct-fraud-shield',
                DROPPRODUCT_PLUGIN_URL . 'assets/css/admin-fraud-shield.css',
                array(),
                DROPPRODUCT_VERSION
            );

            wp_enqueue_script(
                'dropproduct-fraud-shield',
                DROPPRODUCT_PLUGIN_URL . 'assets/js/admin-fraud-shield.js',
                array('jquery'),
                DROPPRODUCT_VERSION,
                true
            );

            wp_localize_script('dropproduct-fraud-shield', 'dpShield', array(
                'ajaxUrl'       => admin_url('admin-ajax.php'),
                'nonce'         => wp_create_nonce('dpshield_admin'),
                'saving'        => __('Saving…', 'dropproduct'),
                'saved'         => __('Saved!', 'dropproduct'),
                'saveBtnLabel'  => __('Save Settings', 'dropproduct'),
                'networkError'  => __('Network error. Please try again.', 'dropproduct'),
                'confirmDelete' => __('Delete this log entry?', 'dropproduct'),
                'confirmClear'  => __('Clear ALL log entries? This cannot be undone.', 'dropproduct'),
                'logsCleared'   => __('All logs cleared.', 'dropproduct'),
            ));

            return;
        }

        // Dashboard page assets.
        if ($hook_suffix === $this->dashboard_hook_suffix) {
            wp_enqueue_style(
                'dropproduct-dashboard',
                DROPPRODUCT_PLUGIN_URL . 'assets/css/admin-dashboard.css',
                array(),
                DROPPRODUCT_VERSION
            );

            wp_enqueue_script(
                'dropproduct-dashboard',
                DROPPRODUCT_PLUGIN_URL . 'assets/js/admin-dashboard.js',
                array('jquery'),
                DROPPRODUCT_VERSION,
                true
            );

            wp_localize_script('dropproduct-dashboard', 'dpDashboard', array(
                'ajaxUrl'         => admin_url('admin-ajax.php'),
                'nonce'           => wp_create_nonce('dropproduct_dashboard'),
                'currency_symbol' => get_woocommerce_currency_symbol(),
                'strings'         => array(
                    'updated_at'  => __('Updated at', 'dropproduct'),
                    'blocked'     => __('Blocked', 'dropproduct'),
                    'on_hold'     => __('On Hold', 'dropproduct'),
                    'allowed'     => __('Allowed', 'dropproduct'),
                    'no_threats'  => __('No recent threats — looking good!', 'dropproduct'),
                    'no_orders'   => __('No pending or processing orders right now.', 'dropproduct'),
                    'customer'    => __('Customer', 'dropproduct'),
                    'status'      => __('Status', 'dropproduct'),
                    'total'       => __('Total', 'dropproduct'),
                    'waiting'     => __('Waiting', 'dropproduct'),
                    'all_good'    => __('All clear — no alerts here!', 'dropproduct'),
                    'left'        => __('left', 'dropproduct'),
                    'out_of_stock'=> __('Out of Stock', 'dropproduct'),
                    'incomplete'  => __('Incomplete', 'dropproduct'),
                    'load_error'  => __('Failed to load data. Try refreshing.', 'dropproduct'),
                ),
            ));

            return;
        }

        // Analytics page assets.
        if ($hook_suffix === $this->analytics_hook_suffix) {
            // Enqueue Chart.js library.
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
                array(),
                '3.9.1',
                true
            );

            wp_enqueue_style(
                'dropproduct-analytics',
                DROPPRODUCT_PLUGIN_URL . 'assets/css/admin-analytics.css',
                array(),
                DROPPRODUCT_VERSION
            );

            wp_enqueue_script(
                'dropproduct-analytics',
                DROPPRODUCT_PLUGIN_URL . 'assets/js/admin-analytics.js',
                array('jquery', 'chart-js'),
                DROPPRODUCT_VERSION,
                true
            );

            wp_localize_script('dropproduct-analytics', 'dropproductAnalyticsData', array(
                'nonce'           => wp_create_nonce('dropproduct_analytics_nonce'),
                'currency_symbol' => get_woocommerce_currency_symbol(),
            ));

            return;
        }

        // Main grid page assets.
        wp_enqueue_media();

        wp_enqueue_script(
            'dropproduct-admin',
            DROPPRODUCT_PLUGIN_URL . 'assets/js/admin-dropproduct.js',
            array('jquery'),
            DROPPRODUCT_VERSION,
            true
        );

        // Fetch product categories for the inline dropdown.
        $categories = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'fields'     => 'id=>name',
        ));

        if (is_wp_error($categories)) {
            $categories = array();
        }

        $localize_data = array(
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('dropproduct_nonce'),
            'categories'   => $categories,
            'isProActive'  => defined('DROPPRODUCT_PRO_VERSION'),
            'i18n'         => array(
                'dropzone'        => __('Drag & drop product images here, or click to browse', 'dropproduct'),
                'uploading'       => __('Uploading…', 'dropproduct'),
                'saving'          => __('Saving…', 'dropproduct'),
                'saved'           => __('Saved', 'dropproduct'),
                'publishing'      => __('Publishing…', 'dropproduct'),
                'published'       => __('Published!', 'dropproduct'),
                'publishAll'      => __('Publish All', 'dropproduct'),
                'deleteConfirm'   => __('Delete this product?', 'dropproduct'),
                'noProducts'      => __('No draft products yet. Upload images to get started.', 'dropproduct'),
                'titleRequired'   => __('Title is required', 'dropproduct'),
                'priceRequired'   => __('Price is required', 'dropproduct'),
                'validationError' => __('Fix highlighted errors before publishing.', 'dropproduct'),
                'uploadError'     => __('Upload failed. Please try again.', 'dropproduct'),
                'networkError'    => __('Network error. Please try again.', 'dropproduct'),
            ),
        );

        /**
         * Filter localized script data before output.
         *
         * Allows Pro and add-ons to inject additional configuration.
         *
         * @since 1.1.0
         * @param array $localize_data Script data array.
         */
        $localize_data = apply_filters('dropproduct_localize_data', $localize_data);

        wp_localize_script('dropproduct-admin', 'dropProduct', $localize_data);

        wp_add_inline_script('dropproduct-admin', 'document.body.classList.add("dropproduct-page");', 'before');
    }

    /**
     * Render the main admin page.
     */
    public function render_page()
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'dropproduct'));
        }

        include DROPPRODUCT_PLUGIN_DIR . 'admin/views/dropproduct-page.php';
    }

    /**
     * Render the settings admin page.
     *
     * @since 1.0.1
     */
    public function render_settings_page()
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'dropproduct'));
        }

        include DROPPRODUCT_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    /**
     * Render the Order Shield admin page.
     *
     * @since 1.0.2
     */
    public function render_fraud_shield_page()
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'dropproduct'));
        }

        include DROPPRODUCT_PLUGIN_DIR . 'admin/views/fraud-shield-page.php';
    }

    /**
     * Render the Dashboard admin page.
     *
     * @since 1.0.3
     */
    public function render_dashboard_page()
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'dropproduct'));
        }

        include DROPPRODUCT_PLUGIN_DIR . 'admin/views/dashboard-page.php';
    }

    /**
     * Render the Sales Analytics admin page.
     *
    * @since 1.1.0
     */
    public function render_analytics_page()
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'dropproduct'));
        }

        include DROPPRODUCT_PLUGIN_DIR . 'admin/views/analytics-page.php';
    }

    // ──────────────────────────────────────────
    //  Utility
    // ──────────────────────────────────────────

    /**
     * Check if the current admin page is a DropProduct page.
     *
     * @param string $hook_suffix Current admin hook suffix.
     * @return bool
     */
    private function is_dropproduct_page($hook_suffix)
    {
        return in_array($hook_suffix, array(
            $this->hook_suffix,
            $this->settings_hook_suffix,
            $this->fraud_shield_hook_suffix,
            $this->dashboard_hook_suffix,
            $this->analytics_hook_suffix,
        ), true);
    }
}
