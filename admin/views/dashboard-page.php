<?php
/**
 * Dashboard Admin Page
 *
 * Skeleton shell — every widget is populated by JS via AJAX.
 * Zero PHP rendering of data — the page loads instantly and
 * JS fills each widget card once the AJAX responses arrive.
 *
 * @package DropProduct
 * @since   1.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render skeleton placeholder rows for instant loading feedback.
 *
 * @param int $rows  Number of rows.
 * @param int $cells Number of cells per row.
 * @return string    HTML string.
 */
function dropproduct_dashboard_skeleton_rows( $rows, $cells ) {
    $html = '<div class="dpd-skeleton-list">';
    for ( $r = 0; $r < $rows; $r++ ) {
        $html .= '<div class="dpd-skeleton-row">';
        for ( $c = 0; $c < $cells; $c++ ) {
            $html .= '<span class="dpd-skeleton-cell dpd-skeleton"></span>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}
?>

<div id="dropproduct-dashboard-root" class="dpd-root">

    <!-- ═══ HEADER ═══════════════════════════════════════════ -->
    <div class="dpd-header">
        <div class="dpd-header__left">
            <div>
                <h1 class="dpd-header__title">
                    <?php esc_html_e( 'Store Dashboard', 'dropproduct' ); ?>
                </h1>
                <p class="dpd-header__sub">
                    <?php esc_html_e( 'Real-time financial insights, security reports &amp; inventory alerts', 'dropproduct' ); ?>
                </p>
            </div>
        </div>
        <div class="dpd-header__right">
            <span id="dpd-last-updated" class="dpd-last-updated"></span>
            <button type="button" id="dpd-refresh-btn" class="dpd-btn dpd-btn--ghost"
                    title="<?php esc_attr_e( 'Refresh all widgets and clear cache', 'dropproduct' ); ?>">
                <span class="dashicons dashicons-update-alt" id="dpd-refresh-icon"></span>
                <?php esc_html_e( 'Refresh', 'dropproduct' ); ?>
            </button>
        </div>
    </div>

    <!-- ═══ KPI STRIP ════════════════════════════════════════ -->
    <div class="dpd-kpi-strip" id="dpd-kpi-strip">

        <div class="dpd-kpi dpd-kpi--inv">
            <span class="dpd-kpi__icon">💰</span>
            <div class="dpd-kpi__body">
                <span class="dpd-kpi__value dpd-skeleton" id="dpd-kpi-inv">0.00</span>
                <span class="dpd-kpi__label">
                    <?php esc_html_e( 'Inventory Value', 'dropproduct' ); ?>
                </span>
            </div>
        </div>

        <div class="dpd-kpi dpd-kpi--profit">
            <span class="dpd-kpi__icon">📈</span>
            <div class="dpd-kpi__body">
                <span class="dpd-kpi__value dpd-skeleton" id="dpd-kpi-profit">0.00</span>
                <span class="dpd-kpi__label">
                    <?php esc_html_e( 'Potential Profit', 'dropproduct' ); ?>
                </span>
            </div>
        </div>

        <div class="dpd-kpi dpd-kpi--margin">
            <span class="dpd-kpi__icon">🎯</span>
            <div class="dpd-kpi__body">
                <span class="dpd-kpi__value dpd-skeleton" id="dpd-kpi-margin">0%</span>
                <span class="dpd-kpi__label">
                    <?php esc_html_e( 'Avg Margin', 'dropproduct' ); ?>
                </span>
            </div>
        </div>

        <div class="dpd-kpi dpd-kpi--threats">
            <span class="dpd-kpi__icon">🛡️</span>
            <div class="dpd-kpi__body">
                <span class="dpd-kpi__value dpd-skeleton" id="dpd-kpi-threats">0</span>
                <span class="dpd-kpi__label">
                    <?php esc_html_e( 'Threats Blocked', 'dropproduct' ); ?>
                </span>
            </div>
        </div>

    </div><!-- /.dpd-kpi-strip -->

    <!-- ═══ MAIN GRID ════════════════════════════════════════ -->
    <div class="dpd-grid">

        <!-- ── LEFT / MAIN COLUMN ────────────────────────── -->
        <div class="dpd-col dpd-col--main">

            <!-- C. Urgent Orders ──────────────────────────── -->
            <div class="dpd-card" id="dpd-orders-card">
                <div class="dpd-card__head">
                    <div class="dpd-card__head-left">
                        <span class="dpd-card__icon">⚡</span>
                        <h2><?php esc_html_e( 'Urgent Orders', 'dropproduct' ); ?></h2>
                    </div>
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_status=wc-pending&post_type=shop_order' ) ); ?>"
                       class="dpd-card__link" target="_blank">
                        <?php esc_html_e( 'View All Orders', 'dropproduct' ); ?> →
                    </a>
                </div>
                <div class="dpd-card__body" id="dpd-orders-body">
                    <?php echo wp_kses_post( dropproduct_dashboard_skeleton_rows( 4, 4 ) ); ?>
                </div>
            </div>

            <!-- D. Inventory Alerts ───────────────────────── -->
            <div class="dpd-card" id="dpd-inventory-card">
                <div class="dpd-card__head">
                    <div class="dpd-card__head-left">
                        <span class="dpd-card__icon">📦</span>
                        <h2><?php esc_html_e( 'Inventory Alerts', 'dropproduct' ); ?></h2>
                    </div>
                    <div class="dpd-inv-tabs" id="dpd-inv-tabs" role="tablist">
                        <button class="dpd-tab is-active" data-tab="low" role="tab">
                            <?php esc_html_e( 'Low Stock', 'dropproduct' ); ?>
                        </button>
                        <button class="dpd-tab" data-tab="oos" role="tab">
                            <?php esc_html_e( 'Out of Stock', 'dropproduct' ); ?>
                        </button>
                        <button class="dpd-tab" data-tab="ghost" role="tab">
                            <?php esc_html_e( 'Ghost Products', 'dropproduct' ); ?>
                        </button>
                    </div>
                </div>
                <div class="dpd-card__body" id="dpd-inventory-body">
                    <?php echo wp_kses_post( dropproduct_dashboard_skeleton_rows( 5, 2 ) ); ?>
                </div>
            </div>

        </div><!-- /.dpd-col--main -->

        <!-- ── RIGHT / SIDEBAR COLUMN ────────────────────── -->
        <div class="dpd-col dpd-col--side">

            <!-- B. Security Shield ────────────────────────── -->
            <div class="dpd-card dpd-card--security" id="dpd-security-card">
                <div class="dpd-card__head">
                    <div class="dpd-card__head-left">
                        <span class="dpd-card__icon">🛡️</span>
                        <h2><?php esc_html_e( 'Order Shield', 'dropproduct' ); ?></h2>
                    </div>
                    <span class="dpd-shield-badge dpd-skeleton" id="dpd-shield-badge">…</span>
                </div>
                <div class="dpd-card__body" id="dpd-security-body">
                    <?php echo wp_kses_post( dropproduct_dashboard_skeleton_rows( 3, 2 ) ); ?>
                </div>
                <div class="dpd-card__foot">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=dropproduct-fraud-shield' ) ); ?>"
                       class="dpd-btn dpd-btn--sm dpd-btn--ghost">
                        <?php esc_html_e( 'Full Shield Report', 'dropproduct' ); ?> →
                    </a>
                </div>
            </div>

            <!-- E. Store Readiness ────────────────────────── -->
            <div class="dpd-card dpd-card--readiness" id="dpd-readiness-card">
                <div class="dpd-card__head">
                    <div class="dpd-card__head-left">
                        <span class="dpd-card__icon">✅</span>
                        <h2><?php esc_html_e( 'Store Readiness', 'dropproduct' ); ?></h2>
                    </div>
                    <span class="dpd-readiness-pct dpd-skeleton" id="dpd-readiness-pct">0%</span>
                </div>
                <div class="dpd-readiness-bar-wrap">
                    <div class="dpd-readiness-bar" id="dpd-readiness-bar" style="width:0%"></div>
                </div>
                <div class="dpd-card__body" id="dpd-readiness-body">
                    <?php echo wp_kses_post( dropproduct_dashboard_skeleton_rows( 6, 1 ) ); ?>
                </div>
            </div>

        </div><!-- /.dpd-col--side -->

    </div><!-- /.dpd-grid -->

</div><!-- /#dropproduct-dashboard-root -->
