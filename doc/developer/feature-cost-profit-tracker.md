# Feature: Cost-to-Profit Tracker

> **Since:** v1.0.2  
> **Files:** `class-dropproduct-product-service.php`, `admin-dropproduct.js`, `admin-dropproduct.css`, `admin/views/dropproduct-page.php`

---

## Overview

The Cost-to-Profit Tracker extends the product grid with three new columns: **Cost Price** (editable input), **Profit** (read-only display), and **Margin %** (read-only display). Calculations run entirely client-side for instant feedback; the cost price is persisted asynchronously via debounced AJAX using the existing `dropproduct_update_product` endpoint.

---

## Data Storage

| Key | Type | Location |
|-----|------|----------|
| `_dropproduct_cost_price` | `decimal(10,2)` (stored as string) | `wp_postmeta` |

The meta key is private (prefixed with `_`). It is registered implicitly — no `register_meta()` call is required since the field is only accessed internally.

**Read:**
```php
(float) get_post_meta( $product_id, '_dropproduct_cost_price', true );
```

**Write (via `update_product_field`):**
```php
$cost = max( 0, (float) $value );
update_post_meta( $product_id, '_dropproduct_cost_price', wc_format_decimal( $cost ) );
```

---

## PHP Changes

### `DropProduct_Product_Service::format_product_data()`

Added `cost_price` to the returned array so it is included in every AJAX product response (initial load, upload, create):

```php
'cost_price' => (float) get_post_meta( $product->get_id(), '_dropproduct_cost_price', true ),
```

### `DropProduct_Product_Service::update_product_field()`

Added a `cost_price` case to the field switch. This case returns early (before `$product->save()`) because only `wp_postmeta` is updated — no WC product data changes.

```php
case 'cost_price':
    $cost = max( 0, (float) $value );
    update_post_meta( $product->get_id(), '_dropproduct_cost_price', wc_format_decimal( $cost ) );
    return true; // Skips the $product->save() below the switch.
```

---

## Table Columns (HTML)

Three `<th>` elements added to `admin/views/dropproduct-page.php` after the existing Actions column:

```html
<th class="dropproduct-col-cost" title="Your purchase cost">
    Cost Price
    <span class="dropproduct-col-hint">Not shown to customers</span>
</th>
<th class="dropproduct-col-profit">Profit</th>
<th class="dropproduct-col-margin">Margin %</th>
```

The empty-state `colspan` was bumped from `11` → `14` to account for the three new columns.

---

## JavaScript API

### `DropProduct.formatFinancials( regularPrice, salePrice, costPrice )`

**Pure function — no DOM access.** Used by `buildRow()` at render time.

```js
/**
 * @param {number|string} regularPrice
 * @param {number|string} salePrice
 * @param {number|string} costPrice
 * @returns {{
 *   profit: number,
 *   margin: number,
 *   profitHtml: string,   // coloured <span>
 *   marginHtml: string    // coloured <span>
 * }}
 */
formatFinancials( regularPrice, salePrice, costPrice )
```

Logic:
- Effective price = `salePrice > 0 ? salePrice : regularPrice`
- `profit = effectivePrice - costPrice`
- `margin = (profit / effectivePrice) * 100`
- Returns `—` HTML when cost or price is 0/empty

### `DropProduct.calculateFinancials( $row )`

Reads the current DOM values for all three price inputs in a row and calls `formatFinancials()`, then updates `.dropproduct-profit-display` and `.dropproduct-margin-display`.

Called on:
- `input` on `.dropproduct-cost-input`
- `blur` on `.dropproduct-editable[data-field="regular_price"]`
- `blur` on `.dropproduct-editable[data-field="sale_price"]`

### `DropProduct.saveCostPrice( $input )`

Posts to `dropproduct_update_product` with `field = 'cost_price'`. Applies `.is-saving` → `.is-saved` / `.is-error` classes to the input for visual feedback.

**Debounce:** Called 600 ms after last `input` event, or immediately on `blur`.

---

## CSS Classes

| Class | Purpose |
|-------|---------|
| `.dropproduct-col-cost` | `<th>/<td>` for Cost Price column (width: 105px) |
| `.dropproduct-col-profit` | `<th>/<td>` for Profit column (width: 90px, right-aligned) |
| `.dropproduct-col-margin` | `<th>/<td>` for Margin % column (width: 80px, right-aligned) |
| `.dropproduct-col-hint` | Small sub-label in column headers |
| `.dropproduct-cost-input` | The cost price `<input>` — frameless, matches price-wrap style |
| `.dropproduct-profit-display` | `<span>` wrapper for profit value |
| `.dropproduct-margin-display` | `<span>` wrapper for margin value |
| `.dp-finance-positive` | Green text for profitable values |
| `.dp-finance-negative` | Red text for loss values |
| `.dp-finance-na` | Grey dash for missing data |

---

## Extension Hook

The existing `dropproduct_format_product_data` filter passes the full `$data` array including `cost_price`. Pro add-ons can modify or extend it:

```php
add_filter( 'dropproduct_format_product_data', function( $data, $product ) {
    // Example: add gross margin category label
    $data['margin_category'] = $data['cost_price'] > 0 ? 'tracked' : 'untracked';
    return $data;
}, 10, 2 );
```
