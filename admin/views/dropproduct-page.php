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
	 *
	 * Used by Pro to inject bulk actions bar, session filter, etc.
	 *
	 * @since 1.1.0
	 */
	do_action( 'dropproduct_after_header' );
	?>

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
	 *
	 * Used by Pro to inject session filter dropdown.
	 *
	 * @since 1.1.0
	 */
	do_action( 'dropproduct_before_grid' );
	?>

	<div class="dropproduct-grid-wrap" id="dropproduct-grid-wrap">
		<table class="dropproduct-grid widefat" id="dropproduct-grid">
			<thead>
				<tr>
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
				</tr>
			</thead>
			<tbody id="dropproduct-grid-body">
				<tr class="dropproduct-empty-row" id="dropproduct-empty-row">
					<td colspan="10">
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

	<?php
	/**
	 * Fires after the grid and modals.
	 *
	 * Used by Pro to inject validation dashboard, activity log, etc.
	 *
	 * @since 1.1.0
	 */
	do_action( 'dropproduct_after_grid' );
	?>
</div>
