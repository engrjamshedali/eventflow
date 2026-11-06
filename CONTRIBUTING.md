# Contributing to EventFlow

First off, thank you for considering contributing to **EventFlow**! It is people like you who make open source software such a vibrant community.

---

## Code of Conduct

By participating in this project, you agree to abide by our standards of respectful, collaborative, and inclusive communication.

---

## How Can I Contribute?

### Reporting Bugs
Before creating bug reports, please check the existing issue tracker to make sure the problem hasn't already been reported.

When filing a bug report, please include:
* A clear and descriptive title.
* Steps to reproduce the behavior.
* What you expected to happen vs. what actually occurred.
* Your environment details: WordPress version, PHP version, Web Server (Apache/Nginx), and active theme.
* Any relevant PHP error logs or browser console screenshots.

### Suggesting Enhancements
Feature requests are always welcomed! Please open an issue with:
* A clear explanation of the use case.
* Why this feature would benefit the broader EventFlow user community.
* Any mockups, flowcharts, or structural ideas you might have.

### Submitting Pull Requests

1. **Fork the repository** on GitHub.
2. **Clone your fork** locally:
   ```bash
   git clone https://github.com/<your-username>/eventflow.git
   cd eventflow
   ```
3. **Create a topic branch** from `main`:
   ```bash
   git checkout -b feature/my-new-feature
   ```
4. **Make your modifications**, adhering to our coding standards.
5. **Run the automated test suites**:
   ```bash
   php tests/test-production-audit.php
   php tests/test-registration-system.php
   php tests/test-team-email-verification.php
   ```
6. **Commit your changes** with descriptive commit messages following Conventional Commits format:
   ```bash
   git commit -m "feat: add payment reconciliation CSV summary"
   ```
7. **Push to your fork**:
   ```bash
   git push origin feature/my-new-feature
   ```
8. **Open a Pull Request** against the `main` branch of `engrjamshedali/eventflow`.

---

## Coding Standards & Guidelines

### WordPress Coding Standards (WPCS)
* Follow the official [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/).
* Use tabs for indentation, spaces for mid-line alignment.
* Use snake_case for functions and variables (`$participant_id`, `process_registration()`).
* Use PascalCase with underscores for class names (`EventFlow_Registration_Manager`).
* Always provide docblocks with `@param` and `@return` types.

### Security Best Practices
* **Nonces**: Always verify nonces on form submissions and AJAX handlers via `EventFlow_Security::verify_nonce()`.
* **Capabilities**: Always check user capabilities before executing sensitive actions via `EventFlow_Roles::can(...)` or `current_user_can()`.
* **Sanitization**: Sanitize all incoming user inputs with `sanitize_text_field()`, `absint()`, `sanitize_email()`, etc.
* **Escaping**: Always escape dynamic output in HTML templates using `esc_html()`, `esc_attr()`, `esc_url()`.
* **SQL Queries**: Always use `$wpdb->prepare()` for dynamic database queries. Never interpolate raw variables into SQL queries.
* **Direct File Access**: Ensure all PHP files begin with:
  ```php
  if ( ! defined( 'ABSPATH' ) ) {
      exit;
  }
  ```

### Internationalization (i18n)
* Use the text domain `'eventflow'` across all localized strings.
* Use standard WordPress localization functions: `__()`, `_e()`, `esc_html__()`, `esc_attr__()`, `_n()`.
* If adding new strings, update the `languages/eventflow.pot` template file.

