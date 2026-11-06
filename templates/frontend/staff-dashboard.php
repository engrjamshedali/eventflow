<?php
/**
 * EventFlow Frontend Staff & Admin Management Dashboard
 *
 * Dedicated portal allowing event administrators and verification staff
 * to manage all operations without ever accessing wp-admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user = wp_get_current_user();
$roles = (array) $current_user->roles;
$is_super_admin = EventFlow_Roles::is_super_admin( $current_user->ID );
$is_tech_team = in_array( 'eventflow_tech_team', $roles, true ) && ! $is_super_admin;
$is_payment_staff = ( in_array( 'eventflow_payment_staff', $roles, true ) || in_array( 'eventflow_verification_staff', $roles, true ) ) && ! $is_super_admin;
$is_reg_staff = in_array( 'eventflow_registration_staff', $roles, true ) && ! $is_super_admin;
$is_team_staff = in_array( 'eventflow_team_staff', $roles, true ) && ! $is_super_admin;

$default_view = EventFlow_Roles::get_default_view( $current_user->ID );
$active_view  = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : $default_view;

if ( ! EventFlow_Roles::can_access_view( $active_view, $current_user->ID ) ) {
	$active_view = $default_view;
}

$event = isset( $event ) ? $event : EventFlow_Event_Manager::get_active_event();
$role_label = EventFlow_Roles::get_role_label( $current_user->ID );
$staff_base = EventFlow_Shortcodes::get_staff_dashboard_url();

// Capabilities for current staff
$can_manage_events     = EventFlow_Roles::can( 'eventflow_manage_events' );
$can_manage_activities = EventFlow_Roles::can( 'eventflow_manage_activities' );
$can_view_reg          = EventFlow_Roles::can( 'eventflow_view_registrations' );
$can_edit_reg          = EventFlow_Roles::can( 'eventflow_edit_registrations' );
$can_manage_teams      = EventFlow_Roles::can( 'eventflow_manage_teams' );
$can_verify_payments   = EventFlow_Roles::can( 'eventflow_verify_payments' );
$can_export            = EventFlow_Roles::can( 'eventflow_export_data' ) || $can_verify_payments;
$can_import            = EventFlow_Roles::can( 'eventflow_import_data' );
$can_view_logs         = EventFlow_Roles::can( 'eventflow_view_audit_logs' );
$can_manage_staff      = EventFlow_Roles::can( 'eventflow_manage_staff' );
$can_manage_settings   = EventFlow_Roles::can( 'eventflow_manage_settings' );
$can_announcements     = EventFlow_Roles::can( 'eventflow_manage_announcements' );

// KPI Stats
$kpi = EventFlow_Analytics_Manager::get_overview_kpis( $event ? $event->id : 0 );
$currency = $event ? esc_html( $event->currency ) : 'PKR';
?>

<div class="eventflow-app ef-staff-app">
	<!-- Layout Shell -->
	<div class="ef-staff-shell">
		<!-- Sidebar Navigation -->
		<aside class="ef-staff-sidebar">
			<div class="ef-sidebar-header">
				<div class="ef-sb-brand">
					<?php if ( $event && $event->logo_url ) : ?>
						<img src="<?php echo esc_url( $event->logo_url ); ?>" alt="Logo" class="ef-sb-logo">
					<?php endif; ?>
					<div>
						<h3 class="ef-sb-title"><?php echo $event ? esc_html( $event->title ) : esc_html__( 'EventFlow Management', 'eventflow' ); ?></h3>
						<span class="ef-badge ef-badge-primary ef-sb-role"><?php echo esc_html( $role_label ); ?></span>
					</div>
				</div>
			</div>

			<nav class="ef-sidebar-nav">
				<?php if ( ! $is_tech_team ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'view', 'overview', $staff_base ) ); ?>" class="ef-sb-link <?php echo 'overview' === $active_view ? 'active' : ''; ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
						<span><?php esc_html_e( 'Overview & Analytics', 'eventflow' ); ?></span>
					</a>
				<?php endif; ?>

				<?php if ( $can_view_reg && ! $is_payment_staff && ! $is_tech_team ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'view', 'registrations', $staff_base ) ); ?>" class="ef-sb-link <?php echo 'registrations' === $active_view ? 'active' : ''; ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
						<span><?php esc_html_e( 'Registrations', 'eventflow' ); ?></span>
						<span class="ef-sb-count"><?php echo esc_html( $kpi['total_registrations'] ); ?></span>
					</a>
				<?php endif; ?>

				<?php if ( ( $can_manage_teams || $can_view_reg ) && ! $is_payment_staff && ! $is_tech_team ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'view', 'teams', $staff_base ) ); ?>" class="ef-sb-link <?php echo 'teams' === $active_view ? 'active' : ''; ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
						<span><?php esc_html_e( 'Teams & Rosters', 'eventflow' ); ?></span>
						<span class="ef-sb-count"><?php echo esc_html( $kpi['team_registrations'] ); ?></span>
					</a>
				<?php endif; ?>

				<?php if ( $can_verify_payments && ! $is_tech_team ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'view', 'payments', $staff_base ) ); ?>" class="ef-sb-link <?php echo 'payments' === $active_view ? 'active' : ''; ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
						<span><?php esc_html_e( 'Payment Verification', 'eventflow' ); ?></span>
						<?php if ( $kpi['under_verification_payments'] > 0 ) : ?>
							<span class="ef-sb-badge-alert"><?php echo esc_html( $kpi['under_verification_payments'] ); ?></span>
						<?php endif; ?>
					</a>
				<?php endif; ?>

				<?php if ( $can_manage_activities && ! $is_payment_staff ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'view', 'activities', $staff_base ) ); ?>" class="ef-sb-link <?php echo 'activities' === $active_view ? 'active' : ''; ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
						<span><?php esc_html_e( 'Activities & Form Builder', 'eventflow' ); ?></span>
					</a>
				<?php endif; ?>

				<?php if ( $can_announcements && ! $is_payment_staff ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'view', 'announcements', $staff_base ) ); ?>" class="ef-sb-link <?php echo 'announcements' === $active_view ? 'active' : ''; ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
						<span><?php esc_html_e( 'Announcements', 'eventflow' ); ?></span>
					</a>
				<?php endif; ?>

				<?php if ( $can_export && ! $is_tech_team ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'view', 'export', $staff_base ) ); ?>" class="ef-sb-link <?php echo 'export' === $active_view ? 'active' : ''; ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
						<span><?php echo $is_payment_staff ? esc_html__( 'Payment Reports', 'eventflow' ) : esc_html__( 'Data & Reports', 'eventflow' ); ?></span>
					</a>
				<?php endif; ?>

				<?php if ( $can_view_logs || $is_super_admin ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'view', 'logs', $staff_base ) ); ?>" class="ef-sb-link <?php echo 'logs' === $active_view ? 'active' : ''; ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
						<span><?php esc_html_e( 'Audit & Security Logs', 'eventflow' ); ?></span>
					</a>
				<?php endif; ?>

				<?php if ( $can_manage_staff && $is_super_admin ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'view', 'staff', $staff_base ) ); ?>" class="ef-sb-link <?php echo 'staff' === $active_view ? 'active' : ''; ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
						<span><?php esc_html_e( 'Staff & Permissions', 'eventflow' ); ?></span>
					</a>
				<?php endif; ?>

				<?php if ( $can_manage_settings && $is_super_admin ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'view', 'settings', $staff_base ) ); ?>" class="ef-sb-link <?php echo 'settings' === $active_view ? 'active' : ''; ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
						<span><?php esc_html_e( 'System Settings', 'eventflow' ); ?></span>
					</a>
				<?php endif; ?>
			</nav>

			<div class="ef-sidebar-footer">
				<div class="ef-user-profile-widget">
					<div class="ef-user-avatar ef-avatar-sm">
						<?php echo esc_html( strtoupper( substr( $current_user->display_name, 0, 2 ) ) ); ?>
					</div>
					<div class="ef-up-info">
						<strong><?php echo esc_html( $current_user->display_name ); ?></strong>
						<span class="ef-text-xs"><?php echo esc_html( $role_label ); ?></span>
					</div>
				</div>
				<a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="ef-btn ef-btn-outline ef-btn-xs ef-btn-block" style="margin-top: 10px;">
					<?php esc_html_e( 'Sign Out', 'eventflow' ); ?>
				</a>
			</div>
		</aside>

		<!-- Main Workspace Area -->
		<div class="ef-staff-content">
			<!-- Header Banner -->
			<header class="ef-staff-topbar ef-flex-between">
				<div class="ef-topbar-left" style="display:flex; align-items:center; gap:12px;">
					<button type="button" id="ef-mobile-toggle-btn" class="ef-mobile-menu-btn" aria-label="<?php esc_attr_e( 'Toggle navigation', 'eventflow' ); ?>">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
					</button>
					<h2 class="ef-page-heading">
						<?php
						$titles = array(
							'overview'      => __( 'Command Center & Analytics', 'eventflow' ),
							'registrations' => __( 'Applications & Registrations', 'eventflow' ),
							'teams'         => __( 'Teams & Roster Management', 'eventflow' ),
							'payments'      => __( 'Payment Verification Dashboard', 'eventflow' ),
							'activities'    => __( 'Competitions & Activity Setup', 'eventflow' ),
							'announcements' => __( 'Event Announcements', 'eventflow' ),
							'export'        => __( 'Reporting & Data Management', 'eventflow' ),
							'logs'          => __( 'Audit Trail & Activity Logs', 'eventflow' ),
							'staff'         => __( 'Staff Accounts & RBAC', 'eventflow' ),
							'settings'      => __( 'EventFlow Master Settings', 'eventflow' ),
						);
						echo isset( $titles[ $active_view ] ) ? esc_html( $titles[ $active_view ] ) : esc_html__( 'Staff Portal', 'eventflow' );
						?>
					</h2>
				</div>

				<div class="ef-topbar-actions">
					<a href="<?php echo esc_url( EventFlow_Shortcodes::get_dashboard_url() ); ?>" class="ef-btn ef-btn-secondary ef-btn-sm">
						👤 <?php esc_html_e( 'Participant View', 'eventflow' ); ?>
					</a>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ef-btn ef-btn-outline ef-btn-sm" target="_blank">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
						<?php esc_html_e( 'Public Portal', 'eventflow' ); ?>
					</a>
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-dashboard' ) ); ?>" class="ef-btn ef-btn-outline ef-btn-sm">
							⚙️ <?php esc_html_e( 'WP Admin', 'eventflow' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</header>

			<!-- Toast Notification Container -->
			<div id="ef-toast-container" class="ef-toast-container"></div>

			<!-- Dynamic View Router -->
			<div class="ef-workspace-view">

				<!-- 1. OVERVIEW & ANALYTICS VIEW -->
				<?php if ( 'overview' === $active_view ) : ?>
					<!-- 14 Real Database KPI Cards -->
					<div class="ef-kpi-grid">
						<div class="ef-kpi-card">
							<span class="ef-kpi-title"><?php esc_html_e( 'Total Registrations', 'eventflow' ); ?></span>
							<div class="ef-kpi-value"><?php echo esc_html( $kpi['total_registrations'] ); ?></div>
							<span class="ef-kpi-sub"><?php printf( esc_html__( '%d Solo • %d Teams', 'eventflow' ), $kpi['solo_registrations'], $kpi['team_registrations'] ); ?></span>
						</div>

						<div class="ef-kpi-card">
							<span class="ef-kpi-title"><?php esc_html_e( 'Total Participants', 'eventflow' ); ?></span>
							<div class="ef-kpi-value ef-text-primary"><?php echo esc_html( $kpi['total_participants'] ); ?></div>
							<span class="ef-kpi-sub"><?php esc_html_e( 'Registered candidates', 'eventflow' ); ?></span>
						</div>

						<div class="ef-kpi-card">
							<span class="ef-kpi-title"><?php esc_html_e( 'Total Teams', 'eventflow' ); ?></span>
							<div class="ef-kpi-value"><?php echo esc_html( $kpi['total_teams'] ); ?></div>
							<span class="ef-kpi-sub"><?php esc_html_e( 'Active team rosters', 'eventflow' ); ?></span>
						</div>

						<div class="ef-kpi-card">
							<span class="ef-kpi-title"><?php esc_html_e( 'Solo Registrations', 'eventflow' ); ?></span>
							<div class="ef-kpi-value"><?php echo esc_html( $kpi['solo_registrations'] ); ?></div>
							<span class="ef-kpi-sub"><?php esc_html_e( 'Individual entries', 'eventflow' ); ?></span>
						</div>

						<div class="ef-kpi-card">
							<span class="ef-kpi-title"><?php esc_html_e( 'Team Registrations', 'eventflow' ); ?></span>
							<div class="ef-kpi-value"><?php echo esc_html( $kpi['team_registrations'] ); ?></div>
							<span class="ef-kpi-sub"><?php esc_html_e( 'Multi-member entries', 'eventflow' ); ?></span>
						</div>

						<div class="ef-kpi-card">
							<span class="ef-kpi-title"><?php esc_html_e( 'Total Payments Submitted', 'eventflow' ); ?></span>
							<div class="ef-kpi-value"><?php echo esc_html( $kpi['total_payments_submitted'] ); ?></div>
							<span class="ef-kpi-sub"><?php esc_html_e( 'Receipts uploaded', 'eventflow' ); ?></span>
						</div>

						<div class="ef-kpi-card">
							<span class="ef-kpi-title"><?php esc_html_e( 'Under Verification', 'eventflow' ); ?></span>
							<div class="ef-kpi-value ef-text-warning"><?php echo esc_html( $kpi['under_verification_payments'] ); ?></div>
							<span class="ef-kpi-sub"><?php esc_html_e( 'Pending review', 'eventflow' ); ?></span>
						</div>

						<div class="ef-kpi-card">
							<span class="ef-kpi-title"><?php esc_html_e( 'Verified Payments', 'eventflow' ); ?></span>
							<div class="ef-kpi-value ef-text-success"><?php echo esc_html( $kpi['verified_payments'] ); ?></div>
							<span class="ef-kpi-sub"><?php esc_html_e( 'Approved & confirmed', 'eventflow' ); ?></span>
						</div>

						<div class="ef-kpi-card">
							<span class="ef-kpi-title"><?php esc_html_e( 'Rejected Payments', 'eventflow' ); ?></span>
							<div class="ef-kpi-value ef-text-danger"><?php echo esc_html( $kpi['rejected_payments'] ); ?></div>
							<span class="ef-kpi-sub"><?php esc_html_e( 'Declined with reason', 'eventflow' ); ?></span>
						</div>

						<div class="ef-kpi-card">
							<span class="ef-kpi-title"><?php esc_html_e( 'Re-upload Requested', 'eventflow' ); ?></span>
							<div class="ef-kpi-value ef-text-warning"><?php echo esc_html( $kpi['reupload_requested_payments'] ); ?></div>
							<span class="ef-kpi-sub"><?php esc_html_e( 'Awaiting new proof', 'eventflow' ); ?></span>
						</div>

						<div class="ef-kpi-card">
							<span class="ef-kpi-title"><?php esc_html_e( 'Total Expected Revenue', 'eventflow' ); ?></span>
							<div class="ef-kpi-value"><?php echo sprintf( '%s %s', esc_html( $currency ), number_format( $kpi['total_expected_revenue'], 0 ) ); ?></div>
							<span class="ef-kpi-sub"><?php esc_html_e( 'Expected gross fee', 'eventflow' ); ?></span>
						</div>

						<div class="ef-kpi-card">
							<span class="ef-kpi-title"><?php esc_html_e( 'Total Verified Revenue', 'eventflow' ); ?></span>
							<div class="ef-kpi-value ef-text-success"><?php echo sprintf( '%s %s', esc_html( $currency ), number_format( $kpi['total_verified_revenue'], 0 ) ); ?></div>
							<span class="ef-kpi-sub"><?php printf( esc_html__( '%d Payments confirmed', 'eventflow' ), $kpi['verified_payments'] ); ?></span>
						</div>

						<div class="ef-kpi-card">
							<span class="ef-kpi-title"><?php esc_html_e( 'Pending Revenue', 'eventflow' ); ?></span>
							<div class="ef-kpi-value ef-text-warning"><?php echo sprintf( '%s %s', esc_html( $currency ), number_format( $kpi['pending_revenue'], 0 ) ); ?></div>
							<span class="ef-kpi-sub"><?php esc_html_e( 'Unverified balance', 'eventflow' ); ?></span>
						</div>

						<div class="ef-kpi-card">
							<span class="ef-kpi-title"><?php esc_html_e( 'Active Competitions', 'eventflow' ); ?></span>
							<div class="ef-kpi-value ef-text-primary"><?php echo esc_html( $kpi['total_active_competitions'] ); ?></div>
							<span class="ef-kpi-sub"><?php esc_html_e( 'Published tracks', 'eventflow' ); ?></span>
						</div>
					</div>

					<!-- Analytics Filter Toolbar -->
					<div class="ef-chart-toolbar ef-flex-between" style="margin-top: 28px; margin-bottom: 12px; align-items: center; background: #fff; padding: 12px 18px; border-radius: 8px; border: 1px solid #e2e8f0;">
						<div>
							<h3 style="margin: 0; font-size: 16px; font-weight: 700;"><?php esc_html_e( 'Real-Time Operational Analytics & Performance Charts', 'eventflow' ); ?></h3>
							<span class="ef-text-xs ef-text-muted"><?php esc_html_e( 'Aggregated live from SQL database records without simulated numbers', 'eventflow' ); ?></span>
						</div>
						<div style="display: flex; gap: 10px; align-items: center;">
							<label for="ef-analytics-days" class="ef-text-sm ef-text-muted"><?php esc_html_e( 'Trend Window:', 'eventflow' ); ?></label>
							<select id="ef-analytics-days" class="ef-input ef-input-sm" style="width: auto;">
								<option value="7"><?php esc_html_e( 'Last 7 Days', 'eventflow' ); ?></option>
								<option value="14" selected><?php esc_html_e( 'Last 14 Days', 'eventflow' ); ?></option>
								<option value="30"><?php esc_html_e( 'Last 30 Days', 'eventflow' ); ?></option>
								<option value="60"><?php esc_html_e( 'Last 60 Days', 'eventflow' ); ?></option>
							</select>
							<button type="button" id="ef-refresh-analytics-btn" class="ef-btn ef-btn-outline ef-btn-sm">↻ <?php esc_html_e( 'Refresh Data', 'eventflow' ); ?></button>
						</div>
					</div>

					<!-- 9 Real-Time Interactive Canvas Charts -->
					<!-- Row 1: Daily Trend & Weekly Statistics -->
					<div class="ef-grid ef-grid-2" style="margin-top: 16px;">
						<div class="ef-card">
							<div class="ef-card-header">
								<h4><?php esc_html_e( 'Daily Registrations Velocity', 'eventflow' ); ?></h4>
							</div>
							<div class="ef-card-body">
								<div class="ef-chart-container" id="ef-daily-chart" data-chart='<?php echo esc_attr( wp_json_encode( EventFlow_Analytics_Manager::get_daily_registrations( 14 ) ) ); ?>'>
									<canvas id="ef-daily-canvas" height="220"></canvas>
								</div>
							</div>
						</div>

						<div class="ef-card">
							<div class="ef-card-header">
								<h4><?php esc_html_e( 'Weekly Registrations Statistics (Last 8 Weeks)', 'eventflow' ); ?></h4>
							</div>
							<div class="ef-card-body">
								<div class="ef-chart-container" id="ef-weekly-chart" data-chart='<?php echo esc_attr( wp_json_encode( EventFlow_Analytics_Manager::get_weekly_registrations( 8 ) ) ); ?>'>
									<canvas id="ef-weekly-canvas" height="220"></canvas>
								</div>
							</div>
						</div>
					</div>

					<!-- Row 2: Monthly Statistics & Competitions Distribution -->
					<div class="ef-grid ef-grid-2" style="margin-top: 16px;">
						<div class="ef-card">
							<div class="ef-card-header">
								<h4><?php esc_html_e( 'Monthly Registration Volume (Last 6 Months)', 'eventflow' ); ?></h4>
							</div>
							<div class="ef-card-body">
								<div class="ef-chart-container" id="ef-monthly-chart" data-chart='<?php echo esc_attr( wp_json_encode( EventFlow_Analytics_Manager::get_monthly_registrations( 6 ) ) ); ?>'>
									<canvas id="ef-monthly-canvas" height="220"></canvas>
								</div>
							</div>
						</div>

						<div class="ef-card">
							<div class="ef-card-header">
								<h4><?php esc_html_e( 'Competitions Participation Distribution', 'eventflow' ); ?></h4>
							</div>
							<div class="ef-card-body">
								<div class="ef-chart-container" id="ef-activity-chart" data-chart='<?php echo esc_attr( wp_json_encode( EventFlow_Analytics_Manager::get_activity_distribution() ) ); ?>'>
									<canvas id="ef-activity-canvas" height="220"></canvas>
								</div>
							</div>
						</div>
					</div>

					<!-- Row 3: Payment Verification & Registration Status Distributions -->
					<div class="ef-grid ef-grid-2" style="margin-top: 16px;">
						<div class="ef-card">
							<div class="ef-card-header">
								<h4><?php esc_html_e( 'Payment Verification Distribution', 'eventflow' ); ?></h4>
							</div>
							<div class="ef-card-body">
								<div class="ef-chart-container" id="ef-payment-ver-chart" data-chart='<?php echo esc_attr( wp_json_encode( EventFlow_Analytics_Manager::get_payment_verification_distribution() ) ); ?>'>
									<canvas id="ef-payment-ver-canvas" height="200"></canvas>
								</div>
							</div>
						</div>

						<div class="ef-card">
							<div class="ef-card-header">
								<h4><?php esc_html_e( 'Registration Status Breakdown', 'eventflow' ); ?></h4>
							</div>
							<div class="ef-card-body">
								<div class="ef-chart-container" id="ef-status-chart" data-chart='<?php echo esc_attr( wp_json_encode( EventFlow_Analytics_Manager::get_application_status_distribution() ) ); ?>'>
									<canvas id="ef-status-canvas" height="200"></canvas>
								</div>
							</div>
						</div>
					</div>

					<!-- Row 4: Solo vs Team & Revenue Collection Overview -->
					<div class="ef-grid ef-grid-2" style="margin-top: 16px;">
						<div class="ef-card">
							<div class="ef-card-header">
								<h4><?php esc_html_e( 'Solo vs Team Participation', 'eventflow' ); ?></h4>
							</div>
							<div class="ef-card-body">
								<div class="ef-chart-container" id="ef-solo-team-chart" data-chart='<?php echo esc_attr( wp_json_encode( EventFlow_Analytics_Manager::get_solo_vs_team_distribution() ) ); ?>'>
									<canvas id="ef-solo-team-canvas" height="200"></canvas>
								</div>
							</div>
						</div>

						<div class="ef-card">
							<div class="ef-card-header">
								<h4><?php esc_html_e( 'Revenue Collection Overview', 'eventflow' ); ?></h4>
							</div>
							<div class="ef-card-body">
								<div class="ef-chart-container" id="ef-revenue-chart" data-chart='<?php echo esc_attr( wp_json_encode( EventFlow_Analytics_Manager::get_revenue_collection_overview() ) ); ?>'>
									<canvas id="ef-revenue-canvas" height="200"></canvas>
								</div>
							</div>
						</div>
					</div>

					<!-- Row 5: Payment Method Distribution -->
					<div class="ef-grid ef-grid-1" style="margin-top: 16px;">
						<div class="ef-card">
							<div class="ef-card-header">
								<h4><?php esc_html_e( 'Payment Method Distribution (Bank Transfer vs Mobile Wallets)', 'eventflow' ); ?></h4>
							</div>
							<div class="ef-card-body">
								<div class="ef-chart-container" id="ef-methods-chart" data-chart='<?php echo esc_attr( wp_json_encode( EventFlow_Analytics_Manager::get_payment_method_distribution() ) ); ?>'>
									<canvas id="ef-methods-canvas" height="220"></canvas>
								</div>
							</div>
						</div>
					</div>

						<!-- Recent Activity Feed -->
						<div class="ef-card">
							<div class="ef-card-header ef-flex-between">
								<h4><?php esc_html_e( 'Pending Action Queue', 'eventflow' ); ?></h4>
								<a href="<?php echo esc_url( add_query_arg( array( 'view' => 'payments', 'p_status' => 'under_verification' ), $staff_base ) ); ?>" class="ef-link-sm"><?php esc_html_e( 'Open Queue', 'eventflow' ); ?> &rarr;</a>
							</div>
							<div class="ef-card-body" style="padding: 0;">
								<?php
								$pending_queue = EventFlow_Payment_Verification::get_payments( array( 'status' => 'under_verification', 'per_page' => 5 ) );
								?>
								<?php if ( empty( $pending_queue['items'] ) ) : ?>
									<div class="ef-empty-state" style="padding: 30px;">
										<p><?php esc_html_e( 'No payments currently waiting for verification. All caught up!', 'eventflow' ); ?></p>
									</div>
								<?php else : ?>
									<div class="ef-queue-list">
										<?php foreach ( $pending_queue['items'] as $item ) : ?>
											<div class="ef-queue-row ef-flex-between">
												<div>
													<strong><?php echo esc_html( $item->reg_id ); ?></strong>
													<span>&bull; <?php echo esc_html( $item->activity_title ); ?></span>
													<div class="ef-text-xs ef-text-muted"><?php echo sprintf( '%s via %s (TID: %s)', sprintf( '%s %s', esc_html( $currency ), number_format( $item->amount, 2 ) ), esc_html( $item->payment_method ), esc_html( $item->transaction_id ) ); ?></div>
												</div>
												<button type="button" class="ef-btn ef-btn-primary ef-btn-xs ef-quick-verify-btn" 
														data-payment-id="<?php echo esc_attr( $item->id ); ?>"
														data-reg-id="<?php echo esc_attr( $item->reg_id ); ?>"
														data-activity="<?php echo esc_attr( $item->activity_title ); ?>"
														data-team="<?php echo esc_attr( $item->reg_team_name ? $item->reg_team_name : $item->leader_name ); ?>"
														data-method="<?php echo esc_attr( $item->payment_method ); ?>"
														data-tid="<?php echo esc_attr( $item->transaction_id ); ?>"
														data-amount="<?php echo esc_attr( sprintf( '%s %s', $currency, number_format( $item->amount, 2 ) ) ); ?>"
														data-raw-amount="<?php echo esc_attr( $item->amount ); ?>"
														data-date="<?php echo esc_attr( date_i18n( 'M j, Y', strtotime( $item->payment_date ) ) ); ?>"
														data-raw-date="<?php echo esc_attr( $item->payment_date ); ?>"
														data-notes="<?php echo esc_attr( $item->notes ); ?>"
														data-url="<?php echo esc_url( $item->preview_url ); ?>"
														data-filetype="<?php echo esc_attr( $item->file_type ); ?>"
														data-status="<?php echo esc_attr( $item->status ); ?>">
													<?php esc_html_e( 'Review Proof', 'eventflow' ); ?>
												</button>
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>

				<!-- 2. REGISTRATIONS VIEW -->
				<?php elseif ( 'registrations' === $active_view && $can_view_reg ) : ?>
					<?php
					$filter_search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
					$filter_activity = isset( $_GET['activity_id'] ) ? absint( $_GET['activity_id'] ) : 0;
					$filter_pay      = isset( $_GET['pay_status'] ) ? sanitize_text_field( wp_unslash( $_GET['pay_status'] ) ) : '';
					$filter_app      = isset( $_GET['app_status'] ) ? sanitize_text_field( wp_unslash( $_GET['app_status'] ) ) : '';
					$page            = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

					$reg_data = EventFlow_Registration_Manager::get_all_registrations(
						array(
							'search'             => $filter_search,
							'activity_id'        => $filter_activity,
							'payment_status'     => $filter_pay,
							'application_status' => $filter_app,
							'page'               => $page,
							'per_page'           => 15,
						)
					);
					$activities_list = EventFlow_Activity_Manager::get_activities( array( 'status' => 'all' ) );
					?>

					<div class="ef-card">
						<!-- Filters Bar -->
						<div class="ef-filters-bar">
							<form method="get" action="<?php echo esc_url( $staff_base ); ?>" class="ef-filter-form">
								<input type="hidden" name="view" value="registrations">

								<div class="ef-filter-row">
									<div class="ef-filter-item ef-grow">
										<input type="text" name="s" class="ef-input" placeholder="<?php esc_attr_e( 'Search ID, Team, Leader name or email...', 'eventflow' ); ?>" value="<?php echo esc_attr( $filter_search ); ?>">
									</div>

									<div class="ef-filter-item">
										<select name="activity_id" class="ef-input">
											<option value=""><?php esc_html_e( 'All Competitions', 'eventflow' ); ?></option>
											<?php foreach ( $activities_list as $a ) : ?>
												<option value="<?php echo esc_attr( $a->id ); ?>" <?php selected( $filter_activity, $a->id ); ?>><?php echo esc_html( $a->title ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>

									<div class="ef-filter-item">
										<select name="pay_status" class="ef-input">
											<option value=""><?php esc_html_e( 'All Payment Statuses', 'eventflow' ); ?></option>
											<option value="pending" <?php selected( $filter_pay, 'pending' ); ?>><?php esc_html_e( 'Payment Pending', 'eventflow' ); ?></option>
											<option value="under_verification" <?php selected( $filter_pay, 'under_verification' ); ?>><?php esc_html_e( 'Under Verification', 'eventflow' ); ?></option>
											<option value="verified" <?php selected( $filter_pay, 'verified' ); ?>><?php esc_html_e( 'Verified', 'eventflow' ); ?></option>
											<option value="rejected" <?php selected( $filter_pay, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'eventflow' ); ?></option>
										</select>
									</div>

									<div class="ef-filter-item">
										<select name="app_status" class="ef-input">
											<option value=""><?php esc_html_e( 'All App Statuses', 'eventflow' ); ?></option>
											<option value="submitted" <?php selected( $filter_app, 'submitted' ); ?>><?php esc_html_e( 'Submitted', 'eventflow' ); ?></option>
											<option value="under_review" <?php selected( $filter_app, 'under_review' ); ?>><?php esc_html_e( 'Under Review', 'eventflow' ); ?></option>
											<option value="approved" <?php selected( $filter_app, 'approved' ); ?>><?php esc_html_e( 'Approved', 'eventflow' ); ?></option>
											<option value="rejected" <?php selected( $filter_app, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'eventflow' ); ?></option>
										</select>
									</div>

									<button type="submit" class="ef-btn ef-btn-primary"><?php esc_html_e( 'Filter', 'eventflow' ); ?></button>
									<a href="<?php echo esc_url( add_query_arg( 'view', 'registrations', $staff_base ) ); ?>" class="ef-btn ef-btn-outline"><?php esc_html_e( 'Reset', 'eventflow' ); ?></a>
								</div>
							</form>
						</div>

						<!-- Table -->
						<div class="ef-table-responsive">
							<table class="ef-table ef-table-hover">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Reg ID', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Track / Competition', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Team / Candidate', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Leader Contact', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Fee', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Payment', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Application', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Actions', 'eventflow' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php if ( empty( $reg_data['items'] ) ) : ?>
										<tr>
											<td colspan="8" class="ef-text-center" style="padding: 40px;">
												<div class="ef-empty-icon">🔍</div>
												<p><?php esc_html_e( 'No registration records match your filter criteria.', 'eventflow' ); ?></p>
											</td>
										</tr>
									<?php else : ?>
										<?php foreach ( $reg_data['items'] as $item ) : ?>
											<?php
											$is_team = 'team' === $item->participation_type;
											$clean_phone = preg_replace( '/[^0-9]/', '', $item->leader_phone );
											?>
											<tr class="ef-clickable-row" data-reg-id="<?php echo esc_attr( $item->reg_id ); ?>" style="cursor: pointer;" title="<?php esc_attr_e( 'Click to view team & participant details', 'eventflow' ); ?>">
												<td>
													<a href="#" class="ef-view-reg-btn ef-reg-id-link" data-reg-id="<?php echo esc_attr( $item->reg_id ); ?>" style="text-decoration: none; color: #0f766e; font-weight: 700;">
														<strong class="ef-monospace"><?php echo esc_html( $item->reg_id ); ?></strong>
													</a>
												</td>
												<td><?php echo esc_html( $item->activity_title ); ?></td>
												<td>
													<a href="#" class="ef-view-reg-btn" data-reg-id="<?php echo esc_attr( $item->reg_id ); ?>" style="text-decoration: none; color: inherit;">
														<strong><?php echo esc_html( $item->team_name ? $item->team_name : $item->leader_name ); ?></strong>
														<div class="ef-text-xs ef-text-muted"><?php echo esc_html( ucfirst( $item->participation_type ) ); ?></div>
													</a>
												</td>
												<td>
													<div><a href="mailto:<?php echo esc_attr( $item->leader_email ); ?>" onclick="event.stopPropagation();"><?php echo esc_html( $item->leader_email ); ?></a></div>
													<div class="ef-text-xs ef-text-muted">
														<?php if ( ! empty( $clean_phone ) ) : ?>
															<a href="https://wa.me/<?php echo esc_attr( $clean_phone ); ?>" target="_blank" onclick="event.stopPropagation();" style="color: #059669; text-decoration: none;">
																<?php echo esc_html( $item->leader_phone ); ?>
															</a>
														<?php else : ?>
															<?php echo esc_html( $item->leader_phone ); ?>
														<?php endif; ?>
													</div>
												</td>
												<td><strong class="ef-fee"><?php echo sprintf( '%s %s', esc_html( $currency ), number_format( $item->total_fee, 2 ) ); ?></strong></td>
												<td><?php echo EventFlow_Registration_Manager::get_status_badge( $item->payment_status, 'payment' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
												<td><?php echo EventFlow_Registration_Manager::get_status_badge( $item->application_status, 'app' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
												<td>
													<div class="ef-action-btns">
														<button type="button" class="ef-btn ef-btn-outline ef-btn-xs ef-view-reg-btn" data-reg-id="<?php echo esc_attr( $item->reg_id ); ?>" title="<?php esc_attr_e( 'View Details', 'eventflow' ); ?>">
															<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
														</button>
														<?php if ( $can_edit_reg ) : ?>
															<button type="button" class="ef-btn ef-btn-outline ef-btn-xs ef-edit-status-btn" data-reg-id="<?php echo esc_attr( $item->id ); ?>" data-current-status="<?php echo esc_attr( $item->application_status ); ?>" title="<?php esc_attr_e( 'Update Status', 'eventflow' ); ?>">
																<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="14 2 18 6 7 17 3 17 3 13 14 2"></polygon><line x1="3" y1="22" x2="21" y2="22"></line></svg>
															</button>
														<?php endif; ?>
													</div>
												</td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
							</table>
						</div>

						<!-- Pagination -->
						<?php if ( $reg_data['pages'] > 1 ) : ?>
							<div class="ef-pagination ef-flex-between">
								<span class="ef-text-muted ef-text-xs">
									<?php printf( esc_html__( 'Showing page %d of %d (%d total registrations)', 'eventflow' ), $reg_data['page'], $reg_data['pages'], $reg_data['total'] ); ?>
								</span>
								<div class="ef-page-links">
									<?php for ( $p = 1; $p <= $reg_data['pages']; $p++ ) : ?>
										<a href="<?php echo esc_url( add_query_arg( array( 'view' => 'registrations', 'paged' => $p, 's' => $filter_search ? $filter_search : null, 'activity_id' => $filter_activity ? $filter_activity : null, 'pay_status' => $filter_pay ? $filter_pay : null, 'app_status' => $filter_app ? $filter_app : null ), $staff_base ) ); ?>" class="ef-page-num <?php echo $p === $reg_data['page'] ? 'active' : ''; ?>"><?php echo esc_html( $p ); ?></a>
									<?php endfor; ?>
								</div>
							</div>
						<?php endif; ?>
					</div>

				<!-- 2.5 TEAMS & ROSTER MANAGEMENT VIEW -->
				<?php elseif ( 'teams' === $active_view && ( $can_manage_teams || $can_view_reg ) ) : ?>
					<?php
					$team_search   = isset( $_GET['ts'] ) ? sanitize_text_field( wp_unslash( $_GET['ts'] ) ) : '';
					$team_status   = isset( $_GET['t_status'] ) ? sanitize_text_field( wp_unslash( $_GET['t_status'] ) ) : '';
					$team_activity = isset( $_GET['t_activity'] ) ? absint( $_GET['t_activity'] ) : 0;
					$team_page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

					$activities_list = EventFlow_Activity_Manager::get_activities();
					$teams_data      = EventFlow_Team_Manager::get_teams_list(
						array(
							'search'   => $team_search,
							'status'   => $team_status,
							'activity' => $team_activity,
							'page'     => $team_page,
							'per_page' => 20,
						)
					);
					?>

					<div class="ef-card">
						<div class="ef-filters-bar">
							<form method="get" action="<?php echo esc_url( $staff_base ); ?>" class="ef-filter-form">
								<input type="hidden" name="view" value="teams">
								<div class="ef-filter-row">
									<div class="ef-filter-item ef-grow">
										<input type="text" name="ts" class="ef-input" placeholder="<?php esc_attr_e( 'Search team name, leader name, email, reg ID...', 'eventflow' ); ?>" value="<?php echo esc_attr( $team_search ); ?>">
									</div>
									<div class="ef-filter-item">
										<select name="t_activity" class="ef-input">
											<option value=""><?php esc_html_e( 'All Competitions', 'eventflow' ); ?></option>
											<?php foreach ( $activities_list as $a ) : ?>
												<option value="<?php echo esc_attr( $a->id ); ?>" <?php selected( $team_activity, $a->id ); ?>><?php echo esc_html( $a->title ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="ef-filter-item">
										<select name="t_status" class="ef-input">
											<option value=""><?php esc_html_e( 'All Approval Statuses', 'eventflow' ); ?></option>
											<option value="pending" <?php selected( $team_status, 'pending' ); ?>><?php esc_html_e( 'Pending Approval', 'eventflow' ); ?></option>
											<option value="approved" <?php selected( $team_status, 'approved' ); ?>><?php esc_html_e( 'Approved Teams', 'eventflow' ); ?></option>
											<option value="rejected" <?php selected( $team_status, 'rejected' ); ?>><?php esc_html_e( 'Rejected Teams', 'eventflow' ); ?></option>
										</select>
									</div>
									<div class="ef-filter-actions">
										<button type="submit" class="ef-btn ef-btn-primary ef-btn-sm"><?php esc_html_e( 'Filter Teams', 'eventflow' ); ?></button>
										<a href="<?php echo esc_url( add_query_arg( 'view', 'teams', $staff_base ) ); ?>" class="ef-btn ef-btn-outline ef-btn-sm"><?php esc_html_e( 'Reset', 'eventflow' ); ?></a>
									</div>
								</div>
							</form>
						</div>

						<div class="ef-table-responsive">
							<table class="ef-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Team Name', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Reg ID', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Leader Contact', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Members', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Competition', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'App Status', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Payment', 'eventflow' ); ?></th>
										<th class="ef-text-right"><?php esc_html_e( 'Actions', 'eventflow' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php if ( empty( $teams_data['items'] ) ) : ?>
										<tr>
											<td colspan="8" class="ef-empty-state">
												<div class="ef-empty-icon">👥</div>
												<h4><?php esc_html_e( 'No Teams Found', 'eventflow' ); ?></h4>
												<p><?php esc_html_e( 'No team registrations match your current search and filter criteria.', 'eventflow' ); ?></p>
											</td>
										</tr>
									<?php else : ?>
										<?php foreach ( $teams_data['items'] as $tm ) : ?>
											<tr>
												<td>
													<div class="ef-team-name-cell">
														<strong><?php echo esc_html( $tm->team_name ); ?></strong>
													</div>
												</td>
												<td><code class="ef-tid"><?php echo esc_html( $tm->reg_id ); ?></code></td>
												<td>
													<div><strong><?php echo esc_html( $tm->leader_name ); ?></strong></div>
													<div class="ef-text-xs ef-text-muted"><?php echo esc_html( $tm->leader_email ); ?> • <?php echo esc_html( $tm->leader_phone ); ?></div>
												</td>
												<td>
													<span class="ef-badge ef-badge-neutral"><?php echo esc_html( $tm->member_count ); ?> <?php esc_html_e( 'members', 'eventflow' ); ?></span>
												</td>
												<td><span class="ef-text-sm"><?php echo esc_html( $tm->activity_title ? $tm->activity_title : 'General' ); ?></span></td>
												<td>
													<?php
													$st_badge = 'ef-badge-neutral';
													if ( 'approved' === $tm->application_status ) {
														$st_badge = 'ef-badge-success';
													} elseif ( 'rejected' === $tm->application_status ) {
														$st_badge = 'ef-badge-danger';
													} elseif ( 'pending' === $tm->application_status || 'submitted' === $tm->application_status ) {
														$st_badge = 'ef-badge-warning';
													}
													?>
													<span class="ef-badge <?php echo esc_attr( $st_badge ); ?>"><?php echo esc_html( ucfirst( $tm->application_status ) ); ?></span>
												</td>
												<td>
													<?php
													$p_badge = 'ef-badge-neutral';
													if ( 'verified' === $tm->payment_status ) {
														$p_badge = 'ef-badge-success';
													} elseif ( 'under_verification' === $tm->payment_status ) {
														$p_badge = 'ef-badge-warning';
													} elseif ( 'rejected' === $tm->payment_status ) {
														$p_badge = 'ef-badge-danger';
													}
													?>
													<span class="ef-badge <?php echo esc_attr( $p_badge ); ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $tm->payment_status ) ) ); ?></span>
												</td>
												<td class="ef-text-right">
													<div class="ef-row-actions">
														<button type="button" class="ef-btn ef-btn-outline ef-btn-xs ef-view-reg-btn" data-reg-id="<?php echo esc_attr( $tm->reg_id ); ?>" title="<?php esc_attr_e( 'Inspect Full Roster', 'eventflow' ); ?>">
															👁️ <?php esc_html_e( 'Roster', 'eventflow' ); ?>
														</button>
														<?php if ( 'approved' !== $tm->application_status ) : ?>
															<button type="button" class="ef-btn ef-btn-success ef-btn-xs ef-team-status-action-btn" data-team-id="<?php echo esc_attr( $tm->id ); ?>" data-status="approved" title="<?php esc_attr_e( 'Approve Team', 'eventflow' ); ?>">
																✓ <?php esc_html_e( 'Approve', 'eventflow' ); ?>
															</button>
														<?php endif; ?>
														<?php if ( 'rejected' !== $tm->application_status ) : ?>
															<button type="button" class="ef-btn ef-btn-danger ef-btn-xs ef-team-status-action-btn" data-team-id="<?php echo esc_attr( $tm->id ); ?>" data-status="rejected" title="<?php esc_attr_e( 'Reject Team', 'eventflow' ); ?>">
																✕ <?php esc_html_e( 'Reject', 'eventflow' ); ?>
															</button>
														<?php endif; ?>
													</div>
												</td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
							</table>
						</div>

						<?php if ( ! empty( $teams_data['pages'] ) && $teams_data['pages'] > 1 ) : ?>
							<div class="ef-pagination ef-flex-between">
								<span class="ef-page-info">
									<?php printf( esc_html__( 'Showing page %1$d of %2$d (%3$d total teams)', 'eventflow' ), $teams_data['page'], $teams_data['pages'], $teams_data['total'] ); ?>
								</span>
								<div class="ef-page-links">
									<?php for ( $p = 1; $p <= $teams_data['pages']; $p++ ) : ?>
										<a href="<?php echo esc_url( add_query_arg( array( 'view' => 'teams', 'paged' => $p, 'ts' => $team_search ? $team_search : null, 't_status' => $team_status ? $team_status : null, 't_activity' => $team_activity ? $team_activity : null ), $staff_base ) ); ?>" class="ef-page-num <?php echo $p === $teams_data['page'] ? 'active' : ''; ?>"><?php echo esc_html( $p ); ?></a>
									<?php endfor; ?>
								</div>
							</div>
						<?php endif; ?>
					</div>

				<!-- 3. ADVANCED PAYMENT VERIFICATION VIEW -->
				<?php elseif ( 'payments' === $active_view && $can_verify_payments ) : ?>
					<?php
					$pay_search = isset( $_GET['ps'] ) ? sanitize_text_field( wp_unslash( $_GET['ps'] ) ) : '';
					$pay_filter = isset( $_GET['p_status'] ) ? sanitize_text_field( wp_unslash( $_GET['p_status'] ) ) : 'under_verification';
					$pay_page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

					$payments_data = EventFlow_Payment_Verification::get_payments(
						array(
							'search'   => $pay_search,
							'status'   => $pay_filter,
							'page'     => $pay_page,
							'per_page' => 15,
						)
					);
					?>

					<div class="ef-card">
						<!-- Filters Bar -->
						<div class="ef-filters-bar">
							<form method="get" action="<?php echo esc_url( $staff_base ); ?>" class="ef-filter-form">
								<input type="hidden" name="view" value="payments">

								<div class="ef-filter-row">
									<div class="ef-filter-item ef-grow">
										<input type="text" name="ps" class="ef-input" placeholder="<?php esc_attr_e( 'Search Transaction ID, Reg ID, or Candidate...', 'eventflow' ); ?>" value="<?php echo esc_attr( $pay_search ); ?>">
									</div>

									<div class="ef-filter-item">
										<select name="p_status" class="ef-input">
											<option value="all" <?php selected( $pay_filter, 'all' ); ?>><?php esc_html_e( 'All Verifications', 'eventflow' ); ?></option>
											<option value="under_verification" <?php selected( $pay_filter, 'under_verification' ); ?>><?php esc_html_e( 'Pending Verification (Queue)', 'eventflow' ); ?></option>
											<option value="verified" <?php selected( $pay_filter, 'verified' ); ?>><?php esc_html_e( 'Verified & Approved', 'eventflow' ); ?></option>
											<option value="rejected" <?php selected( $pay_filter, 'rejected' ); ?>><?php esc_html_e( 'Rejected Submissions', 'eventflow' ); ?></option>
											<option value="reupload_requested" <?php selected( $pay_filter, 'reupload_requested' ); ?>><?php esc_html_e( 'Re-upload Requested', 'eventflow' ); ?></option>
										</select>
									</div>

									<button type="submit" class="ef-btn ef-btn-primary"><?php esc_html_e( 'Filter Queue', 'eventflow' ); ?></button>
									<a href="<?php echo esc_url( add_query_arg( 'view', 'payments', $staff_base ) ); ?>" class="ef-btn ef-btn-outline"><?php esc_html_e( 'Reset', 'eventflow' ); ?></a>
								</div>
							</form>
						</div>

						<!-- Payments Table -->
						<div class="ef-table-responsive">
							<table class="ef-table ef-table-hover">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Reg ID', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Candidate / Team', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Activity Track', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Payment Method', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Transaction ID', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Amount', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Submitted Date', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Status', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Screenshot & Action', 'eventflow' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php if ( empty( $payments_data['items'] ) ) : ?>
										<tr>
											<td colspan="9" class="ef-text-center" style="padding: 40px;">
												<div class="ef-empty-icon">💳</div>
												<p><?php esc_html_e( 'No payment submissions in this queue.', 'eventflow' ); ?></p>
											</td>
										</tr>
									<?php else : ?>
										<?php foreach ( $payments_data['items'] as $p ) : ?>
											<tr>
												<td><strong class="ef-monospace"><?php echo esc_html( $p->reg_id ); ?></strong></td>
												<td>
													<strong><?php echo esc_html( $p->reg_team_name ? $p->reg_team_name : $p->leader_name ); ?></strong>
													<div class="ef-text-xs ef-text-muted"><?php echo esc_html( $p->leader_email ); ?></div>
												</td>
												<td><?php echo esc_html( $p->activity_title ); ?></td>
												<td><?php echo esc_html( $p->payment_method ); ?></td>
												<td><code class="ef-tid"><?php echo esc_html( $p->transaction_id ); ?></code></td>
												<td><strong class="ef-fee"><?php echo sprintf( '%s %s', esc_html( $currency ), number_format( $p->amount, 2 ) ); ?></strong></td>
												<td><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $p->payment_date ) ) ); ?></td>
												<td><?php echo EventFlow_Registration_Manager::get_status_badge( $p->status, 'payment' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
												<td>
													<div class="ef-action-btns">
														<button type="button" class="ef-btn ef-btn-primary ef-btn-xs ef-preview-proof-btn" 
																data-payment-id="<?php echo esc_attr( $p->id ); ?>" 
																data-reg-id="<?php echo esc_attr( $p->reg_id ); ?>" 
																data-activity="<?php echo esc_attr( $p->activity_title ); ?>" 
																data-team="<?php echo esc_attr( $p->reg_team_name ? $p->reg_team_name : $p->leader_name ); ?>" 
																data-method="<?php echo esc_attr( $p->payment_method ); ?>" 
																data-tid="<?php echo esc_attr( $p->transaction_id ); ?>" 
																data-amount="<?php echo esc_attr( sprintf( '%s %s', $currency, number_format( $p->amount, 2 ) ) ); ?>" 
																data-raw-amount="<?php echo esc_attr( $p->amount ); ?>" 
																data-date="<?php echo esc_attr( date_i18n( 'M j, Y', strtotime( $p->payment_date ) ) ); ?>" 
																data-raw-date="<?php echo esc_attr( $p->payment_date ); ?>" 
																data-notes="<?php echo esc_attr( $p->notes ); ?>" 
																data-url="<?php echo esc_url( $p->preview_url ); ?>" 
																data-filetype="<?php echo esc_attr( $p->file_type ); ?>" 
																data-status="<?php echo esc_attr( $p->status ); ?>">
															<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
															<?php esc_html_e( 'Inspect', 'eventflow' ); ?>
														</button>
														<button type="button" class="ef-btn ef-btn-outline ef-btn-xs ef-edit-payment-btn" 
																data-payment-id="<?php echo esc_attr( $p->id ); ?>" 
																data-reg-id="<?php echo esc_attr( $p->reg_id ); ?>" 
																data-tid="<?php echo esc_attr( $p->transaction_id ); ?>" 
																data-method="<?php echo esc_attr( $p->payment_method ); ?>" 
																data-amount="<?php echo esc_attr( $p->amount ); ?>" 
																data-date="<?php echo esc_attr( $p->payment_date ); ?>" 
																data-notes="<?php echo esc_attr( $p->notes ); ?>" 
																data-status="<?php echo esc_attr( $p->status ); ?>" 
																title="<?php esc_attr_e( 'Edit Payment Details', 'eventflow' ); ?>">
															✏️
														</button>
														<button type="button" class="ef-btn ef-btn-outline ef-btn-xs ef-payment-history-btn" 
																data-payment-id="<?php echo esc_attr( $p->id ); ?>" 
																data-reg-id="<?php echo esc_attr( $p->reg_id ); ?>" 
																title="<?php esc_attr_e( 'View Payment Audit History', 'eventflow' ); ?>">
															📜
														</button>
													</div>
												</td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
							</table>
						</div>

						<!-- Pagination -->
						<?php if ( $payments_data['pages'] > 1 ) : ?>
							<div class="ef-pagination ef-flex-between">
								<span class="ef-text-muted ef-text-xs">
									<?php printf( esc_html__( 'Page %d of %d (%d submissions)', 'eventflow' ), $payments_data['page'], $payments_data['pages'], $payments_data['total'] ); ?>
								</span>
								<div class="ef-page-links">
									<?php for ( $p = 1; $p <= $payments_data['pages']; $p++ ) : ?>
										<a href="<?php echo esc_url( add_query_arg( array( 'view' => 'payments', 'paged' => $p, 'ps' => $pay_search ? $pay_search : null, 'p_status' => $pay_filter ? $pay_filter : null ), $staff_base ) ); ?>" class="ef-page-num <?php echo $p === $payments_data['page'] ? 'active' : ''; ?>"><?php echo esc_html( $p ); ?></a>
									<?php endfor; ?>
								</div>
							</div>
						<?php endif; ?>
					</div>

				<!-- 4. ACTIVITIES MANAGEMENT VIEW -->
				<?php elseif ( 'activities' === $active_view && $can_manage_activities ) : ?>
					<?php
					$activities_all = EventFlow_Activity_Manager::get_activities( array( 'status' => 'all' ) );
					?>
					<div class="ef-card">
						<div class="ef-card-header ef-flex-between">
							<div>
								<h3><?php esc_html_e( 'Event Activities & Competitions', 'eventflow' ); ?></h3>
								<p class="ef-card-subtitle"><?php esc_html_e( 'Manage competition parameters, eligibility criteria, fees, and team size boundaries.', 'eventflow' ); ?></p>
							</div>
							<button type="button" class="ef-btn ef-btn-primary ef-btn-sm" id="ef-create-activity-btn">
								+ <?php esc_html_e( 'Create New Activity', 'eventflow' ); ?>
							</button>
						</div>

						<div class="ef-table-responsive">
							<table class="ef-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Title', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Type', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Team Limits', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Registration Fee', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Capacity', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Deadline', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Status', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Actions', 'eventflow' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $activities_all as $act ) : ?>
										<tr>
											<td><strong><?php echo esc_html( $act->title ); ?></strong></td>
											<td><span class="ef-tag"><?php echo esc_html( ucfirst( $act->participation_type ) ); ?></span></td>
											<td><?php printf( esc_html__( '%d - %d Members', 'eventflow' ), (int) $act->min_team_size, (int) $act->max_team_size ); ?></td>
											<td><strong class="ef-fee"><?php echo floatval( $act->reg_fee ) > 0 ? sprintf( '%s %s', esc_html( $currency ), number_format( $act->reg_fee, 2 ) ) : esc_html__( 'Free', 'eventflow' ); ?></strong></td>
											<td><?php echo (int) $act->max_participants > 0 ? sprintf( '%d / %d', (int) $act->current_participants, (int) $act->max_participants ) : esc_html__( 'Unlimited', 'eventflow' ); ?></td>
											<td><?php echo ! empty( $act->reg_deadline ) ? esc_html( date_i18n( 'M j, Y', strtotime( $act->reg_deadline ) ) ) : esc_html__( 'Event Default', 'eventflow' ); ?></td>
											<td>
												<span class="ef-badge <?php echo 'published' === $act->status ? 'ef-badge-success' : 'ef-badge-neutral'; ?>">
													<?php echo esc_html( ucfirst( $act->status ) ); ?>
												</span>
											</td>
											<td>
												<div class="ef-action-btns">
													<button type="button" class="ef-btn ef-btn-primary ef-btn-xs ef-open-form-builder-btn" data-id="<?php echo esc_attr( $act->id ); ?>" data-title="<?php echo esc_attr( $act->title ); ?>" title="<?php esc_attr_e( 'Visual Form Builder', 'eventflow' ); ?>">
														📋 <?php esc_html_e( 'Form Builder', 'eventflow' ); ?>
													</button>
													<button type="button" class="ef-btn ef-btn-outline ef-btn-xs ef-edit-activity-btn" data-id="<?php echo esc_attr( $act->id ); ?>">
														<?php esc_html_e( 'Edit', 'eventflow' ); ?>
													</button>
													<button type="button" class="ef-btn ef-btn-danger ef-btn-xs ef-delete-activity-btn" data-id="<?php echo esc_attr( $act->id ); ?>">
														<?php esc_html_e( 'Delete', 'eventflow' ); ?>
													</button>
												</div>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>

				<!-- 5. ANNOUNCEMENTS VIEW -->
				<?php elseif ( 'announcements' === $active_view && $can_announcements ) : ?>
					<?php
					$all_ann = $event ? EventFlow_Event_Manager::get_announcements( $event->id, 50 ) : array();
					?>
					<div class="ef-grid ef-grid-2">
						<div class="ef-card">
							<div class="ef-card-header">
								<h3><?php esc_html_e( 'Broadcast New Announcement', 'eventflow' ); ?></h3>
							</div>
							<form id="ef-announcement-form" class="ef-form" method="post">
								<div class="ef-form-group">
									<label><?php esc_html_e( 'Announcement Title', 'eventflow' ); ?> <span class="ef-req">*</span></label>
									<input type="text" name="title" class="ef-input" placeholder="e.g. Venue Change / Schedule Update" required>
								</div>

								<div class="ef-form-group">
									<label><?php esc_html_e( 'Specific Activity (Optional)', 'eventflow' ); ?></label>
									<select name="activity_id" class="ef-input">
										<option value=""><?php esc_html_e( 'Entire Event (All Participants)', 'eventflow' ); ?></option>
										<?php foreach ( EventFlow_Activity_Manager::get_activities( array( 'status' => 'all' ) ) as $a ) : ?>
											<option value="<?php echo esc_attr( $a->id ); ?>"><?php echo esc_html( $a->title ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="ef-form-group">
									<label><?php esc_html_e( 'Announcement Message', 'eventflow' ); ?> <span class="ef-req">*</span></label>
									<textarea name="content" class="ef-input" rows="4" placeholder="Enter message text here..." required></textarea>
								</div>

								<button type="submit" class="ef-btn ef-btn-primary ef-btn-block">
									<?php esc_html_e( 'Publish Announcement Now', 'eventflow' ); ?>
								</button>
							</form>
						</div>

						<div class="ef-card">
							<div class="ef-card-header">
								<h3><?php esc_html_e( 'Recent Broadcasts', 'eventflow' ); ?></h3>
							</div>
							<div class="ef-card-body">
								<?php if ( empty( $all_ann ) ) : ?>
									<p class="ef-text-muted"><?php esc_html_e( 'No announcements broadcast yet.', 'eventflow' ); ?></p>
								<?php else : ?>
									<div class="ef-ann-feed">
										<?php foreach ( $all_ann as $ann ) : ?>
											<div class="ef-ann-item" id="ef-ann-item-<?php echo esc_attr( $ann->id ); ?>" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;">
												<div style="flex: 1;">
													<strong><?php echo esc_html( $ann->title ); ?></strong>
													<p style="margin: 4px 0;"><?php echo wp_kses_post( $ann->content ); ?></p>
													<span class="ef-text-xs ef-text-muted"><?php echo esc_html( date_i18n( 'M j, Y H:i', strtotime( $ann->created_at ) ) ); ?></span>
												</div>
												<div>
													<button type="button" class="ef-btn ef-btn-danger ef-btn-xs ef-delete-ann-btn" data-id="<?php echo esc_attr( $ann->id ); ?>" title="<?php esc_attr_e( 'Delete Announcement', 'eventflow' ); ?>">
														&times; <?php esc_html_e( 'Delete', 'eventflow' ); ?>
													</button>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>

				<!-- 6. DATA MANAGEMENT: EXPORTS & IMPORTS VIEW -->
				<?php elseif ( 'export' === $active_view && $can_export ) : ?>
					<div class="ef-card">
						<div class="ef-card-header ef-flex-between">
							<div>
								<h3><?php esc_html_e( 'Data Management & Event Intelligence Center', 'eventflow' ); ?></h3>
								<p class="ef-card-subtitle"><?php esc_html_e( 'Export official event-branded PDF reports, Excel datasets, and CSV files, or batch import registrations.', 'eventflow' ); ?></p>
							</div>
							<div class="ef-data-tabs">
								<button type="button" class="ef-btn ef-btn-primary ef-btn-sm ef-data-tab-btn active" data-tab="export">
									📊 <?php esc_html_e( 'Export Reports & Dossiers', 'eventflow' ); ?>
								</button>
								<?php if ( $can_import ) : ?>
									<button type="button" class="ef-btn ef-btn-outline ef-btn-sm ef-data-tab-btn" data-tab="import">
										📥 <?php esc_html_e( 'Batch Data Import', 'eventflow' ); ?>
									</button>
								<?php endif; ?>
							</div>
						</div>

						<!-- TAB 1: EXPORT REPORTS -->
						<div class="ef-data-tab-content" id="ef-data-tab-export">
							<!-- Interactive Export Filters Bar -->
							<div class="ef-filters-bar" style="margin-bottom: 24px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 16px;">
								<div class="ef-filter-row" style="display: flex; gap: 14px; flex-wrap: wrap; align-items: flex-end;">
									<div class="ef-filter-item" style="flex: 1; min-width: 200px;">
										<label class="ef-text-xs ef-text-muted" style="display: block; margin-bottom: 6px; font-weight: 600;"><?php esc_html_e( 'Filter by Activity / Track:', 'eventflow' ); ?></label>
										<select id="ef-export-activity-filter" class="ef-input">
											<option value=""><?php esc_html_e( 'All Competitions & Tracks', 'eventflow' ); ?></option>
											<?php foreach ( EventFlow_Activity_Manager::get_activities( array( 'status' => 'all' ) ) as $act_opt ) : ?>
												<option value="<?php echo esc_attr( $act_opt->id ); ?>"><?php echo esc_html( $act_opt->title ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="ef-filter-item" style="flex: 1; min-width: 180px;">
										<label class="ef-text-xs ef-text-muted" style="display: block; margin-bottom: 6px; font-weight: 600;"><?php esc_html_e( 'Payment Status:', 'eventflow' ); ?></label>
										<select id="ef-export-payment-filter" class="ef-input">
											<option value=""><?php esc_html_e( 'All Payment Statuses', 'eventflow' ); ?></option>
											<option value="verified"><?php esc_html_e( 'Verified Only', 'eventflow' ); ?></option>
											<option value="under_verification"><?php esc_html_e( 'Under Verification Only', 'eventflow' ); ?></option>
											<option value="pending"><?php esc_html_e( 'Pending Payment Only', 'eventflow' ); ?></option>
											<option value="rejected"><?php esc_html_e( 'Rejected Only', 'eventflow' ); ?></option>
											<option value="reupload_requested"><?php esc_html_e( 'Re-upload Requested Only', 'eventflow' ); ?></option>
										</select>
									</div>
									<div class="ef-filter-item" style="flex: 1; min-width: 180px;">
										<label class="ef-text-xs ef-text-muted" style="display: block; margin-bottom: 6px; font-weight: 600;"><?php esc_html_e( 'Application Status:', 'eventflow' ); ?></label>
										<select id="ef-export-app-filter" class="ef-input">
											<option value=""><?php esc_html_e( 'All Application Statuses', 'eventflow' ); ?></option>
											<option value="approved"><?php esc_html_e( 'Approved Applications', 'eventflow' ); ?></option>
											<option value="pending"><?php esc_html_e( 'Pending Review', 'eventflow' ); ?></option>
											<option value="rejected"><?php esc_html_e( 'Rejected Applications', 'eventflow' ); ?></option>
										</select>
									</div>
									<div class="ef-filter-item" style="flex: 1; min-width: 180px;">
										<label class="ef-text-xs ef-text-muted" style="display: block; margin-bottom: 6px; font-weight: 600;"><?php esc_html_e( 'University Name:', 'eventflow' ); ?></label>
										<input type="text" id="ef-export-uni-filter" class="ef-input" placeholder="<?php esc_attr_e( 'e.g. Sukkur IBA...', 'eventflow' ); ?>">
									</div>
								</div>
							</div>

							<!-- 13 Standard Export Category Cards Grid -->
							<div class="ef-grid ef-grid-3 ef-export-cards">
								<?php
								$export_nonce = wp_create_nonce( 'eventflow_export' );
								$categories = array(
									'participants'        => array( 'title' => __( '1. Complete Participant Roster', 'eventflow' ), 'desc' => __( 'Full master roster of all individual and team participants across all competitions.', 'eventflow' ), 'icon' => '👥' ),
									'teams'               => array( 'title' => __( '2. Complete Team Registrations', 'eventflow' ), 'desc' => __( 'List of all registered teams, leader contacts, member counts, and team statuses.', 'eventflow' ), 'icon' => '🛡️' ),
									'team_members'        => array( 'title' => __( '3. Team Member Details (7 Fields)', 'eventflow' ), 'desc' => __( 'Deep roster of every team member with Name, Father, Semester, Dept, Uni, Email, Phone.', 'eventflow' ), 'icon' => '📋' ),
									'payments'            => array( 'title' => __( '4. Payment Verification Ledger', 'eventflow' ), 'desc' => __( 'Complete transaction history with verification timestamps, methods, and officer stamps.', 'eventflow' ), 'icon' => '💰' ),
									'pending_payments'    => array( 'title' => __( '5. Pending Payments Queue', 'eventflow' ), 'desc' => __( 'All transactions awaiting verification or receipts pending submission by participants.', 'eventflow' ), 'icon' => '⏳' ),
									'verified_payments'   => array( 'title' => __( '6. Verified Payments Ledger', 'eventflow' ), 'desc' => __( 'Confirmed transactions with audit reference numbers and approving staff officers.', 'eventflow' ), 'icon' => '✅' ),
									'rejected_payments'   => array( 'title' => __( '7. Rejected Payments Log', 'eventflow' ), 'desc' => __( 'Declined transactions including mandatory administrative rejection reasons.', 'eventflow' ), 'icon' => '❌' ),
									'reupload_requests'   => array( 'title' => __( '8. Re-upload Requests Report', 'eventflow' ), 'desc' => __( 'Payments where participants were requested to re-upload clear proof receipts.', 'eventflow' ), 'icon' => '🔄' ),
									'university_summary'  => array( 'title' => __( '9. University-Wise Registrations', 'eventflow' ), 'desc' => __( 'Aggregated counts and breakdown of participant and team turnout by institution.', 'eventflow' ), 'icon' => '🏛️' ),
									'competition_summary' => array( 'title' => __( '10. Competition-Wise Registrations', 'eventflow' ), 'desc' => __( 'Registrations, participant volume, and revenue totals segmented by category.', 'eventflow' ), 'icon' => '🏆' ),
									'solo_participants'   => array( 'title' => __( '11. Solo Participants Dossier', 'eventflow' ), 'desc' => __( 'Candidates registered exclusively for individual competition challenges.', 'eventflow' ), 'icon' => '👤' ),
									'team_leaders'        => array( 'title' => __( '12. Team Leaders Directory', 'eventflow' ), 'desc' => __( 'Primary contact details, emails, and phone numbers for every team captain.', 'eventflow' ), 'icon' => '⭐' ),
									'staff_activity'      => array( 'title' => __( '13. Staff Activity & Audit Report', 'eventflow' ), 'desc' => __( 'Complete operational audit trail of payment verifications, status changes, and settings.', 'eventflow' ), 'icon' => '📜' ),
								);

								foreach ( $categories as $type_key => $cat ) :
									$pdf_url   = add_query_arg( array( 'eventflow_action' => 'export', 'type' => $type_key, 'format' => 'pdf', '_wpnonce' => $export_nonce ), home_url() );
									$excel_url = add_query_arg( array( 'eventflow_action' => 'export', 'type' => $type_key, 'format' => 'excel', '_wpnonce' => $export_nonce ), home_url() );
									$csv_url   = add_query_arg( array( 'eventflow_action' => 'export', 'type' => $type_key, 'format' => 'csv', '_wpnonce' => $export_nonce ), home_url() );
									$print_url = add_query_arg( array( 'eventflow_action' => 'export', 'type' => $type_key, 'format' => 'html', '_wpnonce' => $export_nonce ), home_url() );
								?>
									<div class="ef-export-box">
										<div class="ef-export-icon"><?php echo esc_html( $cat['icon'] ); ?></div>
										<h4><?php echo esc_html( $cat['title'] ); ?></h4>
										<p><?php echo esc_html( $cat['desc'] ); ?></p>
										<div class="ef-export-btns">
											<a href="<?php echo esc_url( $pdf_url ); ?>" data-base-url="<?php echo esc_url( $pdf_url ); ?>" class="ef-btn ef-btn-danger ef-btn-xs ef-export-download-btn" target="_blank" title="<?php esc_attr_e( 'Official Event-Branded PDF Report', 'eventflow' ); ?>">
												📄 <?php esc_html_e( 'PDF Report', 'eventflow' ); ?>
											</a>
											<a href="<?php echo esc_url( $excel_url ); ?>" data-base-url="<?php echo esc_url( $excel_url ); ?>" class="ef-btn ef-btn-primary ef-btn-xs ef-export-download-btn" title="<?php esc_attr_e( 'Microsoft Excel Spreadsheet', 'eventflow' ); ?>">
												📊 <?php esc_html_e( 'Excel (.xlsx)', 'eventflow' ); ?>
											</a>
											<a href="<?php echo esc_url( $csv_url ); ?>" data-base-url="<?php echo esc_url( $csv_url ); ?>" class="ef-btn ef-btn-outline ef-btn-xs ef-export-download-btn" title="<?php esc_attr_e( 'CSV with UTF-8 BOM', 'eventflow' ); ?>">
												💾 <?php esc_html_e( 'CSV', 'eventflow' ); ?>
											</a>
											<a href="<?php echo esc_url( $print_url ); ?>" data-base-url="<?php echo esc_url( $print_url ); ?>" class="ef-btn ef-btn-outline ef-btn-xs ef-export-download-btn" target="_blank" title="<?php esc_attr_e( 'Print View in Browser', 'eventflow' ); ?>">
												🖨️ <?php esc_html_e( 'Print', 'eventflow' ); ?>
											</a>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

						<!-- TAB 2: ADVANCED DATA IMPORT -->
						<?php if ( $can_import ) : ?>
							<div class="ef-data-tab-content" id="ef-data-tab-import" style="display: none;">
								<!-- Template Download Bar -->
								<div class="ef-import-templates-strip" style="background:#F0FDFA; border:1px solid #CCFBF1; border-radius:8px; padding:16px; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
									<div>
										<h4 style="margin:0 0 4px; color:#0F766E;">📥 <?php esc_html_e( 'Official Data Import Templates', 'eventflow' ); ?></h4>
										<p class="ef-text-xs ef-text-muted" style="margin:0;"><?php esc_html_e( 'Download pre-formatted import templates with required column headers and sample data.', 'eventflow' ); ?></p>
									</div>
									<div class="ef-template-links" style="display:flex; gap:8px;">
										<?php $tpl_solo = add_query_arg( array( 'eventflow_action' => 'import_template', 'type' => 'solo', '_wpnonce' => wp_create_nonce( 'eventflow_import' ) ), home_url() ); ?>
										<a href="<?php echo esc_url( $tpl_solo ); ?>" class="ef-btn ef-btn-outline ef-btn-xs">
											📄 <?php esc_html_e( 'Solo Template (.csv)', 'eventflow' ); ?>
										</a>
										<?php $tpl_team = add_query_arg( array( 'eventflow_action' => 'import_template', 'type' => 'team', '_wpnonce' => wp_create_nonce( 'eventflow_import' ) ), home_url() ); ?>
										<a href="<?php echo esc_url( $tpl_team ); ?>" class="ef-btn ef-btn-outline ef-btn-xs">
											👥 <?php esc_html_e( 'Team Template (.csv)', 'eventflow' ); ?>
										</a>
										<?php $tpl_excel = add_query_arg( array( 'eventflow_action' => 'import_template', 'type' => 'excel', '_wpnonce' => wp_create_nonce( 'eventflow_import' ) ), home_url() ); ?>
										<a href="<?php echo esc_url( $tpl_excel ); ?>" class="ef-btn ef-btn-outline ef-btn-xs">
											📊 <?php esc_html_e( 'Excel Template (.xlsx)', 'eventflow' ); ?>
										</a>
									</div>
								</div>

								<!-- Drag & Drop Upload Zone -->
								<div class="ef-import-zone-wrap">
									<div class="ef-dropzone" id="ef-import-dropzone" style="border:2px dashed #CBD5E1; border-radius:12px; padding:36px; text-align:center; background:#FAFAFA; cursor:pointer; transition:all 0.2s;">
										<div class="ef-dropzone-icon" style="font-size:36px; margin-bottom:12px;">📁</div>
										<h4 style="margin:0 0 6px;"><?php esc_html_e( 'Drag & Drop CSV or Excel File Here', 'eventflow' ); ?></h4>
										<p class="ef-text-sm ef-text-muted" style="margin:0 0 16px;"><?php esc_html_e( 'Supports .csv, .xlsx, and .xls files up to 10MB.', 'eventflow' ); ?></p>
										<input type="file" id="ef-import-file-input" accept=".csv,.xlsx,.xls" style="display:none;">
										<button type="button" class="ef-btn ef-btn-primary ef-btn-sm" onclick="document.getElementById('ef-import-file-input').click();">
											<?php esc_html_e( 'Browse Local File', 'eventflow' ); ?>
										</button>
										<div id="ef-import-selected-filename" class="ef-text-xs ef-text-primary" style="margin-top:10px; font-weight:600; display:none;"></div>
									</div>

									<div style="margin-top:16px; display:flex; justify-content:space-between; align-items:center;">
										<div class="ef-form-check" style="display:flex; align-items:center; gap:8px;">
											<input type="checkbox" id="ef-import-skip-duplicates" checked>
											<label for="ef-import-skip-duplicates" class="ef-text-sm">
												<?php esc_html_e( 'Skip duplicate registrations (Strict Protection: Never overwrite existing confirmed records)', 'eventflow' ); ?>
											</label>
										</div>
										<button type="button" id="ef-btn-inspect-import" class="ef-btn ef-btn-primary" disabled>
											🔍 <?php esc_html_e( 'Inspect & Validate File', 'eventflow' ); ?>
										</button>
									</div>
								</div>

								<!-- Live Inspection & Preview Box (Initially Hidden) -->
								<div id="ef-import-preview-box" class="ef-card" style="display:none; margin-top:24px; border:1px solid #E2E8F0;">
									<div class="ef-card-header ef-flex-between">
										<h4 class="ef-card-title"><?php esc_html_e( 'Import Validation Summary & Field Mapping', 'eventflow' ); ?></h4>
										<span id="ef-import-status-tag" class="ef-badge"></span>
									</div>
									<div class="ef-card-body">
										<!-- Metric Strips -->
										<div class="ef-grid ef-grid-4" style="margin-bottom:20px;">
											<div class="ef-kpi-card" style="padding:14px;">
												<span class="ef-kpi-title"><?php esc_html_e( 'Total Rows', 'eventflow' ); ?></span>
												<div class="ef-kpi-value" id="ef-import-kpi-total">0</div>
											</div>
											<div class="ef-kpi-card" style="padding:14px;">
												<span class="ef-kpi-title"><?php esc_html_e( 'Valid Records', 'eventflow' ); ?></span>
												<div class="ef-kpi-value" id="ef-import-kpi-valid" style="color:#059669;">0</div>
											</div>
											<div class="ef-kpi-card" style="padding:14px;">
												<span class="ef-kpi-title"><?php esc_html_e( 'Duplicates Detected', 'eventflow' ); ?></span>
												<div class="ef-kpi-value" id="ef-import-kpi-dups" style="color:#D97706;">0</div>
											</div>
											<div class="ef-kpi-card" style="padding:14px;">
												<span class="ef-kpi-title"><?php esc_html_e( 'Invalid Rows', 'eventflow' ); ?></span>
												<div class="ef-kpi-value" id="ef-import-kpi-invalid" style="color:#DC2626;">0</div>
											</div>
										</div>

										<!-- Error Reporting Table -->
										<div id="ef-import-errors-wrap" style="display:none; margin-bottom:20px;">
											<h5 style="color:#DC2626; margin:0 0 8px;">⚠️ <?php esc_html_e( 'Row Validation Issues Detected', 'eventflow' ); ?></h5>
											<div class="ef-table-responsive" style="max-height:200px; overflow-y:auto;">
												<table class="ef-table ef-table-sm" id="ef-import-errors-table">
													<thead>
														<tr>
															<th><?php esc_html_e( 'Row #', 'eventflow' ); ?></th>
															<th><?php esc_html_e( 'Candidate / Email', 'eventflow' ); ?></th>
															<th><?php esc_html_e( 'Error Reason', 'eventflow' ); ?></th>
														</tr>
													</thead>
													<tbody id="ef-import-errors-tbody"></tbody>
												</table>
											</div>
										</div>

										<!-- Preview Table -->
										<div class="ef-table-responsive" style="max-height:260px; overflow-y:auto;">
											<table class="ef-table ef-table-sm" id="ef-import-preview-table">
												<thead>
													<tr>
														<th>#</th>
														<th><?php esc_html_e( 'Name / Team', 'eventflow' ); ?></th>
														<th><?php esc_html_e( 'Type', 'eventflow' ); ?></th>
														<th><?php esc_html_e( 'Email', 'eventflow' ); ?></th>
														<th><?php esc_html_e( 'University', 'eventflow' ); ?></th>
														<th><?php esc_html_e( 'Competition', 'eventflow' ); ?></th>
														<th><?php esc_html_e( 'Validation Status', 'eventflow' ); ?></th>
													</tr>
												</thead>
												<tbody id="ef-import-preview-tbody"></tbody>
											</table>
										</div>

										<!-- Confirmation & Execution Bar -->
										<div class="ef-flex-between" style="margin-top:20px; padding-top:16px; border-top:1px solid #E2E8F0;">
											<span class="ef-text-sm ef-text-muted">
												🔒 <?php esc_html_e( 'All import operations run within an ACID database transaction. If any critical database failure occurs, changes are automatically rolled back.', 'eventflow' ); ?>
											</span>
											<button type="button" id="ef-btn-execute-import" class="ef-btn ef-btn-success ef-btn-md">
												🚀 <?php esc_html_e( 'Confirm & Execute Import', 'eventflow' ); ?>
											</button>
										</div>
									</div>
								</div>
							</div>
						<?php endif; ?>
					</div>

				<!-- 6.5 AUDIT & SECURITY LOGS VIEW -->
				<?php elseif ( 'logs' === $active_view && ( $can_view_logs || $is_super_admin ) ) : ?>
					<?php
					global $wpdb;
					$table_logs = EventFlow_DB::table( 'audit_logs' );
					$log_search = isset( $_GET['ls'] ) ? sanitize_text_field( wp_unslash( $_GET['ls'] ) ) : '';
					$log_action = isset( $_GET['l_action'] ) ? sanitize_text_field( wp_unslash( $_GET['l_action'] ) ) : '';
					$log_page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
					$per_page   = 25;
					$offset     = ( $log_page - 1 ) * $per_page;

					$where  = array( '1=1' );
					$values = array();
					if ( ! empty( $log_action ) ) {
						$where[]  = 'l.action = %s';
						$values[] = $log_action;
					}
					if ( ! empty( $log_search ) ) {
						$s        = '%' . $wpdb->esc_like( $log_search ) . '%';
						$where[]  = '(l.action LIKE %s OR l.details LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)';
						$values[] = $s;
						$values[] = $s;
						$values[] = $s;
						$values[] = $s;
					}
					$where_clause = implode( ' AND ', $where );
					$count_sql    = "SELECT COUNT(*) FROM {$table_logs} l LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID WHERE {$where_clause}";
					$total_logs   = (int) ( ! empty( $values ) ? $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) ) : $wpdb->get_var( $count_sql ) );
					$log_pages    = ceil( $total_logs / $per_page );

					$q_sql        = "SELECT l.*, u.display_name, u.user_email FROM {$table_logs} l LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID WHERE {$where_clause} ORDER BY l.created_at DESC LIMIT %d OFFSET %d";
					$q_vals       = $values;
					$q_vals[]     = $per_page;
					$q_vals[]     = $offset;
					$logs_list    = $wpdb->get_results( $wpdb->prepare( $q_sql, $q_vals ) );
					?>

					<div class="ef-card">
						<div class="ef-card-header ef-flex-between">
							<div>
								<h3>📜 <?php esc_html_e( 'System Audit Trail & Security Logs', 'eventflow' ); ?></h3>
								<p class="ef-card-subtitle"><?php esc_html_e( 'Immutable audit ledger recording all administrative decisions, verifications, status changes, and logins.', 'eventflow' ); ?></p>
							</div>
							<span class="ef-badge ef-badge-neutral"><?php printf( esc_html__( '%d Total Audit Events', 'eventflow' ), $total_logs ); ?></span>
						</div>

						<!-- Filter Bar -->
						<div class="ef-filters-bar">
							<form method="get" action="<?php echo esc_url( $staff_base ); ?>" class="ef-filter-form">
								<input type="hidden" name="view" value="logs">
								<div class="ef-filter-row">
									<div class="ef-filter-item ef-grow">
										<input type="text" name="ls" class="ef-input" placeholder="<?php esc_attr_e( 'Search by action, user, note, or ID...', 'eventflow' ); ?>" value="<?php echo esc_attr( $log_search ); ?>">
									</div>
									<div class="ef-filter-item">
										<select name="l_action" class="ef-input">
											<option value=""><?php esc_html_e( 'All Audit Actions', 'eventflow' ); ?></option>
											<option value="payment_verified" <?php selected( $log_action, 'payment_verified' ); ?>><?php esc_html_e( 'Payment Verified', 'eventflow' ); ?></option>
											<option value="payment_rejected" <?php selected( $log_action, 'payment_rejected' ); ?>><?php esc_html_e( 'Payment Rejected', 'eventflow' ); ?></option>
											<option value="account_unlocked" <?php selected( $log_action, 'account_unlocked' ); ?>><?php esc_html_e( 'Account Unlocked', 'eventflow' ); ?></option>
											<option value="team_status_updated" <?php selected( $log_action, 'team_status_updated' ); ?>><?php esc_html_e( 'Team Status Updated', 'eventflow' ); ?></option>
											<option value="data_imported" <?php selected( $log_action, 'data_imported' ); ?>><?php esc_html_e( 'Data Imported', 'eventflow' ); ?></option>
											<option value="settings_updated" <?php selected( $log_action, 'settings_updated' ); ?>><?php esc_html_e( 'Settings Updated', 'eventflow' ); ?></option>
										</select>
									</div>
									<div class="ef-filter-actions">
										<button type="submit" class="ef-btn ef-btn-primary ef-btn-sm"><?php esc_html_e( 'Filter Logs', 'eventflow' ); ?></button>
										<a href="<?php echo esc_url( add_query_arg( 'view', 'logs', $staff_base ) ); ?>" class="ef-btn ef-btn-outline ef-btn-sm"><?php esc_html_e( 'Reset', 'eventflow' ); ?></a>
									</div>
								</div>
							</form>
						</div>

						<div class="ef-table-responsive">
							<table class="ef-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Date & Time', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Action', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Officer / User', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Target Entity', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Details & Audit Context', 'eventflow' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php if ( empty( $logs_list ) ) : ?>
										<tr>
											<td colspan="5" class="ef-empty-state">
												<div class="ef-empty-icon">📜</div>
												<h4><?php esc_html_e( 'No Audit Logs Recorded', 'eventflow' ); ?></h4>
												<p><?php esc_html_e( 'No system actions match your current search and action filters.', 'eventflow' ); ?></p>
											</td>
										</tr>
									<?php else : ?>
										<?php foreach ( $logs_list as $l ) : ?>
											<tr>
												<td><span class="ef-text-xs ef-monospace"><?php echo esc_html( date_i18n( 'M j, Y H:i:s', strtotime( $l->created_at ) ) ); ?></span></td>
												<td>
													<?php
													$badge_cls = 'ef-badge-neutral';
													if ( false !== strpos( $l->action, 'verify' ) || false !== strpos( $l->action, 'approve' ) || false !== strpos( $l->action, 'unlock' ) ) {
														$badge_cls = 'ef-badge-success';
													} elseif ( false !== strpos( $l->action, 'reject' ) || false !== strpos( $l->action, 'failed' ) ) {
														$badge_cls = 'ef-badge-danger';
													} elseif ( false !== strpos( $l->action, 'reupload' ) || false !== strpos( $l->action, 'update' ) ) {
														$badge_cls = 'ef-badge-warning';
													}
													?>
													<span class="ef-badge <?php echo esc_attr( $badge_cls ); ?>"><?php echo esc_html( $l->action ); ?></span>
												</td>
												<td>
													<strong><?php echo esc_html( $l->display_name ? $l->display_name : 'System Automator' ); ?></strong>
													<?php if ( ! empty( $l->user_email ) ) : ?>
														<div class="ef-text-xs ef-text-muted"><?php echo esc_html( $l->user_email ); ?></div>
													<?php endif; ?>
												</td>
												<td>
													<?php if ( ! empty( $l->record_type ) ) : ?>
														<span class="ef-tag"><?php echo esc_html( ucfirst( $l->record_type ) ); ?> #<?php echo esc_html( $l->record_id ); ?></span>
													<?php else : ?>
														<span class="ef-text-muted">-</span>
													<?php endif; ?>
												</td>
												<td><span class="ef-text-sm"><?php echo esc_html( $l->details ); ?></span></td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
							</table>
						</div>

						<?php if ( $log_pages > 1 ) : ?>
							<div class="ef-pagination ef-flex-between">
								<span class="ef-page-info">
									<?php printf( esc_html__( 'Showing page %1$d of %2$d (%3$d total events)', 'eventflow' ), $log_page, $log_pages, $total_logs ); ?>
								</span>
								<div class="ef-page-links">
									<?php for ( $p = 1; $p <= $log_pages; $p++ ) : ?>
										<a href="<?php echo esc_url( add_query_arg( array( 'view' => 'logs', 'paged' => $p, 'ls' => $log_search ? $log_search : null, 'l_action' => $log_action ? $log_action : null ), $staff_base ) ); ?>" class="ef-page-num <?php echo $p === $log_page ? 'active' : ''; ?>"><?php echo esc_html( $p ); ?></a>
									<?php endfor; ?>
								</div>
							</div>
						<?php endif; ?>
					</div>

				<!-- 7. STAFF & RBAC VIEW -->
				<?php elseif ( 'staff' === $active_view && $can_manage_staff ) : ?>
					<div class="ef-grid ef-grid-2">
						<div class="ef-card">
							<div class="ef-card-header">
								<h3><?php esc_html_e( 'Provision Staff Account', 'eventflow' ); ?></h3>
								<p class="ef-card-subtitle"><?php esc_html_e( 'Grant team members access to the frontend portal with designated granular roles.', 'eventflow' ); ?></p>
							</div>

							<form id="ef-staff-form" class="ef-form" method="post">
								<div class="ef-form-group">
									<label><?php esc_html_e( 'Staff Full Name', 'eventflow' ); ?> <span class="ef-req">*</span></label>
									<input type="text" name="staff_name" class="ef-input" placeholder="e.g. Sarah Jenkins" required>
								</div>

								<div class="ef-form-group">
									<label><?php esc_html_e( 'Email Address', 'eventflow' ); ?> <span class="ef-req">*</span></label>
									<input type="email" name="staff_email" class="ef-input" placeholder="sarah@eventflow.dev" required>
								</div>

								<div class="ef-form-group">
									<label><?php esc_html_e( 'Assigned Staff Role', 'eventflow' ); ?> <span class="ef-req">*</span></label>
									<select name="staff_role" class="ef-input" required>
										<?php foreach ( EventFlow_Roles::get_staff_roles() as $role_key => $role_name ) : ?>
											<option value="<?php echo esc_attr( $role_key ); ?>"><?php echo esc_html( $role_name ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="ef-form-group">
									<label><?php esc_html_e( 'Username (Optional - auto-generated if blank)', 'eventflow' ); ?></label>
									<input type="text" name="staff_username" class="ef-input" placeholder="e.g. sarah_admin">
								</div>

								<div class="ef-form-group">
									<label><?php esc_html_e( 'Initial Password (Leave blank to auto-generate)', 'eventflow' ); ?></label>
									<input type="password" name="staff_password" class="ef-input" placeholder="••••••••">
								</div>

								<button type="submit" class="ef-btn ef-btn-primary ef-btn-block">
									<?php esc_html_e( 'Save & Assign Permissions', 'eventflow' ); ?>
								</button>
							</form>
						</div>

						<div class="ef-card">
							<div class="ef-card-header">
								<h3><?php esc_html_e( 'Role Capabilities Reference', 'eventflow' ); ?></h3>
							</div>
							<div class="ef-card-body">
								<div class="ef-role-guide">
									<div class="ef-rg-item">
										<strong><?php esc_html_e( 'Super Administrator', 'eventflow' ); ?>:</strong>
										<p><?php esc_html_e( 'Complete access to all modules, settings, email templates, staff accounts, and financial verifications.', 'eventflow' ); ?></p>
									</div>
									<div class="ef-rg-item">
										<strong><?php esc_html_e( 'Event Administrator', 'eventflow' ); ?>:</strong>
										<p><?php esc_html_e( 'Manages activities, reviews registrations, executes payment approvals, publishes announcements, and views analytics.', 'eventflow' ); ?></p>
									</div>
									<div class="ef-rg-item">
										<strong><?php esc_html_e( 'Payment Verification Staff', 'eventflow' ); ?>:</strong>
										<p><?php esc_html_e( 'Focused solely on verifying, rejecting, or requesting re-uploads for payment receipts with interactive screenshot inspection.', 'eventflow' ); ?></p>
									</div>
									<div class="ef-rg-item">
										<strong><?php esc_html_e( 'Registration Management Staff', 'eventflow' ); ?>:</strong>
										<p><?php esc_html_e( 'Reviews candidate information, updates application review statuses, and exports roster reports.', 'eventflow' ); ?></p>
									</div>
									<div class="ef-rg-item">
										<strong><?php esc_html_e( 'Technical Team', 'eventflow' ); ?>:</strong>
										<p><?php esc_html_e( 'Configures competition tracks, sets team size minimums and maximums, instructions, and capacity limits.', 'eventflow' ); ?></p>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Active Staff Directory Table -->
					<div class="ef-card" style="margin-top: 24px;">
						<div class="ef-card-header">
							<h3><?php esc_html_e( 'Active Management Staff & Verifiers', 'eventflow' ); ?></h3>
							<p class="ef-card-subtitle"><?php esc_html_e( 'Authorized personnel with assigned administrative or verification roles.', 'eventflow' ); ?></p>
						</div>
						<div class="ef-table-responsive">
							<table class="ef-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Staff Member', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Email Address', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Assigned Role', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Actions', 'eventflow' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									$staff_roles = array_keys( EventFlow_Roles::get_staff_roles() );
									$staff_roles[] = 'administrator';
									$staff_users = get_users( array( 'role__in' => $staff_roles, 'orderby' => 'display_name', 'order' => 'ASC' ) );
									if ( empty( $staff_users ) ) :
									?>
										<tr>
											<td colspan="4" class="ef-text-center" style="padding: 24px; color: #64748B;">
												<?php esc_html_e( 'No staff members currently found.', 'eventflow' ); ?>
											</td>
										</tr>
									<?php else : ?>
										<?php foreach ( $staff_users as $su ) :
											$u_roles = (array) $su->roles;
											$role_display = EventFlow_Roles::get_role_label( $su->ID );
											$is_self = ( (int) $su->ID === (int) $current_user->ID );
											$is_admin = in_array( 'administrator', $u_roles, true );
										?>
											<tr>
												<td>
													<strong><?php echo esc_html( $su->display_name ); ?></strong>
													<span style="display:block; font-size:11px; color:#64748B; margin-top:2px;">@<?php echo esc_html( $su->user_login ); ?></span>
													<?php if ( $is_self ) : ?>
														<span class="ef-tag" style="margin-left: 6px;"><?php esc_html_e( 'You', 'eventflow' ); ?></span>
													<?php endif; ?>
												</td>
												<td><a href="mailto:<?php echo esc_attr( $su->user_email ); ?>"><?php echo esc_html( $su->user_email ); ?></a></td>
												<td>
													<span class="ef-badge <?php echo $is_admin ? 'ef-badge-primary' : 'ef-badge-neutral'; ?>">
														<?php echo esc_html( $role_display ); ?>
													</span>
												</td>
												<td>
													<?php if ( ! $is_self && ! $is_admin ) : ?>
														<button type="button" class="ef-btn ef-btn-danger ef-btn-xs ef-revoke-staff-btn" data-user-id="<?php echo esc_attr( $su->ID ); ?>" data-user-name="<?php echo esc_attr( $su->display_name ); ?>">
															<?php esc_html_e( 'Revoke Staff Role', 'eventflow' ); ?>
														</button>
													<?php else : ?>
														<span class="ef-text-xs ef-text-muted"><?php esc_html_e( 'Protected System Account', 'eventflow' ); ?></span>
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>

					<!-- Security Incident & Locked Accounts Center -->
					<div class="ef-card" style="margin-top: 24px;">
						<div class="ef-card-header ef-flex-between">
							<div>
								<h3 style="color:#DC2626;">🔒 <?php esc_html_e( 'Account Lockout & Brute-Force Incident Monitor', 'eventflow' ); ?></h3>
								<p class="ef-card-subtitle"><?php esc_html_e( 'Review participants and staff locked out due to exceeding maximum allowed failed login attempts.', 'eventflow' ); ?></p>
							</div>
							<span class="ef-badge ef-badge-neutral"><?php printf( esc_html__( 'Threshold: %d attempts / %d min lockout', 'eventflow' ), get_option( 'eventflow_max_login_attempts', 5 ), get_option( 'eventflow_lockout_duration', 30 ) ); ?></span>
						</div>
						<div class="ef-card-body" style="padding: 0;">
							<?php
							$locked_accounts = EventFlow_Auth::get_locked_accounts();
							?>
							<div class="ef-table-responsive">
								<table class="ef-table">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Account / IP Target', 'eventflow' ); ?></th>
											<th><?php esc_html_e( 'Failed Attempts', 'eventflow' ); ?></th>
											<th><?php esc_html_e( 'Lockout Triggered', 'eventflow' ); ?></th>
											<th><?php esc_html_e( 'Lockout Expires', 'eventflow' ); ?></th>
											<th class="ef-text-right"><?php esc_html_e( 'Actions', 'eventflow' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php if ( empty( $locked_accounts ) ) : ?>
											<tr>
												<td colspan="5" class="ef-text-center" style="padding: 28px; color: #0D9488;">
													🛡️ <?php esc_html_e( 'Zero accounts currently locked. System is safe and operating under normal security thresholds.', 'eventflow' ); ?>
												</td>
											</tr>
										<?php else : ?>
											<?php foreach ( $locked_accounts as $acc ) : ?>
												<tr>
													<td>
														<strong><?php echo esc_html( $acc['identifier'] ); ?></strong>
														<?php if ( ! empty( $acc['user_email'] ) ) : ?>
															<div class="ef-text-xs ef-text-muted"><?php echo esc_html( $acc['user_email'] ); ?></div>
														<?php endif; ?>
													</td>
													<td><span class="ef-badge ef-badge-danger"><?php echo esc_html( $acc['attempt_count'] ); ?> <?php esc_html_e( 'attempts', 'eventflow' ); ?></span></td>
													<td><span class="ef-text-xs ef-monospace"><?php echo esc_html( $acc['locked_at'] ); ?></span></td>
													<td>
														<span class="ef-badge ef-badge-warning">
															<?php echo esc_html( human_time_diff( strtotime( $acc['lockout_expires'] ), current_time( 'timestamp' ) ) ); ?> remaining
														</span>
													</td>
													<td class="ef-text-right">
														<button type="button" class="ef-btn ef-btn-success ef-btn-xs ef-btn-unlock-account" data-identifier="<?php echo esc_attr( $acc['identifier'] ); ?>">
															🔓 <?php esc_html_e( 'Unlock Account', 'eventflow' ); ?>
														</button>
													</td>
												</tr>
											<?php endforeach; ?>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>

				<!-- 8. SYSTEM SETTINGS VIEW -->
				<?php elseif ( 'settings' === $active_view && $can_manage_settings ) : ?>
					<?php
					$settings        = EventFlow_Payment_Manager::get_settings();
					$events_all      = EventFlow_Event_Manager::get_events();
					$active_event_id = get_option( 'eventflow_active_event_id', $event ? $event->id : 1 );
					$is_standalone   = (bool) get_option( 'eventflow_standalone_mode', 0 );
					$is_takeover     = (bool) get_option( 'eventflow_takeover_homepage', 0 );
					$system_closed   = get_option( 'eventflow_system_closed', 'no' );
					$reg_prefix      = get_option( 'eventflow_reg_id_prefix', 'EF-' );
					$curr            = get_option( 'eventflow_currency', 'PKR' );
					$primary_col     = get_option( 'eventflow_primary_color', '#0D9488' );
					$secondary_col   = get_option( 'eventflow_secondary_color', '#0F172A' );
					$email_from_name = get_option( 'eventflow_email_from_name', get_bloginfo( 'name' ) );
					$email_from_addr = get_option( 'eventflow_email_from_address', get_option( 'admin_email' ) );

					$bank_on = in_array( (string) $settings['bank_enabled'], array( 'yes', '1', 'true' ), true );
					$jc_on   = in_array( (string) $settings['jazzcash_enabled'], array( 'yes', '1', 'true' ), true );
					$ep_on   = in_array( (string) $settings['easypaisa_enabled'], array( 'yes', '1', 'true' ), true );
					?>

					<form id="ef-settings-form" class="ef-form" method="post">
						<input type="hidden" name="has_standalone_toggle" value="1">
						<input type="hidden" name="has_takeover_toggle" value="1">
						<input type="hidden" name="has_gateway_toggles" value="1">

						<!-- Section 1: General & Display Options -->
						<div class="ef-card" style="margin-bottom: 24px;">
							<div class="ef-card-header">
								<h3>⚙️ <?php esc_html_e( 'General System & Portal Options', 'eventflow' ); ?></h3>
								<p class="ef-card-subtitle"><?php esc_html_e( 'Configure active event selection, standalone application layout, and registration prefixes.', 'eventflow' ); ?></p>
							</div>

							<div class="ef-grid ef-grid-2">
								<div class="ef-form-group">
									<label><?php esc_html_e( 'Active Event Context', 'eventflow' ); ?></label>
									<select name="eventflow_active_event_id" class="ef-input">
										<?php foreach ( $events_all as $ev ) : ?>
											<option value="<?php echo esc_attr( $ev->id ); ?>" <?php selected( $active_event_id, $ev->id ); ?>><?php echo esc_html( $ev->title ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="ef-form-group">
									<label><?php esc_html_e( 'Registration Access Control', 'eventflow' ); ?></label>
									<select name="eventflow_system_closed" class="ef-input">
										<option value="no" <?php selected( $system_closed, 'no' ); ?>><?php esc_html_e( 'Open (Registrations Accepted)', 'eventflow' ); ?></option>
										<option value="yes" <?php selected( $system_closed, 'yes' ); ?>><?php esc_html_e( 'Locked / Closed (Maintenance Mode)', 'eventflow' ); ?></option>
									</select>
								</div>
							</div>

							<div class="ef-grid ef-grid-2">
								<div class="ef-form-group">
									<label><?php esc_html_e( 'Registration ID Prefix', 'eventflow' ); ?></label>
									<input type="text" name="eventflow_reg_id_prefix" class="ef-input" value="<?php echo esc_attr( $reg_prefix ); ?>" placeholder="EF-">
								</div>

								<div class="ef-form-group">
									<label><?php esc_html_e( 'System Currency Code', 'eventflow' ); ?></label>
									<input type="text" name="eventflow_currency" class="ef-input" value="<?php echo esc_attr( $curr ); ?>" placeholder="PKR">
								</div>
							</div>

							<div class="ef-grid ef-grid-2" style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 16px; margin-top: 8px;">
								<div class="ef-form-group" style="margin-bottom: 0;">
									<label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
										<input type="checkbox" name="eventflow_standalone_mode" value="1" <?php checked( $is_standalone ); ?> style="margin-top: 3px;">
										<div>
											<strong><?php esc_html_e( 'Standalone Application Mode', 'eventflow' ); ?></strong>
											<div class="ef-text-xs ef-text-muted"><?php esc_html_e( 'Renders full-screen responsive portal, cleanly isolating the EventFlow interface from theme menus and headers.', 'eventflow' ); ?></div>
										</div>
									</label>
								</div>

								<div class="ef-form-group" style="margin-bottom: 0;">
									<label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
										<input type="checkbox" name="eventflow_takeover_homepage" value="1" <?php checked( $is_takeover ); ?> style="margin-top: 3px;">
										<div>
											<strong><?php esc_html_e( 'Homepage Takeover Mode', 'eventflow' ); ?></strong>
											<div class="ef-text-xs ef-text-muted"><?php esc_html_e( 'Automatically serves the EventFlow portal directly on the website root domain homepage.', 'eventflow' ); ?></div>
										</div>
									</label>
								</div>
							</div>
						</div>

						<!-- Section 2: Branding & Appearance -->
						<div class="ef-card" style="margin-bottom: 24px;">
							<div class="ef-card-header">
								<h3>🎨 <?php esc_html_e( 'Branding & Color Theme', 'eventflow' ); ?></h3>
								<p class="ef-card-subtitle"><?php esc_html_e( 'Configure primary accents and dark sidebar color schemes matching university brand identity.', 'eventflow' ); ?></p>
							</div>

							<div class="ef-grid ef-grid-2">
								<div class="ef-form-group">
									<label><?php esc_html_e( 'Primary Teal / Brand Accent Color', 'eventflow' ); ?></label>
									<div class="ef-color-picker-wrap" style="display: flex; align-items: center; gap: 10px;">
										<input type="color" id="ef-primary-color-picker" value="<?php echo esc_attr( $primary_col ); ?>" style="width: 44px; height: 38px; padding: 2px; border: 1px solid #CBD5E1; border-radius: 6px; cursor: pointer;">
										<input type="text" id="ef-primary-color-text" name="eventflow_primary_color" class="ef-input" value="<?php echo esc_attr( $primary_col ); ?>" style="flex: 1;">
									</div>
								</div>

								<div class="ef-form-group">
									<label><?php esc_html_e( 'Secondary Dark / Navy Header Color', 'eventflow' ); ?></label>
									<div class="ef-color-picker-wrap" style="display: flex; align-items: center; gap: 10px;">
										<input type="color" id="ef-secondary-color-picker" value="<?php echo esc_attr( $secondary_col ); ?>" style="width: 44px; height: 38px; padding: 2px; border: 1px solid #CBD5E1; border-radius: 6px; cursor: pointer;">
										<input type="text" id="ef-secondary-color-text" name="eventflow_secondary_color" class="ef-input" value="<?php echo esc_attr( $secondary_col ); ?>" style="flex: 1;">
									</div>
								</div>
							</div>
						</div>

						<!-- Section 3: Financial Payment Gateways -->
						<div class="ef-card" style="margin-bottom: 24px;">
							<div class="ef-card-header">
								<h3>💳 <?php esc_html_e( 'Payment Gateways & Bank Accounts', 'eventflow' ); ?></h3>
								<p class="ef-card-subtitle"><?php esc_html_e( 'Configure official university bank accounts, mobile wallets (JazzCash, Easypaisa, Raast), and QR instructions.', 'eventflow' ); ?></p>
							</div>

							<!-- Gateway Activation Toggles -->
							<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px 16px; margin-bottom: 20px; display: flex; gap: 24px; flex-wrap: wrap;">
								<label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600;">
									<input type="checkbox" name="eventflow_bank_enabled" value="1" <?php checked( $bank_on ); ?>>
									<?php esc_html_e( 'Enable Bank Transfer', 'eventflow' ); ?>
								</label>
								<label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600;">
									<input type="checkbox" name="eventflow_jazzcash_enabled" value="1" <?php checked( $jc_on ); ?>>
									<?php esc_html_e( 'Enable JazzCash', 'eventflow' ); ?>
								</label>
								<label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600;">
									<input type="checkbox" name="eventflow_easypaisa_enabled" value="1" <?php checked( $ep_on ); ?>>
									<?php esc_html_e( 'Enable Easypaisa', 'eventflow' ); ?>
								</label>
							</div>

							<!-- Bank Details -->
							<div class="ef-grid ef-grid-2">
								<div class="ef-form-group">
									<label><?php esc_html_e( 'Bank Name', 'eventflow' ); ?></label>
									<input type="text" name="eventflow_bank_name" class="ef-input" value="<?php echo esc_attr( $settings['bank_name'] ); ?>" placeholder="e.g. Bank Alfalah / HBL">
								</div>
								<div class="ef-form-group">
									<label><?php esc_html_e( 'Account Title', 'eventflow' ); ?></label>
									<input type="text" name="eventflow_account_title" class="ef-input" value="<?php echo esc_attr( $settings['account_title'] ); ?>" placeholder="e.g. Sukkur IBA SIBACOM Society">
								</div>
							</div>

							<div class="ef-grid ef-grid-2">
								<div class="ef-form-group">
									<label><?php esc_html_e( 'Account Number', 'eventflow' ); ?></label>
									<input type="text" name="eventflow_account_number" class="ef-input" value="<?php echo esc_attr( $settings['account_number'] ); ?>">
								</div>
								<div class="ef-form-group">
									<label><?php esc_html_e( 'IBAN', 'eventflow' ); ?></label>
									<input type="text" name="eventflow_iban" class="ef-input" value="<?php echo esc_attr( $settings['iban'] ); ?>" placeholder="PK65ALFH01234567890123">
								</div>
							</div>

							<!-- Mobile Wallets & Raast -->
							<div class="ef-grid ef-grid-2">
								<div class="ef-form-group">
									<label><?php esc_html_e( 'Easypaisa Account Title & Number', 'eventflow' ); ?></label>
									<div style="display: flex; gap: 10px;">
										<input type="text" name="eventflow_easypaisa_title" class="ef-input" style="flex: 1;" value="<?php echo esc_attr( $settings['easypaisa_title'] ); ?>" placeholder="Title">
										<input type="text" name="eventflow_easypaisa_number" class="ef-input" style="flex: 1;" value="<?php echo esc_attr( $settings['easypaisa_number'] ); ?>" placeholder="03001234567">
									</div>
								</div>
								<div class="ef-form-group">
									<label><?php esc_html_e( 'JazzCash Account Title & Number', 'eventflow' ); ?></label>
									<div style="display: flex; gap: 10px;">
										<input type="text" name="eventflow_jazzcash_title" class="ef-input" style="flex: 1;" value="<?php echo esc_attr( $settings['jazzcash_title'] ); ?>" placeholder="Title">
										<input type="text" name="eventflow_jazzcash_number" class="ef-input" style="flex: 1;" value="<?php echo esc_attr( $settings['jazzcash_number'] ); ?>" placeholder="03001234567">
									</div>
								</div>
							</div>

							<div class="ef-grid ef-grid-2">
								<div class="ef-form-group">
									<label><?php esc_html_e( 'Raast ID / PayID', 'eventflow' ); ?></label>
									<input type="text" name="eventflow_raast_id" class="ef-input" value="<?php echo esc_attr( $settings['raast_id'] ); ?>" placeholder="03001234567">
								</div>
								<div class="ef-form-group">
									<label><?php esc_html_e( 'QR Code Image URL', 'eventflow' ); ?></label>
									<input type="url" name="eventflow_qr_code_url" class="ef-input" value="<?php echo esc_attr( $settings['qr_code_url'] ); ?>" placeholder="https://yoursite.com/uploads/qr.png">
								</div>
							</div>

							<div class="ef-form-group">
								<label><?php esc_html_e( 'Payment Instructions Displayed to Candidates', 'eventflow' ); ?></label>
								<textarea name="eventflow_payment_instructions" class="ef-input" rows="4"><?php echo esc_textarea( $settings['instructions'] ); ?></textarea>
							</div>
						</div>

						<!-- Section 4: Email Communications -->
						<div class="ef-card" style="margin-bottom: 24px;">
							<div class="ef-card-header">
								<h3>✉️ <?php esc_html_e( 'Email Notification Dispatcher', 'eventflow' ); ?></h3>
								<p class="ef-card-subtitle"><?php esc_html_e( 'Sender information stamped on confirmation slips, registration receipts, and payment status alerts.', 'eventflow' ); ?></p>
							</div>

							<div class="ef-grid ef-grid-2">
								<div class="ef-form-group">
									<label><?php esc_html_e( 'Sender Display Name', 'eventflow' ); ?></label>
									<input type="text" name="eventflow_email_from_name" class="ef-input" value="<?php echo esc_attr( $email_from_name ); ?>" placeholder="SIBACOM 2026 Organizing Committee">
								</div>
								<div class="ef-form-group">
									<label><?php esc_html_e( 'Sender Email Address', 'eventflow' ); ?></label>
									<input type="email" name="eventflow_email_from_address" class="ef-input" value="<?php echo esc_attr( $email_from_addr ); ?>" placeholder="noreply@iba-suk.edu.pk">
								</div>
							</div>
						</div>

						<!-- Section 5: Login Attempt Protection & Brute-Force Defense -->
						<div class="ef-card" style="margin-bottom: 24px;">
							<div class="ef-card-header">
								<h3>🛡️ <?php esc_html_e( 'Failed Login Attempt Restrictions & Account Lockout Defense', 'eventflow' ); ?></h3>
								<p class="ef-card-subtitle"><?php esc_html_e( 'Configurable brute-force defense limits tracked independently for participants and staff.', 'eventflow' ); ?></p>
							</div>

							<div class="ef-grid ef-grid-3">
								<div class="ef-form-group">
									<label><?php esc_html_e( 'Lockout Protection Engine', 'eventflow' ); ?></label>
									<select name="eventflow_enable_login_protection" class="ef-input">
										<option value="yes" <?php selected( get_option( 'eventflow_enable_login_protection', 'yes' ), 'yes' ); ?>><?php esc_html_e( 'Active (Temporary Lockout Enabled)', 'eventflow' ); ?></option>
										<option value="no" <?php selected( get_option( 'eventflow_enable_login_protection', 'yes' ), 'no' ); ?>><?php esc_html_e( 'Disabled', 'eventflow' ); ?></option>
									</select>
									<span class="ef-help-text"><?php esc_html_e( 'Protects against automated credential guessing and brute-force attacks.', 'eventflow' ); ?></span>
								</div>

								<div class="ef-form-group">
									<label><?php esc_html_e( 'Maximum Allowed Failed Attempts', 'eventflow' ); ?> <span class="ef-req">*</span></label>
									<input type="number" name="eventflow_max_login_attempts" class="ef-input" min="3" max="25" value="<?php echo esc_attr( get_option( 'eventflow_max_login_attempts', 5 ) ); ?>" required>
									<span class="ef-help-text"><?php esc_html_e( 'Attempts before temporary lockout is triggered (default: 5).', 'eventflow' ); ?></span>
								</div>

								<div class="ef-form-group">
									<label><?php esc_html_e( 'Temporary Lockout Duration (Minutes)', 'eventflow' ); ?> <span class="ef-req">*</span></label>
									<input type="number" name="eventflow_lockout_duration" class="ef-input" min="5" max="1440" value="<?php echo esc_attr( get_option( 'eventflow_lockout_duration', 30 ) ); ?>" required>
									<span class="ef-help-text"><?php esc_html_e( 'Minutes account remains locked before auto-resetting (default: 30).', 'eventflow' ); ?></span>
								</div>
							</div>
						</div>

						<!-- Action Bar -->
						<div class="ef-flex-between" style="padding: 16px 0;">
							<button type="submit" class="ef-btn ef-btn-primary ef-btn-lg" id="ef-save-settings-btn">
								💾 <?php esc_html_e( 'Save All Settings', 'eventflow' ); ?>
							</button>
						</div>
					</form>
				<?php endif; ?>

			</div>
		</div>
	</div>

	<!-- ADVANCED PAYMENT SCREENSHOT PREVIEW MODAL (CRITICAL REQUIREMENT: TWO-COLUMN VERIFICATION LAYOUT) -->
	<div class="ef-modal ef-preview-modal" id="ef-payment-preview-modal" aria-hidden="true">
		<div class="ef-modal-backdrop"></div>
		<div class="ef-modal-dialog ef-modal-2xl" style="max-width: 1380px; width: 95vw;">
			<div class="ef-modal-header ef-flex-between">
				<div class="ef-modal-title-wrap" style="display:flex; align-items:center; gap:10px;">
					<h3 class="ef-modal-title"><?php esc_html_e( 'Payment Verification Inspector', 'eventflow' ); ?></h3>
					<span class="ef-badge ef-badge-primary" id="ef-modal-reg-id-badge"></span>
					<span id="ef-pv-status-badge"></span>
				</div>
				<div class="ef-preview-toolbar">
					<button type="button" class="ef-tool-btn" id="ef-zoom-in" title="<?php esc_attr_e( 'Zoom In (+)', 'eventflow' ); ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg></button>
					<button type="button" class="ef-tool-btn" id="ef-zoom-out" title="<?php esc_attr_e( 'Zoom Out (-)', 'eventflow' ); ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg></button>
					<button type="button" class="ef-tool-btn" id="ef-rotate" title="<?php esc_attr_e( 'Rotate 90°', 'eventflow' ); ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg></button>
					<button type="button" class="ef-tool-btn" id="ef-fit-screen" title="<?php esc_attr_e( 'Fit to Screen', 'eventflow' ); ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path></svg></button>
					<button type="button" class="ef-tool-btn" id="ef-zoom-reset" title="<?php esc_attr_e( 'Reset View', 'eventflow' ); ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><polyline points="3 3 3 8 8 8"></polyline></svg></button>
					<button type="button" class="ef-modal-close" data-dismiss="modal">&times;</button>
				</div>
			</div>

			<div class="ef-modal-body ef-split-modal-body ef-pv-two-col-layout">
				<!-- LEFT COLUMN: COMPLETE REGISTRATION, TEAM, MEMBER ROSTER & PAYMENT DETAILS -->
				<div class="ef-pv-left-column">
					<!-- 1. Team & Candidate Information -->
					<div class="ef-card ef-pv-card">
						<div class="ef-card-header ef-flex-between">
							<h4 class="ef-card-title"><?php esc_html_e( 'Team & Registration Information', 'eventflow' ); ?></h4>
							<span id="ef-pv-type-badge" class="ef-badge ef-badge-neutral"></span>
						</div>
						<div class="ef-card-body ef-pv-details-grid">
							<div class="ef-pv-field">
								<span class="ef-text-muted"><?php esc_html_e( 'Team / Candidate:', 'eventflow' ); ?></span>
								<strong id="ef-pv-team-name" class="ef-text-lg">-</strong>
							</div>
							<div class="ef-pv-field">
								<span class="ef-text-muted"><?php esc_html_e( 'Registration ID:', 'eventflow' ); ?></span>
								<code id="ef-pv-reg-id" class="ef-tid-lg">-</code>
							</div>
							<div class="ef-pv-field">
								<span class="ef-text-muted"><?php esc_html_e( 'Leader Name:', 'eventflow' ); ?></span>
								<strong id="ef-pv-leader-name">-</strong>
							</div>
							<div class="ef-pv-field">
								<span class="ef-text-muted"><?php esc_html_e( 'Leader Email:', 'eventflow' ); ?></span>
								<span id="ef-pv-leader-email">-</span>
							</div>
							<div class="ef-pv-field">
								<span class="ef-text-muted"><?php esc_html_e( 'Leader Phone / WhatsApp:', 'eventflow' ); ?></span>
								<span id="ef-pv-leader-phone">-</span>
							</div>
							<div class="ef-pv-field">
								<span class="ef-text-muted"><?php esc_html_e( 'Registration Date:', 'eventflow' ); ?></span>
								<span id="ef-pv-reg-date">-</span>
							</div>
							<div class="ef-pv-field">
								<span class="ef-text-muted"><?php esc_html_e( 'University / Institute:', 'eventflow' ); ?></span>
								<span id="ef-pv-university">-</span>
							</div>
							<div class="ef-pv-field">
								<span class="ef-text-muted"><?php esc_html_e( 'Department & Semester:', 'eventflow' ); ?></span>
								<span id="ef-pv-dept-sem">-</span>
							</div>
							<div class="ef-pv-field ef-span-2">
								<span class="ef-text-muted"><?php esc_html_e( 'Competition / Track:', 'eventflow' ); ?></span>
								<strong id="ef-pv-competition-title" class="ef-text-primary">-</strong>
							</div>
						</div>
					</div>

					<!-- 2. All Team Members Roster (All 7 Fields) -->
					<div class="ef-card ef-pv-card" style="margin-top: 14px;">
						<div class="ef-card-header ef-flex-between">
							<h4 class="ef-card-title"><?php esc_html_e( 'All Team Members & Academic Details', 'eventflow' ); ?></h4>
							<span class="ef-badge ef-badge-neutral" id="ef-pv-members-count-badge">0</span>
						</div>
						<div class="ef-card-body" style="padding: 0;">
							<div class="ef-table-responsive" style="max-height: 220px; overflow-y: auto;">
								<table class="ef-table ef-table-sm" id="ef-pv-members-table">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Participant Name', 'eventflow' ); ?></th>
											<th><?php esc_html_e( 'Role', 'eventflow' ); ?></th>
											<th><?php esc_html_e( 'Email', 'eventflow' ); ?></th>
											<th><?php esc_html_e( 'Contact / WhatsApp', 'eventflow' ); ?></th>
											<th><?php esc_html_e( 'University', 'eventflow' ); ?></th>
											<th><?php esc_html_e( 'Dept / Semester', 'eventflow' ); ?></th>
										</tr>
									</thead>
									<tbody id="ef-pv-members-tbody">
										<!-- Populated dynamically via JS -->
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<!-- 3. Payment & Financial Reconciliation -->
					<div class="ef-card ef-pv-card" style="margin-top: 14px;">
						<div class="ef-card-header ef-flex-between">
							<h4 class="ef-card-title"><?php esc_html_e( 'Payment & Fee Reconciliation', 'eventflow' ); ?></h4>
							<span id="ef-pv-fee-diff-badge"></span>
						</div>
						<div class="ef-card-body ef-pv-details-grid">
							<div class="ef-pv-field">
								<span class="ef-text-muted"><?php esc_html_e( 'Transaction ID (TID):', 'eventflow' ); ?></span>
								<code id="ef-pv-tid" class="ef-tid-lg">-</code>
							</div>
							<div class="ef-pv-field">
								<span class="ef-text-muted"><?php esc_html_e( 'Payment Method:', 'eventflow' ); ?></span>
								<strong id="ef-pv-method">-</strong>
							</div>
							<div class="ef-pv-field">
								<span class="ef-text-muted"><?php esc_html_e( 'Submitted Payment Amount:', 'eventflow' ); ?></span>
								<strong id="ef-pv-amount" class="ef-fee" style="font-size: 1.15rem; color: #0D9488;">-</strong>
							</div>
							<div class="ef-pv-field">
								<span class="ef-text-muted"><?php esc_html_e( 'Required Registration Fee:', 'eventflow' ); ?></span>
								<strong id="ef-pv-required-amount" class="ef-fee" style="font-size: 1.15rem;">-</strong>
							</div>
							<div class="ef-pv-field">
								<span class="ef-text-muted"><?php esc_html_e( 'Payment Submission Date:', 'eventflow' ); ?></span>
								<span id="ef-pv-date">-</span>
							</div>
							<div class="ef-pv-field">
								<span class="ef-text-muted"><?php esc_html_e( 'Last Verification Officer:', 'eventflow' ); ?></span>
								<span id="ef-pv-verified-by">-</span>
							</div>
						</div>
					</div>

					<!-- Previous Rejection Remarks Box -->
					<div class="ef-card ef-pv-card" id="ef-pv-rejection-remarks-box" style="display:none; background:#FEF2F2; border:1px solid #FECACA; border-left:4px solid #DC2626; border-radius:8px; padding:14px 16px; margin-top:14px;">
						<div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
							<span style="font-size:16px;">⚠️</span>
							<h4 style="margin:0; font-size:13.5px; font-weight:700; color:#991B1B;"><?php esc_html_e( 'Previous Rejection Remarks & Reason', 'eventflow' ); ?></h4>
							<span class="ef-badge ef-badge-danger" id="ef-pv-rejected-version-badge" style="font-size:10px; margin-left:auto;"></span>
						</div>
						<p id="ef-pv-previous-rejection-text" style="margin:0; font-size:13px; color:#1E293B; font-weight:500; line-height:1.4;"></p>
					</div>

					<!-- Submission Versions / Resubmissions & Historical Receipts -->
					<div class="ef-card ef-pv-card" style="margin-top: 14px;">
						<div class="ef-card-header ef-flex-between">
							<h4 class="ef-card-title"><?php esc_html_e( 'Submission Versions & Receipts', 'eventflow' ); ?></h4>
							<span class="ef-badge ef-badge-neutral" id="ef-pv-version-count-badge">1</span>
						</div>
						<div class="ef-card-body">
							<div id="ef-pv-submissions-container">
								<p class="ef-text-muted ef-text-xs"><?php esc_html_e( 'Loading submission versions...', 'eventflow' ); ?></p>
							</div>
						</div>
					</div>

					<!-- 4. Previous Verification History & Audit Trail -->
					<div class="ef-card ef-pv-card" style="margin-top: 14px;">
						<div class="ef-card-header">
							<h4 class="ef-card-title"><?php esc_html_e( 'Verification History & Status Log', 'eventflow' ); ?></h4>
						</div>
						<div class="ef-card-body" style="padding: 0;">
							<div class="ef-table-responsive" style="max-height: 160px; overflow-y: auto;">
								<table class="ef-table ef-table-sm" id="ef-pv-history-table">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Timestamp', 'eventflow' ); ?></th>
											<th><?php esc_html_e( 'Action', 'eventflow' ); ?></th>
											<th><?php esc_html_e( 'Officer', 'eventflow' ); ?></th>
											<th><?php esc_html_e( 'Notes / Reason', 'eventflow' ); ?></th>
										</tr>
									</thead>
									<tbody id="ef-pv-history-tbody">
										<!-- Populated dynamically via JS -->
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>

				<!-- RIGHT COLUMN: INTERACTIVE PROOF VIEWER, ZOOM TOOLS & VERIFICATION ACTIONS -->
				<div class="ef-pv-right-column">
					<div class="ef-pv-viewer-card ef-card">
						<div class="ef-card-header ef-flex-between">
							<h4 class="ef-card-title"><?php esc_html_e( 'Payment Receipt Proof', 'eventflow' ); ?></h4>
							<a href="#" id="ef-btn-download-receipt" class="ef-btn ef-btn-outline ef-btn-xs" download>
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
								<?php esc_html_e( 'Download Receipt', 'eventflow' ); ?>
							</a>
						</div>
						<div class="ef-card-body" style="padding: 0; position: relative;">
							<div class="ef-screenshot-viewport" id="ef-screenshot-viewport" style="min-height: 480px; max-height: 560px;">
								<div id="ef-preview-loading" class="ef-spinner" style="display:none; position: absolute; top: 45%; left: 45%;"></div>
								<div id="ef-preview-error" class="ef-notice ef-notice-danger" style="display:none; margin: 30px;">
									<?php esc_html_e( 'Receipt document could not be loaded or file permission denied.', 'eventflow' ); ?>
								</div>
								<div class="ef-image-canvas-wrap">
									<img src="" alt="Proof" id="ef-preview-img" style="display:none;">
									<iframe src="" id="ef-preview-pdf" style="display:none; width: 100%; height: 500px; border: none;"></iframe>
								</div>
							</div>
						</div>
					</div>

					<!-- Staff Verification Decision Actions -->
					<div class="ef-card ef-pv-action-card" style="margin-top: 14px;">
						<div class="ef-card-header">
							<h4 class="ef-card-title"><?php esc_html_e( 'Staff Verification Actions', 'eventflow' ); ?></h4>
						</div>
						<div class="ef-card-body">
							<input type="hidden" id="ef-active-payment-id" value="0">

							<div class="ef-form-group">
								<label for="ef-action-notes"><?php esc_html_e( 'Verification Notes / Reference Code', 'eventflow' ); ?></label>
								<textarea id="ef-action-notes" class="ef-input" rows="2" placeholder="<?php esc_attr_e( 'e.g. Bank statement cross-checked with Habib Bank ref...', 'eventflow' ); ?>"></textarea>
							</div>

							<div class="ef-v-buttons">
								<button type="button" class="ef-btn ef-btn-success ef-btn-block" id="ef-btn-verify-payment">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
									<?php esc_html_e( 'Approve & Confirm Payment', 'eventflow' ); ?>
								</button>

								<div class="ef-grid ef-grid-2" style="margin-top: 10px;">
									<button type="button" class="ef-btn ef-btn-warning ef-btn-sm" id="ef-btn-reupload-payment">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
										<?php esc_html_e( 'Request Re-upload', 'eventflow' ); ?>
									</button>
									<button type="button" class="ef-btn ef-btn-danger ef-btn-sm" id="ef-btn-reject-payment">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
										<?php esc_html_e( 'Reject Payment', 'eventflow' ); ?>
									</button>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- REJECT PAYMENT MODAL -->
	<div class="ef-modal" id="ef-reject-modal" aria-hidden="true">
		<div class="ef-modal-backdrop"></div>
		<div class="ef-modal-dialog">
			<div class="ef-modal-header">
				<h3 class="ef-modal-title"><?php esc_html_e( 'Reject Payment Proof', 'eventflow' ); ?></h3>
				<button type="button" class="ef-modal-close" data-dismiss="modal">&times;</button>
			</div>
			<div class="ef-modal-body">
				<p class="ef-text-sm ef-text-muted"><?php esc_html_e( 'Specify a clear rejection reason. This reason will be logged in the audit trail and emailed directly to the participant.', 'eventflow' ); ?></p>
				<div class="ef-form-group">
					<label><?php esc_html_e( 'Rejection Reason', 'eventflow' ); ?> <span class="ef-req">*</span></label>
					<textarea id="ef-reject-reason-text" class="ef-input" rows="3" placeholder="<?php esc_attr_e( 'e.g. Transaction ID could not be confirmed in university bank statement...', 'eventflow' ); ?>" required></textarea>
				</div>
			</div>
			<div class="ef-modal-footer">
				<button type="button" class="ef-btn ef-btn-secondary" data-dismiss="modal"><?php esc_html_e( 'Cancel', 'eventflow' ); ?></button>
				<button type="button" class="ef-btn ef-btn-danger" id="ef-confirm-reject-btn"><?php esc_html_e( 'Confirm Rejection & Notify', 'eventflow' ); ?></button>
			</div>
		</div>
	</div>

	<!-- REQUEST RE-UPLOAD MODAL -->
	<div class="ef-modal" id="ef-reupload-modal" aria-hidden="true">
		<div class="ef-modal-backdrop"></div>
		<div class="ef-modal-dialog">
			<div class="ef-modal-header">
				<h3 class="ef-modal-title"><?php esc_html_e( 'Request Proof Re-upload', 'eventflow' ); ?></h3>
				<button type="button" class="ef-modal-close" data-dismiss="modal">&times;</button>
			</div>
			<div class="ef-modal-body">
				<p class="ef-text-sm ef-text-muted"><?php esc_html_e( 'Explain clearly what is missing or illegible in the current receipt. The participant will be notified to replace their receipt.', 'eventflow' ); ?></p>
				<div class="ef-form-group">
					<label><?php esc_html_e( 'Instructions for Participant', 'eventflow' ); ?> <span class="ef-req">*</span></label>
					<textarea id="ef-reupload-instructions-text" class="ef-input" rows="3" placeholder="<?php esc_attr_e( 'e.g. The uploaded image is blurry. Please upload an official bank receipt showing transaction date and sender name...', 'eventflow' ); ?>" required></textarea>
				</div>
			</div>
			<div class="ef-modal-footer">
				<button type="button" class="ef-btn ef-btn-secondary" data-dismiss="modal"><?php esc_html_e( 'Cancel', 'eventflow' ); ?></button>
				<button type="button" class="ef-btn ef-btn-warning" id="ef-confirm-reupload-btn"><?php esc_html_e( 'Send Re-upload Request', 'eventflow' ); ?></button>
			</div>
		</div>
	</div>

	<!-- EDIT PAYMENT MODAL -->
	<div class="ef-modal" id="ef-payment-edit-modal" aria-hidden="true">
		<div class="ef-modal-backdrop"></div>
		<div class="ef-modal-dialog ef-modal-lg">
			<div class="ef-modal-header">
				<h3 class="ef-modal-title"><?php esc_html_e( 'Edit Payment Record', 'eventflow' ); ?></h3>
				<button type="button" class="ef-modal-close" data-dismiss="modal">&times;</button>
			</div>
			<form id="ef-edit-payment-form" enctype="multipart/form-data">
				<div class="ef-modal-body">
					<input type="hidden" name="payment_id" id="ef-edit-pay-id" value="0">
					<input type="hidden" name="current_status" id="ef-edit-pay-current-status" value="">

					<div class="ef-grid ef-grid-2">
						<div class="ef-form-group">
							<label><?php esc_html_e( 'Transaction ID / Reference', 'eventflow' ); ?> <span class="ef-req">*</span></label>
							<input type="text" name="transaction_id" id="ef-edit-pay-tid" class="ef-input" required>
						</div>

						<div class="ef-form-group">
							<label><?php esc_html_e( 'Payment Method', 'eventflow' ); ?> <span class="ef-req">*</span></label>
							<select name="payment_method" id="ef-edit-pay-method" class="ef-input" required>
								<option value="Bank Transfer"><?php esc_html_e( 'Bank Transfer', 'eventflow' ); ?></option>
								<option value="JazzCash"><?php esc_html_e( 'JazzCash', 'eventflow' ); ?></option>
								<option value="Easypaisa"><?php esc_html_e( 'Easypaisa', 'eventflow' ); ?></option>
								<option value="Cash / Manual"><?php esc_html_e( 'Cash / Manual Receipt', 'eventflow' ); ?></option>
							</select>
						</div>
					</div>

					<div class="ef-grid ef-grid-2">
						<div class="ef-form-group">
							<label><?php esc_html_e( 'Paid Amount', 'eventflow' ); ?> <span class="ef-req">*</span></label>
							<input type="number" step="0.01" name="amount" id="ef-edit-pay-amount" class="ef-input" required>
						</div>

						<div class="ef-form-group">
							<label><?php esc_html_e( 'Payment Date', 'eventflow' ); ?> <span class="ef-req">*</span></label>
							<input type="date" name="payment_date" id="ef-edit-pay-date" class="ef-input" required>
						</div>
					</div>

					<div class="ef-form-group">
						<label><?php esc_html_e( 'Verification Status Override', 'eventflow' ); ?></label>
						<select name="status" id="ef-edit-pay-status" class="ef-input">
							<option value="pending"><?php esc_html_e( 'Pending Payment', 'eventflow' ); ?></option>
							<option value="under_verification"><?php esc_html_e( 'Under Verification', 'eventflow' ); ?></option>
							<option value="verified"><?php esc_html_e( 'Verified & Approved', 'eventflow' ); ?></option>
							<option value="rejected"><?php esc_html_e( 'Rejected', 'eventflow' ); ?></option>
							<option value="reupload_requested"><?php esc_html_e( 'Re-upload Requested', 'eventflow' ); ?></option>
						</select>
					</div>

					<!-- Mandatory Correction Reason for Verified Payments -->
					<div class="ef-form-group" id="ef-correction-reason-box" style="display:none; background: #FFFBEB; border: 1px solid #FDE68A; padding: 12px; border-radius: 6px;">
						<label style="color: #92400E; font-weight: 700;">⚠️ <?php esc_html_e( 'Mandatory Correction Reason', 'eventflow' ); ?> <span class="ef-req">*</span></label>
						<p class="ef-text-xs" style="color: #B45309; margin: 4px 0 8px;"><?php esc_html_e( 'This payment was already verified. Modifying financial values requires an explicit correction audit reason.', 'eventflow' ); ?></p>
						<textarea name="correction_reason" id="ef-edit-pay-correction" class="ef-input" rows="2" placeholder="<?php esc_attr_e( 'Specify why verified payment data is being amended...', 'eventflow' ); ?>"></textarea>
					</div>

					<div class="ef-form-group">
						<label><?php esc_html_e( 'Replacement Receipt / Proof (Optional)', 'eventflow' ); ?></label>
						<input type="file" name="payment_proof" id="ef-edit-pay-file" class="ef-input" accept="image/*,application/pdf">
						<span class="ef-text-xs ef-text-muted"><?php esc_html_e( 'Upload new screenshot or PDF. Previous file will be preserved in audit logs.', 'eventflow' ); ?></span>
					</div>

					<div class="ef-form-group">
						<label><?php esc_html_e( 'Internal Staff Notes', 'eventflow' ); ?></label>
						<textarea name="notes" id="ef-edit-pay-notes" class="ef-input" rows="2"></textarea>
					</div>
				</div>

				<div class="ef-modal-footer">
					<button type="button" class="ef-btn ef-btn-secondary" data-dismiss="modal"><?php esc_html_e( 'Cancel', 'eventflow' ); ?></button>
					<button type="submit" class="ef-btn ef-btn-primary" id="ef-save-payment-edit-btn"><?php esc_html_e( 'Save Payment Updates', 'eventflow' ); ?></button>
				</div>
			</form>
		</div>
	</div>

	<!-- PAYMENT AUDIT HISTORY TIMELINE MODAL -->
	<div class="ef-modal" id="ef-payment-history-modal" aria-hidden="true">
		<div class="ef-modal-backdrop"></div>
		<div class="ef-modal-dialog ef-modal-lg">
			<div class="ef-modal-header">
				<h3 class="ef-modal-title"><?php esc_html_e( 'Payment Lifecycle & Audit History', 'eventflow' ); ?></h3>
				<button type="button" class="ef-modal-close" data-dismiss="modal">&times;</button>
			</div>
			<div class="ef-modal-body">
				<div id="ef-payment-history-content">
					<div class="ef-spinner"></div>
				</div>
			</div>
			<div class="ef-modal-footer">
				<button type="button" class="ef-btn ef-btn-secondary" data-dismiss="modal"><?php esc_html_e( 'Close', 'eventflow' ); ?></button>
			</div>
		</div>
	</div>

	<!-- DYNAMIC FORM BUILDER MODAL (18+ FIELD TYPES) -->
	<div class="ef-modal ef-fb-modal" id="ef-form-builder-modal" aria-hidden="true">
		<div class="ef-modal-backdrop"></div>
		<div class="ef-modal-dialog ef-modal-xl" style="max-width: 1200px;">
			<div class="ef-modal-header ef-flex-between">
				<div class="ef-modal-title-wrap">
					<h3 class="ef-modal-title">📋 <?php esc_html_e( 'Dynamic Form Builder', 'eventflow' ); ?>: <span id="ef-fb-act-title-display" style="color:#0D9488;"></span></h3>
					<span class="ef-text-xs ef-text-muted"><?php esc_html_e( 'Customize participant questions and candidate field attributes for this competition.', 'eventflow' ); ?></span>
				</div>
				<div class="ef-fb-top-actions" style="display:flex; gap:8px; align-items:center;">
					<select id="ef-fb-template-preset" class="ef-input ef-input-sm" style="width:auto;">
						<option value=""><?php esc_html_e( 'Load Template Preset...', 'eventflow' ); ?></option>
						<option value="coding"><?php esc_html_e( 'Coding / Speed Programming', 'eventflow' ); ?></option>
						<option value="robotics"><?php esc_html_e( 'Robo Wars / Hardware', 'eventflow' ); ?></option>
						<option value="quiz"><?php esc_html_e( 'Quiz / Olympiad', 'eventflow' ); ?></option>
						<option value="gaming"><?php esc_html_e( 'E-Gaming / Esports', 'eventflow' ); ?></option>
					</select>
					<button type="button" class="ef-btn ef-btn-outline ef-btn-sm" id="ef-fb-preview-btn">👁️ <?php esc_html_e( 'Preview Form', 'eventflow' ); ?></button>
					<button type="button" class="ef-btn ef-btn-danger ef-btn-sm" id="ef-fb-clear-btn"><?php esc_html_e( 'Clear All', 'eventflow' ); ?></button>
					<button type="button" class="ef-btn ef-btn-primary ef-btn-sm" id="ef-fb-save-btn">💾 <?php esc_html_e( 'Save Form Schema', 'eventflow' ); ?></button>
					<button type="button" class="ef-modal-close" data-dismiss="modal">&times;</button>
				</div>
			</div>

			<div class="ef-modal-body" style="padding:0;">
				<input type="hidden" id="ef-fb-activity-id" value="0">
				
				<div class="ef-fb-workspace" style="display:flex; height:620px; overflow:hidden;">
					<!-- 1. LEFT PALETTE: 18 FIELD TYPES -->
					<div class="ef-fb-palette" style="width:260px; border-right:1px solid #E2E8F0; padding:16px; overflow-y:auto; background:#F8FAFC;">
						<h5 style="margin:0 0 10px; font-size:12px; text-transform:uppercase; color:#64748B; letter-spacing:0.5px;"><?php esc_html_e( 'Text & Contact', 'eventflow' ); ?></h5>
						<div class="ef-fb-palette-group" style="display:flex; flex-direction:column; gap:6px; margin-bottom:16px;">
							<button type="button" class="ef-fb-palette-btn" data-type="text">📝 <?php esc_html_e( 'Single Line Text', 'eventflow' ); ?></button>
							<button type="button" class="ef-fb-palette-btn" data-type="textarea">📄 <?php esc_html_e( 'Multi-line Text', 'eventflow' ); ?></button>
							<button type="button" class="ef-fb-palette-btn" data-type="email">✉️ <?php esc_html_e( 'Email Address', 'eventflow' ); ?></button>
							<button type="button" class="ef-fb-palette-btn" data-type="tel">📞 <?php esc_html_e( 'Phone Number', 'eventflow' ); ?></button>
							<button type="button" class="ef-fb-palette-btn" data-type="number">🔢 <?php esc_html_e( 'Number / Quantity', 'eventflow' ); ?></button>
							<button type="button" class="ef-fb-palette-btn" data-type="cnic">🪪 <?php esc_html_e( 'CNIC / National ID', 'eventflow' ); ?></button>
						</div>

						<h5 style="margin:0 0 10px; font-size:12px; text-transform:uppercase; color:#64748B; letter-spacing:0.5px;"><?php esc_html_e( 'Choices & Options', 'eventflow' ); ?></h5>
						<div class="ef-fb-palette-group" style="display:flex; flex-direction:column; gap:6px; margin-bottom:16px;">
							<button type="button" class="ef-fb-palette-btn" data-type="select">🔽 <?php esc_html_e( 'Dropdown Select', 'eventflow' ); ?></button>
							<button type="button" class="ef-fb-palette-btn" data-type="multi_select">📑 <?php esc_html_e( 'Multi-select List', 'eventflow' ); ?></button>
							<button type="button" class="ef-fb-palette-btn" data-type="radio">🔘 <?php esc_html_e( 'Radio Buttons', 'eventflow' ); ?></button>
							<button type="button" class="ef-fb-palette-btn" data-type="checkbox">☑️ <?php esc_html_e( 'Checkbox Group', 'eventflow' ); ?></button>
							<button type="button" class="ef-fb-palette-btn" data-type="consent">✅ <?php esc_html_e( 'Consent / Agreement', 'eventflow' ); ?></button>
						</div>

						<h5 style="margin:0 0 10px; font-size:12px; text-transform:uppercase; color:#64748B; letter-spacing:0.5px;"><?php esc_html_e( 'Date, Time & Media', 'eventflow' ); ?></h5>
						<div class="ef-fb-palette-group" style="display:flex; flex-direction:column; gap:6px; margin-bottom:16px;">
							<button type="button" class="ef-fb-palette-btn" data-type="date">📅 <?php esc_html_e( 'Date Picker', 'eventflow' ); ?></button>
							<button type="button" class="ef-fb-palette-btn" data-type="time">⏰ <?php esc_html_e( 'Time Picker', 'eventflow' ); ?></button>
							<button type="button" class="ef-fb-palette-btn" data-type="file">📎 <?php esc_html_e( 'File Upload', 'eventflow' ); ?></button>
							<button type="button" class="ef-fb-palette-btn" data-type="url">🌐 <?php esc_html_e( 'Website / URL', 'eventflow' ); ?></button>
						</div>

						<h5 style="margin:0 0 10px; font-size:12px; text-transform:uppercase; color:#64748B; letter-spacing:0.5px;"><?php esc_html_e( 'Layout & Text', 'eventflow' ); ?></h5>
						<div class="ef-fb-palette-group" style="display:flex; flex-direction:column; gap:6px;">
							<button type="button" class="ef-fb-palette-btn" data-type="heading">🏷️ <?php esc_html_e( 'Section Heading', 'eventflow' ); ?></button>
							<button type="button" class="ef-fb-palette-btn" data-type="paragraph">ℹ️ <?php esc_html_e( 'Information Text', 'eventflow' ); ?></button>
							<button type="button" class="ef-fb-palette-btn" data-type="divider">➖ <?php esc_html_e( 'Divider Line', 'eventflow' ); ?></button>
						</div>
					</div>

					<!-- 2. CENTER CANVAS: ACTIVE FIELDS LIST -->
					<div class="ef-fb-canvas" style="flex:1; padding:20px; overflow-y:auto; background:#FFFFFF;">
						<div class="ef-fb-canvas-header ef-flex-between" style="margin-bottom:14px;">
							<span class="ef-text-sm ef-text-muted"><?php esc_html_e( 'Drag or use action buttons to arrange form fields. Click a field to edit its properties.', 'eventflow' ); ?></span>
							<span class="ef-badge ef-badge-neutral" id="ef-fb-fields-count">0 Fields</span>
						</div>

						<div id="ef-fb-fields-list" class="ef-fb-fields-list" style="display:flex; flex-direction:column; gap:10px;">
							<!-- Dynamically populated via JavaScript -->
						</div>

						<div id="ef-fb-empty-state" class="ef-empty-state" style="padding:60px 20px; border:2px dashed #E2E8F0; border-radius:8px;">
							<div class="ef-empty-icon">📋</div>
							<h4><?php esc_html_e( 'No Custom Fields Yet', 'eventflow' ); ?></h4>
							<p><?php esc_html_e( 'Click any field type from the left palette to add questions to this competition form.', 'eventflow' ); ?></p>
						</div>
					</div>

					<!-- 3. RIGHT INSPECTOR: FIELD PROPERTY EDITOR -->
					<div class="ef-fb-inspector" id="ef-fb-inspector" style="width:300px; border-left:1px solid #E2E8F0; padding:16px; overflow-y:auto; background:#F8FAFC; display:none;">
						<div class="ef-flex-between" style="margin-bottom:12px; border-bottom:1px solid #E2E8F0; padding-bottom:8px;">
							<h4 style="margin:0; font-size:14px;"><?php esc_html_e( 'Field Properties', 'eventflow' ); ?></h4>
							<span class="ef-tag" id="ef-prop-type-badge">Text</span>
						</div>

						<input type="hidden" id="ef-prop-field-index" value="-1">

						<div class="ef-form-group">
							<label class="ef-text-xs"><?php esc_html_e( 'Field Label', 'eventflow' ); ?> <span class="ef-req">*</span></label>
							<input type="text" id="ef-prop-label" class="ef-input ef-input-sm" required>
						</div>

						<div class="ef-form-group">
							<label class="ef-text-xs"><?php esc_html_e( 'Field Key / Name (unique identifier)', 'eventflow' ); ?> <span class="ef-req">*</span></label>
							<input type="text" id="ef-prop-key" class="ef-input ef-input-sm" required>
						</div>

						<div class="ef-form-group" id="ef-prop-placeholder-group">
							<label class="ef-text-xs"><?php esc_html_e( 'Placeholder Text', 'eventflow' ); ?></label>
							<input type="text" id="ef-prop-placeholder" class="ef-input ef-input-sm">
						</div>

						<div class="ef-form-group">
							<label class="ef-text-xs"><?php esc_html_e( 'Help / Description Text', 'eventflow' ); ?></label>
							<textarea id="ef-prop-help" class="ef-input ef-input-sm" rows="2"></textarea>
						</div>

						<div class="ef-form-group" id="ef-prop-required-group">
							<label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px;">
								<input type="checkbox" id="ef-prop-required" value="1">
								<strong><?php esc_html_e( 'Required Field', 'eventflow' ); ?></strong>
							</label>
						</div>

						<!-- Options for Choice Fields -->
						<div class="ef-form-group" id="ef-prop-options-group" style="display:none;">
							<label class="ef-text-xs"><?php esc_html_e( 'Options (one per line)', 'eventflow' ); ?> <span class="ef-req">*</span></label>
							<textarea id="ef-prop-options" class="ef-input ef-input-sm" rows="4" placeholder="Option 1&#10;Option 2&#10;Option 3"></textarea>
						</div>

						<!-- File Upload Rules -->
						<div id="ef-prop-file-group" style="display:none;">
							<div class="ef-form-group">
								<label class="ef-text-xs"><?php esc_html_e( 'Allowed Extensions (comma separated)', 'eventflow' ); ?></label>
								<input type="text" id="ef-prop-extensions" class="ef-input ef-input-sm" placeholder="pdf, docx, zip, jpg">
							</div>
							<div class="ef-form-group">
								<label class="ef-text-xs"><?php esc_html_e( 'Max File Size (MB)', 'eventflow' ); ?></label>
								<input type="number" id="ef-prop-maxsize" class="ef-input ef-input-sm" value="5" min="1" max="25">
							</div>
						</div>

						<div class="ef-form-group">
							<label class="ef-text-xs"><?php esc_html_e( 'Default Value', 'eventflow' ); ?></label>
							<input type="text" id="ef-prop-default" class="ef-input ef-input-sm">
						</div>

						<div class="ef-form-group">
							<label class="ef-text-xs"><?php esc_html_e( 'Custom CSS Class', 'eventflow' ); ?></label>
							<input type="text" id="ef-prop-class" class="ef-input ef-input-sm" placeholder="e.g. col-span-2">
						</div>

						<button type="button" class="ef-btn ef-btn-primary ef-btn-sm ef-btn-block" id="ef-apply-properties-btn">
							<?php esc_html_e( 'Apply Changes', 'eventflow' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- LIVE FORM PREVIEW MODAL -->
	<div class="ef-modal" id="ef-form-preview-modal" aria-hidden="true">
		<div class="ef-modal-backdrop"></div>
		<div class="ef-modal-dialog ef-modal-lg">
			<div class="ef-modal-header">
				<h3 class="ef-modal-title">👁️ <?php esc_html_e( 'Live Form Preview', 'eventflow' ); ?></h3>
				<button type="button" class="ef-modal-close" data-dismiss="modal">&times;</button>
			</div>
			<div class="ef-modal-body">
				<div id="ef-form-preview-canvas" style="padding:16px;"></div>
			</div>
			<div class="ef-modal-footer">
				<button type="button" class="ef-btn ef-btn-secondary" data-dismiss="modal"><?php esc_html_e( 'Close Preview', 'eventflow' ); ?></button>
			</div>
		</div>
	</div>

	<!-- REGISTRATION FULL DETAILS MODAL -->
	<div class="ef-modal" id="ef-reg-details-modal" aria-hidden="true">
		<div class="ef-modal-backdrop"></div>
		<div class="ef-modal-dialog ef-modal-lg">
			<div class="ef-modal-header">
				<h3 class="ef-modal-title" id="ef-reg-modal-title"><?php esc_html_e( 'Application Details', 'eventflow' ); ?></h3>
				<button type="button" class="ef-modal-close" data-dismiss="modal">&times;</button>
			</div>
			<div class="ef-modal-body" id="ef-reg-modal-content">
				<div class="ef-spinner"></div>
			</div>
			<div class="ef-modal-footer">
				<button type="button" class="ef-btn ef-btn-secondary" data-dismiss="modal"><?php esc_html_e( 'Close', 'eventflow' ); ?></button>
			</div>
		</div>
	</div>

	<!-- EDIT APPLICATION STATUS MODAL -->
	<div class="ef-modal" id="ef-status-edit-modal" aria-hidden="true">
		<div class="ef-modal-backdrop"></div>
		<div class="ef-modal-dialog">
			<div class="ef-modal-header">
				<h3 class="ef-modal-title"><?php esc_html_e( 'Update Application Status', 'eventflow' ); ?></h3>
				<button type="button" class="ef-modal-close" data-dismiss="modal">&times;</button>
			</div>
			<form id="ef-update-status-form">
				<div class="ef-modal-body">
					<input type="hidden" name="registration_id" id="ef-edit-status-reg-id" value="0">

					<div class="ef-form-group">
						<label><?php esc_html_e( 'Application Status', 'eventflow' ); ?> <span class="ef-req">*</span></label>
						<select name="status" id="ef-edit-status-select" class="ef-input" required>
							<option value="submitted"><?php esc_html_e( 'Submitted', 'eventflow' ); ?></option>
							<option value="under_review"><?php esc_html_e( 'Under Review', 'eventflow' ); ?></option>
							<option value="approved"><?php esc_html_e( 'Approved', 'eventflow' ); ?></option>
							<option value="rejected"><?php esc_html_e( 'Rejected', 'eventflow' ); ?></option>
							<option value="completed"><?php esc_html_e( 'Completed', 'eventflow' ); ?></option>
						</select>
					</div>

					<div class="ef-form-group" id="ef-rejection-reason-group" style="display: none;">
						<label><?php esc_html_e( 'Rejection Reason (Dispatched to Participant)', 'eventflow' ); ?> <span class="ef-req">*</span></label>
						<textarea name="rejection_reason" id="ef-rejection-reason-input" class="ef-input" rows="3" placeholder="<?php esc_attr_e( 'Specify why the application cannot be accepted...', 'eventflow' ); ?>"></textarea>
					</div>

					<div class="ef-form-group">
						<label><?php esc_html_e( 'Internal Staff Notes (Optional)', 'eventflow' ); ?></label>
						<textarea name="admin_notes" id="ef-admin-notes-input" class="ef-input" rows="2"></textarea>
					</div>
				</div>
				<div class="ef-modal-footer">
					<button type="button" class="ef-btn ef-btn-secondary" data-dismiss="modal"><?php esc_html_e( 'Cancel', 'eventflow' ); ?></button>
					<button type="submit" class="ef-btn ef-btn-primary"><?php esc_html_e( 'Save Status Change', 'eventflow' ); ?></button>
				</div>
			</form>
		</div>
	</div>

	<!-- CREATE / EDIT ACTIVITY MODAL -->
	<div class="ef-modal" id="ef-activity-edit-modal" aria-hidden="true">
		<div class="ef-modal-backdrop"></div>
		<div class="ef-modal-dialog ef-modal-lg">
			<div class="ef-modal-header">
				<h3 class="ef-modal-title" id="ef-act-modal-title"><?php esc_html_e( 'Create Activity / Track', 'eventflow' ); ?></h3>
				<button type="button" class="ef-modal-close" data-dismiss="modal">&times;</button>
			</div>
			<form id="ef-activity-form">
				<div class="ef-modal-body">
					<input type="hidden" name="activity_id" id="ef-act-id" value="0">

					<div class="ef-form-group">
						<label><?php esc_html_e( 'Activity Title', 'eventflow' ); ?> <span class="ef-req">*</span></label>
						<input type="text" name="title" id="ef-act-title" class="ef-input" placeholder="e.g. Speed Programming Championship" required>
					</div>

					<div class="ef-form-group">
						<label><?php esc_html_e( 'Description & Overview', 'eventflow' ); ?></label>
						<textarea name="description" id="ef-act-desc" class="ef-input" rows="3"></textarea>
					</div>

					<div class="ef-grid ef-grid-3">
						<div class="ef-form-group">
							<label><?php esc_html_e( 'Participation Type', 'eventflow' ); ?></label>
							<select name="participation_type" id="ef-act-type" class="ef-input">
								<option value="both"><?php esc_html_e( 'Both Solo and Team', 'eventflow' ); ?></option>
								<option value="team"><?php esc_html_e( 'Team Registration Only', 'eventflow' ); ?></option>
								<option value="solo"><?php esc_html_e( 'Solo Registration Only', 'eventflow' ); ?></option>
							</select>
						</div>

						<div class="ef-form-group">
							<label><?php esc_html_e( 'Min Team Size', 'eventflow' ); ?></label>
							<input type="number" name="min_team_size" id="ef-act-min-team" class="ef-input" value="1" min="1">
						</div>

						<div class="ef-form-group">
							<label><?php esc_html_e( 'Max Team Size', 'eventflow' ); ?></label>
							<input type="number" name="max_team_size" id="ef-act-max-team" class="ef-input" value="4" min="1">
						</div>
					</div>

					<div class="ef-grid ef-grid-3">
						<div class="ef-form-group">
							<label><?php esc_html_e( 'Registration Fee', 'eventflow' ); ?></label>
							<input type="number" step="0.01" name="reg_fee" id="ef-act-fee" class="ef-input" value="0.00">
						</div>

						<div class="ef-form-group">
							<label><?php esc_html_e( 'Max Participants Cap (0 = Unlimited)', 'eventflow' ); ?></label>
							<input type="number" name="max_participants" id="ef-act-max-participants" class="ef-input" value="0">
						</div>

						<div class="ef-form-group">
							<label><?php esc_html_e( 'Status', 'eventflow' ); ?></label>
							<select name="status" id="ef-act-status" class="ef-input">
								<option value="published"><?php esc_html_e( 'Published (Visible & Active)', 'eventflow' ); ?></option>
								<option value="draft"><?php esc_html_e( 'Draft (Hidden)', 'eventflow' ); ?></option>
								<option value="closed"><?php esc_html_e( 'Closed', 'eventflow' ); ?></option>
							</select>
						</div>
					</div>

					<div class="ef-grid ef-grid-3">
						<div class="ef-form-group">
							<label><?php esc_html_e( 'Fee Calculation Model', 'eventflow' ); ?></label>
							<select name="fee_calculation" id="ef-act-fee-calc" class="ef-input">
								<option value="per_team"><?php esc_html_e( 'Flat Fee per Entry / Team', 'eventflow' ); ?></option>
								<option value="per_person"><?php esc_html_e( 'Per Person / Member Fee', 'eventflow' ); ?></option>
							</select>
						</div>

						<div class="ef-form-group">
							<label><?php esc_html_e( 'Registration Deadline', 'eventflow' ); ?></label>
							<input type="datetime-local" name="reg_deadline" id="ef-act-deadline" class="ef-input">
						</div>

						<div class="ef-form-group">
							<label><?php esc_html_e( 'Banner / Logo Image URL', 'eventflow' ); ?></label>
							<input type="url" name="image_url" id="ef-act-image-url" class="ef-input" placeholder="https://...">
						</div>
					</div>

					<div class="ef-form-group">
						<label><?php esc_html_e( 'Eligibility Criteria', 'eventflow' ); ?></label>
						<textarea name="eligibility" id="ef-act-eligibility" class="ef-input" rows="2" placeholder="<?php esc_attr_e( 'e.g. Enrolled undergraduate students only...', 'eventflow' ); ?>"></textarea>
					</div>

					<div class="ef-form-group">
						<label><?php esc_html_e( 'Special Instructions & Rules', 'eventflow' ); ?></label>
						<textarea name="instructions" id="ef-act-instructions" class="ef-input" rows="2" placeholder="<?php esc_attr_e( 'e.g. Allowed compilers, hardware specs...', 'eventflow' ); ?>"></textarea>
					</div>
				</div>

				<div class="ef-modal-footer">
					<button type="button" class="ef-btn ef-btn-secondary" data-dismiss="modal"><?php esc_html_e( 'Cancel', 'eventflow' ); ?></button>
					<button type="submit" class="ef-btn ef-btn-primary"><?php esc_html_e( 'Save Activity', 'eventflow' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

