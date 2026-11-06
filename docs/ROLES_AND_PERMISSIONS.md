# EventFlow – Roles & Permissions Architecture (RBAC)

EventFlow provides a comprehensive Role-Based Access Control (RBAC) system with granular capabilities. This ensures administrative staff, financial evaluators, and technical teams only have access to their designated areas, while keeping participants isolated to their personal portal.

---

## 1. User Roles Overview

### 1. Master Administrator / Super Administrator
- **Internal Slug**: `administrator` (native WP) and `eventflow_super_admin`
- **Scope**: Full system control.
- **Capabilities**:
  - Full plugin settings configuration.
  - Event creation, scheduling, and deletion.
  - Activity creation, modification, and rule setting.
  - All registration reviews, edits, and deletions.
  - Payment verification, rejection, and audit inspection.
  - Email automation template customization and SMTP tests.
  - Staff provisioning and user role assignment.
  - Full analytics and multi-format data export.

### 2. Event Administrator
- **Internal Slug**: `eventflow_event_admin`
- **Scope**: Event operations manager.
- **Capabilities**:
  - Manage event details and schedules.
  - Create and edit activities.
  - Review all registrations.
  - Verify and reject payments.
  - Publish broadcasts and announcements.
  - View real-time analytics and export reports.

### 3. Payment Verification Staff
- **Internal Slug**: `eventflow_payment_staff`
- **Scope**: Finance and verification committee.
- **Capabilities**:
  - Dedicated access to the **Payment Verification Dashboard**.
  - Interactive screenshot inspection (zoom, pan, rotate, PDF preview).
  - Verify payments, approve transactions, or request re-uploads.
  - View financial audit history and transaction IDs.
  - *Does not have access to general settings, email templates, or activity creation.*

### 4. Registration Management Staff
- **Internal Slug**: `eventflow_registration_staff`
- **Scope**: Attendee coordination and credentials team.
- **Capabilities**:
  - View and search all registration records.
  - Review participant and team member rosters (7 fields).
  - Update application review statuses (Submitted, Under Review, Approved, Rejected).
  - Export participant rosters to CSV / Excel / Printable PDF.
  - *Does not have financial payment approval capabilities.*

### 5. Technical Team
- **Internal Slug**: `eventflow_tech_team`
- **Scope**: Competition rules and track conveners.
- **Capabilities**:
  - Create, publish, and edit competition activities.
  - Configure participation modes (Solo, Team, Both).
  - Define minimum/maximum team sizes, capacity limits, and eligibility criteria.
  - Publish track-specific announcements.
  - *Does not have access to financial payments or user accounts.*

### 6. Participant
- **Internal Slug**: `eventflow_participant`
- **Scope**: Standard candidate / team leader.
- **Capabilities**:
  - Browse published competitions.
  - Submit individual or team registration forms.
  - View personal applications and tracking states.
  - Submit and re-upload payment screenshots.
  - Download official confirmation vouchers and receipts.
  - *Strictly restricted from viewing administrative or staff data.*

---

## 2. Granular Capabilities Matrix

| Capability Slug | Super Admin | Event Admin | Payment Staff | Reg Staff | Tech Team | Participant |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| `eventflow_manage_settings` | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `eventflow_manage_roles` | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `eventflow_manage_staff` | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `eventflow_manage_events` | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `eventflow_manage_activities` | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| `eventflow_view_registrations` | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| `eventflow_edit_registrations` | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| `eventflow_delete_registrations` | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `eventflow_verify_payments` | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| `eventflow_view_payments` | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| `eventflow_manage_announcements`| ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `eventflow_export_data` | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| `eventflow_view_analytics` | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `eventflow_manage_emails` | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `eventflow_staff_access` | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| `eventflow_participant_access` | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ |

---

## 3. How to Provision Staff Accounts

### Option 1: Via the Frontend Staff Portal (No WP-Admin Required)
1. Log in as a Super Administrator and open the staff portal (`/event-dashboard/`).
2. Click **Staff & Permissions** in the sidebar.
3. Fill in:
   - **Staff Full Name**
   - **Email Address**
   - **Assigned Role** (select from dropdown)
   - **Initial Password** (or leave blank for auto-generation)
4. Click **Save & Assign Permissions**.

### Option 2: Via WordPress Admin
1. Go to **EventFlow** &rarr; **Staff & Roles**.
2. Complete the staff provision form and assign the desired role.

