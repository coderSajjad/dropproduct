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

**Purpose:** Handle custom field updates that aren't covered by the built-in field mapping. Note: `cost_price` is now a first-class built-in case (v1.0.2) and no longer falls through to this action.

---

### `dropproduct_fraud_score` *(reserved — v1.0.3)*

**Location:** `DropProduct_Fraud_Shield` (not yet called — reserved)  
**Since:** *(planned 1.0.3)*  
**Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `$result` | `array` | `['score' => int, 'rules' => string[]]` |
| `$data` | `array` | Checkout data (email, ip, phone, etc.) |

**Returns:** Modified `$result`  
**Purpose:** Add custom scoring rules to the Order Shield engine without modifying core code.

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
| `dropproduct_update_product` | `DropProduct_Ajax::handle_update_product()` | Update single field on a product (incl. `cost_price`) |
| `dropproduct_publish_single` | `DropProduct_Ajax::handle_publish_single()` | Publish a single draft product (v1.0.1) |
| `dropproduct_publish_all` | `DropProduct_Ajax::handle_publish_all()` | Validate and publish multiple products |
| `dropproduct_delete_product` | `DropProduct_Ajax::handle_delete_product()` | Delete a product |
| `dropproduct_load_products` | `DropProduct_Ajax::handle_load_products()` | Load existing DropProduct products |
| `dropproduct_bulk_price_adjust` | `DropProduct_Ajax::handle_bulk_price_adjust()` | Bulk adjust prices for selected products (v1.0.1) |

**Order Shield endpoints** (separate nonce: `dpshield_admin`, capability: `manage_woocommerce`):

| Action | Purpose |
|--------|--------|
| `dropproduct_save_fraud_settings` | Save Order Shield settings to WP option |
| `dropproduct_fraud_delete_log` | Delete a single fraud log entry |
| `dropproduct_fraud_clear_logs` | Truncate the entire fraud log table |

---

## Localized JS Objects

### `dropProduct` (main grid page)

Passed via `wp_localize_script()`:

| Key | Type | Description |
|-----|------|-------------|
| `ajaxUrl` | `string` | WordPress admin-ajax.php URL |
| `nonce` | `string` | Security nonce (`dropproduct_nonce`) |
| `categories` | `object` | `{id: name}` map of product categories |
| `isProActive` | `bool` | Whether the Pro plugin is active |
| `i18n` | `object` | All translatable UI strings |

### `dpShield` (Order Shield page)

Passed via `wp_localize_script()` on the Order Shield admin page only:

| Key | Type | Description |
|-----|------|-------------|
| `ajaxUrl` | `string` | WordPress admin-ajax.php URL |
| `nonce` | `string` | Security nonce (`dpshield_admin`) |
| `saving` | `string` | "Saving…" |
| `saved` | `string` | "Saved!" |
| `saveBtnLabel` | `string` | "Save Settings" |
| `networkError` | `string` | "Network error. Please try again." |
| `confirmDelete` | `string` | "Delete this log entry?" |
| `confirmClear` | `string` | "Clear ALL log entries? This cannot be undone." |
| `logsCleared` | `string` | "All logs cleared." |
