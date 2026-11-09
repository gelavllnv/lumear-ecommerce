# Run Lumear on your own Windows computer

This source project uses real backend accounts. Each computer runs its own
MySQL database and Python API. Separate databases do not synchronize.

## What to receive

- The updated Flutter project, including `backend/`, `scripts/`, and `assets/`.
- A current export of the group's working `lumear_ecommerce` database, supplied
  separately by the database owner. The source project does not include its
  saved accounts, products, or orders. Include the current tables and migrations
  in that export; the source `database/shared/schema-reference.sql` is only a
  schema reference. The older `database/lumear_ecommerce.sql` is a different design.
- Any required uploaded media, supplied separately by the project owner if you
  need the existing images/documents. A database export does not contain file bytes.

Do not reuse another computer's `backend/.venv`, `build/`, `.dart_tool/`, or
`android/local.properties`. Let Flutter regenerate machine-specific paths.
Create your own backend config; setup preserves an existing `.env.mysql`.

## First-time setup

1. Install Flutter, Python 3 with the Windows `py` launcher, MySQL Server and
   Workbench, and Android SDK/platform tools. Confirm `flutter doctor` works.
2. Open PowerShell in the project folder and run:

   ```powershell
   powershell -ExecutionPolicy Bypass -File scripts/setup-local.ps1
   ```

   If Python has no `py` launcher, add `-Python C:\path\to\python.exe`.
3. Import the current database export into your local MySQL server using Workbench.
   Use the database name from that export in `backend/.env.mysql`. Enter your own
   `MYSQL_HOST`, `MYSQL_PORT`, `MYSQL_DATABASE`, `MYSQL_USER`, and `MYSQL_PASSWORD`.
   Setup never imports or overwrites a database.
4. Configure email for registration, Google sign-in, and password recovery:

   ```powershell
   powershell -ExecutionPolicy Bypass -File scripts/configure-email.ps1
   ```

   The Google Web client ID is already provided in `.env.mysql.example` and
   matches Flutter's default. If using another Google project, update both.
   Your computer's Android debug signing certificate can differ from your
   teammate's: register its SHA-1 in the team's Android OAuth client setup.
   See [Google sign-in setup](GOOGLE_SIGN_IN.md).
5. Confirm database compatibility:

   ```powershell
   backend/.venv/Scripts/python.exe backend/run_api.py --check
   ```

   This must report `status: ok` and `email_otp_enabled: true` for Google sign-in.
   If it reports missing tables/columns, use the matching migrations in
   `backend/migrations/` after checking which are already applied; see the
   [backend README](backend/README.md). Do not re-import an older schema.

## Every time you run it

Start MySQL. Connect your Android phone/tablet by USB, enable USB debugging,
and accept the authorization prompt. Find its ID using `adb devices`, then run:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/run-connected.ps1 -Device YOUR-DEVICE-ID
```

This starts/checks the API and forwards the device's port 8000 to your own PC.
Keep the API and USB connection running. Reconnect/relaunch after USB forwarding
is lost. The **Lumear · Shared MySQL** VS Code launch option also starts the API.
For an Android emulator, follow the API URL instructions in [README.md](README.md).

Sign in with a verified account in your imported database or register a real
account. Google sign-in still needs internet and working email delivery.

If the app says **Cannot connect to Lumear**, confirm
`http://127.0.0.1:8000/api/health` opens on your PC and run the connected launcher
again. An APK by itself cannot start the API or copy the database to a phone.
