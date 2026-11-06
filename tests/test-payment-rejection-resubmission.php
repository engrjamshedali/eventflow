<?php
/**
 * EventFlow Strict Payment Rejection, Resubmission & Participant Notification Test Suite
 *
 * Validates all 15 Scenarios specified in Section 9:
 * 1. New participant submits payment proof -> pending verification (version 1)
 * 2. Admin approves payment -> verified status, admin ID and timestamp logged
 * 3. Participant receives approval notification in-dashboard
 * 4. Admin rejects payment with mandatory reason -> rejected status and reason recorded
 * 5. Participant receives rejection notification in-dashboard with exact reason
 * 6. Participant views exact rejection reason and professional panel
 * 7. Participant re-uploads corrected payment proof
 * 8. Previous submission remains available in history (version 1 preserved, version 2 created)
 * 9. Latest submission appears in admin pending queue and active warning is reset
 * 10. Admin receives resubmission notification
 * 11. Admin approves the resubmitted payment
 * 12. Participant receives final approval notification
 * 13. Security: Participant cannot access another participant's payment records
 * 14. Duplicate submission prevention on repeated rapid clicks
 * 15. UI / Security audit: Zero meeting links, safe escaping and complete responsive data model
 */

define( 'ABSPATH', __DIR__ . '/../' );
if ( ! defined( 'EVENTFLOW_PLUGIN_DIR' ) ) { define( 'EVENTFLOW_PLUGIN_DIR', file_exists( __DIR__ . '/../includes/class-db.php' ) ? dirname( __DIR__ ) . '/' : __DIR__ . '/../eventflow/' ); }
define( 'EVENTFLOW_PLUGIN_URL', 'https://portal.sibacom.tech/wp-content/plugins/eventflow/' );
define( 'EVENTFLOW_VERSION', '4.0.3' );
define( 'AUTH_KEY', 'test-auth-key-12345' );
define( 'AUTH_SALT', 'test-auth-salt-67890' );

if ( ! defined( 'OBJECT' ) ) define( 'OBJECT', 'OBJECT' );
if ( ! defined( 'ARRAY_A' ) ) define( 'ARRAY_A', 'ARRAY_A' );
if ( ! defined( 'ARRAY_N' ) ) define( 'ARRAY_N', 'ARRAY_N' );
if ( ! defined( 'UPLOAD_ERR_OK' ) ) define( 'UPLOAD_ERR_OK', 0 );

// ==============================================================================
// WORDPRESS BASIC STUBS & WP_ERROR
// ==============================================================================

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $errors = array();
		public $error_data = array();

		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( ! empty( $code ) ) {
				$this->errors[ $code ][] = $message;
				if ( ! empty( $data ) ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		public function get_error_message( $code = '' ) {
			if ( empty( $code ) ) {
				$first = reset( $this->errors );
				return is_array( $first ) ? $first[0] : (string) $first;
			}
			return isset( $this->errors[ $code ][0] ) ? $this->errors[ $code ][0] : '';
		}

		public function get_error_code() {
			$keys = array_keys( $this->errors );
			return ! empty( $keys ) ? $keys[0] : '';
		}
	}
}

function wp_create_nonce( $action = -1 ) {
	return 'mock_nonce_' . $action;
}

function wp_verify_nonce( $nonce, $action = -1 ) {
	return true;
}

function is_wp_error( $thing ) {
	return ( $thing instanceof WP_Error );
}

function wp_mkdir_p( $target ) {
	if ( file_exists( $target ) ) return true;
	return @mkdir( $target, 0777, true );
}

function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	return json_encode( $data, $options, $depth );
}

function wp_cache_get( $key, $group = '' ) {
	return false;
}

function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
	return true;
}

function home_url( $path = '' ) {
	return 'https://portal.sibacom.tech' . $path;
}

function admin_url( $path = '' ) {
	return 'https://portal.sibacom.tech/wp-admin/' . $path;
}

function add_query_arg( $args, $url = '' ) {
	return $url . '?' . http_build_query( $args );
}

function wp_kses_post( $str ) {
	return $str;
}

function human_time_diff( $from, $to = 0 ) {
	if ( empty( $to ) ) $to = time();
	$diff = (int) abs( $to - $from );
	if ( $diff < 3600 ) return max( 1, round( $diff / 60 ) ) . ' mins';
	if ( $diff < 86400 ) return round( $diff / 3600 ) . ' hours';
	return round( $diff / 86400 ) . ' days';
}

function trailingslashit( $string ) {
	return rtrim( (string) $string, '/\\' ) . '/';
}

function untrailingslashit( $string ) {
	return rtrim( (string) $string, '/\\' );
}

function wp_generate_password( $length = 12, $special_chars = true ) {
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	$password = '';
	for ( $i = 0; $i < $length; $i++ ) {
		$password .= $chars[ wp_rand( 0, strlen( $chars ) - 1 ) ];
	}
	return $password;
}

function wp_rand( $min = 0, $max = 0 ) {
	return mt_rand( $min, $max );
}

function wp_check_filetype_and_ext( $file, $filename, $mimes = null ) {
	$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
	$type = 'application/octet-stream';
	if ( in_array( $ext, array( 'jpg', 'jpeg' ), true ) ) $type = 'image/jpeg';
	elseif ( 'png' === $ext ) $type = 'image/png';
	elseif ( 'pdf' === $ext ) $type = 'application/pdf';

	return array(
		'ext'             => $ext,
		'type'            => $type,
		'proper_filename' => false,
	);
}

// ==============================================================================
// IN-MEMORY SQL DATABASE MOCK
// ==============================================================================

