=== DropProduct – Bulk Product Uploader for WooCommerce ===
Contributors: codersajjad
Tags: woocommerce, bulk product upload, product creator, drag drop upload, woocommerce bulk edit, fraud protection, anti-fraud
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

DropProduct is a WooCommerce bulk product uploader that instantly creates draft products from image uploads — drag & drop multiple images, auto-generate titles, group galleries, edit inline, and publish in one click. Powerful built-in tools include real-time cost-to-profit tracking, bulk price adjustments, a Sales Analytics dashboard with CSV export, and a rule-based Order Shield for fraud protection.

== Description ==

**DropProduct** is the fastest way to add products to your WooCommerce store. Stop wasting hours creating products one by one — just drag & drop your product images and let DropProduct do the rest.

Upload 10, 50, or 100+ product images at once. Each image instantly becomes a draft product with a clean title generated from the filename. Edit prices, categories, SKUs, and descriptions right in the grid — everything saves automatically. When you're ready, publish all products in a single click.

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

**Core Product Management**

* **Drag & Drop Bulk Upload** — Upload unlimited product images at once (JPEG, PNG, GIF, WebP)
* **Smart Title Generation** — Filenames like `blue-cotton-hoodie.jpg` become "Blue Cotton Hoodie" automatically
* **Gallery Grouping** — Images sharing a base name merge into one product with a gallery
* **Inline Grid Editor** — Edit title, description, regular price, sale price, SKU, stock status, category, and cost price directly in the table
* **Auto-Save** — Every change saves instantly via AJAX — no save button needed
* **Sale Price Validation** — Warns you if the sale price is higher than the regular price
* **Description Editor** — Add product descriptions via a clean popup modal
* **Hover Image Preview** — Hover over any thumbnail to see the full-size image
* **Batch Publish with Validation** — Publish all drafts in one click; missing title or price fields are highlighted in red
* **Individual Publish** — Publish a single draft product directly from the grid row
* **Draft Counter** — See how many unpublished products you have at a glance
* **HPOS Compatible** — Fully compatible with WooCommerce High-Performance Order Storage
* **Zero Bloat** — Assets only load on the DropProduct page; no impact on the rest of your admin

**📊 Sales Analytics Dashboard**

* New dedicated admin submenu under DropProduct with modern analytics cards and charts
* Track total sales, orders, average order value, conversion rate, sales over time, top products, top countries, and traffic channels
* Date range filters and CSV export for quick reporting

**💰 Quick Bulk Price Adjuster (Price Slasher)**

* Apply a percentage or fixed-amount price increase/decrease to all selected products simultaneously
* Works on Regular Price, Sale Price, or both at once
* Toggle the Price Slasher bar on/off with a dedicated toolbar button
* Prices are updated via AJAX — no page reload
* Final prices are clamped to ≥ 0 and rounded to 2 decimal places

**📸 Smart SEO Alt-Text Automator** *(requires toggle in Settings)*

* Automatically generates and assigns SEO-friendly alt text to product images on upload
* Parses the filename: removes extension, replaces hyphens/underscores with spaces, converts to Title Case
* Only sets alt text when the field is currently empty — never overwrites manual work

**⚙️ Cost-to-Profit Tracker**

* Add a "Cost Price" to any product row — stored privately as `_dropproduct_cost_price` (never shown to customers)
* Instant real-time calculation of **Profit** (= Selling Price − Cost) and **Margin %** (= Profit ÷ Selling Price × 100)
* Both values update live as you type — no AJAX round-trip needed for display
* Cost saved automatically via debounced AJAX; recalculates whenever the regular or sale price changes
* Profit and Margin display with colour coding: green (positive), red (negative), grey (no data)

**🛡️ Ultimate Order Shield** *(requires toggle in Settings)*

