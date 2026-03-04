<?php
/**
 * Admin page template for WooUpload – Bulk Product Creator.
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
				<?php esc_html_e( 'WooUpload', 'woocommerce-uploady' ); ?>
			</h1>
			<span class="wc-uploady-tagline"><?php esc_html_e( 'The New Era of Fast Product Management', 'woocommerce-uploady' ); ?></span>
		</div>
		<div class="wc-uploady-header__right">
			<span class="wc-uploady-count">
				<?php esc_html_e( 'Drafts:', 'woocommerce-uploady' ); ?>
				<strong id="wc-uploady-draft-count">0</strong>
			</span>
			<button type="button" id="wc-uploady-publish-all" class="button button-primary button-large" disabled>
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Publish All', 'woocommerce-uploady' ); ?>
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
				<?php esc_html_e( 'Drag & drop product images here', 'woocommerce-uploady' ); ?>
			</p>
			<p class="wc-uploady-dropzone__subtext">
				<?php esc_html_e( 'or', 'woocommerce-uploady' ); ?>
			</p>
			<button type="button" class="button button-secondary" id="wc-uploady-browse-btn">
				<?php esc_html_e( 'Browse Files', 'woocommerce-uploady' ); ?>
			</button>
			<input type="file" id="wc-uploady-file-input" multiple accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;" />
			<p class="wc-uploady-dropzone__hint">
				<?php esc_html_e( 'JPEG, PNG, GIF, WebP — images with similar names are grouped automatically', 'woocommerce-uploady' ); ?>
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
					<th class="wc-uploady-col-image"><?php esc_html_e( 'Image', 'woocommerce-uploady' ); ?></th>
					<th class="wc-uploady-col-title"><?php esc_html_e( 'Title', 'woocommerce-uploady' ); ?></th>
					<th class="wc-uploady-col-desc"><?php esc_html_e( 'Desc', 'woocommerce-uploady' ); ?></th>
					<th class="wc-uploady-col-price"><?php esc_html_e( 'Regular Price', 'woocommerce-uploady' ); ?></th>
					<th class="wc-uploady-col-sale-price"><?php esc_html_e( 'Sale Price', 'woocommerce-uploady' ); ?></th>
					<th class="wc-uploady-col-sku"><?php esc_html_e( 'SKU', 'woocommerce-uploady' ); ?></th>
					<th class="wc-uploady-col-stock"><?php esc_html_e( 'Stock', 'woocommerce-uploady' ); ?></th>
					<th class="wc-uploady-col-category"><?php esc_html_e( 'Category', 'woocommerce-uploady' ); ?></th>
					<th class="wc-uploady-col-status"><?php esc_html_e( 'Status', 'woocommerce-uploady' ); ?></th>
					<th class="wc-uploady-col-actions"><?php esc_html_e( 'Actions', 'woocommerce-uploady' ); ?></th>
				</tr>
			</thead>
			<tbody id="wc-uploady-grid-body">
				<tr class="wc-uploady-empty-row" id="wc-uploady-empty-row">
					<td colspan="10">
						<div class="wc-uploady-empty-state">
							<span class="dashicons dashicons-format-gallery"></span>
							<p><?php esc_html_e( 'No draft products yet. Upload images to get started.', 'woocommerce-uploady' ); ?></p>
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
				<?php esc_html_e( 'Product Description', 'woocommerce-uploady' ); ?>
			</h3>
			<button type="button" class="wc-uploady-desc-modal__close" id="wc-uploady-desc-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<div class="wc-uploady-desc-modal__body">
			<textarea id="wc-uploady-desc-textarea" placeholder="<?php esc_attr_e( 'Enter product description…', 'woocommerce-uploady' ); ?>"></textarea>
		</div>
		<div class="wc-uploady-desc-modal__footer">
			<button type="button" class="button button-secondary" id="wc-uploady-desc-cancel">
				<?php esc_html_e( 'Cancel', 'woocommerce-uploady' ); ?>
			</button>
			<button type="button" class="button button-primary" id="wc-uploady-desc-save">
				<?php esc_html_e( 'Save', 'woocommerce-uploady' ); ?>
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
