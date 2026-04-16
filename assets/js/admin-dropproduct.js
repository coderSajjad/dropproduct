/**
 * WooCommerce DropProduct – Bulk Product Creator — Admin JavaScript
 *
 * SPA-style grid with drag & drop upload, inline editing,
 * auto-save, image preview, bulk publish, individual publish,
 * delete confirmation modal, and Pro lock popup.
 *
 * @package DropProduct
 * @since   1.0.0
 */

/* global jQuery, dropProduct */
(function ($) {
	'use strict';

	var DropProduct = {
		/**
		 * Initialize the DropProduct SPA.
		 */
		init: function () {
			this.cache();
			this.cacheModal();
			this.cacheDeleteModal();
			this.cacheProPopup();
			this.cachePriceSlasher();
			this.bindEvents();
			this.loadExistingProducts();
		},

		/**
		 * Cache DOM elements.
		 */
		cache: function () {
			this.$wrap = $('.dropproduct-wrap');
			this.$dropzone = $('#dropproduct-dropzone');
			this.$fileInput = $('#dropproduct-file-input');
			this.$browseBtn = $('#dropproduct-browse-btn');
			this.$gridBody = $('#dropproduct-grid-body');
			this.$emptyRow = $('#dropproduct-empty-row');
			this.$publishBtn = $('#dropproduct-publish-all');
			this.$draftCount = $('#dropproduct-draft-count');
			this.$notices = $('#dropproduct-notices');
			this.$preview = $('#dropproduct-image-preview');
			this.$previewImg = $('#dropproduct-preview-img');
			this.$progressWrap = $('#dropproduct-upload-progress');
			this.$progressFill = $('#dropproduct-progress-fill');
			this.$progressText = $('#dropproduct-progress-text');
			this.$dropInner = this.$dropzone.find('.dropproduct-dropzone__inner');
		},

		/**
		 * Cache description modal elements.
		 */
		cacheModal: function () {
			this.$descModal = $('#dropproduct-desc-modal');
			this.$descOverlay = $('#dropproduct-desc-overlay');
			this.$descTextarea = $('#dropproduct-desc-textarea');
			this.$descSaveBtn = $('#dropproduct-desc-save');
			this.$descCancelBtn = $('#dropproduct-desc-cancel');
			this.$descCloseBtn = $('#dropproduct-desc-close');
			this._descProductId = 0;
		},

		/**
		 * Cache delete confirmation modal elements.
		 */
		cacheDeleteModal: function () {
			this.$confirmOverlay = $('#dropproduct-confirm-overlay');
			this.$confirmModal = $('#dropproduct-confirm-modal');
			this.$confirmCancel = $('#dropproduct-confirm-cancel');
			this.$confirmDelete = $('#dropproduct-confirm-delete');
			this._deleteRow = null;
		},

		/**
		 * Cache Pro lock popup elements (free version only).
		 */
		cacheProPopup: function () {
			this.$proOverlay = $('#dropproduct-pro-overlay');
			this.$proPopup = $('#dropproduct-pro-popup');
			this.$proPopupClose = $('#dropproduct-pro-popup-close');
			this.$proPopupX = $('#dropproduct-pro-popup-x');
		},

		/**
		 * Cache Price Slasher bar elements.
		 */
		cachePriceSlasher: function () {
			this.$slasherBar       = $('#dropproduct-slasher-bar');
			this.$slasherToggleBtn = $('#dropproduct-slasher-toggle-btn');
			this.$slasherCount     = $('#dropproduct-slasher-count');     // badge on button
			this.$slasherCountBar  = $('#dropproduct-slasher-count-bar'); // badge inside bar
			this.$slasherField     = $('#dropproduct-slasher-field');
			this.$slasherAmount    = $('#dropproduct-slasher-amount');
			this.$slasherType      = $('#dropproduct-slasher-type');
			this.$slasherApply     = $('#dropproduct-slasher-apply');
			this.$slasherClear     = $('#dropproduct-slasher-clear');
			this.$selectAll        = $('#dropproduct-select-all');
			// Tracks selected product IDs {id: true}.
			this._selectedIds      = {};
			// Active operation: 'increase' | 'decrease'.
			this._slasherOp        = 'increase';
			// Whether the slasher bar is expanded.
			this._slasherOpen      = false;
		},

		/**
		 * Bind all event listeners.
		 */
		bindEvents: function () {
			var self = this;

			// Drag & drop.
			this.$dropzone
				.on('dragenter dragover', function (e) {
					e.preventDefault();
					e.stopPropagation();
					self.$dropzone.addClass('is-dragover');
				})
				.on('dragleave drop', function (e) {
					e.preventDefault();
					e.stopPropagation();
					self.$dropzone.removeClass('is-dragover');
				})
				.on('drop', function (e) {
					var files = e.originalEvent.dataTransfer.files;
					if (files.length) {
						self.uploadFiles(files);
					}
				});

			// Browse button.
			this.$browseBtn.on('click', function () {
				self.$fileInput.trigger('click');
			});

			this.$fileInput.on('change', function () {
				if (this.files.length) {
					self.uploadFiles(this.files);
					this.value = ''; // Reset so same files can be re-uploaded.
				}
			});

			// Inline editing — auto-save on blur.
			this.$gridBody.on('blur', '.dropproduct-editable', function () {
				self.saveField($(this));

				// Validate prices when a price field changes.
				var fieldName = $(this).data('field');
				if (fieldName === 'regular_price' || fieldName === 'sale_price') {
					self.validatePrices($(this).closest('tr'));
					// Recalculate profit/margin when selling price changes.
					self.calculateFinancials($(this).closest('tr'));
				}
			});

			// Cost Price input: instant client-side recalc + debounced AJAX save.
			this.$gridBody.on('input', '.dropproduct-cost-input', function () {
				var $input = $(this);
				var $row   = $input.closest('tr');

				// Instant UI update — no wait for server.
				self.calculateFinancials($row);

				// Debounced save — 600 ms after last keystroke.
				clearTimeout($input.data('costTimer'));
				$input.data('costTimer', setTimeout(function () {
					self.saveCostPrice($input);
				}, 600));
			});

			// Also save on blur (catches paste, arrow keys, tab-away).
			this.$gridBody.on('blur', '.dropproduct-cost-input', function () {
				var $input = $(this);
				clearTimeout($input.data('costTimer'));
				self.saveCostPrice($input);
				self.calculateFinancials($input.closest('tr'));
			});

			// Also save select fields on change.
			this.$gridBody.on('change', 'select.dropproduct-editable', function () {
				self.saveField($(this));
			});

			// Delete product — open custom modal instead of confirm().
			this.$gridBody.on('click', '.dropproduct-delete-btn', function () {
				var $row = $(this).closest('tr');
				self.openDeleteModal($row);
			});

			// Delete modal: confirm.
			this.$confirmDelete.on('click', function () {
				self.confirmDelete();
			});

			// Delete modal: cancel.
			this.$confirmCancel.on('click', function () {
				self.closeDeleteModal();
			});

			this.$confirmOverlay.on('click', function () {
				self.closeDeleteModal();
			});

			// Publish all.
			this.$publishBtn.on('click', function () {
				self.publishAll();
			});

			// Publish individual product.
			this.$gridBody.on('click', '.dropproduct-publish-single-btn', function () {
				var $row = $(this).closest('tr');
				// Only publish if still a draft.
				if ($row.hasClass('is-published')) return;
				self.publishSingle($row);
			});

			// Image preview on hover.
			this.$gridBody
				.on('mouseenter', '.dropproduct-thumb', function (e) {
					var fullUrl = $(this).data('full');
					if (fullUrl) {
						self.$previewImg.attr('src', fullUrl);
						self.$preview.show();
						self.positionPreview(e);
					}
				})
				.on('mousemove', '.dropproduct-thumb', function (e) {
					self.positionPreview(e);
				})
				.on('mouseleave', '.dropproduct-thumb', function () {
					self.$preview.hide();
					self.$previewImg.attr('src', '');
				});

			// Description modal.
			this.$gridBody.on('click', '.dropproduct-desc-btn', function () {
				var $row = $(this).closest('tr');
				self.openDescriptionModal($row);
			});

			this.$descSaveBtn.on('click', function () {
				self.saveDescription();
			});

			this.$descCancelBtn.on('click', function () {
				self.closeDescriptionModal();
			});

			this.$descCloseBtn.on('click', function () {
				self.closeDescriptionModal();
			});

			this.$descOverlay.on('click', function () {
				self.closeDescriptionModal();
			});

			// Keyboard close for description modal.
			$(document).on('keydown', function (e) {
				if (e.key === 'Escape') {
					self.closeDescriptionModal();
					self.closeDeleteModal();
					self.closeProPopup();
				}
			});

			// Pro feature lock triggers (shown in free version).
			$(document).on('click', '.dropproduct-pro-lock-trigger', function (e) {
				e.preventDefault();
				e.stopPropagation();
				self.openProPopup();
			});

			// Locked session bar clicks.
			$(document).on('click keydown', '.dropproduct-locked-select', function (e) {
				if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
				e.preventDefault();
				self.openProPopup();
			});

			// Pro popup close.
			if (this.$proPopupClose.length) {
				this.$proPopupClose.on('click', function () { self.closeProPopup(); });
			}
			if (this.$proPopupX.length) {
				this.$proPopupX.on('click', function () { self.closeProPopup(); });
			}
			if (this.$proOverlay.length) {
				this.$proOverlay.on('click', function () { self.closeProPopup(); });
			}

			// ──── Price Slasher ─────────────────────────────────────

			// Individual row checkbox.
			this.$gridBody.on('change', '.dropproduct-row-check', function () {
				var $row = $(this).closest('tr');
				var id   = String($row.data('product-id'));
				if ($(this).is(':checked')) {
					self._selectedIds[id] = true;
					$row.addClass('is-selected');
				} else {
					delete self._selectedIds[id];
					$row.removeClass('is-selected');
				}
				self.updateSlasherBar();
				self.syncSelectAll();
			});

			// Select-all checkbox.
			this.$selectAll.on('change', function () {
				var checked = $(this).is(':checked');
				self.$gridBody.find('tr[data-product-id]').each(function () {
					var $row = $(this);
					var id   = String($row.data('product-id'));
					$row.find('.dropproduct-row-check').prop('checked', checked);
					if (checked) {
						self._selectedIds[id] = true;
						$row.addClass('is-selected');
					} else {
						delete self._selectedIds[id];
						$row.removeClass('is-selected');
					}
				});
				self.updateSlasherBar();
			});

			// Toggle button — open/close the slasher bar.
			this.$slasherToggleBtn.on('click', function () {
				self.toggleSlasherBar();
			});

			// Operation toggle (Increase / Decrease).
			this.$slasherBar.on('click', '.dropproduct-slasher-toggle', function () {
				self.$slasherBar.find('.dropproduct-slasher-toggle').removeClass('is-active');
				$(this).addClass('is-active');
				self._slasherOp = $(this).data('op');
			});

			// Apply price adjustment.
			this.$slasherApply.on('click', function () {
				self.adjustPrices();
			});

			// Clear selection.
			this.$slasherClear.on('click', function () {
				self.clearSelection();
			});
		},

		/**
		 * Load existing draft products from the server.
		 */
		loadExistingProducts: function () {
			var self = this;

			$.post(dropProduct.ajaxUrl, {
				action: 'dropproduct_load_products',
				nonce: dropProduct.nonce
			}, function (response) {
				if (response.success && response.data.products.length) {
					self.renderProducts(response.data.products);
				}
			});
		},

		/**
		 * Upload files to the server.
		 *
		 * @param {FileList} files Files from drag & drop or file input.
		 */
		uploadFiles: function (files) {
			var self = this;
			var formData = new FormData();

			formData.append('action', 'dropproduct_upload_images');
			formData.append('nonce', dropProduct.nonce);

			var imageCount = 0;
			for (var i = 0; i < files.length; i++) {
				if (files[i].type.indexOf('image/') === 0) {
					formData.append('images[]', files[i]);
					imageCount++;
				}
			}

			if (!imageCount) {
				this.showNotice(dropProduct.i18n.uploadError, 'error');
				return;
			}

			// Show progress.
			this.$dropInner.hide();
			this.$progressWrap.show();
			this.$progressFill.css('width', '0%');
			this.$progressText.text(dropProduct.i18n.uploading);
			this.$dropzone.addClass('is-uploading');

			$.ajax({
				url: dropProduct.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				xhr: function () {
					var xhr = new XMLHttpRequest();
					xhr.upload.addEventListener('progress', function (e) {
						if (e.lengthComputable) {
							var pct = Math.round((e.loaded / e.total) * 100);
							self.$progressFill.css('width', pct + '%');
							self.$progressText.text(dropProduct.i18n.uploading + ' ' + pct + '%');
						}
					});
					return xhr;
				},
				success: function (response) {
					self.resetDropzone();

					if (response.success) {
						self.renderProducts(response.data.products);
						self.showNotice(
							response.data.products.length + ' product(s) created as drafts.',
							'success'
						);
					} else {
						self.showNotice(response.data.message || dropProduct.i18n.uploadError, 'error');
					}
				},
				error: function () {
					self.resetDropzone();
					self.showNotice(dropProduct.i18n.networkError, 'error');
				}
			});
		},

		/**
		 * Reset the dropzone to its default state.
		 */
		resetDropzone: function () {
			this.$progressWrap.hide();
			this.$dropInner.show();
			this.$dropzone.removeClass('is-uploading');
		},

		/**
		 * Render product rows into the grid.
		 *
		 * @param {Array} products Array of product data objects.
		 */
		renderProducts: function (products) {
			var self = this;

			this.$emptyRow.hide();

			$.each(products, function (i, product) {
				// Skip if row already exists (e.g. from load + upload).
				if ($('#dropproduct-row-' + product.id).length) {
					return;
				}
				self.$gridBody.prepend(self.buildRow(product));
			});

			this.updateDraftCount();
		},

		/**
		 * Build a table row for a product.
		 *
		 * @param {Object} product Product data.
		 * @return {string} HTML string.
		 */
		buildRow: function (product) {
			var galleryBadge = product.gallery_count > 0
				? '<span class="dropproduct-gallery-badge">+' + product.gallery_count + '</span>'
				: '';

			var stockOptions = this.buildStockOptions(product.stock_status);
			var categoryOptions = this.buildCategoryOptions(product.category_id);
			var statusClass = product.status === 'publish' ? 'publish' : 'draft';
			var isPublished = product.status === 'publish';
			var isSelected  = !!this._selectedIds[String(product.id)];

			// Publish single button — hidden when already published.
			var publishSingleBtn = isPublished
				? ''
				: '<button type="button" class="dropproduct-publish-single-btn" title="' + this.escAttr('Publish this product') + '">'
					+ '<span class="dashicons dashicons-yes-alt"></span>'
					+ '</button>';

			var classes = [];
			if (isPublished) classes.push('is-published');
			if (isSelected)  classes.push('is-selected');

			return '<tr id="dropproduct-row-' + product.id + '" data-product-id="' + product.id + '"'
				+ (classes.length ? ' class="' + classes.join(' ') + '"' : '') + '>'
				+ '<td class="dropproduct-col-check">'
				+ '<label class="dropproduct-check-label">'
				+ '<input type="checkbox" class="dropproduct-row-check"' + (isSelected ? ' checked' : '') + ' />'
				+ '<span class="dropproduct-check-custom"></span>'
				+ '</label>'
				+ '</td>'
				+ '<td class="dropproduct-col-image">'
				+ (product.image_thumb
					? '<img src="' + product.image_thumb + '" alt="" class="dropproduct-thumb" data-full="' + product.image_full + '" />'
					: '<span class="dashicons dashicons-format-image" style="font-size:40px;color:#dcdcde;"></span>')
				+ galleryBadge
				+ '</td>'
				+ '<td class="dropproduct-col-title">'
				+ '<input type="text" class="dropproduct-editable" data-field="title" value="' + this.escAttr(product.title) + '" />'
				+ '</td>'
				+ '<td class="dropproduct-col-desc">'
				+ '<button type="button" class="dropproduct-desc-btn' + (product.description ? ' has-desc' : '') + '" title="Edit description">'
				+ '<span class="dashicons dashicons-edit"></span>'
				+ (product.description ? '<span class="dropproduct-desc-dot"></span>' : '')
				+ '</button>'
				+ '<input type="hidden" class="dropproduct-desc-value" value="' + this.escAttr(product.description) + '" />'
				+ '</td>'
				+ '<td class="dropproduct-col-price">'
				+ '<div class="dropproduct-price-wrap">'
				+ '<span class="dropproduct-currency">$</span>'
				+ '<input type="number" class="dropproduct-editable dropproduct-price-input" data-field="regular_price" value="' + this.escAttr(product.regular_price) + '" step="0.01" min="0" placeholder="0.00" />'
				+ '</div>'
				+ '</td>'
				+ '<td class="dropproduct-col-sale-price">'
				+ '<div class="dropproduct-price-wrap">'
				+ '<span class="dropproduct-currency">$</span>'
				+ '<input type="number" class="dropproduct-editable dropproduct-price-input" data-field="sale_price" value="' + this.escAttr(product.sale_price) + '" step="0.01" min="0" placeholder="0.00" />'
				+ '</div>'
				+ '</td>'
				+ '<td class="dropproduct-col-sku">'
				+ '<input type="text" class="dropproduct-editable" data-field="sku" value="' + this.escAttr(product.sku) + '" />'
				+ '</td>'
				+ '<td class="dropproduct-col-stock">'
				+ '<select class="dropproduct-editable" data-field="stock_status">' + stockOptions + '</select>'
				+ '</td>'
				+ '<td class="dropproduct-col-category">'
				+ '<select class="dropproduct-editable" data-field="category">' + categoryOptions + '</select>'
				+ '</td>'
				+ '<td class="dropproduct-col-status">'
				+ '<span class="dropproduct-status dropproduct-status--' + statusClass + '">' + product.status + '</span>'
				+ '</td>'
				+ '<td class="dropproduct-col-actions">'
				+ publishSingleBtn
				+ '<button type="button" class="dropproduct-delete-btn" title="Delete">'
				+ '<span class="dashicons dashicons-trash"></span>'
				+ '</button>'
				+ '</td>'
				// Cost-to-Profit Tracker — three read/write financial columns.
				+ '<td class="dropproduct-col-cost">'
				+ '<div class="dropproduct-price-wrap">'
				+ '<span class="dropproduct-currency">$</span>'
				+ '<input type="number" class="dropproduct-cost-input" data-field="cost_price" value="' + this.escAttr(product.cost_price > 0 ? product.cost_price : '') + '" step="0.01" min="0" placeholder="0.00" />'
				+ '</div>'
				+ '</td>'
				+ '<td class="dropproduct-col-profit">'
				+ '<span class="dropproduct-profit-display">'
				+ this.formatFinancials(product.regular_price, product.sale_price, product.cost_price).profitHtml
				+ '</span>'
				+ '</td>'
				+ '<td class="dropproduct-col-margin">'
				+ '<span class="dropproduct-margin-display">'
				+ this.formatFinancials(product.regular_price, product.sale_price, product.cost_price).marginHtml
				+ '</span>'
				+ '</td>'
				+ '</tr>';
		},

		/**
		 * Build stock status select options.
		 *
		 * @param {string} selected Current stock status.
		 * @return {string} HTML options string.
		 */
		buildStockOptions: function (selected) {
			var options = [
				{ value: 'instock', label: 'In stock' },
				{ value: 'outofstock', label: 'Out of stock' },
				{ value: 'onbackorder', label: 'On backorder' }
			];

			return options.map(function (opt) {
				var sel = opt.value === selected ? ' selected' : '';
				return '<option value="' + opt.value + '"' + sel + '>' + opt.label + '</option>';
			}).join('');
		},

		/**
		 * Build category select options.
		 *
		 * @param {number} selectedId Currently assigned category ID.
		 * @return {string} HTML options string.
		 */
		buildCategoryOptions: function (selectedId) {
			var html = '<option value="">— None —</option>';
			var cats = dropProduct.categories || {};

			$.each(cats, function (id, name) {
				var sel = parseInt(id) === parseInt(selectedId) ? ' selected' : '';
				html += '<option value="' + id + '"' + sel + '>' + name + '</option>';
			});

			return html;
		},

		// ── Cost-to-Profit Tracker helpers ────────────────────────────

		/**
		 * Pure calculation & HTML formatter — used by buildRow() (no DOM access).
		 *
		 * @param  {number|string} regularPrice
		 * @param  {number|string} salePrice
		 * @param  {number|string} costPrice
		 * @return {{ profit: number, margin: number, profitHtml: string, marginHtml: string }}
		 */
		formatFinancials: function (regularPrice, salePrice, costPrice) {
			var cost   = parseFloat(costPrice) || 0;
			var sale   = parseFloat(salePrice)  || 0;
			var reg    = parseFloat(regularPrice)|| 0;

			// Use sale price if set, otherwise regular price.
			var price  = sale > 0 ? sale : reg;

			if ( cost <= 0 || price <= 0 ) {
				return { profit: 0, margin: 0, profitHtml: '<span class="dp-finance-na">—</span>', marginHtml: '<span class="dp-finance-na">—</span>' };
			}

			var profit = price - cost;
			var margin = (profit / price) * 100;

			var profitClass = profit >= 0
				? 'dp-finance-profit dp-finance-positive'
				: 'dp-finance-profit dp-finance-negative';
			var marginClass = margin >= 0
				? 'dp-finance-margin dp-finance-positive'
				: 'dp-finance-margin dp-finance-negative';

			var profitSign  = profit >= 0 ? '+' : '';
			var marginSign  = margin >= 0 ? '+' : '';

			return {
				profit:     profit,
				margin:     margin,
				profitHtml: '<span class="' + profitClass + '">' + profitSign + '$' + Math.abs(profit).toFixed(2) + '</span>',
				marginHtml: '<span class="' + marginClass + '">' + marginSign + margin.toFixed(1) + '%</span>',
			};
		},

		/**
		 * Read prices from a row's DOM, compute Profit & Margin, update the cells.
		 *
		 * @param {jQuery} $row The <tr> element.
		 */
		calculateFinancials: function ($row) {
			var regularPrice = $row.find('[data-field="regular_price"]').val();
			var salePrice    = $row.find('[data-field="sale_price"]').val();
			var costPrice    = $row.find('.dropproduct-cost-input').val();

			var result = this.formatFinancials(regularPrice, salePrice, costPrice);

			$row.find('.dropproduct-profit-display').html(result.profitHtml);
			$row.find('.dropproduct-margin-display').html(result.marginHtml);
		},

		/**
		 * AJAX-persist the cost price for a given row.
		 *
		 * @param {jQuery} $input The cost price <input> element.
		 */
		saveCostPrice: function ($input) {
			var $row      = $input.closest('tr');
			var productId = $row.data('product-id');
			var cost      = parseFloat($input.val()) || 0;

			if ( ! productId ) { return; }

			// Visual saving indicator.
			$input.addClass('is-saving').removeClass('is-saved is-error');

			$.post(dropProduct.ajaxUrl, {
				action:     'dropproduct_update_product',
				nonce:      dropProduct.nonce,
				product_id: productId,
				field:      'cost_price',
				value:      cost,
			}, function (response) {
				$input.removeClass('is-saving');
				if ( response.success ) {
					$input.addClass('is-saved');
					setTimeout(function () { $input.removeClass('is-saved'); }, 1500);
				} else {
					$input.addClass('is-error');
				}
			}).fail(function () {
				$input.removeClass('is-saving').addClass('is-error');
			});
		},

		/**
		 * Save a single field via AJAX.
		 *
		 * @param {jQuery} $field The editable input/select element.
		 */
		saveField: function ($field) {
			var self = this;
			var $row = $field.closest('tr');
			var productId = $row.data('product-id');
			var field = $field.data('field');
			var value = $field.val();

			$field.removeClass('is-saved is-error').addClass('is-saving');

			$.post(dropProduct.ajaxUrl, {
				action: 'dropproduct_update_product',
				nonce: dropProduct.nonce,
				product_id: productId,
				field: field,
				value: value
			}, function (response) {
				$field.removeClass('is-saving');

				if (response.success) {
					$field.addClass('is-saved');
					setTimeout(function () {
						$field.removeClass('is-saved');
					}, 1500);
				} else {
					$field.addClass('is-error');
					self.showNotice(response.data.message, 'error');
				}
			}).fail(function () {
				$field.removeClass('is-saving').addClass('is-error');
				self.showNotice(dropProduct.i18n.networkError, 'error');
			});
		},

		/**
		 * Validate sale price against regular price.
		 *
		 * If sale price is greater than or equal to regular price,
		 * highlight the sale price input in red and show an inline warning.
		 *
		 * @param {jQuery} $row Table row element.
		 */
		validatePrices: function ($row) {
			var $regularInput = $row.find('[data-field="regular_price"]');
			var $saleInput    = $row.find('[data-field="sale_price"]');
			var $saleCell     = $saleInput.closest('td');

			var regularPrice = parseFloat($regularInput.val());
			var salePrice    = parseFloat($saleInput.val());

			// Remove any existing warning.
			$saleCell.find('.dropproduct-price-warning').remove();
			$saleInput.removeClass('is-error');

			// Only validate when both fields have values.
			if (isNaN(salePrice) || salePrice === 0 || isNaN(regularPrice)) {
				return;
			}

			if (salePrice >= regularPrice) {
				$saleInput.addClass('is-error');
				$saleCell.append(
					'<span class="dropproduct-price-warning">'
					+ '<span class="dashicons dashicons-warning"></span> '
					+ 'Sale price must be lower than regular price.'
					+ '</span>'
				);
			}
		},

		// ──────────────────────────────────────────
		//  Delete Confirmation Modal
		// ──────────────────────────────────────────

		/**
		 * Open the custom delete confirmation modal.
		 *
		 * @param {jQuery} $row Table row element.
		 */
		openDeleteModal: function ($row) {
			this._deleteRow = $row;
			this.$confirmOverlay.addClass('is-open');
			this.$confirmModal.addClass('is-open');
		},

		/**
		 * Close the delete confirmation modal.
		 */
		closeDeleteModal: function () {
			this.$confirmOverlay.removeClass('is-open');
			this.$confirmModal.removeClass('is-open');
			this._deleteRow = null;
		},

		/**
		 * Execute the delete after user confirms.
		 */
		confirmDelete: function () {
			var $row = this._deleteRow;
			this.closeDeleteModal();
			if ($row) {
				this.deleteProduct($row);
			}
		},

		/**
		 * Delete a product and remove its row.
		 *
		 * @param {jQuery} $row Table row element.
		 */
		deleteProduct: function ($row) {
			var self = this;
			var productId = $row.data('product-id');
			var productTitle = $row.find('[data-field="title"]').val() || 'this product';

			$row.addClass('is-saving');

			$.post(dropProduct.ajaxUrl, {
				action: 'dropproduct_delete_product',
				nonce: dropProduct.nonce,
				product_id: productId
			}, function (response) {
				if (response.success) {
					$row.fadeOut(300, function () {
						$(this).remove();
						self.updateDraftCount();

						if (!self.$gridBody.find('tr:not(#dropproduct-empty-row)').length) {
							self.$emptyRow.show();
						}
					});

					// Show a styled notice (not a browser alert).
					self.showNotice(
						'<span class="dashicons dashicons-trash" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom;"></span> '
						+ self.escHtml(productTitle) + ' deleted successfully.',
						'error'
					);

				} else {
					$row.removeClass('is-saving');
					self.showNotice(response.data.message, 'error');
				}
			}).fail(function () {
				$row.removeClass('is-saving');
				self.showNotice(dropProduct.i18n.networkError, 'error');
			});
		},

		// ──────────────────────────────────────────
		//  Publish Individual Product
		// ──────────────────────────────────────────

		/**
		 * Publish a single draft product.
		 *
		 * @param {jQuery} $row Table row element.
		 * @since 1.0.1
		 */
		publishSingle: function ($row) {
			var self = this;
			var productId = $row.data('product-id');
			var $btn = $row.find('.dropproduct-publish-single-btn');
			var title = $row.find('[data-field="title"]').val().trim();
			var price = $row.find('[data-field="regular_price"]').val().trim();

			// Basic client-side validation.
			$row.removeClass('has-error');
			$row.find('.dropproduct-editable').removeClass('is-error');

			var hasError = false;
			if (!title) {
				$row.find('[data-field="title"]').addClass('is-error');
				hasError = true;
			}
			if (!price) {
				$row.find('[data-field="regular_price"]').addClass('is-error');
				hasError = true;
			}
			if (hasError) {
				$row.addClass('has-error');
				self.showNotice(dropProduct.i18n.validationError, 'error');
				return;
			}

			$btn.prop('disabled', true).addClass('is-publishing');

			$.post(dropProduct.ajaxUrl, {
				action: 'dropproduct_publish_single',
				nonce: dropProduct.nonce,
				product_id: productId
			}, function (response) {
				$btn.prop('disabled', false).removeClass('is-publishing');

				if (response.success) {
					// Mark row as published.
					$row.addClass('is-published');
					$row.find('.dropproduct-status')
						.removeClass('dropproduct-status--draft')
						.addClass('dropproduct-status--publish')
						.text('publish');

					// Remove the publish button since it's now live.
					$btn.fadeOut(200, function () { $(this).remove(); });

					self.updateDraftCount();
					self.showNotice(
						'<span class="dashicons dashicons-yes-alt" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom;"></span> '
						+ self.escHtml(title) + ' published successfully.',
						'success'
					);
				} else {
					self.showNotice(response.data.message, 'error');
				}
			}).fail(function () {
				$btn.prop('disabled', false).removeClass('is-publishing');
				self.showNotice(dropProduct.i18n.networkError, 'error');
			});
		},

		/**
		 * Publish all valid draft products.
		 */
		publishAll: function () {
			var self = this;
			var $rows = this.$gridBody.find('tr[data-product-id]').not('.is-published');
			var productIds = [];
			var hasErrors = false;

			// Clear previous validation.
			$rows.removeClass('has-error');
			$rows.find('.dropproduct-editable').removeClass('is-error');

			// Validate each row.
			$rows.each(function () {
				var $row = $(this);
				var title = $row.find('[data-field="title"]').val().trim();
				var price = $row.find('[data-field="regular_price"]').val().trim();
				var valid = true;

				if (!title) {
					$row.find('[data-field="title"]').addClass('is-error');
					valid = false;
				}

				if (!price) {
					$row.find('[data-field="regular_price"]').addClass('is-error');
					valid = false;
				}

				if (!valid) {
					$row.addClass('has-error');
					hasErrors = true;
				} else {
					productIds.push($row.data('product-id'));
				}
			});

			if (hasErrors && !productIds.length) {
				this.showNotice(dropProduct.i18n.validationError, 'error');
				return;
			}

			if (!productIds.length) {
				return;
			}

			this.$publishBtn.prop('disabled', true).text(dropProduct.i18n.publishing);

			$.post(dropProduct.ajaxUrl, {
				action: 'dropproduct_publish_all',
				nonce: dropProduct.nonce,
				product_ids: productIds
			}, function (response) {
				self.$publishBtn.prop('disabled', false).html(
					'<span class="dashicons dashicons-yes-alt"></span> ' + dropProduct.i18n.publishAll
				);

				if (response.success) {
					// Mark published rows.
					$.each(response.data.published, function (i, id) {
						var $row = $('#dropproduct-row-' + id);
						$row.addClass('is-published');
						$row.find('.dropproduct-status')
							.removeClass('dropproduct-status--draft')
							.addClass('dropproduct-status--publish')
							.text('publish');
						// Remove individual publish button.
						$row.find('.dropproduct-publish-single-btn').remove();
					});

					// Show failed ones.
					$.each(response.data.failed, function (i, fail) {
						var $row = $('#dropproduct-row-' + fail.id);
						$row.addClass('has-error');
					});

					var msg = response.data.published.length + ' product(s) published.';
					if (response.data.failed.length) {
						msg += ' ' + response.data.failed.length + ' failed validation.';
					}
					self.showNotice(msg, response.data.failed.length ? 'info' : 'success');
					self.updateDraftCount();
				} else {
					self.showNotice(response.data.message, 'error');
				}
			}).fail(function () {
				self.$publishBtn.prop('disabled', false).html(
					'<span class="dashicons dashicons-yes-alt"></span> ' + dropProduct.i18n.publishAll
				);
				self.showNotice(dropProduct.i18n.networkError, 'error');
			});
		},

		/**
		 * Update the draft product count display.
		 */
		updateDraftCount: function () {
			var count = this.$gridBody.find('tr[data-product-id]').not('.is-published').length;
			this.$draftCount.text(count);
			this.$publishBtn.prop('disabled', count === 0);
		},

		// ──────────────────────────────────────────
		//  Pro Feature Lock Popup
		// ──────────────────────────────────────────

		/**
		 * Open the Pro feature lock popup.
		 */
		openProPopup: function () {
			if (!this.$proPopup.length) return;
			this.$proOverlay.addClass('is-open');
			this.$proPopup.addClass('is-open');
		},

		/**
		 * Close the Pro feature lock popup.
		 */
		closeProPopup: function () {
			if (!this.$proPopup.length) return;
			this.$proOverlay.removeClass('is-open');
			this.$proPopup.removeClass('is-open');
		},

		// ──────────────────────────────────────────
		//  Notices
		// ──────────────────────────────────────────

		/**
		 * Show an admin notice.
		 *
		 * @param {string} message Notice text (may contain HTML for icons).
		 * @param {string} type    'success', 'error', or 'info'.
		 */
		showNotice: function (message, type) {
			var $notice = $('<div class="dropproduct-notice dropproduct-notice--' + type + '">'
				+ message
				+ '</div>');

			this.$notices.prepend($notice);

			setTimeout(function () {
				$notice.fadeOut(300, function () {
					$(this).remove();
				});
			}, 5000);
		},

		/**
		 * Position the image preview tooltip near the cursor.
		 *
		 * @param {Event} e Mouse event.
		 */
		positionPreview: function (e) {
			var x = e.clientX + 16;
			var y = e.clientY + 16;

			// Prevent overflow off right edge.
			if (x + 300 > window.innerWidth) {
				x = e.clientX - 300;
			}

			// Prevent overflow off bottom edge.
			if (y + 300 > window.innerHeight) {
				y = e.clientY - 300;
			}

			this.$preview.css({
				left: x + 'px',
				top: y + 'px'
			});
		},

		// ──────────────────────────────────────────
		//  Description Modal
		// ──────────────────────────────────────────

		/**
		 * Open the description modal for a product row.
		 *
		 * @param {jQuery} $row Table row element.
		 */
		openDescriptionModal: function ($row) {
			var productId = $row.data('product-id');
			var currentDesc = $row.find('.dropproduct-desc-value').val() || '';

			this._descProductId = productId;
			this.$descTextarea.val(this.decodeHtml(currentDesc));
			this.$descOverlay.addClass('is-open');
			this.$descModal.addClass('is-open');
			this.$descTextarea.focus();
		},

		/**
		 * Close the description modal.
		 */
		closeDescriptionModal: function () {
			this.$descOverlay.removeClass('is-open');
			this.$descModal.removeClass('is-open');
			this._descProductId = 0;
			this.$descTextarea.val('');
		},

		/**
		 * Save the description from the modal via AJAX.
		 */
		saveDescription: function () {
			var self = this;
			var productId = this._descProductId;
			var value = this.$descTextarea.val();

			if (!productId) return;

			this.$descSaveBtn.prop('disabled', true).text('Saving…');

			$.post(dropProduct.ajaxUrl, {
				action: 'dropproduct_update_product',
				nonce: dropProduct.nonce,
				product_id: productId,
				field: 'description',
				value: value
			}, function (response) {
				self.$descSaveBtn.prop('disabled', false).text('Save');

				if (response.success) {
					// Update hidden value in the row.
					var $row = $('#dropproduct-row-' + productId);
					$row.find('.dropproduct-desc-value').val(value);

					// Toggle the indicator dot.
					var $btn = $row.find('.dropproduct-desc-btn');
					if (value.trim()) {
						$btn.addClass('has-desc');
						if (!$btn.find('.dropproduct-desc-dot').length) {
							$btn.append('<span class="dropproduct-desc-dot"></span>');
						}
					} else {
						$btn.removeClass('has-desc');
						$btn.find('.dropproduct-desc-dot').remove();
					}

					self.closeDescriptionModal();
					self.showNotice('Description saved.', 'success');
				} else {
					self.showNotice(response.data.message, 'error');
				}
			}).fail(function () {
				self.$descSaveBtn.prop('disabled', false).text('Save');
				self.showNotice(dropProduct.i18n.networkError, 'error');
			});
		},

		// ──────────────────────────────────────────
		//  Utilities
		// ──────────────────────────────────────────

		/**
		 * Decode HTML entities from an attribute value.
		 *
		 * @param {string} str Encoded string.
		 * @return {string} Decoded string.
		 */
		decodeHtml: function (str) {
			if (!str) return '';
			var textarea = document.createElement('textarea');
			textarea.innerHTML = str;
			return textarea.value;
		},

		/**
		 * Escape HTML entities.
		 *
		 * @param {string} str Raw string.
		 * @return {string} Escaped string.
		 */
		escHtml: function (str) {
			if (!str) return '';
			var div = document.createElement('div');
			div.appendChild(document.createTextNode(str));
			return div.innerHTML;
		},

		/**
		 * Escape for attribute values.
		 *
		 * @param {string} str Raw string.
		 * @return {string} Escaped string.
		 */
		escAttr: function (str) {
			if (!str && str !== 0) return '';
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#39;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;');
		},

		// ──────────────────────────────────────
		//  Price Slasher Methods
		// ──────────────────────────────────────

		/**
		 * Update count badges only (button badge + bar badge).
		 * Bar visibility is controlled separately by toggleSlasherBar().
		 */
		updateSlasherBar: function () {
			var count = Object.keys(this._selectedIds).length;
			this.$slasherCount.text(count);
			this.$slasherCount.toggleClass('has-count', count > 0);
			this.$slasherCountBar.text(count);
		},

		/**
		 * Toggle the Price Slasher bar open/closed via the toolbar button.
		 */
		toggleSlasherBar: function () {
			this._slasherOpen = !this._slasherOpen;

			if (this._slasherOpen) {
				this.$slasherBar.addClass('is-visible').attr('aria-hidden', 'false');
				this.$slasherToggleBtn
					.addClass('is-active')
					.attr('aria-expanded', 'true');
			} else {
				this.$slasherBar.removeClass('is-visible').attr('aria-hidden', 'true');
				this.$slasherToggleBtn
					.removeClass('is-active')
					.attr('aria-expanded', 'false');
			}
		},

		/**
		 * Sync the select-all checkbox state to reflect current selection.
		 * Sets indeterminate state when only some rows are selected.
		 */
		syncSelectAll: function () {
			var $rows    = this.$gridBody.find('tr[data-product-id]');
			var total    = $rows.length;
			var selected = Object.keys(this._selectedIds).length;

			var el = this.$selectAll[0];
			if (!el) return;

			if (selected === 0) {
				el.checked       = false;
				el.indeterminate = false;
			} else if (selected === total) {
				el.checked       = true;
				el.indeterminate = false;
			} else {
				el.checked       = false;
				el.indeterminate = true;
			}
		},

		/**
		 * Clear the current row selection.
		 * Does NOT close the slasher bar — user may want to adjust again.
		 */
		clearSelection: function () {
			this._selectedIds = {};
			this.$gridBody.find('.dropproduct-row-check').prop('checked', false);
			this.$gridBody.find('tr[data-product-id]').removeClass('is-selected');
			var el = this.$selectAll[0];
			if (el) { el.checked = false; el.indeterminate = false; }
			this.updateSlasherBar();
		},

		/**
		 * Send the bulk price-adjustment AJAX request.
		 *
		 * The PHP handler returns a flat {id, regular_price, sale_price}
		 * object per product so the JS can directly set each input value
		 * without any ambiguity about field ordering.
		 *
		 * @since 1.0.1
		 */
		adjustPrices: function () {
			var self = this;
			var ids  = Object.keys(this._selectedIds);

			if (!ids.length) {
				this.showNotice('Select at least one product first (tick the checkboxes in the table).', 'error');
				return;
			}

			var amount = parseFloat(this.$slasherAmount.val());
			if (!amount || amount <= 0) {
				this.showNotice('Enter a valid amount greater than zero.', 'error');
				this.$slasherAmount.focus();
				return;
			}

			var priceField = this.$slasherField.val();
			var adjustType = this.$slasherType.val();
			var operation  = this._slasherOp;

			// Loading state.
			this.$slasherApply
				.prop('disabled', true)
				.html('<span class="dashicons dashicons-update-alt spin"></span> Applying\u2026');

			$.post(dropProduct.ajaxUrl, {
				action:      'dropproduct_bulk_price_adjust',
				nonce:       dropProduct.nonce,
				product_ids: ids,
				operation:   operation,
				amount:      amount,
				adjust_type: adjustType,
				price_field: priceField,
			}, function (response) {
				self.$slasherApply
					.prop('disabled', false)
					.html('<span class="dashicons dashicons-tag"></span> Apply');

				if (response.success) {
					var updated   = response.data.updated;
					var sign      = operation === 'increase' ? '+' : '\u2212';
					var typeLabel = adjustType === 'percentage' ? amount + '%' : '$' + amount;

					$.each(updated, function (i, item) {
						var $row = $('#dropproduct-row-' + item.id);
						if (!$row.length) return;

						// Update regular price input directly.
						var $reg = $row.find('[data-field="regular_price"]');
						if ($reg.length && item.regular_price !== undefined && item.regular_price !== null) {
							$reg.val(item.regular_price);
							if (priceField === 'regular_price' || priceField === 'both') {
								self.flashPriceCell($reg);
							}
						}

						// Update sale price input directly.
						var $sale = $row.find('[data-field="sale_price"]');
						if ($sale.length && item.sale_price !== undefined && item.sale_price !== null) {
							$sale.val(item.sale_price > 0 ? item.sale_price : '');
							if (priceField === 'sale_price' || priceField === 'both') {
								self.flashPriceCell($sale);
							}
						}
					});

					self.showNotice(
						'<span class="dashicons dashicons-tag" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom;"></span> '
						+ sign + typeLabel + ' applied to ' + updated.length + ' product(s).',
						'success'
					);

					// Clear selection but keep the bar open.
					self.clearSelection();

				} else {
					self.showNotice(
						(response.data && response.data.message) || dropProduct.i18n.networkError,
						'error'
					);
				}
			}).fail(function () {
				self.$slasherApply
					.prop('disabled', false)
					.html('<span class="dashicons dashicons-tag"></span> Apply');
				self.showNotice(dropProduct.i18n.networkError, 'error');
			});
		},

		/**
		 * Flash a price input cell green to signal a successful update.
		 *
		 * @param {jQuery} $input The price input element.
		 */
		flashPriceCell: function ($input) {
			$input.addClass('price-updated');
			setTimeout(function () {
				$input.removeClass('price-updated');
			}, 1400);
		}

	};

	// Boot when DOM is ready.
	$(document).ready(function () {
		DropProduct.init();
	});

})(jQuery);
