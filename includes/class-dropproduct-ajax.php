<?php

/**
 * AJAX request handlers.
 *
 * Handles all admin AJAX endpoints for image upload, product update,
 * publish, and delete operations.
 *
 * @package DropProduct
 * @since   1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class DropProduct_Ajax
 *
 * @since 1.0.0
 */
class DropProduct_Ajax
{

    /**
     * Product service instance.
     *
     * @var DropProduct_Product_Service
     */
    private $product_service;

    /**
     * Grouping engine instance.
     *
     * @var DropProduct_Grouping_Engine
     */
    private $grouping_engine;

    /**
     * Constructor.
     *
     * @param DropProduct_Product_Service $product_service Product service.
     * @param DropProduct_Grouping_Engine $grouping_engine Grouping engine.
     */
    public function __construct($product_service, $grouping_engine)
    {
        $this->product_service = $product_service;
        $this->grouping_engine = $grouping_engine;
    }

    /**
     * Handle image upload and product creation.
     *
     * Receives uploaded files, attaches them to the media library,
     * groups them by filename, and creates draft products.
     */
    public function handle_upload_images()
    {
        $this->verify_request();

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() above.
        if (empty($_FILES['images'])) {
            wp_send_json_error(array('message' => __('No images uploaded.', 'dropproduct')));
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified above; elements sanitized in normalize_files_array() via sanitize_file_upload().
        $files         = $this->normalize_files_array( $_FILES['images'] );
        $attachment_ids = array();

        foreach ( $files as $file ) {
            // File elements are already sanitized by normalize_files_array().
            $file_array = array(
                'name'     => sanitize_file_name( $file['name'] ),
                'type'     => sanitize_mime_type( $file['type'] ),
                'tmp_name' => $file['tmp_name'],
                'error'    => intval( $file['error'] ),
                'size'     => intval( $file['size'] ),
            );

            // Validate file type.
            $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
            if (! $check['type'] || ! in_array($check['type'], $this->allowed_mime_types(), true)) {
                continue;
            }

            $attachment_id = media_handle_sideload($file_array, 0);

            if (is_wp_error($attachment_id)) {
                continue;
            }

            $attachment_ids[] = $attachment_id;
        }

        if (empty($attachment_ids)) {
            wp_send_json_error(array('message' => __('No valid images were uploaded.', 'dropproduct')));
        }

        // Group images and create products.
        $groups   = $this->grouping_engine->group($attachment_ids);
        $products = array();

        foreach ($groups as $group) {
            $product = $this->product_service->create_draft_product(
                $group['title'],
                $group['featured'],
                $group['gallery']
            );

            if (! is_wp_error($product)) {
                $products[] = $this->product_service->format_product_data($product);
            }
        }

        wp_send_json_success(array('products' => $products));
    }

    /**
     * Handle inline product field update.
     */
    public function handle_update_product()
    {
        $this->verify_request();

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() above.
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() above.
        $field      = isset($_POST['field']) ? sanitize_text_field(wp_unslash($_POST['field'])) : '';

        // Allow HTML for description, sanitize everything else as plain text.
        if ( 'description' === $field ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() above.
            $value = isset($_POST['value']) ? wp_kses_post(wp_unslash($_POST['value'])) : '';
        } else {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() above.
            $value = isset($_POST['value']) ? sanitize_text_field(wp_unslash($_POST['value'])) : '';
        }

        if (! $product_id || ! $field) {
            wp_send_json_error(array('message' => __('Invalid request.', 'dropproduct')));
        }

        $result = $this->product_service->update_product_field($product_id, $field, $value);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Saved.', 'dropproduct')));
    }

    /**
     * Handle publishing all valid draft products.
     */
    public function handle_publish_all()
    {
        $this->verify_request();

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() above.
        $product_ids = isset($_POST['product_ids']) ? array_map('absint', (array) $_POST['product_ids']) : array();

        if (empty($product_ids)) {
            wp_send_json_error(array('message' => __('No products to publish.', 'dropproduct')));
        }

        $published = array();
        $failed    = array();

        foreach ($product_ids as $product_id) {
            $result = $this->product_service->publish_product($product_id);

            if (is_wp_error($result)) {
                $failed[] = array(
                    'id'      => $product_id,
                    'message' => $result->get_error_message(),
                );
            } else {
                $published[] = $product_id;
            }
        }

        wp_send_json_success(array(
            'published' => $published,
            'failed'    => $failed,
        ));
    }

    /**
     * Handle deleting a draft product.
     */
    public function handle_delete_product()
    {
        $this->verify_request();

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() above.
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

        if (! $product_id) {
            wp_send_json_error(array('message' => __('Invalid product ID.', 'dropproduct')));
        }

        $result = $this->product_service->delete_product($product_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Product deleted.', 'dropproduct')));
    }

    /**
     * Handle loading existing draft products.
     */
    public function handle_load_products()
    {
        $this->verify_request();

        $products = $this->product_service->get_draft_products();

        wp_send_json_success(array('products' => $products));
    }

    /**
     * Handle uploading a single image file.
     *
     * Returns the attachment ID so the client can collect IDs
     * and call create_products after all uploads finish.
     */
    public function handle_upload_single_image()
    {
        $this->verify_request();

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() above.
        if (empty($_FILES['image'])) {
            wp_send_json_error(array('message' => __('No image provided.', 'dropproduct')));
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified above; elements sanitized via sanitize_file_upload().
        $file = $this->sanitize_file_upload( $_FILES['image'] );

        // Validate file type.
        $check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
        if ( ! $check['type'] || ! in_array( $check['type'], $this->allowed_mime_types(), true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid file type.', 'dropproduct' ) ) );
        }

        $file_array = array(
            'name'     => sanitize_file_name( $file['name'] ),
            'type'     => sanitize_mime_type( $file['type'] ),
            'tmp_name' => $file['tmp_name'],
            'error'    => intval( $file['error'] ),
            'size'     => intval( $file['size'] ),
        );

        $attachment_id = media_handle_sideload($file_array, 0);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        }

        wp_send_json_success(array(
            'attachment_id' => $attachment_id,
            'filename'      => sanitize_file_name($file['name']),
        ));
    }

    /**
     * Handle grouping and creating products from already-uploaded attachment IDs.
     */
    public function handle_create_products()
    {
        $this->verify_request();

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() above.
        $attachment_ids = isset($_POST['attachment_ids']) ? array_map('absint', (array) $_POST['attachment_ids']) : array();

        if (empty($attachment_ids)) {
            wp_send_json_error(array('message' => __('No images provided.', 'dropproduct')));
        }

        $groups   = $this->grouping_engine->group($attachment_ids);
        $products = array();

        foreach ($groups as $group) {
            $product = $this->product_service->create_draft_product(
                $group['title'],
                $group['featured'],
                $group['gallery']
            );

            if (! is_wp_error($product)) {
                $products[] = $this->product_service->format_product_data($product);
            }
        }

        wp_send_json_success(array('products' => $products));
    }

    /**
     * Verify nonce and capability for AJAX requests.
     */
    private function verify_request()
    {
        if (! check_ajax_referer('dropproduct_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'dropproduct')), 403);
        }

        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'dropproduct')), 403);
        }
    }

