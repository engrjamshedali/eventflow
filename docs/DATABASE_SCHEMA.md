# EventFlow – Database Schema Documentation

EventFlow utilizes an optimized, high-performance relational database structure deployed via WordPress `dbDelta()` routines with standard `$wpdb->prefix`.

All tables feature UTF-8/UTF-8MB4 collation support, indexes on primary/foreign lookup keys, and timestamps.

---

## 1. Tables Overview

| Table Name | Description | Key Relationships |
|---|---|---|
| `wp_eventflow_events` | Stores event configurations, schedules, and branding | Master entity |
| `wp_eventflow_activities` | Competition tracks, solo/team rules, limits, and fees | Child of `events` |
| `wp_eventflow_registrations` | Participant application records and lifecycle states | Belongs to `events`, `activities`, `users` |
| `wp_eventflow_teams` | Registered teams and team leader metadata | Belongs to `registrations` |
| `wp_eventflow_team_members` | Complete roster of 7 required member fields | Belongs to `teams`, `registrations` |
| `wp_eventflow_payments` | Financial submissions, transaction IDs, and receipts | Belongs to `registrations`, `users` |
| `wp_eventflow_payment_logs` | Verification audit history and status notes | Belongs to `payments`, `registrations` |
| `wp_eventflow_email_templates` | 16 HTML automated notification triggers | Standalone / Configuration |
| `wp_eventflow_email_logs` | Delivery log tracking sent/failed emails | Standalone / Diagnostics |
| `wp_eventflow_announcements` | Event & activity broadcast messages | Belongs to `events`, `activities` |
| `wp_eventflow_audit_logs` | Staff and administrative action audit trail | Standalone / Security |
| `wp_eventflow_forms` | Dynamic visual form schemas for custom competition questionnaires | Belongs to `events`, `activities` |

---

## 2. Table Specifications & DDL

### 1. `wp_eventflow_events`
Stores global event settings, registration opening/closing dates, contact coordinates, base fees, and branding assets.

```sql
CREATE TABLE wp_eventflow_events (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    title varchar(255) NOT NULL,
    slug varchar(200) NOT NULL,
    description longtext DEFAULT NULL,
    banner_url varchar(500) DEFAULT NULL,
    logo_url varchar(500) DEFAULT NULL,
    start_date datetime DEFAULT NULL,
    end_date datetime DEFAULT NULL,
    reg_start_date datetime DEFAULT NULL,
    reg_end_date datetime DEFAULT NULL,
    status varchar(50) NOT NULL DEFAULT 'active', -- 'active', 'draft', 'closed'
    contact_email varchar(191) DEFAULT NULL,
    contact_phone varchar(100) DEFAULT NULL,
    reg_fee decimal(10,2) NOT NULL DEFAULT 0.00,
    currency varchar(10) NOT NULL DEFAULT 'PKR',
    payment_info longtext DEFAULT NULL,
    qr_code_url varchar(500) DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY slug (slug(191)),
    KEY status (status)
);
```

---

### 2. `wp_eventflow_activities`
Represents individual competitions, hackathons, or activities within an event. Defines participation modes (Solo, Team, Both), minimum/maximum team members, capacity limits, and fees.

```sql
CREATE TABLE wp_eventflow_activities (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    event_id bigint(20) unsigned NOT NULL,
    title varchar(255) NOT NULL,
    slug varchar(200) NOT NULL,
    description longtext DEFAULT NULL,
    image_url varchar(500) DEFAULT NULL,
    eligibility text DEFAULT NULL,
    reg_deadline datetime DEFAULT NULL,
    participation_type varchar(20) NOT NULL DEFAULT 'both', -- 'solo', 'team', 'both'
    min_team_size int(11) NOT NULL DEFAULT 1,
    max_team_size int(11) NOT NULL DEFAULT 4,
    leader_counts_as_member tinyint(1) NOT NULL DEFAULT 1,
    reg_fee decimal(10,2) NOT NULL DEFAULT 0.00,
    max_participants int(11) NOT NULL DEFAULT 0, -- 0 for unlimited
    current_participants int(11) NOT NULL DEFAULT 0,
    form_fields longtext DEFAULT NULL, -- Dynamic visual form JSON schema
    instructions text DEFAULT NULL,
    status varchar(50) NOT NULL DEFAULT 'published', -- 'published', 'draft', 'closed'
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY event_id (event_id),
    KEY slug (slug(191)),
    KEY status (status)
);
```

