<?php
/**
 * EventFlow Admin View: Export & Reports
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$activities = EventFlow_Activity_Manager::get_activities( array( 'status' => 'all' ) );
?>

<div class="wrap ef-admin-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Export & Reporting', 'eventflow' ); ?></h1>
	<hr class="wp-header-end">

	<div class="ef-card" style="margin-top: 20px;">
		<div class="ef-card-header">
			<h3><?php esc_html_e( 'Custom Filtered Export Generator', 'eventflow' ); ?></h3>
			<p class="ef-card-subtitle"><?php esc_html_e( 'Select filters and export participants, team rosters, or financial payment reports.', 'eventflow' ); ?></p>
		</div>

		<form method="get" action="<?php echo esc_url( home_url() ); ?>" class="ef-form" target="_blank">
			<input type="hidden" name="eventflow_action" value="export">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'eventflow_export' ) ); ?>">

			<div class="ef-grid ef-grid-3">
				<div class="ef-form-group">
					<label><?php esc_html_e( 'Report Dataset Type', 'eventflow' ); ?></label>
					<select name="type" class="ef-input">
						<option value="teams"><?php esc_html_e( 'All Candidates & Team Members (7 Member Fields)', 'eventflow' ); ?></option>
						<option value="registrations"><?php esc_html_e( 'Applications & Registrations', 'eventflow' ); ?></option>
						<option value="payments"><?php esc_html_e( 'Payments & Financial Transactions', 'eventflow' ); ?></option>
					</select>
				</div>

				<div class="ef-form-group">
					<label><?php esc_html_e( 'Competition Track', 'eventflow' ); ?></label>
					<select name="activity_id" class="ef-input">
						<option value="0"><?php esc_html_e( 'All Tracks', 'eventflow' ); ?></option>
						<?php foreach ( $activities as $a ) : ?>
							<option value="<?php echo esc_attr( $a->id ); ?>"><?php echo esc_html( $a->title ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="ef-form-group">
					<label><?php esc_html_e( 'Payment Status Filter', 'eventflow' ); ?></label>
					<select name="payment_status" class="ef-input">
						<option value="all"><?php esc_html_e( 'All Statuses', 'eventflow' ); ?></option>
						<option value="verified"><?php esc_html_e( 'Verified Only', 'eventflow' ); ?></option>
						<option value="under_verification"><?php esc_html_e( 'Under Verification Only', 'eventflow' ); ?></option>
						<option value="pending"><?php esc_html_e( 'Pending Only', 'eventflow' ); ?></option>
						<option value="rejected"><?php esc_html_e( 'Rejected Only', 'eventflow' ); ?></option>
					</select>
				</div>
			</div>

			<div class="ef-form-group">
				<label><?php esc_html_e( 'Output File Format', 'eventflow' ); ?></label>
				<div style="display: flex; gap: 16px; margin-top: 6px;">
					<label><input type="radio" name="format" value="csv" checked> <strong>CSV (Comma Delimited / Excel UTF-8)</strong></label>
					<label><input type="radio" name="format" value="excel"> <strong>Excel Spreadsheet (.xls)</strong></label>
					<label><input type="radio" name="format" value="html"> <strong>Printable HTML / PDF View</strong></label>
				</div>
			</div>

			<button type="submit" class="button button-primary button-hero">
				<?php esc_html_e( 'Generate & Download Report', 'eventflow' ); ?>
			</button>
		</form>
	</div>
</div>

