<?php
/**
 * SIBACOM EVENT Registration Portal - Native Email Administration View
 *
 * Clean 5-tab interface:
 * 1. Email Settings (Configured SIBACOM EVENT identity, native wp_mail(), notification triggers, test email)
 * 2. Email Templates (Template manager, WYSIWYG editor, dynamic placeholder pills, live preview)
 * 3. Email Queue (Background worker, WP-Cron status, batch processor, retry backoff)
 * 4. Email Logs (Database-backed logs, status badges, search, filters, manual retry, CSV export)
 * 5. Delivery Diagnostics (wp_mail API, PHP mail transport, DNS SPF/DKIM/DMARC for portal.sibacom.tech)
 *
 * @package EventFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$allowed_tabs = array( 'settings', 'smtp', 'templates', 'queue', 'logs', 'diagnostics' );
$active_tab   = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
if ( ! in_array( $active_tab, $allowed_tabs, true ) ) {
	$active_tab = 'settings';
}

$queue_stats = EventFlow_Email_Queue::get_queue_stats();
$diagnostics = EventFlow_Email_Diagnostics::run_full_diagnosis();
$templates   = EventFlow_Email_Manager::get_all_templates();
$smtp_config = class_exists( 'EventFlow_SMTP' ) ? EventFlow_SMTP::get_config() : array();
$smtp_conflicts = class_exists( 'EventFlow_SMTP' ) ? EventFlow_SMTP::detect_conflicts() : array();

// Templates tab active item
$edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : ( ! empty( $templates ) ? $templates[0]->id : 0 );
$active_tpl = null;
foreach ( $templates as $t ) {
	if ( (int) $t->id === $edit_id ) {
		$active_tpl = $t;
		break;
	}
}
if ( ! $active_tpl && ! empty( $templates ) ) {
	$active_tpl = $templates[0];
}

// Logs tab query
$log_status_filter = isset( $_GET['log_status'] ) ? sanitize_text_field( wp_unslash( $_GET['log_status'] ) ) : 'all';
$log_type_filter   = isset( $_GET['log_type'] ) ? sanitize_text_field( wp_unslash( $_GET['log_type'] ) ) : 'all';
$log_search        = isset( $_GET['log_search'] ) ? sanitize_text_field( wp_unslash( $_GET['log_search'] ) ) : '';
$log_date_from     = isset( $_GET['filter_from'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_from'] ) ) : ( isset( $_GET['log_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['log_date_from'] ) ) : '' );
$log_date_to       = isset( $_GET['filter_to'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_to'] ) ) : ( isset( $_GET['log_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['log_date_to'] ) ) : '' );
$log_page          = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$log_limit         = 25;
$log_offset        = ( $log_page - 1 ) * $log_limit;

$table_logs   = EventFlow_DB::table( 'email_logs' );
$where_clauses = array( '1=1' );
$where_args    = array();

if ( 'all' !== $log_status_filter ) {
	if ( 'accepted' === $log_status_filter || 'sent' === $log_status_filter ) {
		$where_clauses[] = "( status = 'accepted' OR status = 'sent' )";
	} else {
		$where_clauses[] = 'status = %s';
		$where_args[]    = $log_status_filter;
	}
}
if ( 'all' !== $log_type_filter ) {
	$where_clauses[] = 'template_key = %s';
	$where_args[]    = $log_type_filter;
}
if ( ! empty( $log_date_from ) ) {
	$where_clauses[] = 'sent_at >= %s';
	$where_args[]    = $log_date_from . ' 00:00:00';
}
if ( ! empty( $log_date_to ) ) {
	$where_clauses[] = 'sent_at <= %s';
	$where_args[]    = $log_date_to . ' 23:59:59';
}
if ( ! empty( $log_search ) ) {
	$where_clauses[] = '( recipient_email LIKE %s OR subject LIKE %s OR registration_id LIKE %s OR sender_email LIKE %s )';
	$search_like     = '%' . $wpdb->esc_like( $log_search ) . '%';
	$where_args[]    = $search_like;
	$where_args[]    = $search_like;
	$where_args[]    = $search_like;
	$where_args[]    = $search_like;
}

$where_sql   = implode( ' AND ', $where_clauses );
$count_query = empty( $where_args ) ? "SELECT COUNT(*) FROM {$table_logs} WHERE {$where_sql}" : $wpdb->prepare( "SELECT COUNT(*) FROM {$table_logs} WHERE {$where_sql}", $where_args );
$total_logs  = (int) $wpdb->get_var( $count_query );
$total_log_pages = ceil( $total_logs / $log_limit );

$select_query = empty( $where_args )
	? "SELECT * FROM {$table_logs} WHERE {$where_sql} ORDER BY id DESC LIMIT {$log_offset}, {$log_limit}"
	: $wpdb->prepare( "SELECT * FROM {$table_logs} WHERE {$where_sql} ORDER BY id DESC LIMIT {$log_offset}, {$log_limit}", $where_args );
$email_logs = $wpdb->get_results( $select_query );

// Queue tab query
$table_queue = EventFlow_DB::table( 'email_queue' );
$queue_items = $wpdb->get_results( "SELECT * FROM {$table_queue} ORDER BY id DESC LIMIT 50" );

// Compute 7 real-time email operations monitoring statistics (Master Prompt Section 5.A)
$stat_total_triggered = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_logs}" );
$stat_sent_accepted   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_logs} WHERE status IN ('sent', 'accepted')" );
$stat_failed          = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_logs} WHERE status = 'failed'" );
$stat_pending_queue   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_queue} WHERE status IN ('pending', 'retrying')" );
$stat_processing      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_queue} WHERE status = 'processing'" );
$stat_completed_denom = $stat_sent_accepted + $stat_failed;
$stat_success_rate    = $stat_completed_denom > 0 ? round( ( $stat_sent_accepted / $stat_completed_denom ) * 100, 1 ) : ( $stat_total_triggered > 0 ? 0.0 : 100.0 );
$stat_active_templates = count( $templates );
?>

<div class="wrap ef-admin-wrap">
	<div class="ef-flex-between" style="margin-bottom: 14px; align-items: flex-start;">
		<div>
			<h1 class="wp-heading-inline" style="font-weight: 800; color: #0F172A;">
				<?php esc_html_e( 'SIBACOM EVENT – Email Operations & Automation Center', 'eventflow' ); ?>
			</h1>
			<p class="ef-text-muted ef-text-sm" style="margin-top: 4px;">
				<?php esc_html_e( 'WordPress Native (wp_mail) automated email delivery engine, responsive SIBACOM EVENT branding, multi-member team dispatches, queue manager, and live diagnostics.', 'eventflow' ); ?>
			</p>
		</div>
		<div style="display: flex; gap: 10px; align-items: center;">
			<span class="ef-badge ef-badge-success" style="padding: 6px 12px; font-weight: 600;">
				● <?php esc_html_e( 'Native wp_mail() Active', 'eventflow' ); ?>
			</span>
			<button type="button" class="button button-primary" id="ef-open-quick-test-btn" style="background: #0D9488; border-color: #0F766E;">
				<span class="dashicons dashicons-email-alt" style="vertical-align: middle; margin-top: -2px;"></span> <?php esc_html_e( 'Send Test Email', 'eventflow' ); ?>
			</button>
		</div>
	</div>
	<hr class="wp-header-end">

	<!-- 7 Real-time Monitoring KPI Statistics Cards (Master Prompt Section 5.A) -->
	<div class="ef-email-kpi-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-top: 18px; margin-bottom: 20px;">
		<div class="ef-card" style="padding: 12px; text-align: center; border-top: 3px solid #0D9488;">
			<span class="ef-text-xs ef-text-muted" style="text-transform: uppercase; font-weight: 700; font-size: 11px;"><?php esc_html_e( 'Total Emails Sent', 'eventflow' ); ?></span>
			<div style="font-size: 20px; font-weight: 800; color: #0F172A; margin-top: 4px;"><?php echo number_format_i18n( $stat_total_triggered ); ?></div>
		</div>
		<div class="ef-card" style="padding: 12px; text-align: center; border-top: 3px solid #10B981;">
			<span class="ef-text-xs ef-text-muted" style="text-transform: uppercase; font-weight: 700; font-size: 11px;"><?php esc_html_e( 'Sent / Accepted by Mail Server', 'eventflow' ); ?></span>
			<div style="font-size: 20px; font-weight: 800; color: #059669; margin-top: 4px;"><?php echo number_format_i18n( $stat_sent_accepted ); ?></div>
		</div>
		<div class="ef-card" style="padding: 12px; text-align: center; border-top: 3px solid #EF4444;">
			<span class="ef-text-xs ef-text-muted" style="text-transform: uppercase; font-weight: 700; font-size: 11px;"><?php esc_html_e( 'Failed Dispatches', 'eventflow' ); ?></span>
			<div style="font-size: 20px; font-weight: 800; color: #DC2626; margin-top: 4px;"><?php echo number_format_i18n( $stat_failed ); ?></div>
		</div>
		<div class="ef-card" style="padding: 12px; text-align: center; border-top: 3px solid #F59E0B;">
			<span class="ef-text-xs ef-text-muted" style="text-transform: uppercase; font-weight: 700; font-size: 11px;"><?php esc_html_e( 'In Queue / Pending', 'eventflow' ); ?></span>
			<div style="font-size: 20px; font-weight: 800; color: #D97706; margin-top: 4px;"><?php echo number_format_i18n( $stat_pending_queue ); ?></div>
		</div>
		<div class="ef-card" style="padding: 12px; text-align: center; border-top: 3px solid #3B82F6;">
			<span class="ef-text-xs ef-text-muted" style="text-transform: uppercase; font-weight: 700; font-size: 11px;"><?php esc_html_e( 'Processing', 'eventflow' ); ?></span>
			<div style="font-size: 20px; font-weight: 800; color: #2563EB; margin-top: 4px;"><?php echo number_format_i18n( $stat_processing ); ?></div>
		</div>
		<div class="ef-card" style="padding: 12px; text-align: center; border-top: 3px solid #8B5CF6;">
			<span class="ef-text-xs ef-text-muted" style="text-transform: uppercase; font-weight: 700; font-size: 11px;"><?php esc_html_e( 'Delivery Success Rate', 'eventflow' ); ?></span>
			<div style="font-size: 20px; font-weight: 800; color: #7C3AED; margin-top: 4px;"><?php echo esc_html( $stat_success_rate ); ?>%</div>
		</div>
		<div class="ef-card" style="padding: 12px; text-align: center; border-top: 3px solid #06B6D4;">
			<span class="ef-text-xs ef-text-muted" style="text-transform: uppercase; font-weight: 700; font-size: 11px;"><?php esc_html_e( 'Active Email Templates', 'eventflow' ); ?></span>
			<div style="font-size: 20px; font-weight: 800; color: #0891B2; margin-top: 4px;"><?php echo number_format_i18n( $stat_active_templates ); ?></div>
		</div>
	</div>

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible" style="margin-top: 15px;">
			<p><strong><?php esc_html_e( 'Email settings saved successfully.', 'eventflow' ); ?></strong></p>
		</div>
	<?php endif; ?>

	<!-- Top Navigation Tabs -->
	<nav class="nav-tab-wrapper ef-tab-nav" style="margin-top: 18px; margin-bottom: 24px; border-bottom-color: #CBD5E1;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-emails&tab=settings' ) ); ?>" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Email Settings', 'eventflow' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-emails&tab=smtp' ) ); ?>" class="nav-tab <?php echo 'smtp' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-email"></span> <?php esc_html_e( 'SMTP Transport', 'eventflow' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-emails&tab=templates' ) ); ?>" class="nav-tab <?php echo 'templates' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-media-document"></span> <?php esc_html_e( 'Email Templates', 'eventflow' ); ?> (<?php echo count( $templates ); ?>)
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-emails&tab=queue' ) ); ?>" class="nav-tab <?php echo 'queue' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Email Queue', 'eventflow' ); ?>
			<?php if ( $queue_stats['pending'] > 0 || $queue_stats['retrying'] > 0 ) : ?>
				<span class="ef-tab-count"><?php echo esc_html( $queue_stats['pending'] + $queue_stats['retrying'] ); ?></span>
			<?php endif; ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-emails&tab=logs' ) ); ?>" class="nav-tab <?php echo 'logs' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Email Logs', 'eventflow' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-emails&tab=diagnostics' ) ); ?>" class="nav-tab <?php echo 'diagnostics' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-heart"></span> <?php esc_html_e( 'Email Diagnostics', 'eventflow' ); ?>
		</a>
	</nav>

	<!-- =====================================================================
	     TAB 1: EMAIL SETTINGS (SENDER IDENTITY & NOTIFICATION TRIGGERS)
	     ===================================================================== -->
	<?php if ( 'settings' === $active_tab ) : ?>
		<div class="ef-grid ef-grid-3">
			<div style="grid-column: span 2;">
				<!-- Notification Triggers & Identity Form -->
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="ef-form">
					<input type="hidden" name="eventflow_admin_action" value="save_email_general_settings">
					<?php wp_nonce_field( 'eventflow_save_email_general_nonce' ); ?>

					<!-- Configurable SIBACOM EVENT Sender Identity Box -->
					<div class="ef-card" style="margin-bottom: 24px; border-left: 4px solid #0D9488;">
						<div class="ef-card-header">
							<h3 style="color: #0F766E;"><?php esc_html_e( 'Official SIBACOM EVENT Sender Identity', 'eventflow' ); ?></h3>
							<p class="ef-card-subtitle">
								<?php esc_html_e( 'All automated plugin notifications are dispatched using WordPress native wp_mail() transport with official SIBACOM EVENT branding.', 'eventflow' ); ?>
							</p>
						</div>

						<div class="ef-grid ef-grid-3" style="padding-top: 10px;">
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Sender Display Name', 'eventflow' ); ?></strong> <span class="ef-req">*</span></label>
								<input type="text" name="eventflow_email_from_name" class="ef-input" value="<?php echo esc_attr( EventFlow_Email_Manager::get_sender_name() ); ?>" required placeholder="SIBACOM EVENT">
								<p class="description"><?php esc_html_e( 'Official sender name (SIBACOM EVENT).', 'eventflow' ); ?></p>
							</div>

							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Sender Email Address', 'eventflow' ); ?></strong> <span class="ef-req">*</span></label>
								<input type="email" name="eventflow_email_from_address" class="ef-input" value="<?php echo esc_attr( EventFlow_Email_Manager::get_sender_email() ); ?>" required placeholder="info@portal.sibacom.tech">
								<p class="description"><?php esc_html_e( 'Native wp_mail_from envelope address.', 'eventflow' ); ?></p>
							</div>

							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Reply-To Address', 'eventflow' ); ?></strong></label>
								<input type="email" name="eventflow_email_reply_to" class="ef-input" value="<?php echo esc_attr( EventFlow_Email_Manager::get_reply_to() ); ?>" placeholder="info@portal.sibacom.tech">
								<p class="description"><?php esc_html_e( 'Applicant inquiries route here.', 'eventflow' ); ?></p>
							</div>
						</div>

						<div class="ef-form-group" style="margin-top: 10px;">
							<label><strong><?php esc_html_e( 'Email Footer Branding Text', 'eventflow' ); ?></strong></label>
							<input type="text" name="eventflow_email_footer_text" class="ef-input" value="<?php echo esc_attr( get_option( 'eventflow_email_footer_text', 'SIBACOM EVENT Official Registration Portal' ) ); ?>">
						</div>
					</div>

					<!-- Administrative Notification Address -->
					<div class="ef-card" style="margin-bottom: 24px;">
						<div class="ef-card-header">
							<h3><?php esc_html_e( 'Administrative Notification Recipient', 'eventflow' ); ?></h3>
							<p class="ef-card-subtitle"><?php esc_html_e( 'Recipient for admin-only notifications such as new payment submissions.', 'eventflow' ); ?></p>
						</div>

						<div class="ef-form-group">
							<label for="ef_admin_notification_email"><?php esc_html_e( 'Authorized Admin Email', 'eventflow' ); ?></label>
							<input type="email" name="eventflow_admin_notification_email" id="ef_admin_notification_email" class="ef-input" style="max-width: 420px;" value="<?php echo esc_attr( get_option( 'eventflow_admin_notification_email', get_option( 'admin_email' ) ) ); ?>" placeholder="admin@domain.com">
							<span class="ef-help-text"><?php esc_html_e( 'Receives immediate alerts when participants submit new payment proofs.', 'eventflow' ); ?></span>
						</div>
					</div>

					<!-- Notification Types Enable / Disable Toggles -->
					<div class="ef-card" style="margin-bottom: 24px;">
						<div class="ef-card-header">
							<h3><?php esc_html_e( 'Automated Notification Triggers', 'eventflow' ); ?></h3>
							<p class="ef-card-subtitle"><?php esc_html_e( 'Toggle which events trigger automated notifications to participants or administrators.', 'eventflow' ); ?></p>
						</div>

						<?php
						$triggers_list = array(
							'account_created'            => array( 'title' => __( 'Participant Account Creation', 'eventflow' ), 'desc' => __( 'Dispatched to candidate upon registration portal account creation.', 'eventflow' ), 'target' => 'Participant' ),
							'welcome_email'              => array( 'title' => __( 'Participant Welcome Email', 'eventflow' ), 'desc' => __( 'Dispatched with portal orientation and event deadline details.', 'eventflow' ), 'target' => 'Participant' ),
							'registration_submitted'     => array( 'title' => __( 'Application / Registration Submitted', 'eventflow' ), 'desc' => __( 'Dispatched to team leader upon submitting activity registration.', 'eventflow' ), 'target' => 'Participant' ),
							'registration_confirmation'  => array( 'title' => __( 'Team Member Confirmation', 'eventflow' ), 'desc' => __( 'Dispatched to individual team members added to a registration.', 'eventflow' ), 'target' => 'Participant' ),
							'payment_submitted'          => array( 'title' => __( 'Payment Screenshot Submitted', 'eventflow' ), 'desc' => __( 'Dispatched to participant confirming receipt of transaction proof.', 'eventflow' ), 'target' => 'Participant' ),
							'admin_payment_submitted'    => array( 'title' => __( 'New Payment Submission Alert', 'eventflow' ), 'desc' => __( 'Immediate alert to authorized administrator when payment proof is uploaded.', 'eventflow' ), 'target' => 'Admin' ),
							'payment_under_verification' => array( 'title' => __( 'Payment Under Verification', 'eventflow' ), 'desc' => __( 'Informs candidate that finance staff is reviewing the bank receipt.', 'eventflow' ), 'target' => 'Participant' ),
							'payment_verified'           => array( 'title' => __( 'Payment Verified & Approved', 'eventflow' ), 'desc' => __( 'Dispatched to participant when payment is verified and cleared.', 'eventflow' ), 'target' => 'Participant' ),
							'payment_rejected'           => array( 'title' => __( 'Payment Rejected with Exact Reason', 'eventflow' ), 'desc' => __( 'Dispatched with administrator notes explaining verification issue.', 'eventflow' ), 'target' => 'Participant' ),
							'payment_reupload_requested' => array( 'title' => __( 'Payment Re-upload Requested', 'eventflow' ), 'desc' => __( 'Prompts candidate to upload a clearer transaction proof.', 'eventflow' ), 'target' => 'Participant' ),
							'application_approved'       => array( 'title' => __( 'Application Approved', 'eventflow' ), 'desc' => __( 'Confirms final activity slot allocation and approval.', 'eventflow' ), 'target' => 'Participant' ),
							'application_rejected'       => array( 'title' => __( 'Application Rejected', 'eventflow' ), 'desc' => __( 'Notifies candidate of rejection with official remarks.', 'eventflow' ), 'target' => 'Participant' ),
							'deadline_reminder'          => array( 'title' => __( 'Registration Deadline Reminder', 'eventflow' ), 'desc' => __( 'Dispatched automatically when registration deadline approaches.', 'eventflow' ), 'target' => 'Participant' ),
							'activity_announcement'      => array( 'title' => __( 'New Activity / Event Announcement', 'eventflow' ), 'desc' => __( 'Dispatched to registered participants when new announcements are published.', 'eventflow' ), 'target' => 'Relevant Participants' ),
							'event_update'               => array( 'title' => __( 'Event Information Updated', 'eventflow' ), 'desc' => __( 'Dispatched when core schedule or venue details change.', 'eventflow' ), 'target' => 'Relevant Participants' ),
						);
						?>

						<div style="display: flex; flex-direction: column; gap: 14px;">
							<?php foreach ( $triggers_list as $t_key => $t_info ) :
								$enabled = 'yes' === get_option( 'eventflow_notify_trigger_' . $t_key, 'yes' );
							?>
								<label style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 14px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; cursor: pointer;">
									<input type="checkbox" name="eventflow_notify_trigger_<?php echo esc_attr( $t_key ); ?>" value="yes" <?php checked( $enabled, true ); ?> style="margin-top: 3px; width: 18px; height: 18px;">
									<div style="flex: 1;">
										<div style="display: flex; align-items: center; gap: 8px;">
											<strong><?php echo esc_html( $t_info['title'] ); ?></strong>
											<span class="ef-badge <?php echo 'Admin' === $t_info['target'] ? 'ef-badge-warning' : 'ef-badge-info'; ?>" style="font-size: 11px;">
												<?php echo esc_html( $t_info['target'] ); ?>
											</span>
										</div>
										<p class="ef-text-xs ef-text-muted" style="margin: 3px 0 0;"><?php echo esc_html( $t_info['desc'] ); ?></p>
									</div>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Queue Options -->
					<div class="ef-card" style="margin-bottom: 24px;">
						<div class="ef-card-header">
							<h3><?php esc_html_e( 'Background Processing & Queue Settings', 'eventflow' ); ?></h3>
							<p class="ef-card-subtitle"><?php esc_html_e( 'Asynchronous WP-Cron delivery prevents participant checkout latency.', 'eventflow' ); ?></p>
						</div>

						<div class="ef-form-group">
							<label class="ef-toggle-label" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
								<input type="checkbox" name="eventflow_email_queue_enabled" value="yes" <?php checked( get_option( 'eventflow_email_queue_enabled', 'yes' ), 'yes' ); ?> style="width: 20px; height: 20px;">
								<div>
									<strong><?php esc_html_e( 'Enable Background Email Queue', 'eventflow' ); ?></strong>
									<span class="ef-text-muted ef-text-xs" style="display: block;"><?php esc_html_e( 'Queues emails into database and processes them asynchronously via WP-Cron for fast page responses.', 'eventflow' ); ?></span>
								</div>
							</label>
						</div>

						<div class="ef-grid ef-grid-2" style="margin-top: 14px;">
							<div class="ef-form-group">
								<label><?php esc_html_e( 'Batch Size (per execution)', 'eventflow' ); ?></label>
								<input type="number" name="eventflow_email_batch_size" class="ef-input" value="<?php echo esc_attr( get_option( 'eventflow_email_batch_size', 25 ) ); ?>" min="5" max="100">
								<span class="ef-help-text"><?php esc_html_e( 'Number of emails sent per cron iteration (default: 25).', 'eventflow' ); ?></span>
							</div>

							<div class="ef-form-group">
								<label><?php esc_html_e( 'Max Retry Attempts', 'eventflow' ); ?></label>
								<input type="number" name="eventflow_email_max_retries" class="ef-input" value="<?php echo esc_attr( get_option( 'eventflow_email_max_retries', 3 ) ); ?>" min="1" max="10">
								<span class="ef-help-text"><?php esc_html_e( 'Maximum retry attempts with exponential backoff on failure.', 'eventflow' ); ?></span>
							</div>
						</div>
					</div>

					<p class="submit">
						<button type="submit" class="button button-primary button-large" style="background: #0D9488; border-color: #0F766E;">
							<?php esc_html_e( 'Save Email Configuration', 'eventflow' ); ?>
						</button>
					</p>
				</form>
			</div>

			<!-- Right Column: Quick Test Email Box & Server Overview -->
			<div>
				<div class="ef-card" style="margin-bottom: 24px;">
					<div class="ef-card-header">
						<h3><?php esc_html_e( 'Send Test Email', 'eventflow' ); ?></h3>
						<p class="ef-card-subtitle"><?php esc_html_e( 'Trigger a real wp_mail() test dispatch to verify server mail transport acceptance.', 'eventflow' ); ?></p>
					</div>

					<div class="ef-form-group">
						<label for="ef-quick-test-email"><?php esc_html_e( 'Recipient Email', 'eventflow' ); ?> <span class="ef-req">*</span></label>
						<input type="email" id="ef-quick-test-email" class="ef-input" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required placeholder="recipient@domain.com">
					</div>

					<div class="ef-form-group">
						<label for="ef-quick-test-template"><?php esc_html_e( 'Template Type', 'eventflow' ); ?></label>
						<select id="ef-quick-test-template" class="ef-input">
							<option value="test_email"><?php esc_html_e( 'Administrative Test Notification', 'eventflow' ); ?></option>
							<option value="welcome_email"><?php esc_html_e( 'Participant Welcome Email', 'eventflow' ); ?></option>
							<option value="registration_submitted"><?php esc_html_e( 'Registration Submitted', 'eventflow' ); ?></option>
							<option value="payment_verified"><?php esc_html_e( 'Payment Verified', 'eventflow' ); ?></option>
							<option value="admin_payment_submitted"><?php esc_html_e( 'New Payment Alert (Admin)', 'eventflow' ); ?></option>
						</select>
					</div>

					<button type="button" class="button button-primary" id="ef-quick-test-submit-btn" style="width: 100%; margin-top: 8px; background: #0D9488; border-color: #0F766E;">
						<span class="dashicons dashicons-email-alt" style="vertical-align: middle;"></span> <?php esc_html_e( 'Send Real Test Email', 'eventflow' ); ?>
					</button>

					<div id="ef-quick-test-result" style="display: none; margin-top: 14px; padding: 12px 14px; border-radius: 6px; font-size: 13px; line-height: 1.5;"></div>
				</div>

				<div class="ef-card">
					<div class="ef-card-header">
						<h3><?php esc_html_e( 'Server Mail Status', 'eventflow' ); ?></h3>
					</div>
					<ul class="ef-detail-list">
						<li><strong><?php esc_html_e( 'Mail Function:', 'eventflow' ); ?></strong> <span><?php echo function_exists( 'mail' ) ? '✓ Available' : '✗ Disabled'; ?></span></li>
						<li><strong><?php esc_html_e( 'PHP Version:', 'eventflow' ); ?></strong> <span>PHP <?php echo esc_html( PHP_VERSION ); ?></span></li>
						<li><strong><?php esc_html_e( 'Queue Crontab:', 'eventflow' ); ?></strong> <span><?php echo esc_html( $diagnostics['cron']['next_run'] ); ?></span></li>
						<li><strong><?php esc_html_e( 'Last Success:', 'eventflow' ); ?></strong> <span class="ef-text-xs"><?php echo esc_html( $diagnostics['transport_history']['last_success_time'] ); ?></span></li>
						<li><strong><?php esc_html_e( 'Last Failure:', 'eventflow' ); ?></strong> <span class="ef-text-xs"><?php echo esc_html( $diagnostics['transport_history']['last_failure_time'] ); ?></span></li>
					</ul>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<!-- =====================================================================
	     TAB: SMTP TRANSPORT & SERVER CONFIGURATION
	     ===================================================================== -->
	<?php if ( 'smtp' === $active_tab ) : ?>
		<div class="ef-grid ef-grid-3">
			<div style="grid-column: span 2;">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="ef-form">
					<input type="hidden" name="eventflow_admin_action" value="save_smtp_settings">
					<?php wp_nonce_field( 'eventflow_save_smtp_nonce' ); ?>

					<!-- Mailer Mode Selector Card -->
					<div class="ef-card" style="margin-bottom: 24px; border-left: 4px solid #0D9488;">
						<div class="ef-card-header">
							<h3 style="color: #0F766E;"><?php esc_html_e( 'Email Transport Mode', 'eventflow' ); ?></h3>
							<p class="ef-card-subtitle">
								<?php esc_html_e( 'Choose how SIBACOM EVENT dispatches notification emails.', 'eventflow' ); ?>
							</p>
						</div>

						<div style="display: flex; flex-direction: column; gap: 12px; margin-top: 10px;">
							<label style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 14px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; cursor: pointer;">
								<input type="radio" name="eventflow_mailer_mode" value="eventflow_smtp" <?php checked( $smtp_config['mode'], 'eventflow_smtp' ); ?> style="margin-top: 3px; width: 18px; height: 18px;">
								<div style="flex: 1;">
									<strong><?php esc_html_e( 'EventFlow Dedicated SMTP Transport (Recommended)', 'eventflow' ); ?></strong>
									<span class="ef-badge ef-badge-success" style="font-size: 11px; margin-left: 6px;"><?php esc_html_e( 'Active & Reliable', 'eventflow' ); ?></span>
									<p class="ef-text-xs ef-text-muted" style="margin: 3px 0 0;">
										<?php esc_html_e( 'Direct TLS/SSL SMTP socket communication via PHPMailer. Ensures 100% reliable transport acceptance with authentication.', 'eventflow' ); ?>
									</p>
								</div>
							</label>

							<label style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 14px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; cursor: pointer;">
								<input type="radio" name="eventflow_mailer_mode" value="wp_mail" <?php checked( $smtp_config['mode'], 'wp_mail' ); ?> style="margin-top: 3px; width: 18px; height: 18px;">
								<div style="flex: 1;">
									<strong><?php esc_html_e( 'WordPress Native wp_mail() / Server Mail (sendmail/postfix)', 'eventflow' ); ?></strong>
									<p class="ef-text-xs ef-text-muted" style="margin: 3px 0 0;">
										<?php esc_html_e( 'Dispatches using the underlying host server sendmail or an existing third-party SMTP plugin installed on WordPress.', 'eventflow' ); ?>
									</p>
								</div>
							</label>
						</div>

						<div class="ef-form-group" style="margin-top: 14px;">
							<label class="ef-toggle-label" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
								<input type="checkbox" name="eventflow_smtp_enabled" value="yes" <?php checked( $smtp_config['enabled'], 'yes' ); ?> style="width: 20px; height: 20px;">
								<div>
									<strong><?php esc_html_e( 'Enable Direct SMTP Engine for SIBACOM EVENT', 'eventflow' ); ?></strong>
									<span class="ef-text-muted ef-text-xs" style="display: block;"><?php esc_html_e( 'Overrides default php mail() and routes all eventflow dispatches through your configured SMTP server.', 'eventflow' ); ?></span>
								</div>
							</label>
						</div>
					</div>

					<!-- SMTP Server Parameters Card -->
					<div class="ef-card" style="margin-bottom: 24px;">
						<div class="ef-card-header">
							<h3><?php esc_html_e( 'SMTP Server Parameters', 'eventflow' ); ?></h3>
							<p class="ef-card-subtitle"><?php esc_html_e( 'Host, port, encryption, and secure authentication credentials.', 'eventflow' ); ?></p>
						</div>

						<div class="ef-grid ef-grid-2">
							<div class="ef-form-group">
								<label for="ef_smtp_host"><strong><?php esc_html_e( 'SMTP Host', 'eventflow' ); ?></strong> <span class="ef-req">*</span></label>
								<input type="text" id="ef_smtp_host" name="eventflow_smtp_host" class="ef-input" value="<?php echo esc_attr( $smtp_config['host'] ); ?>" placeholder="mail.portal.sibacom.tech" required>
								<span class="ef-help-text"><?php esc_html_e( 'e.g. mail.portal.sibacom.tech, smtp.hostinger.com, or smtp.gmail.com', 'eventflow' ); ?></span>
							</div>

							<div class="ef-form-group">
								<label for="ef_smtp_port"><strong><?php esc_html_e( 'SMTP Port', 'eventflow' ); ?></strong> <span class="ef-req">*</span></label>
								<input type="number" id="ef_smtp_port" name="eventflow_smtp_port" class="ef-input" value="<?php echo esc_attr( $smtp_config['port'] ); ?>" placeholder="587" required min="1" max="65535">
								<span class="ef-help-text"><?php esc_html_e( 'Common ports: 587 (TLS), 465 (SSL), or 25 (None)', 'eventflow' ); ?></span>
							</div>
						</div>

						<div class="ef-grid ef-grid-2" style="margin-top: 14px;">
							<div class="ef-form-group">
								<label for="ef_smtp_encryption"><strong><?php esc_html_e( 'Encryption Type', 'eventflow' ); ?></strong></label>
								<select id="ef_smtp_encryption" name="eventflow_smtp_encryption" class="ef-input">
									<option value="tls" <?php selected( $smtp_config['encryption'], 'tls' ); ?>><?php esc_html_e( 'TLS (Recommended, Port 587)', 'eventflow' ); ?></option>
									<option value="ssl" <?php selected( $smtp_config['encryption'], 'ssl' ); ?>><?php esc_html_e( 'SSL (Port 465)', 'eventflow' ); ?></option>
									<option value="none" <?php selected( $smtp_config['encryption'], 'none' ); ?>><?php esc_html_e( 'None (Unencrypted, Port 25)', 'eventflow' ); ?></option>
								</select>
							</div>

							<div class="ef-form-group">
								<label for="ef_smtp_timeout"><?php esc_html_e( 'Connection Timeout (Seconds)', 'eventflow' ); ?></label>
								<input type="number" id="ef_smtp_timeout" name="eventflow_smtp_timeout" class="ef-input" value="<?php echo esc_attr( $smtp_config['timeout'] ); ?>" min="5" max="60">
							</div>
						</div>

						<div class="ef-form-group" style="margin-top: 14px;">
							<label class="ef-toggle-label" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
								<input type="checkbox" id="ef_smtp_auth" name="eventflow_smtp_auth" value="yes" <?php checked( $smtp_config['auth'], 'yes' ); ?> style="width: 18px; height: 18px;">
								<span><strong><?php esc_html_e( 'Require SMTP Authentication (AUTH LOGIN)', 'eventflow' ); ?></strong></span>
							</label>
						</div>

						<div id="ef_smtp_auth_fields" style="margin-top: 14px; padding-top: 14px; border-top: 1px solid #E2E8F0;">
							<div class="ef-grid ef-grid-2">
								<div class="ef-form-group">
									<label for="ef_smtp_username"><strong><?php esc_html_e( 'SMTP Username', 'eventflow' ); ?></strong></label>
									<input type="text" id="ef_smtp_username" name="eventflow_smtp_username" class="ef-input" value="<?php echo esc_attr( $smtp_config['username'] ); ?>" placeholder="info@portal.sibacom.tech" autocomplete="off">
									<span class="ef-help-text"><?php esc_html_e( 'Full email address or SMTP service account identifier.', 'eventflow' ); ?></span>
								</div>

								<div class="ef-form-group">
									<label for="ef_smtp_password"><strong><?php esc_html_e( 'SMTP Password', 'eventflow' ); ?></strong></label>
									<div style="position: relative;">
										<input type="password" id="ef_smtp_password" name="eventflow_smtp_password" class="ef-input" value="<?php echo $smtp_config['has_password'] ? '••••••••' : ''; ?>" placeholder="<?php echo $smtp_config['has_password'] ? esc_attr__( 'Saved securely (AES-256 encrypted)', 'eventflow' ) : esc_attr__( 'Enter SMTP password', 'eventflow' ); ?>" autocomplete="new-password" style="padding-right: 40px;">
										<button type="button" id="ef-toggle-smtp-pass" class="button" style="position: absolute; right: 2px; top: 2px; height: 32px; padding: 0 8px; border: none; background: transparent; cursor: pointer;" title="<?php esc_attr_e( 'Toggle password visibility', 'eventflow' ); ?>">
											<span class="dashicons dashicons-visibility" style="font-size: 18px; line-height: 32px;"></span>
										</button>
									</div>
									<span class="ef-help-text">
										<?php if ( $smtp_config['has_password'] ) : ?>
											<span style="color: #059669;">✓ <?php esc_html_e( 'Stored with AES-256-CBC encryption. Leave unchanged to keep current password.', 'eventflow' ); ?></span>
										<?php else : ?>
											<?php esc_html_e( 'Encrypted with AES-256-CBC and stored securely in the database.', 'eventflow' ); ?>
										<?php endif; ?>
									</span>
								</div>
							</div>
						</div>
					</div>

					<!-- Official Sender Identity Synchronization Card -->
					<div class="ef-card" style="margin-bottom: 24px;">
						<div class="ef-card-header">
							<h3><?php esc_html_e( 'Sender Identity & Envelope Headers', 'eventflow' ); ?></h3>
							<p class="ef-card-subtitle"><?php esc_html_e( 'Verified SIBACOM EVENT branding used in headers and RFC 5322 From address.', 'eventflow' ); ?></p>
						</div>

						<div class="ef-grid ef-grid-3">
							<div class="ef-form-group">
								<label for="ef_from_name"><strong><?php esc_html_e( 'From Display Name', 'eventflow' ); ?></strong> <span class="ef-req">*</span></label>
								<input type="text" id="ef_from_name" name="eventflow_email_from_name" class="ef-input" value="<?php echo esc_attr( $smtp_config['from_name'] ); ?>" required placeholder="SIBACOM EVENT">
							</div>

							<div class="ef-form-group">
								<label for="ef_from_address"><strong><?php esc_html_e( 'From Email Address', 'eventflow' ); ?></strong> <span class="ef-req">*</span></label>
								<input type="email" id="ef_from_address" name="eventflow_email_from_address" class="ef-input" value="<?php echo esc_attr( $smtp_config['from_email'] ); ?>" required placeholder="info@portal.sibacom.tech">
							</div>

							<div class="ef-form-group">
								<label for="ef_reply_to"><strong><?php esc_html_e( 'Reply-To Email', 'eventflow' ); ?></strong></label>
								<input type="email" id="ef_reply_to" name="eventflow_email_reply_to" class="ef-input" value="<?php echo esc_attr( $smtp_config['reply_to'] ); ?>" placeholder="info@portal.sibacom.tech">
							</div>
						</div>
					</div>

					<p class="submit">
						<button type="submit" class="button button-primary button-large" style="background: #0D9488; border-color: #0F766E;">
							<span class="dashicons dashicons-saved" style="vertical-align: middle;"></span> <?php esc_html_e( 'Save SMTP Configuration', 'eventflow' ); ?>
						</button>
					</p>
				</form>
			</div>

			<!-- Right Sidebar: Live Connection Tester & Conflict Inspection -->
			<div>
				<!-- Live Connection Tester Card -->
				<div class="ef-card" style="margin-bottom: 24px; border-top: 3px solid #0D9488;">
					<div class="ef-card-header">
						<h3><?php esc_html_e( 'Live Socket & Auth Tester', 'eventflow' ); ?></h3>
						<p class="ef-card-subtitle"><?php esc_html_e( 'Perform real-time DNS resolution, TCP socket connection, and SMTP authentication handshake.', 'eventflow' ); ?></p>
					</div>

					<p class="ef-text-sm ef-text-muted" style="margin-bottom: 14px;">
						<?php esc_html_e( 'Tests parameters entered in the form above before saving to verify mail server reachability.', 'eventflow' ); ?>
					</p>

					<button type="button" class="button button-primary" id="ef-test-smtp-btn" style="width: 100%; background: #0D9488; border-color: #0F766E;">
						<span class="dashicons dashicons-networking" style="vertical-align: middle;"></span> <?php esc_html_e( 'Test SMTP Connection Now', 'eventflow' ); ?>
					</button>

					<div id="ef-smtp-test-result" style="display: none; margin-top: 14px; padding: 12px; border-radius: 6px; font-size: 13px;"></div>
				</div>

				<!-- Third-Party Plugin Conflict Detection Card -->
				<div class="ef-card" style="margin-bottom: 24px;">
					<div class="ef-card-header">
						<h3><?php esc_html_e( 'Mailer Conflict Inspector', 'eventflow' ); ?></h3>
					</div>

					<?php if ( ! empty( $smtp_conflicts ) ) : ?>
						<div class="ef-alert ef-alert-warning" style="margin-top: 10px; padding: 12px; background: #FFFBEB; border: 1px solid #FCD34D; border-radius: 6px;">
							<strong style="color: #92400E;">⚠ <?php esc_html_e( 'Potential Plugin Overlap Detected:', 'eventflow' ); ?></strong>
							<ul style="margin: 8px 0 0 16px; padding: 0; font-size: 12px; color: #78350F;">
								<?php foreach ( $smtp_conflicts as $conflict ) : ?>
									<li><strong><?php echo esc_html( $conflict['name'] ); ?>:</strong> <?php echo esc_html( $conflict['description'] ); ?></li>
								<?php endforeach; ?>
							</ul>
							<p class="ef-text-xs" style="margin-top: 8px; color: #92400E;">
								<?php esc_html_e( 'When EventFlow SMTP is enabled, it binds at priority 999 to guarantee official SIBACOM EVENT sender identity.', 'eventflow' ); ?>
							</p>
						</div>
					<?php else : ?>
						<div style="padding: 12px; background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 6px; color: #065F46; font-size: 13px;">
							<strong>✓ <?php esc_html_e( 'Clean Mail Environment', 'eventflow' ); ?></strong>
							<p class="ef-text-xs" style="margin: 4px 0 0;">
								<?php esc_html_e( 'No conflicting third-party SMTP plugins detected. EventFlow has full control over PHPMailer.', 'eventflow' ); ?>
							</p>
						</div>
					<?php endif; ?>
				</div>

				<!-- Quick Diagnostic Reference Card -->
				<div class="ef-card">
					<div class="ef-card-header">
						<h3><?php esc_html_e( 'Transport Reference', 'eventflow' ); ?></h3>
					</div>
					<ul class="ef-detail-list">
						<li><strong><?php esc_html_e( 'Sender Identity:', 'eventflow' ); ?></strong> <span>SIBACOM EVENT</span></li>
						<li><strong><?php esc_html_e( 'Official Email:', 'eventflow' ); ?></strong> <span class="ef-text-xs">info@portal.sibacom.tech</span></li>
						<li><strong><?php esc_html_e( 'Encryption Mode:', 'eventflow' ); ?></strong> <span>AES-256-CBC</span></li>
						<li><strong><?php esc_html_e( 'OpenSSL Active:', 'eventflow' ); ?></strong> <span><?php echo function_exists( 'openssl_encrypt' ) ? '✓ Enabled' : '✗ Disabled'; ?></span></li>
					</ul>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<!-- =====================================================================
	     TAB 2: EMAIL TEMPLATES MANAGER
	     ===================================================================== -->
	<?php if ( 'templates' === $active_tab ) : ?>
		<div class="ef-grid ef-grid-3">
			<!-- Template Selector Sidebar -->
			<div class="ef-card" style="grid-column: span 1;">
				<div class="ef-card-header">
					<h3><?php esc_html_e( 'Notification Templates', 'eventflow' ); ?></h3>
					<p class="ef-card-subtitle"><?php esc_html_e( 'Select an automated template to edit its subject, HTML content, and active status.', 'eventflow' ); ?></p>
				</div>

				<ul class="ef-template-nav-list" style="margin: 0; padding: 0; list-style: none;">
					<?php foreach ( $templates as $t ) : ?>
						<li style="margin-bottom: 6px;">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-emails&tab=templates&edit=' . $t->id ) ); ?>"
							   class="ef-template-item <?php echo (int) $t->id === (int) $active_tpl->id ? 'active' : ''; ?>"
							   style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; border-radius: 6px; text-decoration: none; border: 1px solid <?php echo (int) $t->id === (int) $active_tpl->id ? '#0D9488' : '#E2E8F0'; ?>; background: <?php echo (int) $t->id === (int) $active_tpl->id ? '#F0FDFA' : '#FFFFFF'; ?>;">
								<div>
									<strong style="color: <?php echo (int) $t->id === (int) $active_tpl->id ? '#0F766E' : '#1E293B'; ?>; font-size: 13.5px; display: block;">
										<?php echo esc_html( $t->name ); ?>
									</strong>
									<span class="ef-text-xs ef-text-muted"><?php echo esc_html( $t->template_key ); ?></span>
								</div>
								<span class="ef-badge <?php echo (int) $t->is_active ? 'ef-badge-success' : 'ef-badge-gray'; ?>" style="font-size: 11px;">
									<?php echo (int) $t->is_active ? esc_html__( 'Active', 'eventflow' ) : esc_html__( 'Inactive', 'eventflow' ); ?>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<!-- Template Editor Area -->
			<div class="ef-card" style="grid-column: span 2;">
				<?php if ( $active_tpl ) : ?>
					<div class="ef-card-header ef-flex-between">
						<div>
							<h3><?php echo esc_html( $active_tpl->name ); ?></h3>
							<p class="ef-card-subtitle">
								<code><?php echo esc_html( $active_tpl->template_key ); ?></code> &bull;
								<?php esc_html_e( 'Sent using SIBACOM EVENT branded responsive template layout.', 'eventflow' ); ?>
							</p>
						</div>
						<div style="display: flex; gap: 8px;">
							<button type="button" class="button button-secondary ef-preview-template-btn" data-id="<?php echo esc_attr( $active_tpl->id ); ?>">
								<span class="dashicons dashicons-visibility" style="vertical-align: middle;"></span> <?php esc_html_e( 'Preview HTML', 'eventflow' ); ?>
							</button>
							<button type="button" class="button ef-reset-template-btn" data-id="<?php echo esc_attr( $active_tpl->id ); ?>" style="color: #64748B;">
								<?php esc_html_e( 'Reset to Default', 'eventflow' ); ?>
							</button>
						</div>
					</div>

					<!-- Dynamic Placeholders Bar -->
					<div style="background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 8px; padding: 12px 14px; margin-bottom: 20px;">
						<strong class="ef-text-xs" style="text-transform: uppercase; color: #64748B; display: block; margin-bottom: 6px;">
							<?php esc_html_e( 'Dynamic Placeholders (Click to insert):', 'eventflow' ); ?>
						</strong>
						<div style="display: flex; flex-wrap: wrap; gap: 6px;">
							<?php
							$placeholders_available = array(
								'{participant_name}', '{participant_email}', '{team_name}', '{registration_id}',
								'{activity_name}', '{payment_amount}', '{transaction_id}', '{payment_status}',
								'{application_status}', '{rejection_reason}', '{event_name}', '{event_date}',
								'{registration_deadline}', '{website_url}',
							);
							foreach ( $placeholders_available as $ph ) :
							?>
								<button type="button" class="button button-small ef-insert-ph-btn" data-ph="<?php echo esc_attr( $ph ); ?>" style="font-family: monospace; font-size: 11px;">
									<?php echo esc_html( $ph ); ?>
								</button>
							<?php endforeach; ?>
						</div>
					</div>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="ef-form">
						<input type="hidden" name="eventflow_admin_action" value="update_email_template">
						<input type="hidden" name="template_id" value="<?php echo esc_attr( $active_tpl->id ); ?>">
						<?php wp_nonce_field( 'eventflow_update_template_nonce' ); ?>

						<div class="ef-form-group">
							<label for="ef-tpl-subject"><?php esc_html_e( 'Email Subject Line', 'eventflow' ); ?> <span class="ef-req">*</span></label>
							<input type="text" name="template_subject" id="ef-tpl-subject" class="ef-input" value="<?php echo esc_attr( $active_tpl->subject ); ?>" required>
							<span class="ef-help-text"><?php esc_html_e( 'Supports dynamic placeholders like {participant_name}, {event_name}, {registration_id}.', 'eventflow' ); ?></span>
						</div>

						<div class="ef-form-group">
							<label for="ef-tpl-body"><?php esc_html_e( 'Email Body Content', 'eventflow' ); ?> <span class="ef-req">*</span></label>
							<textarea name="template_body" id="ef-tpl-body" class="ef-input" rows="12" style="font-family: monospace; font-size: 13px; line-height: 1.5;" required><?php echo esc_textarea( $active_tpl->body ); ?></textarea>
							<span class="ef-help-text"><?php esc_html_e( 'HTML formatting supported. Wrap action buttons in <a href="{website_url}" class="btn">Button Text</a>.', 'eventflow' ); ?></span>
						</div>

						<div class="ef-form-group">
							<label class="ef-toggle-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
								<input type="checkbox" name="is_active" value="1" <?php checked( (int) $active_tpl->is_active, 1 ); ?> style="width: 18px; height: 18px;">
								<strong><?php esc_html_e( 'Enable this notification template', 'eventflow' ); ?></strong>
							</label>
						</div>

						<p class="submit">
							<button type="submit" class="button button-primary button-large" style="background: #0D9488; border-color: #0F766E;">
								<?php esc_html_e( 'Save Template Changes', 'eventflow' ); ?>
							</button>
						</p>
					</form>
				<?php else : ?>
					<p class="ef-text-muted"><?php esc_html_e( 'No email templates found in database.', 'eventflow' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<!-- =====================================================================
	     TAB 3: EMAIL QUEUE
	     ===================================================================== -->
	<?php if ( 'queue' === $active_tab ) : ?>
		<div class="ef-stat-grid" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 14px; margin-bottom: 24px;">
			<div class="ef-stat-card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 18px; text-align: center;">
				<span class="ef-text-muted ef-text-xs" style="text-transform: uppercase; font-weight: 700;"><?php esc_html_e( 'Pending', 'eventflow' ); ?></span>
				<div style="font-size: 26px; font-weight: 800; color: #0284C7; margin-top: 4px;"><?php echo esc_html( $queue_stats['pending'] ); ?></div>
			</div>
			<div class="ef-stat-card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 18px; text-align: center;">
				<span class="ef-text-muted ef-text-xs" style="text-transform: uppercase; font-weight: 700;"><?php esc_html_e( 'Processing', 'eventflow' ); ?></span>
				<div style="font-size: 26px; font-weight: 800; color: #D97706; margin-top: 4px;"><?php echo esc_html( $queue_stats['processing'] ); ?></div>
			</div>
			<div class="ef-stat-card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 18px; text-align: center;">
				<span class="ef-text-muted ef-text-xs" style="text-transform: uppercase; font-weight: 700;"><?php esc_html_e( 'Retry Pending', 'eventflow' ); ?></span>
				<div style="font-size: 26px; font-weight: 800; color: #EA580C; margin-top: 4px;"><?php echo esc_html( $queue_stats['retrying'] ); ?></div>
			</div>
			<div class="ef-stat-card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 18px; text-align: center;">
				<span class="ef-text-muted ef-text-xs" style="text-transform: uppercase; font-weight: 700;"><?php esc_html_e( 'Accepted by Transport', 'eventflow' ); ?></span>
				<div style="font-size: 26px; font-weight: 800; color: #0D9488; margin-top: 4px;"><?php echo esc_html( $queue_stats['accepted'] + $queue_stats['sent'] ); ?></div>
			</div>
			<div class="ef-stat-card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 18px; text-align: center;">
				<span class="ef-text-muted ef-text-xs" style="text-transform: uppercase; font-weight: 700;"><?php esc_html_e( 'Failed', 'eventflow' ); ?></span>
				<div style="font-size: 26px; font-weight: 800; color: #DC2626; margin-top: 4px;"><?php echo esc_html( $queue_stats['failed'] ); ?></div>
			</div>
		</div>

		<!-- Hosting Cron Recommendation Banner -->
		<div style="background: #F0FDFA; border: 1px solid #99F6E4; border-radius: 8px; padding: 16px 20px; margin-bottom: 22px;">
			<h4 style="margin: 0 0 6px 0; color: #0F766E;">
				ℹ <?php esc_html_e( 'Reliable Background Execution via Hosting Crontab', 'eventflow' ); ?>
			</h4>
			<p class="ef-text-sm" style="margin: 0 0 10px 0; color: #134E4A;">
				<?php esc_html_e( 'WordPress built-in WP-Cron is traffic-triggered: it only runs when a visitor loads a page on your site. For high-volume Olympiad events or low-traffic portals, configure a real server cron job in your hosting control panel (cPanel, Plesk, or Linux crontab) for guaranteed scheduled execution:', 'eventflow' ); ?>
			</p>
			<code style="display: block; padding: 8px 12px; background: #FFFFFF; border: 1px solid #CCFBF1; border-radius: 4px; font-size: 12px; color: #0F766E;">
				* * * * * wget -q -O - https://portal.sibacom.tech/wp-cron.php?doing_wp_cron >/dev/null 2>&1
			</code>
		</div>

		<div class="ef-card">
			<div class="ef-card-header ef-flex-between">
				<div>
					<h3><?php esc_html_e( 'Queued Notification Items', 'eventflow' ); ?></h3>
					<p class="ef-card-subtitle"><?php esc_html_e( 'Showing the latest 50 background queue entries.', 'eventflow' ); ?></p>
				</div>
				<div style="display: flex; gap: 8px;">
					<button type="button" class="button button-primary" id="ef-process-queue-now-btn" style="background: #0D9488; border-color: #0F766E;">
						<span class="dashicons dashicons-update" style="vertical-align: middle;"></span> <?php esc_html_e( 'Process Queue Now', 'eventflow' ); ?>
					</button>
					<button type="button" class="button" id="ef-clear-completed-queue-btn">
						<?php esc_html_e( 'Clear Completed', 'eventflow' ); ?>
					</button>
				</div>
			</div>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Recipient', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Subject', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Template', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Status', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Attempts', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Scheduled At', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'eventflow' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $queue_items ) ) : ?>
						<tr>
							<td colspan="8" style="text-align: center; padding: 24px; color: #64748B;">
								<?php esc_html_e( 'The email queue is currently empty. All notifications have been processed.', 'eventflow' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $queue_items as $qi ) : ?>
							<tr>
								<td>
									#<?php echo esc_html( $qi->id ); ?>
									<?php if ( ! empty( $qi->dispatch_id ) ) : ?>
										<br><code class="ef-text-xs" style="font-size: 10px; color: #475569;"><?php echo esc_html( $qi->dispatch_id ); ?></code>
									<?php endif; ?>
								</td>
								<td>
									<strong><?php echo esc_html( $qi->recipient_email ); ?></strong>
									<?php if ( ! empty( $qi->recipient_name ) ) : ?>
										<br><span class="ef-text-xs ef-text-muted"><?php echo esc_html( $qi->recipient_name ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $qi->subject ); ?></td>
								<td>
									<code><?php echo esc_html( $qi->template_key ); ?></code>
									<?php if ( ! empty( $qi->workflow ) ) : ?>
										<br><span class="ef-badge ef-badge-neutral" style="font-size: 10px;"><?php echo esc_html( str_replace( '_', ' ', $qi->workflow ) ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( 'accepted' === $qi->status || 'sent' === $qi->status ) : ?>
										<span class="ef-badge ef-badge-success"><?php esc_html_e( 'Accepted by Transport', 'eventflow' ); ?></span>
									<?php elseif ( 'pending' === $qi->status ) : ?>
										<span class="ef-badge ef-badge-info"><?php esc_html_e( 'Queued', 'eventflow' ); ?></span>
									<?php elseif ( 'processing' === $qi->status ) : ?>
										<span class="ef-badge ef-badge-warning"><?php esc_html_e( 'Sending...', 'eventflow' ); ?></span>
									<?php elseif ( 'retrying' === $qi->status ) : ?>
										<span class="ef-badge ef-badge-warning"><?php esc_html_e( 'Retry Pending', 'eventflow' ); ?></span>
									<?php elseif ( 'failed' === $qi->status ) : ?>
										<span class="ef-badge ef-badge-danger"><?php esc_html_e( 'Failed', 'eventflow' ); ?></span>
									<?php else : ?>
										<span class="ef-badge ef-badge-gray"><?php echo esc_html( ucfirst( $qi->status ) ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $qi->attempts . ' / ' . $qi->max_attempts ); ?></td>
								<td><?php echo esc_html( $qi->scheduled_at ); ?></td>
								<td>
									<?php if ( in_array( $qi->status, array( 'failed', 'retrying' ), true ) ) : ?>
										<button type="button" class="button button-small ef-retry-queue-btn" data-id="<?php echo esc_attr( $qi->id ); ?>">
											<?php esc_html_e( 'Retry Now', 'eventflow' ); ?>
										</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<!-- =====================================================================
	     TAB 4: EMAIL LOGS
	     ===================================================================== -->
	<?php if ( 'logs' === $active_tab ) : ?>
		<div class="ef-card">
			<div class="ef-card-header ef-flex-between">
				<div>
					<h3><?php esc_html_e( 'Database Email Logs', 'eventflow' ); ?></h3>
					<p class="ef-card-subtitle"><?php esc_html_e( 'Audit trail of every automated email dispatch attempt, sender identity, and transport status.', 'eventflow' ); ?></p>
				</div>
				<div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display: inline;">
						<input type="hidden" name="eventflow_admin_action" value="export_email_logs">
						<?php wp_nonce_field( 'eventflow_export_email_logs_nonce' ); ?>
						<button type="submit" class="button">
							<span class="dashicons dashicons-download" style="vertical-align: middle;"></span> <?php esc_html_e( 'Export Logs (CSV)', 'eventflow' ); ?>
						</button>
					</form>
				</div>
			</div>

			<!-- Filters Bar -->
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
				<input type="hidden" name="page" value="eventflow-emails">
				<input type="hidden" name="tab" value="logs">

				<select name="log_status">
					<option value="all" <?php selected( $log_status_filter, 'all' ); ?>><?php esc_html_e( 'All Statuses', 'eventflow' ); ?></option>
					<option value="accepted" <?php selected( $log_status_filter, 'accepted' ); ?>><?php esc_html_e( 'Sent / Accepted', 'eventflow' ); ?></option>
					<option value="failed" <?php selected( $log_status_filter, 'failed' ); ?>><?php esc_html_e( 'Failed', 'eventflow' ); ?></option>
					<option value="queued" <?php selected( $log_status_filter, 'queued' ); ?>><?php esc_html_e( 'Queued', 'eventflow' ); ?></option>
					<option value="retrying" <?php selected( $log_status_filter, 'retrying' ); ?>><?php esc_html_e( 'Retry Pending', 'eventflow' ); ?></option>
					<option value="sending" <?php selected( $log_status_filter, 'sending' ); ?>><?php esc_html_e( 'Sending', 'eventflow' ); ?></option>
				</select>

				<select name="log_type">
					<option value="all" <?php selected( $log_type_filter, 'all' ); ?>><?php esc_html_e( 'All Notification Types', 'eventflow' ); ?></option>
					<?php foreach ( $templates as $t ) : ?>
						<option value="<?php echo esc_attr( $t->template_key ); ?>" <?php selected( $log_type_filter, $t->template_key ); ?>><?php echo esc_html( $t->name ); ?></option>
					<?php endforeach; ?>
				</select>

				<label class="ef-text-xs ef-text-muted" style="margin-right: -4px;"><?php esc_html_e( 'From:', 'eventflow' ); ?></label>
				<input type="date" name="filter_from" id="filter_from" class="filter_from" value="<?php echo esc_attr( $log_date_from ); ?>" title="<?php esc_attr_e( 'From Date', 'eventflow' ); ?>">

				<label class="ef-text-xs ef-text-muted" style="margin-right: -4px;"><?php esc_html_e( 'To:', 'eventflow' ); ?></label>
				<input type="date" name="filter_to" id="filter_to" class="filter_to" value="<?php echo esc_attr( $log_date_to ); ?>" title="<?php esc_attr_e( 'To Date', 'eventflow' ); ?>">

				<input type="search" name="log_search" value="<?php echo esc_attr( $log_search ); ?>" placeholder="<?php esc_attr_e( 'Search recipient, subject, reg ID...', 'eventflow' ); ?>" style="width: 220px;">

				<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter Logs', 'eventflow' ); ?></button>
				<?php if ( 'all' !== $log_status_filter || 'all' !== $log_type_filter || ! empty( $log_search ) || ! empty( $log_date_from ) || ! empty( $log_date_to ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-emails&tab=logs' ) ); ?>" class="button"><?php esc_html_e( 'Reset Filters', 'eventflow' ); ?></a>
				<?php endif; ?>
			</form>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Timestamp', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Recipient', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Recipient Type', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Subject', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Notification Type', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Reg ID', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Status', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Retries', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'eventflow' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $email_logs ) ) : ?>
						<tr>
							<td colspan="10" style="text-align: center; padding: 24px; color: #64748B;">
								<?php esc_html_e( 'No email dispatch logs matching your filter criteria.', 'eventflow' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $email_logs as $el ) : ?>
							<tr>
								<td>
									#<?php echo esc_html( $el->id ); ?>
									<?php if ( ! empty( $el->dispatch_id ) ) : ?>
										<br><code class="ef-text-xs" style="font-size: 10px; color: #475569;"><?php echo esc_html( $el->dispatch_id ); ?></code>
									<?php endif; ?>
								</td>
								<td class="ef-text-xs"><?php echo esc_html( $el->sent_at ); ?></td>
								<td>
									<strong><?php echo esc_html( $el->recipient_email ); ?></strong>
									<?php if ( ! empty( $el->recipient_name ) ) : ?>
										<br><span class="ef-text-xs ef-text-muted"><?php echo esc_html( $el->recipient_name ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php
									$rt = ! empty( $el->recipient_type ) ? strtolower( $el->recipient_type ) : 'solo';
									if ( 'leader' === $rt ) {
										echo '<span class="ef-badge ef-badge-primary" style="font-size: 11px;">' . esc_html__( 'Leader', 'eventflow' ) . '</span>';
									} elseif ( 'member' === $rt ) {
										echo '<span class="ef-badge ef-badge-info" style="font-size: 11px;">' . esc_html__( 'Member', 'eventflow' ) . '</span>';
									} elseif ( 'admin' === $rt ) {
										echo '<span class="ef-badge ef-badge-warning" style="font-size: 11px;">' . esc_html__( 'Admin', 'eventflow' ) . '</span>';
									} else {
										echo '<span class="ef-badge ef-badge-neutral" style="font-size: 11px;">' . esc_html__( 'Solo', 'eventflow' ) . '</span>';
									}
									?>
								</td>
								<td><?php echo esc_html( $el->subject ); ?></td>
								<td>
									<code><?php echo esc_html( $el->template_key ); ?></code>
									<?php if ( ! empty( $el->workflow ) ) : ?>
										<br><span class="ef-badge ef-badge-neutral" style="font-size: 10px;"><?php echo esc_html( str_replace( '_', ' ', $el->workflow ) ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( ! empty( $el->registration_id ) ) : ?>
										<span class="ef-badge ef-badge-info"><?php echo esc_html( $el->registration_id ); ?></span>
									<?php else : ?>
										<span class="ef-text-muted">&mdash;</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( 'accepted' === $el->status || 'sent' === $el->status ) : ?>
										<span class="ef-badge ef-badge-success badge-sent" title="<?php esc_attr_e( 'Dispatched via WordPress native mail transport', 'eventflow' ); ?>">
											<?php esc_html_e( 'Sent / Accepted', 'eventflow' ); ?>
										</span>
									<?php elseif ( 'failed' === $el->status ) : ?>
										<span class="ef-badge ef-badge-danger badge-failed" title="<?php echo esc_attr( ! empty( $el->error_message ) ? $el->error_message : 'Delivery attempt failed' ); ?>">
											<?php esc_html_e( 'Failed', 'eventflow' ); ?>
										</span>
										<?php if ( ! empty( $el->error_message ) ) : ?>
											<div class="ef-text-xs ef-text-danger" style="margin-top: 3px; max-width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo esc_attr( $el->error_message ); ?>">
												<?php echo esc_html( $el->error_message ); ?>
											</div>
										<?php endif; ?>
									<?php elseif ( 'queued' === $el->status ) : ?>
										<span class="ef-badge ef-badge-info"><?php esc_html_e( 'Queued', 'eventflow' ); ?></span>
									<?php elseif ( 'retrying' === $el->status ) : ?>
										<span class="ef-badge ef-badge-warning"><?php esc_html_e( 'Retry Pending', 'eventflow' ); ?></span>
									<?php elseif ( 'sending' === $el->status ) : ?>
										<span class="ef-badge ef-badge-warning"><?php esc_html_e( 'Sending', 'eventflow' ); ?></span>
									<?php else : ?>
										<span class="ef-badge ef-badge-gray"><?php echo esc_html( ucfirst( $el->status ) ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( isset( $el->retry_count ) ? $el->retry_count : 0 ); ?></td>
								<td>
									<div style="display: flex; gap: 6px;">
										<button type="button" class="button button-small ef-view-email-log-btn" data-id="<?php echo esc_attr( $el->id ); ?>" title="<?php esc_attr_e( 'Inspect Email Message', 'eventflow' ); ?>">
											<span class="dashicons dashicons-visibility" style="vertical-align: middle; font-size: 14px; width: 14px; height: 14px;"></span>
										</button>
										<?php if ( 'failed' === $el->status ) : ?>
											<button type="button" class="button button-small ef-retry-email-log-btn" data-id="<?php echo esc_attr( $el->id ); ?>">
												<?php esc_html_e( 'Retry', 'eventflow' ); ?>
											</button>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $total_log_pages > 1 ) : ?>
				<div class="tablenav" style="margin-top: 16px;">
					<div class="tablenav-pages">
						<span class="displaying-num"><?php printf( esc_html__( '%s items', 'eventflow' ), number_format_i18n( $total_logs ) ); ?></span>
						<?php
						echo paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => $total_log_pages,
							'current'   => $log_page,
						) );
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<!-- =====================================================================
	     TAB 5: DELIVERY DIAGNOSTICS & DELIVERABILITY (SPF, DKIM, DMARC)
	     ===================================================================== -->
	<?php if ( 'diagnostics' === $active_tab ) : ?>
		<div class="ef-grid ef-grid-3">
			<div class="ef-card" style="grid-column: span 2;">
				<div class="ef-card-header">
					<h3><?php esc_html_e( 'System & Hosting Mail Transport Diagnostics', 'eventflow' ); ?></h3>
					<p class="ef-card-subtitle">
						<?php esc_html_e( 'Native wp_mail() relies on your server\'s configured mail transport. These checks verify that your server is properly equipped to transmit messages.', 'eventflow' ); ?>
					</p>
				</div>

				<!-- Important Transport Notice -->
				<div style="background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 8px; padding: 14px 18px; margin-bottom: 22px;">
					<strong style="color: #92400E; display: block; margin-bottom: 4px;">
						⚠ <?php esc_html_e( 'Mail Transport Hand-off Notice:', 'eventflow' ); ?>
					</strong>
					<p class="ef-text-sm" style="margin: 0; color: #78350F;">
						<?php esc_html_e( 'A successful wp_mail() result or wp_mail_succeeded event confirms that WordPress handed the message to your hosting server\'s configured mail transport. It does not independently guarantee recipient inbox placement. Inbox delivery requires valid server SPF, DKIM, and DMARC alignment on your domain.', 'eventflow' ); ?>
					</p>
				</div>

				<!-- System Checks Table -->
				<table class="widefat striped" style="margin-bottom: 24px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Environment Component', 'eventflow' ); ?></th>
							<th><?php esc_html_e( 'Detected Value', 'eventflow' ); ?></th>
							<th><?php esc_html_e( 'Transport Status', 'eventflow' ); ?></th>
							<th><?php esc_html_e( 'Details', 'eventflow' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $diagnostics['system'] as $sys_check ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $sys_check['label'] ); ?></strong></td>
								<td><code><?php echo esc_html( $sys_check['value'] ); ?></code></td>
								<td>
									<span class="ef-badge <?php echo 'pass' === $sys_check['status'] ? 'ef-badge-success' : ( 'fail' === $sys_check['status'] ? 'ef-badge-danger' : 'ef-badge-warning' ); ?>">
										<?php echo 'pass' === $sys_check['status'] ? esc_html__( 'Passed', 'eventflow' ) : ( 'fail' === $sys_check['status'] ? esc_html__( 'Failed', 'eventflow' ) : esc_html__( 'Notice', 'eventflow' ) ); ?>
									</span>
								</td>
								<td class="ef-text-sm"><?php echo esc_html( $sys_check['message'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<!-- Domain DNS Deliverability Checks -->
				<div class="ef-card-header" style="padding: 0 0 10px 0;">
					<h3><?php esc_html_e( 'Domain Authentication & DNS Inspection', 'eventflow' ); ?></h3>
					<p class="ef-card-subtitle">
						<?php printf( esc_html__( 'Live DNS verification for sender domain: %s', 'eventflow' ), '<code>portal.sibacom.tech</code>' ); ?>
					</p>
				</div>

				<!-- 1. A Record -->
				<div class="ef-diagnostic-card" style="border: 1px solid <?php echo 'pass' === $diagnostics['dns']['a_record']['status'] ? '#A7F3D0' : '#FDE68A'; ?>; background: <?php echo 'pass' === $diagnostics['dns']['a_record']['status'] ? '#ECFDF5' : '#FFFBEB'; ?>; border-radius: 8px; padding: 16px; margin-bottom: 14px;">
					<div class="ef-flex-between">
						<div>
							<h4 style="margin: 0 0 4px 0; color: #0F172A;">
								<?php echo 'pass' === $diagnostics['dns']['a_record']['status'] ? '✓ ' : '⚠ '; ?>
								<?php esc_html_e( 'Domain Resolution (A Record)', 'eventflow' ); ?>
							</h4>
							<div class="ef-text-sm"><?php echo esc_html( $diagnostics['dns']['a_record']['message'] ); ?></div>
						</div>
						<span class="ef-badge <?php echo 'pass' === $diagnostics['dns']['a_record']['status'] ? 'ef-badge-success' : 'ef-badge-warning'; ?>">
							<?php echo 'pass' === $diagnostics['dns']['a_record']['status'] ? esc_html__( 'Resolving', 'eventflow' ) : esc_html__( 'Pending DNS', 'eventflow' ); ?>
						</span>
					</div>
				</div>

				<!-- 2. MX Records -->
				<div class="ef-diagnostic-card" style="border: 1px solid <?php echo 'pass' === $diagnostics['dns']['mx']['status'] ? '#A7F3D0' : '#FDE68A'; ?>; background: <?php echo 'pass' === $diagnostics['dns']['mx']['status'] ? '#ECFDF5' : '#FFFBEB'; ?>; border-radius: 8px; padding: 16px; margin-bottom: 14px;">
					<div class="ef-flex-between">
						<div>
							<h4 style="margin: 0 0 4px 0; color: #0F172A;">
								<?php echo 'pass' === $diagnostics['dns']['mx']['status'] ? '✓ ' : '⚠ '; ?>
								<?php esc_html_e( 'Mail Exchange (MX) & Inbound Mailbox', 'eventflow' ); ?>
							</h4>
							<div class="ef-text-sm"><?php echo esc_html( $diagnostics['dns']['mx']['message'] ); ?></div>
						</div>
						<span class="ef-badge <?php echo 'pass' === $diagnostics['dns']['mx']['status'] ? 'ef-badge-success' : 'ef-badge-warning'; ?>">
							<?php echo 'pass' === $diagnostics['dns']['mx']['status'] ? esc_html__( 'Found', 'eventflow' ) : esc_html__( 'Action Required', 'eventflow' ); ?>
						</span>
					</div>
				</div>

				<!-- 3. SPF Check -->
				<div class="ef-diagnostic-card" style="border: 1px solid <?php echo 'pass' === $diagnostics['dns']['spf']['status'] ? '#A7F3D0' : '#FDE68A'; ?>; background: <?php echo 'pass' === $diagnostics['dns']['spf']['status'] ? '#ECFDF5' : '#FFFBEB'; ?>; border-radius: 8px; padding: 16px; margin-bottom: 14px;">
					<div class="ef-flex-between">
						<div>
							<h4 style="margin: 0 0 4px 0; color: #0F172A;">
								<?php echo 'pass' === $diagnostics['dns']['spf']['status'] ? '✓ ' : '⚠ '; ?>
								<?php esc_html_e( 'SPF (Sender Policy Framework)', 'eventflow' ); ?>
							</h4>
							<div class="ef-text-sm"><?php echo wp_kses_post( $diagnostics['dns']['spf']['message'] ); ?></div>
						</div>
						<span class="ef-badge <?php echo 'pass' === $diagnostics['dns']['spf']['status'] ? 'ef-badge-success' : 'ef-badge-warning'; ?>">
							<?php echo 'pass' === $diagnostics['dns']['spf']['status'] ? esc_html__( 'Configured', 'eventflow' ) : esc_html__( 'Action Required', 'eventflow' ); ?>
						</span>
					</div>
				</div>

				<!-- 4. DMARC Check -->
				<div class="ef-diagnostic-card" style="border: 1px solid <?php echo 'pass' === $diagnostics['dns']['dmarc']['status'] ? '#A7F3D0' : '#FDE68A'; ?>; background: <?php echo 'pass' === $diagnostics['dns']['dmarc']['status'] ? '#ECFDF5' : '#FFFBEB'; ?>; border-radius: 8px; padding: 16px; margin-bottom: 14px;">
					<div class="ef-flex-between">
						<div>
							<h4 style="margin: 0 0 4px 0; color: #0F172A;">
								<?php echo 'pass' === $diagnostics['dns']['dmarc']['status'] ? '✓ ' : '⚠ '; ?>
								<?php esc_html_e( 'DMARC Policy Compliance', 'eventflow' ); ?>
							</h4>
							<div class="ef-text-sm"><?php echo wp_kses_post( $diagnostics['dns']['dmarc']['message'] ); ?></div>
						</div>
						<span class="ef-badge <?php echo 'pass' === $diagnostics['dns']['dmarc']['status'] ? 'ef-badge-success' : 'ef-badge-warning'; ?>">
							<?php echo 'pass' === $diagnostics['dns']['dmarc']['status'] ? esc_html__( 'Configured', 'eventflow' ) : esc_html__( 'Action Required', 'eventflow' ); ?>
						</span>
					</div>
				</div>

				<!-- 5. DKIM Guidance -->
				<div class="ef-diagnostic-card" style="border: 1px solid #CCFBF1; background: #F0FDFA; border-radius: 8px; padding: 16px;">
					<h4 style="margin: 0 0 6px 0; color: #0F766E;">
						ℹ <?php esc_html_e( 'DKIM (DomainKeys Identified Mail) Guidance', 'eventflow' ); ?>
					</h4>
					<p class="ef-text-sm" style="margin: 0; color: #134E4A;">
						<?php echo esc_html( $diagnostics['dns']['dkim_guidance']['message'] ); ?>
					</p>
				</div>
			</div>

			<!-- Diagnostics Sidebar -->
			<div>
				<div class="ef-card" style="margin-bottom: 24px;">
					<div class="ef-card-header">
						<h3><?php esc_html_e( 'Transport Summary', 'eventflow' ); ?></h3>
					</div>
					<ul class="ef-detail-list">
						<li><strong><?php esc_html_e( 'Sender Identity:', 'eventflow' ); ?></strong> <span><code><?php echo esc_html( EventFlow_Email_Manager::get_sender_email() ); ?></code></span></li>
						<li><strong><?php esc_html_e( 'Sender Name:', 'eventflow' ); ?></strong> <span><?php echo esc_html( EventFlow_Email_Manager::get_sender_name() ); ?></span></li>
						<li><strong><?php esc_html_e( 'Last Test Dispatch:', 'eventflow' ); ?></strong> <span class="ef-text-xs"><?php echo esc_html( $diagnostics['transport_history']['last_test_attempt'] ); ?></span></li>
						<li><strong><?php esc_html_e( 'Last Success:', 'eventflow' ); ?></strong> <span class="ef-text-xs"><?php echo esc_html( $diagnostics['transport_history']['last_success_time'] ); ?></span></li>
						<li><strong><?php esc_html_e( 'Last Failure:', 'eventflow' ); ?></strong> <span class="ef-text-xs"><?php echo esc_html( $diagnostics['transport_history']['last_failure_time'] ); ?></span></li>
					</ul>
					<?php if ( ! empty( $diagnostics['transport_history']['last_error_message'] ) && 'None' !== $diagnostics['transport_history']['last_error_message'] ) : ?>
						<div style="margin-top: 12px; padding: 10px 12px; background: #FEF2F2; border: 1px solid #FEE2E2; border-radius: 6px;">
							<strong class="ef-text-xs" style="color: #991B1B; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Last Error Details:', 'eventflow' ); ?></strong>
							<code style="font-size: 11.5px; color: #DC2626; word-break: break-all;"><?php echo esc_html( $diagnostics['transport_history']['last_error_message'] ); ?></code>
						</div>
					<?php endif; ?>
				</div>

				<div class="ef-card">
					<div class="ef-card-header">
						<h3><?php esc_html_e( 'Delivery Statistics', 'eventflow' ); ?></h3>
					</div>
					<ul class="ef-detail-list">
						<li><strong><?php esc_html_e( 'Total Dispatches:', 'eventflow' ); ?></strong> <span><?php echo esc_html( $diagnostics['stats']['total'] ); ?></span></li>
						<li><strong><?php esc_html_e( 'Accepted:', 'eventflow' ); ?></strong> <span style="color: #0D9488; font-weight: 700;"><?php echo esc_html( $diagnostics['stats']['accepted'] ); ?></span></li>
						<li><strong><?php esc_html_e( 'Failed:', 'eventflow' ); ?></strong> <span style="color: #DC2626; font-weight: 700;"><?php echo esc_html( $diagnostics['stats']['failed'] ); ?></span></li>
						<li><strong><?php esc_html_e( 'Acceptance Rate:', 'eventflow' ); ?></strong> <span><?php echo esc_html( $diagnostics['stats']['delivery_rate'] ); ?>%</span></li>
					</ul>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<!-- =====================================================================
	     MODAL: SEND TEST EMAIL
	     ===================================================================== -->
	<div id="ef-test-email-modal" class="ef-modal" aria-hidden="true" style="display: none;">
		<div class="ef-modal-backdrop"></div>
		<div class="ef-modal-dialog" style="max-width: 580px;">
			<div class="ef-modal-header ef-flex-between">
				<h3><?php esc_html_e( 'Send Real Test Email', 'eventflow' ); ?></h3>
				<button type="button" class="ef-modal-close" data-dismiss="modal">&times;</button>
			</div>
			<div class="ef-modal-body">
				<p class="ef-text-sm ef-text-muted">
					<?php esc_html_e( 'Generates a real test notification using WordPress\'s native wp_mail() with fixed sender info@portal.sibacom.tech.', 'eventflow' ); ?>
				</p>

				<div class="ef-form-group">
					<label for="ef-modal-test-recipient"><?php esc_html_e( 'Recipient Email Address', 'eventflow' ); ?> <span class="ef-req">*</span></label>
					<input type="email" id="ef-modal-test-recipient" class="ef-input" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required placeholder="recipient@domain.com">
				</div>

				<div class="ef-form-group">
					<label for="ef-modal-test-template"><?php esc_html_e( 'Notification Trigger Template to Test', 'eventflow' ); ?></label>
					<select id="ef-modal-test-template" class="ef-input">
						<option value="test_email"><?php esc_html_e( 'Administrative Test Notification', 'eventflow' ); ?></option>
						<?php foreach ( $templates as $tpl ) : ?>
							<option value="<?php echo esc_attr( $tpl->template_key ); ?>"><?php echo esc_html( $tpl->name ); ?> (<?php echo esc_html( $tpl->template_key ); ?>)</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div id="ef-modal-test-result" style="display: none; margin-top: 16px; padding: 14px; border-radius: 6px; font-size: 13px; line-height: 1.5;"></div>
			</div>
			<div class="ef-modal-footer" style="display: flex; justify-content: flex-end; gap: 10px;">
				<button type="button" class="button" data-dismiss="modal"><?php esc_html_e( 'Close', 'eventflow' ); ?></button>
				<button type="button" class="button button-primary" id="ef-modal-execute-test-btn" style="background: #0D9488; border-color: #0F766E;">
					<?php esc_html_e( 'Dispatch Test Email', 'eventflow' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- =====================================================================
	     MODAL: VIEW FULL EMAIL LOG
	     ===================================================================== -->
	<div id="ef-view-log-modal" class="ef-modal" aria-hidden="true" style="display: none;">
		<div class="ef-modal-backdrop"></div>
		<div class="ef-modal-dialog" style="max-width: 780px;">
			<div class="ef-modal-header ef-flex-between">
				<h3 id="ef-log-modal-title"><?php esc_html_e( 'Email Message Inspector', 'eventflow' ); ?></h3>
				<button type="button" class="ef-modal-close" data-dismiss="modal">&times;</button>
			</div>
			<div class="ef-modal-body">
				<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; font-size: 13px;">
					<div><strong><?php esc_html_e( 'To:', 'eventflow' ); ?></strong> <span id="ef-log-modal-to"></span></div>
					<div><strong><?php esc_html_e( 'From:', 'eventflow' ); ?></strong> <span id="ef-log-modal-from"></span></div>
					<div><strong><?php esc_html_e( 'Subject:', 'eventflow' ); ?></strong> <span id="ef-log-modal-subject"></span></div>
					<div><strong><?php esc_html_e( 'Trigger Key:', 'eventflow' ); ?></strong> <code id="ef-log-modal-key"></code></div>
					<div><strong><?php esc_html_e( 'Dispatched:', 'eventflow' ); ?></strong> <span id="ef-log-modal-date"></span></div>
					<div><strong><?php esc_html_e( 'Status:', 'eventflow' ); ?></strong> <span id="ef-log-modal-status"></span></div>
					<div id="ef-log-modal-resp-row"><strong><?php esc_html_e( 'Transport Response:', 'eventflow' ); ?></strong> <span id="ef-log-modal-resp"></span></div>
				</div>

				<h4><?php esc_html_e( 'Rendered HTML Body:', 'eventflow' ); ?></h4>
				<div id="ef-log-modal-body" style="border: 1px solid #CBD5E1; border-radius: 8px; max-height: 380px; overflow-y: auto; padding: 16px; background: #FFFFFF;"></div>
			</div>
			<div class="ef-modal-footer">
				<button type="button" class="button" data-dismiss="modal"><?php esc_html_e( 'Close', 'eventflow' ); ?></button>
			</div>
		</div>
	</div>

	<!-- =====================================================================
	     MODAL: LIVE TEMPLATE PREVIEW
	     ===================================================================== -->
	<div id="ef-preview-tpl-modal" class="ef-modal" aria-hidden="true" style="display: none;">
		<div class="ef-modal-backdrop"></div>
		<div class="ef-modal-dialog" style="max-width: 740px;">
			<div class="ef-modal-header ef-flex-between">
				<h3><?php esc_html_e( 'Live Template Preview', 'eventflow' ); ?></h3>
				<button type="button" class="ef-modal-close" data-dismiss="modal">&times;</button>
			</div>
			<div class="ef-modal-body">
				<div style="margin-bottom: 12px; font-size: 13px; color: #64748B;">
					<strong><?php esc_html_e( 'Subject Preview:', 'eventflow' ); ?></strong> <span id="ef-preview-subject-display" style="color: #0F172A; font-weight: 600;"></span>
				</div>
				<div id="ef-preview-html-frame" style="border: 1px solid #E2E8F0; border-radius: 8px; max-height: 440px; overflow-y: auto; background: #F1F5F9; padding: 12px;"></div>
			</div>
			<div class="ef-modal-footer">
				<button type="button" class="button" data-dismiss="modal"><?php esc_html_e( 'Close Preview', 'eventflow' ); ?></button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	var staffNonce = '<?php echo esc_js( wp_create_nonce( 'eventflow_staff_nonce' ) ); ?>';

	// Modal Helpers
	function openModal(id) {
		$('#' + id).fadeIn(150);
	}
	function closeModal(id) {
		$('#' + id).fadeOut(150);
	}
	$(document).on('click', '[data-dismiss="modal"], .ef-modal-backdrop', function() {
		$(this).closest('.ef-modal').fadeOut(150);
	});

	// Quick Test Modal Openers
	$('#ef-open-quick-test-btn').on('click', function() {
		$('#ef-modal-test-result').hide();
		openModal('ef-test-email-modal');
	});

	// Execute Test Email (from Modal)
	$('#ef-modal-execute-test-btn').on('click', function() {
		var btn = $(this);
		var email = $('#ef-modal-test-recipient').val().trim();
		var tpl   = $('#ef-modal-test-template').val();
		var resBox = $('#ef-modal-test-result');

		if (!email) {
			alert('Please enter a recipient email.');
			return;
		}

		btn.prop('disabled', true).text('Dispatching wp_mail()...');
		resBox.hide().removeClass('notice-success notice-error');

		$.post(ajaxurl, {
			action: 'eventflow_send_test_email',
			target_email: email,
			template_key: tpl,
			nonce: staffNonce
		}, function(resp) {
			btn.prop('disabled', false).text('Dispatch Test Email');
			resBox.show();
			if (resp.success) {
				resBox.css({'background': '#ECFDF5', 'border': '1px solid #A7F3D0', 'color': '#065F46'}).html('<strong>✓ Accepted:</strong> ' + resp.data.message);
			} else {
				resBox.css({'background': '#FEF2F2', 'border': '1px solid #FECACA', 'color': '#991B1B'}).html('<strong>✗ Failed:</strong> ' + (resp.data.message || 'Server mail transport could not send email.'));
			}
		}).fail(function() {
			btn.prop('disabled', false).text('Dispatch Test Email');
			resBox.show().css({'background': '#FEF2F2', 'border': '1px solid #FECACA', 'color': '#991B1B'}).html('<strong>✗ Network Error:</strong> AJAX request failed.');
		});
	});

	// Execute Quick Test Email (from Settings sidebar)
	$('#ef-quick-test-submit-btn').on('click', function() {
		var btn = $(this);
		var email = $('#ef-quick-test-email').val().trim();
		var tpl   = $('#ef-quick-test-template').val();
		var resBox = $('#ef-quick-test-result');

		if (!email) {
			alert('Please enter a valid recipient email.');
			return;
		}

		btn.prop('disabled', true).text('Dispatching wp_mail()...');
		resBox.hide();

		$.post(ajaxurl, {
			action: 'eventflow_send_test_email',
			target_email: email,
			template_key: tpl,
			nonce: staffNonce
		}, function(resp) {
			btn.prop('disabled', false).text('Send Real Test Email');
			resBox.show();
			if (resp.success) {
				resBox.css({'background': '#ECFDF5', 'border': '1px solid #A7F3D0', 'color': '#065F46'}).html('<strong>✓ Accepted:</strong> ' + resp.data.message);
			} else {
				resBox.css({'background': '#FEF2F2', 'border': '1px solid #FECACA', 'color': '#991B1B'}).html('<strong>✗ Failed:</strong> ' + (resp.data.message || 'Server mail transport failure.'));
			}
		}).fail(function() {
			btn.prop('disabled', false).text('Send Real Test Email');
			resBox.show().css({'background': '#FEF2F2', 'border': '1px solid #FECACA', 'color': '#991B1B'}).html('<strong>✗ Server Error:</strong> Request failed.');
		});
	});

	// Insert Dynamic Placeholder into Template Editor
	$('.ef-insert-ph-btn').on('click', function() {
		var ph = $(this).data('ph');
		var txtArea = document.getElementById('ef-tpl-body');
		if (txtArea) {
			var start = txtArea.selectionStart;
			var end   = txtArea.selectionEnd;
			var text  = txtArea.value;
			txtArea.value = text.substring(0, start) + ph + text.substring(end);
			txtArea.focus();
			txtArea.selectionStart = txtArea.selectionEnd = start + ph.length;
		}
	});

	// Live Preview Template
	$('.ef-preview-template-btn').on('click', function() {
		var id = $(this).data('id');
		var subj = $('#ef-tpl-subject').val();
		var body = $('#ef-tpl-body').val();

		$.post(ajaxurl, {
			action: 'eventflow_preview_email_template',
			template_id: id,
			subject: subj,
			body: body,
			nonce: staffNonce
		}, function(resp) {
			if (resp.success) {
				$('#ef-preview-subject-display').text(resp.data.subject);
				$('#ef-preview-html-frame').html(resp.data.html);
				openModal('ef-preview-tpl-modal');
			}
		});
	});

	// Reset Template
	$('.ef-reset-template-btn').on('click', function() {
		var id = $(this).data('id');
		if (!confirm('Are you sure you want to reset this template back to its default SIBACOM EVENT content?')) {
			return;
		}

		$.post(ajaxurl, {
			action: 'eventflow_reset_email_template',
			template_id: id,
			nonce: staffNonce
		}, function(resp) {
			if (resp.success) {
				window.location.reload();
			} else {
				alert(resp.data.message || 'Failed to reset template.');
			}
		});
	});

	// Process Queue Now Button
	$('#ef-process-queue-now-btn').on('click', function() {
		var btn = $(this);
		btn.prop('disabled', true).text('Processing Batch...');

		$.post(ajaxurl, {
			action: 'eventflow_process_email_queue',
			batch_size: 25,
			nonce: staffNonce
		}, function(resp) {
			btn.prop('disabled', false).text('Process Queue Now');
			if (resp.success) {
				alert(resp.data.message);
				window.location.reload();
			} else {
				alert(resp.data.message || 'Failed to process queue.');
			}
		});
	});

	// Clear Completed Queue
	$('#ef-clear-completed-queue-btn').on('click', function() {
		if (!confirm('Clear all sent and cancelled queue items?')) {
			return;
		}

		$.post(ajaxurl, {
			action: 'eventflow_clear_completed_queue',
			nonce: staffNonce
		}, function(resp) {
			if (resp.success) {
				window.location.reload();
			}
		});
	});

	// Retry Single Queue Item
	$(document).on('click', '.ef-retry-queue-btn', function() {
		var btn = $(this);
		var qid = btn.data('id');
		btn.prop('disabled', true).text('Retrying...');

		$.post(ajaxurl, {
			action: 'eventflow_retry_queue_item',
			queue_id: qid,
			nonce: staffNonce
		}, function(resp) {
			if (resp.success) {
				window.location.reload();
			} else {
				alert(resp.data.message);
				btn.prop('disabled', false).text('Retry Now');
			}
		});
	});

	// Inspect Email Log Modal
	$(document).on('click', '.ef-view-email-log-btn', function() {
		var id = $(this).data('id');

		$.post(ajaxurl, {
			action: 'eventflow_get_email_log_preview',
			log_id: id,
			nonce: staffNonce
		}, function(resp) {
			if (resp.success) {
				var data = resp.data;
				$('#ef-log-modal-to').text(data.recipient_email + (data.recipient_name ? ' (' + data.recipient_name + ')' : ''));
				$('#ef-log-modal-from').text(data.sender_email || 'info@portal.sibacom.tech');
				$('#ef-log-modal-subject').text(data.subject);
				$('#ef-log-modal-key').text(data.template_key);
				$('#ef-log-modal-date').text(data.sent_at);
				$('#ef-log-modal-status').text(data.status);
				$('#ef-log-modal-resp').text(data.provider_response || (data.error_message ? data.error_message : 'Dispatched'));
				$('#ef-log-modal-body').html(data.body);
				openModal('ef-view-log-modal');
			}
		});
	});

	// Retry Failed Email Log
	$(document).on('click', '.ef-retry-email-log-btn', function() {
		var btn = $(this);
		var logId = btn.data('id');
		btn.prop('disabled', true).text('Retrying...');

		$.post(ajaxurl, {
			action: 'eventflow_retry_email_log',
			log_id: logId,
			nonce: staffNonce
		}, function(resp) {
			if (resp.success) {
				alert(resp.data.message);
				window.location.reload();
			} else {
				alert(resp.data.message || 'Retry failed.');
				btn.prop('disabled', false).text('Retry');
			}
		});
	});

	// Toggle SMTP Password Visibility
	$('#ef-toggle-smtp-pass').on('click', function() {
		var passInput = $('#ef_smtp_password');
		var icon = $(this).find('.dashicons');
		if (passInput.attr('type') === 'password') {
			passInput.attr('type', 'text');
			icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
		} else {
			passInput.attr('type', 'password');
			icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
		}
	});

	// Toggle SMTP Auth Fields
	$('#ef_smtp_auth').on('change', function() {
		if ($(this).is(':checked')) {
			$('#ef_smtp_auth_fields').slideDown(150);
		} else {
			$('#ef_smtp_auth_fields').slideUp(150);
		}
	});

	// Live SMTP Connection Test
	$('#ef-test-smtp-btn').on('click', function() {
		var btn = $(this);
		var resBox = $('#ef-smtp-test-result');
		var host = $('#ef_smtp_host').val().trim();
		var port = $('#ef_smtp_port').val().trim();
		var enc  = $('#ef_smtp_encryption').val();
		var auth = $('#ef_smtp_auth').is(':checked') ? 'yes' : 'no';
		var user = $('#ef_smtp_username').val().trim();
		var pass = $('#ef_smtp_password').val();
		var timeout = $('#ef_smtp_timeout').val() || 10;

		if (!host) {
			alert('Please enter an SMTP Host domain/IP.');
			return;
		}

		btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin" style="vertical-align: middle;"></span> Testing Connection...');
		resBox.show().css({'background': '#F1F5F9', 'border': '1px solid #CBD5E1', 'color': '#334155'}).html('<em>Probing socket connection to ' + host + ':' + port + '...</em>');

		$.post(ajaxurl, {
			action: 'eventflow_smtp_test_connection',
			host: host,
			port: port,
			encryption: enc,
			auth: auth,
			username: user,
			password: pass,
			timeout: timeout,
			nonce: staffNonce
		}, function(resp) {
			btn.prop('disabled', false).html('<span class="dashicons dashicons-networking" style="vertical-align: middle;"></span> Test SMTP Connection Now');
			var data = resp.data || {};
			var html = '';

			if (resp.success) {
				html += '<div style="color: #065F46; font-weight: 700; margin-bottom: 8px;">✓ ' + (data.message || 'SMTP Connection & Handshake Succeeded!') + '</div>';
			} else {
				html += '<div style="color: #991B1B; font-weight: 700; margin-bottom: 8px;">✗ ' + (data.message || 'Connection Test Failed') + '</div>';
			}

			if (data.steps && data.steps.length > 0) {
				html += '<ul style="margin: 8px 0 0 0; padding: 0; list-style: none; font-size: 12px;">';
				for (var i = 0; i < data.steps.length; i++) {
					var s = data.steps[i];
					var isPass = s.status === 'pass';
					html += '<li style="margin-bottom: 4px; padding: 4px 8px; border-radius: 4px; background: ' + (isPass ? '#ECFDF5' : '#FEF2F2') + '; color: ' + (isPass ? '#065F46' : '#991B1B') + ';">';
					html += '<strong>' + (isPass ? '✓' : '✗') + ' ' + s.step + ':</strong> ' + s.message;
					html += '</li>';
				}
				html += '</ul>';
			}

			if (data.guidance) {
				html += '<div style="margin-top: 10px; padding: 8px; background: #FFFBEB; border: 1px solid #FCD34D; border-radius: 4px; color: #92400E; font-size: 11px;">';
				html += '<strong>Guidance:</strong> ' + data.guidance;
				html += '</div>';
			}

			resBox.html(html);
			if (resp.success) {
				resBox.css({'background': '#ECFDF5', 'border': '1px solid #A7F3D0'});
			} else {
				resBox.css({'background': '#FEF2F2', 'border': '1px solid #FECACA'});
			}
		}).fail(function() {
			btn.prop('disabled', false).html('<span class="dashicons dashicons-networking" style="vertical-align: middle;"></span> Test SMTP Connection Now');
			resBox.show().css({'background': '#FEF2F2', 'border': '1px solid #FECACA', 'color': '#991B1B'}).html('<strong>✗ AJAX Network Error:</strong> Could not reach admin-ajax.php.');
		});
	});
});
</script>
