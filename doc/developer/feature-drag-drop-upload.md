# Feature: Drag & Drop Bulk Image Upload

## Goal

Replace the tedious WooCommerce product creation process (one product at a time) with a simple drag-and-drop interface that creates products from images instantly.

---

## Mechanism

### Frontend (`admin-uploady.js`)

The dropzone element listens for `dragenter`, `dragover`, `dragleave`, `drop`, and `click` events. On file drop or selection:

1. Files are filtered for allowed MIME types (JPEG, PNG, GIF, WebP)
2. A `FormData` object is built with all valid image files
3. Files are sent via `$.ajax()` POST to `wc_uploady_upload_images`
4. Upload progress is shown via an animated progress bar on the dropzone
5. On success, returned product data is rendered as grid rows
6. On error, a toast notification is shown

```javascript
uploadFiles: function (files) {
    var formData = new FormData();
    formData.append('action', 'wc_uploady_upload_images');
    formData.append('nonce', wcUploady.nonce);

    for (var i = 0; i < files.length; i++) {
        if (files[i].type.indexOf('image/') === 0) {
            formData.append('images[]', files[i]);
        }
    }

    $.ajax({
        url: wcUploady.ajaxUrl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        xhr: function () {
            var xhr = new XMLHttpRequest();
            xhr.upload.addEventListener('progress', function (e) {
                if (e.lengthComputable) {
                    var pct = Math.round((e.loaded / e.total) * 100);
                    // Update progress bar
                }
            });
            return xhr;
        },
        success: function (response) { /* render products */ },
        error: function () { /* show error notice */ }
    });
}
```

### Upload Progress UI

During upload the dropzone switches states:
1. Inner content (icon, text, button) is hidden
2. Progress container is shown with an animated fill bar
3. Percentage text updates in real-time via XHR `progress` event
4. After completion (success or error), dropzone resets to default state via `resetDropzone()`

### Backend — Batch Upload (`WC_Uploady_Ajax::handle_upload_images()`)

1. Calls `verify_request()` — validates nonce + capability
2. Normalizes the `$_FILES` array via `normalize_files_array()` (PHP sends multi-file arrays in a messy format)
3. Validates each file against `allowed_mime_types()`:
   - `image/jpeg`, `image/png`, `image/gif`, `image/webp`
4. Uses `media_handle_sideload()` to save each file to the WordPress Media Library
5. Passes all attachment IDs to `WC_Uploady_Grouping_Engine::group()`
6. Creates draft products via `WC_Uploady_Product_Service::create_draft_product()`
7. Returns formatted product data array for the grid

### Backend — Single Image Upload (`WC_Uploady_Ajax::handle_upload_single_image()`)

An alternative upload endpoint for single-file-at-a-time uploads:

1. Receives one file in `$_FILES['image']`
2. Validates MIME type
3. Uploads via `media_handle_sideload()`
4. Returns the `attachment_id` and sanitized `filename`

After all single uploads complete, the client calls `handle_create_products()` with collected attachment IDs to trigger grouping and product creation.

### Backend — Create Products (`WC_Uploady_Ajax::handle_create_products()`)

1. Receives an array of `attachment_ids` via POST
2. Groups them via `WC_Uploady_Grouping_Engine::group()`
3. Creates draft products for each group
4. Returns formatted product data

### Allowed MIME Types

```php
private function allowed_mime_types() {
    return array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
}
```

### `normalize_files_array()` — Handling PHP's Multi-File Format

PHP sends multi-file uploads in a confusing structure:
```php
// $_FILES['images'] = { name: [f1, f2], tmp_name: [t1, t2], ... }
// This normalizes to: [ { name: f1, tmp_name: t1, ... }, { name: f2, ... } ]
```

---

## Key Files

| File | Role |
|------|------|
| `admin-uploady.js` → `uploadFiles()` | Client-side upload handler with XHR progress |
| `admin-uploady.js` → `resetDropzone()` | Reset dropzone to default state after upload |
| `class-wc-uploady-ajax.php` → `handle_upload_images()` | Batch upload processor |
| `class-wc-uploady-ajax.php` → `handle_upload_single_image()` | Single image upload processor |
| `class-wc-uploady-ajax.php` → `handle_create_products()` | Product creation from attachment IDs |
| `uploady-page.php` | Dropzone HTML template + progress bar |
| `admin-uploady.css` | Dropzone styling (drag states, progress bar, animations) |