* Full WooCommerce fraud protection engine — no external APIs
* **Honeypot field** — invisible to real users; filled by bots → immediate block
* **Blacklist** — block orders from specific names, phones, or email addresses
* **Disposable email detection** — 20+ known throwaway domains blocked by default; fully editable list
* **IP velocity limiting** — too many orders from the same IP within 1 hour scores +30 risk points
* **IP / Billing country mismatch** — uses WC Geolocation (no external API); adds risk score and private order note
* **Card testing protection** — excessive failed payment attempts trigger an immediate block
* **Checkout speed check** — orders submitted faster than a configurable threshold are scored as suspicious
* **Configurable thresholds** — separate block and review thresholds; force On-Hold instead of block if preferred
* **Cash on Delivery restriction** — automatically hide COD for high-risk customers
* **Activity Log** — every checkout check is logged with IP, email, risk score, triggered rules, and final action
* **Admin settings panel** — full control via DropProduct → 🛡️ Order Shield menu

**📦 Bulk Editing**

* Select multiple products and set price, category, stock status, tax class, or shipping class for all of them at once
* Floating bulk bar appears when rows are selected — shows action buttons for each field
* Clean prompt modal lets you choose the new value before applying

**🔖 Session Management**

* Upload sessions are tracked automatically — each batch of images is tagged with a unique session ID
* Filter the product grid by session to work on one batch at a time
* Session dropdown always shows the most recent 50 sessions

**📋 Activity Log**

* Full audit trail of all create, publish, delete, and edit actions
* Filter by action type (upload, publish, delete, edit) and paginate through entries
* Clear individual entries or wipe the entire log from the admin panel

**🔍 SEO Tools**

* Edit URL slugs, meta descriptions (Yoast + Rank Math compatible), and image ALT text inline
* SEO fields update via AJAX — no page reload needed

= ⭐ Pro Features =

