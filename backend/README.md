# Local shared MySQL API

The adapter connects Flutter to `lumear_shared_20261103_7c92` at
`127.0.0.1:3306`. It does not import a substitute schema or seed sample accounts.
The dedicated local app account has SELECT/INSERT/UPDATE/DELETE access to this
database only. Credentials are in ignored `backend/.env.mysql`, outside Flutter.

## Start the connection

From the project root:

```powershell
backend/.venv/Scripts/python.exe backend/run_api.py --check
backend/.venv/Scripts/python.exe backend/run_api.py
```

The API listens at `http://127.0.0.1:8000/api`. `/health` confirms MySQL storage
and a non-secret database fingerprint. On another checkout, create a Python
environment, install `requirements-mysql.txt`, and create `.env.mysql` from the
example only if the local config does not already exist.

To start the API, forward USB traffic, and launch Flutter together:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/run-connected.ps1 -Device 921b0a62
```

Replace the device ID for another phone. The launcher uses a short Java TEMP
directory to avoid Windows socket-path failures and checks the API database/code
fingerprints. Alternatively, start the API and choose **Lumear · Shared MySQL**
in VS Code. Flutter uses `config/local-api.json`; the phone needs
`adb reverse tcp:8000 tcp:8000`. Keep MySQL and the API running on this PC.
This local copy is not synchronized with the teammate's hosted database.
Online use needs a hosted HTTPS API and an updated Flutter API URL.

## Accounts

Existing verified database accounts can use their current password. Suspensions
and original two-factor requirements are not bypassed. Mobile session tokens
are held in memory; restarting the API requires signing in again.

Continue with Google verifies signature, issuer, expiry, audience and verified
email through Google's official `google-auth` library. `GOOGLE_CLIENT_ID` must
match Flutter's public Web client ID. The stable Google subject is stored in
the existing `auth_identities` table. Tokens/passwords are never logged.

Existing accounts retain their role, including sellers. New Google users choose
Buyer or Seller in the app. New shops are **pending**, with 8% commission; they
can edit shop details while waiting for approval. Listing/order actions require
an approved shop. Google does not approve shops or grant admin access.
Gmail/Google Workspace addresses can link by verified email; third-party email
providers need additional verification and are not automatically linked.

## Data and limits

- Workspaces, products/varieties, database categories, addresses, cart, saved
  products, COD checkout, order states and cancellation reasons use shared tables.
- Shipping is PHP 50 per shop order. Checkout preserves the seller's configured
  commission rate and uses inventory transactions and duplicate-request keys.
- Seller fulfillment is pending → accepted → packed → pickup requested with an
  approved courier. Pickup creates one unassigned shipment; courier handoff and
  delivery/settlement updates remain the logistics backend's responsibility.
- Archive/restore uses `products.publication_status` (`archived` / `hidden`),
  independently of moderation. The website must use the same convention.
- Wallet/payout reads use shared balances and journal deltas. Payout requests
  are saved for manual review; the API does not transfer funds.
- Sales reports use recorded orders/commissions. Traffic and attribution metrics
  are labeled unavailable without event data. Completion timeline dates are used
  where present, with the app's disclosed placement-date fallback otherwise.
- Messages use existing conversations/messages, with ownership and retry checks.
- Product/shop uploads go to ignored local media storage, with paths in existing
  tables. The SQL ZIP did not include the website's original image files.

Password email recovery/change and email/password registration are not enabled
until transactional email and purpose-bound challenge handling are configured.
Google registration works without SMTP. AI replies, voucher checkout, return
approval, two-factor sign-in and rider delivery updates are not implemented by
this local adapter; unavailable writes return explicit errors, not fake success.

## Verify

```powershell
$env:RUN_SHARED_MYSQL_TESTS='1'
backend/.venv/Scripts/python.exe -m unittest discover -s backend -p 'test_*.py' -v
```

Integration records are created within a transaction and always rolled back.
Tests do not change imported passwords, create/drop schemas or approve real
sellers. Signed-token tests use a local RSA key; synthetic tokens are not treated
as real Google sign-ins outside those tests.
