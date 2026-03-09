=== DropProduct – Bulk Product Uploader for WooCommerce ===
Contributors: codersajjad
Tags: woocommerce, bulk product upload, product creator, drag drop upload, woocommerce bulk edit
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bulk create WooCommerce products from images. Drag & drop, auto-generate titles, edit inline, and publish in one click.

== Description ==

**DropProduct** is the fastest way to add products to your WooCommerce store. Stop wasting hours creating products one by one — just drag & drop your product images and let DropProduct do the rest.

Upload 10, 50, or 100+ product images at once. Each image instantly becomes a draft product with a clean title generated from the filename. Edit prices, categories, SKUs, and descriptions right in the grid — everything saves automatically. When you're ready, publish all products in a single click.

Whether you're launching a new store, restocking your catalog, or migrating from another platform, DropProduct turns hours of tedious work into minutes.

= ⚡ How It Works =

1. **Drag & drop** your product images onto the upload zone
2. **Products are created automatically** as drafts with smart titles from filenames
3. **Related images are grouped** — `shoe-1.jpg` and `shoe-2.jpg` become one product with a gallery
4. **Edit inline** — title, description, regular price, sale price, SKU, stock, and category
5. **Publish all** valid products in one click

= 🎯 Who Is This For? =

* **New store owners** building their first product catalog
* **Dropshippers** adding hundreds of products quickly
* **Photographers & artists** selling prints and digital products
* **Wholesalers** managing large inventories
* **Anyone** tired of WooCommerce's slow, one-at-a-time product creation

= 🆓 Free Features =

* **Drag & Drop Bulk Upload** — Upload unlimited product images at once (JPEG, PNG, GIF, WebP)
* **Smart Title Generation** — Filenames like `blue-cotton-hoodie.jpg` become "Blue Cotton Hoodie" automatically
* **Gallery Grouping** — Images sharing a base name (`shoe-1.jpg`, `shoe-2.jpg`, `shoe-3.jpg`) merge into one product with a gallery
* **Inline Grid Editor** — Edit title, description, regular price, sale price, SKU, stock status, and category directly in the table
* **Auto-Save** — Every change saves instantly via AJAX — no save button needed
* **Sale Price Validation** — Warns you if the sale price is higher than the regular price
* **Description Editor** — Add product descriptions via a clean popup modal
* **Hover Image Preview** — Hover over any thumbnail to see the full-size image
* **Batch Publish with Validation** — Publish all drafts in one click; missing title or price fields are highlighted in red
* **Draft Counter** — See how many unpublished products you have at a glance
* **HPOS Compatible** — Fully compatible with WooCommerce High-Performance Order Storage
* **Zero Bloat** — Assets only load on the DropProduct page; no impact on the rest of your admin

= ⭐ Pro Features =

