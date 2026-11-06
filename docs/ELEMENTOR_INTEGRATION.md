# EventFlow – Elementor Integration Guide

EventFlow includes native, deep compatibility with Elementor (both Free and Pro editions). It registers dedicated widgets within the Elementor panel, allowing you to design beautiful event landing pages and registration portals without writing code.

---

## 1. Native Elementor Widgets Category

When editing any page with Elementor, open the widget panel and scroll down to the **EventFlow Events** category:

```text
┌───────────────────────────────┐
│       EventFlow Events        │
├───────────────────────────────┤
│ [❖] EventFlow Landing Hero    │
│ [❖] EventFlow Activities Grid │
│ [❖] EventFlow Login & Register│
│ [❖] Participant Dashboard     │
│ [❖] Staff Management Portal   │
└───────────────────────────────┘
```

---

## 2. Available Elementor Widgets

### 1. `EventFlow Landing Hero`
- **Widget Name**: `eventflow_landing_widget`
- **Category**: `eventflow-elements`
- **Controls**:
  - **Show Countdown Timer**: Toggle countdown on/off.
  - **Show Announcements Bar**: Toggle live announcement ticker on/off.
- **Output**: Beautiful hero section with active event title, description, status badge, live countdown, and registration action buttons.

### 2. `EventFlow Activities Grid`
- **Widget Name**: `eventflow_activities_widget`
- **Category**: `eventflow-elements`
- **Controls**:
  - **Section Title**: Customize heading text above the competition grid.
- **Output**: Responsive grid of all active competitions with solo/team tags, fees, capacity, "Rules & Info" modal buttons, and direct registration links.

### 3. `EventFlow Login & Registration`
- **Widget Name**: `eventflow_auth_widget`
- **Category**: `eventflow-elements`
- **Output**: Tabbed sign in, participant account creation, and password recovery card with smooth transitions and AJAX authentication.

### 4. `Participant Dashboard`
- **Widget Name**: `eventflow_dashboard_widget`
- **Category**: `eventflow-elements`
- **Output**: Full participant portal where logged-in candidates view their registered tracks, upload payment screenshots, track review status, and download confirmation vouchers.

### 5. `Staff Management Portal`
- **Widget Name**: `eventflow_staff_dashboard_widget`
- **Category**: `eventflow-elements`
- **Output**: Frontend administration and verification command center featuring real-time charts, registration search/filters, and the screenshot preview inspector.

---

## 3. How to Build an Event Landing Page with Elementor

1. Create a new page (**Pages** &rarr; **Add New**), title it **Summit 2026**, and set the Page Template to **Elementor Full Width**.
2. Click **Edit with Elementor**.
3. Drag the **EventFlow Landing Hero** widget into your top section.
4. Below it, add a section and drag the **EventFlow Activities Grid** widget.
5. In the next section, add your organizers, sponsors, or campus venue Google Map.
6. Click **Publish**.

---

## 4. Shortcode Widget Alternative

You can also use Elementor's native **Shortcode** widget:
- Drag the **Shortcode** widget to any column.
- Enter any EventFlow shortcode (e.g. `[eventflow_landing]`, `[eventflow_auth]`, `[eventflow_dashboard]`).
- Click **Apply**.

