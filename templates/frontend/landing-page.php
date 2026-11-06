<?php
/**
 * EventFlow Frontend Landing Page Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$event = isset( $event ) ? $event : EventFlow_Event_Manager::get_active_event();
$activities = isset( $activities ) ? $activities : EventFlow_Activity_Manager::get_activities();
$status = isset( $status ) ? $status : EventFlow_Event_Manager::get_registration_status( $event );
$announcements = isset( $announcements ) ? $announcements : ( $event ? EventFlow_Event_Manager::get_announcements( $event->id, 3 ) : array() );

$event_title = $event ? esc_html( $event->title ) : __( 'Annual Tech & Innovation Summit 2026', 'eventflow' );
$event_desc  = $event && ! empty( $event->description ) ? wp_kses_post( $event->description ) : __( 'Compete with the brightest minds in engineering, algorithms, robotics, and design.', 'eventflow' );
$banner_url  = $event && ! empty( $event->banner_url ) ? esc_url( $event->banner_url ) : '';
$logo_url    = $event && ! empty( $event->logo_url ) ? esc_url( $event->logo_url ) : '';
$reg_end     = $event && ! empty( $event->reg_end_date ) ? $event->reg_end_date : '';
$currency    = $event ? esc_html( $event->currency ) : 'PKR';
?>

<div class="eventflow-app eventflow-landing">
	<!-- Hero Section -->
	<header class="ef-hero <?php echo $banner_url ? 'has-bg' : ''; ?>" style="<?php echo $banner_url ? 'background-image: url(' . esc_url( $banner_url ) . ');' : ''; ?>">
		<div class="ef-hero-overlay"></div>
		<div class="ef-hero-inner">
			<?php if ( $logo_url ) : ?>
				<div class="ef-hero-logo">
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $event_title ); ?>">
				</div>
			<?php endif; ?>

			<div class="ef-hero-badge-wrap">
				<span class="ef-badge <?php echo esc_attr( $status['class'] ); ?>">
					<span class="ef-badge-dot"></span>
					<?php echo esc_html( $status['label'] ); ?>
				</span>
				<?php if ( $event && $event->start_date ) : ?>
					<span class="ef-hero-dates">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
						<?php echo esc_html( date_i18n( 'F j, Y', strtotime( $event->start_date ) ) ); ?>
						<?php if ( $event->end_date ) : ?>
							- <?php echo esc_html( date_i18n( 'F j, Y', strtotime( $event->end_date ) ) ); ?>
						<?php endif; ?>
					</span>
				<?php endif; ?>
			</div>

			<h1 class="ef-hero-title"><?php echo esc_html( $event_title ); ?></h1>
			<p class="ef-hero-description"><?php echo $event_desc; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>

			<!-- Registration Countdown -->
			<?php if ( $reg_end && 'open' === $status['code'] ) : ?>
				<div class="ef-countdown" data-deadline="<?php echo esc_attr( date( 'c', strtotime( $reg_end ) ) ); ?>">
					<div class="ef-countdown-title"><?php esc_html_e( 'Registration Closes In:', 'eventflow' ); ?></div>
					<div class="ef-countdown-timer">
						<div class="ef-timer-box"><span class="ef-days">00</span><label><?php esc_html_e( 'Days', 'eventflow' ); ?></label></div>
						<div class="ef-timer-sep">:</div>
						<div class="ef-timer-box"><span class="ef-hours">00</span><label><?php esc_html_e( 'Hours', 'eventflow' ); ?></label></div>
						<div class="ef-timer-sep">:</div>
						<div class="ef-timer-box"><span class="ef-minutes">00</span><label><?php esc_html_e( 'Mins', 'eventflow' ); ?></label></div>
						<div class="ef-timer-sep">:</div>
						<div class="ef-timer-box"><span class="ef-seconds">00</span><label><?php esc_html_e( 'Secs', 'eventflow' ); ?></label></div>
					</div>
				</div>
			<?php endif; ?>

			<!-- CTA Buttons -->
			<div class="ef-hero-actions">
				<?php if ( is_user_logged_in() ) : ?>
					<a href="<?php echo esc_url( EventFlow_Shortcodes::get_dashboard_url() ); ?>" class="ef-btn ef-btn-primary ef-btn-lg">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
						<?php esc_html_e( 'Access Participant Dashboard', 'eventflow' ); ?> &rarr;
					</a>
				<?php else : ?>
					<?php if ( 'open' === $status['code'] ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'tab', 'register', EventFlow_Shortcodes::get_auth_url() ) ); ?>" class="ef-btn ef-btn-primary ef-btn-lg">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
							<?php esc_html_e( 'Register Now', 'eventflow' ); ?>
						</a>
					<?php endif; ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', 'login', EventFlow_Shortcodes::get_auth_url() ) ); ?>" class="ef-btn ef-btn-secondary ef-btn-lg">
						<?php esc_html_e( 'Participant Login', 'eventflow' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
	</header>

	<!-- Important Announcements Ticker -->
	<?php if ( ! empty( $announcements ) ) : ?>
		<section class="ef-announcements-bar">
			<div class="ef-container">
				<div class="ef-announcement-card">
					<div class="ef-announcement-icon">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
					</div>
					<div class="ef-announcement-content">
						<span class="ef-announcement-tag"><?php esc_html_e( 'Notice', 'eventflow' ); ?></span>
						<strong><?php echo esc_html( $announcements[0]->title ); ?>:</strong>
						<span><?php echo esc_html( wp_strip_all_tags( $announcements[0]->content ) ); ?></span>
					</div>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<!-- Competitions & Activities Grid -->
	<section class="ef-section ef-activities-section" id="activities">
		<div class="ef-container">
			<div class="ef-section-header">
				<h2 class="ef-section-title"><?php esc_html_e( 'Official Competitions & Activities', 'eventflow' ); ?></h2>
				<p class="ef-section-subtitle"><?php esc_html_e( 'Explore all available competitive tracks, eligibility rules, and team requirements.', 'eventflow' ); ?></p>
			</div>

			<?php if ( empty( $activities ) ) : ?>
				<div class="ef-empty-state">
					<div class="ef-empty-icon">📂</div>
					<h3><?php esc_html_e( 'No Activities Published Yet', 'eventflow' ); ?></h3>
					<p><?php esc_html_e( 'Activities will be announced soon by the technical administration team.', 'eventflow' ); ?></p>
				</div>
			<?php else : ?>
				<div class="ef-activity-grid">
					<?php foreach ( $activities as $act ) : ?>
						<?php
						$is_open = EventFlow_Activity_Manager::is_open_for_registration( $act );
						$type_label = 'both' === $act->participation_type ? __( 'Solo or Team', 'eventflow' ) : ( 'team' === $act->participation_type ? __( 'Team Only', 'eventflow' ) : __( 'Solo Only', 'eventflow' ) );
						?>
						<div class="ef-activity-card">
							<?php if ( ! empty( $act->image_url ) ) : ?>
								<div class="ef-activity-img" style="background-image: url('<?php echo esc_url( $act->image_url ); ?>');"></div>
							<?php endif; ?>
							<div class="ef-activity-body">
								<div class="ef-activity-tags">
									<span class="ef-tag"><?php echo esc_html( $type_label ); ?></span>
									<?php if ( (int) $act->max_team_size > 1 ) : ?>
										<span class="ef-tag ef-tag-muted">
											<?php printf( esc_html__( '%d-%d Members', 'eventflow' ), (int) $act->min_team_size, (int) $act->max_team_size ); ?>
										</span>
									<?php endif; ?>
								</div>

								<h3 class="ef-activity-title"><?php echo esc_html( $act->title ); ?></h3>
								<p class="ef-activity-desc"><?php echo esc_html( wp_trim_words( $act->description, 20 ) ); ?></p>

								<div class="ef-activity-meta">
									<div class="ef-meta-item">
										<span class="ef-meta-label"><?php esc_html_e( 'Fee:', 'eventflow' ); ?></span>
										<span class="ef-meta-value ef-fee"><?php echo floatval( $act->reg_fee ) > 0 ? sprintf( '%s %s', esc_html( $currency ), number_format( $act->reg_fee, 2 ) ) : esc_html__( 'Free', 'eventflow' ); ?></span>
									</div>
									<?php if ( ! empty( $act->reg_deadline ) ) : ?>
										<div class="ef-meta-item">
											<span class="ef-meta-label"><?php esc_html_e( 'Deadline:', 'eventflow' ); ?></span>
											<span class="ef-meta-value"><?php echo esc_html( date_i18n( 'M j', strtotime( $act->reg_deadline ) ) ); ?></span>
										</div>
									<?php endif; ?>
								</div>

								<div class="ef-activity-footer">
									<button type="button" class="ef-btn ef-btn-outline ef-btn-sm ef-open-activity-modal" data-id="<?php echo esc_attr( $act->id ); ?>">
										<?php esc_html_e( 'Rules & Info', 'eventflow' ); ?>
									</button>

									<?php if ( $is_open ) : ?>
										<a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'register_activity', 'activity_id' => $act->id ), EventFlow_Shortcodes::get_dashboard_url() ) ); ?>" class="ef-btn ef-btn-primary ef-btn-sm">
											<?php esc_html_e( 'Register', 'eventflow' ); ?> &rarr;
										</a>
									<?php else : ?>
										<span class="ef-badge ef-badge-neutral"><?php esc_html_e( 'Closed', 'eventflow' ); ?></span>
									<?php endif; ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<!-- Contact & Support Info -->
	<?php if ( $event && ( $event->contact_email || $event->contact_phone ) ) : ?>
		<section class="ef-section ef-contact-strip">
			<div class="ef-container ef-contact-inner">
				<div>
					<h3><?php esc_html_e( 'Have questions or need assistance?', 'eventflow' ); ?></h3>
					<p><?php esc_html_e( 'Our organizing committee is here to assist with team registration and verification inquiries.', 'eventflow' ); ?></p>
				</div>
				<div class="ef-contact-items">
					<?php if ( $event->contact_email ) : ?>
						<a href="mailto:<?php echo esc_attr( $event->contact_email ); ?>" class="ef-contact-pill">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
							<?php echo esc_html( $event->contact_email ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $event->contact_phone ) : ?>
						<a href="tel:<?php echo esc_attr( $event->contact_phone ); ?>" class="ef-contact-pill">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
							<?php echo esc_html( $event->contact_phone ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<!-- Activity Details Modal -->
	<div class="ef-modal" id="ef-activity-detail-modal" aria-hidden="true">
		<div class="ef-modal-backdrop"></div>
		<div class="ef-modal-dialog">
			<div class="ef-modal-header">
				<h3 class="ef-modal-title" id="ef-modal-act-title"><?php esc_html_e( 'Activity Details', 'eventflow' ); ?></h3>
				<button type="button" class="ef-modal-close" data-dismiss="modal">&times;</button>
			</div>
			<div class="ef-modal-body" id="ef-modal-act-body">
				<div class="ef-spinner"></div>
			</div>
			<div class="ef-modal-footer">
				<button type="button" class="ef-btn ef-btn-secondary" data-dismiss="modal"><?php esc_html_e( 'Close', 'eventflow' ); ?></button>
				<a href="#" id="ef-modal-act-register-btn" class="ef-btn ef-btn-primary"><?php esc_html_e( 'Proceed to Registration', 'eventflow' ); ?> &rarr;</a>
			</div>
		</div>
	</div>
</div>

