# Hooks Reference

Complete reference of WordPress hooks provided by the free plugin for extensibility.

---

## Filters

### `dropproduct_group_images`

**Location:** `DropProduct_Grouping_Engine::group()`  
**Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `$buckets` | `array` | `base_name => attachment_ids[]` mapping |
| `$attachment_ids` | `array` | Flat array of all attachment IDs |

**Returns:** Modified `$buckets` array  
**Purpose:** Override or extend the image grouping logic.

---

### `dropproduct_validate_product`

**Location:** `DropProduct_Product_Service::validate_for_publish()`  
**Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `$errors` | `array` | Validation error messages |
| `$product` | `WC_Product` | Product being validated |

**Returns:** Modified `$errors` array  
**Purpose:** Add custom validation rules before publishing.

---

### `dropproduct_localize_data`

**Location:** `DropProduct_Admin::enqueue_scripts()`  
**Since:** 1.1.0  
**Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `$data` | `array` | Localized JS data (`dropProduct` object) |

**Returns:** Extended `$data` array  
**Purpose:** Inject additional configuration into the frontend JavaScript. The data includes `ajaxUrl`, `nonce`, `categories`, `isProActive`, and `i18n` strings.

---

### `dropproduct_format_product_data`

**Location:** `DropProduct_Product_Service::format_product_data()`  
**Since:** 1.1.0  
**Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `$data` | `array` | Formatted product data for frontend |
| `$product` | `WC_Product` | Product instance |

**Returns:** Extended `$data` array  
**Purpose:** Add extra fields to the frontend product data (e.g., Pro-specific fields).

---

## Actions

### `dropproduct_before_create_product`

**Location:** `DropProduct_Product_Service::create_draft_product()`  
**Since:** 1.0.0  
**Parameters:** `WC_Product_Simple $product` — The product *before* it is saved  
**Purpose:** Modify the product object before the initial `save()` call (e.g., set default values, add metadata).

### `dropproduct_after_create_product`

**Location:** `DropProduct_Product_Service::create_draft_product()`  
**Since:** 1.1.0  
**Parameters:** `WC_Product $product` — The saved product (has an ID)  
**Purpose:** Run logic after a product is created (e.g., session tagging, logging, notification).

### `dropproduct_after_publish_product`

**Location:** `DropProduct_Product_Service::publish_product()`  
**Since:** 1.1.0  
**Parameters:** `int $product_id`  
**Purpose:** Run logic after a product is published (e.g., cache invalidation, notifications).

### `dropproduct_after_delete_product`

**Location:** `DropProduct_Product_Service::delete_product()`  
**Since:** 1.1.0  
**Parameters:** `int $product_id`  
**Purpose:** Run logic after a product is deleted (e.g., cleanup attachment metadata).

### `dropproduct_update_custom_field`

**Location:** `DropProduct_Product_Service::update_product_field()` (default case)  
**Since:** 1.0.0  
**Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `$product` | `WC_Product` | The product being updated |
| `$field` | `string` | The field name |
| `$value` | `mixed` | The new value |

**Purpose:** Handle custom field updates that aren't covered by the built-in field mapping. Pro uses this for variable product fields, custom attributes, etc.

### Template Actions

| Hook | Location | Since | Purpose |
|------|----------|-------|---------|
| `dropproduct_after_header` | `dropproduct-page.php` | 1.1.0 | Inject UI after the page header (Pro: bulk actions bar, session filter) |
| `dropproduct_before_grid` | `dropproduct-page.php` | 1.1.0 | Inject UI before the product grid (Pro: session filter dropdown) |
| `dropproduct_after_grid` | `dropproduct-page.php` | 1.1.0 | Inject UI after the grid and modals (Pro: validation dashboard, activity log) |

---

## AJAX Endpoints

All use `wp_ajax_{action}`. Nonce key: `dropproduct_nonce`. Capability: `manage_woocommerce`.

| Action | Handler Method | Purpose |
|--------|---------------|---------|
| `dropproduct_upload_images` | `DropProduct_Ajax::handle_upload_images()` | Batch upload images → group → create products |
| `dropproduct_upload_single_image` | `DropProduct_Ajax::handle_upload_single_image()` | Upload one image, return attachment ID |
| `dropproduct_create_products` | `DropProduct_Ajax::handle_create_products()` | Group attachment IDs → create products |
| `dropproduct_update_product` | `DropProduct_Ajax::handle_update_product()` | Update single field on a product |
| `dropproduct_publish_all` | `DropProduct_Ajax::handle_publish_all()` | Validate and publish multiple products |
| `dropproduct_delete_product` | `DropProduct_Ajax::handle_delete_product()` | Delete a product |
| `dropproduct_load_products` | `DropProduct_Ajax::handle_load_products()` | Load existing DropProduct Products |

---

## Localized JS Object (`dropProduct`)

Passed via `wp_localize_script()`:

| Key | Type | Description |
|-----|------|-------------|
| `ajaxUrl` | `string` | WordPress admin-ajax.php URL |
| `nonce` | `string` | Security nonce |
| `categories` | `object` | `{id: name}` map of product categories |
| `isProActive` | `bool` | Whether the Pro plugin is active (`DROPPRODUCT_PRO_VERSION` defined) |
| `i18n` | `object` | All translatable UI strings |

### i18n Strings

| Key | Default Value |
|-----|---------------|
| `dropzone` | "Drag & drop product images here, or click to browse" |
| `uploading` | "Uploading…" |
| `saving` | "Saving…" |
| `saved` | "Saved" |
| `publishing` | "Publishing…" |
| `published` | "Published!" |
| `publishAll` | "Publish All" |
| `deleteConfirm` | "Delete this product?" |
| `noProducts` | "No draft products yet. Upload images to get started." |
| `titleRequired` | "Title is required" |
| `priceRequired` | "Price is required" |
| `validationError` | "Fix highlighted errors before publishing." |
| `uploadError` | "Upload failed. Please try again." |
| `networkError` | "Network error. Please try again." |
