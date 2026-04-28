# Getting Started with DropProduct

Welcome to **DropProduct** — the fastest way to create WooCommerce products from images.

---

## What Does It Do?

Instead of creating products one at a time in WooCommerce, DropProduct lets you:
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

1. Upload the `dropproduct` folder to `/wp-content/plugins/`
2. Go to **Plugins** in your WordPress admin
3. Click **Activate** on **DropProduct**
4. Visit **DropProduct** in the admin sidebar

---

## Quick Start Guide

### Step 1: Open DropProduct
Click **DropProduct** in the WordPress admin sidebar. You'll see the dropzone and an empty product grid.

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
| 📊 Sales Analytics | View revenue, orders, products, countries, and trends in a modern dashboard |

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

The free version supports **simple products only**. For advanced features, check out [DropProduct Pro](https://dropproduct.dev/pro):

- Variable products (color/size variations)
- Bulk editing (change many products at once)
- Validation dashboard
- SEO tools
- Session management & rollback
- Performance controls
- Template presets
- Activity log

Sales Analytics is included in the free version and gives you a quick view of sales performance directly inside WordPress admin.

---

## Sales Analytics

DropProduct includes a built-in Sales Analytics dashboard (DropProduct → Sales Analytics). It provides quick, actionable metrics for DropProduct-created products: revenue, orders, top products, countries, and time-series trends.

- Requirements: WooCommerce active, orders in the store, and products created by DropProduct (products have the `_dropproduct_product` postmeta set to `1`).
- How it works: the dashboard loads aggregated data via an AJAX endpoint and renders charts with Chart.js. Charts and CSV export are client-side; data is served by the `DropProduct_Analytics` service in the PHP `includes/` folder.
- Demo data: a sample SQL file for testing is included at `admin/sql/dropproduct-demo-data.sql`. Import it into your site database (via WP-CLI, phpMyAdmin, or other DB tools) to populate orders/products for the demo dashboard.

Quick test:
1. Import `admin/sql/dropproduct-demo-data.sql` into your site database.
2. Visit **DropProduct → Sales Analytics** and choose a date range.
3. Open your browser DevTools Network tab and confirm `admin-ajax.php?action=dropproduct_get_analytics` returns JSON.

Notes:
- The analytics dashboard is intentionally lightweight; if you need richer channel/device attribution integrate your tracking system and map UTM/GA data into your WooCommerce orders (or extend the analytics service in `includes/class-dropproduct-analytics.php`).
- For privacy and performance, analytics respects WordPress capability checks and only exposes data to users with `manage_woocommerce` capability.
