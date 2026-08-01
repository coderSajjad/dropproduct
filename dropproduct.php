<?php
/**
 * Plugin Name: DropProduct
 * Plugin URI:  https://wordpress.org/plugins/dropproduct/
 * Description: The fastest way to bulk create WooCommerce products from images with drag & drop, smart grouping, inline editing, and one-click publish.
 * Version:     1.2.0
 * Author:      Sajjad Hossain
 * Author URI:  https://sajjadhossain.vercel.app
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dropproduct
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 10.9
 *
 * @package DropProduct
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'DROPPRODUCT_VERSION', '1.2.0' );
define( 'DROPPRODUCT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DROPPRODUCT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DROPPRODUCT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce HPOS.
 *
 * @since 1.0.0
 */
function dropproduct_declare_hpos_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'dropproduct_declare_hpos_compatibility' );

require_once DROPPRODUCT_PLUGIN_DIR . 'includes/class-dropproduct-dependencies.php';

/**
 * Activation handler.
 *
 * Activation always succeeds, even without WooCommerce — see
 * DropProduct_Dependencies::on_activate() for why.
 *
 * @since 1.2.0
 */
register_activation_hook( __FILE__, array( 'DropProduct_Dependencies', 'on_activate' ) );

/**
 * Boot the plugin once WooCommerce is confirmed present and supported.
 *
 * When the dependency is not met the plugin stays dormant and shows a soft,
 * actionable admin notice instead of loading half-working functionality.
 *
 * @since 1.0.0
 */
function dropproduct_init() {
	if ( ! DropProduct_Dependencies::is_satisfied() ) {
		if ( is_admin() ) {
			DropProduct_Dependencies::register_notices();
		}
		return;
	}

	require_once DROPPRODUCT_PLUGIN_DIR . 'includes/class-dropproduct.php';

	$plugin = new DropProduct();
	$plugin->run();
}
add_action( 'plugins_loaded', 'dropproduct_init' );
