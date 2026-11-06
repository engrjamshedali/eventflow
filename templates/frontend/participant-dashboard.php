<?php
/**
 * EventFlow Live Events & Participant Dashboard Template
 *
 * Implements the Live Events discovery interface, header, filtering,
 * card grid, details modal, and registration workflow tabs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user          = wp_get_current_user();
$active_tab    = isset( $_GET['tab'] ) 
	? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) 
	: ( ( isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'reupload', 'payment' ), true ) ) ? 'payment' : 'events' );
$registrations = EventFlow_Registration_Manager::get_user_registrations( $user->ID );
$event         = EventFlow_Event_Manager::get_active_event();
$currency      = $event ? esc_html( $event->currency ) : 'PKR';
$announcements = $event ? EventFlow_Event_Manager::get_announcements( $event->id, 5 ) : array();
$unread_notifications_count = class_exists( 'EventFlow_Notification_Manager' ) ? EventFlow_Notification_Manager::get_unread_count( $user->ID ) : 0;

// Fetch all published activities from the real database
$activities = EventFlow_Activity_Manager::get_activities( array(
	'status'   => 'published',
	'event_id' => $event ? $event->id : 0,
	'orderby'  => 'id',
	'order'    => 'ASC',
) );

// Map user's registered activity IDs for quick lookup
$user_registered_activity_ids = array();
if ( ! empty( $registrations ) ) {
	foreach ( $registrations as $r ) {
		$user_registered_activity_ids[ (int) $r->activity_id ] = $r->reg_id;
	}
}

// Brand logo setup
$logo_url = '';
if ( $event && ! empty( $event->logo_url ) ) {
	$logo_url = $event->logo_url;
} else {
	$brand_dir = EVENTFLOW_PLUGIN_DIR . 'assets/images/branding/';
	$brand_url = EVENTFLOW_PLUGIN_URL . 'assets/images/branding/';
	if ( file_exists( $brand_dir . 'sukkur_iba_logo.png' ) ) {
		$logo_url = $brand_url . 'sukkur_iba_logo.png';
	} elseif ( file_exists( $brand_dir . 'cse_society_logo.png' ) ) {
		$logo_url = $brand_url . 'cse_society_logo.png';
	}
}

$event_title = $event ? $event->title : __( 'SIBACOM — Sukkur IBA University', 'eventflow' );
?>

<div class="eventflow-app ef-live-dashboard">

	<!-- ====================================================
	     TOP HEADER
	     ==================================================== -->
	<header class="ef-top-header">
		<div class="ef-header-inner">
			<!-- Platform Logo & Portal Brand -->
			<div class="ef-header-brand">
				<a href="<?php echo esc_url( EventFlow_Shortcodes::get_dashboard_url() ); ?>" class="ef-brand-link">
					<?php if ( $logo_url ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $event_title ); ?>" class="ef-header-logo-img">
					<?php else : ?>
						<div class="ef-header-logo-fallback">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>
						</div>
					<?php endif; ?>
					<div class="ef-brand-text">
						<span class="ef-brand-name"><?php echo esc_html( $event_title ); ?></span>
						<span class="ef-brand-sub"><?php esc_html_e( 'Participant Portal', 'eventflow' ); ?></span>
					</div>
				</a>
			</div>

			<!-- Participant Navigation Links -->
			<nav class="ef-header-nav" aria-label="<?php esc_attr_e( 'Participant Portal Navigation', 'eventflow' ); ?>">
				<a href="?tab=events" class="ef-nav-link <?php echo 'events' === $active_tab || 'overview' === $active_tab ? 'active' : ''; ?>">
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
					<span class="ef-nav-label"><?php esc_html_e( 'Live Events', 'eventflow' ); ?></span>
				</a>

				<a href="?tab=registrations" class="ef-nav-link <?php echo 'registrations' === $active_tab ? 'active' : ''; ?>">
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
					<span class="ef-nav-label"><?php esc_html_e( 'My Registrations', 'eventflow' ); ?></span>
					<?php if ( ! empty( $registrations ) ) : ?>
						<span class="ef-badge-count"><?php echo count( $registrations ); ?></span>
					<?php endif; ?>
				</a>

				<a href="?tab=notifications" class="ef-nav-link <?php echo ( 'notifications' === $active_tab || 'announcements' === $active_tab ) ? 'active' : ''; ?>" title="<?php esc_attr_e( 'Notifications', 'eventflow' ); ?>">
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
					<span class="ef-nav-label"><?php esc_html_e( 'Notifications', 'eventflow' ); ?></span>
					<?php if ( $unread_notifications_count > 0 ) : ?>
						<span class="ef-badge-count ef-badge-alert" id="ef-nav-unread-count" style="background:#EF4444; color:#FFF; font-weight:700;"><?php echo esc_html( $unread_notifications_count ); ?></span>
					<?php elseif ( ! empty( $announcements ) ) : ?>
						<span class="ef-badge-dot"></span>
					<?php endif; ?>
				</a>

				<a href="?tab=profile" class="ef-nav-link <?php echo 'profile' === $active_tab ? 'active' : ''; ?>">
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
					<span class="ef-nav-label"><?php esc_html_e( 'Profile', 'eventflow' ); ?></span>
				</a>
			</nav>

			<!-- Right User Profile & Controls -->
			<div class="ef-header-user-section">
				<?php if ( EventFlow_Roles::is_staff() ) : ?>
					<a href="<?php echo esc_url( EventFlow_Shortcodes::get_staff_dashboard_url() ); ?>" class="ef-btn-staff-pill" title="<?php esc_attr_e( 'Switch to Staff Dashboard', 'eventflow' ); ?>">
						⚡ <span class="ef-staff-label"><?php esc_html_e( 'Staff Dashboard', 'eventflow' ); ?></span>
					</a>
				<?php endif; ?>

				<!-- User Profile Pill -->
				<a href="?tab=profile" class="ef-user-profile-pill" style="text-decoration:none;" title="<?php esc_attr_e( 'Manage profile', 'eventflow' ); ?>">
					<div class="ef-user-avatar">
						<?php echo esc_html( strtoupper( substr( $user->display_name ? $user->display_name : $user->user_login, 0, 2 ) ) ); ?>
					</div>
					<div class="ef-user-details">
						<span class="ef-user-welcome"><?php printf( esc_html__( 'Hi, %s', 'eventflow' ), esc_html( $user->display_name ) ); ?></span>
						<span class="ef-user-subtext"><?php echo esc_html( $user->user_email ); ?></span>
					</div>
				</a>

				<!-- Logout Button -->
				<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>" class="ef-btn-logout" title="<?php esc_attr_e( 'Sign out of account', 'eventflow' ); ?>">
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
					<span class="ef-logout-text"><?php esc_html_e( 'Logout', 'eventflow' ); ?></span>
				</a>
			</div>
		</div>
	</header>

	<!-- ====================================================
	     MAIN DASHBOARD BODY
	     ==================================================== -->
	<main class="ef-dashboard-main">
		<div class="ef-dash-content-container">

			<?php
			$rejected_regs = array();
			if ( ! empty( $registrations ) ) {
				foreach ( $registrations as $_r ) {
					if ( 'rejected' === $_r->payment_status ) {
						$rejected_regs[] = $_r;
					}
				}
			}
			?>
			<?php if ( ! empty( $rejected_regs ) && 'payment' !== $active_tab ) : ?>
				<?php foreach ( $rejected_regs as $rej_item ) : ?>
					<div class="ef-alert ef-alert-danger ef-dash-action-banner" style="margin-bottom: 24px; border-left: 5px solid #DC2626; background: #FEF2F2; padding: 18px 22px; border-radius: 10px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.08);">
						<div style="display:flex; align-items:center; gap:14px; flex:1; min-width:280px;">
							<span style="font-size:26px;">⚠️</span>
							<div>
								<div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
									<h4 style="margin:0; color:#991B1B; font-size:15px; font-weight:700;">
										<?php printf( esc_html__( 'Payment Verification Unsuccessful: %s', 'eventflow' ), esc_html( $rej_item->activity_title ) ); ?>
									</h4>
									<span class="ef-badge ef-badge-danger" style="font-size:11px;"><?php esc_html_e( 'Rejected – Re-upload Required', 'eventflow' ); ?></span>
								</div>
								<p style="margin:6px 0 0; color:#7F1D1D; font-size:13.5px; line-height:1.4;">
									<strong><?php esc_html_e( 'Reason:', 'eventflow' ); ?></strong> <?php echo esc_html( ! empty( $rej_item->rejection_reason ) ? $rej_item->rejection_reason : ( ! empty( $rej_item->admin_notes ) ? $rej_item->admin_notes : __( 'Payment proof could not be verified. Please review and re-upload.', 'eventflow' ) ) ); ?>
								</p>
							</div>
						</div>
						<div>
							<a href="?tab=payment&action=reupload&reg_id=<?php echo esc_attr( $rej_item->reg_id ); ?>" class="ef-btn-teal" style="display:inline-flex; align-items:center; gap:6px; font-weight:600; padding:10px 20px; border-radius:8px; text-decoration:none; white-space:nowrap; box-shadow:0 2px 4px rgba(13,148,136,0.2);">
								🔄 <?php esc_html_e( 'Re-upload Payment Proof', 'eventflow' ); ?> &rarr;
							</a>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

			<!-- ----------------------------------------------------
			     TAB 1: LIVE EVENTS DISCOVERY (Default & Primary View)
			     ---------------------------------------------------- -->
			<?php if ( 'events' === $active_tab || 'overview' === $active_tab ) : ?>
				
				<!-- Hero Section Header -->
				<div class="ef-discovery-hero">
					<div class="ef-hero-text">
						<div class="ef-hero-badge">
							<span class="ef-dot-live"></span>
							<?php esc_html_e( 'OFFICIAL EVENT DIRECTORY', 'eventflow' ); ?>
						</div>
						<h1 class="ef-hero-title"><?php esc_html_e( 'Explore Live Events', 'eventflow' ); ?></h1>
						<p class="ef-hero-subtitle">
							<?php esc_html_e( 'Discover exciting events, competitions and activities. Find your next opportunity and register today.', 'eventflow' ); ?>
						</p>
					</div>

					<!-- Quick Stats Pill Bar -->
					<div class="ef-hero-stats">
						<div class="ef-stat-box">
							<span class="ef-stat-num"><?php echo count( $activities ); ?></span>
							<span class="ef-stat-label"><?php esc_html_e( 'Total Activities', 'eventflow' ); ?></span>
						</div>
						<div class="ef-stat-box">
							<span class="ef-stat-num"><?php echo count( $registrations ); ?></span>
							<span class="ef-stat-label"><?php esc_html_e( 'My Entries', 'eventflow' ); ?></span>
						</div>
					</div>
				</div>

				<!-- Announcements Banner (if any) -->
				<?php if ( ! empty( $announcements ) ) : ?>
					<div class="ef-announcement-banner">
						<div class="ef-ann-icon">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
						</div>
						<div class="ef-ann-content">
							<span class="ef-ann-title"><?php echo esc_html( $announcements[0]->title ); ?></span>
							<span class="ef-ann-snippet"><?php echo esc_html( wp_trim_words( $announcements[0]->content, 20 ) ); ?></span>
						</div>
						<a href="?tab=announcements" class="ef-ann-link"><?php esc_html_e( 'View All', 'eventflow' ); ?> &rarr;</a>
					</div>
				<?php endif; ?>

				<!-- Event Filtering & Search Controls -->
				<div class="ef-filter-toolbar">
					<!-- Filter Category Tabs -->
					<div class="ef-filter-pills" id="ef-filter-pills">
						<button type="button" class="ef-filter-btn active" data-filter="all">
							<?php esc_html_e( 'All Events', 'eventflow' ); ?>
							<span class="ef-btn-count"><?php echo count( $activities ); ?></span>
						</button>
						<button type="button" class="ef-filter-btn" data-filter="open">
							<span class="ef-status-indicator indicator-open"></span>
							<?php esc_html_e( 'Registration Open', 'eventflow' ); ?>
						</button>
						<button type="button" class="ef-filter-btn" data-filter="coming_soon">
							<span class="ef-status-indicator indicator-coming"></span>
							<?php esc_html_e( 'Coming Soon', 'eventflow' ); ?>
						</button>
						<button type="button" class="ef-filter-btn" data-filter="closed">
							<span class="ef-status-indicator indicator-closed"></span>
							<?php esc_html_e( 'Registration Closed', 'eventflow' ); ?>
						</button>
						<button type="button" class="ef-filter-btn" data-filter="registered">
							<span class="ef-status-indicator indicator-registered"></span>
							<?php esc_html_e( 'My Registered Events', 'eventflow' ); ?>
							<?php if ( ! empty( $registrations ) ) : ?>
								<span class="ef-btn-count"><?php echo count( $registrations ); ?></span>
							<?php endif; ?>
						</button>
					</div>

					<!-- Live Search Input -->
					<div class="ef-search-box">
						<span class="ef-search-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
						</span>
						<input type="text" id="ef-event-search-input" class="ef-search-input" placeholder="<?php esc_attr_e( 'Search events, competitions or tracks...', 'eventflow' ); ?>" autocomplete="off">
						<button type="button" class="ef-search-clear" id="ef-search-clear" style="display:none;" aria-label="<?php esc_attr_e( 'Clear search', 'eventflow' ); ?>">&times;</button>
					</div>
				</div>

				<!-- Event Cards Responsive Grid -->
				<?php if ( empty( $activities ) ) : ?>
					<div class="ef-empty-discovery">
						<div class="ef-empty-icon">📅</div>
						<h3><?php esc_html_e( 'No Live Events Published Yet', 'eventflow' ); ?></h3>
						<p><?php esc_html_e( 'The event organizers have not published activities at this moment. Please check back shortly.', 'eventflow' ); ?></p>
					</div>
				<?php else : ?>
					<div class="ef-discovery-grid" id="ef-discovery-grid">
						<?php foreach ( $activities as $act ) : ?>
							<?php
							// Determine accurate status based on database rules
							$now = current_time( 'timestamp' );
							$is_open = EventFlow_Activity_Manager::is_open_for_registration( $act );
							$is_registered = isset( $user_registered_activity_ids[ (int) $act->id ] );

							$status_code  = 'open';
							$status_label = __( 'Registration Open', 'eventflow' );
							$status_class = 'ef-badge-status-open';

							if ( (int) $act->max_participants > 0 && (int) $act->current_participants >= (int) $act->max_participants ) {
								$status_code  = 'full';
								$status_label = __( 'Fully Booked', 'eventflow' );
								$status_class = 'ef-badge-status-full';
							} elseif ( ! empty( $act->reg_deadline ) && $now > strtotime( $act->reg_deadline ) ) {
								$status_code  = 'closed';
								$status_label = __( 'Registration Closed', 'eventflow' );
								$status_class = 'ef-badge-status-closed';
							} elseif ( 'published' !== $act->status ) {
								$status_code  = 'closed';
								$status_label = __( 'Registration Closed', 'eventflow' );
								$status_class = 'ef-badge-status-closed';
							} elseif ( ! $is_open ) {
								$status_code  = 'coming_soon';
								$status_label = __( 'Coming Soon', 'eventflow' );
								$status_class = 'ef-badge-status-coming';
							}

							// Participation type formatted label
							$part_type = $act->participation_type;
							if ( 'both' === $part_type ) {
								$part_label = sprintf( __( 'Solo or Team (%d-%d Members)', 'eventflow' ), (int) $act->min_team_size, (int) $act->max_team_size );
							} elseif ( 'team' === $part_type ) {
								$part_label = sprintf( __( 'Team Only (%d-%d Members)', 'eventflow' ), (int) $act->min_team_size, (int) $act->max_team_size );
							} else {
								$part_label = __( 'Solo Participation', 'eventflow' );
							}

							// Fee formatted
							$fee_formatted = floatval( $act->reg_fee ) > 0 ? sprintf( '%s %s', esc_html( $currency ), number_format( $act->reg_fee, 2 ) ) : __( 'Free Entry', 'eventflow' );

							// Slots / capacity
							if ( (int) $act->max_participants > 0 ) {
								$remaining = max( 0, (int) $act->max_participants - (int) $act->current_participants );
								$slots_label = sprintf( __( '%d / %d Slots Left', 'eventflow' ), $remaining, (int) $act->max_participants );
							} else {
								$slots_label = __( 'Open Capacity', 'eventflow' );
							}

							// Dates
							$event_date_str = '';
							if ( $event && $event->start_date ) {
								$event_date_str = date_i18n( 'M j, Y', strtotime( $event->start_date ) );
							}
							$deadline_str = ! empty( $act->reg_deadline ) ? date_i18n( 'M j, Y', strtotime( $act->reg_deadline ) ) : __( 'Standard Deadline', 'eventflow' );

							// Banner image fallback
							$banner_img = $act->image_url;
							?>

							<article class="ef-event-card"
								data-status="<?php echo esc_attr( $status_code ); ?>"
								data-registered="<?php echo $is_registered ? 'true' : 'false'; ?>"
								data-title="<?php echo esc_attr( strtolower( $act->title ) ); ?>"
								data-desc="<?php echo esc_attr( strtolower( $act->description ) ); ?>">
								
								<!-- Event Banner / Graphic -->
								<div class="ef-card-banner">
									<?php if ( ! empty( $banner_img ) ) : ?>
										<img src="<?php echo esc_url( $banner_img ); ?>" alt="<?php echo esc_attr( $act->title ); ?>" class="ef-banner-media" loading="lazy">
									<?php else : ?>
										<div class="ef-banner-placeholder">
											<div class="ef-placeholder-art">
												<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
											</div>
											<span class="ef-placeholder-title"><?php echo esc_html( $act->title ); ?></span>
										</div>
									<?php endif; ?>

									<!-- Status Pill Badge Overlay -->
									<div class="ef-banner-badges">
										<span class="ef-status-pill <?php echo esc_attr( $status_class ); ?>">
											<span class="ef-pill-dot"></span>
											<?php echo esc_html( $status_label ); ?>
										</span>

										<?php if ( $is_registered ) : ?>
											<span class="ef-registered-pill">
												✓ <?php esc_html_e( 'Registered', 'eventflow' ); ?>
											</span>
										<?php endif; ?>
									</div>
								</div>

								<!-- Card Body Content -->
								<div class="ef-card-body">
									<h3 class="ef-card-title"><?php echo esc_html( $act->title ); ?></h3>
									<p class="ef-card-desc"><?php echo esc_html( wp_trim_words( $act->description, 16 ) ); ?></p>

									<!-- Event Details Meta Grid -->
									<div class="ef-card-meta-grid">
										<?php if ( $event_date_str ) : ?>
											<div class="ef-meta-cell">
												<span class="ef-cell-label"><?php esc_html_e( 'Event Date', 'eventflow' ); ?></span>
												<span class="ef-cell-value">
													<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
													<?php echo esc_html( $event_date_str ); ?>
												</span>
											</div>
										<?php endif; ?>

										<div class="ef-meta-cell">
											<span class="ef-cell-label"><?php esc_html_e( 'Deadline', 'eventflow' ); ?></span>
											<span class="ef-cell-value">
												<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
												<?php echo esc_html( $deadline_str ); ?>
											</span>
										</div>

										<div class="ef-meta-cell">
											<span class="ef-cell-label"><?php esc_html_e( 'Participation', 'eventflow' ); ?></span>
											<span class="ef-cell-value">
												<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
												<?php echo esc_html( $part_label ); ?>
											</span>
										</div>

										<div class="ef-meta-cell">
											<span class="ef-cell-label"><?php esc_html_e( 'Capacity / Slots', 'eventflow' ); ?></span>
											<span class="ef-cell-value">
												<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
												<?php echo esc_html( $slots_label ); ?>
											</span>
										</div>
									</div>

									<!-- Card Pricing Highlight -->
									<div class="ef-card-pricing-row">
										<div class="ef-fee-block">
											<span class="ef-fee-sub"><?php esc_html_e( 'Registration Fee', 'eventflow' ); ?></span>
											<strong class="ef-fee-amount"><?php echo esc_html( $fee_formatted ); ?></strong>
										</div>
									</div>

									<!-- Card Action Buttons -->
									<div class="ef-card-actions">
										<button type="button" class="ef-btn-view-details ef-open-activity-modal" data-id="<?php echo esc_attr( $act->id ); ?>">
											<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
											<?php esc_html_e( 'View Details', 'eventflow' ); ?>
										</button>

										<?php if ( $is_open ) : ?>
											<a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'register_activity', 'activity_id' => $act->id ), EventFlow_Shortcodes::get_dashboard_url() ) ); ?>" class="ef-btn-register-now">
												<?php esc_html_e( 'Register Now', 'eventflow' ); ?> &rarr;
											</a>
										<?php else : ?>
											<button type="button" class="ef-btn-register-disabled" disabled>
												<?php echo esc_html( $status_label ); ?>
											</button>
										<?php endif; ?>
									</div>
								</div>
							</article>
						<?php endforeach; ?>
					</div>

					<!-- No Filter Results Message -->
					<div class="ef-no-results-box" id="ef-no-results" style="display:none;">
						<div class="ef-empty-icon">🔍</div>
						<h4><?php esc_html_e( 'No matching events found', 'eventflow' ); ?></h4>
						<p><?php esc_html_e( 'Try clearing your search query or selecting a different filter.', 'eventflow' ); ?></p>
						<button type="button" class="ef-btn-teal ef-btn-pill" id="ef-reset-filters-btn"><?php esc_html_e( 'Reset Filters', 'eventflow' ); ?></button>
					</div>
				<?php endif; ?>

			<!-- ----------------------------------------------------
			     TAB 2: MY REGISTRATIONS
			     ---------------------------------------------------- -->
			<?php elseif ( 'registrations' === $active_tab ) : ?>
				
				<div class="ef-dash-card">
					<div class="ef-dash-card-header">
						<div>
							<h2 class="ef-dash-card-title"><?php esc_html_e( 'My Submitted Registrations', 'eventflow' ); ?></h2>
							<p class="ef-dash-card-sub"><?php esc_html_e( 'Manage your competition team entries, payment receipts, and event access vouchers.', 'eventflow' ); ?></p>
						</div>
						<a href="?tab=events" class="ef-btn-teal ef-btn-pill">+ <?php esc_html_e( 'Register for Another Event', 'eventflow' ); ?></a>
					</div>

					<?php if ( empty( $registrations ) ) : ?>
						<div class="ef-empty-state">
							<div class="ef-empty-icon">📂</div>
							<h3><?php esc_html_e( 'No Applications Found', 'eventflow' ); ?></h3>
							<p><?php esc_html_e( 'You have not submitted any event registrations yet.', 'eventflow' ); ?></p>
							<a href="?tab=events" class="ef-btn-teal ef-btn-pill"><?php esc_html_e( 'Explore Live Events', 'eventflow' ); ?> &rarr;</a>
						</div>
					<?php else : ?>
						<div class="ef-registrations-stack">
							<?php foreach ( $registrations as $r ) : ?>
								<?php $full_reg = EventFlow_Registration_Manager::get_full_registration_details( $r->id ); ?>
								<div class="ef-reg-item-card">
									<div class="ef-reg-item-head">
										<div class="ef-reg-id-box">
											<span class="ef-reg-label"><?php esc_html_e( 'REGISTRATION ID', 'eventflow' ); ?></span>
											<strong class="ef-reg-code"><?php echo esc_html( $r->reg_id ); ?></strong>
										</div>
										<div class="ef-reg-badges">
											<?php echo EventFlow_Registration_Manager::get_status_badge( $r->application_status, 'app' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											<?php echo EventFlow_Registration_Manager::get_status_badge( $r->payment_status, 'payment' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										</div>
									</div>

									<div class="ef-reg-item-body">
										<div class="ef-reg-meta-row">
											<div>
												<span class="ef-meta-label"><?php esc_html_e( 'Competition / Activity:', 'eventflow' ); ?></span>
												<h4 class="ef-meta-val"><?php echo esc_html( $r->activity_title ); ?></h4>
											</div>
											<div>
												<span class="ef-meta-label"><?php esc_html_e( 'Participation Type:', 'eventflow' ); ?></span>
												<h4 class="ef-meta-val"><?php echo 'team' === $r->participation_type ? sprintf( esc_html__( 'Team: %s', 'eventflow' ), esc_html( $r->team_name ) ) : esc_html__( 'Solo Participant', 'eventflow' ); ?></h4>
											</div>
											<div>
												<span class="ef-meta-label"><?php esc_html_e( 'Registration Fee:', 'eventflow' ); ?></span>
												<h4 class="ef-meta-val ef-fee-teal"><?php echo sprintf( '%s %s', esc_html( $currency ), number_format( $r->total_fee, 2 ) ); ?></h4>
											</div>
										</div>

										<?php if ( ! empty( $full_reg->members ) ) : ?>
											<div class="ef-roster-box">
												<span class="ef-roster-label"><?php esc_html_e( 'Team Roster:', 'eventflow' ); ?></span>
												<div class="ef-roster-pills">
													<?php foreach ( $full_reg->members as $m ) : ?>
														<span class="ef-roster-pill">
															<strong><?php echo esc_html( $m->full_name ); ?></strong>
															(<?php echo esc_html( $m->university ); ?>)
															<?php if ( $m->is_leader ) : ?><span class="ef-lead-tag"><?php esc_html_e( 'Leader', 'eventflow' ); ?></span><?php endif; ?>
														</span>
													<?php endforeach; ?>
												</div>
											</div>
										<?php endif; ?>

										<?php if ( 'rejected' === $r->payment_status || 'rejected' === $r->application_status ) : ?>
											<div class="ef-alert ef-alert-danger" style="margin-top: 14px; border-left: 4px solid #DC2626; background: #FEF2F2; padding: 16px 18px; border-radius: 8px;">
												<div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
													<span style="font-size:18px;">❌</span>
													<h4 style="margin:0; color:#991B1B; font-size:15px; font-weight:700;"><?php esc_html_e( 'Payment Verification Unsuccessful', 'eventflow' ); ?></h4>
												</div>
												<p style="margin:0 0 10px; color:#7F1D1D; font-size:13.5px;"><?php esc_html_e( 'Your payment submission requires correction.', 'eventflow' ); ?></p>
												<div style="background:#FFF; border:1px solid #FECACA; border-radius:6px; padding:10px 14px; margin-bottom:12px;">
													<strong style="color:#B91C1C; font-size:13px;"><?php esc_html_e( "Admin's Reason:", 'eventflow' ); ?></strong>
													<p style="margin:4px 0 0; color:#1E293B; font-size:13.5px; font-weight:500;"><?php echo ! empty( $r->rejection_reason ) ? esc_html( $r->rejection_reason ) : esc_html__( 'Payment proof could not be verified. Please review your receipt and re-upload an authentic proof.', 'eventflow' ); ?></p>
												</div>
												<p style="margin:0 0 12px; color:#7F1D1D; font-size:13px;"><?php esc_html_e( 'Please review the reason and upload a corrected payment receipt.', 'eventflow' ); ?></p>
												<a href="?tab=payment&reg_id=<?php echo esc_attr( $r->reg_id ); ?>" class="ef-btn-teal ef-btn-sm" style="display:inline-block; font-weight:600;">
													🔄 <?php esc_html_e( 'Re-upload Payment Proof', 'eventflow' ); ?> &rarr;
												</a>
											</div>
										<?php elseif ( 'reupload_requested' === $r->payment_status ) : ?>
											<div class="ef-alert ef-alert-warning" style="margin-top: 14px; border-left: 4px solid #F59E0B; background: #FFFBEB; padding: 16px 18px; border-radius: 8px;">
												<strong style="color: #B45309;">⚠️ <?php esc_html_e( 'Action Required — Re-upload Payment Proof:', 'eventflow' ); ?></strong>
												<p style="margin: 4px 0 10px; color: #78350F; font-size: 13.5px;"><?php echo ! empty( $r->rejection_reason ) ? esc_html( $r->rejection_reason ) : esc_html__( 'The verification team has requested a replacement or clearer payment receipt for this application.', 'eventflow' ); ?></p>
												<a href="?tab=payment&reg_id=<?php echo esc_attr( $r->reg_id ); ?>" class="ef-btn-teal ef-btn-sm" style="display: inline-block; font-weight:600;">
													🔄 <?php esc_html_e( 'Re-upload Payment Proof', 'eventflow' ); ?> &rarr;
												</a>
											</div>
										<?php elseif ( 'under_verification' === $r->payment_status ) : ?>
											<div class="ef-alert ef-alert-info" style="margin-top: 14px; border-left: 4px solid #0D9488; background: #F0FDFA; padding: 14px 18px; border-radius: 8px;">
												<div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
													<span style="font-size:18px;">⏳</span>
													<h4 style="margin:0; color:#0F766E; font-size:14.5px; font-weight:700;"><?php esc_html_e( 'Payment Submitted Successfully', 'eventflow' ); ?></h4>
												</div>
												<p style="margin:0; color:#115E59; font-size:13.5px;"><?php esc_html_e( 'Your updated payment proof has been submitted and is awaiting admin verification.', 'eventflow' ); ?></p>
											</div>
										<?php elseif ( 'verified' === $r->payment_status ) : ?>
											<div class="ef-alert ef-alert-success" style="margin-top: 14px; border-left: 4px solid #10B981; background: #ECFDF5; padding: 14px 18px; border-radius: 8px;">
												<div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
													<span style="font-size:18px;">✅</span>
													<h4 style="margin:0; color:#065F46; font-size:14.5px; font-weight:700;"><?php esc_html_e( 'Payment Approved', 'eventflow' ); ?></h4>
												</div>
												<p style="margin:0; color:#047857; font-size:13.5px;"><?php esc_html_e( 'Your payment for this event has been verified. Your registration is confirmed.', 'eventflow' ); ?></p>
											</div>
										<?php endif; ?>
									</div>

									<div class="ef-reg-item-footer">
										<span class="ef-submitted-date">
											<?php printf( esc_html__( 'Submitted: %s', 'eventflow' ), esc_html( date_i18n( get_option( 'date_format' ), strtotime( $r->created_at ) ) ) ); ?>
										</span>

										<div class="ef-footer-buttons">
											<?php if ( in_array( $r->payment_status, array( 'pending', 'reupload_requested', 'rejected', 'under_verification' ), true ) ) : ?>
												<a href="?tab=payment&reg_id=<?php echo esc_attr( $r->reg_id ); ?>" class="ef-btn-teal ef-btn-pill ef-btn-sm">
													<?php 
													if ( 'rejected' === $r->payment_status || 'reupload_requested' === $r->payment_status ) {
														esc_html_e( 'Re-upload Payment Proof', 'eventflow' );
													} elseif ( 'under_verification' === $r->payment_status ) {
														esc_html_e( 'Update Payment Proof', 'eventflow' );
													} else {
														esc_html_e( 'Submit Payment Proof', 'eventflow' );
													}
													?> &rarr;
												</a>
											<?php endif; ?>

											<a href="?tab=receipt&reg_id=<?php echo esc_attr( $r->reg_id ); ?>" class="ef-btn-outline-teal ef-btn-sm" target="_blank">
												<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
												<?php esc_html_e( 'Official Voucher / Pass', 'eventflow' ); ?>
											</a>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

			<!-- ----------------------------------------------------
			     TAB 3: DYNAMIC REGISTRATION FORM (Workflow Hand-off)
			     ---------------------------------------------------- -->
			<?php elseif ( 'register_activity' === $active_tab ) : ?>
				
				<?php
				$act_id   = isset( $_GET['activity_id'] ) ? absint( $_GET['activity_id'] ) : 0;
				$activity = EventFlow_Activity_Manager::get_activity( $act_id );
				?>
				<div class="ef-dash-card">
					<div class="ef-dash-card-header">
						<div>
							<a href="?tab=events" class="ef-back-link">&larr; <?php esc_html_e( 'Back to Live Events Directory', 'eventflow' ); ?></a>
							<h2 class="ef-dash-card-title" style="margin-top: 8px;">
								<?php printf( esc_html__( 'Event Registration: %s', 'eventflow' ), $activity ? esc_html( $activity->title ) : '' ); ?>
							</h2>
						</div>
					</div>

					<!-- Existing Dynamic Registration Form (Preserves 7 verified fields, team builder, backend) -->
					<?php include EVENTFLOW_PLUGIN_DIR . 'templates/frontend/registration-form.php'; ?>
				</div>

			<!-- ----------------------------------------------------
			     TAB 4: PAYMENT SUBMISSION & PROOF
			     ---------------------------------------------------- -->
			<?php elseif ( 'payment' === $active_tab ) : ?>
				
				<?php
				$reg_id = isset( $_GET['reg_id'] ) ? sanitize_text_field( wp_unslash( $_GET['reg_id'] ) ) : '';
				$reg    = EventFlow_Registration_Manager::get_full_registration_details( $reg_id );
				?>
				<div class="ef-dash-card">
					<div class="ef-dash-card-header">
						<div>
							<a href="?tab=registrations" class="ef-back-link">&larr; <?php esc_html_e( 'Back to My Registrations', 'eventflow' ); ?></a>
							<h2 class="ef-dash-card-title" style="margin-top: 8px;"><?php esc_html_e( 'Payment Verification & Submission', 'eventflow' ); ?></h2>
						</div>
					</div>

					<?php if ( ! $reg ) : ?>
						<div class="ef-alert ef-alert-danger" style="margin:20px; border-left:4px solid #DC2626; padding:16px;">
							<p><?php esc_html_e( 'Registration record not found.', 'eventflow' ); ?></p>
						</div>
					<?php elseif ( (int) $reg->user_id !== (int) $user->ID && ! EventFlow_Roles::is_staff() ) : ?>
						<div class="ef-alert ef-alert-danger" style="margin:20px; border-left:4px solid #DC2626; padding:16px;">
							<strong>⛔ <?php esc_html_e( 'Access Denied', 'eventflow' ); ?>:</strong>
							<p style="margin:4px 0 0;"><?php esc_html_e( 'You are not authorized to view or modify this payment record.', 'eventflow' ); ?></p>
						</div>
					<?php else : ?>
						<!-- Payment Submission View -->
						<?php include EVENTFLOW_PLUGIN_DIR . 'templates/frontend/payment-submission.php'; ?>
					<?php endif; ?>
				</div>

			<!-- ----------------------------------------------------
			     TAB 5: OFFICIAL PRINTABLE PASS / VOUCHER
			     ---------------------------------------------------- -->
			<?php elseif ( 'receipt' === $active_tab ) : ?>
				
				<?php
				$reg_id = isset( $_GET['reg_id'] ) ? sanitize_text_field( wp_unslash( $_GET['reg_id'] ) ) : '';
				$reg    = EventFlow_Registration_Manager::get_full_registration_details( $reg_id );
				if ( ! $reg ) {
					echo '<div class="ef-dash-card"><div class="ef-alert ef-alert-danger" style="margin:20px; border-left:4px solid #DC2626; padding:16px;"><p>' . esc_html__( 'Registration record not found.', 'eventflow' ) . '</p></div></div>';
				} elseif ( (int) $reg->user_id !== (int) $user->ID && ! EventFlow_Roles::is_staff() ) {
					echo '<div class="ef-dash-card"><div class="ef-alert ef-alert-danger" style="margin:20px; border-left:4px solid #DC2626; padding:16px;"><strong>⛔ ' . esc_html__( 'Access Denied', 'eventflow' ) . ':</strong><p style="margin:4px 0 0;">' . esc_html__( 'You are not authorized to view this registration receipt or voucher.', 'eventflow' ) . '</p></div></div>';
				} else {
					include EVENTFLOW_PLUGIN_DIR . 'templates/frontend/receipt-template.php';
				}
				?>

			<!-- ----------------------------------------------------
			     TAB 6: NOTIFICATIONS & ANNOUNCEMENTS
			     ---------------------------------------------------- -->
			<?php elseif ( 'notifications' === $active_tab || 'announcements' === $active_tab ) : ?>
				<?php
				$user_notifications = class_exists( 'EventFlow_Notification_Manager' ) 
					? EventFlow_Notification_Manager::get_user_notifications( $user->ID, 50 ) 
					: array();
				?>
				<div class="ef-dash-card">
					<div class="ef-dash-card-header ef-flex-between" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
						<div>
							<h2 class="ef-dash-card-title"><?php esc_html_e( 'Activity Notifications & Announcements', 'eventflow' ); ?></h2>
							<p class="ef-dash-card-sub"><?php esc_html_e( 'Real-time updates regarding your event registrations, payment verification status, and official announcements.', 'eventflow' ); ?></p>
						</div>
						<?php if ( ! empty( $user_notifications ) && $unread_notifications_count > 0 ) : ?>
							<button type="button" class="ef-btn-outline-teal ef-btn-sm" id="ef-mark-all-notifs-btn">
								✓ <?php esc_html_e( 'Mark All as Read', 'eventflow' ); ?>
							</button>
						<?php endif; ?>
					</div>

					<!-- In-Dashboard Notifications Feed -->
					<div class="ef-notifications-section" style="margin-bottom: 30px;">
						<h3 style="font-size:15px; font-weight:700; color:#1E293B; margin:0 0 14px; border-bottom:1px solid #E2E8F0; padding-bottom:8px;">
							🔔 <?php esc_html_e( 'Your Personal Activity Alerts', 'eventflow' ); ?>
						</h3>

						<?php if ( empty( $user_notifications ) ) : ?>
							<div class="ef-empty-state" style="padding: 24px 16px; text-align: center;">
								<div class="ef-empty-icon" style="font-size: 28px;">📭</div>
								<h4 style="margin: 8px 0 4px; color: #64748B;"><?php esc_html_e( 'No notifications yet', 'eventflow' ); ?></h4>
								<p style="font-size: 13px; color: #94A3B8;"><?php esc_html_e( 'You will receive notifications when the finance team verifies your payment or requires updates.', 'eventflow' ); ?></p>
							</div>
						<?php else : ?>
							<div class="ef-notifications-stack" style="display:flex; flex-direction:column; gap:12px;">
								<?php foreach ( $user_notifications as $notif ) : ?>
									<?php
									$is_unread = ( (int) $notif->is_read === 0 );
									$border_color = '#E2E8F0';
									$bg_color = $is_unread ? '#F8FAFC' : '#FFFFFF';
									$icon = '🔔';
									$type_badge = '';

									if ( 'payment_rejected' === $notif->type ) {
										$border_color = '#EF4444';
										$bg_color = $is_unread ? '#FEF2F2' : '#FFFFFF';
										$icon = '❌';
										$type_badge = '<span class="ef-badge ef-badge-danger" style="font-size:11px;">' . esc_html__( 'Action Required', 'eventflow' ) . '</span>';
									} elseif ( 'payment_approved' === $notif->type ) {
										$border_color = '#10B981';
										$bg_color = $is_unread ? '#ECFDF5' : '#FFFFFF';
										$icon = '✅';
										$type_badge = '<span class="ef-badge ef-badge-success" style="font-size:11px;">' . esc_html__( 'Verified', 'eventflow' ) . '</span>';
									} elseif ( 'payment_resubmitted' === $notif->type ) {
										$border_color = '#0D9488';
										$bg_color = $is_unread ? '#F0FDFA' : '#FFFFFF';
										$icon = '⏳';
										$type_badge = '<span class="ef-badge ef-badge-primary" style="font-size:11px;">' . esc_html__( 'Submitted', 'eventflow' ) . '</span>';
									} elseif ( 'payment_reupload' === $notif->type ) {
										$border_color = '#F59E0B';
										$bg_color = $is_unread ? '#FFFBEB' : '#FFFFFF';
										$icon = '⚠️';
										$type_badge = '<span class="ef-badge ef-badge-warning" style="font-size:11px;">' . esc_html__( 'Re-upload', 'eventflow' ) . '</span>';
									}
									?>
									<div class="ef-notif-card" data-notif-id="<?php echo esc_attr( $notif->id ); ?>" style="border:1px solid <?php echo esc_attr( $border_color ); ?>; border-left:4px solid <?php echo esc_attr( $border_color ); ?>; background:<?php echo esc_attr( $bg_color ); ?>; border-radius:8px; padding:16px;">
										<div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom:6px;">
											<div style="display:flex; align-items:center; gap:8px;">
												<span style="font-size:18px;"><?php echo esc_html( $icon ); ?></span>
												<strong style="color:#0F172A; font-size:14.5px;"><?php echo esc_html( $notif->title ); ?></strong>
												<?php echo $type_badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
												<?php if ( $is_unread ) : ?>
													<span class="ef-badge-dot" style="background:#EF4444; width:8px; height:8px; border-radius:50%; display:inline-block;" title="<?php esc_attr_e( 'Unread notification', 'eventflow' ); ?>"></span>
												<?php endif; ?>
											</div>
											<span class="ef-text-muted ef-text-xs" style="color:#94A3B8; font-size:12px; white-space:nowrap;">
												<?php echo esc_html( human_time_diff( strtotime( $notif->created_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'eventflow' ) ); ?>
											</span>
										</div>

										<div class="ef-notif-message" style="color:#334155; font-size:13.5px; line-height:1.5; margin:6px 0 10px;">
											<?php echo wp_kses_post( nl2br( $notif->message ) ); ?>
										</div>

										<div style="display:flex; gap:10px; align-items:center; margin-top:8px;">
											<?php if ( ! empty( $notif->action_url ) ) : ?>
												<a href="<?php echo esc_url( $notif->action_url ); ?>" class="ef-btn-teal ef-btn-sm" style="display:inline-block; font-size:12.5px; padding:5px 12px;">
													<?php echo 'payment_rejected' === $notif->type ? esc_html__( 'Re-upload Payment Proof', 'eventflow' ) : esc_html__( 'View Details', 'eventflow' ); ?> &rarr;
												</a>
											<?php endif; ?>

											<?php if ( $is_unread ) : ?>
												<button type="button" class="ef-btn-outline-teal ef-btn-sm ef-mark-notif-read-btn" data-id="<?php echo esc_attr( $notif->id ); ?>" style="font-size:11.5px; padding:4px 10px;">
													✓ <?php esc_html_e( 'Mark as read', 'eventflow' ); ?>
												</button>
											<?php endif; ?>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>

					<!-- Broadcast Announcements Stack -->
					<?php if ( ! empty( $announcements ) ) : ?>
						<div class="ef-announcements-section">
							<h3 style="font-size:15px; font-weight:700; color:#1E293B; margin:0 0 14px; border-bottom:1px solid #E2E8F0; padding-bottom:8px;">
								📢 <?php esc_html_e( 'Official Broadcast Announcements', 'eventflow' ); ?>
							</h3>
							<div class="ef-announcements-stack">
								<?php foreach ( $announcements as $ann ) : ?>
									<div class="ef-ann-card-box">
										<div class="ef-ann-card-head">
											<h4 class="ef-ann-head-title"><?php echo esc_html( $ann->title ); ?></h4>
											<span class="ef-ann-timestamp">
												<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $ann->created_at ) ) ); ?>
											</span>
										</div>
										<div class="ef-ann-card-body">
											<?php echo wp_kses_post( $ann->content ); ?>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>

			<!-- ----------------------------------------------------
			     TAB 7: PARTICIPANT PROFILE & CREDENTIALS
			     ---------------------------------------------------- -->
			<?php elseif ( 'profile' === $active_tab ) : ?>
				<?php
				$user_phone      = get_user_meta( $user->ID, 'eventflow_phone', true );
				$user_university = get_user_meta( $user->ID, 'eventflow_university', true );
				$user_department = get_user_meta( $user->ID, 'eventflow_department', true );
				$user_semester   = get_user_meta( $user->ID, 'eventflow_semester', true );
				$user_batch      = get_user_meta( $user->ID, 'eventflow_academic_year', true );
				?>
				<div class="ef-dash-card">
					<div class="ef-dash-card-header">
						<div>
							<h2 class="ef-dash-card-title"><?php esc_html_e( 'Participant Profile & Academic Credentials', 'eventflow' ); ?></h2>
							<p class="ef-dash-card-sub"><?php esc_html_e( 'Manage your candidate account information, contact numbers, and verified institutional details.', 'eventflow' ); ?></p>
						</div>
					</div>

					<div id="ef-profile-alert" class="ef-alert" style="display: none; margin-bottom: 20px;"></div>

					<form id="ef-participant-profile-form" style="max-width: 800px;">
						<div class="ef-grid ef-grid-2">
							<div class="ef-form-group">
								<label class="ef-label-bold"><?php esc_html_e( 'Full Name', 'eventflow' ); ?> <span class="ef-req">*</span></label>
								<input type="text" name="display_name" class="ef-input" value="<?php echo esc_attr( $user->display_name ); ?>" required>
							</div>

							<div class="ef-form-group">
								<label class="ef-label-bold"><?php esc_html_e( 'Email Address', 'eventflow' ); ?> <span class="ef-badge ef-badge-neutral" style="font-size: 11px;"><?php esc_html_e( 'Read Only', 'eventflow' ); ?></span></label>
								<input type="email" class="ef-input" value="<?php echo esc_attr( $user->user_email ); ?>" disabled style="background: #F1F5F9; cursor: not-allowed;">
								<span class="ef-help-text"><?php esc_html_e( 'Primary account email cannot be changed.', 'eventflow' ); ?></span>
							</div>
						</div>

						<div class="ef-grid ef-grid-2">
							<div class="ef-form-group">
								<label class="ef-label-bold"><?php esc_html_e( 'Phone / WhatsApp Number', 'eventflow' ); ?> <span class="ef-req">*</span></label>
								<input type="tel" name="phone" class="ef-input" value="<?php echo esc_attr( $user_phone ); ?>" placeholder="03001234567" required>
							</div>

							<div class="ef-form-group">
								<label class="ef-label-bold"><?php esc_html_e( 'University / Institution', 'eventflow' ); ?> <span class="ef-req">*</span></label>
								<input type="text" name="university" class="ef-input" value="<?php echo esc_attr( $user_university ); ?>" placeholder="e.g. Sukkur IBA University" required>
							</div>
						</div>

						<div class="ef-grid ef-grid-3">
							<div class="ef-form-group">
								<label class="ef-label-bold"><?php esc_html_e( 'Department / Major', 'eventflow' ); ?> <span class="ef-req">*</span></label>
								<input type="text" name="department" class="ef-input" value="<?php echo esc_attr( $user_department ); ?>" placeholder="e.g. Computer Science" required>
							</div>

							<div class="ef-form-group">
								<label class="ef-label-bold"><?php esc_html_e( 'Current Semester', 'eventflow' ); ?> <span class="ef-req">*</span></label>
								<input type="text" name="semester" class="ef-input" value="<?php echo esc_attr( $user_semester ); ?>" placeholder="e.g. 6th" required>
							</div>

							<div class="ef-form-group">
								<label class="ef-label-bold"><?php esc_html_e( 'Academic Year / Batch', 'eventflow' ); ?> <span class="ef-req">*</span></label>
								<input type="text" name="academic_year" class="ef-input" value="<?php echo esc_attr( $user_batch ); ?>" placeholder="e.g. 2022-2026" required>
							</div>
						</div>

						<div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #E2E8F0;">
							<button type="submit" id="ef-save-profile-btn" class="ef-btn-teal ef-btn-pill" style="padding: 10px 24px;">
								💾 <?php esc_html_e( 'Save Profile Changes', 'eventflow' ); ?>
							</button>
						</div>
					</form>
				</div>

			<?php endif; ?>

		</div>
	</main>
</div>

<!-- ====================================================
     EVENT DETAILS RICH MODAL
     ==================================================== -->
<div class="ef-modal-backdrop" id="ef-activity-detail-modal" aria-hidden="true" style="display: none;">
	<div class="ef-modal-container">
		<div class="ef-modal-card">
			<!-- Modal Header -->
			<div class="ef-modal-header">
				<div class="ef-modal-header-text">
					<span class="ef-modal-kicker"><?php esc_html_e( 'COMPETITION SPECIFICATIONS', 'eventflow' ); ?></span>
					<h2 class="ef-modal-title" id="ef-modal-act-title"><?php esc_html_e( 'Activity Details', 'eventflow' ); ?></h2>
				</div>
				<button type="button" class="ef-modal-close-btn ef-modal-dismiss" aria-label="<?php esc_attr_e( 'Close details modal', 'eventflow' ); ?>">&times;</button>
			</div>

			<!-- Modal Dynamic Body Content -->
			<div class="ef-modal-body" id="ef-modal-act-body">
				<div class="ef-modal-loading">
					<div class="ef-spinner ef-spinner-teal"></div>
					<span><?php esc_html_e( 'Loading event details...', 'eventflow' ); ?></span>
				</div>
			</div>

			<!-- Modal Footer Actions -->
			<div class="ef-modal-footer">
				<button type="button" class="ef-btn-secondary ef-modal-dismiss"><?php esc_html_e( 'Close', 'eventflow' ); ?></button>
				<a href="#" class="ef-btn-teal ef-btn-pill" id="ef-modal-act-register-btn" style="display:none;">
					<?php esc_html_e( 'Register for this Event', 'eventflow' ); ?> &rarr;
				</a>
			</div>
		</div>
	</div>
</div>
