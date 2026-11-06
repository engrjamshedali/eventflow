<?php
/**
 * Plugin Name:       EventFlow – Advanced Event Registration & Management System
 * Plugin URI:        https://eventflow.dev/
 * Description:       A premium, enterprise-grade event registration, activity competition management, team builder, payment verification, and staff analytics dashboard for WordPress.
 * Version:           5.0.2
 * Author:            EventFlow Engineering Team
 * Author URI:        https://eventflow.dev/
 * Text Domain:       eventflow
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Plugin Constants
define( 'EVENTFLOW_VERSION', '5.0.2' );
define( 'EVENTFLOW_PLUGIN_FILE', __FILE__ );
define( 'EVENTFLOW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EVENTFLOW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EVENTFLOW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-autoloader.php';
EventFlow_Autoloader::register();

/**
 * Main instance initialization
 *
 * @return EventFlow
 */
function eventflow() {
	return EventFlow::instance();
}

// Register Activation, Deactivation, and Uninstall Hooks
register_activation_hook( __FILE__, array( 'EventFlow_DB', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'EventFlow_DB', 'deactivate' ) );

// Boot the plugin
add_action( 'plugins_loaded', 'eventflow', 10 );

