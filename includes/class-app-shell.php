<?php
/**
 * EventFlow Standalone App Shell Controller
 *
 * Intercepts WordPress template loading to render an isolated, full-page
 * web application. Bypasses active theme headers, footers, logos, and navigation menus.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_App_Shell {

	/**
	 * Initialize hooks
	 */
	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'intercept_template' ), 999 );
		add_action( 'template_redirect', array( __CLASS__, 'handle_redirects' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'filter_admin_bar' ), 999 );
		add_filter( 'logout_redirect', array( __CLASS__, 'filter_logout_redirect' ), 999, 3 );
	}

	/**
	 * Ensure logout returns user to home authentication screen
	 *
	 * @param string $redirect_to
	 * @param string $requested_redirect_to
	 * @param WP_User|null $user
	 * @return string
	 */
	public static function filter_logout_redirect( $redirect_to, $requested_redirect_to, $user ) {
		return home_url( '/' );
	}

	/**
	 * Hide admin bar for participants and guests in standalone mode
	 *
	 * @param bool $show
	 * @return bool
	 */
	public static function filter_admin_bar( $show ) {
		if ( self::is_standalone_request() ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return false;
			}
		}
		return $show;
	}

	/**
	 * Check if current request should use standalone app shell
	 *
	 * @return bool
	 */
	public static function is_standalone_request() {
		if ( is_admin() || wp_doing_ajax() ) {
			return false;
		}

		$standalone_enabled = (bool) get_option( 'eventflow_standalone_mode', 1 );
		if ( ! $standalone_enabled ) {
			return false;
		}

		// 1. Explicit query parameter
		if ( isset( $_GET['eventflow_app'] ) || isset( $_GET['eventflow_view'] ) ) {
			return true;
		}

		// 2. Homepage Takeover
		$takeover_home = (bool) get_option( 'eventflow_takeover_homepage', 1 );
		if ( $takeover_home && ( is_front_page() || is_home() ) ) {
			return true;
		}

		// 3. Dedicated EventFlow pages
		if ( is_page() ) {
			$page_id = get_queried_object_id();
			$ef_pages = array(
				(int) get_option( 'eventflow_page_landing', 0 ),
				(int) get_option( 'eventflow_page_auth', 0 ),
				(int) get_option( 'eventflow_page_dashboard', 0 ),
				(int) get_option( 'eventflow_page_staff_dashboard', 0 ),
				(int) get_option( 'eventflow_page_track', 0 ),
			);
			if ( in_array( $page_id, array_filter( $ef_pages ), true ) ) {
				return true;
			}

			global $post;
			if ( $post ) {
				$slug = $post->post_name;
				if ( in_array( $slug, array( 'event-portal', 'participant-login', 'participant-dashboard', 'event-dashboard', 'track-status', 'events', 'live-events' ), true ) ) {
					return true;
				}
				if ( has_shortcode( $post->post_content, 'eventflow_landing' )
					|| has_shortcode( $post->post_content, 'eventflow_auth' )
					|| has_shortcode( $post->post_content, 'eventflow_login' )
					|| has_shortcode( $post->post_content, 'eventflow_register' )
					|| has_shortcode( $post->post_content, 'eventflow_dashboard' )
					|| has_shortcode( $post->post_content, 'eventflow_staff_dashboard' )
					|| has_shortcode( $post->post_content, 'eventflow_track' )
					|| has_shortcode( $post->post_content, 'eventflow_payment' )
					|| has_shortcode( $post->post_content, 'eventflow_registration_form' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Handle redirects
	 */
	public static function handle_redirects() {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$current_user = wp_get_current_user();
		$redirect_url = class_exists( 'EventFlow_Auth' )
			? EventFlow_Auth::get_user_redirect_url( $current_user )
			: EventFlow_Shortcodes::get_dashboard_url();

		$takeover_home = (bool) get_option( 'eventflow_takeover_homepage', 1 );
		$is_home       = is_front_page() || is_home();

		// If user is logged in and visits homepage during takeover, route directly to their dashboard
		if ( $is_home && $takeover_home ) {
			if ( ! empty( $redirect_url ) && rtrim( $redirect_url, '/' ) !== rtrim( home_url(), '/' ) ) {
				wp_safe_redirect( $redirect_url );
				exit;
			}
		}

		// If user is logged in and visits auth page, redirect to their role-specific dashboard
		if ( is_page() ) {
			$slug = get_post_field( 'post_name', get_queried_object_id() );
			if ( in_array( $slug, array( 'participant-login', 'login', 'register' ), true ) && ! isset( $_GET['action'] ) ) {
				wp_safe_redirect( $redirect_url );
				exit;
			}
		}
	}

	/**
	 * Intercept template loading to render the standalone App Shell
	 *
	 * @param string $template
	 * @return string
	 */
	public static function intercept_template( $template ) {
		if ( ! self::is_standalone_request() ) {
			return $template;
		}

		$takeover_home = (bool) get_option( 'eventflow_takeover_homepage', 1 );
		$is_home       = is_front_page() || is_home();

		// Determine view to render
		$view       = 'auth-home';
		$page_title = __( 'Event Portal', 'eventflow' );

		if ( $is_home && $takeover_home ) {
			if ( is_user_logged_in() ) {
				if ( isset( $_GET['view'] ) && 'staff' === $_GET['view'] && EventFlow_Roles::is_staff() ) {
					$view       = 'staff-dashboard';
					$page_title = __( 'Event Command Center', 'eventflow' );
				} else {
					$view       = 'dashboard';
					$page_title = __( 'Explore Live Events', 'eventflow' );
				}
			} else {
				$view       = 'auth-home';
				$page_title = __( 'Participant Authentication & Registration', 'eventflow' );
			}
		} elseif ( is_page() ) {
			global $post;
			$slug    = $post ? $post->post_name : '';
			$content = $post ? $post->post_content : '';

			if ( 'event-dashboard' === $slug || has_shortcode( $content, 'eventflow_staff_dashboard' ) ) {
				$view       = 'staff-dashboard';
				$page_title = __( 'Event Command Center', 'eventflow' );
			} elseif ( in_array( $slug, array( 'events', 'live-events', 'participant-dashboard' ), true ) || has_shortcode( $content, 'eventflow_dashboard' ) ) {
				if ( is_user_logged_in() ) {
					$view       = 'dashboard';
					$page_title = __( 'Explore Live Events', 'eventflow' );
				} else {
					$view       = 'auth-home';
					$page_title = __( 'Participant Login', 'eventflow' );
				}
			} elseif ( in_array( $slug, array( 'participant-login', 'login', 'register' ), true ) || has_shortcode( $content, 'eventflow_auth' ) || has_shortcode( $content, 'eventflow_login' ) || has_shortcode( $content, 'eventflow_register' ) ) {
				if ( is_user_logged_in() ) {
					$view       = 'dashboard';
					$page_title = __( 'Explore Live Events', 'eventflow' );
				} else {
					$view       = 'auth-home';
					$page_title = __( 'Sign In or Register', 'eventflow' );
				}
			} elseif ( 'track-status' === $slug || has_shortcode( $content, 'eventflow_track' ) ) {
				$view       = 'track';
				$page_title = __( 'Track Registration', 'eventflow' );
			} elseif ( 'event-portal' === $slug || has_shortcode( $content, 'eventflow_landing' ) ) {
				$view       = 'landing';
				$page_title = __( 'Event Portal', 'eventflow' );
			} else {
				$view       = 'custom-page';
				$page_title = get_the_title();
			}
		}

		if ( isset( $_GET['eventflow_view'] ) ) {
			$requested = sanitize_key( $_GET['eventflow_view'] );
			if ( in_array( $requested, array( 'auth-home', 'auth', 'dashboard', 'staff-dashboard', 'landing', 'track' ), true ) ) {
				$view = $requested;
			}
		}

		// Enqueue required assets
		if ( 'staff-dashboard' === $view ) {
			EventFlow_Shortcodes::enqueue_staff_assets();
		} else {
			EventFlow_Shortcodes::enqueue_frontend_assets();
		}

		// Pass view & title globals to the App Shell template
		$GLOBALS['ef_standalone_view']  = $view;
		$GLOBALS['ef_standalone_title'] = $page_title;

		$shell_path = EVENTFLOW_PLUGIN_DIR . 'templates/frontend/app-shell.php';
		if ( file_exists( $shell_path ) ) {
			return $shell_path;
		}

		return $template;
	}
}
