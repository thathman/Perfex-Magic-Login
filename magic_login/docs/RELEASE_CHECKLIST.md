# Release Checklist

Use this checklist before creating a production Magic Login tag.

## Version and repository

- [ ] `magic_login.php` module header matches `MAGIC_LOGIN_VERSION`.
- [ ] `update_manifest.json` version matches the module version.
- [ ] Git tag will be `v<module-version>`.
- [ ] `auto_update_safe` is deliberately set to true or false.
- [ ] PHP lint GitHub Action passes on `main`.
- [ ] No secrets, API tokens, real customer data or test credentials are committed.

## Fresh installation

- [ ] Upload release ZIP through **Setup → Modules**.
- [ ] Activate Magic Login.
- [ ] Token, audit, OTP and update-history tables exist.
- [ ] Default module settings are created.
- [ ] Magic Login admin page loads without PHP/SQL errors.

## Upgrade installation

Test at minimum:

- [ ] v1.0.0 → current.
- [ ] v1.1.0 → current.
- [ ] Perfex shows/executes the expected module migrations.
- [ ] Module `installed_version` equals the released module version afterward.
- [ ] When files are newer than the database, Magic Login routes fail closed until Upgrade Database is completed.

## Magic links

- [ ] Admin can create a link.
- [ ] Link logs the correct active client contact into Perfex.
- [ ] Destination redirect is preserved.
- [ ] Used link cannot be reused.
- [ ] Expired link is rejected.
- [ ] Revoked link is rejected.
- [ ] Revocation uses POST/CSRF.
- [ ] Concurrent redemption cannot successfully consume the same token twice.

## Email merge fields

- [ ] `{magic_login_url}` appears in applicable Perfex email templates.
- [ ] `{magic_login_button}` resolves for the actual recipient.
- [ ] `{magic_login_expiry}` resolves correctly.
- [ ] Automatic invoice link securing works when enabled.
- [ ] Estimate, proposal, contract, ticket and project link securing is smoke-tested.
- [ ] Staff-facing emails are not converted into client magic links.
- [ ] Email generation is skipped while a Magic Login DB migration is pending.

## WhatsApp OTP

- [ ] Continue with WhatsApp appears only when enabled.
- [ ] A real E.164 contact number receives an OTP through the configured bridge.
- [ ] Unknown number receives the same generic browser/API response pattern.
- [ ] Correct code authenticates the intended contact.
- [ ] Incorrect code increments attempts.
- [ ] Expired code fails.
- [ ] Used code fails.
- [ ] IP rate limit works.
- [ ] Contact rate limit works.
- [ ] WhatsApp login is unavailable while a module DB migration is pending.

## API

- [ ] API is disabled by default.
- [ ] API key generation displays the key once.
- [ ] Stored API key value is a hash, not plaintext.
- [ ] Invalid Bearer key receives 401.
- [ ] `create-link` works.
- [ ] `request-otp` works.
- [ ] `verify-otp` returns a one-time Perfex login URL.
- [ ] `revoke` works for an active token.
- [ ] API returns 503 while a module database upgrade is pending.

## GitHub updater

- [ ] Check GitHub detects a newer test release.
- [ ] Missing checksum is rejected.
- [ ] Incorrect checksum is rejected.
- [ ] Mismatched tag/package/manifest version is rejected.
- [ ] Unsafe ZIP path is rejected.
- [ ] Invalid migration chain is rejected.
- [ ] Module backup is created before file replacement.
- [ ] Pre-migration file replacement failure restores the backup.
- [ ] Migration failure retains the backup and does **not** auto-restore old PHP files.
- [ ] Failed update appears in Update History with useful recovery detail.
- [ ] Successful update appears in Update History.
- [ ] Perfex Modules native Update action works.
- [ ] Automatic updates respect `auto_update_safe` and configured policy.

## Final release

- [ ] Test on the primary supported Perfex 3.x version.
- [ ] Review release notes for breaking/configuration changes.
- [ ] Merge/finalize `main`.
- [ ] Create the version tag.
- [ ] Confirm GitHub Actions creates `magic_login.zip` and `magic_login.zip.sha256`.
- [ ] Download the published assets and perform one final clean installation test.
