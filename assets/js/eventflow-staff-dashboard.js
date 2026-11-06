/**
 * EventFlow Frontend Staff & Admin Management Portal JavaScript
 *
 * Implements:
 * 1. Native Canvas Charts for all 9 metrics with velocity trend filtering
 * 2. Visual Dynamic Form Builder (18+ field types, presets, preview, inspector)
 * 3. Registrations full inspection & status update modals
 * 4. Activities creation, editing, deletion & form builder trigger
 * 5. Announcements & Staff provisioning
 */

(function ($) {
	'use strict';

	var StaffDashboard = {
		formFields: [],
		activeFieldIndex: -1,
		currentActivityId: 0,

		init: function () {
			this.bindModalControls();
			this.bindMobileNavigation();
			this.renderCharts();
			this.bindAnalyticsRefresh();
			this.bindChartResize();
			this.bindRegistrationModals();
			this.bindActivityModals();
			this.bindFormBuilder();
			this.bindAnnouncementForm();
			this.bindStaffManagement();
			this.bindSettingsForm();
			this.bindExportFilters();
			this.bindTeamManagement();
			this.bindDataManagement();
			this.bindLockedAccounts();
		},

		/**
		 * Global Modal Controls (Dismissal, Backdrop, Escape Key)
		 */
		bindModalControls: function () {
			// Dismiss on close button or cancel button
			$(document).on('click', '.ef-modal-close, [data-dismiss="modal"]', function (e) {
				e.preventDefault();
				var $m = $(this).closest('.ef-modal');
				$m.removeClass('active').attr('aria-hidden', 'true');
				if (!$('.ef-modal.active').length) {
					$('body').css('overflow', '');
				}
			});

			// Dismiss on backdrop click
			$(document).on('click', '.ef-modal', function (e) {
				if ($(e.target).hasClass('ef-modal') || $(e.target).hasClass('ef-modal-backdrop')) {
					$(this).removeClass('active').attr('aria-hidden', 'true');
					if (!$('.ef-modal.active').length) {
						$('body').css('overflow', '');
					}
				}
			});

			// Dismiss on Escape key
			$(document).on('keydown', function (e) {
				if (e.key === 'Escape' || e.keyCode === 27) {
					var $active = $('.ef-modal.active');
					if ($active.length) {
						$active.last().removeClass('active').attr('aria-hidden', 'true');
						if (!$('.ef-modal.active').length) {
							$('body').css('overflow', '');
						}
					}
				}
			});
		},

		/**
		 * Mobile Navigation Toggle
		 */
		bindMobileNavigation: function () {
			$(document).on('click', '#ef-mobile-toggle-btn', function (e) {
				e.preventDefault();
				$('.ef-staff-sidebar').toggleClass('ef-mobile-open');
			});
		},

		/**
		 * Debounced Chart Resize Handler
		 */
		bindChartResize: function () {
			var self = this;
			var resizeTimer;
			$(window).on('resize', function () {
				clearTimeout(resizeTimer);
				resizeTimer = setTimeout(function () {
					self.renderCharts();
				}, 200);
			});
		},

		/**
		 * 1. Render Real Canvas Charts (All 9 Charts)
		 */
		renderCharts: function (freshData) {
			if (typeof window.EventFlowChart === 'undefined') return;

			function getChartData(containerId, dataKey) {
				if (freshData && freshData[dataKey]) {
					return freshData[dataKey];
				}
				var $el = $(containerId);
				return $el.length ? $el.data('chart') : null;
			}

			// 1. Daily Trend
			var daily = getChartData('#ef-daily-chart', 'daily_registrations');
			if (daily && daily.labels && daily.data) {
				var canvasDaily = document.getElementById('ef-daily-canvas');
				if (canvasDaily) window.EventFlowChart.drawLineChart(canvasDaily, daily.labels, daily.data, '#0D9488');
			}

			// 2. Weekly Statistics (Vertical Bar)
			var weekly = getChartData('#ef-weekly-chart', 'weekly_registrations');
			if (weekly && weekly.labels && weekly.data) {
				var canvasWeekly = document.getElementById('ef-weekly-canvas');
				if (canvasWeekly) window.EventFlowChart.drawVerticalBarChart(canvasWeekly, weekly.labels, weekly.data, '#3B82F6');
			}

			// 3. Monthly Volume (Vertical Bar)
			var monthly = getChartData('#ef-monthly-chart', 'monthly_registrations');
			if (monthly && monthly.labels && monthly.data) {
				var canvasMonthly = document.getElementById('ef-monthly-canvas');
				if (canvasMonthly) window.EventFlowChart.drawVerticalBarChart(canvasMonthly, monthly.labels, monthly.data, '#6366F1');
			}

			// 4. Competitions Distribution (Horizontal Bar)
			var act = getChartData('#ef-activity-chart', 'activity_distribution');
			if (act && act.labels && act.data) {
				var canvasAct = document.getElementById('ef-activity-canvas');
				if (canvasAct) window.EventFlowChart.drawBarChart(canvasAct, act.labels, act.data, '#06B6D4');
			}

			// 5. Payment Verification Distribution (Doughnut)
			var payVer = getChartData('#ef-payment-ver-chart', 'payment_verification');
			if (payVer && payVer.labels && payVer.data) {
				var canvasPayVer = document.getElementById('ef-payment-ver-canvas');
				if (canvasPayVer) window.EventFlowChart.drawDoughnutChart(canvasPayVer, payVer.labels, payVer.data);
			}

			// 6. Application Status Breakdown (Doughnut)
			var status = getChartData('#ef-status-chart', 'application_status');
			if (status && status.labels && status.data) {
				var canvasStatus = document.getElementById('ef-status-canvas');
				if (canvasStatus) window.EventFlowChart.drawDoughnutChart(canvasStatus, status.labels, status.data);
			}

			// 7. Solo vs Team Participation (Doughnut)
			var soloTeam = getChartData('#ef-solo-team-chart', 'solo_vs_team');
			if (soloTeam && soloTeam.labels && soloTeam.data) {
				var canvasSoloTeam = document.getElementById('ef-solo-team-canvas');
				if (canvasSoloTeam) window.EventFlowChart.drawDoughnutChart(canvasSoloTeam, soloTeam.labels, soloTeam.data);
			}

			// 8. Revenue Collection Overview (Horizontal Bar)
			var revenue = getChartData('#ef-revenue-chart', 'revenue_overview');
			if (revenue && revenue.labels && revenue.data) {
				var canvasRevenue = document.getElementById('ef-revenue-canvas');
				if (canvasRevenue) window.EventFlowChart.drawBarChart(canvasRevenue, revenue.labels, revenue.data, '#10B981');
			}

			// 9. Payment Method Distribution (Doughnut)
			var methods = getChartData('#ef-methods-chart', 'payment_methods');
			if (methods && methods.labels && methods.data) {
				var canvasMethods = document.getElementById('ef-methods-canvas');
				if (canvasMethods) window.EventFlowChart.drawDoughnutChart(canvasMethods, methods.labels, methods.data);
			}
		},

		/**
		 * 2. Analytics Velocity & Refresh Controls
		 */
		bindAnalyticsRefresh: function () {
			var self = this;

			function fetchAnalytics() {
				var days = $('#ef-analytics-days').val() || 14;
				var $btn = $('#ef-refresh-analytics-btn');
				$btn.prop('disabled', true).text('Refreshing...');

				$.getJSON(window.eventflow_staff_vars.ajax_url, {
					action: 'eventflow_get_analytics_data',
					days: days,
					nonce: window.eventflow_staff_vars.staff_nonce
				}, function (res) {
					$btn.prop('disabled', false).text('↻ Refresh Data');
					if (res.success && res.data) {
						self.renderCharts(res.data);
						self.showToast('Analytics charts updated successfully.', 'success');
					} else {
						self.showToast('Failed to refresh analytics data.', 'error');
					}
				}).fail(function () {
					$btn.prop('disabled', false).text('↻ Refresh Data');
					self.showToast('Error connecting to server.', 'error');
				});
			}

			$('#ef-refresh-analytics-btn').on('click', function () {
				fetchAnalytics();
			});

			$('#ef-analytics-days').on('change', function () {
				fetchAnalytics();
			});
		},

		/**
		 * 3. Registration Full Details & Status Update Modals
		 */
		bindRegistrationModals: function () {
			var self = this;

			// Click anywhere on clickable table row to view full details
			$(document).on('click', 'tr.ef-clickable-row', function (e) {
				if ($(e.target).closest('a:not(.ef-view-reg-btn), button:not(.ef-view-reg-btn), input, select, textarea').length > 0) {
					return;
				}
				var regId = $(this).data('reg-id');
				if (regId) {
					$('.ef-view-reg-btn[data-reg-id="' + regId + '"]').first().trigger('click');
				}
			});

			// View Full Details Modal
			$(document).on('click', '.ef-view-reg-btn', function (e) {
				e.preventDefault();
				var regId = $(this).data('reg-id');
				if (!regId) return;

				var $modal = $('#ef-reg-details-modal');
				var $body = $('#ef-reg-modal-content').html('<div class="ef-spinner"></div>');
				$modal.addClass('active').attr('aria-hidden', 'false');

				$.getJSON(window.eventflow_staff_vars.ajax_url, {
					action: 'eventflow_get_registration_details',
					reg_id: regId,
					nonce: window.eventflow_staff_vars.staff_nonce
				}, function (res) {
					if (res.success) {
						var d = res.data;
						var isTeam = (d.participation_type === 'team');
						var candidateName = (d.team && d.team.leader_name) ? d.team.leader_name : (d.leader_name || 'Candidate');
						
						if (isTeam) {
							$('#ef-reg-modal-title').text('Team Details: ' + (d.team_name || 'Team') + ' (' + d.reg_id + ')');
						} else {
							$('#ef-reg-modal-title').text('Individual Candidate: ' + candidateName + ' (' + d.reg_id + ')');
						}

						var html = '<div class="ef-reg-modal-view">';
						html += '<div class="ef-grid ef-grid-3" style="background:#F8FAFC; padding:16px; border-radius:8px; margin-bottom:20px;">';
						html += '<div><span class="ef-text-muted ef-text-xs">COMPETITION:</span><h4 style="margin:4px 0 0;">' + (d.activity ? d.activity.title : '') + '</h4></div>';
						html += '<div><span class="ef-text-muted ef-text-xs">' + (isTeam ? 'TEAM NAME:' : 'PARTICIPANT:') + '</span><h4 style="margin:4px 0 0;">' + (isTeam ? (d.team_name || candidateName) : candidateName) + '</h4></div>';
						html += '<div><span class="ef-text-muted ef-text-xs">TOTAL FEE:</span><h4 style="margin:4px 0 0;" class="ef-fee">' + (d.event ? d.event.currency : 'PKR') + ' ' + parseFloat(d.total_fee).toFixed(2) + '</h4></div>';
						html += '</div>';

						// Resolve members list with fallback
						var members = (d.members && d.members.length) ? d.members : [];
						if (!members.length) {
							members = [{
								full_name: candidateName,
								father_name: '',
								university: '',
								department: '',
								semester: '',
								academic_year: '',
								email: (d.team ? d.team.leader_email : (d.leader_email || '')),
								contact_number: (d.team ? d.team.leader_phone : (d.leader_phone || '')),
								is_leader: 1
							}];
						}

						// Members Table
						html += '<h4>' + (isTeam ? ('Team Members & Roster (' + members.length + ')') : 'Candidate Academic Profile') + '</h4>';
						html += '<div class="ef-table-responsive"><table class="ef-table ef-table-bordered" style="margin-bottom:20px;">';
						html += '<thead><tr><th>#</th><th>Full Name</th><th>Father Name</th><th>University</th><th>Dept & Sem</th><th>Email</th><th>Phone / WhatsApp</th><th>Role</th></tr></thead><tbody>';
						for (var i = 0; i < members.length; i++) {
							var m = members[i];
							var cleanPhone = m.contact_number ? m.contact_number.replace(/[^0-9]/g, '') : '';
							var phoneCell = m.contact_number || '—';
							if (cleanPhone) {
								phoneCell += ' <a href="https://wa.me/' + cleanPhone + '" target="_blank" class="ef-badge ef-badge-success" style="margin-left:4px; font-size:10px;">WhatsApp</a>';
							}
							var roleBadge = isTeam 
								? (m.is_leader == 1 ? '<span class="ef-tag ef-tag-primary">Team Leader</span>' : '<span class="ef-tag">Member</span>')
								: '<span class="ef-tag ef-tag-info">Solo Candidate</span>';

							html += '<tr><td>' + (i + 1) + '</td><td><strong>' + (m.full_name || '—') + '</strong></td><td>' + (m.father_name || '—') + '</td><td>' + (m.university || '—') + '</td><td>' + (m.department || '—') + (m.semester ? (' (Sem ' + m.semester + ')') : '') + '</td><td><a href="mailto:' + (m.email || '') + '">' + (m.email || '—') + '</a></td><td>' + phoneCell + '</td><td>' + roleBadge + '</td></tr>';
						}
						html += '</tbody></table></div>';

						// Custom Form Fields Data (if any)
						if (d.custom_fields && typeof d.custom_fields === 'object' && Object.keys(d.custom_fields).length > 0) {
							html += '<h4>Custom Registration Answers</h4>';
							html += '<div class="ef-grid ef-grid-2" style="background:#F8FAFC; padding:16px; border-radius:8px; margin-bottom:20px;">';
							for (var k in d.custom_fields) {
								if (d.custom_fields.hasOwnProperty(k)) {
									var val = d.custom_fields[k];
									var displayVal = Array.isArray(val) ? val.join(', ') : val;
									html += '<div><span class="ef-text-muted ef-text-xs" style="text-transform:uppercase;">' + k.replace(/_/g, ' ') + ':</span><div style="font-weight:600; margin-top:2px;">' + (displayVal || '-') + '</div></div>';
								}
							}
							html += '</div>';
						}

						// Payment Snapshot
						if (d.payment) {
							html += '<h4>Payment Verification Status</h4>';
							html += '<div class="ef-grid ef-grid-2" style="background:#F1F5F9; padding:16px; border-radius:8px;">';
							html += '<div><strong>Method:</strong> ' + (d.payment.payment_method || '—') + '<br><strong>TID:</strong> ' + (d.payment.transaction_id || '—') + '<br><strong>Amount:</strong> ' + (d.payment.amount || d.total_fee) + '</div>';
							html += '<div><strong>Status:</strong> ' + (d.payment.status || 'pending').toUpperCase();
							if (d.payment.preview_url) {
								html += '<br><button type="button" class="ef-btn ef-btn-primary ef-btn-xs ef-preview-proof-btn" data-payment-id="' + d.payment.id + '" data-reg-id="' + d.reg_id + '" data-tid="' + d.payment.transaction_id + '" data-amount="' + d.payment.amount + '" data-activity="' + (d.activity ? d.activity.title : '') + '" data-team="' + (d.team_name || candidateName) + '" data-method="' + (d.payment.payment_method || '') + '" data-date="' + (d.payment.payment_date || '') + '" data-status="' + (d.payment.status || '') + '" data-url="' + d.payment.preview_url + '" data-filetype="' + d.payment.file_type + '" style="margin-top:6px;">Preview Payment Proof</button>';
							}
							html += '</div></div>';
						}

						html += '</div>';
						$body.html(html);
					} else {
						$body.html('<p class="ef-notice ef-notice-warning">' + (res.data && res.data.message ? res.data.message : 'Failed to load registration details.') + '</p>');
					}
				});
			});

			// Status Update Modal Trigger
			$(document).on('click', '.ef-edit-status-btn', function () {
				var regId = $(this).data('reg-id');
				var curStatus = $(this).data('current-status') || 'submitted';

				$('#ef-edit-status-reg-id').val(regId);
				$('#ef-edit-status-select').val(curStatus);
				$('#ef-rejection-reason-group').toggle(curStatus === 'rejected');
				$('#ef-status-edit-modal').addClass('active').attr('aria-hidden', 'false');
			});

			$('#ef-edit-status-select').on('change', function () {
				$('#ef-rejection-reason-group').toggle($(this).val() === 'rejected');
			});

			// Submit Status Update
			$('#ef-update-status-form').on('submit', function (e) {
				e.preventDefault();
				var postData = $(this).serializeArray();
				postData.push({ name: 'action', value: 'eventflow_update_registration_status' });
				postData.push({ name: 'nonce', value: window.eventflow_staff_vars.staff_nonce });

				var $submitBtn = $(this).find('button[type="submit"]').prop('disabled', true);

				$.post(window.eventflow_staff_vars.ajax_url, postData, function (res) {
					$submitBtn.prop('disabled', false);
					if (res.success) {
						self.showToast(res.data.message, 'success');
						$('#ef-status-edit-modal').removeClass('active');
						setTimeout(function () { window.location.reload(); }, 900);
					} else {
						alert(res.data.message || 'Update failed.');
					}
				}).fail(function () {
					$submitBtn.prop('disabled', false);
					alert('Error connecting to server.');
				});
			});
		},

		/**
		 * 4. Activity Create, Edit, Delete Handlers
		 */
		bindActivityModals: function () {
			var self = this;

			$('#ef-create-activity-btn').on('click', function () {
				$('#ef-activity-form')[0].reset();
				$('#ef-act-id').val('0');
				$('#ef-act-fee-calc').val('per_team');
				$('#ef-act-deadline').val('');
				$('#ef-act-image-url').val('');
				$('#ef-act-modal-title').text('Create Activity / Track');
				$('#ef-activity-edit-modal').addClass('active').attr('aria-hidden', 'false');
			});

			$(document).on('click', '.ef-edit-activity-btn', function () {
				var actId = $(this).data('id');
				if (!actId) return;

				$.getJSON(window.eventflow_staff_vars.ajax_url, {
					action: 'eventflow_get_activity',
					id: actId
				}, function (res) {
					if (res.success) {
						var a = res.data;
						$('#ef-act-id').val(a.id);
						$('#ef-act-title').val(a.title);
						$('#ef-act-desc').val(a.description);
						$('#ef-act-type').val(a.participation_type);
						$('#ef-act-min-team').val(a.min_team_size);
						$('#ef-act-max-team').val(a.max_team_size);
						$('#ef-act-fee').val(a.reg_fee);
						$('#ef-act-fee-calc').val(a.fee_calculation || 'per_team');
						$('#ef-act-max-participants').val(a.max_participants);
						$('#ef-act-status').val(a.status);
						$('#ef-act-eligibility').val(a.eligibility);
						$('#ef-act-instructions').val(a.instructions);
						$('#ef-act-deadline').val(a.reg_deadline ? a.reg_deadline.replace(' ', 'T').substring(0, 16) : '');
						$('#ef-act-image-url').val(a.image_url || '');

						$('#ef-act-modal-title').text('Edit Activity: ' + a.title);
						$('#ef-activity-edit-modal').addClass('active').attr('aria-hidden', 'false');
					}
				});
			});

			$('#ef-activity-form').on('submit', function (e) {
				e.preventDefault();
				var postData = $(this).serializeArray();
				postData.push({ name: 'action', value: 'eventflow_save_activity' });
				postData.push({ name: 'nonce', value: window.eventflow_staff_vars.staff_nonce });

				var $submitBtn = $(this).find('button[type="submit"]').prop('disabled', true);

				$.post(window.eventflow_staff_vars.ajax_url, postData, function (res) {
					$submitBtn.prop('disabled', false);
					if (res.success) {
						self.showToast(res.data.message, 'success');
						$('#ef-activity-edit-modal').removeClass('active');
						setTimeout(function () { window.location.reload(); }, 900);
					} else {
						alert(res.data.message || 'Failed to save activity.');
					}
				}).fail(function () {
					$submitBtn.prop('disabled', false);
					alert('Error connecting to server.');
				});
			});

			$(document).on('click', '.ef-delete-activity-btn', function () {
				if (!confirm('Are you sure you want to delete this competition track?')) return;
				var actId = $(this).data('id');

				$.post(window.eventflow_staff_vars.ajax_url, {
					action: 'eventflow_delete_activity',
					activity_id: actId,
					nonce: window.eventflow_staff_vars.staff_nonce
				}, function (res) {
					if (res.success) {
						self.showToast(res.data.message, 'success');
						setTimeout(function () { window.location.reload(); }, 900);
					} else {
						alert(res.data.message);
					}
				});
			});
		},

		/**
		 * 5. DYNAMIC FORM BUILDER (18+ Field Types & Visual Canvas)
		 */
		bindFormBuilder: function () {
			var self = this;

			var defaultFieldLabels = {
				text: 'Single Line Text',
				textarea: 'Multi-line Text',
				email: 'Email Address',
				tel: 'Phone Number',
				number: 'Number / Quantity',
				cnic: 'CNIC / National ID',
				select: 'Dropdown Select',
				multi_select: 'Multi-select List',
				radio: 'Radio Buttons',
				checkbox: 'Checkbox Group',
				consent: 'Consent / Agreement',
				date: 'Date Picker',
				time: 'Time Picker',
				file: 'File Upload',
				url: 'Website / URL',
				heading: 'Section Heading',
				paragraph: 'Information Text',
				divider: 'Divider Line'
			};

			var templatePresets = {
				coding: [
					{ id: 'f_url', type: 'url', label: 'GitHub Profile URL', key: 'github_url', placeholder: 'https://github.com/username', help: 'Link to your active GitHub account', required: true, options: [], extensions: '', max_size: 5, default_val: '', css_class: '' },
					{ id: 'f_lang', type: 'select', label: 'Primary Programming Language', key: 'programming_language', placeholder: '', help: 'Selected language environment for contest compiler', required: true, options: ['C++', 'Java', 'Python 3', 'Rust', 'C#'], extensions: '', max_size: 5, default_val: 'C++', css_class: '' },
					{ id: 'f_shirt', type: 'select', label: 'Official Contest T-Shirt Size', key: 'tshirt_size', placeholder: '', help: '', required: true, options: ['Small (S)', 'Medium (M)', 'Large (L)', 'Extra Large (XL)', 'Double XL (XXL)'], extensions: '', max_size: 5, default_val: 'Medium (M)', css_class: '' },
					{ id: 'f_laptop', type: 'radio', label: 'Will you bring your own laptop?', key: 'bring_laptop', placeholder: '', help: '', required: true, options: ['Yes, I will bring my personal laptop', 'No, I need a university workstation'], extensions: '', max_size: 5, default_val: 'Yes, I will bring my personal laptop', css_class: '' },
					{ id: 'f_emerg', type: 'tel', label: 'Emergency Contact Phone', key: 'emergency_phone', placeholder: '03001234567', help: 'Family or guardian contact number', required: true, options: [], extensions: '', max_size: 5, default_val: '', css_class: '' },
					{ id: 'f_agree', type: 'consent', label: 'I pledge to abide by the ACM Code of Conduct and contest rules.', key: 'code_of_conduct_consent', placeholder: '', help: '', required: true, options: [], extensions: '', max_size: 5, default_val: '', css_class: '' }
				],
				robotics: [
					{ id: 'f_rname', type: 'text', label: 'Robot Machine Name', key: 'robot_name', placeholder: 'e.g. ThunderStrike', help: 'Official tournament name of your machine', required: true, options: [], extensions: '', max_size: 5, default_val: '', css_class: '' },
					{ id: 'f_rdims', type: 'text', label: 'Dimensions (Length x Width x Height in cm)', key: 'robot_dimensions', placeholder: 'e.g. 40 x 30 x 25 cm', help: 'Must adhere to track size constraints', required: true, options: [], extensions: '', max_size: 5, default_val: '', css_class: '' },
					{ id: 'f_rwt', type: 'number', label: 'Total Weight (kg)', key: 'robot_weight', placeholder: 'e.g. 4.5', help: 'Max weight allowable is 5.0 kg', required: true, options: [], extensions: '', max_size: 5, default_val: '', css_class: '' },
					{ id: 'f_rbat', type: 'text', label: 'Battery Chemistry & Operating Voltage', key: 'battery_specs', placeholder: 'e.g. 3S LiPo 11.1V 2200mAh', help: '', required: true, options: [], extensions: '', max_size: 5, default_val: '', css_class: '' },
					{ id: 'f_rfreq', type: 'select', label: 'RF Controller Frequency Band', key: 'rf_frequency', placeholder: '', help: '', required: true, options: ['2.4 GHz', '5.8 GHz', '433 MHz', 'Bluetooth', 'Wired Tether'], extensions: '', max_size: 5, default_val: '2.4 GHz', css_class: '' },
					{ id: 'f_rsaf', type: 'consent', label: 'I certify this machine complies with all mechanical and electrical safety rules.', key: 'robotics_safety_consent', placeholder: '', help: '', required: true, options: [], extensions: '', max_size: 5, default_val: '', css_class: '' }
				],
				quiz: [
					{ id: 'f_qcat', type: 'select', label: 'Specialization Subject Preference', key: 'subject_category', placeholder: '', help: '', required: true, options: ['General Computer Science', 'Algorithms & Mathematics', 'Cybersecurity & Networks', 'AI & Machine Learning'], extensions: '', max_size: 5, default_val: 'General Computer Science', css_class: '' },
					{ id: 'f_qexp', type: 'radio', label: 'Have you participated in university Olympiads previously?', key: 'prior_olympiad_exp', placeholder: '', help: '', required: true, options: ['Yes', 'No'], extensions: '', max_size: 5, default_val: 'No', css_class: '' },
					{ id: 'f_qcnic', type: 'cnic', label: 'National ID / CNIC / B-Form Number', key: 'cnic_number', placeholder: '42101-1234567-1', help: 'Required for entry verification', required: true, options: [], extensions: '', max_size: 5, default_val: '', css_class: '' },
					{ id: 'f_qhon', type: 'consent', label: 'I certify that all academic information provided is accurate and verifiable.', key: 'academic_honor_code', placeholder: '', help: '', required: true, options: [], extensions: '', max_size: 5, default_val: '', css_class: '' }
				],
				gaming: [
					{ id: 'f_gign', type: 'text', label: 'In-Game Gamertag / Summoner Name', key: 'gamertag', placeholder: 'e.g. ShadowSniper', help: 'Exact player nickname used in matches', required: true, options: [], extensions: '', max_size: 5, default_val: '', css_class: '' },
					{ id: 'f_gdisc', type: 'text', label: 'Discord Tag (e.g. Username#0000)', key: 'discord_tag', placeholder: 'username#1234', help: 'For tournament voice match channels', required: true, options: [], extensions: '', max_size: 5, default_val: '', css_class: '' },
					{ id: 'f_gplat', type: 'radio', label: 'Tournament Platform', key: 'gaming_platform', placeholder: '', help: '', required: true, options: ['PC / Steam', 'PlayStation 5', 'Mobile Device'], extensions: '', max_size: 5, default_val: 'PC / Steam', css_class: '' },
					{ id: 'f_gacc', type: 'text', label: 'Steam Profile Link or Riot ID', key: 'game_account_link', placeholder: 'https://steamcommunity.com/id/...', help: '', required: true, options: [], extensions: '', max_size: 5, default_val: '', css_class: '' },
					{ id: 'f_gche', type: 'consent', label: 'I accept anti-cheat surveillance and will not use unauthorized third-party tools.', key: 'anti_cheat_consent', placeholder: '', help: '', required: true, options: [], extensions: '', max_size: 5, default_val: '', css_class: '' }
				]
			};

			function renderFieldsList() {
				var $list = $('#ef-fb-fields-list');
				var $empty = $('#ef-fb-empty-state');
				var count = self.formFields.length;
				$('#ef-fb-fields-count').text(count + ' Field' + (count === 1 ? '' : 's'));

				if (count === 0) {
					$list.empty();
					$empty.show();
					$('#ef-fb-inspector').hide();
					self.activeFieldIndex = -1;
					return;
				}

				$empty.hide();
				var html = '';

				for (var i = 0; i < count; i++) {
					var f = self.formFields[i];
					var isSelected = (i === self.activeFieldIndex);
					var typeBadge = (f.type || 'text').replace('_', ' ').toUpperCase();
					var reqBadge = f.required ? '<span class="ef-badge ef-badge-alert" style="font-size:10px; padding:2px 6px;">Required</span>' : '<span class="ef-badge ef-badge-neutral" style="font-size:10px; padding:2px 6px;">Optional</span>';

					html += '<div class="ef-fb-field-card ' + (isSelected ? 'selected' : '') + '" data-index="' + i + '">';
					html += '<div class="ef-fb-field-card-header">';
					html += '<div class="ef-fb-card-title">';
					html += '<span style="cursor:grab; color:#94A3B8;">⋮⋮</span> ';
					html += '<strong>' + (f.label || 'Untitled Field') + '</strong>';
					html += '</div>';

					html += '<div class="ef-fb-card-actions">';
					if (i > 0) {
						html += '<button type="button" class="ef-fb-btn-icon ef-fb-move-up" data-index="' + i + '" title="Move Up">▲</button>';
					}
					if (i < count - 1) {
						html += '<button type="button" class="ef-fb-btn-icon ef-fb-move-down" data-index="' + i + '" title="Move Down">▼</button>';
					}
					html += '<button type="button" class="ef-fb-btn-icon ef-fb-duplicate" data-index="' + i + '" title="Duplicate">📋</button>';
					html += '<button type="button" class="ef-fb-btn-icon ef-btn-delete ef-fb-delete" data-index="' + i + '" title="Delete">🗑️</button>';
					html += '</div>';
					html += '</div>';

					html += '<div class="ef-fb-card-meta">';
					html += '<span class="ef-tag" style="font-size:11px;">' + typeBadge + '</span>';
					html += '<code>' + (f.key || 'key') + '</code>';
					if (['heading', 'paragraph', 'divider'].indexOf(f.type) === -1) {
						html += reqBadge;
					}
					html += '</div>';

					html += '</div>';
				}

				$list.html(html);

				if (self.activeFieldIndex >= 0 && self.activeFieldIndex < count) {
					loadFieldIntoInspector(self.activeFieldIndex);
				} else {
					$('#ef-fb-inspector').hide();
				}
			}

			function loadFieldIntoInspector(index) {
				self.activeFieldIndex = index;
				var f = self.formFields[index];
				if (!f) return;

				var $insp = $('#ef-fb-inspector').show();
				$('#ef-prop-field-index').val(index);
				$('#ef-prop-type-badge').text((f.type || 'text').toUpperCase());
				$('#ef-prop-label').val(f.label || '');
				$('#ef-prop-key').val(f.key || '');
				$('#ef-prop-placeholder').val(f.placeholder || '');
				$('#ef-prop-help').val(f.help || '');
				$('#ef-prop-required').prop('checked', !!f.required);
				$('#ef-prop-default').val(f.default_val || '');
				$('#ef-prop-class').val(f.css_class || '');

				var isChoice = ['select', 'multi_select', 'radio', 'checkbox'].indexOf(f.type) !== -1;
				$('#ef-prop-options-group').toggle(isChoice);
				if (isChoice) {
					var opts = Array.isArray(f.options) ? f.options.join('\n') : (f.options || '');
					$('#ef-prop-options').val(opts);
				}

				var isFile = (f.type === 'file');
				$('#ef-prop-file-group').toggle(isFile);
				if (isFile) {
					$('#ef-prop-extensions').val(f.extensions || 'pdf, docx, zip');
					$('#ef-prop-maxsize').val(f.max_size || 5);
				}

				var isLayout = ['heading', 'paragraph', 'divider'].indexOf(f.type) !== -1;
				$('#ef-prop-required-group').toggle(!isLayout);
				$('#ef-prop-placeholder-group').toggle(!isLayout);

				$('.ef-fb-field-card').removeClass('selected');
				$('.ef-fb-field-card[data-index="' + index + '"]').addClass('selected');
			}

			function applyInspectorChanges() {
				var index = parseInt($('#ef-prop-field-index').val(), 10);
				if (index < 0 || index >= self.formFields.length) return;

				var f = self.formFields[index];
				f.label = $('#ef-prop-label').val().trim() || 'Untitled Field';
				var rawKey = $('#ef-prop-key').val().trim().toLowerCase().replace(/[^a-z0-9_]/g, '_');
				f.key = rawKey || ('field_' + index);
				f.placeholder = $('#ef-prop-placeholder').val().trim();
				f.help = $('#ef-prop-help').val().trim();
				f.required = $('#ef-prop-required').is(':checked');
				f.default_val = $('#ef-prop-default').val().trim();
				f.css_class = $('#ef-prop-class').val().trim();

				if (['select', 'multi_select', 'radio', 'checkbox'].indexOf(f.type) !== -1) {
					var lines = $('#ef-prop-options').val().split('\n');
					var cleanOpts = [];
					for (var i = 0; i < lines.length; i++) {
						var line = lines[i].trim();
						if (line) cleanOpts.push(line);
					}
					f.options = cleanOpts.length ? cleanOpts : ['Option 1', 'Option 2'];
				}

				if (f.type === 'file') {
					f.extensions = $('#ef-prop-extensions').val().trim() || 'pdf, docx, zip';
					f.max_size = parseInt($('#ef-prop-maxsize').val(), 10) || 5;
				}

				renderFieldsList();
			}

			// Open Form Builder Modal
			$(document).on('click', '.ef-open-form-builder-btn', function () {
				var actId = $(this).data('id');
				var actTitle = $(this).data('title');
				self.currentActivityId = actId;

				$('#ef-fb-activity-id').val(actId);
				$('#ef-fb-act-title-display').text(actTitle);
				$('#ef-fb-template-preset').val('');
				$('#ef-fb-inspector').hide();
				self.activeFieldIndex = -1;

				var $modal = $('#ef-form-builder-modal');
				$modal.addClass('active').attr('aria-hidden', 'false');

				// Fetch existing schema
				$.getJSON(window.eventflow_staff_vars.ajax_url, {
					action: 'eventflow_get_form_schema',
					activity_id: actId
				}, function (res) {
					if (res.success && res.data && res.data.schema) {
						self.formFields = Array.isArray(res.data.schema) ? res.data.schema : [];
					} else {
						self.formFields = [];
					}
					renderFieldsList();
				}).fail(function () {
					self.formFields = [];
					renderFieldsList();
				});
			});

			// Palette Button Click -> Add Field
			$(document).on('click', '.ef-fb-palette-btn', function () {
				var type = $(this).data('type') || 'text';
				var count = self.formFields.length + 1;
				var baseLabel = defaultFieldLabels[type] || 'Field';

				var newField = {
					id: 'f_' + Math.random().toString(36).substr(2, 8),
					type: type,
					label: baseLabel + ' ' + count,
					key: type + '_' + count,
					placeholder: '',
					help: '',
					required: (['email', 'tel', 'cnic', 'consent'].indexOf(type) !== -1),
					options: (['select', 'multi_select', 'radio', 'checkbox'].indexOf(type) !== -1 ? ['Option 1', 'Option 2', 'Option 3'] : []),
					extensions: (type === 'file' ? 'pdf, docx, zip' : ''),
					max_size: 5,
					default_val: '',
					css_class: ''
				};

				self.formFields.push(newField);
				self.activeFieldIndex = self.formFields.length - 1;
				renderFieldsList();
			});

			// Select Field Card
			$(document).on('click', '.ef-fb-field-card', function (e) {
				if ($(e.target).closest('.ef-fb-card-actions').length) return;
				var index = parseInt($(this).data('index'), 10);
				loadFieldIntoInspector(index);
			});

			// Field Card Actions
			$(document).on('click', '.ef-fb-move-up', function (e) {
				e.stopPropagation();
				var idx = parseInt($(this).data('index'), 10);
				if (idx > 0) {
					var temp = self.formFields[idx];
					self.formFields[idx] = self.formFields[idx - 1];
					self.formFields[idx - 1] = temp;
					self.activeFieldIndex = idx - 1;
					renderFieldsList();
				}
			});

			$(document).on('click', '.ef-fb-move-down', function (e) {
				e.stopPropagation();
				var idx = parseInt($(this).data('index'), 10);
				if (idx < self.formFields.length - 1) {
					var temp = self.formFields[idx];
					self.formFields[idx] = self.formFields[idx + 1];
					self.formFields[idx + 1] = temp;
					self.activeFieldIndex = idx + 1;
					renderFieldsList();
				}
			});

			$(document).on('click', '.ef-fb-duplicate', function (e) {
				e.stopPropagation();
				var idx = parseInt($(this).data('index'), 10);
				var original = self.formFields[idx];
				var clone = JSON.parse(JSON.stringify(original));
				clone.id = 'f_' + Math.random().toString(36).substr(2, 8);
				clone.key = clone.key + '_copy';
				clone.label = clone.label + ' (Copy)';
				self.formFields.splice(idx + 1, 0, clone);
				self.activeFieldIndex = idx + 1;
				renderFieldsList();
			});

			$(document).on('click', '.ef-fb-delete', function (e) {
				e.stopPropagation();
				var idx = parseInt($(this).data('index'), 10);
				if (!confirm('Remove this field from the competition form?')) return;
				self.formFields.splice(idx, 1);
				if (self.activeFieldIndex === idx) {
					self.activeFieldIndex = -1;
				} else if (self.activeFieldIndex > idx) {
					self.activeFieldIndex--;
				}
				renderFieldsList();
			});

			// Apply Properties
			$('#ef-apply-properties-btn').on('click', function () {
				applyInspectorChanges();
				self.showToast('Field properties applied.', 'success');
			});

			// Template Preset Load
			$('#ef-fb-template-preset').on('change', function () {
				var presetKey = $(this).val();
				if (!presetKey || !templatePresets[presetKey]) return;

				if (self.formFields.length > 0) {
					if (!confirm('Loading a preset template will replace your current unsaved fields. Continue?')) {
						$(this).val('');
						return;
					}
				}

				self.formFields = JSON.parse(JSON.stringify(templatePresets[presetKey]));
				self.activeFieldIndex = 0;
				renderFieldsList();
				self.showToast('Template preset loaded: ' + presetKey.toUpperCase(), 'success');
			});

			// Clear All Fields
			$('#ef-fb-clear-btn').on('click', function () {
				if (!confirm('Clear all custom fields for this form?')) return;
				self.formFields = [];
				self.activeFieldIndex = -1;
				renderFieldsList();
				self.showToast('All fields cleared.', 'info');
			});

			// Live Form Preview Modal
			$('#ef-fb-preview-btn').on('click', function () {
				var $canvas = $('#ef-form-preview-canvas').empty();
				if (self.formFields.length === 0) {
					$canvas.html('<div class="ef-empty-state"><p>Form is empty. Add fields to preview.</p></div>');
				} else {
					var pHtml = '<div class="ef-card" style="padding:24px; max-width:700px; margin:0 auto; box-shadow:0 4px 6px rgba(0,0,0,0.05);">';
					pHtml += '<h3 style="margin-top:0; border-bottom:2px solid #0D9488; padding-bottom:8px;">' + $('#ef-fb-act-title-display').text() + '</h3>';

					for (var i = 0; i < self.formFields.length; i++) {
						var f = self.formFields[i];
						var reqMark = f.required ? ' <span class="ef-req" style="color:#EF4444;">*</span>' : '';
						var helpTxt = f.help ? '<span class="ef-help-text" style="display:block; font-size:12px; color:#64748B; margin-top:3px;">' + f.help + '</span>' : '';

						if (f.type === 'heading') {
							pHtml += '<h4 style="margin:20px 0 8px; color:#0F172A; border-bottom:1px solid #E2E8F0; padding-bottom:4px;">' + f.label + '</h4>' + helpTxt;
						} else if (f.type === 'paragraph') {
							pHtml += '<p style="font-size:13px; color:#475569; margin:12px 0;">' + f.label + '</p>' + helpTxt;
						} else if (f.type === 'divider') {
							pHtml += '<hr style="border:none; border-top:1px solid #E2E8F0; margin:18px 0;">';
						} else if (f.type === 'textarea') {
							pHtml += '<div class="ef-form-group" style="margin-bottom:14px;"><label style="font-weight:600; font-size:13px;">' + f.label + reqMark + '</label><textarea class="ef-input" placeholder="' + (f.placeholder || '') + '" rows="3" disabled></textarea>' + helpTxt + '</div>';
						} else if (f.type === 'select') {
							pHtml += '<div class="ef-form-group" style="margin-bottom:14px;"><label style="font-weight:600; font-size:13px;">' + f.label + reqMark + '</label><select class="ef-input" disabled><option>' + (f.placeholder || 'Select an option...') + '</option>';
							if (f.options) {
								for (var o = 0; o < f.options.length; o++) pHtml += '<option>' + f.options[o] + '</option>';
							}
							pHtml += '</select>' + helpTxt + '</div>';
						} else if (f.type === 'radio') {
							pHtml += '<div class="ef-form-group" style="margin-bottom:14px;"><label style="font-weight:600; font-size:13px; display:block; margin-bottom:6px;">' + f.label + reqMark + '</label>';
							if (f.options) {
								for (var r = 0; r < f.options.length; r++) {
									pHtml += '<label style="display:inline-flex; align-items:center; gap:6px; margin-right:16px; font-size:13px;"><input type="radio" disabled> ' + f.options[r] + '</label>';
								}
							}
							pHtml += helpTxt + '</div>';
						} else if (f.type === 'checkbox') {
							pHtml += '<div class="ef-form-group" style="margin-bottom:14px;"><label style="font-weight:600; font-size:13px; display:block; margin-bottom:6px;">' + f.label + reqMark + '</label>';
							if (f.options) {
								for (var c = 0; c < f.options.length; c++) {
									pHtml += '<label style="display:inline-flex; align-items:center; gap:6px; margin-right:16px; font-size:13px;"><input type="checkbox" disabled> ' + f.options[c] + '</label>';
								}
							}
							pHtml += helpTxt + '</div>';
						} else if (f.type === 'consent') {
							pHtml += '<div class="ef-form-group" style="margin-bottom:14px;"><label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer;"><input type="checkbox" disabled> <strong>' + f.label + reqMark + '</strong></label>' + helpTxt + '</div>';
						} else {
							var inType = (['email', 'tel', 'number', 'date', 'time', 'url', 'file'].indexOf(f.type) !== -1) ? f.type : 'text';
							pHtml += '<div class="ef-form-group" style="margin-bottom:14px;"><label style="font-weight:600; font-size:13px;">' + f.label + reqMark + '</label><input type="' + inType + '" class="ef-input" placeholder="' + (f.placeholder || '') + '" disabled>' + helpTxt + '</div>';
						}
					}

					pHtml += '</div>';
					$canvas.html(pHtml);
				}

				$('#ef-form-preview-modal').addClass('active').attr('aria-hidden', 'false');
			});

			// Save Form Schema to Backend
			$('#ef-fb-save-btn').on('click', function () {
				var $btn = $(this);
				if (!self.currentActivityId) {
					alert('No activity selected.');
					return;
				}

				$btn.prop('disabled', true).text('Saving Schema...');

				$.post(window.eventflow_staff_vars.ajax_url, {
					action: 'eventflow_save_form_schema',
					activity_id: self.currentActivityId,
					title: $('#ef-fb-act-title-display').text(),
					schema: JSON.stringify(self.formFields),
					nonce: window.eventflow_staff_vars.staff_nonce
				}, function (res) {
					$btn.prop('disabled', false).html('💾 Save Form Schema');
					if (res.success) {
						self.showToast('Dynamic form schema saved successfully!', 'success');
					} else {
						alert(res.data.message || 'Failed to save form schema.');
					}
				}).fail(function () {
					$btn.prop('disabled', false).html('💾 Save Form Schema');
					alert('Error communicating with server.');
				});
			});
		},

		/**
		 * 6. Announcement Form & Delete Actions
		 */
		bindAnnouncementForm: function () {
			var self = this;
			$('#ef-announcement-form').on('submit', function (e) {
				e.preventDefault();
				var postData = $(this).serializeArray();
				postData.push({ name: 'action', value: 'eventflow_save_announcement' });
				postData.push({ name: 'nonce', value: window.eventflow_staff_vars.staff_nonce });

				var $submitBtn = $(this).find('button[type="submit"]').prop('disabled', true).text('Publishing...');

				$.post(window.eventflow_staff_vars.ajax_url, postData, function (res) {
					$submitBtn.prop('disabled', false).text('Publish Announcement Now');
					if (res.success) {
						self.showToast(res.data.message || 'Announcement published successfully!', 'success');
						setTimeout(function () { window.location.reload(); }, 1000);
					} else {
						alert(res.data.message || 'Action failed.');
					}
				}).fail(function () {
					$submitBtn.prop('disabled', false).text('Publish Announcement Now');
					alert('Error connecting to server.');
				});
			});

			// Delete Announcement
			$(document).on('click', '.ef-delete-ann-btn', function (e) {
				e.preventDefault();
				if (!confirm('Are you sure you want to remove this announcement broadcast?')) return;
				var $btn = $(this);
				var annId = $btn.data('id');
				var $item = $btn.closest('.ef-ann-item');

				$btn.prop('disabled', true);
				$.post(window.eventflow_staff_vars.ajax_url, {
					action: 'eventflow_delete_announcement',
					announcement_id: annId,
					nonce: window.eventflow_staff_vars.staff_nonce
				}, function (res) {
					if (res.success) {
						self.showToast(res.data.message || 'Announcement deleted.', 'success');
						$item.fadeOut(250, function () { $(this).remove(); });
					} else {
						$btn.prop('disabled', false);
						alert(res.data.message || 'Failed to delete announcement.');
					}
				}).fail(function () {
					$btn.prop('disabled', false);
					alert('Error connecting to server.');
				});
			});
		},

		/**
		 * 7. Staff Provisioning & Role Management
		 */
		bindStaffManagement: function () {
			var self = this;
			$('#ef-staff-form').on('submit', function (e) {
				e.preventDefault();
				var postData = $(this).serializeArray();
				postData.push({ name: 'action', value: 'eventflow_save_staff' });
				postData.push({ name: 'nonce', value: window.eventflow_staff_vars.staff_nonce });

				var $submitBtn = $(this).find('button[type="submit"]').prop('disabled', true).text('Saving Account...');

				$.post(window.eventflow_staff_vars.ajax_url, postData, function (res) {
					$submitBtn.prop('disabled', false).text('Save & Assign Permissions');
					if (res.success) {
						self.showToast(res.data.message, 'success');
						setTimeout(function () { window.location.reload(); }, 1000);
					} else {
						alert(res.data.message || 'Action failed.');
					}
				}).fail(function () {
					$submitBtn.prop('disabled', false).text('Save & Assign Permissions');
					alert('Error connecting to server.');
				});
			});

			// Revoke/Remove Staff Role
			$(document).on('click', '.ef-revoke-staff-btn', function (e) {
				e.preventDefault();
				var userId = $(this).data('user-id');
				var userName = $(this).data('user-name') || 'this user';
				if (!confirm('Are you sure you want to revoke staff privileges for ' + userName + '? They will be returned to regular participant status.')) {
					return;
				}

				var $row = $(this).closest('tr');

				$.post(window.eventflow_staff_vars.ajax_url, {
					action: 'eventflow_remove_staff',
					user_id: userId,
					nonce: window.eventflow_staff_vars.staff_nonce
				}, function (res) {
					if (res.success) {
						self.showToast(res.data.message || 'Staff role revoked.', 'success');
						$row.fadeOut(250, function () { $(this).remove(); });
					} else {
						alert(res.data.message || 'Failed to revoke staff role.');
					}
				}).fail(function () {
					alert('Error connecting to server.');
				});
			});
		},

		/**
		 * 8. Master System & Payment Settings (Zero-Admin Frontend AJAX)
		 */
		bindSettingsForm: function () {
			var self = this;

			// Sync color pickers with hex text inputs
			$(document).on('input', '#ef-set-pri-picker', function () {
				$('#ef-set-pri-hex').val($(this).val());
			});
			$(document).on('input', '#ef-set-pri-hex', function () {
				$('#ef-set-pri-picker').val($(this).val());
			});
			$(document).on('input', '#ef-set-sec-picker', function () {
				$('#ef-set-sec-hex').val($(this).val());
			});
			$(document).on('input', '#ef-set-sec-hex', function () {
				$('#ef-set-sec-picker').val($(this).val());
			});

			// AJAX Settings Save
			$('#ef-settings-form').on('submit', function (e) {
				e.preventDefault();
				var postData = $(this).serializeArray();
				postData.push({ name: 'action', value: 'eventflow_save_settings' });
				postData.push({ name: 'nonce', value: window.eventflow_staff_vars.staff_nonce });

				var $btn = $('#ef-save-settings-btn').prop('disabled', true).text('Saving System Settings...');

				$.post(window.eventflow_staff_vars.ajax_url, postData, function (res) {
					$btn.prop('disabled', false).html('💾 Save System Settings');
					if (res.success) {
						self.showToast(res.data.message || 'System and payment settings updated successfully!', 'success');
					} else {
						alert(res.data.message || 'Failed to update settings.');
					}
				}).fail(function () {
					$btn.prop('disabled', false).html('💾 Save System Settings');
					alert('Error connecting to server.');
				});
			});
		},

		/**
		 * 9. Export Filters Dynamic Parameter Sync
		 */
		bindExportFilters: function () {
			function updateExportUrls() {
				var actId = $('#ef-export-activity-filter, #ef-export-activity-select').val() || '';
				var status = $('#ef-export-payment-filter, #ef-export-status-select').val() || '';
				var appStatus = $('#ef-export-app-filter').val() || '';
				var uni = ($('#ef-export-uni-filter').val() || '').trim();

				$('.ef-export-download-btn').each(function () {
					var $btn = $(this);
					var base = $btn.data('base-url') || $btn.attr('href');
					if (!$btn.data('base-url')) {
						$btn.data('base-url', base);
					}

					try {
						var url = new URL(base, window.location.origin);
						if (actId) {
							url.searchParams.set('activity_id', actId);
						} else {
							url.searchParams.delete('activity_id');
						}

						if (status) {
							url.searchParams.set('payment_status', status);
						} else {
							url.searchParams.delete('payment_status');
						}

						if (appStatus) {
							url.searchParams.set('status', appStatus);
						} else {
							url.searchParams.delete('status');
						}

						if (uni) {
							url.searchParams.set('university', uni);
						} else {
							url.searchParams.delete('university');
						}

						$btn.attr('href', url.toString());
					} catch (e) {
						// Fallback if URL parsing fails
					}
				});
			}

			$(document).on('change input', '#ef-export-activity-filter, #ef-export-payment-filter, #ef-export-app-filter, #ef-export-uni-filter, #ef-export-activity-select, #ef-export-status-select', function () {
				updateExportUrls();
			});
		},

		/**
		 * 10. Team & Roster Management Actions
		 */
		bindTeamManagement: function () {
			var self = this;

			$(document).on('click', '.ef-team-status-action-btn', function (e) {
				e.preventDefault();
				var $btn = $(this);
				var teamId = $btn.data('team-id');
				var status = $btn.data('status');
				if (!teamId || !status) return;

				var confirmMsg = (status === 'approved')
					? 'Are you sure you want to approve this team registration application?'
					: 'Are you sure you want to reject this team registration application?';

				if (!confirm(confirmMsg)) return;

				$btn.prop('disabled', true);
				var nonce = (window.eventflow_staff_vars && window.eventflow_staff_vars.staff_nonce) || '';
				var ajaxUrl = (window.eventflow_staff_vars && window.eventflow_staff_vars.ajax_url) || '/wp-admin/admin-ajax.php';

				$.post(ajaxUrl, {
					action: 'eventflow_update_team_status',
					team_id: teamId,
					status: status,
					nonce: nonce
				}, function (res) {
					$btn.prop('disabled', false);
					if (res.success) {
						self.showToast(res.data.message || 'Team status updated successfully.', 'success');
						setTimeout(function () { window.location.reload(); }, 600);
					} else {
						alert(res.data.message || 'Failed to update team status.');
					}
				}).fail(function () {
					$btn.prop('disabled', false);
					alert('Network error communicating with server.');
				});
			});
		},

		/**
		 * 11. Data Management Center (13 Export Categories + Advanced Import)
		 */
		bindDataManagement: function () {
			var self = this;

			// Tab Switching between "Export Reports" and "Batch Data Import"
			$(document).on('click', '.ef-data-tab-btn', function (e) {
				e.preventDefault();
				var tab = $(this).data('tab');
				$('.ef-data-tab-btn').removeClass('active ef-btn-primary').addClass('ef-btn-outline');
				$(this).addClass('active ef-btn-primary').removeClass('ef-btn-outline');
				$('.ef-data-tab-content').hide();
				$('#ef-data-tab-' + tab).show();
			});

			// --- BATCH DATA IMPORT WORKFLOW ---
			var selectedImportFile = null;
			var $dropzone = $('#ef-import-dropzone');
			var $fileInput = $('#ef-import-file-input');
			var $inspectBtn = $('#ef-btn-inspect-import');
			var $executeBtn = $('#ef-btn-execute-import');
			var $filenameBadge = $('#ef-import-selected-filename');
			var $previewBox = $('#ef-import-preview-box');

			function handleSelectedFile(file) {
				if (!file) return;
				var allowedExt = ['.csv', '.xlsx', '.xls'];
				var fileName = file.name.toLowerCase();
				var isValidExt = allowedExt.some(function (ext) { return fileName.endsWith(ext); });

				if (!isValidExt) {
					alert('Invalid file format. Please select a valid CSV (.csv) or Excel (.xlsx, .xls) spreadsheet.');
					return;
				}

				if (file.size > 10 * 1024 * 1024) {
					alert('File size exceeds the 10MB limit.');
					return;
				}

				selectedImportFile = file;
				var sizeKb = (file.size / 1024).toFixed(1);
				$filenameBadge.text('Selected: ' + file.name + ' (' + sizeKb + ' KB)').show();
				$inspectBtn.prop('disabled', false).html('🔍 Inspect &amp; Validate File');
				$previewBox.hide();
			}

			// Dropzone Drag-and-Drop
			$dropzone.on('dragover dragenter', function (e) {
				e.preventDefault();
				e.stopPropagation();
				$(this).addClass('drag-over');
			});
			$dropzone.on('dragleave dragend drop', function (e) {
				e.preventDefault();
				e.stopPropagation();
				$(this).removeClass('drag-over');
			});
			$dropzone.on('drop', function (e) {
				var files = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files;
				if (files && files.length > 0) {
					handleSelectedFile(files[0]);
				}
			});

			// File input change
			$fileInput.on('change', function () {
				if (this.files && this.files.length > 0) {
					handleSelectedFile(this.files[0]);
				}
			});

			// Inspect & Preview Import
			$inspectBtn.on('click', function (e) {
				e.preventDefault();
				if (!selectedImportFile) {
					alert('Please select a file to inspect first.');
					return;
				}

				var nonce = (window.eventflow_staff_vars && window.eventflow_staff_vars.staff_nonce) || '';
				var ajaxUrl = (window.eventflow_staff_vars && window.eventflow_staff_vars.ajax_url) || '/wp-admin/admin-ajax.php';

				var formData = new FormData();
				formData.append('action', 'eventflow_preview_import');
				formData.append('import_file', selectedImportFile);
				formData.append('nonce', nonce);

				$inspectBtn.prop('disabled', true).html('<div class="ef-spinner"></div> Validating Rows...');

				$.ajax({
					url: ajaxUrl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function (res) {
						$inspectBtn.prop('disabled', false).html('🔍 Inspect &amp; Validate File');
						if (res.success && res.data) {
							var d = res.data;
							$previewBox.slideDown(200);

							// Update KPIs
							$('#ef-import-kpi-total').text(d.total_rows || 0);
							$('#ef-import-kpi-valid').text(d.valid_rows || 0);
							$('#ef-import-kpi-dups').text(d.duplicates || 0);
							$('#ef-import-kpi-invalid').text(d.invalid_rows || 0);

							// Status Tag
							if (d.valid_rows > 0) {
								$('#ef-import-status-tag').attr('class', 'ef-badge ef-badge-success').text('Ready for Import');
								$executeBtn.prop('disabled', false);
							} else {
								$('#ef-import-status-tag').attr('class', 'ef-badge ef-badge-danger').text('No Valid Records');
								$executeBtn.prop('disabled', true);
							}

							// Render Errors
							if (d.invalid_rows > 0 && d.errors && d.errors.length) {
								var errHtml = '';
								for (var i = 0; i < d.errors.length; i++) {
									var err = d.errors[i];
									errHtml += '<tr>';
									errHtml += '<td><code>#' + (err.row || '-') + '</code></td>';
									errHtml += '<td>' + (err.identifier || err.email || '-') + '</td>';
									errHtml += '<td style="color:#DC2626;">' + (err.message || 'Validation failed') + '</td>';
									errHtml += '</tr>';
								}
								$('#ef-import-errors-tbody').html(errHtml);
								$('#ef-import-errors-wrap').show();
							} else {
								$('#ef-import-errors-wrap').hide();
							}

							// Render Rows Preview
							if (d.preview && d.preview.length) {
								var prevHtml = '';
								for (var j = 0; j < d.preview.length; j++) {
									var row = d.preview[j];
									var stBadge = row.valid 
										? '<span class="ef-badge ef-badge-success">Valid</span>' 
										: '<span class="ef-badge ef-badge-danger">Invalid</span>';
									if (row.is_duplicate) {
										stBadge = '<span class="ef-badge ef-badge-warning">Duplicate</span>';
									}

									prevHtml += '<tr>';
									prevHtml += '<td>' + (j + 1) + '</td>';
									prevHtml += '<td><strong>' + (row.name || row.team_name || '-') + '</strong></td>';
									prevHtml += '<td><span class="ef-badge ef-badge-neutral">' + (row.type || 'solo') + '</span></td>';
									prevHtml += '<td class="ef-text-xs">' + (row.email || '-') + '</td>';
									prevHtml += '<td class="ef-text-xs">' + (row.university || '-') + '</td>';
									prevHtml += '<td class="ef-text-xs">' + (row.activity_title || row.activity || '-') + '</td>';
									prevHtml += '<td>' + stBadge + '</td>';
									prevHtml += '</tr>';
								}
								$('#ef-import-preview-tbody').html(prevHtml);
							} else {
								$('#ef-import-preview-tbody').html('<tr><td colspan="7" class="ef-text-center ef-text-muted">No rows to display.</td></tr>');
							}
						} else {
							alert((res.data && res.data.message) || 'Failed to inspect file.');
						}
					},
					error: function () {
						$inspectBtn.prop('disabled', false).html('🔍 Inspect &amp; Validate File');
						alert('Server communication error during file inspection.');
					}
				});
			});

			// Execute Final Batch Import
			$executeBtn.on('click', function (e) {
				e.preventDefault();
				if (!selectedImportFile) return;

				var validCount = $('#ef-import-kpi-valid').text() || 'valid';
				if (!confirm('Are you sure you want to execute batch import for ' + validCount + ' records? All changes will be securely written inside a database transaction.')) {
					return;
				}

				var nonce = (window.eventflow_staff_vars && window.eventflow_staff_vars.staff_nonce) || '';
				var ajaxUrl = (window.eventflow_staff_vars && window.eventflow_staff_vars.ajax_url) || '/wp-admin/admin-ajax.php';

				var formData = new FormData();
				formData.append('action', 'eventflow_process_import');
				formData.append('import_file', selectedImportFile);
				formData.append('skip_duplicates', $('#ef-import-skip-duplicates').is(':checked') ? 1 : 0);
				formData.append('nonce', nonce);

				$executeBtn.prop('disabled', true).html('<div class="ef-spinner"></div> Importing Records...');

				$.ajax({
					url: ajaxUrl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function (res) {
						$executeBtn.prop('disabled', false).html('🚀 Confirm &amp; Execute Import');
						if (res.success) {
							self.showToast(res.data.message || 'Data import completed successfully!', 'success');
							alert(res.data.message || 'Import completed successfully!');
							setTimeout(function () {
								window.location.reload();
							}, 800);
						} else {
							alert((res.data && res.data.message) || 'Import failed.');
						}
					},
					error: function () {
						$executeBtn.prop('disabled', false).html('🚀 Confirm &amp; Execute Import');
						alert('Server error occurred during data import execution.');
					}
				});
			});
		},

		/**
		 * 12. Locked Accounts & Brute Force Defense Admin Actions
		 */
		bindLockedAccounts: function () {
			var self = this;

			$(document).on('click', '.ef-btn-unlock-account', function (e) {
				e.preventDefault();
				var $btn = $(this);
				var identifier = $btn.data('identifier');
				if (!identifier) return;

				if (!confirm('Are you sure you want to unlock this account (' + identifier + ')? Failed login counters will be cleared immediately.')) {
					return;
				}

				$btn.prop('disabled', true).text('Unlocking...');

				var nonce = (window.eventflow_staff_vars && window.eventflow_staff_vars.staff_nonce) ||
							(window.eventflow_admin_vars && window.eventflow_admin_vars.staff_nonce) || '';
				var ajaxUrl = (window.eventflow_staff_vars && window.eventflow_staff_vars.ajax_url) ||
							  (window.eventflow_admin_vars && window.eventflow_admin_vars.ajax_url) || '/wp-admin/admin-ajax.php';

				$.post(ajaxUrl, {
					action: 'eventflow_unlock_account',
					identifier: identifier,
					nonce: nonce
				}, function (res) {
					if (res.success) {
						self.showToast(res.data.message || 'Account unlocked successfully!', 'success');
						var $row = $btn.closest('tr');
						$row.fadeOut(300, function () {
							$row.remove();
							if (!$('.ef-btn-unlock-account').length) {
								$('#ef-locked-accounts-table tbody').html('<tr><td colspan="5" class="ef-text-center ef-text-muted" style="padding:20px;">No accounts are currently locked. System is fully operational.</td></tr>');
							}
						});
					} else {
						$btn.prop('disabled', false).text('Unlock Account');
						alert(res.data.message || 'Failed to unlock account.');
					}
				}).fail(function () {
					$btn.prop('disabled', false).text('Unlock Account');
					alert('Network error communicating with server.');
				});
			});
		},

		/**
		 * Toast Notification Banner
		 */
		showToast: function (msg, type) {
			var $container = $('#ef-toast-container');
			if (!$container.length) {
				$container = $('<div id="ef-toast-container" class="ef-toast-container"></div>');
				$('body').append($container);
			}

			var $toast = $('<div class="ef-toast ' + (type || 'info') + '">' + msg + '</div>');
			$container.append($toast);

			setTimeout(function () {
				$toast.fadeOut(300, function () { $(this).remove(); });
			}, 3500);
		}
	};

	$(document).ready(function () {
		StaffDashboard.init();
	});

})(jQuery);
