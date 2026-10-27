# Implementation Roadmap

## Completed Foundation

- Laravel Blade workspaces, role access, scheduling, transactions, attendance, commissions, feedback, RFM rewards, settings, reports, and CSV export are implemented.
- The project uses a local Docker Desktop web stack with PHP/Apache, MariaDB 10.11 LTS, and Mailpit.
- Retired client, API, database, and hosted-pilot surfaces are excluded from this repository.

## Remaining Delivery Work

1. Confirm Hostinger's MariaDB version, prepare the final verified application export, and retain checked backups. The local MySQL-to-MariaDB transfer is documented in `MARIADB_MIGRATION.md`.
2. Perform full MariaDB regression, accessibility, and role-workspace acceptance with migrated records.
3. Configure the selected production web host, MariaDB backups, HTTPS, Google OAuth callback, monitoring, and recovery runbook.
4. Complete non-technical handover and operations documentation.