    /**
     * Normalize the multiple files array from PHP's messy format.
     *
     * Converts $_FILES['images'] from { name: [...], tmp_name: [...] }
     * to [ { name, tmp_name, ... }, ... ].
     *
     * @param array $files $_FILES array for the field.
     * @return array Normalized array of individual file arrays.
     */
    private function normalize_files_array( $files )
    {
        $normalized = array();

        if ( ! is_array( $files['name'] ) ) {
            return array( $this->sanitize_file_upload( $files ) );
        }

        $count = count( $files['name'] );

        for ( $i = 0; $i < $count; $i++ ) {
            $normalized[] = $this->sanitize_file_upload( array(
                'name'     => $files['name'][ $i ],
                'type'     => $files['type'][ $i ],
                'tmp_name' => $files['tmp_name'][ $i ],
                'error'    => $files['error'][ $i ],
                'size'     => $files['size'][ $i ],
            ) );
        }

        return $normalized;
    }

    /**
     * Sanitize individual elements of a file upload array.
     *
     * @param array $file Single file array from $_FILES.
     * @return array Sanitized file array.
     */
    private function sanitize_file_upload( $file )
    {
        return array(
            'name'     => sanitize_file_name( $file['name'] ),
            'type'     => sanitize_mime_type( $file['type'] ),
            // The tmp_name is a server-generated temporary file path, not user input.
            // It is validated by wp_check_filetype_and_ext() before use.
            'tmp_name' => $file['tmp_name'],
            'error'    => intval( $file['error'] ),
            'size'     => intval( $file['size'] ),
        );
    }

    /**
     * Allowed MIME types for product images.
     *
     * @return array
     */
    private function allowed_mime_types()
    {
        return array(
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        );
    }
}
