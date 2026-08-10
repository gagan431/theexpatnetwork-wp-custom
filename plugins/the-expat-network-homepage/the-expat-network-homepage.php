<?php
/**
 * Plugin Name: The Expat Network Homepage
 * Plugin URI: https://theexpatnetwork.org/
 * Description: Renders The Expat Network homepage with secure, native candidate and partner lead forms.
 * Version: 1.3.0
 * Author: The Expat Network
 * Author URI: https://theexpatnetwork.org/
 * Text Domain: the-expat-network-homepage
 * Requires at least: 6.4
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TEN_HOMEPAGE_VERSION', '1.3.0' );
define( 'TEN_HOMEPAGE_FILE', __FILE__ );
define( 'TEN_HOMEPAGE_DIR', plugin_dir_path( __FILE__ ) );
define( 'TEN_HOMEPAGE_URL', plugin_dir_url( __FILE__ ) );

require_once TEN_HOMEPAGE_DIR . 'includes/class-ten-settings.php';
require_once TEN_HOMEPAGE_DIR . 'includes/class-ten-submissions.php';
require_once TEN_HOMEPAGE_DIR . 'includes/class-ten-form-handler.php';
require_once TEN_HOMEPAGE_DIR . 'includes/class-ten-homepage.php';

/**
 * Boot the plugin.
 *
 * @return void
 */
function ten_homepage_boot() {
	$settings = new TEN_Settings();
	$settings->init();

	$submissions = new TEN_Submissions();
	$submissions->init();

	$forms = new TEN_Form_Handler( $settings, $submissions );
	$forms->init();

	$homepage = new TEN_Homepage( $settings, $forms );
	$homepage->init();
}
add_action( 'plugins_loaded', 'ten_homepage_boot' );

/**
 * Prepare retention metadata and the cleanup schedule on activation.
 *
 * @return void
 */
function ten_homepage_activate() {
	$submissions = new TEN_Submissions();
	$submissions->activate();
}
register_activation_hook( __FILE__, 'ten_homepage_activate' );

/**
 * Remove only the scheduled cleanup event on deactivation.
 * Lead data and settings remain intact.
 *
 * @return void
 */
function ten_homepage_deactivate() {
	TEN_Submissions::deactivate();
}
register_deactivation_hook( __FILE__, 'ten_homepage_deactivate' );
