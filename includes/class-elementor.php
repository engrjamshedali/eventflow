<?php
/**
 * EventFlow Elementor Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Elementor {

	/**
	 * Init Elementor hooks
	 */
	public static function init() {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'add_category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
	}

	/**
	 * Register EventFlow Category in Elementor Editor
	 *
	 * @param \Elementor\Elements_Manager $elements_manager
	 */
	public static function add_category( $elements_manager ) {
		$elements_manager->add_category(
			'eventflow-elements',
			array(
				'title' => __( 'EventFlow Events', 'eventflow' ),
				'icon'  => 'fa fa-calendar-check',
			)
		);
	}

	/**
	 * Register Custom Elementor Widgets
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager
	 */
	public static function register_widgets( $widgets_manager ) {
		require_once EVENTFLOW_PLUGIN_DIR . 'elementor/widgets/class-widget-landing.php';
		require_once EVENTFLOW_PLUGIN_DIR . 'elementor/widgets/class-widget-activities.php';
		require_once EVENTFLOW_PLUGIN_DIR . 'elementor/widgets/class-widget-auth.php';
		require_once EVENTFLOW_PLUGIN_DIR . 'elementor/widgets/class-widget-dashboard.php';
		require_once EVENTFLOW_PLUGIN_DIR . 'elementor/widgets/class-widget-staff-dashboard.php';

		$widgets_manager->register( new \EventFlow_Widget_Landing() );
		$widgets_manager->register( new \EventFlow_Widget_Activities() );
		$widgets_manager->register( new \EventFlow_Widget_Auth() );
		$widgets_manager->register( new \EventFlow_Widget_Dashboard() );
		$widgets_manager->register( new \EventFlow_Widget_Staff_Dashboard() );
	}
}

