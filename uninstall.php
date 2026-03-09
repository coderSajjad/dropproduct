<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @package DropProduct
 * @since   1.0.0
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options.
delete_option('dropproduct_settings');
