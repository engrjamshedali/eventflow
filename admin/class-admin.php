<?php
/**
 * EventFlow WordPress Admin Backoffice Controller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Admin {

	/**
	 * Init Admin Hooks
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'restrict_admin_access' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_admin_post_actions' ) );
		add_action( 'admin_notices', array( __CLASS__, 'display_activation_notice' ) );
	}

	/**
	 * Restrict wp-admin access for participants and restricted staff roles.
	 * Only full administrators with manage_options have access to wp-admin.
	 * Preserves AJAX, CRON, and background operations.
	 */
	public static function restrict_admin_access() {
		if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$current_user = wp_get_current_user();

		// Super Admins & full site admins with manage_options are allowed
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		// Non-administrator staff and participants are redirected to frontend command center/dashboard
		$redirect_url = class_exists( 'EventFlow_Auth' )
			? EventFlow_Auth::get_user_redirect_url( $current_user )
			: home_url( '/event-dashboard/' );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Register WordPress Admin Menu & Submenus
	 */
	public static function register_admin_menu() {
		$main_cap = 'eventflow_staff_access';

		// Top Level Menu
		add_menu_page(
			__( 'EventFlow Management', 'eventflow' ),
			__( 'EventFlow', 'eventflow' ),
			$main_cap,
			'eventflow-dashboard',
			array( __CLASS__, 'render_view_dashboard' ),
			'dashicons-calendar-alt',
			26
		);

		// Submenus
		add_submenu_page(
			'eventflow-dashboard',
			__( 'Dashboard & Analytics', 'eventflow' ),
			__( 'Dashboard', 'eventflow' ),
			$main_cap,
			'eventflow-dashboard',
			array( __CLASS__, 'render_view_dashboard' )
		);

		add_submenu_page(
			'eventflow-dashboard',
			__( 'Events Configuration', 'eventflow' ),
			__( 'Events', 'eventflow' ),
			'eventflow_manage_events',
			'eventflow-events',
			array( __CLASS__, 'render_view_events' )
		);

		add_submenu_page(
			'eventflow-dashboard',
			__( 'Activities & Competitions', 'eventflow' ),
			__( 'Activities', 'eventflow' ),
			'eventflow_manage_activities',
			'eventflow-activities',
			array( __CLASS__, 'render_view_activities' )
		);

		add_submenu_page(
			'eventflow-dashboard',
			__( 'Registrations Management', 'eventflow' ),
			__( 'Registrations', 'eventflow' ),
			'eventflow_view_registrations',
			'eventflow-registrations',
			array( __CLASS__, 'render_view_registrations' )
		);

		add_submenu_page(
			'eventflow-dashboard',
			__( 'Payment Verification', 'eventflow' ),
			__( 'Payments', 'eventflow' ),
			'eventflow_verify_payments',
			'eventflow-payments',
			array( __CLASS__, 'render_view_payments' )
		);

		add_submenu_page(
			'eventflow-dashboard',
			__( 'Email Templates & Automation', 'eventflow' ),
			__( 'Email Templates', 'eventflow' ),
			'eventflow_manage_emails',
			'eventflow-emails',
			array( __CLASS__, 'render_view_emails' )
		);

		add_submenu_page(
			'eventflow-dashboard',
			__( 'Staff Accounts & Roles', 'eventflow' ),
			__( 'Staff & Roles', 'eventflow' ),
			'eventflow_manage_staff',
			'eventflow-staff',
			array( __CLASS__, 'render_view_staff' )
		);

		add_submenu_page(
			'eventflow-dashboard',
			__( 'Export & Reporting', 'eventflow' ),
			__( 'Export & Reports', 'eventflow' ),
			'eventflow_export_data',
			'eventflow-export',
			array( __CLASS__, 'render_view_export' )
		);

		add_submenu_page(
			'eventflow-dashboard',
			__( 'EventFlow Settings', 'eventflow' ),
			__( 'Settings', 'eventflow' ),
			'eventflow_manage_settings',
			'eventflow-settings',
			array( __CLASS__, 'render_view_settings' )
		);

		add_submenu_page(
			'eventflow-dashboard',
			__( 'Audit & System Logs', 'eventflow' ),
			__( 'Audit Logs', 'eventflow' ),
			'eventflow_manage_settings',
			'eventflow-logs',
			array( __CLASS__, 'render_view_logs' )
		);
	}

	/**
	 * Enqueue Admin Scripts & Styles
	 *
	 * @param string $hook
	 */
	public static function enqueue_admin_assets( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( false === strpos( $hook, 'eventflow' ) && 0 !== strpos( $page, 'eventflow' ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'dashicons' );

		$css_ver = file_exists( EVENTFLOW_PLUGIN_DIR . 'assets/css/eventflow-admin.css' )
			? filemtime( EVENTFLOW_PLUGIN_DIR . 'assets/css/eventflow-admin.css' )
			: EVENTFLOW_VERSION;

		$charts_ver = file_exists( EVENTFLOW_PLUGIN_DIR . 'assets/js/eventflow-charts.js' )
			? filemtime( EVENTFLOW_PLUGIN_DIR . 'assets/js/eventflow-charts.js' )
			: EVENTFLOW_VERSION;

		$preview_ver = file_exists( EVENTFLOW_PLUGIN_DIR . 'assets/js/eventflow-payment-preview.js' )
			? filemtime( EVENTFLOW_PLUGIN_DIR . 'assets/js/eventflow-payment-preview.js' )
			: EVENTFLOW_VERSION;

		$admin_js_ver = file_exists( EVENTFLOW_PLUGIN_DIR . 'assets/js/eventflow-admin.js' )
			? filemtime( EVENTFLOW_PLUGIN_DIR . 'assets/js/eventflow-admin.js' )
			: EVENTFLOW_VERSION;

		wp_enqueue_style( 'eventflow-admin', EVENTFLOW_PLUGIN_URL . 'assets/css/eventflow-admin.css', array( 'dashicons' ), $css_ver );
		wp_enqueue_script( 'eventflow-charts', EVENTFLOW_PLUGIN_URL . 'assets/js/eventflow-charts.js', array(), $charts_ver, true );
		wp_enqueue_script( 'eventflow-preview', EVENTFLOW_PLUGIN_URL . 'assets/js/eventflow-payment-preview.js', array( 'jquery' ), $preview_ver, true );
		wp_enqueue_script( 'eventflow-admin', EVENTFLOW_PLUGIN_URL . 'assets/js/eventflow-admin.js', array( 'jquery', 'eventflow-charts', 'eventflow-preview' ), $admin_js_ver, true );

		wp_localize_script(
			'eventflow-admin',
			'eventflow_admin_vars',
			array(
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'staff_nonce' => wp_create_nonce( 'eventflow_staff_nonce' ),
				'export_url'  => add_query_arg(
					array(
						'eventflow_action' => 'export',
						'_wpnonce'         => wp_create_nonce( 'eventflow_export' ),
					),
					home_url( '/' )
				),
				'i18n'        => array(
					'choose_image'   => __( 'Choose Image', 'eventflow' ),
					'select_image'   => __( 'Select Image', 'eventflow' ),
					'confirm_delete' => __( 'Are you sure you want to delete this item? This action cannot be undone.', 'eventflow' ),
					'copied'         => __( 'Copied!', 'eventflow' ),
				),
			)
		);
	}

	/**
	 * Handle native POST form actions in admin (e.g. Save Settings, Save Email Template)
	 */
	public static function handle_admin_post_actions() {
		if ( ! isset( $_POST['eventflow_admin_action'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['eventflow_admin_action'] ) );

		if ( 'save_settings' === $action ) {
			check_admin_referer( 'eventflow_save_settings_nonce' );
			EventFlow_Security::check_capability( 'eventflow_manage_settings' );

			$fields = array(
				'eventflow_active_event_id'         => 'absint',
				'eventflow_system_closed'           => 'sanitize_text_field',
				'eventflow_reg_id_prefix'           => 'sanitize_text_field',
				'eventflow_currency'                => 'sanitize_text_field',
				'eventflow_primary_color'           => 'sanitize_hex_color',
				'eventflow_secondary_color'         => 'sanitize_hex_color',
				'eventflow_favicon_url'             => 'esc_url_raw',
				'eventflow_bank_name'               => 'sanitize_text_field',
				'eventflow_account_title'           => 'sanitize_text_field',
				'eventflow_account_number'          => 'sanitize_text_field',
				'eventflow_iban'                    => 'sanitize_text_field',
				'eventflow_easypaisa_title'         => 'sanitize_text_field',
				'eventflow_easypaisa_number'        => 'sanitize_text_field',
				'eventflow_jazzcash_title'          => 'sanitize_text_field',
				'eventflow_jazzcash_number'         => 'sanitize_text_field',
				'eventflow_raast_id'                => 'sanitize_text_field',
				'eventflow_qr_code_url'             => 'esc_url_raw',
				'eventflow_payment_instructions'    => 'sanitize_textarea_field',
				'eventflow_email_from_name'         => 'sanitize_text_field',
				'eventflow_email_from_address'      => 'sanitize_email',
				'eventflow_alert_on_login'          => 'sanitize_text_field',
				'eventflow_delete_data_on_uninstall'=> 'sanitize_text_field',
			);

			foreach ( $fields as $option_key => $sanitizer ) {
				if ( isset( $_POST[ $option_key ] ) ) {
					$val = wp_unslash( $_POST[ $option_key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					$sanitized = call_user_func( $sanitizer, $val );
					update_option( $option_key, $sanitized );
				}
			}

			// Handle standalone app & takeover checkboxes
			update_option( 'eventflow_standalone_mode', ! empty( $_POST['eventflow_standalone_mode'] ) ? 1 : 0 );
			update_option( 'eventflow_takeover_homepage', ! empty( $_POST['eventflow_takeover_homepage'] ) ? 1 : 0 );

			// Handle payment gateway enable/disable toggles
			update_option( 'eventflow_bank_enabled', ! empty( $_POST['eventflow_bank_enabled'] ) ? 'yes' : 'no' );
			update_option( 'eventflow_jazzcash_enabled', ! empty( $_POST['eventflow_jazzcash_enabled'] ) ? 'yes' : 'no' );
			update_option( 'eventflow_easypaisa_enabled', ! empty( $_POST['eventflow_easypaisa_enabled'] ) ? 'yes' : 'no' );

			wp_safe_redirect( add_query_arg( array( 'page' => 'eventflow-settings', 'updated' => 'true' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'update_email_template' === $action ) {
			check_admin_referer( 'eventflow_update_template_nonce' );
			EventFlow_Security::check_capability( 'eventflow_manage_emails' );

			$id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
			$subject = isset( $_POST['template_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['template_subject'] ) ) : '';
			$body = isset( $_POST['template_body'] ) ? wp_kses_post( wp_unslash( $_POST['template_body'] ) ) : '';
			$is_active = ! empty( $_POST['is_active'] ) ? 1 : 0;

			EventFlow_Email_Manager::update_template(
				$id,
				array(
					'subject'   => $subject,
					'body'      => $body,
					'is_active' => $is_active,
				)
			);

			wp_safe_redirect( add_query_arg( array( 'page' => 'eventflow-emails', 'tab' => 'templates', 'edit' => $id, 'updated' => 'true' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'save_smtp_settings' === $action ) {
			check_admin_referer( 'eventflow_save_smtp_nonce' );
			EventFlow_Security::check_capability( 'eventflow_manage_emails' );

			EventFlow_SMTP::save_config( $_POST );

			wp_safe_redirect( add_query_arg( array( 'page' => 'eventflow-emails', 'tab' => 'smtp', 'updated' => 'true' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'save_email_general_settings' === $action ) {
			check_admin_referer( 'eventflow_save_email_general_nonce' );
			EventFlow_Security::check_capability( 'eventflow_manage_emails' );

			if ( isset( $_POST['eventflow_email_from_name'] ) ) {
				$from_name = sanitize_text_field( wp_unslash( $_POST['eventflow_email_from_name'] ) );
				if ( ! empty( $from_name ) ) {
					update_option( 'eventflow_email_from_name', $from_name );
				}
			}
			if ( isset( $_POST['eventflow_email_from_address'] ) ) {
				$from_addr = sanitize_email( wp_unslash( $_POST['eventflow_email_from_address'] ) );
				if ( ! empty( $from_addr ) && is_email( $from_addr ) ) {
					update_option( 'eventflow_email_from_address', $from_addr );
				}
			}
			if ( isset( $_POST['eventflow_email_reply_to'] ) ) {
				$reply_to = sanitize_email( wp_unslash( $_POST['eventflow_email_reply_to'] ) );
				if ( ! empty( $reply_to ) && is_email( $reply_to ) ) {
					update_option( 'eventflow_email_reply_to', $reply_to );
				}
			}
			if ( isset( $_POST['eventflow_email_footer_text'] ) ) {
				update_option( 'eventflow_email_footer_text', sanitize_textarea_field( wp_unslash( $_POST['eventflow_email_footer_text'] ) ) );
			}

			if ( isset( $_POST['eventflow_admin_notification_email'] ) ) {
				update_option( 'eventflow_admin_notification_email', sanitize_email( wp_unslash( $_POST['eventflow_admin_notification_email'] ) ) );
			}
			if ( isset( $_POST['eventflow_email_queue_enabled'] ) ) {
				update_option( 'eventflow_email_queue_enabled', 'yes' === $_POST['eventflow_email_queue_enabled'] ? 'yes' : 'no' );
			}
			if ( isset( $_POST['eventflow_email_max_retries'] ) ) {
				update_option( 'eventflow_email_max_retries', absint( $_POST['eventflow_email_max_retries'] ) );
			}
			if ( isset( $_POST['eventflow_email_batch_size'] ) ) {
				update_option( 'eventflow_email_batch_size', absint( $_POST['eventflow_email_batch_size'] ) );
			}

			// Notification Triggers Toggles
			$triggers = array(
				'account_created', 'welcome_email', 'registration_submitted', 'registration_confirmation',
				'payment_submitted', 'admin_payment_submitted', 'payment_under_verification',
				'payment_verified', 'payment_rejected', 'payment_reupload_requested',
				'application_approved', 'application_rejected', 'deadline_reminder',
				'activity_announcement', 'event_update', 'registration_updated', 'registration_cancelled',
				'payment_receipt_uploaded', 'payment_proof_submitted', 'payment_verification_pending',
				'application_status_changed'
			);
			foreach ( $triggers as $trig ) {
				$val = ! empty( $_POST['eventflow_notify_trigger_' . $trig] ) ? 'yes' : 'no';
				update_option( 'eventflow_notify_trigger_' . $trig, $val );
			}

			wp_safe_redirect( add_query_arg( array( 'page' => 'eventflow-emails', 'tab' => 'settings', 'updated' => 'true' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'export_email_logs' === $action ) {
			check_admin_referer( 'eventflow_export_email_logs_nonce' );
			EventFlow_Security::check_capability( 'eventflow_manage_emails' );

			global $wpdb;
			$table_logs = EventFlow_DB::table( 'email_logs' );
			$logs = $wpdb->get_results( "SELECT * FROM {$table_logs} ORDER BY id DESC LIMIT 5000" );

			if ( ob_get_level() ) {
				ob_end_clean();
			}

			header( 'Content-Type: text/csv; charset=UTF-8' );
			header( 'Content-Disposition: attachment; filename="sibacom-email-logs-' . date( 'Y-m-d' ) . '.csv"' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );

			$fp = fopen( 'php://output', 'w' );
			fputs( $fp, "\xEF\xBB\xBF" ); // UTF-8 BOM
			fputcsv( $fp, array( 'Log ID', 'Date & Time', 'Recipient Email', 'Recipient Name', 'Recipient Type', 'Sender Email', 'Subject', 'Template Key', 'Registration ID', 'Status', 'Retries', 'Provider Response', 'Error Message' ) );

			if ( ! empty( $logs ) ) {
				foreach ( $logs as $l ) {
					$status_label = ( in_array( $l->status, array( 'sent', 'accepted' ), true ) ) ? 'Sent / Accepted by Mail Server' : ( 'failed' === $l->status ? 'Failed' : ucfirst( $l->status ) );
					fputcsv(
						$fp,
						array(
							$l->id,
							$l->sent_at,
							$l->recipient_email,
							$l->recipient_name,
							isset( $l->recipient_type ) ? ucfirst( $l->recipient_type ) : 'Solo',
							isset( $l->sender_email ) ? $l->sender_email : 'info@portal.sibacom.tech',
							$l->subject,
							$l->template_key,
							isset( $l->registration_id ) ? $l->registration_id : '',
							$status_label,
							isset( $l->retry_count ) ? $l->retry_count : 0,
							isset( $l->provider_response ) ? $l->provider_response : '',
							$l->error_message,
						)
					);
				}
			}

			fclose( $fp );
			exit;
		}
	}

	// Render view callbacks
	public static function render_view_dashboard() {
		include EVENTFLOW_PLUGIN_DIR . 'admin/views/view-dashboard.php';
	}
	public static function render_view_events() {
		include EVENTFLOW_PLUGIN_DIR . 'admin/views/view-events.php';
	}
	public static function render_view_activities() {
		include EVENTFLOW_PLUGIN_DIR . 'admin/views/view-activities.php';
	}
	public static function render_view_registrations() {
		include EVENTFLOW_PLUGIN_DIR . 'admin/views/view-registrations.php';
	}
	public static function render_view_payments() {
		include EVENTFLOW_PLUGIN_DIR . 'admin/views/view-payments.php';
	}
	public static function render_view_emails() {
		include EVENTFLOW_PLUGIN_DIR . 'admin/views/view-emails.php';
	}
	public static function render_view_staff() {
		include EVENTFLOW_PLUGIN_DIR . 'admin/views/view-staff.php';
	}
	public static function render_view_export() {
		include EVENTFLOW_PLUGIN_DIR . 'admin/views/view-export.php';
	}
	public static function render_view_settings() {
		include EVENTFLOW_PLUGIN_DIR . 'admin/views/view-settings.php';
	}
	public static function render_view_logs() {
		include EVENTFLOW_PLUGIN_DIR . 'admin/views/view-logs.php';
	}

	/**
	 * Log operational audit event
	 *
	 * @param string   $action
	 * @param int|null $user_id
	 * @param mixed    $details
	 * @param string   $object_type
	 * @param mixed    $object_id
	 */
	public static function log_audit( $action, $user_id = null, $details = '', $object_type = 'system', $object_id = null ) {
		EventFlow_Security::log_audit( $action, $object_type, $object_id, $details, $user_id );
	}

	/**
	 * Retrieve audit logs
	 *
	 * @param array $args
	 * @return array
	 */
	public static function get_audit_logs( $args = array() ) {
		global $wpdb;
		$table_logs = EventFlow_DB::table( 'audit_logs' );

		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$per_page = isset( $args['per_page'] ) ? min( 100, max( 1, absint( $args['per_page'] ) ) ) : 25;
		$action   = isset( $args['action'] ) ? sanitize_text_field( $args['action'] ) : '';

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $action ) ) {
			$where[]  = 'action = %s';
			$values[] = $action;
		}

		$where_clause = implode( ' AND ', $where );
		$count_sql    = "SELECT COUNT(*) FROM {$table_logs} WHERE {$where_clause}";
		$total        = (int) ( ! empty( $values ) ? $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) ) : $wpdb->get_var( $count_sql ) );

		$offset = ( $page - 1 ) * $per_page;
		$limit  = $per_page;

		$sql   = "SELECT * FROM {$table_logs} WHERE {$where_clause} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}";
		$items = ! empty( $values ) ? $wpdb->get_results( $wpdb->prepare( $sql, $values ) ) : $wpdb->get_results( $sql );

		return array(
			'items'    => $items ? $items : array(),
			'total'    => $total,
			'pages'    => ceil( $total / $per_page ),
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Display Activation Banner with Quick Links
	 */
	public static function display_activation_notice() {
		if ( ! get_transient( 'eventflow_activation_notice' ) ) {
			return;
		}
		delete_transient( 'eventflow_activation_notice' );

		$landing_url   = EventFlow_Shortcodes::get_landing_url();
		$dashboard_url = EventFlow_Shortcodes::get_dashboard_url();
		$staff_url     = EventFlow_Shortcodes::get_staff_dashboard_url();
		$settings_url  = admin_url( 'admin.php?page=eventflow-settings' );
		$activities_url = admin_url( 'admin.php?page=eventflow-activities' );
		?>
		<div class="notice notice-success is-dismissible" style="border-left: 4px solid #36B49F; padding: 16px 20px; background: #ffffff;">
			<div style="display:flex; align-items:center; gap: 14px; margin-bottom: 10px;">
				<span style="font-size: 28px;">🚀</span>
				<div>
					<h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #1e1b4b;">EventFlow System Activated Successfully!</h3>
					<p style="margin: 4px 0 0; color: #475569; font-size: 13px;">Your complete Event Registration &amp; Management platform is ready with 11 custom database tables, sample competitions, and dedicated portal pages.</p>
				</div>
			</div>
			<div style="display:flex; flex-wrap:wrap; gap: 8px; margin-top: 12px;">
				<a href="<?php echo esc_url( $landing_url ); ?>" target="_blank" class="button button-primary" style="background:#36B49F; border-color:#27A991;">🌐 View Live Events Portal</a>
				<a href="<?php echo esc_url( $staff_url ); ?>" target="_blank" class="button button-secondary">⚡ Frontend Staff Dashboard</a>
				<a href="<?php echo esc_url( $dashboard_url ); ?>" target="_blank" class="button button-secondary">👤 Participant Dashboard</a>
				<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-secondary">⚙️ Payment Gateways &amp; Settings</a>
				<a href="<?php echo esc_url( $activities_url ); ?>" class="button button-secondary">🏆 Manage Competitions</a>
			</div>
		</div>
		<?php
	}
}

