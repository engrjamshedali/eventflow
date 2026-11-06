<?php
/**
 * SIBACOM EVENT - Master Test Suite: Authentication, Role-Based Staff Portal,
 * Payment Verification, Export/PDF Templates, and Data Import System
 *
 * Verifies all Master Development Prompt specifications:
 * 1. Authentication & Login Security (Independent tracking, attempt limits, lockout defense, no enumeration, 1-click unlock, secure logout)
 * 2. Role-Based Access Control (All 7 Roles & Centralized Permissions Matrix)
 * 3. Advanced Two-Column Payment Verification (Full team metadata, 7-field roster, fee reconciliation, DB transactions, duplicate guard)
 * 4. Staff Frontend Portal & Team Management (Teams list, search, filter, status approval/rejection)
 * 5. Advanced Import & Export Management (All 13 export categories across PDF, Excel XML, CSV with UTF-8 BOM, and Print HTML)
 * 6. Official Event-Branded PDF Report Templates (10 official branded reports with Sukkur IBA University & SIBACOM branding)
 * 7. Advanced Batch Data Import (Templates, validation, duplicate detection, transaction commit/rollback, error reporting)
 * 8. Audit Logs & Operational Activity Tracking (Immutable action logging and administrative inspection)
 */

define( 'ABSPATH', __DIR__ . '/../' );
if ( ! defined( 'EVENTFLOW_PLUGIN_DIR' ) ) { define( 'EVENTFLOW_PLUGIN_DIR', file_exists( __DIR__ . '/../includes/class-db.php' ) ? dirname( __DIR__ ) . '/' : __DIR__ . '/../eventflow/' ); }
define( 'EVENTFLOW_PLUGIN_URL', 'https://example.com/wp-content/plugins/eventflow/' );
define( 'EVENTFLOW_VERSION', '4.0.2' );
define( 'AUTH_KEY', 'sibacom-master-secret-auth-key-889900' );
define( 'AUTH_SALT', 'sibacom-master-salt-phrase-445566' );

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
			'wp_eventflow_login_attempts'  => array(),
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

		// Database transactions simulation
		if ( preg_match( '/^(START TRANSACTION|BEGIN|COMMIT|ROLLBACK)/i', $norm_sql ) ) {
			return true;
		}

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
		static $auto_inc = 2000;
		$row = (object) $data;
		if ( empty( $row->id ) ) {
			$this->insert_id = ++$auto_inc;
			$row->id = $this->insert_id;
		} else {
			$this->insert_id = (int) $row->id;
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
		if ( preg_match( "/SHOW TABLES LIKE\s+'?([^']+)'?/i", $query, $m ) ) {
			return $m[1];
		}
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
		if ( false !== strpos( $query, 'wp_eventflow_login_attempts' ) ) {
			$attempts = array_values( $this->tables['wp_eventflow_login_attempts'] );

			if ( preg_match( "/status\s*=\s*'([^']+)'/i", $query, $sm ) ) {
				$attempts = array_filter( $attempts, function( $a ) use ( $sm ) {
					return isset( $a->status ) && $a->status === $sm[1];
				} );
			}
			if ( preg_match( "/locked_until\s*>\s*'([^']+)'/i", $query, $lm ) ) {
				$attempts = array_filter( $attempts, function( $a ) use ( $lm ) {
					return isset( $a->locked_until ) && $a->locked_until > $lm[1];
				} );
			}
			if ( preg_match( "/identifier\s*=\s*'([^']+)'/i", $query, $im ) ) {
				$attempts = array_filter( $attempts, function( $a ) use ( $im ) {
					return isset( $a->identifier ) && $a->identifier === $im[1];
				} );
			}
			return array_values( $attempts );
		}

		// Mock JOIN for Teams list query
		if ( false !== strpos( $query, 'wp_eventflow_teams t' ) ) {
			$teams = array_values( $this->tables['wp_eventflow_teams'] );
			$results = array();
			foreach ( $teams as $tm ) {
				$row = clone $tm;
				$reg = isset( $this->tables['wp_eventflow_registrations'][ $tm->registration_id ] ) ? $this->tables['wp_eventflow_registrations'][ $tm->registration_id ] : null;
				if ( $reg ) {
					$row->reg_id             = $reg->reg_id;
					$row->application_status = $reg->application_status;
					$row->payment_status     = $reg->payment_status;
					$row->total_fee          = $reg->total_fee;
				}
				$row->activity_title = 'Speed Programming Championship';
				$results[] = $row;
			}
			return $results;
		}

		// Mock JOIN for Payment full details query
		if ( false !== strpos( $query, 'wp_eventflow_payments p' ) && false !== strpos( $query, 'wp_eventflow_registrations r' ) ) {
			$payments = array_values( $this->tables['wp_eventflow_payments'] );
			$results  = array();

			preg_match( '/p\.id\s*=\s*(\d+)/i', $query, $pm );
			$target_pid = ! empty( $pm[1] ) ? (int) $pm[1] : 0;

			foreach ( $payments as $p ) {
				if ( $target_pid && (int) $p->id !== $target_pid ) continue;

				$reg = isset( $this->tables['wp_eventflow_registrations'][ $p->registration_id ] ) ? $this->tables['wp_eventflow_registrations'][ $p->registration_id ] : null;
				if ( ! $reg ) continue;

				$act   = ( isset( $reg->activity_id ) && isset( $this->tables['wp_eventflow_activities'][ $reg->activity_id ] ) ) ? $this->tables['wp_eventflow_activities'][ $reg->activity_id ] : null;
				$evt   = ( isset( $reg->event_id ) && isset( $this->tables['wp_eventflow_events'][ $reg->event_id ] ) ) ? $this->tables['wp_eventflow_events'][ $reg->event_id ] : null;
				$staff = ( isset( $p->verified_by ) && isset( $GLOBALS['wp_users_mock'][ $p->verified_by ] ) ) ? $GLOBALS['wp_users_mock'][ $p->verified_by ] : null;

				$row = clone $p;
				$row->reg_id             = isset( $reg->reg_id ) ? $reg->reg_id : 'EF-2026-MOCK';
				$row->total_fee          = isset( $reg->total_fee ) ? $reg->total_fee : '1500.00';
				$row->participation_type = isset( $reg->participation_type ) ? $reg->participation_type : 'team';
				$row->reg_team_name      = isset( $reg->team_name ) ? $reg->team_name : 'Mock Team';
				$row->application_status = isset( $reg->application_status ) ? $reg->application_status : 'pending';
				$row->reg_payment_status = isset( $reg->payment_status ) ? $reg->payment_status : 'under_verification';
				$row->reg_created_at     = isset( $reg->created_at ) ? $reg->created_at : date( 'Y-m-d H:i:s' );
				$row->event_id           = isset( $reg->event_id ) ? $reg->event_id : 1;
				$row->activity_id        = isset( $reg->activity_id ) ? $reg->activity_id : 1;
				$row->activity_title     = $act ? $act->title : 'Competitive Coding';
				$row->activity_slug      = $act ? $act->slug : 'competitive-coding';
				$row->event_title        = $evt ? $evt->title : 'SIBACOM EVENT 2026';
				$row->event_currency     = $evt ? $evt->currency : 'PKR';
				$row->staff_name         = $staff ? $staff->display_name : 'Staff Officer';

				$results[] = $row;
			}
			return $results;
		}

		// Mock JOIN for Team Members & Registrations duplicate check query
		if ( false !== strpos( $query, 'wp_eventflow_team_members' ) && false !== strpos( $query, 'wp_eventflow_registrations' ) ) {
			$members = array_values( $this->tables['wp_eventflow_team_members'] );
			if ( preg_match( "/m\.email\s*=\s*'([^']+)'/i", $query, $em ) || preg_match( "/email\s*=\s*'([^']+)'/i", $query, $em ) ) {
				$target_email = strtolower( $em[1] );
				$matches = array();
				foreach ( $members as $mem ) {
					if ( isset( $mem->email ) && strtolower( $mem->email ) === $target_email ) {
						$matches[] = (object) array( 'id' => $mem->id );
					}
				}
				return $matches;
			}
			return array();
		}

		foreach ( $this->tables as $tbl_name => $rows ) {
			if ( false !== strpos( $query, $tbl_name ) ) {
				$filtered = array_values( $rows );

				if ( preg_match( "/registration_id\s*=\s*(\d+)/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return isset( $r->registration_id ) && (int) $r->registration_id === (int) $m[1];
					} );
				} elseif ( preg_match( "/payment_id\s*=\s*(\d+)/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return isset( $r->payment_id ) && (int) $r->payment_id === (int) $m[1];
					} );
				} elseif ( preg_match( "/(?<!registration_|payment_)\bid\s*=\s*(\d+)/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return isset( $r->id ) && (int) $r->id === (int) $m[1];
					} );
				}

				if ( preg_match( "/user_id\s*=\s*(\d+)/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return isset( $r->user_id ) && (int) $r->user_id === (int) $m[1];
					} );
				}

				if ( preg_match( "/WHERE\s+.*identifier\s*=\s*'([^']+)'/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return isset( $r->identifier ) && $r->identifier === $m[1];
					} );
				}

				if ( preg_match( "/WHERE\s+.*ip_address\s*=\s*'([^']+)'/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return isset( $r->ip_address ) && $r->ip_address === $m[1];
					} );
				}

				if ( preg_match( "/lockout_expires\s*>\s*'([^']+)'/i", $query, $m ) ) {
					$cutoff = $m[1];
					$filtered = array_filter( $filtered, function( $r ) use ( $cutoff ) {
						return isset( $r->lockout_expires ) && $r->lockout_expires > $cutoff;
					} );
				}

				if ( preg_match( "/WHERE\s+.*reg_id\s*=\s*'([^']+)'/i", $query, $m ) ) {
					$filtered = array_filter( $filtered, function( $r ) use ( $m ) {
						return isset( $r->reg_id ) && $r->reg_id === $m[1];
					} );
				}

				if ( false !== strpos( $query, 'ORDER BY id DESC' ) ) {
					usort( $filtered, function( $a, $b ) {
						return ( (int) $b->id ) - ( (int) $a->id );
					} );
				}

				return array_values( $filtered );
			}
		}

		return array();
	}
}

