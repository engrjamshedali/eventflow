<?php
/**
 * EventFlow Autoloader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Autoloader {

	/**
	 * Register the autoloader
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload class file
	 *
	 * @param string $class
	 */
	public static function autoload( $class ) {
		if ( 0 !== strpos( $class, 'EventFlow' ) ) {
			return;
		}

		// Exact class name mapping or conventional mapping
		$map = array(
			'EventFlow'                       => EVENTFLOW_PLUGIN_DIR . 'includes/class-eventflow.php',
			'EventFlow_DB'                    => EVENTFLOW_PLUGIN_DIR . 'includes/class-db.php',
			'EventFlow_Roles'                 => EVENTFLOW_PLUGIN_DIR . 'includes/class-roles.php',
			'EventFlow_Security'              => EVENTFLOW_PLUGIN_DIR . 'includes/class-security.php',
			'EventFlow_Auth'                  => EVENTFLOW_PLUGIN_DIR . 'includes/class-auth.php',
			'EventFlow_Event_Manager'         => EVENTFLOW_PLUGIN_DIR . 'includes/class-event-manager.php',
			'EventFlow_Activity_Manager'      => EVENTFLOW_PLUGIN_DIR . 'includes/class-activity-manager.php',
			'EventFlow_Registration_Manager'  => EVENTFLOW_PLUGIN_DIR . 'includes/class-registration-manager.php',
			'EventFlow_Team_Manager'          => EVENTFLOW_PLUGIN_DIR . 'includes/class-team-manager.php',
			'EventFlow_Payment_Manager'       => EVENTFLOW_PLUGIN_DIR . 'includes/class-payment-manager.php',
			'EventFlow_Payment_Verification'  => EVENTFLOW_PLUGIN_DIR . 'includes/class-payment-verification.php',
			'EventFlow_Notification_Manager'  => EVENTFLOW_PLUGIN_DIR . 'includes/class-notification-manager.php',
			'EventFlow_Participant_Portal'    => EVENTFLOW_PLUGIN_DIR . 'includes/class-participant-portal.php',
			'EventFlow_Staff_Dashboard'       => EVENTFLOW_PLUGIN_DIR . 'includes/class-staff-dashboard.php',
			'EventFlow_Email_Manager'         => EVENTFLOW_PLUGIN_DIR . 'includes/class-email-manager.php',
			'EventFlow_SMTP'                  => EVENTFLOW_PLUGIN_DIR . 'includes/class-smtp.php',
			'EventFlow_Email_Queue'           => EVENTFLOW_PLUGIN_DIR . 'includes/class-email-queue.php',
			'EventFlow_Email_Diagnostics'     => EVENTFLOW_PLUGIN_DIR . 'includes/class-email-diagnostics.php',
			'EventFlow_Export_Manager'        => EVENTFLOW_PLUGIN_DIR . 'includes/class-export-manager.php',
			'EventFlow_Import_Manager'        => EVENTFLOW_PLUGIN_DIR . 'includes/class-import-manager.php',
			'EventFlow_Analytics_Manager'     => EVENTFLOW_PLUGIN_DIR . 'includes/class-analytics-manager.php',
			'EventFlow_Shortcodes'            => EVENTFLOW_PLUGIN_DIR . 'includes/class-shortcodes.php',
			'EventFlow_AJAX_Handler'          => EVENTFLOW_PLUGIN_DIR . 'includes/class-ajax-handler.php',
			'EventFlow_Elementor'             => EVENTFLOW_PLUGIN_DIR . 'includes/class-elementor.php',
			'EventFlow_App_Shell'             => EVENTFLOW_PLUGIN_DIR . 'includes/class-app-shell.php',
			'EventFlow_Favicon'               => EVENTFLOW_PLUGIN_DIR . 'includes/class-favicon.php',
			'EventFlow_Admin'                 => EVENTFLOW_PLUGIN_DIR . 'admin/class-admin.php',
		);

		if ( isset( $map[ $class ] ) && file_exists( $map[ $class ] ) ) {
			require_once $map[ $class ];
			return;
		}

		// Fallback dynamic resolver: EventFlow_Sub_Class -> class-sub-class.php
		$relative_class = str_replace( 'EventFlow_', '', $class );
		$file_name = 'class-' . strtolower( str_replace( '_', '-', $relative_class ) ) . '.php';

		$directories = array(
			EVENTFLOW_PLUGIN_DIR . 'includes/',
			EVENTFLOW_PLUGIN_DIR . 'admin/',
			EVENTFLOW_PLUGIN_DIR . 'elementor/',
			EVENTFLOW_PLUGIN_DIR . 'elementor/widgets/',
		);

		foreach ( $directories as $directory ) {
			$file = $directory . $file_name;
			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
}

