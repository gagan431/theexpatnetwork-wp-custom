<?php
/**
 * Uninstall routine.
 *
 * Private lead records are deliberately retained to prevent accidental data
 * loss. Remove them manually before uninstalling when required by the site's
 * documented retention process.
 *
 * @package TheExpatNetworkHomepage
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'ten_cleanup_expired_leads' );
delete_option( 'ten_homepage_settings' );
delete_option( 'ten_homepage_data_version' );