Unlock advanced power features with [DropProduct Pro](https://dropproduct.dev/pro):

* **Product Duplication** — Clone any product row with one click
* **Validation Dashboard** — Pre-publish validation report with issue breakdown
* **Variable Product Support** — Auto-detect color/size variations from filenames
* **Advanced Grouping Engine** — Custom prefixes/suffixes for smarter image grouping
* **Template Presets** — Default category, stock, tax class auto-applied to every new product
* **Performance Controls** — Configurable batch size, safe mode, and auto-retry

= 💡 Smart Image Naming Tips =

* `blue-hoodie.jpg` → **Blue Hoodie** (1 product, 1 image)
* `blue-hoodie-1.jpg`, `blue-hoodie-2.jpg` → **Blue Hoodie** (1 product, 2-image gallery)

Use hyphens (`-`) or underscores (`_`) to separate words. Trailing numbers are stripped automatically.

= 🔒 Safe & Secure =

* All products start as **drafts** — nothing goes live until you click Publish
* Every request is **nonce-protected** and capability-checked (`manage_woocommerce`)
* All inputs are **sanitized and escaped**
* Order Shield uses **rule-based scoring** — no external APIs, no third-party data sharing

== Installation ==

1. Upload the `DropProduct` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Make sure **WooCommerce** is installed and active
4. Navigate to **DropProduct** in the admin sidebar to start creating products

== Frequently Asked Questions ==

= Does DropProduct require WooCommerce? =

Yes. WooCommerce must be installed and active. DropProduct requires WooCommerce 6.0 or higher.

= What fields can I edit in the grid? =

You can edit: **Title**, **Short Description**, **Regular Price**, **Sale Price**, **SKU**, **Stock Status**, **Category**, and **Cost Price** (internal — not shown to customers).

= How does the Order Shield fraud protection work? =

Order Shield uses a rule-based scoring engine to assess risk at checkout. Each suspicious signal (disposable email, IP velocity, country mismatch, failed payments, checkout speed) adds risk points. If the total exceeds your "Block" threshold, the order is rejected; if it exceeds your "Review" threshold, the order is set to "On Hold". No external APIs are used.

= Where is the Cost Price stored? =

Cost prices are stored in `wp_postmeta` under the key `_dropproduct_cost_price`. They are private and never shown to customers.

= Does DropProduct work with WooCommerce HPOS? =

Yes. DropProduct is fully compatible with WooCommerce High-Performance Order Storage (HPOS).

= Will this plugin slow down my admin? =

No. DropProduct loads its CSS and JavaScript **only on the DropProduct admin page**.

== Changelog ==

= 1.1.0 =
Combined release: includes the unreleased 1.0.1 and 1.0.2 feature sets.

**New Features**

* **Sales Analytics Dashboard** — New DropProduct → 📈 Sales Analytics submenu with a Freemius-style dashboard layout, summary cards, responsive charts, top products, top countries, and device/channel breakdowns.
* **Date Range Filtering** — Quickly switch between 7, 30, 90, and 365-day reporting windows.
* **CSV Export** — Export the current analytics view for reporting and record keeping.
* **Cost-to-Profit Tracker** — New "Cost Price" grid column. Profit and Margin % calculated in real-time on the client side; cost auto-saved via debounced AJAX to `_dropproduct_cost_price` post meta. Colour-coded display: green (profitable), red (loss), grey (no data).
* **Ultimate Order Shield** — Complete WooCommerce fraud protection. Rule-based risk scoring (disposable emails +40, IP velocity +30, repeated data +25, country mismatch +20, failed payments +25, checkout speed +20). Honeypot + blacklist instant-block pre-checks. Configurable block/review thresholds, COD restriction, full activity log. New admin submenu: DropProduct → 🛡️ Order Shield. Custom DB table `{prefix}dropproduct_fraud_log`.
* **Quick Bulk Price Adjuster (Price Slasher)** — New bulk pricing feature for selected products. Open the Price Slasher bar via toolbar toggle and apply % or fixed price adjustments to Regular, Sale, or both prices simultaneously.
* **Smart SEO Alt-Text Automator** — Auto-generates Title Case alt text from filenames on upload. Toggle in Settings. Only sets alt when the field is currently empty.
* **Individual Publish** — Per-row publish button for publishing a single draft without affecting others.
* **Custom Delete Modal** — Replaced browser `confirm()` with a styled modal popup for delete confirmations.

**Improvements**

* Added a dedicated analytics service and AJAX endpoint for loading dashboard data without leaving the admin page.
* Updated the admin navigation to surface Sales Analytics alongside Upload, Settings, Dashboard, and Order Shield.

**Bug Fixes**

* Price Slasher bar redesigned from dark indigo theme to a clean **light theme** — white background, indigo left-border accent, full-contrast inputs and labels.
* `handle_bulk_price_adjust()` AJAX response refactored: returns flat `{id, regular_price, sale_price}` per product instead of nested `fields[]` array — eliminates the "price disappears after apply" bug.
* Profit and Margin auto-recalculate whenever Regular Price or Sale Price is edited in the grid.
* Regular price inputs no longer lose their values after a Price Slasher bulk adjustment is applied.

= 1.0.0 =
* Initial release
* Drag & drop multi-image upload with real-time progress bar
* Smart filename-to-title conversion with automatic gallery grouping
* SPA-style inline editable product grid
* Auto-save on blur/change with visual saving/saved/error states
* Sale price validation with tooltip warning
* Hover image preview, description popup, batch publish with validation
* HPOS compatibility declared; extension hooks for Pro integration

== Upgrade Notice ==

= 1.1.0 =
Major release: Sales Analytics adds a modern reporting dashboard for DropProduct sales performance, including charts, top products, geographic breakdowns, and CSV export, plus the Cost-to-Profit Tracker, Ultimate Order Shield, Price Slasher improvements, SEO Alt-Text Automator, and publish/delete workflow upgrades.

= 1.0.0 =
Initial release of DropProduct.
