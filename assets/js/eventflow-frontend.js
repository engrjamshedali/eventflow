/**
 * EventFlow Frontend Interactions & Dynamic Forms Controller
 *
 * Powers the Split-Screen Authentication experience, Live Events discovery filtering,
 * interactive activity detail modals, dynamic team builder, and payment uploads.
 */

(function ($) {
	'use strict';

	var EventFlowFrontend = {
		activeFilter: 'all',
		searchQuery: '',

		init: function () {
			this.bindSplitScreenAuth();
			this.bindPasswordVisibilityToggle();
			this.bindAcademicToggle();
			this.bindAuthForms();
			this.bindLiveEventsFilteringAndSearch();
			this.bindTeamBuilder();
			this.bindStepperNavigation();
			this.bindPaymentForm();
			this.bindNotificationActions();
			this.bindCopyButtons();
			this.bindCountdown();
			this.bindActivityModal();
			this.bindTrackingForm();
			this.bindProfileForm();
			this.bindModalDismiss();
		},

		/**
		 * 1. Split-Screen Authentication Mode Transitions
		 */
		bindSplitScreenAuth: function () {
			var self = this;
			var $container = $('#ef-auth-split-container');
			if (!$container.length) return;

			function switchMode(targetMode) {
				if (!targetMode) return;

				// Clear alert message
				$('#ef-auth-alert').hide().empty().removeClass('ef-alert-danger ef-alert-success');

				// Update container class for desktop sliding/flipping & mobile view switching
				$container.removeClass('ef-mode-login ef-mode-register ef-mode-forgot')
					.addClass('ef-mode-' + targetMode);

				// Update mobile switch buttons
				$('.ef-mobile-switch-btn').removeClass('active');
				$('.ef-mobile-switch-btn[data-switch-to="' + targetMode + '"]').addClass('active');

				// Focus appropriate first input
				if (targetMode === 'login') {
					$('#ef-login-user').focus();
				} else if (targetMode === 'register') {
					$('#ef-reg-name').focus();
				} else if (targetMode === 'forgot') {
					$('#ef-forgot-email').focus();
				}
			}

			// Switch triggers (buttons and links)
			$(document).on('click', '.ef-auth-toggle-btn, .ef-auth-toggle-link, .ef-mobile-switch-btn', function (e) {
				e.preventDefault();
				var targetMode = $(this).data('switch-to');
				switchMode(targetMode);
			});

			// Check URL hash on page load
			if (window.location.hash) {
				var hash = window.location.hash.replace('#', '');
				if (hash === 'register' || hash === 'login' || hash === 'forgot') {
					switchMode(hash);
				}
			}
		},

		/**
		 * 2. Password Visibility Toggle (Eye Icon)
		 */
		bindPasswordVisibilityToggle: function () {
			$(document).on('click', '.ef-pwd-toggle', function (e) {
				e.preventDefault();
				var $btn = $(this);
				var $input = $btn.siblings('.ef-password-input');
				if (!$input.length) return;

				var currentType = $input.attr('type');
				if (currentType === 'password') {
					$input.attr('type', 'text');
					$btn.find('.ef-eye-open').hide();
					$btn.find('.ef-eye-closed').show();
					$btn.attr('aria-label', 'Hide password');
				} else {
					$input.attr('type', 'password');
					$btn.find('.ef-eye-open').show();
					$btn.find('.ef-eye-closed').hide();
					$btn.attr('aria-label', 'Show password');
				}
			});
		},

		/**
		 * 3. Optional Academic Profile Fields Accordion Toggle
		 */
		bindAcademicToggle: function () {
			$('#ef-toggle-academic').on('click', function (e) {
				e.preventDefault();
				var $content = $('#ef-academic-fields');
				$content.slideToggle(200);
			});
		},

		/**
		 * 4. AJAX Auth Forms (Register, Login, Forgot Password)
		 */
		bindAuthForms: function () {
			var self = this;

			function getAjaxUrl() {
				var url = (window.eventflow_vars && window.eventflow_vars.ajax_url)
					? window.eventflow_vars.ajax_url
					: ($('.ef-auth-data-bridge').data('ajax-url') || '/wp-admin/admin-ajax.php');

				// Prevent mixed-content blockage if current page is HTTPS
				if (window.location.protocol === 'https:' && url.indexOf('http:') === 0) {
					url = url.replace(/^http:/, 'https:');
				}
				return url;
			}

			function getAuthNonce($form) {
				var n = ($form && $form.find('input[name="nonce"]').val()) ||
					(window.eventflow_vars && window.eventflow_vars.auth_nonce) ||
					$('.ef-auth-data-bridge').data('auth-nonce') ||
					(window.eventflow_vars && window.eventflow_vars.nonce) ||
					'';
				return n;
			}

			function extractErrorMessage(xhr, defaultMsg) {
				var msg = '';
				if (xhr && xhr.responseJSON) {
					if (xhr.responseJSON.data) {
						msg = typeof xhr.responseJSON.data === 'string'
							? xhr.responseJSON.data
							: (xhr.responseJSON.data.message || '');
					} else if (xhr.responseJSON.message) {
						msg = xhr.responseJSON.message;
					}
				}

				if (!msg && xhr && xhr.responseText) {
					try {
						var parsed = JSON.parse(xhr.responseText);
						if (parsed && parsed.data) {
							msg = typeof parsed.data === 'string'
								? parsed.data
								: (parsed.data.message || '');
						} else if (parsed && parsed.message) {
							msg = parsed.message;
						}
					} catch (e) {}
				}

				if (!msg) {
					if (xhr && xhr.status === 403) {
						msg = 'Security verification failed or session expired. Please refresh the page and try again.';
					} else if (xhr && xhr.status === 429) {
						msg = 'Too many requests. Please wait a moment before trying again.';
					} else if (xhr && xhr.status === 500) {
						msg = 'A server error occurred. Please contact the administrator or check server logs.';
					} else if (xhr && (xhr.status === 0 || (typeof navigator !== 'undefined' && !navigator.onLine))) {
						msg = 'Unable to connect to the server. Please check your internet connection.';
					} else {
						msg = defaultMsg || 'Unable to connect to the authentication server.';
					}
				}
				return msg;
			}

			// Registration Form Submission
			$('#ef-register-form').on('submit', function (e) {
				e.preventDefault();
				var $form = $(this);
				var $alert = $('#ef-auth-alert');
				var $btn = $form.find('.ef-submit-btn');

				// Basic client-side checks
				var pwd = $form.find('#ef-reg-pwd').val();
				var pwdCf = $form.find('#ef-reg-pwd-cf').val();
				if (pwd && pwdCf && pwd !== pwdCf) {
					$alert.removeClass('ef-alert-success').addClass('ef-alert-danger')
						.html('Passwords do not match. Please verify your password entry.').slideDown(150);
					return;
				}

				$btn.prop('disabled', true);
				$btn.find('.ef-btn-text').hide();
				$btn.find('.ef-spinner').show();
				$alert.hide();

				var rawData = $form.serializeArray();
				var formData = [];
				var hasAction = false;
				var hasNonce = false;

				for (var i = 0; i < rawData.length; i++) {
					if (rawData[i].name === 'action') {
						hasAction = true;
						formData.push({ name: 'action', value: 'eventflow_register' });
					} else if (rawData[i].name === 'nonce') {
						hasNonce = true;
						formData.push({ name: 'nonce', value: rawData[i].value || getAuthNonce($form) });
					} else {
						formData.push(rawData[i]);
					}
				}
				if (!hasAction) formData.push({ name: 'action', value: 'eventflow_register' });
				if (!hasNonce) formData.push({ name: 'nonce', value: getAuthNonce($form) });

				$.post(getAjaxUrl(), formData, function (res) {
					$btn.prop('disabled', false);
					$btn.find('.ef-btn-text').show();
					$btn.find('.ef-spinner').hide();

					if (res && res.success) {
						$alert.removeClass('ef-alert-danger').addClass('ef-alert-success')
							.html('✓ ' + (res.data && res.data.message ? res.data.message : 'Registration successful!')).slideDown(150);
						setTimeout(function () {
							window.location.href = (res.data && res.data.redirect) || (window.eventflow_vars && window.eventflow_vars.dashboard_url) || '/';
						}, 900);
					} else {
						var err = (res && res.data && res.data.message) ? res.data.message : 'Registration failed. Please try again.';
						$alert.removeClass('ef-alert-success').addClass('ef-alert-danger')
							.html(err).slideDown(150);
					}
				}).fail(function (xhr) {
					$btn.prop('disabled', false);
					$btn.find('.ef-btn-text').show();
					$btn.find('.ef-spinner').hide();
					var err = extractErrorMessage(xhr, 'A network error occurred. Please try again.');
					$alert.removeClass('ef-alert-success').addClass('ef-alert-danger')
						.html(err).slideDown(150);
				});
			});

			// Login Form Submission
			$('#ef-login-form').on('submit', function (e) {
				e.preventDefault();
				var $form = $(this);
				var $alert = $('#ef-auth-alert');
				var $btn = $form.find('.ef-submit-btn');

				$btn.prop('disabled', true);
				$btn.find('.ef-btn-text').hide();
				$btn.find('.ef-spinner').show();
				$alert.hide();

				var rawData = $form.serializeArray();
				var formData = [];
				var hasAction = false;
				var hasNonce = false;

				for (var i = 0; i < rawData.length; i++) {
					if (rawData[i].name === 'action') {
						hasAction = true;
						formData.push({ name: 'action', value: 'eventflow_login' });
					} else if (rawData[i].name === 'nonce') {
						hasNonce = true;
						formData.push({ name: 'nonce', value: rawData[i].value || getAuthNonce($form) });
					} else {
						formData.push(rawData[i]);
					}
				}
				if (!hasAction) formData.push({ name: 'action', value: 'eventflow_login' });
				if (!hasNonce) formData.push({ name: 'nonce', value: getAuthNonce($form) });

				$.post(getAjaxUrl(), formData, function (res) {
					$btn.prop('disabled', false);
					$btn.find('.ef-btn-text').show();
					$btn.find('.ef-spinner').hide();

					if (res && res.success) {
						$alert.removeClass('ef-alert-danger').addClass('ef-alert-success')
							.html('✓ ' + (res.data && res.data.message ? res.data.message : 'Login successful!')).slideDown(150);
						setTimeout(function () {
							window.location.href = (res.data && res.data.redirect) || (window.eventflow_vars && window.eventflow_vars.dashboard_url) || '/';
						}, 700);
					} else {
						var err = (res && res.data && res.data.message) ? res.data.message : 'Invalid credentials. Please try again.';
						$alert.removeClass('ef-alert-success').addClass('ef-alert-danger')
							.html(err).slideDown(150);
					}
				}).fail(function (xhr) {
					$btn.prop('disabled', false);
					$btn.find('.ef-btn-text').show();
					$btn.find('.ef-spinner').hide();
					var err = extractErrorMessage(xhr, 'Unable to connect to the authentication server.');
					$alert.removeClass('ef-alert-success').addClass('ef-alert-danger')
						.html(err).slideDown(150);
				});
			});

			// Forgot Password Form Submission
			$('#ef-forgot-form').on('submit', function (e) {
				e.preventDefault();
				var $form = $(this);
				var $alert = $('#ef-auth-alert');
				var $btn = $form.find('.ef-submit-btn');

				$btn.prop('disabled', true);
				$btn.find('.ef-btn-text').hide();
				$btn.find('.ef-spinner').show();

				var rawData = $form.serializeArray();
				var formData = [];
				var hasAction = false;
				var hasNonce = false;

				for (var i = 0; i < rawData.length; i++) {
					if (rawData[i].name === 'action') {
						hasAction = true;
						formData.push({ name: 'action', value: 'eventflow_forgot_password' });
					} else if (rawData[i].name === 'nonce') {
						hasNonce = true;
						formData.push({ name: 'nonce', value: rawData[i].value || getAuthNonce($form) });
					} else {
						formData.push(rawData[i]);
					}
				}
				if (!hasAction) formData.push({ name: 'action', value: 'eventflow_forgot_password' });
				if (!hasNonce) formData.push({ name: 'nonce', value: getAuthNonce($form) });

				$.post(getAjaxUrl(), formData, function (res) {
					$btn.prop('disabled', false);
					$btn.find('.ef-btn-text').show();
					$btn.find('.ef-spinner').hide();

					if (res && res.success) {
						$alert.removeClass('ef-alert-danger').addClass('ef-alert-success')
							.html('✓ ' + (res.data && res.data.message ? res.data.message : 'Reset link dispatched.')).slideDown(150);
					} else {
						var err = (res && res.data && res.data.message) ? res.data.message : 'Unable to complete request.';
						$alert.removeClass('ef-alert-success').addClass('ef-alert-danger')
							.html(err).slideDown(150);
					}
				}).fail(function (xhr) {
					$btn.prop('disabled', false);
					$btn.find('.ef-btn-text').show();
					$btn.find('.ef-spinner').hide();
					var err = extractErrorMessage(xhr, 'Unable to complete request. Please verify connection.');
					$alert.removeClass('ef-alert-success').addClass('ef-alert-danger')
						.html(err).slideDown(150);
				});
			});
		},

		/**
		 * 5. Live Events Discovery Filtering & Instant Search
		 */
		bindLiveEventsFilteringAndSearch: function () {
			var self = this;
			var $grid = $('#ef-discovery-grid');
			if (!$grid.length) return;

			function applyFilters() {
				var query = (self.searchQuery || '').trim().toLowerCase();
				var filter = self.activeFilter;
				var visibleCount = 0;

				$grid.find('.ef-event-card').each(function () {
					var $card = $(this);
					var cardStatus = $card.attr('data-status') || '';
					var isRegistered = $card.attr('data-registered') === 'true';
					var cardTitle = $card.attr('data-title') || '';
					var cardDesc = $card.attr('data-desc') || '';

					// Check filter pill category
					var matchesCategory = false;
					if (filter === 'all') {
						matchesCategory = true;
					} else if (filter === 'open') {
						matchesCategory = (cardStatus === 'open');
					} else if (filter === 'coming_soon') {
						matchesCategory = (cardStatus === 'coming_soon');
					} else if (filter === 'closed') {
						matchesCategory = (cardStatus === 'closed' || cardStatus === 'full');
					} else if (filter === 'registered') {
						matchesCategory = isRegistered;
					}

					// Check search text query
					var matchesSearch = true;
					if (query.length > 0) {
						matchesSearch = (cardTitle.indexOf(query) !== -1 || cardDesc.indexOf(query) !== -1);
					}

					if (matchesCategory && matchesSearch) {
						$card.fadeIn(200);
						visibleCount++;
					} else {
						$card.hide();
					}
				});

				if (visibleCount === 0) {
					$('#ef-no-results').fadeIn(150);
				} else {
					$('#ef-no-results').hide();
				}
			}

			// Filter pill click
			$('.ef-filter-btn').on('click', function () {
				$('.ef-filter-btn').removeClass('active');
				$(this).addClass('active');
				if (this.scrollIntoView) {
					try {
						this.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
					} catch (e) {}
				}
				self.activeFilter = $(this).data('filter') || 'all';
				applyFilters();
			});

			// Instant search input
			$('#ef-event-search-input').on('input', function () {
				self.searchQuery = $(this).val();
				if (self.searchQuery.length > 0) {
					$('#ef-search-clear').show();
				} else {
					$('#ef-search-clear').hide();
				}
				applyFilters();
			});

			// Clear search button
			$('#ef-search-clear').on('click', function () {
				$('#ef-event-search-input').val('');
				self.searchQuery = '';
				$(this).hide();
				applyFilters();
			});

			// Reset filters button in no-results state
			$('#ef-reset-filters-btn').on('click', function () {
				$('#ef-event-search-input').val('');
				self.searchQuery = '';
				$('#ef-search-clear').hide();
				$('.ef-filter-btn').removeClass('active');
				$('.ef-filter-btn[data-filter="all"]').addClass('active');
				self.activeFilter = 'all';
				applyFilters();
			});
		},

		/**
		 * 6. Dynamic Team Builder, Live Fee Calculation, Dropzone & Multi-Step Registration
		 */
		bindTeamBuilder: function () {
			var $form = $('#ef-registration-form');
			if (!$form.length) return;

			var $app = $('#ef-registration-app');
			var catalog = $app.data('catalog') || {};
			var currency = $app.data('currency') || 'PKR';

			function getActiveActivity() {
				var selectedId = $('#ef-activity-select').val();
				if (catalog && catalog[selectedId]) {
					return catalog[selectedId];
				}
				var $opt = $('#ef-activity-select option:selected');
				return {
					id: selectedId,
					title: $opt.text().split('(')[0].trim(),
					participation_type: $opt.data('type') || 'both',
					min_team_size: parseInt($opt.data('min') || 1, 10),
					max_team_size: parseInt($opt.data('max') || 4, 10),
					reg_fee: parseFloat($opt.data('fee') || 0),
					fee_calculation: $opt.data('calculation') || 'per_team',
					payment_proof_required: parseInt($opt.data('proof') || 1, 10)
				};
			}

			function updateFeeSummary() {
				var act = getActiveActivity();
				var fee = parseFloat(act.reg_fee || 0);
				var mode = act.fee_calculation || 'per_team';
				var title = act.title || 'Selected Competition';

				var type = $('input[name="participation_type"]:checked').val() || $('#ef-participation-type-fixed').val() || 'team';
				var count = (type === 'solo') ? 1 : $('#ef-members-container .ef-member-card').length;

				var total = fee;
				if (type === 'team' && mode === 'per_person') {
					total = fee * count;
				}

				var modeLabel = (mode === 'per_person') ? 'Per Person (Fee × Participants)' : 'Per Team (Flat Fee)';
				var typeLabel = (type === 'team') ? 'Team Registration' : 'Solo Registration';

				$('#ef-sum-competition').text(title);
				$('#ef-sum-type').text(typeLabel);
				$('#ef-sum-count').text(count);
				$('#ef-sum-method').text(modeLabel);
				$('#ef-sum-base-fee').text(currency + ' ' + fee.toFixed(2));
				$('#ef-sum-total-amount').text(currency + ' ' + total.toFixed(2));
				$('#ef-form-amount').val(total.toFixed(2));
			}

			function syncActivitySettings() {
				var act = getActiveActivity();
				var minMembers = parseInt(act.min_team_size || 1, 10);
				var maxMembers = parseInt(act.max_team_size || 4, 10);
				var pType = act.participation_type || 'both';
				var fee = parseFloat(act.reg_fee || 0);
				var mode = act.fee_calculation || 'per_team';
				var modeText = (mode === 'per_person') ? 'Per Person' : 'Per Team';

				$form.data('min-members', minMembers);
				$form.data('max-members', maxMembers);
				$form.data('fee', fee);
				$form.data('fee-calculation', mode);
				$form.data('proof-required', act.payment_proof_required);

				$('#ef-display-mode-label').text(pType.charAt(0).toUpperCase() + pType.slice(1));
				$('#ef-display-limits-label').text(minMembers + ' to ' + maxMembers + ' Members');
				$('#ef-display-max-count').text(maxMembers);
				$('#ef-min-req-note').text('(Min ' + minMembers + ' required)');
				$('#ef-display-fee-label').text(currency + ' ' + fee.toFixed(2) + ' (' + modeText + ')');
				$('#ef-rc-team-desc').text('Register with ' + minMembers + ' to ' + maxMembers + ' team members with a designated Team Leader.');

				// Participation Type handling
				if (pType === 'both') {
					$('#ef-type-selector-block').slideDown(150);
					$('#ef-fixed-type-pill').slideUp(150);
					var checkedType = $('input[name="participation_type"]:checked').val() || 'team';
					switchMode(checkedType);
				} else if (pType === 'team') {
					$('#ef-type-selector-block').slideUp(150);
					$('#ef-fixed-type-pill').slideDown(150);
					$('#ef-fixed-type-msg').text('This track permits Team Registration only (' + minMembers + ' to ' + maxMembers + ' members).');
					$('#ef-participation-type-fixed').val('team');
					$('input[name="participation_type"][value="team"]').prop('checked', true);
					switchMode('team');
				} else {
					$('#ef-type-selector-block').slideUp(150);
					$('#ef-fixed-type-pill').slideDown(150);
					$('#ef-fixed-type-msg').text('This track permits Individual / Solo Registration only.');
					$('#ef-participation-type-fixed').val('solo');
					$('input[name="participation_type"][value="solo"]').prop('checked', true);
					switchMode('solo');
				}

				// Check member count against new maxMembers
				var currentMembers = $('#ef-members-container .ef-member-card').length;
				if (currentMembers >= maxMembers) {
					$('#ef-add-member-btn').prop('disabled', true);
					$('#ef-max-reached-msg').show();
				} else {
					$('#ef-add-member-btn').prop('disabled', false);
					$('#ef-max-reached-msg').hide();
				}

				renderCustomFields(act.form_fields);
				updateFeeSummary();
			}

			function escapeHtml(str) {
				if (!str) return '';
				return String(str)
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;')
					.replace(/"/g, '&quot;')
					.replace(/'/g, '&#039;');
			}

			function renderCustomFields(fields) {
				var $container = $('#ef-dynamic-custom-fields-container');
				var $body = $('#ef-dynamic-custom-fields-body');
				$body.empty();

				if (!fields || !Array.isArray(fields) || fields.length === 0) {
					$container.hide();
					return;
				}

				var html = '';
				for (var i = 0; i < fields.length; i++) {
					var f = fields[i];
					var fKey = f.key || ('field_' + i);
					var reqAttr = f.required ? ' required' : '';
					var reqMark = f.required ? ' <span class="ef-req">*</span>' : '';
					var helpHtml = f.help ? '<span class="ef-help-text">' + escapeHtml(f.help) + '</span>' : '';
					var cssClass = f.css_class ? ' ' + f.css_class : '';
					var isFullWidth = ['textarea', 'heading', 'paragraph', 'divider', 'consent'].indexOf(f.type) !== -1 || (f.css_class && f.css_class.indexOf('col-span-2') !== -1);
					var wrapperStyle = isFullWidth ? 'grid-column: 1 / -1;' : '';

					if (f.type === 'heading') {
						html += '<div class="ef-form-group" style="' + wrapperStyle + ' margin-top: 14px; margin-bottom: 4px;"><h4 style="margin: 0; color: #0F172A; font-size: 15px; border-bottom: 1px solid #E2E8F0; padding-bottom: 4px;">' + escapeHtml(f.label) + '</h4>' + helpHtml + '</div>';
					} else if (f.type === 'paragraph') {
						html += '<div class="ef-form-group" style="' + wrapperStyle + ' margin-bottom: 6px;"><p style="font-size: 13px; color: #475569; margin: 0;">' + escapeHtml(f.label) + '</p>' + helpHtml + '</div>';
					} else if (f.type === 'divider') {
						html += '<div style="' + wrapperStyle + ' margin: 10px 0;"><hr style="border: none; border-top: 1px solid #E2E8F0;"></div>';
					} else if (f.type === 'textarea') {
						html += '<div class="ef-form-group' + cssClass + '" style="' + wrapperStyle + '">';
						html += '<label>' + escapeHtml(f.label) + reqMark + '</label>';
						html += '<textarea name="custom_fields[' + fKey + ']" class="ef-input" rows="3" placeholder="' + escapeHtml(f.placeholder || '') + '"' + reqAttr + '>' + escapeHtml(f.default_val || '') + '</textarea>';
						html += helpHtml + '</div>';
					} else if (f.type === 'select') {
						html += '<div class="ef-form-group' + cssClass + '" style="' + wrapperStyle + '">';
						html += '<label>' + escapeHtml(f.label) + reqMark + '</label>';
						html += '<select name="custom_fields[' + fKey + ']" class="ef-input"' + reqAttr + '>';
						html += '<option value="">' + escapeHtml(f.placeholder || 'Select an option...') + '</option>';
						if (f.options && Array.isArray(f.options)) {
							for (var o = 0; o < f.options.length; o++) {
								var optVal = f.options[o];
								var isSel = (optVal === f.default_val) ? ' selected' : '';
								html += '<option value="' + escapeHtml(optVal) + '"' + isSel + '>' + escapeHtml(optVal) + '</option>';
							}
						}
						html += '</select>' + helpHtml + '</div>';
					} else if (f.type === 'multi_select') {
						html += '<div class="ef-form-group' + cssClass + '" style="' + wrapperStyle + '">';
						html += '<label>' + escapeHtml(f.label) + reqMark + '</label>';
						html += '<select name="custom_fields[' + fKey + '][]" class="ef-input" multiple style="height: auto; min-height: 90px;"' + reqAttr + '>';
						if (f.options && Array.isArray(f.options)) {
							for (var ms = 0; ms < f.options.length; ms++) {
								html += '<option value="' + escapeHtml(f.options[ms]) + '">' + escapeHtml(f.options[ms]) + '</option>';
							}
						}
						html += '</select>' + helpHtml + '</div>';
					} else if (f.type === 'radio') {
						html += '<div class="ef-form-group' + cssClass + '" style="' + wrapperStyle + '">';
						html += '<label style="display: block; margin-bottom: 6px;">' + escapeHtml(f.label) + reqMark + '</label>';
						if (f.options && Array.isArray(f.options)) {
							for (var r = 0; r < f.options.length; r++) {
								var rVal = f.options[r];
								var rChecked = (rVal === f.default_val) ? ' checked' : '';
								html += '<label style="display: inline-flex; align-items: center; gap: 6px; margin-right: 16px; font-size: 13.5px; cursor: pointer;"><input type="radio" name="custom_fields[' + fKey + ']" value="' + escapeHtml(rVal) + '"' + rChecked + reqAttr + '> ' + escapeHtml(rVal) + '</label>';
							}
						}
						html += helpHtml + '</div>';
					} else if (f.type === 'checkbox') {
						html += '<div class="ef-form-group' + cssClass + '" style="' + wrapperStyle + '">';
						html += '<label style="display: block; margin-bottom: 6px;">' + escapeHtml(f.label) + reqMark + '</label>';
						if (f.options && Array.isArray(f.options)) {
							for (var cb = 0; cb < f.options.length; cb++) {
								var cbVal = f.options[cb];
								html += '<label style="display: inline-flex; align-items: center; gap: 6px; margin-right: 16px; font-size: 13.5px; cursor: pointer;"><input type="checkbox" name="custom_fields[' + fKey + '][]" value="' + escapeHtml(cbVal) + '"> ' + escapeHtml(cbVal) + '</label>';
							}
						}
						html += helpHtml + '</div>';
					} else if (f.type === 'consent') {
						html += '<div class="ef-form-group' + cssClass + '" style="' + wrapperStyle + ' margin-top: 6px;">';
						html += '<label style="display: flex; align-items: flex-start; gap: 8px; font-size: 13.5px; cursor: pointer;"><input type="checkbox" name="custom_fields[' + fKey + ']" value="1" style="margin-top: 3px;"' + reqAttr + '> <span><strong>' + escapeHtml(f.label) + '</strong>' + reqMark + '</span></label>';
						html += helpHtml + '</div>';
					} else {
						var inputType = (['email', 'tel', 'number', 'date', 'time', 'url'].indexOf(f.type) !== -1) ? f.type : 'text';
						html += '<div class="ef-form-group' + cssClass + '" style="' + wrapperStyle + '">';
						html += '<label>' + escapeHtml(f.label) + reqMark + '</label>';
						html += '<input type="' + inputType + '" name="custom_fields[' + fKey + ']" class="ef-input" placeholder="' + escapeHtml(f.placeholder || '') + '" value="' + escapeHtml(f.default_val || '') + '"' + reqAttr + '>';
						html += helpHtml + '</div>';
					}
				}

				$body.html(html);
				$container.slideDown(200);
			}

			function switchMode(selectedType) {
				$('.ef-radio-card').removeClass('active');
				$('input[name="participation_type"][value="' + selectedType + '"]').closest('.ef-radio-card').addClass('active');

				if (selectedType === 'solo') {
					$('#ef-solo-fields').slideDown(200);
					$('#ef-team-fields').slideUp(200);
					$('#ef-team-name-input').prop('required', false);
					$('#ef-solo-fields input').prop('required', true);
					$('#ef-team-fields input').prop('required', false);
				} else {
					$('#ef-solo-fields').slideUp(200);
					$('#ef-team-fields').slideDown(200);
					$('#ef-team-name-input').prop('required', true);
					$('#ef-solo-fields input').prop('required', false);
					$('#ef-team-fields .ef-leader-card input').prop('required', true);
					$('#ef-members-container .ef-added-member input').prop('required', true);
				}
				updateFeeSummary();
			}

			// Competition dropdown change
			$('#ef-activity-select').on('change', function () {
				syncActivitySettings();
			});

			// Participation Type Toggle
			$('input[name="participation_type"]').on('change', function () {
				switchMode($(this).val());
			});

			// Add Team Member
			$('#ef-add-member-btn').on('click', function () {
				var maxMembers = parseInt($form.data('max-members') || 4, 10);
				var currentMembers = $('#ef-members-container .ef-member-card').length;

				if (currentMembers >= maxMembers) {
					$('#ef-max-reached-msg').show();
					$(this).prop('disabled', true);
					return;
				}

				var nextIndex = currentMembers;
				var memberNum = currentMembers + 1;

				var templateHtml = $('#ef-member-template').html();
				templateHtml = templateHtml.replace(/__INDEX__/g, nextIndex).replace(/__NUM__/g, memberNum);

				var $newCard = $(templateHtml).hide();
				$('#ef-members-container').append($newCard);
				$newCard.slideDown(200);

				$('#ef-current-count').text(memberNum);

				if (memberNum >= maxMembers) {
					$('#ef-add-member-btn').prop('disabled', true);
					$('#ef-max-reached-msg').show();
				} else {
					$('#ef-max-reached-msg').hide();
				}

				updateFeeSummary();
			});

			// Remove Team Member
			$(document).on('click', '.ef-remove-member-btn', function () {
				if (!confirm(window.eventflow_vars.i18n.confirm_remove || 'Remove this team member?')) {
					return;
				}

				var $card = $(this).closest('.ef-member-card');
				$card.slideUp(200, function () {
					$card.remove();
					// Re-index remaining members
					$('#ef-members-container .ef-member-card').each(function (idx) {
						var $c = $(this);
						$c.attr('data-index', idx);
						if (idx > 0) {
							$c.find('.ef-member-card-title .ef-badge').text('Member #' + (idx + 1));
							$c.find('input, select').each(function () {
								var name = $(this).attr('name');
								if (name) {
									$(this).attr('name', name.replace(/members\[\d+\]/, 'members[' + idx + ']'));
								}
							});
						}
					});

					var remaining = $('#ef-members-container .ef-member-card').length;
					var maxMembers = parseInt($form.data('max-members') || 4, 10);
					$('#ef-current-count').text(remaining);
					if (remaining < maxMembers) {
						$('#ef-add-member-btn').prop('disabled', false);
						$('#ef-max-reached-msg').hide();
					}
					updateFeeSummary();
				});
			});

			// Payment Screenshot Drag-and-Drop in Main Registration Form
			var $regDropzone = $('#ef-reg-dropzone');
			var $regFileInput = $('#ef-reg-proof-file');
			var $regPreviewWrap = $regDropzone.find('.ef-file-preview-wrap');
			var $regThumbWrap = $regDropzone.find('.ef-preview-thumb-wrap');
			var $regImgThumb = $('#ef-img-thumb');
			var $regPreviewName = $('#ef-preview-name');
			var $regPreviewSize = $('#ef-preview-size');
			var $regDropContent = $regDropzone.find('.ef-dropzone-content');

			$regFileInput.on('change', function () {
				var file = this.files[0];
				if (!file) return;

				// Check size limit: 5MB
				if (file.size > 5 * 1024 * 1024) {
					alert(window.eventflow_vars.i18n.error_upload || 'File size exceeds maximum allowed 5 MB.');
					$regFileInput.val('');
					return;
				}

				// Check type
				var validTypes = ['image/jpeg', 'image/png', 'application/pdf'];
				if (validTypes.indexOf(file.type) === -1 && !file.name.match(/\.(jpg|jpeg|png|pdf)$/i)) {
					alert('Only JPG, JPEG, PNG, and PDF files are allowed.');
					$regFileInput.val('');
					return;
				}

				$regPreviewName.text(file.name);
				$regPreviewSize.text('(' + (file.size / 1024).toFixed(1) + ' KB)');

				if (file.type.indexOf('image') !== -1 && window.FileReader) {
					var reader = new FileReader();
					reader.onload = function (e) {
						$regImgThumb.attr('src', e.target.result);
						$regThumbWrap.show();
					};
					reader.readAsDataURL(file);
				} else {
					$regThumbWrap.hide();
				}

				$regDropContent.hide();
				$regPreviewWrap.fadeIn(150);
			});

			$('#ef-remove-file-btn, #ef-replace-file-btn').on('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				$regFileInput.val('');
				$regImgThumb.attr('src', '');
				$regPreviewWrap.hide();
				$regDropContent.show();
				if ($(this).attr('id') === 'ef-replace-file-btn') {
					$regFileInput.trigger('click');
				}
			});

			$regDropzone.on('dragover', function () { $(this).addClass('dragover'); });
			$regDropzone.on('dragleave drop', function () { $(this).removeClass('dragover'); });

			// Submit Dynamic Form via AJAX with FormData
			$form.on('submit', function (e) {
				e.preventDefault();
				var $alert = $('#ef-reg-alert').hide().removeClass('ef-alert-danger ef-alert-success');
				var $btn = $('#ef-reg-submit-btn');

				var type = $('input[name="participation_type"]:checked').val() || $('#ef-participation-type-fixed').val() || 'team';
				var minMembers = parseInt($form.data('min-members') || 1, 10);
				var maxMembers = parseInt($form.data('max-members') || 4, 10);

				// Validate Team members count if team
				if (type === 'team') {
					var count = $('#ef-members-container .ef-member-card').length;
					if (count < minMembers) {
						$alert.addClass('ef-alert-danger').html('Minimum team members required: ' + minMembers + '. You currently have ' + count + '.').slideDown(200);
						$('html, body').animate({ scrollTop: $('#ef-team-fields').offset().top - 40 }, 400);
						return;
					}
					if (count > maxMembers) {
						$alert.addClass('ef-alert-danger').html('Maximum team members allowed: ' + maxMembers + '. You currently have ' + count + '.').slideDown(200);
						return;
					}

					// Check duplicate email & phone within team
					var emails = [];
					var phones = [];
					var hasDuplicate = false;
					$('#ef-members-container .ef-member-card').each(function (idx) {
						var email = $(this).find('input[type="email"]').val().trim().toLowerCase();
						var phone = $(this).find('input[type="tel"]').val().replace(/[^0-9]/g, '');

						if (email) {
							if (emails.indexOf(email) !== -1) {
								$alert.addClass('ef-alert-danger').html('Duplicate email detected (' + email + ') across team members. Each member must have a unique email.').slideDown(200);
								hasDuplicate = true;
								return false;
							}
							emails.push(email);
						}

						if (phone && phone.length >= 7) {
							if (phones.indexOf(phone) !== -1) {
								$alert.addClass('ef-alert-danger').html('Duplicate contact number detected across team members. Each member must have a distinct phone number.').slideDown(200);
								hasDuplicate = true;
								return false;
							}
							phones.push(phone);
						}
					});

					if (hasDuplicate) return;
				}

				// Check required dynamic custom fields
				var missingCustom = false;
				$('#ef-dynamic-custom-fields-body [required]').each(function () {
					var $inp = $(this);
					if ($inp.is(':checkbox') || $inp.is(':radio')) {
						var name = $inp.attr('name');
						if (!$('input[name="' + name + '"]:checked').length) {
							missingCustom = true;
							$inp.focus();
							return false;
						}
					} else if (!$inp.val() || !String($inp.val()).trim()) {
						missingCustom = true;
						$inp.focus();
						return false;
					}
				});
				if (missingCustom) {
					$alert.addClass('ef-alert-danger').html('Please complete all required competition questions before submitting.').slideDown(200);
					$('html, body').animate({ scrollTop: $('#ef-dynamic-custom-fields-container').offset().top - 40 }, 400);
					return;
				}

				// Check declaration checkbox
				if (!$('#ef-declaration-checkbox').is(':checked')) {
					$alert.addClass('ef-alert-danger').html('Please review and check the official declaration box before submitting.').slideDown(200);
					return;
				}

				// Check proof file if required
				var proofRequired = (parseInt($form.data('proof-required') || 1, 10) === 1);
				var feeAmount = parseFloat($('#ef-form-amount').val() || 0);
				if (proofRequired && feeAmount > 0 && !$regFileInput[0].files[0]) {
					$alert.addClass('ef-alert-danger').html('Payment receipt screenshot or PDF is mandatory for this competition. Please upload proof before submitting.').slideDown(200);
					$('html, body').animate({ scrollTop: $('#ef-payment-proof-section').offset().top - 40 }, 400);
					return;
				}

				// Disable button & show spinner
				$btn.prop('disabled', true);
				$btn.find('.ef-btn-text').hide();
				$btn.find('.ef-btn-spinner').show();

				var formData = new FormData($form[0]);
				formData.append('action', 'eventflow_submit_registration');
				formData.append('nonce', window.eventflow_vars.nonce);

				$.ajax({
					url: window.eventflow_vars.ajax_url,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function (res) {
						$btn.prop('disabled', false);
						$btn.find('.ef-btn-text').show();
						$btn.find('.ef-btn-spinner').hide();

						if (res.success) {
							// Smoothly switch from form to success confirmation card
							$('#ef-reg-form-wrap').slideUp(300, function () {
								$('#ef-success-reg-id').text(res.data.reg_id);
								$('#ef-success-track').text($('#ef-activity-select option:selected').text().split('(')[0].trim());
								$('#ef-success-name').text(res.data.team_name || res.data.leader_name);
								$('#ef-success-leader').text(res.data.leader_name + ' (' + res.data.leader_email + ')');
								$('#ef-success-fee').text(currency + ' ' + parseFloat(res.data.total_fee || 0).toFixed(2));
								$('#ef-success-payment-badge').text('Payment: ' + (res.data.status_label || 'Pending Verification'));
								$('#ef-success-receipt-btn').attr('href', res.data.receipt_url);

								$('#ef-reg-success-view').fadeIn(300);
								$('html, body').animate({ scrollTop: $('#ef-registration-app').offset().top - 30 }, 400);
							});
						} else {
							$alert.addClass('ef-alert-danger').html(res.data.message || 'Submission failed.').slideDown(200);
							$('html, body').animate({ scrollTop: $alert.offset().top - 40 }, 400);
						}
					},
					error: function () {
						$btn.prop('disabled', false);
						$btn.find('.ef-btn-text').show();
						$btn.find('.ef-btn-spinner').hide();
						$alert.addClass('ef-alert-danger').html('Submission failed due to a network or server error. Please try again.').slideDown(200);
					}
				});
			});

			// Initialize on page load
			syncActivitySettings();
		},

		/**
		 * 7. Payment Submission Form & File Drag/Drop
		 */
		bindPaymentForm: function () {
			var $form = $('#ef-payment-form');
			if (!$form.length) return;

			var $dropzone = $('#ef-file-dropzone');
			var $fileInput = $('#ef-proof-file');
			var $previewWrap = $dropzone.find('.ef-file-preview-wrap');
			var $previewName = $previewWrap.find('.ef-file-name');
			var $content = $dropzone.find('.ef-dropzone-content');
			var $progressWrap = $('#ef-upload-progress-wrap');
			var $progressPercent = $('#ef-upload-progress-percent');
			var $progressFill = $('#ef-upload-progress-fill');
			var $progressStatus = $('#ef-upload-progress-status');

			function validateReceiptFile(file) {
				if (!file) {
					return 'Please select a payment screenshot or receipt document (JPG, PNG, or PDF).';
				}
				var maxSize = 5 * 1024 * 1024; // 5 MB
				if (file.size > maxSize) {
					return 'File size exceeds maximum allowed limit (5 MB). Please choose a compressed image or PDF.';
				}
				var fileName = file.name || '';
				var ext = fileName.split('.').pop().toLowerCase();
				var validExts = ['jpg', 'jpeg', 'png', 'pdf'];
				if ($.inArray(ext, validExts) === -1) {
					return 'Invalid file format. Only JPG, PNG, and PDF files are allowed.';
				}
				return null;
			}

			$fileInput.on('change', function () {
				var file = this.files[0];
				var $alert = $('#ef-payment-alert').hide();
				if (file) {
					var err = validateReceiptFile(file);
					if (err) {
						$alert.removeClass('ef-alert-success').addClass('ef-alert-danger').html(err).show();
						$fileInput.val('');
						$previewWrap.hide();
						$content.show();
						return;
					}
					$previewName.text(file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)');
					$content.hide();
					$previewWrap.show();
				}
			});

			$dropzone.find('.ef-remove-file-btn').on('click', function (e) {
				e.stopPropagation();
				$fileInput.val('');
				$previewWrap.hide();
				$content.show();
				$progressWrap.hide();
			});

			// Drag Over Styles
			$dropzone.on('dragover', function () { $(this).addClass('dragover'); });
			$dropzone.on('dragleave drop', function () { $(this).removeClass('dragover'); });

			$form.on('submit', function (e) {
				e.preventDefault();
				var $alert = $('#ef-payment-alert').hide();
				var $btn = $('#ef-pay-submit-btn');

				var file = $fileInput[0].files[0];
				var fileErr = validateReceiptFile(file);
				if (fileErr) {
					$alert.removeClass('ef-alert-success').addClass('ef-alert-danger').html(fileErr).show();
					return;
				}

				$btn.prop('disabled', true);
				$btn.find('.ef-btn-text').hide();
				$btn.find('.ef-btn-spinner').show();

				$progressPercent.text('0%');
				$progressFill.css('width', '0%');
				$progressStatus.text('Uploading payment proof...');
				$progressWrap.slideDown(200);

				var formData = new FormData($form[0]);
				formData.append('action', 'eventflow_submit_payment');
				formData.append('nonce', window.eventflow_vars.nonce);

				$.ajax({
					url: window.eventflow_vars.ajax_url,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					xhr: function () {
						var xhr = new window.XMLHttpRequest();
						if (xhr.upload) {
							xhr.upload.addEventListener('progress', function (evt) {
								if (evt.lengthComputable) {
									var percentComplete = Math.round((evt.loaded / evt.total) * 100);
									$progressPercent.text(percentComplete + '%');
									$progressFill.css('width', percentComplete + '%');
									if (percentComplete >= 100) {
										$progressStatus.text('Processing and securing file on server...');
									}
								}
							}, false);
						}
						return xhr;
					},
					success: function (res) {
						$btn.prop('disabled', false);
						$btn.find('.ef-btn-text').show();
						$btn.find('.ef-btn-spinner').hide();

						if (res.success) {
							$progressFill.css('width', '100%');
							$progressPercent.text('100%');
							$progressStatus.text('Upload complete! Redirecting...');
							$alert.removeClass('ef-alert-danger').addClass('ef-alert-success').html(res.data.message || 'Payment proof submitted successfully!').show();
							setTimeout(function () {
								window.location.reload();
							}, 1200);
						} else {
							$progressWrap.hide();
							$alert.removeClass('ef-alert-success').addClass('ef-alert-danger').html(res.data.message || 'Payment submission failed.').show();
						}
					},
					error: function (xhr) {
						$btn.prop('disabled', false);
						$btn.find('.ef-btn-text').show();
						$btn.find('.ef-btn-spinner').hide();
						$progressWrap.hide();
						var msg = 'File upload failed. Check connection or size limits.';
						if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
							msg = xhr.responseJSON.data.message;
						}
						$alert.removeClass('ef-alert-success').addClass('ef-alert-danger').html(msg).show();
					}
				});
			});
		},

		/**
		 * 7.1 In-Dashboard Notification Actions
		 */
		bindNotificationActions: function () {
			// Mark single notification as read
			$(document).on('click', '.ef-mark-notif-read-btn', function (e) {
				e.preventDefault();
				var $btn = $(this);
				var notifId = $btn.data('id');
				if (!notifId) return;

				$btn.prop('disabled', true).text('Updating...');

				$.post(window.eventflow_vars.ajax_url, {
					action: 'eventflow_mark_notification_read',
					notification_id: notifId,
					nonce: window.eventflow_vars.nonce
				}, function (res) {
					if (res.success) {
						var $card = $btn.closest('.ef-notif-card');
						$card.find('.ef-badge-dot').remove();
						$card.css('background', '#FFFFFF');
						$btn.remove();

						var $badge = $('#ef-nav-unread-count');
						if ($badge.length) {
							var count = parseInt($badge.text(), 10) || 1;
							count = Math.max(0, count - 1);
							if (count > 0) {
								$badge.text(count);
							} else {
								$badge.remove();
							}
						}
					} else {
						$btn.prop('disabled', false).text('Mark as read');
					}
				}).fail(function () {
					$btn.prop('disabled', false).text('Mark as read');
				});
			});

			// Mark all notifications as read
			$(document).on('click', '#ef-mark-all-notifs-btn', function (e) {
				e.preventDefault();
				var $btn = $(this);
				$btn.prop('disabled', true).text('Updating...');

				$.post(window.eventflow_vars.ajax_url, {
					action: 'eventflow_mark_all_notifications_read',
					nonce: window.eventflow_vars.nonce
				}, function (res) {
					if (res.success) {
						$('.ef-notif-card').css('background', '#FFFFFF');
						$('.ef-badge-dot').remove();
						$('.ef-mark-notif-read-btn').remove();
						$('#ef-nav-unread-count').remove();
						$btn.remove();
					} else {
						$btn.prop('disabled', false).text('Mark All as Read');
					}
				}).fail(function () {
					$btn.prop('disabled', false).text('Mark All as Read');
				});
			});
		},

		/**
		 * 7.5 Interactive Stepper Navigation & Scroll Spy
		 */
		bindStepperNavigation: function () {
			$(document).on('click', '.ef-step-item', function (e) {
				e.preventDefault();
				var stepNum = parseInt($(this).data('step'), 10);
				var $target = $('#ef-form-step-' + stepNum);
				if (!$target.length && stepNum === 4) {
					$target = $('#ef-payment-proof-section');
				}
				if ($target.length) {
					var offset = $target.offset().top - 40;
					$('html, body').stop().animate({ scrollTop: offset }, 350);
					$('.ef-step-item').removeClass('active');
					$(this).addClass('active');
				}
			});

			// Scroll spy to highlight active step dynamically
			var ticking = false;
			$(window).on('scroll', function () {
				if (ticking) return;
				ticking = true;
				requestAnimationFrame(function () {
					var scrollPos = $(window).scrollTop() + 200;
					var activeStep = 1;

					var steps = [
						{ step: 1, $el: $('#ef-form-step-1') },
						{ step: 2, $el: $('#ef-form-step-2') },
						{ step: 3, $el: $('#ef-form-step-3') },
						{ step: 4, $el: $('#ef-payment-proof-section') }
					];

					for (var i = 0; i < steps.length; i++) {
						if (steps[i].$el && steps[i].$el.length) {
							var top = steps[i].$el.offset().top;
							if (scrollPos >= top) {
								activeStep = steps[i].step;
							}
						}
					}

					$('.ef-step-item').each(function () {
						var s = parseInt($(this).data('step'), 10);
						if (s === activeStep) {
							$(this).addClass('active').removeClass('completed');
						} else if (s < activeStep) {
							$(this).addClass('completed').removeClass('active');
						} else {
							$(this).removeClass('active completed');
						}
					});

					// Update step lines
					$('.ef-step-line').each(function (idx) {
						if (idx < activeStep - 1) {
							$(this).addClass('active');
						} else {
							$(this).removeClass('active');
						}
					});

					ticking = false;
				});
			});
		},

		/**
		 * 8. Copy to Clipboard Buttons
		 */
		bindCopyButtons: function () {
			$(document).on('click', '.ef-copy-btn', function () {
				var targetSelector = $(this).data('clipboard-target');
				var text = $(targetSelector).text().trim();
				if (!text) return;

				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text);
				} else {
					var $temp = $('<input>');
					$('body').append($temp);
					$temp.val(text).select();
					document.execCommand('copy');
					$temp.remove();
				}

				var $btn = $(this);
				var originalHtml = $btn.html();
				$btn.addClass('copied').html('<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align: -2px; margin-right: 3px;"><polyline points="20 6 9 17 4 12"/></svg> Copied!');
				setTimeout(function () {
					$btn.removeClass('copied').html(originalHtml);
				}, 1800);
			});
		},

		/**
		 * 9. Live Registration Countdown Timer
		 */
		bindCountdown: function () {
			var $countdown = $('.ef-countdown');
			if (!$countdown.length) return;

			var deadlineStr = $countdown.data('deadline');
			if (!deadlineStr) return;

			var targetDate = new Date(deadlineStr).getTime();

			function updateTimer() {
				var now = new Date().getTime();
				var distance = targetDate - now;

				if (distance <= 0) {
					$countdown.html('<div class="ef-countdown-title">Registration Closed</div>');
					return;
				}

				var days = Math.floor(distance / (1000 * 60 * 60 * 24));
				var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
				var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
				var seconds = Math.floor((distance % (1000 * 60)) / 1000);

				$countdown.find('.ef-days').text(days < 10 ? '0' + days : days);
				$countdown.find('.ef-hours').text(hours < 10 ? '0' + hours : hours);
				$countdown.find('.ef-minutes').text(minutes < 10 ? '0' + minutes : minutes);
				$countdown.find('.ef-seconds').text(seconds < 10 ? '0' + seconds : seconds);
			}

			updateTimer();
			setInterval(updateTimer, 1000);
		},

		/**
		 * 10. Activity Detail Modal (Loaded Dynamically)
		 */
		bindActivityModal: function () {
			$(document).on('click', '.ef-open-activity-modal', function (e) {
				e.preventDefault();
				var actId = $(this).data('id');
				if (!actId) return;

				var $modal = $('#ef-activity-detail-modal');
				var $body = $('#ef-modal-act-body').html('<div class="ef-modal-loading"><div class="ef-spinner ef-spinner-teal"></div><span>Loading event details...</span></div>');
				var $regBtn = $('#ef-modal-act-register-btn');

				$modal.fadeIn(150).addClass('active').attr('aria-hidden', 'false');
				$('body').css('overflow', 'hidden');

				$.getJSON(window.eventflow_vars.ajax_url, {
					action: 'eventflow_get_activity',
					id: actId
				}, function (res) {
					if (res.success) {
						var act = res.data;
						$('#ef-modal-act-title').text(act.title);

						var feeStr = (parseFloat(act.reg_fee) > 0) ? (act.currency + ' ' + parseFloat(act.reg_fee).toFixed(2)) : 'Free Entry';
						var partLabel = act.participation_type.toUpperCase();
						if (act.max_team_size > 1) {
							partLabel += ' (' + act.min_team_size + '-' + act.max_team_size + ' Members)';
						}

						var html = '<div class="ef-modal-spec-sheet">';
						if (act.image_url) {
							html += '<div class="ef-modal-hero-img"><img src="' + act.image_url + '" alt="' + act.title + '"></div>';
						}

						html += '<div class="ef-modal-section">';
						html += '<h3 class="ef-spec-heading">Description & Overview</h3>';
						html += '<div class="ef-spec-text">' + (act.description || 'No description provided.') + '</div>';
						html += '</div>';

						if (act.rules) {
							html += '<div class="ef-modal-section">';
							html += '<h3 class="ef-spec-heading">Rules & Regulations</h3>';
							html += '<div class="ef-spec-box">' + act.rules.replace(/\n/g, '<br>') + '</div>';
							html += '</div>';
						}

						if (act.eligibility) {
							html += '<div class="ef-modal-section">';
							html += '<h3 class="ef-spec-heading">Eligibility Criteria</h3>';
							html += '<div class="ef-spec-box">' + act.eligibility.replace(/\n/g, '<br>') + '</div>';
							html += '</div>';
						}

						if (act.instructions) {
							html += '<div class="ef-modal-section">';
							html += '<h3 class="ef-spec-heading">Important Instructions</h3>';
							html += '<div class="ef-spec-box ef-spec-box-teal">' + act.instructions.replace(/\n/g, '<br>') + '</div>';
							html += '</div>';
						}

						html += '<div class="ef-modal-meta-table">';
						html += '<div class="ef-modal-meta-row"><span>Registration Fee</span><strong>' + feeStr + '</strong></div>';
						html += '<div class="ef-modal-meta-row"><span>Participation Format</span><strong>' + partLabel + '</strong></div>';
						html += '<div class="ef-modal-meta-row"><span>Registration Deadline</span><strong>' + (act.formatted_deadline || 'Standard Deadline') + '</strong></div>';
						if (parseInt(act.max_participants, 10) > 0) {
							html += '<div class="ef-modal-meta-row"><span>Capacity</span><strong>' + act.current_participants + ' of ' + act.max_participants + ' Slots Reserved</strong></div>';
						}
						html += '</div>';
						html += '</div>';

						$body.html(html);

						if (act.is_open) {
							var regUrl = window.eventflow_vars.dashboard_url;
							var separator = (regUrl.indexOf('?') !== -1) ? '&' : '?';
							regUrl += separator + 'tab=register_activity&activity_id=' + act.id;
							$regBtn.attr('href', regUrl).show();
						} else {
							$regBtn.hide();
						}
					} else {
						$body.html('<p class="ef-alert ef-alert-danger">Failed to load activity details from server.</p>');
					}
				}).fail(function () {
					$body.html('<p class="ef-alert ef-alert-danger">Network error loading event specifications.</p>');
				});
			});
		},

		/**
		 * 11. Registration Tracking Tool
		 */
		bindTrackingForm: function () {
			$('#ef-track-form').on('submit', function (e) {
				e.preventDefault();
				var $form = $(this);
				var $alert = $('#ef-track-alert').hide();
				var $result = $('#ef-track-result').hide();
				var $btn = $form.find('.ef-submit-btn');

				$btn.prop('disabled', true);
				$btn.find('.ef-btn-text').hide();
				$btn.find('.ef-spinner').show();

				var postData = {
					action: 'eventflow_track_registration',
					reg_id: $('#ef-track-id').val(),
					email: $('#ef-track-email').val(),
					nonce: window.eventflow_vars.nonce
				};

				$.post(window.eventflow_vars.ajax_url, postData, function (res) {
					$btn.prop('disabled', false);
					$btn.find('.ef-btn-text').show();
					$btn.find('.ef-spinner').hide();

					if (res.success) {
						var d = res.data;
						$('#ef-result-reg-id').text(d.reg_id);
						$('#ef-result-activity').text(d.activity_title);
						$('#ef-result-team').text(d.team_name);
						$('#ef-result-pay-badge').html(d.payment_badge);
						$('#ef-result-app-badge').html(d.application_badge);
						$('#ef-result-date').text(d.created_at);
						$result.slideDown(200);
					} else {
						$alert.removeClass('ef-alert-success').addClass('ef-alert-danger').html(res.data.message).show();
					}
				}).fail(function () {
					$btn.prop('disabled', false);
					$btn.find('.ef-btn-text').show();
					$btn.find('.ef-spinner').hide();
					$alert.removeClass('ef-alert-success').addClass('ef-alert-danger').html('Lookup error. Please try again.').show();
				});
			});
		},

		/**
		 * 12. Modal Dismiss Handler
		 */
		bindModalDismiss: function () {
			function closeModal() {
				$('#ef-activity-detail-modal, #ef-competitions-modal').fadeOut(150).removeClass('active').attr('aria-hidden', 'true');
				$('body').css('overflow', '');
			}

			$(document).on('click', '.ef-modal-dismiss, .ef-modal-backdrop', function (e) {
				if (e.target === this || $(this).hasClass('ef-modal-dismiss')) {
					closeModal();
				}
			});

			$(document).on('keydown', function (e) {
				if (e.key === 'Escape' || e.keyCode === 27) {
					closeModal();
				}
			});
		},

		/**
		 * 13. Participant Profile Update Handler
		 */
		bindProfileForm: function () {
			$('#ef-participant-profile-form').on('submit', function (e) {
				e.preventDefault();
				var $form = $(this);
				var $btn = $form.find('#ef-save-profile-btn');
				var $alert = $('#ef-profile-alert').hide().empty().removeClass('ef-alert-danger ef-alert-success');

				$btn.prop('disabled', true).text('Saving Changes...');

				var postData = $form.serializeArray();
				postData.push({ name: 'action', value: 'eventflow_update_profile' });
				postData.push({ name: 'nonce', value: window.eventflow_vars.nonce });

				$.post(window.eventflow_vars.ajax_url, postData, function (res) {
					$btn.prop('disabled', false).html('💾 Save Profile Changes');
					if (res.success) {
						$alert.addClass('ef-alert-success').html(res.data.message || 'Profile updated successfully!').slideDown(200);
					} else {
						$alert.addClass('ef-alert-danger').html(res.data.message || 'Failed to update profile.').slideDown(200);
					}
				}).fail(function () {
					$btn.prop('disabled', false).html('💾 Save Profile Changes');
					$alert.addClass('ef-alert-danger').html('Network error occurred. Please try again.').slideDown(200);
				});
			});
		}
	};

	$(document).ready(function () {
		EventFlowFrontend.init();
	});

})(jQuery);
