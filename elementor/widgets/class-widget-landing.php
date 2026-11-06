<?php
/**
 * EventFlow Landing Page Elementor Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

class EventFlow_Widget_Landing extends \Elementor\Widget_Base {

	public function get_name() {
		return 'eventflow_landing_widget';
	}

	public function get_title() {
		return __( 'EventFlow Landing Hero', 'eventflow' );
	}

	public function get_icon() {
		return 'eicon-banner';
	}

	public function get_categories() {
		return array( 'eventflow-elements' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Event Hero Settings', 'eventflow' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_countdown',
			array(
				'label'        => __( 'Show Countdown Timer', 'eventflow' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'eventflow' ),
				'label_off'    => __( 'Hide', 'eventflow' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_announcements',
			array(
				'label'        => __( 'Show Announcements Bar', 'eventflow' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'eventflow' ),
				'label_off'    => __( 'Hide', 'eventflow' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		echo do_shortcode( '[eventflow_landing]' );
	}
}
