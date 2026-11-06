<?php
/**
 * Quality and Rendering Audit of All 21 Email Templates
 */
if ( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', __DIR__ . '/../' );
if ( ! defined( 'EVENTFLOW_PLUGIN_DIR' ) ) if ( ! defined( 'EVENTFLOW_PLUGIN_DIR' ) ) { define( 'EVENTFLOW_PLUGIN_DIR', file_exists( __DIR__ . '/../includes/class-db.php' ) ? dirname( __DIR__ ) . '/' : __DIR__ . '/../eventflow/' ); }
if ( ! defined( 'EVENTFLOW_PLUGIN_URL' ) ) define( 'EVENTFLOW_PLUGIN_URL', 'https://portal.sibacom.tech/wp-content/plugins/eventflow/' );
if ( ! defined( 'EVENTFLOW_VERSION' ) ) define( 'EVENTFLOW_VERSION', '4.0.2' );

// Mock minimal WordPress functions
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $u ) { return filter_var( $u, FILTER_SANITIZE_URL ); }
}
if ( ! function_exists( '__' ) ) {
	function __( $t, $d = '' ) { return $t; }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $t, $d = '' ) { return esc_html( $t ); }
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $p = '' ) { return 'https://portal.sibacom.tech' . $p; }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $s ) { return $s; }
}
if ( ! function_exists( 'date_i18n' ) ) {
	function date_i18n( $fmt, $ts = null ) { return date( $fmt, $ts ? $ts : time() ); }
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $opt, $default = '' ) {
		if ( 'date_format' === $opt ) return 'F j, Y';
		if ( 'time_format' === $opt ) return 'g:i A';
		if ( 'eventflow_email_from_name' === $opt ) return 'SIBACOM EVENT';
		if ( 'eventflow_email_from_address' === $opt ) return 'info@portal.sibacom.tech';
		if ( 'eventflow_email_reply_to' === $opt ) return 'info@portal.sibacom.tech';
		return $default;
	}
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) { return 'mysql' === $type ? date( 'Y-m-d H:i:s' ) : time(); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) { return strip_tags( (string) $s ); }
}
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $e ) { return filter_var( trim( (string) $e ), FILTER_SANITIZE_EMAIL ); }
}
if ( ! function_exists( 'is_email' ) ) {
	function is_email( $e ) { return filter_var( $e, FILTER_VALIDATE_EMAIL ); }
}

class Test_WPDB {
	public $prefix = 'wp_';
	public function prepare( $q, ...$args ) { return $q; }
	public function get_row( $q ) { return null; }
	public function get_results( $q ) { return array(); }
	public function get_var( $q ) { return 0; }
	public function insert( $t, $d ) { return 1; }
	public function update( $t, $d, $w ) { return 1; }
}
global $wpdb;
$wpdb = new Test_WPDB();

require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-db.php';
require_once EVENTFLOW_PLUGIN_DIR . 'includes/class-email-manager.php';

echo "\n======================================================================\n";
echo "SIBACOM EVENT - COMPLETE 21 EMAIL TEMPLATES QUALITY AUDIT\n";
echo "======================================================================\n\n";

