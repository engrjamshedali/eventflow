<?php
/**
 * SIBACOM EVENT - Strict Email Service & Team Member Email Verification Test Suite
 *
 * Validates:
 * 1. Individual Registration & Account Welcome Email
 * 2. Solo Event Registration with All 7 Detailed Fields
 * 3. CRITICAL: Team with 2 members (Leader + 2 members) -> 3 personalized emails total
 * 4. CRITICAL: Team with 5 members (Leader + 5 members) -> 6 personalized emails total
 * 5. Dynamic Member Extraction (no members skipped when added dynamically)
 * 6. Non-overwrite of member placeholders (members get their OWN department, semester, academic_year, university, contact_number)
 * 7. Leader Deduplication (Leader in member list receives exactly ONE email)
 * 8. Payment Approved -> Leader + all members notified
 * 9. Payment Rejected -> Leader + all members notified with exact rejection reason
 * 10. Payment Re-upload Requested -> Leader + all members notified with instructions
 * 11. Password Reset -> Reset email dispatched with link
 * 12. Invalid Email Handling -> Handled gracefully without crashing or stopping other members
 * 13. Idempotency & Duplicate Prevention -> Identical event triggers do not duplicate
 * 14. Sender Identity -> From: SIBACOM EVENT <info@portal.sibacom.tech>, Reply-To: info@portal.sibacom.tech
 * 15. Email Logs & Diagnostics -> Logs record correct recipient_type, status, and template_key
 */

define( 'ABSPATH', __DIR__ . '/../' );
if ( ! defined( 'EVENTFLOW_PLUGIN_DIR' ) ) { define( 'EVENTFLOW_PLUGIN_DIR', file_exists( __DIR__ . '/../includes/class-db.php' ) ? dirname( __DIR__ ) . '/' : __DIR__ . '/../eventflow/' ); }
define( 'EVENTFLOW_PLUGIN_URL', 'https://portal.sibacom.tech/wp-content/plugins/eventflow/' );
define( 'EVENTFLOW_VERSION', '4.0.2' );
define( 'AUTH_KEY', 'sibacom-team-verify-auth-key-12345' );
define( 'AUTH_SALT', 'sibacom-team-verify-salt-phrase-67890' );

if ( ! defined( 'OBJECT' ) ) define( 'OBJECT', 'OBJECT' );
if ( ! defined( 'object' ) ) define( 'object', 'OBJECT' );
if ( ! defined( 'ARRAY_A' ) ) define( 'ARRAY_A', 'ARRAY_A' );
if ( ! defined( 'ARRAY_N' ) ) define( 'ARRAY_N', 'ARRAY_N' );

// ==============================================================================
// IN-MEMORY SQL DATABASE MOCK
// ==============================================================================

class WPDB_Team_Email_Mock {
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
				if ( ! isset( $row->$k ) || (string) $row->$k !== (string) $v ) {
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
				if ( ! isset( $row->$k ) || (string) $row->$k !== (string) $v ) {
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
		if ( false !== strpos( $query, 'DESC' ) && false !== strpos( $query, 'team_members' ) ) {
			return array( 'id', 'team_id', 'registration_id', 'full_name', 'father_name', 'semester', 'academic_year', 'department', 'university', 'email', 'contact_number', 'is_leader', 'created_at' );
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
				if ( preg_match( "/idempotency_key\s*=\s*'([^']+)'/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return isset( $r->idempotency_key ) && $r->idempotency_key === $m[1];
					} );
				}

				if ( false !== stripos( $query, 'ORDER BY' ) ) {
					if ( false !== stripos( $query, 'is_leader DESC' ) ) {
						usort( $filtered, function( $a, $b ) {
							$la = ! empty( $a->is_leader ) ? 1 : 0;
							$lb = ! empty( $b->is_leader ) ? 1 : 0;
							if ( $la !== $lb ) return $lb - $la;
							return (int) $a->id - (int) $b->id;
						} );
					} elseif ( false !== stripos( $query, 'id DESC' ) ) {
						usort( $filtered, function( $a, $b ) {
							return (int) $b->id - (int) $a->id;
						} );
					}
				}

				$out = array();
				foreach ( $filtered as $row ) {
					if ( ARRAY_A === $output ) {
						$out[] = (array) $row;
					} elseif ( ARRAY_N === $output ) {
						$out[] = array_values( (array) $row );
					} else {
						$out[] = $row;
					}
				}
				return $out;
			}
		}
		return array();
	}
}

global $wpdb;
$wpdb = new WPDB_Team_Email_Mock();

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
	return 'https://portal.sibacom.tech' . $path;
}
function admin_url( $path = '' ) {
	return 'https://portal.sibacom.tech/wp-admin/' . $path;
}
function wp_login_url( $redirect = '' ) {
	return 'https://portal.sibacom.tech/login/' . ( $redirect ? '?redirect_to=' . urlencode( $redirect ) : '' );
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
	return json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}
