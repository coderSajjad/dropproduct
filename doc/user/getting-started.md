# Getting Started with Uploady

Welcome to **Uploady** — the fastest way to create WooCommerce products from images.

---

## What Does It Do?

Instead of creating products one at a time in WooCommerce, Uploady lets you:
1. **Drag & drop** a bunch of product images
2. Products are **created automatically** with titles from filenames
3. **Edit** prices, categories, descriptions, and more right in the grid
4. **Publish all** products in one click

What used to take hours now takes minutes.

---

## Requirements

- WordPress 5.8 or higher
- WooCommerce 6.0 or higher
- PHP 7.4 or higher

---

## Installation

1. Upload the `uploady` folder to `/wp-content/plugins/`
2. Go to **Plugins** in your WordPress admin
3. Click **Activate** on **Uploady**
4. Visit **Uploady** in the admin sidebar

---

## Quick Start Guide

### Step 1: Open Uploady
Click **Uploady** in the WordPress admin sidebar. You'll see the dropzone and an empty product grid.

### Step 2: Upload Images
Drag product images onto the dropzone, or click **"Browse Files"** to select them from your computer.

**Supported formats:** JPEG, PNG, GIF, WebP

### Step 3: Images Become Products
Each image (or group of related images) automatically becomes a draft product. The product title is generated from the filename:
- `blue-hoodie.jpg` → **Blue Hoodie**
- `red-t-shirt-01.jpg` → **Red T Shirt**

### Step 4: Edit Product Details
Edit product information directly in the grid:
- **Title** — Click to edit the product name
- **Description** — Click the pencil icon to open the description editor
- **Regular Price** — Enter the standard product price
- **Sale Price** — Optionally enter a discounted price
- **SKU** — Set a unique product code
- **Stock** — In stock, Out of stock, or On backorder
- **Category** — Select from existing WooCommerce categories

Changes are **saved automatically** as you edit — no "Save" button needed!

### Step 5: Publish
When everything looks good, click the **"Publish All"** button. All draft products become live on your store.

---

## Features at a Glance

| Feature | What It Does |
|---------|-------------|
| 🖼️ Drag & Drop Upload | Drop images to create products instantly |
| 📝 Smart Titles | Filenames are converted to clean product titles |
| 🖼️ Gallery Grouping | Related images (same name, different numbers) are grouped |
| ✏️ Grid Editor | Edit all product fields inline without page reloads |
| 💾 Auto-Save | Changes save automatically via AJAX |
| 💰 Regular & Sale Price | Set both regular and sale prices per product |
| ⚠️ Price Validation | Warns if sale price is higher than regular price |
| 📋 Description Editor | Add product descriptions via a popup editor |
| 🔍 Hover Preview | Hover over thumbnails to see full-size images |
| 🚀 Batch Publish | Publish all products in one click |
| ⚠️ Field Highlighting | Missing required fields are highlighted in red |
| 📦 Simple Products | Creates WooCommerce Simple Products |

---

## Image Naming Tips

Name your files descriptively for best results:

| ✅ Good Names | ❌ Bad Names |
|-------------|------------|
| `blue-hoodie.jpg` | `IMG_0001.jpg` |
| `red-t-shirt-01.jpg` | `DSC_3421.jpg` |
| `leather-belt.jpg` | `photo.jpg` |

### Gallery Grouping
If you have multiple images for one product, number them:
```
blue-hoodie-1.jpg  → Featured image
blue-hoodie-2.jpg  → Gallery image
blue-hoodie-3.jpg  → Gallery image
```

All three will become one product called "Blue Hoodie" with a 3-image gallery.

---

## Limitations (Free Version)

The free version supports **simple products only**. For advanced features, check out [Uploady Pro](https://Uploady.dev/pro):

- Variable products (color/size variations)
- Bulk editing (change many products at once)
- Validation dashboard
- SEO tools
- Session management & rollback
- Performance controls
- Template presets
- Activity log
