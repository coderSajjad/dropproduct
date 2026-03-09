# Architecture Overview

## Plugin Structure

DropProduct is a WordPress plugin that replaces the default WooCommerce product creation workflow with a high-performance SPA-style grid, enabling bulk product creation from images.

---

## Core Architecture Pattern

```
┌──────────────────────────────────────────────────┐
│          dropproduct.php                  │
│  (Entry Point — constants, HPOS, boot)            │
└─────────────────────┬────────────────────────────┘
                      │
        ┌─────────────▼─────────────┐
        │      DropProduct           │
        │    (Orchestrator)          │
        │  - load_dependencies()     │
        │  - define_admin_hooks()    │
        │  - define_ajax_hooks()     │
        └─────────────┬─────────────┘
                      │
        ┌─────────────▼─────────────┐
        │    DropProduct_Loader       │
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
dropproduct/
├── dropproduct.php          # Entry point, constants, HPOS
├── uninstall.php                     # Cleanup on uninstall
├── readme.txt                        # WordPress.org readme
├── includes/
│   ├── class-dropproduct.php              # Orchestrator
│   ├── class-dropproduct-loader.php       # Hook loader
│   ├── class-dropproduct-admin.php        # Admin UI + scripts
│   ├── class-dropproduct-ajax.php         # AJAX handlers (7 endpoints)
│   ├── class-dropproduct-product-service.php  # WC product CRUD
│   └── class-dropproduct-grouping-engine.php  # Image grouping
├── admin/views/
│   └── dropproduct-page.php              # Main admin page template
├── assets/
│   ├── css/admin-dropproduct.css         # Admin styles (~990 lines)
│   └── js/admin-dropproduct.js           # Admin JavaScript SPA (~760 lines)
└── doc/
    ├── developer/                     # Developer documentation
    ├── user/                          # User documentation
    └── plan.txt                       # Original plan
```

---

## Key Design Decisions

### 1. Loader-Based Hook System
All WordPress hooks are registered through `DropProduct_Loader`, which collects actions/filters and registers them in one `run()` call. This avoids scattered `add_action()` calls and makes the hook registry explicit and centralized.

### 2. Service Layer
`DropProduct_Product_Service` encapsulates all WooCommerce product operations. No direct `$wpdb` queries — everything uses the WC_Product CRUD API (`set_name()`, `set_regular_price()`, `set_sale_price()`, `save()`, etc.).

### 3. SPA-Style Grid
The admin page is a single-page application — no page reloads. All data operations (upload, edit, publish, delete) are handled via AJAX, and the grid updates via jQuery DOM manipulation.

### 4. Dual Upload Strategy
The plugin supports two upload approaches:
- **Batch upload** via `handle_upload_images()` — sends all files in one `FormData` request
- **Single-image upload** via `handle_upload_single_image()` — sends one file at a time, then calls `handle_create_products()` with collected attachment IDs

### 5. Extension Points for Pro
The free plugin includes `apply_filters` and `do_action` hooks at strategic points so the Pro plugin can extend behavior without modifying free plugin code:
- `dropproduct_group_images` — Custom grouping logic
- `dropproduct_validate_product` — Additional validation rules
- `dropproduct_localize_data` — Extra JS configuration
- `dropproduct_format_product_data` — Extra product data fields
- `dropproduct_before_create_product` — Pre-creation modifications
- `dropproduct_after_create_product` — Post-creation actions
- `dropproduct_after_publish_product` — Post-publish actions
- `dropproduct_after_delete_product` — Post-deletion actions
- `dropproduct_update_custom_field` — Handle custom field updates

---

## Security Model

Every AJAX handler calls `verify_request()` which:
1. Checks the nonce via `check_ajax_referer('dropproduct_nonce')`
2. Checks user capability via `current_user_can('manage_woocommerce')`

All inputs are sanitized with `sanitize_text_field()`, `absint()`, `wc_format_decimal()`, `wp_kses_post()` (for description). All outputs are escaped with `esc_html()`, `esc_attr()`.

---

## Product Tracking

Products created by DropProduct are tagged with `_dropproduct_product` meta key (value `'1'`). This allows the plugin to:
- Load only its own draft/published products on the DropProduct page
- Avoid interfering with products created through other means

---

## Boot Sequence

1. WordPress loads `dropproduct.php`
2. Constants defined: `DROPPRODUCT_VERSION`, `DROPPRODUCT_PLUGIN_DIR`, `DROPPRODUCT_PLUGIN_URL`, `DROPPRODUCT_PLUGIN_BASENAME`
3. HPOS compatibility declared via `before_woocommerce_init`
4. On `plugins_loaded`: checks WooCommerce is active, then creates `DropProduct` and calls `run()`
5. Orchestrator loads all class files, registers hooks via loader
6. Loader registers hooks with WordPress

---

## Class Responsibilities Summary

| Class | File | Lines | Role |
|-------|------|-------|------|
| `DropProduct` | `class-dropproduct.php` | ~96 | Orchestrator — loads dependencies, wires hooks |
| `DropProduct_Loader` | `class-dropproduct-loader.php` | ~91 | Collects & registers WordPress hooks |
| `DropProduct_Admin` | `class-dropproduct-admin.php` | ~146 | Admin menu, asset enqueuing, page rendering |
| `DropProduct_Ajax` | `class-dropproduct-ajax.php` | ~357 | 7 AJAX endpoint handlers |
| `DropProduct_Product_Service` | `class-dropproduct-product-service.php` | ~315 | WooCommerce product CRUD operations |
| `DropProduct_Grouping_Engine` | `class-dropproduct-grouping-engine.php` | ~112 | Filename-based image grouping |

---

## Frontend Architecture

The frontend is a single JavaScript object (`DropProduct`) inside an IIFE, structured as:

| Method | Purpose |
|--------|---------|
| `init()` | Boot — calls `cache()`, `cacheModal()`, `bindEvents()`, `loadExistingProducts()` |
| `cache()` | Caches all DOM element references |
| `cacheModal()` | Caches description modal elements |
| `bindEvents()` | Sets up all event listeners (drag/drop, blur save, delete, publish, hover preview, description modal) |
| `loadExistingProducts()` | AJAX call to load existing DropProduct Products on page load |
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