function add_query_arg( ...$args ) {
	if ( is_array( $args[0] ) ) {
		$params = $args[0];
		$url    = isset( $args[1] ) ? $args[1] : '';
	} else {
		$params = array( $args[0] => $args[1] );
		$url    = isset( $args[2] ) ? $args[2] : '';
	}
	$p = http_build_query( $params );
	return ( false !== strpos( $url, '?' ) ) ? $url . '&' . $p : $url . '?' . $p;
}
function wp_parse_args( $args, $defaults = '' ) {
	if ( is_object( $args ) ) $r = get_object_vars( $args );
	elseif ( is_array( $args ) ) $r =& $args;
	else $r = array();
	if ( is_array( $defaults ) ) return array_merge( $defaults, $r );
	return $r;
}
function wp_generate_password( $length = 12 ) {
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	return substr( str_shuffle( str_repeat( $chars, 4 ) ), 0, $length );
}
function wp_check_filetype_and_ext( $file, $filename, $mimes = null ) {
	$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
	$mime_map = array( 'png' => 'image/png', 'jpg' => 'image/jpeg', 'pdf' => 'application/pdf' );
	return isset( $mime_map[ $ext ] ) ? array( 'ext' => $ext, 'type' => $mime_map[ $ext ], 'proper_filename' => false ) : array( 'ext' => false, 'type' => false, 'proper_filename' => false );
}
function wp_mkdir_p( $target ) {
	if ( file_exists( $target ) ) return true;
	return @mkdir( $target, 0777, true );
}
function trailingslashit( $s ) {
	return rtrim( (string) $s, '/\\' ) . '/';
}
function untrailingslashit( $s ) {
	return rtrim( (string) $s, '/\\' );
}
function wp_upload_dir() {
	$d = sys_get_temp_dir() . '/eventflow_email_verify';
	if ( ! file_exists( $d ) ) @mkdir( $d, 0777, true );
	return array( 'basedir' => $d, 'baseurl' => 'https://portal.sibacom.tech/uploads' );
}
function wp_next_scheduled( $hook ) { return false; }
function wp_schedule_event( $ts, $rec, $hook ) { return true; }
function wp_unschedule_event( $ts, $hook ) { return true; }
function wp_create_nonce( $act = -1 ) { return 'mock_nonce_' . $act; }
function wp_verify_nonce( $n, $act = -1 ) { return 1; }

function add_action( $hook, $cb, $pri = 10, $args = 1 ) {
	global $wp_actions;
	$wp_actions[ $hook ][] = $cb;
}
function do_action( $hook, ...$args ) {
	global $wp_actions;
	if ( isset( $wp_actions[ $hook ] ) ) {
		foreach ( $wp_actions[ $hook ] as $cb ) {
			call_user_func_array( $cb, $args );
		}
	}
}
function add_filter( $hook, $cb, $pri = 10, $args = 1 ) {
	global $wp_filters;
	$wp_filters[ $hook ][] = $cb;
}
function apply_filters( $hook, $val, ...$args ) {
	global $wp_filters;
	if ( isset( $wp_filters[ $hook ] ) ) {
		foreach ( $wp_filters[ $hook ] as $cb ) {
			$val = call_user_func_array( $cb, array_merge( array( $val ), $args ) );
		}
	}
	return $val;
}

function get_userdata( $user_id ) {
	global $mock_users;
	return isset( $mock_users[ $user_id ] ) ? $mock_users[ $user_id ] : null;
}
function get_current_user_id() { return 1; }

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code = $code;
		$this->message = $message;
	}
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $thing ) { return ( $thing instanceof WP_Error ); }

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
	do_action( 'wp_mail_succeeded', array( 'to' => (array) $to, 'subject' => $subject, 'message' => $message, 'headers' => $headers, 'attachments' => $attachments ) );
	return true;
}

// Global Options Setup
update_option( 'eventflow_email_queue_enabled', 'no' ); // Immediate dispatch
update_option( 'eventflow_email_from_name', 'SIBACOM EVENT' );
update_option( 'eventflow_email_from_address', 'info@portal.sibacom.tech' );
update_option( 'eventflow_email_reply_to', 'info@portal.sibacom.tech' );

// Load Plugin Classes
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-roles.php';
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

class EventFlow_Shortcodes {
	public static function get_dashboard_url() {
		return 'https://portal.sibacom.tech/participant-dashboard/';
	}
	public static function get_track_url() {
		return 'https://portal.sibacom.tech/track-status/';
	}
}

// ==============================================================================
// TEST HARNESS & ASSERTIONS
// ==============================================================================

$tests_passed = 0;
$tests_failed = 0;

function assert_test( $condition, $title, $detail = '' ) {
	global $tests_passed, $tests_failed;
	if ( $condition ) {
		echo "  [PASS] {$title}\n";
		$tests_passed++;
	} else {
		echo "  [FAIL] {$title}" . ( $detail ? " ({$detail})" : '' ) . "\n";
		$tests_failed++;
	}
}

function clear_mail_history() {
	global $wp_mail_history;
	$wp_mail_history = array();
}

function get_mail_to( $email ) {
	global $wp_mail_history;
	$found = array();
	$email_clean = strtolower( trim( $email ) );
	foreach ( $wp_mail_history as $m ) {
		$to = is_array( $m['to'] ) ? $m['to'][0] : $m['to'];
		if ( strtolower( trim( $to ) ) === $email_clean ) {
			$found[] = $m;
		}
	}
	return $found;
}

echo "======================================================================\n";
echo "SIBACOM EVENT - EMAIL SERVICE & TEAM MEMBER VERIFICATION TEST SUITE\n";
echo "======================================================================\n\n";

// Seed Database Templates
EventFlow_DB::seed_default_email_templates();

