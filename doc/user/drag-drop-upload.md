# Drag & Drop Upload

## What Does It Do?

Upload product images by dragging them onto the WooUpload page. Each image automatically becomes a WooCommerce product — no forms, no manual data entry needed.

---

## How to Use

### Method 1: Drag & Drop
1. Open a folder with your product images
2. Select the images you want to upload
3. Drag them onto the **dropzone area** on the WooUpload page
4. Drop them — products are created instantly!

### Method 2: Browse Files
1. Click the **"Browse Files"** button in the dropzone
2. Select images from your computer
3. Click "Open" — products are created

---

## Supported Image Formats

| Format | Extension |
|--------|-----------|
| JPEG | `.jpg`, `.jpeg` |
| PNG | `.png` |
| GIF | `.gif` |
| WebP | `.webp` |

> **Note:** Other file types (PDF, SVG, etc.) are not supported and will be skipped.

---

## What Happens When You Upload

1. **Images are uploaded** to your WordPress Media Library
2. **Products are created** as drafts (not published yet)
3. **Titles are generated** from filenames (`blue-hoodie.jpg` → "Blue Hoodie")
4. **Related images are grouped** into one product gallery
5. **Products appear** in the grid below, ready for editing

---

## How Many Images Can I Upload?

You can upload as many images as your server allows. The limit depends on:
- **upload_max_filesize** — Max size per file
- **post_max_size** — Max total upload size
- **max_file_uploads** — Max number of files per request

> **Tip:** If you have many images, upload in batches of 10-20 files at a time for best reliability.

---

## Tips

- Name your files descriptively before uploading — the filename becomes the product title
- Use hyphens (`-`) or underscores (`_`) to separate words in filenames
- Number related images (`hoodie-1.jpg`, `hoodie-2.jpg`) to group them as one product
- Images are uploaded to the WordPress Media Library and can be reused elsewhere
