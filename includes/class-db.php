<?php
/**
 * EventFlow Database & Migration Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_DB {

	const DB_VERSION = '5.0.2';

	/**
	 * Get table name with WordPress prefix
	 *
	 * @param string $table
	 * @return string
	 */
	public static function table( $table ) {
		global $wpdb;
		return $wpdb->prefix . 'eventflow_' . $table;
	}

	/**
	 * Plugin activation routine
	 */
	public static function activate() {
		self::create_tables();
		self::migrate_tables();
		self::create_secure_upload_dir();

		// Register roles & caps
		if ( class_exists( 'EventFlow_Roles' ) ) {
			EventFlow_Roles::register_roles();
		}

		// Seed initial templates, default event and pages
		self::seed_default_email_templates();
		self::seed_default_event();
		self::seed_default_pages();
		self::seed_sample_registrations();

		// Initialize default email options if not set
		add_option( 'eventflow_email_from_name', 'SIBACOM EVENT' );
		add_option( 'eventflow_email_from_address', 'info@portal.sibacom.tech' );
		add_option( 'eventflow_email_reply_to', 'info@portal.sibacom.tech' );
		add_option( 'eventflow_mailer_mode', 'eventflow_smtp' );
		add_option( 'eventflow_smtp_port', 587 );
		add_option( 'eventflow_smtp_encryption', 'tls' );
		add_option( 'eventflow_smtp_auth', 'yes' );
		add_option( 'eventflow_smtp_timeout', 15 );
		add_option( 'eventflow_email_queue_enabled', 'yes' );
		add_option( 'eventflow_email_max_retries', 3 );
		add_option( 'eventflow_email_batch_size', 25 );

		// Payment method active switches
		add_option( 'eventflow_bank_enabled', 'yes' );
		add_option( 'eventflow_jazzcash_enabled', 'yes' );
		add_option( 'eventflow_easypaisa_enabled', 'yes' );

		// Schedule background queue worker
		if ( class_exists( 'EventFlow_Email_Queue' ) ) {
			EventFlow_Email_Queue::schedule_cron();
		}

		// Enable full-page standalone app mode and homepage takeover by default
		update_option( 'eventflow_standalone_mode', 1 );
		update_option( 'eventflow_takeover_homepage', 1 );

		update_option( 'eventflow_db_version', self::DB_VERSION );
		set_transient( 'eventflow_activation_notice', 1, 30 );
		flush_rewrite_rules();
	}

	/**
	 * Run upgrade routine if plugin database version is outdated
	 */
	public static function maybe_upgrade() {
		$saved_version = get_option( 'eventflow_db_version', '0.0.0' );
		if ( version_compare( $saved_version, self::DB_VERSION, '<' ) ) {
			self::create_tables();
			self::migrate_tables();
			update_option( 'eventflow_db_version', self::DB_VERSION );
		}
	}

	/**
	 * Run incremental schema migrations for existing databases
	 */
	/**
	 * Run incremental schema migrations for existing databases
	 */
	public static function migrate_tables() {
		global $wpdb;

		// 1. Check email_logs table
		$table_logs = self::table( 'email_logs' );
		$exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_logs ) );
		if ( $exists === $table_logs ) {
			$columns = method_exists( $wpdb, 'get_col' ) ? (array) $wpdb->get_col( "DESC {$table_logs}", 0 ) : array();
			if ( ! in_array( 'sender_email', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_logs} ADD COLUMN sender_email varchar(191) NOT NULL DEFAULT 'info@portal.sibacom.tech', ADD KEY sender_email (sender_email(191))" );
			}
			if ( ! in_array( 'recipient_type', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_logs} ADD COLUMN recipient_type varchar(50) NOT NULL DEFAULT 'solo', ADD KEY recipient_type (recipient_type)" );
			}
			if ( ! in_array( 'body', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_logs} ADD COLUMN body longtext DEFAULT NULL" );
			}
			if ( ! in_array( 'headers', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_logs} ADD COLUMN headers text DEFAULT NULL" );
			}
			if ( ! in_array( 'registration_id', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_logs} ADD COLUMN registration_id varchar(64) DEFAULT NULL, ADD KEY registration_id (registration_id)" );
			}
			if ( ! in_array( 'retry_count', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_logs} ADD COLUMN retry_count int(11) NOT NULL DEFAULT 0" );
			}
			if ( ! in_array( 'provider_response', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_logs} ADD COLUMN provider_response text DEFAULT NULL" );
			}
			if ( ! in_array( 'dispatch_id', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_logs} ADD COLUMN dispatch_id varchar(64) DEFAULT NULL, ADD KEY dispatch_id (dispatch_id)" );
			}
			if ( ! in_array( 'workflow', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_logs} ADD COLUMN workflow varchar(64) DEFAULT NULL, ADD KEY workflow (workflow)" );
			}
			if ( ! in_array( 'created_at', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_logs} ADD COLUMN created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP" );
			}
		}

		// 2. Check email_queue table
		$table_queue = self::table( 'email_queue' );
		$q_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_queue ) );
		if ( $q_exists === $table_queue ) {
			$q_cols = method_exists( $wpdb, 'get_col' ) ? (array) $wpdb->get_col( "DESC {$table_queue}", 0 ) : array();
			if ( ! in_array( 'recipient_type', $q_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_queue} ADD COLUMN recipient_type varchar(50) NOT NULL DEFAULT 'solo', ADD KEY recipient_type (recipient_type)" );
			}
			if ( ! in_array( 'dispatch_id', $q_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_queue} ADD COLUMN dispatch_id varchar(64) DEFAULT NULL, ADD KEY dispatch_id (dispatch_id)" );
			}
			if ( ! in_array( 'workflow', $q_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_queue} ADD COLUMN workflow varchar(64) DEFAULT NULL, ADD KEY workflow (workflow)" );
			}
			if ( ! in_array( 'log_id', $q_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_queue} ADD COLUMN log_id bigint(20) unsigned DEFAULT NULL, ADD KEY log_id (log_id)" );
			}
		}

		// 3. Check events table for comprehensive configuration fields
		$table_events = self::table( 'events' );
		$ev_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_events ) );
		if ( $ev_exists === $table_events ) {
			$ev_cols = method_exists( $wpdb, 'get_col' ) ? (array) $wpdb->get_col( "DESC {$table_events}", 0 ) : array();
			$event_col_defs = array(
				'venue'                   => "ALTER TABLE {$table_events} ADD COLUMN venue varchar(255) DEFAULT 'Academic Block 3 & Main Auditorium, Sukkur IBA University'",
				'university_name'         => "ALTER TABLE {$table_events} ADD COLUMN university_name varchar(255) DEFAULT 'Sukkur IBA University'",
				'organizing_dept'         => "ALTER TABLE {$table_events} ADD COLUMN organizing_dept varchar(255) DEFAULT 'Department of Computer Science & SISC'",
				'website_url'             => "ALTER TABLE {$table_events} ADD COLUMN website_url varchar(500) DEFAULT 'https://portal.sibacom.tech'",
				'start_time'              => "ALTER TABLE {$table_events} ADD COLUMN start_time varchar(20) DEFAULT '09:00 AM'",
				'end_time'                => "ALTER TABLE {$table_events} ADD COLUMN end_time varchar(20) DEFAULT '06:00 PM'",
				'reporting_time'          => "ALTER TABLE {$table_events} ADD COLUMN reporting_time varchar(50) DEFAULT '08:30 AM'",
				'allow_solo'              => "ALTER TABLE {$table_events} ADD COLUMN allow_solo tinyint(1) NOT NULL DEFAULT 1",
				'allow_team'              => "ALTER TABLE {$table_events} ADD COLUMN allow_team tinyint(1) NOT NULL DEFAULT 1",
				'min_team_size'           => "ALTER TABLE {$table_events} ADD COLUMN min_team_size int(11) NOT NULL DEFAULT 2",
				'max_team_size'           => "ALTER TABLE {$table_events} ADD COLUMN max_team_size int(11) NOT NULL DEFAULT 5",
				'min_solo_registrations'  => "ALTER TABLE {$table_events} ADD COLUMN min_solo_registrations int(11) NOT NULL DEFAULT 0",
				'max_total_registrations' => "ALTER TABLE {$table_events} ADD COLUMN max_total_registrations int(11) NOT NULL DEFAULT 0",
				'max_team_registrations'  => "ALTER TABLE {$table_events} ADD COLUMN max_team_registrations int(11) NOT NULL DEFAULT 0",
				'allow_reg_edit'          => "ALTER TABLE {$table_events} ADD COLUMN allow_reg_edit tinyint(1) NOT NULL DEFAULT 1",
				'allow_proof_reupload'    => "ALTER TABLE {$table_events} ADD COLUMN allow_proof_reupload tinyint(1) NOT NULL DEFAULT 1",
				'auto_close_registration' => "ALTER TABLE {$table_events} ADD COLUMN auto_close_registration tinyint(1) NOT NULL DEFAULT 1",
				'pricing_model'           => "ALTER TABLE {$table_events} ADD COLUMN pricing_model varchar(30) NOT NULL DEFAULT 'per_participant'",
				'solo_fee_enabled'        => "ALTER TABLE {$table_events} ADD COLUMN solo_fee_enabled tinyint(1) NOT NULL DEFAULT 1",
				'solo_fee'                => "ALTER TABLE {$table_events} ADD COLUMN solo_fee decimal(10,2) NOT NULL DEFAULT 1000.00",
				'solo_discount'           => "ALTER TABLE {$table_events} ADD COLUMN solo_discount decimal(10,2) NOT NULL DEFAULT 0.00",
				'team_fee_enabled'        => "ALTER TABLE {$table_events} ADD COLUMN team_fee_enabled tinyint(1) NOT NULL DEFAULT 1",
				'team_fee_per_member'     => "ALTER TABLE {$table_events} ADD COLUMN team_fee_per_member decimal(10,2) NOT NULL DEFAULT 500.00",
				'team_fixed_fee'          => "ALTER TABLE {$table_events} ADD COLUMN team_fixed_fee decimal(10,2) NOT NULL DEFAULT 2000.00",
				'team_min_amount'         => "ALTER TABLE {$table_events} ADD COLUMN team_min_amount decimal(10,2) NOT NULL DEFAULT 500.00",
				'team_discount'           => "ALTER TABLE {$table_events} ADD COLUMN team_discount decimal(10,2) NOT NULL DEFAULT 0.00",
			);
			foreach ( $event_col_defs as $col_name => $sql ) {
				if ( ! in_array( $col_name, $ev_cols, true ) ) {
					$wpdb->query( $sql );
				}
			}
		}

		// 4. Check activities table for fee_calculation, payment_proof_required, and form_fields
		$table_activities = self::table( 'activities' );
		$act_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_activities ) );
		if ( $act_exists === $table_activities ) {
			$act_cols = method_exists( $wpdb, 'get_col' ) ? (array) $wpdb->get_col( "DESC {$table_activities}", 0 ) : array();
			if ( ! in_array( 'fee_calculation', $act_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_activities} ADD COLUMN fee_calculation varchar(20) NOT NULL DEFAULT 'per_team'" );
			}
			if ( ! in_array( 'payment_proof_required', $act_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_activities} ADD COLUMN payment_proof_required tinyint(1) NOT NULL DEFAULT 1" );
			}
			if ( ! in_array( 'form_fields', $act_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_activities} ADD COLUMN form_fields longtext DEFAULT NULL" );
			}
		}

		// 5. Check registrations table for fee snapshot and extra fields
		$table_reg = self::table( 'registrations' );
		$reg_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_reg ) );
		if ( $reg_exists === $table_reg ) {
			$reg_cols = method_exists( $wpdb, 'get_col' ) ? (array) $wpdb->get_col( "DESC {$table_reg}", 0 ) : array();
			if ( ! in_array( 'admin_notes', $reg_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_reg} ADD COLUMN admin_notes text DEFAULT NULL" );
			}
			if ( ! in_array( 'rejection_reason', $reg_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_reg} ADD COLUMN rejection_reason text DEFAULT NULL" );
			}
			if ( ! in_array( 'custom_fields', $reg_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_reg} ADD COLUMN custom_fields longtext DEFAULT NULL" );
			}
			if ( ! in_array( 'fee_breakdown', $reg_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_reg} ADD COLUMN fee_breakdown longtext DEFAULT NULL" );
			}
			if ( ! in_array( 'pricing_snapshot', $reg_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_reg} ADD COLUMN pricing_snapshot longtext DEFAULT NULL" );
			}
			if ( ! in_array( 'verified_by', $reg_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_reg} ADD COLUMN verified_by bigint(20) unsigned DEFAULT NULL" );
			}
			if ( ! in_array( 'verified_at', $reg_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_reg} ADD COLUMN verified_at datetime DEFAULT NULL" );
			}
			if ( in_array( 'team_name', $reg_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_reg} MODIFY COLUMN team_name varchar(191) DEFAULT ''" );
			}
		}

		// 6. Check/create status_logs table
		$table_status_logs = self::table( 'status_logs' );
		$status_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_status_logs ) );
		if ( $status_exists !== $table_status_logs ) {
			if ( file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}
			if ( function_exists( 'dbDelta' ) ) {
				$charset_collate = $wpdb->get_charset_collate();
				$sql_status_logs = "CREATE TABLE {$table_status_logs} (
					id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					registration_id bigint(20) unsigned NOT NULL,
					old_status varchar(50) NOT NULL,
					new_status varchar(50) NOT NULL,
					status_type varchar(20) NOT NULL DEFAULT 'application',
					reason text DEFAULT NULL,
					note text DEFAULT NULL,
					changed_by bigint(20) unsigned DEFAULT NULL,
					created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY  (id),
					KEY registration_id (registration_id),
					KEY status_type (status_type)
				) {$charset_collate};";
				dbDelta( $sql_status_logs );
			}
		}

		// 7. Check/create dynamic forms table
		$table_forms = self::table( 'forms' );
		$forms_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_forms ) );
		if ( $forms_exists !== $table_forms ) {
			if ( file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}
			if ( function_exists( 'dbDelta' ) ) {
				$charset_collate = $wpdb->get_charset_collate();
				$sql_forms = "CREATE TABLE {$table_forms} (
					id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					title varchar(255) NOT NULL,
					activity_id bigint(20) unsigned NOT NULL DEFAULT 0,
					event_id bigint(20) unsigned NOT NULL DEFAULT 0,
					fields longtext NOT NULL,
					status varchar(50) NOT NULL DEFAULT 'published',
					created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY  (id),
					KEY activity_id (activity_id),
					KEY event_id (event_id),
					KEY status (status)
				) {$charset_collate};";
				dbDelta( $sql_forms );
			}
		}

		// 8. Check team_members table for academic_year and nullable/default father_name
		$table_members = self::table( 'team_members' );
		$mem_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_members ) );
		if ( $mem_exists === $table_members ) {
			$mem_cols = method_exists( $wpdb, 'get_col' ) ? (array) $wpdb->get_col( "DESC {$table_members}", 0 ) : array();
			if ( ! in_array( 'academic_year', $mem_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_members} ADD COLUMN academic_year varchar(100) DEFAULT NULL" );
			}
			if ( in_array( 'father_name', $mem_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_members} MODIFY COLUMN father_name varchar(191) DEFAULT ''" );
			}
		}

		// 9. Check/create login_attempts table
		$table_login = self::table( 'login_attempts' );
		$login_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_login ) );
		if ( $login_exists !== $table_login ) {
			if ( file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}
			if ( function_exists( 'dbDelta' ) ) {
				$charset_collate = $wpdb->get_charset_collate();
				$sql_login_attempts = "CREATE TABLE {$table_login} (
					id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					identifier varchar(191) NOT NULL,
					user_id bigint(20) unsigned DEFAULT NULL,
					ip_address varchar(100) NOT NULL,
					attempt_count int(11) NOT NULL DEFAULT 1,
					last_attempt_at datetime NOT NULL,
					locked_until datetime DEFAULT NULL,
					status varchar(50) NOT NULL DEFAULT 'active',
					created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY  (id),
					KEY identifier (identifier(191)),
					KEY ip_address (ip_address(100)),
					KEY status (status),
					KEY locked_until (locked_until)
				) {$charset_collate};";
				dbDelta( $sql_login_attempts );
			}
		}

		// 10. Check payments table for version column
		$table_payments = self::table( 'payments' );
		$pay_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_payments ) );
		if ( $pay_exists === $table_payments ) {
			$pay_cols = method_exists( $wpdb, 'get_col' ) ? (array) $wpdb->get_col( "DESC {$table_payments}", 0 ) : array();
			if ( ! in_array( 'version', $pay_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_payments} ADD COLUMN version int(11) NOT NULL DEFAULT 1" );
			}
		}

		// 11. Check/create in-dashboard notifications table
		$table_notif = self::table( 'notifications' );
		$notif_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_notif ) );
		if ( $notif_exists !== $table_notif ) {
			if ( file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}
			if ( function_exists( 'dbDelta' ) ) {
				$charset_collate = $wpdb->get_charset_collate();
				$sql_notif = "CREATE TABLE {$table_notif} (
					id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					user_id bigint(20) unsigned NOT NULL,
					registration_id bigint(20) unsigned DEFAULT NULL,
					payment_id bigint(20) unsigned DEFAULT NULL,
					type varchar(50) NOT NULL DEFAULT 'general',
					title varchar(255) NOT NULL,
					message text NOT NULL,
					action_url varchar(500) DEFAULT NULL,
					is_read tinyint(1) NOT NULL DEFAULT 0,
					read_at datetime DEFAULT NULL,
					created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY  (id),
					KEY user_id (user_id),
					KEY is_read (is_read),
					KEY registration_id (registration_id),
					KEY type (type),
					KEY created_at (created_at)
				) {$charset_collate};";
				dbDelta( $sql_notif );
			}
		}

		// 12. Synchronize & Update Email Templates (Enforce SIBACOM EVENT branding & subjects)
		self::sync_email_templates();
	}

	/**
	 * Synchronize & Update Email Templates (Enforce SIBACOM EVENT branding & subjects)
	 */
	public static function sync_email_templates() {
		global $wpdb;
		$table = self::table( 'email_templates' );
		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
		if ( $table_exists !== $table ) {
			return;
		}

		// 1. Purge legacy branding and replace with 'SIBACOM EVENT'
		$legacy_token = 'SIB' . 'ACO';
		$rows = $wpdb->get_results( "SELECT id, template_key, name, subject, body FROM {$table}" );
		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				$needs_update = false;
				$new_subject = $row->subject;
				$new_body    = $row->body;
				$new_name    = $row->name;

				if ( stripos( $new_subject, $legacy_token ) !== false ) {
					$new_subject = preg_replace( '/\b' . $legacy_token . '\b/i', 'SIBACOM EVENT', $new_subject );
					$needs_update = true;
				}
				if ( stripos( $new_body, $legacy_token ) !== false ) {
					$new_body = preg_replace( '/\b' . $legacy_token . '\b/i', 'SIBACOM EVENT', $new_body );
					$needs_update = true;
				}
				if ( stripos( $new_name, $legacy_token ) !== false ) {
					$new_name = preg_replace( '/\b' . $legacy_token . '\b/i', 'SIBACOM EVENT', $new_name );
					$needs_update = true;
				}

				// Clean up any corrupted non-ASCII dashes or mojibake in subjects and bodies
				if ( false !== strpos( $new_subject, '–' ) || false !== strpos( $new_subject, '—' ) || false !== strpos( $new_subject, '?"' ) ) {
					$new_subject  = str_replace( array( '–', '—', '?"' ), '-', $new_subject );
					$needs_update = true;
				}
				if ( false !== strpos( $new_body, '–' ) || false !== strpos( $new_body, '—' ) || false !== strpos( $new_body, '?"' ) ) {
					$new_body     = str_replace( array( '–', '—', '?"' ), '-', $new_body );
					$needs_update = true;
				}

				if ( $needs_update ) {
					$wpdb->update(
						$table,
						array(
							'name'       => $new_name,
							'subject'    => $new_subject,
							'body'       => $new_body,
							'updated_at' => current_time( 'mysql' ),
						),
						array( 'id' => $row->id ),
						array( '%s', '%s', '%s', '%s' ),
						array( '%d' )
					);
				}
			}
		}

		// 2. Ensure all default templates exist
		$defaults = self::get_default_email_templates_data();
		foreach ( $defaults as $tpl ) {
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, subject FROM {$table} WHERE template_key = %s", $tpl['template_key'] ) );
			if ( ! $existing ) {
				$wpdb->insert(
					$table,
					array(
						'template_key'      => $tpl['template_key'],
						'name'              => $tpl['name'],
						'subject'           => $tpl['subject'],
						'body'              => $tpl['body'],
						'placeholders_help' => isset( $tpl['placeholders_help'] ) ? $tpl['placeholders_help'] : '',
						'is_active'         => 1,
						'created_at'        => current_time( 'mysql' ),
						'updated_at'        => current_time( 'mysql' ),
					),
					array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
				);
			}
		}
	}

	/**
	 * Plugin deactivation routine
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Create or update custom database tables
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		if ( file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			return;
		}

		// 1. Events Table
		$table_events = self::table( 'events' );
		$sql_events = "CREATE TABLE {$table_events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			slug varchar(200) NOT NULL,
			description longtext DEFAULT NULL,
			banner_url varchar(500) DEFAULT NULL,
			logo_url varchar(500) DEFAULT NULL,
			venue varchar(255) DEFAULT NULL,
			university_name varchar(255) DEFAULT 'Sukkur IBA University',
			organizing_dept varchar(255) DEFAULT 'Department of Computer Science',
			website_url varchar(500) DEFAULT NULL,
			start_date datetime DEFAULT NULL,
			end_date datetime DEFAULT NULL,
			reg_start_date datetime DEFAULT NULL,
			reg_end_date datetime DEFAULT NULL,
			start_time varchar(50) DEFAULT NULL,
			end_time varchar(50) DEFAULT NULL,
			reporting_time varchar(50) DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'active',
			contact_email varchar(191) DEFAULT NULL,
			contact_phone varchar(100) DEFAULT NULL,
			allow_solo tinyint(1) NOT NULL DEFAULT 1,
			allow_team tinyint(1) NOT NULL DEFAULT 1,
			min_team_size int(11) NOT NULL DEFAULT 2,
			max_team_size int(11) NOT NULL DEFAULT 5,
			min_solo_registrations int(11) NOT NULL DEFAULT 1,
			max_total_registrations int(11) NOT NULL DEFAULT 500,
			max_team_registrations int(11) NOT NULL DEFAULT 200,
			allow_reg_edit tinyint(1) NOT NULL DEFAULT 0,
			allow_proof_reupload tinyint(1) NOT NULL DEFAULT 1,
			pricing_model varchar(50) NOT NULL DEFAULT 'per_participant',
			reg_fee decimal(10,2) NOT NULL DEFAULT 0.00,
			solo_fee_enabled tinyint(1) NOT NULL DEFAULT 1,
			solo_fee decimal(10,2) NOT NULL DEFAULT 500.00,
			solo_discount decimal(10,2) NOT NULL DEFAULT 0.00,
			team_fee_enabled tinyint(1) NOT NULL DEFAULT 1,
			team_fee_per_member decimal(10,2) NOT NULL DEFAULT 500.00,
			team_fixed_fee decimal(10,2) NOT NULL DEFAULT 2000.00,
			team_discount decimal(10,2) NOT NULL DEFAULT 0.00,
			team_min_payable decimal(10,2) NOT NULL DEFAULT 500.00,
			currency varchar(10) NOT NULL DEFAULT 'PKR',
			accepted_payment_methods varchar(255) DEFAULT 'bank,jazzcash,easypaisa',
			bank_account_title varchar(255) DEFAULT NULL,
			bank_name varchar(255) DEFAULT NULL,
			bank_account_number varchar(100) DEFAULT NULL,
			bank_iban varchar(100) DEFAULT NULL,
			easypaisa_details varchar(255) DEFAULT NULL,
			jazzcash_details varchar(255) DEFAULT NULL,
			raast_id varchar(100) DEFAULT NULL,
			payment_instructions longtext DEFAULT NULL,
			payment_deadline_hours int(11) NOT NULL DEFAULT 48,
			payment_info longtext DEFAULT NULL,
			qr_code_url varchar(500) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY slug (slug(191)),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql_events );

		// 2. Activities Table
		$table_activities = self::table( 'activities' );
		$sql_activities = "CREATE TABLE {$table_activities} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			title varchar(255) NOT NULL,
			slug varchar(200) NOT NULL,
			description longtext DEFAULT NULL,
			image_url varchar(500) DEFAULT NULL,
			eligibility text DEFAULT NULL,
			reg_deadline datetime DEFAULT NULL,
			participation_type varchar(20) NOT NULL DEFAULT 'both',
			min_team_size int(11) NOT NULL DEFAULT 1,
			max_team_size int(11) NOT NULL DEFAULT 4,
			leader_counts_as_member tinyint(1) NOT NULL DEFAULT 1,
			reg_fee decimal(10,2) NOT NULL DEFAULT 0.00,
			fee_calculation varchar(20) NOT NULL DEFAULT 'per_team',
			payment_proof_required tinyint(1) NOT NULL DEFAULT 1,
			max_participants int(11) NOT NULL DEFAULT 0,
			current_participants int(11) NOT NULL DEFAULT 0,
			instructions text DEFAULT NULL,
			form_fields longtext DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'published',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY event_id (event_id),
			KEY slug (slug(191)),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql_activities );

		// 3. Registrations Table
		$table_registrations = self::table( 'registrations' );
		$sql_registrations = "CREATE TABLE {$table_registrations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reg_id varchar(64) NOT NULL,
			event_id bigint(20) unsigned NOT NULL,
			activity_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			participation_type varchar(20) NOT NULL DEFAULT 'solo',
			team_name varchar(191) DEFAULT NULL,
			total_fee decimal(10,2) NOT NULL DEFAULT 0.00,
			fee_breakdown longtext DEFAULT NULL,
			pricing_snapshot longtext DEFAULT NULL,
			payment_status varchar(50) NOT NULL DEFAULT 'pending',
			application_status varchar(50) NOT NULL DEFAULT 'submitted',
			admin_notes text DEFAULT NULL,
			rejection_reason text DEFAULT NULL,
			custom_fields longtext DEFAULT NULL,
			verified_by bigint(20) unsigned DEFAULT NULL,
			verified_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY reg_id (reg_id),
			KEY event_id (event_id),
			KEY activity_id (activity_id),
			KEY user_id (user_id),
			KEY payment_status (payment_status),
			KEY application_status (application_status)
		) {$charset_collate};";
		dbDelta( $sql_registrations );

		// 4. Teams Table
		$table_teams = self::table( 'teams' );
		$sql_teams = "CREATE TABLE {$table_teams} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			registration_id bigint(20) unsigned NOT NULL,
			team_name varchar(191) NOT NULL,
			leader_user_id bigint(20) unsigned NOT NULL,
			leader_name varchar(191) NOT NULL,
			leader_email varchar(191) NOT NULL,
			leader_phone varchar(100) NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY registration_id (registration_id),
			KEY leader_user_id (leader_user_id)
		) {$charset_collate};";
		dbDelta( $sql_teams );

		// 5. Team Members Table (7 specific required fields)
		$table_members = self::table( 'team_members' );
		$sql_members = "CREATE TABLE {$table_members} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			team_id bigint(20) unsigned NOT NULL,
			registration_id bigint(20) unsigned NOT NULL,
			full_name varchar(191) NOT NULL,
			father_name varchar(191) DEFAULT '',
			semester varchar(50) NOT NULL,
			academic_year varchar(100) DEFAULT NULL,
			department varchar(191) NOT NULL,
			university varchar(191) NOT NULL,
			email varchar(191) NOT NULL,
			contact_number varchar(100) NOT NULL,
			is_leader tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY team_id (team_id),
			KEY registration_id (registration_id),
			KEY email (email(191))
		) {$charset_collate};";
		dbDelta( $sql_members );

		// 6. Payments Table
		$table_payments = self::table( 'payments' );
		$sql_payments = "CREATE TABLE {$table_payments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			registration_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			transaction_id varchar(191) NOT NULL,
			payment_method varchar(50) NOT NULL,
			amount decimal(10,2) NOT NULL DEFAULT 0.00,
			payment_date date NOT NULL,
			screenshot_url varchar(500) NOT NULL,
			screenshot_path varchar(500) NOT NULL,
			file_type varchar(50) NOT NULL,
			file_size bigint(20) unsigned NOT NULL,
			version int(11) NOT NULL DEFAULT 1,
			status varchar(50) NOT NULL DEFAULT 'under_verification',
			notes text DEFAULT NULL,
			rejection_reason text DEFAULT NULL,
			verified_by bigint(20) unsigned DEFAULT NULL,
			verified_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY registration_id (registration_id),
			KEY user_id (user_id),
			KEY transaction_id (transaction_id(191)),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql_payments );

		// 7. Payment Verification Logs Table
		$table_payment_logs = self::table( 'payment_logs' );
		$sql_payment_logs = "CREATE TABLE {$table_payment_logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			payment_id bigint(20) unsigned NOT NULL,
			registration_id bigint(20) unsigned NOT NULL,
			action varchar(100) NOT NULL,
			note text DEFAULT NULL,
			performed_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY payment_id (payment_id),
			KEY registration_id (registration_id)
		) {$charset_collate};";
		dbDelta( $sql_payment_logs );

		// 8. Email Templates Table
		$table_templates = self::table( 'email_templates' );
		$sql_templates = "CREATE TABLE {$table_templates} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			template_key varchar(100) NOT NULL,
			name varchar(191) NOT NULL,
			subject varchar(255) NOT NULL,
			body longtext NOT NULL,
			placeholders_help text DEFAULT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY template_key (template_key)
		) {$charset_collate};";
		dbDelta( $sql_templates );

		// 9. Email Logs Table
		$table_email_logs = self::table( 'email_logs' );
		$sql_email_logs = "CREATE TABLE {$table_email_logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dispatch_id varchar(64) DEFAULT NULL,
			workflow varchar(64) DEFAULT NULL,
			template_key varchar(100) NOT NULL,
			sender_email varchar(191) NOT NULL DEFAULT 'info@portal.sibacom.tech',
			recipient_email varchar(191) NOT NULL,
			recipient_name varchar(191) DEFAULT NULL,
			recipient_type varchar(50) NOT NULL DEFAULT 'solo',
			subject varchar(255) NOT NULL,
			body longtext DEFAULT NULL,
			headers text DEFAULT NULL,
			registration_id varchar(64) DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'accepted',
			retry_count int(11) NOT NULL DEFAULT 0,
			error_message text DEFAULT NULL,
			provider_response text DEFAULT NULL,
			sent_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY dispatch_id (dispatch_id),
			KEY workflow (workflow),
			KEY template_key (template_key),
			KEY sender_email (sender_email(191)),
			KEY recipient_email (recipient_email(191)),
			KEY recipient_type (recipient_type),
			KEY registration_id (registration_id),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql_email_logs );

		// 10. Announcements Table
		$table_announcements = self::table( 'announcements' );
		$sql_announcements = "CREATE TABLE {$table_announcements} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			activity_id bigint(20) unsigned DEFAULT NULL,
			title varchar(255) NOT NULL,
			content text NOT NULL,
			target_audience varchar(50) NOT NULL DEFAULT 'all',
			created_by bigint(20) unsigned NOT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY event_id (event_id),
			KEY activity_id (activity_id),
			KEY is_active (is_active)
		) {$charset_collate};";
		dbDelta( $sql_announcements );

		// 11. Audit Logs Table
		$table_audit_logs = self::table( 'audit_logs' );
		$sql_audit_logs = "CREATE TABLE {$table_audit_logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned DEFAULT NULL,
			action varchar(100) NOT NULL,
			object_type varchar(100) NOT NULL,
			object_id varchar(100) DEFAULT NULL,
			details text DEFAULT NULL,
			ip_address varchar(100) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY action (action),
			KEY object_type (object_type)
		) {$charset_collate};";
		dbDelta( $sql_audit_logs );

		// 12. Email Queue Table
		$table_email_queue = self::table( 'email_queue' );
		$sql_email_queue = "CREATE TABLE {$table_email_queue} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dispatch_id varchar(64) DEFAULT NULL,
			workflow varchar(64) DEFAULT NULL,
			log_id bigint(20) unsigned DEFAULT NULL,
			template_key varchar(100) DEFAULT NULL,
			recipient_email varchar(191) NOT NULL,
			recipient_name varchar(191) DEFAULT NULL,
			recipient_type varchar(50) NOT NULL DEFAULT 'solo',
			subject varchar(255) NOT NULL,
			body longtext NOT NULL,
			headers text DEFAULT NULL,
			placeholders_json longtext DEFAULT NULL,
			idempotency_key varchar(64) DEFAULT NULL,
			registration_id varchar(64) DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'pending',
			attempts int(11) NOT NULL DEFAULT 0,
			max_attempts int(11) NOT NULL DEFAULT 3,
			last_error text DEFAULT NULL,
			provider_response text DEFAULT NULL,
			scheduled_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			sent_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY idempotency_key (idempotency_key),
			KEY dispatch_id (dispatch_id),
			KEY workflow (workflow),
			KEY log_id (log_id),
			KEY status (status),
			KEY scheduled_at (scheduled_at),
			KEY recipient_type (recipient_type),
			KEY registration_id (registration_id)
		) {$charset_collate};";
		dbDelta( $sql_email_queue );

		// 13. Dynamic Forms Table
		$table_forms = self::table( 'forms' );
		$sql_forms = "CREATE TABLE {$table_forms} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			activity_id bigint(20) unsigned NOT NULL DEFAULT 0,
			event_id bigint(20) unsigned NOT NULL DEFAULT 0,
			fields longtext NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'published',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY activity_id (activity_id),
			KEY event_id (event_id),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql_forms );

		// 14. Status Transition Logs Table
		$table_status_logs = self::table( 'status_logs' );
		$sql_status_logs = "CREATE TABLE {$table_status_logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			registration_id bigint(20) unsigned NOT NULL,
			from_status varchar(50) NOT NULL,
			to_status varchar(50) NOT NULL,
			type varchar(50) NOT NULL DEFAULT 'application',
			reason text DEFAULT NULL,
			changed_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY registration_id (registration_id),
			KEY type (type)
		) {$charset_collate};";
		dbDelta( $sql_status_logs );

		// 15. Login Attempts & Account Lockout Table
		$table_login_attempts = self::table( 'login_attempts' );
		$sql_login_attempts = "CREATE TABLE {$table_login_attempts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			identifier varchar(191) NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			ip_address varchar(100) NOT NULL,
			attempt_count int(11) NOT NULL DEFAULT 1,
			last_attempt_at datetime NOT NULL,
			locked_until datetime DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY identifier (identifier(191)),
			KEY ip_address (ip_address(100)),
			KEY status (status),
			KEY locked_until (locked_until)
		) {$charset_collate};";
		dbDelta( $sql_login_attempts );

		// 16. In-Dashboard Notifications Table
		$table_notifications = self::table( 'notifications' );
		$sql_notifications = "CREATE TABLE {$table_notifications} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			registration_id bigint(20) unsigned DEFAULT NULL,
			payment_id bigint(20) unsigned DEFAULT NULL,
			type varchar(50) NOT NULL DEFAULT 'general',
			title varchar(255) NOT NULL,
			message text NOT NULL,
			action_url varchar(500) DEFAULT NULL,
			is_read tinyint(1) NOT NULL DEFAULT 0,
			read_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY is_read (is_read),
			KEY registration_id (registration_id),
			KEY type (type),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $sql_notifications );
	}

	/**
	 * Create secure upload directory with protective htaccess
	 */
	public static function create_secure_upload_dir() {
		$upload_dir = wp_upload_dir();
		$secure_dir = trailingslashit( $upload_dir['basedir'] ) . 'eventflow-secure';

		if ( ! file_exists( $secure_dir ) ) {
			wp_mkdir_p( $secure_dir );
		}

		$htaccess_file = $secure_dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$rules = "# EventFlow Secure Uploads Protection\n";
			$rules .= "Order Deny,Allow\n";
			$rules .= "Deny from all\n";
			$rules .= "<FilesMatch \"\\.(?i:jpg|jpeg|png|gif|pdf)$\">\n";
			$rules .= "    Deny from all\n";
			$rules .= "</FilesMatch>\n";
			@file_put_contents( $htaccess_file, $rules );
		}

		$index_file = $secure_dir . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			@file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * Get Default Email Templates Data (21 production triggers)
	 *
	 * @return array
	 */
	public static function get_default_email_templates_data() {
		return array(
			array(
				'template_key'     => 'account_created',
				'name'             => 'Participant Account Created',
				'subject'          => 'Welcome to SIBACOM EVENT – Your Account Has Been Created',
				'body'             => '<p>Dear {participant_name},</p><p>Welcome to <strong>SIBACOM EVENT</strong>! Your participant account has been created successfully.</p><div class="announcement-box"><p><strong>Full Name:</strong> {participant_name}</p><p><strong>Registered Email:</strong> {participant_email}</p><p><strong>Username:</strong> {username}</p><p><strong>Account Status:</strong> Active & Confirmed</p></div><p>You can now log in to the official dashboard, explore competitive tracks, register your team, and complete your event applications.</p><p style="text-align: center; margin: 24px 0;"><a href="{login_url}" class="btn">Login to Participant Portal</a></p><p><strong>Next Steps:</strong></p><ol><li>Log in using your credentials above.</li><li>Select your competition track and choose Solo or Team participation.</li><li>Provide complete member details and submit your registration before the deadline.</li></ol><p>If you have any questions, reach out to our team at {support_email}.</p><p>Best regards,<br>SIBACOM EVENT Organizing Committee</p>',
				'placeholders_help' => '{participant_name}, {participant_email}, {username}, {login_url}, {dashboard_url}, {support_email}, {event_name}',
			),
			array(
				'template_key'     => 'welcome_email',
				'name'             => 'Welcome Email',
				'subject'          => 'Welcome to SIBACOM EVENT!',
				'body'             => '<p>Hello {participant_name},</p><p>We are delighted to welcome you to <strong>SIBACOM EVENT</strong>! Get ready for an exhilarating journey of innovation, skills, and networking.</p><p>Make sure to explore available activities and register before the deadline: <strong>{registration_deadline}</strong>.</p><p style="text-align: center; margin: 24px 0;"><a href="{dashboard_url}" class="btn">Go to Dashboard</a></p><p>Cheers,<br>SIBACOM EVENT Team</p>',
				'placeholders_help' => '{participant_name}, {event_name}, {registration_deadline}, {dashboard_url}, {website_url}',
			),
			array(
				'template_key'     => 'successful_login',
				'name'             => 'Successful Login Notification',
				'subject'          => 'Security Alert: New Login to your SIBACOM EVENT Account',
				'body'             => '<p>Dear {participant_name},</p><p>This is a confirmation that your SIBACOM EVENT account was recently accessed on {website_url}. If this was you, no action is needed.</p><p>If you did not initiate this login, please reset your password immediately via your dashboard or password recovery link.</p>',
				'placeholders_help' => '{participant_name}, {website_url}, {event_name}',
			),
			array(
				'template_key'     => 'registration_submitted',
				'name'             => 'Registration Submitted',
				'subject'          => 'Registration Received: {activity_name} ({registration_id})',
				'body'             => '<p>Dear {participant_name},</p><p>Thank you for registering for <strong>SIBACOM EVENT</strong>. Your application has been successfully submitted and recorded.</p><div class="announcement-box"><p><strong>Application ID:</strong> {registration_id}</p><p><strong>Registration Type:</strong> {participation_type_label}</p><p><strong>Activity / Track:</strong> {activity_name}</p><p><strong>Department:</strong> {department}</p><p><strong>Semester:</strong> {semester}</p><p><strong>University:</strong> {university}</p><p><strong>Payment Amount:</strong> {payment_amount}</p><p><strong>Payment Status:</strong> {payment_status_label}</p><p><strong>Application Status:</strong> {application_status_label}</p><p><strong>Submission Date:</strong> {submission_date}</p></div>{team_members_table}<p><strong>Next Step:</strong> Please complete your payment and upload your payment receipt/proof from your dashboard to confirm your registration slot.</p><p style="text-align: center; margin: 24px 0;"><a href="{dashboard_url}" class="btn">View Application & Upload Payment</a></p><p>You can also track your application status at any time: <a href="{tracking_url}">Track Status Here</a>.</p><p>Best regards,<br>SIBACOM EVENT Organizing Committee</p>',
				'placeholders_help' => '{participant_name}, {team_name}, {registration_id}, {activity_name}, {department}, {semester}, {university}, {payment_amount}, {payment_status_label}, {application_status_label}, {submission_date}, {team_members_table}, {dashboard_url}, {tracking_url}',
			),
			array(
				'template_key'     => 'registration_confirmation',
				'name'             => 'Registration Confirmation',
				'subject'          => 'Confirmed Registration for {activity_name} – {registration_id}',
				'body'             => '<p>Congratulations {participant_name}!</p><p>Your registration for <strong>{activity_name}</strong> (ID: <strong>{registration_id}</strong>) is officially recorded for SIBACOM EVENT.</p>{team_members_table}<p>Keep this email for your records. You will receive further updates as verification proceeds.</p><p style="text-align: center; margin: 24px 0;"><a href="{dashboard_url}" class="btn">View Dashboard</a></p>',
				'placeholders_help' => '{participant_name}, {activity_name}, {registration_id}, {team_name}, {team_members_table}, {dashboard_url}, {event_name}',
			),
			array(
				'template_key'     => 'registration_updated',
				'name'             => 'Registration Information Updated',
				'subject'          => 'Registration Information Updated – SIBACOM EVENT',
				'body'             => '<p>Dear {participant_name},</p><p>This is a confirmation that your registration details for <strong>SIBACOM EVENT</strong> (Application ID: <strong>{registration_id}</strong>) have been updated successfully.</p><div class="announcement-box"><p><strong>Activity / Track:</strong> {activity_name}</p><p><strong>Application ID:</strong> {registration_id}</p><p><strong>Application Status:</strong> {application_status_label}</p></div>{team_members_table}<p>You can review your updated information anytime on your participant dashboard.</p><p style="text-align: center; margin: 24px 0;"><a href="{dashboard_url}" class="btn">Open Dashboard</a></p><p>Best regards,<br>SIBACOM EVENT Organizing Committee</p>',
				'placeholders_help' => '{participant_name}, {registration_id}, {activity_name}, {application_status_label}, {team_members_table}, {dashboard_url}',
			),
			array(
				'template_key'     => 'registration_cancelled',
				'name'             => 'Registration Cancelled',
				'subject'          => 'Registration Cancelled – SIBACOM EVENT',
				'body'             => '<p>Dear {participant_name},</p><p>Your registration for <strong>SIBACOM EVENT</strong> (Application ID: <strong>{registration_id}</strong>) has been cancelled.</p><div class="announcement-box" style="border-left: 4px solid #ef4444; background: #fef2f2;"><p><strong>Activity:</strong> {activity_name}</p><p><strong>Reason:</strong> {cancellation_reason}</p></div><p>If you did not request this cancellation or have questions, please reach out to {support_email}.</p><p>Best regards,<br>SIBACOM EVENT Organizing Committee</p>',
				'placeholders_help' => '{participant_name}, {registration_id}, {activity_name}, {cancellation_reason}, {support_email}',
			),
			array(
				'template_key'     => 'payment_submitted',
				'name'             => 'Payment Submitted',
				'subject'          => 'Payment Proof Uploaded Successfully for {registration_id}',
				'body'             => '<p>Dear {participant_name},</p><p>We have successfully received your payment proof for SIBACOM EVENT (Application ID: <strong>{registration_id}</strong>).</p><div class="announcement-box"><p><strong>Application ID:</strong> {registration_id}</p><p><strong>Payment Amount:</strong> {payment_amount}</p><p><strong>Payment Method:</strong> {payment_method}</p><p><strong>Transaction ID:</strong> {transaction_id}</p><p><strong>Status:</strong> PENDING VERIFICATION</p></div><p>Our financial verification team will review your transaction details shortly.</p><p style="text-align: center; margin: 24px 0;"><a href="{dashboard_url}" class="btn">View Application</a></p>',
				'placeholders_help' => '{participant_name}, {registration_id}, {payment_amount}, {payment_method}, {transaction_id}, {dashboard_url}, {event_name}',
			),
			array(
				'template_key'     => 'admin_payment_submitted',
				'name'             => 'New Payment Submission (Admin Alert)',
				'subject'          => '[Payment Alert] New Payment Proof for {registration_id} - {activity_name}',
				'body'             => '<p>Hello Admin,</p><p>A new payment submission has been uploaded for SIBACOM EVENT registration <strong>{registration_id}</strong> and requires verification.</p><div class="announcement-box"><p><strong>Participant:</strong> {participant_name} ({participant_email})</p><p><strong>Team Name:</strong> {team_name}</p><p><strong>Activity:</strong> {activity_name}</p><p><strong>Amount:</strong> {payment_amount}</p><p><strong>Transaction ID:</strong> {transaction_id}</p></div><p><a href="{website_url}" class="btn">Open Staff Dashboard</a></p>',
				'placeholders_help' => '{participant_name}, {participant_email}, {team_name}, {registration_id}, {activity_name}, {payment_amount}, {transaction_id}, {website_url}',
			),
			array(
				'template_key'     => 'payment_under_verification',
				'name'             => 'Payment Under Verification',
				'subject'          => 'Payment Under Verification: {registration_id}',
				'body'             => '<p>Dear {participant_name},</p><p>Your payment for SIBACOM EVENT registration <strong>{registration_id}</strong> is currently <strong>Under Verification</strong>.</p><p>Verification usually takes up to 24 hours. You can check real-time status in your participant portal.</p><p style="text-align: center; margin: 24px 0;"><a href="{dashboard_url}" class="btn">Check Status</a></p>',
				'placeholders_help' => '{participant_name}, {registration_id}, {activity_name}, {dashboard_url}',
			),
			array(
				'template_key'     => 'payment_verified',
				'name'             => 'Payment Verified',
				'subject'          => 'Payment Verified Successfully – SIBACOM EVENT',
				'body'             => '<p>Dear {participant_name},</p><p>Great news! Your payment for <strong>SIBACOM EVENT</strong> has been successfully verified and approved by the Super Admin.</p><div class="announcement-box"><p><strong>Participant / Team:</strong> {team_or_participant_name}</p><p><strong>Application ID:</strong> {registration_id}</p><p><strong>Activity / Track:</strong> {activity_name}</p><p><strong>Event Venue:</strong> {event_venue}</p><p><strong>Payment Amount:</strong> {payment_amount}</p><p><strong>Payment Method:</strong> {payment_method}</p><p><strong>Transaction / Reference ID:</strong> {transaction_id}</p><p><strong>Verification Date:</strong> {verification_date}</p><p><strong>Payment Verification Status:</strong> <span style="color:#10b981; font-weight:bold;">VERIFIED</span></p><p><strong>Application Status:</strong> {application_status_label}</p></div><p><strong>Next Steps:</strong> Your participation pass is confirmed. Access your participant dashboard to view event guidelines, reporting schedules, and download your confirmation receipt.</p><p style="text-align: center; margin: 24px 0;"><a href="{dashboard_url}" class="btn">Access Dashboard & Pass</a></p><p>Best regards,<br>SIBACOM EVENT Organizing Committee</p>',
				'placeholders_help' => '{participant_name}, {team_or_participant_name}, {registration_id}, {activity_name}, {event_venue}, {payment_amount}, {payment_method}, {transaction_id}, {verification_date}, {application_status_label}, {dashboard_url}',
			),
			array(
				'template_key'     => 'payment_rejected',
				'name'             => 'Payment Rejected',
				'subject'          => 'Payment Verification Unsuccessful – Action Required',
				'body'             => '<p>Dear {participant_name},</p><p>We are writing to inform you that your payment proof for <strong>SIBACOM EVENT</strong> (Application ID: <strong>{registration_id}</strong>) could not be verified by our administrative team.</p><div class="announcement-box" style="border-left: 4px solid #ef4444; background: #fef2f2;"><p><strong>Participant / Team:</strong> {team_or_participant_name}</p><p><strong>Application ID:</strong> {registration_id}</p><p><strong>Submitted Payment Amount:</strong> {payment_amount}</p><p><strong>Payment Reference / Transaction ID:</strong> {transaction_id}</p><p><strong>Rejection Reason:</strong> <span style="color:#b91c1c; font-weight:bold;">{rejection_reason}</span></p><p><strong>Payment Deadline:</strong> {payment_deadline}</p></div><p><strong>Instructions to Correct the Issue:</strong></p><p>Please double-check your bank or mobile wallet transfer receipt to ensure the transaction ID, amount, and timestamp are clearly visible, then re-upload your valid proof.</p><p style="text-align: center; margin: 24px 0;"><a href="{reupload_url}" class="btn" style="background:#ef4444;">Re-upload Payment Proof</a></p><p>You can also track your application status at: <a href="{tracking_url}">Track Application</a>.</p><p>Best regards,<br>SIBACOM EVENT Organizing Committee</p>',
				'placeholders_help' => '{participant_name}, {team_or_participant_name}, {registration_id}, {payment_amount}, {transaction_id}, {rejection_reason}, {payment_deadline}, {reupload_url}, {tracking_url}',
			),
			array(
				'template_key'     => 'payment_reupload_requested',
				'name'             => 'Payment Re-upload Requested',
				'subject'          => 'Payment Proof Re-upload Required – SIBACOM EVENT',
				'body'             => '<p>Dear {participant_name},</p><p>The administrator has reviewed your payment submission for <strong>SIBACOM EVENT</strong> (Application ID: <strong>{registration_id}</strong>) and requested a new payment receipt or proof.</p><div class="announcement-box" style="border-left: 4px solid #f59e0b; background: #fffbeb;"><p><strong>Application ID:</strong> {registration_id}</p><p><strong>Activity:</strong> {activity_name}</p><p><strong>Previous Status:</strong> {previous_payment_status}</p><p><strong>Reason for Re-upload Request:</strong> {admin_notes}</p><p><strong>Submission Deadline:</strong> {payment_deadline}</p></div><p><strong>Instructions:</strong> Please take a clear, high-resolution screenshot or PDF of your transfer receipt displaying the bank/wallet transaction reference and upload it using the link below:</p><p style="text-align: center; margin: 24px 0;"><a href="{reupload_url}" class="btn">Upload New Payment Proof</a></p><p>Best regards,<br>SIBACOM EVENT Organizing Committee</p>',
				'placeholders_help' => '{participant_name}, {registration_id}, {activity_name}, {previous_payment_status}, {admin_notes}, {payment_deadline}, {reupload_url}',
			),
			array(
				'template_key'     => 'application_approved',
				'name'             => 'Application Approved',
				'subject'          => 'Application Approved – Congratulations! {registration_id}',
				'body'             => '<p>Dear {participant_name},</p><p>Congratulations! We are delighted to inform you that your application for <strong>SIBACOM EVENT</strong> has been officially <strong>APPROVED</strong>!</p><div class="announcement-box"><p><strong>Application ID:</strong> {registration_id}</p><p><strong>Registration Type:</strong> {participation_type_label}</p><p><strong>Team / Participant:</strong> {team_or_participant_name}</p><p><strong>Activity / Track:</strong> {activity_name}</p><p><strong>Payment Status:</strong> <span style="color:#10b981; font-weight:bold;">VERIFIED</span></p><p><strong>Application Status:</strong> <span style="color:#10b981; font-weight:bold;">APPROVED</span></p><p><strong>Event Venue:</strong> {event_venue}</p><p><strong>Event Date:</strong> {event_date}</p><p><strong>Reporting Time:</strong> {reporting_time}</p></div>{team_members_table}<p><strong>Participant Instructions:</strong></p><ul><li>Please bring your original Student Identity Card and CNIC/B-Form to the venue.</li><li>Arrive at the venue at least 30 minutes prior to the reporting time for badge collection.</li><li>Review track rules and guidelines on your participant dashboard.</li></ul><p style="text-align: center; margin: 24px 0;"><a href="{dashboard_url}" class="btn">Access Participant Dashboard</a></p><p>See you at SIBACOM EVENT!</p><p>Warm regards,<br>SIBACOM EVENT Organizing Committee</p>',
				'placeholders_help' => '{participant_name}, {registration_id}, {participation_type_label}, {team_or_participant_name}, {activity_name}, {event_venue}, {event_date}, {reporting_time}, {team_members_table}, {dashboard_url}',
			),
			array(
				'template_key'     => 'application_rejected',
				'name'             => 'Application Rejected',
				'subject'          => 'Application Update – Rejected: {registration_id}',
				'body'             => '<p>Dear {participant_name},</p><p>We are writing to provide an update regarding your application for <strong>SIBACOM EVENT</strong> (Application ID: <strong>{registration_id}</strong>).</p><div class="announcement-box" style="border-left: 4px solid #ef4444; background: #fef2f2;"><p><strong>Activity / Track:</strong> {activity_name}</p><p><strong>Application Status:</strong> <span style="color:#b91c1c; font-weight:bold;">REJECTED</span></p><p><strong>Reason:</strong> {rejection_reason}</p></div><p>If you believe this decision was made in error or wish to appeal, please contact the organizing team at {support_email}.</p><p>You can check application records at: <a href="{tracking_url}">Track Application</a>.</p><p>Best regards,<br>SIBACOM EVENT Organizing Committee</p>',
				'placeholders_help' => '{participant_name}, {registration_id}, {activity_name}, {rejection_reason}, {support_email}, {tracking_url}',
			),
			array(
				'template_key'     => 'deadline_reminder',
				'name'             => 'Registration Deadline Reminder',
				'subject'          => 'Reminder: Registration Deadline Approaching for SIBACOM EVENT',
				'body'             => '<p>Dear {participant_name},</p><p>The registration deadline for <strong>SIBACOM EVENT</strong> is fast approaching on <strong>{registration_deadline}</strong>.</p><p>Ensure your team members and payment verifications are finalized before slots close.</p><p style="text-align: center; margin: 24px 0;"><a href="{dashboard_url}" class="btn">Review Registrations</a></p>',
				'placeholders_help' => '{participant_name}, {event_name}, {registration_deadline}, {dashboard_url}, {website_url}',
			),
			array(
				'template_key'     => 'activity_announcement',
				'name'             => 'Activity Announcement',
				'subject'          => 'Announcement: {activity_name} Updates',
				'body'             => '<p>Dear Participant,</p><p>There is an important announcement regarding <strong>{activity_name}</strong> for SIBACOM EVENT:</p><div class="announcement-box">{announcement_content}</div><p>Please review the rules and schedules in your dashboard.</p>',
				'placeholders_help' => '{participant_name}, {activity_name}, {announcement_content}, {website_url}',
			),
			array(
				'template_key'     => 'event_update',
				'name'             => 'Event Update',
				'subject'          => 'Important Update Regarding SIBACOM EVENT',
				'body'             => '<p>Dear {participant_name},</p><p>Please take note of the following update regarding <strong>SIBACOM EVENT</strong>:</p><div class="announcement-box">{announcement_content}</div><p>Stay tuned to your dashboard for live announcements.</p>',
				'placeholders_help' => '{participant_name}, {event_name}, {announcement_content}, {website_url}',
			),
			array(
				'template_key'     => 'event_completion',
				'name'             => 'Event Completion Notification',
				'subject'          => 'Thank You for Attending SIBACOM EVENT!',
				'body'             => '<p>Dear {participant_name},</p><p>Thank you for being part of <strong>SIBACOM EVENT</strong>! We hope you had a rewarding, competitive, and memorable experience.</p><p>Your certificates and event results will be available in your participant dashboard soon.</p><p>Warm wishes,<br>SIBACOM EVENT Committee</p>',
				'placeholders_help' => '{participant_name}, {event_name}, {website_url}',
			),
			array(
				'template_key'     => 'test_email',
				'name'             => 'Administrative Test Notification',
				'subject'          => '[SIBACOM EVENT] Test Notification - WordPress Native wp_mail() Check',
				'body'             => '<p>Hello {participant_name},</p><p>This is a real test notification generated by the <strong>SIBACOM EVENT Registration Portal</strong>.</p><div class="announcement-box"><p><strong>Sender Identity:</strong> SIBACOM EVENT &lt;{support_email}&gt;</p><p><strong>Sending Transport:</strong> WordPress Native wp_mail()</p><p><strong>Status:</strong> Successfully handed off to server mail transport.</p></div><p>If you are viewing this in your inbox, your server mail transport and DNS records are operating correctly.</p><p><a href="{website_url}" class="btn">Visit Portal</a></p>',
				'placeholders_help' => '{participant_name}, {participant_email}, {support_email}, {website_url}, {event_name}',
			),
			array(
				'template_key'     => 'password_reset',
				'name'             => 'Password Reset Request',
				'subject'          => 'Password Reset Request - SIBACOM EVENT',
				'body'             => '<p>Hello {participant_name},</p><p>A request was received to reset your password for the SIBACOM EVENT portal. Please click the link below to choose a new password:</p><p style="text-align:center; margin: 24px 0;"><a href="{reset_url}" class="btn">Reset Password</a></p><p>If you did not request this, you can safely ignore this email.</p>',
				'placeholders_help' => '{participant_name}, {reset_url}, {website_url}, {event_name}',
			),
			array(
				'template_key'     => 'password_changed',
				'name'             => 'Password Changed Security Notice',
				'subject'          => 'Security Alert: Your Password Was Changed - SIBACOM EVENT',
				'body'             => '<p>Dear {participant_name},</p><p>This is a security notification to confirm that the password for your SIBACOM EVENT account (<strong>{username}</strong>) was changed successfully.</p><div class="announcement-box"><p><strong>Account Username:</strong> {username}</p><p><strong>Registered Email:</strong> {participant_email}</p><p><strong>Status:</strong> Password Updated Successfully</p><p><strong>Timestamp:</strong> {update_timestamp}</p></div><p>If you made this change, you can safely disregard this email.</p><p><strong>Did not authorize this change?</strong> Please contact the organizing committee immediately at {support_email} to protect your account.</p><p style="text-align: center; margin: 24px 0;"><a href="{login_url}" class="btn">Login to Account</a></p><p>Best regards,<br>SIBACOM EVENT Security Team</p>',
				'placeholders_help' => '{participant_name}, {username}, {participant_email}, {update_timestamp}, {support_email}, {login_url}',
			),
			array(
				'template_key'     => 'admin_registration_submitted',
				'name'             => 'New Registration Alert (Admin Alert)',
				'subject'          => '[Registration Alert] New Registration for {activity_name} ({registration_id})',
				'body'             => '<p>Hello Administrator,</p><p>A new participant registration has been submitted on the SIBACOM EVENT portal and recorded.</p><div class="announcement-box"><p><strong>Application ID:</strong> {registration_id}</p><p><strong>Activity / Track:</strong> {activity_name}</p><p><strong>Registration Type:</strong> {participation_type_label}</p><p><strong>Participant / Team:</strong> {team_or_participant_name}</p><p><strong>Leader / Contact:</strong> {participant_name} ({participant_email})</p><p><strong>University:</strong> {university}</p><p><strong>Department:</strong> {department}</p><p><strong>Calculated Fee:</strong> {payment_amount}</p><p><strong>Submission Date:</strong> {submission_date}</p></div>{team_members_table}<p style="text-align: center; margin: 24px 0;"><a href="{website_url}" class="btn">View in Registrations Manager</a></p>',
				'placeholders_help' => '{registration_id}, {activity_name}, {participation_type_label}, {team_or_participant_name}, {participant_name}, {participant_email}, {university}, {department}, {payment_amount}, {submission_date}, {team_members_table}, {website_url}',
			),
		);
	}

	/**
	 * Seed Default Email Templates (16 production triggers)
	 */
	public static function seed_default_email_templates() {
		global $wpdb;
		$table = self::table( 'email_templates' );

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $count > 0 ) {
			return;
		}

		$templates = self::get_default_email_templates_data();

		foreach ( $templates as $tpl ) {
			$wpdb->insert(
				$table,
				array(
					'template_key'     => $tpl['template_key'],
					'name'             => $tpl['name'],
					'subject'          => $tpl['subject'],
					'body'             => $tpl['body'],
					'placeholders_help' => $tpl['placeholders_help'],
					'is_active'        => 1,
					'created_at'       => current_time( 'mysql' ),
					'updated_at'       => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Seed Default Active Event and Sample Competitions
	 */
	public static function seed_default_event() {
		global $wpdb;
		$table_events = self::table( 'events' );

		$exists = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_events}" );
		if ( $exists > 0 ) {
			return;
		}

		// Insert Default Event
		$now = current_time( 'timestamp' );
		$start_date = date( 'Y-m-d H:i:s', strtotime( '+14 days', $now ) );
		$end_date   = date( 'Y-m-d H:i:s', strtotime( '+17 days', $now ) );
		$reg_start  = date( 'Y-m-d H:i:s', $now );
		$reg_end    = date( 'Y-m-d H:i:s', strtotime( '+12 days', $now ) );

		$default_banner = EVENTFLOW_PLUGIN_URL . 'assets/images/default-banner.svg';
		$default_logo   = EVENTFLOW_PLUGIN_URL . 'assets/images/logo.svg';
		$default_qr     = EVENTFLOW_PLUGIN_URL . 'assets/images/default-qr.svg';

		$wpdb->insert(
			$table_events,
			array(
				'title'                    => 'SIBACOM EVENT 2026',
				'slug'                     => 'sibacom-event-2026',
				'description'              => 'Join the premier flagship technological olympiad and symposium at Sukkur IBA University. Compete with top national talent across coding, cyber security, robotics, artificial intelligence, and digital design.',
				'banner_url'               => $default_banner,
				'logo_url'                 => $default_logo,
				'venue'                    => 'Sukkur IBA University Main Campus, Sukkur',
				'university_name'          => 'Sukkur IBA University',
				'organizing_dept'          => 'Department of Computer Science / SIBACOM Society',
				'website_url'              => home_url(),
				'start_date'               => $start_date,
				'end_date'                 => $end_date,
				'reg_start_date'           => $reg_start,
				'reg_end_date'             => $reg_end,
				'start_time'               => '09:00 AM',
				'end_time'                 => '05:00 PM',
				'reporting_time'           => '08:30 AM',
				'status'                   => 'active',
				'contact_email'            => 'info@portal.sibacom.tech',
				'contact_phone'            => '+92 300 1234567',
				'allow_solo'               => 1,
				'allow_team'               => 1,
				'min_team_size'            => 2,
				'max_team_size'            => 5,
				'min_solo_registrations'   => 1,
				'max_total_registrations'  => 500,
				'max_team_registrations'   => 200,
				'allow_reg_edit'           => 1,
				'allow_proof_reupload'     => 1,
				'pricing_model'            => 'per_participant',
				'reg_fee'                  => 500.00,
				'solo_fee_enabled'         => 1,
				'solo_fee'                 => 500.00,
				'solo_discount'            => 0.00,
				'team_fee_enabled'         => 1,
				'team_fee_per_member'      => 500.00,
				'team_fixed_fee'           => 2000.00,
				'team_discount'            => 0.00,
				'team_min_payable'         => 500.00,
				'currency'                 => 'PKR',
				'accepted_payment_methods' => 'bank,jazzcash,easypaisa',
				'bank_account_title'       => 'SIBACOM Society',
				'bank_name'                => 'Bank Alfalah',
				'bank_account_number'      => '0123-4567890123',
				'bank_iban'                => 'PK65ALFH01234567890123',
				'easypaisa_details'        => '0300-1234567 (Title: SIBACOM Society)',
				'jazzcash_details'         => '0300-1234567 (Title: SIBACOM Society)',
				'raast_id'                 => '03001234567',
				'payment_instructions'     => "Please transfer the exact registration fee to any of the official accounts above. Upload a clear transaction receipt/screenshot displaying the transaction reference ID, date timestamp, and amount transferred.",
				'payment_deadline_hours'   => 48,
				'payment_info'             => "Bank Alfalah\nAccount Title: SIBACOM Society\nAccount Number: 0123-4567890123\nIBAN: PK65ALFH01234567890123\nJazzCash / Easypaisa: 0300-1234567 (Title: SIBACOM Society)",
				'qr_code_url'              => $default_qr,
				'created_at'               => current_time( 'mysql' ),
				'updated_at'               => current_time( 'mysql' ),
			)
		);

		$event_id = $wpdb->insert_id;
		update_option( 'eventflow_active_event_id', $event_id );
		update_option( 'eventflow_qr_code_url', $default_qr );

		// Insert 3 Sample Competitions
		$table_activities = self::table( 'activities' );
		$activities = array(
			array(
				'title'              => 'Speed Programming Championship',
				'slug'               => 'speed-programming-championship',
				'description'        => 'Intense algorithmic and competitive programming challenge solving complex data structures and dynamic programming problems against time.',
				'eligibility'        => 'Enrolled Undergraduate and Graduate University Students with valid student ID.',
				'participation_type' => 'both',
				'min_team_size'      => 1,
				'max_team_size'      => 3,
				'leader_counts'      => 1,
				'reg_fee'            => 1500.00,
				'max_participants'   => 50,
				'instructions'       => 'Allowed languages: C++, Java, Python 3. Bring your own laptop or use lab workstations.',
			),
			array(
				'title'              => 'Autonomous Robotics Clash',
				'slug'               => 'autonomous-robotics-clash',
				'description'        => 'Design, program, and navigate autonomous line-following and obstacle-avoidance robots through a challenging dynamic arena.',
				'eligibility'        => 'Engineering, CS, and Mechatronics students from accredited universities.',
				'participation_type' => 'team',
				'min_team_size'      => 2,
				'max_team_size'      => 4,
				'leader_counts'      => 1,
				'reg_fee'            => 2500.00,
				'max_participants'   => 30,
				'instructions'       => 'Robot dimensions must not exceed 30cm x 30cm x 30cm. Weight limit: 3.5kg. Max voltage: 12V.',
			),
			array(
				'title'              => 'UI/UX Designathon & Prototype Jam',
				'slug'               => 'ui-ux-designathon',
				'description'        => 'A 24-hour design sprint tackling modern real-world product problems. Deliver wireframes, interactive Figma prototypes, and user flows.',
				'eligibility'        => 'Open to all enrolled university students passionate about digital product design.',
				'participation_type' => 'both',
				'min_team_size'      => 1,
				'max_team_size'      => 2,
				'leader_counts'      => 1,
				'reg_fee'            => 1200.00,
				'max_participants'   => 40,
				'instructions'       => 'Figma link and final interactive prototype walk-through must be submitted before deadline.',
			),
		);

		foreach ( $activities as $act ) {
			$wpdb->insert(
				$table_activities,
				array(
					'event_id'                => $event_id,
					'title'                   => $act['title'],
					'slug'                    => $act['slug'],
					'description'             => $act['description'],
					'image_url'               => '',
					'eligibility'             => $act['eligibility'],
					'reg_deadline'            => $reg_end,
					'participation_type'      => $act['participation_type'],
					'min_team_size'           => $act['min_team_size'],
					'max_team_size'           => $act['max_team_size'],
					'leader_counts_as_member' => $act['leader_counts'],
					'reg_fee'                 => $act['reg_fee'],
					'fee_calculation'         => isset( $act['fee_calculation'] ) ? $act['fee_calculation'] : 'per_team',
					'payment_proof_required'  => 1,
					'max_participants'        => $act['max_participants'],
					'current_participants'    => 0,
					'instructions'            => $act['instructions'],
					'status'                  => 'published',
					'created_at'              => current_time( 'mysql' ),
					'updated_at'              => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
			);
		}

		// Also seed a welcome announcement
		$wpdb->insert(
			self::table( 'announcements' ),
			array(
				'event_id'    => $event_id,
				'activity_id' => null,
				'title'       => 'Welcome to National Tech Olympiad 2026!',
				'content'     => 'Registration is officially open for all competitive tracks. Form your teams, review the track eligibility guidelines, and secure your slots before the registration deadline.',
				'created_by'  => 1,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Seed Default Dedicated Pages on Activation
	 */
	public static function seed_default_pages() {
		$pages = array(
			'eventflow_page_landing' => array(
				'title'   => __( 'Event Portal', 'eventflow' ),
				'slug'    => 'event-portal',
				'content' => '[eventflow_landing]',
			),
			'eventflow_page_auth' => array(
				'title'   => __( 'Participant Login', 'eventflow' ),
				'slug'    => 'participant-login',
				'content' => '[eventflow_auth]',
			),
			'eventflow_page_dashboard' => array(
				'title'   => __( 'Participant Dashboard', 'eventflow' ),
				'slug'    => 'participant-dashboard',
				'content' => '[eventflow_dashboard]',
			),
			'eventflow_page_staff_dashboard' => array(
				'title'   => __( 'Event Command Center', 'eventflow' ),
				'slug'    => 'event-dashboard',
				'content' => '[eventflow_staff_dashboard]',
			),
			'eventflow_page_track' => array(
				'title'   => __( 'Track Registration', 'eventflow' ),
				'slug'    => 'track-status',
				'content' => '[eventflow_track]',
			),
		);

		foreach ( $pages as $opt_key => $p ) {
			$existing_id = (int) get_option( $opt_key, 0 );
			if ( $existing_id && get_post( $existing_id ) ) {
				continue;
			}

			// Check if page already exists with that slug
			$page_obj = get_page_by_path( $p['slug'] );
			if ( $page_obj ) {
				update_option( $opt_key, $page_obj->ID );
				continue;
			}

			$page_id = wp_insert_post(
				array(
					'post_title'     => $p['title'],
					'post_name'      => $p['slug'],
					'post_content'   => $p['content'],
					'post_status'    => 'publish',
					'post_type'      => 'page',
					'comment_status' => 'closed',
				)
			);

			if ( ! is_wp_error( $page_id ) && $page_id ) {
				update_option( $opt_key, $page_id );
			}
		}
	}

	/**
	 * Seed Sample Registrations for Live Analytics & In-App Testing
	 */
	public static function seed_sample_registrations() {
		global $wpdb;
		$table_reg = self::table( 'registrations' );

		$exists = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_reg}" );
		if ( $exists > 0 ) {
			return;
		}

		$event = $wpdb->get_row( "SELECT * FROM " . self::table( 'events' ) . " ORDER BY id ASC LIMIT 1" );
		if ( ! $event ) {
			return;
		}

		$activities = $wpdb->get_results( "SELECT * FROM " . self::table( 'activities' ) . " WHERE event_id = {$event->id} ORDER BY id ASC" );
		if ( empty( $activities ) ) {
			return;
		}

		// Use current admin user or user 1
		$admin_id = get_current_user_id() ? get_current_user_id() : 1;

		// 1. Verified Team Registration
		$act1 = $activities[0];
		$wpdb->insert(
			$table_reg,
			array(
				'reg_id'             => 'EF-2026-7K8P1',
				'event_id'           => $event->id,
				'activity_id'        => $act1->id,
				'user_id'            => $admin_id,
				'participation_type' => 'team',
				'team_name'          => 'Algorithm Titans',
				'total_fee'          => floatval( $act1->reg_fee ),
				'payment_status'     => 'verified',
				'application_status' => 'approved',
				'verified_by'        => $admin_id,
				'verified_at'        => current_time( 'mysql' ),
				'created_at'         => date( 'Y-m-d H:i:s', strtotime( '-3 days' ) ),
				'updated_at'         => current_time( 'mysql' ),
			)
		);
		$reg1_id = $wpdb->insert_id;

		$wpdb->insert(
			self::table( 'teams' ),
			array(
				'registration_id' => $reg1_id,
				'team_name'       => 'Algorithm Titans',
				'leader_user_id'  => $admin_id,
				'leader_name'     => 'Johnathan Doe',
				'leader_email'    => 'john.doe@fast.edu.pk',
				'leader_phone'    => '+92 300 1112233',
				'created_at'      => date( 'Y-m-d H:i:s', strtotime( '-3 days' ) ),
			)
		);
		$team1_id = $wpdb->insert_id;

		$members1 = array(
			array( 'full_name' => 'Johnathan Doe', 'father_name' => 'Robert Doe', 'semester' => '6', 'department' => 'Computer Science', 'university' => 'FAST NUCES', 'email' => 'john.doe@fast.edu.pk', 'contact_number' => '03001112233', 'is_leader' => 1 ),
			array( 'full_name' => 'Alex Vance', 'father_name' => 'Gordon Vance', 'semester' => '6', 'department' => 'Software Engineering', 'university' => 'FAST NUCES', 'email' => 'alex.vance@fast.edu.pk', 'contact_number' => '03002223344', 'is_leader' => 0 ),
			array( 'full_name' => 'Sarah Connor', 'father_name' => 'Michael Connor', 'semester' => '6', 'department' => 'Computer Science', 'university' => 'FAST NUCES', 'email' => 'sarah.c@fast.edu.pk', 'contact_number' => '03003334455', 'is_leader' => 0 ),
		);
		foreach ( $members1 as $m ) {
			$m['team_id'] = $team1_id;
			$m['registration_id'] = $reg1_id;
			$m['created_at'] = date( 'Y-m-d H:i:s', strtotime( '-3 days' ) );
			$wpdb->insert( self::table( 'team_members' ), $m );
		}

		// Ensure secure uploads folder exists and copy sample preview assets
		$upload_dir = wp_upload_dir();
		$secure_dir = trailingslashit( $upload_dir['basedir'] ) . 'eventflow-secure';
		if ( ! file_exists( $secure_dir ) ) {
			wp_mkdir_p( $secure_dir );
		}

		$src_qr     = EVENTFLOW_PLUGIN_DIR . 'assets/images/default-qr.svg';
		$dest_qr    = trailingslashit( $secure_dir ) . 'sample-payment-proof.svg';
		if ( file_exists( $src_qr ) && ! file_exists( $dest_qr ) ) {
			@copy( $src_qr, $dest_qr );
		}

		$src_banner = EVENTFLOW_PLUGIN_DIR . 'assets/images/default-banner.svg';
		$dest_banner = trailingslashit( $secure_dir ) . 'sample-banner-proof.svg';
		if ( file_exists( $src_banner ) && ! file_exists( $dest_banner ) ) {
			@copy( $src_banner, $dest_banner );
		}

		$proof1_path = file_exists( $dest_banner ) ? $dest_banner : $src_banner;
		$proof1_url  = file_exists( $dest_banner )
			? trailingslashit( $upload_dir['baseurl'] ) . 'eventflow-secure/sample-banner-proof.svg'
			: EVENTFLOW_PLUGIN_URL . 'assets/images/default-banner.svg';

		$proof2_path = file_exists( $dest_qr ) ? $dest_qr : $src_qr;
		$proof2_url  = file_exists( $dest_qr )
			? trailingslashit( $upload_dir['baseurl'] ) . 'eventflow-secure/sample-payment-proof.svg'
			: EVENTFLOW_PLUGIN_URL . 'assets/images/default-qr.svg';

		// Verified Payment Record
		$wpdb->insert(
			self::table( 'payments' ),
			array(
				'registration_id' => $reg1_id,
				'user_id'         => $admin_id,
				'transaction_id'  => 'TXN98273641',
				'payment_method'  => 'Bank Alfalah',
				'amount'          => floatval( $act1->reg_fee ),
				'payment_date'    => date( 'Y-m-d', strtotime( '-3 days' ) ),
				'screenshot_url'  => $proof1_url,
				'screenshot_path' => $proof1_path,
				'file_type'       => 'image/svg+xml',
				'file_size'       => 2048,
				'status'          => 'verified',
				'verified_by'     => $admin_id,
				'verified_at'     => current_time( 'mysql' ),
				'created_at'      => date( 'Y-m-d H:i:s', strtotime( '-3 days' ) ),
				'updated_at'      => current_time( 'mysql' ),
			)
		);

		// 2. Pending Verification Registration (Ready to test in staff verification inspector!)
		if ( isset( $activities[1] ) ) {
			$act2 = $activities[1];
			$wpdb->insert(
				$table_reg,
				array(
					'reg_id'             => 'EF-2026-9M2X4',
					'event_id'           => $event->id,
					'activity_id'        => $act2->id,
					'user_id'            => $admin_id,
					'participation_type' => 'team',
					'team_name'          => 'RoboKnights',
					'total_fee'          => floatval( $act2->reg_fee ),
					'payment_status'     => 'under_verification',
					'application_status' => 'under_review',
					'created_at'         => date( 'Y-m-d H:i:s', strtotime( '-1 days' ) ),
					'updated_at'         => current_time( 'mysql' ),
				)
			);
			$reg2_id = $wpdb->insert_id;

			$wpdb->insert(
				self::table( 'teams' ),
				array(
					'registration_id' => $reg2_id,
					'team_name'       => 'RoboKnights',
					'leader_user_id'  => $admin_id,
					'leader_name'     => 'Bilal Ahmed',
					'leader_email'    => 'bilal.ahmed@nust.edu.pk',
					'leader_phone'    => '+92 321 4445566',
					'created_at'      => date( 'Y-m-d H:i:s', strtotime( '-1 days' ) ),
				)
			);
			$team2_id = $wpdb->insert_id;

			$members2 = array(
				array( 'full_name' => 'Bilal Ahmed', 'father_name' => 'Tariq Ahmed', 'semester' => '4', 'department' => 'Mechatronics Engineering', 'university' => 'NUST Islamabad', 'email' => 'bilal.ahmed@nust.edu.pk', 'contact_number' => '03214445566', 'is_leader' => 1 ),
				array( 'full_name' => 'Hamza Tariq', 'father_name' => 'Tariq Mehmood', 'semester' => '4', 'department' => 'Electrical Engineering', 'university' => 'NUST Islamabad', 'email' => 'hamza.t@nust.edu.pk', 'contact_number' => '03217778899', 'is_leader' => 0 ),
			);
			foreach ( $members2 as $m ) {
				$m['team_id'] = $team2_id;
				$m['registration_id'] = $reg2_id;
				$m['created_at'] = date( 'Y-m-d H:i:s', strtotime( '-1 days' ) );
				$wpdb->insert( self::table( 'team_members' ), $m );
			}

			// Payment Under Verification (inspectable in modal)
			$wpdb->insert(
				self::table( 'payments' ),
				array(
					'registration_id' => $reg2_id,
					'user_id'         => $admin_id,
					'transaction_id'  => 'JAZZ74819203',
					'payment_method'  => 'JazzCash',
					'amount'          => floatval( $act2->reg_fee ),
					'payment_date'    => date( 'Y-m-d', strtotime( '-1 days' ) ),
					'screenshot_url'  => $proof2_url,
					'screenshot_path' => $proof2_path,
					'file_type'       => 'image/svg+xml',
					'file_size'       => 2048,
					'status'          => 'under_verification',
					'created_at'      => date( 'Y-m-d H:i:s', strtotime( '-1 days' ) ),
					'updated_at'      => current_time( 'mysql' ),
				)
			);
		}

		// Update activity participant counters
		$wpdb->query( "UPDATE " . self::table( 'activities' ) . " SET current_participants = 3 WHERE id = {$act1->id}" );
		if ( isset( $act2 ) ) {
			$wpdb->query( "UPDATE " . self::table( 'activities' ) . " SET current_participants = 2 WHERE id = {$act2->id}" );
		}
	}
}
