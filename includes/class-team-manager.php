<?php
/**
 * EventFlow Team & Members Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Team_Manager {

	/**
	 * Create team and its member records
	 *
	 * @param int    $registration_id
	 * @param string $team_name
	 * @param array  $leader_info
	 * @param array  $members
	 * @return int|WP_Error Team ID
	 */
	public static function create_team_with_members( $registration_id, $team_name, $leader_info, $members ) {
		global $wpdb;

		$table_teams = EventFlow_DB::table( 'teams' );
		$table_members = EventFlow_DB::table( 'team_members' );

		// Ensure tables exist
		if ( method_exists( $wpdb, 'get_var' ) ) {
			$teams_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_teams ) );
			if ( $teams_exists !== $table_teams && class_exists( 'EventFlow_DB' ) ) {
				EventFlow_DB::create_tables();
				EventFlow_DB::migrate_tables();
			}
		}

		// Insert Team Record
		$wpdb->insert(
			$table_teams,
			array(
				'registration_id' => absint( $registration_id ),
				'team_name'       => sanitize_text_field( $team_name ),
				'leader_user_id'  => absint( $leader_info['user_id'] ),
				'leader_name'     => sanitize_text_field( $leader_info['name'] ),
				'leader_email'    => sanitize_email( $leader_info['email'] ),
				'leader_phone'    => sanitize_text_field( $leader_info['phone'] ),
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		$team_id = $wpdb->insert_id;
		if ( ! $team_id ) {
			if ( ! empty( $wpdb->last_error ) ) {
				error_log( 'EventFlow Team DB Insert Error: ' . $wpdb->last_error );
			}
			// Emergency retry after schema refresh
			if ( class_exists( 'EventFlow_DB' ) ) {
				EventFlow_DB::create_tables();
				EventFlow_DB::migrate_tables();
				$wpdb->insert(
					$table_teams,
					array(
						'registration_id' => absint( $registration_id ),
						'team_name'       => sanitize_text_field( $team_name ),
						'leader_user_id'  => absint( $leader_info['user_id'] ),
						'leader_name'     => sanitize_text_field( $leader_info['name'] ),
						'leader_email'    => sanitize_email( $leader_info['email'] ),
						'leader_phone'    => sanitize_text_field( $leader_info['phone'] ),
						'created_at'      => current_time( 'mysql' ),
					)
				);
				$team_id = $wpdb->insert_id;
			}
		}

		if ( ! $team_id ) {
			$err_msg = __( 'Failed to create team record.', 'eventflow' );
			if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || current_user_can( 'manage_options' ) ) {
				$err_msg .= ' (' . ( ! empty( $wpdb->last_error ) ? $wpdb->last_error : 'Database insert failed' ) . ')';
			}
			return new WP_Error( 'db_error', $err_msg );
		}

		$mem_cols = method_exists( $wpdb, 'get_col' ) ? (array) $wpdb->get_col( "DESC {$table_members}", 0 ) : array();
		if ( ! empty( $mem_cols ) && ! in_array( 'academic_year', $mem_cols, true ) ) {
			$wpdb->query( "ALTER TABLE {$table_members} ADD COLUMN academic_year varchar(100) DEFAULT NULL" );
			$mem_cols = method_exists( $wpdb, 'get_col' ) ? (array) $wpdb->get_col( "DESC {$table_members}", 0 ) : array();
		}

		// Insert each member
		foreach ( $members as $idx => $member ) {
			$acad_year = ! empty( $member['academic_year'] ) ? sanitize_text_field( $member['academic_year'] ) : ( ! empty( $member['batch'] ) ? sanitize_text_field( $member['batch'] ) : '' );
			$father    = ! empty( $member['father_name'] ) ? sanitize_text_field( $member['father_name'] ) : '';
			$is_lead   = ( ! empty( $member['is_leader'] ) || ( isset( $member['role'] ) && 'leader' === $member['role'] ) || 0 === $idx ) ? 1 : 0;

			$member_data = array(
				'team_id'         => $team_id,
				'registration_id' => absint( $registration_id ),
				'full_name'       => sanitize_text_field( $member['full_name'] ),
				'father_name'     => $father,
				'semester'        => sanitize_text_field( $member['semester'] ),
				'department'      => sanitize_text_field( $member['department'] ),
				'university'      => sanitize_text_field( $member['university'] ),
				'email'           => sanitize_email( $member['email'] ),
				'contact_number'  => sanitize_text_field( $member['contact_number'] ),
				'is_leader'       => $is_lead,
				'created_at'      => current_time( 'mysql' ),
			);
			$member_formats = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' );

			if ( empty( $mem_cols ) || in_array( 'academic_year', $mem_cols, true ) ) {
				$member_data['academic_year'] = $acad_year;
				$member_formats[] = '%s';
			}

			$wpdb->insert(
				$table_members,
				$member_data,
				$member_formats
			);
		}

		return $team_id;
	}

	/**
	 * Get team by registration ID
	 *
	 * @param int $registration_id
	 * @return object|null
	 */
	public static function get_team_by_registration( $registration_id ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'teams' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE registration_id = %d LIMIT 1", absint( $registration_id ) ) );
	}

	/**
	 * Get team members by registration ID
	 *
	 * @param int $registration_id
	 * @return array
	 */
	public static function get_members_by_registration( $registration_id ) {
		global $wpdb;
		$table_members = EventFlow_DB::table( 'team_members' );
		$table_teams   = EventFlow_DB::table( 'teams' );

		$members = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_members} WHERE registration_id = %d ORDER BY is_leader DESC, id ASC",
				absint( $registration_id )
			)
		);

		// Fallback for legacy records linked only through team table
		if ( empty( $members ) ) {
			$members = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT tm.* FROM {$table_members} tm INNER JOIN {$table_teams} t ON tm.team_id = t.id WHERE t.registration_id = %d ORDER BY tm.is_leader DESC, tm.id ASC",
					absint( $registration_id )
				)
			);
		}

		// If no member records found in team_members, construct fallback leader member from team record
		if ( empty( $members ) ) {
			$team = self::get_team_by_registration( $registration_id );
			if ( $team && ! empty( $team->leader_name ) ) {
				$members = array(
					(object) array(
						'id'              => 0,
						'team_id'         => $team->id,
						'registration_id' => absint( $registration_id ),
						'full_name'       => $team->leader_name,
						'father_name'     => '',
						'semester'        => '',
						'academic_year'   => '',
						'department'      => '',
						'university'      => '',
						'email'           => $team->leader_email,
						'contact_number'  => $team->leader_phone,
						'is_leader'       => 1,
						'created_at'      => $team->created_at,
					),
				);
			}
		}

		return $members;
	}

	/**
	 * Alias for get_members_by_registration
	 *
	 * @param int $registration_id
	 * @return array
	 */
	public static function get_team_members( $registration_id ) {
		return self::get_members_by_registration( $registration_id );
	}

	/**
	 * Validate team fields and limits
	 *
	 * @param object $activity
	 * @param string $team_name
	 * @param array  $members
	 * @return true|WP_Error
	 */
	public static function validate_team_submission( $activity, $team_name, $members ) {
		if ( empty( $team_name ) ) {
			return new WP_Error( 'missing_team_name', __( 'Please provide a valid Team Name.', 'eventflow' ) );
		}

		$total_members = count( $members );
		$min = (int) $activity->min_team_size;
		$max = (int) $activity->max_team_size;

		if ( $total_members < $min ) {
			return new WP_Error( 'min_team_error', sprintf( __( 'This activity requires at least %d team member(s). You have %d.', 'eventflow' ), $min, $total_members ) );
		}

		if ( $total_members > $max ) {
			return new WP_Error( 'max_team_error', sprintf( __( 'This activity allows at most %d team member(s). You have %d.', 'eventflow' ), $max, $total_members ) );
		}

		$emails_seen = array();
		$phones_seen = array();
		foreach ( $members as $idx => $m ) {
			$num = $idx + 1;
			$label = ( 0 === $idx ) ? __( 'Team Leader', 'eventflow' ) : sprintf( __( 'Member #%d', 'eventflow' ), $num );

			if ( empty( $m['full_name'] ) ) {
				return new WP_Error( 'missing_field', sprintf( __( 'Full Name is required for %s.', 'eventflow' ), $label ) );
			}
			if ( empty( $m['email'] ) || ! is_email( $m['email'] ) ) {
				return new WP_Error( 'invalid_email', sprintf( __( 'A valid Email Address is required for %s.', 'eventflow' ), $label ) );
			}
			if ( empty( $m['contact_number'] ) ) {
				return new WP_Error( 'missing_field', sprintf( __( 'Phone / WhatsApp Number is required for %s.', 'eventflow' ), $label ) );
			}
			if ( empty( $m['department'] ) ) {
				return new WP_Error( 'missing_field', sprintf( __( 'Department is required for %s.', 'eventflow' ), $label ) );
			}
			if ( empty( $m['semester'] ) ) {
				return new WP_Error( 'missing_field', sprintf( __( 'Current Semester is required for %s.', 'eventflow' ), $label ) );
			}
			$batch = ! empty( $m['academic_year'] ) ? trim( $m['academic_year'] ) : ( ! empty( $m['batch'] ) ? trim( $m['batch'] ) : '' );
			if ( empty( $batch ) ) {
				return new WP_Error( 'missing_field', sprintf( __( 'Academic Year / Batch is required for %s.', 'eventflow' ), $label ) );
			}
			if ( empty( $m['university'] ) ) {
				return new WP_Error( 'missing_field', sprintf( __( 'University / Institution Name is required for %s.', 'eventflow' ), $label ) );
			}

			$clean_email = strtolower( trim( $m['email'] ) );
			if ( in_array( $clean_email, $emails_seen, true ) ) {
				return new WP_Error( 'duplicate_member_email', sprintf( __( 'Email %s is duplicated within this team.', 'eventflow' ), esc_html( $clean_email ) ) );
			}
			$emails_seen[] = $clean_email;

			$clean_phone = preg_replace( '/[^0-9]/', '', $m['contact_number'] );
			if ( ! empty( $clean_phone ) && strlen( $clean_phone ) >= 7 ) {
				if ( in_array( $clean_phone, $phones_seen, true ) ) {
					return new WP_Error( 'duplicate_member_phone', sprintf( __( 'Phone number %s is duplicated within this team.', 'eventflow' ), esc_html( $m['contact_number'] ) ) );
				}
				$phones_seen[] = $clean_phone;
			}
		}

		return true;
	}

	/**
	 * Update an existing participant / team member record
	 *
	 * @param int   $member_id
	 * @param array $data
	 * @return bool|WP_Error
	 */
	public static function update_member( $member_id, $data ) {
		global $wpdb;

		$table_members = EventFlow_DB::table( 'team_members' );
		$table_teams   = EventFlow_DB::table( 'teams' );

		// Handle synthesized member (id 0) fallback: insert fresh member and sync team
		if ( 0 === absint( $member_id ) ) {
			$reg_id = isset( $data['registration_id'] ) ? absint( $data['registration_id'] ) : 0;
			$team   = self::get_team_by_registration( $reg_id );
			if ( $team ) {
				$ins = array(
					'team_id'         => $team->id,
					'registration_id' => $reg_id,
					'full_name'       => ! empty( $data['full_name'] ) ? sanitize_text_field( $data['full_name'] ) : $team->leader_name,
					'father_name'     => ! empty( $data['father_name'] ) ? sanitize_text_field( $data['father_name'] ) : '',
					'semester'        => ! empty( $data['semester'] ) ? sanitize_text_field( $data['semester'] ) : '',
					'academic_year'   => ! empty( $data['academic_year'] ) ? sanitize_text_field( $data['academic_year'] ) : '',
					'department'      => ! empty( $data['department'] ) ? sanitize_text_field( $data['department'] ) : '',
					'university'      => ! empty( $data['university'] ) ? sanitize_text_field( $data['university'] ) : '',
					'email'           => ! empty( $data['email'] ) ? sanitize_email( $data['email'] ) : $team->leader_email,
					'contact_number'  => ! empty( $data['contact_number'] ) ? sanitize_text_field( $data['contact_number'] ) : $team->leader_phone,
					'is_leader'       => 1,
					'created_at'      => current_time( 'mysql' ),
				);
				$wpdb->insert( $table_members, $ins );

				$wpdb->update(
					$table_teams,
					array(
						'leader_name'  => $ins['full_name'],
						'leader_email' => $ins['email'],
						'leader_phone' => $ins['contact_number'],
					),
					array( 'id' => $team->id )
				);
				return true;
			}
			return new WP_Error( 'not_found', __( 'Team record not found for registration.', 'eventflow' ) );
		}

		$member = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_members} WHERE id = %d LIMIT 1", absint( $member_id ) ) );
		if ( ! $member ) {
			return new WP_Error( 'not_found', __( 'Participant record not found.', 'eventflow' ) );
		}

		$update_fields = array();
		if ( isset( $data['full_name'] ) ) {
			$update_fields['full_name'] = sanitize_text_field( $data['full_name'] );
		}
		if ( isset( $data['father_name'] ) ) {
			$update_fields['father_name'] = sanitize_text_field( $data['father_name'] );
		}
		if ( isset( $data['email'] ) ) {
			$update_fields['email'] = sanitize_email( $data['email'] );
		}
		if ( isset( $data['contact_number'] ) ) {
			$update_fields['contact_number'] = sanitize_text_field( $data['contact_number'] );
		}
		if ( isset( $data['department'] ) ) {
			$update_fields['department'] = sanitize_text_field( $data['department'] );
		}
		if ( isset( $data['semester'] ) ) {
			$update_fields['semester'] = sanitize_text_field( $data['semester'] );
		}
		if ( isset( $data['academic_year'] ) ) {
			$update_fields['academic_year'] = sanitize_text_field( $data['academic_year'] );
		}
		if ( isset( $data['university'] ) ) {
			$update_fields['university'] = sanitize_text_field( $data['university'] );
		}

		if ( empty( $update_fields ) ) {
			return true;
		}

		$wpdb->update(
			$table_members,
			$update_fields,
			array( 'id' => $member->id )
		);

		// If this is the team leader, synchronize the team header row
		if ( (int) $member->is_leader === 1 ) {
			$team_update = array();
			if ( isset( $update_fields['full_name'] ) ) {
				$team_update['leader_name'] = $update_fields['full_name'];
			}
			if ( isset( $update_fields['email'] ) ) {
				$team_update['leader_email'] = $update_fields['email'];
			}
			if ( isset( $update_fields['contact_number'] ) ) {
				$team_update['leader_phone'] = $update_fields['contact_number'];
			}

			if ( ! empty( $team_update ) ) {
				$wpdb->update(
					$table_teams,
					$team_update,
					array( 'id' => $member->team_id )
				);
			}
		}

		return true;
	}

	/**
	 * Get paginated list of teams with registration and activity details
	 *
	 * @param array $args
	 * @return array
	 */
	public static function get_teams_list( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'page'     => 1,
			'per_page' => 20,
			'status'   => '',
			'activity' => '',
			'search'   => '',
		);
		$params = wp_parse_args( $args, $defaults );

		$table_t = EventFlow_DB::table( 'teams' );
		$table_r = EventFlow_DB::table( 'registrations' );
		$table_a = EventFlow_DB::table( 'activities' );
		$table_m = EventFlow_DB::table( 'team_members' );

		$where   = array( '1=1' );
		$values  = array();

		if ( ! empty( $params['status'] ) ) {
			$where[]  = 'r.application_status = %s';
			$values[] = sanitize_text_field( $params['status'] );
		}

		if ( ! empty( $params['activity'] ) ) {
			$where[]  = 'r.activity_id = %d';
			$values[] = absint( $params['activity'] );
		}

		if ( ! empty( $params['search'] ) ) {
			$s        = '%' . $wpdb->esc_like( sanitize_text_field( $params['search'] ) ) . '%';
			$where[]  = '(t.team_name LIKE %s OR t.leader_name LIKE %s OR t.leader_email LIKE %s OR r.reg_id LIKE %s)';
			$values[] = $s;
			$values[] = $s;
			$values[] = $s;
			$values[] = $s;
		}

		$where_clause = implode( ' AND ', $where );

		// Count total
		$count_sql = "SELECT COUNT(DISTINCT t.id) FROM {$table_t} t
		              INNER JOIN {$table_r} r ON t.registration_id = r.id
		              WHERE {$where_clause}";
		$total     = (int) ( ! empty( $values ) ? $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) ) : $wpdb->get_var( $count_sql ) );

		$page     = max( 1, absint( $params['page'] ) );
		$per_page = max( 1, absint( $params['per_page'] ) );
		$offset   = ( $page - 1 ) * $per_page;
		$pages    = ceil( $total / $per_page );

		$query_sql = "SELECT t.*, r.reg_id, r.application_status, r.payment_status, r.total_fee,
		                     a.title as activity_title,
		                     (SELECT COUNT(*) FROM {$table_m} m WHERE m.team_id = t.id) as member_count
		              FROM {$table_t} t
		              INNER JOIN {$table_r} r ON t.registration_id = r.id
		              LEFT JOIN {$table_a} a ON r.activity_id = a.id
		              WHERE {$where_clause}
		              ORDER BY t.created_at DESC
		              LIMIT %d OFFSET %d";

		$query_values   = $values;
		$query_values[] = $per_page;
		$query_values[] = $offset;

		$items = $wpdb->get_results( $wpdb->prepare( $query_sql, $query_values ) );

		return array(
			'items'    => $items ? $items : array(),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => $pages,
		);
	}

	/**
	 * Update Team Application Status
	 *
	 * @param int    $team_id
	 * @param string $status ('approved', 'pending', 'rejected')
	 * @param string $note
	 * @return bool|WP_Error
	 */
	public static function update_team_status( $team_id, $status, $note = '' ) {
		global $wpdb;

		$allowed = array( 'pending', 'approved', 'rejected' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return new WP_Error( 'invalid_status', __( 'Invalid application status provided.', 'eventflow' ) );
		}

		$table_t = EventFlow_DB::table( 'teams' );
		$table_r = EventFlow_DB::table( 'registrations' );

		$team = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_t} WHERE id = %d LIMIT 1", absint( $team_id ) ) );
		if ( ! $team ) {
			return new WP_Error( 'not_found', __( 'Team record not found.', 'eventflow' ) );
		}

		$updated = $wpdb->update(
			$table_r,
			array( 'application_status' => $status ),
			array( 'id' => $team->registration_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'db_error', __( 'Failed to update team registration status.', 'eventflow' ) );
		}

		EventFlow_Security::log_audit(
			'team_status_updated',
			'team',
			$team_id,
			array(
				'team_name' => $team->team_name,
				'new_status' => $status,
				'note'       => $note,
			)
		);

		return true;
	}
}

