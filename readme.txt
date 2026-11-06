=== EventFlow – Advanced Event Registration & Management System ===
Contributors: engrjamshedali
Tags: event registration, event management, competition management, payment verification, team registration, university symposium, hackathon
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 5.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A premium, enterprise-grade event registration, competition tracking, dynamic team builder, payment verification, and frontend staff analytics command center for WordPress.

== Description ==

**EventFlow** is an enterprise-grade, full-lifecycle event management, competition tracking, dynamic team formation, financial payment verification, and attendee portal plugin for WordPress.

Engineered specifically for university symposiums, hackathons, robotics competitions, tech olympiads, and corporate conferences, EventFlow completely decouples the operational management of events from the standard WordPress administrative backoffice (`/wp-admin/`). It empowers event organizers, verification staff, and attendees with dedicated, modern frontend interfaces.

### Major Features

* **Full-Page Standalone Web App Mode (Theme Bypass)**: Completely bypasses active WordPress theme headers, navigation bars, site logos, sidebars, and footers, delivering a distraction-free, full-viewport application experience for attendees and staff.
* **Split-Screen Authentication**: Reference-accurate animated split-screen authentication portal on a mint background (`#F0F7F5`) featuring teal gradients (`#36B49F` to `#27A991`), brand logo placement, and rate-limited security.
* **Participant Self-Service Portal**: Attendees discover events, manage applications, submit payment proofs, download PDF-ready registration vouchers, and receive live status updates.
* **Dynamic Team Registration Engine**: Supports solo and multi-member team registrations with configurable team size boundaries (min/max), live fee calculation (*Per Team* vs. *Per Person*), duplicate member validation, and collects 7 mandatory participant details per member.
* **In-App Zero-Download Payment Proof Inspector**: Verification staff can zoom in/out, rotate 360°, pan/drag, and review uploaded transaction screenshots side-by-side with participant metadata.
* **Frontend Staff Command Center**: Full operational dashboard accessible via `/event-dashboard/` or shortcode `[eventflow_staff_dashboard]` without requiring WordPress `/wp-admin/` credentials.
* **Real-Time Analytics & 14 KPIs**: Native HTML5 Canvas charts visualizing daily registration velocity, track distributions, and application status breakdowns from real database records (zero external CDN dependencies).
* **Automated RFC-Compliant Email Engine**: Pure WordPress native `wp_mail()` dispatch with 16+ pre-configured triggers, merge tags, asynchronous background queuing, idempotency deduplication, and delivery diagnostics.
* **Elementor Compatibility**: Registers the `EventFlow Events` category and 5 dedicated Elementor widgets.
* **Multi-Format Export**: Export participant rosters, team structures with all 7 member fields, and financial reconciliations to CSV (UTF-8 BOM), Excel (.xls), and printable HTML/PDF dossiers.
* **Role-Based Access Control**: 7 custom roles (Super Admin, Event Admin, Payment Verifier, Registration Staff, Tech Team, Participant, Team Member) with 15 granular capabilities.

== Installation ==

### Manual Installation

1. Download the plugin ZIP archive.
2. In your WordPress administration panel, navigate to **Plugins** > **Add New** > **Upload Plugin**.
3. Select the `eventflow.zip` file and click **Install Now**.
4. Click **Activate Plugin**.

### Turnkey Deployment Setup

Upon activation, EventFlow automatically initializes 12 database tables, registers custom user roles, provisions sample activities, and creates default pages.

1. Ensure the following pages are published with their respective shortcodes:
   * **Landing / Events**: `[eventflow_landing]`
   * **Authentication**: `[eventflow_auth]`
   * **Participant Dashboard**: `[eventflow_dashboard]`
   * **Staff Command Center**: `[eventflow_staff_dashboard]`
   * **Registration Status Tracker**: `[eventflow_track]`
2. Navigate to **EventFlow** > **Settings** in the WordPress admin panel to configure your organization branding, bank accounts, and email dispatch settings.

== Frequently Asked Questions ==

= Does EventFlow require WordPress administrative access for staff? =
No. EventFlow features a dedicated Frontend Staff Command Center (`/event-dashboard/`) that allows event coordinators, payment evaluators, and registration staff to manage participants, verify payments, and export rosters without ever logging into `/wp-admin/`.

= What file formats are supported for payment proof uploads? =
EventFlow supports JPG, JPEG, PNG, and PDF files up to 5 MB. Files are stored securely in a protected directory with randomized filenames and guarded against direct URL directory indexing.

= Can teams have varying numbers of members? =
Yes. Each competition track or activity can be configured with independent minimum and maximum team limits (e.g., minimum 2, maximum 5 members).

= Which payment methods are supported? =
Organizers can configure Bank Transfers (IBFT/1Link, Raast), mobile wallets (Easypaisa, JazzCash), and upload merchant QR codes for direct scan-and-pay.

= Is EventFlow translation-ready? =
Yes. EventFlow includes a standard `.pot` template located in `languages/eventflow.pot` and uses standard WordPress localization functions throughout.

== Screenshots ==

1. Split-screen animated authentication portal with rate-limited login and account registration.
2. Participant discovery dashboard showing live event cards, status badges, and registration modal.
3. Dynamic multi-member team registration builder with real-time fee calculation.
4. In-app zero-download payment proof inspector with SVG/Canvas zoom, pan, and rotation controls.
5. Frontend staff command center with real-time KPI cards and HTML5 Canvas analytics charts.

== Changelog ==

= 5.0.2 =
* Fix: Hardened SQL queries in analytics manager with strict prepared statements and integer casting.
* Update: Updated translation catalog and POT template metadata for v5.0.2.
* Tweak: Optimized test suite path resolution for localized repository execution.

= 5.0.1 =
* Feature: Refined split-screen authentication styling and mobile responsive layouts.
* Security: Enhanced login rate-limiting and automated lockout defense mechanisms.
* Performance: Optimized email queue processing batch size and cron schedule intervals.

= 5.0.0 =
* Feature: Full-Page Standalone Web App Mode (Theme Bypass) with dedicated app shell.
* Feature: Zero-download in-app payment proof inspector with zoom, rotation, and PDF preview.
* Feature: Native HTML5 Canvas charts with 14 database-derived operational KPIs.
* Architecture: Centralized SPL autoloader and singleton orchestrator pattern.

= 4.0.2 =
* Fix: Password reset email trigger parameter ordering resolution.
* Fix: Proof path column resolution in participant registration queries.
* Feature: Dynamic form builder supporting 18 customizable schema field types.

= 3.0.1 =
* Feature: Elementor integration registering 5 drag-and-drop widgets.
* Feature: Automated email engine with asynchronous queue and delivery audit logging.

= 1.1.0 =
* Initial release of EventFlow registration and team management core.

== Upgrade Notice ==

= 5.0.2 =
Recommended update for all users. Includes database query hardening, updated translation templates, and enhanced security verifications.