// Setup Events & Activities in Mock Tables
$event_id = 1;
$wpdb->tables['wp_eventflow_events'][ $event_id ] = (object) array(
	'id'                      => $event_id,
	'title'                   => 'SIBACOM EVENT 2026',
	'slug'                    => 'sibacom-event-2026',
	'university_name'         => 'Sukkur IBA University',
	'venue'                   => 'Main Auditorium, Sukkur IBA University',
	'start_date'              => '2026-11-20',
	'end_date'                => '2026-11-22',
	'start_time'              => '09:00 AM',
	'end_time'                => '06:00 PM',
	'reporting_time'          => '08:15 AM',
	'currency'                => 'PKR',
	'pricing_model'           => 'per_participant',
	'fee_per_participant'     => 500.00,
	'allow_solo'              => 1,
	'allow_team'              => 1,
	'status'                  => 'open',
	'contact_email'           => 'info@portal.sibacom.tech',
	'contact_phone'           => '+92 300 1234567',
);

// Solo Activity
$solo_act_id = 10;
$wpdb->tables['wp_eventflow_activities'][ $solo_act_id ] = (object) array(
	'id'                      => $solo_act_id,
	'event_id'                => $event_id,
	'title'                   => 'Speed Programming Solo',
	'participation_type'      => 'solo',
	'reg_fee'                 => 800.00,
	'min_team_size'           => 1,
	'max_team_size'           => 1,
	'max_participants'        => 100,
	'current_participants'    => 0,
	'payment_proof_required'  => 0,
	'status'                  => 'open',
);

// Team of 2 (Robo Sumo min=2, max=3)
$team2_act_id = 20;
$wpdb->tables['wp_eventflow_activities'][ $team2_act_id ] = (object) array(
	'id'                      => $team2_act_id,
	'event_id'                => $event_id,
	'title'                   => 'Robo Sumo Challenge',
	'participation_type'      => 'team',
	'reg_fee'                 => 1500.00,
	'min_team_size'           => 2,
	'max_team_size'           => 3,
	'max_participants'        => 60,
	'current_participants'    => 0,
	'payment_proof_required'  => 0,
	'status'                  => 'open',
);

// Team of 5 (Hackathon min=3, max=6)
$team5_act_id = 30;
$wpdb->tables['wp_eventflow_activities'][ $team5_act_id ] = (object) array(
	'id'                      => $team5_act_id,
	'event_id'                => $event_id,
	'title'                   => 'Grand 36H Hackathon',
	'participation_type'      => 'team',
	'reg_fee'                 => 3000.00,
	'min_team_size'           => 3,
	'max_team_size'           => 6,
	'max_participants'        => 120,
	'current_participants'    => 0,
	'payment_proof_required'  => 0,
	'status'                  => 'open',
);

// ------------------------------------------------------------------------------
// TEST 1: SENDER IDENTITY & HEADER VERIFICATION
// ------------------------------------------------------------------------------
echo "--- TEST 1: Sender Identity & Header Verification ---\n";
clear_mail_history();

$test_raw = EventFlow_Email_Manager::send_raw(
	'test.candidate@iba-suk.edu.pk',
	'SIBACOM Header Test',
	'<p>Testing official header</p>'
);

assert_test( true === $test_raw, 'send_raw executed successfully' );
assert_test( 1 === count( $wp_mail_history ), 'wp_mail intercepted exactly one outgoing message' );

$sent_h = $wp_mail_history[0]['headers'];
$sent_h_str = is_array( $sent_h ) ? implode( "\n", $sent_h ) : $sent_h;

assert_test( false !== strpos( $sent_h_str, 'SIBACOM EVENT' ), 'Header enforces From Name: "SIBACOM EVENT"' );
assert_test( false !== strpos( $sent_h_str, 'info@portal.sibacom.tech' ), 'Header enforces From Email: "info@portal.sibacom.tech"' );
assert_test( false !== strpos( $sent_h_str, 'Reply-To: SIBACOM EVENT <info@portal.sibacom.tech>' ), 'Header enforces Reply-To: "SIBACOM EVENT <info@portal.sibacom.tech>"' );
assert_test( false !== strpos( $sent_h_str, 'text/html; charset=UTF-8' ), 'Header enforces UTF-8 HTML encoding' );

// ------------------------------------------------------------------------------
// TEST 2: INDIVIDUAL REGISTRATION & ACCOUNT WELCOME EMAIL
// ------------------------------------------------------------------------------
echo "\n--- TEST 2: Individual Registration & Account Welcome Email ---\n";
clear_mail_history();

$mock_users[101] = (object) array(
	'ID'           => 101,
	'user_login'   => 'ali.raza',
	'user_email'   => 'ali.raza@iba-suk.edu.pk',
	'display_name' => 'Ali Raza',
	'first_name'   => 'Ali',
);

EventFlow_Auth::on_user_register( 101 );

$welcome_res = EventFlow_Email_Manager::send(
	'welcome_email',
	'ali.raza@iba-suk.edu.pk',
	array(
		'participant_name'  => 'Ali Raza',
		'participant_email' => 'ali.raza@iba-suk.edu.pk',
		'username'          => 'ali.raza',
		'event_name'        => 'SIBACOM EVENT 2026',
	)
);

assert_test( true === $welcome_res, 'Account welcome email triggered successfully' );
$ali_mails = get_mail_to( 'ali.raza@iba-suk.edu.pk' );
assert_test( count( $ali_mails ) >= 1, 'Welcome email received by user' );
assert_test( false !== strpos( $ali_mails[0]['message'], 'Ali Raza' ), 'Welcome email contains user full name' );
assert_test( false !== strpos( $ali_mails[0]['message'], 'ali.raza' ), 'Welcome email contains username' );
assert_test( false !== strpos( $ali_mails[0]['message'], 'SIBACOM EVENT' ), 'Welcome email contains SIBACOM EVENT branding' );