Unlock the full power of DropProduct with [DropProduct Pro](https://dropproduct.dev/pro):

* **Bulk Editing** — Select multiple products and set price, category, stock, tax class, or shipping class for all of them at once
* **Product Duplication** — Clone any product row with one click
* **Validation Dashboard** — Pre-publish validation report with issue breakdown: missing prices, categories, duplicate SKUs, broken images
* **Variable Product Support** — Auto-detect color/size variations from filenames and create WooCommerce Variable Products with attributes
* **Advanced Grouping Engine** — Strip common words (front, back, final, hd), custom prefixes/suffixes for smarter image grouping
* **Template Presets** — Set default category, stock status, tax class, and shipping class — auto-applied to every new product
* **Session Management** — Track upload sessions, filter products by session, and rollback entire sessions if needed
* **SEO Tools** — Edit URL slugs, meta descriptions (Yoast + Rank Math compatible), and image ALT text inline
* **Activity Log** — Full audit trail of all actions: creates, publishes, deletes, bulk edits
* **Performance Controls** — Configure batch size, safe mode, and retry attempts based on your server
* **Server Info Dashboard** — View PHP version, memory limits, and upload size limits at a glance

= 💡 Smart Image Naming Tips =

Name your files descriptively for the best results:

* `blue-hoodie.jpg` → **Blue Hoodie** (1 product, 1 image)
* `blue-hoodie-1.jpg`, `blue-hoodie-2.jpg`, `blue-hoodie-3.jpg` → **Blue Hoodie** (1 product, 3-image gallery)
* `leather-belt.jpg` → **Leather Belt** (1 product, 1 image)

Use hyphens (`-`) or underscores (`_`) to separate words. Trailing numbers are stripped automatically.

= 🔒 Safe & Secure =

* All products start as **drafts** — nothing goes live until you click Publish
* Every request is **nonce-protected** and capability-checked (`manage_woocommerce`)
* All inputs are **sanitized and escaped** — no raw database queries
* Built on the **WooCommerce CRUD API** for maximum compatibility
* Products created by DropProduct are **tagged with meta** so they never interfere with your existing products

= 🚀 Built for Speed =

* **SPA-style interface** — No page reloads, everything runs via AJAX
* **Assets load only on the DropProduct page** — Zero global admin impact
* **Lightweight codebase** — Clean, well-structured PHP and JavaScript
* **No external dependencies** — No bloated frameworks or libraries

== Installation ==

1. Upload the `DropProduct` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Make sure **WooCommerce** is installed and active
4. Navigate to **DropProduct** in the admin sidebar to start creating products

== Frequently Asked Questions ==

= Does DropProduct require WooCommerce? =

Yes. WooCommerce must be installed and active. DropProduct requires WooCommerce 6.0 or higher.

= What image formats are supported? =

DropProduct supports JPEG (.jpg, .jpeg), PNG (.png), GIF (.gif), and WebP (.webp) image formats.

= What product types does the free version support? =

The free version creates WooCommerce **Simple Products**. Variable product support with automatic attribute detection is available in [DropProduct Pro](https://dropproduct.dev/pro).

= How does the smart image grouping work? =

If you upload files with the same base name but different trailing numbers — like `shoe-1.jpg`, `shoe-2.jpg`, `shoe-3.jpg` — they are grouped into a **single product** called "Shoe". The first image becomes the featured image, and the rest become gallery images.

= Will this plugin slow down my admin? =

No. DropProduct loads its CSS and JavaScript **only on the DropProduct admin page**. It adds zero overhead to the rest of your WordPress admin.

= Is it safe to use on a live store? =

Absolutely. All products are created as **drafts** first. Nothing is published until you click "Publish All" and validation passes. Products created by DropProduct are tagged separately and never interfere with your existing products.

= Can I upload hundreds of images at once? =

Yes, but your server's `upload_max_filesize`, `post_max_size`, and `max_file_uploads` PHP settings determine the upper limit. For best reliability, upload in batches of 10–20 images. [DropProduct Pro](https://dropproduct.dev/pro) adds configurable batch size and auto-retry for large uploads.

= Does DropProduct work with WooCommerce HPOS? =

Yes. DropProduct is fully compatible with WooCommerce High-Performance Order Storage (HPOS).

= What fields can I edit in the grid? =

You can edit: **Title**, **Short Description**, **Regular Price**, **Sale Price**, **SKU**, **Stock Status** (In stock / Out of stock / On backorder), and **Category**. Pro adds SEO fields (URL slug, meta description, image ALT text).

= Can I undo changes? =

Changes are saved to the database immediately as you edit. There is no undo button. However, since products start as drafts, you can delete any product before publishing. [DropProduct Pro](https://dropproduct.dev/pro) adds session rollback to delete entire upload batches at once.


== Changelog ==

= 1.0.0 =
* Initial release
* Drag & drop multi-image upload with real-time progress bar
* Smart filename-to-title conversion with automatic gallery grouping
* SPA-style inline editable product grid (title, description, regular price, sale price, SKU, stock, category)
* Auto-save on blur/change with visual saving/saved/error states
* Sale price validation with tooltip warning
* Hover image preview with cursor tracking
* Batch publish with client-side and server-side validation
* Description editor popup with save indicator
* HPOS compatibility declared
* Extension hooks for Pro integration

== Upgrade Notice ==

= 1.0.0 =
Initial release of DropProduct – the fastest way to bulk create WooCommerce products from images. Drag, drop, edit, publish.
