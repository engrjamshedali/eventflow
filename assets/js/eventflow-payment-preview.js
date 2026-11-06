/**
 * EventFlow Advanced Payment Screenshot Preview & Verification Controller
 * Handles image zoom, pan, rotation, PDF embedding, and modal verification actions.
 */

(function ($) {
	'use strict';

	var PaymentPreview = {
		scale: 1,
		rotation: 0,
		translateX: 0,
		translateY: 0,
		isDragging: false,
		startX: 0,
		startY: 0,
		activePaymentId: 0,

		init: function () {
			this.bindEvents();
		},

		getAjaxUrl: function () {
			return (window.eventflow_staff_vars && window.eventflow_staff_vars.ajax_url) ||
				   (window.eventflow_admin_vars && window.eventflow_admin_vars.ajax_url) ||
				   (window.eventflow_vars && window.eventflow_vars.ajax_url) ||
				   '/wp-admin/admin-ajax.php';
		},

		getStaffNonce: function () {
			return (window.eventflow_staff_vars && window.eventflow_staff_vars.staff_nonce) ||
				   (window.eventflow_admin_vars && window.eventflow_admin_vars.staff_nonce) || '';
		},

		bindEvents: function () {
			var self = this;

			// Modal Dismissal Handler
			$(document).on('click', '.ef-modal-close, [data-dismiss="modal"]', function (e) {
				e.preventDefault();
				$(this).closest('.ef-modal').removeClass('active').attr('aria-hidden', 'true').hide();
			});
			$(document).on('click', '.ef-modal-backdrop', function () {
				$(this).closest('.ef-modal').removeClass('active').attr('aria-hidden', 'true').hide();
			});

			// Open Inspector modal
			$(document).on('click', '.ef-preview-proof-btn, .ef-quick-verify-btn', function (e) {
				e.preventDefault();
				self.openPreviewModal($(this));
			});

			// Switch proof version
			$(document).on('click', '.ef-switch-proof-btn', function (e) {
				e.preventDefault();
				var $btn = $(this);
				var u = $btn.data('url');
				var downUrl = $btn.data('download-url') || u;
				var ft = $btn.data('filetype');
				var tid = $btn.data('tid');
				var pid = $btn.data('payment-id');
				var pStatus = $btn.data('status');
				var pAmount = $btn.data('amount');
				var pDate = $btn.data('date');
				var pMethod = $btn.data('method');

				if (pid) {
					self.activePaymentId = pid;
					$('#ef-active-payment-id').val(pid);
				}
				if (tid) {
					$('#ef-pv-tid').text(tid);
				}
				if (pAmount) {
					$('#ef-pv-amount').text('PKR ' + Number(pAmount).toLocaleString());
				}
				if (pDate) {
					$('#ef-pv-date').text(pDate);
				}
				if (pMethod) {
					$('#ef-pv-method').text(pMethod);
				}
				if (pStatus) {
					self.setBadge(pStatus);
				}
				if (downUrl) {
					$('#ef-btn-download-receipt').attr('href', downUrl).show();
				}
				self.resetTransform();
				self.renderProofFile(u, ft);

				$('.ef-sub-item-box').css('outline', 'none');
				$btn.closest('.ef-sub-item-box').css('outline', '2px solid #0D9488');
			});

			// Zoom In
			$('#ef-zoom-in').on('click', function () {
				self.scale = Math.min(4.0, self.scale + 0.25);
				self.applyTransform();
			});

			// Zoom Out
			$('#ef-zoom-out').on('click', function () {
				self.scale = Math.max(0.4, self.scale - 0.25);
				self.applyTransform();
			});

			// Rotate 90°
			$('#ef-rotate').on('click', function () {
				self.rotation = (self.rotation + 90) % 360;
				self.applyTransform();
			});

			// Fit to Screen
			$('#ef-fit-screen').on('click', function () {
				self.fitToScreen();
			});

			// Reset View
			$('#ef-zoom-reset').on('click', function () {
				self.resetTransform();
			});

			// Mouse Drag / Pan inside viewport
			var $viewport = $('#ef-screenshot-viewport');

			$viewport.on('mousedown', function (e) {
				if (e.target.tagName.toLowerCase() === 'iframe') return;
				self.isDragging = true;
				self.startX = e.clientX - self.translateX;
				self.startY = e.clientY - self.translateY;
				$viewport.css('cursor', 'grabbing');
			});

			$(document).on('mousemove', function (e) {
				if (self.isDragging) {
					self.translateX = e.clientX - self.startX;
					self.translateY = e.clientY - self.startY;
					self.applyTransform();
				}
			});

			$(document).on('mouseup', function () {
				if (self.isDragging) {
					self.isDragging = false;
					$viewport.css('cursor', 'grab');
				}
			});

			// 1. Verify Payment Action
			$('#ef-btn-verify-payment').on('click', function () {
				var paymentId = $('#ef-active-payment-id').val();
				if (!paymentId || paymentId === '0') return;

				if (!confirm('Are you sure you want to verify and approve this payment? An official confirmation email will be dispatched to the participant.')) {
					return;
				}

				var note = $('#ef-action-notes').val();
				self.executeAction('eventflow_verify_payment', {
					payment_id: paymentId,
					note: note
				});
			});

			// 2. Reject Payment Action (Opens Clean Modal)
			$('#ef-btn-reject-payment').on('click', function () {
				var paymentId = $('#ef-active-payment-id').val();
				if (!paymentId || paymentId === '0') return;

				$('#ef-reject-reason-text').val('');
				$('#ef-reject-modal').addClass('active').attr('aria-hidden', 'false').css('display', 'flex');
			});

			$('#ef-confirm-reject-btn').on('click', function () {
				var paymentId = $('#ef-active-payment-id').val();
				var reason = $('#ef-reject-reason-text').val().trim();

				if (!reason) {
					alert('Please enter a rejection reason.');
					$('#ef-reject-reason-text').focus();
					return;
				}

				var notes = $('#ef-action-notes').val() || '';
				if (!notes) {
					notes = reason;
				}

				$('#ef-reject-modal').removeClass('active').attr('aria-hidden', 'true').hide();
				self.executeAction('eventflow_reject_payment', {
					payment_id: paymentId,
					reason: reason,
					notes: notes
				});
			});

			// 3. Request Re-upload Action (Opens Clean Modal)
			$('#ef-btn-reupload-payment').on('click', function () {
				var paymentId = $('#ef-active-payment-id').val();
				if (!paymentId || paymentId === '0') return;

				$('#ef-reupload-instructions-text').val('');
				$('#ef-reupload-modal').addClass('active').attr('aria-hidden', 'false').css('display', 'flex');
			});

			$('#ef-confirm-reupload-btn').on('click', function () {
				var paymentId = $('#ef-active-payment-id').val();
				var instructions = $('#ef-reupload-instructions-text').val().trim();

				if (!instructions) {
					alert('Please enter instructions for the participant.');
					$('#ef-reupload-instructions-text').focus();
					return;
				}

				$('#ef-reupload-modal').removeClass('active').attr('aria-hidden', 'true').hide();
				self.executeAction('eventflow_request_reupload', {
					payment_id: paymentId,
					instructions: instructions
				});
			});

			// 4. Edit Payment Modal Trigger
			$(document).on('click', '.ef-edit-payment-btn', function (e) {
				e.preventDefault();
				var $btn = $(this);
				var paymentId = $btn.data('payment-id');
				var tid = $btn.data('tid') || '';
				var method = $btn.data('method') || 'Bank Transfer';
				var amount = $btn.data('amount') || '0';
				var date = $btn.data('date') || '';
				var notes = $btn.data('notes') || '';
				var status = $btn.data('status') || 'under_verification';

				$('#ef-edit-pay-id').val(paymentId);
				$('#ef-edit-pay-current-status').val(status);
				$('#ef-edit-pay-tid').val(tid);
				$('#ef-edit-pay-method').val(method);
				$('#ef-edit-pay-amount').val(amount);
				$('#ef-edit-pay-date').val(date);
				$('#ef-edit-pay-notes').val(notes);
				$('#ef-edit-pay-status').val(status);
				$('#ef-edit-pay-correction').val('');
				$('#ef-edit-pay-file').val('');

				// Show mandatory correction reason if status is already verified
				if (status === 'verified') {
					$('#ef-correction-reason-box').show();
					$('#ef-edit-pay-correction').prop('required', true);
				} else {
					$('#ef-correction-reason-box').hide();
					$('#ef-edit-pay-correction').prop('required', false);
				}

				$('#ef-payment-edit-modal').addClass('active').attr('aria-hidden', 'false').css('display', 'flex');
			});

			// Toggle correction reason if user changes status to/from verified
			$('#ef-edit-pay-status').on('change', function () {
				var currentStatus = $('#ef-edit-pay-current-status').val();
				if (currentStatus === 'verified' || $(this).val() === 'verified') {
					$('#ef-correction-reason-box').show();
					$('#ef-edit-pay-correction').prop('required', true);
				} else {
					$('#ef-correction-reason-box').hide();
					$('#ef-edit-pay-correction').prop('required', false);
				}
			});

			// Submit Payment Edit Form
			$('#ef-edit-payment-form').on('submit', function (e) {
				e.preventDefault();
				var formData = new FormData(this);
				formData.append('action', 'eventflow_edit_payment');
				formData.append('nonce', self.getStaffNonce());

				var $submitBtn = $('#ef-save-payment-edit-btn').prop('disabled', true);

				$.ajax({
					url: self.getAjaxUrl(),
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function (res) {
						$submitBtn.prop('disabled', false);
						if (res.success) {
							alert(res.data.message || 'Payment updated successfully.');
							$('#ef-payment-edit-modal').removeClass('active');
							window.location.reload();
						} else {
							alert(res.data.message || 'Failed to update payment.');
						}
					},
					error: function () {
						$submitBtn.prop('disabled', false);
						alert('Server communication error.');
					}
				});
			});

			// 5. Payment Audit History Timeline Modal Trigger
			$(document).on('click', '.ef-payment-history-btn', function (e) {
				e.preventDefault();
				var paymentId = $(this).data('payment-id');
				var regId = $(this).data('reg-id') || '';

				if (!paymentId) return;

				var $modal = $('#ef-payment-history-modal');
				var $content = $('#ef-payment-history-content').html('<div class="ef-spinner"></div>');
				$modal.addClass('active').attr('aria-hidden', 'false');

				$.getJSON(self.getAjaxUrl(), {
					action: 'eventflow_get_payment_history',
					payment_id: paymentId,
					nonce: self.getStaffNonce()
				}, function (res) {
					if (res.success && res.data && res.data.length) {
						var html = '<div class="ef-timeline-box">';
						html += '<h4 style="margin: 0 0 16px;">Registration: <strong class="ef-monospace">' + regId + '</strong> (Payment #' + paymentId + ')</h4>';
						html += '<div class="ef-table-responsive"><table class="ef-table ef-table-bordered">';
						html += '<thead><tr><th>Timestamp</th><th>Action</th><th>Performed By</th><th>Audit Notes & Changes</th></tr></thead><tbody>';

						for (var i = 0; i < res.data.length; i++) {
							var row = res.data[i];
							var actionBadge = '<span class="ef-badge ef-badge-neutral">' + row.action + '</span>';
							if (row.action === 'verified') actionBadge = '<span class="ef-badge ef-badge-success">Verified</span>';
							else if (row.action === 'rejected') actionBadge = '<span class="ef-badge ef-badge-danger">Rejected</span>';
							else if (row.action === 'reupload_requested') actionBadge = '<span class="ef-badge ef-badge-warning">Re-upload</span>';
							else if (row.action === 'payment_edited') actionBadge = '<span class="ef-badge ef-badge-primary">Edited</span>';
							else if (row.action === 'receipt_replaced') actionBadge = '<span class="ef-badge ef-badge-primary">Receipt Replaced</span>';

							html += '<tr>';
							html += '<td class="ef-text-xs ef-monospace">' + (row.created_at || '-') + '</td>';
							html += '<td>' + actionBadge + '</td>';
							html += '<td><strong>' + (row.staff_name || 'System / Participant') + '</strong></td>';
							html += '<td class="ef-text-sm">' + (row.note || '-') + '</td>';
							html += '</tr>';
						}

						html += '</tbody></table></div></div>';
						$content.html(html);
					} else {
						$content.html('<p class="ef-notice ef-notice-info">No previous audit logs recorded for this payment record.</p>');
					}
				}).fail(function () {
					$content.html('<p class="ef-notice ef-notice-danger">Failed to fetch payment audit history.</p>');
				});
			});
		},

		escapeHtml: function (str) {
			if (!str) return '';
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		},

		setBadge: function (st) {
			st = st || '';
			var sLabel = st ? st.replace(/_/g, ' ').toUpperCase() : 'UNKNOWN';
			var bClass = 'ef-badge-neutral';
			if (st === 'verified') {
				bClass = 'ef-badge-success';
				sLabel = 'VERIFIED';
			} else if (st === 'rejected') {
				bClass = 'ef-badge-danger';
				sLabel = 'REJECTED – RE-UPLOAD REQUIRED';
			} else if (st === 'under_verification') {
				bClass = 'ef-badge-warning';
				sLabel = 'PENDING VERIFICATION';
			} else if (st === 'reupload_requested') {
				bClass = 'ef-badge-warning';
				sLabel = 'RE-UPLOAD REQUESTED';
			}
			$('#ef-pv-status-badge').html('<span class="ef-badge ' + bClass + '">' + sLabel + '</span>');
		},

		renderProofFile: function (u, ft) {
			var $img = $('#ef-preview-img');
			var $pdf = $('#ef-preview-pdf');
			var $loading = $('#ef-preview-loading').show();
			var $error = $('#ef-preview-error').hide();

			ft = (ft || '').toLowerCase();
			u = u || '';
			if (!u) {
				$loading.hide();
				$error.text('No payment receipt file available for this record.').show();
				$img.hide();
				$pdf.hide();
			} else if (ft.indexOf('pdf') !== -1 || u.indexOf('.pdf') !== -1) {
				$img.hide();
				$pdf.attr('src', u).show();
				$pdf.off('load').on('load', function () {
					$loading.hide();
				});
			} else {
				$pdf.hide().attr('src', '');
				$img.attr('src', u).show();
				$img.off('load error').on('load', function () {
					$loading.hide();
				}).on('error', function () {
					$loading.hide();
					$error.text('Unable to render image. File may be protected or unavailable.').show();
				});
			}
		},

		openPreviewModal: function ($btn) {
			var self = this;
			var paymentId = $btn.data('payment-id');
			var regId     = $btn.data('reg-id') || '';
			var activity  = $btn.data('activity') || '';
			var team      = $btn.data('team') || '';
			var method    = $btn.data('method') || '';
			var tid       = $btn.data('tid') || '';
			var amount    = $btn.data('amount') || '';
			var date      = $btn.data('date') || '';
			var fileUrl   = $btn.data('url') || '';
			var fileType  = ($btn.data('filetype') || '').toLowerCase();
			var status    = $btn.data('status') || '';

			self.activePaymentId = paymentId;
			$('#ef-active-payment-id').val(paymentId);
			$('#ef-modal-reg-id-badge').text(regId || '...');
			$('#ef-pv-reg-id').text(regId || '...');
			$('#ef-pv-tid').text(tid || '...');
			$('#ef-pv-amount').text(amount ? ('PKR ' + amount) : '...');
			$('#ef-pv-required-amount').text('...');
			$('#ef-pv-method').text(method || '...');
			$('#ef-pv-date').text(date || '...');
			$('#ef-pv-team-name').text(team || '...');
			$('#ef-pv-leader-name').text('...');
			$('#ef-pv-leader-email').text('...');
			$('#ef-pv-leader-phone').text('...');
			$('#ef-pv-reg-date').text('...');
			$('#ef-pv-university').text('...');
			$('#ef-pv-dept-sem').text('...');
			$('#ef-pv-competition-title').text(activity || '...');
			$('#ef-pv-fee-diff-badge').empty();
			$('#ef-action-notes').val('');

			// Reset previous rejection remarks box
			$('#ef-pv-rejection-remarks-box').hide();
			$('#ef-pv-previous-rejection-text').text('');
			$('#ef-pv-rejected-version-badge').text('');

			// Loading skeletons for tables
			$('#ef-pv-members-tbody').html('<tr><td colspan="6" class="ef-text-center" style="padding: 16px;"><div class="ef-spinner"></div> Loading member roster...</td></tr>');
			$('#ef-pv-history-tbody').html('<tr><td colspan="4" class="ef-text-center" style="padding: 12px;"><div class="ef-spinner"></div> Loading verification history...</td></tr>');

			self.setBadge(status);
			self.resetTransform();

			// Initial proof render
			self.renderProofFile(fileUrl, fileType);
			if (fileUrl) {
				$('#ef-btn-download-receipt').attr('href', fileUrl).show();
			} else {
				$('#ef-btn-download-receipt').hide();
			}

			// Fetch full registration, team, fee reconciliation & audit details
			if (paymentId) {
				$.getJSON(self.getAjaxUrl(), {
					action: 'eventflow_get_payment_details',
					payment_id: paymentId,
					nonce: self.getStaffNonce()
				}, function (res) {
					if (res.success && res.data) {
						var d = res.data;

						// Set active payment ID to latest submission if available
						if (d.latest_payment_id) {
							self.activePaymentId = d.latest_payment_id;
							$('#ef-active-payment-id').val(d.latest_payment_id);
						}

						// Badges & Header
						$('#ef-modal-reg-id-badge').text(d.reg_id || regId);
						self.setBadge(d.status);
						if (d.participation_type) {
							$('#ef-pv-type-badge').text(d.participation_type.toUpperCase()).show();
						}

						// Previous Rejection Remarks Box
						if (d.previous_rejection_reason) {
							$('#ef-pv-previous-rejection-text').text(d.previous_rejection_reason);
							if (d.latest_version && d.latest_version > 1) {
								$('#ef-pv-rejected-version-badge').text('Prior to v' + d.latest_version).show();
							} else {
								$('#ef-pv-rejected-version-badge').text('Rejection Remarks').show();
							}
							$('#ef-pv-rejection-remarks-box').show();
						} else {
							$('#ef-pv-rejection-remarks-box').hide();
						}

						// 1. Team & Candidate Information
						$('#ef-pv-team-name').text(d.team_name || d.candidate_name || '-');
						$('#ef-pv-reg-id').text(d.reg_id || '-');
						$('#ef-pv-leader-name').text(d.leader_name || '-');
						if (d.leader_email) {
							$('#ef-pv-leader-email').html('<a href="mailto:' + self.escapeHtml(d.leader_email) + '">' + self.escapeHtml(d.leader_email) + '</a>');
						} else {
							$('#ef-pv-leader-email').text('-');
						}
						if (d.leader_phone) {
							$('#ef-pv-leader-phone').html('<a href="tel:' + self.escapeHtml(d.leader_phone) + '">' + self.escapeHtml(d.leader_phone) + '</a>');
						} else {
							$('#ef-pv-leader-phone').text('-');
						}
						$('#ef-pv-reg-date').text(d.reg_date || '-');
						$('#ef-pv-university').text(d.university || '-');
						var deptSem = [];
						if (d.department) deptSem.push(d.department);
						if (d.semester) deptSem.push('Sem ' + d.semester);
						$('#ef-pv-dept-sem').text(deptSem.length ? deptSem.join(' / ') : '-');
						$('#ef-pv-competition-title').text((d.activity_title || '-') + (d.event_title ? (' (' + d.event_title + ')') : ''));

						// 2. All Team Members Roster (7 Fields)
						var members = d.members || [];
						$('#ef-pv-members-count-badge').text(members.length + (d.participation_type === 'team' ? ' Members' : ' Participant(s)'));
						if (members.length > 0) {
							var mHtml = '';
							for (var i = 0; i < members.length; i++) {
								var m = members[i];
								var isLeader = parseInt(m.is_leader, 10) === 1;
								var roleBadge = isLeader ? '<span class="ef-badge ef-badge-primary">Leader</span>' : '<span class="ef-badge ef-badge-neutral">Member</span>';
								var mDept = [];
								if (m.department) mDept.push(m.department);
								if (m.semester) mDept.push('Sem ' + m.semester);

								mHtml += '<tr>';
								mHtml += '<td><strong>' + self.escapeHtml(m.full_name || '-') + '</strong></td>';
								mHtml += '<td>' + roleBadge + '</td>';
								mHtml += '<td class="ef-text-xs"><a href="mailto:' + self.escapeHtml(m.email || '') + '">' + self.escapeHtml(m.email || '-') + '</a></td>';
								mHtml += '<td class="ef-text-xs">' + self.escapeHtml(m.contact_number || '-') + '</td>';
								mHtml += '<td class="ef-text-xs">' + self.escapeHtml(m.university || '-') + '</td>';
								mHtml += '<td class="ef-text-xs">' + self.escapeHtml(mDept.join(' / ') || '-') + '</td>';
								mHtml += '</tr>';
							}
							$('#ef-pv-members-tbody').html(mHtml);
						} else {
							$('#ef-pv-members-tbody').html('<tr><td colspan="6" class="ef-text-center ef-text-muted" style="padding: 12px;">No individual member records found.</td></tr>');
						}

						// 3. Payment & Financial Reconciliation
						var curr = d.currency || 'PKR';
						var submittedAmt = (d.amount != null) ? Number(d.amount) : 0;
						var requiredAmt  = (d.required_amount != null) ? Number(d.required_amount) : 0;
						$('#ef-pv-tid').text(d.transaction_id || '-');
						$('#ef-pv-method').text(d.payment_method || '-');
						$('#ef-pv-amount').text(curr + ' ' + submittedAmt.toLocaleString());
						$('#ef-pv-required-amount').text(curr + ' ' + requiredAmt.toLocaleString());
						$('#ef-pv-date').text(d.formatted_date || d.payment_date || '-');
						$('#ef-pv-verified-by').text(d.verified_by_name ? (d.verified_by_name + (d.verified_at ? ' (' + d.verified_at + ')' : '')) : 'Pending Verification');

						var diff = submittedAmt - requiredAmt;
						if (diff === 0) {
							$('#ef-pv-fee-diff-badge').html('<span class="ef-badge ef-badge-success">✓ Exact Fee Match</span>');
						} else if (diff < 0) {
							$('#ef-pv-fee-diff-badge').html('<span class="ef-badge ef-badge-danger">⚠ Underpaid by ' + curr + ' ' + Math.abs(diff).toLocaleString() + '</span>');
						} else {
							$('#ef-pv-fee-diff-badge').html('<span class="ef-badge ef-badge-warning">ℹ Overpaid by ' + curr + ' ' + Math.abs(diff).toLocaleString() + '</span>');
						}

						// 4. Verification History Table
						var hist = d.history || [];
						if (hist.length > 0) {
							var hHtml = '';
							for (var j = 0; j < hist.length; j++) {
								var h = hist[j];
								var hBadge = '<span class="ef-badge ef-badge-neutral">' + self.escapeHtml(h.action || '') + '</span>';
								if (h.action === 'verified') hBadge = '<span class="ef-badge ef-badge-success">Verified</span>';
								else if (h.action === 'rejected') hBadge = '<span class="ef-badge ef-badge-danger">Rejected</span>';
								else if (h.action === 'reupload_requested') hBadge = '<span class="ef-badge ef-badge-warning">Re-upload</span>';
								else if (h.action === 'payment_edited') hBadge = '<span class="ef-badge ef-badge-primary">Edited</span>';

								hHtml += '<tr>';
								hHtml += '<td class="ef-text-xs ef-monospace">' + self.escapeHtml(h.created_at || '-') + '</td>';
								hHtml += '<td>' + hBadge + '</td>';
								hHtml += '<td class="ef-text-xs"><strong>' + self.escapeHtml(h.staff_name || 'Staff') + '</strong></td>';
								hHtml += '<td class="ef-text-xs">' + self.escapeHtml(h.note || '-') + '</td>';
								hHtml += '</tr>';
							}
							$('#ef-pv-history-tbody').html(hHtml);
						} else {
							$('#ef-pv-history-tbody').html('<tr><td colspan="4" class="ef-text-center ef-text-muted" style="padding: 10px;">No previous verification history.</td></tr>');
						}

						// 5. Submission Versions / Resubmissions & Historical Receipts
						var submissions = d.submissions || [];
						var subCount = submissions.length || 1;
						$('#ef-pv-version-count-badge').text(subCount + (subCount === 1 ? ' Version' : ' Versions'));
						if (submissions.length > 0) {
							var sHtml = '<div style="display:flex; flex-direction:column; gap:8px;">';
							for (var sIdx = 0; sIdx < submissions.length; sIdx++) {
								var sub = submissions[sIdx];
								var isLatest = (sub.is_latest !== undefined) ? (sub.is_latest === true || sub.is_latest === 1) : ((sub.id && d.latest_payment_id) ? (sub.id === d.latest_payment_id) : (sIdx === submissions.length - 1));
								var sStatusBadge = '<span class="ef-badge ef-badge-neutral">' + self.escapeHtml(sub.status) + '</span>';
								if (sub.status === 'verified') sStatusBadge = '<span class="ef-badge ef-badge-success">Approved</span>';
								else if (sub.status === 'rejected') sStatusBadge = '<span class="ef-badge ef-badge-danger">Rejected – Re-upload Required</span>';
								else if (sub.status === 'under_verification') sStatusBadge = '<span class="ef-badge ef-badge-warning">Pending Verification</span>';

								sHtml += '<div class="ef-sub-item-box" data-payment-id="' + sub.id + '" style="border:1px solid ' + (isLatest ? '#0D9488' : '#E2E8F0') + '; background:' + (isLatest ? '#F0FDFA' : '#FAFAFA') + '; border-radius:6px; padding:10px 12px;">';
								sHtml += '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">';
								sHtml += '<div><strong style="color:#0F172A;">Version ' + (sub.version || (sIdx + 1)) + '</strong> ' + (isLatest ? '<span class="ef-badge ef-badge-primary" style="font-size:10px; margin-left:4px;">Latest / Active</span>' : '<span class="ef-badge ef-badge-neutral" style="font-size:10px; margin-left:4px;">Archived</span>') + '</div>';
								sHtml += '<div>' + sStatusBadge + '</div>';
								sHtml += '</div>';
								sHtml += '<div style="font-size:12px; color:#475569; display:grid; grid-template-columns:1fr 1fr; gap:4px; margin-bottom:6px;">';
								sHtml += '<div><span>TID:</span> <code class="ef-tid">' + self.escapeHtml(sub.transaction_id || 'N/A') + '</code></div>';
								sHtml += '<div><span>Date:</span> ' + self.escapeHtml(sub.formatted_date || sub.payment_date || '-') + '</div>';
								sHtml += '<div><span>Amount:</span> <strong>' + curr + ' ' + Number(sub.amount || 0).toLocaleString() + '</strong></div>';
								sHtml += '<div><span>Method:</span> ' + self.escapeHtml(sub.payment_method || '-') + '</div>';
								sHtml += '</div>';

								if (sub.rejection_reason) {
									sHtml += '<div style="background:#FEF2F2; border:1px solid #FECACA; border-radius:4px; padding:6px 8px; margin-bottom:6px; font-size:12px; color:#991B1B;">';
									sHtml += '<strong>Rejection Reason:</strong> ' + self.escapeHtml(sub.rejection_reason);
									sHtml += '</div>';
								}

								if (sub.preview_url) {
									sHtml += '<button type="button" class="button button-small ef-switch-proof-btn" data-payment-id="' + sub.id + '" data-url="' + self.escapeHtml(sub.preview_url) + '" data-download-url="' + self.escapeHtml(sub.download_url || sub.preview_url) + '" data-filetype="' + self.escapeHtml(sub.file_type || '') + '" data-tid="' + self.escapeHtml(sub.transaction_id || '') + '" data-version="' + (sub.version || (sIdx + 1)) + '" data-status="' + self.escapeHtml(sub.status || '') + '" data-amount="' + self.escapeHtml(sub.amount || '') + '" data-date="' + self.escapeHtml(sub.formatted_date || sub.payment_date || '') + '" data-method="' + self.escapeHtml(sub.payment_method || '') + '" style="font-size:11px; padding:2px 8px; height:auto; cursor:pointer;">';
									sHtml += '👁️ View This Receipt Proof' + (isLatest ? ' (Active)' : '');
									sHtml += '</button>';
								}

								sHtml += '</div>';
							}
							sHtml += '</div>';
							$('#ef-pv-submissions-container').html(sHtml);
						} else {
							$('#ef-pv-submissions-container').html('<p class="ef-text-muted ef-text-xs">Single submission on record.</p>');
						}

						// Proof document and download link
						var proofUrl = d.preview_url || fileUrl;
						var downloadUrl = d.download_url || proofUrl;
						if (downloadUrl) {
							$('#ef-btn-download-receipt').attr('href', downloadUrl).show();
						} else {
							$('#ef-btn-download-receipt').hide();
						}
						self.renderProofFile(proofUrl, d.file_type || fileType);
					}
				});
			}

			$('#ef-payment-preview-modal').addClass('active').attr('aria-hidden', 'false').css('display', 'flex');
		},

		resetTransform: function () {
			this.scale = 1;
			this.rotation = 0;
			this.translateX = 0;
			this.translateY = 0;
			this.applyTransform();
		},

		fitToScreen: function () {
			var $viewport = $('#ef-screenshot-viewport');
			var $img = $('#ef-preview-img');
			if (!$img.length || !$img.is(':visible')) return;

			var vw = $viewport.width() || 600;
			var vh = $viewport.height() || 500;
			var nw = $img[0].naturalWidth || $img.width() || 600;
			var nh = $img[0].naturalHeight || $img.height() || 500;

			var ratio = Math.min((vw - 40) / nw, (vh - 40) / nh, 1.0);
			this.scale = Math.max(0.4, ratio);
			this.translateX = 0;
			this.translateY = 0;
			this.applyTransform();
		},

		applyTransform: function () {
			var transformStr = 'translate(' + this.translateX + 'px, ' + this.translateY + 'px) scale(' + this.scale + ') rotate(' + this.rotation + 'deg)';
			$('.ef-image-canvas-wrap').css({
				'transform': transformStr,
				'transition': this.isDragging ? 'none' : 'transform 0.15s ease-out'
			});
		},

		executeAction: function (actionName, postData) {
			var self = this;
			postData.action = actionName;
			postData.nonce = self.getStaffNonce();

			var $btn = $('#ef-btn-verify-payment').prop('disabled', true);

			$.post(self.getAjaxUrl(), postData, function (response) {
				$btn.prop('disabled', false);
				if (response.success) {
					alert(response.data.message || 'Operation succeeded.');
					$('#ef-payment-preview-modal').removeClass('active');
					window.location.reload();
				} else {
					alert(response.data.message || 'Action failed.');
				}
			}).fail(function () {
				$btn.prop('disabled', false);
				alert('Server communication error.');
			});
		}
	};

	$(document).ready(function () {
		PaymentPreview.init();
	});

	window.EventFlowPaymentPreview = PaymentPreview;

})(jQuery);
