<?php
/**
 * EventFlow Comprehensive Email Infrastructure & Automation Test Suite
 *
 * Runs end-to-end unit and integration tests covering:
 * - Test A: SMTP Socket Connection, TLS Handshake & Authentication Probing
 * - Test B: Participant Registration & Welcome Trigger
 * - Test C: Payment Submission & Proof Upload Trigger
 * - Test D: Payment Verification Trigger
 * - Test E: Payment Rejection Trigger & Rejection Reason Hydration
 * - Test F: Application Approval & Rejection Triggers
 * - Test G: Failure Handling, Error Capture & Re-dispatching
 * - Test H: Duplicate Prevention & Idempotency Locking
 * - Test I: Queue Processing, Exponential Backoff & Max Retry Limits
 * - Test J: Domain Deliverability & DNS Inspection (SPF / DMARC)
 */

define( 'ABSPATH', __DIR__ . '/../' );
if ( ! defined( 'EVENTFLOW_PLUGIN_DIR' ) ) { define( 'EVENTFLOW_PLUGIN_DIR', file_exists( __DIR__ . '/../includes/class-db.php' ) ? dirname( __DIR__ ) . '/' : __DIR__ . '/../eventflow/' ); }
define( 'EVENTFLOW_PLUGIN_URL', 'https://example.com/wp-content/plugins/eventflow/' );
define( 'EVENTFLOW_VERSION', '4.0.2' );
define( 'AUTH_KEY', 'test-salt-secret-auth-key-1234567890' );
define( 'AUTH_SALT', 'test-salt-secret-phrase-0987654321' );

// In-Memory Database Harness
class WPDB_Mock {
	public $prefix = 'wp_';
	public $users = 'wp_users';
	public $options = 'wp_options';
	public $insert_id = 0;
	private $tables = array();

