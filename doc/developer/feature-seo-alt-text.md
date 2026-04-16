# Feature: Smart SEO Alt-Text Automator

> **Since:** v1.0.1  
> **Files:** `includes/class-dropproduct-ajax.php`, `admin/views/settings-page.php`

---

## Overview

The SEO Alt-Text Automator intercepts each image attachment created during the DropProduct upload flow and assigns a generated alt-text string derived from the filename — but only when the `_wp_attachment_image_alt` field is currently empty.

---

## Configuration

Stored in `get_option('dropproduct_settings')` under key `auto_alt_text` (boolean).

The setting is toggled in **DropProduct → ⚙️ Settings**. The read:

```php
$settings  = get_option( 'dropproduct_settings', [] );
$is_active = ! empty( $settings['auto_alt_text'] );
```

---

## PHP Implementation

### Entry Point

`DropProduct_Ajax::handle_upload_images()` calls `maybe_apply_auto_alt_text()` immediately after each successful `media_handle_sideload()`:

```php
$this->maybe_apply_auto_alt_text( $attachment_id, $file['name'] );
```

### `maybe_apply_auto_alt_text( int $attachment_id, string $filename ): void`

```php
private function maybe_apply_auto_alt_text( $attachment_id, $filename ) {
    $settings = get_option( 'dropproduct_settings', [] );
    if ( empty( $settings['auto_alt_text'] ) ) {
        return; // Feature disabled — bail early.
    }

    // Only set if the field is currently empty.
    $existing = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
    if ( ! empty( $existing ) ) {
        return;
    }

    $alt = $this->generate_alt_text_from_filename( $filename );
    if ( ! empty( $alt ) ) {
        update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
    }
}
```

### `generate_alt_text_from_filename( string $filename ): string`

Transformation pipeline:

```
blue-slim-fit-jeans.jpg
    → wp_basename()            : blue-slim-fit-jeans.jpg
    → pathinfo(PATHINFO_FILENAME): blue-slim-fit-jeans
    → str_replace(['-','_'],' '): blue slim fit jeans
    → preg_replace('/\s+/',' ') : blue slim fit jeans
    → trim()                   : blue slim fit jeans
    → strtolower() + ucfirst() : Blue Slim Fit Jeans
    → sanitize_text_field()    : Blue Slim Fit Jeans
```

---

## Meta Key

`_wp_attachment_image_alt` — WordPress core attachment meta. Used natively by the block editor, Classic Media Library, Yoast SEO, Rank Math, and all WooCommerce image rendering functions.

---

## Design Decisions

**Why only on empty fields?**  
Prevents overwriting alt text that was manually entered by the store owner or an SEO plugin. The automator is meant to fill gaps, not overwrite intentional edits.

**Why not hook into `add_attachment`?**  
`add_attachment` fires for all uploads system-wide. Hooking there would risk interfering with unrelated media uploads (e.g., post featured images, logo uploads). The current approach gates on the DropProduct upload flow explicitly.

**Why not a WP-CLI or batch command?**  
This is intentionally scoped to new uploads only. Batch alt-text generation for existing media is a candidate for a future Pro feature.

---

## Extending the Alt-Text Generator

Replace or extend the generation logic using the WordPress option:

```php
// Override the generated alt text with your own logic.
add_filter( 'dropproduct_generated_alt_text', function( $alt, $attachment_id, $filename ) {
    // Example: append the site name.
    return $alt . ' – ' . get_bloginfo( 'name' );
}, 10, 3 );
```

> **Note:** The `dropproduct_generated_alt_text` filter is a reserved hook for v1.0.3. The current implementation does not call it — patch `generate_alt_text_from_filename()` to add the `apply_filters()` call if needed.
