<?php
/**
 * EventFlow Real-Time Analytics & Statistics Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Analytics_Manager {

	/**
	 * Get live dashboard key performance indicators (KPIs)
	 *
	 * @param int $event_id
	 * @return array
	 */
	public static function get_overview_kpis( $event_id = 0 ) {
		global $wpdb;

		$event_id = absint( $event_id );

		$table_r = EventFlow_DB::table( 'registrations' );
		$table_m = EventFlow_DB::table( 'team_members' );
		$table_p = EventFlow_DB::table( 'payments' );
		$table_a = EventFlow_DB::table( 'activities' );

		$where_event = $event_id ? $wpdb->prepare( " WHERE event_id = %d", $event_id ) : "";

		// 1. Total Registrations
		$total_registrations = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_r} {$where_event}" );

		// 2. Solo Registrations & 3. Team Registrations
		if ( $event_id ) {
			$solo_registrations = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_r} WHERE event_id = %d AND participation_type = 'solo'", $event_id ) );
			$team_registrations = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_r} WHERE event_id = %d AND participation_type = 'team'", $event_id ) );
		} else {
			$solo_registrations = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_r} WHERE participation_type = 'solo'" );
			$team_registrations = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_r} WHERE participation_type = 'team'" );
		}

		// 4. Total Participants (all team members + solo participants)
		if ( $event_id ) {
			$total_participants = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(m.id) FROM {$table_m} m 
					 INNER JOIN {$table_r} r ON m.registration_id = r.id 
					 WHERE r.event_id = %d",
					$event_id
				)
			);
		} else {
			$total_participants = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_m}" );
		}
		if ( $total_participants <= 0 ) {
			$total_participants = $total_registrations;
		}

		// 5. Payments Submitted & Verification States
		if ( $event_id ) {
			$total_payments_submitted = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(p.id) FROM {$table_p} p 
					 INNER JOIN {$table_r} r ON p.registration_id = r.id 
					 WHERE r.event_id = %d",
					$event_id
				)
			);
			$under_verification_payments = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(p.id) FROM {$table_p} p 
					 INNER JOIN {$table_r} r ON p.registration_id = r.id 
					 WHERE r.event_id = %d AND p.status = 'under_verification'",
					$event_id
				)
			);
			$verified_payments = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(p.id) FROM {$table_p} p 
					 INNER JOIN {$table_r} r ON p.registration_id = r.id 
					 WHERE r.event_id = %d AND p.status = 'verified'",
					$event_id
				)
			);
			$rejected_payments = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(p.id) FROM {$table_p} p 
					 INNER JOIN {$table_r} r ON p.registration_id = r.id 
					 WHERE r.event_id = %d AND p.status = 'rejected'",
					$event_id
				)
			);
			$reupload_requested_payments = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(p.id) FROM {$table_p} p 
					 INNER JOIN {$table_r} r ON p.registration_id = r.id 
					 WHERE r.event_id = %d AND p.status = 'reupload_requested'",
					$event_id
				)
			);
			$total_verified_revenue = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(p.amount) FROM {$table_p} p 
					 INNER JOIN {$table_r} r ON p.registration_id = r.id 
					 WHERE r.event_id = %d AND p.status = 'verified'",
					$event_id
				)
			);
		} else {
			$total_payments_submitted    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_p}" );
			$under_verification_payments = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_p} WHERE status = 'under_verification'" );
			$verified_payments           = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_p} WHERE status = 'verified'" );
			$rejected_payments           = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_p} WHERE status = 'rejected'" );
			$reupload_requested_payments = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_p} WHERE status = 'reupload_requested'" );
			$total_verified_revenue      = (float) $wpdb->get_var( "SELECT SUM(amount) FROM {$table_p} WHERE status = 'verified'" );
		}

		// 6. Expected Revenue & Pending Revenue
		$total_expected_revenue = (float) $wpdb->get_var( "SELECT SUM(total_fee) FROM {$table_r} {$where_event}" );
		$pending_revenue = max( 0.0, $total_expected_revenue - $total_verified_revenue );

		// 7. Total Active Competitions
		$where_act = $event_id ? $wpdb->prepare( " WHERE event_id = %d AND status = 'published'", $event_id ) : " WHERE status = 'published'";
		$total_active_competitions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_a} {$where_act}" );

		// Legacy application statuses for backward compatibility
		if ( $event_id ) {
			$approved_apps    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_r} WHERE event_id = %d AND application_status = 'approved'", $event_id ) );
			$completed_apps   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_r} WHERE event_id = %d AND application_status = 'completed'", $event_id ) );
			$pending_payments = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_r} WHERE event_id = %d AND payment_status = 'pending'", $event_id ) );
		} else {
			$approved_apps    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_r} WHERE application_status = 'approved'" );
			$completed_apps   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_r} WHERE application_status = 'completed'" );
			$pending_payments = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_r} WHERE payment_status = 'pending'" );
		}

		return array(
			// 14 Core KPIs
			'total_registrations'         => $total_registrations,
			'total_participants'          => $total_participants,
			'total_teams'                 => $team_registrations,
			'solo_registrations'          => $solo_registrations,
			'team_registrations'          => $team_registrations,
			'total_payments_submitted'    => $total_payments_submitted,
			'under_verification_payments' => $under_verification_payments,
			'verified_payments'           => $verified_payments,
			'rejected_payments'           => $rejected_payments,
			'reupload_requested_payments' => $reupload_requested_payments,
			'total_expected_revenue'      => $total_expected_revenue,
			'total_verified_revenue'      => $total_verified_revenue,
			'pending_revenue'             => $pending_revenue,
			'total_active_competitions'   => $total_active_competitions,

			// Shorthand & Backward compatible aliases
			'total_payments'              => $total_payments_submitted,
			'under_verification'          => $under_verification_payments,
			'reupload_requested'          => $reupload_requested_payments,
			'expected_revenue'            => $total_expected_revenue,
			'verified_revenue'            => $total_verified_revenue,
			'active_competitions'         => $total_active_competitions,
			'pending_payments'            => $pending_payments + $under_verification_payments,
			'approved_applications'       => $approved_apps,
			'completed_applications'      => $completed_apps,
			'total_revenue'               => $total_verified_revenue,
			'potential_revenue'           => $total_expected_revenue,
		);
	}

	/**
	 * Get daily registrations trend (last 14 days)
	 *
	 * @param int $days
	 * @return array
	 */
	public static function get_daily_registrations( $days = 14 ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'registrations' );

		$labels = array();
		$counts = array();

		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$date = date( 'Y-m-d', strtotime( "-{$i} days", current_time( 'timestamp' ) ) );
			$labels[] = date( 'M d', strtotime( $date ) );

			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE DATE(created_at) = %s",
					$date
				)
			);
			$counts[] = $count;
		}

		return array(
			'labels' => $labels,
			'data'   => $counts,
		);
	}

	/**
	 * Get weekly registrations trend (last 8 weeks)
	 *
	 * @param int $weeks
	 * @return array
	 */
	public static function get_weekly_registrations( $weeks = 8 ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'registrations' );

		$labels = array();
		$counts = array();

		for ( $i = $weeks - 1; $i >= 0; $i-- ) {
			$start_date = date( 'Y-m-d 00:00:00', strtotime( "-{$i} weeks Monday", current_time( 'timestamp' ) ) );
			$end_date   = date( 'Y-m-d 23:59:59', strtotime( "+6 days", strtotime( $start_date ) ) );
			$labels[]   = date( 'M d', strtotime( $start_date ) );

			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND created_at <= %s",
					$start_date,
					$end_date
				)
			);
			$counts[] = $count;
		}

		return array(
			'labels' => $labels,
			'data'   => $counts,
		);
	}

	/**
	 * Get monthly registrations trend (last 6 months)
	 *
	 * @param int $months
	 * @return array
	 */
	public static function get_monthly_registrations( $months = 6 ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'registrations' );

		$labels = array();
		$counts = array();

		for ( $i = $months - 1; $i >= 0; $i-- ) {
			$month_start = date( 'Y-m-01 00:00:00', strtotime( "-{$i} months", current_time( 'timestamp' ) ) );
			$month_end   = date( 'Y-m-t 23:59:59', strtotime( $month_start ) );
			$labels[]    = date( 'M Y', strtotime( $month_start ) );

			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND created_at <= %s",
					$month_start,
					$month_end
				)
			);
			$counts[] = $count;
		}

		return array(
			'labels' => $labels,
			'data'   => $counts,
		);
	}

	/**
	 * Get payment verification status distribution
	 *
	 * @return array
	 */
	public static function get_payment_verification_distribution() {
		global $wpdb;
		$table_p = EventFlow_DB::table( 'payments' );

		$statuses = array(
			'verified'           => __( 'Verified', 'eventflow' ),
			'under_verification' => __( 'Under Verification', 'eventflow' ),
			'reupload_requested' => __( 'Re-upload Requested', 'eventflow' ),
			'rejected'           => __( 'Rejected', 'eventflow' ),
		);

		$labels = array();
		$data   = array();

		foreach ( $statuses as $status_key => $status_label ) {
			$count    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_p} WHERE status = %s", $status_key ) );
			$labels[] = $status_label;
			$data[]   = $count;
		}

		return array(
			'labels' => $labels,
			'data'   => $data,
		);
	}

	/**
	 * Get solo vs team participation breakdown
	 *
	 * @return array
	 */
	public static function get_solo_vs_team_distribution() {
		global $wpdb;
		$table_r = EventFlow_DB::table( 'registrations' );

		$solo_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_r} WHERE participation_type = 'solo'" );
		$team_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_r} WHERE participation_type = 'team'" );

		return array(
			'labels' => array( __( 'Solo Participants', 'eventflow' ), __( 'Team Entries', 'eventflow' ) ),
			'data'   => array( $solo_count, $team_count ),
		);
	}

	/**
	 * Get revenue collection overview
	 *
	 * @return array
	 */
	public static function get_revenue_collection_overview() {
		$kpis = self::get_overview_kpis();

		return array(
			'labels' => array( __( 'Verified Revenue', 'eventflow' ), __( 'Pending Revenue', 'eventflow' ) ),
			'data'   => array( (float) $kpis['total_verified_revenue'], (float) $kpis['pending_revenue'] ),
		);
	}

	/**
	 * Get payment method distribution
	 *
	 * @return array
	 */
	public static function get_payment_method_distribution() {
		global $wpdb;
		$table_p = EventFlow_DB::table( 'payments' );

		$results = $wpdb->get_results(
			"SELECT payment_method, COUNT(*) as txn_count, SUM(amount) as total_amount 
			 FROM {$table_p} 
			 WHERE payment_method != '' AND payment_method IS NOT NULL 
			 GROUP BY payment_method 
			 ORDER BY txn_count DESC"
		);

		$labels = array();
		$data   = array();

		if ( empty( $results ) ) {
			$labels = array( __( 'Bank Transfer', 'eventflow' ), __( 'JazzCash', 'eventflow' ), __( 'Easypaisa', 'eventflow' ) );
			$data   = array( 0, 0, 0 );
		} else {
			foreach ( $results as $row ) {
				$labels[] = ucfirst( str_replace( '_', ' ', isset( $row->payment_method ) ? $row->payment_method : 'other' ) );
				$data[]   = isset( $row->txn_count ) ? (int) $row->txn_count : ( isset( $row->count ) ? (int) $row->count : 1 );
			}
		}

		return array(
			'labels' => $labels,
			'data'   => $data,
		);
	}

	/**
	 * Get activity-wise registrations distribution
	 *
	 * @return array
	 */
	public static function get_activity_distribution() {
		global $wpdb;
		$table_r = EventFlow_DB::table( 'registrations' );
		$table_a = EventFlow_DB::table( 'activities' );

		$results = $wpdb->get_results(
			"SELECT a.title, COUNT(r.id) as reg_count 
			 FROM {$table_a} a 
			 LEFT JOIN {$table_r} r ON a.id = r.activity_id 
			 GROUP BY a.id, a.title 
			 ORDER BY reg_count DESC"
		);

		$labels = array();
		$data   = array();

		foreach ( $results as $row ) {
			$labels[] = isset( $row->title ) ? $row->title : 'Activity';
			$data[]   = isset( $row->reg_count ) ? (int) $row->reg_count : ( isset( $row->count ) ? (int) $row->count : 0 );
		}

		return array(
			'labels' => $labels,
			'data'   => $data,
		);
	}

	/**
	 * Get Application Status breakdown
	 *
	 * @return array
	 */
	public static function get_application_status_distribution() {
		global $wpdb;
		$table = EventFlow_DB::table( 'registrations' );

		$statuses = array( 'submitted', 'under_review', 'approved', 'rejected', 'completed' );
		$labels = array(
			'submitted'    => __( 'Submitted', 'eventflow' ),
			'under_review' => __( 'Under Review', 'eventflow' ),
			'approved'     => __( 'Approved', 'eventflow' ),
			'rejected'     => __( 'Rejected', 'eventflow' ),
			'completed'    => __( 'Completed', 'eventflow' ),
		);

		$data = array();
		$out_labels = array();

		foreach ( $statuses as $st ) {
			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE application_status = %s", $st ) );
			$data[] = $count;
			$out_labels[] = $labels[ $st ];
		}

		return array(
			'labels' => $out_labels,
			'data'   => $data,
		);
	}
}

