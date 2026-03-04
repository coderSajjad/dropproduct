<?php
/**
 * Plugin Name: WooUpload – Bulk Product Creator
 * Plugin URI:  https://wordpress.org/plugins/woocommerce-uploady/
 * Description: The New Era of Fast Product Management — bulk create WooCommerce products from images with a drag & drop SPA-style grid.
 * Version:     1.0.0
 * Author:      Sajjad Hossain
 * Author URI:  https://github.com/coderSajjad
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woocommerce-uploady
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.0
 *
 * @package WooCommerce_Uploady
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'WC_UPLOADY_VERSION', '1.0.0' );
define( 'WC_UPLOADY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_UPLOADY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_UPLOADY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce HPOS.
 *
 * @since 1.0.0
 */
function wc_uploady_declare_hpos_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'wc_uploady_declare_hpos_compatibility' );

/**
 * Check if WooCommerce is active before initializing.
 *
 * @since 1.0.0
 */
function wc_uploady_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wc_uploady_missing_wc_notice' );
		return;
	}

	require_once WC_UPLOADY_PLUGIN_DIR . 'includes/class-wc-uploady.php';

	$plugin = new WC_Uploady();
	$plugin->run();
}
add_action( 'plugins_loaded', 'wc_uploady_init' );

/**
 * Admin notice when WooCommerce is not active.
 *
 * @since 1.0.0
 */
function wc_uploady_missing_wc_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: WooCommerce plugin name */
				esc_html__( '%1$s requires %2$s to be installed and active.', 'woocommerce-uploady' ),
				'<strong>WooUpload – Bulk Product Creator</strong>',
				'<strong>WooCommerce</strong>'
			);
			?>
		</p>
	</div>
	<?php
}
