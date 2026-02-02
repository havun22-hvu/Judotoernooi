# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

We take security seriously. If you discover a security vulnerability, please report it responsibly.

### How to Report

**Email:** security@judotournament.org

Please include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

### What to Expect

| Timeline | Action |
|----------|--------|
| 24 hours | Acknowledgment of your report |
| 72 hours | Initial assessment and severity rating |
| 7 days | Status update with remediation plan |
| 30 days | Public disclosure (if applicable) |

### Security Measures

This application implements:

- **Authentication**: Laravel's built-in authentication with bcrypt hashing
- **Authorization**: Role-based access control (Organisator, Sitebeheerder)
- **CSRF Protection**: All forms protected with CSRF tokens
- **XSS Prevention**: Blade templating with automatic escaping
- **SQL Injection**: Eloquent ORM with parameterized queries
- **Rate Limiting**: Login attempts and API requests
- **Security Headers**: CSP, HSTS, X-Frame-Options, X-Content-Type-Options
- **Input Validation**: Server-side validation on all endpoints
- **Secure Sessions**: HTTP-only, secure cookies in production

### Out of Scope

- Denial of Service attacks
- Social engineering
- Physical security
- Third-party services (Mollie, etc.)

### Recognition

We appreciate responsible disclosure and may acknowledge reporters in our release notes (with permission).

---

Thank you for helping keep JudoToernooi secure.
