# Casa Paraiso Local Demo Credentials

These credentials are for local development and demo use only. Do not use these values for production, Hostinger, database access, or any real deployed account.

Production credentials must stay outside committed source files.

## Admin Account

| Role | Name | Email | Password | Source |
| --- | --- | --- | --- | --- |
| Admin | Casa Paraiso Admin | `admin@casaparaiso.test` | `password` | `database/seeders/DatabaseSeeder.php` |

## Usage

For a verified disposable local database only, add the demo data without wiping existing records:

```powershell
.\scripts\casa-docker.ps1 compose exec -T app php artisan db:seed
```

Open the local login page:

```text
http://localhost:18001/login
```

Sign in using the admin email and password above. Never run `migrate:fresh`, `db:wipe`, or other destructive database commands against an account-bearing database.
