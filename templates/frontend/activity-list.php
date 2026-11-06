<?php
/**
 * EventFlow Activity Cards Grid Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$activities = isset( $activities ) ? $activities : EventFlow_Activity_Manager::get_activities();
$event = isset( $event ) ? $event : EventFlow_Event_Manager::get_active_event();
$currency = $event ? esc_html( $event->currency ) : 'PKR';
?>

<div class="ef-activities-wrapper">
	<?php if ( empty( $activities ) ) : ?>
		<div class="ef-empty-state">
			<div class="ef-empty-icon">🏆</div>
			<h4><?php esc_html_e( 'No Competitions Currently Available', 'eventflow' ); ?></h4>
			<p><?php esc_html_e( 'Please check back soon or review announcements for the competition release schedule.', 'eventflow' ); ?></p>
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
								<span class="ef-meta-label"><?php esc_html_e( 'Registration Fee:', 'eventflow' ); ?></span>
								<span class="ef-meta-value ef-fee"><?php echo floatval( $act->reg_fee ) > 0 ? sprintf( '%s %s', esc_html( $currency ), number_format( $act->reg_fee, 2 ) ) : esc_html__( 'Free', 'eventflow' ); ?></span>
							</div>
							<?php if ( ! empty( $act->reg_deadline ) ) : ?>
								<div class="ef-meta-item">
									<span class="ef-meta-label"><?php esc_html_e( 'Deadline:', 'eventflow' ); ?></span>
									<span class="ef-meta-value"><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $act->reg_deadline ) ) ); ?></span>
								</div>
							<?php endif; ?>
						</div>

						<div class="ef-activity-footer">
							<button type="button" class="ef-btn ef-btn-outline ef-btn-sm ef-open-activity-modal" data-id="<?php echo esc_attr( $act->id ); ?>">
								<?php esc_html_e( 'Rules & Info', 'eventflow' ); ?>
							</button>

							<?php if ( $is_open ) : ?>
								<a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'register_activity', 'activity_id' => $act->id ), EventFlow_Shortcodes::get_dashboard_url() ) ); ?>" class="ef-btn ef-btn-primary ef-btn-sm">
									<?php esc_html_e( 'Register Team', 'eventflow' ); ?> &rarr;
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

