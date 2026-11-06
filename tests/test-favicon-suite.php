<?php
/**
 * Favicon Suite Verification Test
 *
 * Verifies that the complete favicon and touch icon suite is properly generated,
 * loaded, filtered, and rendered across all EventFlow contexts.
 */

define( 'ABSPATH', __DIR__ . '/../' );
if ( ! defined( 'EVENTFLOW_PLUGIN_DIR' ) ) { define( 'EVENTFLOW_PLUGIN_DIR', file_exists( __DIR__ . '/../includes/class-db.php' ) ? dirname( __DIR__ ) . '/' : __DIR__ . '/../eventflow/' ); }
define( 'EVENTFLOW_PLUGIN_URL', 'https://example.com/wp-content/plugins/eventflow/' );
define( 'EVENTFLOW_VERSION', '4.0.2' );

// Mock WordPress functions if not defined
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) { return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) { return $url; }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $str ) { return htmlspecialchars( $str, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $str ) { return htmlspecialchars( $str, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) { return trim( strip_tags( $str ) ); }
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $val ) { return $val; }
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) { return 'SIBACOM EVENT 2026'; }
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {}
}
if ( ! function_exists( 'has_site_icon' ) ) {
	function has_site_icon() { return false; }
}

$GLOBALS['ef_mock_options'] = array(
	'eventflow_primary_color'   => '#36B49F',
	'eventflow_secondary_color' => '#27A991',
	'eventflow_favicon_url'     => '',
);

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return isset( $GLOBALS['ef_mock_options'][ $name ] ) ? $GLOBALS['ef_mock_options'][ $name ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value ) {
		$GLOBALS['ef_mock_options'][ $name ] = $value;
		return true;
	}
}

// Require autoloader and class
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-autoloader.php';
EventFlow_Autoloader::register();

$passed = 0;
$failed = 0;

function ef_assert( $condition, $message ) {
	global $passed, $failed;
	if ( $condition ) {
		echo "  [PASS] {$message}\n";
		$passed++;
	} else {
		echo "  [FAIL] {$message}\n";
		$failed++;
	}
}

echo "\n======================================================================\n";
echo "FAVICON & TOUCH ICON SUITE VERIFICATION\n";
echo "======================================================================\n";

// 1. Check physical files on disk
$fav_dir = EVENTFLOW_PLUGIN_DIR . 'assets/images/favicon/';
$required_files = array(
	'favicon.ico'               => 1000,
	'favicon.svg'               => 100,
	'favicon-16x16.png'         => 300,
	'favicon-32x32.png'         => 500,
	'favicon-48x48.png'         => 500,
	'apple-touch-icon.png'      => 1000,
	'android-chrome-192x192.png'=> 2000,
	'android-chrome-512x512.png'=> 5000,
	'site.webmanifest'          => 100,
	'index.php'                 => 10,
);

foreach ( $required_files as $file => $min_size ) {
	$path = $fav_dir . $file;
	$exists = file_exists( $path );
	$size = $exists ? filesize( $path ) : 0;
	ef_assert( $exists && $size >= $min_size, "Asset '{$file}' exists and is valid ({$size} bytes)" );
}

// 2. Verify ICO magic bytes (00 00 01 00)
$ico_data = file_get_contents( $fav_dir . 'favicon.ico' );
$ico_magic = substr( $ico_data, 0, 4 );
ef_assert( "\x00\x00\x01\x00" === $ico_magic, "favicon.ico has valid Windows Icon container header" );

// 3. Verify PNG magic bytes (89 50 4E 47)
$png_files = array( 'favicon-16x16.png', 'favicon-32x32.png', 'apple-touch-icon.png', 'android-chrome-192x192.png' );
foreach ( $png_files as $pf ) {
	$png_data = file_get_contents( $fav_dir . $pf );
	ef_assert( "\x89PNG" === substr( $png_data, 0, 4 ), "{$pf} has valid PNG signature bytes" );
}

// 4. Verify WebManifest JSON validity
$manifest_raw = file_get_contents( $fav_dir . 'site.webmanifest' );
$manifest_json = json_decode( $manifest_raw, true );
ef_assert( is_array( $manifest_json ) && isset( $manifest_json['icons'] ) && count( $manifest_json['icons'] ) >= 2, "site.webmanifest contains valid JSON with 2+ icons" );
ef_assert( $manifest_json['theme_color'] === '#27A991', "site.webmanifest defines official brand theme_color #27A991" );

// 5. Test EventFlow_Favicon class autoloading
ef_assert( class_exists( 'EventFlow_Favicon' ), "EventFlow_Favicon class is successfully autoloaded" );

