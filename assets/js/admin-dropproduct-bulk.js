/**
 * DropProduct — Free Bulk Editing
 *
 * Handles bulk price, category, stock, tax class, and shipping class
 * updates for selected products in the DropProduct grid.
 *
 * @package DropProduct
 * @since   1.1.1
 */

/* global jQuery, dropProduct */
(function ($) {
    'use strict';

    var DropProductBulk = {

        init: function () {
            this.cache();
            this.bindEvents();
            this.observeGrid();
        },

        cache: function () {
            this.$gridBody     = $('#dropproduct-grid-body');
            this.$bulkBar      = $('#dropproduct-pro-bulk-bar');
            this.$count        = $('#dropproduct-pro-selected-count');
            this.$promptOvl    = $('#dropproduct-pro-prompt-overlay');
            this.$promptModal  = $('#dropproduct-pro-prompt-modal');
            this.$promptTitle  = $('#dropproduct-pro-prompt-title');
            this.$promptBody   = $('#dropproduct-pro-prompt-body');
            this.$promptApply  = $('#dropproduct-pro-prompt-apply');
            this.$promptCancel = $('#dropproduct-pro-prompt-cancel');
            this.$promptClose  = $('#dropproduct-pro-prompt-close');
            this._action       = '';
        },

        bindEvents: function () {
            var self = this;

            // Select-all header checkbox.
            $(document).on('change', '#dropproduct-select-all', function () {
                self.$gridBody.find('.dropproduct-row-check').prop('checked', $(this).is(':checked'));
                self.updateBar();
            });

            // Per-row checkboxes.
            this.$gridBody.on('change', '.dropproduct-row-check', function () {
                self.updateBar();
            });

            // Bulk action buttons.
            this.$bulkBar.on('click', '.dropproduct-pro-bulk-btn', function () {
                self.openPrompt($(this).data('action'));
            });

            // Prompt footer.
            this.$promptApply.on('click', function () { self.execute(); });
            this.$promptCancel.on('click', function () { self.closePrompt(); });
            this.$promptClose.on('click', function () { self.closePrompt(); });
            this.$promptOvl.on('click', function () { self.closePrompt(); });
        },

        observeGrid: function () {
            var self = this;
            if (!this.$gridBody[0]) return;
            var obs = new MutationObserver(function () { self.updateBar(); });
            obs.observe(this.$gridBody[0], { childList: true });
        },

        updateBar: function () {
            var count = this.selectedIds().length;
            this.$count.text(count);
            if (count > 0) {
                this.$bulkBar.slideDown(200);
            } else {
                this.$bulkBar.slideUp(200);
            }
        },

        selectedIds: function () {
            var ids = [];
            this.$gridBody.find('.dropproduct-row-check:checked').each(function () {
                ids.push($(this).closest('tr').data('product-id'));
            });
            return ids;
        },

        openPrompt: function (action) {
            this._action = action;
            var title = '', html = '';

            switch (action) {
                case 'price':
                    title = 'Set Regular Price';
                    html  = '<label>' + 'Price:&nbsp;'
                          + '<input type="number" id="dropproduct-pro-bulk-value" step="0.01" min="0" placeholder="0.00" /></label>';
                    break;

                case 'category':
                    title = 'Set Category';
                    html  = '<label>' + 'Category:&nbsp;'
                          + '<select id="dropproduct-pro-bulk-value"><option value="">— None —</option>';
                    $.each(dropProduct.categories || {}, function (id, name) {
                        html += '<option value="' + id + '">' + $('<span>').text(name).html() + '</option>';
                    });
                    html += '</select></label>';
                    break;

                case 'stock':
                    title = 'Set Stock Status';
                    html  = '<label>' + 'Stock:&nbsp;'
                          + '<select id="dropproduct-pro-bulk-value">'
                          + '<option value="instock">In stock</option>'
                          + '<option value="outofstock">Out of stock</option>'
                          + '<option value="onbackorder">On backorder</option>'
                          + '</select></label>';
                    break;

                case 'tax':
                    title = 'Set Tax Class';
                    html  = '<label>' + 'Tax Class:&nbsp;'
                          + '<select id="dropproduct-pro-bulk-value">';
                    $.each(dropProduct.taxClasses || { '': 'Standard' }, function (slug, label) {
                        html += '<option value="' + slug + '">' + $('<span>').text(label).html() + '</option>';
                    });
                    html += '</select></label>';
                    break;

                case 'shipping':
                    title = 'Set Shipping Class';
                    html  = '<label>' + 'Shipping Class:&nbsp;'
                          + '<select id="dropproduct-pro-bulk-value"><option value="">— None —</option>';
                    $.each(dropProduct.shippingClasses || {}, function (id, name) {
                        html += '<option value="' + id + '">' + $('<span>').text(name).html() + '</option>';
                    });
                    html += '</select></label>';
                    break;

                default:
                    return;
            }

            this.$promptTitle.text(title);
            this.$promptBody.html(html);
            this.$promptOvl.addClass('is-open');
            this.$promptModal.addClass('is-open');
        },

        closePrompt: function () {
            this.$promptOvl.removeClass('is-open');
            this.$promptModal.removeClass('is-open');
            this._action = '';
        },

        execute: function () {
            var self  = this;
            var ids   = this.selectedIds();
            var field = { price: 'regular_price', category: 'category', stock: 'stock_status',
                          tax: 'tax_class', shipping: 'shipping_class' }[this._action];

            if (!ids.length || !field) { this.closePrompt(); return; }

            var value = $('#dropproduct-pro-bulk-value').val();

            this.$promptApply.prop('disabled', true).text('Applying…');

            $.post(dropProduct.ajaxUrl, {
                action:      'dropproduct_bulk_update',
                nonce:       dropProduct.nonce,
                product_ids: ids,
                field:       field,
                value:       value
            })
            .done(function (res) {
                self.$promptApply.prop('disabled', false).text('Apply');
                self.closePrompt();
                if (res.success) {
                    self.refreshRows(ids, field, value);
                    self.notice(res.data.updated + ' product(s) updated.', 'success');
                } else {
                    self.notice((res.data && res.data.message) || 'Error updating products.', 'error');
                }
            })
            .fail(function () {
                self.$promptApply.prop('disabled', false).text('Apply');
                self.notice((dropProduct.i18n && dropProduct.i18n.networkError) || 'Network error.', 'error');
            });
        },

        refreshRows: function (ids, field, value) {
            $.each(ids, function (i, id) {
                var $input = $('#dropproduct-row-' + id).find('[data-field="' + field + '"]');
                if ($input.length) {
                    $input.val(value).addClass('is-saved');
                    setTimeout(function () { $input.removeClass('is-saved'); }, 1500);
                }
            });
        },

        notice: function (msg, type) {
            var $n = $('<div class="dropproduct-notice dropproduct-notice--' + type + '">'
                + $('<span>').text(msg).html() + '</div>');
            $('#dropproduct-notices').prepend($n);
            setTimeout(function () { $n.fadeOut(300, function () { $n.remove(); }); }, 5000);
        }
    };

    $(document).ready(function () {
        if ($('#dropproduct-pro-bulk-bar').length) {
            DropProductBulk.init();
        }
    });

})(jQuery);
