=== WooUpload – Bulk Product Creator ===
Contributors: uploady
Tags: woocommerce, product upload, bulk products, drag drop, product images
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The New Era of Fast Product Management — bulk create WooCommerce products from images with a drag & drop grid.

== Description ==

WooUpload – Bulk Product Creator replaces the slow default WooCommerce product creation workflow with a high-performance, SPA-style editable grid.

**How it works:**

1. Drag & drop your product images onto the upload zone.
2. Products are automatically created as drafts with titles generated from filenames.
3. Images sharing the same base name (e.g., `shoe-1.jpg`, `shoe-2.jpg`) are grouped into a single product with a gallery.
4. Edit product details inline — title, price, SKU, stock status, and category.
5. Click "Publish All" to push validated products live.

**Key Features:**

* Drag & drop multi-image upload
* Automatic filename-based image grouping
* Inline editable product grid (no page reloads)
* Auto-save on blur
* Hover image preview
* Bulk publish with validation
* Built on WooCommerce CRUD — no raw database queries

**Built for Speed:**

* Assets load only on the WooUpload admin page
* Zero global admin bloat
* AJAX-powered — no full page reloads

== Installation ==

1. Upload the `woocommerce-uploady` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Make sure WooCommerce is installed and active.
4. Navigate to **WooCommerce → WooUpload** to start creating products.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes. WooCommerce must be installed and active for the plugin to work.

= What product types are supported? =

The free version supports simple products. Variable product support is available in the Pro version.

= How does image grouping work? =

If uploaded files share a base name with a numeric suffix (e.g., `shoe-1.jpg`, `shoe-2.jpg`), they are grouped into a single product. The first image becomes the featured image, and the rest become the product gallery.

= Is it safe to use on a live store? =

Yes. All products are created as drafts first. Nothing is published until you click "Publish All" and validation passes.

== Screenshots ==

1. Drag & drop upload zone
2. Editable product grid with inline editing
3. Hover image preview
4. Publish validation highlighting

== Changelog ==

= 1.0.0 =
* Initial release.
* Drag & drop image upload with filename grouping.
* SPA-style editable product grid.
* Inline editing with auto-save.
* Bulk publish with validation.
* Hover image preview.

== Upgrade Notice ==

= 1.0.0 =
Initial release of WooUpload – Bulk Product Creator.
