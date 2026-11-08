# Google sign-in setup for Android

## Local API now implemented

`backend/shared_store.py` implements `/auth/google` using Google's official
verification library and the Web client ID below. Identities are linked in the
shared `auth_identities` table. Existing accounts keep their role; new accounts
choose Buyer or Seller. New seller shops remain pending approval. See
[local setup](backend/README.md). Google sign-in now requires email OTP delivery
through the configured Lumear SMTP sender before onboarding or session issuance.
Use `scripts/configure-email.ps1` to configure a Gmail sender locally.

## Required registration details

The app shows a Lumear Continue/Cancel confirmation before opening the native
Google picker. Google controls its own account and consent screens.
`/auth/google` first returns `requires_otp: true` and a challenge ID. The app
collects the email code and calls `/auth/google/verify` for a short-lived
`verification_token`, then repeats the Google exchange with that proof.
After OTP, `/auth/google` returns `requires_account_type: true` with `is_new_account`,
`name`, `email`, and `existing_role` when registration is incomplete.
The app opens the full registration form instead of a workspace. The form stays
open while the server saves the account and only closes after a valid Lumear
session is returned. Save errors remain on the form with all entered details
preserved for retry; successful completion opens the server-assigned workspace.

The completion request repeats the Google ID token and OTP proof and sends `account_type`,
`name`, `phone`, `date_of_birth` (`YYYY-MM-DD`), and `address` with recipient
`name`, `phone`, `street`, `barangay`, `city`, `province`, and `postal_code`.
New accounts also supply a password (8 characters minimum, 72 bytes maximum).
The client confirms the password before sending. Google email is read-only;
the server always uses the token's verified email. Optional profile photos remain
supported. The server derives age from date of birth and requires age 18–120.
All details are saved atomically before a Lumear session is issued.

Existing accounts keep their role and password. Accounts with complete details
can sign in again without repeating registration. New seller shops remain pending.
Apply `backend/migrations/20261104_google_registration.sql` once after the
profile-table migration. The current local database already has this column.

## Cloud configuration and reference contract

The login button and native Google account picker integration are prepared.
No Google Cloud project or backend has been created by this change. Demo mode
explains that Google sign-in is unavailable; it does not authenticate a Google
account or silently create a demo session.

The Lumear project's public Web client ID is configured as the app's default:
`401368596151-qnltc2gebbqu0gui0qtbu5u3j8qf59fu.apps.googleusercontent.com`.
`GOOGLE_SERVER_CLIENT_ID` can override it for another Google Cloud project.
The Android OAuth client and shared backend setup below are still required.

1. Create or select the team's Google Cloud project and configure the Google
   Auth Platform consent screen, audience, and test users while testing.
2. Create an Android OAuth client for package `com.example.flutter_application_1`
   with the SHA-1 fingerprint of the certificate signing the installed APK.
   From `android/`, run `./gradlew.bat signingReport` to inspect fingerprints.
   The current release build also uses the debug signing configuration. Register
   the production/Play signing certificate separately when signing changes.
3. Create a Web application OAuth client in the same project. Its public client
   ID is passed as `GOOGLE_SERVER_CLIENT_ID`; do not pass the Android client ID
   for this value. This implementation supplies it directly to the plugin,
   so Firebase and `google-services.json` are not required for this approach.
4. Run the local adapter with the same Web client ID and configured SMTP sender,
   or deploy it with HTTPS and the shared MySQL database for hosted use.
5. Build/run with all three defines, replacing the placeholders:

```powershell
flutter run --dart-define=ENABLE_BUYER_BACKEND=true --dart-define=API_BASE_URL=https://YOUR-SHARED-HOST/api --dart-define=GOOGLE_SERVER_CLIENT_ID=YOUR-WEB-CLIENT-ID.apps.googleusercontent.com
```

Use the same defines with `flutter build apk`. Use an Android device/emulator
with Google Play services. Google configuration changes require rebuilding;
hot reload does not change Dart environment defines. No client secret belongs
in this app. Web/iOS Google sign-in setup is outside the current Android project.

## Shared backend contract

`POST /api/auth/google`, JSON body:

```json
{"id_token": "GOOGLE_ID_TOKEN"}
```

The backend must verify Google's token signature, issuer, expiry, and audience
against the Web OAuth client ID, and identify the account by Google's `sub`.
The initial response contains `requires_otp` and `challenge_id`. Submit the code
to `POST /api/auth/google/verify`, then repeat the Google exchange with the
returned `verification_token`. After OTP and any required onboarding, the backend
returns a Lumear session token and the account's server assigned role:

```json
{"token": "LUMEAR_SESSION_TOKEN", "role": "buyer"}
```

`seller` is the other supported role. Google identity alone does not grant a
seller role. The backend should apply the team's account-linking and onboarding
policy; incomplete registration, seller approval, duplicate accounts, or denied
access should return a non-2xx JSON `message`. The current client shows that
message and stays on login. It never assigns a seller role from a Google email.

The Lumear session token is used for the existing buyer/seller workspace calls.
The Google ID token is sent only to the login exchange and is not stored locally.
Logging out ends the Lumear app session; it does not remove the device's Google
account or revoke Google consent.

## Verify after configuration

Test choosing an account, canceling the picker, backend rejection, buyer/seller
routing, logout, and signing in again on the actual Android build. Mock tests
cover token exchange, role validation, cancellation, and demo-mode behavior;
they do not verify Google's native picker or Cloud configuration.

References: [Flutter Google sign-in plugin](https://pub.dev/packages/google_sign_in/versions/7.2.0),
[Android plugin setup](https://pub.dev/packages/google_sign_in_android#integration),
[Google server-side token verification](https://developers.google.com/identity/sign-in/android/backend-auth).
