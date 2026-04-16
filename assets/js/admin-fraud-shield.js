/**
 * Ultimate Order Shield — Admin JavaScript
 *
 * Handles:
 *  - Settings form AJAX save
 *  - Gauge bar live update when thresholds change
 *  - COD threshold field enable/disable
 *  - Radio card active state
 *  - Log entry deletion + clear-all
 *
 * @package DropProduct
 * @since   1.0.2
 */
/* global dpShield, jQuery */
(function ($) {
	'use strict';

	/* ──────────────────────────────────────────────────────────
	   Boot
	────────────────────────────────────────────────────────── */

	$(document).ready(function () {
		DpShield.init();
	});

	var DpShield = {

		init: function () {
			this.bindSettings();
			this.bindLogs();
			this.updateGauge();
		},

		/* ── Settings Tab ─────────────────────────────────── */

		bindSettings: function () {
			var self = this;

			// Save form via AJAX.
			$('#dpshield-form').on('submit', function (e) {
				e.preventDefault();
				self.saveSettings();
			});

			// Live gauge update.
			$('#dpshield-block-threshold, #dpshield-review-threshold').on('input change', function () {
				self.updateGauge();
			});

			// Toggle COD threshold field opacity.
			$('#dpshield-cod').on('change', function () {
				var $field = $('#dpshield-cod-threshold-field');
				if ($(this).is(':checked')) {
					$field.css({ opacity: '1', 'pointer-events': 'auto' });
				} else {
					$field.css({ opacity: '0.4', 'pointer-events': 'none' });
				}
			});

			// Radio card selection visual feedback.
			$('.dpshield-radio-group').on('change', 'input[type="radio"]', function () {
				$(this).closest('.dpshield-radio-group').find('.dpshield-radio').removeClass('is-selected');
				$(this).closest('.dpshield-radio').addClass('is-selected');
			});
		},

		saveSettings: function () {
			var self    = this;
			var $btn    = $('#dpshield-save-btn');
			var $msg    = $('#dpshield-save-msg');
			var $form   = $('#dpshield-form');

			$btn.prop('disabled', true).html(
				'<span class="dashicons dashicons-update-alt spin"></span> ' + dpShield.saving
			);
			$msg.removeClass('is-visible is-success is-error');

			var formData = $form.serializeArray();
			formData.push({ name: 'action', value: 'dropproduct_save_fraud_settings' });
			formData.push({ name: 'nonce',  value: dpShield.nonce });

			// Checkboxes (unchecked ones are absent from serializeArray).
			var checkboxes = ['enabled', 'enable_ip_country_check', 'enable_cod_restriction'];
			checkboxes.forEach(function (name) {
				if (!$form.find('[name="' + name + '"]').is(':checked')) {
					formData.push({ name: name, value: '' });
				}
			});

			$.post(dpShield.ajaxUrl, formData, function (response) {
				$btn.prop('disabled', false).html(
					'<span class="dashicons dashicons-yes-alt"></span> ' + dpShield.saveBtnLabel
				);

				if (response.success) {
					$msg.addClass('is-visible is-success').text(response.data.message || dpShield.saved);
				} else {
					$msg.addClass('is-visible is-error').text(
						(response.data && response.data.message) || dpShield.networkError
					);
				}

				// Hide message after 3 s.
				setTimeout(function () {
					$msg.removeClass('is-visible');
				}, 3000);
			}).fail(function () {
				$btn.prop('disabled', false).html(
					'<span class="dashicons dashicons-yes-alt"></span> ' + dpShield.saveBtnLabel
				);
				$msg.addClass('is-visible is-error').text(dpShield.networkError);
			});
		},

		/* ── Risk Gauge ───────────────────────────────────── */

		updateGauge: function () {
			var max    = 200;
			var review = Math.min(max, Math.max(0, parseInt($('#dpshield-review-threshold').val(), 10) || 0));
			var block  = Math.min(max, Math.max(review, parseInt($('#dpshield-block-threshold').val(), 10) || 0));

			var allowPct = (review / max * 100).toFixed(1);
			var holdPct  = ((block - review) / max * 100).toFixed(1);

			$('#dpshield-zone-allow').css('width', allowPct + '%');
			$('#dpshield-zone-hold').css('width',  holdPct + '%');

			// Update label text.
			$('.dpshield-gauge__label--hold').text('⏸ On Hold (≥' + review + ')');
			$('.dpshield-gauge__label--block').text('🚫 Block (≥' + block + ')');
		},

		/* ── Logs Tab ─────────────────────────────────────── */

		bindLogs: function () {
			var self = this;

			// Delete single log entry.
			$(document).on('click', '.dpshield-delete-log', function () {
				var $btn = $(this);
				var id   = $btn.data('log-id');
				if (!id) return;

				if (!window.confirm(dpShield.confirmDelete)) return;

				$btn.prop('disabled', true).find('.dashicons').addClass('spin');

				$.post(dpShield.ajaxUrl, {
					action:  'dropproduct_fraud_delete_log',
					nonce:   dpShield.nonce,
					log_id:  id,
				}, function (response) {
					if (response.success) {
						$btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
					} else {
						$btn.prop('disabled', false).find('.dashicons').removeClass('spin');
					}
				});
			});

			// Clear all logs.
			$('#dpshield-clear-logs-btn').on('click', function () {
				if (!window.confirm(dpShield.confirmClear)) return;

				var $btn = $(this);
				$btn.prop('disabled', true);

				$.post(dpShield.ajaxUrl, {
					action: 'dropproduct_fraud_clear_logs',
					nonce:  dpShield.nonce,
				}, function (response) {
					if (response.success) {
						$('.dpshield-log-table tbody').html(
							'<tr><td colspan="9" style="text-align:center;padding:30px;color:#9ca3af;">' + dpShield.logsCleared + '</td></tr>'
						);
					}
					$btn.prop('disabled', false);
				});
			});
		}
	};

})(jQuery);
