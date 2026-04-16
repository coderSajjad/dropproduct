<?php
/**
 * Ultimate Order Shield — Admin Page
 *
 * Two-tab layout:  Settings | Activity Logs
 *
 * @package DropProduct
 * @since   1.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ── Data ───────────────────────────────────────────────── */
global $dropproduct_fraud_shield_instance;

/** @var DropProduct_Fraud_Shield $shield */
$shield  = $dropproduct_fraud_shield_instance;
$cfg     = $shield->get_settings();

/** @var DropProduct_Fraud_Logger $fraud_logger_instance */
global $dropproduct_fraud_logger_instance;
$fraud_logger = $dropproduct_fraud_logger_instance;

$summary = $fraud_logger->get_summary();

// Logs tab pagination.
$per_page      = 30;
$current_page  = max( 1, absint( $_GET['paged'] ?? 1 ) );
$action_filter = in_array( $_GET['action_filter'] ?? '', array( 'BLOCK', 'ON_HOLD', 'ALLOW' ), true )
    ? sanitize_text_field( wp_unslash( $_GET['action_filter'] ) ) : '';
$offset        = ( $current_page - 1 ) * $per_page;
$log_data      = $fraud_logger->get_logs( $per_page, $offset, $action_filter );
$log_rows      = $log_data['rows'];
$log_total     = $log_data['total'];
$total_pages   = (int) ceil( $log_total / $per_page );

$active_tab    = in_array( $_GET['tab'] ?? '', array( 'settings', 'logs' ), true )
    ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
