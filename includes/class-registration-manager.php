<?php
/**
 * EventFlow Registration Lifecycle & Form Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Registration_Manager {

	/**
	 * Generate a unique, recognizable registration ID
	 * Example: EF-2026-7A9B2
	 *
	 * @return string
	 */
	public static function generate_reg_id() {
		global $wpdb;
		$table = EventFlow_DB::table( 'registrations' );

		$prefix = get_option( 'eventflow_reg_id_prefix', 'EF' );
		$year   = date( 'Y' );

		do {
			$random = function_exists( 'wp_generate_password' ) ? strtoupper( wp_generate_password( 5, false ) ) : strtoupper( substr( md5( uniqid( (string) mt_rand(), true ) ), 0, 5 ) );
			$reg_id = sprintf( '%s-%s-%s', $prefix, $year, $random );
			$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE reg_id = %s", $reg_id ) );
		} while ( $exists > 0 );

		return $reg_id;
	}

	/**
	 * Process registration submission (Solo or Team, with optional payment receipt upload)
	 *
	 * @param int   $user_id
	 * @param array $data
	 * @param array $files
	 * @return array|WP_Error
	 */
	public static function process_registration( $user_id, $data, $files = array() ) {
		global $wpdb;

		$activity_id = isset( $data['activity_id'] ) ? absint( $data['activity_id'] ) : 0;
		$activity = EventFlow_Activity_Manager::get_activity( $activity_id );

		if ( ! $activity ) {
			return new WP_Error( 'invalid_activity', __( 'Selected activity does not exist.', 'eventflow' ) );
		}

		if ( ! EventFlow_Activity_Manager::is_open_for_registration( $activity ) ) {
			return new WP_Error( 'registration_closed', __( 'Registration for this activity is currently closed.', 'eventflow' ) );
		}

		$target_event_id = ! empty( $data['event_id'] ) ? absint( $data['event_id'] ) : ( ! empty( $activity->event_id ) ? absint( $activity->event_id ) : 0 );
		$event = EventFlow_Event_Manager::get_event( $target_event_id );
		if ( $event ) {
			// Validate event status
			if ( in_array( $event->status, array( 'draft', 'closed', 'completed' ), true ) ) {
				return new WP_Error( 'event_closed', sprintf( __( 'Registration is closed. The event status is currently %s.', 'eventflow' ), esc_html( ucfirst( $event->status ) ) ) );
			}
			// Validate event registration deadlines
			if ( ! empty( $event->reg_end_date ) ) {
				$reg_end = strtotime( $event->reg_end_date . ' 23:59:59' );
				if ( $reg_end && current_time( 'timestamp' ) > $reg_end ) {
					return new WP_Error( 'event_deadline_passed', __( 'Registration deadline for this event has passed.', 'eventflow' ) );
				}
			}
			if ( ! empty( $event->reg_start_date ) ) {
				$reg_start = strtotime( $event->reg_start_date . ' 00:00:00' );
				if ( $reg_start && current_time( 'timestamp' ) < $reg_start ) {
					return new WP_Error( 'event_not_opened', __( 'Registration for this event has not opened yet.', 'eventflow' ) );
				}
			}
		}

		$type = isset( $data['participation_type'] ) && in_array( $data['participation_type'], array( 'solo', 'team' ), true )
			? $data['participation_type']
			: 'solo';

		// Validate event participation type rules
		if ( $event ) {
			if ( 'solo' === $type && isset( $event->allow_solo ) && 0 == $event->allow_solo ) {
				return new WP_Error( 'solo_disabled', __( 'Individual registration is currently disabled for this event.', 'eventflow' ) );
			}
			if ( 'team' === $type && isset( $event->allow_team ) && 0 == $event->allow_team ) {
				return new WP_Error( 'team_disabled', __( 'Team registration is currently disabled for this event.', 'eventflow' ) );
			}
		}

		// Validate activity participation type permission
		if ( 'solo' === $type && 'team' === $activity->participation_type ) {
			return new WP_Error( 'type_not_allowed', __( 'Solo participation is not allowed for this activity.', 'eventflow' ) );
		}
		if ( 'team' === $type && 'solo' === $activity->participation_type ) {
			return new WP_Error( 'type_not_allowed', __( 'Team participation is not allowed for this activity.', 'eventflow' ) );
		}

		$team_name = '';
		$members_data = array();
		$leader_name = '';
		$leader_email = '';
		$leader_phone = '';

		if ( 'team' === $type ) {
			$team_name = ! empty( $data['team_name'] ) ? sanitize_text_field( $data['team_name'] ) : '';
			$raw_members = isset( $data['members'] ) && is_array( $data['members'] ) ? array_values( $data['members'] ) : array();

			// Validate team size against event limits
			if ( $event ) {
				if ( isset( $event->min_team_size ) && (int) $event->min_team_size > 0 && count( $raw_members ) < (int) $event->min_team_size ) {
					return new WP_Error( 'min_team_error', sprintf( __( 'Team must have at least %d members as required by event rules.', 'eventflow' ), $event->min_team_size ) );
				}
				if ( isset( $event->max_team_size ) && (int) $event->max_team_size > 0 && count( $raw_members ) > (int) $event->max_team_size ) {
					return new WP_Error( 'max_team_error', sprintf( __( 'Team cannot exceed %d members as allowed by event rules.', 'eventflow' ), $event->max_team_size ) );
				}
			}

			// Validate team
			$validation = EventFlow_Team_Manager::validate_team_submission( $activity, $team_name, $raw_members );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			$members_data = $raw_members;
			$leader_name  = sanitize_text_field( $members_data[0]['full_name'] );
			$leader_email = sanitize_email( $members_data[0]['email'] );
			$leader_phone = sanitize_text_field( $members_data[0]['contact_number'] );
		} else {
			// Solo Registration - extract all 7 required participant fields
			$solo_name      = ! empty( $data['solo_name'] ) ? sanitize_text_field( $data['solo_name'] ) : ( ! empty( $data['members'][0]['full_name'] ) ? sanitize_text_field( $data['members'][0]['full_name'] ) : '' );
			$solo_email     = ! empty( $data['solo_email'] ) ? sanitize_email( $data['solo_email'] ) : ( ! empty( $data['members'][0]['email'] ) ? sanitize_email( $data['members'][0]['email'] ) : '' );
			$solo_phone     = ! empty( $data['solo_phone'] ) ? sanitize_text_field( $data['solo_phone'] ) : ( ! empty( $data['members'][0]['contact_number'] ) ? sanitize_text_field( $data['members'][0]['contact_number'] ) : ( ! empty( $data['members'][0]['phone'] ) ? sanitize_text_field( $data['members'][0]['phone'] ) : '' ) );
			$solo_dept      = ! empty( $data['solo_department'] ) ? sanitize_text_field( $data['solo_department'] ) : ( ! empty( $data['members'][0]['department'] ) ? sanitize_text_field( $data['members'][0]['department'] ) : '' );
			$solo_sem       = ! empty( $data['solo_semester'] ) ? sanitize_text_field( $data['solo_semester'] ) : ( ! empty( $data['members'][0]['semester'] ) ? sanitize_text_field( $data['members'][0]['semester'] ) : '' );
			$solo_batch     = ! empty( $data['solo_academic_year'] ) ? sanitize_text_field( $data['solo_academic_year'] ) : ( ! empty( $data['solo_batch'] ) ? sanitize_text_field( $data['solo_batch'] ) : ( ! empty( $data['members'][0]['academic_year'] ) ? sanitize_text_field( $data['members'][0]['academic_year'] ) : ( ! empty( $data['members'][0]['batch'] ) ? sanitize_text_field( $data['members'][0]['batch'] ) : '' ) ) );
			$solo_uni       = ! empty( $data['solo_university'] ) ? sanitize_text_field( $data['solo_university'] ) : ( ! empty( $data['members'][0]['university'] ) ? sanitize_text_field( $data['members'][0]['university'] ) : '' );

			if ( empty( $solo_name ) ) {
				return new WP_Error( 'missing_field', __( 'Full Name is required.', 'eventflow' ) );
			}
			if ( empty( $solo_email ) || ! is_email( $solo_email ) ) {
				return new WP_Error( 'invalid_email', __( 'A valid Email Address is required.', 'eventflow' ) );
			}
			if ( empty( $solo_phone ) ) {
				return new WP_Error( 'missing_field', __( 'Phone / WhatsApp Number is required.', 'eventflow' ) );
			}
			if ( empty( $solo_dept ) ) {
				return new WP_Error( 'missing_field', __( 'Department is required.', 'eventflow' ) );
			}
			if ( empty( $solo_sem ) ) {
				return new WP_Error( 'missing_field', __( 'Current Semester is required.', 'eventflow' ) );
			}
			if ( empty( $solo_batch ) ) {
				return new WP_Error( 'missing_field', __( 'Academic Year / Batch is required.', 'eventflow' ) );
			}
			if ( empty( $solo_uni ) ) {
				return new WP_Error( 'missing_field', __( 'University / Institution Name is required.', 'eventflow' ) );
			}

			$leader_name  = $solo_name;
			$leader_email = $solo_email;
			$leader_phone = $solo_phone;

			$members_data[] = array(
				'full_name'      => $solo_name,
				'father_name'    => ! empty( $data['solo_father_name'] ) ? sanitize_text_field( $data['solo_father_name'] ) : '',
				'semester'       => $solo_sem,
				'academic_year'  => $solo_batch,
				'department'     => $solo_dept,
				'university'     => $solo_uni,
				'email'          => $solo_email,
				'contact_number' => $solo_phone,
				'is_leader'      => 1,
			);
		}

		// Ensure user ID exists; if user is a guest, link or auto-provision participant account
		if ( ! $user_id || $user_id <= 0 ) {
			if ( email_exists( $leader_email ) ) {
				$user_id = email_exists( $leader_email );
			} else {
				$username = sanitize_user( current( explode( '@', $leader_email ) ), true );
				$base_user = $username ? $username : 'participant';
				$uname = $base_user;
				$ui = 1;
				while ( username_exists( $uname ) ) {
					$uname = $base_user . $ui;
					$ui++;
				}

				$new_uid = wp_create_user( $uname, wp_generate_password( 12, false ), $leader_email );
				if ( ! is_wp_error( $new_uid ) ) {
					$user_id = $new_uid;
					wp_update_user( array( 'ID' => $user_id, 'display_name' => $leader_name, 'role' => 'eventflow_participant' ) );
					update_user_meta( $user_id, 'eventflow_phone', $leader_phone );
				} else {
					$user_id = 0;
				}
			}
		}

		$table_reg = EventFlow_DB::table( 'registrations' );

		// Duplicate prevention: check if user or leader email already registered for this activity
		if ( $user_id > 0 ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table_reg} WHERE activity_id = %d AND user_id = %d AND application_status != 'rejected'",
					$activity_id,
					$user_id
				)
			);
			if ( $existing ) {
				return new WP_Error( 'already_registered', __( 'You have already submitted a registration for this activity.', 'eventflow' ) );
			}
		}

		// Validate event-wide registration limits
		if ( $event ) {
			if ( isset( $event->max_total_registrations ) && (int) $event->max_total_registrations > 0 ) {
				$total_event_regs = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_reg} WHERE event_id = %d AND application_status != 'rejected'", $event->id ) );
				if ( $total_event_regs >= (int) $event->max_total_registrations ) {
					return new WP_Error( 'event_capacity_reached', __( 'Maximum total registration limit reached for this event.', 'eventflow' ) );
				}
			}
			if ( 'team' === $type && isset( $event->max_team_registrations ) && (int) $event->max_team_registrations > 0 ) {
				$total_team_regs = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_reg} WHERE event_id = %d AND participation_type = 'team' AND application_status != 'rejected'", $event->id ) );
				if ( $total_team_regs >= (int) $event->max_team_registrations ) {
					return new WP_Error( 'team_capacity_reached', __( 'Maximum team registration limit reached for this event.', 'eventflow' ) );
				}
			}
		}

		// Server-side dynamic fee calculation and immutable snapshot (never rely on client-submitted value)
		$participant_count = ( 'team' === $type ) ? count( $members_data ) : 1;
		$fee_result = EventFlow_Event_Manager::calculate_fee( $event ? $event : $activity->event_id, $type, $participant_count, $activity );
		$total_fee = isset( $fee_result['fee'] ) ? floatval( $fee_result['fee'] ) : ( isset( $fee_result['total_fee'] ) ? floatval( $fee_result['total_fee'] ) : 0.00 );
		$fee_breakdown_json = wp_json_encode( isset( $fee_result['fee_breakdown'] ) ? $fee_result['fee_breakdown'] : $fee_result );
		$pricing_snapshot_json = wp_json_encode( isset( $fee_result['pricing_snapshot'] ) ? $fee_result['pricing_snapshot'] : ( isset( $fee_result['snapshot'] ) ? $fee_result['snapshot'] : array() ) );

		// Check if payment receipt proof was uploaded
		$payment_file_entry = ! empty( $files['payment_proof'] ) ? $files['payment_proof'] : ( ! empty( $files['payment_screenshot'] ) ? $files['payment_screenshot'] : null );
		$has_payment_file   = ! empty( $payment_file_entry ) && ! empty( $payment_file_entry['name'] );
		$proof_required     = ( ! isset( $activity->payment_proof_required ) || 1 == $activity->payment_proof_required ) && ( $total_fee > 0 );

		if ( $proof_required && ! $has_payment_file ) {
			return new WP_Error( 'missing_proof', __( 'Please upload your payment screenshot or receipt PDF before submitting.', 'eventflow' ) );
		}

		$uploaded_proof = null;
		if ( $has_payment_file ) {
			$uploaded_proof = EventFlow_Payment_Manager::handle_file_upload( $payment_file_entry );
			if ( is_wp_error( $uploaded_proof ) ) {
				return $uploaded_proof;
			}
		}

		$reg_id = self::generate_reg_id();

		// Process Dynamic Custom Fields
		$custom_fields_json = null;
		if ( ! empty( $data['custom_fields'] ) ) {
			$raw_custom = is_array( $data['custom_fields'] ) ? $data['custom_fields'] : json_decode( stripslashes( $data['custom_fields'] ), true );
			if ( is_array( $raw_custom ) ) {
				$sanitized_custom = array();
				foreach ( $raw_custom as $c_key => $c_val ) {
					$clean_key = sanitize_key( $c_key );
					if ( is_array( $c_val ) ) {
						$sanitized_custom[ $clean_key ] = array_map( 'sanitize_text_field', $c_val );
					} else {
						$sanitized_custom[ $clean_key ] = sanitize_textarea_field( $c_val );
					}
				}
				$custom_fields_json = wp_json_encode( $sanitized_custom );
			}
		}

		// When proof is uploaded, payment status is 'under_verification' (Pending Verification)
		$init_payment_status = $has_payment_file ? 'under_verification' : ( $total_fee > 0 ? 'pending' : 'verified' );
		$init_app_status     = ( 'verified' === $init_payment_status ) ? 'approved' : 'submitted';

		// Ensure registrations table exists and schema is up-to-date
		if ( method_exists( $wpdb, 'get_var' ) ) {
			$reg_table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_reg ) );
			if ( $reg_table_exists !== $table_reg && class_exists( 'EventFlow_DB' ) ) {
				EventFlow_DB::create_tables();
				EventFlow_DB::migrate_tables();
			}
		}

		$existing_cols = method_exists( $wpdb, 'get_col' ) ? (array) $wpdb->get_col( "DESC {$table_reg}", 0 ) : array();

		// Auto-migrate custom_fields if missing on active database
		if ( ! empty( $existing_cols ) && ! in_array( 'custom_fields', $existing_cols, true ) ) {
			$wpdb->query( "ALTER TABLE {$table_reg} ADD COLUMN custom_fields longtext DEFAULT NULL" );
			$existing_cols = method_exists( $wpdb, 'get_col' ) ? (array) $wpdb->get_col( "DESC {$table_reg}", 0 ) : array();
		}

		$event_id_val  = ! empty( $activity->event_id ) ? absint( $activity->event_id ) : 1;
		$team_name_val = ! empty( $team_name ) ? sanitize_text_field( $team_name ) : '';

		// Prepare safe registration insert payload
		$reg_data = array(
			'reg_id'             => $reg_id,
			'event_id'           => $event_id_val,
			'activity_id'        => absint( $activity->id ),
			'user_id'            => $user_id ? absint( $user_id ) : 0,
			'participation_type' => $type,
			'team_name'          => $team_name_val,
			'total_fee'          => floatval( $total_fee ),
			'payment_status'     => $init_payment_status,
			'application_status' => $init_app_status,
			'created_at'         => current_time( 'mysql' ),
			'updated_at'         => current_time( 'mysql' ),
		);
		$reg_format = array( '%s', '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s' );

		// Conditionally append admin_notes if column exists
		if ( empty( $existing_cols ) || in_array( 'admin_notes', $existing_cols, true ) ) {
			$reg_data['admin_notes'] = '';
			$reg_format[] = '%s';
		}

		// Conditionally append custom_fields if column exists
		if ( empty( $existing_cols ) || in_array( 'custom_fields', $existing_cols, true ) ) {
			$reg_data['custom_fields'] = $custom_fields_json;
			$reg_format[] = '%s';
		}

		// Conditionally append fee_breakdown if column exists
		if ( empty( $existing_cols ) || in_array( 'fee_breakdown', $existing_cols, true ) ) {
			$reg_data['fee_breakdown'] = $fee_breakdown_json;
			$reg_format[] = '%s';
		}

		// Conditionally append pricing_snapshot if column exists
		if ( empty( $existing_cols ) || in_array( 'pricing_snapshot', $existing_cols, true ) ) {
			$reg_data['pricing_snapshot'] = $pricing_snapshot_json;
			$reg_format[] = '%s';
		}

		// Insert Registration Record
		$inserted = $wpdb->insert( $table_reg, $reg_data, $reg_format );

		if ( ! $inserted ) {
			$db_err = ! empty( $wpdb->last_error ) ? $wpdb->last_error : 'Unknown database error';
			error_log( 'EventFlow Registration DB Insert Error: ' . $db_err . ' | Last Query: ' . ( ! empty( $wpdb->last_query ) ? $wpdb->last_query : 'N/A' ) );

			// Emergency schema self-heal and retry
			if ( class_exists( 'EventFlow_DB' ) ) {
				EventFlow_DB::create_tables();
				EventFlow_DB::migrate_tables();

				$inserted = $wpdb->insert(
					$table_reg,
					array(
						'reg_id'             => $reg_id,
						'event_id'           => $event_id_val,
						'activity_id'        => absint( $activity->id ),
						'user_id'            => $user_id ? absint( $user_id ) : 0,
						'participation_type' => $type,
						'team_name'          => $team_name_val,
						'total_fee'          => floatval( $total_fee ),
						'payment_status'     => $init_payment_status,
						'application_status' => $init_app_status,
						'created_at'         => current_time( 'mysql' ),
						'updated_at'         => current_time( 'mysql' ),
					)
				);
			}
		}

		if ( ! $inserted ) {
			$err_msg = __( 'Could not save registration. Please try again.', 'eventflow' );
			if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || current_user_can( 'manage_options' ) ) {
				$err_msg .= ' (' . ( ! empty( $wpdb->last_error ) ? $wpdb->last_error : 'Database insert failed' ) . ')';
			}
			return new WP_Error( 'db_error', $err_msg );
		}

		$registration_row_id = $wpdb->insert_id;

		// Create team records
		$leader_info = array(
			'user_id' => $user_id ? $user_id : 0,
			'name'    => $leader_name,
			'email'   => $leader_email,
			'phone'   => $leader_phone,
		);

		EventFlow_Team_Manager::create_team_with_members(
			$registration_row_id,
			$team_name ? $team_name : ( $leader_name . ' (Solo)' ),
			$leader_info,
			$members_data
		);

		// Insert payment record if proof was uploaded
		$payment_id = 0;
		if ( $uploaded_proof ) {
			$txn_id     = ! empty( $data['transaction_id'] ) ? sanitize_text_field( $data['transaction_id'] ) : 'TXN-' . date( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 6, false ) );
			$pay_method = ! empty( $data['payment_method'] ) ? sanitize_text_field( $data['payment_method'] ) : 'Bank Transfer';
			$pay_date   = ! empty( $data['payment_date'] ) ? sanitize_text_field( $data['payment_date'] ) : current_time( 'Y-m-d' );
			$pay_amount = ! empty( $data['amount'] ) ? floatval( $data['amount'] ) : $total_fee;

			$wpdb->insert(
				EventFlow_DB::table( 'payments' ),
				array(
					'registration_id' => $registration_row_id,
					'user_id'         => $user_id ? $user_id : 0,
					'transaction_id'  => $txn_id,
					'payment_method'  => $pay_method,
					'amount'          => $pay_amount,
					'payment_date'    => $pay_date,
					'screenshot_url'  => $uploaded_proof['url'],
					'screenshot_path' => $uploaded_proof['path'],
					'file_type'       => $uploaded_proof['type'],
					'file_size'       => $uploaded_proof['size'],
					'status'          => 'under_verification',
					'created_at'      => current_time( 'mysql' ),
					'updated_at'      => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
			);
			$payment_id = $wpdb->insert_id;

			// Insert payment log
			$wpdb->insert(
				EventFlow_DB::table( 'payment_logs' ),
				array(
					'payment_id'      => $payment_id,
					'registration_id' => $registration_row_id,
					'action'          => 'proof_submitted',
					'note'            => sprintf( 'Transaction ID: %s via %s (Amount: %s)', $txn_id, $pay_method, number_format( $pay_amount, 2 ) ),
					'performed_by'    => $user_id ? $user_id : 0,
					'created_at'      => current_time( 'mysql' ),
				)
			);
		}

		// Increment participant count atomically in activity
		$count_inc   = 'team' === $type ? count( $members_data ) : 1;
		$incremented = EventFlow_Activity_Manager::increment_participants( $activity->id, $count_inc );
		if ( ! $incremented ) {
			// Rollback newly inserted registration and payments due to race condition capacity exhaustion
			$wpdb->delete( $table_reg, array( 'id' => $registration_row_id ) );
			$wpdb->delete( EventFlow_DB::table( 'teams' ), array( 'registration_id' => $registration_row_id ) );
			$wpdb->delete( EventFlow_DB::table( 'team_members' ), array( 'registration_id' => $registration_row_id ) );
			if ( $payment_id ) {
				$wpdb->delete( EventFlow_DB::table( 'payments' ), array( 'id' => $payment_id ) );
				$wpdb->delete( EventFlow_DB::table( 'payment_logs' ), array( 'payment_id' => $payment_id ) );
			}
			return new WP_Error( 'capacity_full', __( 'Registration closed: maximum capacity reached for this activity.', 'eventflow' ) );
		}

		// Log audit
		EventFlow_Security::log_audit( 'registration_created', 'registration', $reg_id, array( 'activity' => $activity->title, 'type' => $type ), $user_id );

		// Record initial application status log
		if ( class_exists( 'EventFlow_DB' ) ) {
			$wpdb->insert(
				EventFlow_DB::table( 'status_logs' ),
				array(
					'registration_id' => $registration_row_id,
					'old_status'      => '',
					'new_status'      => $init_app_status,
					'status_type'     => 'application',
					'changed_by'      => $user_id ? absint( $user_id ) : 0,
					'notes'           => __( 'Registration submitted successfully', 'eventflow' ),
					'created_at'      => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
		}

		// Send Email Notifications
		// Uses centralized send_to_registration to notify Solo participant or Team Leader + all members individually
		EventFlow_Email_Manager::send_to_registration(
			$registration_row_id,
			'registration_submitted',
			array(),
			array(
				'idempotency_key' => 'reg_submitted_' . $reg_id,
				'registration_id' => $reg_id,
			)
		);

		// Alert Administrator of new registration submission
		$admin_email = get_option( 'eventflow_admin_notification_email', get_option( 'admin_email' ) );
		if ( ! empty( $admin_email ) && is_email( $admin_email ) ) {
			$currency_symbol = function_exists( 'eventflow_currency_symbol' ) ? eventflow_currency_symbol() : 'PKR';
			EventFlow_Email_Manager::send(
				'admin_registration_submitted',
				$admin_email,
				array(
					'participant_name'         => $leader_name,
					'participant_email'        => $leader_email,
					'team_name'                => $team_name ? $team_name : $leader_name,
					'registration_id'          => $reg_id,
					'activity_name'            => $activity ? $activity->title : '',
					'participation_type_label' => ( 'team' === $type ? __( 'Team Registration', 'eventflow' ) : __( 'Individual Registration', 'eventflow' ) ),
					'payment_amount'           => sprintf( '%s %s', $currency_symbol, number_format( $total_fee, 2 ) ),
					'event_name'               => 'SIBACOM EVENT',
					'website_url'              => admin_url( 'admin.php?page=eventflow-registrations' ),
				),
				array(
					'idempotency_key' => 'admin_reg_submitted_' . $reg_id,
					'registration_id' => $reg_id,
				)
			);
		}

		if ( $uploaded_proof ) {
			EventFlow_Email_Manager::send_to_registration(
				$registration_row_id,
				'payment_under_verification',
				array(),
				array(
					'idempotency_key' => 'payment_under_verification_' . $payment_id,
					'registration_id' => $reg_id,
				)
			);

			// Alert admin of new payment proof submission
			$admin_email = get_option( 'eventflow_admin_notification_email', get_option( 'admin_email' ) );
			if ( ! empty( $admin_email ) && is_email( $admin_email ) ) {
				$currency_symbol = function_exists( 'eventflow_currency_symbol' ) ? eventflow_currency_symbol() : 'PKR';
				EventFlow_Email_Manager::send(
					'admin_payment_submitted',
					$admin_email,
					array(
						'participant_name'  => $leader_name,
						'participant_email' => $leader_email,
						'team_name'         => $team_name ? $team_name : $leader_name,
						'registration_id'   => $reg_id,
						'activity_name'     => $activity ? $activity->title : '',
						'payment_amount'    => sprintf( '%s %s', $currency_symbol, number_format( $total_fee, 2 ) ),
						'transaction_id'    => ! empty( $transaction_id ) ? $transaction_id : 'N/A',
						'event_name'        => 'SIBACOM EVENT',
						'website_url'       => admin_url( 'admin.php?page=eventflow-payments' ),
					),
					array(
						'idempotency_key' => 'admin_payment_submitted_' . $payment_id,
						'registration_id' => $reg_id,
					)
				);
			}
		}

		return array(
			'id'              => $registration_row_id,
			'reg_id'          => $reg_id,
			'activity_id'     => $activity->id,
			'total_fee'       => $total_fee,
			'payment_status'  => $init_payment_status,
			'payment_id'      => $payment_id,
			'team_name'       => $team_name ? $team_name : $leader_name,
			'leader_name'     => $leader_name,
			'leader_email'    => $leader_email,
			'member_count'    => $participant_count,
		);
	}

	/**
	 * Get single registration by ID or Reg ID
	 *
	 * @param int|string $identifier
	 * @return object|null
	 */
	public static function get_registration( $identifier ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'registrations' );

		if ( is_numeric( $identifier ) ) {
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $identifier ) ) );
		} else {
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE reg_id = %s LIMIT 1", sanitize_text_field( $identifier ) ) );
		}
	}

	/**
	 * Get full registration details including activity, event, team and members
	 *
	 * @param int|string $identifier
	 * @return object|null
	 */
	public static function get_full_registration_details( $identifier ) {
		$reg = self::get_registration( $identifier );
		if ( ! $reg ) {
			return null;
		}

		$reg->activity = EventFlow_Activity_Manager::get_activity( $reg->activity_id );
		$reg->event    = EventFlow_Event_Manager::get_event( $reg->event_id );
		$reg->team     = EventFlow_Team_Manager::get_team_by_registration( $reg->id );
		$reg->members  = EventFlow_Team_Manager::get_members_by_registration( $reg->id );
		$reg->payment  = EventFlow_Payment_Manager::get_payment_by_registration( $reg->id );
		$reg->user     = get_userdata( $reg->user_id );

		if ( ! empty( $reg->custom_fields ) && is_string( $reg->custom_fields ) ) {
			$decoded = json_decode( $reg->custom_fields, true );
			if ( is_array( $decoded ) ) {
				$reg->custom_fields = $decoded;
			}
		}

		return $reg;
	}

	/**
	 * Get registrations for a specific participant user
	 *
	 * @param int $user_id
	 * @return array
	 */
	public static function get_user_registrations( $user_id ) {
		global $wpdb;
		$table_reg = EventFlow_DB::table( 'registrations' );
		$table_act = EventFlow_DB::table( 'activities' );

		$sql = "SELECT r.*, a.title as activity_title, a.image_url as activity_image, a.reg_fee as activity_fee 
				FROM {$table_reg} r 
				LEFT JOIN {$table_act} a ON r.activity_id = a.id 
				WHERE r.user_id = %d 
				ORDER BY r.id DESC";

		return $wpdb->get_results( $wpdb->prepare( $sql, absint( $user_id ) ) );
	}

	/**
	 * Get registrations with comprehensive filters and pagination for staff/admin dashboard
	 *
	 * @param array $args
	 * @return array array( 'items' => array, 'total' => int, 'pages' => int )
	 */
	public static function get_all_registrations( $args = array() ) {
		global $wpdb;

		$table_reg = EventFlow_DB::table( 'registrations' );
		$table_act = EventFlow_DB::table( 'activities' );
		$table_team = EventFlow_DB::table( 'teams' );
		$table_members = EventFlow_DB::table( 'team_members' );
		$table_pay = EventFlow_DB::table( 'payments' );

		$defaults = array(
			'search'             => '',
			'activity_id'        => 0,
			'payment_status'     => '',
			'application_status' => '',
			'participation_type' => '',
			'orderby'            => 'id',
			'order'              => 'DESC',
			'page'               => 1,
			'per_page'           => 15,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $args['search'] ) ) {
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(r.reg_id LIKE %s OR r.team_name LIKE %s OR t.leader_name LIKE %s OR t.leader_email LIKE %s)';
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		if ( ! empty( $args['activity_id'] ) ) {
			$where[] = 'r.activity_id = %d';
			$values[] = absint( $args['activity_id'] );
		}

		if ( ! empty( $args['payment_status'] ) && 'all' !== $args['payment_status'] ) {
			$where[] = 'r.payment_status = %s';
			$values[] = sanitize_text_field( $args['payment_status'] );
		}

		if ( ! empty( $args['application_status'] ) && 'all' !== $args['application_status'] ) {
			$where[] = 'r.application_status = %s';
			$values[] = sanitize_text_field( $args['application_status'] );
		}

		if ( ! empty( $args['participation_type'] ) && 'all' !== $args['participation_type'] ) {
			$where[] = 'r.participation_type = %s';
			$values[] = sanitize_text_field( $args['participation_type'] );
		}

		$where_clause = implode( ' AND ', $where );

		// Count total
		$count_sql = "SELECT COUNT(DISTINCT r.id) FROM {$table_reg} r 
					  LEFT JOIN {$table_team} t ON r.id = t.registration_id 
					  WHERE {$where_clause}";

		$total = ! empty( $values )
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) )
			: (int) $wpdb->get_var( $count_sql );

		$page = max( 1, absint( $args['page'] ) );
		$per_page = max( 1, absint( $args['per_page'] ) );
		$offset = ( $page - 1 ) * $per_page;
		$pages = ceil( $total / $per_page );

		$order = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$allowed_order = array( 'id', 'reg_id', 'total_fee', 'created_at', 'payment_status', 'application_status' );
		$orderby = in_array( $args['orderby'], $allowed_order, true ) ? 'r.' . $args['orderby'] : 'r.id';

		$query_sql = "SELECT r.*, a.title as activity_title, t.leader_name, t.leader_email, t.leader_phone, 
							 GREATEST(COALESCE((SELECT COUNT(DISTINCT tm.id) FROM {$table_members} tm LEFT JOIN {$table_team} t2 ON tm.team_id = t2.id WHERE tm.registration_id = r.id OR t2.registration_id = r.id), 0), 1) as member_count, 
							 p.id as payment_id, p.status as payment_rec_status, p.transaction_id, p.payment_method, p.screenshot_path as proof_path, p.screenshot_path, 
							 p.amount as payment_amount, p.rejection_reason as payment_rejection_reason 
					  FROM {$table_reg} r 
					  LEFT JOIN {$table_act} a ON r.activity_id = a.id 
					  LEFT JOIN {$table_team} t ON r.id = t.registration_id 
					  LEFT JOIN {$table_pay} p ON p.registration_id = r.id 
					  WHERE {$where_clause} 
					  ORDER BY {$orderby} {$order} 
					  LIMIT %d OFFSET %d";

		$query_values = $values;
		$query_values[] = $per_page;
		$query_values[] = $offset;

		$items = $wpdb->get_results( $wpdb->prepare( $query_sql, $query_values ) );

		foreach ( $items as $item ) {
			if ( ! empty( $item->payment_id ) ) {
				$item->preview_url = EventFlow_Security::get_secure_proof_url( $item->payment_id );
			}
		}

		return array(
			'items'    => $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => $pages,
		);
	}

	/**
	 * Update application status and notes
	 *
	 * @param int    $registration_id
	 * @param string $app_status
	 * @param string $rejection_reason
	 * @param string $admin_notes
	 * @return bool
	 */
	public static function update_status( $registration_id, $app_status, $rejection_reason = '', $admin_notes = '' ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'registrations' );

		$reg = self::get_registration( $registration_id );
		if ( ! $reg ) {
			return false;
		}

		$data = array(
			'application_status' => sanitize_text_field( $app_status ),
			'updated_at'         => current_time( 'mysql' ),
		);

		if ( ! empty( $rejection_reason ) ) {
			$data['rejection_reason'] = sanitize_textarea_field( $rejection_reason );
		}
		if ( ! empty( $admin_notes ) ) {
			$data['admin_notes'] = sanitize_textarea_field( $admin_notes );
		}

		$wpdb->update( $table, $data, array( 'id' => absint( $registration_id ) ) );

		EventFlow_Security::log_audit(
			'application_status_updated',
			'registration',
			$reg->reg_id,
			array( 'status' => $app_status, 'reason' => $rejection_reason )
		);

		// Record status transition log
		if ( class_exists( 'EventFlow_DB' ) ) {
			$wpdb->insert(
				EventFlow_DB::table( 'status_logs' ),
				array(
					'registration_id' => $reg->id,
					'old_status'      => $reg->application_status,
					'new_status'      => $app_status,
					'status_type'     => 'application',
					'changed_by'      => function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0,
					'notes'           => ! empty( $rejection_reason ) ? $rejection_reason : ( ! empty( $admin_notes ) ? $admin_notes : sprintf( 'Application status set to %s', $app_status ) ),
					'created_at'      => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
		}

		// Trigger Email to Solo Participant or Team Leader + all registered members
		if ( 'approved' === $app_status ) {
			EventFlow_Email_Manager::send_to_registration(
				$reg->id,
				'application_approved',
				array(
					'rejection_reason' => '',
					'admin_notes'      => $admin_notes,
				),
				array(
					'idempotency_key' => 'app_approved_' . $reg->reg_id,
					'registration_id' => $reg->reg_id,
				)
			);
		} elseif ( 'rejected' === $app_status ) {
			EventFlow_Email_Manager::send_to_registration(
				$reg->id,
				'application_rejected',
				array(
					'rejection_reason' => $rejection_reason,
					'admin_notes'      => $admin_notes,
				),
				array(
					'idempotency_key' => 'app_rejected_' . $reg->reg_id . '_' . time(),
					'registration_id' => $reg->reg_id,
				)
			);
		} elseif ( 'cancelled' === $app_status ) {
			EventFlow_Email_Manager::send_to_registration(
				$reg->id,
				'registration_cancelled',
				array(
					'cancellation_reason' => ! empty( $rejection_reason ) ? $rejection_reason : ( ! empty( $admin_notes ) ? $admin_notes : __( 'Cancelled by administrator', 'eventflow' ) ),
					'admin_notes'         => $admin_notes,
				),
				array(
					'idempotency_key' => 'reg_cancelled_' . $reg->reg_id . '_' . time(),
					'registration_id' => $reg->reg_id,
				)
			);
		} else {
			EventFlow_Email_Manager::send_to_registration(
				$reg->id,
				'registration_updated',
				array(
					'new_status'  => $app_status,
					'admin_notes' => $admin_notes,
				),
				array(
					'idempotency_key' => 'reg_updated_' . $reg->reg_id . '_' . time(),
					'registration_id' => $reg->reg_id,
				)
			);
		}

		return true;
	}

	/**
	 * Get CSS Badge formatting for application & payment statuses
	 *
	 * @param string $status
	 * @param string $type
	 * @return string
	 */
	public static function get_status_badge( $status, $type = 'app' ) {
		if ( 'payment' === $type ) {
			$payment_map = array(
				'pending'            => array( 'label' => __( 'Pending Payment', 'eventflow' ), 'class' => 'ef-badge-warning' ),
				'under_verification' => array( 'label' => __( 'Pending Verification', 'eventflow' ), 'class' => 'ef-badge-info' ),
				'verified'           => array( 'label' => __( 'Verified', 'eventflow' ), 'class' => 'ef-badge-success' ),
				'rejected'           => array( 'label' => __( 'Rejected – Re-upload Required', 'eventflow' ), 'class' => 'ef-badge-danger' ),
				'reupload_requested' => array( 'label' => __( 'Re-upload Requested', 'eventflow' ), 'class' => 'ef-badge-warning' ),
			);
			$item = isset( $payment_map[ $status ] ) ? $payment_map[ $status ] : array( 'label' => ucwords( str_replace( '_', ' ', $status ) ), 'class' => 'ef-badge-neutral' );
			return sprintf( '<span class="ef-badge %s">%s</span>', esc_attr( $item['class'] ), esc_html( $item['label'] ) );
		}

		$map = array(
			// Application / Registration Statuses
			'in_progress'        => array( 'label' => __( 'In Progress', 'eventflow' ), 'class' => 'ef-badge-neutral' ),
			'submitted'          => array( 'label' => __( 'Submitted', 'eventflow' ), 'class' => 'ef-badge-info' ),
			'under_review'       => array( 'label' => __( 'Under Review', 'eventflow' ), 'class' => 'ef-badge-warning' ),
			'approved'           => array( 'label' => __( 'Confirmed', 'eventflow' ), 'class' => 'ef-badge-success' ),
			'confirmed'          => array( 'label' => __( 'Confirmed', 'eventflow' ), 'class' => 'ef-badge-success' ),
			'rejected'           => array( 'label' => __( 'Rejected', 'eventflow' ), 'class' => 'ef-badge-danger' ),
			'action_required'    => array( 'label' => __( 'Action Required', 'eventflow' ), 'class' => 'ef-badge-warning' ),
			'completed'          => array( 'label' => __( 'Completed', 'eventflow' ), 'class' => 'ef-badge-primary' ),
		);

		$item = isset( $map[ $status ] ) ? $map[ $status ] : array( 'label' => ucwords( str_replace( '_', ' ', $status ) ), 'class' => 'ef-badge-neutral' );

		return sprintf( '<span class="ef-badge %s">%s</span>', esc_attr( $item['class'] ), esc_html( $item['label'] ) );
	}
}

