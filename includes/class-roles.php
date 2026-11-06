<?php
/**
 * EventFlow Roles & Permissions Manager (RBAC)
 *
 * Centralized Role-Based Access Control and Permission Management Architecture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Roles {

	/**
	 * Register all custom roles and capabilities
	 */
	public static function register_roles() {
		// Define capability lists
		$caps_super_admin = array(
			'read'                           => true,
			'upload_files'                   => true,
			'eventflow_manage_settings'      => true,
			'eventflow_manage_roles'         => true,
			'eventflow_manage_staff'         => true,
			'eventflow_manage_events'        => true,
			'eventflow_manage_activities'    => true,
			'eventflow_view_registrations'   => true,
			'eventflow_edit_registrations'   => true,
			'eventflow_delete_registrations' => true,
			'eventflow_manage_teams'         => true,
			'eventflow_verify_payments'      => true,
			'eventflow_view_payments'        => true,
			'eventflow_manage_announcements' => true,
			'eventflow_export_data'          => true,
			'eventflow_import_data'          => true,
			'eventflow_view_analytics'       => true,
			'eventflow_view_audit_logs'      => true,
			'eventflow_manage_emails'        => true,
			'eventflow_staff_access'         => true,
			'eventflow_participant_access'   => true,
		);

		$caps_event_admin = array(
			'read'                           => true,
			'upload_files'                   => true,
			'eventflow_manage_events'        => true,
			'eventflow_manage_activities'    => true,
			'eventflow_view_registrations'   => true,
			'eventflow_edit_registrations'   => true,
			'eventflow_manage_teams'         => true,
			'eventflow_verify_payments'      => true,
			'eventflow_view_payments'        => true,
			'eventflow_manage_announcements' => true,
			'eventflow_export_data'          => true,
			'eventflow_import_data'          => true,
			'eventflow_view_analytics'       => true,
			'eventflow_view_audit_logs'      => true,
			'eventflow_staff_access'         => true,
			'eventflow_participant_access'   => true,
		);

		$caps_verification_staff = array(
			'read'                      => true,
			'upload_files'              => true,
			'eventflow_verify_payments' => true,
			'eventflow_view_payments'   => true,
			'eventflow_export_data'     => true, // Payments export only
			'eventflow_staff_access'    => true,
		);

		$caps_registration_staff = array(
			'read'                         => true,
			'eventflow_view_registrations' => true,
			'eventflow_edit_registrations' => true,
			'eventflow_manage_teams'       => true,
			'eventflow_export_data'        => true,
			'eventflow_view_analytics'     => true,
			'eventflow_staff_access'       => true,
		);

		$caps_team_staff = array(
			'read'                         => true,
			'eventflow_view_registrations' => true,
			'eventflow_edit_registrations' => true,
			'eventflow_manage_teams'       => true,
			'eventflow_export_data'        => true,
			'eventflow_view_analytics'     => true,
			'eventflow_staff_access'       => true,
		);

		$caps_tech_team = array(
			'read'                        => true,
			'upload_files'                => true,
			'eventflow_manage_activities' => true,
			'eventflow_manage_announcements' => true,
			'eventflow_staff_access'      => true,
		);

		$caps_participant = array(
			'read'                         => true,
			'upload_files'                 => true,
			'eventflow_participant_access' => true,
		);

		$caps_team_member = array(
			'read'                         => true,
			'upload_files'                 => true,
			'eventflow_participant_access' => true,
		);

		// Add custom roles
		add_role( 'eventflow_super_admin', __( 'EventFlow Super Administrator', 'eventflow' ), $caps_super_admin );
		add_role( 'eventflow_event_admin', __( 'EventFlow Event Administrator', 'eventflow' ), $caps_event_admin );
		add_role( 'eventflow_payment_staff', __( 'EventFlow Verification Staff', 'eventflow' ), $caps_verification_staff );
		add_role( 'eventflow_verification_staff', __( 'EventFlow Verification Staff', 'eventflow' ), $caps_verification_staff );
		add_role( 'eventflow_registration_staff', __( 'EventFlow Registration Staff', 'eventflow' ), $caps_registration_staff );
		add_role( 'eventflow_team_staff', __( 'EventFlow Team Management Staff', 'eventflow' ), $caps_team_staff );
		add_role( 'eventflow_tech_team', __( 'EventFlow Technical Team', 'eventflow' ), $caps_tech_team );
		add_role( 'eventflow_participant', __( 'EventFlow Participant / Team Leader', 'eventflow' ), $caps_participant );
		add_role( 'eventflow_team_member', __( 'EventFlow Team Member', 'eventflow' ), $caps_team_member );

		// Also add all capabilities to standard WordPress 'administrator'
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			foreach ( $caps_super_admin as $cap => $grant ) {
				$admin_role->add_cap( $cap, $grant );
			}
		}
	}

	/**
	 * Centralized capability check
	 *
	 * @param string $capability
	 * @param int    $user_id
	 * @return bool
	 */
	public static function can( $capability, $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return false;
		}

		if ( function_exists( 'user_can' ) && user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		return function_exists( 'user_can' ) ? user_can( $user_id, $capability ) : false;
	}

	/**
	 * Check if user is Super Admin or Site Administrator
	 *
	 * @param int $user_id
	 * @return bool
	 */
	public static function is_super_admin( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return false;
		}

		if ( 1 === (int) $user_id ) {
			return true;
		}

		if ( function_exists( 'user_can' ) && ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'eventflow_manage_settings' ) ) ) {
			return true;
		}

		$user = function_exists( 'get_userdata' ) ? get_userdata( $user_id ) : null;
		if ( ( ! $user || empty( $user->roles ) ) && function_exists( 'wp_get_current_user' ) ) {
			$cur = wp_get_current_user();
			if ( $cur && (int) $cur->ID === (int) $user_id && ! empty( $cur->roles ) ) {
				$user = $cur;
			}
		}

		if ( ! $user || empty( $user->roles ) ) {
			return false;
		}

		$super_roles = array( 'administrator', 'eventflow_super_admin' );
		return (bool) array_intersect( (array) $user->roles, $super_roles );
	}

	/**
	 * Check if user can access the staff dashboard
	 *
	 * @param int $user_id
	 * @return bool
	 */
	public static function can_access_staff_dashboard( $user_id = 0 ) {
		return self::is_staff( $user_id );
	}

	/**
	 * Check if user is any staff role or admin
	 *
	 * @param int $user_id
	 * @return bool
	 */
	public static function is_staff( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return false;
		}

		if ( 1 === (int) $user_id ) {
			return true;
		}

		if ( function_exists( 'user_can' ) && ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'eventflow_staff_access' ) ) ) {
			return true;
		}

		if ( function_exists( 'current_user_can' ) && get_current_user_id() === (int) $user_id ) {
			if ( current_user_can( 'manage_options' ) || current_user_can( 'eventflow_verify_payments' ) || current_user_can( 'eventflow_staff_access' ) ) {
				return true;
			}
		}

		$user = function_exists( 'get_userdata' ) ? get_userdata( $user_id ) : null;
		if ( ( ! $user || empty( $user->roles ) ) && function_exists( 'wp_get_current_user' ) ) {
			$cur = wp_get_current_user();
			if ( $cur && (int) $cur->ID === (int) $user_id && ! empty( $cur->roles ) ) {
				$user = $cur;
			}
		}

		if ( ! $user || empty( $user->roles ) ) {
			return false;
		}

		$staff_roles = array(
			'administrator',
			'eventflow_super_admin',
			'eventflow_event_admin',
			'eventflow_payment_staff',
			'eventflow_verification_staff',
			'eventflow_registration_staff',
			'eventflow_team_staff',
			'eventflow_tech_team',
		);

		return (bool) array_intersect( $user->roles, $staff_roles );
	}

	/**
	 * Check if user is a participant or team leader
	 *
	 * @param int $user_id
	 * @return bool
	 */
	public static function is_participant( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->roles ) ) {
			return false;
		}

		return in_array( 'eventflow_participant', $user->roles, true )
			|| in_array( 'eventflow_team_member', $user->roles, true )
			|| in_array( 'subscriber', $user->roles, true );
	}

	/**
	 * Check if user is a team member
	 *
	 * @param int $user_id
	 * @return bool
	 */
	public static function is_team_member( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->roles ) ) {
			return false;
		}

		return in_array( 'eventflow_team_member', $user->roles, true );
	}

	/**
	 * Check if user can access a specific view in staff portal
	 *
	 * @param string $view
	 * @param int    $user_id
	 * @return bool
	 */
	public static function can_access_view( $view, $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return false;
		}

		if ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'eventflow_manage_settings' ) ) {
			return true;
		}

		switch ( $view ) {
			case 'overview':
				return self::can( 'eventflow_view_analytics', $user_id ) || self::can( 'eventflow_staff_access', $user_id );

			case 'registrations':
				return self::can( 'eventflow_view_registrations', $user_id );

			case 'teams':
				return self::can( 'eventflow_manage_teams', $user_id ) || self::can( 'eventflow_view_registrations', $user_id );

			case 'payments':
				return self::can( 'eventflow_verify_payments', $user_id ) || self::can( 'eventflow_view_payments', $user_id );

			case 'activities':
				return self::can( 'eventflow_manage_activities', $user_id );

			case 'announcements':
				return self::can( 'eventflow_manage_announcements', $user_id );

			case 'export':
				return self::can( 'eventflow_export_data', $user_id ) || self::can( 'eventflow_verify_payments', $user_id );

			case 'import':
				return self::can( 'eventflow_import_data', $user_id ) || self::can( 'eventflow_manage_events', $user_id );

			case 'logs':
				return self::can( 'eventflow_view_audit_logs', $user_id );

			case 'staff':
				return self::can( 'eventflow_manage_staff', $user_id );

			case 'settings':
				return self::can( 'eventflow_manage_settings', $user_id );

			default:
				return false;
		}
	}

	/**
	 * Get allowed views for a user in the staff dashboard
	 *
	 * @param int $user_id
	 * @return array
	 */
	public static function get_allowed_views( $user_id = 0 ) {
		$views = array( 'overview', 'registrations', 'teams', 'payments', 'activities', 'announcements', 'export', 'import', 'logs', 'staff', 'settings' );
		$allowed = array();
		foreach ( $views as $v ) {
			if ( self::can_access_view( $v, $user_id ) ) {
				$allowed[] = $v;
			}
		}
		return $allowed;
	}

	/**
	 * Get default starting view for a user role
	 *
	 * @param int $user_id
	 * @return string
	 */
	public static function get_default_view( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->roles ) ) {
			return 'overview';
		}

		$roles = (array) $user->roles;

		if ( in_array( 'eventflow_payment_staff', $roles, true ) || in_array( 'eventflow_verification_staff', $roles, true ) ) {
			return 'payments';
		}
		if ( in_array( 'eventflow_tech_team', $roles, true ) ) {
			return 'activities';
		}
		if ( in_array( 'eventflow_team_staff', $roles, true ) ) {
			return 'teams';
		}
		if ( in_array( 'eventflow_registration_staff', $roles, true ) ) {
			return 'registrations';
		}

		return 'overview';
	}

	/**
	 * Get staff role display name
	 *
	 * @param int $user_id
	 * @return string
	 */
	public static function get_role_label( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->roles ) ) {
			return __( 'Guest', 'eventflow' );
		}

		$map = array(
			'administrator'                => __( 'Master Administrator', 'eventflow' ),
			'eventflow_super_admin'        => __( 'Super Administrator', 'eventflow' ),
			'eventflow_event_admin'        => __( 'Event Administrator', 'eventflow' ),
			'eventflow_payment_staff'      => __( 'Verification Staff', 'eventflow' ),
			'eventflow_verification_staff' => __( 'Verification Staff', 'eventflow' ),
			'eventflow_registration_staff' => __( 'Registration Staff', 'eventflow' ),
			'eventflow_team_staff'         => __( 'Team Management Staff', 'eventflow' ),
			'eventflow_tech_team'          => __( 'Technical Team', 'eventflow' ),
			'eventflow_participant'        => __( 'Participant / Team Leader', 'eventflow' ),
			'eventflow_team_member'        => __( 'Team Member', 'eventflow' ),
		);

		foreach ( $user->roles as $role ) {
			if ( isset( $map[ $role ] ) ) {
				return $map[ $role ];
			}
		}

		return ucfirst( reset( $user->roles ) );
	}

	/**
	 * Get all defined roles in the system
	 *
	 * @return array
	 */
	public static function get_all_roles() {
		return array(
			'administrator'          => __( 'Super Administrator', 'eventflow' ),
			'eventflow_super_admin'  => __( 'Super Administrator', 'eventflow' ),
			'eventflow_admin'        => __( 'Event Administrator', 'eventflow' ),
			'eventflow_payment_staff'=> __( 'Verification Staff', 'eventflow' ),
			'eventflow_reg_staff'    => __( 'Registration Staff', 'eventflow' ),
			'eventflow_team_staff'   => __( 'Team Management Staff', 'eventflow' ),
			'eventflow_participant'  => __( 'Participant / Team Leader', 'eventflow' ),
			'eventflow_team_member'  => __( 'Team Member', 'eventflow' ),
		);
	}

	/**
	 * Get staff roles definition list for admin selects
	 *
	 * @return array
	 */
	public static function get_staff_roles() {
		return array(
			'eventflow_super_admin'        => __( 'Super Administrator (Full System Access)', 'eventflow' ),
			'eventflow_event_admin'        => __( 'Event Administrator (Events, Activities, Registrations & Payments)', 'eventflow' ),
			'eventflow_payment_staff'      => __( 'Verification Staff (Payment Verification & Proof Inspection)', 'eventflow' ),
			'eventflow_registration_staff' => __( 'Registration Staff (Registrations Review & Data Export)', 'eventflow' ),
			'eventflow_team_staff'         => __( 'Team Management Staff (Team Rosters & Approvals)', 'eventflow' ),
			'eventflow_tech_team'          => __( 'Technical Team (Activities, Competitions & Rules)', 'eventflow' ),
		);
	}
}
