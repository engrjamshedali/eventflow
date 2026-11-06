<?php
/**
 * EventFlow Staff Dashboard Elementor Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

class EventFlow_Widget_Staff_Dashboard extends \Elementor\Widget_Base {

	public function get_name() {
		return 'eventflow_staff_dashboard_widget';
	}

	public function get_title() {
		return __( 'EventFlow Staff & Admin Management Portal', 'eventflow' );
	}

	public function get_icon() {
		return 'eicon-table';
	}

	public function get_categories() {
		return array( 'eventflow-elements' );
	}

	protected function render() {
		echo do_shortcode( '[eventflow_staff_dashboard]' );
	}
}
