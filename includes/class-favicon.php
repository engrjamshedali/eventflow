<?php
/**
 * EventFlow Favicon & Brand Icon Manager
 *
 * Provides a comprehensive, production-grade favicon and touch-icon suite for EventFlow
 * and SIBACOM EVENT portals. Handles multi-resolution .ico, modern .svg, Apple touch icons,
 * Android PWA manifests, WordPress site icon filters, and root /favicon.ico fallback.
 *
 * @package EventFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Favicon {

	/**
	 * Rendered flag to prevent duplicate output in the same request
	 *
	 * @var bool
	 */
	private static $rendered = false;

	/**
	 * Initialize favicon hooks
	 */
	public static function init() {
		// Output favicon meta tags on frontend, admin, and login screens
		add_action( 'wp_head', array( __CLASS__, 'render_meta_tags' ), 1 );
		add_action( 'admin_head', array( __CLASS__, 'render_admin_meta_tags' ), 1 );
		add_action( 'login_head', array( __CLASS__, 'render_meta_tags' ), 1 );

		// Core WordPress site icon filters
		add_filter( 'get_site_icon_url', array( __CLASS__, 'filter_site_icon_url' ), 10, 3 );
		add_filter( 'site_icon_meta_tags', array( __CLASS__, 'filter_site_icon_meta_tags' ), 10, 1 );

		// Intercept root /favicon.ico requests if physical file is missing
		add_action( 'init', array( __CLASS__, 'handle_direct_favicon_request' ), 1 );
	}

	/**
	 * Get the active icon asset URLs
	 *
	 * Priority order:
	 * 1. Custom Favicon URL configured in EventFlow Settings (`eventflow_favicon_url`)
	 * 2. Active Event Logo URL if provided
	 * 3. Core WordPress site icon (`site_icon`) if uploaded
	 * 4. Bundled official SIBACOM / Sukkur IBA University emblem suite
	 *
	 * @return array Associative array of icon URLs
	 */
	public static function get_icons() {
		$custom_fav = get_option( 'eventflow_favicon_url', '' );
		$version    = defined( 'EVENTFLOW_VERSION' ) ? EVENTFLOW_VERSION : '4.0.2';
		$fav_url    = EVENTFLOW_PLUGIN_URL . 'assets/images/favicon/';

		// If a custom favicon URL is specified by administrator
		if ( ! empty( $custom_fav ) ) {
			$escaped_custom = esc_url_raw( $custom_fav );
			return array(
				'ico'      => $escaped_custom,
				'svg'      => $fav_url . 'favicon.svg?v=' . $version,
				'p16'      => $escaped_custom,
				'p32'      => $escaped_custom,
				'p48'      => $escaped_custom,
				'apple'    => $escaped_custom,
				'p192'     => $escaped_custom,
				'p512'     => $escaped_custom,
				'manifest' => $fav_url . 'site.webmanifest?v=' . $version,
			);
		}

		// Bundled complete high-resolution favicon suite
		return array(
			'ico'      => $fav_url . 'favicon.ico?v=' . $version,
			'svg'      => $fav_url . 'favicon.svg?v=' . $version,
			'p16'      => $fav_url . 'favicon-16x16.png?v=' . $version,
			'p32'      => $fav_url . 'favicon-32x32.png?v=' . $version,
			'p48'      => $fav_url . 'favicon-48x48.png?v=' . $version,
			'apple'    => $fav_url . 'apple-touch-icon.png?v=' . $version,
			'p192'     => $fav_url . 'android-chrome-192x192.png?v=' . $version,
			'p512'     => $fav_url . 'android-chrome-512x512.png?v=' . $version,
			'manifest' => $fav_url . 'site.webmanifest?v=' . $version,
		);
	}

	/**
	 * Get a specific favicon URL by key or dimension
	 *
	 * @param string $key 'ico', 'svg', '16', '32', '48', 'apple', '192', '512', 'manifest'
	 * @return string Full URL to the requested icon asset
	 */
	public static function get_favicon_url( $key = '32' ) {
		$icons = self::get_icons();
		$map   = array(
			'ico'      => 'ico',
			'svg'      => 'svg',
			'16'       => 'p16',
			'32'       => 'p32',
			'48'       => 'p48',
			'apple'    => 'apple',
			'192'      => 'p192',
			'512'      => 'p512',
			'manifest' => 'manifest',
		);

		$resolved_key = isset( $map[ $key ] ) ? $map[ $key ] : 'p32';
		return isset( $icons[ $resolved_key ] ) ? $icons[ $resolved_key ] : $icons['p32'];
	}

	/**
	 * Get application title for meta tags
	 *
	 * @return string
	 */
	public static function get_app_name() {
		global $wpdb;
		if ( ! empty( $wpdb ) && class_exists( 'EventFlow_Event_Manager' ) ) {
			try {
				$event = EventFlow_Event_Manager::get_active_event();
				if ( $event && ! empty( $event->title ) ) {
					return $event->title;
				}
			} catch ( Exception $e ) {
				// Fallback to bloginfo
			}
		}
		$name = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : 'SIBACOM EVENT';
		return ! empty( $name ) ? $name : 'SIBACOM EVENT';
	}

	/**
	 * Render standard HTML favicon & touch-icon tags
	 */
	public static function render_meta_tags() {
		if ( self::$rendered ) {
			return;
		}
		self::$rendered = true;

		$icons       = self::get_icons();
		$app_name    = self::get_app_name();
		$theme_color = get_option( 'eventflow_primary_color', '#36B49F' );
		$tile_color  = get_option( 'eventflow_secondary_color', '#27A991' );
		?>
<!-- EventFlow & SIBACOM Favicon Suite -->
<link rel="shortcut icon" href="<?php echo esc_url( $icons['ico'] ); ?>" type="image/x-icon">
<link rel="icon" href="<?php echo esc_url( $icons['ico'] ); ?>" type="image/x-icon">
<link rel="icon" type="image/svg+xml" href="<?php echo esc_url( $icons['svg'] ); ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?php echo esc_url( $icons['p16'] ); ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url( $icons['p32'] ); ?>">
<link rel="icon" type="image/png" sizes="48x48" href="<?php echo esc_url( $icons['p48'] ); ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url( $icons['apple'] ); ?>">
<link rel="manifest" href="<?php echo esc_url( $icons['manifest'] ); ?>">
<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( $app_name ); ?>">
<meta name="application-name" content="<?php echo esc_attr( $app_name ); ?>">
<meta name="msapplication-TileColor" content="<?php echo esc_attr( $tile_color ); ?>">
<meta name="theme-color" content="<?php echo esc_attr( $theme_color ); ?>">
<!-- /EventFlow Favicon Suite -->
		<?php
	}

	/**
	 * Render favicon meta tags in WordPress Admin
	 */
	public static function render_admin_meta_tags() {
		// Only render if on an EventFlow admin screen or if WordPress has no site icon configured
		$screen_id = function_exists( 'get_current_screen' ) && get_current_screen() ? get_current_screen()->id : '';
		$is_ef     = false !== strpos( $screen_id, 'eventflow' ) || ( isset( $_GET['page'] ) && 0 === strpos( $_GET['page'], 'eventflow' ) );

		if ( $is_ef || ! has_site_icon() ) {
			self::render_meta_tags();
		}
	}

	/**
	 * Filter WordPress core get_site_icon_url
	 *
	 * When the theme/WordPress does not have an icon configured, supply EventFlow's icon.
	 *
	 * @param string $url
	 * @param int    $size
	 * @param int    $blog_id
	 * @return string
	 */
	public static function filter_site_icon_url( $url, $size, $blog_id ) {
		if ( ! empty( $url ) ) {
			return $url;
		}

		if ( $size <= 16 ) {
			return self::get_favicon_url( '16' );
		} elseif ( $size <= 32 ) {
			return self::get_favicon_url( '32' );
		} elseif ( $size <= 48 ) {
			return self::get_favicon_url( '48' );
		} elseif ( $size <= 180 ) {
			return self::get_favicon_url( 'apple' );
		} elseif ( $size <= 192 ) {
			return self::get_favicon_url( '192' );
		} else {
			return self::get_favicon_url( '512' );
		}
	}

	/**
	 * Filter WordPress core site_icon_meta_tags
	 *
	 * Ensures standard tags are properly populated if core wp_site_icon() runs.
	 *
	 * @param array $meta_tags
	 * @return array
	 */
	public static function filter_site_icon_meta_tags( $meta_tags ) {
		if ( empty( $meta_tags ) ) {
			$icons       = self::get_icons();
			$meta_tags[] = sprintf( '<link rel="icon" href="%s" sizes="32x32" />', esc_url( $icons['p32'] ) );
			$meta_tags[] = sprintf( '<link rel="icon" href="%s" sizes="192x192" />', esc_url( $icons['p192'] ) );
			$meta_tags[] = sprintf( '<link rel="apple-touch-icon" href="%s" />', esc_url( $icons['apple'] ) );
			$meta_tags[] = sprintf( '<meta name="msapplication-TileImage" content="%s" />', esc_url( $icons['p512'] ) );
		}
		return $meta_tags;
	}

	/**
	 * Intercept direct browser requests to /favicon.ico
	 *
	 * If physical favicon.ico does not exist on disk, stream our bundled .ico with
	 * aggressive caching headers instead of letting WordPress return a 404.
	 */
	public static function handle_direct_favicon_request() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );

		if ( '/favicon.ico' === $path || ( isset( $_GET['favicon'] ) && 'eventflow' === $_GET['favicon'] ) ) {
			if ( ! file_exists( ABSPATH . 'favicon.ico' ) ) {
				$ico_file = EVENTFLOW_PLUGIN_DIR . 'assets/images/favicon/favicon.ico';
				if ( file_exists( $ico_file ) ) {
					// Clean output buffer if open
					while ( ob_get_level() > 0 ) {
						ob_end_clean();
					}
					header( 'Content-Type: image/x-icon' );
					header( 'Content-Length: ' . filesize( $ico_file ) );
					header( 'Cache-Control: public, max-age=604800, stale-while-revalidate=86400' );
					header( 'X-Content-Type-Options: nosniff' );
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
					readfile( $ico_file );
					exit;
				}
			}
		}
	}
}
