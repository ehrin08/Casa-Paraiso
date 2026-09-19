# Render and Oracle MySQL Deployment

Casa Paraiso runs as a free Render Docker web service in Singapore with an Oracle Cloud Always Free MySQL HeatWave DB System. The old `casa-paraiso-supabase-api-poc` Render service remains unchanged as archive and rollback evidence.

## Repository deployment assets

- `Dockerfile.render` builds Composer dependencies and Vite assets into a production Apache/PHP image.
- `docker/app/render-entrypoint.sh` configures Apache to listen on Render's `PORT`, warms Laravel caches, and only migrates when `RUN_MIGRATIONS=true`.
- `render.yaml` creates the new `casa-paraiso-web` service from `main`; it contains no secrets.
- The image runs Apache directly. Booking notification badges use authenticated HTTP polling every 30 seconds while visible; no WebSocket process or proxy is required.

## Oracle Cloud setup

1. Create an Oracle Cloud Always Free MySQL HeatWave DB System in the account home region.
2. Create schema `casa_paraiso` using `utf8mb4` and `utf8mb4_unicode_ci`.
3. Create a dedicated application user with privileges limited to that schema. It must be able to create and alter tables during migrations and read/write application records afterwards.
4. Enable public connectivity only for the current Render Singapore outbound CIDR ranges and the administrator's current public IP. Do not open the database to all addresses.
5. Download Oracle's MySQL CA certificate, then add it to the Render service as the secret file `oracle-mysql-ca.pem`. Render exposes it at `/etc/secrets/oracle-mysql-ca.pem`.

## Render creation and configuration

1. In Render, create a Blueprint from `ehrin08/Casa-Paraiso` on `main`. This creates a separate service named `casa-paraiso-web`; do not attach the Blueprint to the archived service.
2. Enter every `sync: false` value when Render prompts. Generate `APP_KEY` with `php artisan key:generate --show` and retain it securely for every later deployment.
3. Set `APP_URL` and `TRUSTED_HOSTS` to the exact Render hostname. Use the custom domain later only after it is verified.
4. Add the Oracle CA secret file in the service Environment page. Do not store it in Git.
5. Configure a transactional mail provider, then set its values in Render. Free Render services cannot send to SMTP ports 25, 465, or 587; use the provider's supported alternate submission port or HTTP mail API integration.
6. Register `https://<render-host>/auth/google/callback` in Google Cloud Console and use that same URL for `GOOGLE_REDIRECT_URI`.
7. Existing deployments can remove obsolete `REVERB_*` and `BROADCAST_CONNECTION` environment variables after deploying the polling release.

## First migration and verification

1. Deploy with `RUN_MIGRATIONS=false`; confirm `/up` becomes healthy and the image starts on Render's assigned port.
2. Change `RUN_MIGRATIONS` to `true` for one controlled deploy. The entrypoint runs only `php artisan migrate --force --no-interaction`; it never seeds, truncates, wipes, or resets data.
3. Immediately return `RUN_MIGRATIONS` to `false` and redeploy once the migration has succeeded.
4. Register or sign in with the configured `SUPER_ADMIN_EMAIL` through verified Google OAuth to create the first super-administrator account.
5. Verify guest, customer, receptionist, therapist, administrator, and super-administrator workflows. Verify password recovery and Google login after mail and OAuth are configured.

## Ongoing operations

- Keep the MySQL database externally backed up and test a restore before real customer data is accepted.
- Use only additive Laravel migrations against the Oracle database.
- The existing daily retention fallback runs when needed; add a scheduler only after the free deployment is stable.
- Do not copy any archived Supabase credentials, CA files, database exports, or retired mobile variables to the new service.
