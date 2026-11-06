<?php
/**
 * SIBACOM EVENT - Complete 14-Scenario Email Delivery Production Audit
 *
 * Strictly tests every workflow specified in Section 8 of the Production Audit:
 * 1. Individual registration.
 * 2. Team registration with multiple members.
 * 3. New account creation.
 * 4. Payment proof upload.
 * 5. Payment rejection.
 * 6. Reupload request.
 * 7. Second payment proof upload.
 * 8. Payment approval.
 * 9. Password reset & password changed.
 * 10. Admin notifications.
 * 11. Staff notifications.
 * 12. Failed email retry.
 * 13. Duplicate prevention.
 * 14. Queue recovery after interruption.
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
if ( ! defined( 'EVENTFLOW_PLUGIN_DIR' ) ) { define( 'EVENTFLOW_PLUGIN_DIR', file_exists( __DIR__ . '/../includes/class-db.php' ) ? dirname( __DIR__ ) . '/' : __DIR__ . '/../eventflow/' ); }
define( 'EVENTFLOW_PLUGIN_URL', 'https://portal.sibacom.tech/wp-content/plugins/eventflow/' );
define( 'EVENTFLOW_VERSION', '4.0.2' );
define( 'AUTH_KEY', 'sibacom-full-audit-key-2026' );
define( 'AUTH_SALT', 'sibacom-full-audit-salt-2026' );

if ( ! defined( 'OBJECT' ) ) define( 'OBJECT', 'OBJECT' );
if ( ! defined( 'ARRAY_A' ) ) define( 'ARRAY_A', 'ARRAY_A' );
if ( ! defined( 'ARRAY_N' ) ) define( 'ARRAY_N', 'ARRAY_N' );

// In-Memory Database Mock
class WPDB_Full_Audit_Mock {
	public $prefix = 'wp_';
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

	public function query( $sql ) {
		$norm_sql = preg_replace( '/\s+/', ' ', trim( (string) $sql ) );
		// Capacity increment
		if ( preg_match( '/UPDATE\s+.*wp_eventflow_activities\s+SET\s+current_participants\s*=\s*current_participants\s*\+\s*(\d+)\s+WHERE\s+id\s*=\s*(\d+)/i', $norm_sql, $m ) ) {
			$delta = (int) $m[1];
			$id    = (int) $m[2];
			if ( isset( $this->tables['wp_eventflow_activities'][ $id ] ) ) {
				$this->tables['wp_eventflow_activities'][ $id ]->current_participants += $delta;
				return 1;
			}
		}
		// Atomic queue locking
		if ( preg_match( '/UPDATE\s+.*wp_eventflow_email_queue\s+SET\s+status\s*=\s*\'processing\'\s+WHERE\s+id\s*=\s*(\d+)\s+AND\s+status\s+IN\s+\(\'pending\',\s*\'retrying\'\)/i', $norm_sql, $m ) ) {
			$qid = (int) $m[1];
			if ( isset( $this->tables['wp_eventflow_email_queue'][ $qid ] ) ) {
				$row = $this->tables['wp_eventflow_email_queue'][ $qid ];
				if ( in_array( $row->status, array( 'pending', 'retrying' ), true ) ) {
					$row->status = 'processing';
					return 1;
				}
				return 0;
			}
		}
		return 1;
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
				if ( preg_match( "/reg_id\s*=\s*'([^']+)'/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return isset( $r->reg_id ) && $r->reg_id === $m[1];
					} );
				}
				if ( preg_match( "/template_key\s*=\s*'([^']+)'/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return isset( $r->template_key ) && $r->template_key === $m[1];
					} );
				}
				// Queue status filters
				if ( false !== stripos( $query, "status = 'processing' AND updated_at" ) ) {
					$filtered = array_filter( $filtered, function( $r ) {
						return isset( $r->status ) && 'processing' === $r->status;
					} );
				} elseif ( false !== stripos( $query, "status = 'pending'" ) && false !== stripos( $query, "status = 'retrying'" ) ) {
					$filtered = array_filter( $filtered, function( $r ) {
						return isset( $r->status ) && in_array( $r->status, array( 'pending', 'retrying' ), true );
					} );
				} elseif ( preg_match( "/status\s*=\s*'([^']+)'/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return isset( $r->status ) && $r->status === $m[1];
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
				if ( false !== stripos( $query, "status IN ('pending', 'retrying')" ) ) {
					$filtered = array_filter( $filtered, function( $r ) {
						return in_array( $r->status, array( 'pending', 'retrying' ), true );
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

	public function get_col( $query, $col_offset = 0 ) {
		if ( false !== strpos( $query, 'DESC wp_eventflow_registrations' ) ) {
			return array( 'id', 'reg_id', 'event_id', 'activity_id', 'user_id', 'participation_type', 'team_name', 'total_fee', 'fee_breakdown', 'pricing_snapshot', 'payment_status', 'application_status', 'created_at', 'updated_at', 'admin_notes', 'rejection_reason' );
		}
		if ( false !== strpos( $query, 'DESC' ) && false !== strpos( $query, 'team_members' ) ) {
			return array( 'id', 'team_id', 'registration_id', 'full_name', 'father_name', 'semester', 'academic_year', 'department', 'university', 'email', 'contact_number', 'is_leader', 'created_at' );
		}
		return array();
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
}

global $wpdb;
$wpdb = new WPDB_Full_Audit_Mock();

// WordPress Mock Global Options & State
global $wp_options, $wp_actions, $wp_filters, $wp_mail_history, $wp_mail_simulate_fail, $mock_users;
$wp_options = array(
	'eventflow_email_from_name'          => 'SIBACOM EVENT',
	'eventflow_email_from_address'       => 'info@portal.sibacom.tech',
	'eventflow_email_reply_to'           => 'info@portal.sibacom.tech',
	'eventflow_admin_notification_email' => 'admin@portal.sibacom.tech',
	'eventflow_email_queue_enabled'      => 'no', // Default to immediate for sync tests
	'eventflow_email_batch_size'         => 25,
	'eventflow_email_max_retries'        => 3,
	'eventflow_mailer_mode'              => 'eventflow_smtp',
	'eventflow_smtp_enabled'             => 'yes',
	'eventflow_smtp_host'                => 'smtp.hostinger.com',
	'eventflow_smtp_port'                => 587,
	'eventflow_smtp_encryption'          => 'tls',
	'eventflow_smtp_auth'                => 'yes',
	'eventflow_smtp_username'            => 'info@portal.sibacom.tech',
	'admin_email'                        => 'admin@portal.sibacom.tech',
	'date_format'                        => 'F j, Y',
	'time_format'                        => 'g:i a',
);
$wp_actions = array();
$wp_filters = array();
$wp_mail_history = array();
$wp_mail_simulate_fail = false;
$mock_users = array();

function get_option( $opt, $default = false ) {
	global $wp_options;
	return isset( $wp_options[ $opt ] ) ? $wp_options[ $opt ] : $default;
}
function update_option( $opt, $val ) {
	global $wp_options;
	$wp_options[ $opt ] = $val;
	return true;
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
	return date( $format, false !== $timestamp ? $timestamp : time() );
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
function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, $decimals );
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
function esc_attr__( $text, $domain = 'default' ) {
	return $text;
}
function wp_json_encode( $data ) {
	return json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
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
	$d = sys_get_temp_dir() . '/eventflow_full_audit';
	if ( ! file_exists( $d ) ) @mkdir( $d, 0777, true );
	return array( 'basedir' => $d, 'baseurl' => 'https://portal.sibacom.tech/uploads' );
}
function wp_next_scheduled( $hook ) { return false; }
function wp_schedule_event( $ts, $rec, $hook ) { return true; }
function wp_unschedule_event( $ts, $hook ) { return true; }
function wp_create_nonce( $act = -1 ) { return 'mock_nonce_' . $act; }
function wp_verify_nonce( $n, $act = -1 ) { return 1; }
function add_query_arg( $args, $url = '' ) {
	$query = http_build_query( $args );
	return $url . ( false !== strpos( $url, '?' ) ? '&' : '?' ) . $query;
}
function wp_unslash( $val ) {
	return is_array( $val ) ? array_map( 'wp_unslash', $val ) : stripslashes( (string) $val );
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
function get_user_by( $field, $value ) {
	global $mock_users;
	foreach ( $mock_users as $u ) {
		if ( 'email' === $field && $u->user_email === $value ) return $u;
		if ( 'login' === $field && $u->user_login === $value ) return $u;
		if ( 'id' === $field && (int) $u->ID === (int) $value ) return $u;
	}
	return (object) array(
		'ID'            => 101,
		'user_login'    => 'testuser',
		'user_email'    => $value,
		'display_name'  => 'Audit Test User',
		'first_name'    => 'Audit',
	);
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
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $thing ) {
	return ( $thing instanceof WP_Error );
}

function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
	global $wp_mail_history, $wp_mail_simulate_fail;
	if ( ! empty( $wp_mail_simulate_fail ) ) {
		$err = new WP_Error( 'wp_mail_failed', 'Simulated SMTP connection timeout' );
		do_action( 'wp_mail_failed', $err );
		return false;
	}

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

function clear_mail_history() {
	global $wp_mail_history;
	$wp_mail_history = array();
}

function get_mail_to( $email ) {
	global $wp_mail_history;
	return array_values( array_filter( $wp_mail_history, function( $m ) use ( $email ) {
		$to = is_array( $m['to'] ) ? $m['to'] : array( $m['to'] );
		return in_array( $email, $to, true );
	} ) );
}

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

// Sync templates
EventFlow_DB::sync_email_templates();

// Setup Mock Event and Activities
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

$solo_act_id = 10;
$wpdb->tables['wp_eventflow_activities'][ $solo_act_id ] = (object) array(
	'id'                      => $solo_act_id,
	'event_id'                => $event_id,
	'title'                   => 'Speed Programming Solo Challenge',
	'participation_type'      => 'solo',
	'reg_fee'                 => 800.00,
	'min_team_size'           => 1,
	'max_team_size'           => 1,
	'max_participants'        => 100,
	'current_participants'    => 0,
	'payment_proof_required'  => 0,
	'status'                  => 'open',
);

$team_act_id = 20;
$wpdb->tables['wp_eventflow_activities'][ $team_act_id ] = (object) array(
	'id'                      => $team_act_id,
	'event_id'                => $event_id,
	'title'                   => 'Web App Hackathon',
	'participation_type'      => 'team',
	'reg_fee'                 => 1500.00,
	'min_team_size'           => 2,
	'max_team_size'           => 4,
	'max_participants'        => 80,
	'current_participants'    => 0,
	'payment_proof_required'  => 0,
	'status'                  => 'open',
);

// Test Framework Counters
$tests_passed = 0;
$tests_failed = 0;
$failure_messages = array();

function assert_test( $condition, $title, $detail = '' ) {
	global $tests_passed, $tests_failed, $failure_messages;
	if ( $condition ) {
		$tests_passed++;
		echo "  [PASS] {$title}\n";
	} else {
		$tests_failed++;
		$msg = "  [FAIL] {$title}" . ( $detail ? " ({$detail})" : '' );
		$failure_messages[] = $msg;
		echo "{$msg}\n";
	}
}

echo "======================================================================\n";
echo " SIBACOM EVENT – COMPLETE 14-WORKFLOW EMAIL DELIVERY PRODUCTION AUDIT\n";
echo "======================================================================\n\n";

// ==============================================================================
// SCENARIO 1: INDIVIDUAL REGISTRATION
// ==============================================================================
echo "--- Scenario 1: Individual Registration Workflow ---\n";
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

assert_test( ! is_wp_error( $solo_reg ), 'Scenario 1.1: Solo registration submitted successfully' );
$solo_mails = get_mail_to( 'ali.raza@iba-suk.edu.pk' );
assert_test( 1 === count( $solo_mails ), 'Scenario 1.2: Solo participant received registration confirmation email' );

$solo_mail = $solo_mails[0];
assert_test( false !== strpos( $solo_mail['subject'], $solo_reg['reg_id'] ), 'Scenario 1.3: Subject contains Registration ID (' . $solo_reg['reg_id'] . ')' );
assert_test( false !== strpos( $solo_mail['message'], 'Ali Raza' ), 'Scenario 1.4: Body contains participant name: Ali Raza' );
assert_test( false !== strpos( $solo_mail['message'], 'Speed Programming Solo' ), 'Scenario 1.5: Body contains activity title' );
assert_test( false !== strpos( $solo_mail['message'], 'Computer Science' ), 'Scenario 1.6: Body contains department' );
assert_test( false !== strpos( $solo_mail['message'], 'Sukkur IBA University' ), 'Scenario 1.7: Body contains university' );
assert_test( false !== strpos( $solo_mail['message'], '800.00' ), 'Scenario 1.8: Body contains registration fee amount: PKR 800.00' );

// ==============================================================================
// SCENARIO 2: TEAM REGISTRATION WITH MULTIPLE MEMBERS
// ==============================================================================
echo "\n--- Scenario 2: Team Registration with Multiple Members ---\n";
clear_mail_history();

$team_reg = EventFlow_Registration_Manager::process_registration( 201, array(
	'event_id'           => $event_id,
	'activity_id'        => $team_act_id,
	'participation_type' => 'team',
	'team_name'          => 'CodeCrafters',
	'members'            => array(
		// Leader
		array(
			'full_name'      => 'Sara Khan',
			'email'          => 'sara.lead@iba-suk.edu.pk',
			'contact_number' => '+92 311 0000001',
			'department'     => 'Software Engineering',
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
			'department'     => 'Data Science',
			'semester'       => '5',
			'academic_year'  => '2022-2026',
			'university'     => 'FAST-NUCES',
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

assert_test( ! is_wp_error( $team_reg ), 'Scenario 2.1: Team CodeCrafters registered without error' );

// Leader + 2 Members + Admin Alert = 4 outgoing emails
$sara_lead_mail  = get_mail_to( 'sara.lead@iba-suk.edu.pk' );
$bilal_mem_mail  = get_mail_to( 'bilal.mem1@iba-suk.edu.pk' );
$zainab_mem_mail = get_mail_to( 'zainab.mem2@iba-suk.edu.pk' );

assert_test( 1 === count( $sara_lead_mail ), 'Scenario 2.2: Team Leader received exactly 1 confirmation email (Deduplicated)' );
assert_test( 1 === count( $bilal_mem_mail ), 'Scenario 2.3: Member 1 (Bilal) received personalized email' );
assert_test( 1 === count( $zainab_mem_mail ), 'Scenario 2.4: Member 2 (Zainab) received personalized email' );

// Verify individual personalization details
$b_body = $bilal_mem_mail[0]['message'];
assert_test( false !== strpos( $b_body, 'Bilal Ahmed' ), 'Scenario 2.5: Member 1 email addressed to Bilal Ahmed' );
assert_test( false !== strpos( $b_body, 'CodeCrafters' ), 'Scenario 2.6: Member 1 email contains team name "CodeCrafters"' );
assert_test( false !== strpos( $b_body, 'Sara Khan' ), 'Scenario 2.7: Member 1 email names leader Sara Khan' );
assert_test( false !== strpos( $b_body, 'Data Science' ), 'Scenario 2.8: Member 1 email contains member 1 department "Data Science"' );
assert_test( false !== strpos( $b_body, 'FAST-NUCES' ), 'Scenario 2.9: Member 1 email contains member 1 university "FAST-NUCES"' );
assert_test( false !== strpos( $b_body, 'Team Roster' ), 'Scenario 2.10: Team email includes HTML Team Roster table' );

// ==============================================================================
// SCENARIO 3: NEW ACCOUNT CREATION WELCOME
// ==============================================================================
echo "\n--- Scenario 3: New Account Creation Welcome ---\n";
clear_mail_history();

$welcome_res = EventFlow_Email_Manager::send(
	'account_created',
	'usman.tariq@iba-suk.edu.pk',
	array(
		'participant_name'  => 'Usman Tariq',
		'participant_email' => 'usman.tariq@iba-suk.edu.pk',
		'username'          => 'usman_tariq',
		'event_name'        => 'SIBACOM EVENT 2026',
	)
);

assert_test( true === $welcome_res, 'Scenario 3.1: Account welcome email triggered successfully' );
$usman_mails = get_mail_to( 'usman.tariq@iba-suk.edu.pk' );
assert_test( 1 === count( $usman_mails ), 'Scenario 3.2: Welcome email delivered to registered candidate' );
assert_test( false !== strpos( $usman_mails[0]['message'], 'Usman Tariq' ), 'Scenario 3.3: Welcome email contains candidate full name' );
assert_test( false !== strpos( $usman_mails[0]['message'], 'usman_tariq' ), 'Scenario 3.4: Welcome email contains username' );
assert_test( false !== strpos( $usman_mails[0]['message'], 'SIBACOM EVENT' ), 'Scenario 3.5: Welcome email enforces SIBACOM EVENT branding' );

// ==============================================================================
// SCENARIO 4: PAYMENT PROOF UPLOAD (PARTICIPANT + ADMIN ALERT)
// ==============================================================================
echo "\n--- Scenario 4: Payment Proof Upload (Participant + Admin Alert) ---\n";
clear_mail_history();

$dummy_receipt = sys_get_temp_dir() . '/dummy_hbl.png';
file_put_contents( $dummy_receipt, 'PNG_DUMMY_IMAGE_DATA' );
$mock_files = array(
	'payment_proof' => array(
		'name'     => 'hbl_receipt.png',
		'tmp_name' => $dummy_receipt,
		'size'     => 1024,
		'error'    => 0,
	),
);

$sub_res = EventFlow_Payment_Manager::process_submission( 101, array(
	'registration_id' => $solo_reg['id'],
	'transaction_id'  => 'TXN-HBL-99220011',
	'payment_method'  => 'Bank Transfer',
	'amount'          => 800.00,
), $mock_files );

assert_test( ! is_wp_error( $sub_res ), 'Scenario 4.1: Payment proof submitted successfully' );

$solo_pay_mails = get_mail_to( 'ali.raza@iba-suk.edu.pk' );
assert_test( count( $solo_pay_mails ) >= 1, 'Scenario 4.2: Participant received payment proof submission confirmation' );
assert_test( false !== strpos( $solo_pay_mails[0]['message'], 'TXN-HBL-99220011' ), 'Scenario 4.3: Participant confirmation records transaction ID' );

$admin_pay_mails = get_mail_to( 'admin@portal.sibacom.tech' );
assert_test( count( $admin_pay_mails ) >= 1, 'Scenario 4.4: Admin received new payment alert' );
assert_test( false !== strpos( $admin_pay_mails[0]['subject'], $solo_reg['reg_id'] ), 'Scenario 4.5: Admin alert subject includes registration ID' );

// ==============================================================================
// SCENARIO 5: PAYMENT REJECTION WITH MANDATORY REASON
// ==============================================================================
echo "\n--- Scenario 5: Payment Rejection with Mandatory Reason ---\n";
clear_mail_history();

$reject_reason = 'Screenshot is cropped and transaction reference digits are obscured.';
$rej_res = EventFlow_Payment_Verification::reject_payment(
	$sub_res['payment_id'],
	$reject_reason,
	'Please provide full unedited mobile screenshot.',
	1
);

assert_test( true === $rej_res, 'Scenario 5.1: Payment rejected by staff with mandatory reason' );

$solo_rej_mails = get_mail_to( 'ali.raza@iba-suk.edu.pk' );
assert_test( 1 === count( $solo_rej_mails ), 'Scenario 5.2: Participant received payment rejection email' );
$rej_mail = $solo_rej_mails[0];
assert_test( false !== strpos( $rej_mail['message'], $reject_reason ), 'Scenario 5.3: Rejection email contains exact rejection reason' );
assert_test( false !== strpos( $rej_mail['message'], 'reupload' ) || false !== strpos( $rej_mail['message'], 'participant-dashboard' ), 'Scenario 5.4: Rejection email provides re-upload dashboard link' );

// ==============================================================================
// SCENARIO 6: REUPLOAD REQUEST
// ==============================================================================
echo "\n--- Scenario 6: Reupload Request ---\n";
clear_mail_history();

$req_instructions = 'Please upload a PDF e-receipt generated directly from your HBL Mobile App.';
$req_res = EventFlow_Payment_Verification::request_reupload(
	$sub_res['payment_id'],
	$req_instructions,
	1
);

assert_test( true === $req_res, 'Scenario 6.1: Staff requested payment re-upload successfully' );

$solo_req_mails = get_mail_to( 'ali.raza@iba-suk.edu.pk' );
assert_test( 1 === count( $solo_req_mails ), 'Scenario 6.2: Participant received re-upload request notification' );
assert_test( false !== strpos( $solo_req_mails[0]['message'], $req_instructions ), 'Scenario 6.3: Re-upload notification embeds exact instructions' );

// ==============================================================================
// SCENARIO 7: SECOND PAYMENT PROOF UPLOAD (REPLACEMENT PROOF)
// ==============================================================================
echo "\n--- Scenario 7: Second Payment Proof Upload (Replacement Proof) ---\n";
clear_mail_history();

$sub_v2 = EventFlow_Payment_Manager::process_submission( 101, array(
	'registration_id' => $solo_reg['id'],
	'transaction_id'  => 'TXN-HBL-99220011-CORRECTED',
	'payment_method'  => 'Bank Transfer',
	'amount'          => 800.00,
), $mock_files );

assert_test( ! is_wp_error( $sub_v2 ), 'Scenario 7.1: Replacement payment proof uploaded successfully' );
assert_test( 2 === (int) $sub_v2['version'], 'Scenario 7.2: Payment version incremented to Version 2' );

$solo_v2_mails = get_mail_to( 'ali.raza@iba-suk.edu.pk' );
assert_test( count( $solo_v2_mails ) >= 1, 'Scenario 7.3: Participant received replacement proof submission confirmation' );

$admin_v2_mails = get_mail_to( 'admin@portal.sibacom.tech' );
assert_test( count( $admin_v2_mails ) >= 1, 'Scenario 7.4: Admin received notification of replacement proof submission' );
assert_test( false !== strpos( $admin_v2_mails[0]['subject'], 'Re-upload' ), 'Scenario 7.5: Admin alert subject flags [Payment Re-upload]' );

// ==============================================================================
// SCENARIO 8: PAYMENT APPROVAL
// ==============================================================================
echo "\n--- Scenario 8: Payment Approval ---\n";
clear_mail_history();

$app_res = EventFlow_Payment_Verification::verify_payment(
	$sub_v2['payment_id'],
	'Payment verified and matched in HBL bank portal statement.',
	1
);

assert_test( true === $app_res, 'Scenario 8.1: Staff verified and approved payment' );

$solo_app_mails = get_mail_to( 'ali.raza@iba-suk.edu.pk' );
assert_test( 1 === count( $solo_app_mails ), 'Scenario 8.2: Participant received payment verified confirmation' );
$app_mail = $solo_app_mails[0];
assert_test( false !== strpos( $app_mail['subject'], 'Verified' ) || false !== strpos( $app_mail['subject'], 'Success' ), 'Scenario 8.3: Approval email subject confirms payment verified' );
assert_test( false !== strpos( $app_mail['message'], 'TXN-HBL-99220011-CORRECTED' ), 'Scenario 8.4: Approval email includes verified transaction reference' );

// ==============================================================================
// SCENARIO 9: PASSWORD RESET & PASSWORD CHANGED NOTIFICATIONS
// ==============================================================================
echo "\n--- Scenario 9: Authentication & Account Security Notifications ---\n";
clear_mail_history();

$pwd_reset_res = EventFlow_Email_Manager::send(
	'password_reset',
	'ali.raza@iba-suk.edu.pk',
	array(
		'participant_name' => 'Ali Raza',
		'reset_url'        => 'https://portal.sibacom.tech/reset-password?key=sec_token_998877',
	)
);
assert_test( true === $pwd_reset_res, 'Scenario 9.1: Password reset email dispatched successfully' );
$reset_mails = get_mail_to( 'ali.raza@iba-suk.edu.pk' );
assert_test( 1 === count( $reset_mails ), 'Scenario 9.2: User received password reset email' );
assert_test( false !== strpos( $reset_mails[0]['message'], 'sec_token_998877' ), 'Scenario 9.3: Password reset link securely rendered' );

clear_mail_history();
$pwd_changed_res = EventFlow_Email_Manager::send(
	'password_changed',
	'ali.raza@iba-suk.edu.pk',
	array(
		'participant_name' => 'Ali Raza',
	)
);
assert_test( true === $pwd_changed_res, 'Scenario 9.4: Password changed security alert dispatched successfully' );
$changed_mails = get_mail_to( 'ali.raza@iba-suk.edu.pk' );
assert_test( 1 === count( $changed_mails ), 'Scenario 9.5: User received password changed security alert' );
assert_test( false !== strpos( $changed_mails[0]['subject'], 'Security Alert' ) || false !== strpos( $changed_mails[0]['subject'], 'Password' ), 'Scenario 9.6: Subject contains Security Alert' );

// ==============================================================================
// SCENARIO 10: ADMIN NOTIFICATIONS
// ==============================================================================
echo "\n--- Scenario 10: Admin Notifications ---\n";
clear_mail_history();

$admin_reg_res = EventFlow_Email_Manager::send(
	'admin_registration_submitted',
	'admin@portal.sibacom.tech',
	array(
		'participant_name' => 'Ali Raza',
		'activity_name'    => 'Speed Programming Solo Challenge',
		'registration_id'  => $solo_reg['reg_id'],
	),
	array(
		'recipient_type' => 'admin',
	)
);

assert_test( true === $admin_reg_res, 'Scenario 10.1: Admin registration notification dispatched' );
$admin_notices = get_mail_to( 'admin@portal.sibacom.tech' );
assert_test( 1 === count( $admin_notices ), 'Scenario 10.2: Admin notification reached configured admin address' );

// ==============================================================================
// SCENARIO 11: STAFF OPERATIONS (APPLICATION APPROVAL & CANCELLATION)
// ==============================================================================
echo "\n--- Scenario 11: Staff Operations Notifications ---\n";
clear_mail_history();

$app_slot_res = EventFlow_Email_Manager::send_to_registration(
	$solo_reg['id'],
	'application_approved',
	array(
		'participant_name' => 'Ali Raza',
		'seat_number'      => 'LAB-3-STATION-22',
	)
);
assert_test( ! empty( $app_slot_res['success'] ), 'Scenario 11.1: Application approved notification dispatched' );

$cancel_res = EventFlow_Email_Manager::send_to_registration(
	$solo_reg['id'],
	'registration_cancelled',
	array(
		'rejection_reason' => 'Cancelled per applicant written request.',
	)
);
assert_test( ! empty( $cancel_res['success'] ), 'Scenario 11.2: Registration cancelled notification dispatched' );

// ==============================================================================
// SCENARIO 12: FAILED EMAIL RETRY MECHANISM
// ==============================================================================
echo "\n--- Scenario 12: Failed Email Retry Mechanism ---\n";
clear_mail_history();

// Enqueue email into queue table
$qid = EventFlow_Email_Queue::enqueue( array(
	'recipient_email' => 'retry.worker@iba-suk.edu.pk',
	'recipient_name'  => 'Retry Candidate',
	'template_key'    => 'welcome_email',
	'subject'         => 'Welcome to SIBACOM EVENT!',
	'body'            => '<p>Welcome Retry Candidate</p>',
	'registration_id' => 'EF-TEST-RETRY',
	'placeholders'    => array( 'participant_name' => 'Retry Candidate' ),
) );

assert_test( $qid > 0, 'Scenario 12.1: Email queued into database with ID: ' . $qid );

// Simulate transient network failure during queue processing
$wp_mail_simulate_fail = true;
$proc_fail = EventFlow_Email_Queue::process_queue( 10 );
$wp_mail_simulate_fail = false; // Reset

$table_q = EventFlow_DB::table( 'email_queue' );
$q_failed_item = $wpdb->get_row( "SELECT * FROM {$table_q} WHERE id = {$qid}" );

assert_test( 'retrying' === $q_failed_item->status || 'failed' === $q_failed_item->status, 'Scenario 12.2: Transient failure transitioned item to retrying/failed status' );
assert_test( (int) $q_failed_item->attempts >= 1, 'Scenario 12.3: Attempt counter incremented to: ' . $q_failed_item->attempts );

// Call retry_item to reset for re-attempt
$retry_ok = EventFlow_Email_Queue::retry_item( $qid );
assert_test( true === $retry_ok, 'Scenario 12.4: Manual retry resets item to pending' );

// Process queue after restoring network connectivity
$proc_retry = EventFlow_Email_Queue::process_queue( 10 );
$q_recovered_item = $wpdb->get_row( "SELECT * FROM {$table_q} WHERE id = {$qid}" );
assert_test( 'accepted' === $q_recovered_item->status || 'sent' === $q_recovered_item->status, 'Scenario 12.5: Queue retry processed and accepted by mail transport' );

// ==============================================================================
// SCENARIO 13: DUPLICATE PREVENTION & IDEMPOTENCY KEY
// ==============================================================================
echo "\n--- Scenario 13: Duplicate Prevention & Idempotency Key ---\n";
clear_mail_history();

$test_idemp_key = 'IDEMP-' . time() . '-AUDIT-KEY';

$disp_first = EventFlow_Email_Manager::send_sibacom_notification(
	'idemp.test@iba-suk.edu.pk',
	'',
	'welcome_email',
	array( 'participant_name' => 'Idemp User' ),
	array( 'idempotency_key' => $test_idemp_key, 'immediate' => true )
);
assert_test( ! empty( $disp_first['success'] ), 'Scenario 13.1: First notification dispatched successfully' );

$disp_second = EventFlow_Email_Manager::send_sibacom_notification(
	'idemp.test@iba-suk.edu.pk',
	'',
	'welcome_email',
	array( 'participant_name' => 'Idemp User' ),
	array( 'idempotency_key' => $test_idemp_key, 'immediate' => true )
);
assert_test( ! empty( $disp_second['idempotent_skip'] ) || ( ! empty( $disp_second['success'] ) && 1 === count( $wp_mail_history ) ), 'Scenario 13.2: Duplicate trigger detected and suppressed' );
assert_test( 1 === count( $wp_mail_history ), 'Scenario 13.3: Mail server received strictly ONE delivery (Zero duplicates)' );

// ==============================================================================
// SCENARIO 14: QUEUE RECOVERY AFTER INTERRUPTED WORKER
// ==============================================================================
echo "\n--- Scenario 14: Queue Recovery after Interrupted Worker ---\n";

$stuck_dispatch_id = EventFlow_Email_Manager::generate_dispatch_id();
$wpdb->insert( $table_q, array(
	'dispatch_id'     => $stuck_dispatch_id,
	'workflow'        => 'participant_registration',
	'recipient_email' => 'crash.recovery@iba-suk.edu.pk',
	'recipient_name'  => 'Crash User',
	'subject'         => 'Stuck Job Test',
	'template_key'    => 'welcome_email',
	'placeholders'    => json_encode( array( 'participant_name' => 'Crash User' ) ),
	'status'          => 'processing', // Stuck in processing (interrupted worker)
	'attempts'        => 1,
	'max_attempts'    => 3,
	'scheduled_at'    => date( 'Y-m-d H:i:s', time() - 600 ), // 10 minutes ago
	'last_attempt_at' => date( 'Y-m-d H:i:s', time() - 600 ),
	'created_at'      => date( 'Y-m-d H:i:s', time() - 600 ),
) );
$stuck_qid = $wpdb->insert_id;

// Running queue processor automatically triggers stuck job watchdog
$proc_watchdog = EventFlow_Email_Queue::process_queue( 10 );
$recovered_job = $wpdb->get_row( "SELECT * FROM {$table_q} WHERE id = {$stuck_qid}" );

assert_test( in_array( $recovered_job->status, array( 'accepted', 'sent', 'retrying' ), true ), 'Scenario 14.1: Interrupted worker job detected and recovered by queue watchdog (Status: ' . $recovered_job->status . ')' );

// ==============================================================================
// AUDIT SUMMARY
// ==============================================================================
echo "\n======================================================================\n";
echo sprintf( "FINAL RESULTS: %d Total Assertions | %d Passed | %d Failed\n", $tests_passed + $tests_failed, $tests_passed, $tests_failed );
echo "======================================================================\n";

if ( 0 === $tests_failed ) {
	echo ">>> 100% PRODUCTION READY: ALL 14 AUDIT SCENARIOS PASSED WITH ZERO ERRORS! <<<\n";
	exit( 0 );
} else {
	echo ">>> FAILURES DETECTED IN AUDIT! <<<\n";
	foreach ( $failure_messages as $msg ) {
		echo "  * {$msg}\n";
	}
	exit( 1 );
}
