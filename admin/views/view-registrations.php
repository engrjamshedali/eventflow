<?php
/**
 * EventFlow Admin View: Upgraded Registrations & Applications Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$filter_search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$filter_activity = isset( $_GET['activity_id'] ) ? absint( $_GET['activity_id'] ) : 0;
$filter_type     = isset( $_GET['participation_type'] ) ? sanitize_text_field( wp_unslash( $_GET['participation_type'] ) ) : '';
$filter_pay      = isset( $_GET['pay_status'] ) ? sanitize_text_field( wp_unslash( $_GET['pay_status'] ) ) : '';
$filter_app      = isset( $_GET['app_status'] ) ? sanitize_text_field( wp_unslash( $_GET['app_status'] ) ) : '';
$page            = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

$reg_data = EventFlow_Registration_Manager::get_all_registrations(
	array(
		'search'             => $filter_search,
		'activity_id'        => $filter_activity,
		'participation_type' => $filter_type,
		'payment_status'     => $filter_pay,
		'application_status' => $filter_app,
		'page'               => $page,
		'per_page'           => 20,
	)
);

$activities = EventFlow_Activity_Manager::get_activities( array( 'status' => 'all' ) );
$event = EventFlow_Event_Manager::get_active_event();
$currency = $event ? esc_html( $event->currency ) : 'PKR';
?>

<div class="wrap ef-admin-wrap">
	<div class="ef-flex-between" style="margin-bottom: 20px;">
		<div>
			<h1 class="wp-heading-inline" style="margin-bottom: 4px;"><?php esc_html_e( 'Registrations & Team Management', 'eventflow' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Review participant submissions, team rosters, fee calculations, and verify payment receipts.', 'eventflow' ); ?></p>
		</div>
		<div class="ef-actions">
			<a href="<?php echo esc_url( add_query_arg( array( 'eventflow_action' => 'export', 'type' => 'registrations', 'format' => 'csv', '_wpnonce' => wp_create_nonce( 'eventflow_export' ) ), home_url() ) ); ?>" class="button button-secondary">
				<span class="dashicons dashicons-download" style="vertical-align: middle; margin-top:-2px;"></span> <?php esc_html_e( 'Export CSV', 'eventflow' ); ?>
			</a>
		</div>
	</div>
	<hr class="wp-header-end">

	<div class="ef-card" style="margin-top: 16px;">
		<!-- Filters Form -->
		<form method="get" style="margin-bottom: 20px;">
			<input type="hidden" name="page" value="eventflow-registrations">
			<div class="ef-filter-row" style="display: flex; gap: 10px; flex-wrap: wrap;">
				<div class="ef-filter-item" style="flex: 1; min-width: 220px;">
					<input type="text" name="s" class="widefat" placeholder="<?php esc_attr_e( 'Search ID, Team, Leader, Email, WhatsApp...', 'eventflow' ); ?>" value="<?php echo esc_attr( $filter_search ); ?>">
				</div>

				<div class="ef-filter-item">
					<select name="activity_id" class="widefat">
						<option value=""><?php esc_html_e( 'All Competitions', 'eventflow' ); ?></option>
						<?php foreach ( $activities as $a ) : ?>
							<option value="<?php echo esc_attr( $a->id ); ?>" <?php selected( $filter_activity, $a->id ); ?>><?php echo esc_html( $a->title ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="ef-filter-item">
					<select name="participation_type" class="widefat">
						<option value=""><?php esc_html_e( 'All Types (Solo & Team)', 'eventflow' ); ?></option>
						<option value="solo" <?php selected( $filter_type, 'solo' ); ?>><?php esc_html_e( 'Solo Only', 'eventflow' ); ?></option>
						<option value="team" <?php selected( $filter_type, 'team' ); ?>><?php esc_html_e( 'Team Only', 'eventflow' ); ?></option>
					</select>
				</div>

				<div class="ef-filter-item">
					<select name="pay_status" class="widefat">
						<option value=""><?php esc_html_e( 'All Payment Statuses', 'eventflow' ); ?></option>
						<option value="pending" <?php selected( $filter_pay, 'pending' ); ?>><?php esc_html_e( 'Pending Payment', 'eventflow' ); ?></option>
						<option value="under_verification" <?php selected( $filter_pay, 'under_verification' ); ?>><?php esc_html_e( 'Pending Verification', 'eventflow' ); ?></option>
						<option value="verified" <?php selected( $filter_pay, 'verified' ); ?>><?php esc_html_e( 'Verified', 'eventflow' ); ?></option>
						<option value="rejected" <?php selected( $filter_pay, 'rejected' ); ?>><?php esc_html_e( 'Payment Rejected', 'eventflow' ); ?></option>
						<option value="reupload_requested" <?php selected( $filter_pay, 'reupload_requested' ); ?>><?php esc_html_e( 'Re-upload Requested', 'eventflow' ); ?></option>
					</select>
				</div>

				<div class="ef-filter-item">
					<select name="app_status" class="widefat">
						<option value=""><?php esc_html_e( 'All Registration Statuses', 'eventflow' ); ?></option>
						<option value="submitted" <?php selected( $filter_app, 'submitted' ); ?>><?php esc_html_e( 'Submitted', 'eventflow' ); ?></option>
						<option value="under_review" <?php selected( $filter_app, 'under_review' ); ?>><?php esc_html_e( 'Under Review', 'eventflow' ); ?></option>
						<option value="confirmed" <?php selected( $filter_app, 'confirmed' ); ?>><?php esc_html_e( 'Confirmed / Approved', 'eventflow' ); ?></option>
						<option value="rejected" <?php selected( $filter_app, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'eventflow' ); ?></option>
						<option value="action_required" <?php selected( $filter_app, 'action_required' ); ?>><?php esc_html_e( 'Action Required', 'eventflow' ); ?></option>
					</select>
				</div>

				<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'eventflow' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=eventflow-registrations' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'eventflow' ); ?></a>
			</div>
		</form>

		<style type="text/css">
			.ef-registrations-table tbody tr.ef-clickable-row {
				cursor: pointer;
				transition: background-color 0.15s ease-in-out;
			}
			.ef-registrations-table tbody tr.ef-clickable-row:hover {
				background-color: #f0fdfa !important;
			}
			.ef-clickable-row:hover .ef-reg-id-link,
			.ef-clickable-row:hover .ef-team-name-link {
				color: #0f766e !important;
				text-decoration: underline !important;
			}
			.ef-clickable-row:hover .ef-leader-name-link {
				color: #0284c7 !important;
				text-decoration: underline !important;
			}
			.ef-members-pill-btn {
				display: inline-block;
				transition: transform 0.15s ease, background 0.15s ease;
			}
			.ef-clickable-row:hover .ef-members-pill-btn {
				transform: scale(1.1);
				background: #0d9488 !important;
				color: #ffffff !important;
			}
			.ef-badge-clickable {
				cursor: pointer;
			}
		</style>

		<!-- Main Registrations Table -->
		<table class="widefat striped ef-registrations-table" style="border: 1px solid #c3c4c7; box-shadow: none;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Reg ID', 'eventflow' ); ?></th>
					<th><?php esc_html_e( 'Date', 'eventflow' ); ?></th>
					<th><?php esc_html_e( 'Competition', 'eventflow' ); ?></th>
					<th><?php esc_html_e( 'Solo / Team', 'eventflow' ); ?></th>
					<th><?php esc_html_e( 'Team Name', 'eventflow' ); ?></th>
					<th><?php esc_html_e( 'Team Leader', 'eventflow' ); ?></th>
					<th><?php esc_html_e( 'Email', 'eventflow' ); ?></th>
					<th><?php esc_html_e( 'WhatsApp', 'eventflow' ); ?></th>
					<th><?php esc_html_e( 'Members', 'eventflow' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'eventflow' ); ?></th>
					<th><?php esc_html_e( 'Payment Status', 'eventflow' ); ?></th>
					<th><?php esc_html_e( 'Reg Status', 'eventflow' ); ?></th>
					<th style="text-align: right;"><?php esc_html_e( 'Actions', 'eventflow' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $reg_data['items'] ) ) : ?>
					<tr><td colspan="13" style="text-align:center; padding: 30px;"><?php esc_html_e( 'No registration records found.', 'eventflow' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $reg_data['items'] as $item ) : ?>
						<?php
						$is_team = 'team' === $item->participation_type;
						$display_team = $item->team_name ? $item->team_name : '—';
						$clean_phone = preg_replace( '/[^0-9]/', '', $item->leader_phone );
						?>
						<tr id="ef-reg-row-<?php echo esc_attr( $item->id ); ?>" class="ef-clickable-row" data-reg-id="<?php echo esc_attr( $item->reg_id ); ?>" title="<?php esc_attr_e( 'Click anywhere on this row to view full team & participant details', 'eventflow' ); ?>">
							<td>
								<a href="#" class="ef-manage-reg-link ef-reg-id-link" data-reg-id="<?php echo esc_attr( $item->reg_id ); ?>" style="text-decoration:none; display:inline-flex; align-items:center; gap:4px;" title="<?php esc_attr_e( 'Click to view registration details', 'eventflow' ); ?>">
									<strong class="ef-monospace" style="color: #0f172a; font-size: 13px;"><?php echo esc_html( $item->reg_id ); ?></strong>
								</a>
							</td>
							<td><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $item->created_at ) ) ); ?></td>
							<td><strong><?php echo esc_html( $item->activity_title ); ?></strong></td>
							<td>
								<?php if ( $is_team ) : ?>
									<span class="ef-badge ef-badge-info ef-badge-clickable ef-manage-reg-link" data-reg-id="<?php echo esc_attr( $item->reg_id ); ?>" style="background:#ccfbf1; color:#0f766e; font-weight:600; padding:2px 8px; border-radius:4px; font-size:11px;" title="<?php esc_attr_e( 'Team Registration - Click to view roster', 'eventflow' ); ?>"><?php esc_html_e( 'TEAM', 'eventflow' ); ?></span>
								<?php else : ?>
									<span class="ef-badge ef-badge-neutral ef-badge-clickable ef-manage-reg-link" data-reg-id="<?php echo esc_attr( $item->reg_id ); ?>" style="background:#f1f5f9; color:#475569; font-weight:600; padding:2px 8px; border-radius:4px; font-size:11px;" title="<?php esc_attr_e( 'Solo Individual - Click to view details', 'eventflow' ); ?>"><?php esc_html_e( 'SOLO', 'eventflow' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<a href="#" class="ef-manage-reg-link ef-team-name-link" data-reg-id="<?php echo esc_attr( $item->reg_id ); ?>" style="text-decoration:none; font-weight:700; color:#0f172a;" title="<?php esc_attr_e( 'Click to view team details & members', 'eventflow' ); ?>">
									<?php echo esc_html( $display_team ); ?>
								</a>
							</td>
							<td>
								<a href="#" class="ef-manage-reg-link ef-leader-name-link" data-reg-id="<?php echo esc_attr( $item->reg_id ); ?>" style="text-decoration:none; color:inherit;" title="<?php esc_attr_e( 'Click to inspect candidate/leader details', 'eventflow' ); ?>">
									<strong><?php echo esc_html( $item->leader_name ); ?></strong>
								</a>
							</td>
							<td>
								<a href="mailto:<?php echo esc_attr( $item->leader_email ); ?>" onclick="event.stopPropagation();" style="color: #2563eb; text-decoration: none;" title="<?php esc_attr_e( 'Send Email', 'eventflow' ); ?>">
									<?php echo esc_html( $item->leader_email ); ?>
								</a>
							</td>
							<td>
								<?php if ( ! empty( $clean_phone ) ) : ?>
									<a href="https://wa.me/<?php echo esc_attr( $clean_phone ); ?>" target="_blank" onclick="event.stopPropagation();" style="color:#059669; text-decoration:none; display:inline-flex; align-items:center; gap:3px;" title="<?php esc_attr_e( 'Open WhatsApp Chat', 'eventflow' ); ?>">
										<span class="ef-monospace"><?php echo esc_html( $item->leader_phone ); ?></span>
									</a>
								<?php else : ?>
									<span class="ef-monospace"><?php echo esc_html( $item->leader_phone ); ?></span>
								<?php endif; ?>
							</td>
							<td style="text-align: center;">
								<a href="#" class="ef-manage-reg-link" data-reg-id="<?php echo esc_attr( $item->reg_id ); ?>" style="text-decoration:none;" title="<?php esc_attr_e( 'Click to inspect full members list', 'eventflow' ); ?>">
									<span class="ef-badge ef-members-pill-btn" style="background: #e0e7ff; color: #4338ca; font-weight: 700; padding: 2px 9px; border-radius: 999px; font-size: 11px;">
										<?php echo (int) $item->member_count; ?>
									</span>
								</a>
							</td>
							<td><strong class="ef-fee" style="color: #0d9488; font-weight: 700;"><?php echo sprintf( '%s %s', esc_html( $currency ), number_format( $item->total_fee, 2 ) ); ?></strong></td>
							<td><?php echo EventFlow_Registration_Manager::get_status_badge( $item->payment_status, 'payment' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td><?php echo EventFlow_Registration_Manager::get_status_badge( $item->application_status, 'app' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td style="text-align: right; white-space: nowrap;">
								<button type="button" class="button button-small button-primary ef-manage-reg-btn" 
										data-reg-id="<?php echo esc_attr( $item->reg_id ); ?>"
										title="<?php esc_attr_e( 'Inspect full team roster, edit participant details, and manage status', 'eventflow' ); ?>">
									<span class="dashicons dashicons-admin-users" style="vertical-align: middle; margin-top:-2px;"></span>
									<?php echo $is_team ? esc_html__( 'Team Roster', 'eventflow' ) : esc_html__( 'Candidate Details', 'eventflow' ); ?>
								</button>

								<?php if ( ! empty( $item->preview_url ) ) : ?>
									<button type="button" class="button button-small ef-preview-proof-btn" 
											data-payment-id="<?php echo esc_attr( $item->payment_id ); ?>" 
											data-reg-id="<?php echo esc_attr( $item->reg_id ); ?>" 
											data-activity="<?php echo esc_attr( $item->activity_title ); ?>" 
											data-team="<?php echo esc_attr( $item->team_name ? $item->team_name : $item->leader_name ); ?>" 
											data-method="<?php echo esc_attr( $item->payment_method ); ?>" 
											data-tid="<?php echo esc_attr( $item->transaction_id ); ?>" 
											data-amount="<?php echo esc_attr( sprintf( '%s %s', $currency, number_format( $item->total_fee, 2 ) ) ); ?>" 
											data-date="<?php echo esc_attr( date_i18n( 'M j, Y', strtotime( $item->created_at ) ) ); ?>" 
											data-url="<?php echo esc_url( $item->preview_url ); ?>" 
											data-filetype="<?php echo esc_attr( $item->proof_path ); ?>" 
											data-status="<?php echo esc_attr( $item->payment_status ); ?>"
											onclick="event.stopPropagation();"
											title="<?php esc_attr_e( 'Quick View Payment Receipt', 'eventflow' ); ?>">
										<span class="dashicons dashicons-visibility" style="vertical-align: middle; margin-top:-2px;"></span>
										<?php esc_html_e( 'Receipt', 'eventflow' ); ?>
									</button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<!-- Pagination -->
		<?php if ( $reg_data['pages'] > 1 ) : ?>
			<div class="tablenav" style="margin-top: 16px;">
				<div class="tablenav-pages">
					<span class="displaying-num"><?php printf( esc_html__( '%d registrations', 'eventflow' ), $reg_data['total'] ); ?></span>
					<?php for ( $p = 1; $p <= $reg_data['pages']; $p++ ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'paged', $p ) ); ?>" class="page-numbers <?php echo $p === $reg_data['page'] ? 'current' : ''; ?>"><?php echo esc_html( $p ); ?></a>
					<?php endfor; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<!-- ==========================================================================
     REGISTRATION INSPECTION & PARTICIPANT EDITING MODAL
     ========================================================================== -->
<div class="ef-modal" id="ef-admin-reg-modal" aria-hidden="true" style="display:none; position: fixed; inset: 0; z-index: 100050; align-items: center; justify-content: center;">
	<div class="ef-modal-backdrop" style="position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 1;"></div>
	<div class="ef-modal-dialog" style="position: relative; max-width: 1020px; width: 94%; margin: auto; background: #ffffff; border-radius: 12px; box-shadow: 0 25px 35px -5px rgba(0, 0, 0, 0.25); max-height: 90vh; height: auto; display: flex; flex-direction: column; overflow: hidden; z-index: 10;">
		
		<div class="ef-modal-header" style="padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
			<div>
				<h3 style="margin: 0; font-size: 19px; color: #0f172a; display: flex; align-items: center; gap: 8px;" id="ef-arm-title"><?php esc_html_e( 'Registration Details', 'eventflow' ); ?></h3>
				<span class="ef-text-muted ef-text-xs" id="ef-arm-subtitle" style="font-size: 12px; color: #64748b; margin-top: 3px; display: block;"></span>
			</div>
			<button type="button" class="ef-modal-close" data-dismiss="modal" style="background: none; border: none; font-size: 26px; cursor: pointer; color: #64748b; padding: 4px 8px; border-radius: 6px;" title="<?php esc_attr_e( 'Close', 'eventflow' ); ?>">&times;</button>
		</div>

		<!-- Modal Tab Navigation -->
		<div class="ef-modal-tabs" style="display: flex; gap: 4px; padding: 0 24px; border-bottom: 1px solid #e2e8f0; background: #ffffff;">
			<button type="button" class="ef-tab-btn is-active" data-tab="roster" id="ef-tab-roster-btn" style="padding: 12px 18px; border: none; background: transparent; border-bottom: 3px solid #0d9488; font-weight: 700; color: #0f766e; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
				<span class="dashicons dashicons-groups" style="font-size: 18px; width: 18px; height: 18px;"></span>
				<span id="ef-tab-roster-label"><?php esc_html_e( 'Team Members & Roster', 'eventflow' ); ?></span>
			</button>
			<button type="button" class="ef-tab-btn" data-tab="payment" id="ef-tab-payment-btn" style="padding: 12px 18px; border: none; background: transparent; border-bottom: 3px solid transparent; font-weight: 600; color: #64748b; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
				<span class="dashicons dashicons-money-alt" style="font-size: 18px; width: 18px; height: 18px;"></span>
				<span><?php esc_html_e( 'Payment Verification', 'eventflow' ); ?></span>
			</button>
			<button type="button" class="ef-tab-btn" data-tab="status" id="ef-tab-status-btn" style="padding: 12px 18px; border: none; background: transparent; border-bottom: 3px solid transparent; font-weight: 600; color: #64748b; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
				<span class="dashicons dashicons-yes-alt" style="font-size: 18px; width: 18px; height: 18px;"></span>
				<span><?php esc_html_e( 'Status & Decision', 'eventflow' ); ?></span>
			</button>
		</div>

		<div class="ef-modal-body" style="padding: 24px; overflow-y: auto; flex: 1;">
			<div id="ef-arm-loading" style="text-align: center; padding: 45px 20px;">
				<span class="spinner is-active" style="float: none; margin: 0 auto; width: 28px; height: 28px;"></span>
				<p style="color: #64748b; margin-top: 12px; font-weight: 500;"><?php esc_html_e( 'Loading registration and participant details...', 'eventflow' ); ?></p>
			</div>

			<div id="ef-arm-content" style="display: none;">
				<!-- TAB 1: ROSTER / PARTICIPANTS -->
				<div class="ef-tab-pane" id="ef-pane-roster">
					<!-- Summary Header Banner -->
					<div id="ef-roster-summary" style="margin-bottom: 22px; padding: 16px 20px; background: #f0fdfa; border: 1.5px solid #99f6e4; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
						<div style="flex: 1; min-width: 280px;">
							<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
								<span id="ef-summary-type-pill" style="background:#0d9488; color:#ffffff; font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; text-transform:uppercase;"></span>
								<strong id="ef-roster-comp-title" style="color: #0f766e; font-size: 16px;"></strong>
							</div>
							<div id="ef-roster-team-title" style="font-size: 14px; color: #1e293b; font-weight: 600;"></div>
							<div id="ef-roster-contacts" style="font-size: 13px; color: #475569; margin-top: 6px; display: flex; gap: 16px; flex-wrap: wrap;"></div>
						</div>
						<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
							<div id="ef-roster-status-chips" style="display: flex; gap: 6px; align-items: center;"></div>
							<span id="ef-roster-count-badge" style="background: #0f766e; color: #ffffff; padding: 5px 14px; border-radius: 999px; font-size: 13px; font-weight: 700;"></span>
						</div>
					</div>

					<!-- Members Cards Container -->
					<div id="ef-roster-members-list">
						<!-- Dynamically populated -->
					</div>

					<!-- Custom Questionnaire / Form Answers (if present) -->
					<div id="ef-roster-custom-fields-box" style="display: none; margin-top: 24px; padding: 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;">
						<h4 style="margin: 0 0 14px 0; font-size: 13px; text-transform: uppercase; color: #0f766e; font-weight: 700; letter-spacing: 0.5px;">
							<span class="dashicons dashicons-clipboard" style="vertical-align: middle; margin-right: 4px;"></span>
							<?php esc_html_e( 'Additional Registration Information', 'eventflow' ); ?>
						</h4>
						<div id="ef-roster-custom-fields-body" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;"></div>
					</div>
				</div>

				<!-- TAB 2: PAYMENT VERIFICATION -->
				<div class="ef-tab-pane" id="ef-pane-payment" style="display: none;">
					<div id="ef-arm-payment-empty" style="display:none; text-align:center; padding: 35px; background: #f8fafc; border-radius: 8px;">
						<p style="color: #64748b; margin:0; font-size: 14px;"><?php esc_html_e( 'No payment proof submitted yet for this registration.', 'eventflow' ); ?></p>
					</div>

					<div id="ef-arm-payment-details">
						<div class="ef-grid ef-grid-2" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 20px;">
							<!-- Screenshot / PDF Preview Box -->
							<div style="background: #0f172a; border-radius: 10px; padding: 14px; text-align: center; min-height: 300px; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative;">
								<img id="ef-arm-proof-img" src="" alt="Proof" style="max-width: 100%; max-height: 420px; border-radius: 4px; box-shadow: 0 6px 16px rgba(0,0,0,0.4); display: none;">
								<iframe id="ef-arm-proof-pdf" src="" style="width: 100%; height: 420px; border: none; display: none;"></iframe>
								<div id="ef-arm-proof-none" style="color: #94a3b8;"><?php esc_html_e( 'No proof preview available', 'eventflow' ); ?></div>
							</div>

							<!-- Details & Verification Panel -->
							<div>
								<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
									<h4 style="margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px;"><?php esc_html_e( 'Payment Transaction Details', 'eventflow' ); ?></h4>
									<p style="margin: 5px 0;"><strong><?php esc_html_e( 'Payment Method:', 'eventflow' ); ?></strong> <span id="ef-arm-pay-method"></span></p>
									<p style="margin: 5px 0;"><strong><?php esc_html_e( 'Transaction ID (TID):', 'eventflow' ); ?></strong> <code id="ef-arm-pay-tid" style="font-weight: 700; color: #0d9488; font-size: 13px;"></code></p>
									<p style="margin: 5px 0;"><strong><?php esc_html_e( 'Amount Paid:', 'eventflow' ); ?></strong> <span id="ef-arm-pay-amount" style="font-weight: 700; color: #0f172a; font-size: 14px;"></span></p>
									<p style="margin: 5px 0;"><strong><?php esc_html_e( 'Verification Status:', 'eventflow' ); ?></strong> <span id="ef-arm-pay-status-badge"></span></p>
								</div>

								<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;">
									<h4 style="margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; color: #0f172a; font-weight: 700; letter-spacing: 0.5px;"><?php esc_html_e( 'Staff Verification Action', 'eventflow' ); ?></h4>
									<div class="ef-form-group" style="margin-bottom: 12px;">
										<label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 12px; color: #334155;"><?php esc_html_e( 'Staff Note / Rejection Reason / Instructions:', 'eventflow' ); ?></label>
										<textarea id="ef-arm-action-notes" class="widefat" rows="2" placeholder="<?php esc_attr_e( 'Optional note for approval, or reason if rejecting/requesting re-upload...', 'eventflow' ); ?>"></textarea>
									</div>

									<div style="display: flex; gap: 8px; flex-direction: column;">
										<button type="button" class="button button-primary" id="ef-arm-btn-approve" style="background:#0d9488; border-color:#0f766e; height: 36px; font-weight: 600;">
											<span class="dashicons dashicons-yes" style="vertical-align: middle; margin-top:-2px;"></span>
											<?php esc_html_e( 'Approve Payment & Confirm Slot', 'eventflow' ); ?>
										</button>
										<div style="display: flex; gap: 8px;">
											<button type="button" class="button button-secondary" id="ef-arm-btn-reupload" style="flex:1;">
												<span class="dashicons dashicons-update" style="vertical-align: middle; margin-top:-2px;"></span>
												<?php esc_html_e( 'Request Re-upload', 'eventflow' ); ?>
											</button>
											<button type="button" class="button button-link-delete" id="ef-arm-btn-reject" style="flex:1; border: 1px solid #fecaca; background: #fef2f2; border-radius: 4px; text-align: center; color: #b91c1c;">
												<span class="dashicons dashicons-no" style="vertical-align: middle; margin-top:-2px;"></span>
												<?php esc_html_e( 'Reject Payment', 'eventflow' ); ?>
											</button>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- TAB 3: STATUS & DECISION -->
				<div class="ef-tab-pane" id="ef-pane-status" style="display: none;">
					<form id="ef-arm-status-form">
						<input type="hidden" id="ef-arm-reg-row-id" name="registration_id" value="0">
						<div style="max-width: 520px; margin: 0 auto; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 22px;">
							<div class="ef-form-group" style="margin-bottom: 16px;">
								<label style="display: block; font-weight: 700; margin-bottom: 6px; color: #1e293b;"><?php esc_html_e( 'Registration / Application Status:', 'eventflow' ); ?></label>
								<select id="ef-arm-status-select" name="status" class="widefat" style="height: 38px;">
									<option value="submitted"><?php esc_html_e( 'Submitted', 'eventflow' ); ?></option>
									<option value="under_review"><?php esc_html_e( 'Under Review', 'eventflow' ); ?></option>
									<option value="confirmed"><?php esc_html_e( 'Confirmed / Approved', 'eventflow' ); ?></option>
									<option value="rejected"><?php esc_html_e( 'Rejected', 'eventflow' ); ?></option>
									<option value="action_required"><?php esc_html_e( 'Action Required', 'eventflow' ); ?></option>
								</select>
							</div>

							<div class="ef-form-group" id="ef-arm-rejection-group" style="margin-bottom: 16px; display: none;">
								<label style="display: block; font-weight: 700; color: #b91c1c; margin-bottom: 6px;"><?php esc_html_e( 'Rejection Reason (Dispatched to Participant):', 'eventflow' ); ?></label>
								<textarea name="rejection_reason" id="ef-arm-rejection-reason" class="widefat" rows="3" placeholder="<?php esc_attr_e( 'Provide the specific reason for rejecting this registration...', 'eventflow' ); ?>"></textarea>
							</div>

							<div class="ef-form-group" style="margin-bottom: 20px;">
								<label style="display: block; font-weight: 700; margin-bottom: 6px; color: #1e293b;"><?php esc_html_e( 'Internal Admin Notes:', 'eventflow' ); ?></label>
								<textarea name="admin_notes" id="ef-arm-admin-notes" class="widefat" rows="3" placeholder="<?php esc_attr_e( 'Internal notes visible only to organizers and staff...', 'eventflow' ); ?>"></textarea>
							</div>

							<button type="submit" class="button button-primary button-large" style="width: 100%; height: 40px; font-weight: 600; background: #0d9488; border-color: #0f766e;">
								<?php esc_html_e( 'Update Registration Status', 'eventflow' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>

		<div class="ef-modal-footer" style="padding: 12px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc; text-align: right;">
			<button type="button" class="button button-secondary ef-modal-close" data-dismiss="modal"><?php esc_html_e( 'Close', 'eventflow' ); ?></button>
		</div>
	</div>
</div>

<!-- ==========================================================================
     INLINE CONTROLLER SCRIPT FOR ADMIN REGISTRATION INSPECTOR
     ========================================================================== -->
<script type="text/javascript">
(function($) {
	'use strict';

	var currentRegData = null;

	$(document).ready(function() {
		// Tab Switching
		$('.ef-tab-btn').on('click', function(e) {
			e.preventDefault();
			var tab = $(this).data('tab');
			$('.ef-tab-btn').css({ 'border-bottom-color': 'transparent', 'color': '#64748b' }).removeClass('is-active');
			$(this).css({ 'border-bottom-color': '#0d9488', 'color': '#0f766e' }).addClass('is-active');
			$('.ef-tab-pane').hide();
			$('#ef-pane-' + tab).show();
		});

		// Modal Dismissal
		$(document).on('click', '.ef-modal-close, [data-dismiss="modal"]', function(e) {
			e.preventDefault();
			$('#ef-admin-reg-modal').removeClass('active').hide().attr('aria-hidden', 'true');
		});

		// Close modal when clicking on the dark backdrop
		$(document).on('click', '#ef-admin-reg-modal .ef-modal-backdrop', function(e) {
			$('#ef-admin-reg-modal').removeClass('active').hide().attr('aria-hidden', 'true');
		});

		// Prevent clicks inside the dialog from bubbling to the backdrop
		$(document).on('click', '#ef-admin-reg-modal .ef-modal-dialog', function(e) {
			e.stopPropagation();
		});

		// Close modal on Escape key press
		$(document).on('keydown', function(e) {
			if (e.key === 'Escape' && $('#ef-admin-reg-modal').is(':visible')) {
				$('#ef-admin-reg-modal').removeClass('active').hide().attr('aria-hidden', 'true');
			}
		});

		// Open Registration Inspector Modal from anywhere: button, cell link, or full row click
		$(document).on('click', '.ef-manage-reg-btn, .ef-manage-reg-link, tr.ef-clickable-row', function(e) {
			// Don't trigger modal if clicking inside direct action links (like email, external WhatsApp, or receipt preview)
			if ($(e.target).closest('a:not(.ef-manage-reg-link), button:not(.ef-manage-reg-btn), input, select, textarea').length > 0) {
				return;
			}
			e.preventDefault();

			var regId = $(this).data('reg-id') || $(this).closest('tr').data('reg-id');
			if (!regId) return;

			openRegistrationModal(regId);
		});

		function openRegistrationModal(regId) {
			$('#ef-arm-title').html('<span class="dashicons dashicons-admin-users" style="font-size:22px; width:22px; height:22px; vertical-align:middle; color:#0d9488;"></span> ' + escapeHtml(regId));
			$('#ef-arm-subtitle').text('Retrieving complete team and member records from database...');
			$('#ef-arm-loading').show();
			$('#ef-arm-content').hide();
			$('#ef-admin-reg-modal').addClass('active').css('display', 'flex').attr('aria-hidden', 'false');

			// Reset to first tab
			$('.ef-tab-btn[data-tab="roster"]').trigger('click');

			$.getJSON(window.eventflow_admin_vars.ajax_url, {
				action: 'eventflow_get_registration_details',
				reg_id: regId,
				nonce: window.eventflow_admin_vars.staff_nonce
			}, function(res) {
				$('#ef-arm-loading').hide();
				if (!res.success || !res.data) {
					alert(res.data ? res.data.message : 'Failed to retrieve registration records.');
					$('#ef-admin-reg-modal').removeClass('active').hide().attr('aria-hidden', 'true');
					return;
				}

				currentRegData = res.data;
				renderRegistrationDetails(currentRegData);
				$('#ef-arm-content').show();
			}).fail(function() {
				$('#ef-arm-loading').hide();
				alert('Server communication error.');
				$('#ef-admin-reg-modal').removeClass('active').hide().attr('aria-hidden', 'true');
			});
		}

		// Render Details into Modal
		function renderRegistrationDetails(d) {
			var isTeam = (d.participation_type === 'team');
			var leaderName = (d.team && d.team.leader_name) ? d.team.leader_name : (d.leader_name || (d.user ? d.user.display_name : 'Candidate'));
			var leaderEmail = (d.team && d.team.leader_email) ? d.team.leader_email : (d.leader_email || (d.user ? d.user.user_email : '—'));
			var leaderPhone = (d.team && d.team.leader_phone) ? d.team.leader_phone : (d.leader_phone || '—');
			var cleanLeaderPhone = leaderPhone ? leaderPhone.replace(/[^0-9]/g, '') : '';

			$('#ef-arm-reg-row-id').val(d.id);

			// Dynamic Modal Header
			if (isTeam) {
				var displayTeamName = d.team_name ? d.team_name : (d.team ? d.team.team_name : 'Team');
				$('#ef-arm-title').html('<span class="dashicons dashicons-groups" style="font-size:24px; width:24px; height:24px; vertical-align:middle; color:#0d9488;"></span> ' + escapeHtml(displayTeamName) + ' <span style="font-size:14px; font-weight:normal; color:#64748b;">(' + escapeHtml(d.reg_id) + ')</span>');
				$('#ef-tab-roster-label').text('Team Members & Roster');
				$('#ef-summary-type-pill').text('Team Registration').css('background', '#0d9488');
			} else {
				$('#ef-arm-title').html('<span class="dashicons dashicons-admin-users" style="font-size:24px; width:24px; height:24px; vertical-align:middle; color:#6366f1;"></span> ' + escapeHtml(leaderName) + ' <span style="font-size:14px; font-weight:normal; color:#64748b;">(' + escapeHtml(d.reg_id) + ')</span>');
				$('#ef-tab-roster-label').text('Individual Participant Details');
				$('#ef-summary-type-pill').text('Solo Candidate').css('background', '#6366f1');
			}

			$('#ef-arm-subtitle').text(
				(d.activity ? d.activity.title : 'Competition') + ' • ' + 
				(isTeam ? 'Team Application' : 'Individual Solo Application') + ' • Registered on ' + (d.created_at || '—')
			);

			$('#ef-roster-comp-title').text(d.activity ? d.activity.title : 'Competition Track');
			$('#ef-roster-team-title').html(
				isTeam 
					? '<strong>Team:</strong> ' + escapeHtml(d.team_name || 'Team') + ' &bull; <strong>Leader:</strong> ' + escapeHtml(leaderName)
					: '<strong>Solo Candidate:</strong> ' + escapeHtml(leaderName)
			);

			// Direct quick contacts in summary
			var contactsHtml = '';
			if (leaderEmail && leaderEmail !== '—') {
				contactsHtml += '<span><a href="mailto:' + escapeHtml(leaderEmail) + '" style="color:#2563eb; text-decoration:none;"><span class="dashicons dashicons-email" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span> ' + escapeHtml(leaderEmail) + '</a></span>';
			}
			if (leaderPhone && leaderPhone !== '—') {
				contactsHtml += '<span><span class="dashicons dashicons-phone" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span> ' + escapeHtml(leaderPhone);
				if (cleanLeaderPhone) {
					contactsHtml += ' <a href="https://wa.me/' + escapeHtml(cleanLeaderPhone) + '" target="_blank" style="background:#22c55e; color:#fff; font-size:10px; font-weight:700; padding:1px 6px; border-radius:3px; text-decoration:none; margin-left:4px;">WhatsApp</a>';
				}
				contactsHtml += '</span>';
			}
			contactsHtml += '<span style="font-weight:700; color:#0d9488;">Fee: ' + escapeHtml(d.event ? d.event.currency : 'PKR') + ' ' + parseFloat(d.total_fee || 0).toFixed(2) + '</span>';
			$('#ef-roster-contacts').html(contactsHtml);

			// Status Badges in Summary
			var payStatusText = (d.payment_status || 'pending').replace('_', ' ').toUpperCase();
			var appStatusText = (d.application_status || 'submitted').replace('_', ' ').toUpperCase();
			var statusChipsHtml = '<span class="ef-badge" style="background:#e0f2fe; color:#0369a1; font-weight:700; padding:3px 8px; border-radius:4px; font-size:11px;">PAYMENT: ' + escapeHtml(payStatusText) + '</span>' +
								  '<span class="ef-badge" style="background:#fef3c7; color:#92400e; font-weight:700; padding:3px 8px; border-radius:4px; font-size:11px;">APP: ' + escapeHtml(appStatusText) + '</span>';
			$('#ef-roster-status-chips').html(statusChipsHtml);

			// Participant members resolution
			var members = (d.members && d.members.length) ? d.members : [];
			if (!members.length) {
				// Fallback synthesized leader member
				members = [{
					id: 0,
					registration_id: d.id,
					full_name: leaderName,
					father_name: '',
					email: leaderEmail,
					contact_number: leaderPhone,
					department: '',
					semester: '',
					academic_year: '',
					university: '',
					is_leader: 1
				}];
			}

			var memberCount = members.length;
			$('#ef-roster-count-badge').text(memberCount + (isTeam ? (' Member' + (memberCount > 1 ? 's' : '')) : ' Candidate'));

			// Populate Participant Cards
			var rosterHtml = '';
			for (var i = 0; i < members.length; i++) {
				var m = members[i];
				var isLeader = parseInt(m.is_leader, 10) === 1;
				var cleanPhone = m.contact_number ? m.contact_number.replace(/[^0-9]/g, '') : '';

				var cardBg = '#ffffff';
				var cardBorder = '#e2e8f0';
				var badgeHtml = '';

				if (isTeam) {
					if (isLeader) {
						cardBg = '#f0fdfa';
						cardBorder = '#14b8a6';
						badgeHtml = '<span style="background:#0d9488; color:#ffffff; font-size:11px; font-weight:700; padding:3px 10px; border-radius:999px; margin-left:8px; display:inline-flex; align-items:center; gap:4px;"><span class="dashicons dashicons-star-filled" style="font-size:12px; width:12px; height:12px;"></span> TEAM LEADER</span>';
					} else {
						badgeHtml = '<span style="background:#e2e8f0; color:#334155; font-size:11px; font-weight:600; padding:3px 10px; border-radius:999px; margin-left:8px;">MEMBER #' + (i + 1) + '</span>';
					}
				} else {
					cardBg = '#f5f3ff';
					cardBorder = '#818cf8';
					badgeHtml = '<span style="background:#6366f1; color:#ffffff; font-size:11px; font-weight:700; padding:3px 10px; border-radius:999px; margin-left:8px; display:inline-flex; align-items:center; gap:4px;"><span class="dashicons dashicons-admin-users" style="font-size:12px; width:12px; height:12px;"></span> INDIVIDUAL CANDIDATE</span>';
				}

				rosterHtml += '<div class="ef-participant-card" style="border: 1.5px solid ' + cardBorder + '; border-radius: 10px; padding: 18px; margin-bottom: 16px; background: ' + cardBg + '; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">';
				rosterHtml += '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px dashed #cbd5e1; padding-bottom:10px;">';
				rosterHtml += '<div style="display:flex; align-items:center; flex-wrap:wrap; gap:4px;">';
				rosterHtml += '<strong style="font-size:16px; color:#0f172a;">' + (isTeam ? ((i + 1) + '. ') : '') + escapeHtml(m.full_name) + '</strong>';
				rosterHtml += badgeHtml;
				rosterHtml += '</div>';
				rosterHtml += '<button type="button" class="button button-small ef-toggle-edit-member" data-id="' + m.id + '" style="font-size:12px;"><span class="dashicons dashicons-edit" style="vertical-align:middle; margin-top:-2px;"></span> ' + 'Edit Details' + '</button>';
				rosterHtml += '</div>';

				// Static 8-field details view
				rosterHtml += '<div class="ef-member-static" id="ef-static-' + m.id + '" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:12px 16px; font-size:13px;">';
				rosterHtml += '<div><span style="color:#64748b; font-size:11px; font-weight:600; text-transform:uppercase; display:block; margin-bottom:2px;">Full Name</span><strong style="color:#0f172a;">' + escapeHtml(m.full_name) + '</strong></div>';
				rosterHtml += '<div><span style="color:#64748b; font-size:11px; font-weight:600; text-transform:uppercase; display:block; margin-bottom:2px;">Father\'s Name</span><strong style="color:#0f172a;">' + escapeHtml(m.father_name || '—') + '</strong></div>';
				
				rosterHtml += '<div><span style="color:#64748b; font-size:11px; font-weight:600; text-transform:uppercase; display:block; margin-bottom:2px;">Email Address</span><a href="mailto:' + escapeHtml(m.email) + '" style="color:#2563eb; text-decoration:none; font-weight:600;">' + escapeHtml(m.email) + '</a></div>';
				
				rosterHtml += '<div><span style="color:#64748b; font-size:11px; font-weight:600; text-transform:uppercase; display:block; margin-bottom:2px;">Phone / WhatsApp</span><div style="display:flex; align-items:center; gap:6px;"><span class="ef-monospace" style="font-weight:700;">' + escapeHtml(m.contact_number) + '</span>';
				if (cleanPhone) {
					rosterHtml += '<a href="https://wa.me/' + escapeHtml(cleanPhone) + '" target="_blank" style="background:#22c55e; color:#ffffff; font-size:10px; font-weight:700; padding:1px 6px; border-radius:3px; text-decoration:none;" title="Chat on WhatsApp">WhatsApp</a>';
				}
				rosterHtml += '</div></div>';

				rosterHtml += '<div><span style="color:#64748b; font-size:11px; font-weight:600; text-transform:uppercase; display:block; margin-bottom:2px;">University / Institution</span><strong style="color:#0f172a;">' + escapeHtml(m.university || '—') + '</strong></div>';
				rosterHtml += '<div><span style="color:#64748b; font-size:11px; font-weight:600; text-transform:uppercase; display:block; margin-bottom:2px;">Department / Discipline</span><strong style="color:#0f172a;">' + escapeHtml(m.department || '—') + '</strong></div>';
				rosterHtml += '<div><span style="color:#64748b; font-size:11px; font-weight:600; text-transform:uppercase; display:block; margin-bottom:2px;">Current Semester</span><strong style="color:#0f172a;">' + (m.semester ? ('Semester ' + escapeHtml(m.semester)) : '—') + '</strong></div>';
				rosterHtml += '<div><span style="color:#64748b; font-size:11px; font-weight:600; text-transform:uppercase; display:block; margin-bottom:2px;">Academic Year / Batch</span><strong style="color:#0f172a;">' + escapeHtml(m.academic_year || '—') + '</strong></div>';
				rosterHtml += '</div>';

				// In-place Full Edit Form
				rosterHtml += '<form class="ef-member-edit-form" id="ef-edit-form-' + m.id + '" style="display:none; margin-top:14px; background:#ffffff; padding:16px; border-radius:8px; border:1px solid #94a3b8; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">';
				rosterHtml += '<input type="hidden" name="member_id" value="' + m.id + '">';
				rosterHtml += '<input type="hidden" name="registration_id" value="' + d.id + '">';
				rosterHtml += '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:14px;">';
				rosterHtml += '<div><label style="font-size:11px; font-weight:700; display:block; margin-bottom:3px; color:#1e293b;">Full Name</label><input type="text" name="full_name" class="widefat" value="' + escapeHtml(m.full_name) + '" required></div>';
				rosterHtml += '<div><label style="font-size:11px; font-weight:700; display:block; margin-bottom:3px; color:#1e293b;">Father\'s Name</label><input type="text" name="father_name" class="widefat" value="' + escapeHtml(m.father_name || '') + '"></div>';
				rosterHtml += '<div><label style="font-size:11px; font-weight:700; display:block; margin-bottom:3px; color:#1e293b;">Email Address</label><input type="email" name="email" class="widefat" value="' + escapeHtml(m.email) + '" required></div>';
				rosterHtml += '<div><label style="font-size:11px; font-weight:700; display:block; margin-bottom:3px; color:#1e293b;">Phone / WhatsApp</label><input type="text" name="contact_number" class="widefat" value="' + escapeHtml(m.contact_number) + '" required></div>';
				rosterHtml += '<div><label style="font-size:11px; font-weight:700; display:block; margin-bottom:3px; color:#1e293b;">Department</label><input type="text" name="department" class="widefat" value="' + escapeHtml(m.department || '') + '"></div>';
				rosterHtml += '<div><label style="font-size:11px; font-weight:700; display:block; margin-bottom:3px; color:#1e293b;">Semester</label><input type="text" name="semester" class="widefat" value="' + escapeHtml(m.semester || '') + '"></div>';
				rosterHtml += '<div><label style="font-size:11px; font-weight:700; display:block; margin-bottom:3px; color:#1e293b;">Academic Year / Batch</label><input type="text" name="academic_year" class="widefat" value="' + escapeHtml(m.academic_year || '') + '"></div>';
				rosterHtml += '<div style="grid-column: 1 / -1;"><label style="font-size:11px; font-weight:700; display:block; margin-bottom:3px; color:#1e293b;">University / Institution</label><input type="text" name="university" class="widefat" value="' + escapeHtml(m.university || '') + '"></div>';
				rosterHtml += '</div>';
				rosterHtml += '<div style="text-align:right; display:flex; gap:8px; justify-content:flex-end;">';
				rosterHtml += '<button type="button" class="button ef-cancel-edit" data-id="' + m.id + '">Cancel</button>';
				rosterHtml += '<button type="submit" class="button button-primary" style="background:#0d9488; border-color:#0f766e;">Save Participant Changes</button>';
				rosterHtml += '</div>';
				rosterHtml += '</form>';

				rosterHtml += '</div>';
			}
			$('#ef-roster-members-list').html(rosterHtml);

			// Populate Custom Registration Fields (if any)
			if (d.custom_fields && typeof d.custom_fields === 'object' && Object.keys(d.custom_fields).length > 0) {
				var cfHtml = '';
				for (var key in d.custom_fields) {
					if (d.custom_fields.hasOwnProperty(key)) {
						var fieldVal = d.custom_fields[key];
						var displayVal = Array.isArray(fieldVal) ? fieldVal.join(', ') : fieldVal;
						var label = key.replace(/_/g, ' ');
						cfHtml += '<div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:6px; padding:10px 14px;">';
						cfHtml += '<span style="font-size:11px; text-transform:uppercase; color:#64748b; font-weight:600; display:block;">' + escapeHtml(label) + '</span>';
						cfHtml += '<strong style="font-size:13px; color:#0f172a; margin-top:2px; display:block;">' + escapeHtml(displayVal || '—') + '</strong>';
						cfHtml += '</div>';
					}
				}
				$('#ef-roster-custom-fields-body').html(cfHtml);
				$('#ef-roster-custom-fields-box').show();
			} else {
				$('#ef-roster-custom-fields-box').hide();
			}

			// Populate Payment Details
			if (d.payment) {
				$('#ef-arm-payment-empty').hide();
				$('#ef-arm-payment-details').show();
				$('#ef-arm-pay-method').text(d.payment.payment_method || '—');
				$('#ef-arm-pay-tid').text(d.payment.transaction_id || '—');
				$('#ef-arm-pay-amount').text((d.event ? d.event.currency : 'PKR') + ' ' + parseFloat(d.payment.amount || d.total_fee).toFixed(2));
				$('#ef-arm-pay-status-badge').text((d.payment.status || 'under_verification').toUpperCase());

				if (d.payment.preview_url) {
					$('#ef-arm-proof-none').hide();
					if (d.payment.preview_url.indexOf('.pdf') !== -1) {
						$('#ef-arm-proof-img').hide().attr('src', '');
						$('#ef-arm-proof-pdf').attr('src', d.payment.preview_url).show();
					} else {
						$('#ef-arm-proof-pdf').hide().attr('src', '');
						$('#ef-arm-proof-img').attr('src', d.payment.preview_url).show();
					}
				} else {
					$('#ef-arm-proof-img, #ef-arm-proof-pdf').hide();
					$('#ef-arm-proof-none').show();
				}
			} else {
				$('#ef-arm-payment-details').hide();
				$('#ef-arm-payment-empty').show();
			}

			// Populate Status Tab
			var currentAppStatus = d.application_status || 'submitted';
			if (currentAppStatus === 'approved') currentAppStatus = 'confirmed';
			$('#ef-arm-status-select').val(currentAppStatus);
			$('#ef-arm-rejection-reason').val(d.rejection_reason || '');
			$('#ef-arm-admin-notes').val(d.admin_notes || '');
			$('#ef-arm-rejection-group').toggle(currentAppStatus === 'rejected');
		}

		// Toggle Member Edit Form
		$(document).on('click', '.ef-toggle-edit-member', function(e) {
			e.preventDefault();
			var id = $(this).data('id');
			$('#ef-static-' + id).toggle();
			$('#ef-edit-form-' + id).toggle();
		});

		$(document).on('click', '.ef-cancel-edit', function(e) {
			e.preventDefault();
			var id = $(this).data('id');
			$('#ef-static-' + id).show();
			$('#ef-edit-form-' + id).hide();
		});

		// Save In-place Participant Changes
		$(document).on('submit', '.ef-member-edit-form', function(e) {
			e.preventDefault();
			var $form = $(this);
			var postData = $form.serializeArray();
			postData.push({ name: 'action', value: 'eventflow_update_participant_details' });
			postData.push({ name: 'nonce', value: window.eventflow_admin_vars.staff_nonce });

			var $btn = $form.find('button[type="submit"]').prop('disabled', true).text('Saving...');

			$.post(window.eventflow_admin_vars.ajax_url, postData, function(res) {
				$btn.prop('disabled', false).text('Save Participant Changes');
				if (res.success) {
					alert(res.data.message || 'Participant updated successfully.');
					// Refresh modal
					openRegistrationModal(currentRegData.reg_id);
				} else {
					alert(res.data.message || 'Failed to update participant.');
				}
			}).fail(function() {
				$btn.prop('disabled', false).text('Save Participant Changes');
				alert('Server communication error.');
			});
		});

		// Status Tab Change Rejection Group Toggle
		$('#ef-arm-status-select').on('change', function() {
			$('#ef-arm-rejection-group').toggle($(this).val() === 'rejected');
		});

		// Save Registration Application Status
		$('#ef-arm-status-form').on('submit', function(e) {
			e.preventDefault();
			var postData = $(this).serializeArray();
			postData.push({ name: 'action', value: 'eventflow_update_registration_status' });
			postData.push({ name: 'nonce', value: window.eventflow_admin_vars.staff_nonce });

			var $btn = $(this).find('button[type="submit"]').prop('disabled', true).text('Updating...');

			$.post(window.eventflow_admin_vars.ajax_url, postData, function(res) {
				$btn.prop('disabled', false).text('Update Registration Status');
				if (res.success) {
					alert(res.data.message);
					window.location.reload();
				} else {
					alert(res.data.message || 'Failed to update registration status.');
				}
			}).fail(function() {
				$btn.prop('disabled', false).text('Update Registration Status');
				alert('Server error.');
			});
		});

		// Payment Verification Actions Inside Inspector
		$('#ef-arm-btn-approve').on('click', function(e) {
			e.preventDefault();
			if (!currentRegData || !currentRegData.payment) return;
			var note = $('#ef-arm-action-notes').val().trim();

			if (!confirm('Approve this payment and confirm the registration slot?')) return;

			var $btn = $(this).prop('disabled', true).text('Approving...');
			$.post(window.eventflow_admin_vars.ajax_url, {
				action: 'eventflow_verify_payment',
				payment_id: currentRegData.payment.id,
				note: note,
				nonce: window.eventflow_admin_vars.staff_nonce
			}, function(res) {
				$btn.prop('disabled', false).text('Approve Payment & Confirm Slot');
				if (res.success) {
					alert(res.data.message);
					window.location.reload();
				} else {
					alert(res.data.message || 'Verification failed.');
				}
			});
		});

		$('#ef-arm-btn-reject').on('click', function(e) {
			e.preventDefault();
			if (!currentRegData || !currentRegData.payment) return;
			var reason = $('#ef-arm-action-notes').val().trim();

			if (!reason) {
				reason = prompt('Please specify a rejection reason for the participant:');
				if (!reason) return;
				$('#ef-arm-action-notes').val(reason);
			}

			if (!confirm('Reject this payment?')) return;

			var $btn = $(this).prop('disabled', true).text('Rejecting...');
			$.post(window.eventflow_admin_vars.ajax_url, {
				action: 'eventflow_reject_payment',
				payment_id: currentRegData.payment.id,
				reason: reason,
				nonce: window.eventflow_admin_vars.staff_nonce
			}, function(res) {
				$btn.prop('disabled', false).text('Reject Payment');
				if (res.success) {
					alert(res.data.message);
					window.location.reload();
				} else {
					alert(res.data.message || 'Rejection failed.');
				}
			});
		});

		$('#ef-arm-btn-reupload').on('click', function(e) {
			e.preventDefault();
			if (!currentRegData || !currentRegData.payment) return;
			var instructions = $('#ef-arm-action-notes').val().trim();

			if (!instructions) {
				instructions = prompt('Specify what needs to be re-uploaded (e.g. Please upload full bank screenshot with clear reference number):');
				if (!instructions) return;
				$('#ef-arm-action-notes').val(instructions);
			}

			var $btn = $(this).prop('disabled', true).text('Sending...');
			$.post(window.eventflow_admin_vars.ajax_url, {
				action: 'eventflow_request_reupload',
				payment_id: currentRegData.payment.id,
				instructions: instructions,
				nonce: window.eventflow_admin_vars.staff_nonce
			}, function(res) {
				$btn.prop('disabled', false).text('Request Re-upload');
				if (res.success) {
					alert(res.data.message);
					window.location.reload();
				} else {
					alert(res.data.message || 'Request failed.');
				}
			});
		});

		function escapeHtml(text) {
			if (!text) return '';
			return $('<div>').text(text).html();
		}
	});
})(jQuery);
</script>
