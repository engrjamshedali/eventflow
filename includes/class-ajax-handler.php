<?php
/**
 * EventFlow Centralized AJAX Controller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_AJAX_Handler {

	/**
	 * Register all AJAX actions
	 */
	public static function init() {
		// Public & Participant Actions (nopriv + priv)
		$public_actions = array(
			'register'            => array( 'EventFlow_Auth', 'handle_register' ),
			'login'               => array( 'EventFlow_Auth', 'handle_login' ),
			'logout'              => array( 'EventFlow_Auth', 'handle_logout' ),
			'forgot_password'     => array( 'EventFlow_Auth', 'handle_forgot_password' ),
			'track_registration'  => array( __CLASS__, 'handle_track_registration' ),
			'get_activity'        => array( __CLASS__, 'handle_get_activity' ),
			'get_form_schema'     => array( __CLASS__, 'handle_get_form_schema' ),
			'submit_registration' => array( __CLASS__, 'handle_submit_registration' ),
			'submit_payment'      => array( __CLASS__, 'handle_submit_payment' ),
		);

		foreach ( $public_actions as $action => $callback ) {
			add_action( 'wp_ajax_eventflow_' . $action, $callback );
			add_action( 'wp_ajax_nopriv_eventflow_' . $action, $callback );
		}

		// Authenticated Actions
		$auth_actions = array(
			'submit_registration'         => array( __CLASS__, 'handle_submit_registration' ),
			'submit_payment'              => array( __CLASS__, 'handle_submit_payment' ),
			'verify_payment'              => array( __CLASS__, 'handle_verify_payment' ),
			'reject_payment'              => array( __CLASS__, 'handle_reject_payment' ),
			'request_reupload'            => array( __CLASS__, 'handle_request_reupload' ),
			'edit_payment'                => array( __CLASS__, 'handle_edit_payment' ),
			'save_form_schema'            => array( __CLASS__, 'handle_save_form_schema' ),
			'get_form_schema'             => array( __CLASS__, 'handle_get_form_schema' ),
			'duplicate_form'              => array( __CLASS__, 'handle_duplicate_form' ),
			'delete_form'                 => array( __CLASS__, 'handle_delete_form' ),
			'update_profile'              => array( __CLASS__, 'handle_update_profile' ),
			'get_analytics_data'          => array( __CLASS__, 'handle_get_analytics_data' ),
			'update_registration_status' => array( __CLASS__, 'handle_update_registration_status' ),
			'save_activity'               => array( __CLASS__, 'handle_save_activity' ),
			'delete_activity'             => array( __CLASS__, 'handle_delete_activity' ),
			'save_event'                  => array( __CLASS__, 'handle_save_event' ),
			'save_announcement'           => array( __CLASS__, 'handle_save_announcement' ),
			'save_staff'                  => array( __CLASS__, 'handle_save_staff' ),
			'send_test_email'             => array( __CLASS__, 'handle_send_test_email' ),
			'smtp_test_connection'        => array( __CLASS__, 'handle_smtp_test_connection' ),
			'smtp_dns_check'              => array( __CLASS__, 'handle_smtp_dns_check' ),
			'process_email_queue'         => array( __CLASS__, 'handle_process_email_queue' ),
			'retry_queue_item'            => array( __CLASS__, 'handle_retry_queue_item' ),
			'cancel_queue_item'           => array( __CLASS__, 'handle_cancel_queue_item' ),
			'clear_completed_queue'       => array( __CLASS__, 'handle_clear_completed_queue' ),
			'retry_email_log'             => array( __CLASS__, 'handle_retry_email_log' ),
			'delete_email_log'            => array( __CLASS__, 'handle_delete_email_log' ),
			'get_email_log_preview'       => array( __CLASS__, 'handle_get_email_log_preview' ),
			'trigger_deadline_check'      => array( __CLASS__, 'handle_trigger_deadline_check' ),
			'preview_email_template'      => array( __CLASS__, 'handle_preview_email_template' ),
			'reset_email_template'        => array( __CLASS__, 'handle_reset_email_template' ),
			'get_payment_history'         => array( __CLASS__, 'handle_get_payment_history' ),
			'get_payment_details'         => array( __CLASS__, 'handle_get_payment_details' ),
			'get_registration_details'    => array( __CLASS__, 'handle_get_registration_details' ),
			'update_participant_details' => array( __CLASS__, 'handle_update_participant_details' ),
			'save_settings'               => array( __CLASS__, 'handle_save_settings' ),
			'delete_announcement'         => array( __CLASS__, 'handle_delete_announcement' ),
			'remove_staff'                => array( __CLASS__, 'handle_remove_staff' ),
			'unlock_account'              => array( __CLASS__, 'handle_unlock_account' ),
			'get_locked_accounts'         => array( __CLASS__, 'handle_get_locked_accounts' ),
			'update_team_status'          => array( __CLASS__, 'handle_update_team_status' ),
			'get_teams_list'              => array( __CLASS__, 'handle_get_teams_list' ),
			'preview_import'              => array( 'EventFlow_Import_Manager', 'handle_ajax_preview' ),
			'process_import'              => array( 'EventFlow_Import_Manager', 'handle_ajax_import' ),
			'get_audit_logs'              => array( __CLASS__, 'handle_get_audit_logs' ),
			'get_notifications'           => array( __CLASS__, 'handle_get_notifications' ),
			'mark_notification_read'      => array( __CLASS__, 'handle_mark_notification_read' ),
			'mark_all_notifications_read' => array( __CLASS__, 'handle_mark_all_notifications_read' ),
		);

		foreach ( $auth_actions as $action => $callback ) {
			add_action( 'wp_ajax_eventflow_' . $action, $callback );
		}
	}

	/**
	 * Handle Registration Submission (Solo or Team, with optional immediate payment receipt)
	 */
	public static function handle_submit_registration() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_frontend_nonce' );

		$user_id = get_current_user_id();
		$result  = EventFlow_Registration_Manager::process_registration( $user_id, $_POST, $_FILES );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$status_label = ( 'under_verification' === $result['payment_status'] )
			? __( 'Pending Verification', 'eventflow' )
			: ( 'verified' === $result['payment_status'] ? __( 'Verified', 'eventflow' ) : __( 'Pending Payment', 'eventflow' ) );

		$msg = ( 'under_verification' === $result['payment_status'] )
			? __( 'Registration and payment proof submitted successfully! Your payment is now Pending Verification by organizers.', 'eventflow' )
			: __( 'Registration submitted successfully! Please proceed to payment to finalize your slot.', 'eventflow' );

		wp_send_json_success(
			array(
				'message'         => $msg,
				'registration_id' => $result['id'],
				'reg_id'          => $result['reg_id'],
				'payment_status'  => $result['payment_status'],
				'status_label'    => $status_label,
				'total_fee'       => $result['total_fee'],
				'team_name'       => $result['team_name'],
				'leader_name'     => $result['leader_name'],
				'leader_email'    => $result['leader_email'],
				'member_count'    => $result['member_count'],
				'payment_url'     => add_query_arg( array( 'tab' => 'payment', 'reg_id' => $result['reg_id'] ), EventFlow_Shortcodes::get_dashboard_url() ),
				'dashboard_url'   => EventFlow_Shortcodes::get_dashboard_url(),
				'receipt_url'     => add_query_arg( array( 'tab' => 'receipt', 'reg_id' => $result['reg_id'] ), EventFlow_Shortcodes::get_dashboard_url() ),
			)
		);
	}

	/**
	 * Handle Payment Proof Submission
	 */
	public static function handle_submit_payment() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_frontend_nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to submit payment proof.', 'eventflow' ) ), 401 );
		}

		$user_id = get_current_user_id();
		$result = EventFlow_Payment_Manager::process_submission( $user_id, $_POST, $_FILES );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Handle Staff Payment Verification
	 */
	public static function handle_verify_payment() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_verify_payments' );

		$payment_id = isset( $_POST['payment_id'] ) ? absint( $_POST['payment_id'] ) : 0;
		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		$res = EventFlow_Payment_Verification::verify_payment( $payment_id, get_current_user_id(), $note );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Payment successfully verified and approved! Confirmation email dispatched.', 'eventflow' ) ) );
	}

	/**
	 * Handle Staff Payment Rejection
	 */
	public static function handle_reject_payment() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_verify_payments' );

		$payment_id = isset( $_POST['payment_id'] ) ? absint( $_POST['payment_id'] ) : 0;
		$reason = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
		$notes  = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

		$res = EventFlow_Payment_Verification::reject_payment( $payment_id, get_current_user_id(), $reason, $notes );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Payment marked as rejected. Participant has been notified.', 'eventflow' ) ) );
	}

	/**
	 * Handle Request Payment Re-upload
	 */
	public static function handle_request_reupload() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_verify_payments' );

		$payment_id = isset( $_POST['payment_id'] ) ? absint( $_POST['payment_id'] ) : 0;
		$instructions = isset( $_POST['instructions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['instructions'] ) ) : '';

		$res = EventFlow_Payment_Verification::request_reupload( $payment_id, get_current_user_id(), $instructions );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Re-upload request sent to participant.', 'eventflow' ) ) );
	}

	/**
	 * Handle Staff Update Registration Application Status
	 */
	public static function handle_update_registration_status() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_edit_registrations' );

		$reg_id   = isset( $_POST['registration_id'] ) ? absint( $_POST['registration_id'] ) : 0;
		$status   = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$reason   = isset( $_POST['rejection_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rejection_reason'] ) ) : '';
		$notes    = isset( $_POST['admin_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ) ) : '';

		$updated = EventFlow_Registration_Manager::update_status( $reg_id, $status, $reason, $notes );
		if ( ! $updated ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update registration status.', 'eventflow' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Registration application status updated successfully.', 'eventflow' ) ) );
	}

	/**
	 * Handle Activity Details Lookup (JSON for modal)
	 */
	public static function handle_get_activity() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$activity = EventFlow_Activity_Manager::get_activity( $id );

		if ( ! $activity ) {
			wp_send_json_error( array( 'message' => __( 'Activity not found.', 'eventflow' ) ) );
		}

		$event = EventFlow_Event_Manager::get_event( $activity->event_id );
		$activity->currency = $event ? $event->currency : 'PKR';
		$activity->formatted_deadline = ! empty( $activity->reg_deadline ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $activity->reg_deadline ) ) : __( 'Standard Deadline', 'eventflow' );
		$activity->is_open = EventFlow_Activity_Manager::is_open_for_registration( $activity );

		wp_send_json_success( $activity );
	}

	/**
	 * Handle Public Registration Tracking
	 */
	public static function handle_track_registration() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_frontend_nonce' );

		$reg_id = isset( $_POST['reg_id'] ) ? sanitize_text_field( wp_unslash( $_POST['reg_id'] ) ) : '';
		$email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( empty( $reg_id ) || empty( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter both your Registration ID and Leader Email.', 'eventflow' ) ) );
		}

		$reg = EventFlow_Registration_Manager::get_registration( $reg_id );
		if ( ! $reg ) {
			wp_send_json_error( array( 'message' => __( 'No registration found matching that ID.', 'eventflow' ) ) );
		}

		$team = EventFlow_Team_Manager::get_team_by_registration( $reg->id );
		if ( ! $team || strtolower( trim( $team->leader_email ) ) !== strtolower( trim( $email ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Verification failed: the email does not match the leader email on file.', 'eventflow' ) ) );
		}

		$activity = EventFlow_Activity_Manager::get_activity( $reg->activity_id );

		wp_send_json_success(
			array(
				'reg_id'             => $reg->reg_id,
				'activity_title'     => $activity ? $activity->title : '',
				'team_name'          => $reg->team_name ? $reg->team_name : $team->leader_name,
				'payment_status'     => $reg->payment_status,
				'payment_badge'      => EventFlow_Registration_Manager::get_status_badge( $reg->payment_status, 'payment' ),
				'application_status' => $reg->application_status,
				'application_badge'  => EventFlow_Registration_Manager::get_status_badge( $reg->application_status, 'app' ),
				'created_at'         => date_i18n( get_option( 'date_format' ), strtotime( $reg->created_at ) ),
			)
		);
	}

	/**
	 * Handle Save/Edit Activity
	 */
	public static function handle_save_activity() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_activities' );

		$id = isset( $_POST['activity_id'] ) ? absint( $_POST['activity_id'] ) : 0;
		$saved_id = EventFlow_Activity_Manager::save_activity( $_POST, $id );

		if ( is_wp_error( $saved_id ) ) {
			wp_send_json_error( array( 'message' => $saved_id->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'     => $id ? __( 'Activity updated successfully.', 'eventflow' ) : __( 'Activity created successfully.', 'eventflow' ),
				'activity_id' => $saved_id,
			)
		);
	}

	/**
	 * Handle Delete Activity
	 */
	public static function handle_delete_activity() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_activities' );

		$id = isset( $_POST['activity_id'] ) ? absint( $_POST['activity_id'] ) : 0;
		$deleted = EventFlow_Activity_Manager::delete_activity( $id );

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Cannot delete activity that already contains registered participants. Please unpublish or close it instead.', 'eventflow' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Activity deleted successfully.', 'eventflow' ) ) );
	}

	/**
	 * Handle Save Event Settings
	 */
	public static function handle_save_event() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_events' );

		$id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		$saved_id = EventFlow_Event_Manager::save_event( $_POST, $id );

		wp_send_json_success( array( 'message' => __( 'Event configuration saved successfully.', 'eventflow' ), 'event_id' => $saved_id ) );
	}

	/**
	 * Handle Save Announcement
	 */
	public static function handle_save_announcement() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_announcements' );

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		$activity_id = isset( $_POST['activity_id'] ) && ! empty( $_POST['activity_id'] ) ? absint( $_POST['activity_id'] ) : null;

		if ( ! $event_id ) {
			$active_event = EventFlow_Event_Manager::get_active_event();
			$event_id = $active_event ? $active_event->id : 1;
		}

		global $wpdb;
		$wpdb->insert(
			EventFlow_DB::table( 'announcements' ),
			array(
				'event_id'    => $event_id,
				'activity_id' => $activity_id,
				'title'       => $title,
				'content'     => $content,
				'created_by'  => get_current_user_id(),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		$announcement_id = (int) $wpdb->insert_id;
		EventFlow_Security::log_audit( 'announcement_created', 'announcement', $announcement_id, $title );

		// Automated Trigger: Dispatch announcement emails to relevant participants
		$notify_participants = isset( $_POST['notify_participants'] ) ? ( 'yes' === $_POST['notify_participants'] || '1' === $_POST['notify_participants'] ) : true;
		$queued_emails = 0;

		if ( $notify_participants ) {
			$table_reg   = EventFlow_DB::table( 'registrations' );
			$table_teams = EventFlow_DB::table( 'teams' );
			$table_act   = EventFlow_DB::table( 'activities' );
			$table_ev    = EventFlow_DB::table( 'events' );

			if ( ! empty( $activity_id ) ) {
				$participants = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT DISTINCT t.leader_email, t.leader_name, t.team_name, r.reg_id, a.title as activity_title, e.title as event_title
						 FROM {$table_reg} r
						 JOIN {$table_teams} t ON t.registration_id = r.id
						 JOIN {$table_act} a ON a.id = r.activity_id
						 JOIN {$table_ev} e ON e.id = r.event_id
						 WHERE r.activity_id = %d",
						$activity_id
					)
				);
				$template_to_send = 'activity_announcement';
			} else {
				$participants = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT DISTINCT t.leader_email, t.leader_name, t.team_name, r.reg_id, '' as activity_title, e.title as event_title
						 FROM {$table_reg} r
						 JOIN {$table_teams} t ON t.registration_id = r.id
						 JOIN {$table_ev} e ON e.id = r.event_id
						 WHERE r.event_id = %d",
						$event_id
					)
				);
				$template_to_send = 'event_update';
			}

			if ( ! empty( $participants ) ) {
				foreach ( $participants as $p ) {
					if ( empty( $p->leader_email ) || ! is_email( $p->leader_email ) ) {
						continue;
					}
					$idem_key = sprintf( 'announcement_%d_%s', $announcement_id, md5( $p->leader_email ) );
					$enq = EventFlow_Email_Manager::send(
						$template_to_send,
						$p->leader_email,
						array(
							'participant_name'     => $p->leader_name ? $p->leader_name : 'Participant',
							'participant_email'    => $p->leader_email,
							'team_name'            => $p->team_name ? $p->team_name : '',
							'registration_id'      => $p->reg_id,
							'activity_name'        => $p->activity_title,
							'event_name'           => $p->event_title,
							'announcement_content' => $content,
							'website_url'          => EventFlow_Shortcodes::get_dashboard_url(),
						),
						array(
							'idempotency_key' => $idem_key,
							'registration_id' => $p->reg_id,
						)
					);
					if ( $enq ) {
						$queued_emails++;
					}
				}
			}
		}

		wp_send_json_success(
			array(
				'message'       => sprintf( __( 'Announcement published successfully! %d participant notification emails dispatched/queued.', 'eventflow' ), $queued_emails ),
				'queued_emails' => $queued_emails,
			)
		);
	}

	/**
	 * Handle Create/Update Staff Account
	 */
	public static function handle_save_staff() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_staff' );

		$name  = isset( $_POST['staff_name'] ) ? sanitize_text_field( wp_unslash( $_POST['staff_name'] ) ) : '';
		$email = isset( $_POST['staff_email'] ) ? sanitize_email( wp_unslash( $_POST['staff_email'] ) ) : '';
		$role  = isset( $_POST['staff_role'] ) ? sanitize_text_field( wp_unslash( $_POST['staff_role'] ) ) : '';
		$raw_u = isset( $_POST['staff_username'] ) ? sanitize_user( wp_unslash( $_POST['staff_username'] ), true ) : '';
		$pwd   = isset( $_POST['staff_password'] ) ? $_POST['staff_password'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		if ( empty( $name ) || empty( $email ) || empty( $role ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in Name, Email, and select a Staff Role.', 'eventflow' ) ) );
			return;
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a valid email address.', 'eventflow' ) ) );
			return;
		}

		// 1. Check if user already exists by email OR by username/login
		$existing_user = get_user_by( 'email', $email );
		if ( ! $existing_user && ! empty( $raw_u ) ) {
			$existing_user = get_user_by( 'login', $raw_u );
		}

		if ( $existing_user ) {
			// Promote existing user to the requested staff role
			$existing_user->set_role( $role );
			$update_data = array(
				'ID'           => $existing_user->ID,
				'display_name' => $name,
			);
			if ( ! empty( $pwd ) ) {
				$update_data['user_pass'] = $pwd;
			}
			wp_update_user( $update_data );

			EventFlow_Security::log_audit( 'staff_role_assigned', 'user', $existing_user->ID, $role );
			wp_send_json_success(
				array(
					'message'  => sprintf( __( 'Role "%1$s" successfully assigned to existing user %2$s (@%3$s).', 'eventflow' ), esc_html( EventFlow_Roles::get_role_label( $existing_user->ID ) ), esc_html( $existing_user->user_email ), esc_html( $existing_user->user_login ) ),
					'user_id'  => $existing_user->ID,
					'username' => $existing_user->user_login,
				)
			);
			return;
		}

		// 2. Creating a new user: generate a guaranteed collision-free unique username
		if ( empty( $pwd ) ) {
			$pwd = function_exists( 'wp_generate_password' ) ? wp_generate_password( 12 ) : substr( md5( uniqid( (string) mt_rand(), true ) ), 0, 12 );
		}

		$base_username = ! empty( $raw_u ) ? $raw_u : sanitize_user( current( explode( '@', $email ) ), true );
		if ( empty( $base_username ) ) {
			$base_username = sanitize_user( str_replace( ' ', '.', strtolower( $name ) ), true );
		}
		if ( empty( $base_username ) ) {
			$base_username = 'staff';
		}

		$base_username = trim( $base_username, '.-_' );
		if ( empty( $base_username ) ) {
			$base_username = 'staff';
		}

		$username = $base_username;
		$suffix   = 1;
		while ( username_exists( $username ) ) {
			$username = $base_username . $suffix;
			$suffix++;
		}

		$user_id = wp_create_user( $username, $pwd, $email );

		if ( is_wp_error( $user_id ) ) {
			// Fallback: If an email collision occurred, update existing user
			if ( 'existing_user_email' === $user_id->get_error_code() ) {
				$fallback_user = get_user_by( 'email', $email );
				if ( $fallback_user ) {
					$fallback_user->set_role( $role );
					wp_update_user( array( 'ID' => $fallback_user->ID, 'display_name' => $name ) );
					if ( ! empty( $pwd ) ) {
						wp_set_password( $pwd, $fallback_user->ID );
					}
					EventFlow_Security::log_audit( 'staff_role_assigned', 'user', $fallback_user->ID, $role );
					wp_send_json_success( array( 'message' => sprintf( __( 'Role updated for existing user %s.', 'eventflow' ), esc_html( $email ) ) ) );
					return;
				}
			}

			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
			return;
		}

		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $name,
				'role'         => $role,
			)
		);

		EventFlow_Security::log_audit( 'staff_account_created', 'user', $user_id, $role );

		wp_send_json_success(
			array(
				'message'  => sprintf( __( 'Staff account created successfully! Username: "%1$s" | Role: "%2$s". Credentials ready for %3$s.', 'eventflow' ), esc_html( $username ), esc_html( EventFlow_Roles::get_role_label( $user_id ) ), esc_html( $email ) ),
				'user_id'  => $user_id,
				'username' => $username,
			)
		);
		return;
	}

	/**
	 * Handle Real Send Test Email with Diagnostics
	 */
	public static function handle_send_test_email() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_emails' );

		$template_key = isset( $_POST['template_key'] ) ? sanitize_text_field( wp_unslash( $_POST['template_key'] ) ) : 'test_email';
		$target_email = isset( $_POST['target_email'] ) ? sanitize_email( wp_unslash( $_POST['target_email'] ) ) : wp_get_current_user()->user_email;

		if ( empty( $target_email ) || ! is_email( $target_email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid recipient email address.', 'eventflow' ) ) );
		}

		$result = EventFlow_Email_Manager::send_test_email( $target_email, $template_key );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Handle SMTP Socket & Authentication Live Diagnostic Probe
	 */
	public static function handle_smtp_test_connection() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_emails' );

		$host       = isset( $_POST['host'] ) ? sanitize_text_field( wp_unslash( $_POST['host'] ) ) : '';
		$port       = isset( $_POST['port'] ) ? absint( $_POST['port'] ) : 587;
		$encryption = isset( $_POST['encryption'] ) ? sanitize_text_field( wp_unslash( $_POST['encryption'] ) ) : 'tls';
		$auth       = isset( $_POST['auth'] ) ? sanitize_text_field( wp_unslash( $_POST['auth'] ) ) : 'yes';
		$username   = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
		$password   = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$timeout    = isset( $_POST['timeout'] ) ? absint( $_POST['timeout'] ) : 10;

		// If password is blank or mask, load existing decrypted password from options
		if ( empty( $password ) || '••••••••' === $password ) {
			$saved_enc = get_option( 'eventflow_smtp_password', '' );
			$password  = EventFlow_SMTP::decrypt_password( $saved_enc );
		}

		if ( 'yes' === $auth && ( ! empty( $username ) || ! empty( $password ) ) ) {
			$res = EventFlow_SMTP::test_smtp_auth( $host, $port, $encryption, $username, $password, $timeout );
		} else {
			$res = EventFlow_SMTP::test_socket_connection( $host, $port, $encryption, $timeout );
		}

		if ( $res['success'] ) {
			wp_send_json_success( $res );
		} else {
			wp_send_json_error( $res );
		}
	}

	/**
	 * Handle Domain DNS Deliverability Inspector (SPF / DMARC)
	 */
	public static function handle_smtp_dns_check() {
		EventFlow_Security::verify_nonce( isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_emails' );

		$smtp_config = EventFlow_SMTP::get_config();
		$domain = isset( $_GET['domain'] ) ? sanitize_text_field( wp_unslash( $_GET['domain'] ) ) : EventFlow_Email_Diagnostics::extract_domain( $smtp_config['from_email'] );

		$dns_res = EventFlow_Email_Diagnostics::check_domain_dns( $domain, $smtp_config['host'] );
		wp_send_json_success( $dns_res );
	}

	/**
	 * Handle Manual Queue Processing Action
	 */
	public static function handle_process_email_queue() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_emails' );

		$batch_size = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 25;
		$results = EventFlow_Email_Queue::process_queue( $batch_size );
		$stats   = EventFlow_Email_Queue::get_queue_stats();

		wp_send_json_success(
			array(
				'message' => sprintf(
					__( 'Queue processed: %1$d sent, %2$d failed, %3$d retrying.', 'eventflow' ),
					$results['sent'],
					$results['failed'],
					$results['retrying']
				),
				'results' => $results,
				'stats'   => $stats,
			)
		);
	}

	/**
	 * Handle Retry of a Queued Item
	 */
	public static function handle_retry_queue_item() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_emails' );

		$id = isset( $_POST['queue_id'] ) ? absint( $_POST['queue_id'] ) : 0;
		$ok = EventFlow_Email_Queue::retry_item( $id );

		if ( $ok ) {
			wp_send_json_success( array( 'message' => __( 'Item status reset to Pending for immediate retry.', 'eventflow' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update queue item.', 'eventflow' ) ) );
		}
	}

	/**
	 * Handle Cancellation of a Queued Item
	 */
	public static function handle_cancel_queue_item() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_emails' );

		$id = isset( $_POST['queue_id'] ) ? absint( $_POST['queue_id'] ) : 0;
		$ok = EventFlow_Email_Queue::cancel_item( $id );

		if ( $ok ) {
			wp_send_json_success( array( 'message' => __( 'Queue item marked as Cancelled.', 'eventflow' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to cancel item.', 'eventflow' ) ) );
		}
	}

	/**
	 * Handle Purging Completed Queue Items
	 */
	public static function handle_clear_completed_queue() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_emails' );

		$count = EventFlow_Email_Queue::clear_completed( 0 ); // 0 = clear all sent/cancelled now
		wp_send_json_success( array( 'message' => sprintf( __( 'Cleared %d completed/cancelled items from queue.', 'eventflow' ), $count ) ) );
	}

	/**
	 * Handle Retry of an Email Log Entry
	 */
	public static function handle_retry_email_log() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_emails' );

		$log_id = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;
		global $wpdb;
		$table_logs = EventFlow_DB::table( 'email_logs' );
		$log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_logs} WHERE id = %d LIMIT 1", $log_id ) );

		if ( ! $log ) {
			wp_send_json_error( array( 'message' => __( 'Log entry not found.', 'eventflow' ) ) );
		}

		// Re-dispatch using immediate native send
		EventFlow_Email_Manager::clear_last_error();
		$headers = ! empty( $log->headers ) ? explode( "\r\n", $log->headers ) : EventFlow_Email_Manager::build_headers();
		$body = ! empty( $log->body ) ? $log->body : $log->subject;

		$sent = EventFlow_Email_Manager::send_raw( $log->recipient_email, $log->subject, $body, $headers );

		$now = current_time( 'mysql' );
		if ( $sent ) {
			$wpdb->update(
				$table_logs,
				array(
					'status'            => 'accepted',
					'error_message'     => null,
					'provider_response' => 'Accepted by WordPress native mail transport on manual retry',
					'sent_at'           => $now,
				),
				array( 'id' => $log->id )
			);
			wp_send_json_success( array( 'message' => sprintf( __( 'Email was accepted by WordPress mail transport for %s!', 'eventflow' ), esc_html( $log->recipient_email ) ) ) );
		} else {
			$last_err = EventFlow_Email_Manager::get_last_error();
			$err_msg  = $last_err ? $last_err->get_error_message() : __( 'wp_mail() returned false. Check hosting mail transport configuration.', 'eventflow' );
			$wpdb->update(
				$table_logs,
				array(
					'status'            => 'failed',
					'retry_count'       => (int) $log->retry_count + 1,
					'error_message'     => $err_msg,
					'provider_response' => 'Transport Failure: ' . $err_msg,
				),
				array( 'id' => $log->id )
			);
			wp_send_json_error( array( 'message' => sprintf( __( 'Retry failed: %s', 'eventflow' ), esc_html( $err_msg ) ) ) );
		}
	}

	/**
	 * Handle Deleting an Email Log Entry
	 */
	public static function handle_delete_email_log() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_emails' );

		$log_id = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;
		global $wpdb;
		$table_logs = EventFlow_DB::table( 'email_logs' );
		$wpdb->delete( $table_logs, array( 'id' => $log_id ), array( '%d' ) );

		wp_send_json_success( array( 'message' => __( 'Log entry deleted.', 'eventflow' ) ) );
	}

	/**
	 * Handle Getting Full Email Log Preview for Modal
	 */
	public static function handle_get_email_log_preview() {
		EventFlow_Security::verify_nonce( isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_emails' );

		$log_id = isset( $_GET['log_id'] ) ? absint( $_GET['log_id'] ) : 0;
		global $wpdb;
		$table_logs = EventFlow_DB::table( 'email_logs' );
		$log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_logs} WHERE id = %d LIMIT 1", $log_id ) );

		if ( ! $log ) {
			wp_send_json_error( array( 'message' => __( 'Log record not found.', 'eventflow' ) ) );
		}

		wp_send_json_success( $log );
	}

	/**
	 * Handle Manual Deadline Reminder Cron Trigger
	 */
	public static function handle_trigger_deadline_check() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_emails' );

		$count = EventFlow_Email_Manager::check_deadline_reminders();
		wp_send_json_success(
			array(
				'message' => sprintf( __( 'Deadline inspection complete: %d reminder notifications queued.', 'eventflow' ), $count ),
				'queued'  => $count,
			)
		);
	}

	/**
	 * Handle Dynamic Template Live Preview
	 */
	public static function handle_preview_email_template() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_emails' );

		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$body    = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';

		// Dummy template object
		$tpl = (object) array(
			'subject' => $subject,
			'body'    => $body,
		);

		$sample_data = array(
			'participant_name'      => 'Ahmad Khan',
			'participant_email'     => 'ahmad@example.com',
			'team_name'             => 'Apex Coders',
			'registration_id'       => 'EF-2026-9M2X4',
			'activity_name'         => 'Speed Programming Championship',
			'payment_amount'        => 'PKR 1,500.00',
			'payment_status'        => 'Verified',
			'transaction_id'        => 'TRX-98218731',
			'application_status'    => 'Approved',
			'rejection_reason'      => 'Transaction ID not found in bank statement',
			'admin_notes'           => 'Please submit a screenshot with clear timestamp and reference code.',
			'announcement_content'  => 'Competition venue has been moved to Hall 3. Reporting time is 9:00 AM sharp.',
		);

		$prepared = EventFlow_Email_Manager::prepare_message( $tpl, 'ahmad@example.com', $sample_data );
		wp_send_json_success( $prepared );
	}

	/**
	 * Handle Resetting an Email Template to Default Content
	 */
	public static function handle_reset_email_template() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_emails' );

		$template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
		$ok = EventFlow_Email_Manager::reset_template( $template_id );

		if ( $ok ) {
			wp_send_json_success( array( 'message' => __( 'Template successfully restored to default copy!', 'eventflow' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to reset template.', 'eventflow' ) ) );
		}
	}

	/**
	 * Get Payment History Logs for modal
	 */
	public static function handle_get_payment_history() {
		EventFlow_Security::verify_nonce( isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_view_payments' );

		$payment_id = isset( $_GET['payment_id'] ) ? absint( $_GET['payment_id'] ) : 0;
		$history = EventFlow_Payment_Verification::get_history( $payment_id );

		wp_send_json_success( $history );
	}

	/**
	 * Get complete registration details for modal view in staff dashboard
	 */
	public static function handle_get_registration_details() {
		EventFlow_Security::verify_nonce( isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_view_registrations' );

		$reg_id = isset( $_GET['reg_id'] ) ? sanitize_text_field( wp_unslash( $_GET['reg_id'] ) ) : '';
		$details = EventFlow_Registration_Manager::get_full_registration_details( $reg_id );

		if ( ! $details ) {
			wp_send_json_error( array( 'message' => __( 'Registration not found.', 'eventflow' ) ) );
		}

		if ( ! empty( $details->payment ) ) {
			$details->payment->preview_url = EventFlow_Security::get_secure_proof_url( $details->payment->id );
		}

		wp_send_json_success( $details );
	}

	/**
	 * Handle Staff Updating a Participant's Details
	 */
	public static function handle_update_participant_details() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_edit_registrations' );

		$member_id = isset( $_POST['member_id'] ) ? absint( $_POST['member_id'] ) : 0;
		if ( ! $member_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid member ID provided.', 'eventflow' ) ) );
		}

		$res = EventFlow_Team_Manager::update_member( $member_id, $_POST );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Participant details updated successfully.', 'eventflow' ) ) );
	}

	/**
	 * Handle Staff Editing a Payment Record
	 */
	public static function handle_edit_payment() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_verify_payments' );

		$payment_id = isset( $_POST['payment_id'] ) ? absint( $_POST['payment_id'] ) : 0;
		if ( ! $payment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid payment ID provided.', 'eventflow' ) ) );
		}

		$res = EventFlow_Payment_Verification::edit_payment( $payment_id, $_POST, $_FILES, get_current_user_id() );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Payment details updated successfully.', 'eventflow' ) ) );
	}

	/**
	 * Handle Saving Dynamic Form Schema
	 */
	public static function handle_save_form_schema() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_activities' );

		$activity_id   = isset( $_POST['activity_id'] ) ? absint( $_POST['activity_id'] ) : 0;
		$title         = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$fields_schema = isset( $_POST['schema'] ) ? wp_unslash( $_POST['schema'] ) : '';

		if ( ! $activity_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid activity ID.', 'eventflow' ) ) );
		}

		$saved = EventFlow_Activity_Manager::save_form_schema( $activity_id, $fields_schema, $title );
		if ( ! $saved ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save form schema.', 'eventflow' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Form schema saved successfully.', 'eventflow' ) ) );
	}

	/**
	 * Handle Getting Dynamic Form Schema
	 */
	public static function handle_get_form_schema() {
		$activity_id = isset( $_GET['activity_id'] ) ? absint( $_GET['activity_id'] ) : 0;
		if ( ! $activity_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid activity ID.', 'eventflow' ) ) );
		}

		$schema = EventFlow_Activity_Manager::get_form_schema( $activity_id );
		wp_send_json_success( array( 'schema' => $schema ) );
	}

	/**
	 * Handle Duplicating Dynamic Form Schema
	 */
	public static function handle_duplicate_form() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_activities' );

		$source_id = isset( $_POST['source_activity_id'] ) ? absint( $_POST['source_activity_id'] ) : 0;
		$target_id = isset( $_POST['target_activity_id'] ) ? absint( $_POST['target_activity_id'] ) : 0;

		if ( ! $source_id || ! $target_id ) {
			wp_send_json_error( array( 'message' => __( 'Source and target activities are required.', 'eventflow' ) ) );
		}

		$ok = EventFlow_Activity_Manager::duplicate_form( $source_id, $target_id );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Failed to duplicate form schema.', 'eventflow' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Form schema successfully duplicated.', 'eventflow' ) ) );
	}

	/**
	 * Handle Deleting Dynamic Form Schema
	 */
	public static function handle_delete_form() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_activities' );

		$activity_id = isset( $_POST['activity_id'] ) ? absint( $_POST['activity_id'] ) : 0;
		if ( ! $activity_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid activity ID.', 'eventflow' ) ) );
		}

		EventFlow_Activity_Manager::delete_form( $activity_id );
		wp_send_json_success( array( 'message' => __( 'Custom form schema removed.', 'eventflow' ) ) );
	}

	/**
	 * Handle Participant Updating Their Profile
	 */
	public static function handle_update_profile() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_frontend_nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to update your profile.', 'eventflow' ) ) );
		}

		$user_id = get_current_user_id();

		$display_name = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
		$phone        = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$university   = isset( $_POST['university'] ) ? sanitize_text_field( wp_unslash( $_POST['university'] ) ) : '';
		$department   = isset( $_POST['department'] ) ? sanitize_text_field( wp_unslash( $_POST['department'] ) ) : '';
		$semester     = isset( $_POST['semester'] ) ? sanitize_text_field( wp_unslash( $_POST['semester'] ) ) : '';
		$batch        = isset( $_POST['academic_year'] ) ? sanitize_text_field( wp_unslash( $_POST['academic_year'] ) ) : '';

		if ( ! empty( $display_name ) ) {
			wp_update_user( array( 'ID' => $user_id, 'display_name' => $display_name ) );
		}

		if ( ! empty( $phone ) ) {
			update_user_meta( $user_id, 'eventflow_phone', $phone );
		}
		if ( ! empty( $university ) ) {
			update_user_meta( $user_id, 'eventflow_university', $university );
		}
		if ( ! empty( $department ) ) {
			update_user_meta( $user_id, 'eventflow_department', $department );
		}
		if ( ! empty( $semester ) ) {
			update_user_meta( $user_id, 'eventflow_semester', $semester );
		}
		if ( ! empty( $batch ) ) {
			update_user_meta( $user_id, 'eventflow_academic_year', $batch );
		}

		EventFlow_Security::log_audit( 'profile_updated', 'user', $user_id, array( 'name' => $display_name, 'phone' => $phone ), $user_id );

		wp_send_json_success( array( 'message' => __( 'Profile updated successfully!', 'eventflow' ) ) );
	}

	/**
	 * Handle Live Analytics Data Lookup with Date Range Support
	 */
	public static function handle_get_analytics_data() {
		EventFlow_Security::verify_nonce( isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '', 'eventflow_staff_nonce' );

		if ( ! EventFlow_Roles::can_access_staff_dashboard() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'eventflow' ) ) );
		}

		$days = isset( $_GET['days'] ) ? max( 7, absint( $_GET['days'] ) ) : 14;

		$kpis    = EventFlow_Analytics_Manager::get_overview_kpis();
		$daily   = EventFlow_Analytics_Manager::get_daily_registrations( $days );
		$weekly  = EventFlow_Analytics_Manager::get_weekly_registrations( 8 );
		$monthly = EventFlow_Analytics_Manager::get_monthly_registrations( 6 );
		$acts    = EventFlow_Analytics_Manager::get_activity_distribution();
		$app_st  = EventFlow_Analytics_Manager::get_application_status_distribution();
		$pay_ver = EventFlow_Analytics_Manager::get_payment_verification_distribution();
		$solo_tm = EventFlow_Analytics_Manager::get_solo_vs_team_distribution();
		$revenue = EventFlow_Analytics_Manager::get_revenue_collection_overview();
		$methods = EventFlow_Analytics_Manager::get_payment_method_distribution();

		wp_send_json_success(
			array(
				'kpis'                  => $kpis,
				'daily_registrations'   => $daily,
				'weekly_registrations'  => $weekly,
				'monthly_registrations' => $monthly,
				'activity_distribution' => $acts,
				'application_status'    => $app_st,
				'payment_verification'  => $pay_ver,
				'solo_vs_team'          => $solo_tm,
				'revenue_overview'      => $revenue,
				'payment_methods'       => $methods,
			)
		);
	}

	/**
	 * Handle Get Single Payment Record Details (Comprehensive Two-Column Roster)
	 */
	public static function handle_get_payment_details() {
		EventFlow_Security::verify_nonce( isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_view_payments' );

		$payment_id = isset( $_GET['payment_id'] ) ? absint( $_GET['payment_id'] ) : 0;
		if ( ! $payment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid payment ID.', 'eventflow' ) ) );
		}

		$item = EventFlow_Payment_Verification::get_payment_full_details( $payment_id );
		if ( ! $item ) {
			wp_send_json_error( array( 'message' => __( 'Payment record not found.', 'eventflow' ) ) );
		}

		wp_send_json_success( $item );
	}

	/**
	 * Handle Saving Settings via AJAX (Frontend Staff Dashboard & Backoffice)
	 */
	public static function handle_save_settings() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_settings' );

		$fields = array(
			'eventflow_active_event_id'          => 'absint',
			'eventflow_system_closed'            => 'sanitize_text_field',
			'eventflow_reg_id_prefix'            => 'sanitize_text_field',
			'eventflow_currency'                 => 'sanitize_text_field',
			'eventflow_primary_color'            => 'sanitize_hex_color',
			'eventflow_secondary_color'          => 'sanitize_hex_color',
			'eventflow_bank_name'                => 'sanitize_text_field',
			'eventflow_account_title'            => 'sanitize_text_field',
			'eventflow_account_number'           => 'sanitize_text_field',
			'eventflow_iban'                     => 'sanitize_text_field',
			'eventflow_easypaisa_title'          => 'sanitize_text_field',
			'eventflow_easypaisa_number'         => 'sanitize_text_field',
			'eventflow_jazzcash_title'           => 'sanitize_text_field',
			'eventflow_jazzcash_number'          => 'sanitize_text_field',
			'eventflow_raast_id'                 => 'sanitize_text_field',
			'eventflow_qr_code_url'              => 'esc_url_raw',
			'eventflow_payment_instructions'     => 'sanitize_textarea_field',
			'eventflow_email_from_name'          => 'sanitize_text_field',
			'eventflow_email_from_address'       => 'sanitize_email',
			'eventflow_enable_login_protection'  => 'sanitize_text_field',
			'eventflow_max_login_attempts'       => 'absint',
			'eventflow_lockout_duration'         => 'absint',
		);

		foreach ( $fields as $option_key => $sanitizer ) {
			if ( isset( $_POST[ $option_key ] ) ) {
				$val = wp_unslash( $_POST[ $option_key ] );
				$sanitized = call_user_func( $sanitizer, $val );
				update_option( $option_key, $sanitized );
			}
		}

		if ( isset( $_POST['eventflow_standalone_mode'] ) || isset( $_POST['has_standalone_toggle'] ) ) {
			update_option( 'eventflow_standalone_mode', ! empty( $_POST['eventflow_standalone_mode'] ) ? 1 : 0 );
		}
		if ( isset( $_POST['eventflow_takeover_homepage'] ) || isset( $_POST['has_takeover_toggle'] ) ) {
			update_option( 'eventflow_takeover_homepage', ! empty( $_POST['eventflow_takeover_homepage'] ) ? 1 : 0 );
		}

		if ( isset( $_POST['eventflow_bank_enabled'] ) || isset( $_POST['has_gateway_toggles'] ) ) {
			update_option( 'eventflow_bank_enabled', ! empty( $_POST['eventflow_bank_enabled'] ) ? 'yes' : 'no' );
			update_option( 'eventflow_jazzcash_enabled', ! empty( $_POST['eventflow_jazzcash_enabled'] ) ? 'yes' : 'no' );
			update_option( 'eventflow_easypaisa_enabled', ! empty( $_POST['eventflow_easypaisa_enabled'] ) ? 'yes' : 'no' );
		}

		EventFlow_Security::log_audit( 'settings_updated', 'system', 0, 'Updated via Staff Command Center' );

		wp_send_json_success( array( 'message' => __( 'System and payment settings updated successfully!', 'eventflow' ) ) );
	}

	/**
	 * Handle Staff Deleting Announcement
	 */
	public static function handle_delete_announcement() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_announcements' );

		$id = isset( $_POST['announcement_id'] ) ? absint( $_POST['announcement_id'] ) : ( isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0 );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid announcement ID.', 'eventflow' ) ) );
		}

		$deleted = EventFlow_Event_Manager::delete_announcement( $id );
		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete announcement.', 'eventflow' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Announcement removed successfully.', 'eventflow' ) ) );
	}

	/**
	 * Handle Removing/Revoking Staff Role
	 */
	public static function handle_remove_staff() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		EventFlow_Security::check_capability( 'eventflow_manage_staff' );

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user ID.', 'eventflow' ) ) );
		}

		if ( $user_id === get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'Security Alert: You cannot revoke your own staff privileges.', 'eventflow' ) ) );
		}

		$target_user = get_userdata( $user_id );
		if ( ! $target_user ) {
			wp_send_json_error( array( 'message' => __( 'User not found.', 'eventflow' ) ) );
		}

		// Reassign to regular participant role
		$target_user->set_role( 'eventflow_participant' );
		EventFlow_Security::log_audit( 'staff_role_revoked', 'user', $user_id, 'Demoted to participant' );

		wp_send_json_success( array( 'message' => sprintf( __( 'Staff role revoked for %s. User demoted to Participant.', 'eventflow' ), esc_html( $target_user->display_name ) ) ) );
	}

	/**
	 * Handle Admin / Staff Unlocking a Locked Account
	 */
	public static function handle_unlock_account() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );
		
		if ( ! EventFlow_Roles::can( 'eventflow_manage_staff' ) && ! EventFlow_Roles::can( 'eventflow_manage_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eventflow' ) ) );
		}

		$identifier = isset( $_POST['identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '';
		if ( empty( $identifier ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing user or email identifier.', 'eventflow' ) ) );
		}

		$unlocked = EventFlow_Auth::unlock_account( $identifier );
		if ( $unlocked ) {
			EventFlow_Security::log_audit( 'account_unlocked', 'user', 0, 'Account manually unlocked by staff for: ' . $identifier );
			wp_send_json_success( array( 'message' => sprintf( __( 'Account [%s] has been unlocked successfully.', 'eventflow' ), esc_html( $identifier ) ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to unlock account or account was not locked.', 'eventflow' ) ) );
		}
	}

	/**
	 * Handle Getting List of Currently Locked Accounts
	 */
	public static function handle_get_locked_accounts() {
		EventFlow_Security::verify_nonce( isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '', 'eventflow_staff_nonce' );

		if ( ! EventFlow_Roles::can( 'eventflow_manage_staff' ) && ! EventFlow_Roles::can( 'eventflow_manage_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eventflow' ) ) );
		}

		$accounts = EventFlow_Auth::get_locked_accounts();
		wp_send_json_success( $accounts ? $accounts : array() );
	}

	/**
	 * Handle Updating Team Registration / Application Status
	 */
	public static function handle_update_team_status() {
		EventFlow_Security::verify_nonce( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '', 'eventflow_staff_nonce' );

		if ( ! EventFlow_Roles::can( 'eventflow_manage_teams' ) && ! EventFlow_Roles::can( 'eventflow_edit_registrations' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eventflow' ) ) );
		}

		$team_id = isset( $_POST['team_id'] ) ? absint( $_POST['team_id'] ) : 0;
		$status  = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$note    = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		if ( ! $team_id || empty( $status ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid team or status provided.', 'eventflow' ) ) );
		}

		$res = EventFlow_Team_Manager::update_team_status( $team_id, $status, $note );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => sprintf( __( 'Team status successfully updated to %s.', 'eventflow' ), ucfirst( $status ) ) ) );
	}

	/**
	 * Handle Getting Paginated List of Teams
	 */
	public static function handle_get_teams_list() {
		EventFlow_Security::verify_nonce( isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '', 'eventflow_staff_nonce' );

		if ( ! EventFlow_Roles::can( 'eventflow_manage_teams' ) && ! EventFlow_Roles::can( 'eventflow_view_registrations' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eventflow' ) ) );
		}

		$page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = isset( $_GET['per_page'] ) ? min( 100, max( 5, absint( $_GET['per_page'] ) ) ) : 20;
		$status   = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$activity = isset( $_GET['activity'] ) ? absint( $_GET['activity'] ) : 0;
		$search   = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';

		$data = EventFlow_Team_Manager::get_teams_list(
			array(
				'page'     => $page,
				'per_page' => $per_page,
				'status'   => $status,
				'activity' => $activity,
				'search'   => $search,
			)
		);

		wp_send_json_success( $data );
	}

	/**
	 * Handle Getting Paginated Audit Logs
	 */
	public static function handle_get_audit_logs() {
		EventFlow_Security::verify_nonce( isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '', 'eventflow_staff_nonce' );

		if ( ! EventFlow_Roles::can( 'eventflow_view_audit_logs' ) && ! EventFlow_Roles::can( 'eventflow_manage_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eventflow' ) ) );
		}

		global $wpdb;
		$table_logs = EventFlow_DB::table( 'audit_logs' );

		$page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = isset( $_GET['per_page'] ) ? min( 100, max( 5, absint( $_GET['per_page'] ) ) ) : 25;
		$action   = isset( $_GET['action_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['action_filter'] ) ) : '';
		$search   = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $action ) ) {
			$where[]  = 'l.action = %s';
			$values[] = $action;
		}

		if ( ! empty( $search ) ) {
			$s        = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(l.action LIKE %s OR l.details LIKE %s OR u.user_login LIKE %s OR u.display_name LIKE %s)';
			$values[] = $s;
			$values[] = $s;
			$values[] = $s;
			$values[] = $s;
		}

		$where_clause = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table_logs} l LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID WHERE {$where_clause}";
		$total     = (int) ( ! empty( $values ) ? $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) ) : $wpdb->get_var( $count_sql ) );

		$offset = ( $page - 1 ) * $per_page;
		$pages  = ceil( $total / $per_page );

		$query_sql = "SELECT l.*, u.user_login, u.display_name, u.user_email
		              FROM {$table_logs} l
		              LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
		              WHERE {$where_clause}
		              ORDER BY l.created_at DESC
		              LIMIT %d OFFSET %d";

		$query_values   = $values;
		$query_values[] = $per_page;
		$query_values[] = $offset;

		$items = $wpdb->get_results( $wpdb->prepare( $query_sql, $query_values ) );

		wp_send_json_success(
			array(
				'items'    => $items ? $items : array(),
				'total'    => $total,
				'page'     => $page,
				'per_page' => $per_page,
				'pages'    => $pages,
			)
		);
	}

	/**
	 * Handle Get User Notifications
	 */
	public static function handle_get_notifications() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'eventflow' ) ), 401 );
		}

		$user_id = get_current_user_id();
		$limit = isset( $_GET['limit'] ) ? max( 1, absint( $_GET['limit'] ) ) : 30;
		$unread_only = ! empty( $_GET['unread_only'] );

		$notifications = EventFlow_Notification_Manager::get_user_notifications(
			$user_id,
			array(
				'limit'       => $limit,
				'unread_only' => $unread_only,
			)
		);
		$unread_count = EventFlow_Notification_Manager::get_unread_count( $user_id );

		wp_send_json_success(
			array(
				'notifications' => $notifications,
				'unread_count'  => $unread_count,
			)
		);
	}

	/**
	 * Handle Mark Notification As Read
	 */
	public static function handle_mark_notification_read() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'eventflow' ) ), 401 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'eventflow_frontend_nonce' ) && ! wp_verify_nonce( $nonce, 'eventflow_staff_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security verification failed.', 'eventflow' ) ), 403 );
		}

		$notification_id = isset( $_POST['notification_id'] ) ? absint( $_POST['notification_id'] ) : 0;
		if ( ! $notification_id ) {
			wp_send_json_error( array( 'message' => __( 'Notification ID is required.', 'eventflow' ) ) );
		}

		$user_id = get_current_user_id();
		EventFlow_Notification_Manager::mark_as_read( $notification_id, $user_id );
		$unread_count = EventFlow_Notification_Manager::get_unread_count( $user_id );

		wp_send_json_success(
			array(
				'message'      => __( 'Notification marked as read.', 'eventflow' ),
				'unread_count' => $unread_count,
			)
		);
	}

	/**
	 * Handle Mark All Notifications As Read
	 */
	public static function handle_mark_all_notifications_read() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'eventflow' ) ), 401 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'eventflow_frontend_nonce' ) && ! wp_verify_nonce( $nonce, 'eventflow_staff_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security verification failed.', 'eventflow' ) ), 403 );
		}

		$user_id = get_current_user_id();
		EventFlow_Notification_Manager::mark_all_read( $user_id );

		wp_send_json_success(
			array(
				'message'      => __( 'All notifications marked as read.', 'eventflow' ),
				'unread_count' => 0,
			)
		);
	}
}

