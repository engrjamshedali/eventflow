# EventFlow – Configuration Guide

This guide details all settings and administrative options available in EventFlow.

---

## 1. Accessing EventFlow Settings

You can configure EventFlow either from:
- **WordPress Admin**: Go to **EventFlow** &rarr; **Settings**.
- **Frontend Staff Portal**: Open `/event-dashboard/?view=settings` (accessible by Super Administrators).

---

## 2. Event Configuration & Scheduling

### Creating or Editing an Event
Navigate to **EventFlow** &rarr; **Events**:
- **Event Title**: The official name of your symposium, summit, or championship.
- **Slug**: URL-friendly slug.
- **Description**: Rich summary displayed on the event landing hero.
- **Start & End Dates**: Main event calendar dates.
- **Registration Opening Date**: When registration buttons become active.
- **Registration Closing Deadline**: Exact date/time when registrations lock. The landing page countdown timer automatically targets this timestamp.
- **Status**:
  - `Active`: Event is publicly visible and accepting registrations.
  - `Draft`: Event is hidden for administrative setup.
  - `Closed`: Event registration is locked.
- **Branding Assets**:
  - **Banner URL**: Upload a high-resolution hero background image (1920x600 recommended) via the WordPress Media Library.
  - **Logo URL**: Official event logo (PNG or SVG with transparent background recommended).

---

## 3. Financial Payment Configuration

Configure the exact payment methods participants see upon registration in **EventFlow** &rarr; **Settings**:

### 1. Bank Wire & Online Transfer (IBFT)
- **Bank Name**: Name of your institution (e.g. *Bank Alfalah, HBL, Meezan*).
- **Account Title**: Exact title matching bank registration (e.g. *Tech Olympiad Society*).
- **Account Number**: Digits without dashes or with standard format.
- **IBAN**: International Bank Account Number (e.g. *PK65ALFH01234567890123*).
*(Note: On the participant payment page, account numbers feature one-click "Copy" buttons).*

### 2. Mobile Wallets (Easypaisa & JazzCash)
- **Easypaisa Account Number & Title**: Specify your official mobile account number.
- **JazzCash Account Number & Title**: Specify your official mobile account number.

### 3. QR Code Instant Scan
- **QR Code URL**: Click **Media Library** to upload your merchant Raast / banking payment QR code.
- Participants will see the QR code directly on their payment page alongside account numbers.

### 4. Custom Payment Instructions
- Provide step-by-step instructions (e.g., *"Take a screenshot of the successful transaction receipt showing the TID. Upload within 24 hours to secure your team slot."*).

---

## 4. Competitions & Activity Configuration

Under **EventFlow** &rarr; **Activities** (or Frontend Portal &rarr; **Activities & Limits**), click **Create New Activity**:

| Field | Configuration Advice |
|---|---|
| **Activity Title** | Specific track name (e.g. *Speed Programming Championship*, *Robotics Arena*). |
| **Participation Type** | Choose **Solo Only**, **Team Only**, or **Both Solo and Team**. |
| **Min Team Size** | Lowest permissible team members (e.g. 2). |
| **Max Team Size** | Highest permissible team members (e.g. 4). |
| **Leader Counts as Member** | When checked, the team leader counts toward the team size cap. |
| **Registration Fee** | Track-specific fee (e.g. 1500.00). Overrides the event base fee. Set to `0` for free entry. |
| **Capacity Limit** | Maximum participant or team slots. When reached, registrations close automatically. Set `0` for unlimited. |
| **Eligibility Criteria** | Clear prerequisites (e.g. *Undergraduate CS students with valid student ID*). |
| **Special Instructions & Rules** | Hardware restrictions, compiler rules, deliverables. |
| **Status** | Set to `Published` to make it visible to participants. |

---

## 5. Automated Email Templates & SMTP Setup

EventFlow comes pre-loaded with **16 automated email notification triggers** under **EventFlow** &rarr; **Email Templates**:
1. Participant Account Created
2. Welcome Email
3. Successful Login Alert
4. Registration Submitted
5. Registration Confirmation
6. Payment Submitted
7. Payment Under Verification
8. Payment Verified & Approved
9. Payment Rejected
10. Payment Re-upload Requested
11. Application Approved
12. Application Rejected
13. Registration Deadline Reminder
14. Activity Announcement
15. Event Update
16. Event Completion Notification

### Customizing Email Layouts
Click any template to edit its subject and rich HTML body. Use dynamic merge tags:
- `{participant_name}`: Leader or candidate name.
- `{team_name}`: Official team name.
- `{registration_id}`: Unique application identifier (e.g. `EF-2026-X9K2L`).
- `{activity_name}`: Competition track title.
- `{payment_amount}`: Amount with currency.
- `{website_url}`: Portal link.
- `{rejection_reason}`: Feedback note when rejecting payment or application.

### Recommended SMTP Configuration
For production deliverability, install any standard SMTP plugin (e.g., *WP Mail SMTP, Post SMTP, or FluentSMTP*) and authenticate with your domain's email server (SendGrid, Mailgun, Amazon SES, or Google Workspace). Use the **Send Test Email** button in **EventFlow** &rarr; **Email Templates** to verify.

---

## 6. Appearance & Branding Colors

In **EventFlow** &rarr; **Settings** &rarr; **Appearance**:
- **Primary Brand Color**: Set your organization or event primary color (default: `#4F46E5`).
- **Secondary Accent Color**: Set your secondary highlight color (default: `#06B6D4`).
All buttons, badges, countdown timers, and email header banners dynamically inherit these colors automatically.

