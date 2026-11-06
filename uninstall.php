<?php
/**
 * EventFlow Uninstall Handler
 *
 * Triggered when plugin is deleted via WordPress Admin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Only drop tables and cleanup if explicit setting is enabled
$delete_data = get_option( 'eventflow_delete_data_on_uninstall', 'no' );

if ( 'yes' === $delete_data ) {
	global $wpdb;

	$tables = array(
		$wpdb->prefix . 'eventflow_audit_logs',
		$wpdb->prefix . 'eventflow_announcements',
		$wpdb->prefix . 'eventflow_email_queue',
		$wpdb->prefix . 'eventflow_email_logs',
		$wpdb->prefix . 'eventflow_email_templates',
		$wpdb->prefix . 'eventflow_payment_logs',
		$wpdb->prefix . 'eventflow_payments',
		$wpdb->prefix . 'eventflow_team_members',
		$wpdb->prefix . 'eventflow_teams',
		$wpdb->prefix . 'eventflow_registrations',
		$wpdb->prefix . 'eventflow_activities',
		$wpdb->prefix . 'eventflow_events',
		$wpdb->prefix . 'eventflow_forms',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// Delete plugin options
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'eventflow_%'" );

	// Remove custom user roles
	$roles = array(
		'eventflow_super_admin',
		'eventflow_event_admin',
		'eventflow_payment_staff',
		'eventflow_registration_staff',
		'eventflow_tech_team',
		'eventflow_participant',
	);

	foreach ( $roles as $role ) {
		remove_role( $role );
	}
}

