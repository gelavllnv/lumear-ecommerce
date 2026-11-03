# Shared web/app account and seller contract

This Flutter client now uses the routes below. This is an integration contract,
not a deployed backend. Implement the same policies in the shared web backend;
screenshots establish the desired workflow but do not establish existing API URLs.
All paths are relative to `/api`; errors use non-2xx JSON `{ "message": "..." }`.
No SMTP credentials or email delivery service is bundled with this repository.
The teammate's database is the source of truth. The local sample schema is not
the integration target. Map the API fields to their existing schema after review;
do not import the local schema or run migrations against their database by default.
The supplied database has now been inspected: see
[`shared/README.md`](shared/README.md) for concrete schema mappings and policy
differences. These routes still require verification against the web backend.

## Password recovery and changes (both roles)

| POST route | JSON request | Success |
| --- | --- | --- |
| `/auth/password/reset/request` | `{"email":"user@example.com"}` | `{"challenge_id":"opaque-id"}` |
| `/auth/password/reset/confirm` | `{"challenge_id":"opaque-id","code":"123456","new_password":"...","confirm_password":"..."}` | `{"updated":true}` |
| `/auth/password/change/request` | `{"current_password":"..."}` and bearer session | `{"challenge_id":"opaque-id"}` |
| `/auth/password/change/confirm` | Same confirmation fields as reset, plus bearer session | `{"updated":true}` |

The server sends a six-digit code to the **registered account email**, including
Gmail and other providers. A seller's editable shop contact email must never
choose the password verification recipient. Change requires verification of the
current password before sending a code. Google-only accounts manage their Google
password with Google; reject password changes with an explanatory error rather
than silently creating a local password. Decide account-linking policy separately.

Reset request returns the same shape, message, and comparable timing for unknown,
ineligible and eligible accounts. Use an opaque dummy challenge for unknown
accounts to prevent account enumeration. Never return the code to the app.
Store challenge/code digests in the authentication service, bound to user,
purpose and (for change) session. Suggested expiry is 10 minutes; limit attempts
and throttle sends by account and source. New requests supersede old challenges.
Reject expired, consumed, incorrect, wrong-purpose or wrong-session challenges.
Do not update credentials at the request step. Confirm atomically verifies the
challenge, enforces password policy (at least 8 characters, uppercase, lowercase,
number), checks confirmation, writes a strong password hash and consumes the
challenge. Send a password-changed notification only after a committed change.
Revoke other sessions; keep the current change session valid. Reset revokes all
sessions and returns the user to sign-in. Do not log passwords or verification codes.

The app shows validation/service failures without claiming a change succeeded.
Local preview explicitly disables this flow; it never simulates email delivery.

## Product archive

`GET /seller/workspace` product objects include boolean `archived` (defaults false
for older responses) and `active`. Include archived products for management.
`PATCH /seller/products/{id}/archive` takes `{"archived":true|false}`.
Archive atomically sets archived=true and active=false. Restore sets both false;
the seller explicitly activates the restored Hidden listing. Preserve media,
variety, stock, identifiers, and order history. Do not delete product rows.
Archiving can apply to legacy multi-variety listings without editing their stock.
Product create/update payloads also carry `archived`; reject active+archived.

Map the partner database's archive field or soft-delete state to API `archived`.
If their schema has no equivalent, agree on a server-side extension with its owner.
Buyer catalog/search and checkout eligibility must require active AND NOT archived
on the server. Existing order snapshots remain readable and fulfillable. Enforce
shop ownership and active-shop authorization for archive/restore and activation.
Exclude archives from actionable low-stock alerts; Hidden listings still count.

## Seller fulfillment

| State | Seller action | Result |
| --- | --- | --- |
| pending | Accept order | accepted |
| accepted | Mark packed | packed |
| pending / accepted | Cancel with nonempty reason | cancelled, reserved stock restored once |
| packed | Request courier pickup, approved partner required | ready_to_ship |
| ready_to_ship | Wait for courier | No additional seller mutation |
| shipped / in_transit / delivered / completed / cancelled | View only | No additional seller mutation |

`POST /seller/orders/{id}/accept` takes `{}` and only accepts pending orders.
`POST /seller/orders/{id}/pack` takes `{}` and requires an accepted order.
`POST /seller/orders/{id}/pickup` takes `{"courier_id":"partner-id"}`.
`POST /seller/orders/{id}/cancel` takes `{"reason":"..."}`.
The app refreshes the workspace after success; server state remains authoritative.
Pickup requests do not mark an order shipped. Courier handoff events own shipment,
tracking and delivery changes. There is no seller Mark shipped shortcut.

Workspace includes `couriers: [{"id":"partner-id","name":"Partner name","approved":true}]`.
Only approved partners appear in selection; an absent/empty list disables pickup.
Orders include `payment_status`, `courier_name`, and `tracking_number` when known.
Cancelled orders include `cancellation_reason`, the seller's required free-text
reason, so it remains visible after refreshing or signing in again.
Payment is displayed separately; packing and pickup never mark an order paid or
release earnings. Use the existing `raw_status` field for workflow state; the app
accepts both `shipped` and legacy `in_transit` for the Shipped display label.

Authorize the seller/shop/order, validate the current state and partner approval
again on every mutation, lock affected rows, record history and make retries safe
(one history entry, stock restoration and pickup booking). A delayed courier
booking response must not cause duplicate pickups. Return 409 on stale/incompatible
state and a readable error. Never trust a client-supplied shop ID or role.

Order filters search reference/customer/items and match exact fulfillment state.
From/To dates are inclusive order placement calendar days in Philippine time
(UTC+8), including the whole end day. The client filters the complete workspace
history; a paginated backend must implement equivalent filters across all pages.

## Integration acceptance checks

Verify real Gmail delivery and expiry/replay protection for both roles; wrong
current password/code cannot change credentials; shop email cannot redirect codes.
Verify archived products disappear from web/app buyer catalog and checkout, restore
Hidden, and preserve order history. Verify both web and app enforce the same
packing/cancellation/pickup states, courier approval, retry behavior, and tracking.
Flutter tests use mocked APIs and local records; they do not prove live delivery
or compatibility with a separately maintained web backend.