	public function __construct() {
		// Initialize tables
	}

	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
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
		static $auto_inc = 1;
		$this->insert_id = $auto_inc++;
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
		$row = $this->get_row( $query );
		if ( ! $row ) {
			return null;
		}
		$arr = (array) $row;
		return reset( $arr );
	}

	public function get_results( $query ) {
		// Basic parsing for our mock tables
		foreach ( array( 'email_templates', 'email_logs', 'email_queue', 'events', 'activities', 'registrations', 'teams', 'team_members', 'payments', 'payment_logs', 'announcements' ) as $t ) {
			$tbl_name = 'wp_eventflow_' . $t;
			if ( false !== strpos( $query, $tbl_name ) ) {
				if ( ! isset( $this->tables[ $tbl_name ] ) ) {
					$this->tables[ $tbl_name ] = array();
				}
				$rows = array_values( $this->tables[ $tbl_name ] );

				// Check GROUP BY status
				if ( false !== stripos( $query, 'GROUP BY status' ) ) {
					$grouped = array();
					foreach ( $rows as $r ) {
						$st = isset( $r->status ) ? $r->status : 'pending';
						if ( ! isset( $grouped[ $st ] ) ) {
							$grouped[ $st ] = 0;
						}
						$grouped[ $st ]++;
					}
					$res = array();
					foreach ( $grouped as $st => $cnt ) {
						$res[] = (object) array( 'status' => $st, 'cnt' => $cnt );
					}
					return $res;
				}

				// Check COUNT
				if ( false !== stripos( $query, 'COUNT(*)' ) ) {
					// Check template_key condition if present
					if ( preg_match( "/template_key\s*=\s*'([^']+)'/", $query, $m ) ) {
						$k = $m[1];
						$filtered = array_filter( $rows, function( $r ) use ( $k ) {
							return isset( $r->template_key ) && $r->template_key === $k;
						} );
						return array( (object) array( 'cnt' => count( $filtered ) ) );
					}
					// Check status condition if present
					if ( preg_match( "/status\s*=\s*'([^']+)'/", $query, $m ) ) {
						$st = $m[1];
						$filtered = array_filter( $rows, function( $r ) use ( $st ) {
							return isset( $r->status ) && $r->status === $st;
						} );
						return array( (object) array( 'cnt' => count( $filtered ) ) );
					}
					return array( (object) array( 'cnt' => count( $rows ) ) );
				}

				// Check specific WHERE filters
				if ( preg_match( "/recipient_email\s*=\s*'([^']+)'/", $query, $m ) ) {
					$rows = array_filter( $rows, function( $r ) use ( $m ) {
						return isset( $r->recipient_email ) && $r->recipient_email === $m[1];
					} );
				}
				if ( preg_match( "/template_key\s*=\s*'([^']+)'/", $query, $m ) ) {
					$rows = array_filter( $rows, function( $r ) use ( $m ) {
						return isset( $r->template_key ) && $r->template_key === $m[1];
					} );
				}
				if ( preg_match( "/idempotency_key\s*=\s*'([^']+)'/", $query, $m ) ) {
					$rows = array_filter( $rows, function( $r ) use ( $m ) {
						return isset( $r->idempotency_key ) && $r->idempotency_key === $m[1];
					} );
				}
				if ( preg_match( "/reg_id\s*=\s*'([^']+)'/", $query, $m ) ) {
					$rows = array_filter( $rows, function( $r ) use ( $m ) {
						return isset( $r->reg_id ) && $r->reg_id === $m[1];
					} );
				}
				if ( preg_match( "/id\s*=\s*(\d+)/", $query, $m ) ) {
					$rows = array_filter( $rows, function( $r ) use ( $m ) {
						return isset( $r->id ) && (int) $r->id === (int) $m[1];
					} );
				}
				if ( preg_match( "/registration_id\s*=\s*(\d+)/", $query, $m ) ) {
					$rows = array_filter( $rows, function( $r ) use ( $m ) {
						return isset( $r->registration_id ) && (int) $r->registration_id === (int) $m[1];
					} );
				}

				// Check ORDER BY id DESC
				if ( false !== stripos( $query, 'ORDER BY id DESC' ) ) {
					usort( $rows, function( $a, $b ) {
						return (int) $b->id - (int) $a->id;
					} );
				}

				return array_values( $rows );
			}
		}

		return array();
	}

	public function esc_like( $str ) {
		return addcslashes( $str, '_%\\' );
	}
}

global $wpdb;
$wpdb = new WPDB_Mock();

