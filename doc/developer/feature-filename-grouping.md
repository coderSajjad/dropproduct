# Feature: Smart Filename to Title Conversion & Gallery Grouping

## Goal

Automatically convert filenames like `blue-cotton-hoodie-01.jpg` into clean product titles like "Blue Cotton Hoodie", and group related images into single products with galleries.

---

## Key Class: `WC_Uploady_Grouping_Engine`

**File:** `includes/class-wc-uploady-grouping-engine.php`

### Public API

| Method | Signature | Description |
|--------|-----------|-------------|
| `group()` | `group(array $attachment_ids): array` | Groups attachment IDs by filename base and returns product groups |

### Return Format

```php
[
    [
        'title'    => 'Blue Cotton Hoodie',  // Humanized base name
        'featured' => 101,                    // First attachment ID
        'gallery'  => [102, 103],             // Remaining attachment IDs
    ],
    // ...
]
```

---

## Title Extraction Pipeline

The base name extraction uses two private methods:

### `extract_base_name($basename)` — Strip Numeric Suffixes

```php
private function extract_base_name($basename) {
    // Match trailing separator + digits: shoe-1, shoe_02, product-03.
    return preg_replace('/[\-_]\d+$/', '', $basename);
}
```

**Examples:**
| Input | Output | Reason |
|-------|--------|--------|
| `shoe-1` | `shoe` | Numeric suffix stripped |
| `shoe-2` | `shoe` | Same base — groups with shoe-1 |
| `shoe_03` | `shoe` | Underscore separator also works |
| `hat` | `hat` | No suffix to strip |
| `red-bag` | `red-bag` | `-bag` is not purely numeric |

### `humanize_title($base_name)` — Convert to Product Title

```php
private function humanize_title($base_name) {
    $title = str_replace(array('-', '_'), ' ', $base_name);
    return ucwords($title);
}
```

### Full Pipeline Example

```
Filename:     "blue-cotton-hoodie-01.jpg"
  → pathinfo:  "blue-cotton-hoodie-01"
  → strip num: "blue-cotton-hoodie"
  → humanize:  "Blue Cotton Hoodie"
```

---

## Gallery Grouping Mechanism

**Location:** `WC_Uploady_Grouping_Engine::group()`

### How It Works

1. For each attachment ID, retrieve the full file path via `get_attached_file()`
2. Extract the filename without extension using `pathinfo()`
3. Strip trailing numeric suffixes to get the base name
4. Bucket all attachment IDs by their base name
5. Apply the `wc_uploady_group_images` filter (extension point)
6. For each bucket:
   - First attachment → `featured` image
   - Remaining attachments → `gallery` images
   - Base name → humanized `title`

### Bucket Example

```
Uploads: shoe-1.jpg, shoe-2.jpg, shoe-3.jpg, hat-1.jpg, hat-2.jpg
                    ↓
Buckets:
  "shoe" → [attachment_101, attachment_102, attachment_103]
  "hat"  → [attachment_104, attachment_105]
                    ↓
Products:
  "Shoe" → featured: 101, gallery: [102, 103]
  "Hat"  → featured: 104, gallery: [105]
```

### Extension Point

```php
$buckets = apply_filters('wc_uploady_group_images', $buckets, $attachment_ids);
```

Pro hooks into this filter to add advanced grouping (ignore words, prefix/suffix stripping, AI-based grouping).

---

## Key Files

| File | Role |
|------|------|
| `class-wc-uploady-grouping-engine.php` → `group()` | Main grouping logic |
| `class-wc-uploady-grouping-engine.php` → `extract_base_name()` | Numeric suffix stripping |
| `class-wc-uploady-grouping-engine.php` → `humanize_title()` | Filename → title conversion |
