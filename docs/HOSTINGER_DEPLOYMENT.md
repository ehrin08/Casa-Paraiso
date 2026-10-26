# Hostinger Unlimited Web Deployment

Target: Hostinger **Unlimited Web** hosting, a custom PHP website, and managed MariaDB. Preserve existing accounts, business records, uploaded photos and the application key. This runbook separates a verified upload package from the private data transfer; it does not provision an account or claim an untested server is live-ready.

Hostinger supports [Laravel on Web/Cloud hosting](https://www.hostinger.com/support/which-programming-languages-and-frameworks-are-supported-at-hostinger/) and uses [MariaDB](https://www.hostinger.com/support/1583226-which-database-management-system-is-used-at-hostinger/). The application needs no production Node server, Docker, Redis, WebSocket server or persistent queue worker. Local Docker remains the development/build/test toolchain.

## Build the upload package

After passing the release checks in `AGENTS.md`, run:

```powershell
.\scripts\casa-hostinger.ps1 package
```

The wrapper validates/audits dependencies, installs locked npm dependencies, builds assets and creates a fresh production Composer installation in isolated staging. It leaves the development vendor and real `.env` intact. Outputs are a dated ZIP and matching `.sha256` file under `output/hostinger-release/`. The ZIP contains `casa-paraiso/`, this guide and `RELEASE.json` with lockfile fingerprints.

Before returning a release, the builder unpacks the actual ZIP in a separate temporary directory and verifies its checksum, production-only vendor, cached CLI boot, HTTP health/assets/uploads, denied private-file requests and safe preflight rejection of incomplete settings. This uses a synthetic environment with an unreachable database and sends no messages. Failed archives are removed. The source-controlled packaging-order/failure checks run with `.\tests\scripts\casa-hostinger.tests.ps1`.

The archive includes production PHP dependencies and `public/build`; uploading only Git source would omit both. It excludes actual environments, database exports, uploaded files, logs, local cached configuration, tests, development dependencies, Node modules and archived provider files. Its `.env.example` is the production template from `deploy/hostinger.env.example`, not the Docker environment. Keep the uploaded ZIP outside `public_html` and remove the ZIP/checksum after verification and extraction.

## Prepare hPanel and PHP

1. Add a **custom PHP website** on a dedicated domain or staging subdomain. Do not select Node, WordPress, AI Builder, or Laravel's auto-installer for this existing application.
2. Enable SSH/SFTP. Choose PHP 8.2 as the verified initial baseline for both website and CLI; a newer PHP version needs verification. Confirm `php -v`; the site's PHP selector alone does not change CLI PHP. Hostinger documents binaries such as `/opt/alt/php82/usr/bin/php` in its [Composer troubleshooting guide](https://www.hostinger.com/support/5792082-how-to-solve-common-composer-issues-at-hostinger/).
3. Enable Laravel's PHP extensions, `pdo_mysql`, and GD with JPEG/PNG/WebP support for both web and CLI PHP. Run `composer2 check-platform-reqs --no-dev` using the matching PHP version. `casa:check-hosting` also checks GD's image capabilities.
4. Enable the `symlink` PHP function for the storage link. If running Composer scripts on hosting, enable the required `proc_open` function too. See [disabled functions](https://www.hostinger.com/support/3212034-how-to-enable-disabled-php-functions-in-hostinger/) and [symlink setup](https://www.hostinger.com/support/1583694-how-to-create-a-symlink-at-hostinger/). Dependencies are already included; do not run `composer update` on the server.
5. Set up SSL and hPanel's HTTP-to-HTTPS redirect. Keep optional full-page caching disabled for authenticated workspaces, booking forms, calendars, profiles and role-scoped feeds.

## Private application and public directory

Use the account/domain directory shown by hPanel, commonly `/home/YOUR_USER/domains/YOUR_DOMAIN`. Extract `casa-paraiso/` there, **outside** the served directory:

```text
domain directory/
  casa-paraiso/             private application, .env, vendor and storage
    public/                 index.php, static assets and storage link only
  public_html -> casa-paraiso/public
```

Verify `sha256sum -c FILE.zip.sha256`, then extract outside `public_html`. Stop if an application directory already exists: do not merge an archive over existing credentials or uploads. Inspect and back up the domain's existing `public_html` before replacing it. For a new dedicated site, rename that original directory to a dated backup, then create the link through SSH from the domain directory:

```sh
ln -s "$PWD/casa-paraiso/public" public_html
cd casa-paraiso
cp .env.example .env
chmod 600 .env
```

Keep Laravel's `public/index.php` unchanged. Do not upload the complete project under `public_html` or expose the private application through a root rewrite. Verify that `/.env`, `/composer.json`, `/.git/config`, `/storage/logs/laravel.log` and private backups cannot be downloaded.

## Private production settings

Edit `.env` privately. Use the final HTTPS `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_TIMEZONE=Asia/Manila`, `FORCE_HTTPS=true` and secure HTTP-only cookies. Set anchored `TRUSTED_HOSTS` patterns, such as `^spa\.your-domain\.com$`; include `www` only if used. Otherwise, redirect `www` to the bare domain at the host; an untrusted `www` host returns 400. Leave `TRUSTED_PROXIES` empty for direct TLS. If a reverse proxy is present, configure only its verified IPs/CIDRs and check HTTPS/client IP detection; do not trust `*` to work around an unknown configuration.

Retain the existing `APP_KEY` when transferring encrypted records; do not run `key:generate` for this migration. Set `SUPER_ADMIN_EMAIL` to the existing designated super administrator and preserve that account's identity and role. Keep database sessions/cache and `QUEUE_CONNECTION=sync`.

Replace Mailpit with authenticated SMTP. Fill host, scheme/port, username, password and a verified sender address using the provider's settings. The template defaults to SMTPS/465; change it if required. Configure SPF/DKIM/DMARC and verify account-verification, password-reset and team password-setup mail with your own account. The preflight never sends mail and cannot prove delivery or DNS authentication.

Fill production Google credentials and authorize **both** exact HTTPS redirect URIs in Google Console:

```text
https://YOUR_DOMAIN/auth/google/callback
https://YOUR_DOMAIN/profile/password/google/callback
```

The second URI supports Google-linked users confirming identity before setting a password. Set `GOOGLE_REDIRECT_URI` to the first URI. Test the real provider and authorized domain; automated tests mock it. Enable HSTS only after validating HTTPS for the domain and its subdomains.

## Confirm the hosting environment

In hPanel, create a dedicated application database and database user, then open phpMyAdmin and run `SELECT VERSION()`. Record the actual version: local MariaDB 10.11 is a provisional baseline, not a claim about the hosting account. If the hosting version differs, verify the migrations, JSON operations, and regression tests against that version before deployment. Keep `utf8mb4` and `utf8mb4_unicode_ci`.

The 2026-10-26 account inspection reported CLI PHP 8.2.35 and MariaDB 12.3.2. An isolated MariaDB 12.3.2 environment passed all 483 Laravel tests and an exact-export restore comparison before the private hosting import. Local development remains on MariaDB 10.11; recheck the hosting runtime for future deployments rather than assuming the provider version is unchanged. Private staging success does not confirm public cutover or delivery; consult the current deployment status before resuming an incomplete launch.

Use the exact database name, username, and hostname supplied by hPanel, including account prefixes. Grant the application user access to its database only. Do not upload the local `testing` database or create the local test account on hosting. Confirm the account's import limits and SSH availability before selecting phpMyAdmin or an SSH import.

## Prepare and restore the export

1. Freeze application writes for the final transfer and produce a new consistent logical export of the verified MariaDB application database. Use `mariadb-dump --single-transaction --skip-lock-tables --no-tablespaces --hex-blob --default-character-set=utf8mb4 --skip-add-drop-table` with the database as a positional argument. Do not use `--databases` or `--all-databases`. Supply credentials privately, without putting passwords in command history.
2. Export tables and data without `CREATE DATABASE`, `USE` statements naming the local database, server accounts/grants, or privileged server configuration. Inspect any views, routines, triggers, events, and definers separately if introduced in future. Keep the original checksummed export; record any reviewed portability changes in a separate copy rather than using broad search-and-replace on SQL containing business data.
3. Check for client-version-specific dump headers (including MariaDB sandbox directives) and verify the selected import client accepts them. Test the exact export and importer on an empty disposable database first. Preserve UTF-8 bytes by exporting/importing files directly rather than through PowerShell text conversion.
4. Confirm the hosting target is the intended **empty** database. Import through phpMyAdmin with that database selected, or through the hosting-provided database client over SSH. An import failure or a populated target requires investigation; do not rerun destructive SQL or drop tables to force completion.
5. Compare every table count and canonical content fingerprints, migration history, JSON values, financial totals, foreign-key integrity, and next auto-increment values. Run `php artisan casa:audit-crud-integrity` and compare with the source baseline before enabling writes.

## Database environment

Configure these values privately on the server, outside the public document root:

```dotenv
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=<hPanel-provided-host>
DB_PORT=3306
DB_DATABASE=<hPanel-database-name>
DB_USERNAME=<hPanel-database-user>
DB_PASSWORD=<private-password>
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

Retain the existing application `APP_KEY` when migrating encrypted data. Do not generate a replacement key as part of database transfer. Keep `pdo_mysql` enabled in PHP. Remove obsolete local Docker/Oracle connection overrides such as `DB_URL`, `DB_SOCKET`, or a stale `MYSQL_ATTR_SSL_CA`; configure TLS according to the actual Hostinger endpoint rather than disabling verification. Hostinger's local database endpoint commonly uses `localhost`, but hPanel is authoritative.

After setting the environment, run `php artisan config:clear`, inspect `php artisan migrate:status`, run only pending normal migrations with `php artisan migrate --force`, and rebuild configuration with `php artisan config:cache`. Never run `migrate:fresh`, seed demo records, or upload local credentials in production.

Before enabling traffic, verify login, role access, appointment creation/confirmation and capacity, transactions, reports, and settings/sentiment JSON. Set up hosting backups and verify recovery. The existing Render/Oracle files remain historical reference; this preparation does not modify those provider services.

## Uploaded files, caches and scheduler

Transfer existing `storage/app/public` files separately over SFTP or a private archive. Compare file counts and SHA-256 checksums with the source; database `photo_path` values must continue to resolve. Keep the original files backed up. Do not transfer local logs, sessions, view caches or absolute storage symlinks. Ensure the account can write `storage/framework/cache/data`, `storage/framework/sessions`, `storage/framework/views`, `storage/logs` and `bootstrap/cache`; use account-appropriate permissions rather than `777`.

From the private application directory, using the verified PHP binary, run:

```sh
php artisan config:clear
php artisan package:discover
php artisan migrate:status
# Only after reviewing pending normal migrations and checking backups:
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan casa:audit-crud-integrity
php artisan casa:check-hosting
```

Replace `php` consistently with the version-specific binary when needed. The preflight is read-only, returns nonzero on failed prerequisites, and never prints credentials or changes records. It reports the database version, which still needs comparison with this release's tested version. Passing it does not verify DNS, real mail/OAuth delivery, hPanel settings or recovery.

Create an hPanel **Custom** cron job running every minute:

```text
/opt/alt/php82/usr/bin/php /home/YOUR_USER/domains/YOUR_DOMAIN/casa-paraiso/artisan schedule:run
```

Use the real binary/path. hPanel cron uses UTC; Laravel schedules use Asia/Manila. A once-per-minute runner accommodates both. Confirm daily error-history pruning runs; the application retains a once-daily request fallback too. Do not run `artisan serve`, Vite, `schedule:work`, or a queue daemon for this hosting profile.

## Launch and recovery gate

- Match actual MariaDB/PHP versions and extensions to release evidence. Rehearse imports/migrations in an isolated compatible environment if versions differ.
- On staging, verify homepage/assets, old and new photo uploads, password and Google authentication, both Google flows, role restrictions, simultaneous booking capacity, payments, reports, settings/JSON, attendance QR/camera over HTTPS, and safe error pages.
- Enable daily database/file backups and protect the application key separately. Rehearse restoration into an isolated database/directory; verify fingerprints, financial totals, foreign keys, uploaded-file checksums and usable access.
- Freeze source writes for cutover, take the final consistent export/file backup, import into the intended empty target, verify comparisons and the integrity audit, then enable traffic. Do not reopen writes against both copies.
- For updates, extract into a fresh private sibling directory, preserve the environment/uploads, review normal migrations, verify the new release, then switch the public link during maintenance. Retain the old application and checked backups.
- Roll back application files only with a compatible schema. For database recovery, freeze writes and use the rehearsed restore, accounting for records/files created since backup. Do not automatically roll back migrations, overwrite populated databases or discard newer bookings.

Record the deployed checksum, runtime versions, successful checks and restore evidence privately. Local release verification and actual Hostinger verification are separate gates.
