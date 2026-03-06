<?php

/**
 * Admin page handler.
 *
 * Registers a top-level admin menu page, enqueues assets
 * conditionally, and renders the admin page template.
 *
 * @package WooCommerce_Uploady
 * @since   1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_Uploady_Admin
 *
 * @since 1.0.0
 */
class WC_Uploady_Admin
{

    /**
     * Admin page hook suffix.
     *
     * @var string
     */
    private $hook_suffix = '';

    	/**
	 * Register a top-level admin menu page.
	 */
	public function add_menu_page() {
		$this->hook_suffix = add_menu_page(
			__( 'Uploady', 'uploady' ),
			__( 'Uploady', 'uploady' ),
			'manage_woocommerce',
			'wc-uploady',
			array( $this, 'render_page' ),
			'dashicons-upload',
			58
		);
	}

    /**
     * Enqueue admin styles — only on the Uploady page.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public function enqueue_styles($hook_suffix)
    {
        if ($hook_suffix !== $this->hook_suffix) {
            return;
        }

        wp_enqueue_style(
            'wc-uploady-admin',
            WC_UPLOADY_PLUGIN_URL . 'assets/css/admin-uploady.css',
            array(),
            WC_UPLOADY_VERSION
        );
    }

    /**
     * Enqueue admin scripts — only on the Uploady page.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public function enqueue_scripts($hook_suffix)
    {
        if ($hook_suffix !== $this->hook_suffix) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_script(
            'wc-uploady-admin',
            WC_UPLOADY_PLUGIN_URL . 'assets/js/admin-uploady.js',
            array('jquery'),
            WC_UPLOADY_VERSION,
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
            'nonce'        => wp_create_nonce('wc_uploady_nonce'),
            'categories'   => $categories,
            'isProActive'  => defined('WC_UPLOADY_PRO_VERSION'),
            'i18n'         => array(
                'dropzone'        => __('Drag & drop product images here, or click to browse', 'uploady'),
                'uploading'       => __('Uploading…', 'uploady'),
                'saving'          => __('Saving…', 'uploady'),
                'saved'           => __('Saved', 'uploady'),
                'publishing'      => __('Publishing…', 'uploady'),
                'published'       => __('Published!', 'uploady'),
                'publishAll'      => __('Publish All', 'uploady'),
                'deleteConfirm'   => __('Delete this product?', 'uploady'),
                'noProducts'      => __('No draft products yet. Upload images to get started.', 'uploady'),
                'titleRequired'   => __('Title is required', 'uploady'),
                'priceRequired'   => __('Price is required', 'uploady'),
                'validationError' => __('Fix highlighted errors before publishing.', 'uploady'),
                'uploadError'     => __('Upload failed. Please try again.', 'uploady'),
                'networkError'    => __('Network error. Please try again.', 'uploady'),
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
        $localize_data = apply_filters('wc_uploady_localize_data', $localize_data);

        wp_localize_script('wc-uploady-admin', 'wcUploady', $localize_data);
    }

    /**
     * Render the admin page.
     */
    public function render_page()
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'uploady'));
        }

        include WC_UPLOADY_PLUGIN_DIR . 'admin/views/uploady-page.php';
    }
}
