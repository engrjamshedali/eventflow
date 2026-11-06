<?php
/**
 * EventFlow Admin View: Activities & Competitions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$activities = EventFlow_Activity_Manager::get_activities( array( 'status' => 'all' ) );
$event = EventFlow_Event_Manager::get_active_event();
$currency = $event ? esc_html( $event->currency ) : 'PKR';

$edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
$edit_act = $edit_id ? EventFlow_Activity_Manager::get_activity( $edit_id ) : null;
?>

<div class="wrap ef-admin-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Activities & Competition Tracks', 'eventflow' ); ?></h1>
	<hr class="wp-header-end">

	<div class="ef-grid ef-grid-2" style="margin-top: 20px;">
		<!-- Left: Form -->
		<div class="ef-card">
			<div class="ef-card-header">
				<h3><?php echo $edit_act ? esc_html__( 'Edit Competition Track', 'eventflow' ) : esc_html__( 'Add New Activity / Competition', 'eventflow' ); ?></h3>
			</div>

			<form id="ef-admin-activity-form" class="ef-form">
				<input type="hidden" name="activity_id" value="<?php echo esc_attr( $edit_act ? $edit_act->id : 0 ); ?>">
				<input type="hidden" name="event_id" value="<?php echo esc_attr( $event ? $event->id : 1 ); ?>">

				<div class="ef-form-group">
					<label><?php esc_html_e( 'Track Title', 'eventflow' ); ?> <span class="ef-req">*</span></label>
					<input type="text" name="title" class="ef-input" value="<?php echo esc_attr( $edit_act ? $edit_act->title : '' ); ?>" placeholder="e.g. Speed Programming Championship" required>
				</div>

				<div class="ef-form-group">
					<label><?php esc_html_e( 'Description', 'eventflow' ); ?></label>
					<textarea name="description" class="ef-input" rows="3"><?php echo esc_textarea( $edit_act ? $edit_act->description : '' ); ?></textarea>
				</div>

				<div class="ef-grid ef-grid-3">
					<div class="ef-form-group">
						<label><?php esc_html_e( 'Participation Type', 'eventflow' ); ?></label>
						<select name="participation_type" class="ef-input">
							<option value="both" <?php selected( $edit_act && 'both' === $edit_act->participation_type ); ?>><?php esc_html_e( 'Solo & Team', 'eventflow' ); ?></option>
							<option value="team" <?php selected( $edit_act && 'team' === $edit_act->participation_type ); ?>><?php esc_html_e( 'Team Only', 'eventflow' ); ?></option>
							<option value="solo" <?php selected( $edit_act && 'solo' === $edit_act->participation_type ); ?>><?php esc_html_e( 'Solo Only', 'eventflow' ); ?></option>
						</select>
					</div>

					<div class="ef-form-group">
						<label><?php esc_html_e( 'Min Team Size', 'eventflow' ); ?></label>
						<input type="number" name="min_team_size" class="ef-input" value="<?php echo esc_attr( $edit_act ? $edit_act->min_team_size : '1' ); ?>" min="1">
					</div>

					<div class="ef-form-group">
						<label><?php esc_html_e( 'Max Team Size', 'eventflow' ); ?></label>
						<input type="number" name="max_team_size" class="ef-input" value="<?php echo esc_attr( $edit_act ? $edit_act->max_team_size : '4' ); ?>" min="1">
					</div>
				</div>

				<div class="ef-grid ef-grid-3">
					<div class="ef-form-group">
						<label><?php esc_html_e( 'Registration Fee', 'eventflow' ); ?></label>
						<input type="number" step="0.01" name="reg_fee" class="ef-input" value="<?php echo esc_attr( $edit_act ? $edit_act->reg_fee : '1500.00' ); ?>">
					</div>

					<div class="ef-form-group">
						<label><?php esc_html_e( 'Fee Calculation Method', 'eventflow' ); ?></label>
						<select name="fee_calculation" class="ef-input">
							<option value="per_team" <?php selected( ! $edit_act || empty( $edit_act->fee_calculation ) || 'per_team' === $edit_act->fee_calculation ); ?>><?php esc_html_e( 'Per Team (Flat fee for entire team)', 'eventflow' ); ?></option>
							<option value="per_person" <?php selected( $edit_act && 'per_person' === $edit_act->fee_calculation ); ?>><?php esc_html_e( 'Per Person (Fee × total participants)', 'eventflow' ); ?></option>
						</select>
					</div>

					<div class="ef-form-group">
						<label><?php esc_html_e( 'Payment Proof Required', 'eventflow' ); ?></label>
						<select name="payment_proof_required" class="ef-input">
							<option value="1" <?php selected( ! $edit_act || ! isset( $edit_act->payment_proof_required ) || 1 == $edit_act->payment_proof_required ); ?>><?php esc_html_e( 'Yes – Screenshot/PDF receipt mandatory', 'eventflow' ); ?></option>
							<option value="0" <?php selected( $edit_act && isset( $edit_act->payment_proof_required ) && 0 == $edit_act->payment_proof_required ); ?>><?php esc_html_e( 'No – Free or on-spot payment', 'eventflow' ); ?></option>
						</select>
					</div>
				</div>

				<div class="ef-grid ef-grid-2">
					<div class="ef-form-group">
						<label><?php esc_html_e( 'Registration Status', 'eventflow' ); ?></label>
						<select name="status" class="ef-input">
							<option value="published" <?php selected( ! $edit_act || 'published' === $edit_act->status ); ?>><?php esc_html_e( 'Registration Open (Published)', 'eventflow' ); ?></option>
							<option value="closed" <?php selected( $edit_act && 'closed' === $edit_act->status ); ?>><?php esc_html_e( 'Registration Closed', 'eventflow' ); ?></option>
							<option value="draft" <?php selected( $edit_act && 'draft' === $edit_act->status ); ?>><?php esc_html_e( 'Draft (Hidden from public)', 'eventflow' ); ?></option>
						</select>
					</div>

					<div class="ef-form-group">
						<label><?php esc_html_e( 'Max Participants Cap', 'eventflow' ); ?></label>
						<input type="number" name="max_participants" class="ef-input" value="<?php echo esc_attr( $edit_act ? $edit_act->max_participants : '50' ); ?>" placeholder="0 for unlimited">
					</div>
				</div>

				<div class="ef-form-group">
					<label><?php esc_html_e( 'Eligibility Rules', 'eventflow' ); ?></label>
					<textarea name="eligibility" class="ef-input" rows="2"><?php echo esc_textarea( $edit_act ? $edit_act->eligibility : '' ); ?></textarea>
				</div>

				<div class="ef-form-group">
					<label><?php esc_html_e( 'Instructions & Constraints', 'eventflow' ); ?></label>
					<textarea name="instructions" class="ef-input" rows="2"><?php echo esc_textarea( $edit_act ? $edit_act->instructions : '' ); ?></textarea>
				</div>

				<div class="ef-form-group">
					<label><?php esc_html_e( 'Activity Image URL', 'eventflow' ); ?></label>
					<div class="ef-upload-field-wrap">
						<input type="url" name="image_url" id="ef-act-img-url" class="ef-input" value="<?php echo esc_attr( $edit_act ? $edit_act->image_url : '' ); ?>">
						<button type="button" class="button ef-media-upload-btn" data-target="#ef-act-img-url"><?php esc_html_e( 'Media Library', 'eventflow' ); ?></button>
					</div>
				</div>

				<button type="submit" class="button button-primary">
					<?php echo $edit_act ? esc_html__( 'Update Activity Track', 'eventflow' ) : esc_html__( 'Save Activity', 'eventflow' ); ?>
				</button>
				<?php if ( $edit_act ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-activities' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'eventflow' ); ?></a>
				<?php endif; ?>
			</form>
		</div>

		<!-- Right: List -->
		<div class="ef-card">
			<div class="ef-card-header">
				<h3><?php esc_html_e( 'Configured Competitions', 'eventflow' ); ?></h3>
			</div>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Title', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Type & Limits', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Fee & Model', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Receipt Req.', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Capacity', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Status', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'eventflow' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $activities as $a ) : ?>
						<?php
						$fee_model = ( ! empty( $a->fee_calculation ) && 'per_person' === $a->fee_calculation ) ? __( 'Per Person', 'eventflow' ) : __( 'Per Team', 'eventflow' );
						$proof_req = ( isset( $a->payment_proof_required ) && 0 == $a->payment_proof_required ) ? __( 'No', 'eventflow' ) : __( 'Yes', 'eventflow' );
						?>
						<tr>
							<td><strong><?php echo esc_html( $a->title ); ?></strong></td>
							<td>
								<span class="ef-tag"><?php echo esc_html( ucfirst( $a->participation_type ) ); ?></span>
								<div class="ef-text-xs ef-text-muted"><?php printf( esc_html__( '%d–%d members', 'eventflow' ), (int) $a->min_team_size, (int) $a->max_team_size ); ?></div>
							</td>
							<td>
								<strong class="ef-fee"><?php echo sprintf( '%s %s', esc_html( $currency ), number_format( $a->reg_fee, 2 ) ); ?></strong>
								<div class="ef-text-xs ef-text-muted"><?php echo esc_html( $fee_model ); ?></div>
							</td>
							<td><span class="ef-tag <?php echo 'Yes' === $proof_req ? 'ef-tag-primary' : ''; ?>"><?php echo esc_html( $proof_req ); ?></span></td>
							<td><?php echo (int) $a->max_participants > 0 ? sprintf( '%d / %d', (int) $a->current_participants, (int) $a->max_participants ) : esc_html__( 'Unlimited', 'eventflow' ); ?></td>
							<td>
								<?php if ( 'published' === $a->status ) : ?>
									<span class="ef-badge ef-badge-success"><?php esc_html_e( 'Open', 'eventflow' ); ?></span>
								<?php elseif ( 'closed' === $a->status ) : ?>
									<span class="ef-badge ef-badge-danger"><?php esc_html_e( 'Closed', 'eventflow' ); ?></span>
								<?php else : ?>
									<span class="ef-badge ef-badge-neutral"><?php esc_html_e( 'Draft', 'eventflow' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-activities&edit=' . $a->id ) ); ?>" class="button button-small">
									<?php esc_html_e( 'Edit', 'eventflow' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

