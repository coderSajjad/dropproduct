<?php
/**
 * Settings page template for DropProduct.
 *
 * @package DropProduct
 * @since   1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = DropProduct_Settings::get_all();
$is_pro   = defined( 'DROPPRODUCT_PRO_VERSION' );
?>
<div class="wrap dropproduct-settings-wrap">

	<!-- Page Header -->
	<div class="dropproduct-settings-header">
		<div class="dropproduct-settings-header__left">
			<div class="dropproduct-settings-header__icon">
				<span class="dashicons dashicons-admin-settings"></span>
			</div>
			<div>
				<h1 class="dropproduct-settings-title">
					<?php esc_html_e( 'DropProduct Settings', 'dropproduct' ); ?>
				</h1>
				<p class="dropproduct-settings-subtitle">
					<?php esc_html_e( 'Configure free-plan features for your DropProduct uploader.', 'dropproduct' ); ?>
				</p>
			</div>
		</div>
		<div class="dropproduct-settings-header__right">
			<span class="dropproduct-settings-version">v<?php echo esc_html( DROPPRODUCT_VERSION ); ?></span>
		</div>
	</div>

	<!-- Notice area -->
	<div class="dropproduct-settings-notices" id="dropproduct-settings-notices"></div>

	<!-- Settings sections -->
	<div class="dropproduct-settings-body">

		<!-- ── SEO Automation ─────────────────────────────────── -->
		<div class="dropproduct-settings-card">
			<div class="dropproduct-settings-card__header">
				<div class="dropproduct-settings-card__icon dropproduct-settings-card__icon--seo">
					<span class="dashicons dashicons-visibility"></span>
				</div>
				<div>
					<h2 class="dropproduct-settings-card__title">
						<?php esc_html_e( 'Smart SEO Alt-Text Automator', 'dropproduct' ); ?>
					</h2>
					<p class="dropproduct-settings-card__desc">
						<?php esc_html_e( 'Automatically generate SEO-friendly alt text from image filenames when uploading via DropProduct. Only applies if the alt text field is currently empty — your manual SEO work is never overwritten.', 'dropproduct' ); ?>
					</p>
				</div>
			</div>

			<div class="dropproduct-settings-card__body">
				<!-- Feature toggle -->
				<div class="dropproduct-settings-row">
					<div class="dropproduct-settings-row__label">
						<span class="dropproduct-settings-row__name">
							<?php esc_html_e( 'Enable Auto Alt-Text', 'dropproduct' ); ?>
						</span>
						<span class="dropproduct-settings-row__hint">
							<?php esc_html_e( 'When enabled, alt text is derived from the filename automatically on upload.', 'dropproduct' ); ?>
						</span>
					</div>
					<div class="dropproduct-settings-row__control">
						<label class="dropproduct-toggle" for="dropproduct-auto-alt-text">
							<input
								type="checkbox"
								id="dropproduct-auto-alt-text"
								name="auto_alt_text"
								value="1"
								<?php checked( $settings['auto_alt_text'], '1' ); ?>
							/>
							<span class="dropproduct-toggle__track"></span>
							<span class="dropproduct-toggle__thumb"></span>
						</label>
					</div>
				</div>

				<!-- How it works example box -->
				<div class="dropproduct-settings-example">
					<div class="dropproduct-settings-example__header">
						<span class="dashicons dashicons-info-outline"></span>
						<?php esc_html_e( 'How it works', 'dropproduct' ); ?>
					</div>
					<div class="dropproduct-settings-example__body">
						<div class="dropproduct-settings-example__row">
							<span class="dropproduct-settings-example__label"><?php esc_html_e( 'Filename:', 'dropproduct' ); ?></span>
							<code>blue_denim_jacket_v2.png</code>
						</div>
						<div class="dropproduct-settings-example__arrow">
							<span class="dashicons dashicons-arrow-down-alt"></span>
						</div>
						<div class="dropproduct-settings-example__row">
							<span class="dropproduct-settings-example__label"><?php esc_html_e( 'Alt text:', 'dropproduct' ); ?></span>
							<strong class="dropproduct-settings-example__output">Blue Denim Jacket V2</strong>
						</div>
					</div>
					<ul class="dropproduct-settings-example__rules">
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Removes file extension (.jpg, .png, etc.)', 'dropproduct' ); ?></li>
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Replaces hyphens and underscores with spaces', 'dropproduct' ); ?></li>
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Converts to Title Case', 'dropproduct' ); ?></li>
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Only applied when the image has no existing alt text', 'dropproduct' ); ?></li>
					</ul>
				</div>
			</div>
		</div>

	</div><!-- /.dropproduct-settings-body -->

	<!-- Save button -->
	<div class="dropproduct-settings-footer">
		<button
			type="button"
			class="button button-primary button-large"
			id="dropproduct-settings-save"
		>
			<span class="dashicons dashicons-saved"></span>
			<?php esc_html_e( 'Save Settings', 'dropproduct' ); ?>
		</button>
		<span class="dropproduct-settings-footer__hint">
			<?php esc_html_e( 'Changes take effect immediately upon saving.', 'dropproduct' ); ?>
		</span>
	</div>

</div><!-- /.dropproduct-settings-wrap -->
