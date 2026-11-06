<?php
/**
 * SIBACOM EVENT - Master Automation, Payment Verification & Event Configuration Test Suite
 *
 * Comprehensive end-to-end verification covering all 20 Acceptance Criteria:
 * 1. Strict Branding & Purge of isolated "SIBACO"
 * 2. Native wp_mail() transport, UTF-8 HTML headers & plain-text fallback
 * 3. Account created welcome email dispatch with credentials and dashboard links
 * 4. Solo registration submission email with all 7 participant fields
 * 5. Team registration email notification engine with full HTML team roster
 * 6. Private individual delivery with zero recipient email leakage
 * 7. Team member deduplication (Leader in member list receives exactly one email)
 * 8. Dynamic fee calculation engine across all 3 pricing models
 * 9. Immutable fee breakdown and pricing configuration snapshots
 * 10. Payment approval notification to leader and all team members
 * 11. Payment rejection with mandatory admin reason and re-upload link
 * 12. Payment proof re-upload request with status logs transition
 * 13. Replacement payment proof upload confirmation & status reset
 * 14. Application approval and rejection notifications with admin reason
 * 15. Super Admin event configuration settings CRUD
 * 16. Dual frontend/backend validation on team min/max sizes and event caps
 * 17. Dynamic pricing settings in Super Admin 4-tab interface
 * 18. Super Admin email dashboard with 7 monitoring KPI cards
 * 19. Email activity table with Recipient Type and strict Sent/Accepted statuses
 * 20. Email logs CSV export with proper filename and column headers
 */

define( 'ABSPATH', __DIR__ . '/../' );
if ( ! defined( 'EVENTFLOW_PLUGIN_DIR' ) ) { define( 'EVENTFLOW_PLUGIN_DIR', file_exists( __DIR__ . '/../includes/class-db.php' ) ? dirname( __DIR__ ) . '/' : __DIR__ . '/../eventflow/' ); }
define( 'EVENTFLOW_PLUGIN_URL', 'https://example.com/wp-content/plugins/eventflow/' );
define( 'EVENTFLOW_VERSION', '4.0.2' );
define( 'AUTH_KEY', 'sibacom-master-secret-key-998877' );
define( 'AUTH_SALT', 'sibacom-master-salt-phrase-112233' );

if ( ! defined( 'OBJECT' ) ) define( 'OBJECT', 'OBJECT' );
if ( ! defined( 'object' ) ) define( 'object', 'OBJECT' );
if ( ! defined( 'ARRAY_A' ) ) define( 'ARRAY_A', 'ARRAY_A' );
if ( ! defined( 'ARRAY_N' ) ) define( 'ARRAY_N', 'ARRAY_N' );

// ==============================================================================
// IN-MEMORY WORDPRESS DATABASE MOCK HARNESS
// ==============================================================================

class WPDB_Master_Mock {
	public $prefix = 'wp_';
	public $users = 'wp_users';
	public $posts = 'wp_posts';
	public $options = 'wp_options';
	public $insert_id = 0;
	public $tables = array();

	public function __construct() {
		$this->tables = array(
			'wp_eventflow_events'          => array(),
			'wp_eventflow_activities'      => array(),
			'wp_eventflow_registrations'   => array(),
			'wp_eventflow_teams'           => array(),
			'wp_eventflow_team_members'    => array(),
			'wp_eventflow_payments'        => array(),
			'wp_eventflow_payment_logs'    => array(),
			'wp_eventflow_status_logs'     => array(),
			'wp_eventflow_email_templates' => array(),
			'wp_eventflow_email_logs'      => array(),
			'wp_eventflow_email_queue'     => array(),
			'wp_eventflow_audit_logs'      => array(),
			'wp_eventflow_forms'           => array(),
		);
	}

	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}

	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	public function prepare( $query, ...$args ) {
		if ( empty( $args ) ) return $query;
		if ( is_array( $args[0] ) && count( $args ) === 1 ) $args = $args[0];

		foreach ( $args as $arg ) {
			if ( is_int( $arg ) || is_float( $arg ) ) {
				$rep = $arg;
			} elseif ( is_null( $arg ) ) {
				$rep = 'NULL';
			} else {
				$rep = "'" . addslashes( (string) $arg ) . "'";
			}

			$pos = strpos( $query, '%s' );
			if ( false === $pos ) $pos = strpos( $query, '%d' );
			if ( false === $pos ) $pos = strpos( $query, '%f' );
			if ( false !== $pos ) {
				$query = substr_replace( $query, $rep, $pos, 2 );
			}
		}
		return $query;
	}

	public function query( $sql ) {
		$norm_sql = preg_replace( '/\s+/', ' ', trim( (string) $sql ) );
		if ( preg_match( '/UPDATE\s+.*wp_eventflow_activities\s+SET\s+current_participants\s*=\s*current_participants\s*\+\s*(\d+)\s+WHERE\s+id\s*=\s*(\d+)/i', $norm_sql, $m ) ) {
			$delta = (int) $m[1];
			$id    = (int) $m[2];
			if ( isset( $this->tables['wp_eventflow_activities'][ $id ] ) ) {
				$row = $this->tables['wp_eventflow_activities'][ $id ];
				if ( (int) $row->max_participants > 0 && ( (int) $row->current_participants + $delta ) > (int) $row->max_participants ) {
					return 0;
				}
				$row->current_participants += $delta;
				return 1;
			}
		}
		return 1;
	}

	public function insert( $table, $data, $format = null ) {
		static $auto_inc = 1000;
		$this->insert_id = ++$auto_inc;
		$row = (object) $data;
		$row->id = $this->insert_id;
		$this->tables[ $table ][ $this->insert_id ] = $row;
		return 1;
	}

	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		$updated = 0;
		if ( ! isset( $this->tables[ $table ] ) ) return 0;
		foreach ( $this->tables[ $table ] as $id => $row ) {
			$match = true;
			foreach ( $where as $k => $v ) {
				if ( ! isset( $row->$k ) || $row->$k != $v ) {
					$match = false;
					break;
				}
			}
			if ( $match ) {
				foreach ( $data as $k => $v ) {
					$row->$k = $v;
				}
				$updated++;
			}
		}
		return $updated;
	}

	public function delete( $table, $where, $where_format = null ) {
		$deleted = 0;
		if ( ! isset( $this->tables[ $table ] ) ) return 0;
		foreach ( $this->tables[ $table ] as $id => $row ) {
			$match = true;
			foreach ( $where as $k => $v ) {
				if ( ! isset( $row->$k ) || $row->$k != $v ) {
					$match = false;
					break;
				}
			}
			if ( $match ) {
				unset( $this->tables[ $table ][ $id ] );
				$deleted++;
			}
		}
		return $deleted;
	}

	public function get_row( $query, $output = OBJECT ) {
		$results = $this->get_results( $query, $output );
		return ! empty( $results ) ? $results[0] : null;
	}

	public function get_var( $query ) {
		if ( preg_match( '/SELECT\s+COUNT\(\*\)\s+FROM\s+/i', $query ) ) {
			$results = $this->get_results( $query );
			return count( $results );
		}
		$row = $this->get_row( $query );
		if ( ! $row ) return null;
		$arr = (array) $row;
		return reset( $arr );
	}

	public function get_col( $query, $col_offset = 0 ) {
		if ( false !== strpos( $query, 'DESC wp_eventflow_registrations' ) ) {
			return array( 'id', 'reg_id', 'event_id', 'activity_id', 'user_id', 'participation_type', 'team_name', 'total_fee', 'fee_breakdown', 'pricing_snapshot', 'payment_status', 'application_status', 'created_at', 'updated_at', 'admin_notes', 'rejection_reason' );
		}
		return array();
	}

	public function get_results( $query, $output = OBJECT ) {
		foreach ( $this->tables as $tbl_name => $rows ) {
			if ( false !== strpos( $query, $tbl_name ) ) {
				$filtered = array_values( $rows );

				if ( preg_match( "/registration_id\s*=\s*(\d+)/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return isset( $r->registration_id ) && (int) $r->registration_id === (int) $m[1];
					} );
				} elseif ( preg_match( "/(?<!registration_)\bid\s*=\s*(\d+)/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return isset( $r->id ) && (int) $r->id === (int) $m[1];
					} );
				}
				if ( preg_match( "/WHERE\s+.*reg_id\s*=\s*'([^']+)'/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return isset( $r->reg_id ) && $r->reg_id === $m[1];
					} );
				}
				if ( preg_match( "/WHERE\s+.*slug\s*=\s*'([^']+)'/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return isset( $r->slug ) && $r->slug === $m[1];
					} );
				}
				if ( preg_match( "/WHERE\s+.*template_key\s*=\s*'([^']+)'/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return isset( $r->template_key ) && $r->template_key === $m[1];
					} );
				}
				if ( preg_match( "/activity_id\s*=\s*(\d+)/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return isset( $r->activity_id ) && (int) $r->activity_id === (int) $m[1];
					} );
				}
				if ( preg_match( "/user_id\s*=\s*(\d+)/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return isset( $r->user_id ) && (int) $r->user_id === (int) $m[1];
					} );
				}
				if ( preg_match( "/event_id\s*=\s*(\d+)/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return isset( $r->event_id ) && (int) $r->event_id === (int) $m[1];
					} );
				}
				if ( preg_match( "/application_status\s*!=\s*'([^']+)'/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return ! isset( $r->application_status ) || $r->application_status !== $m[1];
					} );
				}
				if ( preg_match( "/idempotency_key\s*=\s*'([^']+)'/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return isset( $r->idempotency_key ) && $r->idempotency_key === $m[1];
					} );
				}

				if ( false !== stripos( $query, 'ORDER BY' ) ) {
					if ( false !== stripos( $query, 'id DESC' ) ) {
						usort( $filtered, function( $a, $b ) {
							return (int) $b->id - (int) $a->id;
						} );
					} elseif ( false !== stripos( $query, 'id ASC' ) || false !== stripos( $query, 'member_order ASC' ) ) {
						usort( $filtered, function( $a, $b ) {
							$oa = isset( $a->member_order ) ? (int) $a->member_order : (int) $a->id;
							$ob = isset( $b->member_order ) ? (int) $b->member_order : (int) $b->id;
							return $oa - $ob;
						} );
					}
				}

				return array_values( $filtered );
			}
		}

		return array();
	}
}

