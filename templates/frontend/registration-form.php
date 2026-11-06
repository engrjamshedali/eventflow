<?php
/**
 * EventFlow Comprehensive Dynamic Registration Form Template
 *
 * Implements complete Individual (Solo) and Team Registration with:
 * - Configurable team sizes (min/max limits)
 * - 7 mandatory participant details
 * - Dynamic live fee calculation (Per Team vs Per Person)
 * - Enabled payment accounts with copy-to-clipboard
 * - Payment receipt upload dropzone with preview & replacement
 * - Declaration & server-side verification
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user = is_user_logged_in() ? wp_get_current_user() : (object) array( 'ID' => 0, 'display_name' => '', 'user_email' => '' );

// Retrieve active event and all published activities
$event = EventFlow_Event_Manager::get_active_event();
$currency = $event ? esc_html( $event->currency ) : 'PKR';
$all_activities = EventFlow_Activity_Manager::get_activities( array( 'status' => 'published', 'event_id' => $event ? $event->id : 0 ) );

// Determine initially selected activity
$activity_id = isset( $activity_id ) ? absint( $activity_id ) : ( isset( $activity ) && $activity ? $activity->id : ( isset( $_GET['activity_id'] ) ? absint( $_GET['activity_id'] ) : 0 ) );
$activity = null;

if ( $activity_id ) {
	$activity = EventFlow_Activity_Manager::get_activity( $activity_id );
}

if ( ! $activity && ! empty( $all_activities ) ) {
	$activity = $all_activities[0];
	$activity_id = $activity->id;
}

if ( ! $activity ) {
	echo '<div class="ef-notice ef-notice-warning"><p>' . esc_html__( 'No competitions are currently open for registration. Please check back later.', 'eventflow' ) . '</p></div>';
	return;
}

// Prepare activities JSON for seamless client-side switching
$activities_catalog = array();
foreach ( $all_activities as $act ) {
	$activities_catalog[ $act->id ] = EventFlow_Activity_Manager::format_activity_for_frontend( $act );
}

$payment_settings = EventFlow_Payment_Manager::get_settings();
$enabled_methods  = EventFlow_Payment_Manager::get_enabled_payment_methods();

// Leader default profile meta if logged in
$user_phone = $user->ID ? get_user_meta( $user->ID, 'eventflow_phone', true ) : '';
$user_uni   = $user->ID ? get_user_meta( $user->ID, 'eventflow_university', true ) : '';
$user_dept  = $user->ID ? get_user_meta( $user->ID, 'eventflow_department', true ) : '';
$user_sem   = $user->ID ? get_user_meta( $user->ID, 'eventflow_semester', true ) : '';
$user_batch = $user->ID ? get_user_meta( $user->ID, 'eventflow_batch', true ) : '';

$min_members = max( 1, (int) $activity->min_team_size );
$max_members = max( 1, (int) $activity->max_team_size );
$allow_solo  = ( 'solo' === $activity->participation_type || 'both' === $activity->participation_type );
$allow_team  = ( 'team' === $activity->participation_type || 'both' === $activity->participation_type );
$default_type = $allow_team ? 'team' : 'solo';
$fee_mode    = ! empty( $activity->fee_calculation ) ? $activity->fee_calculation : 'per_team';
$proof_req   = ! isset( $activity->payment_proof_required ) || 1 == $activity->payment_proof_required;
?>

<div class="eventflow-app ef-reg-form-container" id="ef-registration-app" 
	 data-catalog="<?php echo esc_attr( wp_json_encode( $activities_catalog ) ); ?>"
	 data-currency="<?php echo esc_attr( $currency ); ?>">

	<!-- Success State Container (Populated via AJAX on successful submission) -->
	<div id="ef-reg-success-view" class="ef-card ef-success-card" style="display: none;">
		<div class="ef-success-icon-wrap">
			<svg class="ef-success-svg" viewBox="0 0 52 52">
				<circle class="ef-success-circle" cx="26" cy="26" r="25" fill="none"/>
				<path class="ef-success-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
			</svg>
		</div>
		<h2 class="ef-success-title"><?php esc_html_e( 'Registration Successfully Submitted!', 'eventflow' ); ?></h2>
		<p class="ef-success-subtitle">
			<?php esc_html_e( 'Thank you for registering for SIBACOM. Your application has been logged and your payment proof is now Pending Verification.', 'eventflow' ); ?>
		</p>

		<div class="ef-reg-id-showcase">
			<span class="ef-text-muted ef-text-xs"><?php esc_html_e( 'YOUR OFFICIAL REGISTRATION ID', 'eventflow' ); ?></span>
			<h1 class="ef-reg-id-value" id="ef-success-reg-id">EF-2026-XXXXX</h1>
			<div class="ef-success-badges">
				<span class="ef-badge ef-badge-warning" id="ef-success-payment-badge"><?php esc_html_e( 'Payment: Pending Verification', 'eventflow' ); ?></span>
				<span class="ef-badge ef-badge-primary" id="ef-success-type-badge"><?php esc_html_e( 'Team Registration', 'eventflow' ); ?></span>
			</div>
		</div>

		<div class="ef-success-summary-box">
			<div class="ef-grid ef-grid-2">
				<div>
					<span class="ef-text-muted ef-text-xs"><?php esc_html_e( 'Competition Track', 'eventflow' ); ?></span>
					<strong id="ef-success-track">-</strong>
				</div>
				<div>
					<span class="ef-text-muted ef-text-xs"><?php esc_html_e( 'Candidate / Team Name', 'eventflow' ); ?></span>
					<strong id="ef-success-name">-</strong>
				</div>
				<div>
					<span class="ef-text-muted ef-text-xs"><?php esc_html_e( 'Primary Contact / Leader', 'eventflow' ); ?></span>
					<span id="ef-success-leader">-</span>
				</div>
				<div>
					<span class="ef-text-muted ef-text-xs"><?php esc_html_e( 'Total Amount Payable', 'eventflow' ); ?></span>
					<strong class="ef-fee" id="ef-success-fee">-</strong>
				</div>
			</div>
		</div>

		<div class="ef-success-actions">
			<a href="#" id="ef-success-receipt-btn" class="ef-btn ef-btn-primary ef-btn-lg" target="_blank">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
				<?php esc_html_e( 'View Printable Registration Voucher', 'eventflow' ); ?>
			</a>
			<a href="<?php echo esc_url( EventFlow_Shortcodes::get_dashboard_url() ); ?>" class="ef-btn ef-btn-secondary ef-btn-lg">
				<?php esc_html_e( 'Go to Participant Dashboard', 'eventflow' ); ?> &rarr;
			</a>
		</div>
	</div>

	<!-- Main Multi-Step Form Card -->
	<div class="ef-reg-form-card" id="ef-reg-form-wrap">

		<!-- Form Header Banner -->
		<div class="ef-reg-header-banner">
			<div class="ef-flex-between ef-header-flex">
				<div>
					<span class="ef-badge ef-badge-primary"><?php esc_html_e( 'SIBACOM 2026', 'eventflow' ); ?></span>
					<h2 class="ef-reg-main-title"><?php esc_html_e( 'Competition Registration', 'eventflow' ); ?></h2>
					<p class="ef-reg-subtitle">
						<?php esc_html_e( 'Select competition, fill participant details, and submit payment receipt.', 'eventflow' ); ?>
					</p>
				</div>
				<?php if ( ! is_user_logged_in() ) : ?>
					<div class="ef-login-prompt-pill">
						<span><?php esc_html_e( 'Already registered?', 'eventflow' ); ?></span>
						<a href="<?php echo esc_url( EventFlow_Shortcodes::get_auth_url() ); ?>" class="ef-link-bold"><?php esc_html_e( 'Sign In', 'eventflow' ); ?> &rarr;</a>
					</div>
				<?php else : ?>
					<div class="ef-user-badge-pill">
						<span class="ef-text-muted"><?php esc_html_e( 'Signed in:', 'eventflow' ); ?></span>
						<strong><?php echo esc_html( $user->display_name ); ?></strong>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Progress Steps Indicator -->
		<div class="ef-progress-steps">
			<div class="ef-step-item active" data-step="1">
				<div class="ef-step-circle">1</div>
				<span class="ef-step-label"><?php esc_html_e( 'Track & Mode', 'eventflow' ); ?></span>
			</div>
			<div class="ef-step-line"></div>
			<div class="ef-step-item" data-step="2">
				<div class="ef-step-circle">2</div>
				<span class="ef-step-label"><?php esc_html_e( 'Participants', 'eventflow' ); ?></span>
			</div>
			<div class="ef-step-line"></div>
			<div class="ef-step-item" data-step="3">
				<div class="ef-step-circle">3</div>
				<span class="ef-step-label"><?php esc_html_e( 'Fee & Accounts', 'eventflow' ); ?></span>
			</div>
			<div class="ef-step-line"></div>
			<div class="ef-step-item" data-step="4">
				<div class="ef-step-circle">4</div>
				<span class="ef-step-label"><?php esc_html_e( 'Receipt & Submit', 'eventflow' ); ?></span>
			</div>
		</div>

		<!-- Alert Notification Box -->
		<div class="ef-alert" id="ef-reg-alert" style="display: none;"></div>

		<form id="ef-registration-form" class="ef-form" method="post" enctype="multipart/form-data"
			  data-min-members="<?php echo esc_attr( $min_members ); ?>" 
			  data-max-members="<?php echo esc_attr( $max_members ); ?>" 
			  data-fee="<?php echo esc_attr( $activity->reg_fee ); ?>"
			  data-fee-calculation="<?php echo esc_attr( $fee_mode ); ?>"
			  data-proof-required="<?php echo $proof_req ? '1' : '0'; ?>">

			<!-- ============================================================ -->
			<!-- STEP 1: COMPETITION SELECTION & PARTICIPATION MODE            -->
			<!-- ============================================================ -->
			<div class="ef-form-section ef-step-section" id="ef-form-step-1">
				<div class="ef-section-head">
					<span class="ef-section-num">1</span>
					<div>
						<h3 class="ef-section-title"><?php esc_html_e( 'Select Competition & Registration Type', 'eventflow' ); ?></h3>
						<span class="ef-section-subtitle"><?php esc_html_e( 'Choose your competition track. Team limits and fees adapt dynamically.', 'eventflow' ); ?></span>
					</div>
				</div>

				<div class="ef-track-selector-block">
					<!-- Competition Selector -->
					<div class="ef-form-group" style="margin-bottom: 12px;">
						<label class="ef-label-bold" for="ef-activity-select">
							<?php esc_html_e( 'Competition Track', 'eventflow' ); ?> <span class="ef-req">*</span>
						</label>
						<select id="ef-activity-select" name="activity_id" class="ef-input ef-input-lg" required>
							<?php foreach ( $all_activities as $act ) : ?>
								<option value="<?php echo esc_attr( $act->id ); ?>" 
									<?php selected( $act->id, $activity->id ); ?>
									data-type="<?php echo esc_attr( $act->participation_type ); ?>"
									data-min="<?php echo esc_attr( $act->min_team_size ); ?>"
									data-max="<?php echo esc_attr( $act->max_team_size ); ?>"
									data-fee="<?php echo esc_attr( $act->reg_fee ); ?>"
									data-calculation="<?php echo esc_attr( ! empty( $act->fee_calculation ) ? $act->fee_calculation : 'per_team' ); ?>"
									data-proof="<?php echo ( ! isset( $act->payment_proof_required ) || 1 == $act->payment_proof_required ) ? '1' : '0'; ?>">
									<?php echo esc_html( $act->title ); ?> 
									(<?php echo floatval( $act->reg_fee ) > 0 ? sprintf( '%s %s', esc_html( $currency ), number_format( $act->reg_fee, 2 ) ) : esc_html__( 'Free', 'eventflow' ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<!-- Sleek Inline Track Metadata Strip -->
					<div class="ef-track-meta-strip">
						<span class="ef-meta-chip">
							<span class="ef-meta-chip-label"><?php esc_html_e( 'Mode:', 'eventflow' ); ?></span>
							<strong id="ef-display-mode-label" class="ef-tag ef-tag-primary"><?php echo esc_html( ucfirst( $activity->participation_type ) ); ?></strong>
						</span>
						<span class="ef-meta-chip">
							<span class="ef-meta-chip-label"><?php esc_html_e( 'Roster:', 'eventflow' ); ?></span>
							<strong id="ef-display-limits-label"><?php printf( esc_html__( '%d to %d Members', 'eventflow' ), $min_members, $max_members ); ?></strong>
						</span>
						<span class="ef-meta-chip">
							<span class="ef-meta-chip-label"><?php esc_html_e( 'Fee:', 'eventflow' ); ?></span>
							<strong id="ef-display-fee-label">
								<?php 
								$mode_text = ( 'per_person' === $fee_mode ) ? __( 'Per Person', 'eventflow' ) : __( 'Per Team', 'eventflow' );
								echo sprintf( '%s %s (%s)', esc_html( $currency ), number_format( $activity->reg_fee, 2 ), esc_html( $mode_text ) ); 
								?>
							</strong>
						</span>
					</div>
				</div>

				<!-- Participation Type Switcher (Only if both allowed) -->
				<div class="ef-form-group ef-type-selector-wrap" id="ef-type-selector-block" style="<?php echo ( $allow_solo && $allow_team ) ? '' : 'display: none;'; ?>">
					<label class="ef-label-bold"><?php esc_html_e( 'Choose Registration Mode', 'eventflow' ); ?> <span class="ef-req">*</span></label>
					<div class="ef-radio-cards">
						<label class="ef-radio-card ef-mode-team-card <?php echo 'team' === $default_type ? 'active' : ''; ?>">
							<input type="radio" name="participation_type" value="team" <?php checked( $default_type, 'team' ); ?>>
							<div class="ef-rc-content">
								<div class="ef-rc-title">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
									<strong><?php esc_html_e( 'Team Registration', 'eventflow' ); ?></strong>
								</div>
								<span class="ef-rc-desc" id="ef-rc-team-desc">
									<?php printf( esc_html__( 'Register with %d to %d members with a designated leader.', 'eventflow' ), $min_members, $max_members ); ?>
								</span>
							</div>
						</label>

						<label class="ef-radio-card ef-mode-solo-card <?php echo 'solo' === $default_type ? 'active' : ''; ?>">
							<input type="radio" name="participation_type" value="solo" <?php checked( $default_type, 'solo' ); ?>>
							<div class="ef-rc-content">
								<div class="ef-rc-title">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
									<strong><?php esc_html_e( 'Solo Registration', 'eventflow' ); ?></strong>
								</div>
								<span class="ef-rc-desc"><?php esc_html_e( 'Compete individually under your candidate profile.', 'eventflow' ); ?></span>
							</div>
						</label>
					</div>
				</div>

				<!-- Single Permitted Mode Hidden Field (if only solo or team is allowed) -->
				<div id="ef-fixed-type-pill" style="<?php echo ( $allow_solo && $allow_team ) ? 'display: none;' : ''; ?>; margin-top: 12px;">
					<input type="hidden" name="participation_type_fixed" id="ef-participation-type-fixed" value="<?php echo esc_attr( $activity->participation_type ); ?>">
					<div class="ef-alert ef-alert-info" style="margin: 0; padding: 10px 14px; font-size: 13px;">
						<span id="ef-fixed-type-msg">
							<?php 
							if ( 'team' === $activity->participation_type ) {
								printf( esc_html__( 'This track permits Team Registration only (%d to %d members).', 'eventflow' ), $min_members, $max_members );
							} else {
								esc_html_e( 'This track permits Individual / Solo Registration only.', 'eventflow' );
							}
							?>
						</span>
					</div>
				</div>
			</div>

			<!-- ============================================================ -->
			<!-- STEP 2: PARTICIPANT / TEAM MEMBER DETAILS                      -->
			<!-- ============================================================ -->
			<div class="ef-form-section ef-step-section" id="ef-form-step-2">
				<div class="ef-section-head">
					<span class="ef-section-num">2</span>
					<div>
						<h3 class="ef-section-title"><?php esc_html_e( 'Participant Details', 'eventflow' ); ?></h3>
						<span class="ef-section-subtitle"><?php esc_html_e( 'Enter complete academic and contact details for all participants.', 'eventflow' ); ?></span>
					</div>
				</div>

				<!-- A. SOLO CANDIDATE SECTION -->
				<div id="ef-solo-fields" class="ef-section-box" style="<?php echo 'solo' === $default_type ? '' : 'display:none;'; ?>">
					<div class="ef-member-card ef-solo-card">
						<div class="ef-grid ef-grid-2">
							<div class="ef-form-group">
								<label><?php esc_html_e( 'Full Name', 'eventflow' ); ?> <span class="ef-req">*</span></label>
								<input type="text" name="solo_name" class="ef-input" value="<?php echo esc_attr( $user->display_name ); ?>" placeholder="e.g. Muhammad Ali" <?php echo 'solo' === $default_type ? 'required' : ''; ?>>
							</div>
							<div class="ef-form-group">
								<label><?php esc_html_e( 'Email Address', 'eventflow' ); ?> <span class="ef-req">*</span></label>
								<input type="email" name="solo_email" class="ef-input" value="<?php echo esc_attr( $user->user_email ); ?>" placeholder="name@university.edu.pk" <?php echo 'solo' === $default_type ? 'required' : ''; ?>>
							</div>
						</div>

						<div class="ef-grid ef-grid-2">
							<div class="ef-form-group">
								<label><?php esc_html_e( 'Phone / WhatsApp Number', 'eventflow' ); ?> <span class="ef-req">*</span></label>
								<input type="tel" name="solo_phone" class="ef-input" value="<?php echo esc_attr( $user_phone ); ?>" placeholder="e.g. 03001234567" <?php echo 'solo' === $default_type ? 'required' : ''; ?>>
							</div>
							<div class="ef-form-group">
								<label><?php esc_html_e( 'University / Institution', 'eventflow' ); ?> <span class="ef-req">*</span></label>
								<input type="text" name="solo_university" class="ef-input" value="<?php echo esc_attr( $user_uni ); ?>" placeholder="e.g. Sukkur IBA University" <?php echo 'solo' === $default_type ? 'required' : ''; ?>>
							</div>
						</div>

						<div class="ef-grid ef-grid-3">
							<div class="ef-form-group">
								<label><?php esc_html_e( 'Department / Program', 'eventflow' ); ?> <span class="ef-req">*</span></label>
								<input type="text" name="solo_department" class="ef-input" value="<?php echo esc_attr( $user_dept ); ?>" placeholder="e.g. Computer Science" <?php echo 'solo' === $default_type ? 'required' : ''; ?>>
							</div>
							<div class="ef-form-group">
								<label><?php esc_html_e( 'Current Semester', 'eventflow' ); ?> <span class="ef-req">*</span></label>
								<input type="text" name="solo_semester" class="ef-input" value="<?php echo esc_attr( $user_sem ); ?>" placeholder="e.g. 6th" <?php echo 'solo' === $default_type ? 'required' : ''; ?>>
							</div>
							<div class="ef-form-group">
								<label><?php esc_html_e( 'Academic Year / Batch', 'eventflow' ); ?> <span class="ef-req">*</span></label>
								<input type="text" name="solo_academic_year" class="ef-input" value="<?php echo esc_attr( $user_batch ); ?>" placeholder="e.g. 2022-2026 / Batch 2022" <?php echo 'solo' === $default_type ? 'required' : ''; ?>>
							</div>
						</div>
					</div>
				</div>

				<!-- B. TEAM BUILDER SECTION -->
				<div id="ef-team-fields" class="ef-section-box" style="<?php echo 'team' === $default_type ? '' : 'display:none;'; ?>">
					
					<!-- Team Name -->
					<div class="ef-form-group ef-team-name-group">
						<label for="ef-team-name-input" class="ef-label-bold">
							<?php esc_html_e( 'Team Name', 'eventflow' ); ?> <span class="ef-req">*</span>
						</label>
						<input type="text" id="ef-team-name-input" name="team_name" class="ef-input ef-input-lg" 
							   placeholder="<?php esc_attr_e( 'e.g. Cyber Ninjas', 'eventflow' ); ?>" 
							   <?php echo 'team' === $default_type ? 'required' : ''; ?>>
					</div>

					<!-- Member Count & Add Member Action Bar -->
					<div class="ef-members-header ef-flex-between">
						<div class="ef-member-count-status">
							<span class="ef-roster-title"><?php esc_html_e( 'Team Roster', 'eventflow' ); ?></span>
							<strong class="ef-badge ef-badge-neutral">
								<span id="ef-current-count">1</span> / <span id="ef-display-max-count"><?php echo esc_html( $max_members ); ?></span>
							</strong>
							<span class="ef-text-muted ef-text-xs" id="ef-min-req-note">
								(<?php printf( esc_html__( 'Min %d', 'eventflow' ), $min_members ); ?>)
							</span>
						</div>

						<div class="ef-add-member-wrapper">
							<button type="button" id="ef-add-member-btn" class="ef-btn ef-btn-primary ef-btn-sm" <?php echo 1 >= $max_members ? 'disabled' : ''; ?>>
								<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
								<?php esc_html_e( 'Add Member', 'eventflow' ); ?>
							</button>
							<div id="ef-max-reached-msg" class="ef-badge ef-badge-warning" style="<?php echo 1 >= $max_members ? '' : 'display:none;'; ?>">
								<?php esc_html_e( 'Max Limit Reached', 'eventflow' ); ?>
							</div>
						</div>
					</div>

					<!-- Member Cards Stack -->
					<div id="ef-members-container" class="ef-members-stack">
						
						<!-- MEMBER #1: TEAM LEADER (ALWAYS FIRST & NON-REMOVABLE) -->
						<div class="ef-member-card ef-leader-card" data-index="0">
							<div class="ef-member-card-head ef-flex-between">
								<div class="ef-member-card-title">
									<span class="ef-badge ef-badge-primary">
										<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 3px; vertical-align: -1px;"><path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"/></svg>
										<?php esc_html_e( 'Member #1 (Team Leader)', 'eventflow' ); ?>
									</span>
								</div>
								<span class="ef-text-muted ef-text-xs"><?php esc_html_e( 'Leader (Primary Contact)', 'eventflow' ); ?></span>
							</div>

							<input type="hidden" name="members[0][is_leader]" value="1">

							<div class="ef-grid ef-grid-2">
								<div class="ef-form-group">
									<label><?php esc_html_e( 'Full Name', 'eventflow' ); ?> <span class="ef-req">*</span></label>
									<input type="text" name="members[0][full_name]" class="ef-input" value="<?php echo esc_attr( $user->display_name ); ?>" placeholder="e.g. Leader Full Name" <?php echo 'team' === $default_type ? 'required' : ''; ?>>
								</div>
								<div class="ef-form-group">
									<label><?php esc_html_e( 'Email Address', 'eventflow' ); ?> <span class="ef-req">*</span></label>
									<input type="email" name="members[0][email]" class="ef-input" value="<?php echo esc_attr( $user->user_email ); ?>" placeholder="leader@university.edu.pk" <?php echo 'team' === $default_type ? 'required' : ''; ?>>
								</div>
							</div>

							<div class="ef-grid ef-grid-2">
								<div class="ef-form-group">
									<label><?php esc_html_e( 'Phone / WhatsApp Number', 'eventflow' ); ?> <span class="ef-req">*</span></label>
									<input type="tel" name="members[0][contact_number]" class="ef-input" value="<?php echo esc_attr( $user_phone ); ?>" placeholder="e.g. 03001234567" <?php echo 'team' === $default_type ? 'required' : ''; ?>>
								</div>
								<div class="ef-form-group">
									<label><?php esc_html_e( 'University / Institution', 'eventflow' ); ?> <span class="ef-req">*</span></label>
									<input type="text" name="members[0][university]" class="ef-input" value="<?php echo esc_attr( $user_uni ); ?>" placeholder="e.g. Sukkur IBA University" <?php echo 'team' === $default_type ? 'required' : ''; ?>>
								</div>
							</div>

							<div class="ef-grid ef-grid-3">
								<div class="ef-form-group">
									<label><?php esc_html_e( 'Department / Program', 'eventflow' ); ?> <span class="ef-req">*</span></label>
									<input type="text" name="members[0][department]" class="ef-input" value="<?php echo esc_attr( $user_dept ); ?>" placeholder="e.g. Computer Science" <?php echo 'team' === $default_type ? 'required' : ''; ?>>
								</div>
								<div class="ef-form-group">
									<label><?php esc_html_e( 'Current Semester', 'eventflow' ); ?> <span class="ef-req">*</span></label>
									<input type="text" name="members[0][semester]" class="ef-input" value="<?php echo esc_attr( $user_sem ); ?>" placeholder="e.g. 6th" <?php echo 'team' === $default_type ? 'required' : ''; ?>>
								</div>
								<div class="ef-form-group">
									<label><?php esc_html_e( 'Academic Year / Batch', 'eventflow' ); ?> <span class="ef-req">*</span></label>
									<input type="text" name="members[0][academic_year]" class="ef-input" value="<?php echo esc_attr( $user_batch ); ?>" placeholder="e.g. 2022-2026" <?php echo 'team' === $default_type ? 'required' : ''; ?>>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Dynamic Custom Competition Fields Container -->
				<div id="ef-dynamic-custom-fields-container" class="ef-section-box" style="display: none; margin-top: 18px;">
					<h4 class="ef-section-heading" style="margin-bottom: 8px;">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 5px; vertical-align: -2px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
						<?php esc_html_e( 'Competition Specific Requirements', 'eventflow' ); ?>
					</h4>
					<div id="ef-dynamic-custom-fields-body" class="ef-grid ef-grid-2">
						<!-- Dynamically populated via JavaScript based on selected activity form_fields schema -->
					</div>
				</div>
			</div>

			<!-- Dynamic Member Card Template for Client-Side JS -->
			<template id="ef-member-template">
				<div class="ef-member-card ef-added-member" data-index="__INDEX__">
					<div class="ef-member-card-head ef-flex-between">
						<div class="ef-member-card-title">
							<span class="ef-badge ef-badge-neutral"><?php esc_html_e( 'Member #', 'eventflow' ); ?>__NUM__</span>
						</div>
						<button type="button" class="ef-btn ef-btn-danger ef-btn-xs ef-remove-member-btn" title="<?php esc_attr_e( 'Remove member', 'eventflow' ); ?>">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
							<?php esc_html_e( 'Remove', 'eventflow' ); ?>
						</button>
					</div>

					<input type="hidden" name="members[__INDEX__][is_leader]" value="0">

					<div class="ef-grid ef-grid-2">
						<div class="ef-form-group">
							<label><?php esc_html_e( 'Full Name', 'eventflow' ); ?> <span class="ef-req">*</span></label>
							<input type="text" name="members[__INDEX__][full_name]" class="ef-input" placeholder="Member full name" required>
						</div>
						<div class="ef-form-group">
							<label><?php esc_html_e( 'Email Address', 'eventflow' ); ?> <span class="ef-req">*</span></label>
							<input type="email" name="members[__INDEX__][email]" class="ef-input" placeholder="member@university.edu.pk" required>
						</div>
					</div>

					<div class="ef-grid ef-grid-2">
						<div class="ef-form-group">
							<label><?php esc_html_e( 'Phone / WhatsApp Number', 'eventflow' ); ?> <span class="ef-req">*</span></label>
							<input type="tel" name="members[__INDEX__][contact_number]" class="ef-input" placeholder="e.g. 03001234567" required>
						</div>
						<div class="ef-form-group">
							<label><?php esc_html_e( 'University / Institution', 'eventflow' ); ?> <span class="ef-req">*</span></label>
							<input type="text" name="members[__INDEX__][university]" class="ef-input" placeholder="University name" required>
						</div>
					</div>

					<div class="ef-grid ef-grid-3">
						<div class="ef-form-group">
							<label><?php esc_html_e( 'Department / Program', 'eventflow' ); ?> <span class="ef-req">*</span></label>
							<input type="text" name="members[__INDEX__][department]" class="ef-input" placeholder="Department" required>
						</div>
						<div class="ef-form-group">
							<label><?php esc_html_e( 'Current Semester', 'eventflow' ); ?> <span class="ef-req">*</span></label>
							<input type="text" name="members[__INDEX__][semester]" class="ef-input" placeholder="e.g. 4th" required>
						</div>
						<div class="ef-form-group">
							<label><?php esc_html_e( 'Academic Year / Batch', 'eventflow' ); ?> <span class="ef-req">*</span></label>
							<input type="text" name="members[__INDEX__][academic_year]" class="ef-input" placeholder="e.g. 2023-2027" required>
						</div>
					</div>
				</div>
			</template>

			<!-- ============================================================ -->
			<!-- STEP 3: LIVE FEE SUMMARY & PAYMENT ACCOUNT DETAILS             -->
			<!-- ============================================================ -->
			<div class="ef-form-section ef-step-section" id="ef-form-step-3">
				<div class="ef-section-head">
					<span class="ef-section-num">3</span>
					<div>
						<h3 class="ef-section-title"><?php esc_html_e( 'Fee & Payment Accounts', 'eventflow' ); ?></h3>
						<span class="ef-section-subtitle"><?php esc_html_e( 'Review your fee summary and transfer payment to an official receiving account.', 'eventflow' ); ?></span>
					</div>
				</div>

				<!-- Streamlined Compact Fee Summary Card -->
				<div class="ef-card ef-payment-summary-card ef-fee-summary-compact">
					<div class="ef-fee-summary-strip ef-payment-summary-strip">
						<div class="ef-summary-col">
							<span class="ef-text-muted ef-text-xs"><?php esc_html_e( 'Competition', 'eventflow' ); ?></span>
							<strong id="ef-sum-competition"><?php echo esc_html( $activity->title ); ?></strong>
						</div>
						<div class="ef-summary-col">
							<span class="ef-text-muted ef-text-xs"><?php esc_html_e( 'Registration Type', 'eventflow' ); ?></span>
							<strong id="ef-sum-type"><?php echo 'team' === $default_type ? esc_html__( 'Team', 'eventflow' ) : esc_html__( 'Solo', 'eventflow' ); ?></strong>
						</div>
						<div class="ef-summary-col">
							<span class="ef-text-muted ef-text-xs"><?php esc_html_e( 'Participants', 'eventflow' ); ?></span>
							<strong><span id="ef-sum-count">1</span> <span class="ef-text-muted ef-text-xs"><?php esc_html_e( 'registered', 'eventflow' ); ?></span></strong>
						</div>
						<!-- Hidden metrics maintained for JavaScript controller compatibility -->
						<span id="ef-sum-method" style="display:none;"><?php echo ( 'per_person' === $fee_mode ) ? esc_html__( 'Per Person', 'eventflow' ) : esc_html__( 'Per Team', 'eventflow' ); ?></span>
						<span id="ef-sum-base-fee" style="display:none;"><?php echo sprintf( '%s %s', esc_html( $currency ), number_format( $activity->reg_fee, 2 ) ); ?></span>
					</div>

					<div class="ef-total-payable-strip ef-fee-hero-highlight">
						<span class="ef-total-payable-label"><?php esc_html_e( 'Total Amount Payable:', 'eventflow' ); ?></span>
						<span class="ef-total-payable-val" id="ef-sum-total-amount">
							<?php echo sprintf( '%s %s', esc_html( $currency ), number_format( $activity->reg_fee, 2 ) ); ?>
						</span>
					</div>
				</div>

				<!-- Official Receiving Payment Accounts Grid -->
				<div class="ef-payment-accounts-block">
					<div class="ef-accounts-header-row">
						<h4 class="ef-accounts-title"><?php esc_html_e( 'Official Receiving Accounts', 'eventflow' ); ?></h4>
						<span class="ef-accounts-subtitle"><?php esc_html_e( 'Transfer fee to any enabled method below', 'eventflow' ); ?></span>
					</div>

					<div class="ef-grid ef-accounts-grid">
						<!-- Bank Transfer Card -->
						<?php if ( ! empty( $enabled_methods['bank_transfer'] ) && ! empty( $enabled_methods['bank_transfer']['number'] ) ) : ?>
							<div class="ef-account-card ef-bank-card">
								<div class="ef-acc-card-head">
									<span class="ef-badge ef-badge-primary ef-acc-badge">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: -2px;"><path d="M3 21h18M3 10h18M5 10v11M19 10v11M9 10v11M15 10v11M12 2 2 7h20L12 2z"/></svg>
										<?php echo ! empty( $enabled_methods['bank_transfer']['bank_name'] ) ? esc_html( $enabled_methods['bank_transfer']['bank_name'] ) : esc_html__( 'Bank Transfer (IBFT)', 'eventflow' ); ?>
									</span>
								</div>
								<div class="ef-acc-details">
									<div class="ef-acc-field">
										<span class="ef-text-muted"><?php esc_html_e( 'Account Title:', 'eventflow' ); ?></span>
										<strong><?php echo esc_html( $enabled_methods['bank_transfer']['title'] ); ?></strong>
									</div>
									<div class="ef-acc-field ef-copyable-item">
										<span class="ef-text-muted"><?php esc_html_e( 'Account Number:', 'eventflow' ); ?></span>
										<div class="ef-copy-wrap">
											<code id="ef-acc-num-code"><?php echo esc_html( $enabled_methods['bank_transfer']['number'] ); ?></code>
											<button type="button" class="ef-copy-btn" data-clipboard-target="#ef-acc-num-code" title="<?php esc_attr_e( 'Copy account number', 'eventflow' ); ?>">
												<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
												<span><?php esc_html_e( 'Copy', 'eventflow' ); ?></span>
											</button>
										</div>
									</div>
									<?php if ( ! empty( $enabled_methods['bank_transfer']['iban'] ) ) : ?>
										<div class="ef-acc-field ef-copyable-item">
											<span class="ef-text-muted"><?php esc_html_e( 'IBAN:', 'eventflow' ); ?></span>
											<div class="ef-copy-wrap">
												<code id="ef-iban-code"><?php echo esc_html( $enabled_methods['bank_transfer']['iban'] ); ?></code>
												<button type="button" class="ef-copy-btn" data-clipboard-target="#ef-iban-code" title="<?php esc_attr_e( 'Copy IBAN', 'eventflow' ); ?>">
													<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
													<span><?php esc_html_e( 'Copy', 'eventflow' ); ?></span>
												</button>
											</div>
										</div>
									<?php endif; ?>
								</div>
							</div>
						<?php endif; ?>

						<!-- JazzCash Card -->
						<?php if ( ! empty( $enabled_methods['jazzcash'] ) && ! empty( $enabled_methods['jazzcash']['number'] ) ) : ?>
							<div class="ef-account-card ef-jazzcash-card">
								<div class="ef-acc-card-head">
									<span class="ef-badge ef-badge-warning ef-acc-badge">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: -2px;"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
										<?php esc_html_e( 'JazzCash', 'eventflow' ); ?>
									</span>
								</div>
								<div class="ef-acc-details">
									<div class="ef-acc-field">
										<span class="ef-text-muted"><?php esc_html_e( 'Account Title:', 'eventflow' ); ?></span>
										<strong><?php echo esc_html( $enabled_methods['jazzcash']['title'] ); ?></strong>
									</div>
									<div class="ef-acc-field ef-copyable-item">
										<span class="ef-text-muted"><?php esc_html_e( 'Mobile Account:', 'eventflow' ); ?></span>
										<div class="ef-copy-wrap">
											<code id="ef-jazzcash-code"><?php echo esc_html( $enabled_methods['jazzcash']['number'] ); ?></code>
											<button type="button" class="ef-copy-btn" data-clipboard-target="#ef-jazzcash-code" title="<?php esc_attr_e( 'Copy JazzCash number', 'eventflow' ); ?>">
												<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
												<span><?php esc_html_e( 'Copy', 'eventflow' ); ?></span>
											</button>
										</div>
									</div>
								</div>
							</div>
						<?php endif; ?>

						<!-- Easypaisa Card -->
						<?php if ( ! empty( $enabled_methods['easypaisa'] ) && ! empty( $enabled_methods['easypaisa']['number'] ) ) : ?>
							<div class="ef-account-card ef-easypaisa-card">
								<div class="ef-acc-card-head">
									<span class="ef-badge ef-badge-success ef-acc-badge">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: -2px;"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
										<?php esc_html_e( 'Easypaisa', 'eventflow' ); ?>
									</span>
								</div>
								<div class="ef-acc-details">
									<div class="ef-acc-field">
										<span class="ef-text-muted"><?php esc_html_e( 'Account Title:', 'eventflow' ); ?></span>
										<strong><?php echo esc_html( $enabled_methods['easypaisa']['title'] ); ?></strong>
									</div>
									<div class="ef-acc-field ef-copyable-item">
										<span class="ef-text-muted"><?php esc_html_e( 'Mobile Account:', 'eventflow' ); ?></span>
										<div class="ef-copy-wrap">
											<code id="ef-easypaisa-code"><?php echo esc_html( $enabled_methods['easypaisa']['number'] ); ?></code>
											<button type="button" class="ef-copy-btn" data-clipboard-target="#ef-easypaisa-code" title="<?php esc_attr_e( 'Copy Easypaisa number', 'eventflow' ); ?>">
												<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
												<span><?php esc_html_e( 'Copy', 'eventflow' ); ?></span>
											</button>
										</div>
									</div>
								</div>
							</div>
						<?php endif; ?>
					</div>

					<!-- Short, Numbered, Clean Payment Guide -->
					<div class="ef-payment-guide-box">
						<div class="ef-guide-header">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
							<strong><?php esc_html_e( 'Payment Guide:', 'eventflow' ); ?></strong>
						</div>
						<div class="ef-guide-steps-grid">
							<div class="ef-guide-step-item">
								<span class="ef-guide-step-num">1</span>
								<span class="ef-guide-step-desc"><?php esc_html_e( 'Transfer the exact registration fee to any displayed official receiving account.', 'eventflow' ); ?></span>
							</div>
							<div class="ef-guide-step-item">
								<span class="ef-guide-step-num">2</span>
								<span class="ef-guide-step-desc"><?php esc_html_e( 'Save your payment receipt or transaction screenshot.', 'eventflow' ); ?></span>
							</div>
							<div class="ef-guide-step-item">
								<span class="ef-guide-step-num">3</span>
								<span class="ef-guide-step-desc"><?php esc_html_e( 'Upload the payment proof in the next step.', 'eventflow' ); ?></span>
							</div>
							<div class="ef-guide-step-item">
								<span class="ef-guide-step-num">4</span>
								<span class="ef-guide-step-desc"><?php esc_html_e( 'Submit your registration for verification.', 'eventflow' ); ?></span>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- ============================================================ -->
			<!-- STEP 4: PAYMENT DETAILS & RECEIPT UPLOAD                       -->
			<!-- ============================================================ -->
			<div class="ef-form-section ef-step-section" id="ef-payment-proof-section" data-step="4">
				<div class="ef-section-head">
					<span class="ef-section-num">4</span>
					<div>
						<h3 class="ef-section-title"><?php esc_html_e( 'Payment Details & Receipt Upload', 'eventflow' ); ?></h3>
						<span class="ef-section-subtitle"><?php esc_html_e( 'Select payment method and upload clear screenshot or PDF receipt.', 'eventflow' ); ?></span>
					</div>
				</div>

				<div class="ef-grid ef-grid-2">
					<div class="ef-form-group">
						<label for="ef-pay-method"><?php esc_html_e( 'Payment Method Used', 'eventflow' ); ?> <span class="ef-req">*</span></label>
						<select id="ef-pay-method" name="payment_method" class="ef-input" required>
							<option value=""><?php esc_html_e( 'Select Payment Method', 'eventflow' ); ?></option>
							<?php if ( ! empty( $enabled_methods['bank_transfer'] ) ) : ?>
								<option value="<?php echo esc_attr( $enabled_methods['bank_transfer']['label'] ); ?>"><?php echo esc_html( $enabled_methods['bank_transfer']['label'] ); ?></option>
							<?php endif; ?>
							<?php if ( ! empty( $enabled_methods['jazzcash'] ) ) : ?>
								<option value="JazzCash"><?php esc_html_e( 'JazzCash', 'eventflow' ); ?></option>
							<?php endif; ?>
							<?php if ( ! empty( $enabled_methods['easypaisa'] ) ) : ?>
								<option value="Easypaisa"><?php esc_html_e( 'Easypaisa', 'eventflow' ); ?></option>
							<?php endif; ?>
							<option value="Raast Instant"><?php esc_html_e( 'Raast Instant Transfer', 'eventflow' ); ?></option>
							<option value="Other"><?php esc_html_e( 'Other Bank / Cash Deposit', 'eventflow' ); ?></option>
						</select>
					</div>

					<div class="ef-form-group">
						<label for="ef-pay-date"><?php esc_html_e( 'Payment Date', 'eventflow' ); ?> <span class="ef-req">*</span></label>
						<input type="date" id="ef-pay-date" name="payment_date" class="ef-input" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
					</div>
				</div>

				<!-- Hidden Amount field that stays synced with server-side calculation -->
				<input type="hidden" name="amount" id="ef-form-amount" value="<?php echo esc_attr( $activity->reg_fee ); ?>">

				<!-- Drag-and-Drop Receipt Upload Dropzone -->
				<div class="ef-form-group" style="margin-top: 14px;">
					<label class="ef-label-bold">
						<?php esc_html_e( 'Payment Screenshot or Receipt (JPG, PNG, PDF — Max 5 MB)', 'eventflow' ); ?> 
						<span class="ef-req">*</span>
					</label>
					
					<div class="ef-file-dropzone ef-dropzone" id="ef-reg-dropzone">
						<input type="file" id="ef-reg-proof-file" name="payment_proof" accept="image/jpeg,image/png,application/pdf" class="ef-file-input" required>
						
						<div class="ef-dropzone-content">
							<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
							<strong><?php esc_html_e( 'Drag & drop payment receipt or click to browse', 'eventflow' ); ?></strong>
							<span class="ef-text-muted ef-text-xs"><?php esc_html_e( 'Accepted formats: JPG, JPEG, PNG, PDF up to 5 MB', 'eventflow' ); ?></span>
						</div>

						<div class="ef-file-preview-wrap" style="display: none;">
							<div class="ef-preview-thumb-wrap" style="display: none;">
								<img id="ef-img-thumb" src="" alt="Receipt Preview" class="ef-img-thumb">
							</div>
							<div class="ef-preview-info">
								<span class="ef-file-name" id="ef-preview-name"></span>
								<span class="ef-file-size" id="ef-preview-size"></span>
							</div>
							<div class="ef-preview-actions">
								<button type="button" class="ef-btn ef-btn-secondary ef-btn-xs" id="ef-replace-file-btn">
									<?php esc_html_e( 'Replace Receipt', 'eventflow' ); ?>
								</button>
								<button type="button" class="ef-btn ef-btn-danger ef-btn-xs" id="ef-remove-file-btn">
									&times; <?php esc_html_e( 'Remove', 'eventflow' ); ?>
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- ============================================================ -->
			<!-- STEP 5: REVIEW, DECLARATION & SUBMISSION                      -->
			<!-- ============================================================ -->
			<div class="ef-form-section ef-step-section ef-declaration-section">
				<div class="ef-declaration-box">
					<label class="ef-checkbox-label">
						<input type="checkbox" name="ef_declaration" id="ef-declaration-checkbox" required>
						<span>
							<strong><?php esc_html_e( 'Official Declaration:', 'eventflow' ); ?></strong>
							<?php esc_html_e( 'I hereby declare that all participant details and payment information submitted above are true and complete. Submitting fake receipts will lead to immediate disqualification.', 'eventflow' ); ?>
						</span>
					</label>
				</div>

				<div class="ef-form-actions ef-flex-between">
					<div class="ef-submission-status-note">
						<span class="ef-badge ef-badge-neutral">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: -2px;"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
							<?php esc_html_e( 'Status after submission: Pending Verification', 'eventflow' ); ?>
						</span>
					</div>

					<button type="submit" class="ef-btn ef-btn-primary ef-btn-lg ef-submit-btn" id="ef-reg-submit-btn">
						<span class="ef-btn-text">
							<?php esc_html_e( 'Submit Official Registration', 'eventflow' ); ?> &rarr;
						</span>
						<span class="ef-spinner ef-btn-spinner" style="display: none;"></span>
					</button>
				</div>
			</div>
		</form>
	</div>
</div>
