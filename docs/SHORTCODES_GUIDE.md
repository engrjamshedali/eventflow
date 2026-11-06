# EventFlow – Shortcodes Guide

EventFlow provides a modular shortcode library enabling seamless deployment across standard WordPress block/classic editors, page builders, and custom templates.

---

## Shortcodes Reference Table

| Shortcode | Purpose & Description | Parameters | Default / Recommended Page |
|---|---|---|---|
| `[eventflow_landing]` | Full event landing page: hero, countdown timer, status badge, announcements, and competitions grid. | None | Home or `/event/` |
| `[eventflow_auth]` | Tabbed participant authentication box: Login, Registration, and Forgot Password. | None | `/participant-login/` |
| `[eventflow_login]` | Dedicated participant login form only. | None | Any login page |
| `[eventflow_register]` | Dedicated participant registration form only. | None | Any registration page |
| `[eventflow_dashboard]` | Complete participant self-service dashboard: profile, applications list, payment links, and entry pass voucher. | None | `/participant-dashboard/` |
| `[eventflow_activities]` | Responsive grid of all published activities and competitions with modal details and register triggers. | None | Competitions track page |
| `[eventflow_activity_details]` | Single competition detailed overview (rules, eligibility, fees, deadlines, capacity). | `id` (Activity ID) | Single activity template |
| `[eventflow_registration_form]` | Dynamic solo and team registration form with 7 member fields and live validation. | `activity_id` (Activity ID) | Registration page |
| `[eventflow_payment]` | Financial payment page: bank details, QR code, and screenshot upload dropzone. | `registration_id` (Reg ID) | `/payment/` |
| `[eventflow_track]` | Public application and payment status lookup tool. | None | `/track-status/` |
| `[eventflow_staff_dashboard]` | Full frontend staff and admin management command center. | None | `/event-dashboard/` |

---

## Detailed Usage Examples

### 1. Event Landing Page
```html
[eventflow_landing]
```
Displays:
- Event title, logo, and description.
- Hero background image or gradient overlay.
- Real-time countdown timer targeting registration closing deadline.
- Registration status pill (*Open, Closed, Opening Soon*).
- Important announcements ticker.
- Official competitions grid with "Rules & Info" modal buttons.

---

### 2. Participant Authentication
```html
[eventflow_auth]
```
Displays a clean SaaS-style card with smooth tab switching:
- **Sign In**: Email/username + password + remember me.
- **Create Account**: Full name, email, password, phone, university, department, semester. Automatically logs the user in and redirects to the participant dashboard.
- **Forgot Password**: Secure email lookup with password reset dispatch.

---

### 3. Participant Dashboard
```html
[eventflow_dashboard]
```
Protected participant portal. If the visitor is not logged in, an authentication box is displayed. Once authenticated, participants can:
- View their registered competitions and unique Registration IDs.
- Monitor live status indicators:
  - *Not Registered*
  - *Registration In Progress*
  - *Payment Pending*
  - *Payment Under Verification*
  - *Verified*
  - *Rejected*
  - *Approved*
  - *Completed*
- Submit and re-upload payment screenshots.
- Print official entry confirmation vouchers.

---

### 4. Dynamic Team Registration Form
```html
[eventflow_registration_form activity_id="1"]
```
Parameters:
- `activity_id` (optional): The ID of the activity. If omitted, EventFlow automatically reads the `?activity_id=` query string from the URL.

Features:
- Solo / Team participation switcher (if allowed by track).
- Team name field.
- **Add Team Member** dynamic button.
- 7 mandatory fields per member: Full Name, Father's Name, Semester, Department, University, Email, Contact Phone.
- Client-side and server-side validation enforcing min/max team limits.
- Instant submission via AJAX with redirect to payment.

---

### 5. Frontend Staff Command Center
```html
[eventflow_staff_dashboard]
```
Full management suite for authorized staff:
- Live KPI cards (Total Registrations, Participants, Teams, Pending Verifications, Verified Revenue).
- Interactive Canvas Charts (Daily trends, track distributions, status breakdown).
- Searchable registrations table with detail inspection modals.
- **Payment Verification Inspector** with image zoom, pan, rotate, and PDF preview.
- Activity CRUD, announcements broadcast, and data export.
*(Note: Non-staff users are blocked with access restriction messages).*