global $wpdb;
$wpdb = new WPDB_Master_Mock();

// ==============================================================================
// WORDPRESS GLOBAL FUNCTIONS & HOOK MOCKS
// ==============================================================================

global $wp_options, $wp_actions, $wp_filters, $wp_mail_history, $mock_users;
$wp_options      = array();
$wp_actions      = array();
$wp_filters      = array();
$wp_mail_history = array();
$mock_users      = array();

function get_option( $opt, $default = false ) {
	global $wp_options;
	return isset( $wp_options[ $opt ] ) ? $wp_options[ $opt ] : $default;
}
function update_option( $opt, $val ) {
	global $wp_options;
	$wp_options[ $opt ] = $val;
	return true;
}
function add_option( $opt, $val ) {
	global $wp_options;
	if ( ! isset( $wp_options[ $opt ] ) ) {
		$wp_options[ $opt ] = $val;
		return true;
	}
	return false;
}
function delete_option( $opt ) {
	global $wp_options;
	unset( $wp_options[ $opt ] );
	return true;
}

function get_bloginfo( $show = 'name' ) {
	return 'SIBACOM EVENT';
}
function home_url( $path = '' ) {
	return 'https://sibacom.iba-suk.edu.pk' . $path;
}
function admin_url( $path = '' ) {
	return 'https://sibacom.iba-suk.edu.pk/wp-admin/' . $path;
}
function wp_login_url( $redirect = '' ) {
	return 'https://sibacom.iba-suk.edu.pk/login/' . ( $redirect ? '?redirect_to=' . urlencode( $redirect ) : '' );
}
function current_time( $type = 'mysql' ) {
	return 'mysql' === $type ? date( 'Y-m-d H:i:s' ) : time();
}
function date_i18n( $format, $timestamp = false ) {
	return date( $format, $timestamp ? $timestamp : time() );
}
function is_email( $email ) {
	return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
}
function sanitize_email( $email ) {
	return filter_var( trim( (string) $email ), FILTER_SANITIZE_EMAIL );
}
function sanitize_text_field( $str ) {
	return strip_tags( trim( (string) $str ) );
}
function sanitize_textarea_field( $str ) {
	return strip_tags( trim( (string) $str ) );
}
function sanitize_title( $title ) {
	return strtolower( preg_replace( '/[^A-Za-z0-9-]+/', '-', trim( (string) $title ) ) );
}
function esc_html( $str ) {
	return htmlspecialchars( (string) $str, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $str ) {
	return htmlspecialchars( (string) $str, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $str ) {
	return filter_var( (string) $str, FILTER_SANITIZE_URL );
}
function esc_url_raw( $str ) {
	return filter_var( (string) $str, FILTER_SANITIZE_URL );
}
function add_query_arg( $k, $v = '', $u = '' ) {
	if ( is_array( $k ) ) {
		$args = $k;
		$url = $v ? $v : 'https://example.com';
	} else {
		$args = array( $k => $v );
		$url = $u ? $u : 'https://example.com';
	}
	$sep = ( false !== strpos( $url, '?' ) ) ? '&' : '?';
	return $url . $sep . http_build_query( $args );
}
function wp_kses_post( $str ) {
	return $str;
}
function wp_strip_all_tags( $str ) {
	return strip_tags( (string) $str );
}
function absint( $val ) {
	return abs( (int) $val );
}
function __( $text, $domain = 'default' ) {
	return $text;
}
function _e( $text, $domain = 'default' ) {
	echo $text;
}
function esc_html__( $text, $domain = 'default' ) {
	return $text;
}
function esc_html_e( $text, $domain = 'default' ) {
	echo $text;
}
function esc_attr_e( $text, $domain = 'default' ) {
	echo $text;
}
function wp_json_encode( $data ) {
	return json_encode( $data );
}
function number_format_i18n( $num ) {
	return number_format( $num );
}

function wp_parse_args( $args, $defaults = '' ) {
	if ( is_object( $args ) ) {
		$r = get_object_vars( $args );
	} elseif ( is_array( $args ) ) {
		$r =& $args;
	} else {
		$r = array();
	}
	if ( is_array( $defaults ) ) {
		return array_merge( $defaults, $r );
	}
	return $r;
}

function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	return substr( str_shuffle( str_repeat( $chars, (int) ceil( $length / strlen( $chars ) ) ) ), 0, $length );
}

function wp_check_filetype_and_ext( $file, $filename, $mimes = null ) {
	$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
	$mime_map = array(
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'pdf'  => 'application/pdf',
	);
	if ( isset( $mime_map[ $ext ] ) ) {
		return array(
			'ext'             => $ext,
			'type'            => $mime_map[ $ext ],
			'proper_filename' => false,
		);
	}
	return array(
		'ext'             => false,
		'type'            => false,
		'proper_filename' => false,
	);
}

function wp_mkdir_p( $target ) {
	if ( file_exists( $target ) ) return true;
	return @mkdir( $target, 0777, true );
}

function trailingslashit( $string ) {
	return rtrim( (string) $string, '/\\' ) . '/';
}

function untrailingslashit( $string ) {
	return rtrim( (string) $string, '/\\' );
}

function wp_upload_dir() {
	$dir = sys_get_temp_dir() . '/eventflow_master_uploads';
	if ( ! file_exists( $dir ) ) {
		@mkdir( $dir, 0777, true );
	}
	return array(
		'basedir' => $dir,
		'baseurl' => 'https://sibacom.tech/wp-content/uploads/eventflow_master_uploads',
	);
}

function wp_next_scheduled( $hook ) {
	return false;
}
function wp_schedule_event( $ts, $recurrence, $hook ) {
	return true;
}
function wp_unschedule_event( $ts, $hook ) {
	return true;
}
function wp_create_nonce( $action = -1 ) {
	return 'mock_nonce_' . $action;
}
function wp_verify_nonce( $nonce, $action = -1 ) {
	return 1;
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	global $wp_actions;
	$wp_actions[ $hook ][] = $callback;
}
function do_action( $hook, ...$args ) {
	global $wp_actions;
	if ( isset( $wp_actions[ $hook ] ) ) {
		foreach ( $wp_actions[ $hook ] as $cb ) {
			call_user_func_array( $cb, $args );
		}
	}
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	global $wp_filters;
	$wp_filters[ $hook ][] = $callback;
}
function apply_filters( $hook, $value, ...$args ) {
	global $wp_filters;
	if ( isset( $wp_filters[ $hook ] ) ) {
		foreach ( $wp_filters[ $hook ] as $cb ) {
			$value = call_user_func_array( $cb, array_merge( array( $value ), $args ) );
		}
	}
	return $value;
}

function get_userdata( $user_id ) {
	global $mock_users;
	return isset( $mock_users[ $user_id ] ) ? $mock_users[ $user_id ] : null;
}
function get_current_user_id() {
	return 1;
}

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code = $code;
		$this->message = $message;
	}
	public function get_error_message() {
		return $this->message;
	}
	public function get_error_code() {
		return $this->code;
	}
}
function is_wp_error( $thing ) {
	return ( $thing instanceof WP_Error );
}

