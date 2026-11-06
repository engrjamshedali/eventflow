<?php
/**
 * Main EventFlow Plugin Orchestrator Singleton
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow {

	/**
	 * Singleton instance
	 *
	 * @var EventFlow|null
	 */
	protected static $instance = null;

	/**
	 * Get Singleton Instance
	 *
	 * @return EventFlow
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'on_init' ) );

		// Initialize Email Infrastructure & SMTP
		EventFlow_SMTP::init();
		EventFlow_Email_Queue::init();
		EventFlow_Email_Manager::init();

		// Initialize Auth & User Registration hooks
		EventFlow_Auth::init();

		// Initialize Data Import hooks
		EventFlow_Import_Manager::init();

		// Initialize AJAX controller
		EventFlow_AJAX_Handler::init();

		// Initialize Shortcodes
		EventFlow_Shortcodes::init();

		// Initialize Standalone App Shell (bypasses theme headers/footers for full-page experience)
		EventFlow_App_Shell::init();

		// Initialize Favicon & Brand Icons Suite
		EventFlow_Favicon::init();

		// Initialize Admin Backoffice
		if ( is_admin() ) {
			EventFlow_Admin::init();
		}

		// Initialize Elementor integration if loaded
		add_action( 'plugins_loaded', array( $this, 'init_elementor' ), 20 );

		// Custom CSS variable injection
		add_action( 'wp_head', array( $this, 'inject_dynamic_css' ) );
		add_action( 'admin_head', array( $this, 'inject_dynamic_css' ) );
	}

	/**
	 * On WordPress init
	 */
	public function on_init() {
		// Run auto-upgrade check for database tables & migrations
		if ( class_exists( 'EventFlow_DB' ) ) {
			EventFlow_DB::maybe_upgrade();
		}

		// Load text domain
		load_plugin_textdomain( 'eventflow', false, dirname( plugin_basename( EVENTFLOW_PLUGIN_FILE ) ) . '/languages' );

		// Handle Secure Proof Downloads
		EventFlow_Security::serve_secure_file();

		// Handle Data Exports
		EventFlow_Export_Manager::handle_export_request();
	}

	/**
	 * Initialize Elementor Integration
	 */
	public function init_elementor() {
		if ( did_action( 'elementor/loaded' ) ) {
			EventFlow_Elementor::init();
		}
	}

	/**
	 * Inject dynamic brand styling (CSS variables)
	 */
	public function inject_dynamic_css() {
		$primary = get_option( 'eventflow_primary_color', '#36B49F' );
		$secondary = get_option( 'eventflow_secondary_color', '#27A991' );
		?>
		<style id="eventflow-dynamic-brand-vars">
			:root {
				--ef-primary: <?php echo esc_attr( $primary ); ?>;
				--ef-primary-hover: <?php echo esc_attr( $this->adjust_brightness( $primary, -15 ) ); ?>;
				--ef-primary-light: <?php echo esc_attr( $primary ); ?>18;
				--ef-secondary: <?php echo esc_attr( $secondary ); ?>;
			}
		</style>
		<?php
	}

	/**
	 * Simple color brightness calculation for hover states
	 *
	 * @param string $hex
	 * @param int    $steps
	 * @return string
	 */
	private function adjust_brightness( $hex, $steps ) {
		$hex = str_replace( '#', '', $hex );
		if ( 3 === strlen( $hex ) ) {
			$hex = str_repeat( substr( $hex, 0, 1 ), 2 ) . str_repeat( substr( $hex, 1, 1 ), 2 ) . str_repeat( substr( $hex, 2, 1 ), 2 );
		}
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		$r = max( 0, min( 255, $r + $steps ) );
		$g = max( 0, min( 255, $g + $steps ) );
		$b = max( 0, min( 255, $b + $steps ) );

		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}
}

