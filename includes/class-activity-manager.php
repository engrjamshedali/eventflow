<?php
/**
 * EventFlow Activity & Competition Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Activity_Manager {

	/**
	 * Get activities with optional filters
	 *
	 * @param array $args
	 * @return array
	 */
	public static function get_activities( $args = array() ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'activities' );

		$defaults = array(
			'event_id' => 0,
			'status'   => 'published',
			'search'   => '',
			'orderby'  => 'id',
			'order'    => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $args['event_id'] ) ) {
			$where[] = 'event_id = %d';
			$values[] = absint( $args['event_id'] );
		}

		if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
			$where[] = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$where[] = '(title LIKE %s OR description LIKE %s)';
			$wildcard = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $wildcard;
			$values[] = $wildcard;
		}

		$where_clause = implode( ' AND ', $where );
		$order = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
		$orderby = in_array( $args['orderby'], array( 'id', 'title', 'reg_fee', 'created_at' ), true ) ? $args['orderby'] : 'id';

		$sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order}";

		if ( ! empty( $values ) ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Get single activity by ID
	 *
	 * @param int $id
	 * @return object|null
	 */
	public static function get_activity( $id ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'activities' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $id ) ) );
	}

	/**
	 * Check if activity is currently open for registration
	 *
	 * @param object|int $activity
	 * @return bool
	 */
	public static function is_open_for_registration( $activity ) {
		if ( is_numeric( $activity ) ) {
			$activity = self::get_activity( $activity );
		}

		if ( ! $activity || ( empty( $activity->status ) || ( 'published' !== $activity->status && 'open' !== $activity->status ) ) ) {
			return false;
		}

		// Check event registration status first
		$event = EventFlow_Event_Manager::get_event( $activity->event_id );
		if ( ! EventFlow_Event_Manager::is_registration_open( $event ) ) {
			return false;
		}

		// Check activity specific deadline
		if ( ! empty( $activity->reg_deadline ) ) {
			$now = current_time( 'timestamp' );
			$deadline = strtotime( $activity->reg_deadline );
			if ( $now > $deadline ) {
				return false;
			}
		}

		// Check capacity limits
		if ( ! empty( $activity->max_participants ) && (int) $activity->max_participants > 0 && ! empty( $activity->current_participants ) && (int) $activity->current_participants >= (int) $activity->max_participants ) {
			return false;
		}

		return true;
	}

	/**
	 * Save activity (Create or Update)
	 *
	 * @param array $data
	 * @param int   $id
	 * @return int|WP_Error
	 */
	public static function save_activity( $data, $id = 0 ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'activities' );

		$title = sanitize_text_field( $data['title'] );
		$slug  = ! empty( $data['slug'] ) ? sanitize_title( $data['slug'] ) : sanitize_title( $title );

		$event_id = isset( $data['event_id'] ) ? absint( $data['event_id'] ) : 0;
		if ( ! $event_id ) {
			$active_event = EventFlow_Event_Manager::get_active_event();
			$event_id = $active_event ? $active_event->id : 1;
		}

		$fields = array(
			'event_id'                => $event_id,
			'title'                   => $title,
			'slug'                    => $slug,
			'description'             => wp_kses_post( $data['description'] ),
			'image_url'               => esc_url_raw( $data['image_url'] ),
			'eligibility'             => sanitize_textarea_field( $data['eligibility'] ),
			'reg_deadline'            => ! empty( $data['reg_deadline'] ) ? sanitize_text_field( $data['reg_deadline'] ) : null,
			'participation_type'      => in_array( $data['participation_type'], array( 'solo', 'team', 'both' ), true ) ? $data['participation_type'] : 'both',
			'min_team_size'           => max( 1, absint( $data['min_team_size'] ) ),
			'max_team_size'           => max( 1, absint( $data['max_team_size'] ) ),
			'leader_counts_as_member' => ! empty( $data['leader_counts_as_member'] ) ? 1 : 0,
			'reg_fee'                 => max( 0, floatval( $data['reg_fee'] ) ),
			'fee_calculation'         => isset( $data['fee_calculation'] ) && in_array( $data['fee_calculation'], array( 'per_team', 'per_person' ), true ) ? $data['fee_calculation'] : 'per_team',
			'payment_proof_required'  => isset( $data['payment_proof_required'] ) ? ( ! empty( $data['payment_proof_required'] ) ? 1 : 0 ) : 1,
			'max_participants'        => absint( $data['max_participants'] ),
			'instructions'            => sanitize_textarea_field( $data['instructions'] ),
			'status'                  => in_array( $data['status'], array( 'draft', 'published', 'closed' ), true ) ? $data['status'] : 'published',
			'updated_at'              => current_time( 'mysql' ),
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $fields, array( 'id' => absint( $id ) ) );
			EventFlow_Security::log_audit( 'activity_updated', 'activity', $id, $title );
			return $id;
		} else {
			$fields['current_participants'] = 0;
			$fields['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $fields );
			$new_id = $wpdb->insert_id;
			EventFlow_Security::log_audit( 'activity_created', 'activity', $new_id, $title );
			return $new_id;
		}
	}

	/**
	 * Delete activity
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete_activity( $id ) {
		global $wpdb;
		$id = absint( $id );
		$table = EventFlow_DB::table( 'activities' );

		// Check if registrations exist
		$reg_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . EventFlow_DB::table( 'registrations' ) . " WHERE activity_id = %d", $id ) );
		if ( $reg_count > 0 ) {
			return false; // Prevent deletion if active registrations exist
		}

		$wpdb->delete( $table, array( 'id' => $id ) );
		EventFlow_Security::log_audit( 'activity_deleted', 'activity', $id );
		return true;
	}

	/**
	 * Increment participant count atomically with capacity check
	 *
	 * @param int $activity_id
	 * @param int $count
	 * @return bool True if incremented successfully, false if capacity limit reached
	 */
	public static function increment_participants( $activity_id, $count = 1 ) {
		global $wpdb;
		$table       = EventFlow_DB::table( 'activities' );
		$count       = max( 1, absint( $count ) );
		$activity_id = absint( $activity_id );

		// Atomic conditional update: increment ONLY IF max_participants is 0 or current + count <= max_participants
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} 
				 SET current_participants = current_participants + %d 
				 WHERE id = %d 
				   AND (max_participants = 0 OR (current_participants + %d) <= max_participants)",
				$count,
				$activity_id,
				$count
			)
		);

		return ( false !== $updated && $updated > 0 );
	}

	/**
	 * Decrement participant count
	 *
	 * @param int $activity_id
	 * @param int $count
	 * @return bool
	 */
	public static function decrement_participants( $activity_id, $count = 1 ) {
		global $wpdb;
		$table       = EventFlow_DB::table( 'activities' );
		$count       = max( 1, absint( $count ) );
		$activity_id = absint( $activity_id );

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} 
				 SET current_participants = GREATEST(0, CAST(current_participants AS SIGNED) - %d) 
				 WHERE id = %d",
				$count,
				$activity_id
			)
		);

		return ( false !== $updated && $updated > 0 );
	}

	/**
	 * Get dynamic form schema for an activity
	 *
	 * @param int $activity_id
	 * @return array
	 */
	public static function get_form_schema( $activity_id ) {
		global $wpdb;
		$activity_id = absint( $activity_id );
		if ( ! $activity_id ) {
			return array();
		}

		// Check dedicated forms table first
		$table_forms = EventFlow_DB::table( 'forms' );
		$raw_fields  = $wpdb->get_var( $wpdb->prepare( "SELECT fields FROM {$table_forms} WHERE activity_id = %d ORDER BY id DESC LIMIT 1", $activity_id ) );

		if ( ! $raw_fields ) {
			// Fallback to activities table form_fields column
			$table_activities = EventFlow_DB::table( 'activities' );
			$raw_fields       = $wpdb->get_var( $wpdb->prepare( "SELECT form_fields FROM {$table_activities} WHERE id = %d LIMIT 1", $activity_id ) );
		}

		if ( empty( $raw_fields ) ) {
			return array();
		}

		$decoded = json_decode( $raw_fields, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Save dynamic form schema for an activity
	 *
	 * @param int $activity_id
	 * @param array|string $fields_schema
	 * @param string $title
	 * @return bool
	 */
	public static function save_form_schema( $activity_id, $fields_schema, $title = '' ) {
		global $wpdb;
		$activity_id = absint( $activity_id );
		$activity    = self::get_activity( $activity_id );
		if ( ! $activity ) {
			return false;
		}

		if ( is_string( $fields_schema ) ) {
			$json_fields = $fields_schema;
		} else {
			$json_fields = wp_json_encode( $fields_schema );
		}

		$table_forms = EventFlow_DB::table( 'forms' );
		$table_acts  = EventFlow_DB::table( 'activities' );

		// 1. Sync activities table form_fields
		$wpdb->update(
			$table_acts,
			array( 'form_fields' => $json_fields, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $activity_id )
		);

		// 2. Persist in wp_eventflow_forms
		$existing_form_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_forms} WHERE activity_id = %d LIMIT 1", $activity_id ) );
		$form_title       = ! empty( $title ) ? sanitize_text_field( $title ) : $activity->title . ' Custom Form';

		if ( $existing_form_id > 0 ) {
			$wpdb->update(
				$table_forms,
				array(
					'title'      => $form_title,
					'fields'     => $json_fields,
					'status'     => 'published',
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $existing_form_id )
			);
		} else {
			$wpdb->insert(
				$table_forms,
				array(
					'title'       => $form_title,
					'activity_id' => $activity_id,
					'event_id'    => $activity->event_id,
					'fields'      => $json_fields,
					'status'      => 'published',
					'created_at'  => current_time( 'mysql' ),
					'updated_at'  => current_time( 'mysql' ),
				)
			);
		}

		EventFlow_Security::log_audit( 'form_schema_saved', 'activity', $activity_id, array( 'title' => $form_title ) );
		return true;
	}

	/**
	 * Duplicate form schema from one activity to another
	 *
	 * @param int $source_activity_id
	 * @param int $target_activity_id
	 * @return bool
	 */
	public static function duplicate_form( $source_activity_id, $target_activity_id ) {
		$schema = self::get_form_schema( $source_activity_id );
		if ( empty( $schema ) ) {
			return false;
		}
		$target_activity = self::get_activity( $target_activity_id );
		if ( ! $target_activity ) {
			return false;
		}
		return self::save_form_schema( $target_activity_id, $schema, $target_activity->title . ' Custom Form' );
	}

	/**
	 * Delete form schema for an activity
	 *
	 * @param int $activity_id
	 * @return bool
	 */
	public static function delete_form( $activity_id ) {
		global $wpdb;
		$activity_id = absint( $activity_id );
		$table_forms = EventFlow_DB::table( 'forms' );
		$table_acts  = EventFlow_DB::table( 'activities' );

		$wpdb->delete( $table_forms, array( 'activity_id' => $activity_id ) );
		$wpdb->update( $table_acts, array( 'form_fields' => null, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $activity_id ) );

		EventFlow_Security::log_audit( 'form_schema_deleted', 'activity', $activity_id );
		return true;
	}

	/**
	 * Calculate registration fee for an activity
	 *
	 * @param object $activity
	 * @param string $participation_type 'solo' or 'team'
	 * @param int    $participant_count
	 * @return float
	 */
	public static function calculate_fee( $activity, $participation_type = 'solo', $participant_count = 1 ) {
		if ( ! $activity ) {
			return 0.00;
		}

		$base_fee = floatval( $activity->reg_fee );
		if ( $base_fee <= 0 ) {
			return 0.00;
		}

		// Solo is always charged base fee
		if ( 'solo' === $participation_type ) {
			return $base_fee;
		}

		// Team fee calculation
		$mode = ! empty( $activity->fee_calculation ) ? $activity->fee_calculation : 'per_team';
		if ( 'per_person' === $mode ) {
			$count = max( 1, absint( $participant_count ) );
			return $base_fee * $count;
		}

		// 'per_team' charges one flat fee
		return $base_fee;
	}

	/**
	 * Format activity settings for dynamic frontend consumption
	 *
	 * @param object $activity
	 * @return array
	 */
	public static function format_activity_for_frontend( $activity ) {
		if ( ! $activity ) {
			return array();
		}

		$event = EventFlow_Event_Manager::get_event( $activity->event_id );
		$currency = $event ? $event->currency : 'PKR';

		return array(
			'id'                     => (int) $activity->id,
			'title'                  => isset( $activity->title ) ? $activity->title : '',
			'slug'                   => ! empty( $activity->slug ) ? $activity->slug : '',
			'description'            => ! empty( $activity->description ) ? wp_strip_all_tags( $activity->description ) : '',
			'participation_type'     => ! empty( $activity->participation_type ) ? $activity->participation_type : 'both',
			'min_team_size'          => ! empty( $activity->min_team_size ) ? max( 1, (int) $activity->min_team_size ) : 1,
			'max_team_size'          => ! empty( $activity->max_team_size ) ? max( 1, (int) $activity->max_team_size ) : 4,
			'reg_fee'                => ! empty( $activity->reg_fee ) ? floatval( $activity->reg_fee ) : 0.0,
			'currency'               => $currency,
			'fee_calculation'        => ! empty( $activity->fee_calculation ) ? $activity->fee_calculation : 'per_team',
			'payment_proof_required' => isset( $activity->payment_proof_required ) ? (int) $activity->payment_proof_required : 1,
			'form_fields'            => self::get_form_schema( $activity->id ),
			'is_open'                => self::is_open_for_registration( $activity ),
			'status'                 => ! empty( $activity->status ) ? $activity->status : 'published',
		);
	}
}

