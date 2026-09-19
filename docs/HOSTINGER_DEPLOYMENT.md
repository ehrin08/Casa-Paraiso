# Hostinger Database Deployment Preparation

Target: Hostinger Web/Cloud hosting with managed MariaDB. This runbook prepares the database; it does not provision hosting, upload application files, change DNS, or perform a production cutover. Hostinger confirms that its Web/Cloud plans use [MariaDB](https://www.hostinger.com/support/1583226-which-database-management-system-is-used-at-hostinger/).

## Confirm the hosting environment

In hPanel, create a dedicated application database and database user, then open phpMyAdmin and run `SELECT VERSION()`. Record the actual version: local MariaDB 10.11 is a provisional baseline, not a claim about the hosting account. If the hosting version differs, verify the migrations, JSON operations, and regression tests against that version before deployment. Keep `utf8mb4` and `utf8mb4_unicode_ci`.

Use the exact database name, username, and hostname supplied by hPanel, including account prefixes. Grant the application user access to its database only. Do not upload the local `testing` database or create the local test account on hosting. Confirm the account's import limits and SSH availability before selecting phpMyAdmin or an SSH import.

## Prepare and restore the export

1. Freeze application writes for the final transfer and produce a new consistent logical export of the verified MariaDB application database. Use `mariadb-dump --single-transaction --skip-lock-tables --no-tablespaces --hex-blob --default-character-set=utf8mb4 --skip-add-drop-table` with the database as a positional argument. Do not use `--databases` or `--all-databases`. Supply credentials privately, without putting passwords in command history.
2. Export tables and data without `CREATE DATABASE`, `USE` statements naming the local database, server accounts/grants, or privileged server configuration. Inspect any views, routines, triggers, events, and definers separately if introduced in future. Keep the original checksummed export; record any reviewed portability changes in a separate copy rather than using broad search-and-replace on SQL containing business data.
3. Check for client-version-specific dump headers (including MariaDB sandbox directives) and verify the selected import client accepts them. Test the exact export and importer on an empty disposable database first. Preserve UTF-8 bytes by exporting/importing files directly rather than through PowerShell text conversion.
4. Confirm the hosting target is the intended **empty** database. Import through phpMyAdmin with that database selected, or through the hosting-provided database client over SSH. An import failure or a populated target requires investigation; do not rerun destructive SQL or drop tables to force completion.
5. Compare every table count and canonical content fingerprints, migration history, JSON values, financial totals, foreign-key integrity, and next auto-increment values. Run `php artisan casa:audit-crud-integrity` and compare with the source baseline before enabling writes.

## Application environment

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
