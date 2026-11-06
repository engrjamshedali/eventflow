<?php
/**
 * EventFlow Admin View: Audit & Email Logs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table_audit = EventFlow_DB::table( 'audit_logs' );
$table_email = EventFlow_DB::table( 'email_logs' );

$audit_logs = $wpdb->get_results( "SELECT a.*, u.display_name FROM {$table_audit} a LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID ORDER BY a.id DESC LIMIT 40" );
$email_logs = $wpdb->get_results( "SELECT * FROM {$table_email} ORDER BY id DESC LIMIT 40" );
?>

<div class="wrap ef-admin-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'System & Audit Logs', 'eventflow' ); ?></h1>
	<hr class="wp-header-end">

	<!-- Locked Accounts & Failed Login Attempt Monitor -->
	<div class="ef-card" style="margin-top: 20px;">
		<div class="ef-card-header" style="display:flex; justify-content:space-between; align-items:center; padding: 14px 16px; background:#fff; border-bottom:1px solid #E2E8F0;">
			<div>
				<h3 style="color:#DC2626; margin:0;">🔒 <?php esc_html_e( 'Locked Accounts & Failed Login Defense', 'eventflow' ); ?></h3>
				<p class="description" style="margin:4px 0 0;"><?php esc_html_e( 'Review and unlock accounts temporarily locked out due to exceeding failed login attempt limits.', 'eventflow' ); ?></p>
			</div>
			<span class="ef-badge ef-badge-neutral"><?php printf( esc_html__( 'Threshold: %d attempts / %d min lockout', 'eventflow' ), get_option( 'eventflow_max_login_attempts', 5 ), get_option( 'eventflow_lockout_duration', 30 ) ); ?></span>
		</div>
		<div class="ef-card-body" style="padding:0;">
			<?php $locked_accounts = EventFlow_Auth::get_locked_accounts(); ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Account / IP Target', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Failed Attempts', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Lockout Triggered', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Lockout Expires', 'eventflow' ); ?></th>
						<th style="text-align:right;"><?php esc_html_e( 'Action', 'eventflow' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $locked_accounts ) ) : ?>
						<tr>
							<td colspan="5" style="text-align:center; padding:18px; color:#059669;">
								🛡️ <?php esc_html_e( 'Zero accounts currently locked. System is safe and operating under normal security thresholds.', 'eventflow' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $locked_accounts as $acc ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $acc['identifier'] ); ?></strong></td>
								<td><span class="ef-badge ef-badge-danger"><?php echo esc_html( $acc['attempt_count'] ); ?> attempts</span></td>
								<td><code><?php echo esc_html( $acc['locked_at'] ); ?></code></td>
								<td><span class="ef-badge ef-badge-warning"><?php echo esc_html( human_time_diff( strtotime( $acc['lockout_expires'] ), current_time( 'timestamp' ) ) ); ?> remaining</span></td>
								<td style="text-align:right;">
									<button type="button" class="button button-small button-primary ef-btn-unlock-account" data-identifier="<?php echo esc_attr( $acc['identifier'] ); ?>">
										🔓 <?php esc_html_e( 'Unlock', 'eventflow' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="ef-grid ef-grid-2" style="margin-top: 20px;">
		<!-- Audit Trail -->
		<div class="ef-card">
			<div class="ef-card-header">
				<h3><?php esc_html_e( 'Operational Audit Trail (Last 40)', 'eventflow' ); ?></h3>
			</div>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Action', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'User', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Object ID', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Timestamp', 'eventflow' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $audit_logs ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No audit entries yet.', 'eventflow' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $audit_logs as $l ) : ?>
							<tr>
								<td><code><?php echo esc_html( $l->action ); ?></code></td>
								<td><?php echo esc_html( $l->display_name ? $l->display_name : 'System/Guest' ); ?></td>
								<td><?php echo esc_html( $l->object_id ? $l->object_id : '-' ); ?></td>
								<td><?php echo esc_html( date_i18n( 'M j, H:i', strtotime( $l->created_at ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<!-- Email Logs -->
		<div class="ef-card">
			<div class="ef-card-header">
				<h3><?php esc_html_e( 'Email Automation Logs (Last 40)', 'eventflow' ); ?></h3>
			</div>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Template', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Recipient', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Status', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Sent Time', 'eventflow' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $email_logs ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No email records dispatched yet.', 'eventflow' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $email_logs as $el ) : ?>
							<tr>
								<td><code><?php echo esc_html( $el->template_key ); ?></code></td>
								<td><?php echo esc_html( $el->recipient_email ); ?></td>
								<td>
									<span class="ef-badge <?php echo 'sent' === $el->status ? 'ef-badge-success' : 'ef-badge-danger'; ?>">
										<?php echo esc_html( ucfirst( $el->status ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( date_i18n( 'M j, H:i', strtotime( $el->sent_at ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

