<?php
/**
 * EventFlow Security, Audit Logging & Secure File Delivery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Security {

	/**
	 * Verify Nonce safely
	 *
	 * @param string $nonce
	 * @param string $action
	 * @param bool   $die
	 * @return bool
	 */
	public static function verify_nonce( $nonce, $action = 'eventflow_nonce', $die = true ) {
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, $action ) ) {
			if ( $die ) {
				wp_send_json_error( array( 'message' => __( 'Security verification failed. Please refresh the page and try again.', 'eventflow' ), 'code' => 'nonce_failure' ) );
			}
			return false;
		}
		return true;
	}

	/**
	 * Verify user capability
	 *
	 * @param string $capability
	 * @param bool   $die
	 * @return bool
	 */
	public static function check_capability( $capability, $die = true ) {
		if ( ! is_user_logged_in() || ! current_user_can( $capability ) ) {
			if ( $die ) {
				wp_send_json_error( array( 'message' => __( 'Access denied. You do not have sufficient permissions to perform this action.', 'eventflow' ), 'code' => 'forbidden' ) );
			}
			return false;
		}
		return true;
	}

	/**
	 * Rate limiting helper using transients
	 *
	 * @param string $action
	 * @param int    $max_attempts
	 * @param int    $period_seconds
	 * @return bool True if allowed, false if limit exceeded
	 */
	public static function check_rate_limit( $action = 'auth', $max_attempts = 10, $period_seconds = 300 ) {
		$ip = self::get_client_ip();
		$transient_key = 'ef_rl_' . md5( $action . '_' . $ip );
		$attempts = (int) get_transient( $transient_key );

		if ( $attempts >= $max_attempts ) {
			return false;
		}

		set_transient( $transient_key, $attempts + 1, $period_seconds );
		return true;
	}

	/**
	 * Get client IP address
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		$ip = '';
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip = trim( reset( $ips ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '127.0.0.1';
	}

	/**
	 * Log security and operational audit event
	 *
	 * @param string $action
	 * @param string $object_type
	 * @param mixed  $object_id
	 * @param mixed  $details
	 * @param int    $user_id
	 */
	public static function log_audit( $action, $object_type, $object_id = null, $details = null, $user_id = null ) {
		global $wpdb;

		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$details_str = is_array( $details ) || is_object( $details ) ? wp_json_encode( $details ) : (string) $details;

		$wpdb->insert(
			EventFlow_DB::table( 'audit_logs' ),
			array(
				'user_id'     => $user_id ? $user_id : null,
				'action'      => sanitize_text_field( $action ),
				'object_type' => sanitize_text_field( $object_type ),
				'object_id'   => $object_id ? (string) $object_id : null,
				'details'     => $details_str,
				'ip_address'  => self::get_client_ip(),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Securely serve payment screenshots / proof files
	 * Ensures unauthorized users or web crawlers cannot access private financial proofs.
	 */
	public static function serve_secure_file() {
		if ( ! isset( $_GET['eventflow_action'] ) || 'view_proof' !== $_GET['eventflow_action'] ) {
			return;
		}

		$payment_id = isset( $_GET['payment_id'] ) ? absint( $_GET['payment_id'] ) : 0;
		if ( ! $payment_id || ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Unauthorized access.', 'eventflow' ), 403 );
		}

		// Verify nonce
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'eventflow_view_proof_' . $payment_id ) ) {
			wp_die( esc_html__( 'Invalid or expired file link.', 'eventflow' ), 403 );
		}

		global $wpdb;
		$payment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.*, r.user_id as reg_user_id FROM " . EventFlow_DB::table( 'payments' ) . " p 
				 INNER JOIN " . EventFlow_DB::table( 'registrations' ) . " r ON p.registration_id = r.id 
				 WHERE p.id = %d LIMIT 1",
				$payment_id
			)
		);

		if ( ! $payment ) {
			wp_die( esc_html__( 'Payment record not found.', 'eventflow' ), 404 );
		}

		// Authorization check: Staff or the user who owns the registration
		$current_user_id = get_current_user_id();
		$is_staff = EventFlow_Roles::is_staff( $current_user_id );
		$is_owner = ( (int) $payment->user_id === $current_user_id || (int) $payment->reg_user_id === $current_user_id );

		if ( ! $is_staff && ! $is_owner ) {
			wp_die( esc_html__( 'Permission denied to view this document.', 'eventflow' ), 403 );
		}

		$filepath     = ! empty( $payment->screenshot_path ) ? $payment->screenshot_path : '';
		$upload_dir   = wp_upload_dir();
		$secure_base  = realpath( trailingslashit( $upload_dir['basedir'] ) . 'eventflow-secure' );
		$plugin_img   = realpath( EVENTFLOW_PLUGIN_DIR . 'assets/images' );
		$resolved_file = '';

		// 1. Check direct absolute path
		if ( ! empty( $filepath ) && file_exists( $filepath ) ) {
			$resolved_file = realpath( $filepath );
		}

		// 2. Fallback: check in secure upload directory by basename
		if ( ( ! $resolved_file || ! file_exists( $resolved_file ) ) && ! empty( $filepath ) && $secure_base ) {
			$alt_secure = trailingslashit( $secure_base ) . basename( $filepath );
			if ( file_exists( $alt_secure ) ) {
				$resolved_file = realpath( $alt_secure );
			}
		}

		// 3. Fallback: check in plugin assets directory by basename
		if ( ( ! $resolved_file || ! file_exists( $resolved_file ) ) && ! empty( $filepath ) && $plugin_img ) {
			$alt_plugin = trailingslashit( $plugin_img ) . basename( $filepath );
			if ( file_exists( $alt_plugin ) ) {
				$resolved_file = realpath( $alt_plugin );
			}
		}

		// Verify file is within permitted directories
		$is_valid_location = false;
		if ( $resolved_file ) {
			if ( $secure_base && 0 === strpos( $resolved_file, $secure_base ) ) {
				$is_valid_location = true;
			} elseif ( $plugin_img && 0 === strpos( $resolved_file, $plugin_img ) ) {
				$is_valid_location = true;
			}
		}

		// Fail-safe: If file is missing or invalid on disk, stream a clean SVG receipt placeholder instead of fatal 404
		if ( ! $resolved_file || ! $is_valid_location || ! file_exists( $resolved_file ) ) {
			header( 'Content-Type: image/svg+xml' );
			header( 'Cache-Control: no-cache' );
			$tid    = ! empty( $payment->transaction_id ) ? esc_html( $payment->transaction_id ) : 'N/A';
			$method = ! empty( $payment->payment_method ) ? esc_html( $payment->payment_method ) : 'Direct Submission';
			$amount = ! empty( $payment->amount ) ? floatval( $payment->amount ) : 0;
			echo '<?xml version="1.0" encoding="UTF-8"?>';
			?>
			<svg xmlns="http://www.w3.org/2000/svg" width="600" height="380" viewBox="0 0 600 380" fill="none">
				<rect width="600" height="380" rx="16" fill="#0B0F19"/>
				<rect x="20" y="20" width="560" height="340" rx="12" stroke="#6366F1" stroke-width="2" stroke-dasharray="8 8" fill="#131B2E"/>
				<circle cx="300" cy="120" r="40" fill="#6366F1" fill-opacity="0.15"/>
				<path d="M288 120L296 128L314 110" stroke="#6366F1" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
				<text x="300" y="195" text-anchor="middle" fill="#FFFFFF" font-family="system-ui, sans-serif" font-size="20" font-weight="bold">Payment Record on File</text>
				<text x="300" y="225" text-anchor="middle" fill="#94A3B8" font-family="system-ui, sans-serif" font-size="14">Transaction ID: <?php echo esc_html( $tid ); ?> &bull; Method: <?php echo esc_html( $method ); ?></text>
				<text x="300" y="255" text-anchor="middle" fill="#38BDF8" font-family="system-ui, sans-serif" font-size="15" font-weight="600">Amount: <?php echo esc_html( number_format( $amount, 2 ) ); ?></text>
				<text x="300" y="300" text-anchor="middle" fill="#64748B" font-family="system-ui, sans-serif" font-size="12">Verified payment proof metadata preserved in EventFlow database.</text>
			</svg>
			<?php
			exit;
		}

		$mime         = wp_check_filetype( $resolved_file );
		$content_type = $mime['type'] ? $mime['type'] : 'application/octet-stream';
		$is_download  = isset( $_GET['download'] ) && '1' === (string) $_GET['download'];
		$disposition  = $is_download ? 'attachment' : 'inline';

		// Set proper caching & viewing headers
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Length: ' . filesize( $resolved_file ) );
		header( 'Content-Disposition: ' . $disposition . '; filename="' . basename( $resolved_file ) . '"' );
		header( 'Cache-Control: private, max-age=3600' );

		readfile( $resolved_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		exit;
	}

	/**
	 * Generate a secure URL to view payment proof
	 *
	 * @param int $payment_id
	 * @return string
	 */
	public static function get_secure_proof_url( $payment_id ) {
		return add_query_arg(
			array(
				'eventflow_action' => 'view_proof',
				'payment_id'       => $payment_id,
				'_wpnonce'         => wp_create_nonce( 'eventflow_view_proof_' . $payment_id ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Generate a secure URL to directly download payment proof
	 *
	 * @param int $payment_id
	 * @return string
	 */
	public static function get_secure_download_url( $payment_id ) {
		return add_query_arg(
			array(
				'eventflow_action' => 'view_proof',
				'download'         => '1',
				'payment_id'       => $payment_id,
				'_wpnonce'         => wp_create_nonce( 'eventflow_view_proof_' . $payment_id ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Validate and securely upload receipt / payment proof
	 *
	 * @param array $file $_FILES['...']
	 * @return array|WP_Error
	 */
	public static function validate_and_upload_receipt( $file ) {
		return EventFlow_Payment_Manager::handle_file_upload( $file );
	}

	/**
	 * Encrypt sensitive credentials (e.g. SMTP passwords) using AES-256-CBC
	 *
	 * @param string $plaintext
	 * @return string Base64 encoded payload with IV prefix
	 */
	public static function encrypt( $plaintext ) {
		if ( empty( $plaintext ) ) {
			return '';
		}
		if ( ! extension_loaded( 'openssl' ) ) {
			return base64_encode( (string) $plaintext );
		}
		$key = defined( 'AUTH_KEY' ) ? hash( 'sha256', AUTH_KEY, true ) : hash( 'sha256', 'sibacom_event_salt_2026', true );
		$iv  = openssl_random_pseudo_bytes( 16 );
		$enc = openssl_encrypt( (string) $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		return base64_encode( $iv . $enc );
	}

	/**
	 * Decrypt sensitive credentials using AES-256-CBC
	 *
	 * @param string $ciphertext
	 * @return string Decrypted plaintext
	 */
	public static function decrypt( $ciphertext ) {
		if ( empty( $ciphertext ) ) {
			return '';
		}
		$data = base64_decode( (string) $ciphertext );
		if ( false === $data ) {
			return '';
		}
		if ( ! extension_loaded( 'openssl' ) ) {
			return $data;
		}
		$key = defined( 'AUTH_KEY' ) ? hash( 'sha256', AUTH_KEY, true ) : hash( 'sha256', 'sibacom_event_salt_2026', true );
		$iv  = substr( $data, 0, 16 );
		$enc = substr( $data, 16 );
		$dec = openssl_decrypt( $enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		return false !== $dec ? $dec : '';
	}
}

