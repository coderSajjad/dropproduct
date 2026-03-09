# Smart Titles & Gallery Grouping

## Smart Titles — What Does It Do?

When you upload an image, the filename is automatically converted into a clean product title:

| Filename | Product Title |
|----------|-------------|
| `blue-hoodie.jpg` | Blue Hoodie |
| `red_t_shirt.jpg` | Red T Shirt |
| `leather-belt-01.jpg` | Leather Belt |
| `summer-dress-final-3.jpg` | Summer Dress Final |

### How It Works
1. The file extension is removed (`.jpg`, `.png`, etc.)
2. Trailing numbers are stripped (`-01`, `-3`, `_2`)
3. Hyphens and underscores are replaced with spaces
4. Each word is capitalized

---

## Gallery Grouping — What Does It Do?

If you upload multiple images that share the same base name (differing only by numbers), they're automatically grouped into **one product**:

```
blue-hoodie-1.jpg   →  Featured Image (main product photo)
blue-hoodie-2.jpg   →  Gallery Image
blue-hoodie-3.jpg   →  Gallery Image
```

Result: **1 product** called "Blue Hoodie" with a 3-image gallery.

---

## Examples

### Example 1: Single Product Images
```
belt.jpg          → 1 product: "Belt" (1 image)
hat.jpg           → 1 product: "Hat" (1 image)
scarf.jpg         → 1 product: "Scarf" (1 image)
```

### Example 2: Products with Galleries
```
shoe-1.jpg        → 1 product: "Shoe" (3 images)
shoe-2.jpg           ↑ gallery
shoe-3.jpg           ↑ gallery
jacket-1.jpg      → 1 product: "Jacket" (2 images)
jacket-2.jpg         ↑ gallery
```

### Example 3: Mixed Upload
```
ring.jpg          → 1 product: "Ring" (1 image)
necklace-1.jpg    → 1 product: "Necklace" (3 images)
necklace-2.jpg       ↑ gallery
necklace-3.jpg       ↑ gallery
bracelet.jpg      → 1 product: "Bracelet" (1 image)
```

---

## Tips

- The **first image** in each group becomes the **featured image** (main product photo)
- Additional images become **gallery images** (shown in the product image carousel)
- The gallery count is shown as a badge on the thumbnail (e.g., "+3")
- Name your images carefully — the naming determines how they're grouped
- Use **DropProduct Pro** for advanced grouping with ignore words and prefix/suffix stripping
