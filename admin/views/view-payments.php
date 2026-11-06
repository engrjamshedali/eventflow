<?php
/**
 * EventFlow Admin View: Payment Verification & Audit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pay_search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$pay_status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'under_verification';
$page       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

$payments_data = EventFlow_Payment_Verification::get_payments(
	array(
		'search'   => $pay_search,
		'status'   => $pay_status,
		'page'     => $page,
		'per_page' => 15,
	)
);

$event = EventFlow_Event_Manager::get_active_event();
$currency = $event ? esc_html( $event->currency ) : 'PKR';
$pay_counts = EventFlow_Payment_Verification::get_payment_counts( $event ? $event->id : 0 );
?>

<div class="wrap ef-admin-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Payment Verification & Audit', 'eventflow' ); ?></h1>
	<hr class="wp-header-end">

	<!-- 5 KPI Summary Cards -->
	<div class="ef-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 20px;">
		<!-- Pending Queue -->
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-payments&status=under_verification' ) ); ?>" class="ef-kpi-card" style="text-decoration:none;">
			<div class="ef-kpi-card-top">
				<span class="ef-kpi-title"><?php esc_html_e( 'Pending Verification', 'eventflow' ); ?></span>
				<div class="ef-kpi-icon-box ef-kpi-icon-amber">
					<span class="dashicons dashicons-clock"></span>
				</div>
			</div>
			<div class="ef-kpi-value" style="color: #D97706;"><?php echo esc_html( $pay_counts['pending'] ); ?></div>
			<div class="ef-kpi-sub"><?php esc_html_e( 'Awaiting review queue', 'eventflow' ); ?></div>
		</a>

		<!-- Verified & Approved -->
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-payments&status=verified' ) ); ?>" class="ef-kpi-card" style="text-decoration:none;">
			<div class="ef-kpi-card-top">
				<span class="ef-kpi-title"><?php esc_html_e( 'Approved Payments', 'eventflow' ); ?></span>
				<div class="ef-kpi-icon-box ef-kpi-icon-emerald">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
			</div>
			<div class="ef-kpi-value" style="color: #059669;"><?php echo esc_html( $pay_counts['verified'] ); ?></div>
			<div class="ef-kpi-sub"><?php esc_html_e( 'Confirmed & receipts issued', 'eventflow' ); ?></div>
		</a>

		<!-- Rejected -->
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-payments&status=rejected' ) ); ?>" class="ef-kpi-card" style="text-decoration:none;">
			<div class="ef-kpi-card-top">
				<span class="ef-kpi-title"><?php esc_html_e( 'Rejected Payments', 'eventflow' ); ?></span>
				<div class="ef-kpi-icon-box" style="background:#FEE2E2; color:#DC2626;">
					<span class="dashicons dashicons-dismiss"></span>
				</div>
			</div>
			<div class="ef-kpi-value" style="color: #DC2626;"><?php echo esc_html( $pay_counts['rejected'] ); ?></div>
			<div class="ef-kpi-sub"><?php esc_html_e( 'Rejection reason notified', 'eventflow' ); ?></div>
		</a>

		<!-- Resubmissions -->
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-payments&status=under_verification' ) ); ?>" class="ef-kpi-card" style="text-decoration:none;">
			<div class="ef-kpi-card-top">
				<span class="ef-kpi-title"><?php esc_html_e( 'Resubmissions', 'eventflow' ); ?></span>
				<div class="ef-kpi-icon-box ef-kpi-icon-cyan">
					<span class="dashicons dashicons-update"></span>
				</div>
			</div>
			<div class="ef-kpi-value" style="color: #0284C7;"><?php echo esc_html( $pay_counts['resubmissions'] ); ?></div>
			<div class="ef-kpi-sub"><?php esc_html_e( 'Corrected proofs submitted', 'eventflow' ); ?></div>
		</a>

		<!-- Total Submissions -->
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-payments&status=all' ) ); ?>" class="ef-kpi-card" style="text-decoration:none;">
			<div class="ef-kpi-card-top">
				<span class="ef-kpi-title"><?php esc_html_e( 'Total Submissions', 'eventflow' ); ?></span>
				<div class="ef-kpi-icon-box ef-kpi-icon-indigo">
					<span class="dashicons dashicons-money-alt"></span>
				</div>
			</div>
			<div class="ef-kpi-value" style="color: #4F46E5;"><?php echo esc_html( $pay_counts['total'] ); ?></div>
			<div class="ef-kpi-sub"><?php esc_html_e( 'All historical records', 'eventflow' ); ?></div>
		</a>
	</div>

	<div class="ef-card" style="margin-top: 20px;">
		<!-- Filters & Search -->
		<form method="get" style="margin-bottom: 16px;">
			<input type="hidden" name="page" value="eventflow-payments">
			<div class="ef-filter-row" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
				<div class="ef-filter-item ef-grow" style="flex:1; min-width:240px;">
					<input type="text" name="s" class="ef-input large-text" placeholder="<?php esc_attr_e( 'Search by Reg ID, TID, Name, Team, or Email...', 'eventflow' ); ?>" value="<?php echo esc_attr( $pay_search ); ?>">
				</div>

				<div class="ef-filter-item">
					<select name="status" class="ef-input">
						<option value="all" <?php selected( $pay_status, 'all' ); ?>><?php esc_html_e( 'All Payment Records', 'eventflow' ); ?></option>
						<option value="under_verification" <?php selected( $pay_status, 'under_verification' ); ?>><?php esc_html_e( 'Pending Verification Queue', 'eventflow' ); ?></option>
						<option value="verified" <?php selected( $pay_status, 'verified' ); ?>><?php esc_html_e( 'Verified & Approved', 'eventflow' ); ?></option>
						<option value="rejected" <?php selected( $pay_status, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'eventflow' ); ?></option>
						<option value="reupload_requested" <?php selected( $pay_status, 'reupload_requested' ); ?>><?php esc_html_e( 'Re-upload Requested', 'eventflow' ); ?></option>
					</select>
				</div>

				<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'eventflow' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-payments' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'eventflow' ); ?></a>
			</div>
		</form>

		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Reg ID', 'eventflow' ); ?></th>
					<th><?php esc_html_e( 'Team / Candidate', 'eventflow' ); ?></th>
					<th><?php esc_html_e( 'Track', 'eventflow' ); ?></th>
					<th><?php esc_html_e( 'Method', 'eventflow' ); ?></th>
					<th><?php esc_html_e( 'Transaction ID', 'eventflow' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'eventflow' ); ?></th>
					<th><?php esc_html_e( 'Status', 'eventflow' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'eventflow' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $payments_data['items'] ) ) : ?>
					<tr><td colspan="8" style="text-align:center; padding: 24px; color: #64748B;"><?php esc_html_e( 'No payment records found matching criteria.', 'eventflow' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $payments_data['items'] as $item ) : ?>
						<?php $version_num = ! empty( $item->version ) ? (int) $item->version : 1; ?>
						<tr>
							<td><strong class="ef-monospace"><?php echo esc_html( $item->reg_id ); ?></strong></td>
							<td>
								<strong><?php echo esc_html( $item->reg_team_name ? $item->reg_team_name : $item->leader_name ); ?></strong>
								<div style="font-size: 12px; color: #64748B;"><?php echo esc_html( $item->leader_email ); ?></div>
							</td>
							<td><?php echo esc_html( $item->activity_title ); ?></td>
							<td><?php echo esc_html( $item->payment_method ); ?></td>
							<td>
								<code class="ef-tid"><?php echo esc_html( $item->transaction_id ? $item->transaction_id : 'N/A' ); ?></code>
								<?php if ( $version_num > 1 ) : ?>
									<span class="ef-badge ef-badge-resubmission" style="background:#0284C7; color:#FFF; font-weight:700; font-size:11px; padding:2px 8px; border-radius:12px; margin-left:4px; display:inline-block;" title="<?php printf( esc_attr__( 'Resubmitted Proof Version %d', 'eventflow' ), $version_num ); ?>">
										🔄 <?php printf( esc_html__( 'Resubmission (v%d)', 'eventflow' ), $version_num ); ?>
									</span>
								<?php else : ?>
									<span class="ef-badge ef-badge-neutral" style="font-size:10px; opacity:0.8; margin-left:4px;" title="<?php esc_attr_e( 'First submission on record', 'eventflow' ); ?>">
										<?php esc_html_e( '1st Submission', 'eventflow' ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td><strong class="ef-fee"><?php echo sprintf( '%s %s', esc_html( $currency ), number_format( $item->amount, 2 ) ); ?></strong></td>
							<td><?php echo EventFlow_Registration_Manager::get_status_badge( $item->status, 'payment' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td>
								<div style="display:flex; gap:4px; align-items:center;">
									<button type="button" class="button button-small ef-preview-proof-btn" 
											data-payment-id="<?php echo esc_attr( $item->id ); ?>" 
											data-reg-id="<?php echo esc_attr( $item->reg_id ); ?>" 
											data-activity="<?php echo esc_attr( $item->activity_title ); ?>" 
											data-team="<?php echo esc_attr( $item->reg_team_name ? $item->reg_team_name : $item->leader_name ); ?>" 
											data-method="<?php echo esc_attr( $item->payment_method ); ?>" 
											data-tid="<?php echo esc_attr( $item->transaction_id ); ?>" 
											data-amount="<?php echo esc_attr( sprintf( '%s %s', $currency, number_format( $item->amount, 2 ) ) ); ?>" 
											data-date="<?php echo esc_attr( date_i18n( 'M j, Y', strtotime( $item->payment_date ) ) ); ?>" 
											data-url="<?php echo esc_url( $item->preview_url ); ?>" 
											data-filetype="<?php echo esc_attr( $item->file_type ); ?>" 
											data-status="<?php echo esc_attr( $item->status ); ?>">
										<span class="dashicons dashicons-visibility" style="margin-top:2px;"></span>
										<?php esc_html_e( 'Review Proof', 'eventflow' ); ?>
									</button>
									<button type="button" class="button button-small ef-payment-history-btn" 
											data-payment-id="<?php echo esc_attr( $item->id ); ?>" 
											data-reg-id="<?php echo esc_attr( $item->reg_id ); ?>" 
											title="<?php esc_attr_e( 'Audit History', 'eventflow' ); ?>">
										<span class="dashicons dashicons-backup" style="margin-top:2px;"></span>
									</button>
									<button type="button" class="button button-small ef-edit-payment-btn" 
											data-payment-id="<?php echo esc_attr( $item->id ); ?>" 
											data-tid="<?php echo esc_attr( $item->transaction_id ); ?>" 
											data-method="<?php echo esc_attr( $item->payment_method ); ?>" 
											data-amount="<?php echo esc_attr( $item->amount ); ?>" 
											data-date="<?php echo esc_attr( $item->payment_date ); ?>" 
											data-status="<?php echo esc_attr( $item->status ); ?>" 
											title="<?php esc_attr_e( 'Edit Record', 'eventflow' ); ?>">
										<span class="dashicons dashicons-edit" style="margin-top:2px;"></span>
									</button>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- Modal Screenshot Viewer (Enterprise Two-Column Layout) -->
	<div class="ef-modal ef-preview-modal" id="ef-payment-preview-modal" aria-hidden="true">
		<div class="ef-modal-backdrop"></div>
		<div class="ef-modal-dialog ef-modal-2xl" style="max-width: 1360px; width: 95vw;">
			<div class="ef-modal-header" style="display:flex; justify-content:space-between; align-items:center;">
				<div style="display:flex; align-items:center; gap:10px;">
					<h3 class="ef-modal-title" style="margin:0;"><?php esc_html_e( 'Payment Verification Inspector', 'eventflow' ); ?></h3>
					<span class="ef-badge ef-badge-primary" id="ef-modal-reg-id-badge"></span>
					<span id="ef-pv-status-badge"></span>
				</div>
				<div class="ef-preview-toolbar">
					<button type="button" class="ef-tool-btn" id="ef-zoom-in" title="Zoom In">+</button>
					<button type="button" class="ef-tool-btn" id="ef-zoom-out" title="Zoom Out">-</button>
					<button type="button" class="ef-tool-btn" id="ef-rotate" title="Rotate 90°">⟳</button>
					<button type="button" class="ef-tool-btn" id="ef-fit-screen" title="Fit to Screen">⤢</button>
					<button type="button" class="ef-tool-btn" id="ef-zoom-reset" title="Reset View">⟲</button>
					<button type="button" class="ef-modal-close" data-dismiss="modal">&times;</button>
				</div>
			</div>

			<div class="ef-modal-body ef-split-modal-body ef-pv-two-col-layout" style="display:grid; grid-template-columns: 1.15fr 0.85fr; gap:20px; max-height:80vh; overflow-y:auto; padding:20px;">
				<!-- LEFT COLUMN: TEAM ROSTER, ACADEMICS & FINANCIALS -->
				<div class="ef-pv-left-column">
					<!-- Team Info -->
					<div class="ef-card ef-pv-card" style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:16px; margin-bottom:14px;">
						<h4 style="margin:0 0 12px; font-size:14px; font-weight:700; border-bottom:1px solid #E2E8F0; padding-bottom:6px;"><?php esc_html_e( 'Team & Registration Details', 'eventflow' ); ?></h4>
						<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:13px;">
							<div><span class="ef-text-muted"><?php esc_html_e( 'Team / Candidate:', 'eventflow' ); ?></span> <strong id="ef-pv-team-name">-</strong></div>
							<div><span class="ef-text-muted"><?php esc_html_e( 'Reg ID:', 'eventflow' ); ?></span> <code id="ef-pv-reg-id">-</code></div>
							<div><span class="ef-text-muted"><?php esc_html_e( 'Leader Name:', 'eventflow' ); ?></span> <strong id="ef-pv-leader-name">-</strong></div>
							<div><span class="ef-text-muted"><?php esc_html_e( 'Leader Email:', 'eventflow' ); ?></span> <span id="ef-pv-leader-email">-</span></div>
							<div><span class="ef-text-muted"><?php esc_html_e( 'Contact / WhatsApp:', 'eventflow' ); ?></span> <span id="ef-pv-leader-phone">-</span></div>
							<div><span class="ef-text-muted"><?php esc_html_e( 'Submission Date:', 'eventflow' ); ?></span> <span id="ef-pv-reg-date">-</span></div>
							<div><span class="ef-text-muted"><?php esc_html_e( 'University:', 'eventflow' ); ?></span> <span id="ef-pv-university">-</span></div>
							<div><span class="ef-text-muted"><?php esc_html_e( 'Dept & Semester:', 'eventflow' ); ?></span> <span id="ef-pv-dept-sem">-</span></div>
							<div style="grid-column: span 2;"><span class="ef-text-muted"><?php esc_html_e( 'Competition Track:', 'eventflow' ); ?></span> <strong id="ef-pv-competition-title" style="color:#0D9488;">-</strong></div>
						</div>
					</div>

					<!-- Member Roster (7 Fields) -->
					<div class="ef-card ef-pv-card" style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:16px; margin-bottom:14px;">
						<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; border-bottom:1px solid #E2E8F0; padding-bottom:6px;">
							<h4 style="margin:0; font-size:14px; font-weight:700;"><?php esc_html_e( 'Team Members Roster', 'eventflow' ); ?></h4>
							<span class="ef-badge ef-badge-neutral" id="ef-pv-members-count-badge">0</span>
						</div>
						<div class="ef-table-responsive" style="max-height:190px; overflow-y:auto;">
							<table class="wp-list-table widefat striped" id="ef-pv-members-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Name', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Role', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Email', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Phone', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'University', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Dept / Sem', 'eventflow' ); ?></th>
									</tr>
								</thead>
								<tbody id="ef-pv-members-tbody"></tbody>
							</table>
						</div>
					</div>

					<!-- Payment Reconciliation -->
					<div class="ef-card ef-pv-card" style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:16px; margin-bottom:14px;">
						<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; border-bottom:1px solid #E2E8F0; padding-bottom:6px;">
							<h4 style="margin:0; font-size:14px; font-weight:700;"><?php esc_html_e( 'Financial Reconciliation', 'eventflow' ); ?></h4>
							<span id="ef-pv-fee-diff-badge"></span>
						</div>
						<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:13px;">
							<div><span class="ef-text-muted"><?php esc_html_e( 'Transaction ID:', 'eventflow' ); ?></span> <code id="ef-pv-tid" class="ef-tid-lg">-</code></div>
							<div><span class="ef-text-muted"><?php esc_html_e( 'Method:', 'eventflow' ); ?></span> <strong id="ef-pv-method">-</strong></div>
							<div><span class="ef-text-muted"><?php esc_html_e( 'Submitted Amount:', 'eventflow' ); ?></span> <strong id="ef-pv-amount" style="color:#0D9488; font-size:15px;">-</strong></div>
							<div><span class="ef-text-muted"><?php esc_html_e( 'Required Fee:', 'eventflow' ); ?></span> <strong id="ef-pv-required-amount" style="font-size:15px;">-</strong></div>
							<div><span class="ef-text-muted"><?php esc_html_e( 'Payment Date:', 'eventflow' ); ?></span> <span id="ef-pv-date">-</span></div>
							<div><span class="ef-text-muted"><?php esc_html_e( 'Last Officer:', 'eventflow' ); ?></span> <span id="ef-pv-verified-by">-</span></div>
						</div>
					</div>

					<!-- Previous Rejection Remarks Box -->
					<div class="ef-card ef-pv-card" id="ef-pv-rejection-remarks-box" style="display:none; background:#FEF2F2; border:1px solid #FECACA; border-left:4px solid #DC2626; border-radius:8px; padding:14px 16px; margin-bottom:14px;">
						<div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
							<span style="font-size:16px;">⚠️</span>
							<h4 style="margin:0; font-size:13.5px; font-weight:700; color:#991B1B;"><?php esc_html_e( 'Previous Rejection Remarks & Reason', 'eventflow' ); ?></h4>
							<span class="ef-badge ef-badge-danger" id="ef-pv-rejected-version-badge" style="font-size:10px; margin-left:auto;"></span>
						</div>
						<p id="ef-pv-previous-rejection-text" style="margin:0; font-size:13px; color:#1E293B; font-weight:500; line-height:1.4;"></p>
					</div>

					<!-- Submission Versions / Resubmissions & Historical Receipts -->
					<div class="ef-card ef-pv-card" style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:16px; margin-bottom:14px;">
						<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; border-bottom:1px solid #E2E8F0; padding-bottom:6px;">
							<h4 style="margin:0; font-size:14px; font-weight:700;"><?php esc_html_e( 'Submission Versions & Receipts', 'eventflow' ); ?></h4>
							<span class="ef-badge ef-badge-neutral" id="ef-pv-version-count-badge">1</span>
						</div>
						<div id="ef-pv-submissions-container">
							<p class="ef-text-muted ef-text-xs"><?php esc_html_e( 'Loading submission versions...', 'eventflow' ); ?></p>
						</div>
					</div>

					<!-- Verification History Timeline -->
					<div class="ef-card ef-pv-card" style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:16px;">
						<h4 style="margin:0 0 10px; font-size:14px; font-weight:700; border-bottom:1px solid #E2E8F0; padding-bottom:6px;"><?php esc_html_e( 'Audit History & Status Log', 'eventflow' ); ?></h4>
						<div class="ef-table-responsive" style="max-height:150px; overflow-y:auto;">
							<table class="wp-list-table widefat striped" id="ef-pv-history-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Timestamp', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Action', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Officer', 'eventflow' ); ?></th>
										<th><?php esc_html_e( 'Note', 'eventflow' ); ?></th>
									</tr>
								</thead>
								<tbody id="ef-pv-history-tbody"></tbody>
							</table>
						</div>
					</div>
				</div>

				<!-- RIGHT COLUMN: RECEIPT CANVAS & VERIFICATION ACTIONS -->
				<div class="ef-pv-right-column">
					<div class="ef-card" style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:16px; margin-bottom:14px;">
						<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
							<h4 style="margin:0; font-size:14px; font-weight:700;"><?php esc_html_e( 'Receipt Proof Preview', 'eventflow' ); ?></h4>
							<a href="#" id="ef-btn-download-receipt" class="button button-secondary" download>
								💾 <?php esc_html_e( 'Download Receipt', 'eventflow' ); ?>
							</a>
						</div>
						<div class="ef-screenshot-viewport" id="ef-screenshot-viewport" style="background:#F1F5F9; border-radius:6px; min-height:420px; max-height:480px; overflow:hidden; position:relative;">
							<div id="ef-preview-loading" class="ef-spinner" style="display:none; position: absolute; top: 45%; left: 45%;"></div>
							<div id="ef-preview-error" class="notice notice-error" style="display:none; margin: 20px;">
								<p><?php esc_html_e( 'Receipt document could not be loaded or file permission denied.', 'eventflow' ); ?></p>
							</div>
							<div class="ef-image-canvas-wrap">
								<img src="" alt="Proof" id="ef-preview-img" style="display:none; max-width:100%;">
								<iframe src="" id="ef-preview-pdf" style="display:none; width: 100%; height: 460px; border: none;"></iframe>
							</div>
						</div>
					</div>

					<div class="ef-card" style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:16px;">
						<h4 style="margin:0 0 10px; font-size:14px; font-weight:700;"><?php esc_html_e( 'Verification Decision', 'eventflow' ); ?></h4>
						<input type="hidden" id="ef-active-payment-id" value="0">
						<div class="ef-form-group" style="margin-bottom:14px;">
							<label style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Staff Notes / Reference Code', 'eventflow' ); ?></label>
							<textarea id="ef-action-notes" class="large-text" rows="2" placeholder="<?php esc_attr_e( 'Optional verification notes or bank statement ref...', 'eventflow' ); ?>"></textarea>
						</div>

						<button type="button" class="button button-primary ef-btn-block" id="ef-btn-verify-payment" style="width:100%; height:40px; font-size:14px; font-weight:600; background:#059669; border-color:#059669;">
							✓ <?php esc_html_e( 'Verify & Approve Payment', 'eventflow' ); ?>
						</button>
						<div style="display: flex; gap: 8px; margin-top: 10px;">
							<button type="button" class="button button-secondary" id="ef-btn-reupload-payment" style="flex:1;">
								🔄 <?php esc_html_e( 'Request Re-upload', 'eventflow' ); ?>
							</button>
							<button type="button" class="button button-link-delete" id="ef-btn-reject-payment" style="flex:1; border:1px solid #DC2626; color:#DC2626; text-align:center;">
								✕ <?php esc_html_e( 'Reject Payment', 'eventflow' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- REJECT PAYMENT MODAL -->
	<div class="ef-modal" id="ef-reject-modal" aria-hidden="true" style="display:none;">
		<div class="ef-modal-backdrop"></div>
		<div class="ef-modal-dialog" style="max-width:540px; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);">
			<div class="ef-modal-header" style="padding:16px 20px; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center;">
				<h3 class="ef-modal-title" style="margin:0; font-size:16px; font-weight:700; color:#991B1B;"><?php esc_html_e( 'Reject Payment Proof', 'eventflow' ); ?></h3>
				<button type="button" class="ef-modal-close" data-dismiss="modal" style="background:none; border:none; font-size:22px; cursor:pointer;">&times;</button>
			</div>
			<div class="ef-modal-body" style="padding:20px;">
				<p class="ef-text-sm ef-text-muted" style="margin:0 0 14px; color:#64748B; font-size:13px;"><?php esc_html_e( 'Specify a mandatory rejection reason. This reason will be logged in the audit trail, displayed prominently in the participant dashboard, and emailed directly to the participant. Rejection is never a dead end: the participant can re-upload a corrected receipt.', 'eventflow' ); ?></p>
				<div class="ef-form-group">
					<label style="display:block; font-weight:600; margin-bottom:6px;"><?php esc_html_e( 'Rejection Reason', 'eventflow' ); ?> <span class="ef-req" style="color:#DC2626;">*</span></label>
					<textarea id="ef-reject-reason-text" class="ef-input large-text" rows="3" placeholder="<?php esc_attr_e( 'e.g. Transaction ID could not be confirmed in bank statement. Amount is insufficient or receipt is illegible...', 'eventflow' ); ?>" required style="width:100%; padding:8px 12px; border:1px solid #CBD5E1; border-radius:6px;"></textarea>
				</div>
			</div>
			<div class="ef-modal-footer" style="display:flex; justify-content:flex-end; gap:10px; padding:12px 20px; border-top:1px solid #E2E8F0; background:#F8FAFC;">
				<button type="button" class="button" data-dismiss="modal"><?php esc_html_e( 'Cancel', 'eventflow' ); ?></button>
				<button type="button" class="button button-primary" id="ef-confirm-reject-btn" style="background:#DC2626; border-color:#DC2626; color:#FFF; font-weight:600;"><?php esc_html_e( 'Confirm Rejection & Notify', 'eventflow' ); ?></button>
			</div>
		</div>
	</div>

	<!-- REQUEST RE-UPLOAD MODAL -->
	<div class="ef-modal" id="ef-reupload-modal" aria-hidden="true" style="display:none;">
		<div class="ef-modal-backdrop"></div>
		<div class="ef-modal-dialog" style="max-width:540px; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);">
			<div class="ef-modal-header" style="padding:16px 20px; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center;">
				<h3 class="ef-modal-title" style="margin:0; font-size:16px; font-weight:700; color:#D97706;"><?php esc_html_e( 'Request Proof Re-upload', 'eventflow' ); ?></h3>
				<button type="button" class="ef-modal-close" data-dismiss="modal" style="background:none; border:none; font-size:22px; cursor:pointer;">&times;</button>
			</div>
			<div class="ef-modal-body" style="padding:20px;">
				<p class="ef-text-sm ef-text-muted" style="margin:0 0 14px; color:#64748B; font-size:13px;"><?php esc_html_e( 'Explain clearly what is missing or illegible in the current receipt. The participant will be notified to replace their receipt.', 'eventflow' ); ?></p>
				<div class="ef-form-group">
					<label style="display:block; font-weight:600; margin-bottom:6px;"><?php esc_html_e( 'Instructions for Participant', 'eventflow' ); ?> <span class="ef-req" style="color:#DC2626;">*</span></label>
					<textarea id="ef-reupload-instructions-text" class="ef-input large-text" rows="3" placeholder="<?php esc_attr_e( 'e.g. The uploaded image is blurry. Please upload an official bank receipt showing transaction date and sender name...', 'eventflow' ); ?>" required style="width:100%; padding:8px 12px; border:1px solid #CBD5E1; border-radius:6px;"></textarea>
				</div>
			</div>
			<div class="ef-modal-footer" style="display:flex; justify-content:flex-end; gap:10px; padding:12px 20px; border-top:1px solid #E2E8F0; background:#F8FAFC;">
				<button type="button" class="button" data-dismiss="modal"><?php esc_html_e( 'Cancel', 'eventflow' ); ?></button>
				<button type="button" class="button button-primary" id="ef-confirm-reupload-btn" style="background:#D97706; border-color:#D97706; color:#FFF; font-weight:600;"><?php esc_html_e( 'Send Re-upload Request', 'eventflow' ); ?></button>
			</div>
		</div>
	</div>

	<!-- EDIT PAYMENT MODAL -->
	<div class="ef-modal" id="ef-payment-edit-modal" aria-hidden="true" style="display:none;">
		<div class="ef-modal-backdrop"></div>
		<div class="ef-modal-dialog ef-modal-lg" style="max-width:650px; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
			<div class="ef-modal-header" style="padding:16px 20px; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center;">
				<h3 class="ef-modal-title" style="margin:0; font-size:16px; font-weight:700;"><?php esc_html_e( 'Edit Payment Record', 'eventflow' ); ?></h3>
				<button type="button" class="ef-modal-close" data-dismiss="modal" style="background:none; border:none; font-size:22px; cursor:pointer;">&times;</button>
			</div>
			<form id="ef-edit-payment-form" enctype="multipart/form-data">
				<div class="ef-modal-body" style="padding:20px;">
					<input type="hidden" name="payment_id" id="ef-edit-pay-id" value="0">
					<input type="hidden" name="current_status" id="ef-edit-pay-current-status" value="">

					<div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
						<div class="ef-form-group">
							<label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e( 'Transaction ID / Reference', 'eventflow' ); ?> <span class="ef-req" style="color:#DC2626;">*</span></label>
							<input type="text" name="transaction_id" id="ef-edit-pay-tid" class="ef-input" style="width:100%;" required>
						</div>

						<div class="ef-form-group">
							<label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e( 'Payment Method', 'eventflow' ); ?> <span class="ef-req" style="color:#DC2626;">*</span></label>
							<select name="payment_method" id="ef-edit-pay-method" class="ef-input" style="width:100%;" required>
								<option value="Bank Transfer"><?php esc_html_e( 'Bank Transfer', 'eventflow' ); ?></option>
								<option value="JazzCash"><?php esc_html_e( 'JazzCash', 'eventflow' ); ?></option>
								<option value="Easypaisa"><?php esc_html_e( 'Easypaisa', 'eventflow' ); ?></option>
								<option value="Raast Instant"><?php esc_html_e( 'Raast Instant', 'eventflow' ); ?></option>
								<option value="Cash / Manual"><?php esc_html_e( 'Cash / Manual Receipt', 'eventflow' ); ?></option>
							</select>
						</div>
					</div>

					<div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
						<div class="ef-form-group">
							<label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e( 'Paid Amount', 'eventflow' ); ?> <span class="ef-req" style="color:#DC2626;">*</span></label>
							<input type="number" step="0.01" name="amount" id="ef-edit-pay-amount" class="ef-input" style="width:100%;" required>
						</div>

						<div class="ef-form-group">
							<label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e( 'Payment Date', 'eventflow' ); ?> <span class="ef-req" style="color:#DC2626;">*</span></label>
							<input type="date" name="payment_date" id="ef-edit-pay-date" class="ef-input" style="width:100%;" required>
						</div>
					</div>

					<div class="ef-form-group" style="margin-bottom:12px;">
						<label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e( 'Verification Status Override', 'eventflow' ); ?></label>
						<select name="status" id="ef-edit-pay-status" class="ef-input" style="width:100%;">
							<option value="pending"><?php esc_html_e( 'Pending Payment', 'eventflow' ); ?></option>
							<option value="under_verification"><?php esc_html_e( 'Under Verification', 'eventflow' ); ?></option>
							<option value="verified"><?php esc_html_e( 'Verified & Approved', 'eventflow' ); ?></option>
							<option value="rejected"><?php esc_html_e( 'Rejected', 'eventflow' ); ?></option>
							<option value="reupload_requested"><?php esc_html_e( 'Re-upload Requested', 'eventflow' ); ?></option>
						</select>
					</div>

					<div class="ef-form-group" id="ef-correction-reason-box" style="display:none; background: #FFFBEB; border: 1px solid #FDE68A; padding: 12px; border-radius: 6px; margin-bottom:12px;">
						<label style="color: #92400E; font-weight: 700;">⚠️ <?php esc_html_e( 'Mandatory Correction Reason', 'eventflow' ); ?> <span class="ef-req" style="color:#DC2626;">*</span></label>
						<p class="ef-text-xs" style="color: #B45309; margin: 4px 0 8px;"><?php esc_html_e( 'This payment was already verified. Modifying financial values requires an explicit correction audit reason.', 'eventflow' ); ?></p>
						<textarea name="correction_reason" id="ef-edit-pay-correction" class="ef-input" rows="2" style="width:100%;" placeholder="<?php esc_attr_e( 'Specify why verified payment data is being amended...', 'eventflow' ); ?>"></textarea>
					</div>

					<div class="ef-form-group" style="margin-bottom:12px;">
						<label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e( 'Replacement Receipt / Proof (Optional)', 'eventflow' ); ?></label>
						<input type="file" name="payment_proof" id="ef-edit-pay-file" class="ef-input" accept="image/*,application/pdf">
						<span class="ef-text-xs ef-text-muted" style="display:block; margin-top:4px; color:#64748B;"><?php esc_html_e( 'Upload new screenshot or PDF. Previous file will be preserved in audit logs.', 'eventflow' ); ?></span>
					</div>

					<div class="ef-form-group">
						<label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e( 'Internal Staff Notes', 'eventflow' ); ?></label>
						<textarea name="notes" id="ef-edit-pay-notes" class="ef-input" rows="2" style="width:100%;"></textarea>
					</div>
				</div>

				<div class="ef-modal-footer" style="display:flex; justify-content:flex-end; gap:10px; padding:12px 20px; border-top:1px solid #E2E8F0; background:#F8FAFC;">
					<button type="button" class="button" data-dismiss="modal"><?php esc_html_e( 'Cancel', 'eventflow' ); ?></button>
					<button type="submit" class="button button-primary" id="ef-save-payment-edit-btn"><?php esc_html_e( 'Save Payment Updates', 'eventflow' ); ?></button>
				</div>
			</form>
		</div>
	</div>

	<!-- PAYMENT AUDIT HISTORY TIMELINE MODAL -->
	<div class="ef-modal" id="ef-payment-history-modal" aria-hidden="true" style="display:none;">
		<div class="ef-modal-backdrop"></div>
		<div class="ef-modal-dialog ef-modal-lg" style="max-width:700px; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
			<div class="ef-modal-header" style="padding:16px 20px; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center;">
				<h3 class="ef-modal-title" style="margin:0; font-size:16px; font-weight:700;"><?php esc_html_e( 'Payment Lifecycle & Audit History', 'eventflow' ); ?></h3>
				<button type="button" class="ef-modal-close" data-dismiss="modal" style="background:none; border:none; font-size:22px; cursor:pointer;">&times;</button>
			</div>
			<div class="ef-modal-body" style="padding:20px; max-height:420px; overflow-y:auto;">
				<div id="ef-payment-history-content">
					<div class="ef-spinner"></div>
				</div>
			</div>
			<div class="ef-modal-footer" style="display:flex; justify-content:flex-end; padding:12px 20px; border-top:1px solid #E2E8F0; background:#F8FAFC;">
				<button type="button" class="button" data-dismiss="modal"><?php esc_html_e( 'Close', 'eventflow' ); ?></button>
			</div>
		</div>
	</div>
</div>