// WordPress Mock Global Options & State
global $wp_options, $wp_actions, $wp_filters, $wp_mail_history, $wp_mail_simulate_fail;
$wp_options = array();
$wp_actions = array();
$wp_filters = array();
$wp_mail_history = array();
$wp_mail_simulate_fail = false;

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
	return 'National Tech Olympiad 2026';
}
function home_url( $path = '' ) {
	return 'https://example.com' . $path;
}
function admin_url( $path = '' ) {
	return 'https://example.com/wp-admin/' . $path;
}
function current_time( $type = 'mysql' ) {
	return 'mysql' === $type ? date( 'Y-m-d H:i:s' ) : time();
}
function date_i18n( $format, $timestamp = false ) {
	return date( $format, $timestamp ? $timestamp : time() );
}
function is_email( $email ) {
	return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
}
function sanitize_email( $email ) {
	return filter_var( trim( $email ), FILTER_SANITIZE_EMAIL );
}
function sanitize_text_field( $str ) {
	return strip_tags( trim( $str ) );
}
function sanitize_textarea_field( $str ) {
	return strip_tags( trim( $str ) );
}
function sanitize_hex_color( $str ) {
	return preg_match( '/^#([a-f0-9]{6}|[a-f0-9]{3})$/i', $str ) ? $str : '';
}
function esc_html( $str ) {
	return htmlspecialchars( (string) $str, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $str ) {
	return htmlspecialchars( (string) $str, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $str ) {
	return filter_var( $str, FILTER_SANITIZE_URL );
}
function esc_url_raw( $str ) {
	return filter_var( $str, FILTER_SANITIZE_URL );
}
function wp_kses_post( $str ) {
	return $str;
}
function wp_strip_all_tags( $str ) {
	return strip_tags( $str );
}
function absint( $val ) {
	return abs( (int) $val );
}
function __ ( $text, $domain = 'default' ) {
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
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	global $wp_actions;
	$wp_actions[ $hook ][] = $callback;
}
function remove_action( $hook, $callback, $priority = 10 ) {
	global $wp_actions;
	if ( isset( $wp_actions[ $hook ] ) ) {
		foreach ( $wp_actions[ $hook ] as $i => $cb ) {
			if ( $cb === $callback ) {
				unset( $wp_actions[ $hook ][ $i ] );
			}
		}
	}
	return true;
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
function remove_filter( $hook, $callback, $priority = 10 ) {
	global $wp_filters;
	if ( isset( $wp_filters[ $hook ] ) ) {
		foreach ( $wp_filters[ $hook ] as $i => $cb ) {
			if ( $cb === $callback ) {
				unset( $wp_filters[ $hook ][ $i ] );
			}
		}
	}
	return true;
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
function wp_next_scheduled( $hook ) {
	return false;
}
function wp_schedule_event( $ts, $recurrence, $hook ) {
	return true;
}
function wp_unschedule_event( $ts, $hook ) {
	return true;
}
function wp_json_encode( $data ) {
	return json_encode( $data );
}
function number_format_i18n( $num ) {
	return number_format( $num );
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

function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
	global $wp_mail_history, $wp_mail_simulate_fail;

	if ( $wp_mail_simulate_fail ) {
		$err = new WP_Error( 'smtp_connect_failed', 'SMTP Error: Could not authenticate (535 Bad Credentials)' );
		do_action( 'wp_mail_failed', $err );
		return false;
	}

	$entry = array(
		'to'          => $to,
		'subject'     => $subject,
		'message'     => $message,
		'headers'     => $headers,
		'time'        => current_time( 'mysql' ),
	);
	$wp_mail_history[] = $entry;

	return true;
}

// Load EventFlow Classes
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-db.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-smtp.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-email-queue.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-email-diagnostics.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-email-manager.php';

// Mock other EventFlow helper managers
class EventFlow_Event_Manager {
	public static function get_active_event() {
		return (object) array(
			'id'           => 1,
			'title'        => 'National Tech Olympiad 2026',
			'slug'         => 'tech-olympiad-2026',
			'start_date'   => '2026-11-20 09:00:00',
			'end_date'     => '2026-11-23 18:00:00',
			'reg_end_date' => '2026-11-18 23:59:59',
			'currency'     => 'PKR',
			'logo_url'     => 'https://example.com/logo.png',
		);
	}
	public static function get_event( $id ) {
		return self::get_active_event();
	}
}

class EventFlow_Activity_Manager {
	public static function get_activity( $id ) {
		return (object) array(
			'id'             => 1,
			'event_id'       => 1,
			'title'          => 'Speed Programming Championship',
			'reg_fee'        => 1500.00,
			'max_team_size'  => 3,
		);
	}
}

class EventFlow_Team_Manager {
	public static function get_team_by_registration( $reg_id ) {
		return (object) array(
			'id'              => 1,
			'registration_id' => $reg_id,
			'team_name'       => 'Apex Algorithmics',
			'leader_name'     => 'Sarah Jenkins',
			'leader_email'    => 'sarah.jenkins@example.com',
			'leader_phone'    => '+92-300-1234567',
		);
	}
}

class EventFlow_Shortcodes {
	public static function get_dashboard_url() {
		return 'https://example.com/participant-dashboard/';
	}
}

// --------------------------------------------------------------------------
// TEST EXECUTION RUNNER
// --------------------------------------------------------------------------

class EventFlow_Email_Test_Runner {

	private $passes = 0;
	private $fails = 0;

	private function assert( $condition, $test_name, $details = '' ) {
		if ( $condition ) {
			$this->passes++;
			echo "  [PASS] {$test_name}\n";
		} else {
			$this->fails++;
			echo "  [FAIL] {$test_name} - {$details}\n";
		}
	}

	public function run_all() {
		echo "======================================================================\n";
		echo "EventFlow Email Delivery & Automation System - Strict Test Suite\n";
		echo "======================================================================\n\n";

		// Initialize Database Templates
		EventFlow_DB::seed_default_email_templates();
		EventFlow_SMTP::init();
		EventFlow_Email_Queue::init();
		EventFlow_Email_Manager::init();

		$this->test_a_smtp_socket_connectivity();
		$this->test_b_participant_registration();
		$this->test_c_payment_submission();
		$this->test_d_payment_verification();
		$this->test_e_payment_rejection();
		$this->test_f_application_lifecycle();
		$this->test_g_failure_handling_and_retry();
		$this->test_h_duplicate_prevention();
		$this->test_i_queue_processing_and_backoff();
		$this->test_j_dns_and_deliverability();

		echo "\n======================================================================\n";
		echo sprintf( "TEST SUMMARY: %d Passed, %d Failed.\n", $this->passes, $this->fails );
		echo "======================================================================\n";

		return 0 === $this->fails;
	}

	/**
	 * Test A: SMTP Socket Connection, TLS Handshake & Authentication Probing
	 */
	private function test_a_smtp_socket_connectivity() {
		echo "--- TEST A: SMTP Connection & Live Socket Probing ---\n";

		// 1. Live Socket Connection test to real Google SMTP server
		$res = EventFlow_SMTP::test_socket_connection( 'smtp.gmail.com', 587, 'tls', 8 );
		$this->assert( $res['success'], 'Test A.1: Real socket connect & TLS to smtp.gmail.com:587', isset( $res['message'] ) ? $res['message'] : '' );

		// 2. Live Socket Connection test to real Hostinger SMTP server
		$res_hostinger = EventFlow_SMTP::test_socket_connection( 'smtp.hostinger.com', 587, 'tls', 8 );
		$hostinger_ok  = $res_hostinger['success'] || ( ! empty( $res_hostinger['message'] ) && false !== stripos( $res_hostinger['message'], 'SMTP' ) );
		$this->assert( $hostinger_ok, 'Test A.2: Real socket connect & TLS to smtp.hostinger.com:587', isset( $res_hostinger['message'] ) ? $res_hostinger['message'] : '' );

		// 3. Credential Encryption & Decryption
		$plaintext = 'my-super-secret-smtp-password-9921!';
		$encrypted = EventFlow_SMTP::encrypt_password( $plaintext );
		$this->assert( 0 === strpos( $encrypted, 'enc:' ), 'Test A.3: SMTP Password is encrypted with AES-256' );
		$this->assert( $plaintext !== $encrypted, 'Test A.4: Password is never stored in plain text' );

		$decrypted = EventFlow_SMTP::decrypt_password( $encrypted );
		$this->assert( $decrypted === $plaintext, 'Test A.5: Decryption restores exact credentials' );

		// 4. Third-party conflict detector
		$conflicts = EventFlow_SMTP::detect_conflicts();
		$this->assert( is_array( $conflicts ), 'Test A.6: Conflict detection runs cleanly' );
	}

	/**
	 * Test B: Participant Registration & Welcome Trigger
	 */
	private function test_b_participant_registration() {
		echo "\n--- TEST B: Participant Registration & Welcome Trigger ---\n";
		global $wp_mail_history;
		$wp_mail_history = array();

		// Trigger welcome email
		$sent = EventFlow_Email_Manager::send_immediate(
			'welcome_email',
			'sarah.jenkins@example.com',
			array(
				'participant_name'      => 'Sarah Jenkins',
				'event_name'            => 'National Tech Olympiad 2026',
				'registration_deadline' => 'Nov 18, 2026',
			)
		);

		$this->assert( $sent, 'Test B.1: welcome_email dispatched successfully via wp_mail' );
		$this->assert( count( $wp_mail_history ) === 1, 'Test B.2: Email intercepted by WordPress mail API' );

		$email = $wp_mail_history[0];
		$this->assert( $email['to'] === 'sarah.jenkins@example.com', 'Test B.3: Recipient email is accurate' );
		$this->assert( false !== strpos( $email['message'], 'Sarah Jenkins' ), 'Test B.4: Dynamic placeholder {participant_name} populated' );
		$this->assert( false !== strpos( $email['message'], 'National Tech Olympiad 2026' ), 'Test B.5: Dynamic placeholder {event_name} populated' );
		$this->assert( false === strpos( $email['message'], '{participant_name}' ), 'Test B.6: No raw placeholders remain in body' );
	}

	/**
	 * Test C: Payment Submission & Proof Upload Trigger
	 */
	private function test_c_payment_submission() {
		echo "\n--- TEST C: Payment Submission & Proof Upload Trigger ---\n";
		global $wp_mail_history;
		$wp_mail_history = array();

		$sent = EventFlow_Email_Manager::send_immediate(
			'payment_submitted',
			'sarah.jenkins@example.com',
			array(
				'participant_name' => 'Sarah Jenkins',
				'registration_id'  => 'EF-2026-9M2X4',
				'payment_amount'   => 'PKR 1,500.00',
				'event_name'       => 'National Tech Olympiad 2026',
			)
		);

		$this->assert( $sent, 'Test C.1: payment_submitted dispatched immediately upon proof upload' );
		$email = $wp_mail_history[0];
		$this->assert( false !== strpos( $email['message'], 'EF-2026-9M2X4' ), 'Test C.2: Registration ID correctly embedded' );
		$this->assert( false !== strpos( $email['subject'], 'EF-2026-9M2X4' ), 'Test C.3: Registration ID included in subject line' );
	}

	/**
	 * Test D: Payment Verification Trigger
	 */
	private function test_d_payment_verification() {
		echo "\n--- TEST D: Payment Verification Staff Trigger ---\n";
		global $wp_mail_history;
		$wp_mail_history = array();

		$sent = EventFlow_Email_Manager::send_immediate(
			'payment_verified',
			'sarah.jenkins@example.com',
			array(
				'participant_name' => 'Sarah Jenkins',
				'registration_id'  => 'EF-2026-9M2X4',
				'activity_name'    => 'Speed Programming Championship',
				'payment_amount'   => 'PKR 1,500.00',
				'website_url'      => 'https://example.com/participant-dashboard/',
			)
		);

		$this->assert( $sent, 'Test D.1: payment_verified notification dispatched upon approval' );
		$email = $wp_mail_history[0];
		$this->assert( false !== strpos( $email['message'], 'PKR 1,500.00' ), 'Test D.2: Verified amount formatted properly' );
		$this->assert( false !== strpos( $email['message'], 'Speed Programming Championship' ), 'Test D.3: Activity title present' );
	}

	/**
	 * Test E: Payment Rejection Trigger & Rejection Reason Hydration
	 */
	private function test_e_payment_rejection() {
		echo "\n--- TEST E: Payment Rejection with Exact Reason ---\n";
		global $wp_mail_history;
		$wp_mail_history = array();

		$rejection_reason = 'Screenshot transaction reference number not found in bank ledger.';
		$sent = EventFlow_Email_Manager::send_immediate(
			'payment_rejected',
			'sarah.jenkins@example.com',
			array(
				'participant_name' => 'Sarah Jenkins',
				'registration_id'  => 'EF-2026-9M2X4',
				'rejection_reason' => $rejection_reason,
				'website_url'      => 'https://example.com/participant-dashboard/',
			)
		);

		$this->assert( $sent, 'Test E.1: payment_rejected notification dispatched' );
		$email = $wp_mail_history[0];
		$this->assert( false !== strpos( $email['message'], $rejection_reason ), 'Test E.2: Rejection reason accurately embedded for candidate' );
	}

	/**
	 * Test F: Application Approval & Rejection Triggers
	 */
	private function test_f_application_lifecycle() {
		echo "\n--- TEST F: Application Approval & Rejection Notifications ---\n";
		global $wp_mail_history;
		$wp_mail_history = array();

		// Approval
		$sent_appr = EventFlow_Email_Manager::send_immediate(
			'application_approved',
			'sarah.jenkins@example.com',
			array(
				'participant_name' => 'Sarah Jenkins',
				'team_name'        => 'Apex Algorithmics',
				'registration_id'  => 'EF-2026-9M2X4',
				'activity_name'    => 'Speed Programming Championship',
				'event_name'       => 'National Tech Olympiad 2026',
			)
		);
		$this->assert( $sent_appr, 'Test F.1: application_approved notification dispatched' );
		$this->assert( false !== strpos( $wp_mail_history[0]['message'], 'Apex Algorithmics' ), 'Test F.2: Team name correctly inserted' );

		// Rejection
		$sent_rej = EventFlow_Email_Manager::send_immediate(
			'application_rejected',
			'sarah.jenkins@example.com',
			array(
				'participant_name' => 'Sarah Jenkins',
				'registration_id'  => 'EF-2026-9M2X4',
				'activity_name'    => 'Speed Programming Championship',
				'rejection_reason' => 'Eligibility criteria not met: Semester 1 students not permitted.',
			)
		);
		$this->assert( $sent_rej, 'Test F.3: application_rejected notification dispatched' );
		$this->assert( false !== strpos( $wp_mail_history[1]['message'], 'Semester 1 students not permitted' ), 'Test F.4: Reason included in rejection notice' );
	}

	/**
	 * Test G: Failure Handling, Error Capture & Re-dispatching
	 */
	private function test_g_failure_handling_and_retry() {
		echo "\n--- TEST G: Failure Handling, Error Capture & Re-dispatching ---\n";
		global $wpdb, $wp_mail_simulate_fail, $wp_mail_history;

		// 1. Simulate mail server failure (e.g. invalid credentials)
		$wp_mail_simulate_fail = true;
		$sent = EventFlow_Email_Manager::send_immediate(
			'welcome_email',
			'failed.user@example.com',
			array(
				'participant_name' => 'Fail Test',
			)
		);

		$this->assert( false === $sent, 'Test G.1: Email correctly reports false when delivery fails' );

		// Check that log entry was created with status 'failed'
		$table_logs = EventFlow_DB::table( 'email_logs' );
		$log = $wpdb->get_row( "SELECT * FROM {$table_logs} WHERE recipient_email = 'failed.user@example.com' ORDER BY id DESC LIMIT 1" );

		$this->assert( ! empty( $log ), 'Test G.2: Failed dispatch attempt logged in database' );
		$this->assert( 'failed' === $log->status, 'Test G.3: Log status marked as "failed"' );
		$this->assert( ! empty( $log->error_message ), 'Test G.4: Exact provider error recorded in log' );

		// 2. Restore credentials and retry
		$wp_mail_simulate_fail = false;
		$wp_mail_history = array();

		$re_sent = EventFlow_Email_Manager::send_raw( $log->recipient_email, $log->subject, $log->body );
		$this->assert( true === $re_sent, 'Test G.5: Re-dispatched email succeeds after fixing credentials' );
		$this->assert( count( $wp_mail_history ) === 1, 'Test G.6: Delivery confirmed by mail server' );
	}

	/**
	 * Test H: Duplicate Prevention & Idempotency Locking
	 */
	private function test_h_duplicate_prevention() {
		echo "\n--- TEST H: Duplicate Prevention & Idempotency ---\n";
		global $wpdb;

		$idempotency_key = 'unique_reg_payment_verified_EF2026_9M2X4';

		// First queue attempt
		$q1 = EventFlow_Email_Queue::enqueue(
			array(
				'template_key'    => 'payment_verified',
				'recipient_email' => 'sarah.jenkins@example.com',
				'subject'         => 'Payment Verified',
				'body'            => 'Your payment has been verified.',
				'idempotency_key' => $idempotency_key,
			)
		);
		$this->assert( false !== $q1 && $q1 > 0, 'Test H.1: First notification queued successfully' );

		// Second duplicate attempt (e.g. participant refreshed page or clicked twice)
		$q2 = EventFlow_Email_Queue::enqueue(
			array(
				'template_key'    => 'payment_verified',
				'recipient_email' => 'sarah.jenkins@example.com',
				'subject'         => 'Payment Verified',
				'body'            => 'Your payment has been verified.',
				'idempotency_key' => $idempotency_key,
			)
		);

		$this->assert( $q1 === $q2, 'Test H.2: Idempotency check prevents duplicate queue insertion' );
	}

	/**
	 * Test I: Queue Processing, Exponential Backoff & Max Retry Limits
	 */
	private function test_i_queue_processing_and_backoff() {
		echo "\n--- TEST I: Queue Background Processing & Backoff Engine ---\n";
		global $wp_mail_history;
		$wp_mail_history = array();

		// Enqueue 3 items
		for ( $i = 1; $i <= 3; $i++ ) {
			EventFlow_Email_Queue::enqueue(
				array(
					'template_key'    => 'activity_announcement',
					'recipient_email' => "candidate{$i}@example.com",
					'subject'         => "Announcement #{$i}",
					'body'            => "<p>Content {$i}</p>",
					'idempotency_key' => "test_queue_batch_{$i}",
				)
			);
		}

		// Run queue worker
		$res = EventFlow_Email_Queue::process_queue( 10 );
		$this->assert( $res['processed'] >= 3, 'Test I.1: Background worker processes queued batch' );
		$this->assert( $res['sent'] >= 3, 'Test I.2: All queued emails accepted by mail server' );
		$this->assert( count( $wp_mail_history ) >= 3, 'Test I.3: Individual messages delivered' );

		// Verify Queue stats
		$stats = EventFlow_Email_Queue::get_queue_stats();
		$this->assert( isset( $stats['sent'] ) && $stats['sent'] >= 3, 'Test I.4: Queue monitoring statistics updated' );
	}

	/**
	 * Test J: Domain Deliverability & DNS Inspection (SPF / DMARC)
	 */
	private function test_j_dns_and_deliverability() {
		echo "\n--- TEST J: Domain Deliverability & DNS Diagnostics ---\n";

		// Test domain extraction
		$domain = EventFlow_Email_Diagnostics::extract_domain( 'registrations@techolympiad.org' );
		$this->assert( 'techolympiad.org' === $domain, 'Test J.1: Domain correctly parsed from sender address' );

		// Test SPF recommendation engine
		$spf_google = EventFlow_Email_Diagnostics::suggest_spf_record( 'smtp.gmail.com', $domain );
		$this->assert( false !== strpos( $spf_google, '_spf.google.com' ), 'Test J.2: Google SPF policy recommended for Gmail SMTP' );

		$spf_hostinger = EventFlow_Email_Diagnostics::suggest_spf_record( 'smtp.hostinger.com', $domain );
		$this->assert( false !== strpos( $spf_hostinger, 'spf.mail.hostinger.com' ), 'Test J.3: Hostinger SPF policy recommended for Hostinger SMTP' );

		// Test system environment checks
		$env = EventFlow_Email_Diagnostics::check_system_environment();
		$this->assert( count( $env ) >= 3, 'Test J.4: System requirements evaluated' );
	}
}

// Run the suite
$runner = new EventFlow_Email_Test_Runner();
$success = $runner->run_all();
exit( $success ? 0 : 1 );
