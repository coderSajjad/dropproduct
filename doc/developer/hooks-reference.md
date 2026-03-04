# Hooks Reference

Complete reference of WordPress hooks provided by the free plugin for extensibility.

---

## Filters

### `wc_uploady_group_images`

**Location:** `WC_Uploady_Grouping_Engine::group()`  
**Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `$buckets` | `array` | `base_name => attachment_ids[]` mapping |
| `$attachment_ids` | `array` | Flat array of all attachment IDs |

**Returns:** Modified `$buckets` array  
**Purpose:** Override or extend the image grouping logic.

---

### `wc_uploady_validate_product`

**Location:** `WC_Uploady_Product_Service::validate_for_publish()`  
**Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `$errors` | `array` | Validation error messages |
| `$product` | `WC_Product` | Product being validated |

**Returns:** Modified `$errors` array  
**Purpose:** Add custom validation rules before publishing.

---

### `wc_uploady_localize_data`

**Location:** `WC_Uploady_Admin::enqueue_scripts()`  
**Since:** 1.1.0  
**Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `$data` | `array` | Localized JS data (`wcUploady` object) |

**Returns:** Extended `$data` array  
**Purpose:** Inject additional configuration into the frontend JavaScript. The data includes `ajaxUrl`, `nonce`, `categories`, `isProActive`, and `i18n` strings.

---

### `wc_uploady_format_product_data`

**Location:** `WC_Uploady_Product_Service::format_product_data()`  
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

### `wc_uploady_before_create_product`

**Location:** `WC_Uploady_Product_Service::create_draft_product()`  
**Since:** 1.0.0  
**Parameters:** `WC_Product_Simple $product` — The product *before* it is saved  
**Purpose:** Modify the product object before the initial `save()` call (e.g., set default values, add metadata).

### `wc_uploady_after_create_product`

**Location:** `WC_Uploady_Product_Service::create_draft_product()`  
**Since:** 1.1.0  
**Parameters:** `WC_Product $product` — The saved product (has an ID)  
**Purpose:** Run logic after a product is created (e.g., session tagging, logging, notification).

### `wc_uploady_after_publish_product`

**Location:** `WC_Uploady_Product_Service::publish_product()`  
**Since:** 1.1.0  
**Parameters:** `int $product_id`  
**Purpose:** Run logic after a product is published (e.g., cache invalidation, notifications).

### `wc_uploady_after_delete_product`

**Location:** `WC_Uploady_Product_Service::delete_product()`  
**Since:** 1.1.0  
**Parameters:** `int $product_id`  
**Purpose:** Run logic after a product is deleted (e.g., cleanup attachment metadata).

### `wc_uploady_update_custom_field`

**Location:** `WC_Uploady_Product_Service::update_product_field()` (default case)  
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
| `wc_uploady_after_header` | `uploady-page.php` | 1.1.0 | Inject UI after the page header (Pro: bulk actions bar, session filter) |
| `wc_uploady_before_grid` | `uploady-page.php` | 1.1.0 | Inject UI before the product grid (Pro: session filter dropdown) |
| `wc_uploady_after_grid` | `uploady-page.php` | 1.1.0 | Inject UI after the grid and modals (Pro: validation dashboard, activity log) |

---

## AJAX Endpoints

All use `wp_ajax_{action}`. Nonce key: `wc_uploady_nonce`. Capability: `manage_woocommerce`.

| Action | Handler Method | Purpose |
|--------|---------------|---------|
| `wc_uploady_upload_images` | `WC_Uploady_Ajax::handle_upload_images()` | Batch upload images → group → create products |
| `wc_uploady_upload_single_image` | `WC_Uploady_Ajax::handle_upload_single_image()` | Upload one image, return attachment ID |
| `wc_uploady_create_products` | `WC_Uploady_Ajax::handle_create_products()` | Group attachment IDs → create products |
| `wc_uploady_update_product` | `WC_Uploady_Ajax::handle_update_product()` | Update single field on a product |
| `wc_uploady_publish_all` | `WC_Uploady_Ajax::handle_publish_all()` | Validate and publish multiple products |
| `wc_uploady_delete_product` | `WC_Uploady_Ajax::handle_delete_product()` | Delete a product |
| `wc_uploady_load_products` | `WC_Uploady_Ajax::handle_load_products()` | Load existing WooUpload Products |

---

## Localized JS Object (`wcUploady`)

Passed via `wp_localize_script()`:

| Key | Type | Description |
|-----|------|-------------|
| `ajaxUrl` | `string` | WordPress admin-ajax.php URL |
| `nonce` | `string` | Security nonce |
| `categories` | `object` | `{id: name}` map of product categories |
| `isProActive` | `bool` | Whether the Pro plugin is active (`WC_UPLOADY_PRO_VERSION` defined) |
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
