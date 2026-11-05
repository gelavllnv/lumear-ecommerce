# Local shared MySQL API

The adapter connects Flutter to `lumear_ecommerce` at
`127.0.0.1:3306`. It does not import a substitute schema or seed sample accounts.
The local app account requires SELECT/INSERT/UPDATE/DELETE access to the selected
database. Credentials are in ignored `backend/.env.mysql`, outside Flutter.

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

In VS Code, select **Lumear · Shared MySQL** and press **F5**. Its pre-launch
task checks/starts the local API and sets up USB forwarding automatically.
The launcher also uses a short Java TEMP directory for Windows builds.

To start the API, forward USB traffic, and launch Flutter together:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/run-connected.ps1 -Device 921b0a62
```

Replace the device ID for another phone. The launcher uses a short Java TEMP
directory to avoid Windows socket-path failures and checks the API database/code
fingerprints. Alternatively, start the API and choose **Lumear Â· Shared MySQL**
in VS Code. Flutter uses `config/local-api.json`; the phone needs
`adb reverse tcp:8000 tcp:8000`. Keep MySQL and the API running on this PC.
This local copy is not synchronized with the teammate's hosted database.
Online use needs a hosted HTTPS API and an updated Flutter API URL.

## Google registration migration

For an existing `user_profiles` table, apply
`migrations/20261104_google_registration.sql` once to the selected database.
New installations apply `20261103_user_profiles.sql` first, then this migration.
The startup check reports a missing birth-date column before serving requests.
The November 4 local Workbench database already has both migrations.

## Accounts

Existing verified database accounts can use their current password. Suspensions
and original two-factor requirements are not bypassed. Mobile session tokens
are held in memory; restarting the API requires signing in again.

Continue with Google verifies signature, issuer, expiry, audience and verified
email through Google's official `google-auth` library. `GOOGLE_CLIENT_ID` must
match Flutter's public Web client ID. The stable Google subject is stored in
the existing `auth_identities` table. Tokens/passwords are never logged.

Existing accounts retain their role, including sellers. New Google users choose
Buyer or Seller in the app. Before opening Google, Lumear shows a Continue/Cancel
confirmation. New users must submit full name, verified Google email, contact
number, date of birth (18–120), password, and a default delivery address before
receiving a session. Existing Google users missing birth date/contact/address
must complete those details; their password and role are preserved. The server
validates and saves all registration data in one transaction. New shops are **pending**, with 20% commission; they
can edit shop details while waiting for approval. Listing/order actions require
an approved shop. Google does not approve shops or grant admin access.
Gmail/Google Workspace addresses can link by verified email; third-party email
providers need additional verification and are not automatically linked.

## Data and limits

- Workspaces, products/varieties, database categories, addresses, cart, saved
  products, COD checkout, order states and cancellation reasons use shared tables.
- Shipping is PHP 50 per shop order. New mobile checkouts allocate merchandise
  revenue 80% seller / 10% admin / 10% app owner, with integer centavo rounding,
  inventory transactions and duplicate-request keys.
- Seller fulfillment is pending â†’ accepted â†’ packed â†’ pickup requested with an
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

## Help, assistant, and notification settings

Profile > Help Center provides searchable guidance from `assets/data/lumear_help.json`.
The same content grounds the assistant's answers. Without `OPENAI_API_KEY`,
`POST /chat/assistant` returns a clearly labeled built-in guide answer. With a key,
it uses OpenAI Responses, the `OPENAI_MODEL` setting (default `gpt-4.1-mini`),
`store: false`, bounded conversation/output size, and a six-question/minute
per-user limit. Provider failures return an error; they do not impersonate live AI.
Only the question/history and marketplace help text are sent; the assistant does
not receive database records or tools to modify orders. Live replies require a
valid API key and provider account access; mocked provider tests do not prove that.

Create an OpenAI project key at https://platform.openai.com/api-keys and put it
only in the ignored `backend/.env.mysql` as `OPENAI_API_KEY=...`. Do not paste the
key into chats, Flutter source, `.env.mysql.example`, or Git. Restart the local
API after changing it. Provider usage is governed by your OpenAI account setup.
Reference: https://developers.openai.com/api/docs/guides/api-keys

Apply `migrations/20261104_notification_preferences.sql` once to existing setups.
The current local database already has it. `GET/PATCH /notification-preferences`
saves optional in-app order, promotion, and general preferences per account.
Filtering does not delete notifications; enabling a category restores visibility.
Security notices remain visible. Phone push/email delivery is not implemented.

## Seller e-wallet and 80/10/10 split

Apply `migrations/20261105_wallet_split.sql` once using a database administrator
(the app account intentionally lacks CREATE permission). It adds two tables and
zero-balance platform wallet rows; existing orders and balances remain unchanged.
`apply_wallet_migration.py` is a local-only alternative when the configured
connection has CREATE permission. Restart the local API after migration.
The November 5 local database has this migration applied (67 tables).

New mobile checkouts record 80% merchandise revenue in the seller pending wallet.
The remaining 20% is allocated to admin (10%) and app owner (10%). Shipping is
excluded. Commission rounds half-up to centavos; admin rounds half-up and the
owner receives the remaining commission centavo, so allocations always sum to
subtotal. Unpaid cancellations reverse the pending seller credit in the same
transaction as stock restoration.

In Earnings, **Credit confirmed earnings to wallet** calls
`POST /seller/orders/{seller_order_id}/settle`. Only the owning approved seller
can request this. The server requires completed fulfillment, paid payment status,
no return request and no competing wallet entries. It atomically releases the
seller allocation and credits `mobile_platform_wallets` for `admin` and `owner`.
Repeated release requests have no additional effect. Payment and fulfillment
confirmation remain the logistics/payment backend's responsibility.

Admin and owner totals are internal accounting balances, queryable by an
administrator in `mobile_platform_wallets`; there is no new admin/owner login or
external cash transfer API. Seller withdrawals use the existing reviewed payout
request flow. Orders with returns or other ledger activity must use the original
refund/reconciliation workflow. Do not run the legacy website wallet release on
new mobile allocations: it uses a different shipping/settlement convention.
Update that separate backend before combining its settlement writes with these
orders. Historical orders retain their original commission and settlement flow.
