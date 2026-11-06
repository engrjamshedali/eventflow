<?php
/**
 * EventFlow Participant Portal Alias
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Participant_Portal {

	/**
	 * Render participant portal view
	 *
	 * @param array $atts
	 * @return string
	 */
	public static function render( $atts = array() ) {
		return EventFlow_Shortcodes::render_dashboard( $atts );
	}
}

