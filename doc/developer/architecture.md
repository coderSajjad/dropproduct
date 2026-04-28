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
        │  - define_fraud_shield_hooks() │
        └─────────────┬─────────────┘
                      │
        ┌─────────────▼─────────────┐
        │    DropProduct_Loader       │
        │  (Hook Registration)       │
        └─────────────┬─────────────┘
                      │
    ┌─────────────────┼──────────────────┬──────────────────┐
    │                 │                  │                  │
    ▼                 ▼                  ▼                  ▼
┌─────────┐   ┌────────────┐   ┌──────────────────┐  ┌────────────────────┐
│  Admin   │   │    AJAX    │   │  Product Service │  │  Fraud Shield      │
│  Class   │   │  Handler   │   │  + Grouping      │  │  + Fraud Logger    │
└─────────┘   └────────────┘   └──────────────────┘  └────────────────────┘
```

---

## File Structure

```
dropproduct/
├── dropproduct.php                              # Entry point, constants, HPOS (v1.0.2)
├── uninstall.php                                # Cleanup on uninstall
├── readme.txt                                   # WordPress.org readme (v1.0.2)
├── includes/
│   ├── class-dropproduct.php                    # Orchestrator
│   ├── class-dropproduct-loader.php             # Hook loader
│   ├── class-dropproduct-admin.php              # Admin UI + scripts (v1.0.2 — Order Shield menu)
│   ├── class-dropproduct-ajax.php               # AJAX handlers (8 endpoints + bulk price)
│   ├── class-dropproduct-product-service.php    # WC product CRUD (v1.0.2 — cost_price field)
│   ├── class-dropproduct-grouping-engine.php    # Image grouping
│   ├── class-dropproduct-settings.php           # Settings CRUD
│   ├── class-dropproduct-fraud-shield.php       # Fraud engine (v1.0.2)
│   ├── class-dropproduct-fraud-logger.php       # Fraud log DB table (v1.0.2)
│   └── class-dropproduct-analytics.php          # Sales analytics service (v1.1.0)
├── admin/views/
│   ├── dropproduct-page.php                     # Main admin page template (v1.0.2 — 3 new columns)
│   ├── settings-page.php                        # Settings page template
│   └── fraud-shield-page.php                    # Order Shield admin page (v1.0.2)
├── assets/
│   ├── css/
│   │   ├── admin-dropproduct.css                # Admin styles (~2500 lines)
│   │   └── admin-fraud-shield.css               # Order Shield styles (v1.0.2)
│   └── js/
│       ├── admin-dropproduct.js                 # Admin SPA (~1400 lines, v1.0.2)
│       ├── admin-dropproduct-settings.js        # Settings page JS
│       └── admin-fraud-shield.js                # Order Shield JS (v1.0.2)
└── doc/
    ├── developer/                               # Developer documentation
    ├── user/                                    # User documentation
    └── plan.txt                                 # Original plan
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

| Class | File | Since | Role |
|-------|------|-------|------|
| `DropProduct` | `class-dropproduct.php` | 1.0.0 | Orchestrator — loads dependencies, wires hooks |
| `DropProduct_Loader` | `class-dropproduct-loader.php` | 1.0.0 | Collects & registers WordPress hooks |
| `DropProduct_Admin` | `class-dropproduct-admin.php` | 1.0.0 | Admin menu, asset enqueuing, page rendering |
| `DropProduct_Ajax` | `class-dropproduct-ajax.php` | 1.0.0 | 8 AJAX endpoint handlers + bulk price adjust |
| `DropProduct_Product_Service` | `class-dropproduct-product-service.php` | 1.0.0 | WC product CRUD; `cost_price` field (v1.0.2) |
| `DropProduct_Grouping_Engine` | `class-dropproduct-grouping-engine.php` | 1.0.0 | Filename-based image grouping |
| `DropProduct_Settings` | `class-dropproduct-settings.php` | 1.0.1 | Saves/loads plugin settings option |
| `DropProduct_Fraud_Shield` | `class-dropproduct-fraud-shield.php` | 1.0.2 | Fraud scoring engine, WC checkout hooks, COD restriction |
| `DropProduct_Fraud_Logger` | `class-dropproduct-fraud-logger.php` | 1.0.2 | Custom DB table for fraud audit logs |
| `DropProduct_Analytics` | `class-dropproduct-analytics.php` | 1.1.0 | Sales analytics dashboard data |