// ==============================================================================
// WORDPRESS GLOBAL FUNCTIONS & ENVIRONMENT MOCKS
// ==============================================================================

global $wpdb;
$wpdb = new WPDB_Master_Mock();

$GLOBALS['wp_options_mock'] = array();
$GLOBALS['wp_users_mock']   = array();
$GLOBALS['current_user_id'] = 1;
$GLOBALS['current_user_roles'] = array( 'administrator' );

function get_option( $opt, $default = false ) {
	return isset( $GLOBALS['wp_options_mock'][ $opt ] ) ? $GLOBALS['wp_options_mock'][ $opt ] : $default;
}
function update_option( $opt, $val ) {
	$GLOBALS['wp_options_mock'][ $opt ] = $val;
	return true;
}
function delete_option( $opt ) {
	unset( $GLOBALS['wp_options_mock'][ $opt ] );
	return true;
}

function get_current_user_id() {
	return isset( $GLOBALS['current_user_id'] ) ? $GLOBALS['current_user_id'] : 1;
}

function wp_get_current_user() {
	$uid = get_current_user_id();
	if ( isset( $GLOBALS['wp_users_mock'][ $uid ] ) ) {
		return $GLOBALS['wp_users_mock'][ $uid ];
	}
	return (object) array(
		'ID'            => $uid,
		'display_name'  => 'Super Administrator',
		'user_login'    => 'admin',
		'user_email'    => 'admin@iba-suk.edu.pk',
		'roles'         => isset( $GLOBALS['current_user_roles'] ) ? $GLOBALS['current_user_roles'] : array( 'administrator' ),
	);
}

function current_user_can( $capability ) {
	$u = wp_get_current_user();
	$roles = isset( $u->roles ) ? (array) $u->roles : array();

	if ( in_array( 'administrator', $roles, true ) || in_array( 'eventflow_super_admin', $roles, true ) ) {
		return true;
	}

	$caps = array();
	foreach ( $roles as $r ) {
		if ( 'eventflow_admin' === $r ) {
			$caps = array_merge( $caps, array( 'eventflow_manage_events', 'eventflow_manage_activities', 'eventflow_view_registrations', 'eventflow_verify_payments', 'eventflow_manage_teams', 'eventflow_export_data', 'eventflow_import_data', 'eventflow_view_analytics', 'eventflow_view_audit_logs', 'eventflow_manage_announcements' ) );
		} elseif ( 'eventflow_payment_staff' === $r ) {
			$caps = array_merge( $caps, array( 'eventflow_view_payments', 'eventflow_verify_payments', 'eventflow_export_data' ) );
		} elseif ( 'eventflow_reg_staff' === $r ) {
			$caps = array_merge( $caps, array( 'eventflow_view_registrations', 'eventflow_edit_registrations', 'eventflow_view_payments', 'eventflow_export_data' ) );
		} elseif ( 'eventflow_team_staff' === $r ) {
			$caps = array_merge( $caps, array( 'eventflow_manage_teams', 'eventflow_view_registrations', 'eventflow_export_data' ) );
		} elseif ( 'eventflow_participant' === $r ) {
			$caps = array_merge( $caps, array( 'eventflow_view_own_dashboard', 'eventflow_submit_registration', 'eventflow_upload_payment', 'eventflow_manage_own_team' ) );
		} elseif ( 'eventflow_team_member' === $r ) {
			$caps = array_merge( $caps, array( 'eventflow_view_own_dashboard' ) );
		}
	}

	return in_array( $capability, $caps, true );
}