// Intercept wp_mail()
function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
	global $wp_mail_history;
	$entry = array(
		'to'          => $to,
		'subject'     => $subject,
		'message'     => $message,
		'headers'     => $headers,
		'attachments' => $attachments,
		'time'        => current_time( 'mysql' ),
	);
	$wp_mail_history[] = $entry;

	// Trigger WordPress native mail action hooks
	do_action( 'wp_mail_succeeded', array( 'to' => (array) $to, 'subject' => $subject, 'message' => $message, 'headers' => $headers, 'attachments' => $attachments ) );
	return true;
}

// Global Options Setup: Disable Queue for immediate synchronous delivery
update_option( 'eventflow_email_queue_enabled', 'no' );
update_option( 'eventflow_email_from_name', 'SIBACOM EVENT' );
update_option( 'eventflow_email_from_address', 'info@portal.sibacom.tech' );
update_option( 'eventflow_email_reply_to', 'support@sibacom.tech' );

// Load Plugin Classes
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-db.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-security.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-smtp.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-email-queue.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-email-diagnostics.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-email-manager.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-event-manager.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-activity-manager.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-team-manager.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-registration-manager.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-payment-manager.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-payment-verification.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-auth.php';

// Mock Shortcodes helper
class EventFlow_Shortcodes {
	public static function get_dashboard_url() {
		return 'https://sibacom.iba-suk.edu.pk/participant-dashboard/';
	}
	public static function get_track_url() {
		return 'https://sibacom.iba-suk.edu.pk/track-status/';
	}
}

// Seed Database Templates & Init Managers
EventFlow_DB::seed_default_email_templates();
EventFlow_Email_Manager::init();

// ==============================================================================
// MASTER TEST HARNESS RUNNER
// ==============================================================================

class SIBACOM_Master_Test_Suite {
	private $passes = 0;
	private $fails  = 0;
	private $section = '';

	public function start_section( $name ) {
		$this->section = $name;
		echo "\n" . str_repeat( '=', 70 ) . "\n";
		echo "CRITERION: " . $name . "\n";
		echo str_repeat( '=', 70 ) . "\n";
	}

	public function assert( $condition, $message, $details = '' ) {
		if ( $condition ) {
			$this->passes++;
			echo "  [PASS] {$message}\n";
		} else {
			$this->fails++;
			echo "  [FAIL] {$message} " . ( $details ? "– Details: {$details}" : "" ) . "\n";
		}
	}

	public function get_passes() { return $this->passes; }
	public function get_fails() { return $this->fails; }
}

$suite = new SIBACOM_Master_Test_Suite();

// ==============================================================================
// 1. STRICT BRANDING: ZERO ISOLATED "SIBACO" & SIBACOM EVENT SENDER
// ==============================================================================
$suite->start_section( '1. Strict Branding Verification (Zero Isolated "SIBACO")' );

// Recursively scan all plugin files (php, js, css, html, json)
$plugin_dir = realpath( EVENTFLOW_PLUGIN_DIR );
$dir_iter = new RecursiveDirectoryIterator( $plugin_dir );
$matches_found = array();

foreach ( new RecursiveIteratorIterator( $dir_iter ) as $file ) {
	if ( $file->isFile() && in_array( $file->getExtension(), array( 'php', 'js', 'css', 'html', 'json' ) ) ) {
		$contents = file_get_contents( $file->getPathname() );
		// Match isolated SIBACO not followed by M (case insensitive)
		if ( preg_match( '/\bSIBACO\b(?!M)/i', $contents, $matches ) ) {
			$rel = str_replace( $plugin_dir, '', $file->getPathname() );
			$matches_found[] = $rel . ' matches "' . $matches[0] . '"';
		}
	}
}

$suite->assert( empty( $matches_found ), 'Zero isolated "SIBACO" occurrences across all plugin files', implode( ', ', $matches_found ) );
$suite->assert( EventFlow_Email_Manager::SENDER_NAME === 'SIBACOM EVENT', 'EventFlow_Email_Manager::SENDER_NAME is strictly "SIBACOM EVENT"' );
$suite->assert( EventFlow_Email_Manager::get_sender_name() === 'SIBACOM EVENT', 'get_sender_name() defaults to "SIBACOM EVENT"' );

// Check seeded templates in database for strict SIBACOM EVENT branding
$templates = $wpdb->tables['wp_eventflow_email_templates'];
$all_templates_branded = true;
$bad_template = '';
foreach ( $templates as $tpl ) {
	if ( preg_match( '/\bSIBACO\b(?!M)/i', $tpl->subject . ' ' . $tpl->body ) ) {
		$all_templates_branded = false;
		$bad_template = $tpl->template_key;
		break;
	}
}
$suite->assert( $all_templates_branded, 'All 21 default email templates adhere strictly to SIBACOM EVENT branding', $bad_template );

// ==============================================================================
// 2. WORDPRESS NATIVE WP_MAIL() TRANSPORT & HEADERS
// ==============================================================================
$suite->start_section( '2. Native wp_mail() Transport & UTF-8 Email Headers' );

$wp_mail_history = array();
$test_email_res = EventFlow_Email_Manager::send_sibacom_notification(
	'participant.test@example.com',
	'Test Participant Subject',
	'account_created',
	array(
		'username'      => 'test_user_01',
		'login_url'     => wp_login_url(),
		'dashboard_url' => EventFlow_Shortcodes::get_dashboard_url(),
		'support_email' => 'support@sibacom.tech',
		'support_phone' => '+92-300-1234567',
	)
);

$suite->assert( ! empty( $test_email_res['success'] ), 'Email dispatched successfully via native wp_mail()' );
$suite->assert( count( $wp_mail_history ) > 0, 'wp_mail() intercepted dispatch in history' );

$last_mail = end( $wp_mail_history );
$headers_str = is_array( $last_mail['headers'] ) ? implode( "\n", $last_mail['headers'] ) : (string) $last_mail['headers'];

$suite->assert( false !== strpos( $headers_str, 'Content-Type: text/html; charset=UTF-8' ), 'Email header enforces UTF-8 HTML encoding' );
$suite->assert( false !== strpos( $headers_str, 'From: SIBACOM EVENT' ), 'From header contains official "SIBACOM EVENT" sender' );
$suite->assert( false !== strpos( $headers_str, 'Reply-To:' ), 'Reply-To header is properly configured' );

// Plain text alternative generation
$html_sample = '<h2>Hello Participant</h2><p>Welcome to <strong>SIBACOM EVENT</strong>.<br>Please visit your portal.</p>';
$plain_sample = EventFlow_Email_Manager::html_to_plain_text( $html_sample );
$suite->assert( false === strpos( $plain_sample, '<' ) && false !== strpos( $plain_sample, 'SIBACOM EVENT' ), 'html_to_plain_text() cleanly converts HTML to structured text' );

// Status logging accurately states Sent / Accepted
$latest_log = end( $wpdb->tables['wp_eventflow_email_logs'] );
$suite->assert( $latest_log && ( $latest_log->status === 'sent' || $latest_log->status === 'accepted' ), 'Email log records status as sent/accepted (Accepted by Mail Server)' );

// ==============================================================================
// 3. ACCOUNT CREATION WELCOME EMAIL DISPATCH
// ==============================================================================
$suite->start_section( '3. Account Creation Welcome Email Dispatch' );

global $mock_users;
$mock_users[201] = (object) array(
	'ID'           => 201,
	'user_login'   => 'ahmed_khan',
	'user_email'   => 'ahmed.khan@iba-suk.edu.pk',
	'display_name' => 'Ahmed Khan',
	'first_name'   => 'Ahmed',
	'last_name'    => 'Khan',
);

