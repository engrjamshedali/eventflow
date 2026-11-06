<?php
/**
 * EventFlow Advanced Data Import Engine
 *
 * Secure CSV and Excel importing for solo participants and team rosters.
 * Implements column mapping, validation, duplicate prevention, preview step,
 * transaction execution, and error reporting.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Import_Manager {

	/**
	 * Initialize hooks
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'handle_template_download' ) );
	}

	/**
	 * Download sample CSV import templates
	 */
	public static function handle_template_download() {
		if ( ! isset( $_GET['eventflow_action'] ) || 'download_import_template' !== $_GET['eventflow_action'] ) {
			return;
		}

		if ( ! is_user_logged_in() || ! ( current_user_can( 'eventflow_import_data' ) || current_user_can( 'manage_options' ) ) ) {
			wp_die( esc_html__( 'Unauthorized. Administrative permission required to download import templates.', 'eventflow' ), 403 );
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'eventflow_import_template' ) ) {
			wp_die( esc_html__( 'Security check expired. Please try again.', 'eventflow' ), 403 );
		}

		$type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : 'solo';
		$filename = ( 'team' === $type ) ? 'eventflow-teams-import-template.csv' : 'eventflow-solo-import-template.csv';
		$csv      = self::get_sample_template( $type );

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo $csv;
		exit;
	}

	/**
	 * Generate sample template CSV string
	 *
	 * @param string $type 'solo' or 'team'
	 * @return string
	 */
	public static function get_sample_template( $type = 'solo' ) {
		if ( 'team' === $type ) {
			$headers = array(
				'Team Name',
				'Leader Name',
				'Leader Email',
				'Leader Phone',
				'University / Institute',
				'Department',
				'Semester',
				'Competition / Track Title',
				'Member 1 Name',
				'Member 1 Email',
				'Member 1 Phone',
				'Member 2 Name',
				'Member 2 Email',
				'Member 2 Phone',
			);
			$samples = array(
				array(
					'Code Warriors',
					'Ahmed Khan',
					'ahmed.khan@iba-suk.edu.pk',
					'+923001234567',
					'Sukkur IBA University',
					'Computer Science',
					'6',
					'Speed Programming Championship',
					'Bilal Hussain',
					'bilal.h@iba-suk.edu.pk',
					'+923007654321',
					'Danyal Ali',
					'danyal.ali@iba-suk.edu.pk',
					'+923009988776',
				),
			);
		} else {
			$headers = array(
				'Full Name',
				"Father's Name",
				'Email Address',
				'Contact Number',
				'University / Institute',
				'Department',
				'Semester',
				'Competition / Track Title',
			);
			$samples = array(
				array(
					'Ali Raza',
					'Muhammad Raza',
					'ali.raza@iba-suk.edu.pk',
					'+923019876543',
					'Sukkur IBA University',
					'Software Engineering',
					'4',
					'Speed Programming Championship',
				),
			);
		}

		$output = fopen( 'php://temp', 'r+' );
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		fputcsv( $output, $headers );
		foreach ( $samples as $row ) {
			fputcsv( $output, $row );
		}
		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return $csv;
	}

	/**
	 * Parse uploaded CSV or Excel file into structured rows
	 *
	 * @param array $file $_FILES array item
	 * @return array|WP_Error
	 */
	public static function parse_uploaded_file( $file ) {
		if ( empty( $file ) || ! empty( $file['error'] ) ) {
			return new WP_Error( 'upload_error', __( 'File upload failed or no file selected.', 'eventflow' ) );
		}

		$tmp_path = $file['tmp_name'];
		$filename = $file['name'];
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, array( 'csv', 'txt', 'xls', 'xlsx' ), true ) ) {
			return new WP_Error( 'invalid_format', __( 'Only CSV and Excel format files (.csv, .xlsx, .xls) are supported.', 'eventflow' ) );
		}

		// Handle CSV parsing
		$rows = array();
		$handle = fopen( $tmp_path, 'r' );
		if ( ! $handle ) {
			return new WP_Error( 'file_read_error', __( 'Could not open uploaded file for reading.', 'eventflow' ) );
		}

		// Strip UTF-8 BOM if present
		$bom = fread( $handle, 3 );
		if ( $bom !== "\xEF\xBB\xBF" ) {
			rewind( $handle );
		}

		// Read header
		$raw_headers = fgetcsv( $handle, 4096, ',' );
		if ( ! $raw_headers || count( $raw_headers ) === 1 ) {
			// Try semicolon delimiter fallback
			rewind( $handle );
			$raw_headers = fgetcsv( $handle, 4096, ';' );
			$delimiter   = ';';
		} else {
			$delimiter = ',';
		}

		if ( empty( $raw_headers ) ) {
			fclose( $handle );
			return new WP_Error( 'empty_file', __( 'Uploaded file contains no headers or readable data.', 'eventflow' ) );
		}

		// Normalize headers
		$mapped_headers = array();
		foreach ( $raw_headers as $index => $h ) {
			$mapped_headers[ $index ] = self::normalize_header_key( $h );
		}

		$line_number = 1;
		while ( ( $data = fgetcsv( $handle, 4096, $delimiter ) ) !== false ) {
			$line_number++;
			// Skip completely empty lines
			if ( empty( array_filter( $data ) ) ) {
				continue;
			}

			$row = array( '_line' => $line_number );
			foreach ( $data as $index => $value ) {
				$key = isset( $mapped_headers[ $index ] ) ? $mapped_headers[ $index ] : 'col_' . $index;
				$row[ $key ] = trim( (string) $value );
			}
			$rows[] = $row;
		}

		fclose( $handle );

		if ( empty( $rows ) ) {
			return new WP_Error( 'no_data', __( 'No data rows found in uploaded file.', 'eventflow' ) );
		}

		return $rows;
	}

	/**
	 * Map header names to standard keys
	 *
	 * @param string $header
	 * @return string
	 */
	public static function normalize_header_key( $header ) {
		$clean = strtolower( trim( preg_replace( '/[^a-zA-Z0-9]/', '', (string) $header ) ) );

		if ( in_array( $clean, array( 'fullname', 'name', 'participantname', 'studentname', 'candidatename' ), true ) ) {
			return 'full_name';
		}
		if ( in_array( $clean, array( 'fathername', 'fathersname', 'father', 'guardian' ), true ) ) {
			return 'father_name';
		}
		if ( in_array( $clean, array( 'email', 'emailaddress', 'mail', 'useremail' ), true ) ) {
			return 'email';
		}
		if ( in_array( $clean, array( 'phone', 'phonenumber', 'contact', 'contactnumber', 'mobile', 'whatsapp' ), true ) ) {
			return 'phone';
		}
		if ( in_array( $clean, array( 'university', 'institute', 'institution', 'college', 'uni' ), true ) ) {
			return 'university';
		}
		if ( in_array( $clean, array( 'department', 'dept', 'program', 'discipline' ), true ) ) {
			return 'department';
		}
		if ( in_array( $clean, array( 'semester', 'sem', 'currentsemester', 'year' ), true ) ) {
			return 'semester';
		}
		if ( in_array( $clean, array( 'teamname', 'team' ), true ) ) {
			return 'team_name';
		}
		if ( in_array( $clean, array( 'isleader', 'leader', 'role', 'teamleader' ), true ) ) {
			return 'is_leader';
		}
		if ( in_array( $clean, array( 'competition', 'activity', 'track', 'eventtrack', 'competitioncategory', 'event' ), true ) ) {
			return 'competition';
		}

		return $clean;
	}

	/**
	 * Validate parsed rows and detect duplicates
	 *
	 * @param array $rows
	 * @param int   $event_id
	 * @return array
	 */
	public static function validate_rows( $rows, $event_id = 0 ) {
		global $wpdb;

		if ( is_array( $event_id ) ) {
			$event_id = isset( $event_id['event_id'] ) ? (int) $event_id['event_id'] : 0;
		}

		$table_r = EventFlow_DB::table( 'registrations' );
		$table_m = EventFlow_DB::table( 'team_members' );
		$table_a = EventFlow_DB::table( 'activities' );

		// Cache activities
		$activities_rows = $wpdb->get_results( "SELECT id, title, slug, reg_fee FROM {$table_a}" );
		$activities_map  = array();
		foreach ( (array) $activities_rows as $act ) {
			$activities_map[ strtolower( trim( $act->title ) ) ] = $act;
			$activities_map[ strtolower( trim( $act->slug ) ) ]  = $act;
			$activities_map[ (string) $act->id ]                  = $act;
		}

		// Normalize input rows keys
		$normalized_rows = array();
		$line_num = 1;
		foreach ( $rows as $raw_r ) {
			$r = array();
			foreach ( (array) $raw_r as $k => $v ) {
				$nk = self::normalize_header_key( $k );
				$r[ $nk ] = is_string( $v ) ? trim( $v ) : $v;
			}
			if ( ! isset( $r['_line'] ) ) {
				$r['_line'] = isset( $raw_r['_line'] ) ? $raw_r['_line'] : $line_num;
			}
			$normalized_rows[] = $r;
			$line_num++;
		}

		$valid_rows   = array();
		$invalid_rows = array();
		$duplicates   = array();
		$seen_emails  = array();

		foreach ( $normalized_rows as $row ) {
			$errors       = array();
			$is_duplicate = false;

			$full_name   = isset( $row['full_name'] ) ? sanitize_text_field( $row['full_name'] ) : '';
			$email       = isset( $row['email'] ) ? sanitize_email( $row['email'] ) : '';
			$phone       = isset( $row['phone'] ) ? sanitize_text_field( $row['phone'] ) : '';
			$university  = isset( $row['university'] ) ? sanitize_text_field( $row['university'] ) : '';
			$department  = isset( $row['department'] ) ? sanitize_text_field( $row['department'] ) : '';
			$semester    = isset( $row['semester'] ) ? sanitize_text_field( $row['semester'] ) : '';
			$competition = isset( $row['competition'] ) ? sanitize_text_field( $row['competition'] ) : '';
			$team_name   = isset( $row['team_name'] ) ? sanitize_text_field( $row['team_name'] ) : '';
			$is_leader   = isset( $row['is_leader'] ) && in_array( strtolower( (string) $row['is_leader'] ), array( '1', 'yes', 'true', 'leader' ), true ) ? 1 : 0;

			// Validate required fields
			if ( empty( $full_name ) ) {
				$errors[] = __( 'Missing full name', 'eventflow' );
			}
			if ( empty( $email ) || ! is_email( $email ) ) {
				$errors[] = __( 'Invalid or missing email address', 'eventflow' );
			}
			if ( empty( $university ) ) {
				$errors[] = __( 'Missing university name', 'eventflow' );
			}
			if ( empty( $competition ) ) {
				$errors[] = __( 'Missing competition / track', 'eventflow' );
			}

			// Resolve activity
			$clean_comp = strtolower( trim( $competition ) );
			$matched_activity = null;
			if ( isset( $activities_map[ $clean_comp ] ) ) {
				$matched_activity = $activities_map[ $clean_comp ];
			} else {
				// Partial match
				foreach ( $activities_map as $name => $obj ) {
					if ( strpos( $name, $clean_comp ) !== false || strpos( $clean_comp, $name ) !== false ) {
						$matched_activity = $obj;
						break;
					}
				}
			}

			if ( ! $matched_activity && empty( $errors ) ) {
				$errors[] = sprintf( __( 'Competition "%s" not found in active database', 'eventflow' ), esc_html( $competition ) );
			}

			// Duplicate check within file
			if ( ! empty( $email ) ) {
				if ( isset( $seen_emails[ $email ] ) ) {
					$errors[]     = sprintf( __( 'Duplicate email in uploaded file (first seen on line %d)', 'eventflow' ), $seen_emails[ $email ] );
					$is_duplicate = true;
				} else {
					$seen_emails[ $email ] = $row['_line'];
				}
			}

			// Duplicate check against existing database registrations for this activity
			if ( ! empty( $email ) && $matched_activity && ! $is_duplicate && empty( $errors ) ) {
				$existing_reg = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT m.id FROM {$table_m} m 
						 INNER JOIN {$table_r} r ON m.registration_id = r.id 
						 WHERE m.email = %s AND r.activity_id = %d LIMIT 1",
						$email,
						$matched_activity->id
					)
				);

				if ( $existing_reg ) {
					$errors[]     = sprintf( __( 'Participant already registered for competition "%s"', 'eventflow' ), $matched_activity->title );
					$is_duplicate = true;
				}
			}

			$processed_row = array(
				'_line'         => $row['_line'],
				'full_name'     => $full_name,
				'father_name'   => isset( $row['father_name'] ) ? sanitize_text_field( $row['father_name'] ) : '',
				'email'         => $email,
				'phone'         => $phone,
				'university'    => $university,
				'department'    => $department,
				'semester'      => $semester,
				'team_name'     => $team_name,
				'is_leader'     => $is_leader,
				'competition'   => $competition,
				'activity_id'   => $matched_activity ? $matched_activity->id : 0,
				'activity_title'=> $matched_activity ? $matched_activity->title : $competition,
				'errors'        => $errors,
				'is_duplicate'  => $is_duplicate,
				'is_valid'      => empty( $errors ) && ! $is_duplicate,
			);

			if ( $is_duplicate ) {
				$duplicates[] = $processed_row;
			} elseif ( ! empty( $errors ) ) {
				$invalid_rows[] = $processed_row;
			} else {
				$valid_rows[] = $processed_row;
			}
		}

		$all_errors = array();
		foreach ( array_merge( $invalid_rows, $duplicates ) as $er ) {
			if ( ! empty( $er['errors'] ) ) {
				$all_errors = array_merge( $all_errors, $er['errors'] );
			}
		}

		return array(
			'total'          => count( $normalized_rows ),
			'total_rows'     => count( $normalized_rows ),
			'valid_count'    => count( $valid_rows ),
			'valid_rows'     => count( $valid_rows ),
			'invalid_count'  => count( $invalid_rows ),
			'invalid_rows'   => count( $invalid_rows ),
			'dup_count'      => count( $duplicates ),
			'duplicates'     => count( $duplicates ),
			'valid'          => $valid_rows,
			'invalid'        => $invalid_rows,
			'duplicate_rows' => $duplicates,
			'validated_rows' => array_merge( $valid_rows, $duplicates ),
			'errors'         => $all_errors,
			'sample'         => array_slice( array_merge( $valid_rows, $duplicates, $invalid_rows ), 0, 10 ),
		);
	}

	/**
	 * Process validated import into database
	 *
	 * @param array $valid_rows
	 * @param int|array $options Event ID or options array
	 * @param bool  $dispatch_emails
	 * @return array|WP_Error
	 */
	public static function execute_import( $valid_rows, $options = array(), $dispatch_emails = false ) {
		global $wpdb;

		if ( empty( $valid_rows ) ) {
			return new WP_Error( 'no_valid_rows', __( 'No valid rows available to import.', 'eventflow' ) );
		}

		$event_id        = 0;
		$skip_duplicates = true;

		if ( is_array( $options ) ) {
			$event_id        = isset( $options['event_id'] ) ? (int) $options['event_id'] : 0;
			$skip_duplicates = isset( $options['skip_duplicates'] ) ? (bool) $options['skip_duplicates'] : true;
		} elseif ( is_numeric( $options ) ) {
			$event_id = (int) $options;
		}

		if ( ! $event_id ) {
			$active_event = EventFlow_Event_Manager::get_active_event();
			$event_id     = $active_event ? $active_event->id : 1;
		}

		$table_r = EventFlow_DB::table( 'registrations' );
		$table_t = EventFlow_DB::table( 'teams' );
		$table_m = EventFlow_DB::table( 'team_members' );
		$now     = current_time( 'mysql' );

		// Filter duplicates if skip_duplicates is enabled
		$teams_grouped = array();
		$solo_rows     = array();
		$skipped_count = 0;

		foreach ( $valid_rows as $row ) {
			if ( ! empty( $row['is_duplicate'] ) ) {
				if ( $skip_duplicates ) {
					$skipped_count++;
					continue;
				}
			}

			if ( ! empty( $row['team_name'] ) ) {
				$team_key = $row['activity_id'] . '_' . sanitize_title( $row['team_name'] );
				if ( ! isset( $teams_grouped[ $team_key ] ) ) {
					$teams_grouped[ $team_key ] = array(
						'team_name'   => $row['team_name'],
						'activity_id' => $row['activity_id'],
						'members'     => array(),
					);
				}
				$teams_grouped[ $team_key ]['members'][] = $row;
			} else {
				$solo_rows[] = $row;
			}
		}

		$imported_registrations = 0;
		$imported_participants  = 0;
		$failed_records         = array();

		// Start Database Transaction
		$wpdb->query( 'START TRANSACTION' );

		try {
			// 1. Process Solo Registrations
			foreach ( $solo_rows as $solo ) {
				$user_id = 0;
				if ( function_exists( 'wp_create_user' ) ) {
					$created_uid = self::get_or_create_user( $solo );
					if ( ! is_wp_error( $created_uid ) && $created_uid ) {
						$user_id = $created_uid;
					}
				}

				$reg_id = EventFlow_Registration_Manager::generate_reg_id();

				// Fetch fee for activity
				$act_fee = (float) $wpdb->get_var( $wpdb->prepare( "SELECT reg_fee FROM " . EventFlow_DB::table( 'activities' ) . " WHERE id = %d", $solo['activity_id'] ) );

				$inserted_reg = $wpdb->insert(
					$table_r,
					array(
						'reg_id'             => $reg_id,
						'event_id'           => $event_id,
						'activity_id'        => $solo['activity_id'],
						'user_id'            => $user_id,
						'participation_type' => 'solo',
						'team_name'          => '',
						'total_fee'          => $act_fee,
						'payment_status'     => 'pending', // Unverified initial state
						'application_status' => 'submitted',
						'created_at'         => $now,
						'updated_at'         => $now,
					)
				);

				$registration_id = $wpdb->insert_id;

				if ( $registration_id ) {
					// Insert member record
					$wpdb->insert(
						$table_m,
						array(
							'team_id'         => 0,
							'registration_id' => $registration_id,
							'full_name'       => $solo['full_name'],
							'father_name'     => isset( $solo['father_name'] ) ? $solo['father_name'] : '',
							'semester'        => isset( $solo['semester'] ) ? $solo['semester'] : '',
							'department'      => isset( $solo['department'] ) ? $solo['department'] : '',
							'university'      => isset( $solo['university'] ) ? $solo['university'] : '',
							'email'           => $solo['email'],
							'contact_number'  => isset( $solo['phone'] ) ? $solo['phone'] : '',
							'is_leader'       => 1,
							'created_at'      => $now,
						)
					);

					$imported_registrations++;
					$imported_participants++;
				}
			}

			// 2. Process Team Registrations
			foreach ( $teams_grouped as $t_data ) {
				$team_name   = $t_data['team_name'];
				$activity_id = $t_data['activity_id'];
				$members     = $t_data['members'];

				if ( empty( $members ) ) {
					continue;
				}

				// Find or designate leader
				$leader = null;
				foreach ( $members as $m ) {
					if ( ! empty( $m['is_leader'] ) ) {
						$leader = $m;
						break;
					}
				}
				if ( ! $leader ) {
					$leader = $members[0];
				}

				$leader_user_id = 0;
				if ( function_exists( 'wp_create_user' ) ) {
					$created_lid = self::get_or_create_user( $leader );
					if ( ! is_wp_error( $created_lid ) && $created_lid ) {
						$leader_user_id = $created_lid;
					}
				}

				$reg_id  = EventFlow_Registration_Manager::generate_reg_id();
				$act_fee = (float) $wpdb->get_var( $wpdb->prepare( "SELECT reg_fee FROM " . EventFlow_DB::table( 'activities' ) . " WHERE id = %d", $activity_id ) );

				$wpdb->insert(
					$table_r,
					array(
						'reg_id'             => $reg_id,
						'event_id'           => $event_id,
						'activity_id'        => $activity_id,
						'user_id'            => $leader_user_id,
						'participation_type' => 'team',
						'team_name'          => $team_name,
						'total_fee'          => $act_fee,
						'payment_status'     => 'pending',
						'application_status' => 'submitted',
						'created_at'         => $now,
						'updated_at'         => $now,
					)
				);

				$registration_id = $wpdb->insert_id;

				if ( $registration_id ) {
					// Create Team Row
					$wpdb->insert(
						$table_t,
						array(
							'registration_id' => $registration_id,
							'team_name'       => $team_name,
							'leader_user_id'  => $leader_user_id,
							'leader_name'     => $leader['full_name'],
							'leader_email'    => $leader['email'],
							'leader_phone'    => $leader['phone'],
							'created_at'      => $now,
						)
					);
					$team_id = $wpdb->insert_id;

					// Insert each team member
					foreach ( $members as $mem ) {
						$is_lead = ( $mem['email'] === $leader['email'] ) ? 1 : 0;
						$wpdb->insert(
							$table_m,
							array(
								'team_id'         => $team_id,
								'registration_id' => $registration_id,
								'full_name'       => $mem['full_name'],
								'father_name'     => isset( $mem['father_name'] ) ? $mem['father_name'] : '',
								'semester'        => isset( $mem['semester'] ) ? $mem['semester'] : '',
								'department'      => isset( $mem['department'] ) ? $mem['department'] : '',
								'university'      => isset( $mem['university'] ) ? $mem['university'] : '',
								'email'           => $mem['email'],
								'contact_number'  => isset( $mem['phone'] ) ? $mem['phone'] : '',
								'is_leader'       => $is_lead,
								'created_at'      => $now,
							)
						);
						$imported_participants++;
					}

					$imported_registrations++;
				}
			}

			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'import_failed', sprintf( __( 'Database import error: %s', 'eventflow' ), $e->getMessage() ) );
		}

		EventFlow_Security::log_audit(
			'data_imported',
			'system',
			$imported_registrations,
			array(
				'registrations_count' => $imported_registrations,
				'participants_count'  => $imported_participants,
				'failed_count'        => count( $failed_records ),
				'skipped_count'       => $skipped_count,
			)
		);

		return array(
			'success'                => true,
			'imported_count'         => $imported_registrations,
			'skipped_count'          => $skipped_count,
			'imported_registrations' => $imported_registrations,
			'imported_participants'  => $imported_participants,
			'failed_count'           => count( $failed_records ),
			'failed_records'         => $failed_records,
		);
	}

	/**
	 * Find existing user or create a new participant user account
	 *
	 * @param array $row
	 * @return int|WP_Error
	 */
	private static function get_or_create_user( $row ) {
		$email = sanitize_email( $row['email'] );
		$user  = get_user_by( 'email', $email );

		if ( $user ) {
			return $user->ID;
		}

		// Create user
		$username = sanitize_user( current( explode( '@', $email ) ), true );
		$orig_username = $username;
		$i = 1;
		while ( username_exists( $username ) ) {
			$username = $orig_username . $i;
			$i++;
		}

		$pwd     = function_exists( 'wp_generate_password' ) ? wp_generate_password( 12 ) : substr( md5( uniqid( (string) mt_rand(), true ) ), 0, 12 );
		$user_id = wp_create_user( $username, $pwd, $email );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $row['full_name'],
				'first_name'   => $row['full_name'],
				'role'         => 'eventflow_participant',
			)
		);

		update_user_meta( $user_id, 'eventflow_phone', $row['phone'] );
		update_user_meta( $user_id, 'eventflow_university', $row['university'] );
		update_user_meta( $user_id, 'eventflow_department', $row['department'] );
		update_user_meta( $user_id, 'eventflow_semester', $row['semester'] );

		return $user_id;
	}
}
