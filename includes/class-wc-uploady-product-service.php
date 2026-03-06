<?php

/**
 * Product service layer.
 *
 * Handles all product CRUD operations via WooCommerce's WC_Product API.
 * Abstracted for future Pro extension (variable products, etc.).
 *
 * @package WooCommerce_Uploady
 * @since   1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_Uploady_Product_Service
 *
 * @since 1.0.0
 */
class WC_Uploady_Product_Service
{

    /**
     * Meta key to identify products created by Uploady.
     *
     * @var string
     */
    const META_KEY = '_wc_uploady_product';

    /**
     * Create a draft simple product from grouped image data.
     *
     * @param string $title             Product title.
     * @param int    $featured_image_id Attachment ID for the featured image.
     * @param array  $gallery_ids       Attachment IDs for gallery images.
     * @return WC_Product_Simple|WP_Error The created product or error.
     */
    public function create_draft_product($title, $featured_image_id, $gallery_ids = array())
    {
        $product = new WC_Product_Simple();

        $product->set_name(sanitize_text_field($title));
        $product->set_status('draft');
        $product->set_image_id($featured_image_id);

        if (! empty($gallery_ids)) {
            $product->set_gallery_image_ids(array_map('absint', $gallery_ids));
        }

        // Mark as created by Uploady for easy retrieval.
        $product->add_meta_data(self::META_KEY, '1', true);

        /**
         * Fires before a draft product is saved.
         *
         * @since 1.0.0
         * @param WC_Product_Simple $product The product about to be saved.
         */
        do_action('wc_uploady_before_create_product', $product);

        $product_id = $product->save();

        if (! $product_id) {
            return new WP_Error('create_failed', __('Failed to create product.', 'uploady'));
        }

        /**
         * Fires after a draft product is created and saved.
         *
         * @since 1.1.0
         * @param WC_Product $product The created product.
         */
        do_action('wc_uploady_after_create_product', $product);

        return $product;
    }

    /**
     * Update a single field on a product.
     *
     * @param int    $product_id Product ID.
     * @param string $field      Field name: title, regular_price, sale_price, sku, stock_status, category.
     * @param mixed  $value      New value.
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    public function update_product_field($product_id, $field, $value)
    {
        $product = wc_get_product($product_id);

        if (! $product) {
            return new WP_Error('invalid_product', __('Product not found.', 'uploady'));
        }

        switch ($field) {
            case 'title':
                $product->set_name(sanitize_text_field($value));
                break;

            case 'description':
                $product->set_short_description(wp_kses_post($value));
                break;

            case 'regular_price':
                $product->set_regular_price(wc_format_decimal($value));
                break;

            case 'sale_price':
                $product->set_sale_price(wc_format_decimal($value));
                break;

            case 'sku':
                try {
                    $product->set_sku(sanitize_text_field($value));
                } catch (WC_Data_Exception $e) {
                    return new WP_Error('duplicate_sku', $e->getMessage());
                }
                break;

            case 'stock_status':
                $allowed = array('instock', 'outofstock', 'onbackorder');
                $value   = sanitize_text_field($value);
                if (in_array($value, $allowed, true)) {
                    $product->set_stock_status($value);
                }
                break;

            case 'category':
                $term_id = absint($value);
                if ($term_id) {
                    $product->set_category_ids(array($term_id));
                } else {
                    $product->set_category_ids(array());
                }
                break;

            default:
                /**
                 * Allows Pro/add-ons to handle custom fields.
                 *
                 * @since 1.0.0
                 * @param WC_Product $product    The product being updated.
                 * @param string     $field      The field name.
                 * @param mixed      $value      The new value.
                 */
                do_action('wc_uploady_update_custom_field', $product, $field, $value);
                break;
        }

        $product->save();
        return true;
    }

    /**
     * Validate and publish a draft product.
     *
     * @param int $product_id Product ID.
     * @return true|WP_Error True on success, WP_Error with validation details.
     */
    public function publish_product($product_id)
    {
        $product = wc_get_product($product_id);

        if (! $product) {
            return new WP_Error('invalid_product', __('Product not found.', 'uploady'));
        }

        $errors = $this->validate_for_publish($product);

        if (! empty($errors)) {
            return new WP_Error('validation_failed', implode(', ', $errors));
        }

        $product->set_status('publish');
        $product->save();

        /**
         * Fires after a product is published.
         *
         * @since 1.1.0
         * @param int $product_id The published product ID.
         */
        do_action('wc_uploady_after_publish_product', $product_id);

        return true;
    }

    /**
     * Delete a product created by Uploady.
     *
     * @param int  $product_id Product ID.
     * @param bool $force      Whether to force delete (bypass trash).
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    public function delete_product($product_id, $force = true)
    {
        $product = wc_get_product($product_id);

        if (! $product) {
            return new WP_Error('invalid_product', __('Product not found.', 'uploady'));
        }

        $product->delete($force);

        /**
         * Fires after a product is deleted.
         *
         * @since 1.1.0
         * @param int $product_id The deleted product ID.
         */
        do_action('wc_uploady_after_delete_product', $product_id);

        return true;
    }

    /**
     * Retrieve all draft products created by Uploady.
     *
     * @return array Array of product data arrays.
     */
    public function get_draft_products()
    {
        $products = wc_get_products(array(
            'status'     => array( 'draft', 'publish' ),
            'limit'      => -1,
            'orderby'    => 'date',
            'order'      => 'DESC',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to retrieve only Uploady-created products.
            'meta_query' => array(
                array(
                    'key'   => self::META_KEY,
                    'value' => '1',
                ),
            ),
        ));

        $result = array();

        foreach ($products as $product) {
            $result[] = $this->format_product_data($product);
        }

        return $result;
    }

    /**
     * Format a WC_Product into a data array for the frontend grid.
     *
     * @param WC_Product $product Product instance.
     * @return array Formatted product data.
     */
    public function format_product_data($product)
    {
        $image_id  = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
        $image_full = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';

        $category_ids = $product->get_category_ids();
        $category_id  = ! empty($category_ids) ? $category_ids[0] : 0;

        $data = array(
            'id'                => $product->get_id(),
            'title'             => $product->get_name(),
            'description'       => $product->get_short_description(),
            'regular_price'     => $product->get_regular_price(),
            'sale_price'        => $product->get_sale_price(),
            'sku'               => $product->get_sku(),
            'stock_status'      => $product->get_stock_status(),
            'category_id'       => $category_id,
            'image_thumb'       => $image_url,
            'image_full'        => $image_full,
            'status'            => $product->get_status(),
            'gallery_count'     => count($product->get_gallery_image_ids()),
            'product_type'      => $product->get_type(),
        );

        /**
         * Filter formatted product data before sending to the frontend.
         *
         * @since 1.1.0
         * @param array      $data    Formatted product data.
         * @param WC_Product $product The product instance.
         */
        return apply_filters('wc_uploady_format_product_data', $data, $product);
    }

    /**
     * Validate a product for publishing.
     *
     * @param WC_Product $product Product instance.
     * @return array Array of error messages (empty if valid).
     */
    private function validate_for_publish($product)
    {
        $errors = array();

        if (empty(trim($product->get_name()))) {
            $errors[] = __('Title is required.', 'uploady');
        }

        if ('' === $product->get_regular_price()) {
            $errors[] = __('Price is required.', 'uploady');
        }

        /**
         * Filter validation errors before publish.
         *
         * @since 1.0.0
         * @param array      $errors  Current validation errors.
         * @param WC_Product $product The product being validated.
         */
        return apply_filters('wc_uploady_validate_product', $errors, $product);
    }
}
