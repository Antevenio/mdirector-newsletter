<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @link       http://mdirector.com
 * @since      1.0.0
 *
 * @package    Mdirector_Newsletter
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option('mdirector_active');
delete_option('mdirector-notice');
delete_option('mdirector_settings');