---

## Frontend Architecture

The frontend is a single JavaScript object (`DropProduct`) inside an IIFE, structured as:

| Method | Purpose |
|--------|---------|
| `init()` | Boot — calls all cache/bind methods, then `loadExistingProducts()` |
| `cache()` | Caches core DOM element references |
| `cacheModal()` | Caches description modal elements |
| `cacheDeleteModal()` | Caches delete confirmation modal elements |

## Sales Analytics (Developer Notes)

Location: `includes/class-dropproduct-analytics.php` (since 1.1.0)

Purpose: aggregate and serve analytics data for DropProduct-created products. The service exposes an AJAX endpoint used by the admin dashboard and returns JSON with summary metrics, time-series data, top products, country breakdowns and basic channel/device buckets.

Key integration points and behavior:
- AJAX action: `dropproduct_get_analytics` — registered by the orchestrator. Requests are protected by the plugin nonce and a capability check (`manage_woocommerce`).
- Script handle: `dropproduct-analytics` enqueues admin JS and localizes the nonce and endpoint config for the client-side dashboard.
- Data source: uses WooCommerce orders and items filtered to products that have the `_dropproduct_product` meta flag. Time ranges are applied server-side for efficient aggregation.
- Demo data: `admin/sql/dropproduct-demo-data.sql` contains a small dataset to test the dashboard locally.

Extension points:
- The analytics service is implemented as a standalone class so that Pro or custom code can extend, replace, or filter its output. If Pro needs to augment the response (sessions, activity counts, or extra dimensions), do so by hooking into the orchestrator or registering a custom loader that augments the AJAX handler response.

Developer tips:
- Keep heavy aggregations cached if your site has many orders — consider transient caching around the analytics queries.
- If you need richer UTM/channel attribution, populate order meta from your tracking system, then extend the analytics class to read those meta keys when building the breakdowns.
- Chart rendering is client-side (Chart.js). The PHP class only provides the raw datasets.
| `cacheProPopup()` | Caches Pro lock popup elements |
| `cachePriceSlasher()` | Caches Price Slasher bar elements and initialises `_selectedIds` |
| `bindEvents()` | Sets up all event listeners |
| `loadExistingProducts()` | AJAX call to load existing DropProduct products on page load |
| `uploadFiles(files)` | Builds `FormData`, sends AJAX upload with progress |
| `renderProducts(products)` | Renders product rows into the grid table |
| `buildRow(product)` | Generates HTML for a single product table row (incl. cost/profit/margin cells) |
| `saveField($field)` | Auto-saves a single field via AJAX on blur/change |
| `saveCostPrice($input)` | Debounced AJAX save for the cost price input |
| `formatFinancials(reg, sale, cost)` | Pure function — returns computed profit/margin HTML strings |
| `calculateFinancials($row)` | Reads row DOM, calls `formatFinancials()`, updates profit/margin cells |
| `validatePrices($row)` | Client-side sale price vs regular price validation |
| `applyPriceSlasher()` | Sends bulk price adjustment AJAX, updates grid on response |
| `toggleSlasherBar()` | Shows/hides the Price Slasher bar |
| `publishSingle($row)` | Publishes a single draft product row |
| `publishAll()` | Validates all drafts and batch-publishes valid ones |
| `openDescriptionModal($row)` / `saveDescription()` / `closeDescriptionModal()` | Description popup workflow |
| `openDeleteModal($row)` / `confirmDelete()` / `closeDeleteModal()` | Delete confirmation modal workflow |
| `openProPopup()` / `closeProPopup()` | Pro feature lock popup |
| `showNotice(message, type)` | Displays auto-dismissing toast notifications |
| `positionPreview(e)` | Positions the floating image preview near cursor |
| `escHtml()` / `escAttr()` / `decodeHtml()` | Utility encoding/decoding functions |