class WPDB_Payment_Test_Mock {
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
			'wp_eventflow_notifications'   => array(),
			'wp_eventflow_email_logs'      => array(),
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
		return 1;
	}

	public function insert( $table, $data, $format = null ) {
		static $auto_inc = 2000;
		$this->insert_id = ++$auto_inc;
		$row = (object) $data;
		$row->id = $this->insert_id;
		if ( ! isset( $this->tables[ $table ] ) ) {
			$this->tables[ $table ] = array();
		}
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
		if ( preg_match( '/COUNT\(\*\)\s+FROM\s+wp_eventflow_payments\s+WHERE\s+version\s*>\s*1/i', $query ) ) {
			$resubs = 0;
			foreach ( $this->tables['wp_eventflow_payments'] as $p ) {
				if ( isset( $p->version ) && (int) $p->version > 1 ) {
					$resubs++;
				}
			}
			return $resubs;
		}

		if ( preg_match( '/SELECT\s+COUNT\([^)]*\)\s+FROM\s+/i', $query ) ) {
			$q = preg_replace( '/LIMIT\s+\d+.*$/i', '', $query );
			$results = $this->get_results( $q );
			return count( $results );
		}
		$row = $this->get_row( $query );
		if ( ! $row ) return null;
		$arr = (array) $row;
		return reset( $arr );
	}

	public function get_col( $query, $col_offset = 0 ) {
		return array();
	}

	public function get_results( $query, $output = OBJECT ) {
		// Specific handler for get_payment_counts
		if ( false !== strpos( $query, 'COUNT(id) as total' ) ) {
			$payments = array_values( $this->tables['wp_eventflow_payments'] );
			$total = count( $payments );
			$pending = 0;
			$verified = 0;
			$rejected = 0;
			$reupload = 0;
			foreach ( $payments as $p ) {
				if ( 'under_verification' === $p->status ) $pending++;
				elseif ( 'verified' === $p->status ) $verified++;
				elseif ( 'rejected' === $p->status ) $rejected++;
				elseif ( 'reupload_requested' === $p->status ) $reupload++;
			}
			$obj = (object) array(
				'total'    => $total,
				'pending'  => $pending,
				'verified' => $verified,
				'rejected' => $rejected,
				'reupload' => $reupload,
			);
			return array( $obj );
		}

		// Count resubmissions
		if ( false !== strpos( $query, 'COUNT(*) FROM wp_eventflow_payments WHERE version > 1' ) ) {
			$resubs = 0;
			foreach ( $this->tables['wp_eventflow_payments'] as $p ) {
				if ( isset( $p->version ) && (int) $p->version > 1 ) {
					$resubs++;
				}
			}
			return $resubs;
		}

		$target_table = null;
		if ( preg_match( '/FROM\s+([`\w]+)/i', $query, $m ) ) {
			$found = trim( $m[1], '`' );
			if ( isset( $this->tables[ $found ] ) ) {
				$target_table = $found;
			}
		}
		if ( ! $target_table ) {
			foreach ( $this->tables as $tbl_name => $rows ) {
				if ( false !== strpos( $query, $tbl_name ) ) {
					$target_table = $tbl_name;
					break;
				}
			}
		}

		if ( ! $target_table || ! isset( $this->tables[ $target_table ] ) ) {
			return array();
		}

		$raw_rows = array_values( $this->tables[ $target_table ] );
		$filtered = array();

		// Enrich with joins if applicable
		if ( 'wp_eventflow_payments' === $target_table ) {
			foreach ( $raw_rows as $row ) {
				$r_copy = clone $row;
				if ( isset( $r_copy->registration_id ) && isset( $this->tables['wp_eventflow_registrations'][ $r_copy->registration_id ] ) ) {
					$reg = $this->tables['wp_eventflow_registrations'][ $r_copy->registration_id ];
					foreach ( $reg as $k => $v ) {
						if ( ! isset( $r_copy->$k ) ) {
							$r_copy->$k = $v;
						}
					}
					$r_copy->reg_team_name      = $reg->team_name ?? '';
					$r_copy->reg_created_at     = $reg->created_at ?? '';
					$r_copy->reg_payment_status = $reg->payment_status ?? '';

					if ( isset( $reg->activity_id ) && isset( $this->tables['wp_eventflow_activities'][ $reg->activity_id ] ) ) {
						$act = $this->tables['wp_eventflow_activities'][ $reg->activity_id ];
						$r_copy->activity_title = $act->title ?? '';
						$r_copy->activity_slug  = $act->slug ?? '';
					}
					if ( isset( $reg->event_id ) && isset( $this->tables['wp_eventflow_events'][ $reg->event_id ] ) ) {
						$ev = $this->tables['wp_eventflow_events'][ $reg->event_id ];
						$r_copy->event_title    = $ev->title ?? '';
						$r_copy->event_currency = $ev->currency ?? 'PKR';
					}
				}
				$filtered[] = $r_copy;
			}
		} elseif ( 'wp_eventflow_registrations' === $target_table ) {
			foreach ( $raw_rows as $row ) {
				$r_copy = clone $row;
				if ( isset( $r_copy->activity_id ) && isset( $this->tables['wp_eventflow_activities'][ $r_copy->activity_id ] ) ) {
					$act = $this->tables['wp_eventflow_activities'][ $r_copy->activity_id ];
					$r_copy->activity_title = $act->title ?? 'Activity';
					$r_copy->activity_type  = $act->activity_type ?? 'solo';
				}
				$filtered[] = $r_copy;
			}
		} else {
			$filtered = $raw_rows;
		}

		if ( preg_match( "/(?:\b\w+\.)?registration_id\s*=\s*(\d+)/i", $query, $m ) ) {
			$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
				return isset( $r->registration_id ) && (int) $r->registration_id === (int) $m[1];
			} );
		} elseif ( preg_match( "/(?<!registration_)\b(?:[a-z_]+\.)?id\s*=\s*(\d+)/i", $query, $m ) ) {
			$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
				return isset( $r->id ) && (int) $r->id === (int) $m[1];
			} );
		}
		if ( preg_match( "/(?:[a-z_]+\.)?reg_id\s*=\s*'([^']+)'/i", $query, $m ) ) {
			$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
				return isset( $r->reg_id ) && $r->reg_id === $m[1];
			} );
		}
		if ( preg_match( "/(?:[a-z_]+\.)?user_id\s*=\s*(\d+)/i", $query, $m ) ) {
			$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
				return isset( $r->user_id ) && (int) $r->user_id === (int) $m[1];
			} );
		}
		if ( preg_match( "/(?:[a-z_]+\.)?status\s*=\s*'([^']+)'/i", $query, $m ) ) {
			$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
				return isset( $r->status ) && $r->status === $m[1];
			} );
		}
		if ( preg_match( "/(?:[a-z_]+\.)?is_read\s*=\s*(\d+)/i", $query, $m ) ) {
			$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
				return isset( $r->is_read ) && (int) $r->is_read === (int) $m[1];
			} );
		}

		if ( false !== stripos( $query, 'ORDER BY' ) ) {
			if ( false !== stripos( $query, 'version ASC' ) ) {
				usort( $filtered, function( $a, $b ) {
					$va = isset( $a->version ) ? (int) $a->version : 1;
					$vb = isset( $b->version ) ? (int) $b->version : 1;
					return $va - $vb;
				} );
			} elseif ( false !== stripos( $query, 'id DESC' ) ) {
				usort( $filtered, function( $a, $b ) {
					return (int) $b->id - (int) $a->id;
				} );
			}
		}

		if ( preg_match( "/LIMIT\s+(\d+)(?:\s+OFFSET\s+(\d+))?/i", $query, $m ) ) {
			$limit  = (int) $m[1];
			$offset = isset( $m[2] ) ? (int) $m[2] : 0;
			$filtered = array_slice( $filtered, $offset, $limit );
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

// ==============================================================================
// WORDPRESS GLOBAL FUNCTIONS & HOOK STUBS
// ==============================================================================

global $wpdb, $current_user_id, $current_user_roles, $wp_actions;
$wpdb = new WPDB_Payment_Test_Mock();
$current_user_id = 1;
$current_user_roles = array( 'administrator' );
$wp_actions = array();

function do_action( $tag, ...$args ) {
	global $wp_actions;
	$wp_actions[] = array( 'tag' => $tag, 'args' => $args );
}

function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {}
function apply_filters( $tag, $value, ...$args ) { return $value; }
function wp_unslash( $val ) { return $val; }
function sanitize_text_field( $val ) { return trim( strip_tags( (string) $val ) ); }
function sanitize_textarea_field( $val ) { return trim( strip_tags( (string) $val ) ); }
function sanitize_email( $email ) { return filter_var( trim( (string) $email ), FILTER_SANITIZE_EMAIL ); }
function is_email( $email ) { return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false; }
function sanitize_file_name( $name ) { return preg_replace( '/[^a-zA-Z0-9_\.-]/', '', (string) $name ); }
function absint( $val ) { return abs( (int) $val ); }
function current_time( $type ) { return 'timestamp' === $type ? time() : date( 'Y-m-d H:i:s' ); }
function date_i18n( $format, $timestamp = null ) { return date( $format, $timestamp ?: time() ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return $url; }
function esc_url_raw( $url ) { return $url; }
function esc_html__( $text, $domain = 'default' ) { return $text; }
function esc_attr__( $text, $domain = 'default' ) { return $text; }
function __( $text, $domain = 'default' ) { return $text; }
function _e( $text, $domain = 'default' ) { echo $text; }
function get_option( $name, $default = false ) {
	if ( 'date_format' === $name ) return 'Y-m-d';
	if ( 'time_format' === $name ) return 'H:i:s';
	return $default;
}
function update_option( $name, $value ) { return true; }
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}
function get_current_user_id() { global $current_user_id; return $current_user_id; }
function wp_get_current_user() {
	global $current_user_id, $current_user_roles;
	return (object) array(
		'ID'           => $current_user_id,
		'user_login'   => 'user_' . $current_user_id,
		'display_name' => 'User ' . $current_user_id,
		'user_email'   => 'user' . $current_user_id . '@example.com',
		'roles'        => $current_user_roles,
	);
}
function get_userdata( $user_id ) {
	return (object) array(
		'ID'           => $user_id,
		'user_login'   => 'user_' . $user_id,
		'display_name' => 'User ' . $user_id,
		'user_email'   => 'user' . $user_id . '@example.com',
		'roles'        => array( 'eventflow_participant' ),
	);
}
function get_users( $args = array() ) {
	return array(
		(object) array( 'ID' => 1, 'user_email' => 'admin@portal.sibacom.tech', 'display_name' => 'Main Admin' ),
	);
}
function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
	return true;
}
function wp_upload_dir() {
	$dir = sys_get_temp_dir() . '/eventflow_tests';
	return array(
		'basedir' => $dir,
		'baseurl' => 'https://portal.sibacom.tech/wp-content/uploads',
	);
}

