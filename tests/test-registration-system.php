<?php
/**
 * EventFlow Comprehensive Registration & Payment Lifecycle Test Suite
 *
 * Runs end-to-end unit and integration tests covering:
 * - Scenario 1: Individual / Solo registration
 * - Scenario 2: Team registration with minimum members
 * - Scenario 3: Team registration with maximum members
 * - Scenario 4: Adding multiple members dynamically
 * - Scenario 5: Removing members dynamically
 * - Scenario 6: Attempting to exceed maximum
 * - Scenario 7: Attempting to submit below minimum
 * - Scenario 8: Invalid email and phone numbers
 * - Scenario 9: Duplicate team member information (duplicate email & duplicate phone)
 * - Scenario 10: Per-team fee calculation
 * - Scenario 11: Per-person fee calculation
 * - Scenario 12: Payment account information display
 * - Scenario 13: Valid and invalid payment receipt uploads (types, sizes)
 * - Scenario 14: Payment verification by admin
 * - Scenario 15: Payment rejection and replacement receipt
 * - Scenario 16: Registration confirmation emails
 * - Scenario 17: Mobile responsiveness and CSS classes
 * - Scenario 18: Existing registration records and backward compatibility
 */

define( 'ABSPATH', __DIR__ . '/../' );
if ( ! defined( 'EVENTFLOW_PLUGIN_DIR' ) ) { define( 'EVENTFLOW_PLUGIN_DIR', file_exists( __DIR__ . '/../includes/class-db.php' ) ? dirname( __DIR__ ) . '/' : __DIR__ . '/../eventflow/' ); }
define( 'EVENTFLOW_PLUGIN_URL', 'https://example.com/wp-content/plugins/eventflow/' );
define( 'EVENTFLOW_VERSION', '4.0.2' );
define( 'AUTH_KEY', 'test-salt-secret-auth-key-1234567890' );
define( 'AUTH_SALT', 'test-salt-secret-phrase-0987654321' );

// Mock WordPress In-Memory Database Harness
class WPDB_Mock_Reg {
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
			'wp_eventflow_teams'          => array(),
			'wp_eventflow_team_members'    => array(),
			'wp_eventflow_payments'        => array(),
			'wp_eventflow_payment_logs'    => array(),
			'wp_eventflow_email_templates' => array(),
			'wp_eventflow_email_logs'      => array(),
			'wp_eventflow_audit_logs'      => array(),
		);
	}

	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}

	public function esc_like( $text ) {
		return addcslashes( $text, '_%\\' );
	}

	public function prepare( $query, ...$args ) {
		if ( empty( $args ) ) {
			return $query;
		}
		if ( is_array( $args[0] ) && count( $args ) === 1 ) {
			$args = $args[0];
		}
		foreach ( $args as $arg ) {
			if ( is_int( $arg ) || is_float( $arg ) ) {
				$rep = $arg;
			} elseif ( is_null( $arg ) ) {
				$rep = 'NULL';
			} else {
				$rep = "'" . addslashes( (string) $arg ) . "'";
			}
			$pos = strpos( $query, '%s' );
			if ( false === $pos ) {
				$pos = strpos( $query, '%d' );
			}
			if ( false === $pos ) {
				$pos = strpos( $query, '%f' );
			}
			if ( false !== $pos ) {
				$query = substr_replace( $query, $rep, $pos, 2 );
			}
		}
		return $query;
	}

	public function query( $sql ) {
		return true;
	}

	public function insert( $table, $data, $format = null ) {
		static $auto_inc = 100;
		$this->insert_id = ++$auto_inc;
		$row = (object) array_merge( array( 'id' => $this->insert_id ), $data );
		$this->tables[ $table ][ $this->insert_id ] = $row;
		return 1;
	}

	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		if ( ! isset( $this->tables[ $table ] ) ) {
			return 0;
		}
		$updated = 0;
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
		if ( ! isset( $this->tables[ $table ] ) ) {
			return 0;
		}
		$deleted = 0;
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

	public function get_row( $query ) {
		$results = $this->get_results( $query );
		return ! empty( $results ) ? $results[0] : null;
	}

	public function get_var( $query ) {
		// Handle simple COUNT queries
		if ( false !== stripos( $query, 'COUNT(' ) ) {
			foreach ( $this->tables as $tbl => $rows ) {
				if ( false !== strpos( $query, $tbl ) ) {
					// Check where conditions
					if ( preg_match( "/reg_id\s*=\s*'([^']+)'/", $query, $m ) ) {
						$cnt = 0;
						foreach ( $rows as $r ) {
							if ( isset( $r->reg_id ) && $r->reg_id === $m[1] ) {
								$cnt++;
							}
						}
						return $cnt;
					}
					return count( $rows );
				}
			}
			return 0;
		}

		$row = $this->get_row( $query );
		if ( ! $row ) {
			return null;
		}
		$arr = (array) $row;
		return reset( $arr );
	}

	public function get_results( $query ) {
		foreach ( array( 'events', 'activities', 'registrations', 'teams', 'team_members', 'payments', 'payment_logs', 'email_templates', 'email_logs', 'audit_logs' ) as $t ) {
			$tbl_name = 'wp_eventflow_' . $t;
			if ( false !== strpos( $query, $tbl_name ) ) {
				if ( ! isset( $this->tables[ $tbl_name ] ) ) {
					$this->tables[ $tbl_name ] = array();
				}
				$rows = array_values( $this->tables[ $tbl_name ] );

				// Filter by is_active
				if ( false !== strpos( $query, 'is_active = 1' ) ) {
					$rows = array_values( array_filter( $rows, function( $r ) {
						return ! empty( $r->is_active );
					} ) );
				}

				// Filter by id
				if ( preg_match( "/(?:WHERE|\bAND)\s+r?\.?id\s*=\s*(\d+)/", $query, $m ) ) {
					$target_id = (int) $m[1];
					$rows = array_values( array_filter( $rows, function( $r ) use ( $target_id ) {
						return (int) $r->id === $target_id;
					} ) );
				}

				// Filter by activity_id
				if ( preg_match( "/(?:WHERE|\bAND)\s+activity_id\s*=\s*(\d+)/", $query, $m ) ) {
					$act_id = (int) $m[1];
					$rows = array_values( array_filter( $rows, function( $r ) use ( $act_id ) {
						return isset( $r->activity_id ) && (int) $r->activity_id === $act_id;
					} ) );
				}

				// Filter by user_id
				if ( preg_match( "/(?:WHERE|\bAND)\s+user_id\s*=\s*(\d+)/", $query, $m ) ) {
					$uid = (int) $m[1];
					$rows = array_values( array_filter( $rows, function( $r ) use ( $uid ) {
						return isset( $r->user_id ) && (int) $r->user_id === $uid;
					} ) );
				}

				// Filter application_status != 'rejected'
				if ( false !== strpos( $query, "application_status != 'rejected'" ) ) {
					$rows = array_values( array_filter( $rows, function( $r ) {
						return ! isset( $r->application_status ) || 'rejected' !== $r->application_status;
					} ) );
				}

				// Filter by reg_id
				if ( preg_match( "/(?:WHERE|\bAND)\s+r?\.?reg_id\s*=\s*'([^']+)'/", $query, $m ) ) {
					$target_reg = $m[1];
					$rows = array_values( array_filter( $rows, function( $r ) use ( $target_reg ) {
						return isset( $r->reg_id ) && $r->reg_id === $target_reg;
					} ) );
				}

				// Filter by registration_id
				if ( preg_match( "/(?:WHERE|\bAND)\s+registration_id\s*=\s*(\d+)/", $query, $m ) ) {
					$target_reg_id = (int) $m[1];
					$rows = array_values( array_filter( $rows, function( $r ) use ( $target_reg_id ) {
						return isset( $r->registration_id ) && (int) $r->registration_id === $target_reg_id;
					} ) );
				}

				// Filter by payment_id
				if ( preg_match( "/(?:WHERE|\bAND)\s+payment_id\s*=\s*(\d+)/", $query, $m ) ) {
					$target_pid = (int) $m[1];
					$rows = array_values( array_filter( $rows, function( $r ) use ( $target_pid ) {
						return isset( $r->payment_id ) && (int) $r->payment_id === $target_pid;
					} ) );
				}

				// Filter by template_key
				if ( preg_match( "/(?:WHERE|\bAND)\s+template_key\s*=\s*'([^']+)'/", $query, $m ) ) {
					$tkey = $m[1];
					$rows = array_values( array_filter( $rows, function( $r ) use ( $tkey ) {
						return isset( $r->template_key ) && $r->template_key === $tkey;
					} ) );
				}

				return $rows;
			}
		}
		return array();
	}
}

