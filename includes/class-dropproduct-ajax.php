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

            // Smart SEO Alt-Text Automator: set alt from filename if enabled and empty.
            $this->maybe_apply_auto_alt_text($attachment_id, $file['name']);

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
     * Handle publishing a single draft product.
     *
     * @since 1.0.1
     */
    public function handle_publish_single()
    {
        $this->verify_request();

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() above.
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

        if (! $product_id) {
            wp_send_json_error(array('message' => __('Invalid product ID.', 'dropproduct')));
        }

        $result = $this->product_service->publish_product($product_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message'    => __('Product published.', 'dropproduct'),
            'product_id' => $product_id,
        ));
    }

    /**
     * Handle bulk price adjustment (Price Slasher).
     *
     * Adjusts regular price, sale price, or both for a set of products
     * by a percentage or fixed amount, either increasing or decreasing.
     *
     * @since 1.0.1
     */
    public function handle_bulk_price_adjust()
    {
        $this->verify_request();

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() above.
        $product_ids = isset($_POST['product_ids']) ? array_map('absint', (array) $_POST['product_ids']) : array();
        $amount      = isset($_POST['amount'])      ? (float) $_POST['amount']                                    : 0;
        $adjust_type = isset($_POST['adjust_type']) ? sanitize_text_field(wp_unslash($_POST['adjust_type']))       : 'percentage';
        $operation   = isset($_POST['operation'])   ? sanitize_text_field(wp_unslash($_POST['operation']))         : 'increase';
        $price_field = isset($_POST['price_field']) ? sanitize_text_field(wp_unslash($_POST['price_field']))       : 'regular_price';
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        // Validate.
        if (empty($product_ids) || $amount <= 0) {
            wp_send_json_error(array('message' => __('Invalid input. Amount must be greater than zero.', 'dropproduct')));
        }

        $allowed_ops    = array('increase', 'decrease');
        $allowed_types  = array('percentage', 'fixed');
        $allowed_fields = array('regular_price', 'sale_price', 'both');

        if (! in_array($operation, $allowed_ops, true)
            || ! in_array($adjust_type, $allowed_types, true)
            || ! in_array($price_field, $allowed_fields, true)
        ) {
            wp_send_json_error(array('message' => __('Invalid operation parameters.', 'dropproduct')));
        }

        $updated = array();

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (! $product) {
                continue;
            }

            // Determine which price fields to adjust.
            $fields_to_adjust = ('both' === $price_field)
                ? array('regular_price', 'sale_price')
                : array($price_field);

            $changed = false;

            foreach ($fields_to_adjust as $field) {
                $getter = 'get_' . $field;
                $setter = 'set_' . $field;

                $current = (float) $product->$getter();

                // Skip empty sale price (nothing to adjust).
                if ('sale_price' === $field && $current <= 0) {
                    continue;
                }

                // Calculate adjustment.
                $adj = ('percentage' === $adjust_type)
                    ? $current * ($amount / 100)
                    : $amount;

                $new_price = ('increase' === $operation)
                    ? $current + $adj
                    : $current - $adj;

                // Clamp — no negative prices.
                $new_price = max(0, round($new_price, 2));

                $product->$setter(wc_format_decimal($new_price));
                $changed = true;
            }

            if (! $changed) {
                continue;
            }

            // Safety: if sale price >= regular price after adjustment, clear sale price.
            $new_regular = (float) $product->get_regular_price();
            $new_sale    = (float) $product->get_sale_price();
            if ($new_sale > 0 && $new_sale >= $new_regular) {
                $product->set_sale_price('');
            }

            $product->save();

            // Return both prices so the JS can refresh both inputs without ambiguity.
            $updated[] = array(
                'id'            => $product_id,
                'regular_price' => (float) $product->get_regular_price(),
                'sale_price'    => (float) $product->get_sale_price(),
            );
        }

        wp_send_json_success(array(
            'updated' => $updated,
            'count'   => count($updated),
        ));
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

        // Smart SEO Alt-Text Automator: set alt from filename if enabled and empty.
        $this->maybe_apply_auto_alt_text($attachment_id, $file['name']);

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

    // ──────────────────────────────────────────
    //  Smart SEO Alt-Text Automator
    // ──────────────────────────────────────────

    /**
     * Apply auto-generated alt text to an attachment if the feature is enabled
     * and the attachment has no existing alt text.
     *
     * Condition: only writes when `_wp_attachment_image_alt` is empty,
     * so manual SEO work is never overwritten.
     *
     * @since 1.0.1
     * @param int    $attachment_id WordPress attachment ID.
     * @param string $filename      Original filename (may include extension).
     */
    private function maybe_apply_auto_alt_text($attachment_id, $filename)
    {
        // Feature gate — respect the settings toggle.
        if (! DropProduct_Settings::is_auto_alt_text_enabled()) {
            return;
        }

        // Only apply when the alt field is blank.
        $existing = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if (! empty($existing)) {
            return;
        }

        $alt = $this->generate_alt_text_from_filename($filename);

        if (! empty($alt)) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
        }
    }

    /**
     * Convert a filename into SEO-friendly Title Case alt text.
     *
     * Steps:
     *  1. Strip the file extension.
     *  2. Replace hyphens and underscores with spaces.
     *  3. Collapse multiple spaces.
     *  4. Convert to Title Case (first letter of each word capitalised).
     *  5. Sanitize for database storage.
     *
     * Example: `blue_denim_jacket_v2.png` → `Blue Denim Jacket V2`
     *
     * @since 1.0.1
     * @param string $filename Raw filename, with or without a path.
     * @return string Generated alt text, ready to store.
     */
    private function generate_alt_text_from_filename($filename)
    {
        // Use only the basename (strip any directory path).
        $basename = wp_basename($filename);

        // Remove file extension.
        $name = pathinfo($basename, PATHINFO_FILENAME);

        // Replace hyphens and underscores with spaces.
        $name = str_replace(array('-', '_'), ' ', $name);

        // Collapse multiple consecutive spaces.
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name);

        if (empty($name)) {
            return '';
        }

        // Title Case: capitalise first letter of each word.
        $words = explode(' ', strtolower($name));
        $words = array_map('ucfirst', $words);
        $name  = implode(' ', $words);

        return sanitize_text_field($name);
    }
}
