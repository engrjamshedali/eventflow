<?php
/**
 * EventFlow Email Queue & Background Asynchronous Worker
 *
 * Implements a persistent, resilient database-backed email queue with
 * staged exponential backoff, duplicate prevention (idempotency),
 * WP-Cron automation, and manual administrative oversight.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Email_Queue {

	const CRON_HOOK = 'eventflow_process_email_queue_cron';

	/**
	 * Initialize queue hooks and schedules
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_intervals' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'process_queue' ) );

		// Ensure cron schedule is alive
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'eventflow_two_minutes', self::CRON_HOOK );
		}
	}

	/**
	 * Register custom cron intervals
	 *
	 * @param array $schedules
	 * @return array
	 */
	public static function add_cron_intervals( $schedules ) {
		if ( ! isset( $schedules['eventflow_two_minutes'] ) ) {
			$schedules['eventflow_two_minutes'] = array(
				'interval' => 120,
				'display'  => __( 'Every 2 Minutes (EventFlow Queue)', 'eventflow' ),
			);
		}
		if ( ! isset( $schedules['eventflow_one_minute'] ) ) {
			$schedules['eventflow_one_minute'] = array(
				'interval' => 60,
				'display'  => __( 'Every Minute (EventFlow Queue)', 'eventflow' ),
			);
		}
		return $schedules;
	}

	/**
	 * Schedule background cron
	 */
	public static function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 30, 'eventflow_two_minutes', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule background cron
	 */
	public static function unschedule_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Classify whether an email failure is permanent (fatal) or temporary (retryable)
	 *
	 * @param string $error_message
	 * @param string $recipient_email
	 * @return bool True if permanent (do not retry), false if temporary
	 */
	public static function is_permanent_failure( $error_message, $recipient_email = '' ) {
		if ( ! empty( $recipient_email ) && ! is_email( $recipient_email ) ) {
			return true;
		}

		$error_lower = strtolower( (string) $error_message );
		$permanent_patterns = array(
			'550', '551', '552', '553', '554', '5.1.1', '5.1.2', '5.1.3', '5.7.1',
			'user unknown', 'mailbox unavailable', 'no such user', 'recipient address rejected',
			'invalid address', 'domain not found', 'unrouteable address', 'mailbox not found',
			'address rejected', 'relay access denied', 'user does not exist',
		);

		foreach ( $permanent_patterns as $pattern ) {
			if ( false !== strpos( $error_lower, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Synchronize queue state to email_logs using exact identifiers
	 *
	 * @param object      $item Queue item row
	 * @param string      $status New status
	 * @param string|null $error_msg Error message
	 * @param int         $retry_count Attempts
	 * @param string      $provider_resp Response string
	 */
	public static function sync_log_status( $item, $status, $error_msg = null, $retry_count = 0, $provider_resp = '' ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return;
		}
		$table_logs = EventFlow_DB::table( 'email_logs' );
		$now = current_time( 'mysql' );

		$update_data = array(
			'status'      => $status,
			'retry_count' => (int) $retry_count,
		);

		if ( null !== $error_msg ) {
			$update_data['error_message'] = $error_msg;
		}
		if ( ! empty( $provider_resp ) ) {
			$update_data['provider_response'] = $provider_resp;
		}
		if ( in_array( $status, array( 'accepted', 'sent' ), true ) ) {
			$update_data['sent_at']       = $now;
			$update_data['error_message'] = null;
		}

		// 1. Exact match by log_id if present
		if ( ! empty( $item->log_id ) ) {
			$wpdb->update( $table_logs, $update_data, array( 'id' => absint( $item->log_id ) ) );
			return;
		}

		// 2. Exact match by dispatch_id if present
		if ( ! empty( $item->dispatch_id ) ) {
			$wpdb->update( $table_logs, $update_data, array( 'dispatch_id' => sanitize_text_field( $item->dispatch_id ) ) );
			return;
		}

		// 3. Fallback match by recipient and subject
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_logs}
				 SET status = %s, retry_count = %d, error_message = %s, provider_response = %s
				 WHERE recipient_email = %s AND subject = %s
				 ORDER BY id DESC LIMIT 1",
				$status,
				(int) $retry_count,
				$error_msg,
				$provider_resp,
				$item->recipient_email,
				$item->subject
			)
		);
	}

	/**
	 * Enqueue an email for asynchronous delivery
	 *
	 * @param array $data
	 * @return int|bool Queue ID or false on duplicate/failure
	 */
	public static function enqueue( $data ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return false;
		}
		$table_queue = EventFlow_DB::table( 'email_queue' );
		$table_logs  = EventFlow_DB::table( 'email_logs' );

		$recipient_email = isset( $data['recipient_email'] ) ? sanitize_email( $data['recipient_email'] ) : '';
		if ( empty( $recipient_email ) || ! is_email( $recipient_email ) ) {
			return false;
		}

		$dispatch_id     = isset( $data['dispatch_id'] ) && ! empty( $data['dispatch_id'] )
			? sanitize_text_field( $data['dispatch_id'] )
			: ( class_exists( 'EventFlow_Email_Manager' ) ? EventFlow_Email_Manager::generate_dispatch_id() : sprintf( 'EF-DISP-%s-%s', date( 'YmdHis' ), substr( md5( uniqid( '', true ) ), 0, 4 ) ) );
		$workflow        = isset( $data['workflow'] ) && ! empty( $data['workflow'] )
			? sanitize_text_field( $data['workflow'] )
			: ( class_exists( 'EventFlow_Email_Manager' ) ? EventFlow_Email_Manager::determine_workflow( isset( $data['template_key'] ) ? $data['template_key'] : '' ) : 'general' );

		$template_key    = isset( $data['template_key'] ) ? sanitize_text_field( $data['template_key'] ) : '';
		$recipient_name  = isset( $data['recipient_name'] ) ? sanitize_text_field( $data['recipient_name'] ) : '';
		$recipient_type  = isset( $data['recipient_type'] ) ? sanitize_text_field( $data['recipient_type'] ) : 'solo';
		$subject         = isset( $data['subject'] ) ? sanitize_text_field( $data['subject'] ) : '';
		$body            = isset( $data['body'] ) ? $data['body'] : '';
		$headers         = isset( $data['headers'] ) ? ( is_array( $data['headers'] ) ? implode( "\r\n", $data['headers'] ) : $data['headers'] ) : '';
		$registration_id = isset( $data['registration_id'] ) ? sanitize_text_field( $data['registration_id'] ) : null;
		$placeholders    = isset( $data['placeholders'] ) ? wp_json_encode( $data['placeholders'] ) : null;

		// Idempotency Key computation
		$idempotency_key = isset( $data['idempotency_key'] ) && ! empty( $data['idempotency_key'] )
			? sanitize_text_field( $data['idempotency_key'] )
			: md5( sprintf( '%s|%s|%s|%s|%s', $template_key, $recipient_email, (string) $registration_id, $subject, date( 'YmdH' ) ) );

		// Duplicate Prevention Check: Check if an item with this key is pending, processing, or sent in the last 12 hours
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, status, attempts FROM {$table_queue} WHERE idempotency_key = %s LIMIT 1",
				$idempotency_key
			)
		);

		if ( $existing ) {
			// Already queued or dispatched
			return (int) $existing->id;
		}

		$max_attempts = (int) get_option( 'eventflow_email_max_retries', 3 );
		if ( $max_attempts < 1 ) {
			$max_attempts = 3;
		}

		$now          = current_time( 'mysql' );
		$scheduled_at = isset( $data['scheduled_at'] ) ? $data['scheduled_at'] : $now;

		// Record in Email Logs first with 'queued' status to obtain log_id
		$sender_email = class_exists( 'EventFlow_Email_Manager' ) ? EventFlow_Email_Manager::get_sender_email() : EventFlow_Email_Manager::SENDER_EMAIL;
		$wpdb->insert(
			$table_logs,
			array(
				'dispatch_id'       => $dispatch_id,
				'workflow'          => $workflow,
				'template_key'      => $template_key,
				'sender_email'      => $sender_email,
				'recipient_email'   => $recipient_email,
				'recipient_name'    => $recipient_name,
				'recipient_type'    => $recipient_type,
				'subject'           => $subject,
				'body'              => $body,
				'headers'           => $headers,
				'registration_id'   => $registration_id,
				'status'            => 'queued',
				'retry_count'       => 0,
				'error_message'     => null,
				'provider_response' => 'Queued for background delivery',
				'sent_at'           => $now,
				'created_at'        => $now,
			)
		);
		$log_id = (int) $wpdb->insert_id;

		$inserted = $wpdb->insert(
			$table_queue,
			array(
				'dispatch_id'       => $dispatch_id,
				'workflow'          => $workflow,
				'log_id'            => $log_id,
				'template_key'      => $template_key,
				'recipient_email'   => $recipient_email,
				'recipient_name'    => $recipient_name,
				'recipient_type'    => $recipient_type,
				'subject'           => $subject,
				'body'              => $body,
				'headers'           => $headers,
				'placeholders_json' => $placeholders,
				'idempotency_key'   => $idempotency_key,
				'registration_id'   => $registration_id,
				'status'            => 'pending',
				'attempts'          => 0,
				'max_attempts'      => $max_attempts,
				'scheduled_at'      => $scheduled_at,
				'created_at'        => $now,
				'updated_at'        => $now,
			)
		);

		if ( ! $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Process a batch of queued emails
	 *
	 * @param int $batch_size
	 * @return array Results summary
	 */
	public static function process_queue( $batch_size = 0 ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return array( 'processed' => 0, 'sent' => 0, 'failed' => 0, 'retrying' => 0 );
		}
		$table_queue = EventFlow_DB::table( 'email_queue' );
		$table_logs  = EventFlow_DB::table( 'email_logs' );

		if ( ! $batch_size ) {
			$batch_size = (int) get_option( 'eventflow_email_batch_size', 25 );
		}
		if ( $batch_size < 1 ) {
			$batch_size = 25;
		}

		$now = current_time( 'mysql' );

		// 1. Stuck Job Recovery: recover items stuck in 'processing' for > 5 minutes due to worker crash or timeout
		$stuck_cutoff = date( 'Y-m-d H:i:s', time() - 300 );
		$stuck_items  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_queue} WHERE status = 'processing' AND updated_at < %s LIMIT 25",
				$stuck_cutoff
			)
		);
		if ( ! empty( $stuck_items ) ) {
			foreach ( $stuck_items as $stuck ) {
				$stuck_attempts = (int) $stuck->attempts + 1;
				if ( $stuck_attempts >= (int) $stuck->max_attempts ) {
					$wpdb->update(
						$table_queue,
						array(
							'status'     => 'failed',
							'attempts'   => $stuck_attempts,
							'last_error' => __( 'Worker timeout or process interrupted; max attempts reached.', 'eventflow' ),
							'updated_at' => $now,
						),
						array( 'id' => $stuck->id )
					);
					self::sync_log_status( $stuck, 'failed', __( 'Worker timeout or process interrupted; max attempts reached.', 'eventflow' ), $stuck_attempts );
				} else {
					$wpdb->update(
						$table_queue,
						array(
							'status'       => 'retrying',
							'attempts'     => $stuck_attempts,
							'scheduled_at' => $now,
							'last_error'   => __( 'Worker timeout or process interrupted; automatically recovered.', 'eventflow' ),
							'updated_at'   => $now,
						),
						array( 'id' => $stuck->id )
					);
					self::sync_log_status( $stuck, 'retrying', __( 'Worker timeout or process interrupted; automatically recovered.', 'eventflow' ), $stuck_attempts );
				}
			}
		}

		// 2. Find pending or eligible retrying items
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_queue}
				 WHERE ( status = 'pending' AND scheduled_at <= %s )
				    OR ( status = 'retrying' AND scheduled_at <= %s )
				 ORDER BY scheduled_at ASC, id ASC
				 LIMIT %d",
				$now,
				$now,
				$batch_size
			)
		);

		if ( empty( $items ) ) {
			return array(
				'processed' => 0,
				'sent'      => 0,
				'failed'    => 0,
				'retrying'  => 0,
			);
		}

		$processed_count = 0;
		$sent_count      = 0;
		$failed_count    = 0;
		$retrying_count  = 0;

		foreach ( $items as $item ) {
			// Atomic Concurrency Lock: Claim item only if still pending or retrying
			$locked = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table_queue}
					 SET status = 'processing', updated_at = %s
					 WHERE id = %d AND status IN ('pending', 'retrying')",
					current_time( 'mysql' ),
					$item->id
				)
			);

			if ( ! $locked ) {
				// Claimed by another worker
				continue;
			}

			EventFlow_Email_Manager::clear_last_error();

			// Prepare headers
			$headers = ! empty( $item->headers ) ? explode( "\r\n", $item->headers ) : array();

			// Resolve subject and body defensively
			$subject = ! empty( $item->subject ) ? $item->subject : '';
			$body    = ! empty( $item->body ) ? $item->body : '';

			if ( empty( $body ) && ! empty( $item->template_key ) ) {
				$tpl = EventFlow_Email_Manager::get_template_by_key( $item->template_key );
				if ( $tpl ) {
					$placeholders = ! empty( $item->placeholders_json ) ? json_decode( $item->placeholders_json, true ) : array();
					$prepared     = EventFlow_Email_Manager::prepare_message( $tpl, $item->recipient_email, $placeholders, $subject );
					$subject      = $prepared['subject'];
					$body         = $prepared['content'];
				}
			}

			// Dispatch email using native engine
			$sent = EventFlow_Email_Manager::send_raw(
				$item->recipient_email,
				$subject,
				$body,
				$headers
			);

			$processed_count++;
			$now_after = current_time( 'mysql' );

			if ( $sent ) {
				$sent_count++;

				$wpdb->update(
					$table_queue,
					array(
						'status'            => 'accepted',
						'sent_at'           => $now_after,
						'provider_response' => 'Accepted by WordPress native mail transport',
						'last_error'        => null,
						'updated_at'        => $now_after,
					),
					array( 'id' => $item->id )
				);

				self::sync_log_status(
					$item,
					'accepted',
					null,
					(int) $item->attempts,
					'Accepted by WordPress native mail transport'
				);
			} else {
				$attempts     = (int) $item->attempts + 1;
				$last_err     = EventFlow_Email_Manager::get_last_error();
				$err_msg      = $last_err ? $last_err->get_error_message() : __( 'wp_mail returned false. Check server mail transport configuration.', 'eventflow' );
				$is_permanent = self::is_permanent_failure( $err_msg, $item->recipient_email );

				if ( $is_permanent || $attempts >= (int) $item->max_attempts ) {
					// Hard failure (Permanent error code or max attempts reached)
					$failed_count++;
					$fail_reason = $is_permanent
						? sprintf( __( 'Permanent failure: %s (will not retry)', 'eventflow' ), $err_msg )
						: $err_msg;

					$wpdb->update(
						$table_queue,
						array(
							'status'     => 'failed',
							'attempts'   => $attempts,
							'last_error' => $fail_reason,
							'updated_at' => $now_after,
						),
						array( 'id' => $item->id )
					);

					self::sync_log_status(
						$item,
						'failed',
						$fail_reason,
						$attempts,
						'Transport Failure: ' . $fail_reason
					);
				} else {
					// Staged exponential backoff retry: 2 min, 8 min, 30 min
					$retrying_count++;
					$delay_seconds = (int) pow( 2, $attempts ) * 120;
					$next_schedule = date( 'Y-m-d H:i:s', time() + $delay_seconds );

					$wpdb->update(
						$table_queue,
						array(
							'status'       => 'retrying',
							'attempts'     => $attempts,
							'scheduled_at' => $next_schedule,
							'last_error'   => $err_msg,
							'updated_at'   => $now_after,
						),
						array( 'id' => $item->id )
					);

					self::sync_log_status(
						$item,
						'retrying',
						$err_msg,
						$attempts,
						sprintf( 'Transport Retry Scheduled (Attempt %d/%d)', $attempts, (int) $item->max_attempts )
					);
				}
			}
		}

		return array(
			'processed' => $processed_count,
			'sent'      => $sent_count,
			'failed'    => $failed_count,
			'retrying'  => $retrying_count,
		);
	}

	/**
	 * Get aggregated queue stats
	 *
	 * @return array
	 */
	public static function get_queue_stats() {
		global $wpdb;
		$table = EventFlow_DB::table( 'email_queue' );

		$counts = array(
			'pending'    => 0,
			'processing' => 0,
			'retrying'   => 0,
			'sent'       => 0,
			'accepted'   => 0,
			'failed'     => 0,
			'total'      => 0,
		);

		$rows = $wpdb->get_results( "SELECT status, COUNT(*) as cnt FROM {$table} GROUP BY status" );
		if ( ! empty( $rows ) ) {
			foreach ( $rows as $r ) {
				if ( isset( $counts[ $r->status ] ) ) {
					$counts[ $r->status ] += (int) $r->cnt;
				}
				if ( 'accepted' === $r->status ) {
					$counts['sent'] += (int) $r->cnt;
				} elseif ( 'sent' === $r->status ) {
					$counts['accepted'] += (int) $r->cnt;
				}
				$counts['total'] += (int) $r->cnt;
			}
		}

		return $counts;
	}

	/**
	 * Manually retry a specific queued item
	 *
	 * @param int $queue_id
	 * @return bool
	 */
	public static function retry_item( $queue_id ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return false;
		}
		$table_queue = EventFlow_DB::table( 'email_queue' );
		$item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_queue} WHERE id = %d LIMIT 1", absint( $queue_id ) ) );
		if ( ! $item ) {
			return false;
		}

		$updated = (bool) $wpdb->update(
			$table_queue,
			array(
				'status'       => 'pending',
				'attempts'     => 0,
				'scheduled_at' => current_time( 'mysql' ),
				'last_error'   => null,
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => absint( $queue_id ) )
		);

		if ( $updated ) {
			self::sync_log_status( $item, 'queued', null, 0, 'Re-queued for delivery via admin action' );
		}

		return $updated;
	}

	/**
	 * Retry all failed items in the queue
	 *
	 * @return int Number of reset items
	 */
	public static function retry_all_failed() {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return 0;
		}
		$table_queue = EventFlow_DB::table( 'email_queue' );

		$failed_items = $wpdb->get_results( "SELECT * FROM {$table_queue} WHERE status = 'failed'" );
		$count = 0;
		if ( ! empty( $failed_items ) ) {
			foreach ( $failed_items as $item ) {
				if ( self::retry_item( $item->id ) ) {
					$count++;
				}
			}
		}
		return $count;
	}

	/**
	 * Cancel a queued item
	 *
	 * @param int $queue_id
	 * @return bool
	 */
	public static function cancel_item( $queue_id ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'email_queue' );

		return (bool) $wpdb->update(
			$table,
			array(
				'status'     => 'cancelled',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $queue_id ) )
		);
	}

	/**
	 * Purge completed sent emails older than specified hours
	 *
	 * @param int $hours
	 * @return int Deleted count
	 */
	public static function clear_completed( $hours = 48 ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'email_queue' );
		$cutoff = date( 'Y-m-d H:i:s', time() - ( $hours * 3600 ) );

		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE ( status = 'sent' OR status = 'cancelled' ) AND updated_at <= %s",
				$cutoff
			)
		);
	}
}
