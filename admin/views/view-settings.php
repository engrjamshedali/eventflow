<?php
/**
 * EventFlow Admin View: Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$events = EventFlow_Event_Manager::get_all_events();
$active_event_id = (int) get_option( 'eventflow_active_event_id', 0 );
$system_closed   = get_option( 'eventflow_system_closed', 'no' );
$reg_prefix      = get_option( 'eventflow_reg_id_prefix', 'EF' );
$currency        = get_option( 'eventflow_currency', 'PKR' );
$primary_color   = get_option( 'eventflow_primary_color', '#4F46E5' );
$secondary_color = get_option( 'eventflow_secondary_color', '#06B6D4' );
$favicon_url     = get_option( 'eventflow_favicon_url', '' );
$standalone_mode = (int) get_option( 'eventflow_standalone_mode', 1 );
$takeover_home   = (int) get_option( 'eventflow_takeover_homepage', 1 );

$settings = EventFlow_Payment_Manager::get_settings();
$email_from_name = get_option( 'eventflow_email_from_name', get_bloginfo( 'name' ) );
$email_from_addr = get_option( 'eventflow_email_from_address', get_option( 'admin_email' ) );
$delete_on_uninstall = get_option( 'eventflow_delete_data_on_uninstall', 'no' );
?>

<div class="wrap ef-admin-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'EventFlow Settings & System Configuration', 'eventflow' ); ?></h1>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved successfully.', 'eventflow' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="ef-form" style="margin-top: 20px;">
		<input type="hidden" name="eventflow_admin_action" value="save_settings">
		<?php wp_nonce_field( 'eventflow_save_settings_nonce' ); ?>

		<!-- SECTION 0: FULL-PAGE STANDALONE & HOMEPAGE TAKEOVER -->
		<div class="ef-card" style="border-left: 4px solid #6366F1; margin-bottom: 20px;">
			<div class="ef-card-header">
				<h3>⚡ <?php esc_html_e( 'Full-Page Standalone Web App Mode', 'eventflow' ); ?></h3>
			</div>
			<p style="margin: 0 0 16px; color: #64748B; font-size: 13px;">
				<?php esc_html_e( 'When active, EventFlow renders in a dedicated, isolated full-page canvas. It completely removes your WordPress theme’s header, navigation menu, logo, sidebar, and footer.', 'eventflow' ); ?>
			</p>

			<div class="ef-form-group ef-checkbox-group" style="margin-bottom: 12px;">
				<label>
					<input type="checkbox" name="eventflow_standalone_mode" value="1" <?php checked( $standalone_mode, 1 ); ?>>
					<strong><?php esc_html_e( 'Enable Full-Page Standalone Web App Mode', 'eventflow' ); ?></strong>
					<span class="ef-help-text"><?php esc_html_e( 'Bypasses WordPress theme headers, navigation bars, and footers for all EventFlow portal views.', 'eventflow' ); ?></span>
				</label>
			</div>

			<div class="ef-form-group ef-checkbox-group">
				<label>
					<input type="checkbox" name="eventflow_takeover_homepage" value="1" <?php checked( $takeover_home, 1 ); ?>>
					<strong><?php esc_html_e( 'Set EventFlow as Website Homepage ("/")', 'eventflow' ); ?></strong>
					<span class="ef-help-text"><?php esc_html_e( 'Visiting your website root directly displays the full-page Login & Registration portal for guests, and the Participant Dashboard for logged-in users.', 'eventflow' ); ?></span>
				</label>
			</div>
		</div>

		<!-- SECTION 1: GENERAL & EVENT LIFECYCLE -->
		<div class="ef-card">
			<div class="ef-card-header">
				<h3><?php esc_html_e( 'General & Event Activation', 'eventflow' ); ?></h3>
			</div>

			<div class="ef-grid ef-grid-2">
				<div class="ef-form-group">
					<label><?php esc_html_e( 'Active Event Selection', 'eventflow' ); ?></label>
					<select name="eventflow_active_event_id" class="ef-input">
						<?php foreach ( $events as $ev ) : ?>
							<option value="<?php echo esc_attr( $ev->id ); ?>" <?php selected( $active_event_id, $ev->id ); ?>><?php echo esc_html( $ev->title ); ?></option>
						<?php endforeach; ?>
					</select>
					<span class="ef-help-text"><?php esc_html_e( 'The active event will be displayed across the landing hero and participant dashboard.', 'eventflow' ); ?></span>
				</div>

				<div class="ef-form-group">
					<label><?php esc_html_e( 'Master Registration Switch', 'eventflow' ); ?></label>
					<select name="eventflow_system_closed" class="ef-input">
						<option value="no" <?php selected( $system_closed, 'no' ); ?>><?php esc_html_e( 'System Active (Follow event schedule)', 'eventflow' ); ?></option>
						<option value="yes" <?php selected( $system_closed, 'yes' ); ?>><?php esc_html_e( 'Completely Close Registration System', 'eventflow' ); ?></option>
					</select>
				</div>
			</div>

			<div class="ef-grid ef-grid-2">
				<div class="ef-form-group">
					<label><?php esc_html_e( 'Registration ID Prefix', 'eventflow' ); ?></label>
					<input type="text" name="eventflow_reg_id_prefix" class="ef-input" value="<?php echo esc_attr( $reg_prefix ); ?>" placeholder="EF">
					<span class="ef-help-text"><?php esc_html_e( 'Example: "EF" creates IDs like EF-2026-A1B2C.', 'eventflow' ); ?></span>
				</div>

				<div class="ef-form-group">
					<label><?php esc_html_e( 'System Currency Code', 'eventflow' ); ?></label>
					<input type="text" name="eventflow_currency" class="ef-input" value="<?php echo esc_attr( $currency ); ?>" placeholder="PKR">
				</div>
			</div>
		</div>

		<!-- SECTION 2: BRANDING & APPEARANCE -->
		<div class="ef-card" style="margin-top: 20px;">
			<div class="ef-card-header">
				<h3><?php esc_html_e( 'Appearance & Branding Colors', 'eventflow' ); ?></h3>
				<p class="ef-card-subtitle"><?php esc_html_e( 'Customize primary and accent colors used across frontend widgets, buttons, and email headers.', 'eventflow' ); ?></p>
			</div>

			<div class="ef-grid ef-grid-2">
				<div class="ef-form-group">
					<label><?php esc_html_e( 'Primary Brand Color', 'eventflow' ); ?></label>
					<div style="display: flex; gap: 8px; align-items: center;">
						<input type="color" value="<?php echo esc_attr( $primary_color ); ?>" onchange="document.getElementById('ef-pri-hex').value = this.value">
						<input type="text" id="ef-pri-hex" name="eventflow_primary_color" class="ef-input" value="<?php echo esc_attr( $primary_color ); ?>">
					</div>
				</div>

				<div class="ef-form-group">
					<label><?php esc_html_e( 'Secondary Accent Color', 'eventflow' ); ?></label>
					<div style="display: flex; gap: 8px; align-items: center;">
						<input type="color" value="<?php echo esc_attr( $secondary_color ); ?>" onchange="document.getElementById('ef-sec-hex').value = this.value">
						<input type="text" id="ef-sec-hex" name="eventflow_secondary_color" class="ef-input" value="<?php echo esc_attr( $secondary_color ); ?>">
					</div>
				</div>
			</div>

			<div class="ef-form-group" style="margin-top: 16px;">
				<label><strong><?php esc_html_e( 'Website Favicon & Touch Icon URL', 'eventflow' ); ?></strong></label>
				<div class="ef-upload-field-wrap" style="display: flex; gap: 12px; align-items: center; margin-top: 6px;">
					<div style="width: 44px; height: 44px; border: 1px solid #E2E8F0; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #F8FAFC; overflow: hidden; flex-shrink: 0; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
						<img id="ef-favicon-preview" src="<?php echo esc_url( class_exists( 'EventFlow_Favicon' ) ? EventFlow_Favicon::get_favicon_url( '32' ) : EVENTFLOW_PLUGIN_URL . 'assets/images/favicon/favicon-32x32.png' ); ?>" alt="Favicon Preview" style="width: 32px; height: 32px; object-fit: contain;">
					</div>
					<input type="url" name="eventflow_favicon_url" id="ef-settings-favicon-url" class="ef-input" style="flex: 1;" value="<?php echo esc_attr( $favicon_url ); ?>" placeholder="<?php echo esc_attr( EVENTFLOW_PLUGIN_URL . 'assets/images/favicon/favicon.ico' ); ?>" onchange="if(this.value){document.getElementById('ef-favicon-preview').src=this.value;}">
					<button type="button" class="button ef-media-upload-btn" data-target="#ef-settings-favicon-url"><?php esc_html_e( 'Upload / Select Favicon', 'eventflow' ); ?></button>
				</div>
				<span class="ef-help-text"><?php esc_html_e( 'Displayed across all browser tabs, bookmarks, iOS/Android home screen shortcuts, and PWA manifests. Leave blank to use the official bundled SIBACOM / Sukkur IBA University emblem suite.', 'eventflow' ); ?></span>
			</div>
		</div>

		<!-- SECTION 3: BANK & PAYMENT SETTINGS -->
		<div class="ef-card" style="margin-top: 20px;">
			<div class="ef-card-header">
				<h3><?php esc_html_e( 'Financial Accounts & Payment Gateway Info', 'eventflow' ); ?></h3>
				<p class="ef-card-subtitle"><?php esc_html_e( 'Configure official receiving accounts, instructions, and individual gateway enablement.', 'eventflow' ); ?></p>
			</div>

			<!-- Method 1: Bank Transfer -->
			<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
				<div class="ef-flex-between" style="margin-bottom: 12px;">
					<h4 style="margin: 0;">🏦 <?php esc_html_e( 'Bank Transfer (IBFT / Direct Deposit)', 'eventflow' ); ?></h4>
					<label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
						<input type="checkbox" name="eventflow_bank_enabled" value="yes" <?php checked( $settings['bank_enabled'], 'yes' ); ?>>
						<strong><?php esc_html_e( 'Enable Bank Transfer', 'eventflow' ); ?></strong>
					</label>
				</div>
				<div class="ef-grid ef-grid-2">
					<div class="ef-form-group">
						<label><?php esc_html_e( 'Bank Name', 'eventflow' ); ?></label>
						<input type="text" name="eventflow_bank_name" class="ef-input" value="<?php echo esc_attr( $settings['bank_name'] ); ?>" placeholder="e.g. Bank Alfalah / Meezan Bank">
					</div>
					<div class="ef-form-group">
						<label><?php esc_html_e( 'Account Title', 'eventflow' ); ?></label>
						<input type="text" name="eventflow_account_title" class="ef-input" value="<?php echo esc_attr( $settings['account_title'] ); ?>" placeholder="Official receiving account title">
					</div>
				</div>
				<div class="ef-grid ef-grid-2">
					<div class="ef-form-group">
						<label><?php esc_html_e( 'Account Number', 'eventflow' ); ?></label>
						<input type="text" name="eventflow_account_number" class="ef-input" value="<?php echo esc_attr( $settings['account_number'] ); ?>">
					</div>
					<div class="ef-form-group">
						<label><?php esc_html_e( 'IBAN', 'eventflow' ); ?></label>
						<input type="text" name="eventflow_iban" class="ef-input" value="<?php echo esc_attr( $settings['iban'] ); ?>">
					</div>
				</div>
			</div>

			<!-- Method 2: JazzCash -->
			<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
				<div class="ef-flex-between" style="margin-bottom: 12px;">
					<h4 style="margin: 0;">📱 <?php esc_html_e( 'JazzCash Mobile Account', 'eventflow' ); ?></h4>
					<label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
						<input type="checkbox" name="eventflow_jazzcash_enabled" value="yes" <?php checked( $settings['jazzcash_enabled'], 'yes' ); ?>>
						<strong><?php esc_html_e( 'Enable JazzCash', 'eventflow' ); ?></strong>
					</label>
				</div>
				<div class="ef-grid ef-grid-2">
					<div class="ef-form-group">
						<label><?php esc_html_e( 'JazzCash Account Title', 'eventflow' ); ?></label>
						<input type="text" name="eventflow_jazzcash_title" class="ef-input" value="<?php echo esc_attr( $settings['jazzcash_title'] ); ?>">
					</div>
					<div class="ef-form-group">
						<label><?php esc_html_e( 'JazzCash Mobile Number', 'eventflow' ); ?></label>
						<input type="text" name="eventflow_jazzcash_number" class="ef-input" value="<?php echo esc_attr( $settings['jazzcash_number'] ); ?>">
					</div>
				</div>
			</div>

			<!-- Method 3: Easypaisa -->
			<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
				<div class="ef-flex-between" style="margin-bottom: 12px;">
					<h4 style="margin: 0;">📱 <?php esc_html_e( 'Easypaisa Mobile Account', 'eventflow' ); ?></h4>
					<label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
						<input type="checkbox" name="eventflow_easypaisa_enabled" value="yes" <?php checked( $settings['easypaisa_enabled'], 'yes' ); ?>>
						<strong><?php esc_html_e( 'Enable Easypaisa', 'eventflow' ); ?></strong>
					</label>
				</div>
				<div class="ef-grid ef-grid-2">
					<div class="ef-form-group">
						<label><?php esc_html_e( 'Easypaisa Account Title', 'eventflow' ); ?></label>
						<input type="text" name="eventflow_easypaisa_title" class="ef-input" value="<?php echo esc_attr( $settings['easypaisa_title'] ); ?>">
					</div>
					<div class="ef-form-group">
						<label><?php esc_html_e( 'Easypaisa Mobile Number', 'eventflow' ); ?></label>
						<input type="text" name="eventflow_easypaisa_number" class="ef-input" value="<?php echo esc_attr( $settings['easypaisa_number'] ); ?>">
					</div>
				</div>
			</div>

			<div class="ef-form-group">
				<label><?php esc_html_e( 'Payment QR Code Image URL', 'eventflow' ); ?></label>
				<div class="ef-upload-field-wrap">
					<input type="url" name="eventflow_qr_code_url" id="ef-settings-qr-url" class="ef-input" value="<?php echo esc_attr( $settings['qr_code_url'] ); ?>">
					<button type="button" class="button ef-media-upload-btn" data-target="#ef-settings-qr-url"><?php esc_html_e( 'Upload / Select QR Code', 'eventflow' ); ?></button>
				</div>
			</div>

			<div class="ef-form-group">
				<label><?php esc_html_e( 'Payment Instructions & Notice for Participants', 'eventflow' ); ?></label>
				<textarea name="eventflow_payment_instructions" class="ef-input" rows="4"><?php echo esc_textarea( $settings['instructions'] ); ?></textarea>
			</div>
		</div>

		<!-- SECTION 4: EMAIL NOTIFICATION CONFIGURATION -->
		<div class="ef-card" style="margin-top: 20px;">
			<div class="ef-card-header ef-flex-between">
				<div>
					<h3><?php esc_html_e( 'Email Sender Headers & SMTP Infrastructure', 'eventflow' ); ?></h3>
					<p class="ef-card-subtitle"><?php esc_html_e( 'Default identity used across all participant transactional communications.', 'eventflow' ); ?></p>
				</div>
				<div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-emails' ) ); ?>" class="button">
						<span class="dashicons dashicons-email-alt" style="vertical-align: middle;"></span> <?php esc_html_e( 'Open Complete Email Hub &rarr;', 'eventflow' ); ?>
					</a>
				</div>
			</div>

			<div class="ef-grid ef-grid-2">
				<div class="ef-form-group">
					<label><?php esc_html_e( 'From Name', 'eventflow' ); ?></label>
					<input type="text" name="eventflow_email_from_name" class="ef-input" value="<?php echo esc_attr( $email_from_name ); ?>">
				</div>
				<div class="ef-form-group">
					<label><?php esc_html_e( 'From Email Address', 'eventflow' ); ?></label>
					<input type="email" name="eventflow_email_from_address" class="ef-input" value="<?php echo esc_attr( $email_from_addr ); ?>">
				</div>
			</div>

			<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px 16px; margin-top: 14px;">
				<div class="ef-flex-between">
					<div>
						<strong><?php esc_html_e( 'Dedicated SMTP, Queue Worker & Live Delivery Diagnostics', 'eventflow' ); ?></strong>
						<div class="ef-text-xs ef-text-muted"><?php esc_html_e( 'Configure SMTP host, TLS encryption, background email queue, retry limits, and inspect delivery logs in the dedicated Email Hub.', 'eventflow' ); ?></div>
					</div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-emails&tab=smtp' ) ); ?>" class="button button-small button-primary">
						<?php esc_html_e( 'Configure SMTP', 'eventflow' ); ?>
					</a>
				</div>
			</div>
		</div>

		<!-- SECTION 5: UNINSTALL & CLEANUP -->
		<div class="ef-card" style="margin-top: 20px;">
			<div class="ef-card-header">
				<h3><?php esc_html_e( 'Data Retention on Uninstall', 'eventflow' ); ?></h3>
			</div>

			<div class="ef-form-group">
				<label>
					<input type="checkbox" name="eventflow_delete_data_on_uninstall" value="yes" <?php checked( $delete_on_uninstall, 'yes' ); ?>>
					<span><?php esc_html_e( 'Completely erase all custom tables, uploaded screenshots, and registrations when the plugin is deleted.', 'eventflow' ); ?></span>
				</label>
			</div>
		</div>

		<p class="submit" style="margin-top: 20px;">
			<button type="submit" class="button button-primary button-hero">
				<?php esc_html_e( 'Save All EventFlow Configuration', 'eventflow' ); ?>
			</button>
		</p>
	</form>
</div>

