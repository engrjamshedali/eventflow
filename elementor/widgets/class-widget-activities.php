<?php
/**
 * EventFlow Activities Grid Elementor Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

class EventFlow_Widget_Activities extends \Elementor\Widget_Base {

	public function get_name() {
		return 'eventflow_activities_widget';
	}

	public function get_title() {
		return __( 'EventFlow Activities Grid', 'eventflow' );
	}

	public function get_icon() {
		return 'eicon-gallery-grid';
	}

	public function get_categories() {
		return array( 'eventflow-elements' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Activities Grid Settings', 'eventflow' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'title',
			array(
				'label'   => __( 'Section Title', 'eventflow' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Competitions & Activities', 'eventflow' ),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		echo do_shortcode( '[eventflow_activities]' );
	}
}
