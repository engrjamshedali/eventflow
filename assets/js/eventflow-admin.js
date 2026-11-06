/**
 * EventFlow WordPress Admin Integration Scripts
 */

(function ($) {
	'use strict';

	$(document).ready(function () {
		// 1. WordPress Media Uploader Frame
		var mediaFrame;
		$(document).on('click', '.ef-media-upload-btn', function (e) {
			e.preventDefault();
			var targetSelector = $(this).data('target');
			var $targetInput = $(targetSelector);

			if (mediaFrame) {
				mediaFrame.open();
				return;
			}

			mediaFrame = wp.media({
				title: window.eventflow_admin_vars.i18n.select_image || 'Select Image',
				button: {
					text: window.eventflow_admin_vars.i18n.choose_image || 'Use Image'
				},
				multiple: false
			});

			mediaFrame.on('select', function () {
				var attachment = mediaFrame.state().get('selection').first().toJSON();
				$targetInput.val(attachment.url).trigger('change');
			});

			mediaFrame.open();
		});

		// 2. Send Test Email
		$('#ef-send-test-email-btn').on('click', function () {
			var $btn = $(this);
			var targetEmail = $('#ef-test-email-input').val();
			var templateKey = $btn.data('template-key') || 'welcome_email';

			if (!targetEmail) {
				alert('Please enter an email address for testing.');
				return;
			}

			$btn.prop('disabled', true).text('Sending...');

			$.post(window.eventflow_admin_vars.ajax_url, {
				action: 'eventflow_send_test_email',
				target_email: targetEmail,
				template_key: templateKey,
				nonce: window.eventflow_admin_vars.staff_nonce
			}, function (res) {
				$btn.prop('disabled', false).text('Send Test Email');
				if (res.success) {
					alert(res.data.message);
				} else {
					alert(res.data.message || 'Dispatch failed.');
				}
			}).fail(function () {
				$btn.prop('disabled', false).text('Send Test Email');
				alert('Server communication error.');
			});
		});

		// 3. Admin Event Form Submit via AJAX
		$('#ef-admin-event-form').on('submit', function (e) {
			e.preventDefault();
			var $form = $(this);
			var postData = $form.serializeArray();
			postData.push({ name: 'action', value: 'eventflow_save_event' });
			postData.push({ name: 'nonce', value: window.eventflow_admin_vars.staff_nonce });

			$.post(window.eventflow_admin_vars.ajax_url, postData, function (res) {
				if (res.success) {
					alert(res.data.message);
					window.location.href = 'admin.php?page=eventflow-events';
				} else {
					alert(res.data.message || 'Failed to save event.');
				}
			});
		});

		// 4. Admin Activity Form Submit via AJAX
		$('#ef-admin-activity-form').on('submit', function (e) {
			e.preventDefault();
			var $form = $(this);
			var postData = $form.serializeArray();
			postData.push({ name: 'action', value: 'eventflow_save_activity' });
			postData.push({ name: 'nonce', value: window.eventflow_admin_vars.staff_nonce });

			$.post(window.eventflow_admin_vars.ajax_url, postData, function (res) {
				if (res.success) {
					alert(res.data.message);
					window.location.href = 'admin.php?page=eventflow-activities';
				} else {
					alert(res.data.message || 'Failed to save activity.');
				}
			});
		});

		// 5. Admin Staff Provision Form Submit via AJAX
		$('#ef-admin-staff-form').on('submit', function (e) {
			e.preventDefault();
			var $form = $(this);
			var postData = $form.serializeArray();
			postData.push({ name: 'action', value: 'eventflow_save_staff' });
			postData.push({ name: 'nonce', value: window.eventflow_admin_vars.staff_nonce });

			$.post(window.eventflow_admin_vars.ajax_url, postData, function (res) {
				if (res.success) {
					alert(res.data.message);
					window.location.reload();
				} else {
					alert(res.data.message || 'Failed to save staff account.');
				}
			});
		});

		// 6. Admin Dashboard Charts Init & Resize Handling
		function renderAdminCharts() {
			if (typeof window.EventFlowChart === 'undefined') return;

			var $daily = $('#ef-admin-daily-chart');
			if ($daily.length) {
				var dData = $daily.data('chart');
				if (dData && dData.labels) {
					var c1 = document.getElementById('ef-admin-daily-canvas');
					if (c1) window.EventFlowChart.drawLineChart(c1, dData.labels, dData.data, '#4F46E5');
				}
			}

			var $act = $('#ef-admin-activity-chart');
			if ($act.length) {
				var aData = $act.data('chart');
				if (aData && aData.labels) {
					var c2 = document.getElementById('ef-admin-activity-canvas');
					if (c2) window.EventFlowChart.drawBarChart(c2, aData.labels, aData.data, '#06B6D4');
				}
			}
		}

		renderAdminCharts();

		var resizeTimer;
		$(window).on('resize', function () {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(renderAdminCharts, 120);
		});

		$(document).on('click', '#collapse-menu', function () {
			setTimeout(renderAdminCharts, 220);
		});

		// 7. Interactive Shortcode Click-to-Copy
		$(document).on('click', '.ef-copy-shortcode', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var text = $btn.data('shortcode') || $btn.text().trim();
			if (!text) return;

			var copiedMsg = (window.eventflow_admin_vars && window.eventflow_admin_vars.i18n && window.eventflow_admin_vars.i18n.copied) || 'Copied!';
			var $textSpan = $btn.find('.ef-shortcode-text');
			var originalHtml = $btn.html();

			var triggerFeedback = function () {
				$btn.addClass('ef-copied');
				if ($textSpan.length) {
					$textSpan.text(copiedMsg);
				}
				setTimeout(function () {
					$btn.removeClass('ef-copied').html(originalHtml);
				}, 1600);
			};

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(triggerFeedback);
			} else {
				var $temp = $('<input>');
				$('body').append($temp);
				$temp.val(text).select();
				document.execCommand('copy');
				$temp.remove();
				triggerFeedback();
			}
		});

		// 8. Global Modal Dismissal
		$(document).on('click', '.ef-modal-close, [data-dismiss="modal"]', function (e) {
			e.preventDefault();
			$(this).closest('.ef-modal').hide().removeClass('active').attr('aria-hidden', 'true');
		});

		$(document).on('click', '.ef-modal-backdrop', function () {
			$(this).closest('.ef-modal').hide().removeClass('active').attr('aria-hidden', 'true');
		});

		// 9. SMTP Provider Presets Click-to-Fill
		$(document).on('click', '.ef-preset-btn', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var host = $btn.data('host');
			var port = $btn.data('port');
			var enc  = $btn.data('enc');
			var auth = $btn.data('auth');
			var note = $btn.data('notice');

			$('#ef_smtp_host').val(host);
			$('#ef_smtp_port').val(port);
			$('#ef_smtp_encryption').val(enc);
			$('#ef_smtp_auth').val(auth);

			if (note) {
				$('#ef-preset-notice').html('<strong>Provider Note:</strong> ' + note).slideDown(150);
			}
		});

		// 10. Live SMTP Socket Connection & Authentication Probe
		$('#ef-probe-smtp-btn').on('click', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $console = $('#ef-diagnostic-console');

			var host       = $('#ef_smtp_host').val();
			var port       = $('#ef_smtp_port').val();
			var encryption = $('#ef_smtp_encryption').val();
			var auth       = $('#ef_smtp_auth').val();
			var username   = $('#ef_smtp_username').val();
			var password   = $('#ef_smtp_password').val();
			var timeout    = $('#ef_smtp_timeout').val() || 10;

			if (!host) {
				alert('Please enter an SMTP Host first.');
				return;
			}

			$btn.prop('disabled', true).text('Testing Socket...');
			$console.html('<div style="color: #38BDF8;">// [INIT] Initiating live socket probe to ' + host + ':' + port + ' (' + encryption.toUpperCase() + ')...</div>');

			$.post(window.eventflow_admin_vars.ajax_url, {
				action: 'eventflow_smtp_test_connection',
				host: host,
				port: port,
				encryption: encryption,
				auth: auth,
				username: username,
				password: password,
				timeout: timeout,
				nonce: window.eventflow_admin_vars.staff_nonce
			}, function (res) {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-dashboard" style="vertical-align: middle;"></span> Test SMTP Connection');

				var data = res.data;
				if (data.steps && data.steps.length) {
					$.each(data.steps, function (i, s) {
						var color = s.status === 'pass' ? '#4ADE80' : '#F87171';
						var mark  = s.status === 'pass' ? '✓' : '✗';
						$console.append('<div style="color: ' + color + '; margin-top: 4px;">[' + mark + '] ' + s.step + ': ' + s.message + '</div>');
					});
				}

				if (res.success) {
					$console.append('<div style="color: #4ADE80; font-weight: bold; margin-top: 10px;">★ SUCCESS: ' + data.message + '</div>');
				} else {
					$console.append('<div style="color: #F87171; font-weight: bold; margin-top: 10px;">✘ ERROR: ' + (data.message || 'Probe failed') + '</div>');
					if (data.guidance) {
						$console.append('<div style="color: #FBBF24; margin-top: 6px; padding: 6px; border: 1px dashed #F59E0B; border-radius: 4px;">ℹ ADVISORY: ' + data.guidance + '</div>');
					}
				}
				$console.scrollTop($console[0].scrollHeight);
			}).fail(function () {
				$btn.prop('disabled', false).text('Test SMTP Connection');
				$console.append('<div style="color: #F87171; margin-top: 8px;">✘ Server error communicating with admin AJAX handler.</div>');
			});
		});

		$('#ef-clear-console-btn').on('click', function () {
			$('#ef-diagnostic-console').html('<div class="ef-console-line ef-text-muted">// Ready for diagnostics. Click "Test SMTP Connection" to initiate socket probe.</div>');
		});

		// 11. Test Email Modal Triggering & Dispatch
		$('#ef-open-quick-test-btn').on('click', function (e) {
			e.preventDefault();
			$('#ef-modal-test-result').hide().empty();
			$('#ef-test-email-modal').show().addClass('active').attr('aria-hidden', 'false');
		});

		$('#ef-send-template-test-btn').on('click', function (e) {
			e.preventDefault();
			var key = $(this).data('template-key') || 'welcome_email';
			$('#ef-modal-test-template').val(key);
			$('#ef-modal-test-result').hide().empty();
			$('#ef-test-email-modal').show().addClass('active').attr('aria-hidden', 'false');
		});

		$('#ef-modal-execute-test-btn').on('click', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var targetEmail = $('#ef-modal-test-recipient').val();
			var templateKey = $('#ef-modal-test-template').val();
			var $resBox     = $('#ef-modal-test-result');

			if (!targetEmail) {
				alert('Please enter a recipient email address.');
				return;
			}

			$btn.prop('disabled', true).text('Dispatching...');
			$resBox.show().html('<div style="color: #64748B;">Routing test notification through SMTP server...</div>');

			$.post(window.eventflow_admin_vars.ajax_url, {
				action: 'eventflow_send_test_email',
				target_email: targetEmail,
				template_key: templateKey,
				nonce: window.eventflow_admin_vars.staff_nonce
			}, function (res) {
				$btn.prop('disabled', false).text('Dispatch Test Email');
				if (res.success) {
					$resBox.css({ 'background': '#ECFDF5', 'border': '1px solid #A7F3D0', 'color': '#065F46' })
						.html('<strong>✓ Delivery Confirmed:</strong> ' + res.data.message);
				} else {
					$resBox.css({ 'background': '#FEF2F2', 'border': '1px solid #FECACA', 'color': '#991B1B' })
						.html('<strong>✘ Delivery Failed:</strong> ' + (res.data.message || 'Error sending test email.'));
				}
			}).fail(function () {
				$btn.prop('disabled', false).text('Dispatch Test Email');
				$resBox.css({ 'background': '#FEF2F2', 'border': '1px solid #FECACA', 'color': '#991B1B' })
					.html('<strong>✘ Communication Error:</strong> Unable to reach WordPress server.');
			});
		});

		// 12. Email Queue Controls
		$('#ef-process-queue-now-btn').on('click', function (e) {
			e.preventDefault();
			var $btn = $(this);
			$btn.prop('disabled', true).text('Processing...');

			$.post(window.eventflow_admin_vars.ajax_url, {
				action: 'eventflow_process_email_queue',
				nonce: window.eventflow_admin_vars.staff_nonce
			}, function (res) {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-update" style="vertical-align: middle;"></span> Process Queue Now');
				if (res.success) {
					alert(res.data.message);
					window.location.reload();
				} else {
					alert(res.data.message || 'Error processing queue.');
				}
			});
		});

		$(document).on('click', '.ef-retry-queue-btn', function (e) {
			e.preventDefault();
			var queueId = $(this).data('id');
			$.post(window.eventflow_admin_vars.ajax_url, {
				action: 'eventflow_retry_queue_item',
				queue_id: queueId,
				nonce: window.eventflow_admin_vars.staff_nonce
			}, function (res) {
				if (res.success) {
					window.location.reload();
				} else {
					alert(res.data.message || 'Failed to retry item.');
				}
			});
		});

		$(document).on('click', '.ef-cancel-queue-btn', function (e) {
			e.preventDefault();
			if (!confirm('Cancel this queued email notification?')) return;
			var queueId = $(this).data('id');
			$.post(window.eventflow_admin_vars.ajax_url, {
				action: 'eventflow_cancel_queue_item',
				queue_id: queueId,
				nonce: window.eventflow_admin_vars.staff_nonce
			}, function (res) {
				if (res.success) {
					window.location.reload();
				}
			});
		});

		$('#ef-clear-queue-btn').on('click', function (e) {
			e.preventDefault();
			if (!confirm('Purge all completed/sent and cancelled items from the queue?')) return;
			$.post(window.eventflow_admin_vars.ajax_url, {
				action: 'eventflow_clear_completed_queue',
				nonce: window.eventflow_admin_vars.staff_nonce
			}, function (res) {
				if (res.success) {
					window.location.reload();
				}
			});
		});

		// 13. Email Logs Actions: View Full Email in Modal, Retry, Delete
		$(document).on('click', '.ef-view-email-log-btn', function (e) {
			e.preventDefault();
			var logId = $(this).data('id');
			$.get(window.eventflow_admin_vars.ajax_url, {
				action: 'eventflow_get_email_log_preview',
				log_id: logId,
				nonce: window.eventflow_admin_vars.staff_nonce
			}, function (res) {
				if (res.success) {
					var l = res.data;
					$('#ef-log-modal-to').text((l.recipient_name ? l.recipient_name + ' ' : '') + '<' + l.recipient_email + '>');
					$('#ef-log-modal-subject').text(l.subject);
					$('#ef-log-modal-key').text(l.template_key);
					$('#ef-log-modal-date').text(l.sent_at);
					$('#ef-log-modal-status').text(l.status.toUpperCase());
					$('#ef-log-modal-resp').text(l.provider_response || l.error_message || 'None');
					$('#ef-log-modal-body').html(l.body || '<p><em>No HTML body recorded for this log entry.</em></p>');
					$('#ef-view-log-modal').show().addClass('active').attr('aria-hidden', 'false');
				} else {
					alert(res.data.message || 'Failed to load log.');
				}
			});
		});

		$(document).on('click', '.ef-retry-email-log-btn', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var logId = $btn.data('id');
			$btn.prop('disabled', true).text('Retrying...');
			$.post(window.eventflow_admin_vars.ajax_url, {
				action: 'eventflow_retry_email_log',
				log_id: logId,
				nonce: window.eventflow_admin_vars.staff_nonce
			}, function (res) {
				$btn.prop('disabled', false).text('Retry');
				if (res.success) {
					alert(res.data.message);
					window.location.reload();
				} else {
					alert(res.data.message || 'Retry failed.');
				}
			});
		});

		$(document).on('click', '.ef-delete-email-log-btn', function (e) {
			e.preventDefault();
			if (!confirm('Delete this email log record permanently?')) return;
			var $row = $(this).closest('tr');
			var logId = $(this).data('id');
			$.post(window.eventflow_admin_vars.ajax_url, {
				action: 'eventflow_delete_email_log',
				log_id: logId,
				nonce: window.eventflow_admin_vars.staff_nonce
			}, function (res) {
				if (res.success) {
					$row.fadeOut(200, function () { $(this).remove(); });
				}
			});
		});

		// 14. Template Placeholders Tag Insertion
		$(document).on('click', '.ef-insert-tag-btn', function (e) {
			e.preventDefault();
			var tag = $(this).data('tag');

			// Try to insert into TinyMCE if active
			if (typeof tinymce !== 'undefined' && tinymce.get('template_body') && !tinymce.get('template_body').isHidden()) {
				tinymce.get('template_body').insertContent(tag);
			} else {
				var $textarea = $('#template_body');
				if ($textarea.length) {
					var pos = $textarea.prop('selectionStart') || 0;
					var val = $textarea.val();
					$textarea.val(val.substring(0, pos) + tag + val.substring(pos));
				}
			}
		});

		// 15. Live Template Preview Modal
		$('#ef-preview-template-btn').on('click', function (e) {
			e.preventDefault();
			var subject = $('#ef_template_subject').val();
			var body = '';

			if (typeof tinymce !== 'undefined' && tinymce.get('template_body') && !tinymce.get('template_body').isHidden()) {
				body = tinymce.get('template_body').getContent();
			} else {
				body = $('#template_body').val();
			}

			$.post(window.eventflow_admin_vars.ajax_url, {
				action: 'eventflow_preview_email_template',
				subject: subject,
				body: body,
				nonce: window.eventflow_admin_vars.staff_nonce
			}, function (res) {
				if (res.success) {
					$('#ef-preview-subject-display').text(res.data.subject);
					$('#ef-preview-html-frame').html(res.data.content);
					$('#ef-preview-tpl-modal').show().addClass('active').attr('aria-hidden', 'false');
				}
			});
		});

		// 16. Reset Template
		$('#ef-reset-template-btn').on('click', function (e) {
			e.preventDefault();
			if (!confirm('Are you sure you want to restore this template to its default subject and copy? Any custom edits will be lost.')) return;
			var tplId = $(this).data('id');
			$.post(window.eventflow_admin_vars.ajax_url, {
				action: 'eventflow_reset_email_template',
				template_id: tplId,
				nonce: window.eventflow_admin_vars.staff_nonce
			}, function (res) {
				if (res.success) {
					alert(res.data.message);
					window.location.reload();
				} else {
					alert(res.data.message || 'Failed to reset template.');
				}
			});
		});

		// 17. Trigger Deadline Check
		$('#ef-trigger-deadline-btn').on('click', function (e) {
			e.preventDefault();
			var $btn = $(this);
			$btn.prop('disabled', true).text('Checking deadlines...');
			$.post(window.eventflow_admin_vars.ajax_url, {
				action: 'eventflow_trigger_deadline_check',
				nonce: window.eventflow_admin_vars.staff_nonce
			}, function (res) {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-clock" style="vertical-align: middle;"></span> Trigger Deadline Reminder Check Now');
				if (res.success) {
					alert(res.data.message);
				}
			});
		});
	});

})(jQuery);

