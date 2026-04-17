/**
 * DropProduct Dashboard — Admin JavaScript
 *
 * SPA-style dashboard: all 5 widgets fetched in parallel via AJAX.
 * Zero page reloads. Skeleton loaders show while data is in flight.
 *
 * @package DropProduct
 * @since   1.0.3
 */
/* global dpDashboard, jQuery */
(function ($) {
    'use strict';

    var DpDash = {

        cfg: window.dpDashboard || {},
        currency: window.woocommerce_admin_meta_boxes ? window.woocommerce_admin_meta_boxes.currency_format : '%s',
        currencySymbol: window.dpDashboard ? window.dpDashboard.currency_symbol : '$',
        _invTab: 'low',    // currently active inventory tab
        _invData: null,    // cached inventory response

        /* ────────────────────────────────────────────────────
           Boot
           ──────────────────────────────────────────────────── */
        init: function () {
            this.bindRefresh();
            this.bindInvTabs();
            this.loadAll(false);
        },

        loadAll: function (force) {
            this.fetchFinancials(force);
            this.fetchSecurity(force);
            this.fetchOrders(force);
            this.fetchInventory(force);
            this.fetchReadiness(force);

            var now = new Date();
            $('#dpd-last-updated').text(
                DpDash.cfg.strings.updated_at + ' ' +
                now.getHours() + ':' + String(now.getMinutes()).padStart(2, '0')
            );
        },

        bindRefresh: function () {
            $('#dpd-refresh-btn').on('click', function () {
                var $icon = $('#dpd-refresh-icon');
                $icon.addClass('dpd-spinning');

                $.post(DpDash.cfg.ajaxUrl, {
                    action: 'dropproduct_dashboard_flush_cache',
                    nonce:  DpDash.cfg.nonce,
                }, function () {
                    DpDash.loadAll(true);
                    setTimeout(function () { $icon.removeClass('dpd-spinning'); }, 1200);
                });
            });
        },

        bindInvTabs: function () {
            $(document).on('click', '.dpd-tab', function () {
                var tab = $(this).data('tab');
                $('.dpd-tab').removeClass('is-active');
                $(this).addClass('is-active');
                DpDash._invTab = tab;
                if (DpDash._invData) {
                    DpDash.renderInventory(DpDash._invData);
                }
            });
        },

        /* ────────────────────────────────────────────────────
           A. Financial KPIs
           ──────────────────────────────────────────────────── */
        fetchFinancials: function (force) {
            $.post(this.cfg.ajaxUrl, {
                action: 'dropproduct_dashboard_financials',
                nonce:  this.cfg.nonce,
                force:  force ? 1 : 0,
            }, function (res) {
                if (!res.success) { return; }
                var d = res.data;

                DpDash.countUp('#dpd-kpi-inv', d.inventory_value, true);
                DpDash.countUp('#dpd-kpi-profit', d.potential_profit, true);
                DpDash.removeSkeleton('#dpd-kpi-margin');
                $('#dpd-kpi-margin').text(d.avg_margin.toFixed(1) + '%');
            });
        },

        /* ────────────────────────────────────────────────────
           B. Security / Order Shield
           ──────────────────────────────────────────────────── */
        fetchSecurity: function (force) {
            $.post(this.cfg.ajaxUrl, {
                action: 'dropproduct_dashboard_security',
                nonce:  this.cfg.nonce,
                force:  force ? 1 : 0,
            }, function (res) {
                if (!res.success) {
                    DpDash.error('dpd-security-body');
                    return;
                }
                var d = res.data;

                // KPI strip.
                DpDash.countUp('#dpd-kpi-threats', d.total_blocked, false);

                // Shield status badge.
                var $badge = $('#dpd-shield-badge');
                DpDash.removeSkeleton('#dpd-shield-badge');
                if (d.shield_enabled) {
                    $badge.addClass('dpd-shield-badge--active').text('🛡 Protected');
                } else {
                    $badge.addClass('dpd-shield-badge--inactive').text('⚠ Inactive');
                }

                // Stats row.
                var statsHtml =
                    '<div class="dpd-shield-stat-row">' +
                    DpDash.shieldStat(d.total_blocked, DpDash.cfg.strings.blocked, 'block') +
                    DpDash.shieldStat(d.total_on_hold, DpDash.cfg.strings.on_hold,  'hold') +
                    DpDash.shieldStat(d.total_allowed, DpDash.cfg.strings.allowed,  'allow') +
                    '</div>';

                // Recent threats list.
                var threatsHtml = '';
                if (d.recent_threats && d.recent_threats.length) {
                    threatsHtml = '<div class="dpd-threat-list">';
                    $.each(d.recent_threats, function (i, t) {
                        threatsHtml +=
                            '<div class="dpd-threat-item">' +
                            '<span class="dpd-threat-action dpd-threat-action--' + t.action + '">' + t.action + '</span>' +
                            '<span class="dpd-threat-email">' + DpDash.esc(t.email || t.ip) + '</span>' +
                            '<span class="dpd-threat-score">+' + t.score + '</span>' +
                            '</div>';
                    });
                    threatsHtml += '</div>';
                } else {
                    threatsHtml = '<p class="dpd-empty"><span class="dpd-empty__icon">✅</span><br>' + DpDash.cfg.strings.no_threats + '</p>';
                }

                $('#dpd-security-body').html(statsHtml + threatsHtml);
            }).fail(function () { DpDash.error('dpd-security-body'); });
        },

        shieldStat: function (num, label, cls) {
            return '<div class="dpd-shield-stat dpd-shield-stat--' + cls + '">' +
                '<span class="dpd-shield-stat__num">' + DpDash.numFormat(num) + '</span>' +
                '<span class="dpd-shield-stat__label">' + DpDash.esc(label) + '</span>' +
                '</div>';
        },

        /* ────────────────────────────────────────────────────
           C. Urgent Orders
           ──────────────────────────────────────────────────── */
        fetchOrders: function (force) {
            $.post(this.cfg.ajaxUrl, {
                action: 'dropproduct_dashboard_orders',
                nonce:  this.cfg.nonce,
                force:  force ? 1 : 0,
            }, function (res) {
                if (!res.success) { DpDash.error('dpd-orders-body'); return; }
                var orders = res.data;

                if (!orders || !orders.length) {
                    $('#dpd-orders-body').html(
                        '<div class="dpd-empty"><div class="dpd-empty__icon">🎉</div>' +
                        DpDash.cfg.strings.no_orders + '</div>'
                    );
                    return;
                }

                var html = '<table class="dpd-table">' +
                    '<thead><tr>' +
                    '<th>#</th>' +
                    '<th>' + DpDash.cfg.strings.customer + '</th>' +
                    '<th>' + DpDash.cfg.strings.status + '</th>' +
                    '<th>' + DpDash.cfg.strings.total + '</th>' +
                    '<th>' + DpDash.cfg.strings.waiting + '</th>' +
                    '</tr></thead><tbody>';

                $.each(orders, function (i, o) {
                    var pillCls = o.status_key === 'pending' ? 'pending' : 'processing';
                    html += '<tr>' +
                        '<td><a href="' + o.edit_url + '" class="dpd-order-id" target="_blank">#' + o.id + '</a></td>' +
                        '<td>' + DpDash.esc(o.customer) + '</td>' +
                        '<td><span class="dpd-status-pill dpd-status-pill--' + pillCls + '">' + DpDash.esc(o.status) + '</span></td>' +
                        '<td>' + o.total + '</td>' +
                        '<td class="dpd-time-ago">' + DpDash.esc(o.time_ago) + '</td>' +
                        '</tr>';
                });

                html += '</tbody></table>';
                $('#dpd-orders-body').html(html);
            }).fail(function () { DpDash.error('dpd-orders-body'); });
        },

        /* ────────────────────────────────────────────────────
           D. Inventory Alerts
           ──────────────────────────────────────────────────── */
        fetchInventory: function (force) {
            $.post(this.cfg.ajaxUrl, {
                action: 'dropproduct_dashboard_inventory',
                nonce:  this.cfg.nonce,
                force:  force ? 1 : 0,
            }, function (res) {
                if (!res.success) { DpDash.error('dpd-inventory-body'); return; }
                DpDash._invData = res.data;
                DpDash.renderInventory(res.data);
            }).fail(function () { DpDash.error('dpd-inventory-body'); });
        },

        renderInventory: function (d) {
            var tab   = this._invTab;
            var items = tab === 'low'   ? d.low_stock
                      : tab === 'oos'   ? d.out_of_stock
                                        : d.ghost;

            if (!items || !items.length) {
                $('#dpd-inventory-body').html(
                    '<div class="dpd-empty"><div class="dpd-empty__icon">🎉</div>' +
                    this.cfg.strings.all_good + '</div>'
                );
                return;
            }

            var html = '<ul class="dpd-inv-list">';
            $.each(items, function (i, item) {
                var badge = '';
                if (tab === 'low') {
                    badge = '<span class="dpd-inv-qty dpd-inv-qty--low">' + item.qty + ' ' + DpDash.cfg.strings.left + '</span>';
                } else if (tab === 'oos') {
                    badge = '<span class="dpd-inv-qty dpd-inv-qty--oos">' + DpDash.cfg.strings.out_of_stock + '</span>';
                } else {
                    badge = '<span class="dpd-inv-qty dpd-inv-qty--ghost">' + DpDash.cfg.strings.incomplete + '</span>';
                }

                html +=
                    '<li class="dpd-inv-item">' +
                    '<a href="' + item.edit_url + '" class="dpd-inv-item__name" target="_blank">' + DpDash.esc(item.title) + '</a>' +
                    badge +
                    '</li>';
            });

            html += '</ul>';
            $('#dpd-inventory-body').html(html);
        },

        /* ────────────────────────────────────────────────────
           E. Store Readiness
           ──────────────────────────────────────────────────── */
        fetchReadiness: function (force) {
            $.post(this.cfg.ajaxUrl, {
                action: 'dropproduct_dashboard_readiness',
                nonce:  this.cfg.nonce,
                force:  force ? 1 : 0,
            }, function (res) {
                if (!res.success) { DpDash.error('dpd-readiness-body'); return; }
                var d = res.data;

                // Progress bar + percentage.
                DpDash.removeSkeleton('#dpd-readiness-pct');
                $('#dpd-readiness-pct').text(d.percent + '%');
                setTimeout(function () {
                    $('#dpd-readiness-bar').css('width', d.percent + '%');
                }, 100);

                // Checklist.
                var html = '<div class="dpd-check-list">';
                $.each(d.items, function (i, item) {
                    var cls = item.done ? 'dpd-check-item--done' : 'dpd-check-item--pending';
                    html +=
                        '<div class="dpd-check-item ' + cls + '">' +
                        '<div class="dpd-check-item__left">' +
                        '<span class="dpd-check-icon"></span>' +
                        DpDash.esc(item.label) +
                        '</div>' +
                        (!item.done
                            ? '<a href="' + item.fix_url + '" class="dpd-fix-link" target="_blank">Fix →</a>'
                            : ''
                        ) +
                        '</div>';
                });
                html += '</div>';
                $('#dpd-readiness-body').html(html);
            }).fail(function () { DpDash.error('dpd-readiness-body'); });
        },

        /* ────────────────────────────────────────────────────
           Helpers
           ──────────────────────────────────────────────────── */

        /** Animated number counter. */
        countUp: function (selector, target, isCurrency) {
            var $el      = $(selector);
            var duration = 900;
            var start    = 0;
            var startTime = null;

            DpDash.removeSkeleton(selector);

            (function step(timestamp) {
                if (!startTime) { startTime = timestamp; }
                var progress = Math.min((timestamp - startTime) / duration, 1);
                var ease     = 1 - Math.pow(1 - progress, 3);
                var current  = Math.round(start + (target - start) * ease * 100) / 100;

                if (isCurrency) {
                    $el.text(DpDash.formatCurrency(current));
                } else {
                    $el.text(DpDash.numFormat(current));
                }

                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    if (isCurrency) {
                        $el.text(DpDash.formatCurrency(target));
                    } else {
                        $el.text(DpDash.numFormat(target));
                    }
                }
            })(performance.now());
        },

        formatCurrency: function (val) {
            var sym = DpDash.cfg.currency_symbol || '$';
            return sym + Number(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        numFormat: function (val) {
            return Number(val).toLocaleString();
        },

        removeSkeleton: function (selector) {
            $(selector).removeClass('dpd-skeleton').css({ background: '', color: '' });
        },

        error: function (bodyId) {
            $('#' + bodyId).html(
                '<div class="dpd-error-msg">⚠ ' + (DpDash.cfg.strings.load_error || 'Failed to load data.') + '</div>'
            );
        },

        esc: function (str) {
            return $('<span>').text(str || '').html();
        },
    };

    $(function () { DpDash.init(); });

}(jQuery));