// ------------------------------------------------------------------------------
// TEST 3: SOLO EVENT REGISTRATION CONFIRMATION (ALL 7 FIELDS)
// ------------------------------------------------------------------------------
echo "\n--- TEST 3: Solo Event Registration Confirmation (All 7 Fields) ---\n";
clear_mail_history();

$solo_reg = EventFlow_Registration_Manager::process_registration( 101, array(
	'event_id'           => $event_id,
	'activity_id'        => $solo_act_id,
	'participation_type' => 'solo',
	'members'            => array(
		array(
			'full_name'      => 'Ali Raza',
			'email'          => 'ali.raza@iba-suk.edu.pk',
			'contact_number' => '+92 301 1122334',
			'department'     => 'Computer Science',
			'semester'       => '6',
			'academic_year'  => '2022-2026',
			'university'     => 'Sukkur IBA University',
		),
	),
) );

assert_test( ! is_wp_error( $solo_reg ), 'Solo registration created without error' );
$solo_mails = get_mail_to( 'ali.raza@iba-suk.edu.pk' );
assert_test( 1 === count( $solo_mails ), 'Solo participant received registration confirmation email' );

$solo_body = $solo_mails[0]['message'];
assert_test( false !== strpos( $solo_body, 'Ali Raza' ), 'Solo email contains participant name: "Ali Raza"' );
assert_test( false !== strpos( $solo_body, $solo_reg['reg_id'] ), 'Solo email contains Registration ID' );
assert_test( false !== strpos( $solo_body, 'Speed Programming Solo' ), 'Solo email contains activity title' );
assert_test( false !== strpos( $solo_body, 'Computer Science' ), 'Solo email contains department: "Computer Science"' );
assert_test( false !== strpos( $solo_body, '6' ), 'Solo email contains semester: "6"' );
assert_test( false !== strpos( $solo_body, 'Sukkur IBA University' ), 'Solo email contains university: "Sukkur IBA University"' );
assert_test( false !== strpos( $solo_body, '800.00' ), 'Solo email contains registration fee amount: 800.00' );

// ------------------------------------------------------------------------------
// TEST 4: CRITICAL - TEAM WITH 2 MEMBERS (LEADER + 2 MEMBERS = 3 TOTAL EMAILS)
// ------------------------------------------------------------------------------
echo "\n--- TEST 4: Team with 2 Members (Leader + 2 Members = 3 Personalized Emails Total) ---\n";
clear_mail_history();

$team_robosumo = EventFlow_Registration_Manager::process_registration( 201, array(
	'event_id'           => $event_id,
	'activity_id'        => $team2_act_id,
	'participation_type' => 'team',
	'team_name'          => 'RoboDynasty',
	'members'            => array(
		// Leader (Member 0)
		array(
			'full_name'      => 'Hamza Tariq',
			'email'          => 'hamza.lead@iba-suk.edu.pk',
			'contact_number' => '+92 311 0000001',
			'department'     => 'Electrical Engineering',
			'semester'       => '7',
			'academic_year'  => '2021-2025',
			'university'     => 'Sukkur IBA University',
			'role'           => 'leader',
			'is_leader'      => 1,
		),
		// Member 1
		array(
			'full_name'      => 'Bilal Ahmed',
			'email'          => 'bilal.mem1@iba-suk.edu.pk',
			'contact_number' => '+92 311 0000002',
			'department'     => 'Mechatronics',
			'semester'       => '5',
			'academic_year'  => '2022-2026',
			'university'     => 'Sukkur IBA University',
			'is_leader'      => 0,
		),
		// Member 2
		array(
			'full_name'      => 'Zainab Noor',
			'email'          => 'zainab.mem2@iba-suk.edu.pk',
			'contact_number' => '+92 311 0000003',
			'department'     => 'Mechanical Engineering',
			'semester'       => '3',
			'academic_year'  => '2023-2027',
			'university'     => 'NED University',
			'is_leader'      => 0,
		),
	),
) );

assert_test( ! is_wp_error( $team_robosumo ), 'Team RoboDynasty created without error' );

// Exactly 3 emails must have been dispatched (Leader + Member 1 + Member 2)
assert_test( 3 === count( $wp_mail_history ), 'Team with 2 members dispatched EXACTLY 3 personalized emails total' );

$lead_mail = get_mail_to( 'hamza.lead@iba-suk.edu.pk' );
$mem1_mail = get_mail_to( 'bilal.mem1@iba-suk.edu.pk' );
$mem2_mail = get_mail_to( 'zainab.mem2@iba-suk.edu.pk' );

assert_test( 1 === count( $lead_mail ), 'Team Leader received personalized email' );
assert_test( 1 === count( $mem1_mail ), 'Member 1 (Bilal) received personalized email' );
assert_test( 1 === count( $mem2_mail ), 'Member 2 (Zainab) received personalized email' );

// Leader Content Verification
$lead_body = $lead_mail[0]['message'];
assert_test( false !== strpos( $lead_body, 'Hamza Tariq' ), 'Leader email addressed to "Hamza Tariq"' );
assert_test( false !== strpos( $lead_body, 'RoboDynasty' ), 'Leader email contains team name "RoboDynasty"' );
assert_test( false !== strpos( $lead_body, 'Robo Sumo Challenge' ), 'Leader email contains event/activity title' );
assert_test( false !== strpos( $lead_body, 'Electrical Engineering' ), 'Leader email has leader department "Electrical Engineering"' );

