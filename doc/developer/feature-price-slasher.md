# Feature: Quick Bulk Price Adjuster (Price Slasher)

> **Since:** v1.0.1  
> **Files:** `includes/class-dropproduct-ajax.php`, `assets/js/admin-dropproduct.js`, `assets/css/admin-dropproduct.css`, `admin/views/dropproduct-page.php`

---

## Overview

The Price Slasher allows store managers to apply a percentage or fixed-amount price adjustment to any number of selected products simultaneously. The bar is toggled via a persistent toolbar button and operates entirely client-side for the UI, with a single batched AJAX call to persist all changes.

---

## PHP: AJAX Handler

**Class:** `DropProduct_Ajax`  
**Method:** `handle_bulk_price_adjust()`  
**WP action hook:** `wp_ajax_dropproduct_bulk_price_adjust`

### Request Parameters (POST)

| Field | Type | Description |
|-------|------|-------------|
| `nonce` | string | Verified against `dropproduct_nonce` |
| `product_ids` | int[] | Array of product IDs to adjust |
| `price_field` | string | `'regular_price'` \| `'sale_price'` \| `'both'` |
| `operation` | string | `'increase'` \| `'decrease'` |
| `type` | string | `'percentage'` \| `'fixed'` |
| `amount` | float | Adjustment amount (always positive) |

### Response Shape (v1.0.2 refactor)

```json
{
  "success": true,
  "data": {
    "products": [
      { "id": 123, "regular_price": "25.00", "sale_price": "19.99" },
      { "id": 124, "regular_price": "30.00", "sale_price": "" }
    ]
  }
}
```

> **Breaking change from v1.0.1:** The response was previously `{ fields: [{ name, value }, ...] }` per product. Refactored to a flat object per product for reliable DOM targeting by input `[name]`.

### Calculation Logic

```php
// Percentage
$new = $operation === 'increase'
    ? $current * ( 1 + $amount / 100 )
    : $current * ( 1 - $amount / 100 );

// Fixed
$new = $operation === 'increase'
    ? $current + $amount
    : $current - $amount;

// Floor at 0, round to 2dp
$new = max( 0, round( $new, 2 ) );
```

**Sale price auto-clear:** After adjustment, if `sale_price >= regular_price`, `sale_price` is set to `''` (cleared).

---

## JavaScript

### State Variables (on `DropProduct` object)

| Variable | Type | Description |
|----------|------|-------------|
| `_selectedIds` | `{[id: string]: true}` | Set of currently selected product IDs |
| `_slasherOp` | `'increase' \| 'decrease'` | Active operation |
| `_slasherOpen` | boolean | Whether the bar is visible |

### Key Methods

#### `toggleSlasherBar()`
Shows/hides the `#dropproduct-slasher-bar` element and syncs the toolbar button badge count.

#### `applyPriceSlasher()`
1. Reads `_selectedIds`, `_slasherOp`, `$slasherAmount.val()`, `$slasherType.val()`, `$slasherField.val()`
2. POSTs to `dropproduct_bulk_price_adjust`
3. On success: iterates `response.data.products`, finds each row by `data-product-id`, updates `[data-field="regular_price"]` and `[data-field="sale_price"]` inputs, triggers `calculateFinancials()` on each row

#### Row selection (`_selectedIds` management)
- `.dropproduct-row-check` change → add/remove from `_selectedIds`, toggle `.is-selected` class on `<tr>`
- `#dropproduct-select-all` change → sync all visible rows
- Badge on toolbar button and inside bar are updated on every change via `updateSlasherBadge()`

### Toolbar Toggle Button (HTML)

```html
<button type="button" id="dropproduct-slasher-toggle-btn"
        class="dropproduct-slasher-toggle-btn button">
    <span class="dashicons dashicons-tag"></span>
    ⚡ Price Slasher
    <span class="dropproduct-slasher-count" id="dropproduct-slasher-count"></span>
</button>
```

---

## CSS Architecture (v1.0.2 Light Theme)

The Slasher bar uses a **light theme** (white background, indigo left-border accent). Key design tokens:

```css
.dropproduct-slasher-bar {
    background: #fff;
    border-left: 4px solid #4f46e5;
    box-shadow: 0 4px 24px rgba(0,0,0,0.09);
}
```

All inputs, dropdowns and labels inside the bar use dark-coloured text on a light background to ensure WCAG-level contrast without relying on dark mode.

---

## Extending Price Slasher

The `price_field` parameter can be extended via JS to support custom meta fields. On the PHP side, add the new field to the `switch` in `handle_bulk_price_adjust()`:

```php
case 'cost_price':
    $cost = (float) get_post_meta( $product_id, '_dropproduct_cost_price', true );
    // apply calculation...
    update_post_meta( $product_id, '_dropproduct_cost_price', $new );
    break;
```
