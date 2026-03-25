<?php

/**
 * Admin page handler.
 *
 * Registers a top-level admin menu page, enqueues assets
 * conditionally, and renders the admin page template.
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
			__( 'DropProduct', 'dropproduct' ),
			__( 'DropProduct', 'dropproduct' ),
			'manage_woocommerce',
			'dropproduct',
			array( $this, 'render_page' ),
			'dashicons-upload',
			56
		);
	}

    /**
     * Enqueue admin styles — only on the DropProduct page.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public function enqueue_styles($hook_suffix)
    {
        if ($hook_suffix !== $this->hook_suffix) {
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
     * Enqueue admin scripts — only on the DropProduct page.
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
     * Render the admin page.
     */
    public function render_page()
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'dropproduct'));
        }

        include DROPPRODUCT_PLUGIN_DIR . 'admin/views/dropproduct-page.php';
    }
}
