# Feature: Grid Editor, Auto-Save, Price Validation & Image Preview

## Goal

Provide an inline-editable product grid that saves changes via AJAX without page reloads, with hover previews for product images and client-side price validation.

---

## Grid Editor (SPA-Style)

### Mechanism

The grid is a standard HTML `<table>` where each row represents a product. Editable fields use `<input>` and `<select>` elements directly in the table cells.

**Key JS Method:** `buildRow(product)` in `admin-uploady.js`

Each row has `id="wc-uploady-row-{product_id}"` and `data-product-id="{id}"` for easy targeting.

### Grid Columns

| Column | Element | Data Attribute | Width |
|--------|---------|---------------|-------|
| Image | `<img>` thumbnail with hover preview | `data-full` (full-size URL) | 60px |
| Title | `<input type="text">` | `data-field="title"` | min 180px |
| Description | `<button>` (opens modal) + hidden `<input>` | — | 80px |
| Regular Price | `<input type="number">` inside `wc-uploady-price-wrap` | `data-field="regular_price"` | 100px |
| Sale Price | `<input type="number">` inside `wc-uploady-price-wrap` | `data-field="sale_price"` | 100px |
| SKU | `<input type="text">` | `data-field="sku"` | 120px |
| Stock | `<select>` (In stock / Out of stock / On backorder) | `data-field="stock_status"` | 120px |
| Category | `<select>` (populated from `wcUploady.categories`) | `data-field="category"` | 140px |
| Status | `<span>` badge (draft/publish) | — | 80px |
| Actions | Delete button | — | 80px |

### Price Display

Both Regular Price and Sale Price use the same price wrapper structure:

```html
<div class="wc-uploady-price-wrap">
    <span class="wc-uploady-currency">$</span>
    <input type="number" class="wc-uploady-editable wc-uploady-price-input"
           data-field="regular_price" step="0.01" min="0" placeholder="0.00" />
</div>
```

The currency symbol is displayed as a prefix label. The `price-wrap` div handles focus/hover border states via CSS.

### Gallery Badge

If a product has gallery images, a small badge is shown next to the thumbnail:

```html
<span class="wc-uploady-gallery-badge">+3</span>
```

---

## Auto-Save via AJAX

### Mechanism

All fields with class `wc-uploady-editable` trigger auto-save:
- **Text/number inputs** save on `blur` event
- **Select dropdowns** save on `change` event

**Key JS Method:** `saveField($field)` in `admin-uploady.js`

```javascript
saveField: function ($field) {
    var $row = $field.closest('tr');
    var productId = $row.data('product-id');
    var field = $field.data('field');
    var value = $field.val();

    $field.removeClass('is-saved is-error').addClass('is-saving');

    $.post(wcUploady.ajaxUrl, {
        action: 'wc_uploady_update_product',
        nonce: wcUploady.nonce,
        product_id: productId,
        field: field,
        value: value
    }, function (response) {
        $field.removeClass('is-saving');
        if (response.success) {
            $field.addClass('is-saved');
            setTimeout(function () { $field.removeClass('is-saved'); }, 1500);
        } else {
            $field.addClass('is-error');
        }
    });
}
```

### Visual State Classes

| CSS Class | Meaning | Styling |
|-----------|---------|---------|
| `is-saving` | Request in flight | Yellow/amber border + background |
| `is-saved` | Successfully saved | Green border + background (auto-clears after 1.5s) |
| `is-error` | Save failed | Red border + background |

**Backend Handler:** `WC_Uploady_Ajax::handle_update_product()`

Sanitizes input based on field type:
- `title`, `sku`: `sanitize_text_field()`
- `description`: `wp_kses_post()`
- `regular_price`, `sale_price`: `wc_format_decimal()`
- `stock_status`: validated against whitelist (`instock`, `outofstock`, `onbackorder`)
- `category`: `absint()` + `set_category_ids()`
- Other fields: delegated via `wc_uploady_update_custom_field` action

---

## Sale Price Validation

### Mechanism

**Key JS Method:** `validatePrices($row)`

When either the regular price or sale price field loses focus, client-side validation runs:

```javascript
validatePrices: function ($row) {
    var $regularInput = $row.find('[data-field="regular_price"]');
    var $saleInput    = $row.find('[data-field="sale_price"]');
    var regularPrice  = parseFloat($regularInput.val());
    var salePrice     = parseFloat($saleInput.val());

    // Remove previous warning
    $saleCell.find('.wc-uploady-price-warning').remove();

    // Validate: sale price must be lower than regular price
    if (salePrice >= regularPrice) {
        $saleInput.addClass('is-error');
        $saleCell.append(
            '<span class="wc-uploady-price-warning">' +
            '<span class="dashicons dashicons-warning"></span> ' +
            'Sale price must be lower than regular price.' +
            '</span>'
        );
    }
}
```

The warning appears as a red tooltip above the sale price cell, styled with CSS animation (`wcUploadyTooltipIn`).

---

## Hover Image Preview

### Mechanism

When the user hovers over a product thumbnail, a larger preview appears near the cursor.

**JS Implementation:**
- `mouseenter` on `.wc-uploady-thumb` — loads full-size URL from `data-full` attribute into preview `<img>`
- `mousemove` — updates preview position via `positionPreview(e)`
- `mouseleave` — hides preview and clears src

**Positioning Logic:**
```javascript
positionPreview: function (e) {
    var x = e.clientX + 16;
    var y = e.clientY + 16;

    // Prevent overflow off right edge
    if (x + 300 > window.innerWidth) x = e.clientX - 300;
    // Prevent overflow off bottom edge
    if (y + 300 > window.innerHeight) y = e.clientY - 300;

    this.$preview.css({ left: x + 'px', top: y + 'px' });
}
```

**CSS:** The preview uses `position: fixed`, `z-index: 100000`, `border-radius`, and `box-shadow` for a polished floating card effect. Max dimensions: 280×280px.

---

## Description Modal

### Mechanism

Short descriptions are edited via a modal popup instead of inline (to handle multiline text).

1. Click the description pencil icon → `openDescriptionModal($row)`
2. Modal loads current description from a hidden field (`<input class="wc-uploady-desc-value">`) in the row
3. Description is decoded from HTML entities via `decodeHtml()`
4. User edits in a `<textarea>`
5. Click "Save" → `saveDescription()` sends AJAX POST with `field: 'description'`
6. On success: hidden field is updated, dot indicator toggled, modal closes
7. The saved value is stored in the product's `short_description` via `$product->set_short_description()`

### Description Indicator

- Button class `has-desc` — when description exists, button turns green with a green status dot
- Green dot indicator (`<span class="wc-uploady-desc-dot">`) appears at top-right of the button
- When description is cleared, the dot and class are removed

---

## Key Files

| File | Role |
|------|------|
| `admin-uploady.js` → `buildRow()` | Row HTML generation (10 columns) |
| `admin-uploady.js` → `saveField()` | Auto-save handler |
| `admin-uploady.js` → `validatePrices()` | Client-side price validation |
| `admin-uploady.js` → `bindEvents()` | All event listeners |
| `admin-uploady.js` → `positionPreview()` | Image preview positioning |
| `admin-uploady.js` → `openDescriptionModal()` / `saveDescription()` | Description modal workflow |
| `class-wc-uploady-ajax.php` → `handle_update_product()` | Server-side field update |
| `class-wc-uploady-product-service.php` → `update_product_field()` | WC CRUD operations |
| `uploady-page.php` | Grid table structure + description modal HTML |
| `admin-uploady.css` | Saving/saved/error states, price-wrap, preview styles, tooltip animation |