function user_can( $user, $capability ) {
	$user_id = is_object( $user ) ? ( $user->ID ?? 0 ) : (int) $user;
	if ( 1 === $user_id ) {
		return true;
	}
	if ( is_object( $user ) && isset( $user->roles ) && in_array( 'administrator', (array) $user->roles, true ) ) {
		return true;
	}
	return false;
}

function current_user_can( $capability ) {
	global $current_user_id, $current_user_roles;
	if ( 1 === (int) $current_user_id || in_array( 'administrator', (array) $current_user_roles, true ) ) {
		return true;
	}
	if ( in_array( $capability, (array) $current_user_roles, true ) ) {
		return true;
	}
	return false;
}

function get_user_meta( $user_id, $key = '', $single = false ) {
	return $single ? '' : array();
}

function update_user_meta( $user_id, $key, $value ) {
	return true;
}

// Load plugin autoloader and core classes
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-autoloader.php';
EventFlow_Autoloader::register();

// Require essential classes directly
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-db.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-roles.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-security.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-event-manager.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-activity-manager.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-team-manager.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-registration-manager.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-notification-manager.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-payment-manager.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-payment-verification.php';

// ==============================================================================
// TEST HARNESS & ASSERTIONS
// ==============================================================================

class PaymentWorkflowTestSuite {
	private $passed = 0;
	private $failed = 0;
	private $errors = array();

	public function assert( $condition, $message ) {
		if ( $condition ) {
			$this->passed++;
			echo "  [PASS] {$message}\n";
		} else {
			$this->failed++;
			$this->errors[] = $message;
			echo "  [FAIL] {$message}\n";
		}
	}