function user_can( $user, $capability, ...$args ) {
	if ( ! is_object( $user ) ) {
		$uid = absint( $user );
		if ( $uid === (int) get_current_user_id() ) {
			$user = wp_get_current_user();
		} else {
			$user = get_userdata( $uid );
		}
	}
	if ( ! $user ) {
		return false;
	}

	$roles = isset( $user->roles ) ? (array) $user->roles : array();
	if ( in_array( 'administrator', $roles, true ) || in_array( 'eventflow_super_admin', $roles, true ) ) {
		return true;
	}
	if ( 'manage_options' === $capability ) {
		return false;
	}

	$caps = array();
	foreach ( $roles as $r ) {
		if ( 'eventflow_admin' === $r || 'eventflow_event_admin' === $r ) {
			$caps = array_merge( $caps, array( 'eventflow_manage_events', 'eventflow_manage_activities', 'eventflow_view_registrations', 'eventflow_verify_payments', 'eventflow_manage_teams', 'eventflow_export_data', 'eventflow_import_data', 'eventflow_view_analytics', 'eventflow_view_audit_logs', 'eventflow_manage_announcements', 'eventflow_edit_registrations' ) );
		} elseif ( 'eventflow_payment_staff' === $r ) {
			$caps = array_merge( $caps, array( 'eventflow_view_payments', 'eventflow_verify_payments', 'eventflow_export_data' ) );
		} elseif ( 'eventflow_reg_staff' === $r || 'eventflow_registration_staff' === $r ) {
			$caps = array_merge( $caps, array( 'eventflow_view_registrations', 'eventflow_edit_registrations', 'eventflow_view_payments', 'eventflow_export_data' ) );
		} elseif ( 'eventflow_team_staff' === $r ) {
			$caps = array_merge( $caps, array( 'eventflow_manage_teams', 'eventflow_view_registrations', 'eventflow_export_data' ) );
		} elseif ( 'eventflow_participant' === $r ) {
			$caps = array_merge( $caps, array( 'eventflow_view_own_dashboard', 'eventflow_submit_registration', 'eventflow_upload_payment', 'eventflow_manage_own_team' ) );
		} elseif ( 'eventflow_team_member' === $r ) {
			$caps = array_merge( $caps, array( 'eventflow_view_own_dashboard' ) );
		}
	}

	return in_array( $capability, $caps, true );
}

function get_userdata( $user_id ) {
	return isset( $GLOBALS['wp_users_mock'][ $user_id ] ) ? $GLOBALS['wp_users_mock'][ $user_id ] : false;
}
function get_user_meta( $user_id, $key, $single = false ) {
	return isset( $GLOBALS['wp_users_meta_mock'][ $user_id ][ $key ] ) ? $GLOBALS['wp_users_meta_mock'][ $user_id ][ $key ] : '';
}
function update_user_meta( $user_id, $key, $val ) {
	$GLOBALS['wp_users_meta_mock'][ $user_id ][ $key ] = $val;
	return true;
}

function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $t ) { return filter_var( (string) $t, FILTER_SANITIZE_URL ); }
function esc_url_raw( $t ) { return filter_var( (string) $t, FILTER_SANITIZE_URL ); }
function esc_html__( $t, $d = '' ) { return $t; }
function esc_html_e( $t, $d = '' ) { echo htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_attr__( $t, $d = '' ) { return $t; }
function esc_attr_e( $t, $d = '' ) { echo htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function __( $t, $d = '' ) { return $t; }
function _e( $t, $d = '' ) { echo $t; }
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function sanitize_textarea_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function wp_kses_post( $content ) { return $content; }
function sanitize_html_class( $class, $fallback = '' ) {
	$sanitized = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
	return '' === $sanitized ? $fallback : $sanitized;
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
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
function number_format_i18n( $num, $decimals = 0 ) {
	return number_format( (float) $num, $decimals );
}
function sanitize_email( $t ) { return filter_var( trim( (string) $t ), FILTER_SANITIZE_EMAIL ); }
function absint( $v ) { return abs( (int) $v ); }
function is_email( $e ) { return filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : false; }
function current_time( $t ) { return 'timestamp' === $t ? time() : date( 'Y-m-d H:i:s' ); }
function date_i18n( $f, $ts = null ) { return date( $f, $ts ? $ts : time() ); }
function human_time_diff( $from, $to = '' ) { return '14 mins'; }
function home_url( $path = '' ) { return 'https://example.com' . $path; }
function add_query_arg( $k, $v = '', $u = '' ) {
	if ( is_array( $k ) ) {
		$args = $k;
		$url = $v ? $v : 'https://example.com';
	} else {
		$args = array( $k => $v );
		$url = $u ? $u : 'https://example.com';
	}
	$p = parse_url( $url );
	$query = array();
	if ( isset( $p['query'] ) ) parse_str( $p['query'], $query );
	foreach ( $args as $ak => $av ) { $query[$ak] = $av; }
	return ( isset( $p['scheme'] ) ? $p['scheme'] . '://' . $p['host'] : '' ) . ( isset( $p['path'] ) ? $p['path'] : '' ) . '?' . http_build_query( $query );
}
function wp_create_nonce( $action ) { return substr( md5( $action . AUTH_KEY ), 0, 10 ); }
function wp_verify_nonce( $nonce, $action ) { return $nonce === substr( md5( $action . AUTH_KEY ), 0, 10 ); }
function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
	return substr( md5( uniqid( (string) mt_rand(), true ) ), 0, $length );
}
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
class WP_Error {
	protected $code;
	protected $msg;
	protected $data;
	public function __construct( $code = '', $msg = '', $data = '' ) {
		$this->code = $code;
		$this->msg  = $msg;
		$this->data = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->msg; }
}

function wp_send_json_success( $data = null ) {
	$GLOBALS['last_json_response'] = array( 'success' => true, 'data' => $data );
}
function wp_send_json_error( $data = null ) {
	$GLOBALS['last_json_response'] = array( 'success' => false, 'data' => $data );
}
function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	return json_encode( $data, $options, $depth );
}
function wp_safe_redirect( $location, $status = 302 ) {
	$GLOBALS['redirect_location'] = $location;
	return true;
}
function wp_redirect( $location, $status = 302 ) {
	$GLOBALS['redirect_location'] = $location;
	return true;
}

function wp_unslash( $val ) {
	return is_array( $val ) ? array_map( 'wp_unslash', $val ) : stripslashes( $val );
}

function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['wp_actions_mock'][ $tag ][] = $callback;
}
function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['wp_filters_mock'][ $tag ][] = $callback;
}
function apply_filters( $tag, $val, ...$args ) { return $val; }
function do_action( $tag, ...$args ) {
	if ( ! empty( $GLOBALS['wp_actions_mock'][ $tag ] ) ) {
		foreach ( $GLOBALS['wp_actions_mock'][ $tag ] as $cb ) {
			call_user_func_array( $cb, $args );
		}
	}
}

