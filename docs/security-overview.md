# Security Overview: Kintai

🌐 **English** · [Français](i18n/security-overview.fr.md) · [日本語](i18n/security-overview.ja.md)

## 🏗 Security by Architecture
- **Tenant Isolation:** The "One Instance per Owner" model prevents cross-tenant data leaks by design.
- **Minimal Dependencies:** Reducing external packages minimizes the attack surface.
- **Physical Separation:** Databases are logically or physically separated, reducing the impact of a single database compromise.

## 🔐 Core Protections
- **Authentication:** Session-based authentication for the web, Bearer tokens for the API.
- **Authorization:** Granular role-based access control (RBAC) per store (Admin, Manager, Staff).
- **Encryption:** Secure password hashing (Bcrypt/Argon2) and encrypted sensitive configuration.
- **CSRF & XSS:** Built-in middleware for CSRF protection and strict view-level escaping.
- **Audit Logs:** Every sensitive action (shift change, settings update, user creation) is logged with the user ID, timestamp, and IP address.

## 📡 Network & Infrastructure
- **SSL/TLS:** Mandatory HTTPS for all SaaS instances.
- **Security Headers:** HSTS, Content Security Policy (CSP), and X-Frame-Options enabled by default via middleware.
- **Rate Limiting:** Protects login and sensitive API endpoints from brute-force attacks.

## 🛠 Secret Management
- **Instance Secrets:** Managed by the SaaS Orchestrator during provisioning.
- **Environment Isolation:** Secrets are never committed to version control and are stored in isolated `.env` files or secure secret stores (e.g., Vault).

## ⚖ Compliance
- **GDPR Readiness:** Data portability, right to be forgotten, and physical isolation simplify GDPR compliance for the SaaS.
- **Local Labor Law:** Architecture allows for the strict tracking of hours and breaks as required by local regulations.
