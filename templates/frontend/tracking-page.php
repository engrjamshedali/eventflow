<?php
/**
 * EventFlow Public Registration Tracking Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="eventflow-app ef-tracking-container">
	<div class="ef-card ef-tracking-card">
		<div class="ef-tracking-header">
			<div class="ef-icon-search">🔎</div>
			<h2><?php esc_html_e( 'Track Application & Payment Status', 'eventflow' ); ?></h2>
			<p class="ef-text-muted"><?php esc_html_e( 'Enter your unique Registration ID and Team Leader email to view real-time verification and application progress.', 'eventflow' ); ?></p>
		</div>

		<form id="ef-track-form" class="ef-form" method="post">
			<div class="ef-form-group">
				<label for="ef-track-id"><?php esc_html_e( 'Registration ID', 'eventflow' ); ?> <span class="ef-req">*</span></label>
				<input type="text" id="ef-track-id" name="reg_id" class="ef-input" placeholder="e.g. EF-2026-XXXXX" required>
			</div>

			<div class="ef-form-group">
				<label for="ef-track-email"><?php esc_html_e( 'Leader Email Address', 'eventflow' ); ?> <span class="ef-req">*</span></label>
				<input type="email" id="ef-track-email" name="email" class="ef-input" placeholder="leader@university.edu" required>
			</div>

			<button type="submit" class="ef-btn ef-btn-primary ef-btn-block ef-submit-btn">
				<span class="ef-btn-text"><?php esc_html_e( 'Check Status', 'eventflow' ); ?> &rarr;</span>
				<span class="ef-spinner ef-btn-spinner" style="display: none;"></span>
			</button>
		</form>

		<div class="ef-alert" id="ef-track-alert" style="display: none; margin-top: 18px;"></div>

		<!-- Tracking Result Card -->
		<div id="ef-track-result" class="ef-track-result-box" style="display: none; margin-top: 24px;">
			<div class="ef-track-result-head ef-flex-between">
				<div>
					<span class="ef-text-muted ef-text-xs"><?php esc_html_e( 'REGISTRATION ID', 'eventflow' ); ?></span>
					<h3 id="ef-result-reg-id" class="ef-monospace"></h3>
				</div>
				<div id="ef-result-app-badge"></div>
			</div>

			<div class="ef-track-meta-table">
				<div class="ef-meta-row">
					<span><?php esc_html_e( 'Competition Track:', 'eventflow' ); ?></span>
					<strong id="ef-result-activity"></strong>
				</div>
				<div class="ef-meta-row">
					<span><?php esc_html_e( 'Team / Candidate:', 'eventflow' ); ?></span>
					<strong id="ef-result-team"></strong>
				</div>
				<div class="ef-meta-row">
					<span><?php esc_html_e( 'Payment Verification:', 'eventflow' ); ?></span>
					<div id="ef-result-pay-badge"></div>
				</div>
				<div class="ef-meta-row">
					<span><?php esc_html_e( 'Submission Date:', 'eventflow' ); ?></span>
					<span id="ef-result-date"></span>
				</div>
			</div>
		</div>
	</div>
</div>

