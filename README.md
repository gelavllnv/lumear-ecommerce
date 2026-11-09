# Lumear buyer and seller app

Flutter buyer and seller interfaces connected to the shared MySQL API in
[`backend/`](backend/README.md). Normal runs and builds use real backend accounts
and persisted data. The normal app no longer offers demo accounts or local demo
registration. An unavailable backend shows an error instead of sample data.

## Run on your computer

1. Run `flutter pub get`.
2. Follow [the backend setup](backend/README.md): install the Python dependencies,
   create your ignored `backend/.env.mysql` from `backend/.env.mysql.example`, and
   connect it to the group's database with the required migrations. Keep database
   passwords on the backend.
3. Select **Lumear · Shared MySQL** in VS Code and press **F5**, or run:

   ```powershell
   powershell -ExecutionPolicy Bypass -File scripts/run-connected.ps1 -Device YOUR-DEVICE-ID
   ```

The launcher checks the database, starts the API, and forwards Android USB traffic.
Sign in with an existing verified database account or create a real account.
Registration and password recovery need the backend email sender configuration;
see `scripts/configure-email.ps1` and the backend README. Google sign-in also needs
[Google OAuth configuration](GOOGLE_SIGN_IN.md).

Plain `flutter run` uses the real backend at `http://127.0.0.1:8000/api` by default.
Start the API first. For a USB Android phone, run
`adb reverse tcp:8000 tcp:8000`; for an Android emulator use
`flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api`.

## Share with groupmates

For a groupmate running the source with their own local backend/database, follow
[GROUPMATE_SETUP.md](GROUPMATE_SETUP.md). `scripts/setup-local.ps1` installs the
dependencies and creates their config without replacing an existing one.

A project copy does not copy or synchronize your database, backend configuration,
or server. Each groupmate must either set up their local API against the group's
database, or use the same reachable hosted API:

```powershell
flutter run --dart-define=API_BASE_URL=https://YOUR-API-HOST/api
flutter build apk --dart-define=API_BASE_URL=https://YOUR-API-HOST/api
```

Replace the example address with your actual API address. On a phone,
`127.0.0.1` refers to that phone unless USB forwarding is configured. A local
MySQL copy is independent of your groupmate's copy; sharing one backend/database
is what makes everyone see the same saved accounts, products, and orders.
Rebuild and reinstall any previously distributed APK to apply these changes.

## Features and checks

Buyer browsing and seller product forms share 8 categories and 48 subcategories.
The registration address form uses a bundled PHLPost lookup for postal codes.
See [integration notes](INTEGRATION.md) and
[backend capabilities and limitations](backend/README.md) for service requirements.
Previously uploaded seller documents stay in the ignored `private_uploads/`
folder and are not part of the project handoff.

Run `flutter analyze` and `flutter test` to check the app. Automated tests can
explicitly select in-memory fixtures; normal launches always select the backend.
Connected tests use a mock API and do not require a running database.
