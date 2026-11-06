<?php
/**
 * EventFlow Advanced Payment Verification Dashboard Controller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Payment_Verification {

	/**
	 * Get payments list with filters and pagination
	 *
	 * @param array $args
	 * @return array
	 */
	public static function get_payments( $args = array() ) {
		global $wpdb;

		$table_p = EventFlow_DB::table( 'payments' );
		$table_r = EventFlow_DB::table( 'registrations' );
		$table_a = EventFlow_DB::table( 'activities' );
		$table_t = EventFlow_DB::table( 'teams' );

		$defaults = array(
			'search'   => '',
			'status'   => '',
			'activity' => 0,
			'method'   => '',
			'orderby'  => 'id',
			'order'    => 'DESC',
			'page'     => 1,
			'per_page' => 15,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $args['search'] ) ) {
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(r.reg_id LIKE %s OR p.transaction_id LIKE %s OR t.leader_name LIKE %s OR t.team_name LIKE %s OR t.leader_email LIKE %s OR u_user.user_email LIKE %s OR u_user.display_name LIKE %s)';
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
			$where[] = 'p.status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		if ( ! empty( $args['activity'] ) ) {
			$where[] = 'r.activity_id = %d';
			$values[] = absint( $args['activity'] );
		}

		if ( ! empty( $args['method'] ) && 'all' !== $args['method'] ) {
			$where[] = 'p.payment_method = %s';
			$values[] = sanitize_text_field( $args['method'] );
		}

		$where_clause = implode( ' AND ', $where );

		// Count total
		$count_sql = "SELECT COUNT(p.id) FROM {$table_p} p 
					  INNER JOIN {$table_r} r ON p.registration_id = r.id 
					  LEFT JOIN {$table_t} t ON r.id = t.registration_id 
					  LEFT JOIN {$wpdb->users} u_user ON r.user_id = u_user.ID 
					  WHERE {$where_clause}";

		$total = ! empty( $values )
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) )
			: (int) $wpdb->get_var( $count_sql );

		$page = max( 1, absint( $args['page'] ) );
		$per_page = max( 1, absint( $args['per_page'] ) );
		$offset = ( $page - 1 ) * $per_page;
		$pages = ceil( $total / $per_page );

		$order = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$allowed_order = array( 'id', 'amount', 'payment_date', 'status', 'created_at' );
		$orderby = in_array( $args['orderby'], $allowed_order, true ) ? 'p.' . $args['orderby'] : 'p.id';

		$query_sql = "SELECT p.*, r.reg_id, r.total_fee, r.participation_type, r.team_name as reg_team_name, 
							 a.title as activity_title, t.leader_name, t.leader_email, t.leader_phone, 
							 u.display_name as staff_name 
					  FROM {$table_p} p 
					  INNER JOIN {$table_r} r ON p.registration_id = r.id 
					  LEFT JOIN {$table_a} a ON r.activity_id = a.id 
					  LEFT JOIN {$table_t} t ON r.id = t.registration_id 
					  LEFT JOIN {$wpdb->users} u ON p.verified_by = u.ID 
					  LEFT JOIN {$wpdb->users} u_user ON r.user_id = u_user.ID 
					  WHERE {$where_clause} 
					  ORDER BY {$orderby} {$order} 
					  LIMIT %d OFFSET %d";

		$query_values = $values;
		$query_values[] = $per_page;
		$query_values[] = $offset;

		$items = $wpdb->get_results( $wpdb->prepare( $query_sql, $query_values ) );

		// Add secure preview link to each item
		foreach ( $items as $item ) {
			$item->preview_url  = EventFlow_Security::get_secure_proof_url( $item->id );
			$item->download_url = EventFlow_Security::get_secure_download_url( $item->id );
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
	 * Get full payment details including team, members, fees and audit history
	 *
	 * @param int $payment_id
	 * @return array|null
	 */
	public static function get_payment_full_details( $payment_id ) {
		global $wpdb;

		$table_p = EventFlow_DB::table( 'payments' );
		$table_r = EventFlow_DB::table( 'registrations' );
		$table_a = EventFlow_DB::table( 'activities' );
		$table_t = EventFlow_DB::table( 'teams' );
		$table_m = EventFlow_DB::table( 'team_members' );
		$table_e = EventFlow_DB::table( 'events' );

		$payment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.*, r.reg_id, r.total_fee, r.participation_type, r.team_name as reg_team_name,
				        r.application_status, r.payment_status as reg_payment_status, r.created_at as reg_created_at,
				        r.event_id, r.activity_id,
				        a.title as activity_title, a.slug as activity_slug,
				        e.title as event_title, e.currency as event_currency,
				        u.display_name as staff_name
				 FROM {$table_p} p
				 INNER JOIN {$table_r} r ON p.registration_id = r.id
				 LEFT JOIN {$table_a} a ON r.activity_id = a.id
				 LEFT JOIN {$table_e} e ON r.event_id = e.id
				 LEFT JOIN {$wpdb->users} u ON p.verified_by = u.ID
				 WHERE p.id = %d LIMIT 1",
				absint( $payment_id )
			)
		);

		if ( ! $payment ) {
			return null;
		}

		// Fetch Team Record (if exists)
		$team = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_t} WHERE registration_id = %d LIMIT 1",
				$payment->registration_id
			)
		);

		// Fetch Team Members
		$members = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_m} WHERE registration_id = %d ORDER BY is_leader DESC, id ASC",
				$payment->registration_id
			)
		);

		// Fallback for solo participant when members table has not yet been populated
		if ( empty( $members ) && $payment->user_id ) {
			$user = get_userdata( $payment->user_id );
			$member_obj = (object) array(
				'id'             => 0,
				'full_name'      => $user ? $user->display_name : 'Solo Participant',
				'father_name'    => '',
				'semester'       => get_user_meta( $payment->user_id, 'eventflow_semester', true ),
				'department'     => get_user_meta( $payment->user_id, 'eventflow_department', true ),
				'university'     => get_user_meta( $payment->user_id, 'eventflow_university', true ),
				'email'          => $user ? $user->user_email : '',
				'contact_number' => get_user_meta( $payment->user_id, 'eventflow_phone', true ),
				'is_leader'      => 1,
			);
			$members = array( $member_obj );
		}

		// Resolve Leader & Academic Information
		$leader_name  = '';
		$leader_email = '';
		$leader_phone = '';
		$university   = '';
		$department   = '';
		$semester     = '';

		if ( $team ) {
			$leader_name  = $team->leader_name;
			$leader_email = $team->leader_email;
			$leader_phone = $team->leader_phone;
		}

		if ( ! empty( $members ) ) {
			foreach ( $members as $m ) {
				if ( ! empty( $m->is_leader ) ) {
					if ( empty( $leader_name ) ) {
						$leader_name = $m->full_name;
					}
					if ( empty( $leader_email ) ) {
						$leader_email = $m->email;
					}
					if ( empty( $leader_phone ) ) {
						$leader_phone = $m->contact_number;
					}
					$university = $m->university;
					$department = $m->department;
					$semester   = $m->semester;
					break;
				}
			}
			if ( empty( $university ) && ! empty( $members[0] ) ) {
				$university = $members[0]->university;
				$department = $members[0]->department;
				$semester   = $members[0]->semester;
			}
		}

		// Previous verification history
		$history = self::get_history( $payment->id, $payment->registration_id );

		// All submission versions for this registration (latest is index 0)
		$submissions = EventFlow_Payment_Manager::get_all_payments_by_registration( $payment->registration_id );
		$latest_payment = ! empty( $submissions ) ? $submissions[0] : $payment;

		$previous_rejection_reason = '';
		if ( ! empty( $submissions ) ) {
			foreach ( $submissions as $s_check ) {
				if ( ! empty( $s_check->rejection_reason ) ) {
					$previous_rejection_reason = (string) $s_check->rejection_reason;
					break;
				}
			}
		}
		if ( empty( $previous_rejection_reason ) && ! empty( $payment->rejection_reason ) ) {
			$previous_rejection_reason = (string) $payment->rejection_reason;
		}

		$submissions_chronological = ! empty( $submissions ) ? array_reverse( $submissions ) : array();
		$submissions_formatted = array();
		if ( ! empty( $submissions_chronological ) ) {
			foreach ( $submissions_chronological as $s ) {
				$submissions_formatted[] = array(
					'id'               => (int) $s->id,
					'version'          => isset( $s->version ) ? (int) $s->version : 1,
					'is_latest'        => ( (int) $s->id === (int) $latest_payment->id ),
					'transaction_id'   => $s->transaction_id,
					'payment_method'   => $s->payment_method,
					'amount'           => (float) $s->amount,
					'payment_date'     => $s->payment_date,
					'formatted_date'   => date_i18n( 'M j, Y', strtotime( $s->payment_date ) ),
					'status'           => $s->status,
					'notes'            => isset( $s->notes ) ? (string) $s->notes : '',
					'rejection_reason' => isset( $s->rejection_reason ) ? (string) $s->rejection_reason : '',
					'preview_url'      => EventFlow_Security::get_secure_proof_url( $s->id ),
					'download_url'     => EventFlow_Security::get_secure_download_url( $s->id ),
					'file_type'        => $s->file_type,
					'file_size'        => (int) $s->file_size,
					'created_at'       => $s->created_at,
				);
			}
		}

		$currency        = $payment->event_currency ? $payment->event_currency : 'PKR';
		$team_name_final = $team ? $team->team_name : ( $payment->reg_team_name ? $payment->reg_team_name : $leader_name );

		return array(
			'id'                        => (int) $payment->id,
			'payment_id'                => (int) $payment->id,
			'latest_payment_id'         => (int) $latest_payment->id,
			'latest_version'            => isset( $latest_payment->version ) ? (int) $latest_payment->version : 1,
			'registration_id'           => (int) $payment->registration_id,
			'reg_id'                    => $payment->reg_id,
			'transaction_id'            => $payment->transaction_id,
			'payment_method'            => $payment->payment_method,
			'amount'                    => (float) $payment->amount,
			'required_amount'           => (float) $payment->total_fee,
			'currency'                  => $currency,
			'payment_date'              => $payment->payment_date,
			'formatted_date'            => date_i18n( 'M j, Y', strtotime( $payment->payment_date ) ),
			'status'                    => $payment->status,
			'preview_url'               => EventFlow_Security::get_secure_proof_url( $payment->id ),
			'download_url'              => EventFlow_Security::get_secure_download_url( $payment->id ),
			'file_type'                 => $payment->file_type,
			'file_size'                 => (int) $payment->file_size,
			'notes'                     => isset( $payment->notes ) ? (string) $payment->notes : '',
			'rejection_reason'          => isset( $payment->rejection_reason ) ? (string) $payment->rejection_reason : '',
			'previous_rejection_reason' => $previous_rejection_reason,
			'verified_by_name'          => ! empty( $payment->staff_name ) ? $payment->staff_name : 'Staff Member',
			'verified_at'               => ! empty( $payment->verified_at ) ? date_i18n( 'M j, Y H:i', strtotime( $payment->verified_at ) ) : '',
			// Team Info
			'team_name'                 => $team_name_final,
			'candidate_name'            => $team_name_final,
			'leader_name'               => $leader_name,
			'leader_email'              => $leader_email,
			'leader_phone'              => $leader_phone,
			'member_count'              => count( $members ),
			'reg_date'                  => date_i18n( 'M j, Y H:i', strtotime( $payment->reg_created_at ) ),
			'university'                => $university,
			'department'                => $department,
			'semester'                  => $semester,
			'activity_title'            => $payment->activity_title,
			'event_title'               => $payment->event_title,
			'participation_type'        => $payment->participation_type,
			'application_status'        => $payment->application_status,
			// All members list
			'members'                   => $members,
			// History logs
			'history'                   => $history,
			// Submissions list
			'submissions'               => $submissions_formatted,
		);
	}

	/**
	 * Verify Payment with database transaction and duplicate prevention
	 *
	 * @param int    $payment_id
	 * @param int    $staff_id
	 * @param string $note
	 * @return bool|WP_Error
	 */
	public static function verify_payment( $payment_id, $staff_id = 0, $note = '' ) {
		global $wpdb;

		if ( is_string( $staff_id ) && ! is_numeric( $staff_id ) ) {
			$tmp_note = $staff_id;
			$staff_id = absint( $note );
			$note     = $tmp_note;
		} else {
			$staff_id = absint( $staff_id );
			$note     = (string) $note;
		}

		if ( ! $staff_id ) {
			$staff_id = get_current_user_id();
		}

		if ( class_exists( 'EventFlow_Roles' ) && ! EventFlow_Roles::is_staff( $staff_id ) ) {
			return new WP_Error( 'unauthorized', __( 'You do not have permission to verify payments.', 'eventflow' ) );
		}

		$table_p = EventFlow_DB::table( 'payments' );
		$table_r = EventFlow_DB::table( 'registrations' );

		$payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_p} WHERE id = %d LIMIT 1", absint( $payment_id ) ) );
		if ( ! $payment ) {
			return new WP_Error( 'not_found', __( 'Payment record not found.', 'eventflow' ) );
		}

		// Prevent duplicate approvals
		if ( 'verified' === $payment->status ) {
			return new WP_Error( 'already_verified', __( 'This payment has already been verified and confirmed.', 'eventflow' ) );
		}

		$now = current_time( 'mysql' );

		// Database Transaction Guard
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Update Payment record
			$updated_p = $wpdb->update(
				$table_p,
				array(
					'status'      => 'verified',
					'notes'       => sanitize_textarea_field( $note ),
					'verified_by' => absint( $staff_id ),
					'verified_at' => $now,
					'updated_at'  => $now,
				),
				array( 'id' => $payment->id )
			);

			// Update Registration record
			$updated_r = $wpdb->update(
				$table_r,
				array(
					'payment_status'     => 'verified',
					'application_status' => 'approved',
					'verified_by'        => absint( $staff_id ),
					'verified_at'        => $now,
					'updated_at'         => $now,
				),
				array( 'id' => $payment->registration_id )
			);

			// Insert Payment Log
			$wpdb->insert(
				EventFlow_DB::table( 'payment_logs' ),
				array(
					'payment_id'      => $payment->id,
					'registration_id' => $payment->registration_id,
					'action'          => 'verified',
					'note'            => ! empty( $note ) ? sanitize_text_field( $note ) : __( 'Payment verified and approved.', 'eventflow' ),
					'performed_by'    => absint( $staff_id ),
					'created_at'      => $now,
				)
			);

			// Record status transition log
			if ( class_exists( 'EventFlow_DB' ) ) {
				$wpdb->insert(
					EventFlow_DB::table( 'status_logs' ),
					array(
						'registration_id' => $payment->registration_id,
						'old_status'      => $payment->status ? $payment->status : 'under_verification',
						'new_status'      => 'verified',
						'status_type'     => 'payment',
						'notes'           => ! empty( $note ) ? sanitize_text_field( $note ) : __( 'Payment verified and approved.', 'eventflow' ),
						'changed_by'      => absint( $staff_id ),
						'created_at'      => $now,
					),
					array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
				);
			}

			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'transaction_failed', __( 'Verification failed due to a database error. Changes rolled back.', 'eventflow' ) );
		}

		$reg = EventFlow_Registration_Manager::get_registration( $payment->registration_id );
		EventFlow_Security::log_audit( 'payment_verified', 'payment', $payment->id, array( 'reg_id' => $reg ? $reg->reg_id : '' ), $staff_id );

		// Send Email to Solo Participant or Team Leader + all registered members
		if ( $reg ) {
			$event    = EventFlow_Event_Manager::get_event( $reg->event_id );
			$currency = $event ? $event->currency : 'PKR';

			EventFlow_Email_Manager::send_to_registration(
				$reg->id,
				'payment_verified',
				array(
					'payment_amount'       => sprintf( '%s %s', $currency, number_format( $payment->amount, 2 ) ),
					'payment_method'       => ! empty( $payment->payment_method ) ? $payment->payment_method : 'Bank Transfer',
					'transaction_id'       => ! empty( $payment->transaction_id ) ? $payment->transaction_id : 'N/A',
					'verification_date'    => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time( 'timestamp' ) ),
					'verification_status'  => 'VERIFIED',
					'admin_notes'          => $note,
					'website_url'          => EventFlow_Shortcodes::get_dashboard_url(),
				),
				array(
					'idempotency_key' => 'payment_verified_' . $payment->id,
					'registration_id' => $reg->reg_id,
				)
			);

			// In-Dashboard Notification to Participant
			if ( class_exists( 'EventFlow_Notification_Manager' ) ) {
				$event_title = ! empty( $event->title ) ? $event->title : 'Event';
				EventFlow_Notification_Manager::create(
					array(
						'user_id'         => $reg->user_id,
						'registration_id' => $reg->id,
						'payment_id'      => $payment->id,
						'type'            => 'payment_verified',
						'title'           => __( 'Payment Approved', 'eventflow' ),
						'message'         => sprintf(
							__( 'Congratulations! Your payment for %s has been successfully verified. Your registration is confirmed.', 'eventflow' ),
							$event_title
						),
						'action_url'      => add_query_arg( array( 'tab' => 'receipt', 'reg_id' => $reg->reg_id ), EventFlow_Shortcodes::get_dashboard_url() ),
					)
				);
			}
		}

		return true;
	}

	/**
	 * Reject Payment with database transaction and mandatory reason
	 *
	 * @param int    $payment_id
	 * @param int    $staff_id
	 * @param string $reason
	 * @param string $notes
	 * @return bool|WP_Error
	 */
	public static function reject_payment( $payment_id, $staff_id = 0, $reason = '', $notes = '' ) {
		global $wpdb;

		if ( is_string( $staff_id ) && ! is_numeric( $staff_id ) ) {
			$tmp_reason = $staff_id;
			$staff_id   = absint( $reason );
			$reason     = $tmp_reason;
		} else {
			$staff_id = absint( $staff_id );
			$reason   = (string) $reason;
		}

		$notes = ! empty( $notes ) ? (string) $notes : $reason;

		if ( ! $staff_id ) {
			$staff_id = get_current_user_id();
		}

		if ( class_exists( 'EventFlow_Roles' ) && ! EventFlow_Roles::is_staff( $staff_id ) ) {
			return new WP_Error( 'unauthorized', __( 'You do not have permission to reject payments.', 'eventflow' ) );
		}

		$table_p = EventFlow_DB::table( 'payments' );
		$table_r = EventFlow_DB::table( 'registrations' );

		$payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_p} WHERE id = %d LIMIT 1", absint( $payment_id ) ) );
		if ( ! $payment ) {
			return new WP_Error( 'not_found', __( 'Payment record not found.', 'eventflow' ) );
		}

		if ( empty( trim( $reason ) ) ) {
			return new WP_Error( 'missing_reason', __( 'A specific rejection reason is mandatory when declining payment proof.', 'eventflow' ) );
		}

		$now = current_time( 'mysql' );

		// Database Transaction Guard
		$wpdb->query( 'START TRANSACTION' );

		try {
			$wpdb->update(
				$table_p,
				array(
					'status'           => 'rejected',
					'rejection_reason' => sanitize_textarea_field( $reason ),
					'notes'            => sanitize_textarea_field( $notes ),
					'verified_by'      => absint( $staff_id ),
					'verified_at'      => $now,
					'updated_at'       => $now,
				),
				array( 'id' => $payment->id )
			);

			$wpdb->update(
				$table_r,
				array(
					'payment_status'   => 'rejected',
					'rejection_reason' => sanitize_textarea_field( $reason ),
					'admin_notes'      => sanitize_textarea_field( $notes ),
					'verified_by'      => absint( $staff_id ),
					'verified_at'      => $now,
					'updated_at'       => $now,
				),
				array( 'id' => $payment->registration_id )
			);

			// Insert Payment Log
			$wpdb->insert(
				EventFlow_DB::table( 'payment_logs' ),
				array(
					'payment_id'      => $payment->id,
					'registration_id' => $payment->registration_id,
					'action'          => 'rejected',
					'note'            => sprintf( 'Payment proof rejected: %s', sanitize_text_field( $reason ) ),
					'performed_by'    => absint( $staff_id ),
					'created_at'      => $now,
				)
			);

			// Record status transition log
			if ( class_exists( 'EventFlow_DB' ) ) {
				$wpdb->insert(
					EventFlow_DB::table( 'status_logs' ),
					array(
						'registration_id' => $payment->registration_id,
						'old_status'      => $payment->status ? $payment->status : 'under_verification',
						'new_status'      => 'rejected',
						'status_type'     => 'payment',
						'notes'           => sanitize_text_field( $reason ),
						'changed_by'      => absint( $staff_id ),
						'created_at'      => $now,
					),
					array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
				);
			}

			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'transaction_failed', __( 'Rejection failed due to a database error. Changes rolled back.', 'eventflow' ) );
		}

		$reg = EventFlow_Registration_Manager::get_registration( $payment->registration_id );
		EventFlow_Security::log_audit(
			'payment_rejected',
			'payment',
			$payment->id,
			array(
				'reg_id' => $reg ? $reg->reg_id : '',
				'tid'    => $payment->transaction_id,
				'reason' => $reason,
				'notes'  => $notes,
			),
			$staff_id
		);

		// Notify Participant & Team Members individually
		if ( $reg ) {
			$event    = EventFlow_Event_Manager::get_event( $reg->event_id );
			$currency = $event ? $event->currency : 'PKR';
			$dash_url = EventFlow_Shortcodes::get_dashboard_url();
			$reupload_url = add_query_arg(
				array(
					'tab'    => 'payment',
					'action' => 'reupload',
					'reg_id' => $reg->reg_id,
				),
				$dash_url
			);

			EventFlow_Email_Manager::send_to_registration(
				$reg->id,
				'payment_rejected',
				array(
					'rejection_reason'            => $reason,
					'admin_notes'                 => $notes,
					'payment_amount'              => sprintf( '%s %s', $currency, number_format( $payment->amount, 2 ) ),
					'transaction_id'              => ! empty( $payment->transaction_id ) ? $payment->transaction_id : 'N/A',
					'payment_method'              => ! empty( $payment->payment_method ) ? $payment->payment_method : 'Bank Transfer',
					'payment_verification_status' => 'REJECTED – RE-UPLOAD REQUIRED',
					'website_url'                 => $dash_url,
					'dashboard_url'               => $dash_url,
					'reupload_url'                => $reupload_url,
				),
				array(
					'idempotency_key' => 'payment_rejected_' . $payment->id . '_' . time(),
					'registration_id' => $reg->reg_id,
				)
			);

			// In-Dashboard Notification to Participant
			if ( class_exists( 'EventFlow_Notification_Manager' ) ) {
				$event_title = ! empty( $event->title ) ? $event->title : 'Event';
				EventFlow_Notification_Manager::create(
					array(
						'user_id'         => $reg->user_id,
						'registration_id' => $reg->id,
						'payment_id'      => $payment->id,
						'type'            => 'payment_rejected',
						'title'           => __( 'Payment Rejected – Re-upload Required', 'eventflow' ),
						'message'         => sprintf(
							__( 'Your payment verification for %s was unsuccessful. Reason: %s. Please log in to your dashboard and upload a corrected payment receipt.', 'eventflow' ),
							$event_title,
							$reason
						),
						'action_url'      => $reupload_url,
					)
				);
			}
		}

		return true;
	}

	/**
	 * Request Payment Re-upload with database transaction and clear instructions
	 *
	 * @param int    $payment_id
	 * @param int    $staff_id
	 * @param string $instructions
	 * @return bool|WP_Error
	 */
	public static function request_reupload( $payment_id, $staff_id = 0, $instructions = '' ) {
		global $wpdb;

		if ( is_string( $staff_id ) && ! is_numeric( $staff_id ) ) {
			$tmp_instructions = $staff_id;
			$staff_id         = absint( $instructions );
			$instructions     = $tmp_instructions;
		} else {
			$staff_id     = absint( $staff_id );
			$instructions = (string) $instructions;
		}

		if ( ! $staff_id ) {
			$staff_id = get_current_user_id();
		}

		if ( class_exists( 'EventFlow_Roles' ) && ! EventFlow_Roles::is_staff( $staff_id ) ) {
			return new WP_Error( 'unauthorized', __( 'You do not have permission to request re-upload of payment proofs.', 'eventflow' ) );
		}

		$table_p = EventFlow_DB::table( 'payments' );
		$table_r = EventFlow_DB::table( 'registrations' );

		$payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_p} WHERE id = %d LIMIT 1", absint( $payment_id ) ) );
		if ( ! $payment ) {
			return new WP_Error( 'not_found', __( 'Payment record not found.', 'eventflow' ) );
		}

		if ( empty( trim( $instructions ) ) ) {
			return new WP_Error( 'missing_instructions', __( 'Please provide clear instructions for the participant regarding what needs to be re-uploaded.', 'eventflow' ) );
		}

		$now = current_time( 'mysql' );

		// Database Transaction Guard
		$wpdb->query( 'START TRANSACTION' );

		try {
			$wpdb->update(
				$table_p,
				array(
					'status'      => 'reupload_requested',
					'notes'       => sanitize_textarea_field( $instructions ),
					'verified_by' => absint( $staff_id ),
					'updated_at'  => $now,
				),
				array( 'id' => $payment->id )
			);

			$wpdb->update(
				$table_r,
				array(
					'payment_status' => 'reupload_requested',
					'admin_notes'    => sanitize_textarea_field( $instructions ),
					'updated_at'     => $now,
				),
				array( 'id' => $payment->registration_id )
			);

			// Insert Log
			$wpdb->insert(
				EventFlow_DB::table( 'payment_logs' ),
				array(
					'payment_id'      => $payment->id,
					'registration_id' => $payment->registration_id,
					'action'          => 'reupload_requested',
					'note'            => sanitize_text_field( $instructions ),
					'performed_by'    => absint( $staff_id ),
					'created_at'      => $now,
				)
			);

			// Record status transition log
			if ( class_exists( 'EventFlow_DB' ) ) {
				$wpdb->insert(
					EventFlow_DB::table( 'status_logs' ),
					array(
						'registration_id' => $payment->registration_id,
						'old_status'      => $payment->status ? $payment->status : 'under_verification',
						'new_status'      => 'reupload_requested',
						'status_type'     => 'payment',
						'notes'           => sanitize_text_field( $instructions ),
						'changed_by'      => absint( $staff_id ),
						'created_at'      => $now,
					),
					array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
				);
			}

			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'transaction_failed', __( 'Request failed due to a database error. Changes rolled back.', 'eventflow' ) );
		}

		$reg = EventFlow_Registration_Manager::get_registration( $payment->registration_id );
		EventFlow_Security::log_audit( 'payment_reupload_requested', 'payment', $payment->id, array( 'instructions' => $instructions ), $staff_id );

		// Notify Participant & Team Members individually
		if ( $reg ) {
			EventFlow_Email_Manager::send_to_registration(
				$reg->id,
				'payment_reupload_requested',
				array(
					'admin_notes'     => $instructions,
					'previous_status' => $payment->status,
					'website_url'     => EventFlow_Shortcodes::get_dashboard_url(),
				),
				array(
					'idempotency_key' => 'payment_reupload_' . $payment->id . '_' . time(),
					'registration_id' => $reg->reg_id,
				)
			);

			// In-Dashboard Notification to Participant
			if ( class_exists( 'EventFlow_Notification_Manager' ) ) {
				$event = EventFlow_Event_Manager::get_event( $reg->event_id );
				$event_title = ! empty( $event->title ) ? $event->title : 'Event';
				EventFlow_Notification_Manager::create(
					array(
						'user_id'         => $reg->user_id,
						'registration_id' => $reg->id,
						'payment_id'      => $payment->id,
						'type'            => 'payment_reupload_requested',
						'title'           => __( 'Action Required: Re-upload Payment Proof', 'eventflow' ),
						'message'         => sprintf(
							__( 'Your payment verification for %s requires correction. Instructions: %s. Please log in to your dashboard and upload a corrected payment receipt.', 'eventflow' ),
							$event_title,
							$instructions
						),
						'action_url'      => add_query_arg( array( 'tab' => 'payment', 'reg_id' => $reg->reg_id ), EventFlow_Shortcodes::get_dashboard_url() ),
					)
				);
			}
		}

		return true;
	}

	/**
	 * Get full verification audit history for a payment
	 *
	 * @param int $payment_id
	 * @param int $registration_id
	 * @return array
	 */
	public static function get_history( $payment_id, $registration_id = 0 ) {
		global $wpdb;
		$table_logs = EventFlow_DB::table( 'payment_logs' );

		if ( $registration_id ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT l.*, u.display_name as staff_name 
					 FROM {$table_logs} l 
					 LEFT JOIN {$wpdb->users} u ON l.performed_by = u.ID 
					 WHERE l.registration_id = %d OR l.payment_id = %d 
					 ORDER BY l.id DESC",
					absint( $registration_id ),
					absint( $payment_id )
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, u.display_name as staff_name 
				 FROM {$table_logs} l 
				 LEFT JOIN {$wpdb->users} u ON l.performed_by = u.ID 
				 WHERE l.payment_id = %d 
				 ORDER BY l.id DESC",
				absint( $payment_id )
			)
		);
	}

	/**
	 * Edit payment details with change tracking and audit logging
	 *
	 * @param int   $payment_id
	 * @param array $data
	 * @param array $files
	 * @param int   $staff_id
	 * @return bool|WP_Error
	 */
	public static function edit_payment( $payment_id, $data, $files = array(), $staff_id = 0 ) {
		global $wpdb;

		if ( ! $staff_id ) {
			$staff_id = get_current_user_id();
		}

		if ( class_exists( 'EventFlow_Roles' ) && ! EventFlow_Roles::is_staff( $staff_id ) ) {
			return new WP_Error( 'unauthorized', __( 'You do not have permission to reject payments.', 'eventflow' ) );
		}

		$table_p = EventFlow_DB::table( 'payments' );
		$table_r = EventFlow_DB::table( 'registrations' );

		$payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_p} WHERE id = %d LIMIT 1", absint( $payment_id ) ) );
		if ( ! $payment ) {
			return new WP_Error( 'not_found', __( 'Payment record not found.', 'eventflow' ) );
		}

		$correction_reason = isset( $data['correction_reason'] ) ? sanitize_textarea_field( $data['correction_reason'] ) : '';

		// If status is verified, require an explicit correction reason
		if ( 'verified' === $payment->status && empty( $correction_reason ) ) {
			return new WP_Error( 'missing_correction_reason', __( 'An explicit correction reason is required when editing an already verified payment.', 'eventflow' ) );
		}

		$changes = array();
		$update_data = array(
			'updated_at' => current_time( 'mysql' ),
		);

		// Transaction ID
		if ( isset( $data['transaction_id'] ) ) {
			$new_tid = sanitize_text_field( $data['transaction_id'] );
			if ( $new_tid !== $payment->transaction_id ) {
				$changes['transaction_id'] = array( 'from' => $payment->transaction_id, 'to' => $new_tid );
				$update_data['transaction_id'] = $new_tid;
			}
		}

		// Payment Method
		if ( isset( $data['payment_method'] ) ) {
			$new_method = sanitize_text_field( $data['payment_method'] );
			if ( $new_method !== $payment->payment_method ) {
				$changes['payment_method'] = array( 'from' => $payment->payment_method, 'to' => $new_method );
				$update_data['payment_method'] = $new_method;
			}
		}

		// Amount
		if ( isset( $data['amount'] ) ) {
			$new_amount = floatval( $data['amount'] );
			if ( abs( $new_amount - floatval( $payment->amount ) ) > 0.001 ) {
				$changes['amount'] = array( 'from' => floatval( $payment->amount ), 'to' => $new_amount );
				$update_data['amount'] = $new_amount;
			}
		}

		// Payment Date
		if ( isset( $data['payment_date'] ) ) {
			$new_date = sanitize_text_field( $data['payment_date'] );
			if ( $new_date !== $payment->payment_date ) {
				$changes['payment_date'] = array( 'from' => $payment->payment_date, 'to' => $new_date );
				$update_data['payment_date'] = $new_date;
			}
		}

		// Notes
		if ( isset( $data['notes'] ) ) {
			$new_notes = sanitize_textarea_field( $data['notes'] );
			if ( $new_notes !== $payment->notes ) {
				$changes['notes'] = array( 'from' => $payment->notes, 'to' => $new_notes );
				$update_data['notes'] = $new_notes;
			}
		}

		// Status override (if provided)
		if ( ! empty( $data['status'] ) && in_array( $data['status'], array( 'pending', 'under_verification', 'verified', 'rejected', 'reupload_requested' ), true ) ) {
			if ( $data['status'] !== $payment->status ) {
				$changes['status'] = array( 'from' => $payment->status, 'to' => $data['status'] );
				$update_data['status'] = $data['status'];
			}
		}

		// Receipt file replacement
		if ( ! empty( $files['payment_proof'] ) && ! empty( $files['payment_proof']['name'] ) ) {
			$uploaded = EventFlow_Security::validate_and_upload_receipt( $files['payment_proof'] );
			if ( is_wp_error( $uploaded ) ) {
				return $uploaded;
			}
			$changes['receipt_file'] = array(
				'from' => basename( (string) $payment->screenshot_path ),
				'to'   => basename( $uploaded['path'] ),
			);
			$update_data['screenshot_url']  = $uploaded['url'];
			$update_data['screenshot_path'] = $uploaded['path'];
			$update_data['file_type']       = $uploaded['type'];
			$update_data['file_size']       = $uploaded['size'];
		}

		if ( empty( $changes ) && empty( $correction_reason ) ) {
			return true; // No modifications requested
		}

		// Apply update in transaction
		$wpdb->query( 'START TRANSACTION' );

		try {
			$wpdb->update( $table_p, $update_data, array( 'id' => $payment->id ) );

			// Sync registration payment_status and application_status if status was modified
			if ( isset( $update_data['status'] ) ) {
				$new_app_status = ( 'verified' === $update_data['status'] ) ? 'approved' : ( 'rejected' === $update_data['status'] ? 'rejected' : 'under_review' );
				$wpdb->update(
					$table_r,
					array(
						'payment_status'     => $update_data['status'],
						'application_status' => $new_app_status,
						'updated_at'         => current_time( 'mysql' ),
					),
					array( 'id' => $payment->registration_id )
				);
			}

			// Build change note
			$note_parts = array();
			if ( ! empty( $correction_reason ) ) {
				$note_parts[] = sprintf( 'Correction Reason: %s', $correction_reason );
			}
			foreach ( $changes as $field => $diff ) {
				$note_parts[] = sprintf( '%s: "%s" -> "%s"', ucfirst( str_replace( '_', ' ', $field ) ), $diff['from'], $diff['to'] );
			}
			$full_note = implode( ' | ', $note_parts );

			// Log into payment_logs
			$wpdb->insert(
				EventFlow_DB::table( 'payment_logs' ),
				array(
					'payment_id'      => $payment->id,
					'registration_id' => $payment->registration_id,
					'action'          => 'payment_edited',
					'note'            => $full_note,
					'performed_by'    => absint( $staff_id ),
					'created_at'      => current_time( 'mysql' ),
				)
			);

			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'transaction_failed', __( 'Payment update failed. Changes rolled back.', 'eventflow' ) );
		}

		// Log audit
		EventFlow_Security::log_audit(
			'payment_edited',
			'payment',
			$payment->id,
			array(
				'changes'           => $changes,
				'correction_reason' => $correction_reason,
			),
			$staff_id
		);

		return true;
	}

	/**
	 * Get payment status counts and KPI summary for admin/staff dashboard
	 *
	 * @param int $event_id
	 * @return array
	 */
	public static function get_payment_counts( $event_id = 0 ) {
		global $wpdb;
		$table_p = EventFlow_DB::table( 'payments' );
		$table_r = EventFlow_DB::table( 'registrations' );

		if ( $event_id ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(p.id) FROM {$table_p} p INNER JOIN {$table_r} r ON p.registration_id = r.id WHERE r.event_id = %d", $event_id ) );
			$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(p.id) FROM {$table_p} p INNER JOIN {$table_r} r ON p.registration_id = r.id WHERE r.event_id = %d AND p.status = 'under_verification'", $event_id ) );
			$verified = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(p.id) FROM {$table_p} p INNER JOIN {$table_r} r ON p.registration_id = r.id WHERE r.event_id = %d AND p.status = 'verified'", $event_id ) );
			$rejected = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(p.id) FROM {$table_p} p INNER JOIN {$table_r} r ON p.registration_id = r.id WHERE r.event_id = %d AND p.status = 'rejected'", $event_id ) );
			$reupload = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(p.id) FROM {$table_p} p INNER JOIN {$table_r} r ON p.registration_id = r.id WHERE r.event_id = %d AND p.status = 'reupload_requested'", $event_id ) );
			$resubmissions = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(p.id) FROM {$table_p} p INNER JOIN {$table_r} r ON p.registration_id = r.id WHERE r.event_id = %d AND p.version > 1", $event_id ) );
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_p}" );
			$pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_p} WHERE status = 'under_verification'" );
			$verified = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_p} WHERE status = 'verified'" );
			$rejected = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_p} WHERE status = 'rejected'" );
			$reupload = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_p} WHERE status = 'reupload_requested'" );
			$resubmissions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_p} WHERE version > 1" );
		}

		return array(
			'total'         => $total,
			'pending'       => $pending,
			'verified'      => $verified,
			'rejected'      => $rejected,
			'reupload'      => $reupload,
			'resubmissions' => $resubmissions,
		);
	}
}