global $wpdb;
$wpdb = new WPDB_Mock_Reg();

// WordPress Mock Functions
global $mock_options;
$mock_options = array(
	'eventflow_currency'          => 'PKR',
	'eventflow_reg_id_prefix'     => 'EF',
	'eventflow_bank_enabled'      => '1',
	'eventflow_bank_name'         => 'Meezan Bank Ltd',
	'eventflow_bank_account_title'=> 'SIBACOM Society',
	'eventflow_bank_account_no'   => '01020304050607',
	'eventflow_bank_iban'         => 'PK92MEZN0001020304050607',
	'eventflow_jazzcash_enabled'  => '1',
	'eventflow_jazzcash_title'    => 'SIBACOM Official',
	'eventflow_jazzcash_no'       => '03001234567',
	'eventflow_easypaisa_enabled' => '1',
	'eventflow_easypaisa_title'   => 'SIBACOM Official',
	'eventflow_easypaisa_no'      => '03451234567',
	'eventflow_payment_instructions' => 'Please transfer the exact fee and upload screenshot with clear transaction ID.',
);

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		global $mock_options;
		return isset( $mock_options[ $name ] ) ? $mock_options[ $name ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value ) {
		global $mock_options;
		$mock_options[ $name ] = $value;
		return true;
	}
}
if ( ! function_exists( 'add_option' ) ) {
	function add_option( $name, $value ) {
		global $mock_options;
		if ( ! isset( $mock_options[ $name ] ) ) {
			$mock_options[ $name ] = $value;
		}
		return true;
	}
}
if ( ! function_exists( 'is_email' ) ) {
	function is_email( $email ) {
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		return filter_var( trim( (string) $email ), FILTER_SANITIZE_EMAIL );
	}
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}
if ( ! function_exists( 'sanitize_user' ) ) {
	function sanitize_user( $username, $strict = false ) {
		return preg_replace( '/[^a-zA-Z0-9 _.\-@]/', '', (string) $username );
	}
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		return strtolower( preg_replace( '/[^a-zA-Z0-9\-]/', '-', (string) $title ) );
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $text ) {
		return (string) $text;
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( intval( $maybeint ) );
	}
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		return 'mysql' === $type ? date( 'Y-m-d H:i:s' ) : time();
	}
}
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special_chars = true ) {
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		return substr( str_shuffle( str_repeat( $chars, 5 ) ), 0, $length );
	}
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $errors = array();
		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( ! empty( $code ) ) {
				$this->errors[ $code ][] = $message;
			}
		}
		public function get_error_message() {
			if ( empty( $this->errors ) ) return '';
			$first = reset( $this->errors );
			return is_array( $first ) ? $first[0] : (string) $first;
		}
		public function get_error_code() {
			$keys = array_keys( $this->errors );
			return ! empty( $keys ) ? $keys[0] : '';
		}
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
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
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 1;
	}
}
if ( ! function_exists( 'email_exists' ) ) {
	function email_exists( $email ) {
		return false;
	}
}
if ( ! function_exists( 'username_exists' ) ) {
	function username_exists( $username ) {
		return false;
	}
}
if ( ! function_exists( 'get_user_by' ) ) {
	function get_user_by( $field, $value ) {
		return false;
	}
}
if ( ! function_exists( 'wp_create_user' ) ) {
	function wp_create_user( $username, $password, $email ) {
		static $uid = 50;
		return ++$uid;
	}
}
if ( ! function_exists( 'wp_update_user' ) ) {
	function wp_update_user( $userdata ) {
		return isset( $userdata['ID'] ) ? $userdata['ID'] : true;
	}
}
if ( ! function_exists( 'update_user_meta' ) ) {
	function update_user_meta( $user_id, $meta_key, $meta_value ) {
		return true;
	}
}
if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $uid ) {
		return (object) array(
			'ID'           => $uid,
			'display_name' => 'Test User',
			'user_email'   => 'user@example.com',
		);
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}
if ( ! function_exists( 'wp_check_filetype_and_ext' ) ) {
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
}
if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $target ) {
		if ( file_exists( $target ) ) return true;
		return @mkdir( $target, 0777, true );
	}
}
if ( ! function_exists( 'wp_unique_filename' ) ) {
	function wp_unique_filename( $dir, $name ) {
		return time() . '_' . $name;
	}
}
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( (string) $string, '/\\' ) . '/';
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $string ) {
		return rtrim( (string) $string, '/\\' );
	}
}
if ( ! function_exists( 'wp_cache_get' ) ) {
	function wp_cache_get( $key, $group = '' ) {
		return false;
	}
}
if ( ! function_exists( 'wp_cache_set' ) ) {
	function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
		return true;
	}
}
if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		$dir = sys_get_temp_dir() . '/eventflow_test_uploads';
		if ( ! file_exists( $dir ) ) {
			@mkdir( $dir, 0777, true );
		}
		return array(
			'basedir' => $dir,
			'baseurl' => 'https://example.com/wp-content/uploads',
			'path'    => $dir,
			'url'     => 'https://example.com/wp-content/uploads',
			'error'   => false,
		);
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}
if ( ! function_exists( '_e' ) ) {
	function _e( $text, $domain = 'default' ) { echo $text; }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) { return $text; }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) { return $url; }
}
if ( ! function_exists( 'date_i18n' ) ) {
	function date_i18n( $format, $timestamp ) { return date( $format, $timestamp ); }
}
if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url = '' ) { return $url . '?' . http_build_query( $args ); }
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) { return 'https://example.com' . $path; }
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) { return 'https://example.com/wp-admin/' . $path; }
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action ) { return 'mock_nonce_' . $action; }
}
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action ) { return true; }
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = 'name' ) {
		return 'name' === $show ? 'SIBACOM 2026' : 'noreply@sibacom.iba-suk.edu.pk';
	}
}
if ( ! function_exists( 'wp_mail' ) ) {
	function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
		global $dispatched_emails;
		$dispatched_emails[] = array(
			'to'          => $to,
			'subject'     => $subject,
			'message'     => $message,
			'headers'     => $headers,
			'attachments' => $attachments,
		);
		return true;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) { return true; }
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $tag, ...$arg ) { return true; }
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) { return true; }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$var ) { return $value; }
}

