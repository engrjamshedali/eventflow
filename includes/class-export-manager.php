<?php
/**
 * EventFlow Advanced Reporting, Multi-Format Export Engine & Event-Branded PDF Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Export_Manager {

	/**
	 * Verify if current user can export specific data type
	 *
	 * @param string $type
	 * @return bool
	 */
	public static function can_export_type( $type = 'payments' ) {
		$is_full_export   = current_user_can( 'eventflow_export_data' ) || current_user_can( 'manage_options' );
		$is_payment_staff = current_user_can( 'eventflow_verify_payments' );
		$is_team_staff    = current_user_can( 'eventflow_manage_teams' );

		if ( ! $is_full_export && ! $is_payment_staff && ! $is_team_staff ) {
			return false;
		}

		$payment_types = array( 'payments', 'verified_payments', 'pending_payments', 'rejected_payments', 'reupload_payments' );

		// Payment staff can only export payment categories
		if ( $is_payment_staff && ! $is_full_export && ! in_array( $type, $payment_types, true ) ) {
			return false;
		}

		// Audit logs can only be exported by full administrators
		if ( 'audit_logs' === $type && ! current_user_can( 'eventflow_view_audit_logs' ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if current user can export payments
	 *
	 * @return bool
	 */
	public static function can_export_payments() {
		return self::can_export_type( 'payments' );
	}

	/**
	 * Check if current user can export registrations
	 *
	 * @return bool
	 */
	public static function can_export_registrations() {
		return self::can_export_type( 'registrations' );
	}

	/**
	 * Handle file export requests via URL
	 */
	public static function handle_export_request() {
		if ( ! isset( $_GET['eventflow_action'] ) || 'export' !== $_GET['eventflow_action'] ) {
			return;
		}

		// Security & Capability Check
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Unauthorized. Please log in to export event reports.', 'eventflow' ), 403 );
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'eventflow_export' ) ) {
			wp_die( esc_html__( 'Security check failed or expired. Please refresh and try again.', 'eventflow' ), 403 );
		}

		$type   = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : 'registrations';
		$format = isset( $_GET['format'] ) ? strtolower( sanitize_text_field( wp_unslash( $_GET['format'] ) ) ) : 'csv';

		if ( ! self::can_export_type( $type ) ) {
			wp_die( esc_html__( 'Access Denied: You do not have permissions to export this category of data.', 'eventflow' ), 403 );
		}

		$filters = array(
			'event_id'           => isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0,
			'activity_id'        => isset( $_GET['activity_id'] ) ? absint( $_GET['activity_id'] ) : 0,
			'payment_status'     => isset( $_GET['payment_status'] ) ? sanitize_text_field( wp_unslash( $_GET['payment_status'] ) ) : '',
			'application_status' => isset( $_GET['application_status'] ) ? sanitize_text_field( wp_unslash( $_GET['application_status'] ) ) : ( isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '' ),
			'university'         => isset( $_GET['university'] ) ? sanitize_text_field( wp_unslash( $_GET['university'] ) ) : '',
			'department'         => isset( $_GET['department'] ) ? sanitize_text_field( wp_unslash( $_GET['department'] ) ) : '',
			'semester'           => isset( $_GET['semester'] ) ? sanitize_text_field( wp_unslash( $_GET['semester'] ) ) : '',
			'date_from'          => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'            => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'search'             => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
		);

		self::dispatch_export( $type, $format, $filters );
		exit;
	}

	/**
	 * Dispatch export according to category and format
	 *
	 * @param string $type
	 * @param string $format
	 * @param array  $filters
	 */
	public static function dispatch_export( $type, $format = 'csv', $filters = array(), $exit = true ) {
		global $wpdb;

		$table_r = EventFlow_DB::table( 'registrations' );
		$table_t = EventFlow_DB::table( 'teams' );
		$table_m = EventFlow_DB::table( 'team_members' );
		$table_p = EventFlow_DB::table( 'payments' );
		$table_a = EventFlow_DB::table( 'activities' );
		$table_e = EventFlow_DB::table( 'events' );

		// Base WHERE clause
		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $filters['event_id'] ) ) {
			$where[]  = 'r.event_id = %d';
			$values[] = $filters['event_id'];
		}
		if ( ! empty( $filters['activity_id'] ) ) {
			$where[]  = 'r.activity_id = %d';
			$values[] = $filters['activity_id'];
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'DATE(r.created_at) >= %s';
			$values[] = $filters['date_from'];
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'DATE(r.created_at) <= %s';
			$values[] = $filters['date_to'];
		}

		$event = EventFlow_Event_Manager::get_active_event();
		$currency = $event ? $event->currency : 'PKR';
		$now_date = date_i18n( 'Y-m-d' );

		// Switch on Category
		switch ( $type ) {
			case 'solo':
			case 'individual':
				$title     = __( 'Solo Participant Registration Report', 'eventflow' );
				$filename  = 'eventflow-solo-participants-' . $now_date;
				$where[]   = "r.participation_type = 'solo'";
				$where_str = implode( ' AND ', $where );

				$sql = "SELECT r.reg_id, a.title as activity_title, m.full_name, m.father_name, 
							   m.email, m.contact_number, m.university, m.department, m.semester, 
							   r.total_fee, r.payment_status, r.application_status, r.created_at 
						FROM {$table_r} r 
						LEFT JOIN {$table_a} a ON r.activity_id = a.id 
						LEFT JOIN {$table_m} m ON r.id = m.registration_id 
						WHERE {$where_str} 
						ORDER BY r.id DESC";
				$rows = ! empty( $values ) ? $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

				$headers = array(
					'Registration ID', 'Competition Track', 'Full Name', "Father's Name",
					'Email Address', 'Contact / WhatsApp', 'University', 'Department',
					'Semester', 'Registration Fee (' . $currency . ')', 'Payment Status', 'Review Status', 'Applied Date'
				);
				break;

			case 'teams':
			case 'complete_teams':
				$title     = __( 'Complete Team Registration Report', 'eventflow' );
				$filename  = 'eventflow-teams-report-' . $now_date;
				$where[]   = "r.participation_type = 'team'";
				if ( ! empty( $filters['payment_status'] ) && 'all' !== $filters['payment_status'] ) {
					$where[]  = 'r.payment_status = %s';
					$values[] = $filters['payment_status'];
				}
				$where_str = implode( ' AND ', $where );

				$sql = "SELECT r.reg_id, t.team_name, a.title as activity_title, t.leader_name, 
							   t.leader_email, t.leader_phone, 
							   (SELECT COUNT(*) FROM {$table_m} WHERE team_id = t.id) as member_count, 
							   (SELECT university FROM {$table_m} WHERE team_id = t.id AND is_leader = 1 LIMIT 1) as university, 
							   r.total_fee, r.payment_status, r.application_status, r.created_at 
						FROM {$table_t} t 
						INNER JOIN {$table_r} r ON t.registration_id = r.id 
						LEFT JOIN {$table_a} a ON r.activity_id = a.id 
						WHERE {$where_str} 
						ORDER BY r.id DESC";
				$rows = ! empty( $values ) ? $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

				$headers = array(
					'Registration ID', 'Team Name', 'Competition Track', 'Team Leader',
					'Leader Email', 'Leader Phone', 'Total Members', 'University',
					'Total Fee (' . $currency . ')', 'Payment Status', 'Team Approval Status', 'Registration Date'
				);
				break;

			case 'team_leaders':
				$title     = __( 'Team Leaders Directory Report', 'eventflow' );
				$filename  = 'eventflow-team-leaders-' . $now_date;
				$where[]   = "r.participation_type = 'team'";
				$where_str = implode( ' AND ', $where );

				$sql = "SELECT r.reg_id, t.team_name, t.leader_name, t.leader_email, t.leader_phone, 
							   a.title as activity_title, 
							   (SELECT university FROM {$table_m} WHERE team_id = t.id AND is_leader = 1 LIMIT 1) as university, 
							   (SELECT department FROM {$table_m} WHERE team_id = t.id AND is_leader = 1 LIMIT 1) as department, 
							   r.payment_status, r.application_status, r.created_at 
						FROM {$table_t} t 
						INNER JOIN {$table_r} r ON t.registration_id = r.id 
						LEFT JOIN {$table_a} a ON r.activity_id = a.id 
						WHERE {$where_str} 
						ORDER BY r.id DESC";
				$rows = ! empty( $values ) ? $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

				$headers = array(
					'Registration ID', 'Team Name', 'Leader Full Name', 'Leader Email',
					'Leader Contact', 'Competition Track', 'University', 'Department',
					'Payment Status', 'Approval Status', 'Registration Date'
				);
				break;

			case 'team_members':
				$title     = __( 'Team Member Details Report', 'eventflow' );
				$filename  = 'eventflow-team-members-' . $now_date;
				$where[]   = "r.participation_type = 'team'";
				$where_str = implode( ' AND ', $where );

				$sql = "SELECT r.reg_id, t.team_name, m.full_name, m.father_name, m.semester, 
							   m.department, m.university, m.email, m.contact_number, 
							   IF(m.is_leader = 1, 'Leader', 'Member') as role_label, 
							   a.title as activity_title, r.payment_status, r.application_status 
						FROM {$table_m} m 
						INNER JOIN {$table_t} t ON m.team_id = t.id 
						INNER JOIN {$table_r} r ON m.registration_id = r.id 
						LEFT JOIN {$table_a} a ON r.activity_id = a.id 
						WHERE {$where_str} 
						ORDER BY r.id DESC, m.is_leader DESC, m.id ASC";
				$rows = ! empty( $values ) ? $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

				$headers = array(
					'Registration ID', 'Team Name', 'Member Full Name', "Father's Name",
					'Semester', 'Department', 'University', 'Email Address', 'Contact Number',
					'Roster Role', 'Competition Track', 'Payment Status', 'Application Status'
				);
				break;

			case 'payments':
			case 'payment_records':
				$title     = __( 'Payment Verification & Financial Report', 'eventflow' );
				$filename  = 'eventflow-all-payments-' . $now_date;
				if ( ! empty( $filters['payment_status'] ) && 'all' !== $filters['payment_status'] ) {
					$where[]  = 'p.status = %s';
					$values[] = $filters['payment_status'];
				}
				$where_str = implode( ' AND ', $where );

				$sql = "SELECT p.id as payment_id, r.reg_id, a.title as activity_title, 
							   COALESCE(t.team_name, t.leader_name, 'Candidate') as candidate_or_team, 
							   p.payment_method, p.transaction_id, p.amount, r.total_fee as required_fee, 
							   p.payment_date, p.status as payment_status, u.display_name as verified_by_staff, 
							   p.verified_at, p.notes 
						FROM {$table_p} p 
						INNER JOIN {$table_r} r ON p.registration_id = r.id 
						LEFT JOIN {$table_a} a ON r.activity_id = a.id 
						LEFT JOIN {$table_t} t ON r.id = t.registration_id 
						LEFT JOIN {$wpdb->users} u ON p.verified_by = u.ID 
						WHERE {$where_str} 
						ORDER BY p.id DESC";
				$rows = ! empty( $values ) ? $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

				$headers = array(
					'Payment ID', 'Registration ID', 'Competition Track', 'Team / Candidate',
					'Payment Method', 'Transaction ID', 'Submitted Amount (' . $currency . ')', 'Required Fee (' . $currency . ')',
					'Submission Date', 'Verification Status', 'Verified By Staff', 'Verification Timestamp', 'Staff Notes'
				);
				break;

			case 'verified_payments':
				$title     = __( 'Verified Payments Report', 'eventflow' );
				$filename  = 'eventflow-verified-payments-' . $now_date;
				$where[]   = "p.status = 'verified'";
				$where_str = implode( ' AND ', $where );

				$sql = "SELECT p.id as payment_id, r.reg_id, a.title as activity_title, 
							   COALESCE(t.team_name, t.leader_name, 'Candidate') as candidate_or_team, 
							   p.payment_method, p.transaction_id, p.amount, p.payment_date, 
							   u.display_name as verified_by, p.verified_at, p.notes 
						FROM {$table_p} p 
						INNER JOIN {$table_r} r ON p.registration_id = r.id 
						LEFT JOIN {$table_a} a ON r.activity_id = a.id 
						LEFT JOIN {$table_t} t ON r.id = t.registration_id 
						LEFT JOIN {$wpdb->users} u ON p.verified_by = u.ID 
						WHERE {$where_str} 
						ORDER BY p.id DESC";
				$rows = ! empty( $values ) ? $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

				$headers = array(
					'Payment ID', 'Registration ID', 'Competition Track', 'Candidate / Team Name',
					'Payment Method', 'Transaction Reference ID', 'Confirmed Amount (' . $currency . ')',
					'Payment Date', 'Verified By Staff', 'Confirmed Timestamp', 'Verification Notes'
				);
				break;

			case 'pending_payments':
				$title     = __( 'Pending Payment & Verification Queue Report', 'eventflow' );
				$filename  = 'eventflow-pending-payments-' . $now_date;
				$where[]   = "(p.status = 'under_verification' OR p.status = 'pending' OR r.payment_status = 'pending')";
				$where_str = implode( ' AND ', $where );

				$sql = "SELECT r.reg_id, a.title as activity_title, 
							   COALESCE(t.team_name, t.leader_name, 'Candidate') as candidate_or_team, 
							   COALESCE(t.leader_phone, '') as contact_number, 
							   COALESCE(p.payment_method, 'Awaiting Submission') as payment_method, 
							   COALESCE(p.transaction_id, 'Pending') as transaction_id, 
							   r.total_fee as fee_due, r.payment_status, r.created_at 
						FROM {$table_r} r 
						LEFT JOIN {$table_p} p ON r.id = p.registration_id 
						LEFT JOIN {$table_a} a ON r.activity_id = a.id 
						LEFT JOIN {$table_t} t ON r.id = t.registration_id 
						WHERE {$where_str} 
						ORDER BY r.id DESC";
				$rows = ! empty( $values ) ? $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

				$headers = array(
					'Registration ID', 'Competition Track', 'Candidate / Team', 'Contact Number',
					'Payment Method', 'Transaction ID', 'Fee Due (' . $currency . ')', 'Payment Status', 'Application Date'
				);
				break;

			case 'rejected_payments':
				$title     = __( 'Rejected Payments Report', 'eventflow' );
				$filename  = 'eventflow-rejected-payments-' . $now_date;
				$where[]   = "p.status = 'rejected'";
				$where_str = implode( ' AND ', $where );

				$sql = "SELECT p.id as payment_id, r.reg_id, a.title as activity_title, 
							   COALESCE(t.team_name, t.leader_name, 'Candidate') as candidate_or_team, 
							   t.leader_email, p.transaction_id, p.amount, p.rejection_reason, 
							   u.display_name as rejected_by_staff, p.updated_at 
						FROM {$table_p} p 
						INNER JOIN {$table_r} r ON p.registration_id = r.id 
						LEFT JOIN {$table_a} a ON r.activity_id = a.id 
						LEFT JOIN {$table_t} t ON r.id = t.registration_id 
						LEFT JOIN {$wpdb->users} u ON p.verified_by = u.ID 
						WHERE {$where_str} 
						ORDER BY p.id DESC";
				$rows = ! empty( $values ) ? $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

				$headers = array(
					'Payment ID', 'Registration ID', 'Track', 'Candidate / Team',
					'Leader Email', 'Transaction ID', 'Submitted Amount (' . $currency . ')',
					'Official Rejection Reason', 'Rejected By Staff', 'Rejection Timestamp'
				);
				break;

			case 'reupload_payments':
				$title     = __( 'Payment Re-upload Requests Report', 'eventflow' );
				$filename  = 'eventflow-reupload-requests-' . $now_date;
				$where[]   = "p.status = 'reupload_requested'";
				$where_str = implode( ' AND ', $where );

				$sql = "SELECT p.id as payment_id, r.reg_id, a.title as activity_title, 
							   COALESCE(t.team_name, t.leader_name, 'Candidate') as candidate_or_team, 
							   t.leader_email, t.leader_phone, p.transaction_id, p.notes as staff_instructions, 
							   u.display_name as requested_by_staff, p.updated_at 
						FROM {$table_p} p 
						INNER JOIN {$table_r} r ON p.registration_id = r.id 
						LEFT JOIN {$table_a} a ON r.activity_id = a.id 
						LEFT JOIN {$table_t} t ON r.id = t.registration_id 
						LEFT JOIN {$wpdb->users} u ON p.verified_by = u.ID 
						WHERE {$where_str} 
						ORDER BY p.id DESC";
				$rows = ! empty( $values ) ? $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

				$headers = array(
					'Payment ID', 'Registration ID', 'Competition Track', 'Candidate / Team',
					'Leader Email', 'Leader Phone', 'Transaction ID', 'Staff Re-upload Instructions',
					'Requested By Staff', 'Request Timestamp'
				);
				break;

			case 'university_registrations':
			case 'university_wise':
				$title     = __( 'University-wise Registration & Representation Report', 'eventflow' );
				$filename  = 'eventflow-university-registrations-' . $now_date;
				$where_str = implode( ' AND ', $where );

				$sql = "SELECT COALESCE(NULLIF(m.university, ''), 'Unspecified Institution') as university_name, 
							   COUNT(DISTINCT r.id) as total_registrations, 
							   COUNT(DISTINCT m.id) as total_participants, 
							   SUM(CASE WHEN r.participation_type = 'team' THEN 1 ELSE 0 END) as team_entries, 
							   SUM(CASE WHEN r.participation_type = 'solo' THEN 1 ELSE 0 END) as solo_entries, 
							   SUM(CASE WHEN r.payment_status = 'verified' THEN 1 ELSE 0 END) as verified_teams, 
							   SUM(r.total_fee) as expected_revenue 
						FROM {$table_m} m 
						INNER JOIN {$table_r} r ON m.registration_id = r.id 
						WHERE {$where_str} 
						GROUP BY university_name 
						ORDER BY total_participants DESC";
				$rows = ! empty( $values ) ? $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

				$headers = array(
					'University / Institution Name', 'Total Applications', 'Total Participants',
					'Team Entries', 'Solo Entries', 'Verified Applications', 'Total Expected Revenue (' . $currency . ')'
				);
				break;

			case 'competition_registrations':
			case 'competition_wise':
				$title     = __( 'Competition-wise Registration Analytics Report', 'eventflow' );
				$filename  = 'eventflow-competition-registrations-' . $now_date;
				$where_str = implode( ' AND ', $where );

				$sql = "SELECT a.title as competition_track, a.participation_type as track_mode, 
							   a.reg_fee as track_fee, 
							   COUNT(r.id) as total_applications, 
							   SUM(CASE WHEN r.participation_type = 'team' THEN 1 ELSE 0 END) as teams_registered, 
							   SUM(CASE WHEN r.participation_type = 'solo' THEN 1 ELSE 0 END) as solo_registered, 
							   SUM(CASE WHEN r.payment_status = 'verified' THEN 1 ELSE 0 END) as verified_payments, 
							   SUM(CASE WHEN r.payment_status = 'under_verification' THEN 1 ELSE 0 END) as pending_verification, 
							   SUM(r.total_fee) as expected_revenue 
						FROM {$table_a} a 
						LEFT JOIN {$table_r} r ON a.id = r.activity_id 
						GROUP BY a.id 
						ORDER BY total_applications DESC";
				$rows = $wpdb->get_results( $sql, ARRAY_A );

				$headers = array(
					'Competition Track', 'Allowed Participation Mode', 'Base Fee (' . $currency . ')',
					'Total Registrations', 'Teams Count', 'Solo Candidates',
					'Verified Payments', 'Under Verification', 'Total Revenue (' . $currency . ')'
				);
				break;

			case 'audit_logs':
			case 'staff_activity':
				$title     = __( 'Staff Operational Activity & Security Audit Report', 'eventflow' );
				$filename  = 'eventflow-staff-activity-' . $now_date;
				$table_aud = EventFlow_DB::table( 'audit_logs' );

				$sql = "SELECT a.id as log_id, a.action, a.object_type, a.object_id, 
							   COALESCE(u.display_name, 'System / Guest') as performed_by, 
							   a.ip_address, a.details, a.created_at as timestamp 
						FROM {$table_aud} a 
						LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID 
						ORDER BY a.id DESC LIMIT 1000";
				$rows = $wpdb->get_results( $sql, ARRAY_A );

				$headers = array(
					'Log ID', 'Action Performed', 'Target Object', 'Object ID',
					'Staff Member / User', 'IP Address', 'Audit Details', 'Timestamp'
				);
				break;

			case 'participants':
			case 'all_participants':
			default:
				$title     = __( 'Complete Participant Registration Report', 'eventflow' );
				$filename  = 'eventflow-all-participants-' . $now_date;
				if ( ! empty( $filters['payment_status'] ) && 'all' !== $filters['payment_status'] ) {
					$where[]  = 'r.payment_status = %s';
					$values[] = $filters['payment_status'];
				}
				$where_str = implode( ' AND ', $where );

				$sql = "SELECT r.reg_id, a.title as activity_title, 
							   COALESCE(t.team_name, 'Solo Candidate') as team_name, 
							   m.full_name, m.father_name, m.semester, m.department, m.university, 
							   m.email, m.contact_number, 
							   IF(r.participation_type = 'solo', 'Solo Candidate', IF(m.is_leader = 1, 'Team Leader', 'Member')) as role_label, 
							   r.payment_status, r.application_status, r.created_at 
						FROM {$table_m} m 
						INNER JOIN {$table_r} r ON m.registration_id = r.id 
						LEFT JOIN {$table_t} t ON r.id = t.registration_id 
						LEFT JOIN {$table_a} a ON r.activity_id = a.id 
						WHERE {$where_str} 
						ORDER BY r.id DESC, m.is_leader DESC, m.id ASC";
				$rows = ! empty( $values ) ? $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

				$headers = array(
					'Registration ID', 'Competition Track', 'Team Name', 'Full Name',
					"Father's Name", 'Semester', 'Department', 'University',
					'Email Address', 'Contact Number', 'Participant Role',
					'Payment Status', 'Application Status', 'Registration Date'
				);
				break;
		}

		EventFlow_Security::log_audit(
			'data_exported',
			'export',
			count( $rows ),
			array(
				'category' => $type,
				'format'   => $format,
				'count'    => count( $rows ),
			)
		);

		if ( 'pdf' === $format || 'html' === $format ) {
			self::render_event_branded_pdf_report( $title, $filename, $headers, $rows, $filters );
			if ( $exit ) {
				exit;
			}
		} elseif ( 'excel' === $format || 'xlsx' === $format ) {
			self::render_excel_report( $title, $filename, $headers, $rows );
			if ( $exit ) {
				exit;
			}
		} else {
			self::render_csv_report( $filename, $headers, $rows );
			if ( $exit ) {
				exit;
			}
		}
	}

	/**
	 * Render Event-Branded Premium Printable PDF Report Template
	 *
	 * @param string $title
	 * @param string $filename
	 * @param array  $headers
	 * @param array  $rows
	 * @param array  $filters
	 */
	public static function render_event_branded_pdf_report( $title, $filename, $headers, $rows, $filters = array() ) {
		$active_event = EventFlow_Event_Manager::get_active_event();
		$event_name   = $active_event ? $active_event->title : 'SIBACOM EVENT';
		$org_title    = 'Sukkur IBA University • Department of Computer Science & IEEE / SISC Computing Society';
		$gen_date     = date_i18n( 'F j, Y - H:i:s' );
		$staff_user   = wp_get_current_user();
		$staff_name   = $staff_user && $staff_user->ID ? $staff_user->display_name . ' (' . EventFlow_Roles::get_role_label( $staff_user->ID ) . ')' : 'Event Administrator';
		$is_landscape = count( $headers ) > 7;
		$page_size    = $is_landscape ? 'A4 landscape' : 'A4 portrait';

		// Resolve official branding logos
		$logo_plugin_dir = EVENTFLOW_PLUGIN_DIR . 'assets/images/branding/';
		$iba_logo_url    = EVENTFLOW_PLUGIN_URL . 'assets/images/branding/sukkur_iba_logo.png';
		$cse_logo_url    = EVENTFLOW_PLUGIN_URL . 'assets/images/branding/cse_dept_logo.jpg';
		$sisc_logo_url   = EVENTFLOW_PLUGIN_URL . 'assets/images/branding/cse_society_logo.png';
		if ( $active_event && ! empty( $active_event->logo_url ) ) {
			$event_logo_url = $active_event->logo_url;
		} else {
			$event_logo_url = $iba_logo_url;
		}

		?>
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo esc_html( $title . ' - ' . $event_name ); ?></title>
			<?php if ( class_exists( 'EventFlow_Favicon' ) ) { EventFlow_Favicon::render_meta_tags(); } ?>
			<style>
				@page {
					size: <?php echo esc_html( $page_size ); ?>;
					margin: <?php echo $is_landscape ? '12mm 10mm 15mm 10mm' : '15mm 12mm 15mm 12mm'; ?>;
				}
				* { box-sizing: border-box; }
				body {
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
					color: #0F172A;
					background: #FFFFFF;
					margin: 0;
					padding: 0;
					font-size: <?php echo $is_landscape ? '11px' : '12px'; ?>;
					line-height: 1.4;
				}
				/* Screen-only Print Bar */
				.ef-print-bar {
					background: #0B0F19;
					color: #FFFFFF;
					padding: 12px 24px;
					display: flex;
					align-items: center;
					justify-content: space-between;
					box-shadow: 0 4px 12px rgba(0,0,0,0.15);
					position: sticky;
					top: 0;
					z-index: 9999;
				}
				.ef-print-bar-title {
					font-weight: 700;
					font-size: 14px;
					display: flex;
					align-items: center;
					gap: 10px;
				}
				.ef-print-actions {
					display: flex;
					gap: 10px;
				}
				.ef-btn-print {
					background: #0D9488;
					color: #FFFFFF;
					border: none;
					border-radius: 6px;
					padding: 8px 18px;
					font-weight: 600;
					font-size: 13px;
					cursor: pointer;
					display: inline-flex;
					align-items: center;
					gap: 6px;
					text-decoration: none;
					transition: background 0.15s ease;
				}
				.ef-btn-print:hover { background: #0F766E; }
				.ef-btn-secondary {
					background: #334155;
					color: #FFFFFF;
					border: none;
					border-radius: 6px;
					padding: 8px 14px;
					font-weight: 500;
					font-size: 12px;
					cursor: pointer;
					text-decoration: none;
				}
				.ef-btn-secondary:hover { background: #475569; }

				/* Document Container */
				.ef-doc-wrap {
					max-width: <?php echo $is_landscape ? '1280px' : '960px'; ?>;
					margin: 0 auto;
					padding: 24px 20px;
				}

				/* Official Header */
				.ef-doc-header {
					border-bottom: 2px solid #0D9488;
					padding-bottom: 16px;
					margin-bottom: 18px;
				}
				.ef-header-top {
					display: flex;
					align-items: center;
					justify-content: space-between;
					gap: 20px;
				}
				.ef-header-logos {
					display: flex;
					align-items: center;
					gap: 16px;
				}
				.ef-logo-iba {
					height: 52px;
					width: auto;
					object-fit: contain;
				}
				.ef-logo-cse {
					height: 48px;
					width: auto;
					border-radius: 4px;
				}
				.ef-header-titles {
					text-align: right;
				}
				.ef-event-title {
					font-size: 18px;
					font-weight: 800;
					color: #0F766E;
					margin: 0 0 2px;
					letter-spacing: -0.3px;
					text-transform: uppercase;
				}
				.ef-org-subtitle {
					font-size: 11px;
					color: #475569;
					margin: 0;
					font-weight: 500;
				}

				/* Report Banner */
				.ef-report-banner {
					display: flex;
					align-items: center;
					justify-content: space-between;
					background: #F0FDFA;
					border: 1px solid #CCFBF1;
					border-left: 4px solid #0D9488;
					border-radius: 6px;
					padding: 12px 16px;
					margin-bottom: 18px;
				}
				.ef-report-title {
					font-size: 16px;
					font-weight: 800;
					color: #115E59;
					margin: 0 0 4px;
				}
				.ef-report-meta {
					font-size: 10.5px;
					color: #64748B;
					margin: 0;
				}
				.ef-kpi-pill-wrap {
					display: flex;
					gap: 12px;
				}
				.ef-kpi-pill {
					background: #FFFFFF;
					border: 1px solid #E2E8F0;
					border-radius: 6px;
					padding: 6px 14px;
					text-align: center;
				}
				.ef-kpi-pill-val {
					font-size: 16px;
					font-weight: 800;
					color: #0F766E;
					line-height: 1;
				}
				.ef-kpi-pill-lbl {
					font-size: 9px;
					font-weight: 600;
					color: #64748B;
					text-transform: uppercase;
					letter-spacing: 0.5px;
					margin-top: 3px;
				}

				/* Data Table */
				table.ef-report-table {
					width: 100%;
					border-collapse: collapse;
					margin-top: 10px;
					font-size: <?php echo $is_landscape ? '10px' : '11px'; ?>;
				}
				table.ef-report-table thead {
					display: table-header-group; /* Repeating header across pages */
				}
				table.ef-report-table th {
					background-color: #134E4A;
					color: #FFFFFF;
					text-align: left;
					padding: 7px 8px;
					font-weight: 700;
					text-transform: uppercase;
					letter-spacing: 0.3px;
					font-size: <?php echo $is_landscape ? '9px' : '10px'; ?>;
					border: 1px solid #0F766E;
				}
				table.ef-report-table td {
					padding: 6px 8px;
					border: 1px solid #E2E8F0;
					vertical-align: top;
					word-break: break-word;
				}
				table.ef-report-table tbody tr:nth-child(even) {
					background-color: #F8FAFC;
				}
				table.ef-report-table tbody tr {
					page-break-inside: avoid;
				}
				.ef-monospace {
					font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
					font-weight: 700;
					color: #0F766E;
				}
				.ef-status-badge {
					display: inline-block;
					padding: 2px 6px;
					border-radius: 4px;
					font-size: 9px;
					font-weight: 700;
					text-transform: uppercase;
					letter-spacing: 0.3px;
				}
				.ef-badge-verified, .ef-badge-approved { background: #DCFCE7; color: #166534; }
				.ef-badge-under_verification, .ef-badge-pending { background: #FEF3C7; color: #92400E; }
				.ef-badge-rejected { background: #FEE2E2; color: #991B1B; }
				.ef-badge-reupload_requested { background: #FFEDD5; color: #9A3412; }

				/* Official Footer */
				.ef-doc-footer {
					margin-top: 24px;
					border-top: 1px solid #CBD5E1;
					padding-top: 12px;
					display: flex;
					align-items: center;
					justify-content: space-between;
					font-size: 9.5px;
					color: #64748B;
				}

				/* Media Print Specific Rules */
				@media print {
					.ef-print-bar { display: none !important; }
					.ef-doc-wrap { max-width: 100% !important; padding: 0 !important; }
					body { font-size: 10px; }
					table.ef-report-table th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
					.ef-status-badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
					.ef-report-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
				}
			</style>
		</head>
		<body>
			<!-- Interactive Action Toolbar -->
			<div class="ef-print-bar">
				<div class="ef-print-bar-title">
					<span>📄 <?php echo esc_html( $title ); ?></span>
					<span style="font-weight: 400; opacity: 0.8; font-size: 12px;">(<?php echo count( $rows ); ?> <?php esc_html_e( 'Records', 'eventflow' ); ?>)</span>
				</div>
				<div class="ef-print-actions">
					<button type="button" class="ef-btn-print" onclick="window.print()">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
						<?php esc_html_e( 'Print / Save as Official PDF', 'eventflow' ); ?>
					</button>
					<a href="<?php echo esc_url( add_query_arg( 'format', 'csv' ) ); ?>" class="ef-btn-secondary">CSV</a>
					<a href="<?php echo esc_url( add_query_arg( 'format', 'excel' ) ); ?>" class="ef-btn-secondary">Excel</a>
					<button type="button" class="ef-btn-secondary" onclick="window.close()"><?php esc_html_e( 'Close', 'eventflow' ); ?></button>
				</div>
			</div>

			<div class="ef-doc-wrap">
				<!-- Header with Official Society Logos -->
				<div class="ef-doc-header">
					<div class="ef-header-top">
						<div class="ef-header-logos">
							<?php if ( file_exists( $logo_plugin_dir . 'sukkur_iba_logo.png' ) ) : ?>
								<img src="<?php echo esc_url( $iba_logo_url ); ?>" alt="Sukkur IBA University" class="ef-logo-iba">
							<?php endif; ?>
							<?php if ( file_exists( $logo_plugin_dir . 'cse_dept_logo.jpg' ) ) : ?>
								<img src="<?php echo esc_url( $cse_logo_url ); ?>" alt="Department of Computer Science" class="ef-logo-cse">
							<?php endif; ?>
							<?php if ( file_exists( $logo_plugin_dir . 'cse_society_logo.png' ) ) : ?>
								<img src="<?php echo esc_url( $sisc_logo_url ); ?>" alt="SISC Society" class="ef-logo-cse">
							<?php endif; ?>
						</div>

						<div class="ef-header-titles">
							<h1 class="ef-event-title"><?php echo esc_html( $event_name ); ?></h1>
							<p class="ef-org-subtitle"><?php echo esc_html( $org_title ); ?></p>
						</div>
					</div>
				</div>

				<!-- Report Summary Banner -->
				<div class="ef-report-banner">
					<div>
						<h2 class="ef-report-title"><?php echo esc_html( $title ); ?></h2>
						<p class="ef-report-meta">
							<?php printf( esc_html__( 'Generated on %s • Generated by: %s', 'eventflow' ), esc_html( $gen_date ), esc_html( $staff_name ) ); ?>
						</p>
					</div>

					<div class="ef-kpi-pill-wrap">
						<div class="ef-kpi-pill">
							<div class="ef-kpi-pill-val"><?php echo count( $rows ); ?></div>
							<div class="ef-kpi-pill-lbl"><?php esc_html_e( 'Total Entries', 'eventflow' ); ?></div>
						</div>
					</div>
				</div>

				<!-- Official Data Table -->
				<table class="ef-report-table">
					<thead>
						<tr>
							<th style="width: 30px;">#</th>
							<?php foreach ( $headers as $h ) : ?>
								<th><?php echo esc_html( $h ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $rows ) ) : ?>
							<tr>
								<td colspan="<?php echo count( $headers ) + 1; ?>" style="text-align:center; padding: 30px; color:#64748B;">
									<?php esc_html_e( 'No records match the requested criteria.', 'eventflow' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $rows as $idx => $r ) : ?>
								<tr>
									<td style="color:#94A3B8; font-weight:600; text-align:center;"><?php echo $idx + 1; ?></td>
									<?php foreach ( (array) $r as $key => $cell ) : ?>
										<td>
											<?php
											$cell_val = (string) $cell;
											if ( in_array( $cell_val, array( 'verified', 'approved', 'under_verification', 'pending', 'rejected', 'reupload_requested' ), true ) ) {
												$cls = 'ef-badge-' . sanitize_html_class( $cell_val );
												echo '<span class="ef-status-badge ' . esc_attr( $cls ) . '">' . esc_html( strtoupper( str_replace( '_', ' ', $cell_val ) ) ) . '</span>';
											} elseif ( 0 === strpos( $cell_val, 'EF-' ) ) {
												echo '<span class="ef-monospace">' . esc_html( $cell_val ) . '</span>';
											} else {
												echo esc_html( $cell_val );
											}
											?>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<!-- Official Event Footer -->
				<div class="ef-doc-footer">
					<div>
						<strong>SIBACOM EVENT</strong> &bull; <?php esc_html_e( 'Sukkur IBA University Official Administrative Report — Confidential', 'eventflow' ); ?>
					</div>
					<div>
						<?php printf( esc_html__( 'Record Count: %d &bull; EventFlow Management Engine', 'eventflow' ), count( $rows ) ); ?>
					</div>
				</div>
			</div>
		</body>
		</html>
		<?php
	}

	/**
	 * Render Styled Excel XML Spreadsheet Report (.xlsx/.xls)
	 *
	 * @param string $title
	 * @param string $filename
	 * @param array  $headers
	 * @param array  $rows
	 */
	public static function render_excel_report( $title, $filename, $headers, $rows ) {
		if ( ! headers_sent() ) {
			header( 'Content-Type: application/vnd.ms-excel; charset=UTF-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '.xls"' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
		}

		echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
		echo '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
		echo '<style>';
		echo 'th { background-color: #0D9488; color: #FFFFFF; font-weight: bold; border: 1px solid #0F766E; padding: 6px; }';
		echo 'td { border: 1px solid #CBD5E1; padding: 5px; }';
		echo 'tr.even { background-color: #F8FAFC; }';
		echo '</style></head><body>';
		echo '<h2>' . esc_html( $title ) . '</h2>';
		echo '<p>Generated on ' . esc_html( date_i18n( 'Y-m-d H:i:s' ) ) . ' | SIBACOM EVENT - Sukkur IBA University</p>';
		echo '<table border="1">';
		echo '<thead><tr>';
		foreach ( $headers as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $idx => $row ) {
			$cls = ( $idx % 2 === 0 ) ? 'even' : 'odd';
			echo '<tr class="' . $cls . '">';
			foreach ( (array) $row as $val ) {
				echo '<td>' . esc_html( (string) $val ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></body></html>';
	}

	/**
	 * Render CSV Report with UTF-8 BOM
	 *
	 * @param string $filename
	 * @param array  $headers
	 * @param array  $rows
	 */
	public static function render_csv_report( $filename, $headers, $rows ) {
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/csv; charset=UTF-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '.csv"' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
		}

		$output = fopen( 'php://temp', 'r+' );
		// Output UTF-8 BOM
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		fputcsv( $output, $headers );

		foreach ( $rows as $row ) {
			fputcsv( $output, array_values( (array) $row ) );
		}

		rewind( $output );
		echo stream_get_contents( $output );
		fclose( $output );
	}
}
