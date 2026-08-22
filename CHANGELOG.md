# Changelog

## 1.1.4

### Fixed

- Waited for the automatic ALTCHA widget to attach its hidden proof before submitting passwordless AJAX requests, preventing valid email and WhatsApp requests from being rejected as unverifiable.
- Kept the fix idempotent and schema-neutral with a no-op Perfex migration target.

## 1.1.3

### Added

- Operations-only Magic Login workspace with a server-side Login Links table, server-side Audit Log, pagination, SQL-backed filters and modal link creation.
- Native Perfex AJAX contact search and native Perfex Settings integration.
- Humanized audit history, responsive admin improvements and settings UX refinements.
- Indexes supporting operational query performance for link lifecycle, source, destination and date queries.

### Changed

- Preserved permissions and regression protection for the client password-login toggle, ALTCHA validation, email, WhatsApp and API flows.
- Moved the operational index migration to a new idempotent `113` migration so published `1.1.2` installations upgrade safely.
- v1.1.3 supersedes the earlier v1.1.2 admin-UI candidate; the published v1.1.2 release remains unchanged.

## 1.1.2

### Added

- Automatic ALTCHA verification on password, registration, password-reset, magic-link and WhatsApp authentication forms.
- Inline AJAX success and error feedback for passwordless requests.
- Visible resend cooldowns for email links and WhatsApp codes.
- Country-aware WhatsApp number formatting with international dial-code selection.
- Administrator setting to disable username-and-password client login.

### Changed

- Refreshed Magic Login and WhatsApp authentication views to integrate with the Airix client theme.
- Improved dark-mode icon contrast and responsive form behavior.
- Hardened cooldown, rate-limit and duplicate-request handling across passwordless authentication flows.

## 1.1.1 - Release candidate

### Added

- Perfex-native email merge fields: `{magic_login_url}`, `{magic_login_button}`, `{magic_login_expiry}`.
- Optional automatic securing of customer invoice, estimate, proposal, contract, ticket and project links.
- Central passwordless client authentication service using Perfex client login lifecycle hooks.
- WhatsApp OTP client login flow.
- Configurable HTTPS Baileys/WhatsApp transport with payload/header/success filters.
- External Bearer-authenticated Magic Login API.
- API key generation, rotation and revocation with hash-only storage.
- Token/authentication audit trail.
- GitHub Release update checking and manual installation.
- Optional cron-driven safe automatic update policies.
- SHA-256 package verification and ZIP path validation.
- Module backups before self-update.
- Persistent update attempt/history records.
- Database-readiness guards across admin, client login, API and email auto-link flows.
- GitHub Actions PHP lint and release packaging workflows.
- API, WhatsApp, update and release-checklist documentation.

### Changed

- Consolidated public magic-link redemption through a single token/authentication service.
- Token consumption is atomic and genuinely single-use.
- Redirect destinations are normalized and stored server-side instead of trusting arbitrary query-string destinations.
- Revocation is POST/CSRF protected and tracked separately from successful token use.
- Module migration numbering follows Perfex's numeric sequential migration behavior (`110`, `111`).
- Updater refuses automatic releases unless the package manifest explicitly marks them `auto_update_safe`.
- Updater no longer restores old PHP files after database migration has started; migration failures retain the backup for manual recovery instead.
- Release workflow refuses tags without the matching Perfex migration target, including code-only releases that need a no-op migration.

### Security

- Magic-link tokens are hash-only at rest.
- OTP codes are password-hashed, expiring, attempt-limited and single-use.
- OTP request rate limiting applies per IP and per contact.
- Unknown WhatsApp numbers use generic outward responses to reduce enumeration.
- External API keys are stored only as SHA-256 hashes.
- Browser actions retain Perfex CSRF protection; only Bearer-authenticated API routes are CSRF-exempt.
- Module functionality fails closed while a database migration is pending.

## 1.1.0

- Security/schema foundation for revocation, context-aware redirects, audit data and structured Perfex migrations.

## 1.0.0

- Initial Magic Login module with administrator-generated client login links.