// Global dispatched email spy
global $dispatched_emails;
$dispatched_emails = array();

// Require plugin core classes
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-db.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-security.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-event-manager.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-activity-manager.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-team-manager.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-payment-manager.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-payment-verification.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-roles.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-email-queue.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-email-manager.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-registration-manager.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-shortcodes.php';

// Seed initial Active Event & Test Activities
$default_event = (object) array(
	'id'          => 1,
	'title'       => 'SIBACOM 2026 Mega Tech Olympiad',
	'slug'        => 'sibacom-2026',
	'status'      => 'active',
	'is_active'   => 1,
	'currency'    => 'PKR',
	'start_date'  => date( 'Y-m-d', strtotime( '+10 days' ) ),
	'end_date'    => date( 'Y-m-d', strtotime( '+12 days' ) ),
	'reg_end_date'=> date( 'Y-m-d 23:59:59', strtotime( '+7 days' ) ),
	'reg_deadline'=> date( 'Y-m-d 23:59:59', strtotime( '+7 days' ) ),
);
$wpdb->tables['wp_eventflow_events'][1] = $default_event;

// Activity 1: Robo War (Team only, 2-4 members, PKR 1500 per team)
$act_robowar = (object) array(
	'id'                  => 10,
	'event_id'            => 1,
	'title'               => 'Robo War Championship',
	'participation_type'  => 'team',
	'min_team_size'       => 2,
	'max_team_size'       => 4,
	'reg_fee'             => 1500.00,
	'fee_calculation'     => 'per_team',
	'payment_proof_required' => 0,
	'status'              => 'open',
	'reg_deadline'        => date( 'Y-m-d 23:59:59', strtotime( '+5 days' ) ),
);
$wpdb->tables['wp_eventflow_activities'][10] = $act_robowar;

// Activity 2: Speed Programming (Both Solo & Team, 1-3 members, PKR 500 per person)
$act_prog = (object) array(
	'id'                  => 20,
	'event_id'            => 1,
	'title'               => 'Speed Programming Contest',
	'participation_type'  => 'both',
	'min_team_size'       => 1,
	'max_team_size'       => 3,
	'reg_fee'             => 500.00,
	'fee_calculation'     => 'per_person',
	'payment_proof_required' => 1,
	'status'              => 'open',
	'reg_deadline'        => date( 'Y-m-d 23:59:59', strtotime( '+5 days' ) ),
);
$wpdb->tables['wp_eventflow_activities'][20] = $act_prog;

// Activity 3: UI/UX Design Battle (Solo only, PKR 800)
$act_uiux = (object) array(
	'id'                  => 30,
	'event_id'            => 1,
	'title'               => 'UI/UX Design Battle',
	'participation_type'  => 'solo',
	'min_team_size'       => 1,
	'max_team_size'       => 1,
	'reg_fee'             => 800.00,
	'fee_calculation'     => 'per_person',
	'payment_proof_required' => 0,
	'status'              => 'open',
	'reg_deadline'        => date( 'Y-m-d 23:59:59', strtotime( '+5 days' ) ),
);
$wpdb->tables['wp_eventflow_activities'][30] = $act_uiux;

// Seed Default Email Templates for testing
foreach ( EventFlow_DB::get_default_email_templates_data() as $idx => $tpl_data ) {
	$wpdb->tables['wp_eventflow_email_templates'][ $idx + 1 ] = (object) array_merge(
		array( 'id' => $idx + 1, 'is_active' => 1 ),
		$tpl_data
	);
}

