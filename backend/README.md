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

New Google sellers must also provide their shop name and description, a
government-issued ID and TIN number. The ID upload accepts JPG, PNG, or PDF up
to 5 MB. Selfies and business registration documents are not required. The
server validates the ID and requires a TIN before creating the account; this
does not verify the TIN against an external registry. It records a pending submission
in `seller_kyc_submissions`. Files are stored under `PRIVATE_UPLOAD_ROOT`
(default: project `private_uploads/`), outside public media, using random names.
Failed registrations remove their newly written files. Existing sellers retain
their approval status and do not re-upload documents during Google sign-in.
Administrator review uses the existing KYC records and private storage; these
files have no public download endpoint. No new schema migration is required.

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

Password email recovery/change and Google sign-in require transactional email
configuration as described below. Email/password registration remains unavailable.
Voucher checkout, two-factor sign-in and rider delivery updates are not implemented by
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

## Buyer refund wallet and account OTP (November 7)

My Orders includes **Cancel order**, **Return / Refund** and **Order received**.
Cancellation requires a reason and is available for unpaid pending/accepted
orders before packing; the existing transaction restores inventory and reverses
pending seller credits exactly once. Paid or packed orders cannot use immediate
cancellation. Return/refund opens the same wallet-destination request form for
an eligible order item; existing request statuses appear on the order card.

`POST /orders/{seller_order_id}/received` confirms receipt only for the owning
buyer after delivery and confirmed payment. Open requested/approved/disputed
returns block confirmation. The endpoint marks the seller order completed and
records a timeline event; retries do not add another event. It does not change
payment status or release wallet earnings. Buyers see a confirmation dialog
advising them to check all items and request a return first if there is an issue.
The local preview updates only orders created in that running preview session.

Profile > **My e-wallet** shows the credited PHP balance, refund history and
return statuses and seller return instructions. Buyers can request a return for all units of one item from a
delivered/completed, paid order. The server checks ownership and rejects duplicate
requests. Merchandise discounts are deducted; shipping is excluded. Local preview
shows an empty wallet and cannot submit requests or hold real money. Wallet
checkout payments and withdrawals are outside this refund-balance feature.

Apply `migrations/20261107_buyer_wallet.sql` as a database administrator, or run
`backend/.venv/Scripts/python.exe backend/apply_buyer_wallet_migration.py` with a
local connection that has CREATE permission. The configured app account lacks
CREATE permission; the migration was applied through the saved local administrator
Workbench connection on November 7 (69 tables). Existing orders,
seller balances and historical refunds are not backfilled.

Seller > Orders > **Returns and refunds** reads `GET /seller/returns`. Only
the owning approved seller can call `POST /seller/returns/{id}/approve`, `/reject`
or `/refund`. Review needs a response with return instructions or a rejection
reason. The buyer sees that response in My e-wallet. A refund requires an approved
request and explicit seller confirmation that returned items were received.

`buyer_wallet.review_return` is the refund processor for mobile orders. It
atomically reverses the appropriate seller/platform allocation, records the
refund, and credits the buyer wallet. Pending seller funds are reduced before
release; already released earnings and platform balances are debited together.
Open payout reservations cannot be spent by refunds. Insufficient funds return
an error without partial writes. Integer-centavo allocation uses remaining
shares so sequential partial refunds conserve all money, including rounding.
Duplicate refund retries return the prior result without another debit/credit.
Earnings release after a refund credits only the remaining allocations. Full
refunds leave merchandise earnings at zero; shipping remains excluded.

Requests record **Lumear e-wallet** as their destination in
`mobile_buyer_wallet_returns`; credits and reversed shares are recorded in
`mobile_buyer_wallet_credits`. Do not also issue an external refund for these
requests. Historical orders without `mobile_sale_allocations` still require the
original settlement administrator; the app cannot reconstruct their accounting.
Buyers have no endpoint to approve or credit refunds. Physical return shipping
is arranged using the seller's instructions; it is not an automated courier label.

Configure `SMTP_HOST`, `SMTP_PORT` (STARTTLS, default 587), `SMTP_USERNAME`,
`SMTP_PASSWORD` and `SMTP_FROM` in ignored `backend/.env.mysql`, then restart
the API. `scripts/configure-email.ps1` securely prompts for a Gmail sender address
and App Password and writes only the ignored local configuration. It verifies
SMTP login without printing credentials. A Gmail App Password requires Google
2-Step Verification: https://support.google.com/accounts/answer/185833.
Only one sender is configured; recipients use their own registered emails.
`backend/check_email.py --send-to YOUR_EMAIL` explicitly sends one delivery test.
The local Gmail sender was configured and SMTP-authenticated on November 7;
the running API reports `email_otp_enabled: true`. Do not put credentials in Flutter or Git.
Missing email configuration blocks the OTP flows explicitly.

Every Google sign-in requires a six-digit email OTP before account creation,
onboarding or session issuance. `/auth/google/verify` exchanges the code for a
short-lived proof bound to the verified Google subject/email. The proof survives
onboarding validation retries and is consumed when the session is issued.
Forgot password uses `/auth/password/reset/request` and `/confirm`; change
password uses `/auth/password/change/request` and `/confirm`, requires an active
session and checks the current password before sending email. Unknown/ineligible
recovery emails receive the same request response without an email being sent.
Password updates invalidate prior sessions through the stored password version.

Codes expire after ten minutes, allow five verification attempts and have a
60-second per-email resend limit across purposes. Resending invalidates the
previous code for that purpose. Codes are stored only as keyed digests, never
returned or logged. Challenges/proofs, like sessions, are process-local and
clear on restart; run a single API process until shared challenge/session storage
is implemented. Automated checks mock SMTP delivery. On November 7 the configured
SMTP server also authenticated successfully and accepted one user-authorized test
email to the sender account; inbox/spam arrival must be confirmed by the recipient.

The connected Android debug APK is at `build/app/outputs/flutter-apk/app-debug.apk`.
It uses `config/local-api.json` and requires USB API forwarding to this PC:
connect the Android device with USB debugging, then run
`scripts/start-local-api.ps1 -Device DEVICE_ID`. Keep MySQL and the API running.
Use `scripts/run-connected.ps1 -Device DEVICE_ID` to build/install/run from source.
No Android device was attached during the build. Native Google account selection
and actual inbox receipt remain device/manual verification steps.