$templates = EventFlow_DB::get_default_email_templates_data();
$mock_data = array(
    'participant_name'         => 'Ali Khan',
    'participant_email'        => 'ali.khan@iba-suk.edu.pk',
    'username'                 => 'alikhan',
    'team_name'                => 'RoboDynasty',
    'team_or_participant_name' => 'RoboDynasty',
    'registration_id'          => 'EF-2026-TEST1',
    'activity_name'            => 'Speed Programming Championship',
    'department'               => 'Computer Science',
    'semester'                 => '6',
    'academic_year'            => '2024-2026',
    'university'               => 'Sukkur IBA University',
    'contact_number'           => '+92 300 1234567',
    'participation_type_label' => 'Team Registration',
    'payment_amount'           => 'PKR 2,000.00',
    'payment_status'           => 'Under Verification',
    'payment_status_label'     => 'Under Verification',
    'payment_method'           => 'Bank Transfer',
    'transaction_id'           => 'TXN-20261105-ABCDEF',
    'verification_date'        => date('F j, Y'),
    'application_status'       => 'Under Review',
    'application_status_label' => 'Under Review',
    'rejection_reason'         => 'Transaction receipt image is blurred and TID is unreadable.',
    'cancellation_reason'      => 'Participant requested registration cancellation.',
    'admin_notes'              => 'Please provide bank statement showing reference number.',
    'previous_payment_status'  => 'Rejected',
    'submission_date'          => date('F j, Y'),
    'update_timestamp'         => date('F j, Y g:i A'),
    'payment_deadline'         => 'November 15, 2026',
    'event_name'               => 'SIBACOM EVENT',
    'event_date'               => 'December 1-3, 2026',
    'start_time'               => '09:00 AM',
    'end_time'                 => '06:00 PM',
    'reporting_time'           => '08:30 AM',
    'event_venue'              => 'Main Auditorium, Sukkur IBA University',
    'registration_deadline'    => 'November 15, 2026',
    'website_url'              => 'https://portal.sibacom.tech',
    'login_url'                => 'https://portal.sibacom.tech/login',
    'dashboard_url'            => 'https://portal.sibacom.tech/dashboard',
    'tracking_url'             => 'https://portal.sibacom.tech/track?reg_id=EF-2026-TEST1',
    'reupload_url'             => 'https://portal.sibacom.tech/dashboard?tab=payment&action=reupload&reg_id=EF-2026-TEST1',
    'receipt_url'              => 'https://portal.sibacom.tech/dashboard?tab=receipt&reg_id=EF-2026-TEST1',
    'reset_url'                => 'https://portal.sibacom.tech/login?action=rp&key=xyz',
    'support_email'            => 'info@portal.sibacom.tech',
    'support_phone'            => '+92 300 1234567',
    'support_contact'          => 'info@portal.sibacom.tech | +92 300 1234567',
    'leader_name'              => 'Hamza Tariq',
    'leader_email'             => 'hamza@iba-suk.edu.pk',
    'leader_phone'             => '+92 300 9876543',
    'leader_department'        => 'Electrical Engineering',
    'leader_semester'          => '5',
    'leader_university'        => 'Sukkur IBA University',
    'total_team_members'       => '3',
    'recipient_role'           => 'Team Leader',
    'team_role'                => 'Team Leader',
    'team_members_table'       => '<div class="team-members-table-wrap"><table class="team-members-table"><thead><tr><th>Name</th><th>Role</th><th>Dept</th><th>Semester</th><th>University</th><th>Phone</th></tr></thead><tbody><tr><td>Hamza Tariq</td><td>Team Leader</td><td>EE</td><td>5</td><td>Sukkur IBA</td><td>+923009876543</td></tr></tbody></table></div>',
    'team_members_list'        => "1. Hamza Tariq [Team Leader] - hamza@iba-suk.edu.pk | +923009876543 | EE | 5 | Sukkur IBA",
    'announcement_content'     => 'Please arrive 30 minutes before your round starts.',
    'sender_name'              => 'SIBACOM EVENT',
    'sender_email'             => 'info@portal.sibacom.tech',
    'reply_to'                 => 'info@portal.sibacom.tech',
);

$all_passed = true;
$passed_count = 0;
foreach ($templates as $i => $t) {
    $t_obj = (object) $t;
    $prepared = EventFlow_Email_Manager::prepare_message($t_obj, 'ali.khan@iba-suk.edu.pk', $mock_data);

    // Check for raw unreplaced placeholders in subject or body
    preg_match_all('/\{[a-z0-9_]+\}/i', $prepared['subject'], $s_ph);
    preg_match_all('/\{[a-z0-9_]+\}/i', $prepared['body'], $b_ph);

    $unreplaced = array_merge($s_ph[0], $b_ph[0]);
    $has_unreplaced = count($unreplaced) > 0;
    $has_bad_brand = (false !== stripos($prepared['subject'], 'SIBACO ') || false !== stripos($prepared['body'], 'SIBACO '));

    echo sprintf("[%02d] Template: %-28s | Subject: %s\n", ($i+1), $t['template_key'], $prepared['subject']);

    if ($has_unreplaced) {
        echo "     [FAIL] Unreplaced tags: " . implode(', ', $unreplaced) . "\n";
        $all_passed = false;
    } else {
        echo "     [PASS] Placeholders rendered cleanly\n";
        $passed_count++;
    }

    if ($has_bad_brand) {
        echo "     [FAIL] Corrupted branding detected!\n";
        $all_passed = false;
    } else {
        echo "     [PASS] SIBACOM EVENT branding verified\n";
        $passed_count++;
    }

    $has_html = (false !== strpos($prepared['content'], '<!DOCTYPE html') && false !== strpos($prepared['content'], 'SIBACOM EVENT'));
    $has_plain = !empty($prepared['plain_text']);
    if ($has_html && $has_plain) {
        echo "     [PASS] Multipart HTML & Plain-Text rendered (" . strlen($prepared['content']) . " / " . strlen($prepared['plain_text']) . " bytes)\n";
        $passed_count++;
    } else {
        echo "     [FAIL] Missing HTML wrapper or plain text\n";
        $all_passed = false;
    }
    echo "\n";
}

echo "======================================================================\n";
echo "AUDIT SUMMARY: " . $passed_count . " assertions passed. " . ($all_passed ? "ALL TEMPLATES COMPLIANT!" : "AUDIT FAILED") . "\n";
echo "======================================================================\n";