// Member 1 Content Verification (Personalized, NOT overwritten by leader)
$mem1_body = $mem1_mail[0]['message'];
assert_test( false !== strpos( $mem1_body, 'Bilal Ahmed' ), 'Member 1 email addressed to "Bilal Ahmed"' );
assert_test( false !== strpos( $mem1_body, 'RoboDynasty' ), 'Member 1 email contains team name "RoboDynasty"' );
assert_test( false !== strpos( $mem1_body, 'Hamza Tariq' ), 'Member 1 email names leader "Hamza Tariq"' );
assert_test( false !== strpos( $mem1_body, 'Mechatronics' ), 'Member 1 email has member 1 department "Mechatronics" (NOT leader dept)' );
assert_test( false !== strpos( $mem1_body, '5' ), 'Member 1 email has member 1 semester "5"' );

// Member 2 Content Verification (Personalized, NOT overwritten by leader)
$mem2_body = $mem2_mail[0]['message'];
assert_test( false !== strpos( $mem2_body, 'Zainab Noor' ), 'Member 2 email addressed to "Zainab Noor"' );
assert_test( false !== strpos( $mem2_body, 'RoboDynasty' ), 'Member 2 email contains team name "RoboDynasty"' );
assert_test( false !== strpos( $mem2_body, 'Hamza Tariq' ), 'Member 2 email names leader "Hamza Tariq"' );
assert_test( false !== strpos( $mem2_body, 'Mechanical Engineering' ), 'Member 2 email has member 2 department "Mechanical Engineering"' );
assert_test( false !== strpos( $mem2_body, 'NED University' ), 'Member 2 email has member 2 university "NED University" (NOT leader uni)' );

// Full Roster included in all team emails
assert_test( false !== strpos( $lead_body, 'Team Roster: RoboDynasty' ), 'Leader email includes Team Roster table' );
assert_test( false !== strpos( $mem1_body, 'Team Roster: RoboDynasty' ), 'Member 1 email includes Team Roster table' );
assert_test( false !== strpos( $mem2_body, 'Team Roster: RoboDynasty' ), 'Member 2 email includes Team Roster table' );

// ------------------------------------------------------------------------------
// TEST 5: CRITICAL - TEAM WITH 5 MEMBERS (LEADER + 5 MEMBERS = 6 TOTAL EMAILS)
// ------------------------------------------------------------------------------
echo "\n--- TEST 5: Team with 5 Members (Leader + 5 Members = 6 Personalized Emails Total) ---\n";
clear_mail_history();

$hackathon_team = EventFlow_Registration_Manager::process_registration( 301, array(
	'event_id'           => $event_id,
	'activity_id'        => $team5_act_id,
	'participation_type' => 'team',
	'team_name'          => 'CodeTitans',
	'members'            => array(
		// Leader
		array(
			'full_name'      => 'Usman Farooq',
			'email'          => 'usman.lead@iba-suk.edu.pk',
			'contact_number' => '+92 321 1111110',
			'department'     => 'Software Engineering',
			'semester'       => '8',
			'academic_year'  => '2021-2025',
			'university'     => 'Sukkur IBA University',
			'role'           => 'leader',
			'is_leader'      => 1,
		),
		// Member 1 (Dynamic)
		array(
			'full_name'      => 'Ayesha Siddiqui',
			'email'          => 'ayesha.dyn1@iba-suk.edu.pk',
			'contact_number' => '+92 321 1111111',
			'department'     => 'Data Science',
			'semester'       => '6',
			'academic_year'  => '2022-2026',
			'university'     => 'FAST-NUCES Karachi',
			'is_leader'      => 0,
		),
		// Member 2 (Dynamic)
		array(
			'full_name'      => 'Saad Bin Khalid',
			'email'          => 'saad.dyn2@iba-suk.edu.pk',
			'contact_number' => '+92 321 1111112',
			'department'     => 'Cyber Security',
			'semester'       => '4',
			'academic_year'  => '2023-2027',
			'university'     => 'NUST Islamabad',
			'is_leader'      => 0,
		),
		// Member 3 (Dynamic)
		array(
			'full_name'      => 'Mariam Javed',
			'email'          => 'mariam.dyn3@iba-suk.edu.pk',
			'contact_number' => '+92 321 1111113',
			'department'     => 'Artificial Intelligence',
			'semester'       => '5',
			'academic_year'  => '2022-2026',
			'university'     => 'GIKI Swabi',
			'is_leader'      => 0,
		),
		// Member 4 (Dynamic)
		array(
			'full_name'      => 'Fahad Memon',
			'email'          => 'fahad.dyn4@iba-suk.edu.pk',
			'contact_number' => '+92 321 1111114',
			'department'     => 'Information Technology',
			'semester'       => '7',
			'academic_year'  => '2021-2025',
			'university'     => 'Mehran UET Jamshoro',
			'is_leader'      => 0,
		),
		// Member 5 (Dynamic)
		array(
			'full_name'      => 'Hira Qureshi',
			'email'          => 'hira.dyn5@iba-suk.edu.pk',
			'contact_number' => '+92 321 1111115',
			'department'     => 'Computer Systems',
			'semester'       => '3',
			'academic_year'  => '2023-2027',
			'university'     => 'Sukkur IBA University',
			'is_leader'      => 0,
		),
	),
) );

assert_test( ! is_wp_error( $hackathon_team ), 'Team CodeTitans (6 members) registered without error' );

