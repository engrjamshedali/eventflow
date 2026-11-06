# Changelog

All notable changes to the **EventFlow** WordPress plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [5.0.2] - 2026-11-06

### Added
- **Repository Documentation Suite**: Comprehensive WordPress.org-standard `readme.txt`, detailed `README.md`, `CONTRIBUTING.md`, `SECURITY.md`, and `LICENSE` (GPLv2+).
- **Localized Test Suite Execution**: Automated test scripts in `tests/` now dynamically detect both modular plugin root execution and standalone testing harnesses.
- **Architectural Documentation**: Curated technical manuals in `docs/` covering configuration, database schema, Elementor widgets, RBAC capabilities, shortcodes, and troubleshooting.

### Fixed
- **Analytics SQL Hardening**: Enforced strict `$wpdb->prepare()` queries and explicit integer casting in `EventFlow_Analytics_Manager::get_overview_kpis()`.
- **POT Template Metadata**: Updated translation catalog header in `languages/eventflow.pot` to match version `5.0.2`.

---

## [5.0.1] - 2026-11-04

### Added
- **Split-Screen Authentication Design**: Reference-accurate card interface on light mint background (`#F0F7F5`) with teal gradients (`#36B49F` to `#27A991`).
- **Brute-Force & Lockout Defense**: Exponential lockout defense automatically locks accounts after 3 failed login attempts for 15 minutes, with administrator 1-click unlock.
- **Favicon & WebManifest Engine**: Integrated PWA WebManifest and multi-resolution favicon generation (`16px`, `32px`, `48px`, Apple Touch Icon, Android Chrome).

### Changed
- **Email Queue Performance**: Optimized cron scheduler batch size with atomic database locks preventing concurrent dispatches.

---

## [5.0.0] - 2026-11-03

### Added
- **Full-Page Standalone Web App Mode**: Implemented `EventFlow_App_Shell` to completely bypass active WordPress theme headers, navigation bars, sidebars, and footers.
- **In-App Zero-Download Payment Proof Inspector**: SVG/Canvas-based receipt viewer featuring zoom in/out, 360° rotation, drag/pan, and embedded PDF inspection.
- **Real-Time Canvas Analytics**: 14 database-derived operational KPIs and 9 native HTML5 Canvas charts with zero external CDN dependencies.
- **Frontend Staff Command Center**: Zero-admin management portal accessible via `/event-dashboard/` or `[eventflow_staff_dashboard]`.

---

## [4.0.2] - 2026-11-01

### Fixed
- **Password Reset Parameter Order**: Resolved parameter sequence in `handle_forgot_password()` for proper email template slug mapping.
- **Proof Path Query Resolution**: Resolved `proof_path` column aliasing in participant registration queries.

### Added
- **Dynamic Form Builder**: Custom registration schema builder supporting 18 field types with database persistence.

---

## [3.0.1] - 2026-10-25

### Added
- **Elementor Page Builder Integration**: Registered `EventFlow Events` widget category with 5 drag-and-drop Elementor widgets.
- **Asynchronous Mail Queuing**: Background queuing engine with retry logic and delivery audit logging.

---

## [1.1.0] - 2026-10-15

### Added
- Core event registration and team management engine.
- Relational database schema with 12 custom tables.
- Solo and multi-member team registration with 7 mandatory participant details.
- Bank Alfalah, IBFT/1Link, Raast, JazzCash, Easypaisa payment configurations.

