<?php
/**
 * Admin page template for DropProduct – Bulk Product Creator.
 *
 * @package DropProduct
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_pro = defined( 'DROPPRODUCT_PRO_VERSION' );
?>
<div class="wrap dropproduct-wrap">
	<div class="dropproduct-header">
		<div class="dropproduct-header__left">
			<h1 class="dropproduct-title">
				<span class="dashicons dashicons-upload"></span>
				<?php esc_html_e( 'DropProduct', 'dropproduct' ); ?>
			</h1>
			<span class="dropproduct-tagline"><?php esc_html_e( 'The New Era of Fast Product Management', 'dropproduct' ); ?></span>
		</div>
		<div class="dropproduct-header__right">
			<span class="dropproduct-count">
				<?php esc_html_e( 'Drafts:', 'dropproduct' ); ?>
				<strong id="dropproduct-draft-count">0</strong>
			</span>
			<button type="button" id="dropproduct-publish-all" class="button button-primary button-large" disabled>
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Publish All', 'dropproduct' ); ?>
			</button>
		</div>
	</div>

	<div class="dropproduct-notices" id="dropproduct-notices"></div>

	<?php
	/**
	 * Fires after the header, before the dropzone.
	 * Used by Pro to inject bulk actions bar, session filter, etc.
	 *
	 * @since 1.1.0
	 */
	do_action( 'dropproduct_after_header' );

	if ( ! $is_pro ) :
	?>
	<div class="dropproduct-pro-bulk-bar" id="dropproduct-pro-bulk-bar" style="display:none;">
		<div class="dropproduct-pro-bulk-bar__left">
			<span class="dropproduct-pro-bulk-count">
				<strong id="dropproduct-pro-selected-count">0</strong>
				<?php esc_html_e( 'selected', 'dropproduct' ); ?>
			</span>
		</div>
		<div class="dropproduct-pro-bulk-bar__right">
			<button type="button" class="button dropproduct-pro-bulk-btn" data-action="price">
				<span class="dashicons dashicons-money-alt"></span>
				<?php esc_html_e( 'Set Price', 'dropproduct' ); ?>
			</button>
			<button type="button" class="button dropproduct-pro-bulk-btn" data-action="category">
				<span class="dashicons dashicons-category"></span>
				<?php esc_html_e( 'Set Category', 'dropproduct' ); ?>
			</button>
			<button type="button" class="button dropproduct-pro-bulk-btn" data-action="stock">
				<span class="dashicons dashicons-clipboard"></span>
				<?php esc_html_e( 'Set Stock', 'dropproduct' ); ?>
			</button>
			<button type="button" class="button dropproduct-pro-bulk-btn" data-action="tax">
				<span class="dashicons dashicons-editor-paste-text"></span>
				<?php esc_html_e( 'Tax Class', 'dropproduct' ); ?>
			</button>
			<button type="button" class="button dropproduct-pro-bulk-btn" data-action="shipping">
				<span class="dashicons dashicons-car"></span>
				<?php esc_html_e( 'Shipping', 'dropproduct' ); ?>
			</button>
		</div>
	</div>
	<?php endif; ?>

	<div class="dropproduct-dropzone" id="dropproduct-dropzone">
		<div class="dropproduct-dropzone__inner">
			<span class="dashicons dashicons-cloud-upload"></span>
			<p class="dropproduct-dropzone__text">
				<?php esc_html_e( 'Drag & drop product images here', 'dropproduct' ); ?>
			</p>
			<p class="dropproduct-dropzone__subtext">
				<?php esc_html_e( 'or', 'dropproduct' ); ?>
			</p>
			<button type="button" class="button button-secondary" id="dropproduct-browse-btn">
				<?php esc_html_e( 'Browse Files', 'dropproduct' ); ?>
			</button>
			<input type="file" id="dropproduct-file-input" multiple accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;" />
			<p class="dropproduct-dropzone__hint">
				<?php esc_html_e( 'JPEG, PNG, GIF, WebP — images with similar names are grouped automatically', 'dropproduct' ); ?>
			</p>
		</div>
		<div class="dropproduct-dropzone__progress" id="dropproduct-upload-progress" style="display:none;">
			<div class="dropproduct-progress-bar">
				<div class="dropproduct-progress-bar__fill" id="dropproduct-progress-fill"></div>
			</div>
			<p class="dropproduct-progress-text" id="dropproduct-progress-text"></p>
		</div>
	</div>

	<?php
	/**
	 * Fires before the product grid table.
	 * Used by Pro to inject advanced session filters.
	 *
	 * @since 1.1.0
	 */
	do_action( 'dropproduct_before_grid' );
	?>

	<!-- ── Session & Activity Bar (Free — fully functional since v1.0.3) ── -->
	<div class="dropproduct-session-bar" id="dropproduct-session-bar">
		<div class="dropproduct-session-bar__left">
			<span class="dashicons dashicons-backup" style="color:var(--wu-primary);vertical-align:middle;"></span>
			<label class="dropproduct-session-label" for="dropproduct-session-select">
				<?php esc_html_e( 'Session:', 'dropproduct' ); ?>
			</label>
			<select id="dropproduct-session-select" class="dropproduct-session-select">
				<option value=""><?php esc_html_e( '— All Sessions —', 'dropproduct' ); ?></option>
			</select>
		</div>
		<div class="dropproduct-session-bar__right">
			<button type="button" class="button button-small" id="dropproduct-activity-log-btn">
				<span class="dashicons dashicons-list-view" style="vertical-align:middle;margin-top:2px;"></span>
				<?php esc_html_e( 'Activity Log', 'dropproduct' ); ?>
			</button>
		</div>
	</div>

	<!-- ── Activity Log Panel (hidden by default) ──────────────────────── -->
	<div id="dropproduct-activity-log-panel" class="dropproduct-activity-log-panel" style="display:none;">
		<div class="dpal-header">
			<div class="dpal-header__left">
				<strong><?php esc_html_e( 'Activity Log', 'dropproduct' ); ?></strong>
				<select id="dpal-filter" class="dpal-filter">
					<option value=""><?php esc_html_e( 'All Actions', 'dropproduct' ); ?></option>
					<option value="upload"><?php esc_html_e( 'Upload', 'dropproduct' ); ?></option>
					<option value="publish"><?php esc_html_e( 'Publish', 'dropproduct' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete', 'dropproduct' ); ?></option>
				</select>
			</div>
			<div class="dpal-header__right">
				<button type="button" class="button button-small dpal-clear-btn" id="dpal-clear-btn">
					<?php esc_html_e( 'Clear All', 'dropproduct' ); ?>
				</button>
				<button type="button" class="dpal-close" id="dpal-close-btn" aria-label="<?php esc_attr_e( 'Close', 'dropproduct' ); ?>">✕</button>
			</div>
		</div>
		<div id="dpal-body" class="dpal-body">
			<div class="dpal-loading"><?php esc_html_e( 'Loading…', 'dropproduct' ); ?></div>
		</div>
		<div class="dpal-footer" id="dpal-footer" style="display:none;">
			<button type="button" class="button button-small" id="dpal-prev-btn">← <?php esc_html_e( 'Prev', 'dropproduct' ); ?></button>
			<span id="dpal-page-info"></span>
			<button type="button" class="button button-small" id="dpal-next-btn"><?php esc_html_e( 'Next', 'dropproduct' ); ?> →</button>
		</div>
	</div>

	<!-- Slasher Toolbar: always visible toggle button -->
	<div class="dropproduct-slasher-toolbar" id="dropproduct-slasher-toolbar">
		<button
			type="button"
			class="button dropproduct-slasher-toggle-btn"
			id="dropproduct-slasher-toggle-btn"
			aria-expanded="false"
		>
			<span class="dashicons dashicons-tag"></span>
			<?php esc_html_e( 'Price Slasher', 'dropproduct' ); ?>
			<span class="dropproduct-slasher-badge" id="dropproduct-slasher-count" aria-label="<?php esc_attr_e( 'Selected count', 'dropproduct' ); ?>">0</span>
			<span class="dropproduct-slasher-caret dashicons dashicons-arrow-down-alt2"></span>
		</button>
		<span class="dropproduct-slasher-toolbar-hint" id="dropproduct-slasher-toolbar-hint">
			<?php esc_html_e( 'Select rows in the table, then adjust prices below.', 'dropproduct' ); ?>
		</span>
	</div>

	<!-- ⚡ Price Slasher Bar (toggled by the button above) -->
	<div class="dropproduct-slasher-bar" id="dropproduct-slasher-bar" aria-hidden="true">
		<div class="dropproduct-slasher-bar__left">
			<span class="dropproduct-slasher-bar__icon">
				<span class="dashicons dashicons-tag"></span>
			</span>
			<div class="dropproduct-slasher-bar__selected">
				<strong id="dropproduct-slasher-count-bar">0</strong>
				<span><?php esc_html_e( 'products selected', 'dropproduct' ); ?></span>
			</div>
		</div>

		<div class="dropproduct-slasher-bar__controls">
			<!-- Price target -->
			<div class="dropproduct-slasher-group">
				<label class="dropproduct-slasher-label" for="dropproduct-slasher-field">
					<?php esc_html_e( 'Price', 'dropproduct' ); ?>
				</label>
				<select id="dropproduct-slasher-field" class="dropproduct-slasher-select">
					<option value="regular_price"><?php esc_html_e( 'Regular', 'dropproduct' ); ?></option>
					<option value="sale_price"><?php esc_html_e( 'Sale', 'dropproduct' ); ?></option>
					<option value="both"><?php esc_html_e( 'Both', 'dropproduct' ); ?></option>
				</select>
			</div>

			<!-- Operation -->
			<div class="dropproduct-slasher-group">
				<label class="dropproduct-slasher-label"><?php esc_html_e( 'Action', 'dropproduct' ); ?></label>
				<div class="dropproduct-slasher-toggle-group" role="group">
					<button type="button" class="dropproduct-slasher-toggle is-active" data-op="increase" id="dropproduct-slasher-increase">
						<span class="dashicons dashicons-arrow-up-alt"></span>
						<?php esc_html_e( 'Increase', 'dropproduct' ); ?>
					</button>
					<button type="button" class="dropproduct-slasher-toggle" data-op="decrease" id="dropproduct-slasher-decrease">
						<span class="dashicons dashicons-arrow-down-alt"></span>
						<?php esc_html_e( 'Decrease', 'dropproduct' ); ?>
					</button>
				</div>
			</div>

			<!-- Amount + Type -->
			<div class="dropproduct-slasher-group">
				<label class="dropproduct-slasher-label" for="dropproduct-slasher-amount"><?php esc_html_e( 'Amount', 'dropproduct' ); ?></label>
				<div class="dropproduct-slasher-amount-wrap">
					<input
						type="number"
						id="dropproduct-slasher-amount"
						class="dropproduct-slasher-input"
						value="10"
						min="0.01"
						step="0.01"
						placeholder="10"
					/>
					<select id="dropproduct-slasher-type" class="dropproduct-slasher-type-select">
						<option value="percentage">%</option>
						<option value="fixed">$</option>
					</select>
				</div>
			</div>
		</div>

		<div class="dropproduct-slasher-bar__right">
			<button type="button" class="button dropproduct-slasher-apply" id="dropproduct-slasher-apply">
				<span class="dashicons dashicons-tag"></span>
				<?php esc_html_e( 'Apply', 'dropproduct' ); ?>
			</button>
			<button type="button" class="dropproduct-slasher-clear" id="dropproduct-slasher-clear" title="<?php esc_attr_e( 'Clear selection', 'dropproduct' ); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
	</div>


	<div class="dropproduct-grid-wrap" id="dropproduct-grid-wrap">
		<table class="dropproduct-grid widefat" id="dropproduct-grid">
			<thead>
				<tr>
					<th class="dropproduct-col-check">
						<label class="dropproduct-check-label">
							<input type="checkbox" id="dropproduct-select-all" title="<?php esc_attr_e( 'Select all', 'dropproduct' ); ?>" />
							<span class="dropproduct-check-custom"></span>
						</label>
					</th>
					<th class="dropproduct-col-image"><?php esc_html_e( 'Image', 'dropproduct' ); ?></th>
					<th class="dropproduct-col-title"><?php esc_html_e( 'Title', 'dropproduct' ); ?></th>
					<th class="dropproduct-col-desc"><?php esc_html_e( 'Desc', 'dropproduct' ); ?></th>
					<th class="dropproduct-col-price"><?php esc_html_e( 'Regular Price', 'dropproduct' ); ?></th>
					<th class="dropproduct-col-sale-price"><?php esc_html_e( 'Sale Price', 'dropproduct' ); ?></th>
					<th class="dropproduct-col-sku"><?php esc_html_e( 'SKU', 'dropproduct' ); ?></th>
					<th class="dropproduct-col-stock"><?php esc_html_e( 'Stock', 'dropproduct' ); ?></th>
					<th class="dropproduct-col-category"><?php esc_html_e( 'Category', 'dropproduct' ); ?></th>
					<th class="dropproduct-col-status"><?php esc_html_e( 'Status', 'dropproduct' ); ?></th>
					<th class="dropproduct-col-actions"><?php esc_html_e( 'Actions', 'dropproduct' ); ?></th>
					<th class="dropproduct-col-cost" title="<?php esc_attr_e( 'Your purchase cost for this product', 'dropproduct' ); ?>">
						<?php esc_html_e( 'Cost Price', 'dropproduct' ); ?>
						<span class="dropproduct-col-hint"><?php esc_html_e( 'Not shown to customers', 'dropproduct' ); ?></span>
					</th>
					<th class="dropproduct-col-profit"><?php esc_html_e( 'Profit', 'dropproduct' ); ?></th>
					<th class="dropproduct-col-margin"><?php esc_html_e( 'Margin %', 'dropproduct' ); ?></th>
				</tr>
			</thead>
			<tbody id="dropproduct-grid-body">
				<tr class="dropproduct-empty-row" id="dropproduct-empty-row">
					<td colspan="14">
						<div class="dropproduct-empty-state">
							<span class="dashicons dashicons-format-gallery"></span>
							<p><?php esc_html_e( 'No draft products yet. Upload images to get started.', 'dropproduct' ); ?></p>
						</div>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="dropproduct-image-preview" id="dropproduct-image-preview" style="display:none;">
		<img src="" alt="" id="dropproduct-preview-img" />
	</div>

	<!-- Description Modal -->
	<div class="dropproduct-desc-overlay" id="dropproduct-desc-overlay"></div>
	<div class="dropproduct-desc-modal" id="dropproduct-desc-modal">
		<div class="dropproduct-desc-modal__header">
			<h3>
				<span class="dashicons dashicons-edit"></span>
				<?php esc_html_e( 'Product Description', 'dropproduct' ); ?>
			</h3>
			<button type="button" class="dropproduct-desc-modal__close" id="dropproduct-desc-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<div class="dropproduct-desc-modal__body">
			<textarea id="dropproduct-desc-textarea" placeholder="<?php esc_attr_e( 'Enter product description…', 'dropproduct' ); ?>"></textarea>
		</div>
		<div class="dropproduct-desc-modal__footer">
			<button type="button" class="button button-secondary" id="dropproduct-desc-cancel">
				<?php esc_html_e( 'Cancel', 'dropproduct' ); ?>
			</button>
			<button type="button" class="button button-primary" id="dropproduct-desc-save">
				<?php esc_html_e( 'Save', 'dropproduct' ); ?>
			</button>
		</div>
	</div>

	<!-- Delete Confirmation Modal -->
	<div class="dropproduct-confirm-overlay" id="dropproduct-confirm-overlay"></div>
	<div class="dropproduct-confirm-modal" id="dropproduct-confirm-modal">
		<div class="dropproduct-confirm-modal__icon">
			<span class="dashicons dashicons-trash"></span>
		</div>
		<h3><?php esc_html_e( 'Delete Product?', 'dropproduct' ); ?></h3>
		<p><?php esc_html_e( 'This will permanently delete the product and its images. This action cannot be undone.', 'dropproduct' ); ?></p>
		<div class="dropproduct-confirm-modal__actions">
			<button type="button" class="button button-secondary" id="dropproduct-confirm-cancel">
				<?php esc_html_e( 'Cancel', 'dropproduct' ); ?>
			</button>
			<button type="button" class="button dropproduct-confirm-delete-btn" id="dropproduct-confirm-delete">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e( 'Yes, Delete', 'dropproduct' ); ?>
			</button>
		</div>
	</div>

	<!-- Bulk Edit Prompt Modal (shown when Pro is not active) -->
	<?php if ( ! $is_pro ) : ?>
	<div class="dropproduct-pro-prompt-overlay" id="dropproduct-pro-prompt-overlay"></div>
	<div class="dropproduct-pro-prompt-modal" id="dropproduct-pro-prompt-modal">
		<div class="dropproduct-pro-prompt-modal__header">
			<h3 id="dropproduct-pro-prompt-title"></h3>
			<button type="button" class="dropproduct-pro-prompt-close" id="dropproduct-pro-prompt-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<div class="dropproduct-pro-prompt-modal__body" id="dropproduct-pro-prompt-body"></div>
		<div class="dropproduct-pro-prompt-modal__footer">
			<button type="button" class="button button-secondary" id="dropproduct-pro-prompt-cancel">
				<?php esc_html_e( 'Cancel', 'dropproduct' ); ?>
			</button>
			<button type="button" class="button button-primary" id="dropproduct-pro-prompt-apply">
				<?php esc_html_e( 'Apply', 'dropproduct' ); ?>
			</button>
		</div>
	</div>
	<?php endif; ?>

	<?php
	/**
	 * Fires after the grid and modals.
	 * Used by Pro to inject validation dashboard, activity log, etc.
	 *
	 * @since 1.1.0
	 */
	do_action( 'dropproduct_after_grid' );
	?>
</div>
