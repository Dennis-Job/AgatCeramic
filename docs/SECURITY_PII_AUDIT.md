# Interim Audit — security, audit and personal data (TASK-A012)

Audit date: 2026-09-09.

## Result

No high or critical application-level finding was confirmed in the Phase 0–6 scope.

| Area | Evidence |
| --- | --- |
| Authentication | Admin routes use cookie-based Sanctum, CSRF protection, session regeneration at login/logout, active-account middleware, blocked-user rejection, password reset expiry/throttling and session revocation after password changes. |
| Authorization | Protected Admin routes sit behind `auth:sanctum` and `active_admin`; controllers authorize policies and policies resolve granular permissions. Import jobs revalidate the submitting user's active status and permission. |
| Abuse controls | Login, password reset, checkout and public contact endpoints have dedicated IP/identity rate limiters. |
| Uploads/imports | Form Requests limit type/size; XLSX uploads are private. ZIP image imports also limit entry count, per-file and uncompressed bytes, reject unsafe paths/symlinks, and inspect actual image MIME before storage. |
| Queue payloads | Jobs serialize only integer record IDs, not customer contacts, files, tokens or credentials. Retry/backoff/timeout and failed-job handling are documented. |
| PII logging | Every configured application channel applies `MaskSensitiveLogData`; key/value, free-text email/phone and exception data are sanitised. Audit metadata has independent sanitisation. |
| Access and retention | Audit routes require `audit-log.view`; audit payloads exclude secrets and incidental PII. Five-year retention is scheduled; PostgreSQL trigger immutability is covered by a dedicated integration suite. |
| Secrets | Git tracks only environment templates; no real `.env`, key or credential file was found. Environment documentation restricts production secrets to secret storage and forbids secrets in browser-exposed variables. |

## Verification

- Targeted security/logging/auth/audit/storage tests: 19 passed, 88 assertions.
- `composer audit --locked`: no advisories.

## Explicit operational limits

This review does **not** certify legal compliance with 152-ФЗ or production readiness. TLS,
production secret storage, production backup encryption/access control, restore exercises and
monitoring are Phase 11 responsibilities (TASK-140–145, including TASK-142). Environment
templates intentionally retain local HTTP, `APP_DEBUG=true` and insecure cookies; deployment must
replace them with production-safe values as documented in `ENVIRONMENT.md`.

## Closure

TASK-A012 is complete for the current application audit: it found no high/critical issue blocking
the dependent refactorings. Future security-sensitive API or queue changes must preserve these
controls and extend the relevant negative/permission tests.
