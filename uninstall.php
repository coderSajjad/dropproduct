<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @package WooCommerce_Uploady
 * @since   1.0.0
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options.
delete_option('wc_uploady_settings');
