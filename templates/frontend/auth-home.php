<?php
/**
 * EventFlow Split-Screen Authentication Homepage
 *
 * Implements the pixel-perfect split-screen teal & white authentication design.
 * Served as the exclusive entry point for logged-out visitors.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$event       = EventFlow_Event_Manager::get_active_event();
$event_title = $event ? $event->title : __( 'SIBACOM — Sukkur IBA University', 'eventflow' );
$active_tab  = isset( $_GET['tab'] ) && in_array( $_GET['tab'], array( 'register', 'forgot' ), true ) ? $_GET['tab'] : 'login';

// Determine branding logos
$logo_white = '';
$logo_dark  = '';
if ( $event && ! empty( $event->logo_url ) ) {
	$logo_white = $event->logo_url;
	$logo_dark  = $event->logo_url;
} else {
	$brand_dir = EVENTFLOW_PLUGIN_DIR . 'assets/images/branding/';
	$brand_url = EVENTFLOW_PLUGIN_URL . 'assets/images/branding/';
	if ( file_exists( $brand_dir . 'cse_society_logo_white.png' ) ) {
		$logo_white = $brand_url . 'cse_society_logo_white.png';
	} elseif ( file_exists( $brand_dir . 'sukkur_iba_white_logo.png' ) ) {
		$logo_white = $brand_url . 'sukkur_iba_white_logo.png';
	}
	if ( file_exists( $brand_dir . 'sukkur_iba_logo.png' ) ) {
		$logo_dark = $brand_url . 'sukkur_iba_logo.png';
	} elseif ( file_exists( $brand_dir . 'cse_society_logo.png' ) ) {
		$logo_dark = $brand_url . 'cse_society_logo.png';
	}
}
?>

<div class="eventflow-app ef-split-auth-viewport">
	<!-- Bridge data for script resiliency -->
	<div class="ef-auth-data-bridge" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-auth-nonce="<?php echo esc_attr( wp_create_nonce( 'eventflow_auth_nonce' ) ); ?>" style="display:none;"></div>

	<!-- Background subtle ambient decorations -->
	<div class="ef-ambient-circle ef-ambient-1"></div>
	<div class="ef-ambient-circle ef-ambient-2"></div>

	<!-- Main Centered Authentication Split Card -->
	<div class="ef-auth-split-container <?php echo 'register' === $active_tab ? 'ef-mode-register' : ( 'forgot' === $active_tab ? 'ef-mode-forgot' : 'ef-mode-login' ); ?>" id="ef-auth-split-container">
		
		<!-- ====================================================
		     LEFT PANEL — Teal Welcome Section (#36B49F / #27A991)
		     ==================================================== -->
		<div class="ef-panel-teal">
			<!-- Subtle decorative abstract geometric circles & background patterns -->
			<div class="ef-deco-shape ef-shape-circle-1"></div>
			<div class="ef-deco-shape ef-shape-circle-2"></div>
			<div class="ef-deco-shape ef-shape-circle-3"></div>
			<div class="ef-deco-grid-overlay"></div>

			<!-- Top Platform Logo -->
			<div class="ef-teal-brand">
				<?php if ( $logo_white ) : ?>
					<img src="<?php echo esc_url( $logo_white ); ?>" alt="<?php echo esc_attr( $event_title ); ?>" class="ef-teal-logo-img">
				<?php else : ?>
					<div class="ef-teal-logo-fallback">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>
						<span>SIBACOM</span>
					</div>
				<?php endif; ?>
			</div>

			<!-- Teal Center Interactive Content -->
			<div class="ef-teal-center-content">
				<!-- Content State A: Shown when Registration is active on right -->
				<div class="ef-teal-msg ef-msg-for-register">
					<h2 class="ef-teal-heading"><?php esc_html_e( 'Welcome Back!', 'eventflow' ); ?></h2>
					<p class="ef-teal-desc">
						<?php esc_html_e( 'To keep connected with us please login with your personal information.', 'eventflow' ); ?>
					</p>
					<button type="button" class="ef-btn-ghost-white ef-auth-toggle-btn" data-switch-to="login">
						<?php esc_html_e( 'Sign In', 'eventflow' ); ?>
					</button>
				</div>

				<!-- Content State B: Shown when Login is active on right -->
				<div class="ef-teal-msg ef-msg-for-login">
					<h2 class="ef-teal-heading"><?php esc_html_e( 'Hello, Friend!', 'eventflow' ); ?></h2>
					<p class="ef-teal-desc">
						<?php esc_html_e( 'Enter your personal details and start your journey with us.', 'eventflow' ); ?>
					</p>
					<button type="button" class="ef-btn-ghost-white ef-auth-toggle-btn" data-switch-to="register">
						<?php esc_html_e( 'Sign Up', 'eventflow' ); ?>
					</button>
				</div>

				<!-- Content State C: Shown when Forgot Password is active on right -->
				<div class="ef-teal-msg ef-msg-for-forgot">
					<h2 class="ef-teal-heading"><?php esc_html_e( 'Need Help?', 'eventflow' ); ?></h2>
					<p class="ef-teal-desc">
						<?php esc_html_e( 'Recover access to your participant account and registered competitions in just one step.', 'eventflow' ); ?>
					</p>
					<button type="button" class="ef-btn-ghost-white ef-auth-toggle-btn" data-switch-to="login">
						<?php esc_html_e( 'Back to Login', 'eventflow' ); ?>
					</button>
				</div>
			</div>

			<!-- Bottom Subtle Meta -->
			<div class="ef-teal-footer">
				<span><?php echo esc_html( $event_title ); ?></span>
			</div>
		</div>

		<!-- ====================================================
		     RIGHT PANEL — White Authentication Section
		     ==================================================== -->
		<div class="ef-panel-white">
			<!-- Mobile Header Switcher (Visible on mobile/tablets) -->
			<div class="ef-mobile-auth-switch">
				<button type="button" class="ef-mobile-switch-btn <?php echo 'register' !== $active_tab ? 'active' : ''; ?>" data-switch-to="login">
					<?php esc_html_e( 'Sign In', 'eventflow' ); ?>
				</button>
				<button type="button" class="ef-mobile-switch-btn <?php echo 'register' === $active_tab ? 'active' : ''; ?>" data-switch-to="register">
					<?php esc_html_e( 'Create Account', 'eventflow' ); ?>
				</button>
			</div>

			<!-- Dynamic Feedback Notification Box -->
			<div class="ef-auth-alert" id="ef-auth-alert" role="alert" style="display: none;"></div>

			<!-- ----------------------------------------------------
			     FORM 1: REGISTRATION MODE
			     ---------------------------------------------------- -->
			<div class="ef-form-view ef-view-register" id="ef-view-register">
				<div class="ef-form-header">
					<h2 class="ef-auth-title"><?php esc_html_e( 'Create Account', 'eventflow' ); ?></h2>
					<p class="ef-auth-subtitle"><?php esc_html_e( 'Or use your email for registration.', 'eventflow' ); ?></p>
				</div>

				<form id="ef-register-form" class="ef-auth-form" method="post" novalidate>
					<input type="hidden" name="action" value="eventflow_register">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'eventflow_auth_nonce' ) ); ?>">

					<!-- Full Name Input -->
					<div class="ef-input-field">
						<span class="ef-field-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
						</span>
						<input type="text" id="ef-reg-name" name="full_name" class="ef-input-control" placeholder="<?php esc_attr_e( 'Full Name', 'eventflow' ); ?>" required autocomplete="name">
					</div>

					<!-- Email Address Input -->
					<div class="ef-input-field">
						<span class="ef-field-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
						</span>
						<input type="email" id="ef-reg-email" name="email" class="ef-input-control" placeholder="<?php esc_attr_e( 'Email Address', 'eventflow' ); ?>" required autocomplete="email">
					</div>

					<!-- Password Input -->
					<div class="ef-input-field ef-field-password">
						<span class="ef-field-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
						</span>
						<input type="password" id="ef-reg-pwd" name="password" class="ef-input-control ef-password-input" placeholder="<?php esc_attr_e( 'Password (min. 6 characters)', 'eventflow' ); ?>" required autocomplete="new-password">
						<button type="button" class="ef-pwd-toggle" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'eventflow' ); ?>">
							<svg class="ef-eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
							<svg class="ef-eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
						</button>
					</div>

					<!-- Confirm Password Input -->
					<div class="ef-input-field ef-field-password">
						<span class="ef-field-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
						</span>
						<input type="password" id="ef-reg-pwd-cf" name="password_confirm" class="ef-input-control ef-password-input" placeholder="<?php esc_attr_e( 'Confirm Password', 'eventflow' ); ?>" required autocomplete="new-password">
						<button type="button" class="ef-pwd-toggle" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'eventflow' ); ?>">
							<svg class="ef-eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
							<svg class="ef-eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
						</button>
					</div>

					<!-- Optional Academic Profile Details Accordion -->
					<div class="ef-optional-details-wrap">
						<button type="button" class="ef-optional-toggle" id="ef-toggle-academic">
							<span>+ <?php esc_html_e( 'Add Academic Profile Details (Optional)', 'eventflow' ); ?></span>
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
						</button>
						<div class="ef-optional-content" id="ef-academic-fields" style="display:none;">
							<div class="ef-input-field">
								<span class="ef-field-icon">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
								</span>
								<input type="tel" name="phone" class="ef-input-control" placeholder="<?php esc_attr_e( 'WhatsApp / Phone (e.g. 03001234567)', 'eventflow' ); ?>">
							</div>
							<div class="ef-input-field">
								<span class="ef-field-icon">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
								</span>
								<input type="text" name="university" class="ef-input-control" placeholder="<?php esc_attr_e( 'University / Institute Name', 'eventflow' ); ?>">
							</div>
							<div class="ef-input-field">
								<span class="ef-field-icon">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
								</span>
								<input type="text" name="department" class="ef-input-control" placeholder="<?php esc_attr_e( 'Department / Major (e.g. CS / SE)', 'eventflow' ); ?>">
							</div>
						</div>
					</div>

					<!-- Sign Up Button -->
					<div class="ef-form-actions">
						<button type="submit" class="ef-btn-teal ef-btn-pill ef-submit-btn">
							<span class="ef-btn-text"><?php esc_html_e( 'Sign Up', 'eventflow' ); ?></span>
							<span class="ef-spinner" style="display:none;"></span>
						</button>
					</div>

					<!-- Mobile Toggle Link -->
					<div class="ef-mobile-switch-link">
						<?php esc_html_e( 'Already have an account?', 'eventflow' ); ?>
						<a href="#login" class="ef-auth-toggle-link" data-switch-to="login"><?php esc_html_e( 'Sign In', 'eventflow' ); ?></a>
					</div>
				</form>
			</div>

			<!-- ----------------------------------------------------
			     FORM 2: LOGIN MODE
			     ---------------------------------------------------- -->
			<div class="ef-form-view ef-view-login" id="ef-view-login">
				<div class="ef-form-header">
					<h2 class="ef-auth-title"><?php esc_html_e( 'Welcome Back', 'eventflow' ); ?></h2>
					<p class="ef-auth-subtitle"><?php esc_html_e( 'Or use your email account to sign in.', 'eventflow' ); ?></p>
				</div>

				<form id="ef-login-form" class="ef-auth-form" method="post" novalidate>
					<input type="hidden" name="action" value="eventflow_login">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'eventflow_auth_nonce' ) ); ?>">

					<!-- Email / Username Input -->
					<div class="ef-input-field">
						<span class="ef-field-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
						</span>
						<input type="text" id="ef-login-user" name="log" class="ef-input-control" placeholder="<?php esc_attr_e( 'Email Address or Username', 'eventflow' ); ?>" required autocomplete="username">
					</div>

					<!-- Password Input -->
					<div class="ef-input-field ef-field-password">
						<span class="ef-field-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
						</span>
						<input type="password" id="ef-login-pwd" name="pwd" class="ef-input-control ef-password-input" placeholder="<?php esc_attr_e( 'Password', 'eventflow' ); ?>" required autocomplete="current-password">
						<button type="button" class="ef-pwd-toggle" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'eventflow' ); ?>">
							<svg class="ef-eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
							<svg class="ef-eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
						</button>
					</div>

					<!-- Options Row: Remember Me & Forgot Password -->
					<div class="ef-form-options">
						<label class="ef-remember-checkbox">
							<input type="checkbox" name="rememberme" value="forever" checked>
							<span class="ef-checkbox-custom"></span>
							<span class="ef-checkbox-label"><?php esc_html_e( 'Remember Me', 'eventflow' ); ?></span>
						</label>

						<a href="#forgot" class="ef-forgot-link ef-auth-toggle-link" data-switch-to="forgot">
							<?php esc_html_e( 'Forgot Password?', 'eventflow' ); ?>
						</a>
					</div>

					<!-- Sign In Button -->
					<div class="ef-form-actions">
						<button type="submit" class="ef-btn-teal ef-btn-pill ef-submit-btn">
							<span class="ef-btn-text"><?php esc_html_e( 'Sign In', 'eventflow' ); ?></span>
							<span class="ef-spinner" style="display:none;"></span>
						</button>
					</div>

					<!-- Mobile Toggle Link -->
					<div class="ef-mobile-switch-link">
						<?php esc_html_e( "Don't have an account?", 'eventflow' ); ?>
						<a href="#register" class="ef-auth-toggle-link" data-switch-to="register"><?php esc_html_e( 'Create Account', 'eventflow' ); ?></a>
					</div>
				</form>
			</div>

			<!-- ----------------------------------------------------
			     FORM 3: FORGOT PASSWORD MODE
			     ---------------------------------------------------- -->
			<div class="ef-form-view ef-view-forgot" id="ef-view-forgot">
				<div class="ef-form-header">
					<h2 class="ef-auth-title"><?php esc_html_e( 'Reset Password', 'eventflow' ); ?></h2>
					<p class="ef-auth-subtitle"><?php esc_html_e( 'Enter your registered email address and we will dispatch a password reset link.', 'eventflow' ); ?></p>
				</div>

				<form id="ef-forgot-form" class="ef-auth-form" method="post" novalidate>
					<input type="hidden" name="action" value="eventflow_forgot_password">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'eventflow_auth_nonce' ) ); ?>">

					<!-- Email Address Input -->
					<div class="ef-input-field">
						<span class="ef-field-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
						</span>
						<input type="email" id="ef-forgot-email" name="user_login" class="ef-input-control" placeholder="<?php esc_attr_e( 'Registered Email Address', 'eventflow' ); ?>" required autocomplete="email">
					</div>

					<!-- Send Reset Link Button -->
					<div class="ef-form-actions">
						<button type="submit" class="ef-btn-teal ef-btn-pill ef-submit-btn">
							<span class="ef-btn-text"><?php esc_html_e( 'Send Reset Link', 'eventflow' ); ?></span>
							<span class="ef-spinner" style="display:none;"></span>
						</button>
					</div>

					<!-- Back to Login Link -->
					<div class="ef-forgot-back">
						<button type="button" class="ef-link-btn ef-auth-toggle-link" data-switch-to="login">
							&larr; <?php esc_html_e( 'Back to Sign In', 'eventflow' ); ?>
						</button>
					</div>
				</form>
			</div>

		</div>
	</div>
</div>