// Test Runner Framework
class EventFlow_Test_Runner {
	private $passed = 0;
	private $failed = 0;
	private $scenario_count = 0;

	public function assert( $condition, $message ) {
		if ( $condition ) {
			$this->passed++;
			echo "  [PASS] {$message}\n";
		} else {
			$this->failed++;
			echo "  [FAIL] {$message}\n";
		}
	}

	public function start_scenario( $number, $title ) {
		$this->scenario_count++;
		echo "\n=======================================================\n";
		echo "SCENARIO {$number}: {$title}\n";
		echo "=======================================================\n";
	}

	public function summary() {
		echo "\n=======================================================\n";
		echo "TEST EXECUTION SUMMARY\n";
		echo "=======================================================\n";
		echo "Total Scenarios: {$this->scenario_count}\n";
		echo "Assertions Passed: {$this->passed}\n";
		echo "Assertions Failed: {$this->failed}\n";
		if ( 0 === $this->failed ) {
			echo ">>> ALL 18 SCENARIOS PASSED WITH ZERO REGRESSIONS! <<<\n";
		} else {
			echo ">>> ATTENTION: {$this->failed} ASSERTIONS FAILED <<<\n";
		}
		echo "=======================================================\n";
		return 0 === $this->failed;
	}
}

$t = new EventFlow_Test_Runner();

// ==============================================================================
// SCENARIO 1: INDIVIDUAL / SOLO REGISTRATION
// ==============================================================================
$t->start_scenario( 1, 'Individual / Solo Registration with All 7 Required Fields' );

$solo_submission = array(
	'activity_id'        => 30,
	'participation_type' => 'solo',
	'solo_name'          => 'Zainab Fatima',
	'solo_email'         => 'zainab.fatima@iba-suk.edu.pk',
	'solo_phone'         => '03001234567',
	'solo_department'    => 'Computer Science',
	'solo_semester'      => '6',
	'solo_academic_year' => 'Batch 2023',
	'solo_university'    => 'Sukkur IBA University',
);

$res_solo = EventFlow_Registration_Manager::process_registration( 0, $solo_submission );

$t->assert( ! is_wp_error( $res_solo ), 'Solo registration successfully processed without error' );
$t->assert( isset( $res_solo['reg_id'] ) && 0 === strpos( $res_solo['reg_id'], 'EF-2026-' ), 'Unique Registration ID properly generated (EF-2026-XXXXX)' );
$t->assert( 800.00 == $res_solo['total_fee'], 'Fee calculated correctly on server for solo entry (PKR 800.00)' );
$t->assert( 'pending' === $res_solo['payment_status'], 'Initial payment status defaults to pending when no receipt submitted' );
$t->assert( 1 === $res_solo['member_count'], 'Solo registration records member count as exactly 1' );

// Verify database record has all 7 fields in team_members table
$solo_members = EventFlow_Team_Manager::get_members_by_registration( $res_solo['id'] );
$t->assert( 1 === count( $solo_members ), 'One member record stored for solo participant' );
$t->assert( 'Zainab Fatima' === $solo_members[0]->full_name, 'Full Name correctly saved' );
$t->assert( 'zainab.fatima@iba-suk.edu.pk' === $solo_members[0]->email, 'Email Address correctly saved' );
$t->assert( '03001234567' === $solo_members[0]->contact_number, 'Phone / WhatsApp correctly saved' );
$t->assert( 'Computer Science' === $solo_members[0]->department, 'Department correctly saved' );
$t->assert( '6' === $solo_members[0]->semester, 'Current Semester correctly saved' );
$t->assert( 'Batch 2023' === $solo_members[0]->academic_year, 'Academic Year / Batch correctly saved' );
$t->assert( 'Sukkur IBA University' === $solo_members[0]->university, 'University / Institution correctly saved' );


// ==============================================================================
// SCENARIO 2: TEAM REGISTRATION WITH MINIMUM MEMBERS
// ==============================================================================
$t->start_scenario( 2, 'Team Registration with Minimum Members (Robo War min=2)' );

$team_min_members = array(
	array(
		'full_name'      => 'Ali Raza',
		'email'          => 'ali.raza@iba-suk.edu.pk',
		'contact_number' => '03111111111',
		'department'     => 'Electrical Engineering',
		'semester'       => '7',
		'academic_year'  => 'Batch 2022',
		'university'     => 'Sukkur IBA University',
		'is_leader'      => 1,
	),
	array(
		'full_name'      => 'Bilal Ahmed',
		'email'          => 'bilal.ahmed@iba-suk.edu.pk',
		'contact_number' => '03112222222',
		'department'     => 'Mechanical Engineering',
		'semester'       => '5',
		'academic_year'  => 'Batch 2023',
		'university'     => 'Sukkur IBA University',
		'is_leader'      => 0,
	),
);

$team_min_data = array(
	'activity_id'        => 10,
	'participation_type' => 'team',
	'team_name'          => 'Robo Titans',
	'members'            => $team_min_members,
);

$res_team_min = EventFlow_Registration_Manager::process_registration( 0, $team_min_data );

$t->assert( ! is_wp_error( $res_team_min ), 'Team registration with minimum 2 members succeeds' );
$t->assert( 'Robo Titans' === $res_team_min['team_name'], 'Team name correctly stored' );
$t->assert( 2 === $res_team_min['member_count'], 'Total team members count equals 2' );
$t->assert( 'Ali Raza' === $res_team_min['leader_name'], 'Team leader correctly assigned as first member' );


// ==============================================================================
// SCENARIO 3: TEAM REGISTRATION WITH MAXIMUM MEMBERS
// ==============================================================================
$t->start_scenario( 3, 'Team Registration with Maximum Members (Robo War max=4)' );

