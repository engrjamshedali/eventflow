<?php
/**
 * EventFlow Shortcode Registry & View Router
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Shortcodes {

	/**
	 * Register all shortcodes
	 */
	public static function init() {
		add_shortcode( 'eventflow_landing', array( __CLASS__, 'render_landing' ) );
		add_shortcode( 'eventflow_auth', array( __CLASS__, 'render_auth' ) );
		add_shortcode( 'eventflow_login', array( __CLASS__, 'render_login' ) );
		add_shortcode( 'eventflow_register', array( __CLASS__, 'render_register' ) );
		add_shortcode( 'eventflow_dashboard', array( __CLASS__, 'render_dashboard' ) );
		add_shortcode( 'eventflow_activities', array( __CLASS__, 'render_activities' ) );
		add_shortcode( 'eventflow_activity_details', array( __CLASS__, 'render_activity_details' ) );
		add_shortcode( 'eventflow_registration_form', array( __CLASS__, 'render_registration_form' ) );
		add_shortcode( 'eventflow_payment', array( __CLASS__, 'render_payment' ) );
		add_shortcode( 'eventflow_track', array( __CLASS__, 'render_track' ) );
		add_shortcode( 'eventflow_staff_dashboard', array( __CLASS__, 'render_staff_dashboard' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_frontend_assets' ) );
	}

	/**
	 * Get page URL containing shortcode, or fallback
	 *
	 * @param string $shortcode
	 * @param string $fallback
	 * @return string
	 */
	public static function get_page_url_for_shortcode( $shortcode, $fallback = '/' ) {
		// 1. Direct option lookup if provisioned during activation
		$opt_map = array(
			'eventflow_dashboard'       => 'eventflow_page_dashboard',
			'eventflow_staff_dashboard' => 'eventflow_page_staff_dashboard',
			'eventflow_auth'            => 'eventflow_page_auth',
			'eventflow_landing'         => 'eventflow_page_landing',
			'eventflow_track'           => 'eventflow_page_track',
		);

		if ( isset( $opt_map[ $shortcode ] ) ) {
			$page_id = (int) get_option( $opt_map[ $shortcode ], 0 );
			if ( $page_id && get_post_status( $page_id ) === 'publish' ) {
				return get_permalink( $page_id );
			}
		}

		global $wpdb;

		$page_id = (int) wp_cache_get( 'ef_page_' . $shortcode, 'eventflow' );
		if ( ! $page_id ) {
			$page_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE %s LIMIT 1",
					'%' . $wpdb->esc_like( $shortcode ) . '%'
				)
			);
			if ( $page_id ) {
				wp_cache_set( 'ef_page_' . $shortcode, $page_id, 'eventflow', 3600 );
			}
		}

		return $page_id ? get_permalink( $page_id ) : home_url( $fallback );
	}

	public static function get_dashboard_url() {
		return self::get_page_url_for_shortcode( 'eventflow_dashboard', '/participant-dashboard/' );
	}

	public static function get_staff_dashboard_url() {
		return self::get_page_url_for_shortcode( 'eventflow_staff_dashboard', '/event-dashboard/' );
	}

	public static function get_auth_url() {
		return self::get_page_url_for_shortcode( 'eventflow_auth', '/participant-login/' );
	}

	public static function get_landing_url() {
		return self::get_page_url_for_shortcode( 'eventflow_landing', '/event-portal/' );
	}

	public static function get_track_url() {
		return self::get_page_url_for_shortcode( 'eventflow_track', '/track-status/' );
	}

	/**
	 * Shortcode: [eventflow_landing]
	 */
	public static function render_landing( $atts = array() ) {
		self::enqueue_frontend_assets();
		$event = EventFlow_Event_Manager::get_active_event();
		$activities = EventFlow_Activity_Manager::get_activities( array( 'status' => 'published', 'event_id' => $event ? $event->id : 0 ) );
		$status = EventFlow_Event_Manager::get_registration_status( $event );
		$announcements = $event ? EventFlow_Event_Manager::get_announcements( $event->id, 3 ) : array();

		ob_start();
		include EVENTFLOW_PLUGIN_DIR . 'templates/frontend/landing-page.php';
		return ob_get_clean();
	}

	/**
	 * Shortcode: [eventflow_auth]
	 */
	public static function render_auth( $atts = array() ) {
		self::enqueue_frontend_assets();

		if ( is_user_logged_in() ) {
			if ( EventFlow_Roles::is_staff() ) {
				return sprintf( '<div class="ef-notice ef-notice-info"><p>%s <a href="%s" class="ef-btn ef-btn-primary ef-btn-sm">%s &rarr;</a></p></div>', esc_html__( 'You are currently logged in as Staff.', 'eventflow' ), esc_url( self::get_staff_dashboard_url() ), esc_html__( 'Open Staff Dashboard', 'eventflow' ) );
			}
			return sprintf( '<div class="ef-notice ef-notice-info"><p>%s <a href="%s" class="ef-btn ef-btn-primary ef-btn-sm">%s &rarr;</a></p></div>', esc_html__( 'You are already logged in.', 'eventflow' ), esc_url( self::get_dashboard_url() ), esc_html__( 'Go to Participant Dashboard', 'eventflow' ) );
		}

		$active_tab = isset( $_GET['tab'] ) && in_array( $_GET['tab'], array( 'register', 'forgot' ), true ) ? $_GET['tab'] : 'login';

		ob_start();
		include EVENTFLOW_PLUGIN_DIR . 'templates/frontend/auth-page.php';
		return ob_get_clean();
	}

	public static function render_login( $atts = array() ) {
		$_GET['tab'] = 'login';
		return self::render_auth( $atts );
	}

	public static function render_register( $atts = array() ) {
		$_GET['tab'] = 'register';
		return self::render_auth( $atts );
	}

	/**
	 * Shortcode: [eventflow_dashboard]
	 */
	public static function render_dashboard( $atts = array() ) {
		self::enqueue_frontend_assets();

		if ( ! is_user_logged_in() ) {
			return sprintf(
				'<div class="ef-auth-required-box"><div class="ef-icon-lock">🔒</div><h3>%s</h3><p>%s</p><a href="%s" class="ef-btn ef-btn-primary">%s &rarr;</a></div>',
				esc_html__( 'Participant Authentication Required', 'eventflow' ),
				esc_html__( 'Please sign in or create a participant account to access your event dashboard.', 'eventflow' ),
				esc_url( self::get_auth_url() ),
				esc_html__( 'Login or Register', 'eventflow' )
			);
		}

		$user = wp_get_current_user();
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'overview';
		$registrations = EventFlow_Registration_Manager::get_user_registrations( $user->ID );
		$event = EventFlow_Event_Manager::get_active_event();
		$activities = EventFlow_Activity_Manager::get_activities( array( 'status' => 'published', 'event_id' => $event ? $event->id : 0 ) );
		$payment_settings = EventFlow_Payment_Manager::get_settings();
		$announcements = $event ? EventFlow_Event_Manager::get_announcements( $event->id, 5 ) : array();

		// Handle specific registration for payment or receipt
		$selected_reg = null;
		if ( ! empty( $_GET['reg_id'] ) ) {
			$selected_reg = EventFlow_Registration_Manager::get_full_registration_details( sanitize_text_field( wp_unslash( $_GET['reg_id'] ) ) );
		}

		ob_start();
		include EVENTFLOW_PLUGIN_DIR . 'templates/frontend/participant-dashboard.php';
		return ob_get_clean();
	}

	/**
	 * Shortcode: [eventflow_activities]
	 */
	public static function render_activities( $atts = array() ) {
		self::enqueue_frontend_assets();
		$event = EventFlow_Event_Manager::get_active_event();
		$activities = EventFlow_Activity_Manager::get_activities( array( 'status' => 'published', 'event_id' => $event ? $event->id : 0 ) );

		ob_start();
		include EVENTFLOW_PLUGIN_DIR . 'templates/frontend/activity-list.php';
		return ob_get_clean();
	}

	/**
	 * Shortcode: [eventflow_activity_details]
	 */
	public static function render_activity_details( $atts = array() ) {
		self::enqueue_frontend_assets();
		$atts = shortcode_atts( array( 'id' => 0 ), $atts );
		$id = $atts['id'] ? absint( $atts['id'] ) : ( isset( $_GET['activity_id'] ) ? absint( $_GET['activity_id'] ) : 0 );
		$activity = EventFlow_Activity_Manager::get_activity( $id );

		if ( ! $activity ) {
			return '<p class="ef-notice ef-notice-warning">' . esc_html__( 'Activity not found.', 'eventflow' ) . '</p>';
		}

		$event = EventFlow_Event_Manager::get_event( $activity->event_id );

		ob_start();
		include EVENTFLOW_PLUGIN_DIR . 'templates/frontend/activity-single.php';
		return ob_get_clean();
	}

	/**
	 * Shortcode: [eventflow_registration_form]
	 */
	public static function render_registration_form( $atts = array() ) {
		self::enqueue_frontend_assets();
		$atts = shortcode_atts( array( 'activity_id' => 0 ), $atts );
		$activity_id = $atts['activity_id'] ? absint( $atts['activity_id'] ) : ( isset( $_GET['activity_id'] ) ? absint( $_GET['activity_id'] ) : 0 );
		
		$event = EventFlow_Event_Manager::get_active_event();
		$activities = EventFlow_Activity_Manager::get_activities( array( 'status' => 'published', 'event_id' => $event ? $event->id : 0 ) );
		
		$activity = $activity_id ? EventFlow_Activity_Manager::get_activity( $activity_id ) : ( ! empty( $activities ) ? $activities[0] : null );

		$user = is_user_logged_in() ? wp_get_current_user() : (object) array( 'ID' => 0, 'display_name' => '', 'user_email' => '' );
		$payment_settings = EventFlow_Payment_Manager::get_settings();
		$enabled_payment_methods = EventFlow_Payment_Manager::get_enabled_payment_methods();

		ob_start();
		include EVENTFLOW_PLUGIN_DIR . 'templates/frontend/registration-form.php';
		return ob_get_clean();
	}

	/**
	 * Shortcode: [eventflow_payment]
	 */
	public static function render_payment( $atts = array() ) {
		self::enqueue_frontend_assets();

		if ( ! is_user_logged_in() ) {
			return sprintf( '<p>%s <a href="%s">%s</a></p>', esc_html__( 'Please login to submit payment.', 'eventflow' ), esc_url( self::get_auth_url() ), esc_html__( 'Login', 'eventflow' ) );
		}

		$atts = shortcode_atts( array( 'registration_id' => '' ), $atts );
		$reg_id = $atts['registration_id'] ? sanitize_text_field( $atts['registration_id'] ) : ( isset( $_GET['reg_id'] ) ? sanitize_text_field( wp_unslash( $_GET['reg_id'] ) ) : '' );
		$reg = EventFlow_Registration_Manager::get_full_registration_details( $reg_id );

		if ( ! $reg ) {
			return '<p class="ef-notice ef-notice-warning">' . esc_html__( 'Registration record not found.', 'eventflow' ) . '</p>';
		}

		$payment_settings = EventFlow_Payment_Manager::get_settings();
		$existing_payment = EventFlow_Payment_Manager::get_payment_by_registration( $reg->id );

		ob_start();
		include EVENTFLOW_PLUGIN_DIR . 'templates/frontend/payment-submission.php';
		return ob_get_clean();
	}

	/**
	 * Shortcode: [eventflow_track]
	 */
	public static function render_track( $atts = array() ) {
		self::enqueue_frontend_assets();
		ob_start();
		include EVENTFLOW_PLUGIN_DIR . 'templates/frontend/tracking-page.php';
		return ob_get_clean();
	}

	/**
	 * Shortcode: [eventflow_staff_dashboard]
	 */
	public static function render_staff_dashboard( $atts = array() ) {
		self::enqueue_staff_assets();

		if ( ! is_user_logged_in() ) {
			return sprintf(
				'<div class="ef-auth-required-box"><div class="ef-icon-lock">🔐</div><h3>%s</h3><p>%s</p><a href="%s" class="ef-btn ef-btn-primary">%s &rarr;</a></div>',
				esc_html__( 'Staff Authentication Required', 'eventflow' ),
				esc_html__( 'This administrative portal is restricted to authorized event management staff.', 'eventflow' ),
				esc_url( wp_login_url( self::get_staff_dashboard_url() ) ),
				esc_html__( 'Staff Sign In', 'eventflow' )
			);
		}

		if ( ! EventFlow_Roles::is_staff() ) {
			return '<div class="ef-auth-required-box"><div class="ef-icon-lock">⛔</div><h3>' . esc_html__( 'Access Restricted', 'eventflow' ) . '</h3><p>' . esc_html__( 'Your user account does not possess staff or administrative permissions.', 'eventflow' ) . '</p></div>';
		}

		$current_user = wp_get_current_user();
		$active_view = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : 'overview';
		$event = EventFlow_Event_Manager::get_active_event();

		ob_start();
		include EVENTFLOW_PLUGIN_DIR . 'templates/frontend/staff-dashboard.php';
		return ob_get_clean();
	}

	/**
	 * Register assets for participant portal & frontend forms
	 */
	public static function register_frontend_assets() {
		wp_register_style( 'eventflow-frontend', EVENTFLOW_PLUGIN_URL . 'assets/css/eventflow-frontend.css', array(), EVENTFLOW_VERSION );
		wp_register_script( 'eventflow-frontend', EVENTFLOW_PLUGIN_URL . 'assets/js/eventflow-frontend.js', array( 'jquery' ), EVENTFLOW_VERSION, true );

		$ajax_url = admin_url( 'admin-ajax.php' );
		if ( is_ssl() || ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) {
			$ajax_url = set_url_scheme( $ajax_url, 'https' );
		}

		wp_localize_script(
			'eventflow-frontend',
			'eventflow_vars',
			array(
				'ajax_url'      => $ajax_url,
				'auth_nonce'    => wp_create_nonce( 'eventflow_auth_nonce' ),
				'nonce'         => wp_create_nonce( 'eventflow_frontend_nonce' ),
				'dashboard_url' => self::get_dashboard_url(),
				'i18n'          => array(
					'submitting'     => __( 'Submitting...', 'eventflow' ),
					'processing'     => __( 'Processing...', 'eventflow' ),
					'confirm_remove' => __( 'Remove this team member?', 'eventflow' ),
					'max_reached'    => __( 'Maximum team size limit reached.', 'eventflow' ),
					'min_required'   => __( 'Minimum team members required: ', 'eventflow' ),
					'error_upload'   => __( 'Please select a valid image or PDF proof.', 'eventflow' ),
				),
			)
		);
	}

	/**
	 * Enqueue assets for participant portal & frontend forms
	 */
	public static function enqueue_frontend_assets() {
		self::register_frontend_assets();
		wp_enqueue_style( 'eventflow-frontend' );
		wp_enqueue_script( 'eventflow-frontend' );
	}

	/**
	 * Enqueue assets for Frontend Staff Dashboard
	 */
	public static function enqueue_staff_assets() {
		self::enqueue_frontend_assets();
		wp_enqueue_style( 'eventflow-staff', EVENTFLOW_PLUGIN_URL . 'assets/css/eventflow-staff-dashboard.css', array(), EVENTFLOW_VERSION );
		wp_enqueue_script( 'eventflow-charts', EVENTFLOW_PLUGIN_URL . 'assets/js/eventflow-charts.js', array(), EVENTFLOW_VERSION, true );
		wp_enqueue_script( 'eventflow-preview', EVENTFLOW_PLUGIN_URL . 'assets/js/eventflow-payment-preview.js', array( 'jquery' ), EVENTFLOW_VERSION, true );
		wp_enqueue_script( 'eventflow-staff', EVENTFLOW_PLUGIN_URL . 'assets/js/eventflow-staff-dashboard.js', array( 'jquery', 'eventflow-charts', 'eventflow-preview' ), EVENTFLOW_VERSION, true );

		$ajax_url = admin_url( 'admin-ajax.php' );
		if ( is_ssl() || ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) {
			$ajax_url = set_url_scheme( $ajax_url, 'https' );
		}

		wp_localize_script(
			'eventflow-staff',
			'eventflow_staff_vars',
			array(
				'ajax_url'    => $ajax_url,
				'staff_nonce' => wp_create_nonce( 'eventflow_staff_nonce' ),
				'export_url'  => add_query_arg(
					array(
						'eventflow_action' => 'export',
						'_wpnonce'         => wp_create_nonce( 'eventflow_export' ),
					),
					home_url( '/' )
				),
			)
		);
	}
}

