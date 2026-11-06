<?php
/**
 * SIBACOM EVENT - Base HTML Email Template
 *
 * Fully responsive, cross-client compatible HTML email wrapper featuring
 * SIBACOM EVENT branding, teal accent highlights, clear typography, and official footer.
 *
 * @package EventFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title = isset( $title ) ? $title : '';
$body  = isset( $body ) ? $body : '';
$event = isset( $event ) ? $event : ( class_exists( 'EventFlow_Event_Manager' ) ? EventFlow_Event_Manager::get_active_event() : null );

$portal_name   = 'SIBACOM EVENT';
$event_name    = $event && ! empty( $event->title ) ? esc_html( $event->title ) : $portal_name;
$logo_url      = $event && ! empty( $event->logo_url ) ? esc_url( $event->logo_url ) : '';
$primary_color = '#0D9488'; // SIBACOM Teal
$primary_dark  = '#0F766E';
$accent_color  = '#14B8A6';
$support_email = class_exists( 'EventFlow_Email_Manager' ) ? EventFlow_Email_Manager::get_sender_email() : ( $event && ! empty( $event->contact_email ) ? $event->contact_email : 'info@portal.sibacom.tech' );
$portal_url    = $event && ! empty( $event->website_url ) ? $event->website_url : home_url();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="x-apple-disable-message-reformatting" />
	<title><?php echo esc_html( $title ); ?></title>
	<style type="text/css">
		/* Client-specific resets */
		body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
		table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
		img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
		/* Base Layout */
		body {
			margin: 0 !important;
			padding: 0 !important;
			background-color: #F3F4F6;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
			color: #1F2937;
			line-height: 1.6;
		}
		.email-container {
			max-width: 620px;
			margin: 0 auto;
			background-color: #FFFFFF;
			border-radius: 12px;
			overflow: hidden;
			border: 1px solid #E5E7EB;
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
		}
		/* Header */
		.email-header {
			background: linear-gradient(135deg, <?php echo esc_attr( $primary_dark ); ?> 0%, <?php echo esc_attr( $primary_color ); ?> 100%);
			padding: 34px 28px;
			text-align: center;
			color: #FFFFFF;
		}
		.portal-badge {
			display: inline-block;
			background: rgba(255, 255, 255, 0.18);
			padding: 4px 12px;
			border-radius: 9999px;
			font-size: 11px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			color: #FFFFFF;
			margin-bottom: 10px;
		}
		.email-header h1 {
			margin: 0;
			font-size: 22px;
			font-weight: 800;
			color: #FFFFFF;
			letter-spacing: -0.02em;
			line-height: 1.3;
		}
		.email-header p {
			margin: 6px 0 0;
			font-size: 13px;
			color: #CCFBF1;
		}
		/* Content Body */
		.email-content {
			padding: 36px 32px;
			background-color: #FFFFFF;
			font-size: 15px;
			color: #374151;
		}
		.email-content h2 {
			margin: 0 0 16px;
			color: #111827;
			font-size: 19px;
			font-weight: 700;
			letter-spacing: -0.01em;
		}
		.email-content p {
			margin: 0 0 16px;
			line-height: 1.65;
		}
		.email-content strong {
			color: #111827;
		}
		/* Announcement & Info Box */
		.announcement-box, .ef-box {
			background-color: #F0FDFA;
			border-left: 4px solid <?php echo esc_attr( $primary_color ); ?>;
			border-top: 1px solid #CCFBF1;
			border-right: 1px solid #CCFBF1;
			border-bottom: 1px solid #CCFBF1;
			border-radius: 6px;
			padding: 16px 20px;
			margin: 22px 0;
			font-size: 14.5px;
			color: #134E4A;
		}
		.announcement-box p:last-child, .ef-box p:last-child {
			margin-bottom: 0;
		}
		/* Buttons */
		.btn {
			display: inline-block;
			padding: 12px 28px;
			background-color: <?php echo esc_attr( $primary_color ); ?>;
			color: #FFFFFF !important;
			text-decoration: none;
			border-radius: 8px;
			font-weight: 700;
			font-size: 14px;
			margin: 18px 0;
			text-align: center;
			letter-spacing: 0.02em;
		}
		/* Meta Data Table */
		.meta-table {
			width: 100%;
			margin: 20px 0;
			border: 1px solid #E5E7EB;
			border-radius: 8px;
			border-collapse: collapse;
			background-color: #FFFFFF;
		}
		.meta-table td, .meta-table th {
			padding: 11px 16px;
			font-size: 14px;
			border-bottom: 1px solid #F3F4F6;
			vertical-align: top;
		}
		.meta-table th {
			background-color: #F9FAFB;
			font-weight: 700;
			color: #374151;
			text-align: left;
		}
		.meta-table tr:last-child td {
			border-bottom: none;
		}
		.meta-table td.label {
			width: 38%;
			background-color: #F9FAFB;
			font-weight: 600;
			color: #4B5563;
		}
		.meta-table td.value {
			color: #111827;
			font-weight: 500;
		}
		/* Team Member Table Styling */
		.team-members-table-wrap {
			margin: 24px 0;
			overflow-x: auto;
		}
		.team-members-table {
			width: 100%;
			border-collapse: collapse;
			border: 1px solid #E5E7EB;
			font-size: 13.5px;
		}
		.team-members-table th {
			background-color: #F9FAFB;
			color: #374151;
			padding: 10px 12px;
			font-weight: 700;
			border: 1px solid #E5E7EB;
			text-align: left;
		}
		.team-members-table td {
			padding: 10px 12px;
			border: 1px solid #E5E7EB;
			color: #1F2937;
			vertical-align: top;
		}
		.team-members-table tr:nth-child(even) {
			background-color: #F9FAFB;
		}
		.team-role-badge {
			display: inline-block;
			padding: 2px 8px;
			border-radius: 9999px;
			font-size: 11px;
			font-weight: 700;
		}
		.team-role-leader {
			background-color: #CCFBF1;
			color: #0F766E;
		}
		.team-role-member {
			background-color: #F3F4F6;
			color: #4B5563;
		}
		/* Footer */
		.email-footer {
			background-color: #F9FAFB;
			padding: 26px 32px;
			text-align: center;
			font-size: 12px;
			color: #6B7280;
			border-top: 1px solid #E5E7EB;
			line-height: 1.6;
		}
		.email-footer a {
			color: <?php echo esc_attr( $primary_color ); ?>;
			text-decoration: underline;
		}
		.email-footer p {
			margin: 4px 0;
		}
		/* Mobile responsive rules */
		@media only screen and (max-width: 600px) {
			.email-container {
				width: 100% !important;
				border-radius: 0 !important;
				border-left: none !important;
				border-right: none !important;
			}
			.email-content {
				padding: 24px 18px !important;
			}
			.email-header {
				padding: 26px 18px !important;
			}
			.email-footer {
				padding: 20px 18px !important;
			}
			.btn {
				display: block !important;
				width: auto !important;
			}
			.team-members-table th, .team-members-table td {
				padding: 8px 6px !important;
				font-size: 12px !important;
			}
		}
	</style>
