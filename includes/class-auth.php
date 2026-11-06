<?php
/**
 * EventFlow Participant & Staff Authentication & Session Security Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Auth {

	/**
	 * Initialize Auth hooks
	 */
	public static function init() {
		add_action( 'user_register', array( __CLASS__, 'on_user_register' ), 10, 1 );
		add_action( 'after_password_reset', array( __CLASS__, 'on_password_reset' ), 10, 2 );
	}

	/**
	 * Automatically send password change confirmation security notification
	 *
	 * @param WP_User $user
	 * @param string  $new_pass
	 */
	public static function on_password_reset( $user, $new_pass = '' ) {
		if ( ! is_object( $user ) || empty( $user->user_email ) ) {
			return;
		}

		$full_name = ! empty( $user->display_name ) ? $user->display_name : ( ! empty( $user->first_name ) ? $user->first_name : $user->user_login );

		try {
			EventFlow_Email_Manager::send(
				'password_changed',
				$user->user_email,
				array(
					'participant_name'  => $full_name,
					'participant_email' => $user->user_email,
					'username'          => $user->user_login,
					'login_url'         => ( class_exists( 'EventFlow_Shortcodes' ) && method_exists( 'EventFlow_Shortcodes', 'get_auth_url' ) ) ? EventFlow_Shortcodes::get_auth_url() : ( function_exists( 'wp_login_url' ) ? wp_login_url() : home_url( '/wp-login.php' ) ),
					'reset_url'         => function_exists( 'wp_lostpassword_url' ) ? wp_lostpassword_url() : home_url( '/wp-login.php?action=lostpassword' ),
					'support_email'     => EventFlow_Email_Manager::get_sender_email(),
				),
				array(
					'idempotency_key' => 'pwd_changed_' . $user->ID . '_' . time(),
					'recipient_type'  => 'solo',
					'immediate'       => true,
				)
			);
		} catch ( \Throwable $e ) {
			// Do not block password reset
		}
	}

	/**
	 * Automatically send welcome email upon account creation
	 *
	 * @param int $user_id
	 */
	public static function on_user_register( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->user_email ) ) {
			return;
		}

		$active_event = EventFlow_Event_Manager::get_active_event();
		$event_name   = $active_event ? $active_event->title : 'SIBACOM EVENT';
		$event_date   = $active_event && $active_event->start_date ? date_i18n( get_option( 'date_format' ), strtotime( $active_event->start_date ) ) : '';

		$full_name = ! empty( $user->display_name ) ? $user->display_name : ( ! empty( $user->first_name ) ? $user->first_name : $user->user_login );

		try {
			EventFlow_Email_Manager::send(
				'account_created',
				$user->user_email,
				array(
					'participant_name'  => $full_name,
					'participant_email' => $user->user_email,
					'username'          => $user->user_login,
					'login_url'         => ( class_exists( 'EventFlow_Shortcodes' ) && method_exists( 'EventFlow_Shortcodes', 'get_auth_url' ) ) ? EventFlow_Shortcodes::get_auth_url() : ( function_exists( 'wp_login_url' ) ? wp_login_url() : home_url( '/wp-login.php' ) ),
					'dashboard_url'     => ( class_exists( 'EventFlow_Shortcodes' ) && method_exists( 'EventFlow_Shortcodes', 'get_dashboard_url' ) ) ? EventFlow_Shortcodes::get_dashboard_url() : home_url( '/participant-dashboard/' ),
					'event_name'        => $event_name,
					'event_date'        => $event_date,
				),
				array(
					'idempotency_key' => 'account_created_' . $user_id,
					'recipient_type'  => 'solo',
				)
			);
		} catch ( \Throwable $e ) {
			// Do not block user creation
		}
	}

	/**
	 * Handle AJAX Registration
	 */
	public static function handle_register() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$valid_nonce = ! empty( $nonce ) && (
			wp_verify_nonce( $nonce, 'eventflow_auth_nonce' ) ||
			wp_verify_nonce( $nonce, 'eventflow_frontend_nonce' ) ||
			wp_verify_nonce( $nonce, 'eventflow_nonce' )
		);

		if ( ! $valid_nonce ) {
			wp_send_json_error( array( 'message' => __( 'Security verification failed or session expired. Please refresh the page and try again.', 'eventflow' ) ) );
		}

		if ( ! EventFlow_Security::check_rate_limit( 'register', 5, 300 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many registration attempts. Please wait a few minutes before trying again.', 'eventflow' ) ) );
		}

		$full_name   = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
		$email       = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password    = isset( $_POST['password'] ) ? $_POST['password'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$password_cf = isset( $_POST['password_confirm'] ) ? $_POST['password_confirm'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$phone       = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$university  = isset( $_POST['university'] ) ? sanitize_text_field( wp_unslash( $_POST['university'] ) ) : '';
		$department  = isset( $_POST['department'] ) ? sanitize_text_field( wp_unslash( $_POST['department'] ) ) : '';
		$semester    = isset( $_POST['semester'] ) ? sanitize_text_field( wp_unslash( $_POST['semester'] ) ) : '';

		if ( empty( $full_name ) || empty( $email ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all required fields (Name, Email, Password).', 'eventflow' ) ) );
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'eventflow' ) ) );
		}

		if ( strlen( $password ) < 6 ) {
			wp_send_json_error( array( 'message' => __( 'Password must be at least 6 characters long.', 'eventflow' ) ) );
		}

		if ( $password !== $password_cf ) {
			wp_send_json_error( array( 'message' => __( 'Passwords do not match.', 'eventflow' ) ) );
		}

		if ( email_exists( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'An account with this email address already exists. Please log in.', 'eventflow' ) ) );
		}

		// Generate unique username based on email
		$username = sanitize_user( current( explode( '@', $email ) ), true );
		$original_username = $username;
		$i = 1;
		while ( username_exists( $username ) ) {
			$username = $original_username . $i;
			$i++;
		}

		// Create user
		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
		}

		// Set display name & role
		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $full_name,
				'first_name'   => $full_name,
				'role'         => 'eventflow_participant',
			)
		);

		// Store participant meta
		update_user_meta( $user_id, 'eventflow_phone', $phone );
		update_user_meta( $user_id, 'eventflow_university', $university );
		update_user_meta( $user_id, 'eventflow_department', $department );
		update_user_meta( $user_id, 'eventflow_semester', $semester );

		EventFlow_Security::log_audit( 'participant_registered', 'user', $user_id, array( 'email' => $email ), $user_id );

		// Send account created & welcome emails with duplicate prevention
		$active_event = EventFlow_Event_Manager::get_active_event();
		$event_name   = $active_event ? $active_event->title : get_bloginfo( 'name' );
		$event_date   = $active_event && $active_event->start_date ? date_i18n( get_option( 'date_format' ), strtotime( $active_event->start_date ) ) : '';
		$reg_deadline = $active_event && $active_event->reg_end_date ? date_i18n( get_option( 'date_format' ), strtotime( $active_event->reg_end_date ) ) : '';

		try {
			$login_page_url = ( class_exists( 'EventFlow_Shortcodes' ) && method_exists( 'EventFlow_Shortcodes', 'get_auth_url' ) ) ? EventFlow_Shortcodes::get_auth_url() : ( function_exists( 'wp_login_url' ) ? wp_login_url() : home_url( '/wp-login.php' ) );
			$dash_page_url  = ( class_exists( 'EventFlow_Shortcodes' ) && method_exists( 'EventFlow_Shortcodes', 'get_dashboard_url' ) ) ? EventFlow_Shortcodes::get_dashboard_url() : home_url( '/participant-dashboard/' );

			EventFlow_Email_Manager::send(
				'account_created',
				$email,
				array(
					'participant_name'  => $full_name,
					'participant_email' => $email,
					'username'          => $username,
					'login_url'         => $login_page_url,
					'dashboard_url'     => $dash_page_url,
					'event_name'        => $event_name,
					'event_date'        => $event_date,
				),
				array(
					'idempotency_key' => 'account_created_' . $user_id,
					'recipient_type'  => 'solo',
				)
			);

			EventFlow_Email_Manager::send(
				'welcome_email',
				$email,
				array(
					'participant_name'      => $full_name,
					'participant_email'     => $email,
					'username'              => $username,
					'login_url'             => $login_page_url,
					'dashboard_url'         => $dash_page_url,
					'event_name'            => $event_name,
					'registration_deadline' => $reg_deadline,
				),
				array(
					'idempotency_key' => 'welcome_email_' . $user_id,
					'recipient_type'  => 'solo',
				)
			);
		} catch ( \Throwable $e ) {
			// Prevent email infrastructure error from blocking user account creation
		}

		// Auto login
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		$redirect_url = EventFlow_Shortcodes::get_dashboard_url();

		wp_send_json_success(
			array(
				'message'  => __( 'Registration successful! Redirecting to your dashboard...', 'eventflow' ),
				'redirect' => $redirect_url,
			)
		);
	}

	/**
	 * Check if an account or IP address is currently locked out
	 *
	 * @param string $identifier
	 * @param string $ip
	 * @return array
	 */
	public static function check_login_lockout( $identifier, $ip ) {
		global $wpdb;
		$norm_id = sanitize_user( strtolower( trim( $identifier ) ), true );
		$now     = current_time( 'mysql' );

		// 1. Check database table if available
		$table = EventFlow_DB::table( 'login_attempts' );
		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );

		if ( $table_exists === $table ) {
			$lock = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} 
					 WHERE (identifier = %s OR ip_address = %s) 
					   AND status = 'locked' 
					   AND locked_until > %s 
					 ORDER BY id DESC LIMIT 1",
					$norm_id,
					$ip,
					$now
				)
			);

			if ( $lock ) {
				$remaining = max( 1, (int) ceil( ( strtotime( $lock->locked_until ) - strtotime( $now ) ) / 60 ) );
				return array(
					'locked'            => true,
					'remaining_minutes' => $remaining,
					'record_id'         => $lock->id,
				);
			}
		}

		// 2. Transient fallback for caching/mock environments
		$trans_key_id = 'ef_lockout_' . md5( $norm_id );
		$trans_key_ip = 'ef_lockout_' . md5( $ip );
		$until_id     = (int) get_transient( $trans_key_id );
		$until_ip     = (int) get_transient( $trans_key_ip );
		$until        = max( $until_id, $until_ip );

		if ( $until > time() ) {
			$remaining = max( 1, (int) ceil( ( $until - time() ) / 60 ) );
			return array(
				'locked'            => true,
				'remaining_minutes' => $remaining,
				'record_id'         => 0,
			);
		}

		return array( 'locked' => false, 'remaining_minutes' => 0 );
	}

	/**
	 * Record a failed login attempt with automatic threshold lockout
	 *
	 * @param string $identifier
	 * @param string $ip
	 * @param int    $user_id
	 * @return array
	 */
	public static function record_failed_attempt( $identifier, $ip, $user_id = 0 ) {
		global $wpdb;

		$norm_id          = sanitize_user( strtolower( trim( $identifier ) ), true );
		$max_attempts     = (int) get_option( 'eventflow_max_login_attempts', 5 );
		$lockout_duration = (int) get_option( 'eventflow_lockout_duration', 15 ); // Minutes
		if ( $max_attempts < 1 ) {
			$max_attempts = 5;
		}
		if ( $lockout_duration < 1 ) {
			$lockout_duration = 15;
		}

		$now       = current_time( 'mysql' );
		$table     = EventFlow_DB::table( 'login_attempts' );
		$is_locked = false;
		$attempts  = 1;

		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );

		if ( $table_exists === $table ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE identifier = %s ORDER BY id DESC LIMIT 1",
					$norm_id
				)
			);

			if ( $row ) {
				// If previously locked but time passed, reset
				if ( 'locked' === $row->status && strtotime( $row->locked_until ) <= strtotime( $now ) ) {
					$attempts = 1;
				} else {
					$attempts = (int) $row->attempt_count + 1;
				}

				if ( $attempts >= $max_attempts ) {
					$is_locked    = true;
					$locked_until = date( 'Y-m-d H:i:s', time() + ( $lockout_duration * 60 ) );
					$wpdb->update(
						$table,
						array(
							'attempt_count'   => $attempts,
							'status'          => 'locked',
							'last_attempt_at' => $now,
							'locked_until'    => $locked_until,
							'updated_at'      => $now,
							'ip_address'      => $ip,
						),
						array( 'id' => $row->id )
					);
				} else {
					$wpdb->update(
						$table,
						array(
							'attempt_count'   => $attempts,
							'last_attempt_at' => $now,
							'updated_at'      => $now,
							'ip_address'      => $ip,
						),
						array( 'id' => $row->id )
					);
				}
			} else {
				$attempts = 1;
				if ( $attempts >= $max_attempts ) {
					$is_locked    = true;
					$locked_until = date( 'Y-m-d H:i:s', time() + ( $lockout_duration * 60 ) );
					$wpdb->insert(
						$table,
						array(
							'identifier'      => $norm_id,
							'user_id'         => $user_id ? $user_id : null,
							'ip_address'      => $ip,
							'attempt_count'   => 1,
							'last_attempt_at' => $now,
							'locked_until'    => $locked_until,
							'status'          => 'locked',
							'created_at'      => $now,
							'updated_at'      => $now,
						)
					);
				} else {
					$wpdb->insert(
						$table,
						array(
							'identifier'      => $norm_id,
							'user_id'         => $user_id ? $user_id : null,
							'ip_address'      => $ip,
							'attempt_count'   => 1,
							'last_attempt_at' => $now,
							'status'          => 'active',
							'created_at'      => $now,
							'updated_at'      => $now,
						)
					);
				}
			}
		}

		// Also set transients for fallback environments
		$trans_key_id = 'ef_attempts_' . md5( $norm_id );
		$cur_attempts = (int) get_transient( $trans_key_id ) + 1;
		set_transient( $trans_key_id, $cur_attempts, $lockout_duration * 60 );

		if ( $cur_attempts >= $max_attempts ) {
			$is_locked = true;
			set_transient( 'ef_lockout_' . md5( $norm_id ), time() + ( $lockout_duration * 60 ), $lockout_duration * 60 );
			set_transient( 'ef_lockout_' . md5( $ip ), time() + ( $lockout_duration * 60 ), $lockout_duration * 60 );
		}

		if ( $is_locked ) {
			EventFlow_Security::log_audit(
				'account_locked',
				'user',
				$user_id ? $user_id : $norm_id,
				array(
					'ip'               => $ip,
					'lockout_duration' => $lockout_duration,
					'attempts'         => $attempts,
				),
				$user_id ? $user_id : null
			);
			return array(
				'locked'             => true,
				'lockout_duration'   => $lockout_duration,
				'remaining_attempts' => 0,
			);
		}

		$remaining_attempts = max( 1, $max_attempts - $attempts );
		EventFlow_Security::log_audit(
			'login_failed',
			'user',
			$user_id ? $user_id : $norm_id,
			array(
				'ip'        => $ip,
				'attempt'   => $attempts,
				'remaining' => $remaining_attempts,
			),
			$user_id ? $user_id : null
		);

		return array(
			'locked'             => false,
			'lockout_duration'   => $lockout_duration,
			'remaining_attempts' => $remaining_attempts,
		);
	}

	/**
	 * Reset failed login attempts upon successful authentication
	 *
	 * @param string $identifier
	 * @param string $ip
	 */
	public static function reset_failed_attempts( $identifier, $ip ) {
		global $wpdb;
		$norm_id = sanitize_user( strtolower( trim( $identifier ) ), true );
		$table   = EventFlow_DB::table( 'login_attempts' );

		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
		if ( $table_exists === $table ) {
			$wpdb->update(
				$table,
				array(
					'attempt_count' => 0,
					'status'        => 'active',
					'locked_until'  => null,
					'updated_at'    => current_time( 'mysql' ),
				),
				array( 'identifier' => $norm_id )
			);
			$wpdb->update(
				$table,
				array(
					'attempt_count' => 0,
					'status'        => 'active',
					'locked_until'  => null,
					'updated_at'    => current_time( 'mysql' ),
				),
				array( 'ip_address' => $ip )
			);
		}

		delete_transient( 'ef_attempts_' . md5( $norm_id ) );
		delete_transient( 'ef_lockout_' . md5( $norm_id ) );
		delete_transient( 'ef_lockout_' . md5( $ip ) );
	}

	/**
	 * Get list of currently locked accounts for admin review
	 *
	 * @param int $limit
	 * @return array
	 */
	public static function get_locked_accounts( $limit = 50 ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'login_attempts' );
		$now   = current_time( 'mysql' );

		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
		if ( $table_exists !== $table ) {
			return array();
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, u.display_name, u.user_email 
				 FROM {$table} l 
				 LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
				 WHERE l.status = 'locked' AND l.locked_until > %s 
				 ORDER BY l.id DESC LIMIT %d",
				$now,
				absint( $limit )
			)
		);
	}

	/**
	 * Unlock a locked account manually by an administrator
	 *
	 * @param int|string $id_or_identifier
	 * @param int        $admin_id
	 * @return bool
	 */
	public static function unlock_account( $id_or_identifier, $admin_id = 0 ) {
		global $wpdb;

		if ( ! $admin_id ) {
			$admin_id = get_current_user_id();
		}

		$table = EventFlow_DB::table( 'login_attempts' );
		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );

		if ( $table_exists === $table ) {
			if ( is_numeric( $id_or_identifier ) ) {
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $id_or_identifier ) ) );
			} else {
				$norm_id = sanitize_user( strtolower( trim( $id_or_identifier ) ), true );
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE identifier = %s LIMIT 1", $norm_id ) );
			}

			if ( $row ) {
				$wpdb->update(
					$table,
					array(
						'attempt_count' => 0,
						'status'        => 'unlocked',
						'locked_until'  => null,
						'updated_at'    => current_time( 'mysql' ),
					),
					array( 'id' => $row->id )
				);

				delete_transient( 'ef_attempts_' . md5( $row->identifier ) );
				delete_transient( 'ef_lockout_' . md5( $row->identifier ) );
				delete_transient( 'ef_lockout_' . md5( $row->ip_address ) );

				EventFlow_Security::log_audit(
					'account_unlocked',
					'user',
					$row->user_id ? $row->user_id : $row->identifier,
					array(
						'identifier' => $row->identifier,
						'unlocked_by' => $admin_id,
					),
					$admin_id
				);
				return true;
			}
		}

		return false;
	}

	/**
	 * Handle AJAX Login with Security & Rate Limiting
	 */
	public static function handle_login() {
		$client_ip = EventFlow_Security::get_client_ip();
		$log       = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
		$pwd       = isset( $_POST['pwd'] ) ? $_POST['pwd'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$remember  = ! empty( $_POST['rememberme'] );

		if ( empty( $log ) || empty( $pwd ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your username/email and password.', 'eventflow' ) ) );
			return;
		}

		// 1. Check account / IP lockout state
		$lock_check = self::check_login_lockout( $log, $client_ip );
		if ( $lock_check['locked'] ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: minutes remaining */
						__( 'Access temporarily locked due to multiple failed login attempts. Please try again in %d minute(s) or contact an administrator.', 'eventflow' ),
						$lock_check['remaining_minutes']
					),
					'locked'  => true,
				)
			);
			return;
		}

		// 2. Rate limiting check (against rapid automated bot flooding)
		if ( ! EventFlow_Security::check_rate_limit( 'login', 12, 300 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests from this network. Please wait a few minutes before trying again.', 'eventflow' ) ) );
			return;
		}

		// Security nonce verification: accept auth nonce, frontend nonce, or general nonce.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$nonce_verified = ! empty( $nonce ) && (
			wp_verify_nonce( $nonce, 'eventflow_auth_nonce' ) ||
			wp_verify_nonce( $nonce, 'eventflow_frontend_nonce' ) ||
			wp_verify_nonce( $nonce, 'eventflow_nonce' )
		);

		// Resolve user_login if user entered an email address
		$user_login = $log;
		$user_obj   = null;
		if ( is_email( $log ) ) {
			$user_obj = get_user_by( 'email', $log );
			if ( $user_obj ) {
				$user_login = $user_obj->user_login;
			}
		} else {
			$user_obj = get_user_by( 'login', $log );
		}

		$credentials = array(
			'user_login'    => $user_login,
			'user_password' => $pwd,
			'remember'      => $remember,
		);

		$secure_cookie = is_ssl() || ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] );
		$user          = wp_signon( $credentials, $secure_cookie );

		if ( is_wp_error( $user ) ) {
			// Record failed attempt and compute remaining attempts or lockout
			$user_id_ref = $user_obj ? $user_obj->ID : 0;
			$attempt_res = self::record_failed_attempt( $log, $client_ip, $user_id_ref );

			if ( $attempt_res['locked'] ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
							/* translators: %d: minutes of lockout */
							__( 'Too many failed login attempts. This account is temporarily locked for %d minutes for security.', 'eventflow' ),
							$attempt_res['lockout_duration']
						),
						'locked'  => true,
					)
				);
				return;
			}

			// Security: Never reveal whether an email or username exists
			wp_send_json_error(
				array(
					'message'   => sprintf(
						/* translators: %d: remaining attempts */
						__( 'Invalid email or password. You have %d attempt(s) remaining before temporary lockout.', 'eventflow' ),
						$attempt_res['remaining_attempts']
					),
					'remaining' => $attempt_res['remaining_attempts'],
				)
			);
			return;
		}

		// Successful login: reset failed attempts counter immediately
		self::reset_failed_attempts( $log, $client_ip );

		EventFlow_Security::log_audit( 'user_logged_in', 'user', $user->ID, array( 'ip' => $client_ip ), $user->ID );

		// Determine role-based redirect URL
		$redirect_url = self::get_user_redirect_url( $user );

		// Optional successful login email trigger
		$send_login_alert = get_option( 'eventflow_alert_on_login', 'no' );
		if ( 'yes' === $send_login_alert ) {
			try {
				$active_event = EventFlow_Event_Manager::get_active_event();
				EventFlow_Email_Manager::send(
					'successful_login',
					$user->user_email,
					array(
						'participant_name' => $user->display_name,
						'event_name'       => $active_event ? $active_event->title : get_bloginfo( 'name' ),
					)
				);
			} catch ( \Throwable $e ) {
				// Prevent email sending issue from breaking login
			}
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Login successful! Redirecting to your dashboard...', 'eventflow' ),
				'redirect' => $redirect_url,
			)
		);
	}

	/**
	 * Handle Secure Logout Request
	 */
	public static function handle_logout() {
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : ( isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '' );
		
		$current_user_id = get_current_user_id();
		if ( $current_user_id ) {
			EventFlow_Security::log_audit( 'user_logged_out', 'user', $current_user_id, array( 'ip' => EventFlow_Security::get_client_ip() ), $current_user_id );
		}

		wp_logout();

		$login_url = ( class_exists( 'EventFlow_Shortcodes' ) && method_exists( 'EventFlow_Shortcodes', 'get_auth_url' ) )
			? EventFlow_Shortcodes::get_auth_url()
			: home_url( '/participant-login/' );

		if ( wp_doing_ajax() ) {
			wp_send_json_success(
				array(
					'message'  => __( 'You have been successfully signed out.', 'eventflow' ),
					'redirect' => $login_url,
				)
			);
		} else {
			wp_safe_redirect( add_query_arg( 'logged_out', '1', $login_url ) );
			exit;
		}
	}

	/**
	 * Handle Forgot Password Request
	 */
	public static function handle_forgot_password() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$valid_nonce = ! empty( $nonce ) && (
			wp_verify_nonce( $nonce, 'eventflow_auth_nonce' ) ||
			wp_verify_nonce( $nonce, 'eventflow_frontend_nonce' ) ||
			wp_verify_nonce( $nonce, 'eventflow_nonce' )
		);

		if ( ! $valid_nonce ) {
			wp_send_json_error( array( 'message' => __( 'Security verification failed or session expired. Please refresh the page and try again.', 'eventflow' ) ) );
		}

		if ( ! EventFlow_Security::check_rate_limit( 'forgot_pwd', 4, 600 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait before requesting another reset.', 'eventflow' ) ) );
		}

		$user_input = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
		if ( empty( $user_input ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your registered email address.', 'eventflow' ) ) );
		}

		$user_data = is_email( $user_input ) ? get_user_by( 'email', $user_input ) : get_user_by( 'login', $user_input );
		if ( ! $user_data ) {
			// For security, do not disclose whether user exists
			wp_send_json_success( array( 'message' => __( 'If an account matches that email, a password reset link has been dispatched.', 'eventflow' ) ) );
		}

		$key = get_password_reset_key( $user_data );
		if ( is_wp_error( $key ) ) {
			wp_send_json_error( array( 'message' => __( 'Unable to initiate password reset at this time.', 'eventflow' ) ) );
		}

		$reset_url = network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_data->user_login ), 'login' );
		$subject   = __( 'Password Reset Request', 'eventflow' );

		if ( class_exists( 'EventFlow_Email_Manager' ) ) {
			EventFlow_Email_Manager::send(
				'password_reset',
				$user_data->user_email,
				array(
					'participant_name' => $user_data->display_name,
					'reset_url'        => $reset_url,
					'website_url'      => home_url(),
				),
				array(
					'subject'           => $subject,
					'notification_type' => 'password_reset',
					'immediate'         => true,
				)
			);
		} else {
			$message = sprintf(
				/* translators: %s: user display name */
				__( 'Hello %s,', 'eventflow' ) . "\r\n\r\n" .
				__( 'A password reset was requested for your account. Please click the link below to set a new password:', 'eventflow' ) . "\r\n\r\n" .
				"%s\r\n\r\n" .
				__( 'If you did not request this, you can safely ignore this email.', 'eventflow' ),
				$user_data->display_name,
				$reset_url
			);
			wp_mail( $user_data->user_email, wp_specialchars_decode( $subject ), $message );
		}

		wp_send_json_success( array( 'message' => __( 'Password reset link has been sent to your registered email address.', 'eventflow' ) ) );
	}

	/**
	 * Get role-based redirect URL for a user
	 *
	 * @param WP_User|int|null $user
	 * @return string
	 */
	public static function get_user_redirect_url( $user = null ) {
		if ( ! $user ) {
			$user = wp_get_current_user();
		} elseif ( is_numeric( $user ) ) {
			$user = get_userdata( $user );
		}

		if ( ! $user || ! is_object( $user ) || empty( $user->ID ) ) {
			return home_url( '/' );
		}

		$roles = (array) $user->roles;

		// 1. Finance / Verification Staff -> Payments View
		if ( in_array( 'eventflow_payment_staff', $roles, true ) || in_array( 'eventflow_verification_staff', $roles, true ) ) {
			return add_query_arg( 'view', 'payments', EventFlow_Shortcodes::get_staff_dashboard_url() );
		}

		// 2. Team Management Staff -> Teams View
		if ( in_array( 'eventflow_team_staff', $roles, true ) ) {
			return add_query_arg( 'view', 'teams', EventFlow_Shortcodes::get_staff_dashboard_url() );
		}

		// 3. Technical Team -> Competitions / Activities
		if ( in_array( 'eventflow_tech_team', $roles, true ) ) {
			return add_query_arg( 'view', 'activities', EventFlow_Shortcodes::get_staff_dashboard_url() );
		}

		// 4. Registration Staff -> Registrations list
		if ( in_array( 'eventflow_registration_staff', $roles, true ) ) {
			return add_query_arg( 'view', 'registrations', EventFlow_Shortcodes::get_staff_dashboard_url() );
		}

		// 5. Event Administrator & Super Administrator -> Overview Dashboard
		if ( in_array( 'eventflow_super_admin', $roles, true ) || in_array( 'eventflow_event_admin', $roles, true ) || in_array( 'administrator', $roles, true ) ) {
			return EventFlow_Shortcodes::get_staff_dashboard_url();
		}

		// 6. Participant & Team Member -> Participant Portal
		if ( in_array( 'eventflow_participant', $roles, true ) || in_array( 'eventflow_team_member', $roles, true ) ) {
			return EventFlow_Shortcodes::get_dashboard_url();
		}

		// Default fallback
		if ( user_can( $user, 'manage_options' ) || user_can( $user, 'eventflow_staff_access' ) ) {
			return EventFlow_Shortcodes::get_staff_dashboard_url();
		}

		return EventFlow_Shortcodes::get_dashboard_url();
	}
}