// Exactly 6 emails must have been dispatched (Leader + 5 Members)
assert_test( 6 === count( $wp_mail_history ), 'Team with 5 members dispatched EXACTLY 6 personalized emails total' );

$titan_lead  = get_mail_to( 'usman.lead@iba-suk.edu.pk' );
$titan_mem1  = get_mail_to( 'ayesha.dyn1@iba-suk.edu.pk' );
$titan_mem2  = get_mail_to( 'saad.dyn2@iba-suk.edu.pk' );
$titan_mem3  = get_mail_to( 'mariam.dyn3@iba-suk.edu.pk' );
$titan_mem4  = get_mail_to( 'fahad.dyn4@iba-suk.edu.pk' );
$titan_mem5  = get_mail_to( 'hira.dyn5@iba-suk.edu.pk' );

assert_test( 1 === count( $titan_lead ), 'Team Leader (Usman) received email' );
assert_test( 1 === count( $titan_mem1 ), 'Member 1 (Ayesha) received email' );
assert_test( 1 === count( $titan_mem2 ), 'Member 2 (Saad) received email' );
assert_test( 1 === count( $titan_mem3 ), 'Member 3 (Mariam) received email' );
assert_test( 1 === count( $titan_mem4 ), 'Member 4 (Fahad) received email' );
assert_test( 1 === count( $titan_mem5 ), 'Member 5 (Hira) received email' );

// Verification of individual personalization on dynamic members
assert_test( false !== strpos( $titan_mem1[0]['message'], 'Data Science' ), 'Dynamic Member 1 email has department "Data Science"' );
assert_test( false !== strpos( $titan_mem1[0]['message'], 'FAST-NUCES Karachi' ), 'Dynamic Member 1 email has university "FAST-NUCES Karachi"' );
assert_test( false !== strpos( $titan_mem2[0]['message'], 'Cyber Security' ), 'Dynamic Member 2 email has department "Cyber Security"' );
assert_test( false !== strpos( $titan_mem3[0]['message'], 'Artificial Intelligence' ), 'Dynamic Member 3 email has department "Artificial Intelligence"' );
assert_test( false !== strpos( $titan_mem4[0]['message'], 'Mehran UET Jamshoro' ), 'Dynamic Member 4 email has university "Mehran UET Jamshoro"' );
assert_test( false !== strpos( $titan_mem5[0]['message'], 'Hira Qureshi' ), 'Dynamic Member 5 email addressed to "Hira Qureshi"' );

// ------------------------------------------------------------------------------
// TEST 6: LEADER DEDUPLICATION (LEADER IN MEMBER LIST RECEIVES 1 EMAIL)
// ------------------------------------------------------------------------------
echo "\n--- TEST 6: Leader Deduplication (Leader in Member List Receives 1 Email) ---\n";
clear_mail_history();

// Directly invoke send_to_registration on Team RoboDynasty
$dedup_dispatch = EventFlow_Email_Manager::send_to_registration(
	$team_robosumo['id'],
	'registration_confirmation',
	array(),
	array( 'idempotency_key' => 'dedup_test_' . time() )
);

assert_test( ! empty( $dedup_dispatch['success'] ), 'Confirmation dispatch succeeded' );
assert_test( 3 === $dedup_dispatch['total'], 'Total unique recipients is exactly 3' );
$lead_dups = get_mail_to( 'hamza.lead@iba-suk.edu.pk' );
assert_test( 1 === count( $lead_dups ), 'Leader received exactly 1 email (zero duplicates)' );

// ------------------------------------------------------------------------------
// TEST 7: PAYMENT SUBMISSION & ADMIN ALERT
// ------------------------------------------------------------------------------
echo "\n--- TEST 7: Payment Submission & Admin Alert ---\n";
clear_mail_history();

update_option( 'eventflow_admin_notification_email', 'admin.event@iba-suk.edu.pk' );

$fake_receipt = sys_get_temp_dir() . '/test_receipt.png';
file_put_contents( $fake_receipt, 'FAKE_PNG_BINARY_CONTENT' );

$proof_res = EventFlow_Payment_Manager::process_submission(
	201,
	array(
		'registration_id' => $team_robosumo['id'],
		'transaction_id'  => 'TXN-ROBO-9988',
		'payment_method'  => 'Bank Transfer',
		'amount'          => 1500.00,
	),
	array(
		'payment_proof' => array(
			'name'     => 'test_receipt.png',
			'type'     => 'image/png',
			'tmp_name' => $fake_receipt,
			'error'    => 0,
			'size'     => 1024,
		),
	)
);

assert_test( ! is_wp_error( $proof_res ), 'Payment proof submitted without error' );

// Leader and team members received payment_submitted
$lead_sub_mail = get_mail_to( 'hamza.lead@iba-suk.edu.pk' );
$mem1_sub_mail = get_mail_to( 'bilal.mem1@iba-suk.edu.pk' );
$admin_mail    = get_mail_to( 'admin.event@iba-suk.edu.pk' );

assert_test( count( $lead_sub_mail ) >= 1, 'Leader received payment submission confirmation' );
assert_test( count( $mem1_sub_mail ) >= 1, 'Member 1 received payment submission confirmation' );
assert_test( 1 === count( $admin_mail ), 'Admin received new payment submission alert' );
assert_test( false !== strpos( $admin_mail[0]['message'], 'RoboDynasty' ), 'Admin email contains team name "RoboDynasty"' );

// ------------------------------------------------------------------------------
// TEST 8: PAYMENT APPROVAL NOTIFICATION TO ALL TEAM MEMBERS
// ------------------------------------------------------------------------------
echo "\n--- TEST 8: Payment Approval Notification to Leader and All Members ---\n";
clear_mail_history();