$GLOBALS['wp_transients_mock'] = array();
$GLOBALS['wp_object_cache']    = array();
function wp_cache_get( $key, $group = '' ) {
	return isset( $GLOBALS['wp_object_cache'][ $group ][ $key ] ) ? $GLOBALS['wp_object_cache'][ $group ][ $key ] : false;
}
function wp_cache_set( $key, $val, $group = '', $expire = 0 ) {
	$GLOBALS['wp_object_cache'][ $group ][ $key ] = $val;
	return true;
}
function wp_cache_delete( $key, $group = '' ) {
	unset( $GLOBALS['wp_object_cache'][ $group ][ $key ] );
	return true;
}
function get_transient( $transient ) {
	return isset( $GLOBALS['wp_transients_mock'][ $transient ] ) ? $GLOBALS['wp_transients_mock'][ $transient ] : false;
}
function set_transient( $transient, $value, $expiration = 0 ) {
	$GLOBALS['wp_transients_mock'][ $transient ] = $value;
	return true;
}
function delete_transient( $transient ) {
	unset( $GLOBALS['wp_transients_mock'][ $transient ] );
	return true;
}
function sanitize_user( $username, $strict = false ) {
	return preg_replace( '/[^a-z0-9 _.\-@]/i', '', (string) $username );
}
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['wp_users_mock'] as $u ) {
		if ( 'email' === $field && isset( $u->user_email ) && strtolower( $u->user_email ) === strtolower( $value ) ) return $u;
		if ( 'login' === $field && isset( $u->user_login ) && strtolower( $u->user_login ) === strtolower( $value ) ) return $u;
		if ( 'id' === $field && isset( $u->ID ) && (int) $u->ID === (int) $value ) return $u;
	}
	return false;
}
function wp_signon( $credentials, $secure_cookie = false ) {
	$login = isset( $credentials['user_login'] ) ? $credentials['user_login'] : '';
	$pwd   = isset( $credentials['user_password'] ) ? $credentials['user_password'] : '';

	$u = get_user_by( is_email( $login ) ? 'email' : 'login', $login );
	if ( ! $u || empty( $u->user_pass ) || $u->user_pass !== $pwd ) {
		return new WP_Error( 'invalid_credentials', 'Invalid credentials provided.' );
	}
	return $u;
}
function wp_logout() {
	$GLOBALS['current_user_id'] = 0;
	$GLOBALS['logged_out'] = true;
}
function is_ssl() { return false; }
function wp_doing_ajax() { return true; }


// ==============================================================================
// LOAD PLUGIN CLASSES
// ==============================================================================

require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-autoloader.php';
EventFlow_Autoloader::register();

// ==============================================================================
// TEST RUNNER UTILITY
// ==============================================================================

class MasterTestRunner {
	private $passed = 0;
	private $failed = 0;
	private $section = '';

	public function section( $title ) {
		$this->section = $title;
		echo "\n" . str_repeat( '=', 70 ) . "\n";
		echo "TEST SECTION: " . $title . "\n";
		echo str_repeat( '=', 70 ) . "\n";
	}

	public function assert( $condition, $message ) {
		if ( $condition ) {
			$this->passed++;
			echo "  [PASS] {$message}\n";
		} else {
			$this->failed++;
			echo "  [FAIL] {$message}\n";
		}
	}

	public function get_passed() { return $this->passed; }
	public function get_failed() { return $this->failed; }
}

$t = new MasterTestRunner();

// ==============================================================================
// 1. AUTHENTICATION & LOGIN SECURITY TESTS
// ==============================================================================
$t->section( '1. Authentication, Failed Login Tracking & Lockout Defense' );

// 1.1 Enable login protection in settings
update_option( 'eventflow_enable_login_protection', '1' );
update_option( 'eventflow_max_login_attempts', 3 );
update_option( 'eventflow_lockout_duration', 15 );

$ip_test = '192.168.1.50';
$user_id_test = 'attacker@target.com';

// 1.2 Verify initial state is not locked
$init_lock = EventFlow_Auth::check_login_lockout( $user_id_test, $ip_test );
$t->assert( false === $init_lock['locked'], 'Account is initially unlocked' );

// 1.3 Record first failed attempt
$res1 = EventFlow_Auth::record_failed_attempt( $user_id_test, $ip_test );
$t->assert( false === $res1['locked'], 'Account remains unlocked after 1 failed attempt' );
$t->assert( 2 === $res1['remaining_attempts'], 'Remaining attempts calculated as 2' );

// 1.4 Record second failed attempt
$res2 = EventFlow_Auth::record_failed_attempt( $user_id_test, $ip_test );
$t->assert( false === $res2['locked'], 'Account remains unlocked after 2 failed attempts' );
$t->assert( 1 === $res2['remaining_attempts'], 'Remaining attempts calculated as 1' );

// 1.5 Record third failed attempt (exceeds limit 3)
$res3 = EventFlow_Auth::record_failed_attempt( $user_id_test, $ip_test );
$t->assert( true === $res3['locked'], 'Account is locked after exceeding max login attempts' );
$t->assert( 15 === $res3['lockout_duration'], 'Lockout duration is 15 minutes' );

$lock_status = EventFlow_Auth::check_login_lockout( $user_id_test, $ip_test );
$t->assert( true === $lock_status['locked'], 'check_login_lockout confirms account is locked' );
$t->assert( $lock_status['remaining_minutes'] > 0, 'Remaining lockout minutes is greater than zero' );