$team_max_members = array(
	array(
		'full_name'      => 'Hamza Khan',
		'email'          => 'hamza.khan@iba-suk.edu.pk',
		'contact_number' => '03211111111',
		'department'     => 'CS',
		'semester'       => '8',
		'academic_year'  => 'Batch 2022',
		'university'     => 'Sukkur IBA University',
		'is_leader'      => 1,
	),
	array(
		'full_name'      => 'Member Two',
		'email'          => 'm2@iba-suk.edu.pk',
		'contact_number' => '03212222222',
		'department'     => 'CS',
		'semester'       => '8',
		'academic_year'  => 'Batch 2022',
		'university'     => 'Sukkur IBA University',
	),
	array(
		'full_name'      => 'Member Three',
		'email'          => 'm3@iba-suk.edu.pk',
		'contact_number' => '03213333333',
		'department'     => 'CS',
		'semester'       => '6',
		'academic_year'  => 'Batch 2023',
		'university'     => 'Sukkur IBA University',
	),
	array(
		'full_name'      => 'Member Four',
		'email'          => 'm4@iba-suk.edu.pk',
		'contact_number' => '03214444444',
		'department'     => 'CS',
		'semester'       => '6',
		'academic_year'  => 'Batch 2023',
		'university'     => 'Sukkur IBA University',
	),
);

$res_team_max = EventFlow_Registration_Manager::process_registration( 0, array(
	'activity_id'        => 10,
	'participation_type' => 'team',
	'team_name'          => 'Cyber Bots',
	'members'            => $team_max_members,
) );

$t->assert( ! is_wp_error( $res_team_max ), 'Team registration with maximum 4 members succeeds' );
$t->assert( 4 === $res_team_max['member_count'], 'All 4 members registered under single team record' );


// ==============================================================================
// SCENARIO 4: ADDING MULTIPLE MEMBERS DYNAMICALLY
// ==============================================================================
$t->start_scenario( 4, 'Adding Multiple Members (Validation of multi-member roster)' );

$val_3_members = EventFlow_Team_Manager::validate_team_submission( $act_robowar, 'Trio Warriors', array_slice( $team_max_members, 0, 3 ) );
$t->assert( true === $val_3_members, 'Dynamic addition of 3 members within limits (2-4) passes validation' );


// ==============================================================================
// SCENARIO 5: REMOVING MEMBERS DYNAMICALLY
// ==============================================================================
$t->start_scenario( 5, 'Removing Members Dynamically & Preserving Leader' );

// Start with 4 members, simulate removing 2 members down to minimum of 2
$roster_reduced = array_slice( $team_max_members, 0, 2 );
$val_reduced = EventFlow_Team_Manager::validate_team_submission( $act_robowar, 'Cyber Bots Reduced', $roster_reduced );
$t->assert( true === $val_reduced, 'Roster correctly validated after member removal down to minimum' );
$t->assert( $roster_reduced[0]['full_name'] === 'Hamza Khan', 'Team Leader remains strictly as first participant' );


// ==============================================================================
// SCENARIO 6: ATTEMPTING TO EXCEED MAXIMUM
// ==============================================================================
$t->start_scenario( 6, 'Attempting to Exceed Configured Maximum Team Size' );

$five_members = $team_max_members;
$five_members[] = array(
	'full_name'      => 'Member Five Overflow',
	'email'          => 'm5@iba-suk.edu.pk',
	'contact_number' => '03215555555',
	'department'     => 'CS',
	'semester'       => '4',
	'academic_year'  => 'Batch 2024',
	'university'     => 'Sukkur IBA University',
);

$val_overflow = EventFlow_Team_Manager::validate_team_submission( $act_robowar, 'Overfilled Team', $five_members );
$t->assert( is_wp_error( $val_overflow ), 'Submitting 5 members to an activity allowing max 4 returns error' );
$t->assert( 'max_team_error' === $val_overflow->get_error_code(), 'Expected error code "max_team_error" returned' );


// ==============================================================================
// SCENARIO 7: ATTEMPTING TO SUBMIT BELOW MINIMUM
// ==============================================================================
$t->start_scenario( 7, 'Attempting to Submit Below Configured Minimum Team Size' );

$one_member_only = array_slice( $team_max_members, 0, 1 );
$val_underflow = EventFlow_Team_Manager::validate_team_submission( $act_robowar, 'Underfilled Team', $one_member_only );
$t->assert( is_wp_error( $val_underflow ), 'Submitting 1 member to an activity requiring min 2 returns error' );
$t->assert( 'min_team_error' === $val_underflow->get_error_code(), 'Expected error code "min_team_error" returned' );


// ==============================================================================
// SCENARIO 8: INVALID EMAIL AND PHONE NUMBERS
// ==============================================================================
$t->start_scenario( 8, 'Invalid Email and Missing Required Fields Validation' );

$invalid_email_roster = $team_min_members;
$invalid_email_roster[1]['email'] = 'not-a-valid-email';

$val_bad_email = EventFlow_Team_Manager::validate_team_submission( $act_robowar, 'Bad Email Team', $invalid_email_roster );
$t->assert( is_wp_error( $val_bad_email ), 'Invalid email address rejected' );
$t->assert( 'invalid_email' === $val_bad_email->get_error_code(), 'Expected error code "invalid_email" returned' );

$missing_phone_roster = $team_min_members;
$missing_phone_roster[1]['contact_number'] = '';
$val_bad_phone = EventFlow_Team_Manager::validate_team_submission( $act_robowar, 'Missing Phone Team', $missing_phone_roster );
$t->assert( is_wp_error( $val_bad_phone ), 'Missing phone number rejected' );
$t->assert( 'missing_field' === $val_bad_phone->get_error_code(), 'Expected error code "missing_field" returned' );


// ==============================================================================
// SCENARIO 9: DUPLICATE TEAM MEMBER INFORMATION
// ==============================================================================
$t->start_scenario( 9, 'Intra-Team Duplicate Information Detection (Email & Phone)' );

$dup_email_roster = $team_min_members;
$dup_email_roster[1]['email'] = $dup_email_roster[0]['email']; // Duplicate email!

$val_dup_email = EventFlow_Team_Manager::validate_team_submission( $act_robowar, 'Duplicate Email Team', $dup_email_roster );
$t->assert( is_wp_error( $val_dup_email ), 'Duplicate email within same team rejected' );
$t->assert( 'duplicate_member_email' === $val_dup_email->get_error_code(), 'Expected error code "duplicate_member_email" returned' );

$dup_phone_roster = $team_min_members;
$dup_phone_roster[1]['contact_number'] = $dup_phone_roster[0]['contact_number']; // Duplicate phone!