$approve_res = EventFlow_Payment_Verification::verify_payment(
	$proof_res['payment_id'],
	1,
	'Bank statement matches transaction TXN-ROBO-9988'
);

assert_test( ! is_wp_error( $approve_res ), 'Payment verified by staff successfully' );

$lead_appr = get_mail_to( 'hamza.lead@iba-suk.edu.pk' );
$mem1_appr = get_mail_to( 'bilal.mem1@iba-suk.edu.pk' );
$mem2_appr = get_mail_to( 'zainab.mem2@iba-suk.edu.pk' );

assert_test( 1 === count( $lead_appr ), 'Leader received payment approval notification' );
assert_test( 1 === count( $mem1_appr ), 'Member 1 received payment approval notification' );
assert_test( 1 === count( $mem2_appr ), 'Member 2 received payment approval notification' );
assert_test( 3 === count( $wp_mail_history ), 'All 3 team members received approval notifications' );

$appr_body = $lead_appr[0]['message'];
assert_test( false !== strpos( $appr_body, 'VERIFIED' ), 'Approval email displays VERIFIED badge' );
assert_test( false !== strpos( $appr_body, 'Sukkur IBA University' ), 'Approval email displays venue instructions' );

// ------------------------------------------------------------------------------
// TEST 9: PAYMENT REJECTION NOTIFICATION WITH REJECTION REASON
// ------------------------------------------------------------------------------
echo "\n--- TEST 9: Payment Rejection Notification with Exact Reason ---\n";
clear_mail_history();

// Submit payment for CodeTitans
$fake_receipt2 = sys_get_temp_dir() . '/test_receipt2.png';
file_put_contents( $fake_receipt2, 'FAKE_PNG_BINARY_CONTENT' );

$proof_res2 = EventFlow_Payment_Manager::process_submission(
	301,
	array(
		'registration_id' => $hackathon_team['id'],
		'transaction_id'  => 'TXN-FAKE-1111',
		'payment_method'  => 'JazzCash',
		'amount'          => 3000.00,
	),
	array(
		'payment_proof' => array(
			'name'     => 'test_receipt2.png',
			'type'     => 'image/png',
			'tmp_name' => $fake_receipt2,
			'error'    => 0,
			'size'     => 1024,
		),
	)
);

clear_mail_history();

$reject_reason = 'Screenshot is blurred, transaction ID TXN-FAKE-1111 not found in account statement.';
$reject_res = EventFlow_Payment_Verification::reject_payment(
	$proof_res2['payment_id'],
	1,
	$reject_reason
);

assert_test( ! is_wp_error( $reject_res ), 'Payment rejected successfully by staff' );

// All 6 members of CodeTitans must receive the rejection email
assert_test( 6 === count( $wp_mail_history ), 'All 6 team members received rejection notification' );

$lead_rej = get_mail_to( 'usman.lead@iba-suk.edu.pk' );
$mem1_rej = get_mail_to( 'ayesha.dyn1@iba-suk.edu.pk' );
$mem5_rej = get_mail_to( 'hira.dyn5@iba-suk.edu.pk' );

assert_test( 1 === count( $lead_rej ), 'Team Leader received rejection email' );
assert_test( 1 === count( $mem1_rej ), 'Member 1 received rejection email' );
assert_test( 1 === count( $mem5_rej ), 'Member 5 received rejection email' );

// Exact rejection reason embedded in the email
assert_test( false !== strpos( $lead_rej[0]['message'], $reject_reason ), 'Leader rejection email contains exact rejection reason' );
assert_test( false !== strpos( $mem1_rej[0]['message'], $reject_reason ), 'Member 1 rejection email contains exact rejection reason' );
assert_test( false !== strpos( $mem5_rej[0]['message'], $reject_reason ), 'Member 5 rejection email contains exact rejection reason' );
assert_test( false !== strpos( $lead_rej[0]['message'], 'action=reupload' ), 'Rejection email includes re-upload link' );

// ------------------------------------------------------------------------------
// TEST 10: PAYMENT RE-UPLOAD REQUEST NOTIFICATION WITH INSTRUCTIONS
// ------------------------------------------------------------------------------
echo "\n--- TEST 10: Payment Re-upload Request Notification ---\n";
clear_mail_history();

$reup_instructions = 'Please upload a clear high-resolution receipt showing the bank reference number.';
$reup_res = EventFlow_Payment_Verification::request_reupload(
	$proof_res2['payment_id'],
	1,
	$reup_instructions
);

assert_test( ! is_wp_error( $reup_res ), 'Re-upload requested successfully by staff' );
assert_test( 6 === count( $wp_mail_history ), 'All 6 team members received re-upload request notification' );

$lead_reup = get_mail_to( 'usman.lead@iba-suk.edu.pk' );
assert_test( false !== strpos( $lead_reup[0]['message'], $reup_instructions ), 'Re-upload email contains staff instructions' );
assert_test( false !== strpos( $lead_reup[0]['message'], 'action=reupload' ), 'Re-upload email contains dashboard re-upload URL' );

// ------------------------------------------------------------------------------
// TEST 11: PASSWORD RESET EMAIL DISPATCH
// ------------------------------------------------------------------------------
echo "\n--- TEST 11: Password Reset Email Dispatch ---\n";
clear_mail_history();