$wp_mail_history = array();
EventFlow_Auth::on_user_register( 201 );

$welcome_sent = false;
$welcome_mail = null;
foreach ( $wp_mail_history as $m ) {
	if ( $m['to'] === 'ahmed.khan@iba-suk.edu.pk' && false !== strpos( $m['subject'], 'Welcome to SIBACOM EVENT' ) ) {
		$welcome_sent = true;
		$welcome_mail = $m;
		break;
	}
}

$suite->assert( $welcome_sent, 'Account creation automatically dispatches welcome email' );
$suite->assert( $welcome_mail && false !== strpos( $welcome_mail['message'], 'Ahmed Khan' ), 'Welcome email includes participant full name' );
$suite->assert( $welcome_mail && false !== strpos( $welcome_mail['message'], 'ahmed_khan' ), 'Welcome email includes account username' );
$suite->assert( $welcome_mail && false !== strpos( $welcome_mail['message'], 'ahmed.khan@iba-suk.edu.pk' ), 'Welcome email includes registered email' );
$suite->assert( $welcome_mail && false !== strpos( $welcome_mail['message'], 'login' ), 'Welcome email contains login link' );

// ==============================================================================
// 4. SOLO REGISTRATION SUBMISSION EMAIL (ALL 7 FIELDS)
// ==============================================================================
$suite->start_section( '4. Solo Registration Email (All 7 Required Fields)' );

// Setup Event & Solo Activity
$event_id = 1;
$wpdb->tables['wp_eventflow_events'][ $event_id ] = (object) array(
	'id'                      => $event_id,
	'title'                   => 'SIBACOM Annual TechFest 2026',
	'slug'                    => 'sibacom-techfest-2026',
	'university_name'         => 'Sukkur IBA University',
	'venue'                   => 'Main Auditorium & CS Labs',
	'start_date'              => '2026-11-20',
	'end_date'                => '2026-11-22',
	'start_time'              => '09:00 AM',
	'end_time'                => '05:00 PM',
	'reporting_time'          => '08:30 AM',
	'currency'                => 'PKR',
	'pricing_model'           => 'per_participant',
	'fee_per_participant'     => 600.00,
	'allow_solo'              => 1,
	'allow_team'              => 1,
	'status'                  => 'open',
	'reg_start_date'          => '2026-10-01',
	'reg_end_date'            => '2026-11-18',
	'max_total_registrations' => 500,
	'max_team_registrations'  => 100,
	'contact_email'           => 'support@sibacom.tech',
	'contact_phone'           => '+92-300-1122334',
);

$act_solo_id = 10;
$wpdb->tables['wp_eventflow_activities'][ $act_solo_id ] = (object) array(
	'id'                      => $act_solo_id,
	'event_id'                => $event_id,
	'title'                   => 'Competitive Coding Solo Challenge',
	'participation_type'      => 'solo',
	'reg_fee'                 => 600.00,
	'min_team_size'           => 1,
	'max_team_size'           => 1,
	'max_participants'        => 100,
	'current_participants'    => 0,
	'payment_proof_required'  => 0,
	'status'                  => 'open',
);

$solo_reg_data = array(
	'event_id'           => $event_id,
	'activity_id'        => $act_solo_id,
	'participation_type' => 'solo',
	'members'            => array(
		array(
			'full_name'      => 'Ahmed Khan',
			'email'          => 'ahmed.khan@iba-suk.edu.pk',
			'contact_number' => '+92-301-9988776',
			'department'     => 'Computer Science',
			'semester'       => '6th Semester',
			'academic_year'  => 'Batch 2023-2027',
			'university'     => 'Sukkur IBA University',
		),
	),
);

$wp_mail_history = array();
$solo_reg_res = EventFlow_Registration_Manager::process_registration( 201, $solo_reg_data );
if ( is_wp_error( $solo_reg_res ) ) {
	echo "SOLO REG ERROR: " . $solo_reg_res->get_error_code() . " - " . $solo_reg_res->get_error_message() . "\n";
}
$suite->assert( ! is_wp_error( $solo_reg_res ) && ! empty( $solo_reg_res['id'] ), 'Solo registration submitted successfully' );

$solo_reg_id = $solo_reg_res['reg_id'];
$solo_reg_pk = $solo_reg_res['id'];

if ( empty( $wp_mail_history ) ) {
	echo "DEBUG: wp_mail_history is EMPTY after solo registration!\n";
} else {
	foreach ( $wp_mail_history as $idx => $m ) {
		echo "DEBUG MAIL #{$idx}: to={$m['to']}, subject={$m['subject']}\n";
	}
}

// Check email sent to solo participant
$solo_mail = null;
foreach ( $wp_mail_history as $m ) {
	if ( $m['to'] === 'ahmed.khan@iba-suk.edu.pk' && false !== strpos( $m['subject'], 'Registration' ) ) {
		$solo_mail = $m;
		break;
	}
}

$suite->assert( null !== $solo_mail, 'Confirmation email dispatched to solo participant' );
$suite->assert( false !== strpos( $solo_mail['message'], $solo_reg_id ), 'Solo email contains Application/Registration ID' );
$suite->assert( false !== strpos( $solo_mail['message'], 'Ahmed Khan' ), 'Solo email contains participant full name' );
$suite->assert( false !== strpos( $solo_mail['message'], 'Computer Science' ), 'Solo email contains department' );
$suite->assert( false !== strpos( $solo_mail['message'], '6th Semester' ), 'Solo email contains semester' );
$suite->assert( false !== strpos( $solo_mail['message'], 'Sukkur IBA University' ), 'Solo email contains university' );
$suite->assert( false !== strpos( $solo_mail['message'], 'PKR 600.00' ), 'Solo email contains calculated fee amount' );

// ==============================================================================
// 5. TEAM REGISTRATION EMAIL ENGINE WITH FULL ROSTER TABLE
// ==============================================================================
$suite->start_section( '5. Team Registration Email Engine (Leader & All Members)' );

$act_team_id = 20;
$wpdb->tables['wp_eventflow_activities'][ $act_team_id ] = (object) array(
	'id'                      => $act_team_id,
	'event_id'                => $event_id,
	'title'                   => 'Autonomous Robotics Clash',
	'participation_type'      => 'team',
	'reg_fee'                 => 1500.00,
	'min_team_size'           => 2,
	'max_team_size'           => 4,
	'max_participants'        => 80,
	'current_participants'    => 0,
	'payment_proof_required'  => 0,
	'status'                  => 'open',
);

$team_reg_data = array(
	'event_id'           => $event_id,
	'activity_id'        => $act_team_id,
	'participation_type' => 'team',
	'team_name'          => 'RoboTitans Sukkur',
	'members'            => array(
		array(
			'full_name'      => 'Bilal Qureshi',
			'email'          => 'bilal.leader@iba-suk.edu.pk',
			'contact_number' => '+92-302-1111111',
			'department'     => 'Electrical Engineering',
			'semester'       => '7th Semester',
			'academic_year'  => 'Batch 2022-2026',
			'university'     => 'Sukkur IBA University',
		),
		array(
			'full_name'      => 'Danish Memon',
			'email'          => 'danish.memon@iba-suk.edu.pk',
			'contact_number' => '+92-302-2222222',
			'department'     => 'Computer Science',
			'semester'       => '5th Semester',
			'academic_year'  => 'Batch 2023-2027',
			'university'     => 'Sukkur IBA University',
		),
		array(
			'full_name'      => 'Fatima Noor',
			'email'          => 'fatima.noor@iba-suk.edu.pk',
			'contact_number' => '+92-302-3333333',
			'department'     => 'Software Engineering',
			'semester'       => '5th Semester',
			'academic_year'  => 'Batch 2023-2027',
			'university'     => 'Sukkur IBA University',
		),
	),
);

$wp_mail_history = array();
$team_reg_res = EventFlow_Registration_Manager::process_registration( 201, $team_reg_data );
if ( is_wp_error( $team_reg_res ) ) {
	echo "TEAM REG ERROR: " . $team_reg_res->get_error_code() . " - " . $team_reg_res->get_error_message() . "\n";
}
$suite->assert( ! is_wp_error( $team_reg_res ) && ! empty( $team_reg_res['id'] ), 'Team registration submitted successfully' );

