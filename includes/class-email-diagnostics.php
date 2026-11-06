<?php
/**
 * SIBACOM EVENT - Native Email Transport Diagnostics & Health Inspector
 *
 * Verifies WordPress mail API availability, PHP mail transport capabilities,
 * sender identity alignment, DNS records (SPF, DKIM, DMARC) for portal.sibacom.tech,
 * WP-Cron status, and logs transport success/failure timestamps.
 *
 * @package EventFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Email_Diagnostics {

	const TARGET_DOMAIN = 'portal.sibacom.tech';
	const ROOT_DOMAIN   = 'sibacom.tech';
	const SENDER_EMAIL  = 'info@portal.sibacom.tech';
	const SENDER_NAME   = 'SIBACOM EVENT';

	/**
	 * Run full native email system diagnosis
	 *
	 * @return array
	 */
	public static function run_full_diagnosis() {
		return array(
			'system'            => self::check_system_environment(),
			'sender_identity'   => self::check_sender_identity(),
			'smtp'              => self::check_smtp_transport(),
			'transport_history' => self::get_transport_history(),
			'triggers'          => self::get_triggers_status(),
			'dns'               => self::check_domain_dns( self::TARGET_DOMAIN ),
			'cron'              => self::check_cron_status(),
			'stats'             => self::get_delivery_statistics(),
		);
	}

	/**
	 * Check WordPress mail API and PHP server mail transport availability
	 *
	 * @return array
	 */
	public static function check_system_environment() {
		$checks = array();

		// 1. WordPress Mail API
		$wp_mail_exists = function_exists( 'wp_mail' );
		$checks[] = array(
			'label'   => __( 'WordPress Mail API (wp_mail)', 'eventflow' ),
			'status'  => $wp_mail_exists ? 'pass' : 'fail',
			'value'   => $wp_mail_exists ? __( 'Available & Active', 'eventflow' ) : __( 'Missing / Pluggable conflict', 'eventflow' ),
			'message' => $wp_mail_exists
				? __( 'WordPress native mail API is loaded and ready for dispatch.', 'eventflow' )
				: __( 'wp_mail() is not available. Check if another plugin prematurely redeclared it.', 'eventflow' ),
		);

		// 2. PHP Mail Transport Function & Disabled Functions
		$has_php_mail = function_exists( 'mail' );
		$disabled_funcs = ini_get( 'disable_functions' );
		$disabled_arr   = array_map( 'trim', explode( ',', (string) $disabled_funcs ) );
		$mail_disabled  = in_array( 'mail', $disabled_arr, true );

		if ( ! $has_php_mail || $mail_disabled ) {
			$checks[] = array(
				'label'   => __( 'PHP mail() Function', 'eventflow' ),
				'status'  => 'fail',
				'value'   => __( 'Disabled by Hosting Security Policy', 'eventflow' ),
				'message' => __( 'The native PHP mail() function is disabled in php.ini via disable_functions. The hosting server will reject native email sending until mail() is re-enabled.', 'eventflow' ),
			);
		} else {
			// Inspect platform-specific transport
			$is_windows = ( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' );
			$transport_detail = '';

			if ( $is_windows ) {
				$smtp_host = ini_get( 'SMTP' );
				$smtp_port = ini_get( 'smtp_port' );
				$transport_detail = sprintf( 'Windows Native (Relay to %s:%s)', $smtp_host ? $smtp_host : 'localhost', $smtp_port ? $smtp_port : '25' );
			} else {
				$sendmail_path = ini_get( 'sendmail_path' );
				$transport_detail = $sendmail_path ? sprintf( 'Unix Sendmail (%s)', $sendmail_path ) : 'Default Unix MTA';
			}

			$checks[] = array(
				'label'   => __( 'PHP Server Mail Transport', 'eventflow' ),
				'status'  => 'pass',
				'value'   => $transport_detail,
				'message' => __( 'PHP mail transport is available for handing off messages to the host MTA.', 'eventflow' ),
			);
		}

		// 3. PHP Version
		$php_version = phpversion();
		$php_ok      = version_compare( $php_version, '7.4', '>=' );
		$checks[] = array(
			'label'   => __( 'PHP Version', 'eventflow' ),
			'status'  => $php_ok ? 'pass' : 'fail',
			'value'   => 'PHP ' . $php_version,
			'message' => $php_ok
				? __( 'Meets minimum requirement (PHP >= 7.4).', 'eventflow' )
				: __( 'PHP 7.4 or higher is recommended for secure WordPress mail processing.', 'eventflow' ),
		);

		// 4. WordPress Version
		global $wp_version;
		$wp_ver_ok = version_compare( (string) $wp_version, '5.8', '>=' );
		$checks[] = array(
			'label'   => __( 'WordPress Version', 'eventflow' ),
			'status'  => $wp_ver_ok ? 'pass' : 'warning',
			'value'   => 'WordPress ' . ( $wp_version ? $wp_version : get_bloginfo( 'version' ) ),
			'message' => __( 'Supports modern wp_mail hooks (wp_mail_failed, wp_mail_succeeded).', 'eventflow' ),
		);

		// 5. Multibyte String Extension (UTF-8 Encoding)
		$has_mbstring = extension_loaded( 'mbstring' );
		$checks[] = array(
			'label'   => __( 'Multibyte String (mbstring)', 'eventflow' ),
			'status'  => $has_mbstring ? 'pass' : 'warning',
			'value'   => $has_mbstring ? __( 'Installed', 'eventflow' ) : __( 'Missing', 'eventflow' ),
			'message' => $has_mbstring
				? __( 'Supports international characters in participant names and email headers.', 'eventflow' )
				: __( 'Recommended for UTF-8 character encoding in email headers.', 'eventflow' ),
		);

		return $checks;
	}

	/**
	 * Verify SIBACOM EVENT sender identity configuration
	 *
	 * @return array
	 */
	public static function check_sender_identity() {
		$sender_name  = class_exists( 'EventFlow_Email_Manager' ) ? EventFlow_Email_Manager::get_sender_name() : self::SENDER_NAME;
		$sender_email = class_exists( 'EventFlow_Email_Manager' ) ? EventFlow_Email_Manager::get_sender_email() : self::SENDER_EMAIL;
		$reply_to     = class_exists( 'EventFlow_Email_Manager' ) ? EventFlow_Email_Manager::get_reply_to() : self::SENDER_EMAIL;

		return array(
			'sender_name'     => $sender_name,
			'sender_email'    => $sender_email,
			'reply_to'        => $reply_to,
			'sending_method'  => 'WordPress Native wp_mail()',
			'format'          => 'HTML + Plain Text (Multipart/Alternative)',
			'scoped_filters'  => true,
			'status'          => 'pass',
			'message'         => sprintf( __( 'Sender identity is configured as %s <%s> and scoped strictly to plugin-generated emails.', 'eventflow' ), $sender_name, $sender_email ),
		);
	}

	/**
	 * Get transport history (last test, last success, last failure, errors)
	 *
	 * @return array
	 */
	public static function get_transport_history() {
		return array(
			'last_test_attempt'   => get_option( 'eventflow_email_last_test_attempt', __( 'No test emails sent yet.', 'eventflow' ) ),
			'last_success_time'   => get_option( 'eventflow_email_last_success_time', __( 'No successful submission recorded yet.', 'eventflow' ) ),
			'last_failure_time'   => get_option( 'eventflow_email_last_failure_time', __( 'No failures recorded.', 'eventflow' ) ),
			'last_error_message'  => get_option( 'eventflow_email_last_error', __( 'None', 'eventflow' ) ),
		);
	}

	/**
	 * Verify direct SMTP server connectivity if enabled
	 *
	 * @return array
	 */
	public static function check_smtp_transport() {
		$smtp_enabled = get_option( 'eventflow_smtp_enabled', 'no' );
		if ( 'yes' !== $smtp_enabled ) {
			return array(
				'enabled' => false,
				'status'  => 'info',
				'message' => __( 'Direct SMTP is currently disabled. System is utilizing WordPress Native wp_mail() transport.', 'eventflow' ),
			);
		}

		$host = get_option( 'eventflow_smtp_host', '' );
		$port = (int) get_option( 'eventflow_smtp_port', 587 );
		$user = get_option( 'eventflow_smtp_user', '' );

		if ( empty( $host ) ) {
			return array(
				'enabled' => true,
				'status'  => 'warning',
				'message' => __( 'Direct SMTP is enabled, but SMTP Host is empty.', 'eventflow' ),
			);
		}

		$timeout = 5;
		$errno   = 0;
		$errstr  = '';
		$fp = @fsockopen( $host, $port, $errno, $errstr, $timeout );
		if ( ! $fp ) {
			return array(
				'enabled' => true,
				'status'  => 'fail',
				'host'    => $host,
				'port'    => $port,
				'message' => sprintf( __( 'Could not connect to SMTP server %1$s:%2$d (Error %3$d: %4$s). Check server hostname, port, and firewall rules.', 'eventflow' ), $host, $port, $errno, $errstr ),
			);
		}

		$banner = fgets( $fp, 515 );
		fclose( $fp );

		return array(
			'enabled' => true,
			'status'  => 'pass',
			'host'    => $host,
			'port'    => $port,
			'user'    => $user,
			'banner'  => trim( (string) $banner ),
			'message' => sprintf( __( 'Successfully connected to SMTP server %1$s:%2$d. Transport response: %3$s', 'eventflow' ), $host, $port, esc_html( trim( (string) $banner ) ) ),
		);
	}

	/**
	 * Get status of all 23 automated notification triggers
	 *
	 * @return array
	 */
	public static function get_triggers_status() {
		$triggers = array(
			'account_created'             => array( 'title' => 'Participant Account Creation', 'recipient' => 'Participant' ),
			'welcome_email'               => array( 'title' => 'Participant Welcome Email', 'recipient' => 'Participant' ),
			'password_reset'              => array( 'title' => 'Password Reset Request', 'recipient' => 'User' ),
			'password_changed'            => array( 'title' => 'Password Changed Security Alert', 'recipient' => 'User' ),
			'registration_submitted'      => array( 'title' => 'Application / Registration Submission', 'recipient' => 'Participant / Team' ),
			'admin_registration_submitted'=> array( 'title' => 'New Registration Alert', 'recipient' => 'Authorized Admin' ),
			'registration_confirmation'   => array( 'title' => 'Team Member Registration Confirmation', 'recipient' => 'Participant / Team' ),
			'registration_updated'        => array( 'title' => 'Registration Details Updated', 'recipient' => 'Participant / Team' ),
			'registration_cancelled'      => array( 'title' => 'Registration Cancelled', 'recipient' => 'Participant / Team' ),
			'payment_submitted'           => array( 'title' => 'Payment Proof Upload Confirmation', 'recipient' => 'Participant / Team' ),
			'admin_payment_submitted'     => array( 'title' => 'New Payment Submission Alert', 'recipient' => 'Authorized Admin' ),
			'payment_under_verification'  => array( 'title' => 'Payment Under Verification Notice', 'recipient' => 'Participant / Team' ),
			'payment_verified'            => array( 'title' => 'Payment Approved & Verified', 'recipient' => 'Participant / Team' ),
			'payment_rejected'            => array( 'title' => 'Payment Rejected with Exact Reason', 'recipient' => 'Participant / Team' ),
			'payment_reupload_requested'  => array( 'title' => 'Payment Re-upload Requested with Notes', 'recipient' => 'Participant / Team' ),
			'application_approved'        => array( 'title' => 'Application Approved Notification', 'recipient' => 'Participant / Team' ),
			'application_rejected'        => array( 'title' => 'Application Rejected Notification', 'recipient' => 'Participant / Team' ),
			'deadline_reminder'           => array( 'title' => 'Registration Deadline Approaching', 'recipient' => 'Participant / Team' ),
			'activity_announcement'       => array( 'title' => 'New Event / Activity Announcement', 'recipient' => 'Relevant Participants' ),
			'event_update'                => array( 'title' => 'Event Information Updated', 'recipient' => 'Relevant Participants' ),
			'custom_announcement'         => array( 'title' => 'Broadcast Custom Announcement', 'recipient' => 'All Participants' ),
			'attendance_certificate'      => array( 'title' => 'Certificate of Participation Ready', 'recipient' => 'Participant / Team' ),
			'merit_certificate'           => array( 'title' => 'Certificate of Merit / Award Ready', 'recipient' => 'Winner / Team' ),
		);

		$result = array();
		foreach ( $triggers as $key => $meta ) {
			$opt = get_option( 'eventflow_notify_trigger_' . $key, 'yes' );
			$result[] = array(
				'key'       => $key,
				'title'     => $meta['title'],
				'recipient' => $meta['recipient'],
				'enabled'   => ( 'yes' === $opt ),
			);
		}

		return $result;
	}

	/**
	 * Inspect DNS records (SPF, DKIM, DMARC, MX) for portal.sibacom.tech and sibacom.tech
	 *
	 * @param string $domain
	 * @return array
	 */
	public static function check_domain_dns( $domain = self::TARGET_DOMAIN ) {
		if ( empty( $domain ) || 'localhost' === $domain ) {
			$domain = self::TARGET_DOMAIN;
		}

		$results = array(
			'domain'          => $domain,
			'a_record'        => array( 'status' => 'warning', 'found' => false, 'ip' => null, 'message' => '' ),
			'mx'              => array( 'status' => 'warning', 'found' => false, 'records' => array(), 'message' => '' ),
			'spf'             => array( 'status' => 'warning', 'found' => false, 'record' => null, 'message' => '' ),
			'dmarc'           => array( 'status' => 'warning', 'found' => false, 'record' => null, 'message' => '' ),
			'dkim_guidance'   => array( 'status' => 'info', 'message' => '' ),
		);

		// 1. Check A / Host Record for portal.sibacom.tech
		$a_records = @dns_get_record( $domain, DNS_A );
		if ( ! empty( $a_records ) && is_array( $a_records ) ) {
			$ip = isset( $a_records[0]['ip'] ) ? $a_records[0]['ip'] : '';
			$results['a_record'] = array(
				'status'  => 'pass',
				'found'   => true,
				'ip'      => $ip,
				'message' => sprintf( __( 'Domain resolves to IP %s.', 'eventflow' ), $ip ),
			);
		} else {
			$results['a_record'] = array(
				'status'  => 'warning',
				'found'   => false,
				'ip'      => null,
				'message' => sprintf( __( 'DNS A record for "%s" is not yet resolving on public DNS. Verify that the DNS A record points to your server public IP.', 'eventflow' ), $domain ),
			);
		}

		// 2. Check MX Records
		$mx_records = @dns_get_record( $domain, DNS_MX );
		if ( empty( $mx_records ) ) {
			// Fallback check on root domain sibacom.tech
			$mx_records = @dns_get_record( self::ROOT_DOMAIN, DNS_MX );
		}

		if ( ! empty( $mx_records ) && is_array( $mx_records ) ) {
			$hosts = array();
			foreach ( $mx_records as $mx ) {
				$hosts[] = isset( $mx['target'] ) ? $mx['target'] : '';
			}
			$results['mx'] = array(
				'status'  => 'pass',
				'found'   => true,
				'records' => $hosts,
				'message' => sprintf( __( 'Mail exchange (MX) configured: %s', 'eventflow' ), implode( ', ', $hosts ) ),
			);
		} else {
			$results['mx'] = array(
				'status'  => 'warning',
				'found'   => false,
				'records' => array(),
				'message' => sprintf( __( 'No MX records detected for "%1$s" or "%2$s". Incoming replies to info@%1$s cannot be received until MX records and an active mailbox are provisioned.', 'eventflow' ), $domain, self::ROOT_DOMAIN ),
			);
		}

		// 3. Check SPF via TXT records
		$txt_records = @dns_get_record( $domain, DNS_TXT );
		if ( empty( $txt_records ) ) {
			$txt_records = @dns_get_record( self::ROOT_DOMAIN, DNS_TXT );
		}

		$spf_found  = false;
		$spf_record = '';
		if ( ! empty( $txt_records ) && is_array( $txt_records ) ) {
			foreach ( $txt_records as $record ) {
				$txt = isset( $record['txt'] ) ? $record['txt'] : '';
				if ( 0 === strpos( trim( $txt ), 'v=spf1' ) ) {
					$spf_found  = true;
					$spf_record = trim( $txt );
					break;
				}
			}
		}

		$suggested_spf = 'v=spf1 +a +mx ~all';
		if ( $spf_found ) {
			$results['spf'] = array(
				'status'  => 'pass',
				'found'   => true,
				'record'  => $spf_record,
				'message' => sprintf( __( 'SPF TXT record detected: <code>%s</code>', 'eventflow' ), esc_html( $spf_record ) ),
			);
		} else {
			$results['spf'] = array(
				'status'    => 'warning',
				'found'     => false,
				'record'    => null,
				'suggested' => $suggested_spf,
				'message'   => sprintf(
					__( 'No SPF TXT record detected for domain "%1$s". Major email providers (Gmail, Microsoft, Yahoo) may flag messages from info@%1$s as Spam or reject them entirely. Please create a DNS TXT record for "%1$s" with value:<br><code>%2$s</code>', 'eventflow' ),
					esc_html( $domain ),
					esc_html( $suggested_spf )
				),
			);
		}

		// 4. Check DMARC via _dmarc.domain TXT records
		$dmarc_records = @dns_get_record( '_dmarc.' . $domain, DNS_TXT );
		if ( empty( $dmarc_records ) ) {
			$dmarc_records = @dns_get_record( '_dmarc.' . self::ROOT_DOMAIN, DNS_TXT );
		}

		$dmarc_found  = false;
		$dmarc_record = '';
		if ( ! empty( $dmarc_records ) && is_array( $dmarc_records ) ) {
			foreach ( $dmarc_records as $record ) {
				$txt = isset( $record['txt'] ) ? $record['txt'] : '';
				if ( 0 === strpos( trim( $txt ), 'v=DMARC1' ) ) {
					$dmarc_found  = true;
					$dmarc_record = trim( $txt );
					break;
				}
			}
		}

		$suggested_dmarc = 'v=DMARC1; p=quarantine; rua=mailto:info@portal.sibacom.tech';
		if ( $dmarc_found ) {
			$results['dmarc'] = array(
				'status'  => 'pass',
				'found'   => true,
				'record'  => $dmarc_record,
				'message' => sprintf( __( 'DMARC policy detected: <code>%s</code>', 'eventflow' ), esc_html( $dmarc_record ) ),
			);
		} else {
			$results['dmarc'] = array(
				'status'    => 'warning',
				'found'     => false,
				'record'    => null,
				'suggested' => $suggested_dmarc,
				'message'   => sprintf(
					__( 'No DMARC record found at <code>_dmarc.%1$s</code>. Add a DNS TXT record at host <code>_dmarc.%1$s</code> with value:<br><code>%2$s</code>', 'eventflow' ),
					esc_html( $domain ),
					esc_html( $suggested_dmarc )
				),
			);
		}

		// 5. DKIM Guidance
		$results['dkim_guidance'] = array(
			'status'  => 'info',
			'message' => sprintf(
				__( 'DKIM (DomainKeys Identified Mail) is signed at the server MTA level. If using cPanel, DirectAdmin, or Postfix on your hosting server for "%s", enable "DKIM Signing" in the email management section and add the generated public key TXT record to your DNS.', 'eventflow' ),
				esc_html( $domain )
			),
		);

		return $results;
	}

	/**
	 * Check WordPress Cron health and background queue automation
	 *
	 * @return array
	 */
	public static function check_cron_status() {
		$cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$next_run      = class_exists( 'EventFlow_Email_Queue' ) ? wp_next_scheduled( EventFlow_Email_Queue::CRON_HOOK ) : false;

		$status = 'pass';
		if ( ! $next_run ) {
			$status = 'warning';
			$msg = __( 'Queue cron job is not currently scheduled. Background emails will be processed on administrative visits or manual trigger.', 'eventflow' );
		} elseif ( $cron_disabled ) {
			$status = 'info';
			$msg = __( 'DISABLE_WP_CRON is enabled. Ensure a server crontab (e.g. Linux cron / cPanel cron) calls wp-cron.php every 1-2 minutes.', 'eventflow' );
		} else {
			$msg = sprintf(
				__( 'Queue cron is active and scheduled. Next execution in approximately %d seconds.', 'eventflow' ),
				max( 0, (int) $next_run - time() )
			);
		}

		return array(
			'status'        => $status,
			'cron_disabled' => $cron_disabled,
			'next_run'      => $next_run ? date_i18n( 'M j, Y H:i:s', $next_run ) : __( 'None', 'eventflow' ),
			'message'       => $msg,
		);
	}

	/**
	 * Get email delivery statistics from logs
	 *
	 * @return array
	 */
	public static function get_delivery_statistics() {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return array(
				'total'         => 0,
				'accepted'      => 0,
				'sent'          => 0,
				'failed'        => 0,
				'queued'        => 0,
				'retrying'      => 0,
				'completed'     => 0,
				'delivery_rate' => 100.0,
			);
		}
		$table = EventFlow_DB::table( 'email_logs' );

		$total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$accepted  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'accepted' OR status = 'sent'" );
		$failed    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'failed'" );
		$queued    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'queued'" );
		$retrying  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'retrying'" );
		$completed = $accepted + $failed;
		$rate      = $completed > 0 ? round( ( $accepted / $completed ) * 100, 1 ) : ( $total > 0 ? 0.0 : 100.0 );

		return array(
			'total'         => $total,
			'accepted'      => $accepted,
			'sent'          => $accepted, // Backwards-compatible key
			'failed'        => $failed,
			'queued'        => $queued,
			'retrying'      => $retrying,
			'completed'     => $completed,
			'delivery_rate' => $rate,
		);
	}

	/**
	 * Extract domain name from email address
	 *
	 * @param string $email
	 * @return string
	 */
	public static function extract_domain( $email ) {
		if ( empty( $email ) || ! is_email( $email ) ) {
			return self::TARGET_DOMAIN;
		}
		$parts = explode( '@', $email );
		return isset( $parts[1] ) ? strtolower( trim( $parts[1] ) ) : self::TARGET_DOMAIN;
	}

	/**
	 * Suggest SPF record for domain
	 *
	 * @param string $smtp_host
	 * @param string $domain
	 * @return string
	 */
	public static function suggest_spf_record( $smtp_host, $domain ) {
		$lower_host = strtolower( (string) $smtp_host );
		if ( false !== strpos( $lower_host, 'gmail.com' ) || false !== strpos( $lower_host, 'google.com' ) ) {
			return 'v=spf1 include:_spf.google.com ~all';
		}
		if ( false !== strpos( $lower_host, 'hostinger.com' ) ) {
			return 'v=spf1 include:_spf.mail.hostinger.com ~all';
		}
		return 'v=spf1 +a +mx ~all';
	}
}