</head>
<body style="margin: 0; padding: 24px 0; background-color: #F3F4F6;">
	<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
		<tr>
			<td align="center" style="padding: 12px;">
				<div class="email-container">
					<!-- Branded Header -->
					<div class="email-header">
						<div class="portal-badge">SIBACOM EVENT</div>
						<?php if ( $logo_url ) : ?>
							<div style="margin-bottom: 12px;">
								<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $event_name ); ?>" style="max-height: 54px; max-width: 220px;" />
							</div>
						<?php endif; ?>
						<h1><?php echo esc_html( $event_name ); ?></h1>
						<p><?php echo esc_html( $event && ! empty( $event->university_name ) ? $event->university_name : 'Sukkur IBA University' ); ?></p>
					</div>

					<!-- Main Body -->
					<div class="email-content">
						<?php echo function_exists( 'wp_kses_post' ) ? wp_kses_post( $body ) : $body; ?>
					</div>

					<!-- Official Footer -->
					<div class="email-footer">
						<p style="font-weight: 600; color: #374151;">
							SIBACOM EVENT
						</p>
						<p>
							Official Website: <a href="<?php echo esc_url( $portal_url ); ?>"><?php echo esc_html( preg_replace( '#^https?://#', '', $portal_url ) ); ?></a> | Contact: <a href="mailto:<?php echo esc_attr( $support_email ); ?>"><?php echo esc_html( $support_email ); ?></a>
						</p>
						<p style="margin-top: 10px; font-size: 11px; color: #9CA3AF;">
							This is an automated notification from SIBACOM EVENT dispatched using WordPress native mail transport. Replies to this email will be delivered to <a href="mailto:<?php echo esc_attr( $support_email ); ?>"><?php echo esc_html( $support_email ); ?></a>.
						</p>
						<p style="font-size: 11px; color: #9CA3AF;">
							&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php echo esc_html( $event && ! empty( $event->university_name ) ? $event->university_name : 'Sukkur IBA University' ); ?>. All rights reserved.
						</p>
					</div>
				</div>
			</td>
		</tr>
	</table>
</body>
</html>
