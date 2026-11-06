# EventFlow – Troubleshooting & Maintenance Guide

This document provides quick diagnostics and solutions for common scenarios encountered during deployment and operation.

---

## 1. Notification Emails Not Being Received

### Symptom
Participants do not receive welcome, payment confirmation, or approval emails.

### Cause & Resolution
1. **Default PHP `mail()` Disabled on Hosting**: Many shared and cloud hosts block the default PHP `mail()` function used by standard WordPress installations.
   - **Fix**: Install and configure an SMTP plugin (e.g. *WP Mail SMTP*, *FluentSMTP*, or *Post SMTP*) and connect to an authenticated email delivery service (SendGrid, Mailgun, Amazon SES, or Gmail/Google Workspace).
2. **Inspect Delivery Logs**:
   - Go to **EventFlow** &rarr; **Audit Logs** in WordPress Admin and review the **Email Automation Logs** table.
   - If status shows `failed`, inspect the error message recorded by the system.
3. **Send Test Email**:
   - Navigate to **EventFlow** &rarr; **Email Templates**.
   - Enter your email address in the **Test SMTP / Mail Delivery** box and click **Send Test Email**.

---

## 2. Payment Proof Upload Errors

### Symptom
Participant uploads screenshot and receives *"File size exceeds maximum allowed limit"* or *"Upload failed"*.

### Cause & Resolution
1. **File Format**: EventFlow accepts **JPG, JPEG, PNG, and PDF** files only. Executable or unsupported formats (HEIC, TIFF) are rejected for security.
2. **File Size Limit**: By default, EventFlow enforces a maximum file size of **5 MB**. Ensure the participant's image is within this limit.
3. **Directory Permissions**:
   - Check that `/wp-content/uploads/` is writable by the web server (typically `0755` for folders and `0644` for files).
   - Ensure the secure subfolder `/wp-content/uploads/eventflow-secure/` exists. If missing, navigate to **EventFlow** &rarr; **Settings** and click **Save** to trigger folder creation.

---

## 3. "Access Denied" (403) on Verification Actions

### Symptom
Staff member clicks "Verify Payment" or "Update Status" and sees a permission error.

### Cause & Resolution
1. **Nonce Expiration**: If a dashboard tab remains open across several hours, the WordPress security nonce may expire.
   - **Fix**: Simply refresh the browser page.
2. **Role Capability Missing**:
   - Verify that the logged-in user possesses the required capability. For example, payment verification requires `eventflow_verify_payments`.
   - Go to **EventFlow** &rarr; **Staff & Roles** and ensure the user is assigned the correct staff role (e.g., *Payment Verification Staff*, *Event Administrator*, or *Super Administrator*).

---

## 4. Elementor Widgets Not Visible in Editor

### Symptom
When editing with Elementor, the **EventFlow Events** category is not showing.

### Cause & Resolution
1. **Elementor Activation**: Ensure Elementor is activated. EventFlow checks `did_action( 'elementor/loaded' )` before registering widgets to prevent PHP fatal errors when Elementor is not present.
2. **Regenerate Elementor CSS / Assets**:
   - In WP Admin, go to **Elementor** &rarr; **Tools** &rarr; **General** &rarr; **Regenerate Files & Data**.

---

## 5. 404 Errors on Dedicated Pages

### Symptom
Navigating to `/event-dashboard/` or `/participant-dashboard/` yields a 404 Page Not Found error.

### Cause & Resolution
1. **Create the Pages**: Ensure you have created WordPress pages containing the respective shortcodes (`[eventflow_staff_dashboard]` and `[eventflow_dashboard]`).
2. **Flush Permalinks**:
   - Go to **Settings** &rarr; **Permalinks** in WordPress Admin.
   - Confirm permalink structure is set to **Post name**.
   - Click **Save Changes** without modifying anything to flush the rewrite table.