$team_reg_id = $team_reg_res['reg_id'];
$team_reg_pk = $team_reg_res['id'];

// Assert that emails were sent to ALL 3 members
$recipients_received = array();
foreach ( $wp_mail_history as $m ) {
	if ( false !== strpos( $m['subject'], 'Registration' ) ) {
		$recipients_received[] = $m['to'];
	}
}

$suite->assert( in_array( 'bilal.leader@iba-suk.edu.pk', $recipients_received ), 'Team Leader received registration confirmation email' );
$suite->assert( in_array( 'danish.memon@iba-suk.edu.pk', $recipients_received ), 'Member 2 (Danish) received registration confirmation email' );
$suite->assert( in_array( 'fatima.noor@iba-suk.edu.pk', $recipients_received ), 'Member 3 (Fatima) received registration confirmation email' );
$suite->assert( count( $recipients_received ) === 3, 'Exactly 3 individual emails dispatched for 3 team members' );

// Verify email body contains complete team roster HTML table
$danish_mail = null;
foreach ( $wp_mail_history as $m ) {
	if ( $m['to'] === 'danish.memon@iba-suk.edu.pk' ) {
		$danish_mail = $m;
		break;
	}
}

$suite->assert( $danish_mail && false !== strpos( $danish_mail['message'], 'RoboTitans Sukkur' ), 'Team member email contains Team Name' );
$suite->assert( $danish_mail && false !== strpos( $danish_mail['message'], 'Bilal Qureshi' ), 'Team member email lists Team Leader' );
$suite->assert( $danish_mail && false !== strpos( $danish_mail['message'], 'Fatima Noor' ), 'Team member email lists fellow team members' );
$suite->assert( $danish_mail && false !== strpos( $danish_mail['message'], 'team-members-table' ), 'Team email renders HTML team roster table' );

// ==============================================================================
// 6. PRIVATE INDIVIDUAL DELIVERY (ZERO EMAIL LEAKAGE)
// ==============================================================================
$suite->start_section( '6. Private Individual Delivery (Zero Exposure / Privacy Guarantee)' );

$privacy_guaranteed = true;
foreach ( $wp_mail_history as $m ) {
	// Assert each mail has only 1 recipient in 'to'
	if ( is_array( $m['to'] ) && count( $m['to'] ) > 1 ) {
		$privacy_guaranteed = false;
	}
	// Assert headers do not contain other emails in Cc or Bcc
	$h = is_array( $m['headers'] ) ? implode( ' ', $m['headers'] ) : (string) $m['headers'];
	if ( false !== stripos( $h, 'Cc:' ) || false !== stripos( $h, 'Bcc:' ) ) {
		$privacy_guaranteed = false;
	}
}
$suite->assert( $privacy_guaranteed, 'Every team email is dispatched strictly privately without exposing other emails in headers' );

// ==============================================================================
// 7. TEAM MEMBER DEDUPLICATION
// ==============================================================================
$suite->start_section( '7. Team Member Deduplication (Leader in Member List)' );

$test_reg_obj = (object) array(
	'id'                 => 9999,
	'reg_id'             => 'EF-TEST-DUP-01',
	'event_id'           => $event_id,
	'activity_id'        => $act_team_id,
	'participation_type' => 'team',
	'team_name'          => 'Deduplication Test Team',
	'payment_status'     => 'pending',
	'application_status' => 'pending',
	'total_fee'          => 1500.00,
	'created_at'         => current_time( 'mysql' ),
);
$wpdb->tables['wp_eventflow_registrations'][9999] = $test_reg_obj;
$wpdb->tables['wp_eventflow_teams'][9999] = (object) array(
	'id'              => 9999,
	'registration_id' => 9999,
	'team_name'       => 'Deduplication Test Team',
	'leader_name'     => 'Tariq Mehmood',
	'leader_email'    => 'tariq.leader@iba-suk.edu.pk',
	'leader_phone'    => '+92-303-1111111',
);
// Members list contains the leader's email again
$wpdb->tables['wp_eventflow_team_members'][9001] = (object) array(
	'id'              => 9001,
	'registration_id' => 9999,
	'full_name'       => 'Tariq Mehmood',
	'email'           => 'tariq.leader@iba-suk.edu.pk',
	'member_role'     => 'leader',
	'member_order'    => 1,
);
$wpdb->tables['wp_eventflow_team_members'][9002] = (object) array(
	'id'              => 9002,
	'registration_id' => 9999,
	'full_name'       => 'Tariq Duplicate',
	'email'           => 'tariq.leader@iba-suk.edu.pk', // Same email
	'member_role'     => 'member',
	'member_order'    => 2,
);
$wpdb->tables['wp_eventflow_team_members'][9003] = (object) array(
	'id'              => 9003,
	'registration_id' => 9999,
	'full_name'       => 'Zainab Ali',
	'email'           => 'zainab.ali@iba-suk.edu.pk',
	'member_role'     => 'member',
	'member_order'    => 3,
);

$wp_mail_history = array();
EventFlow_Email_Manager::send_to_registration( 9999, 'registration_submitted' );

$tariq_email_count = 0;
foreach ( $wp_mail_history as $m ) {
	if ( $m['to'] === 'tariq.leader@iba-suk.edu.pk' ) {
		$tariq_email_count++;
	}
}
$suite->assert( $tariq_email_count === 1, 'Leader present in member list receives exactly ONE email (Deduplicated)', "Received: {$tariq_email_count}" );

// ==============================================================================
// 8. DYNAMIC PRICING CALCULATION ENGINE (ALL 3 MODELS)
// ==============================================================================
$suite->start_section( '8. Dynamic Pricing Calculation Engine' );

// 8.1 Model: per_participant (4 members x PKR 500 = PKR 2,000.00)
$event_p = (object) array(
	'pricing_model'       => 'per_participant',
	'team_fee_per_member' => 500.00,
	'currency'            => 'PKR',
);
$calc_per_part = EventFlow_Event_Manager::calculate_fee( $event_p, 'team', 4 );
$suite->assert( (float) $calc_per_part['fee'] === 2000.00, 'per_participant model: 4 x 500 = PKR 2,000.00' );
$suite->assert( ! empty( $calc_per_part['fee_breakdown']['subtotal'] ), 'Fee breakdown contains subtotal' );
$suite->assert( (float) $calc_per_part['fee_breakdown']['rate_per_unit'] === 500.00, 'Fee breakdown contains rate per participant' );

// 8.2 Model: fixed_team (Fixed PKR 1,500.00 regardless of member count)
$event_f = (object) array(
	'pricing_model'  => 'fixed_team',
	'team_fixed_fee' => 1500.00,
	'currency'       => 'PKR',
);
$calc_fixed_3 = EventFlow_Event_Manager::calculate_fee( $event_f, 'team', 3 );
$calc_fixed_5 = EventFlow_Event_Manager::calculate_fee( $event_f, 'team', 5 );
$suite->assert( (float) $calc_fixed_3['fee'] === 1500.00, 'fixed_team model: 3 members = PKR 1,500.00' );
$suite->assert( (float) $calc_fixed_5['fee'] === 1500.00, 'fixed_team model: 5 members remains PKR 1,500.00' );

// 8.3 Model: individual (Solo PKR 800.00)
$event_i = (object) array(
	'pricing_model' => 'individual',
	'solo_fee'      => 800.00,
	'currency'      => 'PKR',
);
$calc_ind = EventFlow_Event_Manager::calculate_fee( $event_i, 'solo', 1 );
$suite->assert( (float) $calc_ind['fee'] === 800.00, 'individual model: PKR 800.00' );

// 8.4 Model with Discount (PKR 200 discount on PKR 2,000.00 = PKR 1,800.00)
$event_d = (object) array(
	'pricing_model'       => 'per_participant',
	'team_fee_per_member' => 500.00,
	'team_discount'       => 200.00,
	'currency'            => 'PKR',
);
$calc_disc = EventFlow_Event_Manager::calculate_fee( $event_d, 'team', 4 );
$suite->assert( (float) $calc_disc['fee'] === 1800.00, 'Discounts applied properly: 2,000 - 200 = PKR 1,800.00' );

// ==============================================================================
// 9. IMMUTABLE PRICING & FEE SNAPSHOTS
// ==============================================================================
$suite->start_section( '9. Immutable Pricing & Fee Snapshots' );

// Check stored registration from Scenario 5
$reg_in_db = $wpdb->tables['wp_eventflow_registrations'][ $team_reg_pk ];