?>
<div class="dpshield-wrap">

    <!-- ═══ HEADER ══════════════════════════════════════════ -->
    <div class="dpshield-header">
        <div class="dpshield-header__brand">
            <div class="dpshield-header__icon">🛡️</div>
            <div>
                <h1><?php esc_html_e( 'Ultimate Order Shield', 'dropproduct' ); ?></h1>
                <p><?php esc_html_e( 'Security & Anti-Fraud Protection for WooCommerce', 'dropproduct' ); ?></p>
            </div>
        </div>
        <div class="dpshield-header__status <?php echo ! empty( $cfg['enabled'] ) ? 'is-active' : 'is-inactive'; ?>">
            <?php echo ! empty( $cfg['enabled'] ) ? '● ' . esc_html__( 'Active', 'dropproduct' ) : '○ ' . esc_html__( 'Inactive', 'dropproduct' ); ?>
        </div>
    </div>

    <!-- ═══ STATS BAR ═══════════════════════════════════════ -->
    <div class="dpshield-stats">
        <div class="dpshield-stat">
            <span class="dpshield-stat__value"><?php echo esc_html( number_format_i18n( $summary['total'] ) ); ?></span>
            <span class="dpshield-stat__label"><?php esc_html_e( 'Total Checks', 'dropproduct' ); ?></span>
        </div>
        <div class="dpshield-stat dpshield-stat--block">
            <span class="dpshield-stat__value"><?php echo esc_html( number_format_i18n( $summary['BLOCK'] ) ); ?></span>
            <span class="dpshield-stat__label"><?php esc_html_e( 'Blocked', 'dropproduct' ); ?></span>
        </div>
        <div class="dpshield-stat dpshield-stat--hold">
            <span class="dpshield-stat__value"><?php echo esc_html( number_format_i18n( $summary['ON_HOLD'] ) ); ?></span>
            <span class="dpshield-stat__label"><?php esc_html_e( 'On Hold', 'dropproduct' ); ?></span>
        </div>
        <div class="dpshield-stat dpshield-stat--allow">
            <span class="dpshield-stat__value"><?php echo esc_html( number_format_i18n( $summary['ALLOW'] ) ); ?></span>
            <span class="dpshield-stat__label"><?php esc_html_e( 'Allowed', 'dropproduct' ); ?></span>
        </div>
    </div>

    <!-- ═══ TABS ════════════════════════════════════════════ -->
    <div class="dpshield-tab-nav">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=dropproduct-fraud-shield&tab=settings' ) ); ?>"
           class="dpshield-tab-nav__item <?php echo 'settings' === $active_tab ? 'is-active' : ''; ?>">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php esc_html_e( 'Settings', 'dropproduct' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=dropproduct-fraud-shield&tab=logs' ) ); ?>"
           class="dpshield-tab-nav__item <?php echo 'logs' === $active_tab ? 'is-active' : ''; ?>">
            <span class="dashicons dashicons-list-view"></span>
            <?php esc_html_e( 'Activity Logs', 'dropproduct' ); ?>
            <?php if ( $summary['BLOCK'] + $summary['ON_HOLD'] > 0 ) : ?>
                <span class="dpshield-tab-badge"><?php echo esc_html( $summary['BLOCK'] + $summary['ON_HOLD'] ); ?></span>
            <?php endif; ?>
        </a>
    </div>

    <!-- ═══ SETTINGS TAB ════════════════════════════════════ -->
    <?php if ( 'settings' === $active_tab ) : ?>
    <div class="dpshield-tab-content" id="dpshield-settings">
        <form id="dpshield-form">
            <?php wp_nonce_field( 'dpshield_admin', 'dpshield_nonce' ); ?>

            <!-- ── Risk Gauge ── -->
            <div class="dpshield-card dpshield-card--gauge">
                <div class="dpshield-card__head">
                    <h2><?php esc_html_e( 'Risk Score Thresholds', 'dropproduct' ); ?></h2>
                    <p><?php esc_html_e( 'Visualise where your ALLOW / ON_HOLD / BLOCK zones sit on the 0–200 scale.', 'dropproduct' ); ?></p>
                </div>
                <div class="dpshield-gauge">
                    <div class="dpshield-gauge__track">
                        <div class="dpshield-gauge__zone dpshield-gauge__zone--allow"  id="dpshield-zone-allow"  style="width: <?php echo esc_attr( round( min( $cfg['review_threshold'], 200 ) / 200 * 100, 1 ) ); ?>%"></div>
                        <div class="dpshield-gauge__zone dpshield-gauge__zone--hold"   id="dpshield-zone-hold"   style="width: <?php echo esc_attr( round( ( min( $cfg['block_threshold'], 200 ) - min( $cfg['review_threshold'], 200 ) ) / 200 * 100, 1 ) ); ?>%"></div>
                        <div class="dpshield-gauge__zone dpshield-gauge__zone--block"  id="dpshield-zone-block"  style="flex:1;"></div>
                    </div>
                    <div class="dpshield-gauge__labels">
                        <span class="dpshield-gauge__label dpshield-gauge__label--allow">✅ <?php esc_html_e( 'Allow', 'dropproduct' ); ?></span>
                        <span class="dpshield-gauge__label dpshield-gauge__label--hold">⏸ <?php esc_html_e( 'On Hold', 'dropproduct' ); ?><?php echo ' (≥' . absint( $cfg['review_threshold'] ) . ')'; ?></span>
                        <span class="dpshield-gauge__label dpshield-gauge__label--block">🚫 <?php esc_html_e( 'Block', 'dropproduct' ); ?><?php echo ' (≥' . absint( $cfg['block_threshold'] ) . ')'; ?></span>
                    </div>
                </div>
            </div>

            <!-- ── General ── -->
            <div class="dpshield-card">
                <div class="dpshield-card__head">
                    <h2><?php esc_html_e( 'General', 'dropproduct' ); ?></h2>
                </div>
                <div class="dpshield-card__body">

                    <div class="dpshield-field dpshield-field--toggle">
                        <label class="dpshield-toggle-switch">
                            <input type="checkbox" name="enabled" id="dpshield-enabled" <?php checked( ! empty( $cfg['enabled'] ) ); ?> />
                            <span class="dpshield-toggle-switch__slider"></span>
                        </label>
                        <div>
                            <span class="dpshield-field__label"><?php esc_html_e( 'Enable Order Shield', 'dropproduct' ); ?></span>
                            <span class="dpshield-field__hint"><?php esc_html_e( 'When disabled, all checks are bypassed and COD restrictions are lifted.', 'dropproduct' ); ?></span>
                        </div>
                    </div>

                    <div class="dpshield-field">
                        <label class="dpshield-field__label" for="dpshield-action-mode"><?php esc_html_e( 'Action Mode when Score ≥ Block Threshold', 'dropproduct' ); ?></label>
                        <div class="dpshield-radio-group">
                            <label class="dpshield-radio <?php echo 'block' === $cfg['action_mode'] ? 'is-selected' : ''; ?>">
                                <input type="radio" name="action_mode" value="block" <?php checked( $cfg['action_mode'], 'block' ); ?> />
                                <span class="dpshield-radio__icon">🚫</span>
                                <span class="dpshield-radio__label"><?php esc_html_e( 'Block Completely', 'dropproduct' ); ?></span>
                                <span class="dpshield-radio__hint"><?php esc_html_e( 'Order rejected with error message.', 'dropproduct' ); ?></span>
                            </label>
                            <label class="dpshield-radio <?php echo 'hold' === $cfg['action_mode'] ? 'is-selected' : ''; ?>">
                                <input type="radio" name="action_mode" value="hold" <?php checked( $cfg['action_mode'], 'hold' ); ?> />
                                <span class="dpshield-radio__icon">⏸</span>
                                <span class="dpshield-radio__label"><?php esc_html_e( 'Force On-Hold', 'dropproduct' ); ?></span>
                                <span class="dpshield-radio__hint"><?php esc_html_e( 'Order created but held for manual review.', 'dropproduct' ); ?></span>
                            </label>
                        </div>
                    </div>

                    <div class="dpshield-field-row">
                        <div class="dpshield-field">
                            <label class="dpshield-field__label" for="dpshield-block-threshold">
                                <?php esc_html_e( 'Block Threshold', 'dropproduct' ); ?>
                                <span class="dpshield-badge dpshield-badge--block">BLOCK</span>
                            </label>
                            <div class="dpshield-input-wrap">
                                <input type="number" name="block_threshold" id="dpshield-block-threshold"
                                       class="dpshield-input dpshield-input--sm"
                                       value="<?php echo absint( $cfg['block_threshold'] ); ?>" min="0" max="200" />
                                <span class="dpshield-input-suffix"><?php esc_html_e( 'risk points', 'dropproduct' ); ?></span>
                            </div>
                        </div>
                        <div class="dpshield-field">
                            <label class="dpshield-field__label" for="dpshield-review-threshold">
                                <?php esc_html_e( 'Review Threshold', 'dropproduct' ); ?>
                                <span class="dpshield-badge dpshield-badge--hold">ON HOLD</span>
                            </label>
                            <div class="dpshield-input-wrap">
                                <input type="number" name="review_threshold" id="dpshield-review-threshold"
                                       class="dpshield-input dpshield-input--sm"
                                       value="<?php echo absint( $cfg['review_threshold'] ); ?>" min="0" max="200" />
                                <span class="dpshield-input-suffix"><?php esc_html_e( 'risk points', 'dropproduct' ); ?></span>
                            </div>
                        </div>
                    </div>

                </div><!-- /.card__body -->
            </div>

            <!-- ── Detection Rules ── -->
            <div class="dpshield-card">
                <div class="dpshield-card__head">
                    <h2><?php esc_html_e( 'Detection Rules & Scoring', 'dropproduct' ); ?></h2>
                    <p><?php esc_html_e( 'Configure the thresholds that trigger each risk scoring rule.', 'dropproduct' ); ?></p>
                </div>
                <div class="dpshield-card__body">

                    <!-- Rule reference table -->
                    <div class="dpshield-rule-table">
                        <div class="dpshield-rule-table__head">
                            <span><?php esc_html_e( 'Rule', 'dropproduct' ); ?></span>
                            <span><?php esc_html_e( 'Points', 'dropproduct' ); ?></span>
                            <span><?php esc_html_e( 'Notes', 'dropproduct' ); ?></span>
                        </div>
                        <?php
                        $rules = array(
                            array( 'Disposable email domain',         '+40', 'Matched against your domain list below' ),
                            array( 'IP order velocity exceeded',      '+30', 'More than Max Orders/IP within 1 hour'  ),
                            array( 'Repeated phone or email',         '+25', 'Same contact data seen in ≥2 past orders' ),
                            array( 'IP / Billing country mismatch',   '+20', 'Requires WC Geolocation or Cloudflare'  ),
                            array( 'Excessive failed payments',       '+25', 'Triggers instant BLOCK (card testing)'  ),
                            array( 'Checkout completed too fast',     '+20', 'Less than the Speed Threshold (seconds)'  ),
                            array( 'Honeypot field triggered',        'BLOCK', 'Immediate regardless of score'        ),
                            array( 'Blacklisted name / phone / email','BLOCK', 'Matched against blacklist below'      ),
                        );
                        foreach ( $rules as $rule ) : ?>
                        <div class="dpshield-rule-table__row">
                            <span><?php echo esc_html( $rule[0] ); ?></span>
                            <span class="dpshield-rule-pts <?php echo strpos( $rule[1], 'BLOCK' ) !== false ? 'is-block' : ''; ?>"><?php echo esc_html( $rule[1] ); ?></span>
                            <span class="dpshield-rule-note"><?php echo esc_html( $rule[2] ); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="dpshield-field-row" style="margin-top:24px;">
                        <div class="dpshield-field">
                            <label class="dpshield-field__label" for="dpshield-max-orders-per-ip"><?php esc_html_e( 'Max Orders Per IP / Hour', 'dropproduct' ); ?></label>
                            <div class="dpshield-input-wrap">
                                <input type="number" name="max_orders_per_ip" id="dpshield-max-orders-per-ip"
                                       class="dpshield-input dpshield-input--sm"
                                       value="<?php echo absint( $cfg['max_orders_per_ip'] ); ?>" min="1" max="100" />
                                <span class="dpshield-input-suffix"><?php esc_html_e( 'orders', 'dropproduct' ); ?></span>
                            </div>
                        </div>
                        <div class="dpshield-field">
                            <label class="dpshield-field__label" for="dpshield-failed-threshold"><?php esc_html_e( 'Failed Payment Threshold', 'dropproduct' ); ?></label>
                            <div class="dpshield-input-wrap">
                                <input type="number" name="failed_payment_threshold" id="dpshield-failed-threshold"
                                       class="dpshield-input dpshield-input--sm"
                                       value="<?php echo absint( $cfg['failed_payment_threshold'] ); ?>" min="1" max="50" />
                                <span class="dpshield-input-suffix"><?php esc_html_e( 'attempts', 'dropproduct' ); ?></span>
                            </div>
                        </div>
                        <div class="dpshield-field">
                            <label class="dpshield-field__label" for="dpshield-speed-threshold"><?php esc_html_e( 'Checkout Speed Threshold', 'dropproduct' ); ?></label>
                            <div class="dpshield-input-wrap">
                                <input type="number" name="checkout_time_threshold" id="dpshield-speed-threshold"
                                       class="dpshield-input dpshield-input--sm"
                                       value="<?php echo absint( $cfg['checkout_time_threshold'] ); ?>" min="0" max="60" />
                                <span class="dpshield-input-suffix"><?php esc_html_e( 'seconds', 'dropproduct' ); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="dpshield-field dpshield-field--toggle" style="margin-top:20px;">
                        <label class="dpshield-toggle-switch">
                            <input type="checkbox" name="enable_ip_country_check" id="dpshield-ip-country" <?php checked( ! empty( $cfg['enable_ip_country_check'] ) ); ?> />
                            <span class="dpshield-toggle-switch__slider"></span>
                        </label>
                        <div>
                            <span class="dpshield-field__label"><?php esc_html_e( 'Enable IP / Country Mismatch Check (+20 pts)', 'dropproduct' ); ?></span>
                            <span class="dpshield-field__hint"><?php esc_html_e( 'Requires WooCommerce Geolocation or Cloudflare. Uses local MaxMind DB — no external API calls.', 'dropproduct' ); ?></span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ── Payment Protection ── -->
            <div class="dpshield-card">
                <div class="dpshield-card__head">
                    <h2><?php esc_html_e( 'Payment Protection', 'dropproduct' ); ?></h2>
                </div>
                <div class="dpshield-card__body">

                    <div class="dpshield-field dpshield-field--toggle">
                        <label class="dpshield-toggle-switch">
                            <input type="checkbox" name="enable_cod_restriction" id="dpshield-cod" <?php checked( ! empty( $cfg['enable_cod_restriction'] ) ); ?> />
                            <span class="dpshield-toggle-switch__slider"></span>
                        </label>
                        <div>
                            <span class="dpshield-field__label"><?php esc_html_e( 'Restrict Cash on Delivery (COD) for High-Risk Orders', 'dropproduct' ); ?></span>
                            <span class="dpshield-field__hint"><?php esc_html_e( 'Removes COD from the checkout payment options when the quick risk score exceeds the threshold below.', 'dropproduct' ); ?></span>
                        </div>
                    </div>

                    <div class="dpshield-field" id="dpshield-cod-threshold-field" style="margin-top:16px; <?php echo empty( $cfg['enable_cod_restriction'] ) ? 'opacity:.4;pointer-events:none;' : ''; ?>">
                        <label class="dpshield-field__label" for="dpshield-cod-threshold"><?php esc_html_e( 'COD Block Threshold', 'dropproduct' ); ?></label>
                        <div class="dpshield-input-wrap">
                            <input type="number" name="cod_restriction_threshold" id="dpshield-cod-threshold"
                                   class="dpshield-input dpshield-input--sm"
                                   value="<?php echo absint( $cfg['cod_restriction_threshold'] ); ?>" min="0" max="200" />
                            <span class="dpshield-input-suffix"><?php esc_html_e( 'risk points', 'dropproduct' ); ?></span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ── Disposable Domains ── -->
            <div class="dpshield-card">
                <div class="dpshield-card__head">
                    <h2><?php esc_html_e( 'Disposable Email Domains', 'dropproduct' ); ?></h2>
                    <p><?php esc_html_e( 'One domain per line. Orders using these email domains score +40 risk points.', 'dropproduct' ); ?></p>
                </div>
                <div class="dpshield-card__body">
                    <textarea name="disposable_domains" id="dpshield-disposable-domains"
                              class="dpshield-textarea" rows="10"><?php echo esc_textarea( $cfg['disposable_domains'] ); ?></textarea>
                </div>
            </div>

            <!-- ── Blacklist ── -->
            <div class="dpshield-card">
                <div class="dpshield-card__head">
                    <h2><?php esc_html_e( 'Blacklist', 'dropproduct' ); ?></h2>
                    <p><?php esc_html_e( 'One entry per line. Can be names, phone numbers, or email addresses. Any match instantly blocks the order.', 'dropproduct' ); ?></p>
                </div>
                <div class="dpshield-card__body">
                    <textarea name="blacklist" id="dpshield-blacklist"
                              class="dpshield-textarea" rows="8" placeholder="John Doe&#10;+1234567890&#10;fraud@example.com"><?php echo esc_textarea( $cfg['blacklist'] ); ?></textarea>
                </div>
            </div>

            <!-- ── Save ── -->
            <div class="dpshield-save-bar">
                <button type="submit" id="dpshield-save-btn" class="dpshield-btn dpshield-btn--primary">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e( 'Save Settings', 'dropproduct' ); ?>
                </button>
                <span class="dpshield-save-msg" id="dpshield-save-msg"></span>
            </div>

        </form>
    </div><!-- /#dpshield-settings -->
    <?php endif; ?>

    <!-- ═══ LOGS TAB ════════════════════════════════════════ -->
    <?php if ( 'logs' === $active_tab ) : ?>
    <div class="dpshield-tab-content" id="dpshield-logs">

        <!-- Filter bar -->
        <div class="dpshield-log-filters">
            <div class="dpshield-log-filters__left">
                <?php
                $filter_labels = array(
                    ''         => __( 'All Events', 'dropproduct' ),
                    'BLOCK'    => '🚫 ' . __( 'Blocked', 'dropproduct' ),
                    'ON_HOLD'  => '⏸ ' . __( 'On Hold', 'dropproduct' ),
                    'ALLOW'    => '✅ ' . __( 'Allowed', 'dropproduct' ),
                );
                foreach ( $filter_labels as $val => $label ) :
                    $url = admin_url( 'admin.php?page=dropproduct-fraud-shield&tab=logs' . ( $val ? '&action_filter=' . $val : '' ) );
                    ?>
                    <a href="<?php echo esc_url( $url ); ?>"
                       class="dpshield-filter-pill <?php echo $action_filter === $val ? 'is-active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                    <?php endforeach; ?>
            </div>
            <div class="dpshield-log-filters__right">
                <button type="button" id="dpshield-clear-logs-btn" class="dpshield-btn dpshield-btn--danger-ghost">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e( 'Clear All Logs', 'dropproduct' ); ?>
                </button>
            </div>
        </div>

        <!-- Log table -->
        <?php if ( empty( $log_rows ) ) : ?>
            <div class="dpshield-empty-state">
                <div class="dpshield-empty-state__icon">📋</div>
                <h3><?php esc_html_e( 'No log entries yet', 'dropproduct' ); ?></h3>
                <p><?php esc_html_e( 'Fraud checks will appear here once customers begin checking out.', 'dropproduct' ); ?></p>
            </div>
        <?php else : ?>

        <table class="dpshield-log-table widefat">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?php esc_html_e( 'Order', 'dropproduct' ); ?></th>
                    <th><?php esc_html_e( 'IP Address', 'dropproduct' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'dropproduct' ); ?></th>
                    <th><?php esc_html_e( 'Score', 'dropproduct' ); ?></th>
                    <th><?php esc_html_e( 'Triggered Rules', 'dropproduct' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'dropproduct' ); ?></th>
                    <th><?php esc_html_e( 'Time (UTC)', 'dropproduct' ); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $log_rows as $row ) :
                $rules = json_decode( $row->triggered_rules, true );
                $action_class = array(
                    'BLOCK'   => 'dpshield-action--block',
                    'ON_HOLD' => 'dpshield-action--hold',
                    'ALLOW'   => 'dpshield-action--allow',
                )[ $row->final_action ] ?? '';
            ?>
                <tr class="dpshield-log-tr" data-log-id="<?php echo absint( $row->id ); ?>">
                    <td class="dpshield-log-id"><?php echo absint( $row->id ); ?></td>
                    <td>
                        <?php if ( $row->order_id ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'post.php?post=' . absint( $row->order_id ) . '&action=edit' ) ); ?>">
                                #<?php echo absint( $row->order_id ); ?>
                            </a>
                        <?php else : ?>
                            <span class="dpshield-na">—</span>
                        <?php endif; ?>
                    </td>
                    <td><code><?php echo esc_html( $row->ip_address ); ?></code></td>
                    <td><?php echo esc_html( $row->email ); ?></td>
                    <td>
                        <span class="dpshield-score dpshield-score--<?php echo esc_attr( strtolower( $row->final_action ) ); ?>">
                            <?php echo absint( $row->risk_score ); ?>
                        </span>
                    </td>
                    <td class="dpshield-rules-cell">
                        <?php if ( is_array( $rules ) && ! empty( $rules ) ) :
                            foreach ( $rules as $rule ) : ?>
                                <span class="dpshield-rule-chip"><?php echo esc_html( str_replace( '_', ' ', $rule ) ); ?></span>
                            <?php endforeach;
                        else : ?>
                            <span class="dpshield-na">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="dpshield-action-badge <?php echo esc_attr( $action_class ); ?>">
                            <?php echo esc_html( $row->final_action ); ?>
                        </span>
                    </td>
                    <td class="dpshield-log-time">
                        <?php echo esc_html( $row->created_at ); ?>
                    </td>
                    <td>
                        <button type="button"
                                class="dpshield-delete-log button-link"
                                data-log-id="<?php echo absint( $row->id ); ?>"
                                title="<?php esc_attr_e( 'Delete log entry', 'dropproduct' ); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ( $total_pages > 1 ) : ?>
        <div class="dpshield-pagination">
            <?php
            for ( $p = 1; $p <= $total_pages; $p++ ) :
                $url = admin_url( 'admin.php?page=dropproduct-fraud-shield&tab=logs&paged=' . $p . ( $action_filter ? '&action_filter=' . $action_filter : '' ) );
            ?>
            <a href="<?php echo esc_url( $url ); ?>"
               class="dpshield-page-btn <?php echo $p === $current_page ? 'is-current' : ''; ?>">
                <?php echo absint( $p ); ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php endif; /* empty check */ ?>
    </div><!-- /#dpshield-logs -->
    <?php endif; ?>

</div><!-- /.dpshield-wrap -->
