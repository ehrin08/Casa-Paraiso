# Local MySQL to MariaDB Migration

The local runtime is MariaDB 10.11 LTS, using Laravel's existing `mysql` connection and `pdo_mysql`. The `mariadb` service uses a new `casa-paraiso-web_mariadb-data` volume. Never mount `casa-paraiso-web_mysql-data` into MariaDB, reset an application database, or run `down --volumes` during transfer/recovery.

## Existing installations

This is a controlled, one-time logical transfer, not part of `setup`. Run all Docker operations through `scripts/casa-docker.ps1`, with explicit `-ComposeArguments` arrays for flags. Keep dump files, credentials, original environment/configuration, and validation manifests under the ignored `storage/backups/` directory. Never print database records or passwords to the console.

1. Save the original Compose file and `.env` before changing the database service. Verify the source server, database, tables, engines, migration history, JSON columns, and any views/routines/triggers/events. Handle non-InnoDB tables or stored objects explicitly before using the simple transfer below.
2. Stop the app, workers, and other writers. Start only the source database. Set the local MySQL source to `super_read_only=ON`; reconnect after any source restart and reapply this setting. Run the read-only `php artisan casa:audit-crud-integrity` and record its baseline without repairing records.
3. Export only the application database with MySQL's own `mysqldump`. Pass `--single-transaction --skip-lock-tables --set-gtid-purged=OFF --no-tablespaces --column-statistics=0 --hex-blob --default-character-set=utf8mb4 --skip-add-drop-table --routines --events --triggers`. Specify the database as a positional argument, without `--databases` or `--all-databases`. Use credentials inside the container environment; write output directly to a file, not through PowerShell's text pipeline. This excludes database creation and MySQL server accounts. Review stored-object definers if objects exist.
4. Copy the dump to the ignored backup directory, calculate SHA-256, and verify it again after copying into the target container. Preserve the original backup unchanged.
5. Stop MySQL to free port 13306. Initialize MariaDB on its new volume with the same application database credentials. Confirm `SELECT VERSION(), DATABASE()` and that the application schema has **zero** tables. Refuse an import into a populated target, including a failed partial import; investigate before continuing. Never reset that target automatically.
6. Import with `mariadb --default-character-set=utf8mb4` into the explicitly selected database. Check the exit code and keep error files private. The source dump restores schema, migration history, account password hashes, keys, timestamps, and data together; do not seed afterward.
7. Compare all table counts and row-content hashes, canonicalizing JSON object keys and sorting table/row enumeration independently of database collation. Compare foreign-key definitions and orphan counts, decimal/financial totals, and sequence values from `SHOW CREATE TABLE`, including empty-table sequences. Re-run the integrity audit and require the same baseline. Preserve manifests without raw records. Reject any unexplained mismatch.
8. Restart MariaDB and repeat the comparison to verify persistence. Switch the ignored `.env` to `DB_HOST=mariadb`, keeping `DB_CONNECTION=mysql`, application credentials, and `APP_KEY`. Use `MARIADB_ROOT_PASSWORD` for the new service. Ensure `DB_URL` and cached configuration cannot override the local target.
9. Clear configuration, run only normal pending migrations, and complete the tests below before restarting the app. Do not admit application writes until the comparison and checks pass.

## Verification

The separate `testing` schema is initialized by `docker/mariadb/init/01-create-testing-database.sql`. The local test account is `sail` with password `password`, as pinned in PHPUnit. If the application uses `sail` too, use these same local credentials; use separate credentials for production.

```powershell
.\scripts\casa-docker.ps1 -Action compose -ComposeArguments @('run', '--rm', '--no-deps', '--entrypoint', 'php', 'app', 'artisan', 'config:clear')
.\scripts\casa-docker.ps1 -Action compose -ComposeArguments @('run', '--rm', '--no-deps', '--entrypoint', 'php', 'app', 'artisan', 'migrate', '--force')
.\scripts\casa-docker.ps1 -Action compose -ComposeArguments @('run', '--rm', '--no-deps', '--entrypoint', 'php', 'app', 'artisan', 'test', '--filter=DatabaseTestIsolationTest')
```

Run targeted authentication, booking capacity, transaction, reporting, settings JSON, and sentiment tests, followed by the full Laravel suite and asset build. Test refreshes/migration recreation are permitted only against the verified `testing` database. Verify startup through the wrapper and check login, calendars, and reports. Fresh-checkout `setup` creates schema without seed data; it does not import old records.

## Rollback

Before MariaDB accepts new application writes, stop the app and MariaDB, restore the saved `.env` and Compose file, clear cached Laravel configuration, start MySQL, and disable `super_read_only` and `read_only` if still enabled. Verify the old database identity and integrity before restarting the app. The current wrapper's legacy-host guard intentionally blocks `start`/`setup`; use explicit `compose` arguments with the restored configuration during this controlled rollback.

A saved Compose file in a backup subdirectory needs `--project-directory .` when used via `-f`, so relative mounts resolve to the repository root. The old `docker/mysql/init` file is retained for this purpose. Do not run both databases on the same published port.

After new writes have reached MariaDB, the MySQL backup is stale. Freeze writes, back up MariaDB, and reconcile those changes before rollback. Do not blindly reconnect to MySQL and lose newer records. Retain the old volume and backup until a separate retention decision.

## Local cutover evidence — 2026-09-12

- Source: MySQL 8.4.11; target: MariaDB 10.11.19; application database: `casa_paraiso`.
- Transfer: 37 tables, 84 rows; source/target counts, canonical row fingerprints, foreign keys/orphans, financial totals, table-DDL sequence values, and integrity audits matched after a MariaDB restart.
- Original configuration, environment, SQL backup, checksum, and private verification harness/manifests are in `storage/backups/mariadb-migration-20260912-223002/` (ignored).
- No pending application migrations were found at cutover. The original MySQL volume is retained and its service is stopped.
- Verification: 243 Laravel tests passed (1,789 assertions), seven existing polling behavior tests passed, and the asset/application-image builds succeeded. The obsolete WebSocket-event assertion in `ExampleTest` was removed to match the already-existing polling implementation; application notification behavior was not changed by this migration.
- Restored-account read-only checks passed for the admin dashboard, calendar, reports, settings, and customer appointments. The existing staff account correctly retained its email-verification redirect. After checks, the wrapper started the app on MariaDB; the landing/login pages returned HTTP 200.