// 6. Test get_icons() return structure
$icons = EventFlow_Favicon::get_icons();
ef_assert( isset( $icons['ico'] ) && strpos( $icons['ico'], 'favicon.ico' ) !== false, "get_icons() returns valid 'ico' URL" );
ef_assert( isset( $icons['svg'] ) && strpos( $icons['svg'], 'favicon.svg' ) !== false, "get_icons() returns valid 'svg' URL" );
ef_assert( isset( $icons['p16'] ) && strpos( $icons['p16'], 'favicon-16x16.png' ) !== false, "get_icons() returns valid 'p16' URL" );
ef_assert( isset( $icons['p32'] ) && strpos( $icons['p32'], 'favicon-32x32.png' ) !== false, "get_icons() returns valid 'p32' URL" );
ef_assert( isset( $icons['apple'] ) && strpos( $icons['apple'], 'apple-touch-icon.png' ) !== false, "get_icons() returns valid 'apple' URL" );
ef_assert( isset( $icons['manifest'] ) && strpos( $icons['manifest'], 'site.webmanifest' ) !== false, "get_icons() returns valid 'manifest' URL" );

// 7. Test get_favicon_url() resolution
ef_assert( strpos( EventFlow_Favicon::get_favicon_url( '16' ), 'favicon-16x16.png' ) !== false, "get_favicon_url('16') resolves 16x16 PNG" );
ef_assert( strpos( EventFlow_Favicon::get_favicon_url( '32' ), 'favicon-32x32.png' ) !== false, "get_favicon_url('32') resolves 32x32 PNG" );
ef_assert( strpos( EventFlow_Favicon::get_favicon_url( 'apple' ), 'apple-touch-icon.png' ) !== false, "get_favicon_url('apple') resolves Apple touch icon" );

// 8. Test render_meta_tags() HTML output
ob_start();
EventFlow_Favicon::render_meta_tags();
$output = ob_get_clean();

ef_assert( strpos( $output, 'rel="icon"' ) !== false, "render_meta_tags() outputs standard rel='icon'" );
ef_assert( strpos( $output, 'rel="shortcut icon"' ) !== false, "render_meta_tags() outputs legacy shortcut icon" );
ef_assert( strpos( $output, 'type="image/svg+xml"' ) !== false, "render_meta_tags() outputs modern SVG favicon link" );
ef_assert( strpos( $output, 'rel="apple-touch-icon"' ) !== false, "render_meta_tags() outputs Apple touch icon link" );
ef_assert( strpos( $output, 'rel="manifest"' ) !== false, "render_meta_tags() outputs WebManifest link" );
ef_assert( strpos( $output, 'name="theme-color"' ) !== false, "render_meta_tags() outputs theme-color meta tag" );
ef_assert( strpos( $output, 'name="msapplication-TileColor"' ) !== false, "render_meta_tags() outputs msapplication-TileColor" );

// 9. Test deduplication guard (second call produces zero output)
ob_start();
EventFlow_Favicon::render_meta_tags();
$dup_output = ob_get_clean();
ef_assert( empty( $dup_output ), "Duplicate render_meta_tags() invocation produces zero duplicate tags" );

// 10. Test WordPress core site icon filter
$filtered_16 = EventFlow_Favicon::filter_site_icon_url( '', 16, 1 );
$filtered_32 = EventFlow_Favicon::filter_site_icon_url( '', 32, 1 );
$filtered_180 = EventFlow_Favicon::filter_site_icon_url( '', 180, 1 );
$existing_override = EventFlow_Favicon::filter_site_icon_url( 'https://custom.com/icon.png', 32, 1 );

ef_assert( strpos( $filtered_16, 'favicon-16x16.png' ) !== false, "filter_site_icon_url fallback delivers 16px icon" );
ef_assert( strpos( $filtered_32, 'favicon-32x32.png' ) !== false, "filter_site_icon_url fallback delivers 32px icon" );
ef_assert( strpos( $filtered_180, 'apple-touch-icon.png' ) !== false, "filter_site_icon_url fallback delivers Apple icon" );
ef_assert( $existing_override === 'https://custom.com/icon.png', "filter_site_icon_url preserves custom WP theme site icon if configured" );

// 11. Test custom favicon override option
update_option( 'eventflow_favicon_url', 'https://example.com/custom-brand-fav.png' );
$custom_icons = EventFlow_Favicon::get_icons();
ef_assert( $custom_icons['ico'] === 'https://example.com/custom-brand-fav.png', "Custom favicon URL override dynamically respected" );

echo "\n----------------------------------------------------------------------\n";
echo "RESULTS: " . ( $passed + $failed ) . " Total Assertions | {$passed} Passed | {$failed} Failed\n";
echo "----------------------------------------------------------------------\n";

if ( $failed > 0 ) {
	exit( 1 );
}
exit( 0 );