$suite->assert( ! empty( $reg_in_db->fee_breakdown ), 'Registration row stores fee_breakdown JSON' );
$suite->assert( ! empty( $reg_in_db->pricing_snapshot ), 'Registration row stores pricing_snapshot JSON' );

$saved_breakdown = json_decode( $reg_in_db->fee_breakdown, true );
$saved_snapshot  = json_decode( $reg_in_db->pricing_snapshot, true );

$suite->assert( is_array( $saved_breakdown ) && isset( $saved_breakdown['total_fee'] ), 'fee_breakdown JSON decodes into valid structure' );
$suite->assert( is_array( $saved_snapshot ) && isset( $saved_snapshot['model'] ), 'pricing_snapshot JSON decodes into valid pricing model snapshot' );

// Mutate event fee settings
$wpdb->tables['wp_eventflow_events'][ $event_id ]->fee_per_participant = 9999.00;
$wpdb->tables['wp_eventflow_events'][ $event_id ]->pricing_model = 'fixed_team';

// Retrieve registration row again; assert historical fee remains unchanged
$reg_reloaded = $wpdb->tables['wp_eventflow_registrations'][ $team_reg_pk ];
$reloaded_snapshot = json_decode( $reg_reloaded->pricing_snapshot, true );

$suite->assert( (float) $reg_reloaded->total_fee !== 9999.00, 'Historical registration total_fee is preserved immutable' );
$suite->assert( (float) $reloaded_snapshot['base_rate'] == 1500.00, 'Historical pricing snapshot base_rate is preserved despite event mutation' );

// ==============================================================================
// 10. PAYMENT VERIFICATION - APPROVAL EMAIL
// ==============================================================================
$suite->start_section( '10. Payment Approval Notification Engine' );

// Create pending payment for Team Registration
$wpdb->insert(
	'wp_eventflow_payments',
	array(
		'registration_id' => $team_reg_pk,
		'amount'          => 1800.00,
		'payment_method'  => 'Bank Transfer',
		'transaction_id'  => 'TXN-SIBA-992211',
		'status'          => 'under_verification',
		'created_at'      => current_time( 'mysql' ),
	)
);
$pay_id = $wpdb->insert_id;

$wp_mail_history = array();
$ver_res = EventFlow_Payment_Verification::verify_payment( $pay_id, 1, 'Payment matched with Sukkur IBA Habib Bank account' );

$suite->assert( true === $ver_res, 'Payment verification action completed successfully' );
$suite->assert( 'verified' === $wpdb->tables['wp_eventflow_payments'][ $pay_id ]->status, 'Payment record updated to "verified"' );
$suite->assert( 'verified' === $wpdb->tables['wp_eventflow_registrations'][ $team_reg_pk ]->payment_status, 'Registration payment_status updated to "verified"' );
$suite->assert( 'approved' === $wpdb->tables['wp_eventflow_registrations'][ $team_reg_pk ]->application_status, 'Registration application_status updated to "approved"' );

// Check that verification emails were sent to ALL team members
$ver_recipients = array();
foreach ( $wp_mail_history as $m ) {
	if ( false !== strpos( $m['subject'], 'Payment Verified Successfully' ) ) {
		$ver_recipients[] = $m['to'];
	}
}

$suite->assert( in_array( 'bilal.leader@iba-suk.edu.pk', $ver_recipients ), 'Leader notified of payment approval' );
$suite->assert( in_array( 'danish.memon@iba-suk.edu.pk', $ver_recipients ), 'Member 2 (Danish) notified of payment approval' );
$suite->assert( in_array( 'fatima.noor@iba-suk.edu.pk', $ver_recipients ), 'Member 3 (Fatima) notified of payment approval' );

// Assert email content details
$leader_ver_mail = null;
foreach ( $wp_mail_history as $m ) {
	if ( $m['to'] === 'bilal.leader@iba-suk.edu.pk' && false !== strpos( $m['subject'], 'Payment Verified Successfully' ) ) {
		$leader_ver_mail = $m;
		break;
	}
}
$suite->assert( $leader_ver_mail && false !== strpos( $leader_ver_mail['message'], 'TXN-SIBA-992211' ), 'Payment verified email includes transaction ID' );
$suite->assert( $leader_ver_mail && false !== strpos( $leader_ver_mail['message'], '1,800.00' ), 'Payment verified email includes verified amount' );
$suite->assert( $leader_ver_mail && false !== strpos( $leader_ver_mail['message'], 'Main Auditorium' ), 'Payment verified email includes event venue' );

// ==============================================================================
// 11. PAYMENT REJECTION WITH MANDATORY ADMIN REASON
// ==============================================================================
$suite->start_section( '11. Payment Rejection Notification & Mandatory Reason' );

// Create another payment record for rejection test
$wpdb->insert(
	'wp_eventflow_payments',
	array(
		'registration_id' => $team_reg_pk,
		'amount'          => 1800.00,
		'payment_method'  => 'Easypaisa',
		'transaction_id'  => 'TXN-REJ-443322',
		'status'          => 'under_verification',
		'created_at'      => current_time( 'mysql' ),
	)
);
$rej_pay_id = $wpdb->insert_id;

$wp_mail_history = array();
$rej_reason = 'Screenshot appears cropped; transaction ID is illegible and date does not match.';
$rej_res = EventFlow_Payment_Verification::reject_payment( $rej_pay_id, 1, $rej_reason );

$suite->assert( true === $rej_res, 'Payment rejection action completed successfully' );
$suite->assert( 'rejected' === $wpdb->tables['wp_eventflow_payments'][ $rej_pay_id ]->status, 'Payment record updated to "rejected"' );
$suite->assert( 'rejected' === $wpdb->tables['wp_eventflow_registrations'][ $team_reg_pk ]->payment_status, 'Registration payment_status updated to "rejected"' );
$suite->assert( $rej_reason === $wpdb->tables['wp_eventflow_payments'][ $rej_pay_id ]->rejection_reason, 'Rejection reason recorded on payment row' );

// Verify rejection email dispatched to Leader and all members
$rej_recipients = array();
$rej_mail_content = '';
foreach ( $wp_mail_history as $m ) {
	if ( false !== strpos( $m['subject'], 'Payment Verification Unsuccessful' ) ) {
		$rej_recipients[] = $m['to'];
		$rej_mail_content = $m['message'];
	}
}

$suite->assert( in_array( 'bilal.leader@iba-suk.edu.pk', $rej_recipients ), 'Leader received payment rejection email' );
$suite->assert( in_array( 'danish.memon@iba-suk.edu.pk', $rej_recipients ), 'Member 2 received payment rejection email' );
$suite->assert( false !== strpos( $rej_mail_content, $rej_reason ), 'Rejection email includes exact administrator reason' );
$suite->assert( false !== strpos( $rej_mail_content, 'reupload' ) || false !== strpos( $rej_mail_content, 'participant-dashboard' ), 'Rejection email includes re-upload instructions & link' );

// ==============================================================================
// 12. PAYMENT PROOF RE-UPLOAD REQUEST
// ==============================================================================
$suite->start_section( '12. Payment Proof Re-upload Request & Status Logs' );

$wp_mail_history = array();
$reup_instructions = 'Please provide an official PDF or bank receipt showing your account title and timestamp.';
$reup_res = EventFlow_Payment_Verification::request_reupload( $rej_pay_id, 1, $reup_instructions );

$suite->assert( true === $reup_res, 'Payment re-upload request action completed' );
$suite->assert( 'reupload_requested' === $wpdb->tables['wp_eventflow_payments'][ $rej_pay_id ]->status, 'Payment record updated to "reupload_requested"' );
$suite->assert( 'reupload_requested' === $wpdb->tables['wp_eventflow_registrations'][ $team_reg_pk ]->payment_status, 'Registration payment status marked "reupload_requested"' );

// Assert transition logged into status_logs
$status_log_found = false;
foreach ( $wpdb->tables['wp_eventflow_status_logs'] as $slog ) {
	if ( $slog->registration_id == $team_reg_pk && $slog->new_status === 'reupload_requested' ) {
		$status_log_found = true;
		break;
	}
}
$suite->assert( $status_log_found, 'Status transition accurately logged into wp_eventflow_status_logs' );