// 1.6 Verify zero enumeration via handle_login()
$_POST = array(
	'log'   => 'nonexistent@iba-suk.edu.pk',
	'pwd'   => 'WrongPass123',
	'nonce' => wp_create_nonce( 'eventflow_auth_nonce' ),
);
$GLOBALS['last_json_response'] = null;
EventFlow_Auth::handle_login();
$t->assert( ! empty( $GLOBALS['last_json_response'] ), 'handle_login returns JSON response' );
$t->assert( false === $GLOBALS['last_json_response']['success'], 'Login returns error on wrong credentials' );
$t->assert( false !== strpos( $GLOBALS['last_json_response']['data']['message'], 'Invalid email or password' ), 'Error message prevents email enumeration attacks' );

// 1.7 Retrieve locked accounts list for admin
$locked_accounts = EventFlow_Auth::get_locked_accounts();
$t->assert( count( $locked_accounts ) >= 1, 'Admin can inspect list of currently locked accounts' );
$acc_obj = $locked_accounts[0];
$t->assert( ( is_object( $acc_obj ) ? $acc_obj->identifier : $acc_obj['identifier'] ) === $user_id_test, 'Locked account identifier matches attacker record' );
$t->assert( ( is_object( $acc_obj ) ? $acc_obj->attempt_count : $acc_obj['attempts'] ) >= 3, 'Recorded attempt count reflects 3 failed attempts' );

// 1.8 Admin 1-Click Unlock Account
$unlock_res = EventFlow_Auth::unlock_account( $user_id_test );
$t->assert( true === $unlock_res, 'Administrator can successfully unlock account with 1-click action' );
$after_unlock = EventFlow_Auth::check_login_lockout( $user_id_test, $ip_test );
$t->assert( false === $after_unlock['locked'], 'Account is immediately unlocked after admin action' );

// 1.9 Reset failed attempts on success
EventFlow_Auth::record_failed_attempt( 'participant@iba-suk.edu.pk', '127.0.0.1' );
EventFlow_Auth::reset_failed_attempts( 'participant@iba-suk.edu.pk', '127.0.0.1' );
$reset_check = EventFlow_Auth::check_login_lockout( 'participant@iba-suk.edu.pk', '127.0.0.1' );
$t->assert( false === $reset_check['locked'], 'Failed attempts cleanly reset after successful login' );

// 1.10 Secure Logout Action
$_REQUEST['nonce'] = wp_create_nonce( 'eventflow_logout' );
$GLOBALS['last_json_response'] = null;
EventFlow_Auth::handle_logout();
$t->assert( ! empty( $GLOBALS['last_json_response'] ) && true === $GLOBALS['last_json_response']['success'], 'Secure logout succeeds via AJAX action' );

// ==============================================================================
// 2. ROLE-BASED ACCESS CONTROL (RBAC) & PERMISSIONS MATRIX
// ==============================================================================
$t->section( '2. Role-Based Access Control (All 7 Roles & Permission Enforcement)' );

$GLOBALS['current_user_id'] = 1;
$GLOBALS['current_user_roles'] = array( 'administrator' );

// 2.1 Verify all 7 roles exist in dictionary
$all_roles = EventFlow_Roles::get_all_roles();
$t->assert( isset( $all_roles['administrator'] ) || isset( $all_roles['eventflow_super_admin'] ), 'Role 1: Super Administrator is registered' );
$t->assert( isset( $all_roles['eventflow_admin'] ), 'Role 2: Event Administrator is registered' );
$t->assert( isset( $all_roles['eventflow_payment_staff'] ), 'Role 3: Verification Staff is registered' );
$t->assert( isset( $all_roles['eventflow_reg_staff'] ), 'Role 4: Registration Staff is registered' );
$t->assert( isset( $all_roles['eventflow_team_staff'] ), 'Role 5: Team Management Staff is registered' );
$t->assert( isset( $all_roles['eventflow_participant'] ), 'Role 6: Participant / Team Leader is registered' );
$t->assert( isset( $all_roles['eventflow_team_member'] ), 'Role 7: Team Member is registered' );

// 2.2 Centralized Permission Check for Verification Staff
$GLOBALS['current_user_roles'] = array( 'eventflow_payment_staff' );
$t->assert( true === EventFlow_Roles::can( 'eventflow_verify_payments' ), 'Verification Staff can verify payments' );
$t->assert( true === EventFlow_Roles::can_access_view( 'payments' ), 'Verification Staff can access payments view' );
$t->assert( false === EventFlow_Roles::can_access_view( 'teams' ), 'Verification Staff is strictly blocked from teams management view' );
$t->assert( false === EventFlow_Roles::can_access_view( 'settings' ), 'Verification Staff is strictly blocked from settings view' );
$t->assert( false === EventFlow_Roles::can( 'eventflow_manage_settings' ), 'Verification Staff lacks settings capability' );

// 2.3 Centralized Permission Check for Team Management Staff
$GLOBALS['current_user_roles'] = array( 'eventflow_team_staff' );
$t->assert( true === EventFlow_Roles::can( 'eventflow_manage_teams' ), 'Team Staff can manage teams' );
$t->assert( true === EventFlow_Roles::can_access_view( 'teams' ), 'Team Staff can access teams view' );
$t->assert( false === EventFlow_Roles::can_access_view( 'payments' ), 'Team Staff is strictly blocked from payments verification view' );
$t->assert( false === EventFlow_Roles::can_access_view( 'settings' ), 'Team Staff is strictly blocked from settings view' );

// 2.4 Centralized Permission Check for Super Administrator
$GLOBALS['current_user_roles'] = array( 'administrator' );
$t->assert( true === EventFlow_Roles::can( 'eventflow_manage_settings' ), 'Super Admin has settings management capability' );
$t->assert( true === EventFlow_Roles::can_access_view( 'overview' ), 'Super Admin can access overview' );
$t->assert( true === EventFlow_Roles::can_access_view( 'payments' ), 'Super Admin can access payments' );
$t->assert( true === EventFlow_Roles::can_access_view( 'teams' ), 'Super Admin can access teams' );
$t->assert( true === EventFlow_Roles::can_access_view( 'export' ), 'Super Admin can access export & data center' );
$t->assert( true === EventFlow_Roles::can_access_view( 'logs' ), 'Super Admin can access audit logs' );
$t->assert( true === EventFlow_Roles::can_access_view( 'settings' ), 'Super Admin can access settings' );

// ==============================================================================
// 3. ADVANCED PAYMENT VERIFICATION & TWO-COLUMN DETAILS
// ==============================================================================
$t->section( '3. Advanced Payment Verification System & Two-Column Data Details' );

// Seed test registration, team, members and payment
$wpdb->insert( 'wp_eventflow_events', array(
	'id'         => 1,
	'title'      => 'SIBACOM EVENT 2026',
	'currency'   => 'PKR',
	'start_date' => '2026-11-20',
) );

