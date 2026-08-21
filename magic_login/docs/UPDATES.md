# Updates and Releases

Magic Login integrates with Perfex's native module update mechanism through `release_handler.php` and can also check/install updates from the Magic Login admin screen.

## Release asset contract

A production GitHub Release must contain:

```text
magic_login.zip
magic_login.zip.sha256
```

The ZIP must contain a top-level `magic_login/` directory and the package version in `magic_login.php` must match the GitHub release tag and `update_manifest.json`.

## Verification before installation

The updater checks:

1. A newer semantic version is available.
2. The package and checksum assets exist.
3. The source uses HTTPS and is a GitHub/GitHubusercontent release URL.
4. SHA-256 of the downloaded ZIP matches the checksum asset.
5. Every ZIP entry stays inside `magic_login/` and does not contain traversal/null paths.
6. The package contains `magic_login/magic_login.php`.
7. Package version matches the GitHub Release version.
8. `update_manifest.json` exists and its version matches the package.
9. The required Perfex migration target exists and the pending migration sequence is contiguous.
10. The existing module directory is writable and a backup can be created.

## Manual updates

Manual updates can be triggered from either:

- **Magic Login → GitHub Updates → Install Latest**, or
- **Setup → Modules → Magic Login → Update** when Perfex reports a newer version.

Manual installation still requires all integrity and migration checks.

## Automatic update policies

Automatic checks use the Perfex cron and run at most once per day.

- `off`: manual updates only.
- `patch`: only same-major/same-minor releases, and the package must be marked auto-update safe.
- `safe`: any newer release explicitly marked auto-update safe.

A release opts in to unattended installation through `magic_login/update_manifest.json`:

```json
{
  "version": "1.1.1",
  "auto_update_safe": false
}
```

Keep `auto_update_safe` false for releases that change behavior significantly, require manual configuration, or deserve operator review.

## Backup and rollback boundary

Before replacing files, the updater creates a ZIP backup under the Perfex temporary directory in `magic-login-backups/`.

There are two distinct failure phases.

### Before database migration starts

If replacing the module files fails, the updater may automatically restore the file backup because the database has not yet been changed.

### After database migration starts

The updater **does not automatically restore old PHP files** if a Perfex migration fails. MySQL DDL may already have been applied and cannot be reliably reversed by restoring files.

Instead the updater:

- retains the backup,
- records the update as failed,
- writes the failure to `magic_login_last_update_status`,
- records the backup path and error in `tblmagic_login_updates`, and
- tells the administrator not to retry until the database state is reviewed.

This is intentional recovery behavior, not a missing rollback feature.

## Update history

Magic Login records update attempts in `tblmagic_login_updates` with:

- source and target versions,
- GitHub release tag,
- verified checksum,
- manual/automatic mode,
- status,
- backup path,
- error detail,
- start/completion timestamps.

Recent records appear on the Magic Login admin page.

## Perfex migration numbering

Perfex's module migration system derives a numeric schema version from the module version and expects contiguous pending migration numbers. For the current release line:

```text
1.1.0 -> 110_version_110.php
1.1.1 -> 111_version_111.php
```

Do not introduce a database-changing release without planning the Perfex migration number first. Keep migration filenames/classes compatible with Perfex's three-digit sequential migration loader.

## GitHub Actions release workflow

Pushing a `vX.Y.Z` tag triggers the release workflow. It verifies that:

```text
Git tag version
=
magic_login.php Version header
=
update_manifest.json version
```

It then builds `magic_login.zip`, creates `magic_login.zip.sha256`, and creates/uploads the GitHub Release assets.

Do not publish a production tag until the release checklist passes.
