# Casa Paraiso Docker Workflow

## Services

The Docker Desktop `desktop-linux` Compose project is `casa-paraiso-web`.

- `app`: Apache/PHP 8.2 with Composer, Node 24, and required Laravel extensions at `http://localhost:18001`. Booking badges poll the authenticated count endpoint every 30 seconds while the page is visible; no WebSocket service is required.
- `mariadb`: MariaDB 10.11 LTS at `127.0.0.1:13306`; database `casa_paraiso`.
- `mailpit`: SMTP at `127.0.0.1:11026`; dashboard at `http://localhost:18025`.

All ports bind to loopback. Named volumes preserve MariaDB data, container-side dependencies,
and Laravel logs. The application source is a Windows bind mount, so `storage/logs` uses its
own Docker-managed volume; the app entrypoint assigns it to Apache's `www-data` user. Logging
channel file modes are managed by that volume rather than passed to Monolog.

## First Setup

```powershell
.\scripts\casa-docker.ps1 setup
```

Setup builds and starts the services, creates the ignored `.env` from `.env.example` when needed, installs Composer and npm dependencies, generates a missing app key, runs normal migrations, and builds assets. It never seeds, truncates, or wipes a database.

Existing MySQL checkouts must follow [the MariaDB migration runbook](MARIADB_MIGRATION.md) first. Setup/start reject the old `DB_HOST=mysql` setting so it cannot silently start the application against an unmigrated database. MariaDB uses a new `mariadb-data` volume; the retired `mysql-data` volume remains rollback evidence. Do not use `down --volumes` during migration or recovery.

## Daily Commands

```powershell
.\scripts\casa-docker.ps1 start
.\scripts\casa-docker.ps1 status
.\scripts\casa-docker.ps1 compose exec -T app php artisan migrate
.\scripts\casa-docker.ps1 compose exec -T app php artisan test
.\scripts\casa-docker.ps1 compose exec -T app npm run build
.\scripts\casa-docker.ps1 stop
```

Use the wrapper rather than bare Compose so commands target Docker Desktop and the correct project.

For commands with Compose options, use an explicit argument array: PowerShell can otherwise consume flags such as `-d` or `-c` as script parameters.

```powershell
.\scripts\casa-docker.ps1 -Action compose -ComposeArguments @('up', '-d', 'mariadb')
```

## Isolated Verification

After the normal setup has built the app image and installed dependencies, `compose.testing.yaml` provides a private MariaDB `testing` database plus separate framework/cache/log volumes and a distinct browser session cookie. Supply an explicit non-default `-ProjectName`; the normal project remains `casa-paraiso-web`. The test app binds to loopback port 18002 (`TEST_APP_PORT` may override it). Never reuse an account-bearing database volume.

```powershell
.\scripts\casa-docker.ps1 -ProjectName casa-paraiso-schedule-test -Action compose -ComposeArguments @('-f', 'compose.testing.yaml', 'up', '-d')
.\scripts\casa-docker.ps1 -ProjectName casa-paraiso-schedule-test -Action compose -ComposeArguments @('-f', 'compose.testing.yaml', 'exec', '-T', 'app', 'php', 'artisan', 'test')
.\scripts\casa-docker.ps1 -ProjectName casa-paraiso-schedule-test -Action compose -ComposeArguments @('-f', 'compose.testing.yaml', 'down', '--volumes')
```

Only remove volumes belonging to that disposable test project. Roster concurrency tests require committed fixtures across independent processes and use guarded testing-database truncation; they do not roll deployed migrations backward. Verify wrapper project selection with `powershell -NoProfile -ExecutionPolicy Bypass -File tests/scripts/casa-docker.tests.ps1`.

## Online Preview with Cloudflare

Install `cloudflared` on Windows, keep Docker Desktop running, and use:

```powershell
.\scripts\casa-preview.ps1 start
.\scripts\casa-preview.ps1 status
.\scripts\casa-preview.ps1 stop
```

`start` checks the local app and built assets, starts the existing Docker services if needed, and launches a hidden Cloudflare Quick Tunnel to the loopback app port (`APP_PORT`, default `18001`). It prints the HTTPS `trycloudflare.com` link after the public homepage responds. Repeating `start` reuses a healthy tunnel; after stopping, a new start creates a new address. Running the script without an action shows status.

Anyone with the link can open the homepage. The existing login and role permissions still protect workspaces. This preview uses the current local database, so normal site actions persist there. Keep the computer, internet connection, Docker, and tunnel running. Quick Tunnels are temporary development previews without uptime guarantees; they are not permanent hosting. Google OAuth needs an explicitly registered callback for each hostname, so use email/password login for these previews. See [Cloudflare Quick Tunnels](https://developers.cloudflare.com/tunnel/get-started/quick-tunnels/).

While running, the launcher disables debug output, uses the public `APP_URL`, adds its exact trusted hostname, enables secure session cookies, and clears Laravel's configuration cache. It retains existing trusted hosts and proxies. Stop restores the previous settings and leaves Docker running. Built assets are required; stop any Vite dev server and run the documented asset build if prompted.

The ignored `.casa-preview/` directory stores the tunnel identity, URL, logs, and only the original settings the launcher changes. It contains no complete `.env` backup or Cloudflare credentials. Failed starts and the 90-second tunnel readiness timeout stop the owned process and restore settings. A dead process is recovered on the next `start` or `stop`; PID reuse is checked against its executable and start time. If a managed setting was manually edited, stop preserves that edit and retains the recovery journal. Follow its message to restore the listed original values and run stop again. If Docker was stopped, restart it and run stop again to finish clearing configuration. Do not delete the journal until recovery succeeds.

Launcher lifecycle checks use disposable fixtures and simulated processes, without connecting to the app database:

```powershell
powershell -NoProfile -File .\tests\scripts\casa-preview.tests.ps1
```

## Data Cutover

The archived database is the historical source and is never modified by this repository. Before importing data into a new MariaDB database, take a checksummed source backup, stop source writes, and use a temporary uncommitted transfer harness with supplied credentials. Validate source/target counts, canonical fingerprints, foreign keys, account/profile totals, financial totals, and auto-increment values before allowing application writes. Use table DDL for sequence values; MySQL information-schema statistics can differ from the values in `SHOW CREATE TABLE`.

Never run destructive reset commands against a database that contains real accounts or business records. The dedicated `testing` database is the only target for destructive test migrations.
