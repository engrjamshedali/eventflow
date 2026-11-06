<?php
/**
 * EventFlow Standalone / Shortcode Authentication Template
 *
 * Renders the reference split-screen authentication experience for
 * shortcode [eventflow_auth] and Elementor widget instances.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_user_logged_in() ) {
	if ( EventFlow_Roles::is_staff() ) {
		?>
		<div class="eventflow-app ef-auth-alert ef-alert-success" style="max-width: 600px; margin: 40px auto; text-align: center;">
			<p><?php esc_html_e( 'You are currently logged in as Staff.', 'eventflow' ); ?></p>
			<a href="<?php echo esc_url( EventFlow_Shortcodes::get_staff_dashboard_url() ); ?>" class="ef-btn-teal ef-btn-pill" style="margin-top: 12px; display: inline-block;">
				<?php esc_html_e( 'Open Staff Dashboard', 'eventflow' ); ?> &rarr;
			</a>
		</div>
		<?php
	} else {
		?>
		<div class="eventflow-app ef-auth-alert ef-alert-success" style="max-width: 600px; margin: 40px auto; text-align: center;">
			<p><?php esc_html_e( 'You are currently logged in.', 'eventflow' ); ?></p>
			<a href="<?php echo esc_url( EventFlow_Shortcodes::get_dashboard_url() ); ?>" class="ef-btn-teal ef-btn-pill" style="margin-top: 12px; display: inline-block;">
				<?php esc_html_e( 'Go to Live Events Dashboard', 'eventflow' ); ?> &rarr;
			</a>
		</div>
		<?php
	}
	return;
}

// Render the split-screen authentication view
include EVENTFLOW_PLUGIN_DIR . 'templates/frontend/auth-home.php';
