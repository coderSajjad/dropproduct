# Feature: Product Service & Simple Products

## Goal

Provide a clean service layer for WooCommerce product CRUD operations, supporting simple products only in the free version.

---

## Key Class: `DropProduct_Product_Service`

**File:** `includes/class-dropproduct-product-service.php` (~315 lines)

### Constants

| Constant | Value | Purpose |
|----------|-------|---------|
| `META_KEY` | `_dropproduct_product` | Identifies DropProduct-created products |

### Public Methods

| Method | Signature | Description |
|--------|-----------|-------------|
| `create_draft_product()` | `($title, $featured_image_id, $gallery_ids = [])` | Creates a `WC_Product_Simple` as draft |
| `update_product_field()` | `($product_id, $field, $value)` | Updates a single field on a product |
| `publish_product()` | `($product_id)` | Validates and publishes a product |
| `delete_product()` | `($product_id, $force = true)` | Force-deletes a product (bypasses trash) |
| `get_draft_products()` | `()` | Returns all DropProduct draft + published products |
| `format_product_data()` | `($product)` | Converts `WC_Product` to grid-ready array |

### Private Methods

| Method | Signature | Description |
|--------|-----------|-------------|
| `validate_for_publish()` | `($product)` | Returns validation error array |

---

## Product Data Format

The `format_product_data()` method returns this structure to the frontend:

```php
array(
    'id'                => 123,
    'title'             => 'Blue Hoodie',
    'description'       => 'A comfortable hoodie',
    'regular_price'     => '29.99',
    'sale_price'        => '19.99',
    'sku'               => 'BH-001',
    'stock_status'      => 'instock',
    'category_id'       => 15,
    'image_thumb'       => 'https://…/thumb-150x150.jpg',
    'image_full'        => 'https://…/medium-300x300.jpg',
    'status'            => 'draft',
    'gallery_count'     => 3,
    'product_type'      => 'simple',
)
```

**Important:** Image URLs use WordPress-generated sizes:
- `image_thumb` → `wp_get_attachment_image_url($id, 'thumbnail')`
- `image_full` → `wp_get_attachment_image_url($id, 'medium')`

---

## Field Update Mapping

`update_product_field()` uses a switch statement to route each field to the appropriate WC CRUD method:

| Field | Sanitization | WC Method | Notes |
|-------|-------------|-----------|-------|
| `title` | `sanitize_text_field()` | `set_name()` | — |
| `description` | `wp_kses_post()` | `set_short_description()` | Allows safe HTML |
| `regular_price` | `wc_format_decimal()` | `set_regular_price()` | — |
| `sale_price` | `wc_format_decimal()` | `set_sale_price()` | — |
| `sku` | `sanitize_text_field()` | `set_sku()` | Wrapped in try/catch for duplicates |
| `stock_status` | Whitelist validation | `set_stock_status()` | Only `instock`, `outofstock`, `onbackorder` |
| `category` | `absint()` | `set_category_ids()` | Sets single category; 0 clears it |
| *(other)* | — | `dropproduct_update_custom_field` action | Extension point for Pro |

### SKU Duplicate Detection

WooCommerce's `set_sku()` throws `WC_Data_Exception` if a duplicate SKU is found. The plugin catches this and returns a `WP_Error`:

```php
try {
    $product->set_sku(sanitize_text_field($value));
} catch (WC_Data_Exception $e) {
    return new WP_Error('duplicate_sku', $e->getMessage());
}
```

---

## Draft Product Creation Flow

`create_draft_product()` performs these steps:

```php
$product = new WC_Product_Simple();
$product->set_name(sanitize_text_field($title));
$product->set_status('draft');
$product->set_image_id($featured_image_id);
$product->set_gallery_image_ids(array_map('absint', $gallery_ids));
$product->add_meta_data(self::META_KEY, '1', true);

do_action('dropproduct_before_create_product', $product);

$product_id = $product->save();

do_action('dropproduct_after_create_product', $product);
```

---

## Product Retrieval

`get_draft_products()` queries for all products with the `_dropproduct_product` meta key:

```php
$products = wc_get_products(array(
    'status'     => array('draft', 'publish'),
    'limit'      => -1,
    'orderby'    => 'date',
    'order'      => 'DESC',
    'meta_query' => array(
        array(
            'key'   => self::META_KEY,
            'value' => '1',
        ),
    ),
));
```

**Note:** This retrieves both draft and published products created by DropProduct, ordered newest-first.

---

## Extension Points

| Hook | Type | Purpose |
|------|------|---------|
| `dropproduct_before_create_product` | Action | Modify product before initial save |
| `dropproduct_after_create_product` | Action | Post-creation logic (session tagging, logging) |
| `dropproduct_after_publish_product` | Action | Post-publish logic |
| `dropproduct_after_delete_product` | Action | Post-deletion cleanup |
| `dropproduct_format_product_data` | Filter | Add extra fields to frontend product data |
| `dropproduct_validate_product` | Filter | Add custom validation rules |
| `dropproduct_update_custom_field` | Action | Handle custom field updates |
