# Casa Paraiso Local Demo Credentials

These credentials are for local development and demo use only. Do not use these values for production, Hostinger, database access, or any real deployed account.

Production credentials must stay outside committed source files.

## Admin Account

| Role | Name | Email | Password | Source |
| --- | --- | --- | --- | --- |
| Admin | Casa Paraiso Admin | `admin@casaparaiso.test` | `password` | `database/seeders/DatabaseSeeder.php` |

## Usage

Seed the local database:

```powershell
docker compose exec -T laravel.test php artisan migrate:fresh --seed
```

Open the local login page:

```text
http://localhost:8001/login
```

Sign in using the admin email and password above.