$val_dup_phone = EventFlow_Team_Manager::validate_team_submission( $act_robowar, 'Duplicate Phone Team', $dup_phone_roster );
$t->assert( is_wp_error( $val_dup_phone ), 'Duplicate phone within same team rejected' );
$t->assert( 'duplicate_member_phone' === $val_dup_phone->get_error_code(), 'Expected error code "duplicate_member_phone" returned' );


// ==============================================================================
// SCENARIO 10: PER-TEAM FEE CALCULATION
// ==============================================================================
$t->start_scenario( 10, 'Per-Team Registration Fee Calculation (Flat team fee)' );

// Robo War is fee_calculation = 'per_team', reg_fee = 1500
$fee_team_2 = EventFlow_Activity_Manager::calculate_fee( $act_robowar, 'team', 2 );
$fee_team_4 = EventFlow_Activity_Manager::calculate_fee( $act_robowar, 'team', 4 );

$t->assert( 1500.00 === $fee_team_2, 'Fee for 2 members under Per-Team model is flat PKR 1,500.00' );
$t->assert( 1500.00 === $fee_team_4, 'Fee for 4 members under Per-Team model remains flat PKR 1,500.00' );


// ==============================================================================
// SCENARIO 11: PER-PERSON FEE CALCULATION
// ==============================================================================
$t->start_scenario( 11, 'Per-Person Registration Fee Calculation (Multiplied by member count)' );

// Speed Programming is fee_calculation = 'per_person', reg_fee = 500
$fee_person_1 = EventFlow_Activity_Manager::calculate_fee( $act_prog, 'solo', 1 );
$fee_person_2 = EventFlow_Activity_Manager::calculate_fee( $act_prog, 'team', 2 );
$fee_person_3 = EventFlow_Activity_Manager::calculate_fee( $act_prog, 'team', 3 );

$t->assert( 500.00 === $fee_person_1, 'Solo fee is 1 x 500 = PKR 500.00' );
$t->assert( 1000.00 === $fee_person_2, 'Team of 2 fee is 2 x 500 = PKR 1,000.00' );
$t->assert( 1500.00 === $fee_person_3, 'Team of 3 fee is 3 x 500 = PKR 1,500.00' );


// ==============================================================================
// SCENARIO 12: PAYMENT ACCOUNT INFORMATION DISPLAY
// ==============================================================================
$t->start_scenario( 12, 'Payment Account Information Display & Enabled Methods' );

$enabled_methods = EventFlow_Payment_Manager::get_enabled_payment_methods();

$t->assert( isset( $enabled_methods['bank'] ), 'Bank Transfer is configured and enabled' );
$t->assert( 'Meezan Bank Ltd' === $enabled_methods['bank']['bank_name'], 'Bank name matches configured setting' );
$t->assert( 'PK92MEZN0001020304050607' === $enabled_methods['bank']['iban'], 'IBAN matches configured setting' );
$t->assert( isset( $enabled_methods['jazzcash'] ), 'JazzCash is configured and enabled' );
$t->assert( '03001234567' === $enabled_methods['jazzcash']['mobile_no'], 'JazzCash account number matches' );
$t->assert( isset( $enabled_methods['easypaisa'] ), 'Easypaisa is configured and enabled' );
$t->assert( '03451234567' === $enabled_methods['easypaisa']['mobile_no'], 'Easypaisa account number matches' );

// Test disabling a method
update_option( 'eventflow_easypaisa_enabled', '0' );
$methods_without_ep = EventFlow_Payment_Manager::get_enabled_payment_methods();
$t->assert( ! isset( $methods_without_ep['easypaisa'] ), 'Disabled payment method is excluded from frontend display' );
update_option( 'eventflow_easypaisa_enabled', '1' ); // Restore


// ==============================================================================
// SCENARIO 13: VALID AND INVALID PAYMENT RECEIPT UPLOADS
// ==============================================================================
$t->start_scenario( 13, 'Valid & Invalid Payment Receipt Uploads (MIME types & File Sizes)' );

// Create temporary dummy receipt files
$tmp_dir = sys_get_temp_dir() . '/eventflow_test_receipts';
if ( ! file_exists( $tmp_dir ) ) {
	@mkdir( $tmp_dir, 0777, true );
}

$valid_png = $tmp_dir . '/receipt.png';
file_put_contents( $valid_png, "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR" ); // Valid PNG signature

$valid_pdf = $tmp_dir . '/receipt.pdf';
file_put_contents( $valid_pdf, "%PDF-1.4 sample pdf content for receipt" ); // Valid PDF signature

$invalid_exe = $tmp_dir . '/malicious.exe';
file_put_contents( $invalid_exe, "MZ executable file content" );

// Test Valid PNG Upload
$file_array_png = array(
	'name'     => 'receipt.png',
	'type'     => 'image/png',
	'tmp_name' => $valid_png,
	'error'    => UPLOAD_ERR_OK,
	'size'     => 1024 * 200, // 200 KB
);
$up_png = EventFlow_Payment_Manager::handle_file_upload( $file_array_png );
$t->assert( ! is_wp_error( $up_png ), 'Valid PNG receipt is accepted and securely stored' );
$t->assert( file_exists( $up_png['path'] ), 'Stored receipt file exists on disk' );

// Test Valid PDF Upload
$file_array_pdf = array(
	'name'     => 'statement.pdf',
	'type'     => 'application/pdf',
	'tmp_name' => $valid_pdf,
	'error'    => UPLOAD_ERR_OK,
	'size'     => 1024 * 500, // 500 KB
);
$up_pdf = EventFlow_Payment_Manager::handle_file_upload( $file_array_pdf );
$t->assert( ! is_wp_error( $up_pdf ), 'Valid PDF receipt is accepted' );

// Test Disallowed File Type (.exe)
$file_array_exe = array(
	'name'     => 'exploit.exe',
	'type'     => 'application/x-msdownload',
	'tmp_name' => $invalid_exe,
	'error'    => UPLOAD_ERR_OK,
	'size'     => 1024 * 50,
);
$up_exe = EventFlow_Payment_Manager::handle_file_upload( $file_array_exe );
$t->assert( is_wp_error( $up_exe ), 'Disallowed executable file upload is strictly rejected' );
$t->assert( 'invalid_file_type' === $up_exe->get_error_code(), 'Expected error code "invalid_file_type" returned' );

