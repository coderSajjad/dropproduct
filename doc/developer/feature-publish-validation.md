# Feature: Batch Publish & Validation

## Goal

Publish all draft products at once with built-in validation to catch missing required fields before they go live.

---

## Batch Publish

### Mechanism

**Frontend:** `admin-dropproduct.js` → `publishAll()`

1. Selects all draft rows (excludes already-published via `.is-published`)
2. Clears previous validation classes (`has-error`, `is-error`)
3. Validates each row — title and regular price are required
4. Rows with errors get `has-error` class, invalid fields get `is-error`
5. Valid product IDs are collected
6. If all rows have errors, shows validation error message and stops
7. Otherwise: disables Publish button, sends AJAX POST to `dropproduct_publish_all`
8. On response:
   - Published rows: adds `is-published` class, status badge changes from "draft" to "publish"
   - Failed rows: adds `has-error` class
   - Shows summary notice: "X product(s) published. Y failed validation."
9. Calls `updateDraftCount()` to refresh the counter

### Client-Side Validation

```javascript
$rows.each(function () {
    var $row   = $(this);
    var title  = $row.find('[data-field="title"]').val().trim();
    var price  = $row.find('[data-field="regular_price"]').val().trim();
    var valid  = true;

    if (!title) {
        $row.find('[data-field="title"]').addClass('is-error');
        valid = false;
    }

    if (!price) {
        $row.find('[data-field="regular_price"]').addClass('is-error');
        valid = false;
    }

    if (!valid) {
        $row.addClass('has-error');
        hasErrors = true;
    } else {
        productIds.push($row.data('product-id'));
    }
});
```

**Backend:** `DropProduct_Ajax::handle_publish_all()`

1. Receives `product_ids[]` array
2. Iterates through each product ID
3. Calls `Product_Service::publish_product()` for each
4. `publish_product()` runs `validate_for_publish()` first
5. Returns separate arrays of published and failed product IDs

---

## Server-Side Validation

### Built-in Checks

`DropProduct_Product_Service::validate_for_publish()`:

| Check | Condition | Error |
|-------|-----------|-------|
| Title required | `empty(trim($product->get_name()))` | "Title is required." |
| Price required | `'' === $product->get_regular_price()` | "Price is required." |

### Extension Point

```php
$errors = apply_filters('dropproduct_validate_product', $errors, $product);
```

Pro adds: category check, duplicate SKU, broken image, variation conflicts.

### Publish Flow (Server-Side)

```php
public function publish_product($product_id) {
    $product = wc_get_product($product_id);
    $errors = $this->validate_for_publish($product);

    if (!empty($errors)) {
        return new WP_Error('validation_failed', implode(', ', $errors));
    }

    $product->set_status('publish');
    $product->save();

    do_action('dropproduct_after_publish_product', $product_id);

    return true;
}
```

---

## Required Field Highlighting (CSS)

Fields with `is-error` class get a red border. The row itself gets `has-error` for a light red background:

```css
.dropproduct-grid tbody tr.has-error {
    background: var(--wu-danger-light);  /* #fef2f2 */
}

.dropproduct-grid tbody tr.has-error .dropproduct-editable.is-error {
    border-color: var(--wu-danger);      /* #dc2626 */
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15);
}
```

---

## Draft Count & Button State

`updateDraftCount()` counts non-published rows and:
- Updates the `#dropproduct-draft-count` text
- Disables the Publish All button when count is 0

---

## Key Files

| File | Role |
|------|------|
| `admin-dropproduct.js` → `publishAll()` | Client-side validation + batch publish request |
| `admin-dropproduct.js` → `updateDraftCount()` | Draft counter and button state management |
| `class-dropproduct-ajax.php` → `handle_publish_all()` | Server-side batch publish handler |
| `class-dropproduct-product-service.php` → `publish_product()` | Single product publish + validation |
| `class-dropproduct-product-service.php` → `validate_for_publish()` | Validation rules |
| `admin-dropproduct.css` | Error highlighting, status badge styles |
