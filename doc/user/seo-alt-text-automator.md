# Smart SEO Alt-Text Automator — User Guide

> **Available in:** DropProduct Free (v1.0.1+)

Automatically assign SEO-friendly alt text to every product image you upload through DropProduct — saving time and improving search engine visibility.

---

## Enabling the Feature

1. Go to **DropProduct → ⚙️ Settings**
2. Toggle **Smart SEO Alt-Text Automator** to ON
3. Save settings

Once enabled, alt text is generated automatically every time you upload an image through the DropProduct drag-and-drop zone.

---

## How Alt Text is Generated

The alt text is derived from the image **filename** using these steps:

1. Strip the file extension (`.jpg`, `.png`, etc.)
2. Replace hyphens (`-`) and underscores (`_`) with spaces
3. Collapse multiple consecutive spaces
4. Convert to **Title Case** (first letter of every word capitalised)
5. Sanitize for safe database storage

**Examples:**

| Filename | Generated Alt Text |
|----------|--------------------|
| `blue-slim-fit-jeans.jpg` | Blue Slim Fit Jeans |
| `womens_red_floral_dress.png` | Womens Red Floral Dress |
| `leather-handbag-v2.jpg` | Leather Handbag V2 |

---

## Important: No Overwrites

The automator only sets alt text when the field is **currently empty**. If you or another tool has already set an alt text for an image, it is left completely untouched.

---

## Tips for Best Results

- Name your image files descriptively before uploading: `black-leather-boots` is much better than `IMG_4521`.
- Use hyphens or underscores between words — both work equally well.
- For existing images without alt text, re-upload them through DropProduct to trigger generation automatically.
