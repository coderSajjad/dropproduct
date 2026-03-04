/**
 * WooCommerce Uploady – Bulk Product Creator — Admin JavaScript
 *
 * SPA-style grid with drag & drop upload, inline editing,
 * auto-save, image preview, and bulk publish.
 *
 * @package WooCommerce_Uploady
 * @since   1.0.0
 */

/* global jQuery, wcUploady */
(function ($) {
	'use strict';

	var Uploady = {
		/**
		 * Initialize the Uploady SPA.
		 */
		init: function () {
			this.cache();
			this.cacheModal();
			this.bindEvents();
			this.loadExistingProducts();
		},

		/**
		 * Cache DOM elements.
		 */
		cache: function () {
			this.$wrap = $('.wc-uploady-wrap');
			this.$dropzone = $('#wc-uploady-dropzone');
			this.$fileInput = $('#wc-uploady-file-input');
			this.$browseBtn = $('#wc-uploady-browse-btn');
			this.$gridBody = $('#wc-uploady-grid-body');
			this.$emptyRow = $('#wc-uploady-empty-row');
			this.$publishBtn = $('#wc-uploady-publish-all');
			this.$draftCount = $('#wc-uploady-draft-count');
			this.$notices = $('#wc-uploady-notices');
			this.$preview = $('#wc-uploady-image-preview');
			this.$previewImg = $('#wc-uploady-preview-img');
			this.$progressWrap = $('#wc-uploady-upload-progress');
			this.$progressFill = $('#wc-uploady-progress-fill');
			this.$progressText = $('#wc-uploady-progress-text');
			this.$dropInner = this.$dropzone.find('.wc-uploady-dropzone__inner');
		},

		/**
		 * Cache description modal elements.
		 */
		cacheModal: function () {
			this.$descModal = $('#wc-uploady-desc-modal');
			this.$descOverlay = $('#wc-uploady-desc-overlay');
			this.$descTextarea = $('#wc-uploady-desc-textarea');
			this.$descSaveBtn = $('#wc-uploady-desc-save');
			this.$descCancelBtn = $('#wc-uploady-desc-cancel');
			this.$descCloseBtn = $('#wc-uploady-desc-close');
			this._descProductId = 0;
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
			this.$gridBody.on('blur', '.wc-uploady-editable', function () {
				self.saveField($(this));

				// Validate prices when a price field changes.
				var fieldName = $(this).data('field');
				if (fieldName === 'regular_price' || fieldName === 'sale_price') {
					self.validatePrices($(this).closest('tr'));
				}
			});

			// Also save select fields on change.
			this.$gridBody.on('change', 'select.wc-uploady-editable', function () {
				self.saveField($(this));
			});

			// Delete product.
			this.$gridBody.on('click', '.wc-uploady-delete-btn', function () {
				var $row = $(this).closest('tr');
				if (confirm(wcUploady.i18n.deleteConfirm)) {
					self.deleteProduct($row);
				}
			});

			// Publish all.
			this.$publishBtn.on('click', function () {
				self.publishAll();
			});

			// Image preview on hover.
			this.$gridBody
				.on('mouseenter', '.wc-uploady-thumb', function (e) {
					var fullUrl = $(this).data('full');
					if (fullUrl) {
						self.$previewImg.attr('src', fullUrl);
						self.$preview.show();
						self.positionPreview(e);
					}
				})
				.on('mousemove', '.wc-uploady-thumb', function (e) {
					self.positionPreview(e);
				})
				.on('mouseleave', '.wc-uploady-thumb', function () {
					self.$preview.hide();
					self.$previewImg.attr('src', '');
				});

			// Description modal.
			this.$gridBody.on('click', '.wc-uploady-desc-btn', function () {
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
		},

		/**
		 * Load existing draft products from the server.
		 */
		loadExistingProducts: function () {
			var self = this;

			$.post(wcUploady.ajaxUrl, {
				action: 'wc_uploady_load_products',
				nonce: wcUploady.nonce
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

			formData.append('action', 'wc_uploady_upload_images');
			formData.append('nonce', wcUploady.nonce);

			var imageCount = 0;
			for (var i = 0; i < files.length; i++) {
				if (files[i].type.indexOf('image/') === 0) {
					formData.append('images[]', files[i]);
					imageCount++;
				}
			}

			if (!imageCount) {
				this.showNotice(wcUploady.i18n.uploadError, 'error');
				return;
			}

			// Show progress.
			this.$dropInner.hide();
			this.$progressWrap.show();
			this.$progressFill.css('width', '0%');
			this.$progressText.text(wcUploady.i18n.uploading);
			this.$dropzone.addClass('is-uploading');

			$.ajax({
				url: wcUploady.ajaxUrl,
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
							self.$progressText.text(wcUploady.i18n.uploading + ' ' + pct + '%');
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
						self.showNotice(response.data.message || wcUploady.i18n.uploadError, 'error');
					}
				},
				error: function () {
					self.resetDropzone();
					self.showNotice(wcUploady.i18n.networkError, 'error');
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
				if ($('#wc-uploady-row-' + product.id).length) {
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
				? '<span class="wc-uploady-gallery-badge">+' + product.gallery_count + '</span>'
				: '';

			var stockOptions = this.buildStockOptions(product.stock_status);
			var categoryOptions = this.buildCategoryOptions(product.category_id);
			var statusClass = product.status === 'publish' ? 'publish' : 'draft';

			return '<tr id="wc-uploady-row-' + product.id + '" data-product-id="' + product.id + '">'
				+ '<td class="wc-uploady-col-image">'
				+ (product.image_thumb
					? '<img src="' + product.image_thumb + '" alt="" class="wc-uploady-thumb" data-full="' + product.image_full + '" />'
					: '<span class="dashicons dashicons-format-image" style="font-size:40px;color:#dcdcde;"></span>')
				+ galleryBadge
				+ '</td>'
				+ '<td class="wc-uploady-col-title">'
				+ '<input type="text" class="wc-uploady-editable" data-field="title" value="' + this.escAttr(product.title) + '" />'
				+ '</td>'
				+ '<td class="wc-uploady-col-desc">'
				+ '<button type="button" class="wc-uploady-desc-btn' + (product.description ? ' has-desc' : '') + '" title="Edit description">'
				+ '<span class="dashicons dashicons-edit"></span>'
				+ (product.description ? '<span class="wc-uploady-desc-dot"></span>' : '')
				+ '</button>'
				+ '<input type="hidden" class="wc-uploady-desc-value" value="' + this.escAttr(product.description) + '" />'
				+ '</td>'
				+ '<td class="wc-uploady-col-price">'
				+ '<div class="wc-uploady-price-wrap">'
				+ '<span class="wc-uploady-currency">$</span>'
				+ '<input type="number" class="wc-uploady-editable wc-uploady-price-input" data-field="regular_price" value="' + this.escAttr(product.regular_price) + '" step="0.01" min="0" placeholder="0.00" />'
				+ '</div>'
				+ '</td>'
				+ '<td class="wc-uploady-col-sale-price">'
				+ '<div class="wc-uploady-price-wrap">'
				+ '<span class="wc-uploady-currency">$</span>'
				+ '<input type="number" class="wc-uploady-editable wc-uploady-price-input" data-field="sale_price" value="' + this.escAttr(product.sale_price) + '" step="0.01" min="0" placeholder="0.00" />'
				+ '</div>'
				+ '</td>'
				+ '<td class="wc-uploady-col-sku">'
				+ '<input type="text" class="wc-uploady-editable" data-field="sku" value="' + this.escAttr(product.sku) + '" />'
				+ '</td>'
				+ '<td class="wc-uploady-col-stock">'
				+ '<select class="wc-uploady-editable" data-field="stock_status">' + stockOptions + '</select>'
				+ '</td>'
				+ '<td class="wc-uploady-col-category">'
				+ '<select class="wc-uploady-editable" data-field="category">' + categoryOptions + '</select>'
				+ '</td>'
				+ '<td class="wc-uploady-col-status">'
				+ '<span class="wc-uploady-status wc-uploady-status--' + statusClass + '">' + product.status + '</span>'
				+ '</td>'
				+ '<td class="wc-uploady-col-actions">'
				+ '<button type="button" class="wc-uploady-delete-btn" title="Delete">'
				+ '<span class="dashicons dashicons-trash"></span>'
				+ '</button>'
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
			var cats = wcUploady.categories || {};

			$.each(cats, function (id, name) {
				var sel = parseInt(id) === parseInt(selectedId) ? ' selected' : '';
				html += '<option value="' + id + '"' + sel + '>' + name + '</option>';
			});

			return html;
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

			$.post(wcUploady.ajaxUrl, {
				action: 'wc_uploady_update_product',
				nonce: wcUploady.nonce,
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
				self.showNotice(wcUploady.i18n.networkError, 'error');
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
			$saleCell.find('.wc-uploady-price-warning').remove();
			$saleInput.removeClass('is-error');

			// Only validate when both fields have values.
			if (isNaN(salePrice) || salePrice === 0 || isNaN(regularPrice)) {
				return;
			}

			if (salePrice >= regularPrice) {
				$saleInput.addClass('is-error');
				$saleCell.append(
					'<span class="wc-uploady-price-warning">'
					+ '<span class="dashicons dashicons-warning"></span> '
					+ 'Sale price must be lower than regular price.'
					+ '</span>'
				);
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

			$row.addClass('is-saving');

			$.post(wcUploady.ajaxUrl, {
				action: 'wc_uploady_delete_product',
				nonce: wcUploady.nonce,
				product_id: productId
			}, function (response) {
				if (response.success) {
					$row.fadeOut(300, function () {
						$(this).remove();
						self.updateDraftCount();

						if (!self.$gridBody.find('tr:not(#wc-uploady-empty-row)').length) {
							self.$emptyRow.show();
						}
					});
				} else {
					$row.removeClass('is-saving');
					self.showNotice(response.data.message, 'error');
				}
			}).fail(function () {
				$row.removeClass('is-saving');
				self.showNotice(wcUploady.i18n.networkError, 'error');
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
			$rows.find('.wc-uploady-editable').removeClass('is-error');

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
				this.showNotice(wcUploady.i18n.validationError, 'error');
				return;
			}

			if (!productIds.length) {
				return;
			}

			this.$publishBtn.prop('disabled', true).text(wcUploady.i18n.publishing);

			$.post(wcUploady.ajaxUrl, {
				action: 'wc_uploady_publish_all',
				nonce: wcUploady.nonce,
				product_ids: productIds
			}, function (response) {
				self.$publishBtn.prop('disabled', false).html(
					'<span class="dashicons dashicons-yes-alt"></span> ' + wcUploady.i18n.publishAll
				);

				if (response.success) {
					// Mark published rows.
					$.each(response.data.published, function (i, id) {
						var $row = $('#wc-uploady-row-' + id);
						$row.addClass('is-published');
						$row.find('.wc-uploady-status')
							.removeClass('wc-uploady-status--draft')
							.addClass('wc-uploady-status--publish')
							.text('publish');
					});

					// Show failed ones.
					$.each(response.data.failed, function (i, fail) {
						var $row = $('#wc-uploady-row-' + fail.id);
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
					'<span class="dashicons dashicons-yes-alt"></span> ' + wcUploady.i18n.publishAll
				);
				self.showNotice(wcUploady.i18n.networkError, 'error');
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

		/**
		 * Show an admin notice.
		 *
		 * @param {string} message Notice text.
		 * @param {string} type    'success', 'error', or 'info'.
		 */
		showNotice: function (message, type) {
			var $notice = $('<div class="wc-uploady-notice wc-uploady-notice--' + type + '">'
				+ this.escHtml(message)
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

		/**
		 * Open the description modal for a product row.
		 *
		 * @param {jQuery} $row Table row element.
		 */
		openDescriptionModal: function ($row) {
			var productId = $row.data('product-id');
			var currentDesc = $row.find('.wc-uploady-desc-value').val() || '';

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

			$.post(wcUploady.ajaxUrl, {
				action: 'wc_uploady_update_product',
				nonce: wcUploady.nonce,
				product_id: productId,
				field: 'description',
				value: value
			}, function (response) {
				self.$descSaveBtn.prop('disabled', false).text('Save');

				if (response.success) {
					// Update hidden value in the row.
					var $row = $('#wc-uploady-row-' + productId);
					$row.find('.wc-uploady-desc-value').val(value);

					// Toggle the indicator dot.
					var $btn = $row.find('.wc-uploady-desc-btn');
					if (value.trim()) {
						$btn.addClass('has-desc');
						if (!$btn.find('.wc-uploady-desc-dot').length) {
							$btn.append('<span class="wc-uploady-desc-dot"></span>');
						}
					} else {
						$btn.removeClass('has-desc');
						$btn.find('.wc-uploady-desc-dot').remove();
					}

					self.closeDescriptionModal();
					self.showNotice('Description saved.', 'success');
				} else {
					self.showNotice(response.data.message, 'error');
				}
			}).fail(function () {
				self.$descSaveBtn.prop('disabled', false).text('Save');
				self.showNotice(wcUploady.i18n.networkError, 'error');
			});
		},

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
		}
	};

	// Boot when DOM is ready.
	$(document).ready(function () {
		Uploady.init();
	});

})(jQuery);
