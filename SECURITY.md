# Security Policy

## Supported Versions

Security updates are actively applied to the latest release line:

| Version | Supported          |
| :---    | :---               |
| 5.0.x   | :white_check_mark: |
| < 5.0   | :x:                |

---

## Reporting a Vulnerability

The EventFlow development team takes security and user privacy seriously. If you discover a security vulnerability or potential threat in this plugin, please follow these responsible disclosure procedures:

1. **Do not create a public GitHub issue** or disclose the vulnerability publicly until it has been reviewed and addressed.
2. **Send an encrypted or private email report** directly to:
   * **Security Maintainer**: [engr.jamshed.ali.rind@gmail.com](mailto:engr.jamshed.ali.rind@gmail.com)
3. Please include in your report:
   * A detailed description of the vulnerability.
   * Proof-of-concept (PoC) code or step-by-step reproduction instructions.
   * The potential impact or attack vector.
   * Your recommended remediation or patch (if available).

---

## Response Timeline

* **Initial Response**: We will acknowledge receipt of your report within **48 hours**.
* **Assessment & Verification**: We aim to confirm the vulnerability and determine the severity within **5 business days**.
* **Patch & Release**: A security release will be prepared and published in a prioritized patch release (e.g., `5.0.3`).
* **Public Disclosure**: Once the patched version is published, the fix will be documented in the changelog and credit will be granted to the reporter (unless you prefer anonymity).

---

## Core Security Architecture & Safeguards

EventFlow integrates defense-in-depth principles:
* **AES-256 Symmetric Credential Encryption**: Third-party credentials such as custom SMTP passwords are encrypted using `openssl_encrypt( 'aes-256-cbc' )` with key derivation from WordPress core authentication salts (`AUTH_KEY`, `AUTH_SALT`).
* **Brute-Force & Lockout Defense**: An automated transient-based security lockout disables accounts after 3 failed consecutive login attempts for a duration of 15 minutes.
* **Strict Role-Based Access Control**: 7 custom roles isolate duties between financial evaluators, registration staff, technical teams, and participants.
* **Protected File Storage**: User-submitted payment proofs are stored in a dedicated `wp-content/uploads/eventflow-secure/` directory with randomized hash filenames and guarded against direct file directory browsing.