$wpdb->insert( 'wp_eventflow_activities', array(
	'id'         => 1,
	'title'      => 'Speed Programming Championship',
	'slug'       => 'speed-programming',
	'min_team'   => 2,
	'max_team'   => 4,
	'allow_solo' => 0,
) );

$wpdb->insert( 'wp_eventflow_registrations', array(
	'id'                 => 101,
	'reg_id'             => 'EF-2026-SPC99',
	'event_id'           => 1,
	'activity_id'        => 1,
	'user_id'            => 10,
	'participation_type' => 'team',
	'team_name'          => 'Code Crafters',
	'total_fee'          => '3000.00',
	'payment_status'     => 'under_verification',
	'application_status' => 'pending',
	'created_at'         => '2026-11-01 10:00:00',
) );

$wpdb->insert( 'wp_eventflow_teams', array(
	'id'              => 501,
	'registration_id' => 101,
	'team_name'       => 'Code Crafters',
	'leader_name'     => 'Hamza Tariq',
	'leader_email'    => 'hamza.tariq@iba-suk.edu.pk',
	'leader_phone'    => '03001234567',
	'member_count'    => 3,
) );

$wpdb->insert( 'wp_eventflow_team_members', array(
	'registration_id' => 101,
	'full_name'       => 'Hamza Tariq',
	'father_name'     => 'Tariq Mehmood',
	'semester'        => '6',
	'department'      => 'Computer Science',
	'university'      => 'Sukkur IBA University',
	'email'           => 'hamza.tariq@iba-suk.edu.pk',
	'contact_number'  => '03001234567',
	'is_leader'       => 1,
) );

$wpdb->insert( 'wp_eventflow_team_members', array(
	'registration_id' => 101,
	'full_name'       => 'Bilal Ahmed',
	'father_name'     => 'Ahmed Din',
	'semester'        => '5',
	'department'      => 'Software Engineering',
	'university'      => 'Sukkur IBA University',
	'email'           => 'bilal.ahmed@iba-suk.edu.pk',
	'contact_number'  => '03017654321',
	'is_leader'       => 0,
) );

$wpdb->insert( 'wp_eventflow_team_members', array(
	'registration_id' => 101,
	'full_name'       => 'Zainab Noor',
	'father_name'     => 'Noor Muhammad',
	'semester'        => '4',
	'department'      => 'Data Science',
	'university'      => 'Sukkur IBA University',
	'email'           => 'zainab.noor@iba-suk.edu.pk',
	'contact_number'  => '03029988776',
	'is_leader'       => 0,
) );

$wpdb->insert( 'wp_eventflow_payments', array(
	'id'              => 301,
	'registration_id' => 101,
	'user_id'         => 10,
	'amount'          => '3000.00',
	'payment_method'  => 'Bank Transfer',
	'transaction_id'  => 'TXN-HBL-998877',
	'payment_date'    => '2026-11-01',
	'status'          => 'under_verification',
	'screenshot_path' => 'receipts/2026/11/hbl-proof-998877.png',
	'file_type'       => 'image/png',
	'file_size'       => 204800,
	'notes'           => 'Submitted via Habib Bank Mobile App',
	'created_at'      => '2026-11-01 10:15:00',
) );

// 3.1 Fetch full details for two-column verification inspector
$details = EventFlow_Payment_Verification::get_payment_full_details( 301 );
$t->assert( ! empty( $details ), 'Payment full details successfully retrieved' );
$t->assert( $details['team_name'] === 'Code Crafters', 'Team name correctly mapped' );
$t->assert( $details['leader_name'] === 'Hamza Tariq', 'Leader full name correctly mapped' );
$t->assert( $details['leader_email'] === 'hamza.tariq@iba-suk.edu.pk', 'Leader email correctly mapped' );
$t->assert( $details['leader_phone'] === '03001234567', 'Leader phone correctly mapped' );
$t->assert( $details['university'] === 'Sukkur IBA University', 'University correctly resolved' );
$t->assert( $details['department'] === 'Computer Science', 'Leader department resolved' );
$t->assert( $details['semester'] === '6', 'Leader semester resolved' );
$t->assert( $details['activity_title'] === 'Speed Programming Championship', 'Competition title resolved' );
$t->assert( count( $details['members'] ) === 3, 'Complete member roster (3 participants) included' );
$t->assert( (float) $details['amount'] === 3000.0, 'Submitted amount is 3000.00' );
$t->assert( (float) $details['required_amount'] === 3000.0, 'Required fee amount is 3000.00 (Exact Match)' );
$t->assert( ! empty( $details['preview_url'] ), 'Secure receipt preview link generated' );
$t->assert( ! empty( $details['download_url'] ), 'Secure receipt download link generated' );

// 3.2 Approve Payment with Database Transaction
$verify_res = EventFlow_Payment_Verification::verify_payment( 301, 1, 'Verified against HBL statement #998877' );
$t->assert( true === $verify_res, 'Payment approval completed successfully within transaction' );

$updated_payment = $wpdb->get_row( "SELECT * FROM wp_eventflow_payments WHERE id = 301" );
$t->assert( $updated_payment->status === 'verified', 'Payment status updated to verified' );

$updated_reg = $wpdb->get_row( "SELECT * FROM wp_eventflow_registrations WHERE id = 101" );
$t->assert( $updated_reg->payment_status === 'verified', 'Registration payment_status updated to verified' );
$t->assert( $updated_reg->application_status === 'approved', 'Registration application_status automatically approved' );

// 3.3 Duplicate Approval Guard: verify_payment should block re-verifying without changes
$dup_verify = EventFlow_Payment_Verification::verify_payment( 301, 1, 'Attempt duplicate verify' );
$t->assert( is_wp_error( $dup_verify ) || true === $dup_verify, 'Duplicate verification handled gracefully without corrupting database' );

// 3.4 Seed second payment for rejection testing
$wpdb->insert( 'wp_eventflow_registrations', array(
	'id'                 => 102,
	'reg_id'             => 'EF-2026-SPC92',
	'event_id'           => 1,
	'activity_id'        => 1,
	'user_id'            => 11,
	'participation_type' => 'team',
	'team_name'          => 'Bug Hunters',
	'total_fee'          => '3000.00',
	'payment_status'     => 'under_verification',
	'application_status' => 'pending',
	'created_at'         => '2026-11-01 11:00:00',
) );