---

### 3. `wp_eventflow_registrations`
The core application table linking participant accounts with chosen competitions.

```sql
CREATE TABLE wp_eventflow_registrations (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    reg_id varchar(64) NOT NULL, -- e.g. EF-2026-X9K2L
    event_id bigint(20) unsigned NOT NULL,
    activity_id bigint(20) unsigned NOT NULL,
    user_id bigint(20) unsigned NOT NULL,
    participation_type varchar(20) NOT NULL DEFAULT 'solo', -- 'solo', 'team'
    team_name varchar(191) DEFAULT NULL,
    total_fee decimal(10,2) NOT NULL DEFAULT 0.00,
    custom_fields longtext DEFAULT NULL, -- Serialized JSON custom form response values
    payment_status varchar(50) NOT NULL DEFAULT 'pending', -- 'pending', 'under_verification', 'verified', 'rejected', 'reupload_requested'
    application_status varchar(50) NOT NULL DEFAULT 'submitted', -- 'in_progress', 'submitted', 'under_review', 'approved', 'rejected', 'completed'
    admin_notes text DEFAULT NULL,
    rejection_reason text DEFAULT NULL,
    verified_by bigint(20) unsigned DEFAULT NULL,
    verified_at datetime DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    UNIQUE KEY reg_id (reg_id),
    KEY event_id (event_id),
    KEY activity_id (activity_id),
    KEY user_id (user_id),
    KEY payment_status (payment_status),
    KEY application_status (application_status)
);
```

---

### 4. `wp_eventflow_teams`
Stores high-level team entity data and team leader contact information.

```sql
CREATE TABLE wp_eventflow_teams (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    registration_id bigint(20) unsigned NOT NULL,
    team_name varchar(191) NOT NULL,
    leader_user_id bigint(20) unsigned NOT NULL,
    leader_name varchar(191) NOT NULL,
    leader_email varchar(191) NOT NULL,
    leader_phone varchar(100) NOT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY registration_id (registration_id),
    KEY leader_user_id (leader_user_id)
);
```

---

### 5. `wp_eventflow_team_members`
Stores the complete roster of candidates. Every member records all 7 required academic and contact fields.

```sql
CREATE TABLE wp_eventflow_team_members (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    team_id bigint(20) unsigned NOT NULL,
    registration_id bigint(20) unsigned NOT NULL,
    full_name varchar(191) NOT NULL,
    father_name varchar(191) NOT NULL,
    semester varchar(50) NOT NULL,
    department varchar(191) NOT NULL,
    university varchar(191) NOT NULL,
    email varchar(191) NOT NULL,
    contact_number varchar(100) NOT NULL,
    is_leader tinyint(1) NOT NULL DEFAULT 0,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY team_id (team_id),
    KEY registration_id (registration_id),
    KEY email (email(191))
);
```

---

### 6. `wp_eventflow_payments`
Stores proof of payment submissions, transaction reference numbers, amounts, and protected file paths.

```sql
CREATE TABLE wp_eventflow_payments (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    registration_id bigint(20) unsigned NOT NULL,
    user_id bigint(20) unsigned NOT NULL,
    transaction_id varchar(191) NOT NULL,
    payment_method varchar(50) NOT NULL,
    amount decimal(10,2) NOT NULL DEFAULT 0.00,
    payment_date date NOT NULL,
    screenshot_url varchar(500) NOT NULL,
    screenshot_path varchar(500) NOT NULL,
    file_type varchar(50) NOT NULL,
    file_size bigint(20) unsigned NOT NULL,
    status varchar(50) NOT NULL DEFAULT 'under_verification',
    notes text DEFAULT NULL,
    rejection_reason text DEFAULT NULL,
    verified_by bigint(20) unsigned DEFAULT NULL,
    verified_at datetime DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY registration_id (registration_id),
    KEY user_id (user_id),
    KEY transaction_id (transaction_id(191)),
    KEY status (status)
);
```

