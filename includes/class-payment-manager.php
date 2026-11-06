<?php
/**
 * EventFlow Payment Submission & Gateway Settings Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Payment_Manager {

	/**
	 * Get payment gateway settings
	 *
	 * @return array
	 */
	public static function get_settings() {
		return array(
			'bank_name'          => get_option( 'eventflow_bank_name', 'Bank Alfalah' ),
			'account_title'      => get_option( 'eventflow_bank_account_title', get_option( 'eventflow_account_title', 'Tech Olympiad Society' ) ),
			'account_number'     => get_option( 'eventflow_bank_account_no', get_option( 'eventflow_account_number', '0123-4567890123' ) ),
			'iban'               => get_option( 'eventflow_bank_iban', get_option( 'eventflow_iban', 'PK65ALFH01234567890123' ) ),
			'bank_enabled'       => get_option( 'eventflow_bank_enabled', 'yes' ),
			'easypaisa_title'    => get_option( 'eventflow_easypaisa_title', 'EventFlow Org' ),
			'easypaisa_number'   => get_option( 'eventflow_easypaisa_no', get_option( 'eventflow_easypaisa_number', '0300-1234567' ) ),
			'easypaisa_enabled'  => get_option( 'eventflow_easypaisa_enabled', 'yes' ),
			'jazzcash_title'     => get_option( 'eventflow_jazzcash_title', 'EventFlow Org' ),
			'jazzcash_number'    => get_option( 'eventflow_jazzcash_no', get_option( 'eventflow_jazzcash_number', '0300-1234567' ) ),
			'jazzcash_enabled'   => get_option( 'eventflow_jazzcash_enabled', 'yes' ),
			'raast_id'           => get_option( 'eventflow_raast_id', '03001234567' ),
			'instructions'       => get_option( 'eventflow_payment_instructions', "1. Transfer the exact fee amount using any bank app, JazzCash, or Easypaisa.\n2. Ensure the recipient title matches our official account title.\n3. Take a clear screenshot of the successful receipt showing the Transaction ID (TID) and Date.\n4. Upload the screenshot below to initiate verification." ),
			'qr_code_url'        => get_option( 'eventflow_qr_code_url', '' ),
			'payment_deadline'   => get_option( 'eventflow_payment_deadline', '' ),
			'currency'           => get_option( 'eventflow_currency', 'PKR' ),
		);
	}

	/**
	 * Get array of enabled payment methods with structured metadata
	 *
	 * @return array
	 */
	public static function get_enabled_payment_methods() {
		$s = self::get_settings();
		$methods = array();

		$is_bank_on = in_array( (string) $s['bank_enabled'], array( 'yes', '1', 'true' ), true );
		$is_jc_on   = in_array( (string) $s['jazzcash_enabled'], array( 'yes', '1', 'true' ), true );
		$is_ep_on   = in_array( (string) $s['easypaisa_enabled'], array( 'yes', '1', 'true' ), true );

		if ( $is_bank_on ) {
			$bank_data = array(
				'id'            => 'bank_transfer',
				'label'         => ! empty( $s['bank_name'] ) ? sprintf( __( 'Bank Transfer (%s)', 'eventflow' ), $s['bank_name'] ) : __( 'Bank Transfer', 'eventflow' ),
				'bank_name'     => $s['bank_name'],
				'title'         => $s['account_title'],
				'number'        => $s['account_number'],
				'iban'          => $s['iban'],
				'type'          => 'bank',
			);
			$methods['bank_transfer'] = $bank_data;
			$methods['bank']          = $bank_data;
		}

		if ( $is_jc_on ) {
			$methods['jazzcash'] = array(
				'id'            => 'jazzcash',
				'label'         => __( 'JazzCash Mobile Account', 'eventflow' ),
				'title'         => $s['jazzcash_title'],
				'number'        => $s['jazzcash_number'],
				'mobile_no'     => $s['jazzcash_number'],
				'type'          => 'wallet',
			);
		}

		if ( $is_ep_on ) {
			$methods['easypaisa'] = array(
				'id'            => 'easypaisa',
				'label'         => __( 'Easypaisa Mobile Account', 'eventflow' ),
				'title'         => $s['easypaisa_title'],
				'number'        => $s['easypaisa_number'],
				'mobile_no'     => $s['easypaisa_number'],
				'type'          => 'wallet',
			);
		}

		return $methods;
	}

	/**
	 * Get payment record by payment ID
	 *
	 * @param int $payment_id
	 * @return object|null
	 */
	public static function get_payment( $payment_id ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'payments' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $payment_id ) ) );
		if ( $row ) {
			$row->preview_url  = EventFlow_Security::get_secure_proof_url( $row->id );
			$row->download_url = EventFlow_Security::get_secure_download_url( $row->id );
		}
		return $row;
	}

	/**
	 * Get payment record by registration ID (latest submission)
	 *
	 * @param int $registration_id
	 * @return object|null
	 */
	public static function get_payment_by_registration( $registration_id ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'payments' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE registration_id = %d ORDER BY id DESC LIMIT 1", absint( $registration_id ) ) );
		if ( $row ) {
			$row->preview_url  = EventFlow_Security::get_secure_proof_url( $row->id );
			$row->download_url = EventFlow_Security::get_secure_download_url( $row->id );
		}
		return $row;
	}

	/**
	 * Get all payment records/versions for a registration
	 *
	 * @param int $registration_id
	 * @return array
	 */
	public static function get_all_payments_by_registration( $registration_id ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'payments' );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE registration_id = %d ORDER BY id DESC",
				absint( $registration_id )
			)
		);
		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				$row->preview_url  = EventFlow_Security::get_secure_proof_url( $row->id );
				$row->download_url = EventFlow_Security::get_secure_download_url( $row->id );
			}
		}
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Process participant payment proof submission
	 *
	 * @param int   $user_id
	 * @param array $data
	 * @param array $files
	 * @return array|WP_Error
	 */
	public static function process_submission( $user_id, $data, $files ) {
		global $wpdb;

		$registration_id = isset( $data['registration_id'] ) ? absint( $data['registration_id'] ) : 0;
		$reg = EventFlow_Registration_Manager::get_registration( $registration_id );

		if ( ! $reg ) {
			return new WP_Error( 'not_found', __( 'Registration record not found.', 'eventflow' ) );
		}

		// User ownership or staff check
		if ( (int) $reg->user_id !== (int) $user_id && ! EventFlow_Roles::is_staff( $user_id ) ) {
			return new WP_Error( 'unauthorized', __( 'You do not have permission to submit payment for this registration.', 'eventflow' ) );
		}

		// Prevent resubmission if payment is already verified
		if ( 'verified' === $reg->payment_status ) {
			return new WP_Error( 'already_verified', __( 'This payment has already been verified and confirmed. Resubmission is not required.', 'eventflow' ) );
		}

		$transaction_id = ! empty( $data['transaction_id'] ) ? sanitize_text_field( $data['transaction_id'] ) : 'TXN-' . date( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 6, false ) );
		$payment_method = isset( $data['payment_method'] ) ? sanitize_text_field( $data['payment_method'] ) : '';
		$payment_date   = isset( $data['payment_date'] ) ? sanitize_text_field( $data['payment_date'] ) : current_time( 'Y-m-d' );
		$amount         = isset( $data['amount'] ) ? floatval( $data['amount'] ) : floatval( $reg->total_fee );

		if ( empty( $payment_method ) ) {
			return new WP_Error( 'missing_fields', __( 'Payment Method is required.', 'eventflow' ) );
		}

		// Handle file upload
		$payment_file = ! empty( $files['payment_proof'] ) ? $files['payment_proof'] : ( ! empty( $files['payment_screenshot'] ) ? $files['payment_screenshot'] : null );
		if ( empty( $payment_file ) || empty( $payment_file['name'] ) ) {
			return new WP_Error( 'missing_file', __( 'Please upload a screenshot or receipt PDF of your payment.', 'eventflow' ) );
		}

		$uploaded_file = self::handle_file_upload( $payment_file );
		if ( is_wp_error( $uploaded_file ) ) {
			return $uploaded_file;
		}

		$table_payments = EventFlow_DB::table( 'payments' );
		$table_reg      = EventFlow_DB::table( 'registrations' );

		// Check all existing payment submissions for this registration
		$all_submissions = self::get_all_payments_by_registration( $reg->id );
		$existing_payment = ! empty( $all_submissions ) ? $all_submissions[0] : null;

		// Anti-duplicate protection: avoid rapid duplicate inserts within 5 seconds with identical TID
		if ( $existing_payment && 'under_verification' === $existing_payment->status && $existing_payment->transaction_id === $transaction_id ) {
			$time_diff = current_time( 'timestamp' ) - strtotime( $existing_payment->created_at );
			if ( $time_diff >= 0 && $time_diff < 5 ) {
				return array(
					'payment_id'     => $existing_payment->id,
					'status'         => 'under_verification',
					'payment_status' => 'under_verification',
					'version'        => isset( $existing_payment->version ) ? (int) $existing_payment->version : 1,
					'message'        => __( 'Payment Submitted Successfully. Your updated payment proof has been submitted and is awaiting admin verification.', 'eventflow' ),
				);
			}
		}

		// Determine new version number (Never overwrite historical records)
		$max_version = 0;
		foreach ( $all_submissions as $sub ) {
			$v = isset( $sub->version ) ? (int) $sub->version : 1;
			if ( $v > $max_version ) {
				$max_version = $v;
			}
		}
		$next_version = $existing_payment ? max( $max_version, count( $all_submissions ) ) + 1 : 1;

		$now = current_time( 'mysql' );

		// Always insert a new record for every submission/resubmission to preserve audit history
		$inserted = $wpdb->insert(
			$table_payments,
			array(
				'registration_id' => $reg->id,
				'user_id'         => $user_id,
				'transaction_id'  => $transaction_id,
				'payment_method'  => $payment_method,
				'amount'          => $amount,
				'payment_date'    => $payment_date,
				'screenshot_url'  => $uploaded_file['url'],
				'screenshot_path' => $uploaded_file['path'],
				'file_type'       => $uploaded_file['type'],
				'file_size'       => $uploaded_file['size'],
				'status'          => 'under_verification',
				'version'         => $next_version,
				'notes'           => $existing_payment ? sprintf( __( 'Resubmission (Version %d)', 'eventflow' ), $next_version ) : '',
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'db_insert_error', __( 'Failed to save payment record. Please try again.', 'eventflow' ) );
		}

		$payment_id = $wpdb->insert_id;

		// Reset registration status to Pending Verification and clear active warning (historical reason is preserved in payments & logs)
		$wpdb->update(
			$table_reg,
			array(
				'payment_status'     => 'under_verification',
				'application_status' => 'under_review',
				'rejection_reason'   => '',
				'updated_at'         => $now,
			),
			array( 'id' => $reg->id )
		);

		// Record payment verification log
		$action_type = ( $existing_payment && in_array( $existing_payment->status, array( 'reupload_requested', 'rejected' ), true ) )
			? 'receipt_replaced'
			: 'proof_submitted';

		$log_note = sprintf( 'Submission #%d: Transaction ID: %s via %s (Amount: %s)', $next_version, $transaction_id, $payment_method, number_format( $amount, 2 ) );
		if ( 'receipt_replaced' === $action_type && ! empty( $existing_payment->screenshot_path ) ) {
			$log_note .= sprintf( ' [Previous proof: %s]', basename( $existing_payment->screenshot_path ) );
		}

		$wpdb->insert(
			EventFlow_DB::table( 'payment_logs' ),
			array(
				'payment_id'      => $payment_id,
				'registration_id' => $reg->id,
				'action'          => $action_type,
				'note'            => $log_note,
				'performed_by'    => $user_id,
				'created_at'      => $now,
			)
		);

		EventFlow_Security::log_audit( 'payment_proof_submitted', 'payment', $payment_id, array( 'reg_id' => $reg->reg_id, 'tid' => $transaction_id, 'version' => $next_version ), $user_id );

		// Record status transition in status_logs
		if ( class_exists( 'EventFlow_DB' ) ) {
			$wpdb->insert(
				EventFlow_DB::table( 'status_logs' ),
				array(
					'registration_id' => $reg->id,
					'old_status'      => $existing_payment ? $existing_payment->status : 'pending',
					'new_status'      => 'under_verification',
					'status_type'     => 'payment',
					'changed_by'      => $user_id ? absint( $user_id ) : 0,
					'notes'           => sprintf( 'Payment proof submitted (Version %d, TID: %s via %s)', $next_version, $transaction_id, $payment_method ),
					'created_at'      => $now,
				),
				array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
		}

		$team     = EventFlow_Team_Manager::get_team_by_registration( $reg->id );
		$activity = EventFlow_Activity_Manager::get_activity( $reg->activity_id );
		$event    = EventFlow_Event_Manager::get_event( $reg->event_id );
		$event_name = $event ? $event->title : ( $activity ? $activity->title : 'Event' );

		// In-Dashboard Notifications
		if ( class_exists( 'EventFlow_Notification_Manager' ) ) {
			$dash_url = EventFlow_Shortcodes::get_dashboard_url();
			if ( $existing_payment ) {
				// Resubmission notification for participant
				EventFlow_Notification_Manager::create(
					array(
						'user_id'         => $reg->user_id,
						'registration_id' => $reg->id,
						'payment_id'      => $payment_id,
						'type'            => 'payment_resubmitted',
						'title'           => __( 'Payment Submitted Successfully', 'eventflow' ),
						'message'         => sprintf(
							__( 'Your updated payment proof for %s has been submitted successfully. Your payment is now awaiting verification.', 'eventflow' ),
							$event_name
						),
						'action_url'      => add_query_arg( array( 'tab' => 'registrations' ), $dash_url ),
					)
				);

				// Resubmission alert for administrator
				EventFlow_Notification_Manager::notify_admins(
					'admin_payment_resubmitted',
					__( 'Payment Proof Resubmitted', 'eventflow' ),
					sprintf(
						__( 'A participant has resubmitted payment proof. Registration ID: %s. Please review the latest submission.', 'eventflow' ),
						$reg->reg_id
					),
					$reg->id,
					$payment_id,
					admin_url( 'admin.php?page=eventflow-payments&s=' . urlencode( $reg->reg_id ) )
				);
			} else {
				// First submission notification for participant
				EventFlow_Notification_Manager::create(
					array(
						'user_id'         => $reg->user_id,
						'registration_id' => $reg->id,
						'payment_id'      => $payment_id,
						'type'            => 'payment_submitted',
						'title'           => __( 'Payment Submitted Successfully', 'eventflow' ),
						'message'         => sprintf(
							__( 'Your payment proof for %s has been submitted and is awaiting admin verification.', 'eventflow' ),
							$event_name
						),
						'action_url'      => add_query_arg( array( 'tab' => 'registrations' ), $dash_url ),
					)
				);
			}
		}

		// Send Email Notifications
		$recipient_email  = $team ? $team->leader_email : '';
		$participant_name = $team ? $team->leader_name : '';
		$currency         = $event ? $event->currency : 'PKR';
		$is_reupload      = ! empty( $existing_payment );
		$reupload_note    = $is_reupload ? sprintf( __( 'Replacement payment proof submitted (Version %d).', 'eventflow' ), $next_version ) : '';

		EventFlow_Email_Manager::send_to_registration(
			$reg->id,
			'payment_submitted',
			array(
				'payment_amount'     => sprintf( '%s %s', $currency, number_format( $amount, 2 ) ),
				'transaction_id'     => $transaction_id,
				'payment_method'     => $payment_method,
				'reupload_note'      => $reupload_note,
				'submission_version' => $next_version,
				'website_url'        => EventFlow_Shortcodes::get_dashboard_url(),
			),
			array(
				'idempotency_key' => 'payment_submitted_' . $payment_id . '_' . time(),
				'registration_id' => $reg->reg_id,
			)
		);

		EventFlow_Email_Manager::send_to_registration(
			$reg->id,
			'payment_under_verification',
			array(
				'payment_amount'     => sprintf( '%s %s', $currency, number_format( $amount, 2 ) ),
				'transaction_id'     => $transaction_id,
				'reupload_note'      => $reupload_note,
				'submission_version' => $next_version,
				'website_url'        => EventFlow_Shortcodes::get_dashboard_url(),
			),
			array(
				'idempotency_key' => 'payment_under_verification_' . $payment_id . '_' . time(),
				'registration_id' => $reg->reg_id,
			)
		);

		// Send New Payment Submission Notification to Authorized Admin
		$admin_email = get_option( 'eventflow_admin_notification_email', get_option( 'admin_email' ) );
		if ( ! empty( $admin_email ) && is_email( $admin_email ) ) {
			$admin_subject = $is_reupload
				? sprintf( __( '[Payment Re-upload] %s – Registration %s', 'eventflow' ), ( $team ? $team->team_name : $participant_name ), $reg->reg_id )
				: '';

			EventFlow_Email_Manager::send(
				'admin_payment_submitted',
				$admin_email,
				array(
					'participant_name'   => $participant_name,
					'participant_email'  => $recipient_email,
					'team_name'          => $team ? $team->team_name : $participant_name,
					'registration_id'    => $reg->reg_id,
					'activity_name'      => $activity ? $activity->title : '',
					'payment_amount'     => sprintf( '%s %s', $currency, number_format( $amount, 2 ) ),
					'transaction_id'     => $transaction_id,
					'reupload_note'      => $reupload_note,
					'submission_version' => $next_version,
					'event_name'         => $event ? $event->title : '',
					'website_url'        => admin_url( 'admin.php?page=eventflow-payments' ),
				),
				array(
					'subject'         => $admin_subject,
					'idempotency_key' => 'admin_payment_submitted_' . $payment_id,
					'registration_id' => $reg->reg_id,
				)
			);
		}

		return array(
			'payment_id'     => $payment_id,
			'status'         => 'under_verification',
			'payment_status' => 'under_verification',
			'version'        => $next_version,
			'message'        => __( 'Payment Submitted Successfully. Your updated payment proof has been submitted and is awaiting admin verification.', 'eventflow' ),
		);
	}

	/**
	 * Handle file upload with strict validation and storage in protected directory
	 *
	 * @param array $file $_FILES['...']
	 * @return array|WP_Error
	 */
	public static function handle_file_upload( $file ) {
		if ( ! isset( $file['error'] ) || is_array( $file['error'] ) || UPLOAD_ERR_OK !== $file['error'] ) {
			return new WP_Error( 'upload_err', __( 'File upload failed. Please try again.', 'eventflow' ) );
		}

		// Size limit: 5MB
		$max_size = 5 * 1024 * 1024;
		if ( $file['size'] > $max_size ) {
			return new WP_Error( 'file_too_large', __( 'File size exceeds maximum allowed limit (5 MB).', 'eventflow' ) );
		}

		// Allowed extensions and MIME types
		$allowed_mimes = array(
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'pdf'      => 'application/pdf',
		);

		$file_info = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed_mimes );
		if ( ! $file_info['ext'] || ! $file_info['type'] ) {
			return new WP_Error( 'invalid_file_type', __( 'Invalid file format. Only JPG, PNG, and PDF files are allowed.', 'eventflow' ) );
		}

		$upload_dir = wp_upload_dir();
		$secure_dir = trailingslashit( $upload_dir['basedir'] ) . 'eventflow-secure';

		if ( ! file_exists( $secure_dir ) ) {
			wp_mkdir_p( $secure_dir );
		}

		// Generate randomized filename: proof_reg_timestamp_hash.ext
		$random_hash = wp_generate_password( 12, false );
		$new_filename = sprintf( 'proof_%s_%s.%s', time(), $random_hash, strtolower( $file_info['ext'] ) );
		$destination = trailingslashit( $secure_dir ) . $new_filename;

		$moved = is_uploaded_file( $file['tmp_name'] ) 
			? move_uploaded_file( $file['tmp_name'], $destination ) 
			: @copy( $file['tmp_name'], $destination );

		if ( ! $moved ) {
			return new WP_Error( 'move_err', __( 'Unable to move uploaded file to secure storage.', 'eventflow' ) );
		}

		// Return path and initial URL
		return array(
			'path' => $destination,
			'url'  => trailingslashit( $upload_dir['baseurl'] ) . 'eventflow-secure/' . $new_filename,
			'type' => $file_info['type'],
			'size' => $file['size'],
		);
	}

	/**
	 * Get accessible URL for payment proof file
	 *
	 * @param string|int $file_path_or_id
	 * @return string
	 */
	public static function get_proof_url( $file_path_or_id ) {
		if ( is_numeric( $file_path_or_id ) ) {
			return EventFlow_Security::get_secure_proof_url( (int) $file_path_or_id );
		}

		if ( empty( $file_path_or_id ) ) {
			return '';
		}

		if ( filter_var( $file_path_or_id, FILTER_VALIDATE_URL ) ) {
			return $file_path_or_id;
		}

		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['baseurl'] ) . 'eventflow-secure/' . basename( (string) $file_path_or_id );
	}
}

