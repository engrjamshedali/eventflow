<?php
/**
 * EventFlow Participant Payment Submission Page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$reg = isset( $reg ) ? $reg : null;
if ( ! $reg ) {
	echo '<div class="ef-notice ef-notice-warning"><p>' . esc_html__( 'Please select a valid registration to submit payment.', 'eventflow' ) . '</p></div>';
	return;
}

$payment_settings = isset( $payment_settings ) ? $payment_settings : EventFlow_Payment_Manager::get_settings();
$existing_payment = isset( $existing_payment ) ? $existing_payment : EventFlow_Payment_Manager::get_payment_by_registration( $reg->id );
$payment_history  = EventFlow_Payment_Manager::get_all_payments_by_registration( $reg->id );

$activity = isset( $reg->activity ) ? $reg->activity : EventFlow_Activity_Manager::get_activity( $reg->activity_id );
$event    = isset( $reg->event ) ? $reg->event : EventFlow_Event_Manager::get_event( $reg->event_id );
$team     = isset( $reg->team ) ? $reg->team : EventFlow_Team_Manager::get_team_by_registration( $reg->id );
$currency = $event ? esc_html( $event->currency ) : 'PKR';

$is_verified           = ( 'verified' === $reg->payment_status );
$is_under_verification = ( 'under_verification' === $reg->payment_status );
$is_rejected           = ( 'rejected' === $reg->payment_status || ( $existing_payment && 'rejected' === $existing_payment->status && ! $is_under_verification ) );
$is_reupload           = ( 'reupload_requested' === $reg->payment_status );
?>

<div class="eventflow-app ef-payment-container">
	<!-- Registration Summary Banner -->
	<div class="ef-payment-banner ef-flex-between">
		<div>
			<span class="ef-text-muted ef-text-xs"><?php esc_html_e( 'PAYMENT FOR REGISTRATION', 'eventflow' ); ?></span>
			<h2 class="ef-payment-reg-id"><?php echo esc_html( $reg->reg_id ); ?></h2>
			<div class="ef-payment-meta-inline">
				<span><strong><?php esc_html_e( 'Event / Track:', 'eventflow' ); ?></strong> <?php echo esc_html( $activity ? $activity->title : 'N/A' ); ?></span>
				<span>&bull;</span>
				<span><strong><?php esc_html_e( 'Candidate / Team:', 'eventflow' ); ?></strong> <?php echo esc_html( $reg->team_name ? $reg->team_name : ( $team ? $team->leader_name : 'Solo' ) ); ?></span>
				<?php if ( $existing_payment ) : ?>
					<span>&bull;</span>
					<span><strong><?php esc_html_e( 'Submitted:', 'eventflow' ); ?></strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $existing_payment->payment_date ) ) ); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<div class="ef-payment-amount-box">
			<span class="ef-text-muted ef-text-xs"><?php esc_html_e( 'TOTAL PAYABLE', 'eventflow' ); ?></span>
			<div class="ef-payment-price"><?php echo sprintf( '%s %s', esc_html( $currency ), number_format( $reg->total_fee, 2 ) ); ?></div>
			<div class="ef-payment-badge-wrap">
				<?php echo EventFlow_Registration_Manager::get_status_badge( $reg->payment_status, 'payment' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
	</div>

	<!-- Payment Status Workflow Alert Panels -->
	<?php if ( $is_rejected ) : ?>
		<!-- REJECTION PANEL (Section 3 Requirement) -->
		<div class="ef-alert ef-alert-danger" style="margin-bottom: 24px; border-left: 4px solid #DC2626; background: #FEF2F2; padding: 18px 20px; border-radius: 8px;">
			<div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
				<span style="font-size:20px;">❌</span>
				<h3 style="margin:0; color:#991B1B; font-size:16px; font-weight:700;"><?php esc_html_e( 'Payment Verification Unsuccessful', 'eventflow' ); ?></h3>
			</div>
			<p style="margin:0 0 12px; color:#7F1D1D; font-size:14px;"><?php esc_html_e( 'Your payment submission requires correction.', 'eventflow' ); ?></p>
			<div style="background:#FFF; border:1px solid #FECACA; border-radius:6px; padding:12px 16px; margin-bottom:12px;">
				<strong style="color:#B91C1C; font-size:13px;"><?php esc_html_e( "Admin's Reason:", 'eventflow' ); ?></strong>
				<p style="margin:4px 0 0; color:#1E293B; font-size:14px; font-weight:500;">
					<?php 
					if ( ! empty( $reg->rejection_reason ) ) {
						echo esc_html( $reg->rejection_reason );
					} elseif ( ! empty( $existing_payment->rejection_reason ) ) {
						echo esc_html( $existing_payment->rejection_reason );
					} else {
						esc_html_e( 'Payment proof could not be verified against the university bank statement. Please review your details and upload a corrected receipt below.', 'eventflow' );
					}
					?>
				</p>
			</div>
			<p style="margin:0; color:#7F1D1D; font-size:13.5px;"><?php esc_html_e( 'Please review the reason and upload a corrected payment receipt below.', 'eventflow' ); ?></p>
		</div>
	<?php elseif ( $is_under_verification ) : ?>
		<!-- UNDER VERIFICATION / SUCCESSFUL SUBMISSION PANEL -->
		<div class="ef-alert ef-alert-info" style="margin-bottom: 24px; border-left: 4px solid #0D9488; background: #F0FDFA; padding: 16px 20px; border-radius: 8px;">
			<div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
				<span style="font-size:18px;">⏳</span>
				<h3 style="margin:0; color:#0F766E; font-size:15px; font-weight:700;"><?php esc_html_e( 'Payment Submitted Successfully', 'eventflow' ); ?></h3>
			</div>
			<p style="margin:0; color:#115E59; font-size:13.5px;"><?php esc_html_e( 'Your updated payment proof has been submitted and is awaiting admin verification.', 'eventflow' ); ?></p>
		</div>
	<?php elseif ( $is_reupload ) : ?>
		<!-- RE-UPLOAD REQUESTED PANEL -->
		<div class="ef-alert ef-alert-warning" style="margin-bottom: 24px; border-left: 4px solid #F59E0B; background: #FFFBEB; padding: 16px 20px; border-radius: 8px;">
			<div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
				<span style="font-size:18px;">⚠️</span>
				<h3 style="margin:0; color:#B45309; font-size:15px; font-weight:700;"><?php esc_html_e( 'Re-upload Payment Proof Requested', 'eventflow' ); ?></h3>
			</div>
			<p style="margin:4px 0 0; color:#78350F; font-size:13.5px;">
				<?php echo ! empty( $reg->rejection_reason ) ? esc_html( $reg->rejection_reason ) : esc_html__( 'The verification committee has requested a replacement or clearer payment receipt for this application.', 'eventflow' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $is_verified ) : ?>
		<div class="ef-card ef-verified-card">
			<div class="ef-verified-icon">✅</div>
			<h3><?php esc_html_e( 'Payment Verified & Approved!', 'eventflow' ); ?></h3>
			<p><?php esc_html_e( 'Your payment has been successfully verified by our finance committee. Your official participation voucher and entry pass are ready.', 'eventflow' ); ?></p>
			<a href="?tab=receipt&reg_id=<?php echo esc_attr( $reg->reg_id ); ?>" class="ef-btn ef-btn-primary ef-btn-lg" target="_blank">
				<?php esc_html_e( 'Download Registration Receipt & Pass', 'eventflow' ); ?> &rarr;
			</a>
		</div>
	<?php else : ?>

		<div class="ef-grid ef-grid-2 ef-payment-layout">
			<!-- LEFT COLUMN: BANK DETAILS & QR CODE -->
			<div class="ef-payment-instructions-col">
				<!-- Official Bank Transfer Details -->
				<div class="ef-card ef-bank-card">
					<div class="ef-card-header">
						<h4><?php esc_html_e( 'Official Bank Transfer Account', 'eventflow' ); ?></h4>
					</div>

					<div class="ef-bank-details-list">
						<div class="ef-bank-row">
							<span class="ef-b-label"><?php esc_html_e( 'Bank Name:', 'eventflow' ); ?></span>
							<strong class="ef-b-val"><?php echo esc_html( $payment_settings['bank_name'] ); ?></strong>
						</div>
						<div class="ef-bank-row">
							<span class="ef-b-label"><?php esc_html_e( 'Account Title:', 'eventflow' ); ?></span>
							<strong class="ef-b-val"><?php echo esc_html( $payment_settings['account_title'] ); ?></strong>
						</div>
						<div class="ef-bank-row ef-copyable-row">
							<span class="ef-b-label"><?php esc_html_e( 'Account Number:', 'eventflow' ); ?></span>
							<div class="ef-copy-wrap">
								<code id="ef-acc-num"><?php echo esc_html( $payment_settings['account_number'] ); ?></code>
								<button type="button" class="ef-copy-btn" data-clipboard-target="#ef-acc-num" title="<?php esc_attr_e( 'Copy to clipboard', 'eventflow' ); ?>">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
								</button>
							</div>
						</div>
						<div class="ef-bank-row ef-copyable-row">
							<span class="ef-b-label"><?php esc_html_e( 'IBAN:', 'eventflow' ); ?></span>
							<div class="ef-copy-wrap">
								<code id="ef-iban-num"><?php echo esc_html( $payment_settings['iban'] ); ?></code>
								<button type="button" class="ef-copy-btn" data-clipboard-target="#ef-iban-num" title="<?php esc_attr_e( 'Copy to clipboard', 'eventflow' ); ?>">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
								</button>
							</div>
						</div>
					</div>

					<!-- Mobile Wallets -->
					<?php if ( ! empty( $payment_settings['easypaisa_number'] ) || ! empty( $payment_settings['jazzcash_number'] ) ) : ?>
						<div class="ef-wallets-block">
							<h5><?php esc_html_e( 'Mobile Wallets (JazzCash & Easypaisa)', 'eventflow' ); ?></h5>
							<?php if ( ! empty( $payment_settings['jazzcash_number'] ) ) : ?>
								<div class="ef-wallet-item">
									<span class="ef-badge ef-badge-warning"><?php esc_html_e( 'JazzCash', 'eventflow' ); ?></span>
									<strong><?php echo esc_html( $payment_settings['jazzcash_number'] ); ?></strong>
									<span>(<?php echo esc_html( $payment_settings['jazzcash_title'] ); ?>)</span>
								</div>
							<?php endif; ?>

							<?php if ( ! empty( $payment_settings['easypaisa_number'] ) ) : ?>
								<div class="ef-wallet-item">
									<span class="ef-badge ef-badge-success"><?php esc_html_e( 'Easypaisa', 'eventflow' ); ?></span>
									<strong><?php echo esc_html( $payment_settings['easypaisa_number'] ); ?></strong>
									<span>(<?php echo esc_html( $payment_settings['easypaisa_title'] ); ?>)</span>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>

				<!-- QR Code Preview -->
				<?php if ( ! empty( $payment_settings['qr_code_url'] ) ) : ?>
					<div class="ef-card ef-qr-card" style="text-align: center;">
						<h4><?php esc_html_e( 'Scan QR Code to Pay', 'eventflow' ); ?></h4>
						<img src="<?php echo esc_url( $payment_settings['qr_code_url'] ); ?>" alt="Payment QR Code" class="ef-qr-image">
						<p class="ef-text-muted ef-text-xs"><?php esc_html_e( 'Scan using Raast, Easypaisa, JazzCash, or any 1Link banking app.', 'eventflow' ); ?></p>
					</div>
				<?php endif; ?>

				<!-- Step-by-step instructions -->
				<div class="ef-card ef-instruction-card">
					<h4><?php esc_html_e( 'Payment Steps & Verification Rules', 'eventflow' ); ?></h4>
					<div class="ef-instruction-text">
						<?php echo nl2br( esc_html( $payment_settings['instructions'] ) ); ?>
					</div>
				</div>
			</div>

			<!-- RIGHT COLUMN: PROOF SUBMISSION FORM -->
			<div class="ef-payment-form-col">
				<!-- Latest Uploaded Receipt (if available) -->
				<?php 
				$has_proof = ( $existing_payment && ( ! empty( $existing_payment->screenshot_path ) || ! empty( $existing_payment->screenshot_url ) || ! empty( $existing_payment->file_path ) ) );
				?>
				<?php if ( $has_proof ) : ?>
					<?php
					$proof_url    = ! empty( $existing_payment->preview_url ) ? $existing_payment->preview_url : EventFlow_Payment_Manager::get_proof_url( $existing_payment->id );
					$download_url = ! empty( $existing_payment->download_url ) ? $existing_payment->download_url : $proof_url;
					$is_pdf       = ( ! empty( $existing_payment->file_type ) && false !== strpos( strtolower( $existing_payment->file_type ), 'pdf' ) ) || 
					                ( ! empty( $existing_payment->screenshot_path ) && false !== strpos( strtolower( $existing_payment->screenshot_path ), '.pdf' ) );
					$prev_version = (int) ( $existing_payment->version ?? 1 );
					?>
					<div class="ef-card" style="margin-bottom: 20px; background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:16px;">
						<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; border-bottom:1px solid #E2E8F0; padding-bottom:6px;">
							<h4 style="margin:0; font-size:14px; font-weight:700; color:#1E293B;">
								<?php echo $is_rejected ? esc_html__( 'Previously Submitted Proof (Rejected)', 'eventflow' ) : esc_html__( 'Latest Uploaded Payment Receipt', 'eventflow' ); ?>
							</h4>
							<span class="ef-badge <?php echo $is_rejected ? 'ef-badge-danger' : 'ef-badge-neutral'; ?>">
								<?php printf( esc_html__( 'Version %d', 'eventflow' ), $prev_version ); ?>
							</span>
						</div>
						<div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
							<div style="width:90px; height:75px; background:#F1F5F9; border-radius:6px; display:flex; align-items:center; justify-content:center; overflow:hidden; border:1px solid #CBD5E1;">
								<?php if ( $is_pdf ) : ?>
									<span style="font-size:32px;">📄</span>
								<?php else : ?>
									<img src="<?php echo esc_url( $proof_url ); ?>" alt="Proof" style="width:100%; height:100%; object-fit:cover;">
								<?php endif; ?>
							</div>
							<div style="flex:1; min-width:200px; font-size:13px; color:#475569;">
								<div><strong><?php esc_html_e( 'TID / Ref:', 'eventflow' ); ?></strong> <code class="ef-tid"><?php echo esc_html( $existing_payment->transaction_id ? $existing_payment->transaction_id : 'N/A' ); ?></code></div>
								<div><strong><?php esc_html_e( 'Method:', 'eventflow' ); ?></strong> <?php echo esc_html( $existing_payment->payment_method ); ?></div>
								<div><strong><?php esc_html_e( 'Amount:', 'eventflow' ); ?></strong> <?php echo sprintf( '%s %s', esc_html( $currency ), number_format( $existing_payment->amount, 2 ) ); ?></div>
								<?php if ( ! empty( $existing_payment->payment_date ) ) : ?>
									<div><strong><?php esc_html_e( 'Date:', 'eventflow' ); ?></strong> <?php echo esc_html( date_i18n( 'M j, Y', strtotime( $existing_payment->payment_date ) ) ); ?></div>
								<?php endif; ?>
								<div style="margin-top:8px; display:flex; gap:8px;">
									<a href="<?php echo esc_url( $proof_url ); ?>" target="_blank" class="ef-btn-teal ef-btn-sm" style="display:inline-block; font-size:12px; padding:3px 10px; text-decoration:none;">
										🔍 <?php esc_html_e( 'View Full Size Receipt', 'eventflow' ); ?> &rarr;
									</a>
									<?php if ( ! empty( $download_url ) ) : ?>
										<a href="<?php echo esc_url( $download_url ); ?>" download class="ef-btn-outline-teal ef-btn-sm" style="display:inline-block; font-size:12px; padding:3px 10px; text-decoration:none;">
											💾 <?php esc_html_e( 'Download', 'eventflow' ); ?>
										</a>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<div class="ef-card">
					<div class="ef-card-header">
						<h4>
							<?php 
							if ( $is_rejected || $is_reupload ) {
								esc_html_e( 'Re-upload Corrected Payment Proof', 'eventflow' );
							} elseif ( $is_under_verification ) {
								esc_html_e( 'Update / Re-submit Payment Proof', 'eventflow' );
							} else {
								esc_html_e( 'Submit Payment Receipt', 'eventflow' );
							}
							?>
						</h4>
						<p class="ef-card-subtitle"><?php esc_html_e( 'Upload your official bank receipt, transaction screenshot, or PDF statement.', 'eventflow' ); ?></p>
					</div>

					<!-- Submission Alert -->
					<div class="ef-alert" id="ef-payment-alert" style="display: none;"></div>

					<form id="ef-payment-form" class="ef-form" method="post" enctype="multipart/form-data">
						<input type="hidden" name="registration_id" value="<?php echo esc_attr( $reg->id ); ?>">

						<div class="ef-form-group">
							<label for="ef-pay-method"><?php esc_html_e( 'Payment Method Used', 'eventflow' ); ?> <span class="ef-req">*</span></label>
							<select id="ef-pay-method" name="payment_method" class="ef-input" required>
								<option value=""><?php esc_html_e( 'Select Payment Method', 'eventflow' ); ?></option>
								<option value="Bank Alfalah" <?php selected( $existing_payment && 'Bank Alfalah' === $existing_payment->payment_method ); ?>><?php esc_html_e( 'Bank Transfer (Alfalah)', 'eventflow' ); ?></option>
								<option value="Other Bank Transfer" <?php selected( $existing_payment && 'Other Bank Transfer' === $existing_payment->payment_method ); ?>><?php esc_html_e( 'Inter-Bank Online Transfer (IBFT)', 'eventflow' ); ?></option>
								<option value="Easypaisa" <?php selected( $existing_payment && 'Easypaisa' === $existing_payment->payment_method ); ?>><?php esc_html_e( 'Easypaisa Mobile Account', 'eventflow' ); ?></option>
								<option value="JazzCash" <?php selected( $existing_payment && 'JazzCash' === $existing_payment->payment_method ); ?>><?php esc_html_e( 'JazzCash Mobile Account', 'eventflow' ); ?></option>
								<option value="Raast Instant" <?php selected( $existing_payment && 'Raast Instant' === $existing_payment->payment_method ); ?>><?php esc_html_e( 'Raast Instant Transfer', 'eventflow' ); ?></option>
								<option value="Other" <?php selected( $existing_payment && 'Other' === $existing_payment->payment_method ); ?>><?php esc_html_e( 'Other', 'eventflow' ); ?></option>
							</select>
						</div>

						<div class="ef-form-group">
							<label for="ef-pay-tid"><?php esc_html_e( 'Transaction ID / Reference Number (TID)', 'eventflow' ); ?> <span class="ef-text-muted" style="font-weight: normal; font-size: 12px;">(<?php esc_html_e( 'Optional if clearly shown on receipt', 'eventflow' ); ?>)</span></label>
							<input type="text" id="ef-pay-tid" name="transaction_id" class="ef-input" placeholder="<?php esc_attr_e( 'e.g. 129384750192', 'eventflow' ); ?>" value="<?php echo esc_attr( $existing_payment ? $existing_payment->transaction_id : '' ); ?>">
							<span class="ef-help-text"><?php esc_html_e( 'Found on your digital transfer receipt. Optional if clearly visible on your uploaded screenshot.', 'eventflow' ); ?></span>
						</div>

						<div class="ef-grid ef-grid-2">
							<div class="ef-form-group">
								<label for="ef-pay-date"><?php esc_html_e( 'Payment Date', 'eventflow' ); ?> <span class="ef-req">*</span></label>
								<input type="date" id="ef-pay-date" name="payment_date" class="ef-input" value="<?php echo esc_attr( $existing_payment ? $existing_payment->payment_date : current_time( 'Y-m-d' ) ); ?>" required>
							</div>
							<div class="ef-form-group">
								<label for="ef-pay-amount"><?php esc_html_e( 'Amount Paid', 'eventflow' ); ?> <span class="ef-req">*</span></label>
								<input type="number" step="0.01" id="ef-pay-amount" name="amount" class="ef-input" value="<?php echo esc_attr( $existing_payment ? $existing_payment->amount : $reg->total_fee ); ?>" required>
							</div>
						</div>

						<!-- Drag and Drop Screenshot Upload -->
						<div class="ef-form-group">
							<label class="ef-label-bold"><?php esc_html_e( 'Upload Payment Screenshot or Receipt PDF', 'eventflow' ); ?> <span class="ef-req">*</span></label>
							<div class="ef-file-dropzone" id="ef-file-dropzone">
								<input type="file" id="ef-proof-file" name="payment_proof" accept="image/jpeg,image/png,application/pdf" class="ef-file-input" required>
								<div class="ef-dropzone-content">
									<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
									<strong><?php esc_html_e( 'Click to browse or drag and drop', 'eventflow' ); ?></strong>
									<span><?php esc_html_e( 'JPG, PNG, or PDF (Max 5 MB)', 'eventflow' ); ?></span>
								</div>
								<div class="ef-file-preview-wrap" style="display: none;">
									<span class="ef-file-name"></span>
									<button type="button" class="ef-remove-file-btn">&times;</button>
								</div>
							</div>
						</div>

						<!-- Smooth Upload Progress Indicator -->
						<div class="ef-upload-progress-wrap" id="ef-upload-progress-wrap" style="display:none; margin: 16px 0; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:12px 14px;">
							<div style="display:flex; justify-content:space-between; font-size:12.5px; font-weight:600; color:#0F766E; margin-bottom:6px;">
								<span class="ef-progress-status" id="ef-upload-progress-status"><?php esc_html_e( 'Uploading payment proof...', 'eventflow' ); ?></span>
								<span class="ef-progress-percent" id="ef-upload-progress-percent">0%</span>
							</div>
							<div class="ef-progress-bar-track" style="height:8px; background:#E2E8F0; border-radius:9999px; overflow:hidden;">
								<div class="ef-progress-bar-fill" id="ef-upload-progress-fill" style="width:0%; height:100%; background:linear-gradient(90deg, #0D9488, #10B981); transition:width 0.2s ease;"></div>
							</div>
						</div>

						<button type="submit" class="ef-btn ef-btn-primary ef-btn-block ef-submit-btn" id="ef-pay-submit-btn">
							<span class="ef-btn-text">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
								<?php 
								if ( $is_rejected || $is_reupload ) {
									esc_html_e( 'Re-upload Corrected Proof for Verification', 'eventflow' );
								} else {
									esc_html_e( 'Upload Proof for Verification', 'eventflow' );
								}
								?>
							</span>
							<span class="ef-spinner ef-btn-spinner" style="display: none;"></span>
						</button>
					</form>
				</div>

				<!-- Submission History Table (Versioned & Immutable Audit Trail) -->
				<?php if ( ! empty( $payment_history ) ) : ?>
					<div class="ef-card" style="margin-top: 24px; background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:16px;">
						<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom:1px solid #E2E8F0; padding-bottom:8px;">
							<h4 style="margin:0; font-size:14.5px; font-weight:700; color:#1E293B;">📜 <?php esc_html_e( 'Payment Submission History', 'eventflow' ); ?></h4>
							<span class="ef-badge ef-badge-neutral"><?php printf( esc_html__( '%d Submissions', 'eventflow' ), count( $payment_history ) ); ?></span>
						</div>
						<div class="ef-table-responsive" style="max-height:240px; overflow-y:auto;">
							<table class="ef-table ef-table-bordered" style="width:100%; font-size:12.5px;">
								<thead>
									<tr style="background:#F8FAFC;">
										<th style="padding:8px 10px;"><?php esc_html_e( 'Ver', 'eventflow' ); ?></th>
										<th style="padding:8px 10px;"><?php esc_html_e( 'Date', 'eventflow' ); ?></th>
										<th style="padding:8px 10px;"><?php esc_html_e( 'Method', 'eventflow' ); ?></th>
										<th style="padding:8px 10px;"><?php esc_html_e( 'Transaction ID', 'eventflow' ); ?></th>
										<th style="padding:8px 10px;"><?php esc_html_e( 'Amount', 'eventflow' ); ?></th>
										<th style="padding:8px 10px;"><?php esc_html_e( 'Status', 'eventflow' ); ?></th>
										<th style="padding:8px 10px;"><?php esc_html_e( 'Audit / Rejection Notes', 'eventflow' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $payment_history as $hist_item ) : ?>
										<?php $h_ver = (int) ( $hist_item->version ?? 1 ); ?>
										<tr>
											<td style="padding:8px 10px; font-weight:700; color:#0F172A;">v<?php echo esc_html( $h_ver ); ?></td>
											<td style="padding:8px 10px;"><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $hist_item->payment_date ) ) ); ?></td>
											<td style="padding:8px 10px;"><?php echo esc_html( $hist_item->payment_method ); ?></td>
											<td style="padding:8px 10px;"><code class="ef-tid"><?php echo esc_html( $hist_item->transaction_id ? $hist_item->transaction_id : '-' ); ?></code></td>
											<td style="padding:8px 10px; font-weight:600;"><?php echo sprintf( '%s %s', esc_html( $currency ), number_format( $hist_item->amount, 2 ) ); ?></td>
											<td style="padding:8px 10px;"><?php echo EventFlow_Registration_Manager::get_status_badge( $hist_item->status, 'payment' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
											<td style="padding:8px 10px; color:#475569; font-size:12px;">
												<?php 
												if ( ! empty( $hist_item->rejection_reason ) ) {
													echo '<span style="color:#DC2626; font-weight:600;">' . esc_html__( 'Reason:', 'eventflow' ) . '</span> ' . esc_html( $hist_item->rejection_reason );
												} elseif ( ! empty( $hist_item->notes ) ) {
													echo esc_html( $hist_item->notes );
												} else {
													echo '&mdash;';
												}
												?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>

	<?php endif; ?>
</div>
