<?php
/**
 * EventFlow Admin View: Events Management & SIBACOM EVENT Configuration
 *
 * Comprehensive Super Admin Event Configuration with 4 tabs:
 * 1. General Event Settings (official name, dates, times, venue, university, dept, contacts)
 * 2. Registration Configuration (solo/team toggles, min/max team size, caps, allow edit, allow re-upload)
 * 3. Dynamic Fee Configuration (pricing models, rates, discounts, min payable, currency)
 * 4. Payment Gateway Accounts & Settings (Bank, JazzCash, Easypaisa, Raast, instructions, deadline)
 *
 * @package EventFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$events = EventFlow_Event_Manager::get_all_events();
$active_event = EventFlow_Event_Manager::get_active_event();

$edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : ( $active_event ? absint( $active_event->id ) : 0 );
$edit_event = $edit_id ? EventFlow_Event_Manager::get_event( $edit_id ) : $active_event;
$currency = $edit_event && ! empty( $edit_event->currency ) ? esc_html( $edit_event->currency ) : 'PKR';
?>

<div class="wrap ef-admin-wrap">
	<div class="ef-flex-between" style="margin-bottom: 16px; align-items: flex-start;">
		<div>
			<h1 class="wp-heading-inline" style="font-weight: 800; color: #0F172A;">
				<?php esc_html_e( 'SIBACOM EVENT – Master Event Configuration', 'eventflow' ); ?>
			</h1>
			<p class="ef-text-muted ef-text-sm" style="margin-top: 4px;">
				<?php esc_html_e( 'Configure official event branding, schedules, registration limits, dynamic pricing formulas, and payment receiving accounts without editing code.', 'eventflow' ); ?>
			</p>
		</div>
		<div style="display: flex; gap: 10px; align-items: center;">
			<?php if ( $edit_event && 'active' === $edit_event->status ) : ?>
				<span class="ef-badge ef-badge-success" style="padding: 6px 12px; font-weight: 600;">
					● <?php esc_html_e( 'Event Status: OPEN', 'eventflow' ); ?>
				</span>
			<?php else : ?>
				<span class="ef-badge ef-badge-warning" style="padding: 6px 12px; font-weight: 600;">
					<?php echo esc_html( strtoupper( $edit_event ? $edit_event->status : 'Draft' ) ); ?>
				</span>
			<?php endif; ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-events' ) ); ?>" class="button">
				<span class="dashicons dashicons-plus-alt2" style="vertical-align: middle;"></span> <?php esc_html_e( 'New Event', 'eventflow' ); ?>
			</a>
		</div>
	</div>
	<hr class="wp-header-end">

	<div class="ef-grid ef-grid-3" style="margin-top: 20px; align-items: start;">
		<!-- Main Configuration Form (Left 2 cols) -->
		<div style="grid-column: span 2;">
			<form id="ef-admin-event-form" class="ef-form">
				<input type="hidden" name="event_id" value="<?php echo esc_attr( $edit_event ? $edit_event->id : 0 ); ?>">

				<!-- Configuration Tabs Navigation -->
				<div class="ef-event-tabs-bar" style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 2px solid #E2E8F0; padding-bottom: 2px;">
					<button type="button" class="ef-event-tab-btn active" data-tab="general" style="padding: 10px 18px; border: none; background: #0D9488; color: #fff; font-weight: 700; border-radius: 6px 6px 0 0; cursor: pointer;">
						<span class="dashicons dashicons-calendar-alt" style="vertical-align: middle; margin-top: -2px;"></span> <?php esc_html_e( '1. General Settings', 'eventflow' ); ?>
					</button>
					<button type="button" class="ef-event-tab-btn" data-tab="registration" style="padding: 10px 18px; border: none; background: #F1F5F9; color: #475569; font-weight: 600; border-radius: 6px 6px 0 0; cursor: pointer;">
						<span class="dashicons dashicons-groups" style="vertical-align: middle; margin-top: -2px;"></span> <?php esc_html_e( '2. Registration Rules', 'eventflow' ); ?>
					</button>
					<button type="button" class="ef-event-tab-btn" data-tab="pricing" style="padding: 10px 18px; border: none; background: #F1F5F9; color: #475569; font-weight: 600; border-radius: 6px 6px 0 0; cursor: pointer;">
						<span class="dashicons dashicons-money-alt" style="vertical-align: middle; margin-top: -2px;"></span> <?php esc_html_e( '3. Dynamic Pricing Model', 'eventflow' ); ?>
					</button>
					<button type="button" class="ef-event-tab-btn" data-tab="payment" style="padding: 10px 18px; border: none; background: #F1F5F9; color: #475569; font-weight: 600; border-radius: 6px 6px 0 0; cursor: pointer;">
						<span class="dashicons dashicons-shield" style="vertical-align: middle; margin-top: -2px;"></span> <?php esc_html_e( '4. Payment Accounts', 'eventflow' ); ?>
					</button>
				</div>

				<!-- ============================================================== -->
				<!-- TAB 1: GENERAL EVENT SETTINGS -->
				<!-- ============================================================== -->
				<div class="ef-event-tab-content active" id="ef-tab-general">
					<div class="ef-card" style="margin-bottom: 24px;">
						<div class="ef-card-header">
							<h3><?php esc_html_e( 'General Event Identity & Schedule', 'eventflow' ); ?></h3>
							<p class="ef-card-subtitle"><?php esc_html_e( 'Basic event credentials, university details, and operational schedule.', 'eventflow' ); ?></p>
						</div>

						<div class="ef-form-group">
							<label><strong><?php esc_html_e( 'Official Event Name', 'eventflow' ); ?></strong> <span class="ef-req">*</span></label>
							<input type="text" name="title" class="ef-input" value="<?php echo esc_attr( $edit_event ? $edit_event->title : 'SIBACOM EVENT' ); ?>" required placeholder="SIBACOM EVENT">
							<p class="description"><?php esc_html_e( 'The official event title used across registration badges, email templates, and portals.', 'eventflow' ); ?></p>
						</div>

						<div class="ef-form-group">
							<label><strong><?php esc_html_e( 'Event Description & Overview', 'eventflow' ); ?></strong></label>
							<textarea name="description" class="ef-input" rows="3" placeholder="<?php esc_attr_e( 'Annual National Technology Olympiad & Developer Competitions organized by Sukkur IBA University.', 'eventflow' ); ?>"><?php echo esc_textarea( $edit_event ? $edit_event->description : '' ); ?></textarea>
						</div>

						<div class="ef-grid ef-grid-3">
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Venue', 'eventflow' ); ?></strong></label>
								<input type="text" name="venue" class="ef-input" value="<?php echo esc_attr( $edit_event && ! empty( $edit_event->venue ) ? $edit_event->venue : 'Sukkur IBA University Main Campus' ); ?>">
							</div>
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'University / Institution', 'eventflow' ); ?></strong></label>
								<input type="text" name="university_name" class="ef-input" value="<?php echo esc_attr( $edit_event && ! empty( $edit_event->university_name ) ? $edit_event->university_name : 'Sukkur IBA University' ); ?>">
							</div>
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Organizing Department / Society', 'eventflow' ); ?></strong></label>
								<input type="text" name="organizing_dept" class="ef-input" value="<?php echo esc_attr( $edit_event && ! empty( $edit_event->organizing_dept ) ? $edit_event->organizing_dept : 'Department of Computer Science' ); ?>">
							</div>
						</div>

						<div class="ef-grid ef-grid-3">
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Event Start Date', 'eventflow' ); ?></strong></label>
								<input type="date" name="start_date" class="ef-input" value="<?php echo esc_attr( $edit_event && $edit_event->start_date ? date( 'Y-m-d', strtotime( $edit_event->start_date ) ) : '' ); ?>">
							</div>
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Event End Date', 'eventflow' ); ?></strong></label>
								<input type="date" name="end_date" class="ef-input" value="<?php echo esc_attr( $edit_event && $edit_event->end_date ? date( 'Y-m-d', strtotime( $edit_event->end_date ) ) : '' ); ?>">
							</div>
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Event Lifecycle Status', 'eventflow' ); ?></strong></label>
								<select name="status" class="ef-input">
									<option value="draft" <?php selected( $edit_event && 'draft' === $edit_event->status ); ?>><?php esc_html_e( 'Draft (Hidden from Public)', 'eventflow' ); ?></option>
									<option value="upcoming" <?php selected( $edit_event && 'upcoming' === $edit_event->status ); ?>><?php esc_html_e( 'Upcoming (Announcement Only)', 'eventflow' ); ?></option>
									<option value="active" <?php selected( $edit_event && ( 'active' === $edit_event->status || 'open' === $edit_event->status ) ); ?>><?php esc_html_e( 'Open (Registrations Active)', 'eventflow' ); ?></option>
									<option value="closed" <?php selected( $edit_event && 'closed' === $edit_event->status ); ?>><?php esc_html_e( 'Closed (Registrations Halted)', 'eventflow' ); ?></option>
									<option value="completed" <?php selected( $edit_event && 'completed' === $edit_event->status ); ?>><?php esc_html_e( 'Completed', 'eventflow' ); ?></option>
								</select>
							</div>
						</div>

						<div class="ef-grid ef-grid-3">
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Start Time', 'eventflow' ); ?></strong></label>
								<input type="text" name="start_time" class="ef-input" value="<?php echo esc_attr( $edit_event && ! empty( $edit_event->start_time ) ? $edit_event->start_time : '09:00 AM' ); ?>" placeholder="09:00 AM">
							</div>
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'End Time', 'eventflow' ); ?></strong></label>
								<input type="text" name="end_time" class="ef-input" value="<?php echo esc_attr( $edit_event && ! empty( $edit_event->end_time ) ? $edit_event->end_time : '05:00 PM' ); ?>" placeholder="05:00 PM">
							</div>
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Reporting / Check-in Time', 'eventflow' ); ?></strong></label>
								<input type="text" name="reporting_time" class="ef-input" value="<?php echo esc_attr( $edit_event && ! empty( $edit_event->reporting_time ) ? $edit_event->reporting_time : '08:30 AM' ); ?>" placeholder="08:30 AM">
							</div>
						</div>

						<div class="ef-grid ef-grid-3">
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Official Contact Email', 'eventflow' ); ?></strong></label>
								<input type="email" name="contact_email" class="ef-input" value="<?php echo esc_attr( $edit_event && ! empty( $edit_event->contact_email ) ? $edit_event->contact_email : 'info@portal.sibacom.tech' ); ?>">
							</div>
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Official WhatsApp / Contact Phone', 'eventflow' ); ?></strong></label>
								<input type="tel" name="contact_phone" class="ef-input" value="<?php echo esc_attr( $edit_event && ! empty( $edit_event->contact_phone ) ? $edit_event->contact_phone : '+92 300 1234567' ); ?>">
							</div>
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Official Portal / Website URL', 'eventflow' ); ?></strong></label>
								<input type="url" name="website_url" class="ef-input" value="<?php echo esc_attr( $edit_event && ! empty( $edit_event->website_url ) ? $edit_event->website_url : home_url() ); ?>">
							</div>
						</div>

						<div class="ef-grid ef-grid-2">
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Registration Opening Date & Time', 'eventflow' ); ?></strong></label>
								<input type="datetime-local" name="reg_start_date" class="ef-input" value="<?php echo esc_attr( $edit_event && $edit_event->reg_start_date ? date( 'Y-m-d\TH:i', strtotime( $edit_event->reg_start_date ) ) : '' ); ?>">
							</div>
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Registration Closing Deadline', 'eventflow' ); ?></strong></label>
								<input type="datetime-local" name="reg_end_date" class="ef-input" value="<?php echo esc_attr( $edit_event && $edit_event->reg_end_date ? date( 'Y-m-d\TH:i', strtotime( $edit_event->reg_end_date ) ) : '' ); ?>">
							</div>
						</div>

						<div class="ef-grid ef-grid-2">
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Event Logo URL', 'eventflow' ); ?></strong></label>
								<div class="ef-upload-field-wrap">
									<input type="url" name="logo_url" id="ef-event-logo-url" class="ef-input" value="<?php echo esc_attr( $edit_event ? $edit_event->logo_url : '' ); ?>">
									<button type="button" class="button ef-media-upload-btn" data-target="#ef-event-logo-url"><?php esc_html_e( 'Select Media', 'eventflow' ); ?></button>
								</div>
							</div>
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Event Banner Image URL', 'eventflow' ); ?></strong></label>
								<div class="ef-upload-field-wrap">
									<input type="url" name="banner_url" id="ef-event-banner-url" class="ef-input" value="<?php echo esc_attr( $edit_event ? $edit_event->banner_url : '' ); ?>">
									<button type="button" class="button ef-media-upload-btn" data-target="#ef-event-banner-url"><?php esc_html_e( 'Select Media', 'eventflow' ); ?></button>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- ============================================================== -->
				<!-- TAB 2: REGISTRATION CONFIGURATION & LIMITS -->
				<!-- ============================================================== -->
				<div class="ef-event-tab-content" id="ef-tab-registration" style="display: none;">
					<div class="ef-card" style="margin-bottom: 24px;">
						<div class="ef-card-header">
							<h3><?php esc_html_e( 'Registration Types, Caps & Permissions', 'eventflow' ); ?></h3>
							<p class="ef-card-subtitle"><?php esc_html_e( 'All limits configured here are strictly validated on both frontend and backend.', 'eventflow' ); ?></p>
						</div>

						<div class="ef-grid ef-grid-2" style="margin-bottom: 16px;">
							<div class="ef-form-group" style="background: #F8FAFC; padding: 14px; border-radius: 8px; border: 1px solid #E2E8F0;">
								<label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 700;">
									<input type="checkbox" name="allow_solo" value="1" <?php checked( ! isset( $edit_event->allow_solo ) || 1 == $edit_event->allow_solo ); ?>>
									<span><?php esc_html_e( 'Enable Individual / Solo Registration', 'eventflow' ); ?></span>
								</label>
								<p class="description" style="margin-top: 6px; margin-left: 24px;">
									<?php esc_html_e( 'Allows participants to register individually without creating a team.', 'eventflow' ); ?>
								</p>
							</div>

							<div class="ef-form-group" style="background: #F8FAFC; padding: 14px; border-radius: 8px; border: 1px solid #E2E8F0;">
								<label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 700;">
									<input type="checkbox" name="allow_team" value="1" <?php checked( ! isset( $edit_event->allow_team ) || 1 == $edit_event->allow_team ); ?>>
									<span><?php esc_html_e( 'Enable Team Registration', 'eventflow' ); ?></span>
								</label>
								<p class="description" style="margin-top: 6px; margin-left: 24px;">
									<?php esc_html_e( 'Allows participants to register as a team with a team leader and multiple members.', 'eventflow' ); ?>
								</p>
							</div>
						</div>

						<div class="ef-grid ef-grid-2">
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Minimum Team Members', 'eventflow' ); ?></strong> <span class="ef-req">*</span></label>
								<input type="number" name="min_team_size" min="1" max="20" class="ef-input" value="<?php echo esc_attr( $edit_event && isset( $edit_event->min_team_size ) ? $edit_event->min_team_size : 2 ); ?>" required>
								<p class="description"><?php esc_html_e( 'Submissions with fewer members than this will be rejected by backend validation.', 'eventflow' ); ?></p>
							</div>

							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Maximum Team Members', 'eventflow' ); ?></strong> <span class="ef-req">*</span></label>
								<input type="number" name="max_team_size" min="1" max="20" class="ef-input" value="<?php echo esc_attr( $edit_event && isset( $edit_event->max_team_size ) ? $edit_event->max_team_size : 5 ); ?>" required>
								<p class="description"><?php esc_html_e( 'Submissions exceeding this member count will be rejected by backend validation.', 'eventflow' ); ?></p>
							</div>
						</div>

						<div class="ef-grid ef-grid-3">
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Max Total Registrations (Event Cap)', 'eventflow' ); ?></strong></label>
								<input type="number" name="max_total_registrations" min="0" class="ef-input" value="<?php echo esc_attr( $edit_event && isset( $edit_event->max_total_registrations ) ? $edit_event->max_total_registrations : 500 ); ?>">
								<p class="description"><?php esc_html_e( '0 for unlimited total registrations across all activities.', 'eventflow' ); ?></p>
							</div>

							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Max Team Registrations Cap', 'eventflow' ); ?></strong></label>
								<input type="number" name="max_team_registrations" min="0" class="ef-input" value="<?php echo esc_attr( $edit_event && isset( $edit_event->max_team_registrations ) ? $edit_event->max_team_registrations : 200 ); ?>">
								<p class="description"><?php esc_html_e( 'Maximum allowed team slots across the event.', 'eventflow' ); ?></p>
							</div>

							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Min Individual Registrations', 'eventflow' ); ?></strong></label>
								<input type="number" name="min_solo_registrations" min="0" class="ef-input" value="<?php echo esc_attr( $edit_event && isset( $edit_event->min_solo_registrations ) ? $edit_event->min_solo_registrations : 1 ); ?>">
							</div>
						</div>

						<div class="ef-grid ef-grid-2" style="margin-top: 10px;">
							<div class="ef-form-group" style="background: #F8FAFC; padding: 12px; border-radius: 6px;">
								<label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 600;">
									<input type="checkbox" name="allow_reg_edit" value="1" <?php checked( ! isset( $edit_event->allow_reg_edit ) || 1 == $edit_event->allow_reg_edit ); ?>>
									<span><?php esc_html_e( 'Allow Registration Editing before Verification', 'eventflow' ); ?></span>
								</label>
							</div>

							<div class="ef-form-group" style="background: #F8FAFC; padding: 12px; border-radius: 6px;">
								<label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 600;">
									<input type="checkbox" name="allow_proof_reupload" value="1" <?php checked( ! isset( $edit_event->allow_proof_reupload ) || 1 == $edit_event->allow_proof_reupload ); ?>>
									<span><?php esc_html_e( 'Allow Payment Proof Re-upload on Request', 'eventflow' ); ?></span>
								</label>
							</div>
						</div>
					</div>
				</div>

				<!-- ============================================================== -->
				<!-- TAB 3: DYNAMIC REGISTRATION FEE CONFIGURATION -->
				<!-- ============================================================== -->
				<div class="ef-event-tab-content" id="ef-tab-pricing" style="display: none;">
					<div class="ef-card" style="margin-bottom: 24px;">
						<div class="ef-card-header">
							<h3><?php esc_html_e( 'Dynamic Pricing Model & Fee Breakdown', 'eventflow' ); ?></h3>
							<p class="ef-card-subtitle"><?php esc_html_e( 'Calculates payable fees dynamically. An immutable JSON snapshot is saved with each submission.', 'eventflow' ); ?></p>
						</div>

						<div class="ef-grid ef-grid-2">
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Active Team Pricing Model', 'eventflow' ); ?></strong> <span class="ef-req">*</span></label>
								<select name="pricing_model" id="ef-pricing-model-select" class="ef-input" style="font-weight: 700; color: #0F766E;">
									<option value="per_participant" <?php selected( ! $edit_event || 'per_participant' === $edit_event->pricing_model ); ?>>
										<?php esc_html_e( '1. Per Participant (Member Count × Rate per Participant)', 'eventflow' ); ?>
									</option>
									<option value="fixed_team" <?php selected( $edit_event && 'fixed_team' === $edit_event->pricing_model ); ?>>
										<?php esc_html_e( '2. Fixed Fee Per Team (Flat rate regardless of team size)', 'eventflow' ); ?>
									</option>
									<option value="individual" <?php selected( $edit_event && 'individual' === $edit_event->pricing_model ); ?>>
										<?php esc_html_e( '3. Individual Fee (Solo entries only)', 'eventflow' ); ?>
									</option>
								</select>
							</div>

							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Currency Code', 'eventflow' ); ?></strong> <span class="ef-req">*</span></label>
								<input type="text" name="currency" class="ef-input" value="<?php echo esc_attr( $currency ); ?>" required placeholder="PKR">
							</div>
						</div>

						<!-- Section A: Individual Registration Pricing -->
						<div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 18px; border-radius: 8px; margin-bottom: 20px;">
							<h4 style="margin: 0 0 12px; color: #1E293B; font-size: 15px;">
								<span class="dashicons dashicons-admin-users" style="vertical-align: middle;"></span> <?php esc_html_e( 'Individual / Solo Registration Fee', 'eventflow' ); ?>
							</h4>

							<div class="ef-grid ef-grid-3">
								<div class="ef-form-group">
									<label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600;">
										<input type="checkbox" name="solo_fee_enabled" value="1" <?php checked( ! isset( $edit_event->solo_fee_enabled ) || 1 == $edit_event->solo_fee_enabled ); ?>>
										<span><?php esc_html_e( 'Enable Solo Fee', 'eventflow' ); ?></span>
									</label>
								</div>

								<div class="ef-form-group">
									<label><strong><?php esc_html_e( 'Solo Registration Fee', 'eventflow' ); ?></strong></label>
									<input type="number" step="0.01" name="solo_fee" class="ef-input" value="<?php echo esc_attr( $edit_event && isset( $edit_event->solo_fee ) ? $edit_event->solo_fee : '500.00' ); ?>">
								</div>

								<div class="ef-form-group">
									<label><strong><?php esc_html_e( 'Solo Optional Discount', 'eventflow' ); ?></strong></label>
									<input type="number" step="0.01" name="solo_discount" class="ef-input" value="<?php echo esc_attr( $edit_event && isset( $edit_event->solo_discount ) ? $edit_event->solo_discount : '0.00' ); ?>">
								</div>
							</div>
						</div>

						<!-- Section B: Team Registration Pricing -->
						<div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 18px; border-radius: 8px; margin-bottom: 20px;">
							<h4 style="margin: 0 0 12px; color: #1E293B; font-size: 15px;">
								<span class="dashicons dashicons-groups" style="vertical-align: middle;"></span> <?php esc_html_e( 'Team Registration Fee Settings', 'eventflow' ); ?>
							</h4>

							<div class="ef-grid ef-grid-3">
								<div class="ef-form-group">
									<label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600;">
										<input type="checkbox" name="team_fee_enabled" value="1" <?php checked( ! isset( $edit_event->team_fee_enabled ) || 1 == $edit_event->team_fee_enabled ); ?>>
										<span><?php esc_html_e( 'Enable Team Fee', 'eventflow' ); ?></span>
									</label>
								</div>

								<div class="ef-form-group">
									<label><strong><?php esc_html_e( 'Fee Per Participant', 'eventflow' ); ?></strong></label>
									<input type="number" step="0.01" name="fee_per_participant" id="fee_per_participant" class="ef-input" value="<?php echo esc_attr( $edit_event && isset( $edit_event->fee_per_participant ) ? $edit_event->fee_per_participant : ( $edit_event && isset( $edit_event->team_fee_per_member ) ? $edit_event->team_fee_per_member : '500.00' ) ); ?>">
									<p class="description"><?php esc_html_e( 'Applied when "Per Participant" model is selected.', 'eventflow' ); ?></p>
								</div>

								<div class="ef-form-group">
									<label><strong><?php esc_html_e( 'Fixed Fee Per Team', 'eventflow' ); ?></strong></label>
									<input type="number" step="0.01" name="fixed_team_fee" id="fixed_team_fee" class="ef-input" value="<?php echo esc_attr( $edit_event && isset( $edit_event->fixed_team_fee ) ? $edit_event->fixed_team_fee : ( $edit_event && isset( $edit_event->team_fixed_fee ) ? $edit_event->team_fixed_fee : '2000.00' ) ); ?>">
									<p class="description"><?php esc_html_e( 'Applied when "Fixed Fee Per Team" model is selected.', 'eventflow' ); ?></p>
								</div>
							</div>

							<div class="ef-grid ef-grid-2" style="margin-top: 10px;">
								<div class="ef-form-group">
									<label><strong><?php esc_html_e( 'Team Optional Discount', 'eventflow' ); ?></strong></label>
									<input type="number" step="0.01" name="team_discount" class="ef-input" value="<?php echo esc_attr( $edit_event && isset( $edit_event->team_discount ) ? $edit_event->team_discount : '0.00' ); ?>">
								</div>

								<div class="ef-form-group">
									<label><strong><?php esc_html_e( 'Minimum Payable Amount', 'eventflow' ); ?></strong></label>
									<input type="number" step="0.01" name="team_min_payable" class="ef-input" value="<?php echo esc_attr( $edit_event && isset( $edit_event->team_min_payable ) ? $edit_event->team_min_payable : '500.00' ); ?>">
									<p class="description"><?php esc_html_e( 'Minimum total fee a team must pay after discounts.', 'eventflow' ); ?></p>
								</div>
							</div>
						</div>

						<!-- Live Pricing Formula Preview Box -->
						<div style="background: #ECFDF5; border: 1px solid #A7F3D0; padding: 16px; border-radius: 8px;">
							<h4 style="margin: 0 0 8px; color: #065F46; font-size: 14px;">
								<span class="dashicons dashicons-calculator" style="vertical-align: middle;"></span> <?php esc_html_e( 'Transparent Fee Calculation Example', 'eventflow' ); ?>
							</h4>
							<p style="margin: 0; font-size: 13px; color: #047857;">
								<?php esc_html_e( 'Example: If fee per participant is PKR 500 and a team submits 4 members: Total Payable = 4 × PKR 500 = PKR 2,000.00.', 'eventflow' ); ?>
							</p>
						</div>
					</div>
				</div>

				<!-- ============================================================== -->
				<!-- TAB 4: PAYMENT CONFIGURATION & GATEWAYS -->
				<!-- ============================================================== -->
				<div class="ef-event-tab-content" id="ef-tab-payment" style="display: none;">
					<div class="ef-card" style="margin-bottom: 24px;">
						<div class="ef-card-header">
							<h3><?php esc_html_e( 'Official Payment Receiving Accounts & Instructions', 'eventflow' ); ?></h3>
							<p class="ef-card-subtitle"><?php esc_html_e( 'Configure the bank and mobile financial accounts displayed to applicants on payment screens.', 'eventflow' ); ?></p>
						</div>

						<!-- Bank Transfer Settings -->
						<div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 18px; border-radius: 8px; margin-bottom: 20px;">
							<div class="ef-flex-between" style="margin-bottom: 12px;">
								<h4 style="margin: 0; color: #1E293B; font-size: 15px;">
									<span class="dashicons dashicons-vault" style="vertical-align: middle;"></span> <?php esc_html_e( 'Official Bank Account Details', 'eventflow' ); ?>
								</h4>
								<label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600;">
									<input type="checkbox" name="bank_enabled" value="1" <?php checked( ! isset( $edit_event->bank_enabled ) || 1 == $edit_event->bank_enabled || 'yes' === get_option( 'eventflow_bank_enabled', 'yes' ) ); ?>>
									<span><?php esc_html_e( 'Accept Bank Transfer', 'eventflow' ); ?></span>
								</label>
							</div>

							<div class="ef-grid ef-grid-2">
								<div class="ef-form-group">
									<label><strong><?php esc_html_e( 'Bank Name', 'eventflow' ); ?></strong></label>
									<input type="text" name="bank_name" class="ef-input" value="<?php echo esc_attr( $edit_event && ! empty( $edit_event->bank_name ) ? $edit_event->bank_name : get_option( 'eventflow_bank_name', 'Meezan Bank Ltd' ) ); ?>">
								</div>

								<div class="ef-form-group">
									<label><strong><?php esc_html_e( 'Account Title', 'eventflow' ); ?></strong></label>
									<input type="text" name="bank_account_title" class="ef-input" value="<?php echo esc_attr( $edit_event && ! empty( $edit_event->bank_account_title ) ? $edit_event->bank_account_title : get_option( 'eventflow_bank_account_title', 'SIBACOM EVENT Society' ) ); ?>">
								</div>
							</div>

							<div class="ef-grid ef-grid-2">
								<div class="ef-form-group">
									<label><strong><?php esc_html_e( 'Account Number', 'eventflow' ); ?></strong></label>
									<input type="text" name="bank_account_no" class="ef-input" value="<?php echo esc_attr( $edit_event && ! empty( $edit_event->bank_account_number ) ? $edit_event->bank_account_number : get_option( 'eventflow_bank_account_no', '01020304050607' ) ); ?>">
								</div>

								<div class="ef-form-group">
									<label><strong><?php esc_html_e( 'IBAN (24 digits)', 'eventflow' ); ?></strong></label>
									<input type="text" name="bank_iban" class="ef-input" value="<?php echo esc_attr( $edit_event && ! empty( $edit_event->bank_iban ) ? $edit_event->bank_iban : get_option( 'eventflow_bank_iban', 'PK92MEZN0001020304050607' ) ); ?>">
								</div>
							</div>
						</div>

						<!-- Mobile Wallets: JazzCash & Easypaisa -->
						<div class="ef-grid ef-grid-2" style="margin-bottom: 20px;">
							<!-- JazzCash -->
							<div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 16px; border-radius: 8px;">
								<div class="ef-flex-between" style="margin-bottom: 10px;">
									<h4 style="margin: 0; color: #1E293B; font-size: 14px;">
										<span class="dashicons dashicons-smartphone" style="vertical-align: middle;"></span> <?php esc_html_e( 'JazzCash Account', 'eventflow' ); ?>
									</h4>
									<label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 12px; font-weight: 600;">
										<input type="checkbox" name="jazzcash_enabled" value="1" <?php checked( ! isset( $edit_event->jazzcash_enabled ) || 1 == $edit_event->jazzcash_enabled || 'yes' === get_option( 'eventflow_jazzcash_enabled', 'yes' ) ); ?>>
										<span><?php esc_html_e( 'Enabled', 'eventflow' ); ?></span>
									</label>
								</div>
								<div class="ef-form-group">
									<label class="ef-text-xs"><strong><?php esc_html_e( 'Account Title', 'eventflow' ); ?></strong></label>
									<input type="text" name="jazzcash_title" class="ef-input" value="<?php echo esc_attr( $edit_event && ! empty( $edit_event->jazzcash_title ) ? $edit_event->jazzcash_title : get_option( 'eventflow_jazzcash_title', 'SIBACOM Official' ) ); ?>">
								</div>
								<div class="ef-form-group">
									<label class="ef-text-xs"><strong><?php esc_html_e( 'Mobile / Till Number', 'eventflow' ); ?></strong></label>
									<input type="text" name="jazzcash_no" class="ef-input" value="<?php echo esc_attr( $edit_event && ! empty( $edit_event->jazzcash_details ) ? $edit_event->jazzcash_details : get_option( 'eventflow_jazzcash_no', '03001234567' ) ); ?>">
								</div>
							</div>

							<!-- Easypaisa -->
							<div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 16px; border-radius: 8px;">
								<div class="ef-flex-between" style="margin-bottom: 10px;">
									<h4 style="margin: 0; color: #1E293B; font-size: 14px;">
										<span class="dashicons dashicons-smartphone" style="vertical-align: middle;"></span> <?php esc_html_e( 'Easypaisa Account', 'eventflow' ); ?>
									</h4>
									<label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 12px; font-weight: 600;">
										<input type="checkbox" name="easypaisa_enabled" value="1" <?php checked( ! isset( $edit_event->easypaisa_enabled ) || 1 == $edit_event->easypaisa_enabled || 'yes' === get_option( 'eventflow_easypaisa_enabled', 'yes' ) ); ?>>
										<span><?php esc_html_e( 'Enabled', 'eventflow' ); ?></span>
									</label>
								</div>
								<div class="ef-form-group">
									<label class="ef-text-xs"><strong><?php esc_html_e( 'Account Title', 'eventflow' ); ?></strong></label>
									<input type="text" name="easypaisa_title" class="ef-input" value="<?php echo esc_attr( $edit_event && ! empty( $edit_event->easypaisa_title ) ? $edit_event->easypaisa_title : get_option( 'eventflow_easypaisa_title', 'SIBACOM Official' ) ); ?>">
								</div>
								<div class="ef-form-group">
									<label class="ef-text-xs"><strong><?php esc_html_e( 'Mobile Number', 'eventflow' ); ?></strong></label>
									<input type="text" name="easypaisa_no" class="ef-input" value="<?php echo esc_attr( $edit_event && ! empty( $edit_event->easypaisa_details ) ? $edit_event->easypaisa_details : get_option( 'eventflow_easypaisa_no', '03451234567' ) ); ?>">
								</div>
							</div>
						</div>

						<div class="ef-grid ef-grid-2">
							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Raast ID / Instant Payment', 'eventflow' ); ?></strong></label>
								<input type="text" name="raast_id" class="ef-input" value="<?php echo esc_attr( $edit_event && ! empty( $edit_event->raast_id ) ? $edit_event->raast_id : get_option( 'eventflow_raast_id', '03001234567' ) ); ?>">
							</div>

							<div class="ef-form-group">
								<label><strong><?php esc_html_e( 'Payment Verification Deadline (Hours)', 'eventflow' ); ?></strong></label>
								<input type="number" name="payment_deadline_hours" min="1" max="168" class="ef-input" value="<?php echo esc_attr( $edit_event && ! empty( $edit_event->payment_deadline_hours ) ? $edit_event->payment_deadline_hours : 48 ); ?>">
								<p class="description"><?php esc_html_e( 'Allowed hours for participant to submit payment proof after registration.', 'eventflow' ); ?></p>
							</div>
						</div>

						<div class="ef-form-group">
							<label><strong><?php esc_html_e( 'Official Payment Instructions for Participants', 'eventflow' ); ?></strong></label>
							<textarea name="payment_instructions" class="ef-input" rows="4"><?php
								echo esc_textarea( $edit_event && ! empty( $edit_event->payment_instructions )
									? $edit_event->payment_instructions
									: get_option( 'eventflow_payment_instructions', "1. Transfer the exact fee amount using any banking app, JazzCash, or Easypaisa.\n2. Ensure the recipient title matches our official account title.\n3. Take a clear screenshot of the successful receipt showing Transaction ID (TID) and Date.\n4. Upload the screenshot to your participant dashboard to initiate verification." )
								);
							?></textarea>
						</div>

						<div class="ef-form-group">
							<label><strong><?php esc_html_e( 'Payment QR Code Image URL', 'eventflow' ); ?></strong></label>
							<div class="ef-upload-field-wrap">
								<input type="url" name="qr_code_url" id="ef-event-qr-url" class="ef-input" value="<?php echo esc_attr( $edit_event ? $edit_event->qr_code_url : '' ); ?>">
								<button type="button" class="button ef-media-upload-btn" data-target="#ef-event-qr-url"><?php esc_html_e( 'Select Media', 'eventflow' ); ?></button>
							</div>
						</div>
					</div>
				</div>

				<!-- Form Actions Toolbar -->
				<div style="background: #fff; padding: 18px; border-radius: 8px; border: 1px solid #CBD5E1; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
					<div>
						<button type="submit" class="button button-primary button-large" style="background: #0D9488; border-color: #0F766E; font-weight: 700; padding: 4px 20px;">
							<span class="dashicons dashicons-saved" style="vertical-align: middle; margin-top: -2px;"></span>
							<?php echo $edit_event ? esc_html__( 'Save & Apply Configuration', 'eventflow' ) : esc_html__( 'Create SIBACOM EVENT', 'eventflow' ); ?>
						</button>
						<?php if ( $edit_event ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-events' ) ); ?>" class="button button-large" style="margin-left: 8px;">
								<?php esc_html_e( 'Cancel', 'eventflow' ); ?>
							</a>
						<?php endif; ?>
					</div>
					<div class="ef-text-sm ef-text-muted">
						<?php esc_html_e( 'SIBACOM EVENT Official Configuration Engine', 'eventflow' ); ?>
					</div>
				</div>
			</form>
		</div>

		<!-- Right Side: Existing Events Directory & Stats -->
		<div>
			<div class="ef-card" style="margin-bottom: 20px;">
				<div class="ef-card-header">
					<h3><?php esc_html_e( 'Events Directory', 'eventflow' ); ?></h3>
				</div>
				<div style="padding: 14px;">
					<?php if ( empty( $events ) ) : ?>
						<p class="ef-text-muted"><?php esc_html_e( 'No events registered yet.', 'eventflow' ); ?></p>
					<?php else : ?>
						<ul style="margin: 0; padding: 0; list-style: none;">
							<?php foreach ( $events as $ev ) : ?>
								<li style="padding: 12px 0; border-bottom: 1px solid #F1F5F9; display: flex; justify-content: space-between; align-items: center;">
									<div>
										<strong style="color: #0F172A; display: block;"><?php echo esc_html( $ev->title ); ?></strong>
										<span class="ef-text-xs ef-text-muted">
											<?php echo $ev->start_date ? esc_html( date_i18n( 'M j, Y', strtotime( $ev->start_date ) ) ) : 'Dates pending'; ?>
										</span>
										<?php if ( $active_event && (int) $active_event->id === (int) $ev->id ) : ?>
											<span class="ef-badge ef-badge-primary" style="font-size: 10px; margin-left: 4px;"><?php esc_html_e( 'Active', 'eventflow' ); ?></span>
										<?php endif; ?>
									</div>
									<div>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-events&edit=' . $ev->id ) ); ?>" class="button button-small">
											<?php esc_html_e( 'Configure', 'eventflow' ); ?>
										</a>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>

			<!-- Quick Info Card -->
			<div class="ef-card" style="border-left: 4px solid #0D9488;">
				<div class="ef-card-header">
					<h4 style="margin: 0; color: #0F766E;"><?php esc_html_e( 'Branding & Architecture Note', 'eventflow' ); ?></h4>
				</div>
				<div style="padding: 14px; font-size: 13px; color: #475569; line-height: 1.5;">
					<p><?php esc_html_e( 'The official event name is strictly SIBACOM EVENT. All email headers, automated notifications, fee snapshots, and participant receipts dynamically bind to these settings.', 'eventflow' ); ?></p>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Simple tab switcher for Event Configuration tabs
	$('.ef-event-tab-btn').on('click', function(e) {
		e.preventDefault();
		var targetTab = $(this).data('tab');

		$('.ef-event-tab-btn').css({
			'background': '#F1F5F9',
			'color': '#475569',
			'font-weight': '600'
		}).removeClass('active');

		$(this).css({
			'background': '#0D9488',
			'color': '#fff',
			'font-weight': '700'
		}).addClass('active');

		$('.ef-event-tab-content').hide();
		$('#ef-tab-' + targetTab).show();
	});
});
</script>
