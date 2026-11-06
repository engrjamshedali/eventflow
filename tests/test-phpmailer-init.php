<?php
define( 'ABSPATH', 1 );
define( 'AUTH_KEY', 'test-salt-secret-auth-key-1234567890' );
define( 'AUTH_SALT', 'test-salt-secret-phrase-0987654321' );

global $wp_options;
$wp_options = array(
	'eventflow_mailer_mode'       => 'eventflow_smtp',
	'eventflow_smtp_enabled'      => 'yes',
	'eventflow_smtp_host'         => 'smtp.gmail.com',
	'eventflow_smtp_port'         => 587,
	'eventflow_smtp_encryption'   => 'tls',
	'eventflow_smtp_auth'         => 'yes',
	'eventflow_smtp_username'     => 'test@gmail.com',
	'eventflow_smtp_timeout'      => 20,
	'eventflow_email_from_name'   => 'Tech Committee',
	'eventflow_email_from_address'=> 'notifications@techolympiad.org',
	'eventflow_email_reply_to'    => 'support@techolympiad.org',
);

function get_option( $opt, $default = false ) {
	global $wp_options;
	return isset( $wp_options[ $opt ] ) ? $wp_options[ $opt ] : $default;
}
function update_option( $opt, $val ) {
	global $wp_options;
	$wp_options[ $opt ] = $val;
	return true;
}
function is_email( $email ) {
	return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
}
function wp_strip_all_tags( $str ) {
	return strip_tags( $str );
}
function get_bloginfo() {
	return 'Test';
}

require_once ( file_exists( __DIR__ . '/../includes/class-smtp.php' ) ? __DIR__ . '/../includes/class-smtp.php' : __DIR__ . '/../eventflow/includes/class-smtp.php' );

$wp_options['eventflow_smtp_password'] = EventFlow_SMTP::encrypt_password( 'app-password-1234' );

class MockPHPMailer {
	public $Mailer = '';
	public $Host = '';
	public $Port = 0;
	public $SMTPSecure = '';
	public $SMTPAuth = false;
	public $Username = '';
	public $Password = '';
	public $Timeout = 0;
	public $From = '';
	public $FromName = '';
	public $Sender = '';
	public $CharSet = '';
	public $Encoding = '';
	public $ContentType = 'text/html';
	public $Body = '<h1>Hello</h1>';
	public $AltBody = '';
	public $ReplyTo = array();

	public function isSMTP() {
		$this->Mailer = 'smtp';
	}
	public function clearReplyTos() {
		$this->ReplyTo = array();
	}
	public function addReplyTo( $address, $name = '' ) {
		$this->ReplyTo[] = array( $address, $name );
	}
}

$phpmailer = new MockPHPMailer();
EventFlow_SMTP::configure_phpmailer( $phpmailer );

assert( 'smtp' === $phpmailer->Mailer, 'Mailer set to smtp' );
assert( 'smtp.gmail.com' === $phpmailer->Host, 'Host is smtp.gmail.com' );
assert( 587 === $phpmailer->Port, 'Port is 587' );
assert( 'tls' === $phpmailer->SMTPSecure, 'SMTPSecure is tls' );
assert( true === $phpmailer->SMTPAuth, 'SMTPAuth is true' );
assert( 'test@gmail.com' === $phpmailer->Username, 'Username matches' );
assert( 'app-password-1234' === $phpmailer->Password, 'Password decrypted correctly' );
assert( 20 === $phpmailer->Timeout, 'Timeout is 20' );
assert( 'notifications@techolympiad.org' === $phpmailer->From, 'From email matches' );
assert( 'Tech Committee' === $phpmailer->FromName, 'FromName matches' );
assert( 'UTF-8' === $phpmailer->CharSet, 'CharSet is UTF-8' );
assert( 'base64' === $phpmailer->Encoding, 'Encoding is base64' );
assert( ! empty( $phpmailer->AltBody ), 'AltBody generated from HTML body' );
assert( count( $phpmailer->ReplyTo ) > 0, 'ReplyTo added' );

echo "PHPMailer configuration hook test: ALL ASSERTIONS PASSED!\n";
