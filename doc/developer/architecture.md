# Architecture Overview

## Plugin Structure

WooUpload is a WordPress plugin that replaces the default WooCommerce product creation workflow with a high-performance SPA-style grid, enabling bulk product creation from images.

---

## Core Architecture Pattern

```
┌──────────────────────────────────────────────────┐
│          woocommerce-uploady.php                  │
│  (Entry Point — constants, HPOS, boot)            │
└─────────────────────┬────────────────────────────┘
                      │
        ┌─────────────▼─────────────┐
        │      WC_Uploady           │
        │    (Orchestrator)          │
        │  - load_dependencies()     │
        │  - define_admin_hooks()    │
        │  - define_ajax_hooks()     │
        └─────────────┬─────────────┘
                      │
        ┌─────────────▼─────────────┐
        │    WC_Uploady_Loader       │
        │  (Hook Registration)       │
        └─────────────┬─────────────┘
                      │
    ┌─────────────────┼──────────────────┐
    │                 │                  │
    ▼                 ▼                  ▼
┌─────────┐   ┌────────────┐   ┌──────────────────┐
│  Admin   │   │    AJAX    │   │  Product Service │
│  Class   │   │  Handler   │   │  + Grouping      │
└─────────┘   └────────────┘   └──────────────────┘
```

---

## File Structure

```
woocommerce-uploady/
├── woocommerce-uploady.php          # Entry point, constants, HPOS
├── uninstall.php                     # Cleanup on uninstall
├── readme.txt                        # WordPress.org readme
├── includes/
│   ├── class-wc-uploady.php              # Orchestrator
│   ├── class-wc-uploady-loader.php       # Hook loader
│   ├── class-wc-uploady-admin.php        # Admin UI + scripts
│   ├── class-wc-uploady-ajax.php         # AJAX handlers (7 endpoints)
│   ├── class-wc-uploady-product-service.php  # WC product CRUD
│   └── class-wc-uploady-grouping-engine.php  # Image grouping
├── admin/views/
│   └── uploady-page.php              # Main admin page template
├── assets/
│   ├── css/admin-uploady.css         # Admin styles (~990 lines)
│   └── js/admin-uploady.js           # Admin JavaScript SPA (~760 lines)
└── doc/
    ├── developer/                     # Developer documentation
    ├── user/                          # User documentation
    └── plan.txt                       # Original plan
```

---

## Key Design Decisions

### 1. Loader-Based Hook System
All WordPress hooks are registered through `WC_Uploady_Loader`, which collects actions/filters and registers them in one `run()` call. This avoids scattered `add_action()` calls and makes the hook registry explicit and centralized.

### 2. Service Layer
`WC_Uploady_Product_Service` encapsulates all WooCommerce product operations. No direct `$wpdb` queries — everything uses the WC_Product CRUD API (`set_name()`, `set_regular_price()`, `set_sale_price()`, `save()`, etc.).

### 3. SPA-Style Grid
The admin page is a single-page application — no page reloads. All data operations (upload, edit, publish, delete) are handled via AJAX, and the grid updates via jQuery DOM manipulation.

### 4. Dual Upload Strategy
The plugin supports two upload approaches:
- **Batch upload** via `handle_upload_images()` — sends all files in one `FormData` request
- **Single-image upload** via `handle_upload_single_image()` — sends one file at a time, then calls `handle_create_products()` with collected attachment IDs

### 5. Extension Points for Pro
The free plugin includes `apply_filters` and `do_action` hooks at strategic points so the Pro plugin can extend behavior without modifying free plugin code:
- `wc_uploady_group_images` — Custom grouping logic
- `wc_uploady_validate_product` — Additional validation rules
- `wc_uploady_localize_data` — Extra JS configuration
- `wc_uploady_format_product_data` — Extra product data fields
- `wc_uploady_before_create_product` — Pre-creation modifications
- `wc_uploady_after_create_product` — Post-creation actions
- `wc_uploady_after_publish_product` — Post-publish actions
- `wc_uploady_after_delete_product` — Post-deletion actions
- `wc_uploady_update_custom_field` — Handle custom field updates

---

## Security Model

Every AJAX handler calls `verify_request()` which:
1. Checks the nonce via `check_ajax_referer('wc_uploady_nonce')`
2. Checks user capability via `current_user_can('manage_woocommerce')`

All inputs are sanitized with `sanitize_text_field()`, `absint()`, `wc_format_decimal()`, `wp_kses_post()` (for description). All outputs are escaped with `esc_html()`, `esc_attr()`.

---

## Product Tracking

Products created by WooUpload are tagged with `_wc_uploady_product` meta key (value `'1'`). This allows the plugin to:
- Load only its own draft/published products on the WooUpload page
- Avoid interfering with products created through other means

---

## Boot Sequence

1. WordPress loads `woocommerce-uploady.php`
2. Constants defined: `WC_UPLOADY_VERSION`, `WC_UPLOADY_PLUGIN_DIR`, `WC_UPLOADY_PLUGIN_URL`, `WC_UPLOADY_PLUGIN_BASENAME`
3. HPOS compatibility declared via `before_woocommerce_init`
4. On `plugins_loaded`: checks WooCommerce is active, then creates `WC_Uploady` and calls `run()`
5. Orchestrator loads all class files, registers hooks via loader
6. Loader registers hooks with WordPress

---

## Class Responsibilities Summary

| Class | File | Lines | Role |
|-------|------|-------|------|
| `WC_Uploady` | `class-wc-uploady.php` | ~96 | Orchestrator — loads dependencies, wires hooks |
| `WC_Uploady_Loader` | `class-wc-uploady-loader.php` | ~91 | Collects & registers WordPress hooks |
| `WC_Uploady_Admin` | `class-wc-uploady-admin.php` | ~146 | Admin menu, asset enqueuing, page rendering |
| `WC_Uploady_Ajax` | `class-wc-uploady-ajax.php` | ~357 | 7 AJAX endpoint handlers |
| `WC_Uploady_Product_Service` | `class-wc-uploady-product-service.php` | ~315 | WooCommerce product CRUD operations |
| `WC_Uploady_Grouping_Engine` | `class-wc-uploady-grouping-engine.php` | ~112 | Filename-based image grouping |

---

## Frontend Architecture

The frontend is a single JavaScript object (`WooUpload`) inside an IIFE, structured as:

| Method | Purpose |
|--------|---------|
| `init()` | Boot — calls `cache()`, `cacheModal()`, `bindEvents()`, `loadExistingProducts()` |
| `cache()` | Caches all DOM element references |
| `cacheModal()` | Caches description modal elements |
| `bindEvents()` | Sets up all event listeners (drag/drop, blur save, delete, publish, hover preview, description modal) |
| `loadExistingProducts()` | AJAX call to load existing WooUpload Products on page load |
| `uploadFiles(files)` | Builds `FormData` from files, sends AJAX upload with progress |
| `renderProducts(products)` | Renders product rows into the grid table |
| `buildRow(product)` | Generates HTML for a single product table row |
| `saveField($field)` | Auto-saves a single field via AJAX on blur/change |
| `validatePrices($row)` | Client-side sale price vs regular price validation |
| `deleteProduct($row)` | Deletes a product with confirmation dialog |
| `publishAll()` | Validates all drafts and batch-publishes valid ones |
| `openDescriptionModal($row)` / `saveDescription()` / `closeDescriptionModal()` | Description popup workflow |
| `showNotice(message, type)` | Displays auto-dismissing toast notifications |
| `positionPreview(e)` | Positions the floating image preview near cursor |
| `escHtml()` / `escAttr()` / `decodeHtml()` | Utility functions for encoding/decoding |