// Test Oversized File (> 5MB)
$file_array_oversize = array(
	'name'     => 'huge.png',
	'type'     => 'image/png',
	'tmp_name' => $valid_png,
	'error'    => UPLOAD_ERR_OK,
	'size'     => 1024 * 1024 * 10, // 10 MB
);
$up_oversize = EventFlow_Payment_Manager::handle_file_upload( $file_array_oversize );
$t->assert( is_wp_error( $up_oversize ), 'Oversized file exceeding 5MB limit is rejected' );
$t->assert( 'file_too_large' === $up_oversize->get_error_code(), 'Expected error code "file_too_large" returned' );


// ==============================================================================
// SCENARIO 14: PAYMENT VERIFICATION BY ADMIN
// ==============================================================================
$t->start_scenario( 14, 'Payment Verification & Slot Confirmation by Authorized Admin' );

// Create registration with receipt submission
$sub_with_receipt = array(
	'activity_id'        => 20,
	'participation_type' => 'solo',
	'solo_name'          => 'Tariq Mehmood',
	'solo_email'         => 'tariq.mehmood@iba-suk.edu.pk',
	'solo_phone'         => '03009988776',
	'solo_department'    => 'Mathematics',
	'solo_semester'      => '4',
	'solo_academic_year' => 'Batch 2024',
	'solo_university'    => 'Sukkur IBA University',
	'payment_method'     => 'JazzCash',
	'transaction_id'     => 'JC-88219901',
	'payment_date'       => date( 'Y-m-d' ),
	'amount_paid'        => '500',
);

$files_with_receipt = array(
	'payment_screenshot' => $file_array_png,
);

$res_ver = EventFlow_Registration_Manager::process_registration( 0, $sub_with_receipt, $files_with_receipt );
$t->assert( ! is_wp_error( $res_ver ), 'Registration with receipt submitted successfully' );
$t->assert( 'under_verification' === $res_ver['payment_status'], 'Initial payment status is set to "under_verification" (Pending Verification)' );
$t->assert( ! empty( $res_ver['payment_id'] ), 'Payment row created with ID' );

// Admin verifies payment
$ver_result = EventFlow_Payment_Verification::verify_payment( $res_ver['payment_id'], 1, 'Verified against Meezan bank statement.' );
$t->assert( true === $ver_result, 'Admin successfully verifies payment' );

// Confirm updated database records
$updated_payment = $wpdb->tables['wp_eventflow_payments'][ $res_ver['payment_id'] ];
$updated_reg     = $wpdb->tables['wp_eventflow_registrations'][ $res_ver['id'] ];

$t->assert( 'verified' === $updated_payment->status, 'Payment record status updated to "verified"' );
$t->assert( 'verified' === $updated_reg->payment_status, 'Registration payment_status updated to "verified"' );
$t->assert( 'approved' === $updated_reg->application_status, 'Registration application_status confirmed/approved' );

// Confirm payment log entry created
$logs = $wpdb->tables['wp_eventflow_payment_logs'];
$found_log = false;
foreach ( $logs as $l ) {
	if ( $l->payment_id === $res_ver['payment_id'] && 'verified' === $l->action ) {
		$found_log = true;
		break;
	}
}
$t->assert( $found_log, 'Verification action logged in payment audit log' );

// Registration without transaction_id (as removed from registration form)
$sub_no_tid = array(
	'activity_id'        => 20,
	'participation_type' => 'solo',
	'solo_name'          => 'Zeeshan Ali',
	'solo_email'         => 'zeeshan.ali@iba-suk.edu.pk',
	'solo_phone'         => '03001122334',
	'solo_department'    => 'Computer Science',
	'solo_semester'      => '5',
	'solo_academic_year' => 'Batch 2023',
	'solo_university'    => 'Sukkur IBA University',
	'payment_method'     => 'Bank Transfer',
	'payment_date'       => date( 'Y-m-d' ),
	'amount_paid'        => '500',
);
$res_no_tid = EventFlow_Registration_Manager::process_registration( 0, $sub_no_tid, $files_with_receipt );
$t->assert( ! is_wp_error( $res_no_tid ), 'Registration without manual transaction_id submitted successfully' );
$payment_no_tid = $wpdb->tables['wp_eventflow_payments'][ $res_no_tid['payment_id'] ];
$t->assert( ! empty( $payment_no_tid->transaction_id ) && strpos( $payment_no_tid->transaction_id, 'TXN-' ) === 0, 'Auto-generated transaction_id created with TXN- prefix' );


// ==============================================================================
// SCENARIO 15: PAYMENT REJECTION AND REPLACEMENT RECEIPT
// ==============================================================================
$t->start_scenario( 15, 'Payment Rejection, Reason Recording, and Receipt Replacement' );

// Create another registration to reject
$res_to_reject = EventFlow_Registration_Manager::process_registration( 2, $sub_with_receipt, $files_with_receipt );
$rej_pid = $res_to_reject['payment_id'];

// Reject with required reason
$rej_result = EventFlow_Payment_Verification::reject_payment( $rej_pid, 1, 'Transaction ID not matching bank transaction records.' );
$t->assert( true === $rej_result, 'Payment rejection processed successfully' );

$rejected_payment = $wpdb->tables['wp_eventflow_payments'][ $rej_pid ];
$rejected_reg     = $wpdb->tables['wp_eventflow_registrations'][ $res_to_reject['id'] ];

$t->assert( 'rejected' === $rejected_payment->status, 'Payment status updated to "rejected"' );
$t->assert( 'Transaction ID not matching bank transaction records.' === $rejected_payment->rejection_reason, 'Rejection reason saved' );
$t->assert( 'rejected' === $rejected_reg->payment_status, 'Registration payment status marked as "rejected"' );