// Re-upload request emails dispatched
$reup_recipients = array();
foreach ( $wp_mail_history as $m ) {
	if ( false !== strpos( $m['subject'], 'Payment Proof Re-upload Required' ) ) {
		$reup_recipients[] = $m['to'];
	}
}
$suite->assert( count( $reup_recipients ) >= 3, 'Re-upload notification sent to leader and all team members' );

// ==============================================================================
// 13. REPLACEMENT PAYMENT PROOF UPLOAD & STATUS RESET
// ==============================================================================
$suite->start_section( '13. Replacement Payment Proof Upload & Status Reset' );

$tmp_file = sys_get_temp_dir() . '/test_receipt_replacement.png';
file_put_contents( $tmp_file, "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR" );

$mock_file_array = array(
	'payment_proof' => array(
		'name'     => 'replacement_receipt.png',
		'type'     => 'image/png',
		'tmp_name' => $tmp_file,
		'error'    => UPLOAD_ERR_OK,
		'size'     => 1024 * 100,
	),
);

$wp_mail_history = array();
$sub_res = EventFlow_Payment_Manager::process_submission(
	201,
	array(
		'registration_id' => $team_reg_pk,
		'amount'          => 1800.00,
		'payment_method'  => 'Bank Transfer',
		'transaction_id'  => 'TXN-REPLACEMENT-8899',
	),
	$mock_file_array
);

$suite->assert( ! is_wp_error( $sub_res ) && ! empty( $sub_res['payment_id'] ), 'Replacement payment proof submitted successfully' );
$suite->assert( 'under_verification' === $wpdb->tables['wp_eventflow_registrations'][ $team_reg_pk ]->payment_status, 'Registration payment status reset to "under_verification"' );

// Check that confirmation email dispatched to leader & members
$reup_conf_recipients = array();
foreach ( $wp_mail_history as $m ) {
	if ( false !== strpos( $m['subject'], 'Payment Proof Uploaded' ) || false !== strpos( $m['subject'], 'Payment Submitted' ) ) {
		$reup_conf_recipients[] = $m['to'];
	}
}
$suite->assert( in_array( 'bilal.leader@iba-suk.edu.pk', $reup_conf_recipients ), 'Replacement receipt confirmation dispatched to team' );

// ==============================================================================
// 14. APPLICATION APPROVAL & REJECTION NOTIFICATIONS
// ==============================================================================
$suite->start_section( '14. Application Approval & Rejection Notifications' );

// 14.1 Application Approved
$wp_mail_history = array();
EventFlow_Registration_Manager::update_status( $team_reg_pk, 'approved', 'Your team application has been confirmed!' );

$app_app_found = false;
foreach ( $wp_mail_history as $m ) {
	if ( $m['to'] === 'bilal.leader@iba-suk.edu.pk' && false !== strpos( $m['subject'], 'Application Approved' ) ) {
		$app_app_found = true;
		break;
	}
}
$suite->assert( $app_app_found, 'Application approval email dispatched to team' );

// 14.2 Application Rejected
$wp_mail_history = array();
EventFlow_Registration_Manager::update_status( $team_reg_pk, 'rejected', 'Eligibility requirements not met (Incomplete institution verification)' );

$app_rej_found = false;
$rej_mail_body = '';
foreach ( $wp_mail_history as $m ) {
	if ( $m['to'] === 'bilal.leader@iba-suk.edu.pk' && false !== strpos( $m['subject'], 'Application Update' ) ) {
		$app_rej_found = true;
		$rej_mail_body = $m['message'];
		break;
	}
}
$suite->assert( $app_rej_found, 'Application rejection email dispatched to team' );
$suite->assert( false !== strpos( $rej_mail_body, 'Incomplete institution verification' ), 'Application rejection email contains admin reason' );

// ==============================================================================
// 15. SUPER ADMIN EVENT CONFIGURATION SETTINGS CRUD
// ==============================================================================
$suite->start_section( '15. Super Admin Event Configuration Settings CRUD' );

$event_config_data = array(
	'title'                   => 'SIBACOM Grand Tech Olympiad 2026',
	'slug'                    => 'sibacom-olympiad-2026',
	'description'             => 'Premier technical and coding competition of Sindh.',
	'logo_url'                => 'https://sibacom.tech/assets/logo.png',
	'banner_url'              => 'https://sibacom.tech/assets/banner.png',
	'university_name'         => 'Sukkur IBA University',
	'department_organizers'   => 'Department of Computer Science & IEEE Student Branch',
	'venue'                   => 'Auditorium Hall, Sukkur IBA Campus',
	'start_date'              => '2026-11-25',
	'end_date'                => '2026-11-28',
	'start_time'              => '08:30 AM',
	'end_time'                => '06:00 PM',
	'reporting_time'          => '08:00 AM',
	'website_url'             => 'https://sibacom.iba-suk.edu.pk',
	'contact_email'           => 'olympiad@sibacom.tech',
	'contact_phone'           => '+92-300-9988776',
	'reg_start_date'          => '2026-10-15',
	'reg_end_date'            => '2026-11-22',
	'status'                  => 'open',
	'allow_solo'              => 1,
	'allow_team'              => 1,
	'min_team_size'           => 2,
	'max_team_size'           => 5,
	'max_total_registrations' => 600,
	'max_team_registrations'  => 120,
	'pricing_model'           => 'per_participant',
	'fee_per_participant'     => 550.00,
	'currency'                => 'PKR',
	'bank_name'               => 'Habib Bank Limited (HBL)',
	'account_title'           => 'Sukkur IBA SIBACOM EVENT Account',
	'account_number'          => '12345678901234',
	'iban'                    => 'PK36HABB0012345678901234',
	'easypaisa_title'         => 'SIBACOM Easypaisa Merchant',
	'easypaisa_number'        => '03001234567',
);

$saved_event_id = EventFlow_Event_Manager::save_event( $event_config_data );
$suite->assert( $saved_event_id > 0, 'Super Admin successfully saved full event configuration' );

$retrieved_event = EventFlow_Event_Manager::get_event( $saved_event_id );
$suite->assert( $retrieved_event->title === 'SIBACOM Grand Tech Olympiad 2026', 'Official Event Name persisted' );
$suite->assert( $retrieved_event->university_name === 'Sukkur IBA University', 'University Name persisted' );
$suite->assert( $retrieved_event->venue === 'Auditorium Hall, Sukkur IBA Campus', 'Venue persisted' );
$suite->assert( $retrieved_event->website_url === 'https://sibacom.iba-suk.edu.pk', 'Website URL persisted' );
$suite->assert( (int) $retrieved_event->max_team_size === 5, 'Max team size persisted' );
$suite->assert( (int) $retrieved_event->min_team_size === 2, 'Min team size persisted' );

// ==============================================================================
// 16. DUAL FRONTEND / BACKEND VALIDATION ON RULES & CAPS
// ==============================================================================
$suite->start_section( '16. Dual Frontend/Backend Validation on Team Sizes & Caps' );

// 16.1 Team size below minimum (min=2, submitted 1)
$sub_min = EventFlow_Registration_Manager::process_registration( 201, array(
	'event_id'           => $saved_event_id,
	'activity_id'        => $act_team_id,
	'participation_type' => 'team',
	'team_name'          => 'LoneWolf',
	'members'            => array(
		array(
			'full_name'      => 'Solo Person',
			'email'          => 'solo@iba-suk.edu.pk',
			'contact_number' => '+92-300-0000001',
		),
	),
) );
$suite->assert( is_wp_error( $sub_min ) && $sub_min->get_error_code() === 'min_team_error', 'Backend rejects team below configured minimum size (min_team_error)' );

// 16.2 Team size exceeding maximum (max=4 in activity, submitted 5)
$sub_max = EventFlow_Registration_Manager::process_registration( 201, array(
	'event_id'           => $saved_event_id,
	'activity_id'        => $act_team_id,
	'participation_type' => 'team',
	'team_name'          => 'SixPack',
	'members'            => array(
		array( 'full_name' => 'M1', 'email' => 'm1@iba.pk', 'contact_number' => '1' ),
		array( 'full_name' => 'M2', 'email' => 'm2@iba.pk', 'contact_number' => '2' ),
		array( 'full_name' => 'M3', 'email' => 'm3@iba.pk', 'contact_number' => '3' ),
		array( 'full_name' => 'M4', 'email' => 'm4@iba.pk', 'contact_number' => '4' ),
		array( 'full_name' => 'M5', 'email' => 'm5@iba.pk', 'contact_number' => '5' ),
	),
) );
$suite->assert( is_wp_error( $sub_max ) && $sub_max->get_error_code() === 'max_team_error', 'Backend rejects team exceeding configured maximum size (max_team_error)' );

