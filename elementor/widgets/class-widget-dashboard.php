<?php
/**
 * EventFlow Participant Dashboard Elementor Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

class EventFlow_Widget_Dashboard extends \Elementor\Widget_Base {

	public function get_name() {
		return 'eventflow_dashboard_widget';
	}

	public function get_title() {
		return __( 'EventFlow Participant Dashboard', 'eventflow' );
	}

	public function get_icon() {
		return 'eicon-dashboard';
	}

	public function get_categories() {
		return array( 'eventflow-elements' );
	}

	protected function render() {
		echo do_shortcode( '[eventflow_dashboard]' );
	}
}
