<?php
/**
 * EventFlow Real SMTP Engine & Configuration Controller
 *
 * Provides real SMTP delivery via WordPress PHPMailer hooks,
 * secure credential encryption, conflict detection with third-party mailers,
 * and live socket connection & authentication diagnostic testers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_SMTP {

	/**
	 * Last captured mail error
	 *
	 * @var WP_Error|null
	 */
	protected static $last_mail_error = null;

	/**
	 * Captured SMTP debug log lines during testing
	 *
	 * @var array
	 */
	protected static $debug_log = array();

	/**
	 * Whether debug capture is currently active
	 *
	 * @var bool
	 */
	protected static $is_capturing_debug = false;

	/**
	 * Initialize SMTP hooks
	 */
	public static function init() {
		add_action( 'phpmailer_init', array( __CLASS__, 'configure_phpmailer' ), 999 );
		add_action( 'wp_mail_failed', array( __CLASS__, 'capture_mail_failed' ), 10, 1 );
	}

	/**
	 * Get all SMTP & Mailer configuration settings
	 *
	 * @return array
	 */
	public static function get_config() {
		$host       = get_option( 'eventflow_smtp_host', '' );
		$port       = (int) get_option( 'eventflow_smtp_port', 587 );
		$encryption = get_option( 'eventflow_smtp_encryption', 'tls' );
		$auth       = get_option( 'eventflow_smtp_auth', 'yes' );
		$username   = get_option( 'eventflow_smtp_username', '' );
		$password   = get_option( 'eventflow_smtp_password', '' );
		$timeout    = (int) get_option( 'eventflow_smtp_timeout', 15 );
		$mode       = get_option( 'eventflow_mailer_mode', 'eventflow_smtp' );
		$enabled    = get_option( 'eventflow_smtp_enabled', ! empty( $host ) ? 'yes' : 'no' );

		$default_from_name  = class_exists( 'EventFlow_Email_Manager' ) ? EventFlow_Email_Manager::get_sender_name() : 'SIBACOM EVENT';
		$default_from_email = class_exists( 'EventFlow_Email_Manager' ) ? EventFlow_Email_Manager::get_sender_email() : 'info@portal.sibacom.tech';
		$default_reply_to   = class_exists( 'EventFlow_Email_Manager' ) ? EventFlow_Email_Manager::get_reply_to() : $default_from_email;

		$from_name  = get_option( 'eventflow_email_from_name', $default_from_name );
		$from_email = get_option( 'eventflow_email_from_address', $default_from_email );
		$reply_to   = get_option( 'eventflow_email_reply_to', $default_reply_to );

		if ( empty( $from_name ) ) {
			$from_name = $default_from_name;
		}
		if ( empty( $from_email ) || ! is_email( $from_email ) ) {
			$from_email = $default_from_email;
		}
		if ( empty( $reply_to ) || ! is_email( $reply_to ) ) {
			$reply_to = $default_reply_to;
		}

		return array(
			'enabled'       => $enabled,
			'mode'          => $mode,
			'host'          => $host,
			'port'          => $port,
			'encryption'    => $encryption,
			'auth'          => $auth,
			'username'      => $username,
			'password'      => $password, // Stored encrypted
			'has_password'  => ! empty( $password ),
			'timeout'       => $timeout > 0 ? $timeout : 15,
			'from_name'     => $from_name,
			'from_email'    => $from_email,
			'reply_to'      => $reply_to,
		);
	}

	/**
	 * Securely save SMTP settings
	 *
	 * @param array $data
	 * @return bool
	 */
	public static function save_config( $data ) {
		if ( isset( $data['eventflow_mailer_mode'] ) ) {
			update_option( 'eventflow_mailer_mode', sanitize_text_field( $data['eventflow_mailer_mode'] ) );
		}
		if ( isset( $data['eventflow_smtp_enabled'] ) ) {
			update_option( 'eventflow_smtp_enabled', 'yes' === $data['eventflow_smtp_enabled'] ? 'yes' : 'no' );
		}
		if ( isset( $data['eventflow_smtp_host'] ) ) {
			update_option( 'eventflow_smtp_host', sanitize_text_field( trim( $data['eventflow_smtp_host'] ) ) );
		}
		if ( isset( $data['eventflow_smtp_port'] ) ) {
			$port = absint( $data['eventflow_smtp_port'] );
			update_option( 'eventflow_smtp_port', $port > 0 ? $port : 587 );
		}
		if ( isset( $data['eventflow_smtp_encryption'] ) ) {
			$enc = sanitize_text_field( $data['eventflow_smtp_encryption'] );
			if ( in_array( $enc, array( 'tls', 'ssl', 'none' ), true ) ) {
				update_option( 'eventflow_smtp_encryption', $enc );
			}
		}
		if ( isset( $data['eventflow_smtp_auth'] ) ) {
			update_option( 'eventflow_smtp_auth', 'yes' === $data['eventflow_smtp_auth'] ? 'yes' : 'no' );
		}
		if ( isset( $data['eventflow_smtp_username'] ) ) {
			update_option( 'eventflow_smtp_username', sanitize_text_field( trim( $data['eventflow_smtp_username'] ) ) );
		}
		// Handle password securely: only update if a non-placeholder, non-empty value was provided
		if ( ! empty( $data['eventflow_smtp_password'] ) && '••••••••' !== $data['eventflow_smtp_password'] ) {
			$encrypted = self::encrypt_password( $data['eventflow_smtp_password'] );
			update_option( 'eventflow_smtp_password', $encrypted );
		}
		if ( isset( $data['eventflow_smtp_timeout'] ) ) {
			$timeout = absint( $data['eventflow_smtp_timeout'] );
			update_option( 'eventflow_smtp_timeout', $timeout > 0 ? $timeout : 15 );
		}
		if ( isset( $data['eventflow_email_from_name'] ) ) {
			update_option( 'eventflow_email_from_name', sanitize_text_field( $data['eventflow_email_from_name'] ) );
		}
		if ( isset( $data['eventflow_email_from_address'] ) ) {
			update_option( 'eventflow_email_from_address', sanitize_email( $data['eventflow_email_from_address'] ) );
		}
		if ( isset( $data['eventflow_email_reply_to'] ) ) {
			update_option( 'eventflow_email_reply_to', sanitize_email( $data['eventflow_email_reply_to'] ) );
		}

		return true;
	}

	/**
	 * Encrypt sensitive credential before saving to database
	 *
	 * @param string $plaintext
	 * @return string
	 */
	public static function encrypt_password( $plaintext ) {
		if ( empty( $plaintext ) ) {
			return '';
		}

		$key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'eventflow-secret-salt-key-9921';
		$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'eventflow-salt-phrase';

		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv_len = openssl_cipher_iv_length( 'aes-256-cbc' );
			$iv     = openssl_random_pseudo_bytes( $iv_len );
			$cipher = openssl_encrypt( $plaintext, 'aes-256-cbc', hash( 'sha256', $key . $salt ), 0, $iv );
			return 'enc:' . base64_encode( $iv . '::' . $cipher );
		}

		// Fallback simple reversible obfuscation if openssl is missing
		return 'obf:' . base64_encode( self::xor_string( $plaintext, $key ) );
	}

	/**
	 * Decrypt stored credential
	 *
	 * @param string $encrypted
	 * @return string
	 */
	public static function decrypt_password( $encrypted ) {
		if ( empty( $encrypted ) ) {
			return '';
		}

		if ( 0 === strpos( $encrypted, 'enc:' ) ) {
			$raw = base64_decode( substr( $encrypted, 4 ) );
			$parts = explode( '::', $raw, 2 );
			if ( 2 === count( $parts ) && function_exists( 'openssl_decrypt' ) ) {
				$key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'eventflow-secret-salt-key-9921';
				$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'eventflow-salt-phrase';
				$decrypted = openssl_decrypt( $parts[1], 'aes-256-cbc', hash( 'sha256', $key . $salt ), 0, $parts[0] );
				if ( false !== $decrypted ) {
					return $decrypted;
				}
			}
		} elseif ( 0 === strpos( $encrypted, 'obf:' ) ) {
			$raw = base64_decode( substr( $encrypted, 4 ) );
			$key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'eventflow-secret-salt-key-9921';
			return self::xor_string( $raw, $key );
		}

		// Legacy plain-text or directly decrypted string
		return $encrypted;
	}

	/**
	 * XOR string helper for fallback obfuscation
	 *
	 * @param string $str
	 * @param string $key
	 * @return string
	 */
	protected static function xor_string( $str, $key ) {
		$out = '';
		$key_len = strlen( $key );
		for ( $i = 0, $n = strlen( $str ); $i < $n; $i++ ) {
			$out .= $str[ $i ] ^ $key[ $i % $key_len ];
		}
		return $out;
	}

	/**
	 * Detect conflicts with existing WordPress SMTP plugins
	 *
	 * @return array
	 */
	public static function detect_conflicts() {
		$conflicts = array();

		if ( class_exists( 'WPMailSMTP\Core' ) || defined( 'WPMS_PLUGIN_VER' ) ) {
			$conflicts[] = array(
				'slug'        => 'wp-mail-smtp',
				'name'        => 'WP Mail SMTP',
				'description' => __( 'WP Mail SMTP is actively installed. When multiple plugins bind to phpmailer_init, configuration conflicts can arise.', 'eventflow' ),
			);
		}

		if ( class_exists( 'EasyWPSmtp' ) || defined( 'EASY_WP_SMTP_VER' ) ) {
			$conflicts[] = array(
				'slug'        => 'easy-wp-smtp',
				'name'        => 'Easy WP SMTP',
				'description' => __( 'Easy WP SMTP is actively installed.', 'eventflow' ),
			);
		}

		if ( class_exists( 'Postman' ) || class_exists( 'PostSMTP' ) ) {
			$conflicts[] = array(
				'slug'        => 'post-smtp',
				'name'        => 'Post SMTP Mailer',
				'description' => __( 'Post SMTP is actively installed.', 'eventflow' ),
			);
		}

		if ( defined( 'FLUENTMAIL' ) || class_exists( 'FluentMail\App\Services\Mailer\Manager' ) ) {
			$conflicts[] = array(
				'slug'        => 'fluent-smtp',
				'name'        => 'FluentSMTP',
				'description' => __( 'FluentSMTP is actively installed.', 'eventflow' ),
			);
		}

		return $conflicts;
	}

	/**
	 * Configure PHPMailer via the phpmailer_init hook
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer|PHPMailer $phpmailer
	 */
	public static function configure_phpmailer( $phpmailer ) {
		$config = self::get_config();

		// If user selected to use WordPress / Third-party mailer, do not override
		if ( 'wp_mail' === $config['mode'] || 'yes' !== $config['enabled'] || empty( $config['host'] ) ) {
			return;
		}

		try {
			$phpmailer->isSMTP();
			$phpmailer->Host = $config['host'];
			$phpmailer->Port = (int) $config['port'];

			if ( 'none' === $config['encryption'] ) {
				$phpmailer->SMTPSecure = '';
				if ( property_exists( $phpmailer, 'SMTPAutoTLS' ) ) {
					$phpmailer->SMTPAutoTLS = false;
				}
			} else {
				$phpmailer->SMTPSecure = $config['encryption']; // 'tls' or 'ssl'
				if ( property_exists( $phpmailer, 'SMTPAutoTLS' ) ) {
					$phpmailer->SMTPAutoTLS = true;
				}
			}

			if ( 'yes' === $config['auth'] ) {
				$phpmailer->SMTPAuth = true;
				$phpmailer->Username = $config['username'];
				$phpmailer->Password = self::decrypt_password( $config['password'] );
			} else {
				$phpmailer->SMTPAuth = false;
			}

			$phpmailer->Timeout = (int) $config['timeout'];

			// Set Sender Identity
			if ( ! empty( $config['from_email'] ) && is_email( $config['from_email'] ) ) {
				$phpmailer->From = $config['from_email'];
				$phpmailer->Sender = $config['from_email'];
			}
			if ( ! empty( $config['from_name'] ) ) {
				$phpmailer->FromName = $config['from_name'];
			}

			// Reply-To
			if ( ! empty( $config['reply_to'] ) && is_email( $config['reply_to'] ) ) {
				$phpmailer->clearReplyTos();
				$phpmailer->addReplyTo( $config['reply_to'], ! empty( $config['from_name'] ) ? $config['from_name'] : '' );
			}

			// Ensure UTF-8 & Base64 transfer encoding
			$phpmailer->CharSet = 'UTF-8';
			$phpmailer->Encoding = 'base64';

			// Multi-part alternative plain text for optimal deliverability
			if ( 'text/html' === $phpmailer->ContentType && ! empty( $phpmailer->Body ) && empty( $phpmailer->AltBody ) ) {
				$phpmailer->AltBody = wp_strip_all_tags( $phpmailer->Body );
			}

			// Live debug capture if enabled
			if ( self::$is_capturing_debug ) {
				$phpmailer->SMTPDebug = 2; // Client & Server messages
				$phpmailer->Debugoutput = function ( $str, $level ) {
					// Strip sensitive passwords from debug logs
					$sanitized = preg_replace( '/(AUTH\s+PLAIN|AUTH\s+LOGIN|334\s+UGFzc3dvcmQ6)\s+[A-Za-z0-9+\/=]+/i', '$1 [REDACTED]', $str );
					self::$debug_log[] = array(
						'time'    => microtime( true ),
						'level'   => $level,
						'message' => trim( $sanitized ),
					);
				};
			}
		} catch ( Exception $e ) {
			self::$last_mail_error = new WP_Error( 'phpmailer_exception', $e->getMessage() );
		}
	}

	/**
	 * Capture wp_mail_failed event
	 *
	 * @param WP_Error $error
	 */
	public static function capture_mail_failed( $error ) {
		self::$last_mail_error = $error;
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
	 * Clear last error state
	 */
	public static function clear_last_error() {
		self::$last_mail_error = null;
		self::$debug_log = array();
	}

	/**
	 * Begin capturing SMTP debug output
	 */
	public static function start_debug_capture() {
		self::$is_capturing_debug = true;
		self::$debug_log = array();
	}

	/**
	 * End debug capture and return captured log lines
	 *
	 * @return array
	 */
	public static function stop_debug_capture() {
		self::$is_capturing_debug = false;
		return self::$debug_log;
	}

	/**
	 * Test low-level TCP/IP Socket connection & TLS handshake with SMTP Host
	 *
	 * @param string $host
	 * @param int    $port
	 * @param string $encryption ('tls', 'ssl', 'none')
	 * @param int    $timeout
	 * @return array
	 */
	public static function test_socket_connection( $host, $port, $encryption = 'tls', $timeout = 10 ) {
		$steps = array();
		$t0 = microtime( true );

		if ( empty( $host ) ) {
			return array(
				'success' => false,
				'message' => __( 'SMTP Host cannot be blank.', 'eventflow' ),
				'steps'   => array(
					array( 'step' => 'Validation', 'status' => 'fail', 'message' => 'No host specified.' ),
				),
			);
		}

		// 1. DNS Resolution
		$ip = gethostbyname( $host );
		if ( $ip === $host && ! filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'DNS lookup failed for %s. Verify the SMTP host spelling.', 'eventflow' ), $host ),
				'steps'   => array(
					array( 'step' => 'DNS Resolution', 'status' => 'fail', 'message' => "Could not resolve domain: {$host}" ),
				),
			);
		}
		$steps[] = array(
			'step'    => 'DNS Resolution',
			'status'  => 'pass',
			'message' => sprintf( 'Resolved %s to %s', $host, $ip ),
		);

		// 2. Socket Connection
		$target = ( 'ssl' === $encryption ) ? 'ssl://' . $host : $host;
		$errno  = 0;
		$errstr = '';

		$context = stream_context_create(
			array(
				'ssl' => array(
					'verify_peer'       => false,
					'verify_peer_name'  => false,
					'allow_self_signed' => true,
				),
			)
		);

		$fp = @stream_socket_client(
			( 'ssl' === $encryption ? 'ssl://' : 'tcp://' ) . $host . ':' . $port,
			$errno,
			$errstr,
			$timeout,
			STREAM_CLIENT_CONNECT,
			$context
		);

		if ( ! $fp ) {
			$guidance = self::diagnose_connection_error( $errno, $errstr, $host, $port, $encryption );
			return array(
				'success'  => false,
				'message'  => sprintf( __( 'Connection failed to %1$s:%2$d – %3$s', 'eventflow' ), $host, $port, $errstr ),
				'guidance' => $guidance,
				'steps'    => array_merge( $steps, array(
					array( 'step' => 'Socket Connection', 'status' => 'fail', 'message' => "({$errno}) {$errstr}" ),
				) ),
			);
		}

		stream_set_timeout( $fp, $timeout );
		$conn_time = round( ( microtime( true ) - $t0 ) * 1000 );
		$steps[] = array(
			'step'    => 'Socket Connection',
			'status'  => 'pass',
			'message' => sprintf( 'Connected to %s:%d in %d ms', $host, $port, $conn_time ),
		);

		// 3. Server Greeting
		$greeting = trim( (string) fgets( $fp, 512 ) );
		if ( 0 !== strpos( $greeting, '220' ) ) {
			fclose( $fp );
			return array(
				'success' => false,
				'message' => sprintf( __( 'Unexpected SMTP Server Greeting: %s', 'eventflow' ), $greeting ),
				'steps'   => array_merge( $steps, array(
					array( 'step' => 'Server Greeting', 'status' => 'fail', 'message' => $greeting ),
				) ),
			);
		}
		$steps[] = array(
			'step'    => 'Server Greeting',
			'status'  => 'pass',
			'message' => $greeting,
		);

		// 4. EHLO Handshake
		fputs( $fp, "EHLO " . ( isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : 'eventflow.local' ) . "\r\n" );
		$capabilities = array();
		while ( $line = fgets( $fp, 512 ) ) {
			$capabilities[] = trim( $line );
			if ( substr( $line, 3, 1 ) === ' ' || ! substr( $line, 3, 1 ) ) {
				break;
			}
		}
		$steps[] = array(
			'step'    => 'EHLO Handshake',
			'status'  => 'pass',
			'message' => 'Capabilities negotiated successfully.',
		);

		// 5. STARTTLS Handshake (if TLS requested)
		if ( 'tls' === $encryption ) {
			fputs( $fp, "STARTTLS\r\n" );
			$tls_resp = trim( (string) fgets( $fp, 512 ) );
			if ( 0 !== strpos( $tls_resp, '220' ) ) {
				fclose( $fp );
				return array(
					'success' => false,
					'message' => sprintf( __( 'STARTTLS rejected by server: %s', 'eventflow' ), $tls_resp ),
					'steps'   => array_merge( $steps, array(
						array( 'step' => 'STARTTLS Handshake', 'status' => 'fail', 'message' => $tls_resp ),
					) ),
				);
			}

			$crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
			if ( defined( 'STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT' ) ) {
				$crypto_method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
				if ( defined( 'STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT' ) ) {
					$crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
				}
			}
			$crypto_ok = @stream_socket_enable_crypto( $fp, true, $crypto_method );
			if ( ! $crypto_ok ) {
				fclose( $fp );
				return array(
					'success' => false,
					'message' => __( 'TLS cryptographic handshake failed. Verify server SSL certificate validity.', 'eventflow' ),
					'steps'   => array_merge( $steps, array(
						array( 'step' => 'TLS Cryptography', 'status' => 'fail', 'message' => 'Crypto negotiation rejected.' ),
					) ),
				);
			}

			$steps[] = array(
				'step'    => 'TLS Encryption',
				'status'  => 'pass',
				'message' => 'Encrypted TLS tunnel established successfully.',
			);
		}

		fputs( $fp, "QUIT\r\n" );
		fclose( $fp );

		return array(
			'success'      => true,
			'message'      => sprintf( __( 'SMTP connection and encryption verified successfully with %s:%d.', 'eventflow' ), $host, $port ),
			'steps'        => $steps,
			'capabilities' => $capabilities,
		);
	}

	/**
	 * Test SMTP Authentication credentials
	 *
	 * @param string $host
	 * @param int    $port
	 * @param string $encryption
	 * @param string $username
	 * @param string $password
	 * @param int    $timeout
	 * @return array
	 */
	public static function test_smtp_auth( $host, $port, $encryption, $username, $password, $timeout = 10 ) {
		$socket_res = self::test_socket_connection( $host, $port, $encryption, $timeout );
		if ( ! $socket_res['success'] ) {
			return $socket_res;
		}

		$steps = $socket_res['steps'];

		$fp = @stream_socket_client(
			( 'ssl' === $encryption ? 'ssl://' : 'tcp://' ) . $host . ':' . $port,
			$errno,
			$errstr,
			$timeout,
			STREAM_CLIENT_CONNECT
		);

		if ( ! $fp ) {
			return array(
				'success' => false,
				'message' => __( 'Reconnection failed during auth probe.', 'eventflow' ),
				'steps'   => $steps,
			);
		}

		stream_set_timeout( $fp, $timeout );
		fgets( $fp, 512 ); // greeting

		// EHLO
		fputs( $fp, "EHLO eventflow.local\r\n" );
		while ( $line = fgets( $fp, 512 ) ) {
			if ( substr( $line, 3, 1 ) === ' ' || ! substr( $line, 3, 1 ) ) {
				break;
			}
		}

		// TLS if needed
		if ( 'tls' === $encryption ) {
			fputs( $fp, "STARTTLS\r\n" );
			fgets( $fp, 512 );
			stream_socket_enable_crypto( $fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT );
			fputs( $fp, "EHLO eventflow.local\r\n" );
			while ( $line = fgets( $fp, 512 ) ) {
				if ( substr( $line, 3, 1 ) === ' ' || ! substr( $line, 3, 1 ) ) {
					break;
				}
			}
		}

		// AUTH LOGIN
		fputs( $fp, "AUTH LOGIN\r\n" );
		$resp = trim( (string) fgets( $fp, 512 ) );

		if ( 0 !== strpos( $resp, '334' ) ) {
			fputs( $fp, "QUIT\r\n" );
			fclose( $fp );
			return array(
				'success' => false,
				'message' => sprintf( __( 'AUTH LOGIN not accepted: %s', 'eventflow' ), $resp ),
				'steps'   => array_merge( $steps, array(
					array( 'step' => 'Authentication Method', 'status' => 'fail', 'message' => $resp ),
				) ),
			);
		}

		// Send base64 Username
		fputs( $fp, base64_encode( $username ) . "\r\n" );
		$user_resp = trim( (string) fgets( $fp, 512 ) );

		if ( 0 !== strpos( $user_resp, '334' ) ) {
			fputs( $fp, "QUIT\r\n" );
			fclose( $fp );
			return array(
				'success' => false,
				'message' => sprintf( __( 'Username rejected by SMTP server: %s', 'eventflow' ), $user_resp ),
				'steps'   => array_merge( $steps, array(
					array( 'step' => 'Username Verification', 'status' => 'fail', 'message' => $user_resp ),
				) ),
			);
		}

		// Send base64 Password
		fputs( $fp, base64_encode( $password ) . "\r\n" );
		$auth_resp = trim( (string) fgets( $fp, 512 ) );

		fputs( $fp, "QUIT\r\n" );
		fclose( $fp );

		if ( 0 === strpos( $auth_resp, '235' ) ) {
			$steps[] = array(
				'step'    => 'SMTP Authentication',
				'status'  => 'pass',
				'message' => sprintf( 'Authentication accepted for %s (Code 235: Success)', $username ),
			);
			return array(
				'success' => true,
				'message' => __( 'SMTP Authentication successful! Credentials accepted by mail server.', 'eventflow' ),
				'steps'   => $steps,
			);
		}

		// Detailed actionable diagnosis
		$guidance = self::diagnose_auth_error( $auth_resp, $host, $username );

		$steps[] = array(
			'step'    => 'SMTP Authentication',
			'status'  => 'fail',
			'message' => $auth_resp,
		);

		return array(
			'success'  => false,
			'message'  => sprintf( __( 'Authentication Failed: %s', 'eventflow' ), $auth_resp ),
			'guidance' => $guidance,
			'steps'    => $steps,
		);
	}

	/**
	 * Send an actual real test email with full debug capture
	 *
	 * @param string $recipient_email
	 * @param string $template_key
	 * @return array
	 */
	public static function send_test_email( $recipient_email, $template_key = 'welcome_email' ) {
		if ( empty( $recipient_email ) || ! is_email( $recipient_email ) ) {
			return array(
				'success' => false,
				'message' => __( 'Please enter a valid recipient email address.', 'eventflow' ),
			);
		}

		self::clear_last_error();
		self::start_debug_capture();

		// Dispatch via EventFlow_Email_Manager
		$sent = EventFlow_Email_Manager::send_immediate(
			$template_key,
			$recipient_email,
			array(
				'participant_name' => 'Test Recipient',
				'registration_id'  => 'EF-TEST-9921',
				'team_name'        => 'EventFlow Test Team',
				'activity_name'    => 'Speed Programming Championship',
				'payment_amount'   => 'PKR 1,500.00',
				'payment_status'   => 'Verified',
			),
			array(
				'is_test_email' => true,
			)
		);

		$debug_log = self::stop_debug_capture();
		$last_error = self::get_last_error();

		if ( $sent ) {
			return array(
				'success'   => true,
				'message'   => sprintf( __( 'Test email dispatched and accepted by mail provider for %s!', 'eventflow' ), $recipient_email ),
				'debug_log' => $debug_log,
			);
		}

		$error_msg = $last_error ? $last_error->get_error_message() : __( 'wp_mail() returned false. Check SMTP credentials, firewall, or provider restrictions.', 'eventflow' );

		return array(
			'success'   => false,
			'message'   => sprintf( __( 'Delivery attempt failed: %s', 'eventflow' ), $error_msg ),
			'error'     => $error_msg,
			'debug_log' => $debug_log,
		);
	}

	/**
	 * Provide actionable diagnostics for socket connection errors
	 */
	protected static function diagnose_connection_error( $errno, $errstr, $host, $port, $encryption ) {
		if ( 465 === (int) $port && 'tls' === $encryption ) {
			return __( 'Port 465 typically requires SSL encryption instead of TLS. Change Encryption to "SSL".', 'eventflow' );
		}
		if ( 587 === (int) $port && 'ssl' === $encryption ) {
			return __( 'Port 587 typically requires TLS encryption instead of SSL. Change Encryption to "TLS".', 'eventflow' );
		}
		if ( 10060 === (int) $errno || false !== stripos( $errstr, 'timed out' ) ) {
			return sprintf(
				__( 'Connection timed out on port %1$d. Your web host may be blocking outbound SMTP connections. Check if ports 587 or 465 are permitted by your hosting provider or firewall.', 'eventflow' ),
				$port
			);
		}
		if ( 10061 === (int) $errno || false !== stripos( $errstr, 'refused' ) ) {
			return sprintf(
				__( 'Connection actively refused by %1$s:%2$d. Confirm the host domain and port number.', 'eventflow' ),
				$host,
				$port
			);
		}
		return sprintf( __( 'Socket error (%1$d): %2$s. Verify host, port, and security rules.', 'eventflow' ), $errno, $errstr );
	}

	/**
	 * Provide actionable diagnostics for SMTP authentication errors
	 */
	protected static function diagnose_auth_error( $auth_resp, $host, $username ) {
		if ( false !== stripos( $host, 'gmail.com' ) || false !== stripos( $host, 'googlemail.com' ) ) {
			return __( 'Google / Gmail SMTP requires an "App Password" if 2-Step Verification is active on your Google account. Do NOT use your regular personal Google account password. Generate a 16-character App Password at: https://myaccount.google.com/apppasswords', 'eventflow' );
		}
		if ( false !== stripos( $host, 'hostinger.com' ) ) {
			return __( 'Hostinger SMTP requires your full email address as the username (e.g. info@yourdomain.com). Also verify whether port 465 with SSL is required.', 'eventflow' );
		}
		if ( false !== stripos( $host, 'office365.com' ) || false !== stripos( $host, 'outlook.com' ) ) {
			return __( 'Microsoft 365 / Outlook SMTP requires "SMTP AUTH" to be enabled in your Microsoft 365 Admin Center for this mailbox user.', 'eventflow' );
		}
		return __( 'Invalid username or password. Ensure there are no trailing whitespace characters in the username or password fields.', 'eventflow' );
	}
}