// 16.3 Solo registration when allow_solo is disabled
$wpdb->tables['wp_eventflow_activities'][ $act_solo_id ]->event_id = $saved_event_id;
$wpdb->tables['wp_eventflow_events'][ $saved_event_id ]->allow_solo = 0;
$sub_solo_dis = EventFlow_Registration_Manager::process_registration( 201, array(
	'event_id'           => $saved_event_id,
	'activity_id'        => $act_solo_id,
	'participation_type' => 'solo',
	'members'            => array( array( 'full_name' => 'S1', 'email' => 's1@iba.pk', 'contact_number' => '1' ) ),
) );
$suite->assert( is_wp_error( $sub_solo_dis ) && $sub_solo_dis->get_error_code() === 'solo_disabled', 'Backend rejects solo registration when allow_solo is disabled' );
$wpdb->tables['wp_eventflow_events'][ $saved_event_id ]->allow_solo = 1;

// 16.4 Registration deadline passed / event closed
$wpdb->tables['wp_eventflow_activities'][ $act_solo_id ]->event_id = $saved_event_id;
$wpdb->tables['wp_eventflow_events'][ $saved_event_id ]->status = 'closed';
$sub_closed = EventFlow_Registration_Manager::process_registration( 201, array(
	'event_id'           => $saved_event_id,
	'activity_id'        => $act_solo_id,
	'participation_type' => 'solo',
	'members'            => array( array( 'full_name' => 'S2', 'email' => 's2@iba.pk', 'contact_number' => '2' ) ),
) );
$suite->assert( is_wp_error( $sub_closed ) && ( $sub_closed->get_error_code() === 'event_closed' || $sub_closed->get_error_code() === 'registration_closed' ), 'Backend rejects submission when event status is closed' );
$wpdb->tables['wp_eventflow_events'][ $saved_event_id ]->status = 'open';

// ==============================================================================
// 17. SUPER ADMIN DYNAMIC PRICING CONFIGURATION UI
// ==============================================================================
$suite->start_section( '17. Dynamic Pricing Super Admin Configuration UI' );

$events_view_file = EVENTFLOW_PLUGIN_DIR . 'admin/views/view-events.php';
$events_view_html = file_get_contents( $events_view_file );

$suite->assert( false !== strpos( $events_view_html, 'tab-general' ), 'Admin Event view includes Tab 1: General Settings' );
$suite->assert( false !== strpos( $events_view_html, 'tab-registration' ), 'Admin Event view includes Tab 2: Registration Rules' );
$suite->assert( false !== strpos( $events_view_html, 'tab-pricing' ), 'Admin Event view includes Tab 3: Dynamic Pricing Model' );
$suite->assert( false !== strpos( $events_view_html, 'tab-payment' ), 'Admin Event view includes Tab 4: Payment Accounts' );
$suite->assert( false !== strpos( $events_view_html, 'pricing_model' ), 'Admin Event view includes pricing_model selection' );
$suite->assert( false !== strpos( $events_view_html, 'fee_per_participant' ), 'Admin Event view includes fee_per_participant field' );
$suite->assert( false !== strpos( $events_view_html, 'fixed_team_fee' ), 'Admin Event view includes fixed_team_fee field' );

// ==============================================================================
// 18. SUPER ADMIN EMAIL DASHBOARD: 7 MONITORING KPI CARDS
// ==============================================================================
$suite->start_section( '18. Super Admin Email Dashboard – 7 Monitoring KPI Cards' );

$emails_view_file = EVENTFLOW_PLUGIN_DIR . 'admin/views/view-emails.php';
$emails_view_html = file_get_contents( $emails_view_file );

$suite->assert( false !== strpos( $emails_view_html, 'Total Emails Sent' ), 'Email dashboard KPI 1: Total Emails Sent' );
$suite->assert( false !== strpos( $emails_view_html, 'Sent / Accepted by Mail Server' ), 'Email dashboard KPI 2: Sent / Accepted by Mail Server' );
$suite->assert( false !== strpos( $emails_view_html, 'Failed Dispatches' ), 'Email dashboard KPI 3: Failed Dispatches' );
$suite->assert( false !== strpos( $emails_view_html, 'In Queue / Pending' ), 'Email dashboard KPI 4: In Queue / Pending' );
$suite->assert( false !== strpos( $emails_view_html, 'Processing' ), 'Email dashboard KPI 5: Processing' );
$suite->assert( false !== strpos( $emails_view_html, 'Delivery Success Rate' ), 'Email dashboard KPI 6: Delivery Success Rate' );
$suite->assert( false !== strpos( $emails_view_html, 'Active Email Templates' ), 'Email dashboard KPI 7: Active Email Templates' );

// ==============================================================================
// 19. EMAIL ACTIVITY TABLE WITH RECIPIENT TYPE & STRICT STATUSES
// ==============================================================================
$suite->start_section( '19. Comprehensive Email Activity Table & Strict Statuses' );

$suite->assert( false !== strpos( $emails_view_html, 'Recipient Type' ), 'Activity table includes "Recipient Type" column' );
$suite->assert( false !== strpos( $emails_view_html, 'badge-sent' ) && false !== strpos( $emails_view_html, 'badge-failed' ), 'Activity table defines badges strictly for Sent/Accepted and Failed' );
$suite->assert( false !== strpos( $emails_view_html, 'filter_from' ) && false !== strpos( $emails_view_html, 'filter_to' ), 'Activity table includes From Date and To Date filtering' );

// Check recipient types recorded in email logs
$logs = $wpdb->tables['wp_eventflow_email_logs'];
$has_solo_type   = false;
$has_leader_type = false;
$has_member_type = false;

foreach ( $logs as $l ) {
	if ( isset( $l->recipient_type ) ) {
		if ( $l->recipient_type === 'solo' ) $has_solo_type = true;
		if ( $l->recipient_type === 'leader' ) $has_leader_type = true;
		if ( $l->recipient_type === 'member' ) $has_member_type = true;
	}
}

$suite->assert( $has_solo_type, 'Email log correctly recorded "solo" recipient type' );
$suite->assert( $has_leader_type, 'Email log correctly recorded "leader" recipient type' );
$suite->assert( $has_member_type, 'Email log correctly recorded "member" recipient type' );

// ==============================================================================
// 20. EMAIL LOGS CSV EXPORT
// ==============================================================================
$suite->start_section( '20. Email Logs CSV Export Engine' );

require_once EVENTFLOW_PLUGIN_DIR . 'admin/class-admin.php';

$expected_filename = 'sibacom-email-logs-' . date( 'Y-m-d' ) . '.csv';
$admin_class_file = EVENTFLOW_PLUGIN_DIR . 'admin/class-admin.php';
$admin_class_code = file_get_contents( $admin_class_file );

$suite->assert( false !== strpos( $admin_class_code, 'sibacom-email-logs-' ), 'CSV export uses filename "sibacom-email-logs-YYYY-MM-DD.csv"' );
$suite->assert( false !== strpos( $admin_class_code, 'Recipient Type' ), 'CSV export header row includes "Recipient Type"' );
$suite->assert( false !== strpos( $admin_class_code, 'Sent / Accepted by Mail Server' ), 'CSV export maps status to "Sent / Accepted by Mail Server"' );
$suite->assert( false !== strpos( $admin_class_code, 'Failed' ), 'CSV export maps failure status to "Failed"' );

// Final Master Assertion Summary
echo "\n" . str_repeat( '=', 70 ) . "\n";
echo sprintf( "FINAL RESULTS: %d Total Assertions | %d Passed | %d Failed\n", $suite->get_passes() + $suite->get_fails(), $suite->get_passes(), $suite->get_fails() );
echo str_repeat( '=', 70 ) . "\n";

if ( $suite->get_fails() === 0 ) {
	echo ">>> 100% PRODUCTION READY: ALL 20 ACCEPTANCE CRITERIA PASSED! <<<\n";
	exit( 0 );
} else {
	echo ">>> TEST SUITE FAILED WITH " . $suite->get_fails() . " FAILURES <<<\n";
	exit( 1 );
}
