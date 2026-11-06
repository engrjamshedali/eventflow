<?php
/**
 * EventFlow Activity Single Detail View
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$activity = isset( $activity ) ? $activity : null;
if ( ! $activity ) {
	echo '<p class="ef-notice ef-notice-warning">' . esc_html__( 'Activity not found.', 'eventflow' ) . '</p>';
	return;
}

$event = isset( $event ) ? $event : EventFlow_Event_Manager::get_event( $activity->event_id );
$currency = $event ? esc_html( $event->currency ) : 'PKR';
$is_open = EventFlow_Activity_Manager::is_open_for_registration( $activity );
?>

<div class="eventflow-app ef-activity-single">
	<div class="ef-card">
		<?php if ( ! empty( $activity->image_url ) ) : ?>
			<div class="ef-single-banner" style="background-image: url('<?php echo esc_url( $activity->image_url ); ?>');"></div>
		<?php endif; ?>

		<div class="ef-card-body">
			<div class="ef-single-header">
				<div>
					<div class="ef-activity-tags">
						<span class="ef-tag"><?php echo esc_html( ucfirst( $activity->participation_type ) ); ?></span>
						<?php if ( (int) $activity->max_team_size > 1 ) : ?>
							<span class="ef-tag ef-tag-muted">
								<?php printf( esc_html__( '%d to %d Members per Team', 'eventflow' ), (int) $activity->min_team_size, (int) $activity->max_team_size ); ?>
							</span>
						<?php endif; ?>
					</div>
					<h1 class="ef-single-title"><?php echo esc_html( $activity->title ); ?></h1>
				</div>

				<div class="ef-single-fee-box">
					<span class="ef-text-muted"><?php esc_html_e( 'Registration Fee', 'eventflow' ); ?></span>
					<div class="ef-fee-lg">
						<?php echo floatval( $activity->reg_fee ) > 0 ? sprintf( '%s %s', esc_html( $currency ), number_format( $activity->reg_fee, 2 ) ) : esc_html__( 'Free Entry', 'eventflow' ); ?>
					</div>
				</div>
			</div>

			<div class="ef-single-content">
				<h3><?php esc_html_e( 'Description & Overview', 'eventflow' ); ?></h3>
				<p><?php echo wp_kses_post( $activity->description ); ?></p>

				<?php if ( ! empty( $activity->eligibility ) ) : ?>
					<h3><?php esc_html_e( 'Eligibility Criteria', 'eventflow' ); ?></h3>
					<div class="ef-info-box">
						<?php echo nl2br( esc_html( $activity->eligibility ) ); ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $activity->instructions ) ) : ?>
					<h3><?php esc_html_e( 'Special Instructions & Rules', 'eventflow' ); ?></h3>
					<div class="ef-info-box ef-info-box-alt">
						<?php echo nl2br( esc_html( $activity->instructions ) ); ?>
					</div>
				<?php endif; ?>

				<div class="ef-meta-summary-table">
					<div class="ef-meta-row">
						<strong><?php esc_html_e( 'Registration Deadline:', 'eventflow' ); ?></strong>
						<span><?php echo ! empty( $activity->reg_deadline ) ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $activity->reg_deadline ) ) ) : esc_html__( 'Open until Event Registration Closes', 'eventflow' ); ?></span>
					</div>
					<div class="ef-meta-row">
						<strong><?php esc_html_e( 'Team Leader Counts As Member:', 'eventflow' ); ?></strong>
						<span><?php echo (int) $activity->leader_counts_as_member ? esc_html__( 'Yes', 'eventflow' ) : esc_html__( 'No', 'eventflow' ); ?></span>
					</div>
					<?php if ( (int) $activity->max_participants > 0 ) : ?>
						<div class="ef-meta-row">
							<strong><?php esc_html_e( 'Capacity Limit:', 'eventflow' ); ?></strong>
							<span><?php printf( esc_html__( '%d of %d Registered', 'eventflow' ), (int) $activity->current_participants, (int) $activity->max_participants ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="ef-single-actions">
				<?php if ( $is_open ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'register_activity', 'activity_id' => $activity->id ), EventFlow_Shortcodes::get_dashboard_url() ) ); ?>" class="ef-btn ef-btn-primary ef-btn-lg">
						<?php esc_html_e( 'Register for this Competition', 'eventflow' ); ?> &rarr;
					</a>
				<?php else : ?>
					<div class="ef-notice ef-notice-warning">
						<p><?php esc_html_e( 'Registration for this activity is currently closed or capacity limit has been reached.', 'eventflow' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

