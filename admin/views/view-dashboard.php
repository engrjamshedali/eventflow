<?php
/**
 * EventFlow Admin View: Dashboard (Command Center)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$event = EventFlow_Event_Manager::get_active_event();
$kpi = EventFlow_Analytics_Manager::get_overview_kpis( $event ? $event->id : 0 );
$currency = $event ? esc_html( $event->currency ) : 'PKR';
?>

<div class="wrap ef-admin-wrap">
	<!-- Hero Command Center Header -->
	<div class="ef-admin-header ef-flex-between">
		<div class="ef-header-brand-wrap">
			<div class="ef-header-icon">
				<span class="dashicons dashicons-calendar-alt"></span>
			</div>
			<div>
				<h1 class="wp-heading-inline">
					<?php esc_html_e( 'EventFlow – Command Center', 'eventflow' ); ?>
					<span class="ef-header-version"><?php echo esc_html( EVENTFLOW_VERSION ); ?></span>
				</h1>
				<div class="ef-admin-subtitle">
					<?php if ( $event ) : ?>
						<span class="ef-live-pill">
							<span class="ef-pulse-dot"></span>
							<?php printf( esc_html__( 'Active Event: %s', 'eventflow' ), esc_html( $event->title ) ); ?>
						</span>
						<?php if ( $event->start_date ) : ?>
							<span class="ef-text-muted ef-monospace">&bull; <?php echo esc_html( date_i18n( 'M j, Y', strtotime( $event->start_date ) ) ); ?></span>
						<?php endif; ?>
					<?php else : ?>
						<span class="ef-live-pill ef-offline-pill">
							<span class="dashicons dashicons-warning" style="font-size: 14px; width: 14px; height: 14px; margin-right: 4px;"></span>
							<?php esc_html_e( 'No Active Event Selected', 'eventflow' ); ?>
						</span>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-events' ) ); ?>" class="ef-monospace" style="font-size: 12px; margin-left: 6px;"><?php esc_html_e( 'Configure Events &rarr;', 'eventflow' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="ef-admin-actions">
			<a href="<?php echo esc_url( EventFlow_Shortcodes::get_staff_dashboard_url() ); ?>" class="button button-primary" target="_blank" title="<?php esc_attr_e( 'Open the frontend staff console', 'eventflow' ); ?>">
				<span class="dashicons dashicons-external"></span>
				<?php esc_html_e( 'Launch Frontend Staff Portal', 'eventflow' ); ?>
			</a>
			<a href="<?php echo esc_url( EventFlow_Shortcodes::get_landing_url() ); ?>" class="button" target="_blank" title="<?php esc_attr_e( 'View the public event portal', 'eventflow' ); ?>">
				<span class="dashicons dashicons-visibility"></span>
				<?php esc_html_e( 'View Public Site', 'eventflow' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-settings' ) ); ?>" class="button" title="<?php esc_attr_e( 'System Settings', 'eventflow' ); ?>">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'Settings', 'eventflow' ); ?>
			</a>
		</div>
	</div>

	<!-- KPI Summary Row -->
	<div class="ef-kpi-grid" style="margin-top: 24px;">
		<!-- Card 1: Registrations -->
		<div class="ef-kpi-card">
			<div class="ef-kpi-card-top">
				<span class="ef-kpi-title"><?php esc_html_e( 'Total Registrations', 'eventflow' ); ?></span>
				<div class="ef-kpi-icon-box ef-kpi-icon-indigo">
					<span class="dashicons dashicons-groups"></span>
				</div>
			</div>
			<div class="ef-kpi-value"><?php echo esc_html( number_format_i18n( $kpi['total_registrations'] ) ); ?></div>
			<div class="ef-kpi-sub">
				<span class="ef-badge ef-badge-neutral"><?php printf( esc_html__( '%d Solo', 'eventflow' ), $kpi['solo_registrations'] ); ?></span>
				<span class="ef-badge ef-badge-primary"><?php printf( esc_html__( '%d Teams', 'eventflow' ), $kpi['team_registrations'] ); ?></span>
			</div>
		</div>

		<!-- Card 2: Candidates / Members -->
		<div class="ef-kpi-card">
			<div class="ef-kpi-card-top">
				<span class="ef-kpi-title"><?php esc_html_e( 'Total Candidates / Members', 'eventflow' ); ?></span>
				<div class="ef-kpi-icon-box ef-kpi-icon-cyan">
					<span class="dashicons dashicons-id-alt"></span>
				</div>
			</div>
			<div class="ef-kpi-value ef-text-primary"><?php echo esc_html( number_format_i18n( $kpi['total_participants'] ) ); ?></div>
			<div class="ef-kpi-sub"><?php esc_html_e( 'Individual enrolled students', 'eventflow' ); ?></div>
		</div>

		<!-- Card 3: Pending Verification -->
		<div class="ef-kpi-card">
			<div class="ef-kpi-card-top">
				<span class="ef-kpi-title"><?php esc_html_e( 'Pending Verification', 'eventflow' ); ?></span>
				<div class="ef-kpi-icon-box ef-kpi-icon-amber">
					<span class="dashicons dashicons-clock"></span>
				</div>
			</div>
			<div class="ef-kpi-value ef-text-warning"><?php echo esc_html( number_format_i18n( $kpi['under_verification_payments'] ) ); ?></div>
			<div class="ef-kpi-sub">
				<?php if ( $kpi['under_verification_payments'] > 0 ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-payments&status=under_verification' ) ); ?>" class="ef-text-warning" style="font-weight: 600; text-decoration: none;">
						<?php esc_html_e( 'Awaiting review in queue &rarr;', 'eventflow' ); ?>
					</a>
				<?php else : ?>
					<?php esc_html_e( 'Queue is fully cleared', 'eventflow' ); ?>
				<?php endif; ?>
			</div>
		</div>

		<!-- Card 4: Verified Revenue -->
		<div class="ef-kpi-card">
			<div class="ef-kpi-card-top">
				<span class="ef-kpi-title"><?php esc_html_e( 'Verified Revenue', 'eventflow' ); ?></span>
				<div class="ef-kpi-icon-box ef-kpi-icon-emerald">
					<span class="dashicons dashicons-money-alt"></span>
				</div>
			</div>
			<div class="ef-kpi-value ef-text-success"><?php echo sprintf( '%s %s', esc_html( $currency ), number_format( $kpi['total_revenue'], 0 ) ); ?></div>
			<div class="ef-kpi-sub">
				<span class="ef-badge ef-badge-success"><?php printf( esc_html__( '%d Verified Transactions', 'eventflow' ), $kpi['verified_payments'] ); ?></span>
			</div>
		</div>
	</div>

	<!-- Quick Navigation Strip -->
	<div class="ef-quick-nav-strip">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-events' ) ); ?>" class="ef-nav-shortcut">
			<div class="ef-nav-shortcut-left">
				<span class="dashicons dashicons-calendar"></span>
				<span><?php esc_html_e( 'Manage Events', 'eventflow' ); ?></span>
			</div>
			<span class="ef-chevron">&rarr;</span>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-activities' ) ); ?>" class="ef-nav-shortcut">
			<div class="ef-nav-shortcut-left">
				<span class="dashicons dashicons-awards"></span>
				<span><?php esc_html_e( 'Competition Tracks', 'eventflow' ); ?></span>
			</div>
			<span class="ef-chevron">&rarr;</span>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-registrations' ) ); ?>" class="ef-nav-shortcut">
			<div class="ef-nav-shortcut-left">
				<span class="dashicons dashicons-list-view"></span>
				<span><?php esc_html_e( 'Registrations & Rosters', 'eventflow' ); ?></span>
			</div>
			<span class="ef-chevron">&rarr;</span>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-payments' ) ); ?>" class="ef-nav-shortcut">
			<div class="ef-nav-shortcut-left">
				<span class="dashicons dashicons-yes-alt"></span>
				<span><?php esc_html_e( 'Payment Verification', 'eventflow' ); ?></span>
			</div>
			<span class="ef-chevron">&rarr;</span>
		</a>
	</div>

	<!-- Analytics Charts Grid -->
	<div class="ef-grid ef-grid-2" style="margin-top: 24px;">
		<!-- Registration Velocity Line Chart -->
		<div class="ef-card">
			<div class="ef-card-header">
				<h3>
					<span class="dashicons dashicons-chart-line" style="color: var(--ef-primary);"></span>
					<?php esc_html_e( 'Registration Trend (Last 14 Days)', 'eventflow' ); ?>
				</h3>
				<span class="ef-card-badge"><?php esc_html_e( 'Daily Velocity', 'eventflow' ); ?></span>
			</div>
			<div class="ef-card-body">
				<div class="ef-chart-container" id="ef-admin-daily-chart" data-chart='<?php echo esc_attr( wp_json_encode( EventFlow_Analytics_Manager::get_daily_registrations( 14 ) ) ); ?>'>
					<canvas id="ef-admin-daily-canvas" height="230"></canvas>
				</div>
			</div>
		</div>

		<!-- Competition Tracks Bar Chart -->
		<div class="ef-card">
			<div class="ef-card-header">
				<h3>
					<span class="dashicons dashicons-chart-bar" style="color: var(--ef-secondary);"></span>
					<?php esc_html_e( 'Competition Tracks Breakdown', 'eventflow' ); ?>
				</h3>
				<span class="ef-card-badge" style="background: var(--ef-secondary-light); color: var(--ef-secondary); border-color: var(--ef-secondary-border);"><?php esc_html_e( 'By Track', 'eventflow' ); ?></span>
			</div>
			<div class="ef-card-body">
				<div class="ef-chart-container" id="ef-admin-activity-chart" data-chart='<?php echo esc_attr( wp_json_encode( EventFlow_Analytics_Manager::get_activity_distribution() ) ); ?>'>
					<canvas id="ef-admin-activity-canvas" height="230"></canvas>
				</div>
			</div>
		</div>
	</div>

	<!-- Quick Links & Shortcode Reference -->
	<div class="ef-card" style="margin-top: 24px;">
		<div class="ef-card-header">
			<h3>
				<span class="dashicons dashicons-shortcode" style="color: var(--ef-primary);"></span>
				<?php esc_html_e( 'Quick Shortcode References for Pages & Elementor', 'eventflow' ); ?>
			</h3>
			<span class="ef-text-muted ef-text-sm"><?php esc_html_e( 'Click any shortcode pill to copy', 'eventflow' ); ?></span>
		</div>
		<div class="ef-card-body" style="padding: 0;">
			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width: 250px;"><?php esc_html_e( 'Shortcode (Click to Copy)', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Function & Description', 'eventflow' ); ?></th>
						<th style="width: 240px;"><?php esc_html_e( 'Recommended Target Page', 'eventflow' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>
							<button type="button" class="ef-copy-shortcode" data-shortcode="[eventflow_landing]" title="<?php esc_attr_e( 'Click to copy shortcode', 'eventflow' ); ?>">
								<span class="dashicons dashicons-admin-page"></span>
								<span class="ef-shortcode-text">[eventflow_landing]</span>
							</button>
						</td>
						<td><?php esc_html_e( 'Complete Event Landing Page with hero banner, countdown timer, activity cards, and quick registration triggers.', 'eventflow' ); ?></td>
						<td>
							<a href="<?php echo esc_url( EventFlow_Shortcodes::get_landing_url() ); ?>" target="_blank" class="ef-monospace">
								<?php esc_html_e( 'View Page &rarr;', 'eventflow' ); ?>
							</a>
						</td>
					</tr>
					<tr>
						<td>
							<button type="button" class="ef-copy-shortcode" data-shortcode="[eventflow_auth]" title="<?php esc_attr_e( 'Click to copy shortcode', 'eventflow' ); ?>">
								<span class="dashicons dashicons-admin-page"></span>
								<span class="ef-shortcode-text">[eventflow_auth]</span>
							</button>
						</td>
						<td><?php esc_html_e( 'Participant Login, Account Creation, and Password Reset Tabbed Box.', 'eventflow' ); ?></td>
						<td>
							<a href="<?php echo esc_url( EventFlow_Shortcodes::get_auth_url() ); ?>" target="_blank" class="ef-monospace">
								<code>/participant-login/</code> &rarr;
							</a>
						</td>
					</tr>
					<tr>
						<td>
							<button type="button" class="ef-copy-shortcode" data-shortcode="[eventflow_dashboard]" title="<?php esc_attr_e( 'Click to copy shortcode', 'eventflow' ); ?>">
								<span class="dashicons dashicons-admin-page"></span>
								<span class="ef-shortcode-text">[eventflow_dashboard]</span>
							</button>
						</td>
						<td><?php esc_html_e( 'Participant Self-Service Portal: My Registrations, Team Roster Editor, and Proof Uploader.', 'eventflow' ); ?></td>
						<td>
							<a href="<?php echo esc_url( EventFlow_Shortcodes::get_dashboard_url() ); ?>" target="_blank" class="ef-monospace">
								<code>/participant-dashboard/</code> &rarr;
							</a>
						</td>
					</tr>
					<tr>
						<td>
							<button type="button" class="ef-copy-shortcode" data-shortcode="[eventflow_staff_dashboard]" title="<?php esc_attr_e( 'Click to copy shortcode', 'eventflow' ); ?>">
								<span class="dashicons dashicons-admin-page"></span>
								<span class="ef-shortcode-text">[eventflow_staff_dashboard]</span>
							</button>
						</td>
						<td><?php esc_html_e( 'Frontend Staff & Admin Management Portal (No WP-admin required for desk coordinators).', 'eventflow' ); ?></td>
						<td>
							<a href="<?php echo esc_url( EventFlow_Shortcodes::get_staff_dashboard_url() ); ?>" target="_blank" class="ef-monospace">
								<code>/event-dashboard/</code> &rarr;
							</a>
						</td>
					</tr>
					<tr>
						<td>
							<button type="button" class="ef-copy-shortcode" data-shortcode="[eventflow_activities]" title="<?php esc_attr_e( 'Click to copy shortcode', 'eventflow' ); ?>">
								<span class="dashicons dashicons-admin-page"></span>
								<span class="ef-shortcode-text">[eventflow_activities]</span>
							</button>
						</td>
						<td><?php esc_html_e( 'Responsive grid of published competitions and activities with fees and team size badges.', 'eventflow' ); ?></td>
						<td><span class="ef-text-muted"><?php esc_html_e( 'Any Elementor / Gutenberg page', 'eventflow' ); ?></span></td>
					</tr>
					<tr>
						<td>
							<button type="button" class="ef-copy-shortcode" data-shortcode="[eventflow_track]" title="<?php esc_attr_e( 'Click to copy shortcode', 'eventflow' ); ?>">
								<span class="dashicons dashicons-admin-page"></span>
								<span class="ef-shortcode-text">[eventflow_track]</span>
							</button>
						</td>
						<td><?php esc_html_e( 'Quick Application & Payment Status Verification Search Form.', 'eventflow' ); ?></td>
						<td>
							<a href="<?php echo esc_url( EventFlow_Shortcodes::get_track_url() ); ?>" target="_blank" class="ef-monospace">
								<code>/track-status/</code> &rarr;
							</a>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
