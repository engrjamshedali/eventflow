<?php
/**
 * EventFlow In-Dashboard Notification Manager
 * Handles participant and administrative notifications with database persistence and deduplication.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Notification_Manager {

	/**
	 * Create an in-dashboard notification
	 *
	 * @param array $args
	 * @return int|WP_Error Notification ID or error
	 */
	public static function create( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'user_id'         => 0,
			'registration_id' => 0,
			'payment_id'      => 0,
			'type'            => 'general',
			'title'           => '',
			'message'         => '',
			'action_url'      => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$user_id = absint( $args['user_id'] );
		if ( ! $user_id ) {
			return new WP_Error( 'missing_user', __( 'User ID is required for notifications.', 'eventflow' ) );
		}

		$title   = sanitize_text_field( $args['title'] );
		$message = wp_kses_post( $args['message'] );
		$type    = sanitize_text_field( $args['type'] );
		$reg_id  = absint( $args['registration_id'] );
		$pay_id  = absint( $args['payment_id'] );
		$action  = esc_url_raw( $args['action_url'] );

		if ( empty( $title ) || empty( $message ) ) {
			return new WP_Error( 'missing_content', __( 'Notification title and message are required.', 'eventflow' ) );
		}

		$table = EventFlow_DB::table( 'notifications' );

		// Duplicate prevention: avoid creating duplicate unread notification for same user, reg_id & type within 60 seconds
		if ( method_exists( $wpdb, 'get_var' ) ) {
			$recent = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} 
					 WHERE user_id = %d AND type = %s AND registration_id = %d AND is_read = 0 
					 AND created_at >= %s LIMIT 1",
					$user_id,
					$type,
					$reg_id,
					date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 60 )
				)
			);

			if ( $recent ) {
				return (int) $recent; // Duplicate suppressed
			}
		}

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'         => $user_id,
				'registration_id' => $reg_id ? $reg_id : null,
				'payment_id'      => $pay_id ? $pay_id : null,
				'type'            => $type,
				'title'           => $title,
				'message'         => $message,
				'action_url'      => $action,
				'is_read'         => 0,
				'read_at'         => null,
				'created_at'      => $now,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', __( 'Failed to save notification record.', 'eventflow' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get notifications for a user
	 *
	 * @param int   $user_id
	 * @param array $args
	 * @return array
	 */
	public static function get_user_notifications( $user_id, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'limit'       => 30,
			'unread_only' => false,
		);
		$args = wp_parse_args( $args, $defaults );

		$table = EventFlow_DB::table( 'notifications' );
		$uid   = absint( $user_id );
		$limit = max( 1, absint( $args['limit'] ) );

		$where = "user_id = {$uid}";
		if ( ! empty( $args['unread_only'] ) ) {
			$where .= ' AND is_read = 0';
		}

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT {$limit}";
		$items = $wpdb->get_results( $sql );

		return is_array( $items ) ? $items : array();
	}

	/**
	 * Get unread notification count for a user
	 *
	 * @param int $user_id
	 * @return int
	 */
	public static function get_unread_count( $user_id ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'notifications' );
		$uid   = absint( $user_id );

		if ( ! $uid ) {
			return 0;
		}

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$table} WHERE user_id = %d AND is_read = 0",
				$uid
			)
		);

		return absint( $count );
	}

	/**
	 * Mark a specific notification as read
	 *
	 * @param int $notification_id
	 * @param int $user_id
	 * @return bool
	 */
	public static function mark_as_read( $notification_id, $user_id = 0 ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'notifications' );
		$nid   = absint( $notification_id );
		$uid   = absint( $user_id );

		$where = array( 'id' => $nid );
		if ( $uid ) {
			$where['user_id'] = $uid;
		}

		$now = current_time( 'mysql' );
		$updated = $wpdb->update(
			$table,
			array(
				'is_read' => 1,
				'read_at' => $now,
			),
			$where
		);

		return false !== $updated;
	}

	/**
	 * Mark all notifications as read for a user
	 *
	 * @param int $user_id
	 * @return bool
	 */
	public static function mark_all_read( $user_id ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'notifications' );
		$uid   = absint( $user_id );

		if ( ! $uid ) {
			return false;
		}

		$now = current_time( 'mysql' );
		$updated = $wpdb->update(
			$table,
			array(
				'is_read' => 1,
				'read_at' => $now,
			),
			array(
				'user_id' => $uid,
				'is_read' => 0,
			)
		);

		return false !== $updated;
	}

	/**
	 * Broadcast a notification to authorized admin and payment verifier users
	 *
	 * @param string $type
	 * @param string $title
	 * @param string $message
	 * @param int    $registration_id
	 * @param int    $payment_id
	 * @param string $action_url
	 * @return int Number of admins notified
	 */
	public static function notify_admins( $type, $title, $message, $registration_id = 0, $payment_id = 0, $action_url = '' ) {
		$admin_roles = array( 'administrator', 'eventflow_admin', 'eventflow_payment_verifier' );

		$users = get_users(
			array(
				'role__in' => $admin_roles,
				'fields'   => array( 'ID' ),
			)
		);

		if ( empty( $users ) ) {
			// Fallback: notify super admin user ID 1
			$users = array( (object) array( 'ID' => 1 ) );
		}

		$count = 0;
		$notified_uids = array();

		foreach ( $users as $u ) {
			$uid = absint( $u->ID );
			if ( $uid && ! in_array( $uid, $notified_uids, true ) ) {
				$notified_uids[] = $uid;
				self::create(
					array(
						'user_id'         => $uid,
						'registration_id' => $registration_id,
						'payment_id'      => $payment_id,
						'type'            => $type,
						'title'           => $title,
						'message'         => $message,
						'action_url'      => $action_url,
					)
				);
				$count++;
			}
		}

		return $count;
	}
}