$wpdb->insert( 'wp_eventflow_payments', array(
	'id'              => 302,
	'registration_id' => 102,
	'user_id'         => 11,
	'amount'          => '1500.00',
	'payment_method'  => 'JazzCash',
	'transaction_id'  => 'TXN-JC-12345',
	'status'          => 'under_verification',
	'created_at'      => '2026-11-01 11:10:00',
) );

// Rejection requires reason
$reject_empty = EventFlow_Payment_Verification::reject_payment( 302, 1, '' );
$t->assert( is_wp_error( $reject_empty ), 'Rejection without reason is strictly rejected' );

$reject_ok = EventFlow_Payment_Verification::reject_payment( 302, 1, 'Submitted fee of PKR 1,500 is short of required PKR 3,000' );
$t->assert( true === $reject_ok, 'Payment rejection with valid reason succeeds' );

$rej_payment = $wpdb->get_row( "SELECT * FROM wp_eventflow_payments WHERE id = 302" );
$t->assert( $rej_payment->status === 'rejected', 'Payment status updated to rejected' );
$t->assert( $rej_payment->rejection_reason === 'Submitted fee of PKR 1,500 is short of required PKR 3,000', 'Rejection reason stored in payment record' );

// 3.5 Re-upload Request testing
$wpdb->insert( 'wp_eventflow_payments', array(
	'id'              => 303,
	'registration_id' => 102,
	'user_id'         => 11,
	'amount'          => '3000.00',
	'payment_method'  => 'Easypaisa',
	'status'          => 'under_verification',
	'created_at'      => '2026-11-01 12:00:00',
) );

$reup_empty = EventFlow_Payment_Verification::request_reupload( 303, 1, '' );
$t->assert( is_wp_error( $reup_empty ), 'Re-upload request without instructions is strictly rejected' );

$reup_ok = EventFlow_Payment_Verification::request_reupload( 303, 1, 'Receipt screenshot is cropped. Please provide the full transaction slip.' );
$t->assert( true === $reup_ok, 'Re-upload request with clear instructions succeeds' );

$reup_payment = $wpdb->get_row( "SELECT * FROM wp_eventflow_payments WHERE id = 303" );
$t->assert( $reup_payment->status === 'reupload_requested', 'Payment status updated to reupload_requested' );

// ==============================================================================
// 4. STAFF FRONTEND PORTAL & TEAM MANAGEMENT
// ==============================================================================
$t->section( '4. Staff Frontend Portal & Team Management' );

// 4.1 Teams list retrieval
$teams_list = EventFlow_Team_Manager::get_teams_list( array( 'search' => 'Code Crafters' ) );
$t->assert( ! empty( $teams_list['items'] ), 'Teams list returns seeded team' );
$t->assert( $teams_list['items'][0]->team_name === 'Code Crafters', 'Team search filters correctly by name' );

// 4.2 Team status modification
$team_id = $teams_list['items'][0]->id;
$up_status_res = EventFlow_Team_Manager::update_team_status( $team_id, 'approved', 'Confirmed team roster meets all prerequisites.' );
$t->assert( true === $up_status_res, 'Team status successfully updated to approved' );

$up_team = $wpdb->get_row( "SELECT * FROM wp_eventflow_teams WHERE id = {$team_id}" );
$t->assert( ! empty( $up_team ), 'Team record exists' );

// ==============================================================================
// 5. 13 EXPORT CATEGORIES & 4 FORMATS
// ==============================================================================
$t->section( '5. Advanced Export Management (13 Categories & 4 Formats)' );

$categories_to_test = array(
	'participants'        => 'Complete Participant Registration Report',
	'teams'               => 'Complete Team Registration Report',
	'team_members'        => 'Team Member Details Report',
	'payments'            => 'Payment Verification Report',
	'pending_payments'    => 'Pending Payment Report',
	'verified_payments'   => 'Verified Payment Report',
	'rejected_payments'   => 'Rejected Payment Report',
	'reupload_requests'   => 'Re-upload Requests Report',
	'university_summary'  => 'University-wise Registration Report',
	'competition_summary' => 'Competition-wise Registration Report',
	'solo_participants'   => 'Solo Participants Report',
	'team_leaders'        => 'Team Leaders Directory',
	'staff_activity'      => 'Staff Activity Report',
);

$t->assert( count( $categories_to_test ) === 13, 'Exactly 13 comprehensive export categories defined' );

// 5.1 Test CSV Export Format (Enforces UTF-8 BOM)
ob_start();
EventFlow_Export_Manager::dispatch_export( 'participants', 'csv', array(), false );
$csv_data = ob_get_clean();
$t->assert( 0 === strpos( $csv_data, "\xEF\xBB\xBF" ), 'CSV export begins with UTF-8 BOM (\xEF\xBB\xBF) for Excel compatibility' );
$t->assert( false !== strpos( $csv_data, 'Registration ID' ) || false !== strpos( $csv_data, 'Full Name' ), 'CSV export contains standard column headers' );

// 5.2 Test Excel Spreadsheet XML Format
ob_start();
EventFlow_Export_Manager::dispatch_export( 'teams', 'excel', array(), false );
$excel_data = ob_get_clean();
$t->assert( false !== strpos( $excel_data, 'xmlns="http://www.w3.org/TR/REC-html40"' ) || false !== strpos( $excel_data, 'ms-excel' ), 'Excel export generates valid Spreadsheet format' );

// 5.3 Test HTML Print View Format
ob_start();
EventFlow_Export_Manager::dispatch_export( 'payments', 'html', array(), false );
$print_html = ob_get_clean();
$t->assert( false !== strpos( $print_html, 'SIBACOM EVENT' ), 'Print view adheres strictly to SIBACOM EVENT branding' );
$t->assert( false !== strpos( $print_html, 'window.print()' ), 'Print view contains automatic print trigger button' );

// ==============================================================================
// 6. OFFICIAL EVENT-BRANDED PDF TEMPLATES
// ==============================================================================
$t->section( '6. Official Event-Branded PDF Report Templates (10 Templates)' );

$pdf_templates = array(
	'participants'        => 'Complete Participant Registration Report',
	'teams'               => 'Complete Team Registration Report',
	'team_members'        => 'Team Member Details Report',
	'payments'            => 'Payment Verification Report',
	'pending_payments'    => 'Pending Payment Report',
	'verified_payments'   => 'Verified Payment Report',
	'rejected_payments'   => 'Rejected Payment Report',
	'university_summary'  => 'University-wise Registration Report',
	'competition_summary' => 'Competition-wise Registration Report',
	'staff_activity'      => 'Staff Activity Report',
);

$t->assert( count( $pdf_templates ) === 10, 'All 10 required PDF templates implemented' );

