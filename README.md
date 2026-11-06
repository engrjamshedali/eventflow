# EventFlow – Advanced Event Registration & Management System

[![WordPress Version](https://img.shields.io/badge/WordPress-5.8%2B%20(Tested%20up%20to%206.7)-blue.svg?style=flat-square&logo=wordpress)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%20|%208.0%20|%208.1%20|%208.2%20|%208.3-777BB4.svg?style=flat-square&logo=php)](https://php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-green.svg?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Release Version](https://img.shields.io/badge/Version-5.0.2-teal.svg?style=flat-square)](https://github.com/engrjamshedali/eventflow/releases)

> **A premium, enterprise-grade WordPress plugin for event registration, multi-track competition management, dynamic team builders, payment proof verification, frontend staff dashboards, real-time analytics, and automated email notifications.**

---

## 🌟 Executive Overview

**EventFlow** is an open-source, full-lifecycle event management and competition management system engineered for WordPress. Originally crafted for university symposiums, hackathons, robotics competitions, tech olympiads, and corporate conferences, EventFlow completely decouples the operational management of events from the standard WordPress administrative backoffice (`/wp-admin/`).

By delivering a **standalone, zero-admin Frontend Staff Command Center** alongside an intuitive **Participant Self-Service Portal**, EventFlow eliminates operational friction, prevents administrative credential proliferation, and provides a frictionless attendee experience.

---

## 🚀 Key Feature Modules

### 1. Full-Page Standalone Web App Mode (Theme Bypass)
* **Zero Theme Interference**: Completely bypasses active WordPress theme headers, navigation bars, site logos, sidebars, and footers through a specialized `EventFlow_App_Shell`.
* **Turnkey Homepage Experience**: Visiting `/` immediately delivers the full-viewport Login & Registration portal for guests, and the Participant Discovery Dashboard for authenticated attendees.
* **Pure Web App Look & Feel**: Immersive full-viewport layout (`100vw` / `100vh`) with dedicated app headers, user profile badges, and clean session switching.
* **Admin Bar Auto-Suppression**: Automatically hides the WordPress admin bar for regular participants to maintain an authentic application experience.

### 2. Split-Screen Authentication & Lockout Defense
* **Reference-Accurate Design**: Split-screen card layout on a light mint background (`#F0F7F5`) featuring a rich teal/green gradient (`#36B49F` &rarr; `#27A991`), geometric patterns, brand logos, and a crisp white authentication section (`#FFFFFF`).
* **Instant Mode Switching**: Animated transitions between *Create Account*, *Welcome Back (Login)*, and *Forgot Password* without page reloads.
* **Enterprise Security**: Includes CSRF nonces, rate-limiting, secure password storage via `wp_hash_password`, brute-force defense with automated 15-minute lockouts, and live client/server validation.

### 3. "Explore Live Events" Participant Discovery Dashboard
* **Dynamic Event Cards**: Direct database retrieval from `wp_eventflow_activities`, featuring status badges (*Registration Open*, *Coming Soon*, *Registration Closed*, *Fully Booked*), registration deadlines, entry fees, team constraints, and seat availability.
* **Multi-Criteria Filtering & Live Search**: Instant tab filtering (*All Events*, *Registration Open*, *Coming Soon*, *Registration Closed*, *My Registered Events*) and real-time title/track keyword search.
* **Deep Event Inspection Modal**: "View Details" opens a rich modal dialog with comprehensive rules, eligibility criteria, schedules, fee structure, team sizes, and important instructions.

### 4. Dynamic Team Registration Builder
* **Flexible Participation Modes**: Individual (Solo) participation, Team participation, or Both.
* **Interactive Roster Builder**: Dynamic "+ Add Team Member" card insertion with configurable minimum and maximum team sizes.
* **7 Mandatory Fields per Member**:
  1. Full Name
  2. Father's Name
  3. Current Semester
  4. Academic Department
  5. University / Institute
  6. Email Address
  7. Contact / WhatsApp Number
* **Strict Validation**: Enforces team size boundaries, calculates fees dynamically (*Per Team* vs. *Per Person*), and prevents intra-team duplicate emails or phone numbers.

### 5. Advanced In-App Payment Verification Inspector
* **Zero-Download In-App Inspection**: Inspect uploaded payment screenshots and receipts directly inside the dashboard modal.
* **Interactive Canvas & Image Controls**:
  * Zoom In (`+25%`) & Zoom Out (`-25%`)
  * Continuous 90° Rotation (`⟳`)
  * Interactive mouse drag / pan across viewport
  * In-app embedded PDF viewer
* **Side-by-Side Metadata**: Transaction ID, candidate name, track title, and amount displayed alongside the receipt.
* **One-Click Staff Actions**: *Verify Payment*, *Reject Payment* (with mandatory reason prompt), *Request Re-upload*, and internal notes.
* **Audit Diff History**: All payment modifications and resubmissions record immutable audit trails with version tracking.

### 6. Frontend Staff & Admin Command Center
* **Dedicated Frontend URL**: Accessible via `/event-dashboard/` or shortcode `[eventflow_staff_dashboard]`.
* **Zero WP-Admin Requirement**: Staff manage the entire event lifecycle without WordPress backend administrative credentials.
* **14 Real-Time KPIs**: Total Registrations, Participants, Teams, Solo entries, Payment statuses (Under Verification, Verified, Rejected, Re-upload Requested), Expected Revenue, Verified Revenue, Pending Revenue, and Active Competitions.
* **Native HTML5 Canvas Charts**: Zero-CDN interactive charts for daily registration velocity, track distributions, and application status breakdowns.
* **Searchable Data Tables**: Real-time filtering by track, payment state, application state, and pagination.

### 7. Automated Email Notification Engine
* **Pure WordPress Native `wp_mail()`**: RFC-compliant notification engine with dynamic HTML layouts and plain-text alternatives.
* **16+ Pre-Configured Triggers**: Registration confirmation, payment verification, rejection alerts with feedback, re-upload requests, deadline reminders, and completion notices.
* **Asynchronous Queue & Idempotency**: Background queue processing with lock mechanisms preventing duplicate dispatches.
* **Delivery Diagnostics**: Tracks outgoing dispatch status, recipient, timestamp, and failure logs.

### 8. Elementor Compatibility
* **Dedicated Elementor Category**: `EventFlow Events`.
* **5 Drag-and-Drop Widgets**:
  1. *Landing Hero Widget*
  2. *Activities Grid Widget*
  3. *Auth Box Widget*
  4. *Participant Dashboard Widget*
  5. *Staff Portal Widget*

### 9. Multi-Format Reporting & Export
* Export participant rosters, complete team structures with all 7 member fields, and financial reconciliations.
* Supported formats: **CSV (UTF-8 BOM)**, **Excel (.xls)**, and **Printable HTML / PDF Dossier**.

---

## 📂 Repository Directory Structure

```text
eventflow/
├── eventflow.php              # Main plugin entrypoint & bootstrap headers
├── uninstall.php              # Secure cleanup & table teardown routine
├── readme.txt                 # WordPress.org standard repository specification
├── README.md                  # Comprehensive project documentation (this file)
├── LICENSE                    # GNU General Public License v2.0
├── CHANGELOG.md               # Version history and release notes
├── CONTRIBUTING.md            # Developer contribution guidelines & standards
├── SECURITY.md                # Security policy & vulnerability reporting
├── .gitignore                 # Version control exclusion rules
│
├── admin/                     # WordPress Admin backoffice controllers & screens
│   ├── class-admin.php        # Admin controller, menus & asset enqueue
│   └── views/                 # 10 Admin management views (dashboard, activities, emails, etc.)
│
├── assets/                    # Compiled assets & design resources
│   ├── css/                   # Scoped styles (frontend, admin, staff dashboard, receipts)
│   ├── js/                    # Vanilla ES6+ & jQuery controllers, Canvas charts, preview modal
│   └── images/                # SVG icons, sample branding, and favicon asset suite
│
├── elementor/                 # Custom Elementor integration
│   └── widgets/               # 5 Drag-and-drop Elementor widget classes
│
├── includes/                  # Core modular business logic classes
│   ├── class-autoloader.php   # Centralized SPL autoloader
│   ├── class-eventflow.php    # Singleton orchestrator
│   ├── class-db.php           # 12-table relational schema & dbDelta migrations
│   ├── class-roles.php        # 7 Custom roles & 15 granular capabilities
│   ├── class-security.php     # Nonces, rate limiting, and audit logging
│   ├── class-auth.php         # Authentication, registration & lockout defense
│   ├── class-registration-manager.php # Registration processing & fee engine
│   ├── class-team-manager.php # Team formation & member validation
│   ├── class-payment-manager.php # Payment submission & secure file handling
│   ├── class-payment-verification.php # Verification engine & audit diffs
│   ├── class-activity-manager.php # Competition tracks & atomic capacity tracking
│   ├── class-event-manager.php # Multi-event orchestration
│   ├── class-analytics-manager.php # 14 KPIs & chart aggregations
│   ├── class-email-manager.php # Pure native wp_mail() dispatcher
│   ├── class-email-queue.php  # Asynchronous background mail scheduler
│   ├── class-email-diagnostics.php # Mail delivery diagnostics & testing
│   ├── class-smtp.php         # SMTP settings & AES-256 password encryption
│   ├── class-shortcodes.php   # Shortcode registrations & query routing
│   ├── class-app-shell.php    # Full-page standalone theme bypass
│   ├── class-participant-portal.php # Participant frontend portal
│   ├── class-staff-dashboard.php # Frontend staff command center
│   ├── class-export-manager.php # Multi-format data exporter
│   ├── class-import-manager.php # CSV data import engine
│   ├── class-notification-manager.php # Announcements & alerts
│   ├── class-favicon.php      # Favicon & touch icon management
│   └── class-ajax-handler.php # Centralized AJAX routing (35+ actions)
│
├── languages/                 # Internationalization & translations
│   └── eventflow.pot          # GetText translation template
│
├── templates/                 # Frontend views and email layouts
│   ├── frontend/              # 10 Frontend templates (app shell, auth, dashboard, etc.)
│   └── emails/                # Modular base email HTML template
│
├── tests/                     # Automated testing suite
│   ├── test-production-audit.php # 66-point master production audit
│   ├── test-registration-system.php # Registration & validation tests
│   ├── test-team-email-verification.php # 101-point email & team suite
│   ├── test-email-system.php  # Email dispatch & template checks
│   ├── test-favicon-suite.php # Favicon & manifest suite
│   ├── test-master-auth-portal-reports.php # Auth, roles & export tests
│   ├── test-payment-rejection-resubmission.php # Payment lifecycle tests
│   ├── test-phpmailer-init.php # PHPMailer configuration checks
│   ├── test-sibacom-master.php # Master end-to-end audit
│   └── test-templates-quality.php # 23-template rendering quality suite
│
└── docs/                      # Architectural & developer references
    ├── CONFIGURATION_GUIDE.md # Admin setup, payments, and branding guide
    ├── CONTEXT.md             # Complete technical architecture reference
    ├── DATABASE_SCHEMA.md     # 12-table relational schema specification
    ├── ELEMENTOR_INTEGRATION.md # Elementor widget configuration guide
    ├── INSTALLATION_GUIDE.md  # Step-by-step turnkey deployment guide
    ├── ROLES_AND_PERMISSIONS.md # Role-Based Access Control matrix
    ├── SHORTCODES_GUIDE.md    # Comprehensive shortcode parameters & examples
    ├── TESTING_REPORT.md      # Testing execution log & verification results
    └── TROUBLESHOOTING.md     # Diagnostics and common resolutions
```

---

## 💻 System Requirements

| Component | Minimum Version | Recommended Version |
| :--- | :--- | :--- |
| **WordPress** | 5.8 | 6.5 - 6.7+ |
| **PHP** | 7.4 | 8.1 / 8.2 / 8.3 |
| **MySQL / MariaDB** | MySQL 5.7 / MariaDB 10.3 | MySQL 8.0+ / MariaDB 10.5+ |
| **PHP Extensions** | `openssl`, `mbstring`, `json`, `gd` | `openssl`, `mbstring`, `json`, `gd`, `fileinfo` |
| **Web Server** | Apache 2.4+ / Nginx 1.18+ | Nginx or Apache with HTTPS enabled |

---

## ⚡ Quickstart & Installation

### Option 1: WordPress Admin Upload
1. Download `eventflow.zip` from the latest release.
2. In your WordPress admin dashboard, navigate to **Plugins** &rarr; **Add New** &rarr; **Upload Plugin**.
3. Select `eventflow.zip` and click **Install Now**.
4. Click **Activate Plugin**.

### Option 2: Git Clone
Clone directly into your WordPress plugins directory:
```bash
cd wp-content/plugins/
git clone https://github.com/engrjamshedali/eventflow.git eventflow
```
Activate the plugin via WP-CLI or the WordPress admin panel:
```bash
wp plugin activate eventflow
```

### Turnkey Page Setup
On activation, EventFlow seeds 12 relational database tables, registers roles, and generates default templates. Publish the following pages with their respective shortcodes:

| Page Purpose | Recommended Slug | Shortcode |
| :--- | :--- | :--- |
| **Event Landing Page** | `/events/` | `[eventflow_landing]` |
| **Split-Screen Auth** | `/login/` | `[eventflow_auth]` |
| **Participant Portal** | `/participant-dashboard/` | `[eventflow_dashboard]` |
| **Staff Command Center** | `/event-dashboard/` | `[eventflow_staff_dashboard]` |
| **Application Tracker** | `/track-registration/` | `[eventflow_track]` |

---

## ⚙️ Shortcodes Reference

| Shortcode | Supported Attributes | Description |
| :--- | :--- | :--- |
| `[eventflow_landing]` | `event_id`, `show_hero`, `show_activities` | Renders the public event landing page with countdown timer and competitions grid. |
| `[eventflow_auth]` | `default_mode="login\|register"`, `redirect_to` | Renders the split-screen authentication card with mode toggling. |
| `[eventflow_dashboard]` | `view="overview\|registrations\|payment\|profile"` | Renders the attendee self-service portal, tickets, and payment vouchers. |
| `[eventflow_staff_dashboard]`| `view="overview\|registrations\|payments\|teams"` | Renders the zero-admin frontend staff management command center. |
| `[eventflow_track]` | `show_search="yes"` | Public registration status checker by Registration ID or email. |

---

## 🔒 Security Architecture

EventFlow is built with security as a primary foundation:
* **Anti-CSRF Protection**: All form submissions and AJAX requests verify cryptographic WordPress nonces (`wp_verify_nonce`).
* **Strict Authorization**: Capability checks (`EventFlow_Roles::can(...)`) are enforced on every administrative and staff endpoint.
* **SQL Injection Prevention**: 100% of dynamic queries use `$wpdb->prepare()` with parameterized placeholders (`%d`, `%s`, `%f`).
* **Brute-Force & Rate Limiting**: Exponential lockout defense automatically locks accounts after 3 failed attempts for 15 minutes.
* **Encrypted Credentials**: Stored SMTP passwords use OpenSSL `AES-256-CBC` symmetric encryption with WordPress `AUTH_KEY` / `AUTH_SALT` derivation.
* **Secure File Handling**: Uploaded payment receipts are validated via `wp_check_filetype_and_ext` against allowed MIME types (`image/jpeg`, `image/png`, `application/pdf`), renamed with cryptographically random hashes, stored in a protected directory, and served through secure verification routes.

---

## 🧪 Automated Testing Suite

EventFlow includes comprehensive automated PHP verification test suites covering 300+ assertions with 100% pass rates:

```bash
# Run production readiness audit suite (66 assertions)
php tests/test-production-audit.php

# Run registration and team size boundary tests
php tests/test-registration-system.php

# Run email engine and team notification audit (101 assertions)
php tests/test-team-email-verification.php

# Run favicon and WebManifest test suite
php tests/test-favicon-suite.php
```

---

## 📖 Extended Documentation

In-depth technical guides are available in the [`docs/`](docs/) directory:
* [Database Relational Schema (`docs/DATABASE_SCHEMA.md`)](docs/DATABASE_SCHEMA.md)
* [Configuration & Payment Accounts Guide (`docs/CONFIGURATION_GUIDE.md`)](docs/CONFIGURATION_GUIDE.md)
* [Roles & Capabilities Matrix (`docs/ROLES_AND_PERMISSIONS.md`)](docs/ROLES_AND_PERMISSIONS.md)
* [Elementor Widget Integration Guide (`docs/ELEMENTOR_INTEGRATION.md`)](docs/ELEMENTOR_INTEGRATION.md)
* [Shortcodes Comprehensive Guide (`docs/SHORTCODES_GUIDE.md`)](docs/SHORTCODES_GUIDE.md)
* [Installation & Turnkey Deployment Guide (`docs/INSTALLATION_GUIDE.md`)](docs/INSTALLATION_GUIDE.md)
* [Architecture & Context Reference (`docs/CONTEXT.md`)](docs/CONTEXT.md)
* [Troubleshooting & Diagnostics (`docs/TROUBLESHOOTING.md`)](docs/TROUBLESHOOTING.md)

---

## 🤝 Contributing

Contributions, bug reports, and pull requests are warmly welcomed! Please read [`CONTRIBUTING.md`](CONTRIBUTING.md) for details on code style, testing requirements, and the pull request submission process.

---

## 🛡️ Security Vulnerability Reporting

If you discover a security vulnerability within EventFlow, please consult [`SECURITY.md`](SECURITY.md) for responsible disclosure procedures. Please do not report security vulnerabilities via public GitHub issues.

---

## 📄 License & Attribution

EventFlow is licensed under the **GNU General Public License v2.0 or later (GPL-2.0-or-later)**. See [`LICENSE`](LICENSE) for full details.

* **Author**: [Jamshed Ali](https://github.com/engrjamshedali) / EventFlow Engineering Team
* **Email**: [engr.jamshed.ali.rind@gmail.com](mailto:engr.jamshed.ali.rind@gmail.com)
* **Website**: [eventflow.dev](https://eventflow.dev/)