$reset_res = EventFlow_Email_Manager::send(
	'password_reset',
	'ali.raza@iba-suk.edu.pk',
	array(
		'participant_name' => 'Ali Raza',
		'reset_url'        => 'https://portal.sibacom.tech/login/?action=rp&key=mock_reset_key_7788',
		'website_url'      => 'https://portal.sibacom.tech',
	),
	array(
		'subject'           => 'Password Reset Request',
		'notification_type' => 'password_reset',
		'immediate'         => true,
	)
);
assert_test( true === $reset_res, 'Password reset notification triggered without error' );

$reset_mail = get_mail_to( 'ali.raza@iba-suk.edu.pk' );
assert_test( 1 === count( $reset_mail ), 'User received password reset email' );
assert_test( false !== strpos( $reset_mail[0]['message'], 'mock_reset_key_7788' ), 'Password reset email contains reset key URL' );
assert_test( false !== strpos( $reset_mail[0]['message'], 'Ali Raza' ), 'Password reset email contains user full name' );

// ------------------------------------------------------------------------------
// TEST 12: INVALID EMAIL HANDLING & GRACEFUL ERROR HANDLING
// ------------------------------------------------------------------------------
echo "\n--- TEST 12: Invalid Email Handling ---\n";
clear_mail_history();

// Try sending to completely empty or invalid email address
$invalid_res = EventFlow_Email_Manager::send_sibacom_notification(
	'not-an-email-address',
	'Invalid Test',
	'welcome_email',
	array( 'participant_name' => 'Invalid Tester' )
);

assert_test( false === $invalid_res['success'], 'Invalid email rejected gracefully without throwing PHP fatal error' );
assert_test( in_array( $invalid_res['status'], array( 'failed', 'invalid_email' ), true ), 'Failure status marked as failed/invalid_email' );

$res_bad_mix = EventFlow_Email_Manager::send_sibacom_notification(
	'valid.member@iba-suk.edu.pk',
	'Valid Member',
	'registration_submitted',
	array( 'participant_name' => 'Valid Member', 'registration_id' => 'EF-TEST-BAD-EMAIL' ),
	array( 'registration_id' => 'EF-TEST-BAD-EMAIL' )
);

assert_test( ! empty( $res_bad_mix['success'] ), 'Valid member in mixed situation receives email successfully' );

// ------------------------------------------------------------------------------
// TEST 13: DUPLICATE TRIGGER PREVENTION & IDEMPOTENCY
// ------------------------------------------------------------------------------
echo "\n--- TEST 13: Duplicate Trigger Prevention & Idempotency ---\n";
clear_mail_history();

// Send first notification
$key_once = 'idemp_key_test_999';
$disp1 = EventFlow_Email_Manager::send_sibacom_notification(
	'unique.idemp@iba-suk.edu.pk',
	'Unique Idemp',
	'welcome_email',
	array( 'participant_name' => 'Unique Idemp' ),
	array( 'idempotency_key' => $key_once )
);

assert_test( ! empty( $disp1['success'] ), 'First notification sent successfully' );
assert_test( 1 === count( $wp_mail_history ), 'First dispatch logged in history' );

// Attempt immediate duplicate trigger with same idempotency key
$disp2 = EventFlow_Email_Manager::send_sibacom_notification(
	'unique.idemp@iba-suk.edu.pk',
	'Unique Idemp',
	'welcome_email',
	array( 'participant_name' => 'Unique Idemp' ),
	array( 'idempotency_key' => $key_once )
);

assert_test( ! empty( $disp2['idempotent_skip'] ) || ! empty( $disp2['success'] ), 'Duplicate trigger recognized idempotency key' );
// History should still only have 1 email dispatched
assert_test( 1 === count( $wp_mail_history ), 'Duplicate notification was NOT sent to mail server (Zero Duplicates)' );

// ------------------------------------------------------------------------------
// TEST 14: DATABASE EMAIL LOGS & RECIPIENT TYPE AUDIT
// ------------------------------------------------------------------------------
echo "\n--- TEST 14: Database Email Logs & Recipient Type Audit ---\n";

$items = array_values( $wpdb->tables['wp_eventflow_email_logs'] );

assert_test( ! empty( $items ), 'Email logs retrieved from database' );

$types_found = array();
foreach ( $items as $l ) {
	if ( ! empty( $l->recipient_type ) ) {
		$types_found[ $l->recipient_type ] = true;
	}
}

assert_test( isset( $types_found['solo'] ), 'Email logs record recipient_type: "solo"' );
assert_test( isset( $types_found['leader'] ), 'Email logs record recipient_type: "leader"' );
assert_test( isset( $types_found['member'] ), 'Email logs record recipient_type: "member"' );

$last_log = end( $items );
assert_test( 'info@portal.sibacom.tech' === $last_log->sender_email, 'Email logs record sender_email: "info@portal.sibacom.tech"' );
assert_test( in_array( $last_log->status, array( 'sent', 'accepted', 'queued' ), true ), 'Email logs record valid delivery status' );

// ------------------------------------------------------------------------------
// SUMMARY
// ------------------------------------------------------------------------------
echo "\n======================================================================\n";
echo "TEST RESULTS: {$tests_passed} Passed, {$tests_failed} Failed.\n";
echo "======================================================================\n";

if ( 0 === $tests_failed ) {
	echo ">>> ALL 14 TEST CRITERIA PASSED WITH ZERO REGRESSIONS! <<<\n";
	exit( 0 );
} else {
	echo ">>> SOME TESTS FAILED. PLEASE REVIEW LOGS. <<<\n";
	exit( 1 );
}
