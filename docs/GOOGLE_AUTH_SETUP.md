# Google OAuth Setup

Casa Paraiso supports web Google OAuth alongside verified email/password registration, recovery, and login. Google identities retain the same role and profile rules as password accounts.

## Configuration

1. Create a Google OAuth web client for the deployed HTTPS host.
2. Store `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` only in the environment.
3. Set `GOOGLE_REDIRECT_URI` to `https://your-host/auth/google/callback`.
4. Add the same URI to Google Cloud Console's authorized redirect URIs.
5. Keep local development at `http://localhost:18001/auth/google/callback` only when it is registered for the development client.

After deployment, rebuild Laravel's configuration cache and verify sign-in, identity linking, and password setup for Google-only accounts.
