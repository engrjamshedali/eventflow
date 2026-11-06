<?php
/**
 * EventFlow Official Registration Confirmation Voucher & Receipt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$reg = isset( $reg ) ? $reg : null;
if ( ! $reg ) {
	echo '<p>' . esc_html__( 'Registration record not found.', 'eventflow' ) . '</p>';
	return;
}

$activity = isset( $reg->activity ) ? $reg->activity : EventFlow_Activity_Manager::get_activity( $reg->activity_id );
$event    = isset( $reg->event ) ? $reg->event : EventFlow_Event_Manager::get_event( $reg->event_id );
$team     = isset( $reg->team ) ? $reg->team : EventFlow_Team_Manager::get_team_by_registration( $reg->id );
$members  = isset( $reg->members ) ? $reg->members : EventFlow_Team_Manager::get_members_by_registration( $reg->id );
$payment  = isset( $reg->payment ) ? $reg->payment : EventFlow_Payment_Manager::get_payment_by_registration( $reg->id );

$event_title = $event ? $event->title : get_bloginfo( 'name' );
$logo_url    = $event && ! empty( $event->logo_url ) ? $event->logo_url : '';
$currency    = $event ? $event->currency : 'PKR';
$is_standalone = ! did_action( 'wp_head' );
?>

<?php if ( $is_standalone ) : ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php printf( esc_html__( 'Registration Receipt – %s', 'eventflow' ), esc_html( $reg->reg_id ) ); ?></title>
	<?php if ( class_exists( 'EventFlow_Favicon' ) ) { EventFlow_Favicon::render_meta_tags(); } ?>
	<link rel="stylesheet" href="<?php echo esc_url( EVENTFLOW_PLUGIN_URL . 'assets/css/eventflow-receipt.css' ); ?>">
</head>
<body class="ef-receipt-body">
<?php else : ?>
	<link rel="stylesheet" href="<?php echo esc_url( EVENTFLOW_PLUGIN_URL . 'assets/css/eventflow-receipt.css' ); ?>">
	<div class="ef-receipt-embedded-wrap">
<?php endif; ?>

<div class="ef-receipt-container">
	<!-- Actions Bar (Hidden during Print) -->
	<div class="ef-receipt-actions no-print">
		<button onclick="window.print()" class="ef-btn ef-btn-primary">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
			<?php esc_html_e( 'Print / Save as PDF', 'eventflow' ); ?>
		</button>
		<a href="<?php echo esc_url( EventFlow_Shortcodes::get_dashboard_url() ); ?>" class="ef-btn ef-btn-outline">
			&larr; <?php esc_html_e( 'Return to Dashboard', 'eventflow' ); ?>
		</a>
	</div>

	<!-- Printable Voucher Card -->
	<div class="ef-voucher-sheet">
		<!-- Top Event Header -->
		<div class="ef-voucher-header">
			<div class="ef-vh-left">
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo" class="ef-vh-logo">
				<?php endif; ?>
				<div>
					<h2 class="ef-vh-event-title"><?php echo esc_html( $event_title ); ?></h2>
					<span class="ef-vh-doc-title"><?php esc_html_e( 'OFFICIAL PARTICIPATION CONFIRMATION & RECEIPT', 'eventflow' ); ?></span>
				</div>
			</div>

			<div class="ef-vh-right">
				<div class="ef-reg-id-box">
					<span class="ef-text-xs"><?php esc_html_e( 'REGISTRATION ID', 'eventflow' ); ?></span>
					<strong class="ef-reg-id-text"><?php echo esc_html( $reg->reg_id ); ?></strong>
				</div>
			</div>
		</div>

		<!-- Status Ribbon -->
		<div class="ef-voucher-ribbon">
			<div class="ef-vr-item">
				<span class="ef-vr-label"><?php esc_html_e( 'Application Status:', 'eventflow' ); ?></span>
				<strong class="ef-vr-status"><?php echo esc_html( strtoupper( str_replace( '_', ' ', $reg->application_status ) ) ); ?></strong>
			</div>
			<div class="ef-vr-item">
				<span class="ef-vr-label"><?php esc_html_e( 'Payment Status:', 'eventflow' ); ?></span>
				<strong class="ef-vr-status"><?php echo esc_html( strtoupper( str_replace( '_', ' ', $reg->payment_status ) ) ); ?></strong>
			</div>
			<div class="ef-vr-item">
				<span class="ef-vr-label"><?php esc_html_e( 'Issued On:', 'eventflow' ); ?></span>
				<strong><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $reg->created_at ) ) ); ?></strong>
			</div>
		</div>

		<!-- Summary Grid -->
		<div class="ef-voucher-section">
			<h4 class="ef-v-sec-title"><?php esc_html_e( 'Competition & Team Overview', 'eventflow' ); ?></h4>
			<table class="ef-v-table-simple">
				<tr>
					<td style="width: 25%;"><strong><?php esc_html_e( 'Competition Track:', 'eventflow' ); ?></strong></td>
					<td style="width: 75%;"><?php echo esc_html( $activity ? $activity->title : 'N/A' ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Participation Type:', 'eventflow' ); ?></strong></td>
					<td><?php echo esc_html( ucfirst( $reg->participation_type ) ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Team / Candidate Name:', 'eventflow' ); ?></strong></td>
					<td><strong><?php echo esc_html( $reg->team_name ? $reg->team_name : ( $team ? $team->leader_name : 'Solo' ) ); ?></strong></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Team Leader Email / Phone:', 'eventflow' ); ?></strong></td>
					<td><?php echo esc_html( $team ? sprintf( '%s (%s)', $team->leader_email, $team->leader_phone ) : 'N/A' ); ?></td>
				</tr>
			</table>
		</div>

		<!-- Members Table -->
		<?php if ( ! empty( $members ) ) : ?>
			<div class="ef-voucher-section">
				<h4 class="ef-v-sec-title"><?php esc_html_e( 'Registered Members List', 'eventflow' ); ?></h4>
				<table class="ef-v-table-bordered">
					<thead>
						<tr>
							<th>#</th>
							<th><?php esc_html_e( 'Full Name', 'eventflow' ); ?></th>
							<th><?php esc_html_e( "Father's Name", 'eventflow' ); ?></th>
							<th><?php esc_html_e( 'University', 'eventflow' ); ?></th>
							<th><?php esc_html_e( 'Department & Semester', 'eventflow' ); ?></th>
							<th><?php esc_html_e( 'Contact Phone', 'eventflow' ); ?></th>
							<th><?php esc_html_e( 'Role', 'eventflow' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $members as $idx => $m ) : ?>
							<tr>
								<td><?php echo esc_html( $idx + 1 ); ?></td>
								<td><strong><?php echo esc_html( $m->full_name ); ?></strong></td>
								<td><?php echo esc_html( $m->father_name ); ?></td>
								<td><?php echo esc_html( $m->university ); ?></td>
								<td><?php echo esc_html( $m->department . ' (Sem ' . $m->semester . ')' ); ?></td>
								<td><?php echo esc_html( $m->contact_number ); ?></td>
								<td><?php echo (int) $m->is_leader ? '<span class="ef-tag ef-tag-primary">' . esc_html__( 'Leader', 'eventflow' ) . '</span>' : esc_html__( 'Member', 'eventflow' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<!-- Payment Breakdown & Stamp -->
		<div class="ef-voucher-section ef-payment-summary-sec">
			<div class="ef-v-payment-left">
				<h4 class="ef-v-sec-title"><?php esc_html_e( 'Financial Verification Receipt', 'eventflow' ); ?></h4>
				<table class="ef-v-table-simple">
					<tr>
						<td><strong><?php esc_html_e( 'Registration Fee Payable:', 'eventflow' ); ?></strong></td>
						<td><strong><?php echo sprintf( '%s %s', esc_html( $currency ), number_format( $reg->total_fee, 2 ) ); ?></strong></td>
					</tr>
					<?php if ( $payment ) : ?>
						<tr>
							<td><strong><?php esc_html_e( 'Amount Paid:', 'eventflow' ); ?></strong></td>
							<td><?php echo sprintf( '%s %s', esc_html( $currency ), number_format( $payment->amount, 2 ) ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Transaction ID (TID):', 'eventflow' ); ?></strong></td>
							<td><code class="ef-tid"><?php echo esc_html( $payment->transaction_id ); ?></code></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Payment Method:', 'eventflow' ); ?></strong></td>
							<td><?php echo esc_html( $payment->payment_method ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Payment Date:', 'eventflow' ); ?></strong></td>
							<td><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $payment->payment_date ) ) ); ?></td>
						</tr>
					<?php endif; ?>
				</table>
			</div>

			<div class="ef-v-payment-right">
				<?php if ( 'verified' === $reg->payment_status ) : ?>
					<div class="ef-stamp-box verified">
						<span>VERIFIED & APPROVED</span>
						<small><?php echo esc_html( $event_title ); ?></small>
						<small><?php echo esc_html( $reg->verified_at ? date( 'Y-m-d', strtotime( $reg->verified_at ) ) : date( 'Y-m-d' ) ); ?></small>
					</div>
				<?php else : ?>
					<div class="ef-stamp-box pending">
						<span><?php echo esc_html( strtoupper( str_replace( '_', ' ', $reg->payment_status ) ) ); ?></span>
						<small><?php esc_html_e( 'Awaiting Verification', 'eventflow' ); ?></small>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Important Instructions -->
		<div class="ef-voucher-footer">
			<h5><?php esc_html_e( 'Important Participant Instructions:', 'eventflow' ); ?></h5>
			<ul>
				<li><?php esc_html_e( 'All team members must carry their original University Student ID cards on the event day.', 'eventflow' ); ?></li>
				<li><?php esc_html_e( 'Present this printed pass or digital PDF at the event reception desk for badge collection.', 'eventflow' ); ?></li>
				<li><?php esc_html_e( 'Reporting time and venue details will be dispatched to the team leader email.', 'eventflow' ); ?></li>
			</ul>
			<div class="ef-voucher-security-note">
				<span><?php printf( esc_html__( 'Generated automatically by EventFlow System. Reference: %s', 'eventflow' ), esc_html( $reg->reg_id ) ); ?></span>
			</div>
		</div>
	</div>
</div>

<?php if ( $is_standalone ) : ?>
</body>
</html>
<?php else : ?>
</div>
<?php endif; ?>

