<?php
/**
 * EventFlow Participant Authentication Elementor Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

class EventFlow_Widget_Auth extends \Elementor\Widget_Base {

	public function get_name() {
		return 'eventflow_auth_widget';
	}

	public function get_title() {
		return __( 'EventFlow Login & Registration', 'eventflow' );
	}

	public function get_icon() {
		return 'eicon-lock-user';
	}

	public function get_categories() {
		return array( 'eventflow-elements' );
	}

	protected function render() {
		echo do_shortcode( '[eventflow_auth]' );
	}
}
