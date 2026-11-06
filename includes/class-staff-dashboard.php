<?php
/**
 * EventFlow Staff Dashboard Controller Alias
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Staff_Dashboard {

	/**
	 * Render staff command center view
	 *
	 * @param array $atts
	 * @return string
	 */
	public static function render( $atts = array() ) {
		return EventFlow_Shortcodes::render_staff_dashboard( $atts );
	}
}

