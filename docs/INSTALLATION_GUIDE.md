# EventFlow – Installation & Quickstart Guide

This document provides step-by-step instructions to install, activate, and initialize the **EventFlow – Advanced Event Registration & Management System** on any WordPress installation.

---

## 1. System Requirements

| Component | Minimum Requirement | Recommended |
|---|---|---|
| **WordPress** | 5.8+ | 6.4+ |
| **PHP** | 7.4 | 8.1 / 8.2 |
| **Database** | MySQL 5.7+ or MariaDB 10.3+ | MySQL 8.0+ |
| **PHP Extensions** | `curl`, `json`, `mbstring`, `fileinfo`, `gd` or `imagick` | Enabled |
| **Elementor** | Free or Pro (Optional, fully supported) | Latest |
| **File Permissions** | `wp-content/uploads/` writable (0755 / 0775) | Standard |

---

## 2. Installation Methods

### Method A: Upload Plugin ZIP via WordPress Admin (Recommended)

1. Log into your WordPress Admin Dashboard (`/wp-admin/`).
2. Navigate to **Plugins** &rarr; **Add New**.
3. Click the **Upload Plugin** button at the top of the screen.
4. Click **Choose File** and select `eventflow.zip` from your computer.
5. Click **Install Now**.
6. After installation completes, click **Activate Plugin**.

### Method B: Manual Extraction via FTP / cPanel File Manager

1. Extract the contents of `eventflow.zip` on your local computer. You will obtain a directory named `eventflow`.
2. Connect to your web server via SFTP or cPanel File Manager.
3. Upload the `eventflow` folder directly into:
   ```text
   /wp-content/plugins/
   ```
4. Verify the folder structure:
   ```text
   /wp-content/plugins/eventflow/eventflow.php
   ```
5. In your WordPress Admin, go to **Plugins** &rarr; **Installed Plugins**, find **EventFlow – Advanced Event Registration & Management System**, and click **Activate**.

---

## 3. What Happens on Activation

When activated, EventFlow automatically provisions a complete turnkey environment:
1. **Activates Full-Page Standalone Web App Mode**: Completely eliminates the active WordPress theme's header, navigation menu, logo, sidebar, and footer. Your website immediately behaves like a dedicated web application.
2. **Takes Over Homepage ("/") Seamlessly**:
   - For guests: Immediately displays the full-page Login & Registration portal with live countdown timer, announcements, and competitions preview.
   - For logged-in participants: Immediately displays the full-page Participant Dashboard.
   - For staff: Provides instant toggle access to the full-page Staff Command Center at `/event-dashboard/`.
3. **Creates 11 Custom Relational Tables**: Uses WordPress `dbDelta` to build tables with indexed foreign keys without altering existing theme data.
4. **Registers Custom User Roles & Capabilities**: Installs 6 specialized roles (`eventflow_super_admin`, `eventflow_event_admin`, `eventflow_payment_staff`, `eventflow_registration_staff`, `eventflow_tech_team`, `eventflow_participant`) and grants capabilities to WordPress `administrator`.
5. **Provisions 5 Dedicated Pages Instantly**:
   - `/event-portal/` (Public Landing Portal)
   - `/participant-login/` (Authentication Portal)
   - `/participant-dashboard/` (Candidate Dashboard)
   - `/event-dashboard/` (Frontend Staff & Organizer Command Center)
   - `/track-status/` (Public Registration Tracker)
6. **Seeds Default Active Event & Competitions**: Deploys a ready-to-test event (*"National Tech Olympiad 2026"*) with 3 sample competitions (*Speed Programming Championship*, *Autonomous Robotics Clash*, *UI/UX Designathon*).
7. **Seeds Sample Submissions**: Seeds sample team registrations and a payment verification test case (`EF-2026-9M2X4`) ready for instant testing in the verification inspector.
8. **Seeds 16 Professional Email Templates**: Populates rich HTML notification triggers ready for dynamic use.
9. **Configures Protected File Directory**: Creates `/wp-content/uploads/eventflow-secure/` with a protective `.htaccess` (`Deny from all`) preventing unauthorized public browsing of sensitive participant payment receipts.

---

## 4. Dedicated Pages Overview

The following 5 pages are automatically created upon plugin activation:

| Page Title | URL Slug | Shortcode / Content | Purpose |
|---|---|---|---|
| **Event Portal / Home** | `/event-portal/` | `[eventflow_landing]` | Public landing page with hero banner, countdown timer, announcements, and competitions grid. |
| **Participant Login** | `/participant-login/` | `[eventflow_auth]` | Participant sign in, account registration, and password recovery. |
| **Participant Dashboard** | `/participant-dashboard/` | `[eventflow_dashboard]` | Participant self-service area: profile, applications list, payment submission, and entry voucher downloads. |
| **Staff Command Center** | `/event-dashboard/` | `[eventflow_staff_dashboard]` | Dedicated frontend portal for organizers and payment staff (no WP-admin access needed). |
| **Track Registration** | `/track-status/` | `[eventflow_track]` | Public tool allowing participants to check status using Registration ID & email. |

---

## 5. Permalinks Refresh

To ensure clean routing of AJAX endpoints and secure proof downloads:
1. Go to **Settings** &rarr; **Permalinks** in WordPress Admin.
2. Ensure permalinks are set to **Post name** (`/%postname%/`).
3. Click **Save Changes** to flush the rewrite cache.