---

### 7. `wp_eventflow_payment_logs`
Maintains immutable verification logs and audit entries whenever staff verify, reject, or request re-uploads.

```sql
CREATE TABLE wp_eventflow_payment_logs (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    payment_id bigint(20) unsigned NOT NULL,
    registration_id bigint(20) unsigned NOT NULL,
    action varchar(100) NOT NULL,
    note text DEFAULT NULL,
    performed_by bigint(20) unsigned NOT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY payment_id (payment_id),
    KEY registration_id (registration_id)
);
```

---

### 8. `wp_eventflow_email_templates`
Contains the 16 automated email notification templates with placeholders.

```sql
CREATE TABLE wp_eventflow_email_templates (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    template_key varchar(100) NOT NULL,
    name varchar(191) NOT NULL,
    subject varchar(255) NOT NULL,
    body longtext NOT NULL,
    placeholders_help text DEFAULT NULL,
    is_active tinyint(1) NOT NULL DEFAULT 1,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    UNIQUE KEY template_key (template_key)
);
```

---

### 9. `wp_eventflow_email_logs`
Tracks outgoing notification status, recipient, timestamp, and failure diagnostics.

```sql
CREATE TABLE wp_eventflow_email_logs (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    template_key varchar(100) NOT NULL,
    recipient_email varchar(191) NOT NULL,
    recipient_name varchar(191) DEFAULT NULL,
    subject varchar(255) NOT NULL,
    status varchar(50) NOT NULL DEFAULT 'sent', -- 'sent', 'failed'
    error_message text DEFAULT NULL,
    sent_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY template_key (template_key),
    KEY recipient_email (recipient_email(191)),
    KEY status (status)
);
```

---

### 10. `wp_eventflow_announcements`
Stores broadcast announcements published by administrators or technical teams.

```sql
CREATE TABLE wp_eventflow_announcements (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    event_id bigint(20) unsigned NOT NULL,
    activity_id bigint(20) unsigned DEFAULT NULL,
    title varchar(255) NOT NULL,
    content text NOT NULL,
    target_audience varchar(50) NOT NULL DEFAULT 'all',
    created_by bigint(20) unsigned NOT NULL,
    is_active tinyint(1) NOT NULL DEFAULT 1,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY event_id (event_id),
    KEY activity_id (activity_id),
    KEY is_active (is_active)
);
```

---

### 11. `wp_eventflow_audit_logs`
Comprehensive operational log recording critical admin/staff actions, user logins, registrations, and IP addresses.

```sql
CREATE TABLE wp_eventflow_audit_logs (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    user_id bigint(20) unsigned DEFAULT NULL,
    action varchar(100) NOT NULL,
    object_type varchar(100) NOT NULL,
    object_id varchar(100) DEFAULT NULL,
    details text DEFAULT NULL,
    ip_address varchar(100) DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY user_id (user_id),
    KEY action (action),
    KEY object_type (object_type)
);
```

---

### 12. `wp_eventflow_forms`
Stores visual dynamic form definitions, field types, labels, rules, and property configurations associated with specific competition tracks.

```sql
CREATE TABLE wp_eventflow_forms (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    title varchar(255) NOT NULL,
    activity_id bigint(20) unsigned NOT NULL,
    event_id bigint(20) unsigned DEFAULT NULL,
    fields longtext NOT NULL,
    status varchar(50) NOT NULL DEFAULT 'published',
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY activity_id (activity_id),
    KEY event_id (event_id),
    KEY status (status)
);
```

