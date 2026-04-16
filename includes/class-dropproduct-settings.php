<?php

/**
 * Plugin settings manager.
 *
 * Handles saving and retrieving free-plugin settings,
 * and provides the AJAX endpoint for the settings form.
 *
 * @package DropProduct
 * @since   1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DropProduct_Settings
 *
 * @since 1.0.1
 */
class DropProduct_Settings {

	/**
	 * Option key used to store all free-plugin settings.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'dropproduct_settings';

	// ──────────────────────────────────────────
	//  Public API
	// ──────────────────────────────────────────

	/**
	 * Get all settings, merged with defaults.
	 *
	 * @return array
	 */
	public static function get_all() {
		$defaults = array(
			'auto_alt_text' => '1', // '1' = enabled, '0' = disabled.
		);

		return wp_parse_args( get_option( self::OPTION_KEY, array() ), $defaults );
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::get_all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	/**
	 * Check whether the Smart SEO Alt-Text Automator is enabled.
	 *
	 * @return bool
	 */
	public static function is_auto_alt_text_enabled() {
		return '1' === self::get( 'auto_alt_text', '1' );
	}

	// ──────────────────────────────────────────
	//  AJAX Handlers
	// ──────────────────────────────────────────

	/**
	 * Handle saving settings via AJAX.
	 *
	 * @since 1.0.1
	 */
	public function handle_save_settings() {
		if ( ! check_ajax_referer( 'dropproduct_settings_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'dropproduct' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dropproduct' ) ), 403 );
		}

		$current = self::get_all();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
		$current['auto_alt_text'] = isset( $_POST['auto_alt_text'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['auto_alt_text'] ) ) ? '1' : '0';

		update_option( self::OPTION_KEY, $current );

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'dropproduct' ) ) );
	}
}
