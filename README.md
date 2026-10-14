# Lumear Marketplace

Laravel multi-vendor marketplace for the Philippines. The active payment method is Cash on Delivery (COD) only; seller withdrawals and refunds are recorded as manual, offline actions.

## Phase 1 included

- Buyer, seller, and admin role separation.
- Email verification and backend-driven Google OAuth with PKCE/state verification.
- Seller onboarding: store profile, secure KYC upload, and encrypted manual payout details.
- Products with multiple SKU variants, inventory movement history, and safe stock deduction.
- COD checkout with row locks, order timeline events, idempotency key support, seller wallet ledger, manual COD release, and payout queue.
- Admin seller/KYC review, private document downloads, manual payout approval/payment/rejection, and audit logs.
- Responsive Laravel/Vite UI; Blade markup, CSS, and JavaScript live in separate files.

## Local setup

1. Copy `.env.example` to `.env`, configure the existing MySQL database, then run:

   ```powershell
   composer install
   npm install
   php artisan key:generate
   php artisan migrate
   php artisan db:seed
   npm run build
   php artisan serve
   ```

2. Existing records are preserved. The Phase 1 migration is additive; do **not** use `migrate:fresh` on a database containing marketplace data.

3. To create the first administrator, add a unique email and strong password to `.env`:

   ```env
   ADMIN_NAME="Marketplace Administrator"
   ADMIN_EMAIL="admin@example.com"
   ADMIN_PASSWORD="use-a-long-unique-password"
   ```

   Then run:

   ```powershell
   php artisan db:seed --class=AdminUserSeeder
   ```

   Sign in at `/login`. On first login, the administrator is routed to the mandatory TOTP setup page; add the displayed key to an authenticator app, confirm the six-digit code, then continue to `/admin`.

## Google Sign-In

Google Sign-In is available for **buyers and sellers** after you create an OAuth client in Google Cloud Console. It is deliberately disabled until the credentials below are supplied; this prevents an unsafe or partially configured login flow.

1. In [Google Cloud Console](https://console.cloud.google.com/apis/credentials), create a project (or choose an existing one), configure the OAuth consent screen, then create an **OAuth client ID** of type **Web application**.
2. Add this exact Authorized redirect URI for local development: `http://localhost:8000/auth/google/callback`.
3. Add your deployed HTTPS callback too when publishing, for example: `https://your-domain.com/auth/google/callback`.
4. Add these values to `.env` (never commit the secret):

```env
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
```

5. Run `php artisan config:clear` and restart `php artisan serve`.

The buyer and seller buttons both use this one OAuth client. The selected role is retained securely in the server-side OAuth state, so a public Google login cannot create an admin account.

### Windows SSL certificate error

If the Laravel log says `cURL error 60` while calling `oauth2.googleapis.com`, PHP has no configured certificate authority (CA) bundle. Lumear includes a local CA bundle at `storage/certificates/windows-root-ca-bundle.pem` and keeps SSL verification enabled for the Google token exchange and ID-token verification. Do not disable certificate verification. To use a different managed bundle, set `GOOGLE_CA_BUNDLE` to its absolute path.

Create a Google OAuth Web Application client and set its authorized redirect URI to:

```text
http://localhost:8000/auth/google/callback
```

Then add these values to `.env`:

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="http://localhost:8000/auth/google/callback"
ALLOWED_ADMIN_EMAIL_DOMAINS="example.com"
```

Buyer and seller Google buttons are available on `/login` and `/register`. Public Google sign-in can only create buyer or seller accounts. It never auto-creates an administrator.

## Email verification delivery

Local development defaults to `MAIL_MAILER=log`. This safely writes verification messages to `storage/logs/laravel.log`; it does **not** send mail to an inbox. Configure a real mail provider before expecting verification emails to arrive.

For a Gmail account with an App Password, add the following to `.env` (use your own sending address and App Password; never commit it):

```env
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=your-address@gmail.com
MAIL_PASSWORD=your-16-character-google-app-password
MAIL_FROM_ADDRESS=your-address@gmail.com
MAIL_FROM_NAME="Lumear"
MAIL_TIMEOUT=10
```

After saving the settings, run `php artisan config:clear`, restart `php artisan serve`, and press **Send another link**. Google App Passwords require two-step verification on the sending Google account. You may use Resend, Postmark, or another transactional email provider instead; select its supported Laravel mailer and use its provider credentials.

## Docker

For a local service stack, first update the database variables in `.env` to the values in `docker-compose.yml`, then run:

```powershell
docker compose up --build
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

The storefront is available at `http://localhost:8000`. Docker starts MySQL, Redis, Meilisearch, and MinIO alongside Laravel. Never use the example container passwords outside local development.

## Verification

```powershell
php artisan test
npm run build
```

## Operational notes

- New seller KYC and payout proof files are private; only administrators can download KYC records.
- Seller payout-account numbers are encrypted by Laravel’s encrypted model cast.
- COD income initially appears as pending. An admin releases it only after confirmed delivery and courier remittance, then the seller can request a manual payout.
