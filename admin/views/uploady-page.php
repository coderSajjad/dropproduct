<?php
/**
 * Admin page template for Uploady – Bulk Product Creator.
 *
 * @package WooCommerce_Uploady
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<script>document.body.classList.add('wc-uploady-page');</script>
<div class="wrap wc-uploady-wrap">
	<div class="wc-uploady-header">
		<div class="wc-uploady-header__left">
			<h1 class="wc-uploady-title">
				<span class="dashicons dashicons-upload"></span>
				<?php esc_html_e( 'wooupload', 'wooupload' ); ?>
			</h1>
			<span class="wc-uploady-tagline"><?php esc_html_e( 'The New Era of Fast Product Management', 'wooupload' ); ?></span>
		</div>
		<div class="wc-uploady-header__right">
			<span class="wc-uploady-count">
				<?php esc_html_e( 'Drafts:', 'wooupload' ); ?>
				<strong id="wc-uploady-draft-count">0</strong>
			</span>
			<button type="button" id="wc-uploady-publish-all" class="button button-primary button-large" disabled>
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Publish All', 'wooupload' ); ?>
			</button>
		</div>
	</div>

	<div class="wc-uploady-notices" id="wc-uploady-notices"></div>

	<?php
	/**
	 * Fires after the header, before the dropzone.
	 *
	 * Used by Pro to inject bulk actions bar, session filter, etc.
	 *
	 * @since 1.1.0
	 */
	do_action( 'wc_uploady_after_header' );
	?>

	<div class="wc-uploady-dropzone" id="wc-uploady-dropzone">
		<div class="wc-uploady-dropzone__inner">
			<span class="dashicons dashicons-cloud-upload"></span>
			<p class="wc-uploady-dropzone__text">
				<?php esc_html_e( 'Drag & drop product images here', 'wooupload' ); ?>
			</p>
			<p class="wc-uploady-dropzone__subtext">
				<?php esc_html_e( 'or', 'wooupload' ); ?>
			</p>
			<button type="button" class="button button-secondary" id="wc-uploady-browse-btn">
				<?php esc_html_e( 'Browse Files', 'wooupload' ); ?>
			</button>
			<input type="file" id="wc-uploady-file-input" multiple accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;" />
			<p class="wc-uploady-dropzone__hint">
				<?php esc_html_e( 'JPEG, PNG, GIF, WebP — images with similar names are grouped automatically', 'wooupload' ); ?>
			</p>
		</div>
		<div class="wc-uploady-dropzone__progress" id="wc-uploady-upload-progress" style="display:none;">
			<div class="wc-uploady-progress-bar">
				<div class="wc-uploady-progress-bar__fill" id="wc-uploady-progress-fill"></div>
			</div>
			<p class="wc-uploady-progress-text" id="wc-uploady-progress-text"></p>
		</div>
	</div>

	<?php
	/**
	 * Fires before the product grid table.
	 *
	 * Used by Pro to inject session filter dropdown.
	 *
	 * @since 1.1.0
	 */
	do_action( 'wc_uploady_before_grid' );
	?>

	<div class="wc-uploady-grid-wrap" id="wc-uploady-grid-wrap">
		<table class="wc-uploady-grid widefat" id="wc-uploady-grid">
			<thead>
				<tr>
					<th class="wc-uploady-col-image"><?php esc_html_e( 'Image', 'wooupload' ); ?></th>
					<th class="wc-uploady-col-title"><?php esc_html_e( 'Title', 'wooupload' ); ?></th>
					<th class="wc-uploady-col-desc"><?php esc_html_e( 'Desc', 'wooupload' ); ?></th>
					<th class="wc-uploady-col-price"><?php esc_html_e( 'Regular Price', 'wooupload' ); ?></th>
					<th class="wc-uploady-col-sale-price"><?php esc_html_e( 'Sale Price', 'wooupload' ); ?></th>
					<th class="wc-uploady-col-sku"><?php esc_html_e( 'SKU', 'wooupload' ); ?></th>
					<th class="wc-uploady-col-stock"><?php esc_html_e( 'Stock', 'wooupload' ); ?></th>
					<th class="wc-uploady-col-category"><?php esc_html_e( 'Category', 'wooupload' ); ?></th>
					<th class="wc-uploady-col-status"><?php esc_html_e( 'Status', 'wooupload' ); ?></th>
					<th class="wc-uploady-col-actions"><?php esc_html_e( 'Actions', 'wooupload' ); ?></th>
				</tr>
			</thead>
			<tbody id="wc-uploady-grid-body">
				<tr class="wc-uploady-empty-row" id="wc-uploady-empty-row">
					<td colspan="10">
						<div class="wc-uploady-empty-state">
							<span class="dashicons dashicons-format-gallery"></span>
							<p><?php esc_html_e( 'No draft products yet. Upload images to get started.', 'wooupload' ); ?></p>
						</div>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="wc-uploady-image-preview" id="wc-uploady-image-preview" style="display:none;">
		<img src="" alt="" id="wc-uploady-preview-img" />
	</div>

	<!-- Description Modal -->
	<div class="wc-uploady-desc-overlay" id="wc-uploady-desc-overlay"></div>
	<div class="wc-uploady-desc-modal" id="wc-uploady-desc-modal">
		<div class="wc-uploady-desc-modal__header">
			<h3>
				<span class="dashicons dashicons-edit"></span>
				<?php esc_html_e( 'Product Description', 'wooupload' ); ?>
			</h3>
			<button type="button" class="wc-uploady-desc-modal__close" id="wc-uploady-desc-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<div class="wc-uploady-desc-modal__body">
			<textarea id="wc-uploady-desc-textarea" placeholder="<?php esc_attr_e( 'Enter product description…', 'wooupload' ); ?>"></textarea>
		</div>
		<div class="wc-uploady-desc-modal__footer">
			<button type="button" class="button button-secondary" id="wc-uploady-desc-cancel">
				<?php esc_html_e( 'Cancel', 'wooupload' ); ?>
			</button>
			<button type="button" class="button button-primary" id="wc-uploady-desc-save">
				<?php esc_html_e( 'Save', 'wooupload' ); ?>
			</button>
		</div>
	</div>

	<?php
	/**
	 * Fires after the grid and modals.
	 *
	 * Used by Pro to inject validation dashboard, activity log, etc.
	 *
	 * @since 1.1.0
	 */
	do_action( 'wc_uploady_after_grid' );
	?>
</div>