// Participant submits replacement receipt
$replacement_submission = array(
	'registration_id' => $res_to_reject['id'],
	'payment_method'  => 'JazzCash',
	'transaction_id'  => 'JC-99999999-CORRECTED',
	'amount'          => 500.00,
	'payment_date'    => date( 'Y-m-d' ),
);
$res_replace = EventFlow_Payment_Manager::process_submission( 2, $replacement_submission, $files_with_receipt );
if ( is_wp_error( $res_replace ) ) {
	echo "ERROR IN SCENARIO 15: " . $res_replace->get_error_message() . " (Code: " . $res_replace->get_error_code() . ")\n";
	exit( 1 );
}
$t->assert( ! is_wp_error( $res_replace ), 'Replacement receipt accepted' );
$t->assert( 'under_verification' === $res_replace['payment_status'], 'Status reset to "under_verification" awaiting re-review' );


// ==============================================================================
// SCENARIO 16: REGISTRATION CONFIRMATION EMAILS
// ==============================================================================
$t->start_scenario( 16, 'Registration Confirmation & Notification Emails Integration' );

// Test Email Templates exist in system
$tpl_reg_sub = EventFlow_Email_Manager::get_template( 'registration_submitted' );
$tpl_ver     = EventFlow_Email_Manager::get_template( 'payment_verified' );
$tpl_rej     = EventFlow_Email_Manager::get_template( 'payment_rejected' );
$tpl_reup    = EventFlow_Email_Manager::get_template( 'payment_reupload_requested' );

$t->assert( ! empty( $tpl_reg_sub ), 'Email template "registration_submitted" exists' );
$t->assert( ! empty( $tpl_ver ), 'Email template "payment_verified" exists' );
$t->assert( ! empty( $tpl_rej ), 'Email template "payment_rejected" exists' );
$t->assert( ! empty( $tpl_reup ), 'Email template "payment_reupload_requested" exists' );

// Test Template Merge Tags Hydration
$hydrated = EventFlow_Email_Manager::prepare_message(
	$tpl_reg_sub,
	'ahmad@iba-suk.edu.pk',
	array(
		'participant_name' => 'Ahmad Bilal',
		'registration_id'  => 'EF-2026-TEST1',
		'activity_name'    => 'Robo War Championship',
		'payment_amount'   => 'PKR 1,500.00',
	)
);
$t->assert( false !== strpos( $hydrated['subject'], 'EF-2026-TEST1' ) || false !== strpos( $hydrated['body'], 'EF-2026-TEST1' ), 'Registration ID placeholder merged successfully into subject or body' );
$t->assert( false !== strpos( $hydrated['body'], 'Ahmad Bilal' ), 'Participant Name placeholder merged successfully into email copy' );


// ==============================================================================
// SCENARIO 17: MOBILE RESPONSIVENESS AND CSS CLASSES
// ==============================================================================
$t->start_scenario( 17, 'Frontend UI/UX, CSS Components, and Mobile Breakpoints' );

$css_file = EVENTFLOW_PLUGIN_DIR . 'assets/css/eventflow-frontend.css';
$t->assert( file_exists( $css_file ), 'Frontend CSS file exists' );

$css_content = file_get_contents( $css_file );

$t->assert( false !== strpos( $css_content, '.ef-step-section' ), 'CSS contains .ef-step-section for 10-step form structure' );
$t->assert( false !== strpos( $css_content, '.ef-member-card' ), 'CSS contains .ef-member-card for dynamic participant cards' );
$t->assert( false !== strpos( $css_content, '.ef-payment-summary-strip' ), 'CSS contains .ef-payment-summary-strip for live fee calculation' );
$t->assert( false !== strpos( $css_content, '.ef-accounts-grid' ), 'CSS contains .ef-accounts-grid for professional payment method cards' );
$t->assert( false !== strpos( $css_content, '.ef-dropzone' ), 'CSS contains .ef-dropzone for drag-and-drop receipt upload' );
$t->assert( false !== strpos( $css_content, '@media (max-width: 768px)' ), 'CSS contains mobile responsive breakpoint @media (max-width: 768px)' );


// ==============================================================================
// SCENARIO 18: EXISTING REGISTRATION RECORDS AND BACKWARD COMPATIBILITY
// ==============================================================================
$t->start_scenario( 18, 'Existing Registration Records & Backward Compatibility' );

// Legacy activity record missing fee_calculation and payment_proof_required (simulating pre-existing DB row)
$legacy_activity = (object) array(
	'id'                 => 99,
	'event_id'           => 1,
	'title'              => 'Legacy Web Design Contest',
	'participation_type' => 'team',
	'min_team_size'      => 2,
	'max_team_size'      => 4,
	'reg_fee'            => 1000.00,
	// fee_calculation and payment_proof_required are intentionally omitted (undefined)
);

// calculate_fee should gracefully default to per_team without warnings
$legacy_fee = EventFlow_Activity_Manager::calculate_fee( $legacy_activity, 'team', 3 );
$t->assert( 1000.00 === $legacy_fee, 'Legacy activity missing fee_calculation safely defaults to flat per_team fee' );

// Format for frontend handles legacy fields
$formatted_legacy = EventFlow_Activity_Manager::format_activity_for_frontend( $legacy_activity );
$t->assert( 'per_team' === $formatted_legacy['fee_calculation'], 'Frontend formatter safely falls back to per_team for legacy rows' );
$t->assert( 1 === $formatted_legacy['payment_proof_required'], 'Frontend formatter safely defaults payment_proof_required to 1' );

// Status badge helper handles legacy status strings
$badge_submitted = EventFlow_Registration_Manager::get_status_badge( 'submitted', 'app' );
$badge_verified  = EventFlow_Registration_Manager::get_status_badge( 'verified', 'payment' );
$badge_unknown   = EventFlow_Registration_Manager::get_status_badge( 'custom_legacy_status', 'app' );

$t->assert( false !== strpos( $badge_submitted, 'Submitted' ), 'Status badge renders "Submitted" correctly' );
$t->assert( false !== strpos( $badge_verified, 'Verified' ), 'Status badge renders "Verified" correctly' );
$t->assert( false !== strpos( $badge_unknown, 'Custom Legacy Status' ), 'Status badge handles unknown legacy statuses gracefully without failure' );

// Run Summary
$all_passed = $t->summary();
exit( $all_passed ? 0 : 1 );
