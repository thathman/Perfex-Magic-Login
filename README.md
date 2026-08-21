# Perfex Magic Login

Passwordless client access for Perfex CRM, with secure one-time login links, email merge tags, WhatsApp OTP login, an external API, audit logging, and GitHub Release updates.

Current development version: **1.1.1**

## Highlights

- Secure single-use client login links with expiry and revocation.
- Perfex email merge tags: `{magic_login_url}`, `{magic_login_button}`, `{magic_login_expiry}`.
- Optional automatic securing of invoice, estimate, proposal, contract, ticket and project links sent to clients.
- WhatsApp OTP login using a configurable HTTPS API endpoint (designed to work with Baileys-style bridges).
- Bearer-authenticated external API for link creation, WhatsApp OTP and revocation.
- Audit history for authentication events and update history for module upgrades.
- GitHub Release update checks through Perfex's native module update mechanism.
- Optional safe automatic updates through Perfex cron.

## Installation

1. Download the release asset `magic_login.zip` from GitHub Releases.
2. In Perfex, go to **Setup → Modules → Upload Module**.
3. Upload the ZIP and activate **Magic Login**.
4. If Perfex shows **Upgrade Database**, run it before using the module.
5. Open **Magic Login** from the admin sidebar and configure the desired features.

Do not install a source-code snapshot from `main` on a production Perfex instance. Releases are packaged with a matching SHA-256 checksum for the updater.

## Upgrade safety

Magic Login fails closed when its PHP files are newer than the installed module schema. Client magic links, WhatsApp login, email auto-link generation and the API return/behave as unavailable until the Perfex module migration is completed.

The updater verifies the GitHub Release package checksum, validates the archive and package version, validates the Perfex migration chain, creates a module backup, replaces files, and then delegates database migration to Perfex.

Automatic file rollback is permitted only before database migration begins. If a migration fails, the backup is retained and the failure is recorded for manual recovery; the updater does not pretend that restoring PHP files can reverse MySQL DDL.

## Documentation

- [API](magic_login/docs/API.md)
- [WhatsApp / Baileys integration](magic_login/docs/WHATSAPP.md)
- [Updates and releases](magic_login/docs/UPDATES.md)
- [Release checklist](magic_login/docs/RELEASE_CHECKLIST.md)

## Compatibility

The module header currently declares Perfex CRM `3.0.*` or later. Primary development/testing should be performed against current supported Perfex 3.x releases before publishing a production release.

## Security notes

- Magic login tokens are stored as SHA-256 hashes and are single-use.
- OTP codes are stored with `password_hash()` and have expiry and attempt limits.
- API keys are displayed once and stored only as SHA-256 hashes.
- External API routes are CSRF-exempt because they require Bearer authentication; browser forms retain Perfex CSRF protection.
- Redirects are normalized and stored server-side with the login token.
- State-changing admin actions use POST.

## License

No license file has been added yet. Add an explicit license before advertising the project as open source or distributing it under specific reuse terms.
