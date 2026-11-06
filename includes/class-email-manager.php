<?php
/**
 * SIBACOM EVENT - Centralized Email Dispatch & Notification Engine
 *
 * Implements a pure WordPress-native email system using wp_mail(), scoped sender
 * filters (info@portal.sibacom.tech / SIBACOM EVENT), dynamic
 * placeholder resolution, branded responsive HTML templates, plain-text fallback,
 * background queuing with idempotency locking, and mail transport diagnostics.
 *
 * @package EventFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Email_Manager {

	/**
	 * Default Sender Identity Constants
	 */
	const SENDER_NAME  = 'SIBACOM EVENT';
	const SENDER_EMAIL = 'info@portal.sibacom.tech';
	const REPLY_TO     = 'info@portal.sibacom.tech';

	/**
	 * Dynamic getter for Sender Display Name
	 *
	 * @return string
	 */
	public static function get_sender_name() {
		$name = get_option( 'eventflow_email_from_name', '' );
		return ! empty( $name ) ? sanitize_text_field( $name ) : self::SENDER_NAME;
	}

	/**
	 * Dynamic getter for Sender Email Address
	 *
	 * @return string
	 */
	public static function get_sender_email() {
		$email = get_option( 'eventflow_email_from_address', '' );
		return ( ! empty( $email ) && is_email( $email ) ) ? sanitize_email( $email ) : self::SENDER_EMAIL;
	}

	/**
	 * Dynamic getter for Reply-To Email Address
	 *
	 * @return string
	 */
	public static function get_reply_to() {
		$email = get_option( 'eventflow_email_reply_to', '' );
		return ( ! empty( $email ) && is_email( $email ) ) ? sanitize_email( $email ) : self::REPLY_TO;
	}

	/**
	 * Cron hook for registration deadline notifications
	 */
	const DEADLINE_CRON_HOOK = 'eventflow_check_deadline_reminders_cron';

	/**
	 * Safe query arg appender with fallback if WordPress functions aren't loaded
	 *
	 * @param array|string $args
	 * @param string       $url
	 * @return string
	 */
	public static function safe_add_query_arg( $args, $url ) {
		if ( function_exists( 'add_query_arg' ) ) {
			return add_query_arg( $args, $url );
		}
		$query = is_array( $args ) ? http_build_query( $args ) : (string) $args;
		$sep   = ( false !== strpos( $url, '?' ) ) ? '&' : '?';
		return $url . $sep . $query;
	}

	/**
	 * Scoped Mail Context Flag
	 * Ensures wp_mail_from and wp_mail_from_name only apply to plugin-generated emails.
	 *
	 * @var bool
	 */
	protected static $in_plugin_mail_context = false;

	/**
	 * Current plain text alternative body for PHPMailer AltBody
	 *
	 * @var string
	 */
	protected static $current_plain_text = '';

	/**
	 * Last captured WordPress mail error
	 *
	 * @var WP_Error|null
	 */
	protected static $last_mail_error = null;

	/**
	 * Last mail submission outcome flag
	 *
	 * @var bool|null
	 */
	protected static $last_mail_succeeded = null;

	/**
	 * Active log ID for current dispatch attempt
	 *
	 * @var int|null
	 */
	protected static $current_log_id = null;

	/**
	 * In-memory idempotency cache for immediate dispatches
	 *
	 * @var array
	 */
	protected static $dispatched_idempotency_keys = array();

	/**
	 * Initialize Email Manager and WordPress hooks
	 */
	public static function init() {
		// Hook into native WordPress mail failure and success hooks
		add_action( 'wp_mail_failed', array( __CLASS__, 'on_wp_mail_failed' ), 10, 1 );
		if ( version_compare( get_bloginfo( 'version' ), '5.9', '>=' ) ) {
			add_action( 'wp_mail_succeeded', array( __CLASS__, 'on_wp_mail_succeeded' ), 10, 1 );
		}

		// Deadline reminder cron
		add_action( self::DEADLINE_CRON_HOOK, array( __CLASS__, 'check_deadline_reminders' ) );
		if ( ! wp_next_scheduled( self::DEADLINE_CRON_HOOK ) ) {
			wp_schedule_event( time() + 3600, 'daily', self::DEADLINE_CRON_HOOK );
		}
	}

	/**
	 * Filter: wp_mail_from
	 * Scoped strictly to plugin-generated emails to never alter unrelated WordPress emails.
	 *
	 * @param string $from_email
	 * @return string
	 */
	public static function filter_wp_mail_from( $from_email ) {
		if ( self::$in_plugin_mail_context ) {
			return self::get_sender_email();
		}
		return $from_email;
	}

	/**
	 * Filter: wp_mail_from_name
	 * Scoped strictly to plugin-generated emails.
	 *
	 * @param string $from_name
	 * @return string
	 */
	public static function filter_wp_mail_from_name( $from_name ) {
		if ( self::$in_plugin_mail_context ) {
			return self::get_sender_name();
		}
		return $from_name;
	}

	/**
	 * Action: phpmailer_init
	 * Injects multipart plain-text alternative and enforces UTF-8 encoding.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer|object $phpmailer
	 */
	public static function configure_phpmailer( $phpmailer ) {
		if ( ! self::$in_plugin_mail_context ) {
			return;
		}

		$phpmailer->CharSet = 'UTF-8';

		$sender_email = self::get_sender_email();
		$sender_name  = self::get_sender_name();
		$reply_to     = self::get_reply_to();

		if ( is_object( $phpmailer ) ) {
			$phpmailer->From     = $sender_email;
			$phpmailer->FromName = $sender_name;
			$phpmailer->Sender   = $sender_email;

			if ( method_exists( $phpmailer, 'clearReplyTos' ) ) {
				$phpmailer->clearReplyTos();
			}
			if ( method_exists( $phpmailer, 'addReplyTo' ) ) {
				$phpmailer->addReplyTo( $reply_to, $sender_name );
			}

			// Direct SMTP transport if enabled in settings
			$smtp_enabled = get_option( 'eventflow_smtp_enabled', 'no' );
			if ( 'yes' === $smtp_enabled && method_exists( $phpmailer, 'isSMTP' ) ) {
				$smtp_host = get_option( 'eventflow_smtp_host', '' );
				$smtp_port = (int) get_option( 'eventflow_smtp_port', 587 );
				$smtp_user = get_option( 'eventflow_smtp_user', '' );
				$smtp_pass = class_exists( 'EventFlow_Security' ) ? EventFlow_Security::decrypt( get_option( 'eventflow_smtp_pass_encrypted', '' ) ) : '';
				$smtp_enc  = get_option( 'eventflow_smtp_encryption', 'tls' );
				$smtp_auth = get_option( 'eventflow_smtp_auth', 'yes' );

				if ( ! empty( $smtp_host ) ) {
					$phpmailer->isSMTP();
					$phpmailer->Host     = $smtp_host;
					$phpmailer->Port     = $smtp_port;
					$phpmailer->SMTPAuth = ( 'yes' === $smtp_auth );
					if ( $phpmailer->SMTPAuth ) {
						$phpmailer->Username = $smtp_user;
						$phpmailer->Password = $smtp_pass;
					}
					if ( 'ssl' === $smtp_enc ) {
						$phpmailer->SMTPSecure = 'ssl';
					} elseif ( 'tls' === $smtp_enc ) {
						$phpmailer->SMTPSecure = 'tls';
					} else {
						$phpmailer->SMTPSecure  = '';
						$phpmailer->SMTPAutoTLS = false;
					}
					$phpmailer->Timeout = (int) get_option( 'eventflow_smtp_timeout', 15 );
				}
			}
		}

		if ( ! empty( self::$current_plain_text ) && is_object( $phpmailer ) ) {
			$phpmailer->AltBody = self::$current_plain_text;
		}
	}

	/**
	 * Action: wp_mail_failed
	 * Captures native WordPress mail failure details
	 *
	 * @param WP_Error $error
	 */
	public static function on_wp_mail_failed( $error ) {
		self::$last_mail_error     = $error;
		self::$last_mail_succeeded = false;

		$err_msg = ( is_object( $error ) && method_exists( $error, 'get_error_message' ) )
			? $error->get_error_message()
			: ( is_string( $error ) ? $error : __( 'Mail transport rejected the message.', 'eventflow' ) );
		update_option( 'eventflow_email_last_failure_time', current_time( 'mysql' ) );
		update_option( 'eventflow_email_last_error', $err_msg );

		if ( self::$current_log_id ) {
			global $wpdb;
			$table_logs = EventFlow_DB::table( 'email_logs' );
			$wpdb->update(
				$table_logs,
				array(
					'status'            => 'failed',
					'error_message'     => $err_msg,
					'provider_response' => 'wp_mail_failed hook fired: ' . $err_msg,
				),
				array( 'id' => self::$current_log_id )
			);
		}
	}

	/**
	 * Action: wp_mail_succeeded
	 * Captures native WordPress mail success hand-off
	 *
	 * @param array $mail_data
	 */
	public static function on_wp_mail_succeeded( $mail_data ) {
		self::$last_mail_succeeded = true;
		update_option( 'eventflow_email_last_success_time', current_time( 'mysql' ) );

		if ( self::$current_log_id ) {
			global $wpdb;
			$table_logs = EventFlow_DB::table( 'email_logs' );
			$wpdb->update(
				$table_logs,
				array(
					'status'            => 'accepted',
					'error_message'     => null,
					'provider_response' => 'Accepted by server mail transport (wp_mail_succeeded)',
				),
				array( 'id' => self::$current_log_id )
			);
		}
	}

	/**
	 * Clear previous error states before a dispatch attempt
	 */
	public static function clear_last_error() {
		self::$last_mail_error     = null;
		self::$last_mail_succeeded = null;
		self::$current_log_id      = null;
	}

	/**
	 * Get last captured mail error
	 *
	 * @return WP_Error|null
	 */
	public static function get_last_error() {
		return self::$last_mail_error;
	}

	/**
	 * Generate a unique dispatch tracking ID
	 * Example: EF-DISP-20261105163000-A1B2
	 *
	 * @return string
	 */
	public static function generate_dispatch_id() {
		$random = function_exists( 'wp_generate_password' )
			? strtoupper( wp_generate_password( 4, false ) )
			: strtoupper( substr( md5( uniqid( (string) mt_rand(), true ) ), 0, 4 ) );
		return sprintf( 'EF-DISP-%s-%s', date( 'YmdHis' ), $random );
	}

	/**
	 * Determine workflow category from template key or arguments
	 *
	 * @param string $template_key
	 * @param array  $data
	 * @return string
	 */
	public static function determine_workflow( $template_key, $data = array() ) {
		$template_key = strtolower( trim( (string) $template_key ) );
		switch ( $template_key ) {
			case 'registration_submitted':
			case 'registration_confirmation':
			case 'welcome_email':
			case 'registration_updated':
			case 'registration_cancelled':
				return 'participant_registration';
			case 'admin_registration_submitted':
				return 'admin_operations';
			case 'payment_submitted':
			case 'payment_under_verification':
				return 'payment_submission';
			case 'admin_payment_submitted':
				return 'admin_operations';
			case 'payment_verified':
			case 'application_approved':
				return 'payment_verification';
			case 'payment_rejected':
			case 'application_rejected':
				return 'payment_rejection';
			case 'payment_reupload_requested':
				return 'payment_reupload';
			case 'account_created':
			case 'successful_login':
			case 'password_reset':
			case 'password_changed':
				return 'authentication';
			case 'activity_announcement':
			case 'event_update':
			case 'custom_announcement':
			case 'deadline_reminder':
				return 'event_announcements';
			case 'attendance_certificate':
			case 'merit_certificate':
				return 'certificates';
			case 'test_email':
				return 'system_test';
			default:
				return 'general_notification';
		}
	}

	/**
	 * Centralized SIBACOM EVENT Email Notification Engine
	 *
	 * Validates recipient, resolves placeholders, applies SIBACOM EVENT branding,
	 * scopes sender to info@portal.sibacom.tech, generates plain-text alternative,
	 * calls native wp_mail(), and accurately records the transport hand-off.
	 *
	 * @param string      $recipient Recipient email address
	 * @param string|null $subject   Custom or override subject (empty to use template subject)
	 * @param string      $template  Template key in database
	 * @param array       $data      Dynamic placeholder dictionary
	 * @param array       $args      Options: 'immediate' (bool), 'registration_id' (string), 'idempotency_key' (string), 'is_test_email' (bool), 'dispatch_id' (string), 'workflow' (string)
	 * @return array ['success' => bool, 'status' => string, 'message' => string, 'dispatch_id' => string, 'workflow' => string, 'log_id' => int|null, 'queue_id' => int|null]
	 */
	public static function send_sibacom_notification( $recipient, $subject = '', $template = '', $data = array(), $args = array() ) {
		self::clear_last_error();

		$dispatch_id = ! empty( $args['dispatch_id'] ) ? sanitize_text_field( $args['dispatch_id'] ) : self::generate_dispatch_id();
		$workflow    = ! empty( $args['workflow'] ) ? sanitize_text_field( $args['workflow'] ) : self::determine_workflow( $template, $data );
		$data['dispatch_id'] = $dispatch_id;
		$data['workflow']    = $workflow;

		// 1. Recipient Validation
		if ( empty( $subject ) && ! empty( $args['subject'] ) ) {
			$subject = sanitize_text_field( $args['subject'] );
		}
		$recipient = sanitize_email( trim( $recipient ) );
		if ( empty( $recipient ) || ! is_email( $recipient ) ) {
			self::log_dispatch_attempt(
				array(
					'dispatch_id'     => $dispatch_id,
					'workflow'        => $workflow,
					'template_key'    => $template ? $template : 'unknown',
					'recipient_email' => $recipient ? $recipient : 'invalid@recipient.empty',
					'recipient_name'  => isset( $data['participant_name'] ) ? sanitize_text_field( $data['participant_name'] ) : '',
					'subject'         => $subject ? $subject : __( 'Notification Attempt', 'eventflow' ),
					'body'            => '',
					'headers'         => self::build_headers(),
					'registration_id' => isset( $data['registration_id'] ) ? $data['registration_id'] : null,
					'status'          => 'failed',
					'error_message'   => __( 'Recipient email address is invalid or empty.', 'eventflow' ),
					'response'        => 'Validation Error: Invalid Recipient',
				)
			);

			return array(
				'success'     => false,
				'status'      => 'failed',
				'message'     => __( 'Please provide a valid recipient email address.', 'eventflow' ),
				'dispatch_id' => $dispatch_id,
				'workflow'    => $workflow,
				'log_id'      => self::$current_log_id,
				'queue_id'    => null,
			);
		}

		// 2. Load Email Template
		$template_obj = null;
		if ( ! empty( $template ) ) {
			$template_obj = self::get_template_by_key( $template );
		}

		$is_test = ! empty( $args['is_test_email'] );

		// Check if notification trigger is enabled
		if ( ! empty( $template ) && ! $is_test ) {
			$trigger_opt = get_option( 'eventflow_notify_trigger_' . $template, 'yes' );
			if ( 'no' === $trigger_opt ) {
				return array(
					'success'     => false,
					'status'      => 'disabled',
					'message'     => sprintf( __( 'Notification trigger for "%s" is disabled in settings.', 'eventflow' ), $template ),
					'dispatch_id' => $dispatch_id,
					'workflow'    => $workflow,
					'log_id'      => null,
					'queue_id'    => null,
				);
			}
		}

		if ( $template_obj && ! (int) $template_obj->is_active && ! $is_test ) {
			return array(
				'success'     => false,
				'status'      => 'disabled',
				'message'     => sprintf( __( 'Email template "%s" is currently inactive.', 'eventflow' ), $template ),
				'dispatch_id' => $dispatch_id,
				'workflow'    => $workflow,
				'log_id'      => null,
				'queue_id'    => null,
			);
		}

		// 3. Hydrate Dynamic Placeholders and Prepare Content
		$prepared = self::prepare_message( $template_obj, $recipient, $data, $subject );
		$final_subject = $prepared['subject'];
		$final_html    = $prepared['content'];
		$plain_text    = $prepared['plain_text'];
		$headers       = self::build_headers();

		$registration_id = isset( $data['registration_id'] )
			? sanitize_text_field( $data['registration_id'] )
			: ( isset( $args['registration_id'] ) ? sanitize_text_field( $args['registration_id'] ) : null );
		$recipient_type  = isset( $args['recipient_type'] ) ? sanitize_text_field( $args['recipient_type'] ) : 'solo';

		// 4. Background Queue vs Immediate Send
		$queue_enabled   = get_option( 'eventflow_email_queue_enabled', 'yes' );
		$force_immediate = ! empty( $args['immediate'] ) || $is_test;

		$idempotency_key = isset( $args['idempotency_key'] ) && ! empty( $args['idempotency_key'] )
			? sanitize_text_field( $args['idempotency_key'] )
			: null;

		// Check idempotency for immediate dispatches
		if ( $idempotency_key && ! $is_test ) {
			if ( isset( self::$dispatched_idempotency_keys[ $idempotency_key ] ) || ( function_exists( 'get_transient' ) && false !== get_transient( 'ef_idemp_' . $idempotency_key ) ) ) {
				return array(
					'success'         => true,
					'idempotent_skip' => true,
					'status'          => 'idempotent_skipped',
					'message'         => __( 'Duplicate trigger suppressed by idempotency key.', 'eventflow' ),
					'dispatch_id'     => $dispatch_id,
					'workflow'        => $workflow,
					'log_id'          => null,
					'queue_id'        => null,
				);
			}
		}

		if ( 'yes' === $queue_enabled && ! $force_immediate ) {
			$queue_idempotency = $idempotency_key
				? $idempotency_key
				: md5( sprintf( '%s|%s|%s|%s|%s', $template, $recipient, (string) $registration_id, $final_subject, date( 'Ymd' ) ) );

			$queue_id = EventFlow_Email_Queue::enqueue(
				array(
					'dispatch_id'     => $dispatch_id,
					'workflow'        => $workflow,
					'template_key'    => $template,
					'recipient_email' => $recipient,
					'recipient_name'  => isset( $data['participant_name'] ) ? sanitize_text_field( $data['participant_name'] ) : '',
					'recipient_type'  => $recipient_type,
					'subject'         => $final_subject,
					'body'            => $final_html,
					'headers'         => $headers,
					'placeholders'    => $data,
					'registration_id' => $registration_id,
					'idempotency_key' => $queue_idempotency,
				)
			);

			return array(
				'success'     => (bool) $queue_id,
				'status'      => 'queued',
				'message'     => __( 'Notification successfully queued for background delivery.', 'eventflow' ),
				'dispatch_id' => $dispatch_id,
				'workflow'    => $workflow,
				'log_id'      => null,
				'queue_id'    => $queue_id,
			);
		}

		// 5. Immediate Dispatch through Scoped Native wp_mail()
		$log_id = self::log_dispatch_attempt(
			array(
				'dispatch_id'     => $dispatch_id,
				'workflow'        => $workflow,
				'template_key'    => $template ? $template : 'direct',
				'recipient_email' => $recipient,
				'recipient_name'  => isset( $data['participant_name'] ) ? sanitize_text_field( $data['participant_name'] ) : '',
				'recipient_type'  => $recipient_type,
				'subject'         => $final_subject,
				'body'            => $final_html,
				'headers'         => $headers,
				'registration_id' => $registration_id,
				'status'          => 'sending',
				'error_message'   => null,
				'response'        => 'Sending to mail transport...',
			)
		);
		self::$current_log_id = $log_id;

		$sent = self::send_raw( $recipient, $final_subject, $final_html, $headers, $plain_text );

		$last_error = self::get_last_error();
		$error_msg  = null;
		$status     = 'accepted';
		$provider_resp = 'Accepted by WordPress native mail transport';

		if ( $sent ) {
			$status = 'accepted';
			$provider_resp = '250 OK: Accepted by mail transport (wp_mail returned true)';
			update_option( 'eventflow_email_last_success_time', current_time( 'mysql' ) );

			if ( $idempotency_key ) {
				self::$dispatched_idempotency_keys[ $idempotency_key ] = true;
				if ( function_exists( 'set_transient' ) ) {
					set_transient( 'ef_idemp_' . $idempotency_key, 1, 12 * HOUR_IN_SECONDS );
				}
			}
		} else {
			$status = 'failed';
			$error_msg = $last_error
				? $last_error->get_error_message()
				: __( 'wp_mail() returned false. Check hosting server mail configuration and server error logs.', 'eventflow' );
			$provider_resp = 'Transport Failure: ' . $error_msg;
			update_option( 'eventflow_email_last_failure_time', current_time( 'mysql' ) );
			update_option( 'eventflow_email_last_error', $error_msg );
		}

		// Update Log with final outcome
		if ( $log_id ) {
			global $wpdb;
			if ( is_object( $wpdb ) ) {
				$table_logs = EventFlow_DB::table( 'email_logs' );
				$wpdb->update(
					$table_logs,
					array(
						'status'            => $status,
						'error_message'     => $error_msg,
						'provider_response' => $provider_resp,
						'sent_at'           => current_time( 'mysql' ),
					),
					array( 'id' => $log_id )
				);
			}
		}

		return array(
			'success'     => $sent,
			'status'      => $status,
			'message'     => $sent
				? __( 'Notification accepted by WordPress mail transport.', 'eventflow' )
				: ( $error_msg ? $error_msg : __( 'WordPress could not send email through native mail transport.', 'eventflow' ) ),
			'dispatch_id' => $dispatch_id,
			'workflow'    => $workflow,
			'log_id'      => $log_id,
			'queue_id'    => null,
		);
	}

	/**
	 * Backwards-compatible send() method
	 *
	 * @param string $template_key
	 * @param string $recipient_email
	 * @param array  $custom_placeholders
	 * @param array  $args
	 * @return bool
	 */
	public static function send( $template_key, $recipient_email, $custom_placeholders = array(), $args = array() ) {
		$res = self::send_sibacom_notification( $recipient_email, '', $template_key, $custom_placeholders, $args );
		return ! empty( $res['success'] );
	}

	/**
	 * Send notification immediately without queuing
	 *
	 * @param string $template_key
	 * @param string $recipient_email
	 * @param array  $custom_placeholders
	 * @param array  $args
	 * @return bool
	 */
	public static function send_immediate( $template_key, $recipient_email, $custom_placeholders = array(), $args = array() ) {
		$args['immediate'] = true;
		$res = self::send_sibacom_notification( $recipient_email, '', $template_key, $custom_placeholders, $args );
		return ! empty( $res['success'] );
	}

	/**
	 * Send a real test email through WordPress native mail transport
	 *
	 * @param string $recipient_email
	 * @param string $template_key
	 * @return array
	 */
	public static function send_test_email( $recipient_email, $template_key = 'test_email' ) {
		$recipient_email = sanitize_email( trim( $recipient_email ) );
		if ( empty( $recipient_email ) || ! is_email( $recipient_email ) ) {
			return array(
				'success' => false,
				'message' => __( 'Please enter a valid recipient email address.', 'eventflow' ),
			);
		}

		update_option( 'eventflow_email_last_test_attempt', sprintf( '%s (to %s)', current_time( 'mysql' ), $recipient_email ) );

		$res = self::send_sibacom_notification(
			$recipient_email,
			'',
			$template_key,
			array(
				'participant_name'      => 'Test Administrator',
				'participant_email'     => $recipient_email,
				'team_name'             => 'SIBACOM EVENT Team',
				'registration_id'       => 'SIBACOM-TEST-2026',
				'activity_name'         => 'Speed Programming & Web Development',
				'payment_amount'        => 'PKR 2,500.00',
				'transaction_id'        => 'TXN-99887766',
				'payment_status'        => 'Verified',
				'application_status'    => 'Approved',
				'rejection_reason'      => 'N/A',
				'event_name'            => 'SIBACOM EVENT 2026',
				'event_date'            => 'November 20-23, 2026',
				'registration_deadline' => 'November 18, 2026',
				'website_url'           => home_url(),
			),
			array(
				'immediate'      => true,
				'is_test_email'  => true,
				'recipient_type' => 'admin',
			)
		);

		if ( $res['success'] ) {
			return array(
				'success' => true,
				'message' => __( 'Test email was accepted by the WordPress mail transport. Please check the recipient inbox and spam folder to confirm delivery.', 'eventflow' ),
				'log_id'  => $res['log_id'],
			);
		} else {
			$reason = ! empty( $res['message'] ) ? ' ' . $res['message'] : '';
			return array(
				'success' => false,
				'message' => __( 'WordPress could not send the test email. Check the hosting mail configuration and server error logs.', 'eventflow' ) . $reason,
				'log_id'  => $res['log_id'],
			);
		}
	}

	/**
	 * Core low-level wp_mail dispatch wrapper
	 * Enforces scoped sender filters and UTF-8 / plain-text alternatives.
	 *
	 * @param string       $to
	 * @param string       $subject
	 * @param string       $html_body
	 * @param array|string $headers
	 * @param string       $plain_text
	 * @return bool
	 */
	public static function send_raw( $to, $subject, $html_body, $headers = array(), $plain_text = '' ) {
		if ( empty( $to ) || ! is_email( $to ) ) {
			return false;
		}

		if ( empty( $headers ) ) {
			$headers = self::build_headers();
		}

		// Ensure UTF-8 HTML header is present
		$has_content_type = false;
		if ( is_array( $headers ) ) {
			foreach ( $headers as $h ) {
				if ( 0 === stripos( $h, 'Content-Type:' ) ) {
					$has_content_type = true;
					break;
				}
			}
			if ( ! $has_content_type ) {
				$headers[] = 'Content-Type: text/html; charset=UTF-8';
			}
		}

		// Prepare plain-text fallback
		self::$current_plain_text = ! empty( $plain_text ) ? $plain_text : self::html_to_plain_text( $html_body );

		// Scope sender identity to SIBACOM EVENT for this dispatch only
		self::$in_plugin_mail_context = true;
		add_filter( 'wp_mail_from', array( __CLASS__, 'filter_wp_mail_from' ), 99999 );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'filter_wp_mail_from_name' ), 99999 );
		add_action( 'phpmailer_init', array( __CLASS__, 'configure_phpmailer' ), 99999 );

		$sent = false;
		try {
			$sent = wp_mail( $to, $subject, $html_body, $headers );
		} catch ( \Throwable $e ) {
			$sent = false;
			self::$last_mail_error = new WP_Error( 'mail_exception', $e->getMessage() );
		} finally {
			// Always cleanly release scoped sender filters
			self::$in_plugin_mail_context = false;
			self::$current_plain_text     = '';
			if ( function_exists( 'remove_filter' ) ) {
				remove_filter( 'wp_mail_from', array( __CLASS__, 'filter_wp_mail_from' ), 99999 );
				remove_filter( 'wp_mail_from_name', array( __CLASS__, 'filter_wp_mail_from_name' ), 99999 );
			}
			if ( function_exists( 'remove_action' ) ) {
				remove_action( 'phpmailer_init', array( __CLASS__, 'configure_phpmailer' ), 99999 );
			}
		}

		return (bool) $sent;
	}

	/**
	 * Build RFC-compliant headers with SIBACOM EVENT identity
	 *
	 * @return array
	 */
	public static function build_headers() {
		$sender_name  = self::get_sender_name();
		$sender_email = self::get_sender_email();
		$reply_to     = self::get_reply_to();

		return array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $sender_name, $sender_email ),
			sprintf( 'Reply-To: %s <%s>', $sender_name, $reply_to ),
			'X-Mailer: SIBACOM EVENT (WordPress native wp_mail)',
		);
	}

	/**
	 * Prepare dynamic placeholders, HTML layout, and plain text
	 *
	 * @param object|null $template
	 * @param string      $recipient_email
	 * @param array       $custom_placeholders
	 * @param string      $subject_override
	 * @return array ['subject' => string, 'body' => string, 'content' => string, 'plain_text' => string]
	 */
	public static function prepare_message( $template, $recipient_email, $custom_placeholders = array(), $subject_override = '' ) {
		$placeholders = self::build_placeholders( $recipient_email, $custom_placeholders );

		$raw_subject = ! empty( $subject_override )
			? $subject_override
			: ( $template && ! empty( $template->subject ) ? $template->subject : __( 'SIBACOM EVENT Notification', 'eventflow' ) );

		$raw_body = $template && ! empty( $template->body )
			? $template->body
			: ( isset( $custom_placeholders['body'] ) ? $custom_placeholders['body'] : '' );

		// Replace placeholders
		$subject = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $raw_subject );
		$body    = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $raw_body );

		// Clean up any remaining unprocessed {placeholder} tags so raw code never leaks to users
		$clean_subject = trim( preg_replace( '/\{[a-z0-9_]+\}/i', '', $subject ) );
		$clean_body    = trim( preg_replace( '/\{[a-z0-9_]+\}/i', '', $body ) );

		// Wrap in branded HTML layout
		$active_event = class_exists( 'EventFlow_Event_Manager' ) ? EventFlow_Event_Manager::get_active_event() : null;
		$html_message = self::render_email_layout( $clean_subject, $clean_body, $active_event );
		$plain_text   = self::html_to_plain_text( $clean_body );

		return array(
			'subject'    => $clean_subject,
			'body'       => $clean_body,
			'content'    => $html_message,
			'plain_text' => $plain_text,
		);
	}

	/**
	 * Build array of dynamic placeholders with escaping and DB auto-hydration
	 *
	 * @param string $recipient_email
	 * @param array  $custom
	 * @return array
	 */
	public static function build_placeholders( $recipient_email, $custom = array() ) {
		$active_event = class_exists( 'EventFlow_Event_Manager' ) ? EventFlow_Event_Manager::get_active_event() : null;
		$event_name   = $active_event && ! empty( $active_event->title ) ? $active_event->title : 'SIBACOM EVENT 2026';
		$event_date   = $active_event && ! empty( $active_event->start_date ) ? date_i18n( get_option( 'date_format' ), strtotime( $active_event->start_date ) ) : '';
		$reg_deadline = $active_event && ! empty( $active_event->reg_end_date ) ? date_i18n( get_option( 'date_format' ), strtotime( $active_event->reg_end_date ) ) : '';

		// If registration_id provided, auto-hydrate from database
		if ( ! empty( $custom['registration_id'] ) ) {
			self::hydrate_registration_details( $custom['registration_id'], $custom );
		}

		$sender_name  = self::get_sender_name();
		$sender_email = self::get_sender_email();
		$reply_to     = self::get_reply_to();

		// Auto-resolve user if recipient matches a WordPress user and names are empty
		if ( empty( $custom['username'] ) || empty( $custom['participant_name'] ) ) {
			$u = function_exists( 'get_user_by' ) ? get_user_by( 'email', $recipient_email ) : null;
			if ( $u ) {
				if ( empty( $custom['username'] ) && ! empty( $u->user_login ) ) {
					$custom['username'] = $u->user_login;
				}
				if ( empty( $custom['participant_name'] ) && ! empty( $u->display_name ) ) {
					$custom['participant_name'] = $u->display_name;
				}
			}
		}

		// Determine team_or_participant_name
		if ( empty( $custom['team_or_participant_name'] ) ) {
			if ( ! empty( $custom['team_name'] ) ) {
				$custom['team_or_participant_name'] = $custom['team_name'];
			} elseif ( ! empty( $custom['participant_name'] ) ) {
				$custom['team_or_participant_name'] = $custom['participant_name'];
			}
		}

		$dash_url  = ( class_exists( 'EventFlow_Shortcodes' ) && method_exists( 'EventFlow_Shortcodes', 'get_dashboard_url' ) ) ? EventFlow_Shortcodes::get_dashboard_url() : home_url( '/participant-dashboard/' );
		$track_url = ( class_exists( 'EventFlow_Shortcodes' ) && method_exists( 'EventFlow_Shortcodes', 'get_track_url' ) ) ? EventFlow_Shortcodes::get_track_url() : home_url( '/track-status/' );
		$auth_url  = ( class_exists( 'EventFlow_Shortcodes' ) && method_exists( 'EventFlow_Shortcodes', 'get_auth_url' ) ) ? EventFlow_Shortcodes::get_auth_url() : ( function_exists( 'wp_login_url' ) ? wp_login_url() : home_url( '/wp-login.php' ) );
		$reg_id_val = ! empty( $custom['registration_id'] ) ? $custom['registration_id'] : '';

		$defaults = array(
			'{dispatch_id}'              => ! empty( $custom['dispatch_id'] ) ? $custom['dispatch_id'] : '',
			'{workflow}'                 => ! empty( $custom['workflow'] ) ? $custom['workflow'] : '',
			'{participant_name}'         => __( 'Participant', 'eventflow' ),
			'{participant_email}'        => $recipient_email,
			'{username}'                 => '',
			'{team_name}'                => '',
			'{team_or_participant_name}' => '',
			'{registration_id}'          => $reg_id_val,
			'{activity_name}'            => '',
			'{department}'               => '',
			'{semester}'                 => '',
			'{academic_year}'            => '',
			'{university}'               => 'Sukkur IBA University',
			'{contact_number}'           => '',
			'{participation_type_label}' => __( 'Registration', 'eventflow' ),
			'{payment_amount}'           => '',
			'{payment_status}'           => '',
			'{payment_status_label}'     => '',
			'{payment_method}'           => '',
			'{transaction_id}'           => '',
			'{verification_date}'        => date_i18n( get_option( 'date_format' ), current_time( 'timestamp' ) ),
			'{application_status}'       => '',
			'{application_status_label}' => '',
			'{rejection_reason}'         => '',
			'{cancellation_reason}'      => '',
			'{admin_notes}'              => '',
			'{previous_payment_status}'  => '',
			'{submission_date}'          => date_i18n( get_option( 'date_format' ), current_time( 'timestamp' ) ),
			'{update_timestamp}'         => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time( 'timestamp' ) ),
			'{payment_deadline}'         => $reg_deadline,
			'{event_name}'               => $event_name,
			'{event_date}'               => $event_date,
			'{start_time}'               => $active_event && ! empty( $active_event->start_time ) ? $active_event->start_time : '09:00 AM',
			'{end_time}'                 => $active_event && ! empty( $active_event->end_time ) ? $active_event->end_time : '05:00 PM',
			'{reporting_time}'           => $active_event && ! empty( $active_event->reporting_time ) ? $active_event->reporting_time : '08:30 AM',
			'{event_venue}'              => $active_event && ! empty( $active_event->venue ) ? $active_event->venue : ( $active_event && ! empty( $active_event->university_name ) ? $active_event->university_name : 'Sukkur IBA University Main Campus' ),
			'{registration_deadline}'    => $reg_deadline,
			'{website_url}'              => home_url(),
			'{login_url}'                => $auth_url,
			'{dashboard_url}'            => $dash_url,
			'{tracking_url}'             => $reg_id_val ? self::safe_add_query_arg( array( 'reg_id' => $reg_id_val ), $track_url ) : $track_url,
			'{reupload_url}'             => $reg_id_val ? self::safe_add_query_arg( array( 'tab' => 'payment', 'action' => 'reupload', 'reg_id' => $reg_id_val ), $dash_url ) : self::safe_add_query_arg( array( 'action' => 'reupload' ), $dash_url ),
			'{receipt_url}'              => $reg_id_val ? self::safe_add_query_arg( array( 'tab' => 'receipt', 'reg_id' => $reg_id_val ), $dash_url ) : $dash_url,
			'{reset_url}'                => function_exists( 'wp_lostpassword_url' ) ? wp_lostpassword_url() : home_url( '/wp-login.php?action=lostpassword' ),
			'{support_email}'            => $sender_email,
			'{support_phone}'            => $active_event && ! empty( $active_event->contact_phone ) ? $active_event->contact_phone : '+92 300 1234567',
			'{support_contact}'          => sprintf( '%s | %s', $sender_email, ( $active_event && ! empty( $active_event->contact_phone ) ? $active_event->contact_phone : '+92 300 1234567' ) ),
			'{leader_name}'              => '',
			'{leader_email}'             => '',
			'{leader_phone}'             => '',
			'{leader_department}'        => '',
			'{leader_semester}'          => '',
			'{leader_university}'        => '',
			'{total_team_members}'       => '1',
			'{registration_status}'      => '',
			'{recipient_role}'           => '',
			'{team_role}'                => '',
			'{team_summary_box}'         => '',
			'{team_members_table}'       => '',
			'{team_members_list}'        => '',
			'{announcement_content}'     => '',
			'{sender_name}'              => $sender_name,
			'{sender_email}'             => $sender_email,
			'{reply_to}'                 => $reply_to,
		);

		$placeholders = array();
		foreach ( $defaults as $tag => $fallback ) {
			$raw_key = trim( $tag, '{}' );
			if ( isset( $custom[ $raw_key ] ) && '' !== $custom[ $raw_key ] ) {
				$val = $custom[ $raw_key ];
				// Format URLs or rich HTML blocks correctly without double-escaping
				if ( in_array( $raw_key, array( 'website_url', 'login_url', 'dashboard_url', 'tracking_url', 'reupload_url', 'receipt_url', 'reset_url' ), true ) ) {
					$placeholders[ $tag ] = esc_url( $val );
				} elseif ( in_array( $raw_key, array( 'team_members_table', 'team_summary_box', 'announcement_content', 'body' ), true ) ) {
					$placeholders[ $tag ] = wp_kses_post( (string) $val );
				} else {
					$placeholders[ $tag ] = esc_html( (string) $val );
				}
			} else {
				$placeholders[ $tag ] = $fallback;
			}
		}

		return $placeholders;
	}

	/**
	 * Auto-hydrate registration details from database
	 *
	 * @param string $reg_id
	 * @param array  &$custom
	 */
	protected static function hydrate_registration_details( $reg_id, &$custom ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return;
		}
		$table_reg = EventFlow_DB::table( 'registrations' );
		$reg = null;
		if ( is_numeric( $reg_id ) ) {
			$reg = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_reg} WHERE id = %d OR reg_id = %s LIMIT 1", absint( $reg_id ), (string) $reg_id ) );
		} else {
			$reg = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_reg} WHERE reg_id = %s LIMIT 1", $reg_id ) );
		}

		if ( ! $reg ) {
			return;
		}

		// Payment record auto-hydration
		$table_pay = EventFlow_DB::table( 'payments' );
		$pay = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_pay} WHERE registration_id = %d ORDER BY id DESC LIMIT 1", absint( $reg->id ) ) );
		if ( $pay ) {
			if ( empty( $custom['transaction_id'] ) && ! empty( $pay->transaction_id ) ) {
				$custom['transaction_id'] = $pay->transaction_id;
			}
			if ( empty( $custom['payment_method'] ) && ! empty( $pay->payment_method ) ) {
				$custom['payment_method'] = $pay->payment_method;
			}
		}

		if ( empty( $custom['payment_status'] ) && ! empty( $reg->payment_status ) ) {
			$custom['payment_status'] = ucfirst( str_replace( '_', ' ', $reg->payment_status ) );
			$custom['payment_status_label'] = $custom['payment_status'];
		}
		if ( empty( $custom['application_status'] ) && ! empty( $reg->application_status ) ) {
			$custom['application_status'] = ucfirst( str_replace( '_', ' ', $reg->application_status ) );
			$custom['application_status_label'] = $custom['application_status'];
		}
		if ( empty( $custom['registration_status'] ) ) {
			$p_status = ! empty( $custom['payment_status_label'] ) ? $custom['payment_status_label'] : '';
			$a_status = ! empty( $custom['application_status_label'] ) ? $custom['application_status_label'] : '';
			$custom['registration_status'] = trim( $a_status . ( $p_status ? ' (' . $p_status . ')' : '' ) );
		}
		if ( empty( $custom['rejection_reason'] ) && ! empty( $reg->rejection_reason ) ) {
			$custom['rejection_reason'] = $reg->rejection_reason;
		}
		if ( empty( $custom['admin_notes'] ) && ! empty( $reg->admin_notes ) ) {
			$custom['admin_notes'] = $reg->admin_notes;
		}

		// Team & Leader
		if ( class_exists( 'EventFlow_Team_Manager' ) ) {
			$team = EventFlow_Team_Manager::get_team_by_registration( $reg->id );
			if ( $team ) {
				if ( empty( $custom['team_name'] ) && ! empty( $team->team_name ) ) {
					$custom['team_name'] = $team->team_name;
					$custom['team_or_participant_name'] = $team->team_name;
				}
				if ( empty( $custom['leader_name'] ) && ! empty( $team->leader_name ) ) {
					$custom['leader_name'] = $team->leader_name;
				}
				if ( empty( $custom['leader_email'] ) && ! empty( $team->leader_email ) ) {
					$custom['leader_email'] = $team->leader_email;
				}
				if ( empty( $custom['leader_phone'] ) && ! empty( $team->leader_phone ) ) {
					$custom['leader_phone'] = $team->leader_phone;
				}
				if ( empty( $custom['participant_name'] ) && ! empty( $team->leader_name ) ) {
					$custom['participant_name'] = $team->leader_name;
				}
				if ( empty( $custom['participant_email'] ) && ! empty( $team->leader_email ) ) {
					$custom['participant_email'] = $team->leader_email;
				}
			}
		}

		// Activity
		if ( empty( $custom['activity_name'] ) && ! empty( $reg->activity_id ) && class_exists( 'EventFlow_Activity_Manager' ) ) {
			$act = EventFlow_Activity_Manager::get_activity( $reg->activity_id );
			if ( $act ) {
				$custom['activity_name'] = $act->title;
			}
		}

		// Amount & Currency
		if ( empty( $custom['payment_amount'] ) && isset( $reg->total_fee ) ) {
			$event = class_exists( 'EventFlow_Event_Manager' ) ? EventFlow_Event_Manager::get_event( $reg->event_id ) : null;
			$curr  = $event && ! empty( $event->currency ) ? $event->currency : 'PKR';
			$custom['payment_amount'] = sprintf( '%s %s', $curr, number_format( (float) $reg->total_fee, 2 ) );
		}
	}

	/**
	 * Convert HTML string into structured plain text
	 *
	 * @param string $html
	 * @return string
	 */
	public static function html_to_plain_text( $html ) {
		// Convert line breaks and paragraph ends
		$text = preg_replace( '/<br\s*\/?>/i', "\n", $html );
		$text = preg_replace( '/<\/p>/i', "\n\n", $text );
		$text = preg_replace( '/<\/div>/i', "\n", $text );
		$text = preg_replace( '/<\/h[1-6]>/i', "\n\n", $text );
		$text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $text ) : strip_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		return trim( preg_replace( "/\n{3,}/", "\n\n", $text ) );
	}

	/**
	 * Render SIBACOM EVENT branded HTML email layout
	 *
	 * @param string      $title
	 * @param string      $body
	 * @param object|null $event
	 * @return string
	 */
	public static function render_email_layout( $title, $body, $event = null ) {
		$template_file = EVENTFLOW_PLUGIN_DIR . 'templates/emails/base-email-template.php';
		if ( file_exists( $template_file ) ) {
			ob_start();
			include $template_file;
			return ob_get_clean();
		}

		// Fallback inline template
		return sprintf(
			'<!DOCTYPE html><html><body style="font-family:sans-serif;background:#F8FAFC;padding:20px;"><div style="max-width:600px;margin:auto;background:#FFF;padding:30px;border-radius:8px;border:1px solid #E2E8F0;"><h2 style="color:#0D9488;">%s</h2><div>%s</div><hr style="margin-top:30px;border:none;border-top:1px solid #E2E8F0;"><p style="font-size:12px;color:#64748B;">SIBACOM EVENT &bull; info@portal.sibacom.tech</p></div></body></html>',
			esc_html( $title ),
			wp_kses_post( $body )
		);
	}

	/**
	 * Record a dispatch attempt into database table email_logs
	 *
	 * @param array $entry
	 * @return int Insert ID
	 */
	protected static function log_dispatch_attempt( $entry ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return 0;
		}
		$table_logs = EventFlow_DB::table( 'email_logs' );

		$sender_email = self::get_sender_email();
		$now          = current_time( 'mysql' );

		$headers_str = isset( $entry['headers'] )
			? ( is_array( $entry['headers'] ) ? implode( "\r\n", $entry['headers'] ) : (string) $entry['headers'] )
			: '';

		$dispatch_id = isset( $entry['dispatch_id'] ) ? sanitize_text_field( $entry['dispatch_id'] ) : self::generate_dispatch_id();
		$workflow    = isset( $entry['workflow'] ) ? sanitize_text_field( $entry['workflow'] ) : ( isset( $entry['template_key'] ) ? self::determine_workflow( $entry['template_key'] ) : 'general' );

		$wpdb->insert(
			$table_logs,
			array(
				'dispatch_id'       => $dispatch_id,
				'workflow'          => $workflow,
				'template_key'      => isset( $entry['template_key'] ) ? sanitize_text_field( $entry['template_key'] ) : 'general',
				'sender_email'      => $sender_email,
				'recipient_email'   => sanitize_email( $entry['recipient_email'] ),
				'recipient_name'    => isset( $entry['recipient_name'] ) ? sanitize_text_field( $entry['recipient_name'] ) : '',
				'recipient_type'    => isset( $entry['recipient_type'] ) ? sanitize_text_field( $entry['recipient_type'] ) : 'solo',
				'subject'           => sanitize_text_field( $entry['subject'] ),
				'body'              => isset( $entry['body'] ) ? $entry['body'] : '',
				'headers'           => $headers_str,
				'registration_id'   => isset( $entry['registration_id'] ) ? sanitize_text_field( $entry['registration_id'] ) : null,
				'status'            => isset( $entry['status'] ) ? sanitize_text_field( $entry['status'] ) : 'accepted',
				'retry_count'       => 0,
				'error_message'     => isset( $entry['error_message'] ) ? $entry['error_message'] : null,
				'provider_response' => isset( $entry['response'] ) ? $entry['response'] : 'Dispatched',
				'sent_at'           => $now,
				'created_at'        => $now,
			)
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Send automated notifications for a registration (Individual or Team)
	 *
	 * Detects registration participation type (solo vs team).
	 * For Solo: sends single notification to the participant.
	 * For Team:
	 *   - Queries the team record and all registered team members from database.
	 *   - Generates a styled responsive HTML table of the complete team roster with all 7 fields.
	 *   - Deduplicates recipients by email address (so leader never receives duplicate emails).
	 *   - Dispatches personalized individual emails to the team leader AND to EVERY team member.
	 *   - Keeps recipient email addresses private (each recipient gets an individual email).
	 *   - Records delivery attempts with recipient_type ('leader' or 'member').
	 *   - Ensures an error on one member does not block dispatch to others.
	 *
	 * @param int|string|object $registration Registration DB ID, reg_id string, or object
	 * @param string            $template_key Email template key
	 * @param array             $custom_data   Custom data overrides
	 * @param array             $args          Options: 'immediate', etc.
	 * @return array Summary of dispatch results
	 */
	public static function send_to_registration( $registration, $template_key, $custom_data = array(), $args = array() ) {
		global $wpdb;
		$table_reg = EventFlow_DB::table( 'registrations' );

		// 1. Resolve registration object
		$reg_obj = null;
		if ( is_object( $registration ) && isset( $registration->id ) ) {
			$reg_obj = $registration;
		} elseif ( is_numeric( $registration ) ) {
			$reg_obj = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_reg} WHERE id = %d LIMIT 1", absint( $registration ) ) );
			if ( ! $reg_obj ) {
				$reg_obj = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_reg} WHERE reg_id = %s LIMIT 1", (string) $registration ) );
			}
		} elseif ( is_string( $registration ) ) {
			$reg_obj = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_reg} WHERE reg_id = %s LIMIT 1", sanitize_text_field( $registration ) ) );
		}

		if ( ! $reg_obj ) {
			return array(
				'success' => false,
				'message' => __( 'Registration not found for notification.', 'eventflow' ),
				'results' => array(),
			);
		}

		$reg_pk    = ! empty( $reg_obj->id ) ? $reg_obj->id : ( ! empty( $reg_obj->reg_id ) ? $reg_obj->reg_id : 0 );
		$reg_id    = ! empty( $reg_obj->reg_id ) ? $reg_obj->reg_id : $reg_pk;
		$is_team   = ( isset( $reg_obj->participation_type ) && 'team' === $reg_obj->participation_type );
		$activity_id = ! empty( $reg_obj->activity_id ) ? $reg_obj->activity_id : 0;
		$event_id    = ! empty( $reg_obj->event_id ) ? $reg_obj->event_id : 0;
		$activity  = ( class_exists( 'EventFlow_Activity_Manager' ) && $activity_id ) ? EventFlow_Activity_Manager::get_activity( $activity_id ) : null;
		$event     = ( class_exists( 'EventFlow_Event_Manager' ) && $event_id ) ? EventFlow_Event_Manager::get_event( $event_id ) : null;
		$currency  = $event && ! empty( $event->currency ) ? $event->currency : 'PKR';
		$fee_val   = isset( $reg_obj->total_fee ) ? (float) $reg_obj->total_fee : 0.0;
		$fee_formatted = sprintf( '%s %s', $currency, number_format( $fee_val, 2 ) );

		// Status labels
		$payment_status     = ! empty( $reg_obj->payment_status ) ? $reg_obj->payment_status : 'pending';
		$application_status = ! empty( $reg_obj->application_status ) ? $reg_obj->application_status : 'pending';
		$payment_status_label     = ucfirst( str_replace( '_', ' ', $payment_status ) );
		$application_status_label = ucfirst( str_replace( '_', ' ', $application_status ) );

		$created_date = ! empty( $reg_obj->created_at ) ? $reg_obj->created_at : current_time( 'mysql' );
		$submission_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $created_date ) );

		// Base common data
		$base_data = array_merge(
			array(
				'registration_id'          => $reg_id,
				'activity_name'            => $activity ? $activity->title : '',
				'event_name'               => $event ? $event->title : 'SIBACOM EVENT',
				'event_date'               => $event && $event->start_date ? date_i18n( get_option( 'date_format' ), strtotime( $event->start_date ) ) : '',
				'event_venue'              => $event && ! empty( $event->venue ) ? $event->venue : ( $event && ! empty( $event->university_name ) ? $event->university_name : 'Sukkur IBA University' ),
				'start_time'               => $event && ! empty( $event->start_time ) ? $event->start_time : '09:00 AM',
				'end_time'                 => $event && ! empty( $event->end_time ) ? $event->end_time : '05:00 PM',
				'reporting_time'           => $event && ! empty( $event->reporting_time ) ? $event->reporting_time : '08:30 AM',
				'payment_amount'           => $fee_formatted,
				'payment_status'           => $payment_status,
				'payment_status_label'     => $payment_status_label,
				'application_status'       => $application_status,
				'application_status_label' => $application_status_label,
				'rejection_reason'         => ! empty( $reg_obj->rejection_reason ) ? $reg_obj->rejection_reason : ( isset( $custom_data['rejection_reason'] ) ? $custom_data['rejection_reason'] : '' ),
				'admin_notes'              => ! empty( $reg_obj->admin_notes ) ? $reg_obj->admin_notes : ( isset( $custom_data['admin_notes'] ) ? $custom_data['admin_notes'] : '' ),
				'submission_date'          => $submission_date,
				'update_timestamp'         => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time( 'timestamp' ) ),
				'participation_type_label' => $is_team ? __( 'Team Registration', 'eventflow' ) : __( 'Individual / Solo Registration', 'eventflow' ),
				'dashboard_url'            => ( class_exists( 'EventFlow_Shortcodes' ) && method_exists( 'EventFlow_Shortcodes', 'get_dashboard_url' ) ) ? EventFlow_Shortcodes::get_dashboard_url() : home_url( '/participant-dashboard/' ),
				'tracking_url'             => ( class_exists( 'EventFlow_Shortcodes' ) && method_exists( 'EventFlow_Shortcodes', 'get_track_url' ) ) ? self::safe_add_query_arg( array( 'reg_id' => $reg_id ), EventFlow_Shortcodes::get_track_url() ) : home_url( '/track-status/?reg_id=' . $reg_id ),
				'reupload_url'             => ( class_exists( 'EventFlow_Shortcodes' ) && method_exists( 'EventFlow_Shortcodes', 'get_dashboard_url' ) ) ? self::safe_add_query_arg( array( 'action' => 'reupload', 'reg_id' => $reg_id ), EventFlow_Shortcodes::get_dashboard_url() ) : home_url( '/participant-dashboard/?action=reupload&reg_id=' . $reg_id ),
				'support_email'            => self::get_sender_email(),
				'support_phone'            => $event && ! empty( $event->contact_phone ) ? $event->contact_phone : '+92 300 1234567',
			),
			$custom_data
		);

		// If solo registration:
		if ( ! $is_team ) {
			$user   = ! empty( $reg_obj->user_id ) ? get_userdata( $reg_obj->user_id ) : null;
			$member = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . EventFlow_DB::table( 'team_members' ) . " WHERE registration_id = %d LIMIT 1", $reg_pk ) );
			$team_r = null;
			if ( ! $member ) {
				$team_r = EventFlow_Team_Manager::get_team_by_registration( $reg_pk );
			}

			$participant_name  = ( $member && ! empty( $member->full_name ) ) ? $member->full_name : ( ( $team_r && ! empty( $team_r->leader_name ) ) ? $team_r->leader_name : ( $user ? $user->display_name : 'Participant' ) );
			$participant_email = ( $member && ! empty( $member->email ) ) ? $member->email : ( ( $team_r && ! empty( $team_r->leader_email ) ) ? $team_r->leader_email : ( $user ? $user->user_email : '' ) );

			if ( empty( $participant_email ) ) {
				return array(
					'success'       => false,
					'total'         => 0,
					'success_count' => 0,
					'message'       => 'No participant email found for solo registration.',
					'recipients'    => array(),
				);
			}

			$solo_data = array_merge( $base_data, array(
				'participant_name'          => $participant_name,
				'participant_email'         => $participant_email,
				'team_name'                 => '',
				'team_or_participant_name'  => $participant_name,
				'recipient_role'            => __( 'Participant', 'eventflow' ),
				'team_role'                 => __( 'Participant', 'eventflow' ),
				'department'                => $member && ! empty( $member->department ) ? $member->department : '',
				'semester'                  => $member && ! empty( $member->semester ) ? $member->semester : '',
				'academic_year'             => $member && ! empty( $member->academic_year ) ? $member->academic_year : '',
				'university'                => $member && ! empty( $member->university ) ? $member->university : '',
				'contact_number'            => $member && ! empty( $member->contact_number ) ? $member->contact_number : ( ( $team_r && ! empty( $team_r->leader_phone ) ) ? $team_r->leader_phone : '' ),
				'leader_name'               => $participant_name,
				'leader_email'              => $participant_email,
				'leader_phone'              => $member && ! empty( $member->contact_number ) ? $member->contact_number : ( ( $team_r && ! empty( $team_r->leader_phone ) ) ? $team_r->leader_phone : '' ),
				'leader_department'         => $member && ! empty( $member->department ) ? $member->department : '',
				'leader_semester'           => $member && ! empty( $member->semester ) ? $member->semester : '',
				'leader_university'         => $member && ! empty( $member->university ) ? $member->university : '',
				'total_team_members'        => 1,
				'team_members_table'        => '',
				'team_members_list'         => '',
			) );

			$base_idempotency = ! empty( $args['idempotency_key'] ) ? $args['idempotency_key'] : sprintf( '%s|%s', $template_key, $reg_id );
			$solo_args = array_merge( $args, array(
				'registration_id' => $reg_id,
				'recipient_type'  => 'solo',
				'idempotency_key' => md5( sprintf( '%s|%s', $base_idempotency, strtolower( trim( $participant_email ) ) ) ),
			) );

			$res = self::send_sibacom_notification( $participant_email, '', $template_key, $solo_data, $solo_args );
			$is_ok = ! empty( $res['success'] );
			return array(
				'success'       => $is_ok,
				'total'         => 1,
				'success_count' => $is_ok ? 1 : 0,
				'recipients'    => array( $participant_email => $res ),
			);
		}

		// Team Registration Logic
		$team = EventFlow_Team_Manager::get_team_by_registration( $reg_pk );
		$members = method_exists( 'EventFlow_Team_Manager', 'get_members_by_registration' )
			? EventFlow_Team_Manager::get_members_by_registration( $reg_pk )
			: ( method_exists( 'EventFlow_Team_Manager', 'get_team_members' )
				? EventFlow_Team_Manager::get_team_members( $reg_pk )
				: array() );

		$team_name     = $team && ! empty( $team->team_name ) ? $team->team_name : ( ! empty( $reg_obj->team_name ) ? $reg_obj->team_name : 'Team' );
		$total_members = count( $members );

		// Identify Team Leader first
		$leader_info = null;
		foreach ( $members as $idx => $m ) {
			if ( ! empty( $m->is_leader ) || ( isset( $m->role ) && 'leader' === $m->role ) || 0 === $idx ) {
				$leader_info = $m;
				break;
			}
		}

		$leader_name  = ( $leader_info && ! empty( $leader_info->full_name ) ) ? $leader_info->full_name : ( ( $team && ! empty( $team->leader_name ) ) ? $team->leader_name : 'Team Leader' );
		$leader_email = ( $leader_info && ! empty( $leader_info->email ) ) ? $leader_info->email : ( ( $team && ! empty( $team->leader_email ) ) ? $team->leader_email : '' );
		$leader_phone = ( $leader_info && ! empty( $leader_info->contact_number ) ) ? $leader_info->contact_number : ( ( $team && ! empty( $team->leader_phone ) ) ? $team->leader_phone : '' );
		$leader_dept  = ( $leader_info && ! empty( $leader_info->department ) ) ? $leader_info->department : '';
		$leader_sem   = ( $leader_info && ! empty( $leader_info->semester ) ) ? $leader_info->semester : '';
		$leader_univ  = ( $leader_info && ! empty( $leader_info->university ) ) ? $leader_info->university : '';

		// Generate HTML and plain text team members roster
		$table_html  = '<div class="team-members-table-wrap">';
		$table_html .= '<h3 style="font-size:16px; margin:16px 0 8px; color:#111827;">' . sprintf( esc_html__( 'Team Roster: %s (%d Members)', 'eventflow' ), esc_html( $team_name ), $total_members ) . '</h3>';
		$table_html .= '<table class="team-members-table" width="100%" cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse; border:1px solid #E5E7EB; font-size:13px;">';
		$table_html .= '<thead><tr style="background:#F9FAFB; font-weight:700;">';
		$table_html .= '<th align="left">#</th>';
		$table_html .= '<th align="left">' . esc_html__( 'Member Name', 'eventflow' ) . '</th>';
		$table_html .= '<th align="left">' . esc_html__( 'Role', 'eventflow' ) . '</th>';
		$table_html .= '<th align="left">' . esc_html__( 'Department', 'eventflow' ) . '</th>';
		$table_html .= '<th align="left">' . esc_html__( 'Semester / Batch', 'eventflow' ) . '</th>';
		$table_html .= '<th align="left">' . esc_html__( 'University', 'eventflow' ) . '</th>';
		$table_html .= '<th align="left">' . esc_html__( 'Contact', 'eventflow' ) . '</th>';
		$table_html .= '</tr></thead><tbody>';

		$list_text = sprintf( "Team Roster: %s (%d Members)\n", $team_name, $total_members );

		$idx = 1;
		foreach ( $members as $m_idx => $m ) {
			$is_lead    = ( ! empty( $m->is_leader ) || ( isset( $m->role ) && 'leader' === $m->role ) || 0 === $m_idx );
			$role_label = $is_lead ? __( 'Leader', 'eventflow' ) : __( 'Member', 'eventflow' );
			$m_sem      = ! empty( $m->semester ) ? 'Sem ' . $m->semester : '';
			$m_acad     = ! empty( $m->academic_year ) ? '(' . $m->academic_year . ')' : '';
			$sem_batch  = trim( sprintf( '%s %s', $m_sem, $m_acad ) );
			$m_dept     = ! empty( $m->department ) ? $m->department : '';
			$m_uni      = ! empty( $m->university ) ? $m->university : '';
			$m_phone    = ! empty( $m->contact_number ) ? $m->contact_number : '';

			$table_html .= '<tr>';
			$table_html .= '<td>' . $idx . '</td>';
			$table_html .= '<td><strong>' . esc_html( ! empty( $m->full_name ) ? $m->full_name : 'Member' ) . '</strong><br><span style="font-size:11px; color:#6B7280;">' . esc_html( ! empty( $m->email ) ? $m->email : '' ) . '</span></td>';
			$table_html .= '<td><span style="font-size:11px; padding:2px 6px; border-radius:4px; font-weight:700; background:' . ( $is_lead ? '#CCFBF1; color:#0F766E;' : '#F3F4F6; color:#4B5563;' ) . '">' . esc_html( $role_label ) . '</span></td>';
			$table_html .= '<td>' . esc_html( $m_dept ) . '</td>';
			$table_html .= '<td>' . esc_html( $sem_batch ) . '</td>';
			$table_html .= '<td>' . esc_html( $m_uni ) . '</td>';
			$table_html .= '<td>' . esc_html( $m_phone ) . '</td>';
			$table_html .= '</tr>';

			$list_text .= sprintf(
				"%d. %s [%s] - %s | %s | %s | %s | %s\n",
				$idx,
				! empty( $m->full_name ) ? $m->full_name : 'Member',
				$role_label,
				! empty( $m->email ) ? $m->email : '',
				$m_phone,
				$m_dept,
				$sem_batch,
				$m_uni
			);

			$idx++;
		}
		$table_html .= '</tbody></table></div>';

		// Deduplicated Recipient Map: email => array of member-specific details
		$recipients = array();

		// Seed team leader from team record if available
		if ( $team && ! empty( $team->leader_email ) && is_email( $team->leader_email ) ) {
			$norm_email = strtolower( trim( $team->leader_email ) );
			$recipients[ $norm_email ] = array(
				'email'          => $team->leader_email,
				'name'           => ! empty( $team->leader_name ) ? $team->leader_name : 'Team Leader',
				'type'           => 'leader',
				'department'     => $leader_dept,
				'semester'       => $leader_sem,
				'academic_year'  => '',
				'university'     => $leader_univ,
				'contact_number' => ! empty( $team->leader_phone ) ? $team->leader_phone : $leader_phone,
			);
		}

		// Process all registered team members (including dynamically added members)
		foreach ( $members as $m_idx => $m ) {
			if ( empty( $m->email ) || ! is_email( $m->email ) ) {
				continue;
			}
			$norm_email = strtolower( trim( $m->email ) );
			$is_lead    = ( ! empty( $m->is_leader ) || ( isset( $m->role ) && 'leader' === $m->role ) || 0 === $m_idx );

			$m_name  = ! empty( $m->full_name ) ? $m->full_name : ( $is_lead ? $leader_name : 'Participant' );
			$m_dept  = ! empty( $m->department ) ? $m->department : ( $is_lead ? $leader_dept : '' );
			$m_sem   = ! empty( $m->semester ) ? $m->semester : ( $is_lead ? $leader_sem : '' );
			$m_acad  = ! empty( $m->academic_year ) ? $m->academic_year : '';
			$m_uni   = ! empty( $m->university ) ? $m->university : ( $is_lead ? $leader_univ : '' );
			$m_phone = ! empty( $m->contact_number ) ? $m->contact_number : ( $is_lead ? $leader_phone : '' );

			if ( ! isset( $recipients[ $norm_email ] ) ) {
				$recipients[ $norm_email ] = array(
					'email'          => $m->email,
					'name'           => $m_name,
					'type'           => $is_lead ? 'leader' : 'member',
					'department'     => $m_dept,
					'semester'       => $m_sem,
					'academic_year'  => $m_acad,
					'university'     => $m_uni,
					'contact_number' => $m_phone,
				);
			} else {
				// Update with richer member-specific fields if available
				if ( ! empty( $m_name ) ) {
					$recipients[ $norm_email ]['name'] = $m_name;
				}
				if ( ! empty( $m_dept ) ) {
					$recipients[ $norm_email ]['department'] = $m_dept;
				}
				if ( ! empty( $m_sem ) ) {
					$recipients[ $norm_email ]['semester'] = $m_sem;
				}
				if ( ! empty( $m_acad ) ) {
					$recipients[ $norm_email ]['academic_year'] = $m_acad;
				}
				if ( ! empty( $m_uni ) ) {
					$recipients[ $norm_email ]['university'] = $m_uni;
				}
				if ( ! empty( $m_phone ) ) {
					$recipients[ $norm_email ]['contact_number'] = $m_phone;
				}
				if ( $is_lead ) {
					$recipients[ $norm_email ]['type'] = 'leader';
				}
			}
		}

		// Dispatch personalized individual email to each recipient
		$results       = array();
		$success_count = 0;
		$base_idemp    = ! empty( $args['idempotency_key'] ) ? $args['idempotency_key'] : sprintf( '%s|%s', $template_key, $reg_id );

		foreach ( $recipients as $norm_email => $r_data ) {
			$r_email      = $r_data['email'];
			$r_name       = $r_data['name'];
			$r_type       = $r_data['type']; // 'leader' or 'member'
			$r_role_label = ( 'leader' === $r_type ) ? __( 'Team Leader', 'eventflow' ) : __( 'Team Member', 'eventflow' );

			$recipient_placeholders = array_merge(
				$base_data,
				array(
					'participant_name'         => $r_name,
					'participant_email'        => $r_email,
					'team_name'                => $team_name,
					'team_or_participant_name' => $team_name,
					'recipient_role'           => $r_role_label,
					'team_role'                => $r_role_label,
					'department'               => $r_data['department'],
					'semester'                 => $r_data['semester'],
					'academic_year'            => $r_data['academic_year'],
					'university'               => $r_data['university'],
					'contact_number'           => $r_data['contact_number'],
					'leader_name'              => $leader_name,
					'leader_email'             => $leader_email,
					'leader_phone'             => $leader_phone,
					'leader_department'        => $leader_dept,
					'leader_semester'          => $leader_sem,
					'leader_university'        => $leader_univ,
					'total_team_members'       => $total_members,
					'team_members_table'       => $table_html,
					'team_members_list'        => $list_text,
				)
			);

			$dispatch_args = array_merge(
				$args,
				array(
					'registration_id' => $reg_id,
					'recipient_type'  => $r_type,
					'idempotency_key' => md5( sprintf( '%s|%s', $base_idemp, $norm_email ) ),
				)
			);

			try {
				$res = self::send_sibacom_notification( $r_email, '', $template_key, $recipient_placeholders, $dispatch_args );
				$results[ $r_email ] = $res;
				if ( ! empty( $res['success'] ) ) {
					$success_count++;
				}
			} catch ( \Throwable $e ) {
				$results[ $r_email ] = array(
					'success' => false,
					'status'  => 'failed',
					'message' => $e->getMessage(),
				);
			}
		}

		return array(
			'success'       => ( $success_count > 0 ),
			'total'         => count( $recipients ),
			'success_count' => $success_count,
			'recipients'    => $results,
		);
	}

	/**
	 * Get template row by key
	 *
	 * @param string $template_key
	 * @return object|null
	 */
	public static function get_template_by_key( $template_key ) {
		global $wpdb;
		$template_obj = null;
		if ( is_object( $wpdb ) ) {
			$table = EventFlow_DB::table( 'email_templates' );
			$template_obj = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE template_key = %s LIMIT 1", $template_key ) );
		}

		if ( ! $template_obj ) {
			// Fallback to default definition if database row is not yet provisioned
			$defaults = EventFlow_DB::get_default_email_templates_data();
			foreach ( $defaults as $tpl ) {
				if ( $tpl['template_key'] === $template_key ) {
					return (object) array(
						'id'           => 0,
						'template_key' => $tpl['template_key'],
						'name'         => isset( $tpl['name'] ) ? $tpl['name'] : ( isset( $tpl['title'] ) ? $tpl['title'] : $tpl['template_key'] ),
						'title'        => isset( $tpl['name'] ) ? $tpl['name'] : ( isset( $tpl['title'] ) ? $tpl['title'] : $tpl['template_key'] ),
						'subject'      => $tpl['subject'],
						'body'         => $tpl['body'],
						'placeholders' => isset( $tpl['placeholders'] ) ? $tpl['placeholders'] : '',
						'is_active'    => 1,
					);
				}
			}
		}

		return $template_obj;
	}

	/**
	 * Get single template by ID or key
	 *
	 * @param int|string $id
	 * @return object|null
	 */
	public static function get_template( $id ) {
		if ( ! is_numeric( $id ) ) {
			return self::get_template_by_key( $id );
		}
		global $wpdb;
		$table = EventFlow_DB::table( 'email_templates' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $id ) ) );
	}

	/**
	 * Get all templates
	 *
	 * @return array
	 */
	public static function get_all_templates() {
		global $wpdb;
		$table = EventFlow_DB::table( 'email_templates' );
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC" );
	}

	/**
	 * Update email template
	 *
	 * @param int   $id
	 * @param array $data
	 * @return bool
	 */
	public static function update_template( $id, $data ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'email_templates' );

		return (bool) $wpdb->update(
			$table,
			array(
				'subject'    => sanitize_text_field( $data['subject'] ),
				'body'       => wp_kses_post( $data['body'] ),
				'is_active'  => ! empty( $data['is_active'] ) ? 1 : 0,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) )
		);
	}

	/**
	 * Reset email template back to default content
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function reset_template( $id ) {
		$template = self::get_template( $id );
		if ( ! $template ) {
			return false;
		}

		$defaults = EventFlow_DB::get_default_email_templates_data();
		foreach ( $defaults as $tpl ) {
			if ( $tpl['template_key'] === $template->template_key ) {
				global $wpdb;
				$table = EventFlow_DB::table( 'email_templates' );
				return (bool) $wpdb->update(
					$table,
					array(
						'subject'    => $tpl['subject'],
						'body'       => $tpl['body'],
						'is_active'  => 1,
						'updated_at' => current_time( 'mysql' ),
					),
					array( 'id' => absint( $id ) )
				);
			}
		}

		return false;
	}

	/**
	 * Scheduled Cron Handler: Check approaching registration deadlines and send reminders
	 *
	 * @return int Number of queued reminder emails
	 */
	public static function check_deadline_reminders() {
		global $wpdb;
		$table_events = EventFlow_DB::table( 'events' );
		$table_reg    = EventFlow_DB::table( 'registrations' );
		$table_teams  = EventFlow_DB::table( 'teams' );

		$now         = current_time( 'timestamp' );
		$in_48_hours = date( 'Y-m-d H:i:s', $now + ( 48 * 3600 ) );
		$now_date    = date( 'Y-m-d H:i:s', $now );

		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_events}
				 WHERE status = 'active'
				   AND reg_end_date >= %s
				   AND reg_end_date <= %s",
				$now_date,
				$in_48_hours
			)
		);

		if ( empty( $events ) ) {
			return 0;
		}

		$queued_count = 0;

		foreach ( $events as $event ) {
			$registrations = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT r.*, t.leader_email, t.leader_name, t.team_name
					 FROM {$table_reg} r
					 LEFT JOIN {$table_teams} t ON t.registration_id = r.id
					 WHERE r.event_id = %d
					   AND ( r.payment_status = 'pending' OR r.payment_status = 'reupload_requested' )",
					$event->id
				)
			);

			if ( empty( $registrations ) ) {
				continue;
			}

			$deadline_formatted = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $event->reg_end_date ) );

			foreach ( $registrations as $r ) {
				if ( empty( $r->leader_email ) || ! is_email( $r->leader_email ) ) {
					continue;
				}

				$idempotency_key = sprintf( 'deadline_%d_%s_%s', $event->id, $r->reg_id, date( 'Ymd' ) );

				$res = self::send_sibacom_notification(
					$r->leader_email,
					'',
					'deadline_reminder',
					array(
						'participant_name'      => $r->leader_name ? $r->leader_name : 'Participant',
						'team_name'             => $r->team_name ? $r->team_name : '',
						'registration_id'       => $r->reg_id,
						'event_name'            => $event->title,
						'registration_deadline' => $deadline_formatted,
						'website_url'           => class_exists( 'EventFlow_Shortcodes' ) ? EventFlow_Shortcodes::get_dashboard_url() : home_url(),
					),
					array(
						'idempotency_key' => $idempotency_key,
						'registration_id' => $r->reg_id,
					)
				);

				if ( ! empty( $res['success'] ) ) {
					$queued_count++;
				}
			}
		}

		return $queued_count;
	}
}

/**
 * Global helper function: send_sibacom_notification
 *
 * All email triggers throughout the plugin use this centralized engine.
 *
 * @param string      $recipient Recipient email address
 * @param string|null $subject   Email subject (optional, if empty template subject is used)
 * @param string      $template  Template key in database
 * @param array       $data      Dynamic placeholders dictionary
 * @param array       $args      Additional options: 'immediate', 'registration_id', 'idempotency_key'
 * @return array Result array ['success' => bool, 'status' => string, 'message' => string, 'log_id' => int|null, 'queue_id' => int|null]
 */
function send_sibacom_notification( $recipient, $subject = '', $template = '', $data = array(), $args = array() ) {
	return EventFlow_Email_Manager::send_sibacom_notification( $recipient, $subject, $template, $data, $args );
}
