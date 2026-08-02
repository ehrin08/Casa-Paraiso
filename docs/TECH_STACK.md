# Casa Paraiso Technology Stack

## Approved Stack

- Backend and web UI: Laravel 12, PHP 8.2, Blade, Tailwind CSS v4, Alpine.js, Turbo Drive, and Vite.
- Database: MySQL 8.4 with `utf8mb4_unicode_ci` collation.
- Authentication: Laravel Breeze email/password authentication, verification, password recovery, and Laravel Socialite Google OAuth.
- Local runtime: Docker Desktop Compose project `casa-paraiso-web` with Apache/PHP, MySQL, and Mailpit.
- Package management: Composer for PHP and npm for compiled web assets. Node is a build dependency, not a production service requirement.

## Web Boundaries

The application is server-rendered. Alpine.js may manage local disclosure, camera, calendar, and modal behavior; URLs, filters, pagination, form submissions, exports, and authorization remain Laravel web requests.

Maintain responsive layouts for small screens, but do not add a native client, public mobile API, device-token authentication, or a remote application wrapper.

## Database Rules

- Laravel migrations are the source of truth for MySQL schema changes.
- MySQL is the default application connection and the `testing` database is the PHPUnit target.
- Keep schema changes portable within MySQL and use migrations/seeders instead of manual dashboard edits.
- Database credentials, backups, source exports, and one-time migration settings remain uncommitted.

## Local Verification

```powershell
.\scripts\casa-docker.ps1 setup
.\scripts\casa-docker.ps1 compose exec -T app php artisan test
.\scripts\casa-docker.ps1 compose exec -T app npm run build
```