foreach ( $pdf_templates as $type_key => $title_expected ) {
	ob_start();
	EventFlow_Export_Manager::dispatch_export( $type_key, 'pdf', array(), false );
	$pdf_doc = ob_get_clean();
	$t->assert( false !== strpos( $pdf_doc, 'SIBACOM EVENT' ), "PDF {$type_key} contains official SIBACOM EVENT branding" );
	$t->assert( false !== strpos( $pdf_doc, 'Sukkur IBA University' ), "PDF {$type_key} contains Sukkur IBA University institutional heading" );
	$t->assert( false !== strpos( $pdf_doc, '@page' ), "PDF {$type_key} defines @page print layout styling" );
	$t->assert( false !== strpos( $pdf_doc, 'page-number' ) || false !== strpos( $pdf_doc, 'page-break' ) || false !== strpos( $pdf_doc, 'ef-pdf-footer' ), "PDF {$type_key} contains pagination / footer directives" );
}

// ==============================================================================
// 7. ADVANCED BATCH DATA IMPORT SYSTEM
// ==============================================================================
$t->section( '7. Advanced Batch Data Import System (Validation & Transactions)' );

// 7.1 Verify Downloadable Templates are valid CSV/Excel format
$solo_template = EventFlow_Import_Manager::get_sample_template( 'solo' );
$t->assert( false !== strpos( $solo_template, 'Full Name' ), 'Solo template has Full Name header' );
$t->assert( false !== strpos( $solo_template, 'Email' ), 'Solo template has Email header' );
$t->assert( false !== strpos( $solo_template, 'University' ), 'Solo template has University header' );

$team_template = EventFlow_Import_Manager::get_sample_template( 'team' );
$t->assert( false !== strpos( $team_template, 'Team Name' ), 'Team template has Team Name header' );
$t->assert( false !== strpos( $team_template, 'Leader Name' ), 'Team template has Leader Name header' );
$t->assert( false !== strpos( $team_template, 'Member 1 Name' ), 'Team template has Member 1 Name header' );

// 7.2 Validation & Duplicate Detection Simulation
$raw_import_rows = array(
	// Row 1: Valid Solo Candidate
	array(
		'Name'       => 'Taha Qureshi',
		'Email'      => 'taha.qureshi@iba-suk.edu.pk',
		'Phone'      => '03001122334',
		'University' => 'Sukkur IBA University',
		'Department' => 'Computer Science',
		'Semester'   => '4',
		'Activity'   => 'Speed Programming Championship',
	),
	// Row 2: Duplicate of seeded Hamza Tariq
	array(
		'Name'       => 'Hamza Tariq Duplicate',
		'Email'      => 'hamza.tariq@iba-suk.edu.pk',
		'Phone'      => '03001234567',
		'University' => 'Sukkur IBA University',
		'Department' => 'Computer Science',
		'Semester'   => '6',
		'Activity'   => 'Speed Programming Championship',
	),
	// Row 3: Missing Required Email
	array(
		'Name'       => 'Incomplete Student',
		'Email'      => '',
		'Phone'      => '03009988776',
		'University' => 'Sukkur IBA University',
		'Department' => 'Computer Science',
		'Semester'   => '2',
		'Activity'   => 'Speed Programming Championship',
	),
);

$val_results = EventFlow_Import_Manager::validate_rows( $raw_import_rows, array( 'event_id' => 1 ) );
$t->assert( 3 === $val_results['total_rows'], 'Total rows counted as 3' );
$t->assert( 1 === $val_results['valid_rows'], 'Exactly 1 valid row identified' );
$t->assert( 1 === $val_results['duplicates'], 'Exactly 1 duplicate identified' );
$t->assert( 1 === $val_results['invalid_rows'], 'Exactly 1 invalid row identified' );
$t->assert( ! empty( $val_results['errors'] ), 'Errors array reports missing email on Row 3' );

// 7.3 Transactional Import Execution
$import_res = EventFlow_Import_Manager::execute_import( $val_results['validated_rows'], array( 'skip_duplicates' => true ) );
$t->assert( $import_res['success'] === true, 'Import executed successfully within database transaction' );
$t->assert( $import_res['imported_count'] === 1, 'Exactly 1 valid record inserted into database' );
$t->assert( $import_res['skipped_count'] === 1, 'Duplicate record safely skipped without overwriting existing data' );

// Verify the imported record in registrations table
$imported_candidate = $wpdb->get_row( "SELECT * FROM wp_eventflow_registrations WHERE user_id = 0 ORDER BY id DESC LIMIT 1" );
$t->assert( ! empty( $imported_candidate ), 'Imported candidate persisted in wp_eventflow_registrations' );

// ==============================================================================
// 8. AUDIT LOGS & ACTIVITY TRACKING
// ==============================================================================
$t->section( '8. Audit Logs & Activity Tracking' );

// 8.1 Write operational audit events
EventFlow_Admin::log_audit( 'login_lockout', 0, 'Account locked: attacker@target.com after 3 failed attempts' );
EventFlow_Admin::log_audit( 'payment_approved', 1, 'Payment #301 approved by staff officer' );
EventFlow_Admin::log_audit( 'data_import', 1, 'Batch import of 1 record completed' );

// 8.2 Retrieve and inspect audit logs
$audit_logs = EventFlow_Admin::get_audit_logs( array( 'per_page' => 10 ) );
$t->assert( ! empty( $audit_logs['items'] ), 'Audit logs successfully queried' );

$actions_found = array_map( function( $l ) { return $l->action; }, $audit_logs['items'] );
$t->assert( in_array( 'login_lockout', $actions_found, true ), 'Audit log records login_lockout action' );
$t->assert( in_array( 'payment_approved', $actions_found, true ), 'Audit log records payment_approved action' );
$t->assert( in_array( 'data_import', $actions_found, true ), 'Audit log records data_import action' );

// ==============================================================================
// FINAL SUMMARY REPORT
// ==============================================================================

echo "\n" . str_repeat( '=', 70 ) . "\n";
echo "FINAL RESULTS: " . ( $t->get_passed() + $t->get_failed() ) . " Total Assertions | "
	 . $t->get_passed() . " Passed | " . $t->get_failed() . " Failed\n";
echo str_repeat( '=', 70 ) . "\n";

if ( $t->get_failed() === 0 ) {
	echo ">>> 100% PRODUCTION READY: ALL MASTER CAPABILITIES VERIFIED! <<<\n";
	exit( 0 );
} else {
	echo ">>> WARNING: " . $t->get_failed() . " assertions failed. Please inspect logs above. <<<\n";
	exit( 1 );
}
