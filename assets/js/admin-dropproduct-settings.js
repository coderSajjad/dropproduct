/**
 * WooCommerce DropProduct – Settings Page JavaScript
 *
 * Handles the toggle switcher auto-save and form submission
 * for the DropProduct settings admin page.
 *
 * @package DropProduct
 * @since   1.0.1
 */

/* global jQuery, dropProductSettings */
(function ($) {
	'use strict';

	var DropProductSettingsPage = {

		init: function () {
			this.cache();
			this.bindEvents();
		},

		cache: function () {
			this.$form = $('#dropproduct-settings-save').closest('.dropproduct-settings-wrap');
			this.$saveBtn = $('#dropproduct-settings-save');
			this.$notices = $('#dropproduct-settings-notices');
			this.$autoAltToggle = $('#dropproduct-auto-alt-text');
		},

		bindEvents: function () {
			var self = this;

			// Live visual feedback when toggle changes.
			this.$autoAltToggle.on('change', function () {
				var $card = $(this).closest('.dropproduct-settings-card');
				if ($(this).is(':checked')) {
					$card.removeClass('is-disabled');
				} else {
					$card.addClass('is-disabled');
				}
			});

			// Save button.
			this.$saveBtn.on('click', function () {
				self.save();
			});
		},

		/**
		 * Save settings via AJAX.
		 */
		save: function () {
			var self = this;

			this.$saveBtn
				.prop('disabled', true)
				.html('<span class="dashicons dashicons-update-alt spin"></span> ' + dropProductSettings.i18n.saving);

			var data = {
				action:        'dropproduct_save_settings',
				nonce:         dropProductSettings.nonce,
				auto_alt_text: this.$autoAltToggle.is(':checked') ? '1' : '0',
			};

			$.post(dropProductSettings.ajaxUrl, data, function (response) {
				self.$saveBtn.prop('disabled', false).html(
					'<span class="dashicons dashicons-saved"></span> Save Settings'
				);

				if (response.success) {
					self.showNotice(response.data.message || dropProductSettings.i18n.saved, 'success');
				} else {
					self.showNotice(
						(response.data && response.data.message) || dropProductSettings.i18n.networkError,
						'error'
					);
				}
			}).fail(function () {
				self.$saveBtn.prop('disabled', false).html(
					'<span class="dashicons dashicons-saved"></span> Save Settings'
				);
				self.showNotice(dropProductSettings.i18n.networkError, 'error');
			});
		},

		/**
		 * Show a temporary notice.
		 *
		 * @param {string} message Message text.
		 * @param {string} type    'success' or 'error'.
		 */
		showNotice: function (message, type) {
			var $notice = $(
				'<div class="dropproduct-settings-notice dropproduct-settings-notice--' + type + '">'
				+ '<span class="dashicons dashicons-' + (type === 'success' ? 'yes-alt' : 'warning') + '"></span> '
				+ message
				+ '</div>'
			);

			this.$notices.empty().append($notice);

			setTimeout(function () {
				$notice.fadeOut(300, function () { $(this).remove(); });
			}, 4000);
		},
	};

	$(document).ready(function () {
		DropProductSettingsPage.init();
	});

})(jQuery);