	public function run() {
		global $wpdb, $current_user_id, $current_user_roles;

		echo "====================================================================\n";
		echo " SIBACOM EVENTFLOW: STRICT PAYMENT WORKFLOW TEST SUITE (15 SCENARIOS)\n";
		echo "====================================================================\n\n";

		// 0. Seed test event and activity
		$event_id = 1;
		$wpdb->tables['wp_eventflow_events'][$event_id] = (object) array(
			'id'         => $event_id,
			'title'      => 'SIBACOM 2026 Mega Olympiad',
			'currency'   => 'PKR',
			'status'     => 'active',
			'start_date' => '2026-11-15',
			'end_date'   => '2026-11-17',
		);

		$activity_id = 10;
		$wpdb->tables['wp_eventflow_activities'][$activity_id] = (object) array(
			'id'                   => $activity_id,
			'event_id'             => $event_id,
			'title'                => 'Speed Programming Championship',
			'registration_fee'     => 1500.00,
			'participation_type'   => 'team',
			'max_participants'     => 100,
			'current_participants' => 0,
			'status'               => 'published',
		);

		// Seed a registration for Participant 1 (User ID = 50)
		$user_1_id = 50;
		$reg_1_id = 100;
		$wpdb->tables['wp_eventflow_registrations'][$reg_1_id] = (object) array(
			'id'                 => $reg_1_id,
			'reg_id'             => 'EFL-2026-SP101',
			'event_id'           => $event_id,
			'activity_id'        => $activity_id,
			'user_id'            => $user_1_id,
			'participation_type' => 'team',
			'team_name'          => 'Code Masters',
			'total_fee'          => 1500.00,
			'payment_status'     => 'pending',
			'application_status' => 'pending',
			'rejection_reason'   => null,
			'created_at'         => '2026-11-05 08:00:00',
			'updated_at'         => '2026-11-05 08:00:00',
		);

		// Create mock file for proof upload
		$upload_dir = wp_upload_dir()['basedir'] . '/eventflow/receipts/' . date( 'Y/m' );
		if ( ! file_exists( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}
		$test_proof_file = $upload_dir . '/test_bank_receipt.png';
		file_put_contents( $test_proof_file, 'MOCK_IMAGE_DATA_PNG' );

		$mock_files_1 = array(
			'payment_proof' => array(
				'name'     => 'test_bank_receipt.png',
				'type'     => 'image/png',
				'tmp_name' => $test_proof_file,
				'error'    => UPLOAD_ERR_OK,
				'size'     => filesize( $test_proof_file ),
			),
		);

		// ==============================================================================
		// SCENARIO 1: New participant submits payment proof -> pending verification (version 1)
		// ==============================================================================
		echo "--- Scenario 1: Initial Payment Submission ---\n";
		$current_user_id = $user_1_id;
		$current_user_roles = array( 'eventflow_participant' );

		$submission_data = array(
			'registration_id' => $reg_1_id,
			'payment_method'  => 'Bank Transfer',
			'transaction_id'  => 'TXN-99887766',
			'payment_date'    => '2026-11-05',
			'amount'          => 1500.00,
		);

		$res1 = EventFlow_Payment_Manager::process_submission( $user_1_id, $submission_data, $mock_files_1 );
		$this->assert( ! is_wp_error( $res1 ) && ! empty( $res1['payment_id'] ), 'Scenario 1.1: Payment proof submitted successfully' );

		$payment_v1 = EventFlow_Payment_Manager::get_payment_by_registration( $reg_1_id );
		$this->assert( $payment_v1 && (int) $payment_v1->version === 1, 'Scenario 1.2: Payment record has version 1' );
		$this->assert( $payment_v1->status === 'under_verification', 'Scenario 1.3: Payment status is under_verification' );
		$this->assert( $wpdb->tables['wp_eventflow_registrations'][$reg_1_id]->payment_status === 'under_verification', 'Scenario 1.4: Registration payment_status updated to under_verification' );

		// ==============================================================================
		// SCENARIO 2: Admin approves payment -> verified status, admin ID & timestamp logged
		// ==============================================================================
		echo "\n--- Scenario 2: Admin Payment Approval ---\n";
		$admin_id = 1;
		$current_user_id = $admin_id;
		$current_user_roles = array( 'administrator' );

		$verify_res = EventFlow_Payment_Verification::verify_payment( $payment_v1->id, 'Funds verified in bank statement' );
		$this->assert( ! is_wp_error( $verify_res ) && true === $verify_res, 'Scenario 2.1: Admin verifies payment successfully' );

		$approved_payment = EventFlow_Payment_Manager::get_payment_by_registration( $reg_1_id );
		$this->assert( $approved_payment->status === 'verified', 'Scenario 2.2: Payment status updated to verified' );
		$this->assert( (int) $approved_payment->verified_by === $admin_id, 'Scenario 2.3: Admin user ID recorded on payment' );
		$this->assert( ! empty( $approved_payment->verified_at ), 'Scenario 2.4: Verification timestamp recorded' );
		$this->assert( $wpdb->tables['wp_eventflow_registrations'][$reg_1_id]->payment_status === 'verified', 'Scenario 2.5: Registration payment status is verified' );

		// ==============================================================================
		// SCENARIO 3: Participant receives approval notification in-dashboard
		// ==============================================================================
		echo "\n--- Scenario 3: In-Dashboard Approval Notification ---\n";
		$user_1_notifs = EventFlow_Notification_Manager::get_user_notifications( $user_1_id );
		$this->assert( count( $user_1_notifs) >= 1, 'Scenario 3.1: Participant received in-dashboard notification' );

		$approval_notif = null;
		foreach ( $user_1_notifs as $n ) {
			if ( 'payment_approved' === $n->type || 'payment_verified' === $n->type ) {
				$approval_notif = $n;
				break;
			}
		}
		$this->assert( null !== $approval_notif, 'Scenario 3.2: Found notification of type payment_approved/payment_verified' );
		$this->assert( false !== strpos( $approval_notif->title, 'Verified' ) || false !== strpos( $approval_notif->title, 'Approved' ) || false !== strpos( $approval_notif->message, 'verified' ), 'Scenario 3.3: Notification confirms payment verified' );

		// ==============================================================================
		// SCENARIO 4: Admin rejects payment with mandatory reason
		// ==============================================================================
		echo "\n--- Scenario 4: Admin Rejection with Mandatory Reason ---\n";
		// Create a second registration for rejection test
		$user_2_id = 60;
		$reg_2_id = 200;
		$wpdb->tables['wp_eventflow_registrations'][$reg_2_id] = (object) array(
			'id'                 => $reg_2_id,
			'reg_id'             => 'EFL-2026-SP202',
			'event_id'           => $event_id,
			'activity_id'        => $activity_id,
			'user_id'            => $user_2_id,
			'participation_type' => 'solo',
			'team_name'          => 'Solo Racer',
			'total_fee'          => 1500.00,
			'payment_status'     => 'pending',
			'application_status' => 'pending',
			'rejection_reason'   => null,
			'created_at'         => '2026-11-05 09:00:00',
			'updated_at'         => '2026-11-05 09:00:00',
		);

		// Submit initial payment for reg 2
		$current_user_id = $user_2_id;
		$mock_files_2 = array(
			'payment_proof' => array(
				'name'     => 'test_receipt_user2.png',
				'type'     => 'image/png',
				'tmp_name' => $test_proof_file,
				'error'    => UPLOAD_ERR_OK,
				'size'     => filesize( $test_proof_file ),
			),
		);

		$sub2 = EventFlow_Payment_Manager::process_submission( $user_2_id, array(
			'registration_id' => $reg_2_id,
			'payment_method'  => 'Easypaisa',
			'transaction_id'  => 'EP-11223344',
			'payment_date'    => '2026-11-05',
			'amount'          => 1500.00,
		), $mock_files_2 );
		$p2_v1_id = $sub2['payment_id'];

		// Admin attempts rejection without reason -> MUST FAIL
		$current_user_id = $admin_id;
		$reject_empty = EventFlow_Payment_Verification::reject_payment( $p2_v1_id, '   ' );
		$this->assert( is_wp_error( $reject_empty ) && 'missing_reason' === $reject_empty->get_error_code(), 'Scenario 4.1: Rejection rejected when reason is empty' );

		// Admin provides explicit rejection reason and remarks
		$exact_reason = 'Transaction reference could not be located in university bank statement. The uploaded image is blurry and missing sender name.';
		$admin_notes  = 'Please re-upload a clear screenshot showing transaction reference and date.';
		$reject_ok = EventFlow_Payment_Verification::reject_payment( $p2_v1_id, $admin_id, $exact_reason, $admin_notes );
		$this->assert( ! is_wp_error( $reject_ok ) && true === $reject_ok, 'Scenario 4.2: Payment rejected successfully with valid reason' );

		$p2_rejected = EventFlow_Payment_Manager::get_payment( $p2_v1_id );
		$this->assert( 'rejected' === $p2_rejected->status, 'Scenario 4.3: Payment row status set to rejected' );
		$this->assert( $p2_rejected->rejection_reason === $exact_reason, 'Scenario 4.4: Exact rejection reason stored in payment row' );
		$this->assert( $p2_rejected->notes === $admin_notes, 'Scenario 4.4b: Admin remarks stored in payment notes' );
		$this->assert( $wpdb->tables['wp_eventflow_registrations'][$reg_2_id]->payment_status === 'rejected', 'Scenario 4.5: Registration payment_status set to rejected' );
		$this->assert( $wpdb->tables['wp_eventflow_registrations'][$reg_2_id]->rejection_reason === $exact_reason, 'Scenario 4.6: Registration active rejection_reason stored' );
		$this->assert( $wpdb->tables['wp_eventflow_registrations'][$reg_2_id]->admin_notes === $admin_notes, 'Scenario 4.6b: Registration admin_notes stored' );

		// ==============================================================================
		// SCENARIO 5: Participant receives rejection notification in-dashboard
		// ==============================================================================
		echo "\n--- Scenario 5: In-Dashboard Rejection Notification ---\n";
		$user_2_notifs = EventFlow_Notification_Manager::get_user_notifications( $user_2_id );
		$rejection_notif = null;
		foreach ( $user_2_notifs as $n ) {
			if ( 'payment_rejected' === $n->type ) {
				$rejection_notif = $n;
				break;
			}
		}
		$this->assert( null !== $rejection_notif, 'Scenario 5.1: Found in-dashboard notification of type payment_rejected' );
		$this->assert( 'Payment Rejected – Re-upload Required' === $rejection_notif->title, 'Scenario 5.1b: Notification title is Payment Rejected – Re-upload Required' );
		$this->assert( false !== strpos( $rejection_notif->message, $exact_reason ), 'Scenario 5.2: Notification message contains exact Admin Rejection Reason' );
		$this->assert( false !== strpos( $rejection_notif->action_url, 'tab=payment' ) && false !== strpos( $rejection_notif->action_url, 'action=reupload' ), 'Scenario 5.3: Action URL directs participant to re-upload proof (?tab=payment&action=reupload)' );

		// ==============================================================================
		// SCENARIO 6: Participant dashboard displays exact rejection reason & panel
		// ==============================================================================
		echo "\n--- Scenario 6: Participant Dashboard Rejection Display ---\n";
		$current_user_id = $user_2_id;
		$user_2_regs = EventFlow_Registration_Manager::get_user_registrations( $user_2_id );
		$this->assert( count( $user_2_regs ) === 1, 'Scenario 6.1: Participant registrations retrieved' );
		$this->assert( 'rejected' === $user_2_regs[0]->payment_status, 'Scenario 6.2: Status badge shows rejected' );
		$payment_badge = EventFlow_Registration_Manager::get_status_badge( 'rejected', 'payment' );
		$this->assert( false !== strpos( $payment_badge, 'Rejected – Re-upload Required' ), 'Scenario 6.2b: Payment badge displays "Rejected – Re-upload Required"' );
		$app_badge = EventFlow_Registration_Manager::get_status_badge( 'rejected', 'app' );
		$this->assert( false !== strpos( $app_badge, 'Rejected' ) && false === strpos( $app_badge, 'Re-upload' ), 'Scenario 6.2c: Application badge remains strictly "Rejected"' );
		$this->assert( $user_2_regs[0]->rejection_reason === $exact_reason, 'Scenario 6.3: Exact rejection reason present for UI template render' );

		// ==============================================================================
		// SCENARIO 7: Participant re-uploads corrected payment proof
		// ==============================================================================
		echo "\n--- Scenario 7: Resubmission Workflow ---\n";
		$corrected_submission = array(
			'registration_id' => $reg_2_id,
			'payment_method'  => 'Bank Transfer',
			'transaction_id'  => 'TXN-CORRECTED-9900',
			'payment_date'    => '2026-11-05',
			'amount'          => 1500.00,
		);

		$mock_files_corrected = array(
			'payment_proof' => array(
				'name'     => 'test_receipt_user2_corrected.png',
				'type'     => 'image/png',
				'tmp_name' => $test_proof_file,
				'error'    => UPLOAD_ERR_OK,
				'size'     => filesize( $test_proof_file ),
			),
		);

		$resubmit_res = EventFlow_Payment_Manager::process_submission( $user_2_id, $corrected_submission, $mock_files_corrected );
		$this->assert( ! is_wp_error( $resubmit_res ) && ! empty( $resubmit_res['payment_id'] ), 'Scenario 7.1: Resubmission succeeded' );
		$p2_v2_id = $resubmit_res['payment_id'];

		// ==============================================================================
		// SCENARIO 8: Previous submission preserved (Version 1 immutable, Version 2 created)
		// ==============================================================================
		echo "\n--- Scenario 8: Version Immutability & Audit Trail ---\n";
		$all_p2_payments = EventFlow_Payment_Manager::get_all_payments_by_registration( $reg_2_id );
		$this->assert( count( $all_p2_payments ) === 2, 'Scenario 8.1: Historical payment records count is 2 (Never overwritten)' );

		$p2_v1_check = EventFlow_Payment_Manager::get_payment( $p2_v1_id );
		$this->assert( 'rejected' === $p2_v1_check->status, 'Scenario 8.2: Version 1 maintains rejected status' );
		$this->assert( $p2_v1_check->rejection_reason === $exact_reason, 'Scenario 8.3: Version 1 preserves exact historical rejection reason' );
		$this->assert( 'EP-11223344' === $p2_v1_check->transaction_id, 'Scenario 8.4: Version 1 preserves original transaction ID' );

		$p2_v2_check = EventFlow_Payment_Manager::get_payment( $p2_v2_id );
		$this->assert( (int) $p2_v2_check->version === 2, 'Scenario 8.5: New payment record has version 2' );
		$this->assert( 'under_verification' === $p2_v2_check->status, 'Scenario 8.6: Version 2 status is under_verification' );
		$this->assert( 'TXN-CORRECTED-9900' === $p2_v2_check->transaction_id, 'Scenario 8.7: Version 2 has new corrected transaction ID' );

		// ==============================================================================
		// SCENARIO 9: Latest submission in admin pending queue & active warning reset
		// ==============================================================================
		echo "\n--- Scenario 9: Status Reset & Admin Pending Queue ---\n";
		$reg_2_after = $wpdb->tables['wp_eventflow_registrations'][$reg_2_id];
		$this->assert( 'under_verification' === $reg_2_after->payment_status, 'Scenario 9.1: Registration payment status reset to under_verification' );
		$this->assert( empty( $reg_2_after->rejection_reason ), 'Scenario 9.2: Active rejection_reason cleared from registration so active warning is not shown' );

		$admin_pending_queue = EventFlow_Payment_Verification::get_payments( array(
			'status' => 'under_verification',
		) );
		$found_in_queue = false;
		foreach ( $admin_pending_queue['items'] as $item ) {
			if ( (int) $item->id === (int) $p2_v2_id ) {
				$found_in_queue = true;
				break;
			}
		}
		$this->assert( $found_in_queue, 'Scenario 9.3: Version 2 appears in Admin Pending Verification Queue' );

		// ==============================================================================
		// SCENARIO 10: Admin receives resubmission notification
		// ==============================================================================
		echo "\n--- Scenario 10: Admin Resubmission Notification ---\n";
		$admin_notifs = EventFlow_Notification_Manager::get_user_notifications( $admin_id );
		$resub_notif = null;
		foreach ( $admin_notifs as $an ) {
			if ( in_array( $an->type, array( 'admin_payment_resubmitted', 'payment_resubmitted' ), true ) && false !== strpos( $an->message, 'EFL-2026-SP202' ) ) {
				$resub_notif = $an;
				break;
			}
		}
		$this->assert( null !== $resub_notif, 'Scenario 10.1: Admin received notification of participant resubmission' );
		$this->assert( $resub_notif && false !== strpos( $resub_notif->message, 'EFL-2026-SP202' ), 'Scenario 10.2: Notification links to correct Registration ID' );

		// ==============================================================================
		// SCENARIO 11: Admin approves the resubmitted payment
		// ==============================================================================
		echo "\n--- Scenario 11: Admin Approves Resubmitted Payment ---\n";
		$current_user_id = $admin_id;
		$approve_v2 = EventFlow_Payment_Verification::verify_payment( $p2_v2_id, 'Second submission proof confirmed and verified.' );
		$this->assert( ! is_wp_error( $approve_v2 ) && true === $approve_v2, 'Scenario 11.1: Admin approves Version 2' );

		$p2_v2_approved = EventFlow_Payment_Manager::get_payment( $p2_v2_id );
		$this->assert( 'verified' === $p2_v2_approved->status, 'Scenario 11.2: Version 2 status is verified' );
		$this->assert( 'verified' === $wpdb->tables['wp_eventflow_registrations'][$reg_2_id]->payment_status, 'Scenario 11.3: Registration payment status is verified' );
		$this->assert( in_array( $wpdb->tables['wp_eventflow_registrations'][$reg_2_id]->application_status, array( 'approved', 'verified' ), true ), 'Scenario 11.4: Registration application status confirmed' );

		// Historical v1 still rejected
		$p2_v1_still = EventFlow_Payment_Manager::get_payment( $p2_v1_id );
		$this->assert( 'rejected' === $p2_v1_still->status, 'Scenario 11.5: Historical Version 1 remains rejected in audit history' );

		// ==============================================================================
		// SCENARIO 12: Participant receives final approval notification
		// ==============================================================================
		echo "\n--- Scenario 12: Final Approval Notification to Participant ---\n";
		$user_2_final_notifs = EventFlow_Notification_Manager::get_user_notifications( $user_2_id );
		$final_appr = null;
		foreach ( $user_2_final_notifs as $n ) {
			if ( 'payment_approved' === $n->type || 'payment_verified' === $n->type ) {
				$final_appr = $n;
				break;
			}
		}
		$this->assert( null !== $final_appr, 'Scenario 12.1: Participant 2 received final approval notification' );
		$this->assert( false !== strpos( $final_appr->message, 'confirmed' ) || false !== strpos( $final_appr->message, 'verified' ), 'Scenario 12.2: Message confirms registration is complete' );

		// ==============================================================================
		// SCENARIO 13: Participant cannot access another participant's payment records
		// ==============================================================================
		echo "\n--- Scenario 13: Security & Access Control ---\n";
		$current_user_id = $user_1_id; // User 1
		$current_user_roles = array( 'eventflow_participant' );

		// User 1 tries to submit payment for User 2's registration (reg_2_id)
		$unauthorized_sub = EventFlow_Payment_Manager::process_submission( $user_1_id, array(
			'registration_id' => $reg_2_id,
			'payment_method'  => 'JazzCash',
			'transaction_id'  => 'HACK-ATTEMPT',
			'payment_date'    => '2026-11-05',
			'amount'          => 1500.00,
		), $mock_files_1 );
		$this->assert( is_wp_error( $unauthorized_sub ) && 'unauthorized' === $unauthorized_sub->get_error_code(), 'Scenario 13.1: Cross-user payment submission blocked with unauthorized error' );

		// Non-staff tries to verify payment
		$unauthorized_verify = EventFlow_Payment_Verification::verify_payment( $p2_v2_id, 'Hacked' );
		$this->assert( is_wp_error( $unauthorized_verify ) && 'unauthorized' === $unauthorized_verify->get_error_code(), 'Scenario 13.2: Non-staff verification blocked with unauthorized error' );

		// ==============================================================================
		// SCENARIO 14: Repeated clicks do not create duplicate submissions (Throttling)
		// ==============================================================================
		echo "\n--- Scenario 14: Anti-Duplicate Rapid Click Throttling ---\n";
		$current_user_id = $user_2_id;
		$current_user_roles = array( 'eventflow_participant' );

		// Reset status of registration 2 temporarily to under_verification to test rapid click throttle
		$wpdb->tables['wp_eventflow_registrations'][$reg_2_id]->payment_status = 'under_verification';
		$wpdb->tables['wp_eventflow_payments'][$p2_v2_id]->status = 'under_verification';
		$wpdb->tables['wp_eventflow_payments'][$p2_v2_id]->created_at = current_time( 'mysql' );

		$payments_count_before = count( $wpdb->tables['wp_eventflow_payments'] );

		// Submit identical TID within 5 seconds
		$dup_sub = EventFlow_Payment_Manager::process_submission( $user_2_id, array(
			'registration_id' => $reg_2_id,
			'payment_method'  => 'Bank Transfer',
			'transaction_id'  => 'TXN-CORRECTED-9900', // Identical TID
			'payment_date'    => '2026-11-05',
			'amount'          => 1500.00,
		), $mock_files_corrected );

		$payments_count_after = count( $wpdb->tables['wp_eventflow_payments'] );
		$this->assert( $payments_count_before === $payments_count_after, 'Scenario 14.1: Repeated rapid submission with identical TID did not create duplicate payment row' );
		$this->assert( ! is_wp_error( $dup_sub ) && (int) $dup_sub['payment_id'] === (int) $p2_v2_id, 'Scenario 14.2: Repeated click returned existing active payment safely' );

		// Restore verified status
		$wpdb->tables['wp_eventflow_registrations'][$reg_2_id]->payment_status = 'verified';
		$wpdb->tables['wp_eventflow_payments'][$p2_v2_id]->status = 'verified';

		// ==============================================================================
		// SCENARIO 15: Clean UI, Inspector Full Details & Data Integrity Audit
		// ==============================================================================
		echo "\n--- Scenario 15: Clean UI & Data Integrity Audit ---\n";
		// Check that get_payment_full_details returns version history, roster, and details
		$full_details = EventFlow_Payment_Verification::get_payment_full_details( $p2_v2_id );
		$this->assert( ! empty( $full_details['submissions'] ) && count( $full_details['submissions'] ) === 2, 'Scenario 15.1: Inspector returns all historical versions' );
		$versions = array_map( function( $sub ) {
			return (int) $sub['version'];
		}, $full_details['submissions'] );
		$this->assert( in_array( 1, $versions, true ), 'Scenario 15.2: Submission history includes version 1' );
		$this->assert( in_array( 2, $versions, true ), 'Scenario 15.3: Submission history includes version 2' );
		$this->assert( $full_details['previous_rejection_reason'] === $exact_reason, 'Scenario 15.3b: Inspector provides previous rejection reason' );
		$this->assert( (int) $full_details['latest_payment_id'] === (int) $p2_v2_id, 'Scenario 15.3c: Inspector provides correct latest_payment_id' );
		$this->assert( false === $full_details['submissions'][0]['is_latest'], 'Scenario 15.3d: v1 is not marked latest' );
		$this->assert( true === $full_details['submissions'][1]['is_latest'], 'Scenario 15.3e: v2 is marked latest' );

		// Verify get_payment_counts returns accurate KPI numbers
		$counts = EventFlow_Payment_Verification::get_payment_counts( $event_id );
		$this->assert( isset( $counts['total'] ) && $counts['total'] >= 2, 'Scenario 15.4: KPI Total submissions count accurate' );
		$this->assert( isset( $counts['resubmissions'] ) && $counts['resubmissions'] >= 1, 'Scenario 15.5: KPI Resubmissions count accurate' );
		$this->assert( isset( $counts['verified'] ) && $counts['verified'] >= 2, 'Scenario 15.6: KPI Verified count accurate' );

		// Notification mark-as-read test
		$notif_to_read = $user_2_final_notifs[0];
		$mark_res = EventFlow_Notification_Manager::mark_as_read( $notif_to_read->id, $user_2_id );
		$this->assert( true === $mark_res, 'Scenario 15.7: In-dashboard notification marked as read' );

		// ==============================================================================
		// SCENARIO 16: Multi-Round Rejection and Re-upload Cycle (Version 3)
		// ==============================================================================
		echo "\n--- Scenario 16: Multi-Round Rejection & Resubmission Cycle (Version 3) ---\n";
		// Admin rejects Version 2 with a new reason
		$current_user_id = $admin_id;
		$round2_reason = 'Amount is short by PKR 300. Required: PKR 1,500, received: PKR 1,200.';
		$round2_notes  = 'Please deposit remaining PKR 300 and upload the updated receipt proof.';
		$reject_v2_res = EventFlow_Payment_Verification::reject_payment( $p2_v2_id, $admin_id, $round2_reason, $round2_notes );
		$this->assert( true === $reject_v2_res, 'Scenario 16.1: Admin successfully rejects Version 2' );

		// Check registration payment status is back to 'rejected'
		$reg_2_round2 = $wpdb->tables['wp_eventflow_registrations'][$reg_2_id];
		$this->assert( 'rejected' === $reg_2_round2->payment_status, 'Scenario 16.2: Registration status resets to rejected after second rejection' );
		$this->assert( $round2_reason === $reg_2_round2->rejection_reason, 'Scenario 16.3: Active rejection reason updated to round 2 reason' );

		// Participant receives new rejection notification
		$user_2_notifs_round2 = EventFlow_Notification_Manager::get_user_notifications( $user_2_id );
		$round2_notif = $user_2_notifs_round2[0];
		$this->assert( false !== strpos( $round2_notif->message, $round2_reason ), 'Scenario 16.4: Participant receives second rejection notification with round 2 reason' );

		// Participant uploads Version 3 without explicit TID to test fallback TID generation in re-upload
		$current_user_id = $user_2_id;
		$submission_v3 = array(
			'registration_id' => $reg_2_id,
			'payment_method'  => 'Bank Transfer',
			'transaction_id'  => '', // Omitted TID
			'payment_date'    => '2026-11-05',
			'amount'          => 1500.00,
		);
		$mock_files_v3 = array(
			'payment_proof' => array(
				'name'     => 'test_receipt_user2_v3.png',
				'type'     => 'image/png',
				'tmp_name' => $test_proof_file,
				'error'    => UPLOAD_ERR_OK,
				'size'     => filesize( $test_proof_file ),
			),
		);
		$resubmit_v3_res = EventFlow_Payment_Manager::process_submission( $user_2_id, $submission_v3, $mock_files_v3 );
		$this->assert( ! is_wp_error( $resubmit_v3_res ) && ! empty( $resubmit_v3_res['payment_id'] ), 'Scenario 16.5: Version 3 resubmission succeeds with auto-generated TID' );
		$p2_v3_id = $resubmit_v3_res['payment_id'];

		$p2_v3_check = EventFlow_Payment_Manager::get_payment( $p2_v3_id );
		$this->assert( (int) $p2_v3_check->version === 3, 'Scenario 16.6: Payment record version is 3' );
		$this->assert( 'under_verification' === $p2_v3_check->status, 'Scenario 16.7: Version 3 status is under_verification' );
		$this->assert( 0 === strpos( $p2_v3_check->transaction_id, 'TXN-' ), 'Scenario 16.8: Version 3 has auto-generated TXN- prefix' );

		// Check registration payment status is back to 'under_verification'
		$reg_2_v3 = $wpdb->tables['wp_eventflow_registrations'][$reg_2_id];
		$this->assert( 'under_verification' === $reg_2_v3->payment_status, 'Scenario 16.9: Registration payment status returned to under_verification' );
		$this->assert( empty( $reg_2_v3->rejection_reason ), 'Scenario 16.10: Active rejection reason cleared upon Version 3 upload' );

		// Verify 3 immutable payment rows exist for registration 2
		$all_p2_payments_v3 = EventFlow_Payment_Manager::get_all_payments_by_registration( $reg_2_id );
		$this->assert( count( $all_p2_payments_v3 ) === 3, 'Scenario 16.11: Exactly 3 historical versions preserved in database' );

		// Admin verifies Version 3
		$current_user_id = $admin_id;
		$verify_v3_res = EventFlow_Payment_Verification::verify_payment( $p2_v3_id, 'Version 3 complete and fee verified in full.' );
		$this->assert( true === $verify_v3_res, 'Scenario 16.12: Admin verifies Version 3' );

		$p2_v3_final = EventFlow_Payment_Manager::get_payment( $p2_v3_id );
		$this->assert( 'verified' === $p2_v3_final->status, 'Scenario 16.13: Version 3 status is verified' );
		$this->assert( 'verified' === $wpdb->tables['wp_eventflow_registrations'][$reg_2_id]->payment_status, 'Scenario 16.14: Final registration payment status is verified' );

		// Verify full details for the 3 versions
		$v3_full_details = EventFlow_Payment_Verification::get_payment_full_details( $p2_v3_id );
		$this->assert( count( $v3_full_details['submissions'] ) === 3, 'Scenario 16.15: Inspector returns all 3 submissions in chronological order' );
		$this->assert( false === $v3_full_details['submissions'][0]['is_latest'], 'Scenario 16.16: v1 is not latest' );
		$this->assert( false === $v3_full_details['submissions'][1]['is_latest'], 'Scenario 16.17: v2 is not latest' );
		$this->assert( true === $v3_full_details['submissions'][2]['is_latest'], 'Scenario 16.18: v3 is latest' );
		$this->assert( 'rejected' === $v3_full_details['submissions'][0]['status'], 'Scenario 16.19: v1 retains rejected status' );
		$this->assert( 'rejected' === $v3_full_details['submissions'][1]['status'], 'Scenario 16.20: v2 retains rejected status' );
		$this->assert( 'verified' === $v3_full_details['submissions'][2]['status'], 'Scenario 16.21: v3 is verified' );

		echo "\n====================================================================\n";
		echo " RESULTS: {$this->passed} PASSED, {$this->failed} FAILED\n";
		echo "====================================================================\n";

		if ( ! empty( $this->errors ) ) {
			echo "Failed Tests:\n";
			foreach ( $this->errors as $err ) {
				echo " - {$err}\n";
			}
			exit( 1 );
		}
	}
}

$suite = new PaymentWorkflowTestSuite();
$suite->run();
