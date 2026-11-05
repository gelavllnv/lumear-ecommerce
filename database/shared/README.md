# Selected shared database

The active local database is `lumear_ecommerce`, re-imported in Workbench by
the user and selected on November 4, 2026. It contains the same 63 shared table
names, with the mobile `user_profiles` table added for compatibility. The ZIP
and schema reference described below record the earlier source, not a new import.
See [local connection verification](LOCAL_IMPORT.md).

The user selected `lumear-20261103T124903Z-1-001.zip` on November 3, 2026
as the database source for the buyer/seller integration. It contains 63 MySQL
table exports, including row data. No backend source, API routes, mail settings,
or server connection configuration is included.

`schema-reference.sql` retains only the 63 CREATE TABLE definitions, with a
source ZIP SHA-256 for provenance. It excludes all exported rows, account data,
session payloads, token values, audit records, and DROP statements. Table-level
AUTO_INCREMENT counters are removed; column auto-increment definitions remain.
This is a schema reference, not an import script or migration. The original
inspection did not import data. A separate user-authorized local import has now
been completed; see [local import results](LOCAL_IMPORT.md). The Flutter app
connects through the local adapter in `backend/`.

This shared schema supersedes the local `database/lumear_ecommerce.sql` design
as the integration target. Do not import that older design into this database.
The Flutter app continues to communicate with an authenticated backend API;
database credentials do not belong in the app.

## Backend mapping to the current Flutter contract

These are integration mappings and review items, not verified existing routes.
The API implementation must map database types to the existing JSON contract:
string IDs, integer centavos, JSON booleans, and explicit timestamp offsets.

| App concept | Shared database source / adaptation |
| --- | --- |
| Account identity | `users.id`, `name`, `email`, `phone`, `email_verified_at`; role comes through `role_user` / `roles`, not a client claim |
| Seller shop | `sellers` joined to `users`; shop ID is `sellers.id`, not user ID; status `approved` is already recognized by Flutter |
| Shop images | Resolve `sellers.logo_path` / `banner_path` to authorized media URLs |
| Product | `products.id`, `seller_id`, `name`, `description`, `is_active`; category through `categories` |
| Variety, price, stock | `product_variants.name`, `price_minor`, `stock`, scoped by product; preserve all variants in API responses |
| Category hierarchy | `categories.parent_id`; separate `seller_store_categories` are seller-defined store groups, not marketplace subcategories |
| Product media | `product_images.path` / `position`; resolve URLs. Video support is not established by this table |
| Product archive | `products.publication_status` and `deleted_at` exist, but schema alone does not define archive/restore semantics. Confirm web model/controller behavior before mapping API `archived`; moderation state must remain independent |
| Buyer order | `orders`, including `reference`, `buyer_id`, `shipping_address`, payment fields and total |
| Seller order mutation ID | `seller_orders.id`; do not pass parent `orders.id` or `orders.reference` as the seller-order resource ID |
| Seller workflow `raw_status` | `seller_orders.status`: pending → accepted → packed → ready_to_ship → shipped → delivered → completed; cancelled is also supported |
| Seller order totals | `seller_orders.subtotal_minor`, `shipping_fee_minor`, `commission_minor` map directly to integer centavos; do not multiply by 100 |
| Order items | `order_items`, scoped by `seller_order_id`; preserve `product_name`, `variant_name`, `unit_price_minor`, quantity and recorded discounts |
| Payment display | `orders.payment_status` / `payment_method`; fulfillment must not mark payment paid |
| Cancellation reason | Candidate sources: `seller_orders.note` and cancellation event in `order_timelines.note` / `metadata`. Confirm actual web storage behavior, then expose API `cancellation_reason` consistently |
| Completion date | No `seller_orders.completed_at` column in the export. Resolve the relevant completed event from `order_timelines` if this is the web convention; do not use generic `updated_at` as proof of completion |
| Courier choices | `logistics_providers` where status is `approved`; API returns IDs, names, `approved: true` |
| Pickup / tracking | `seller_orders.logistics_provider_id`, `shipments.logistics_provider_id`, `tracking_code`, rider and shipment status. Booking and seller-order transitions require backend transactions |
| Earnings / payouts | Preserve recorded `commission_minor`; use wallet accounts/journals and payout tables for server-owned balances |
| Password recovery | `password_reset_tokens` exists. A token table does not establish a six-digit email code API or SMTP delivery |
| Email verification | `email_verification_codes` has user, code_hash, expires_at and used_at, but no purpose/session fields. Do not reuse registration codes as password-change authorization without backend purpose binding |
| Authentication transport | `sessions` and `auth_identities` exist, but the dump does not establish the app's bearer-token API. Adapt the web backend's supported authentication scheme explicitly |

## Known policy differences to resolve with the backend owner

- The imported `sellers.commission_bps` default is historical (800 basis points).
  New mobile purchases now enforce **80% seller / 10% admin / 10% app owner**.
  Apply `backend/migrations/20261105_wallet_split.sql` for the new allocation
  ledger. Recorded commissions and balances on existing orders remain intact.
  The separately supplied web backend must adopt the same policy before web
  purchases can use this split; the mobile change does not update that server.
- `shipping_fee_minor` defaults to zero; the app expects PHP 50 per shop order.
  Column defaults do not establish checkout policy. Verify web quote/order logic.
- Multiple variants per product are permitted by this schema. Flutter currently
  prevents editing legacy multi-variant products as one flattened price/stock.
  Do not delete variants or apply the older local one-variant schema constraint.
- Archive behavior, cancellation-note conventions, date timezone and email-code
  endpoints cannot be confirmed from table definitions alone.

## Local adapter and remaining web integration

The local adapter is now implemented in [`../../backend/`](../../backend/README.md)
and connected to the imported MySQL copy. Buyer/seller Google sign-in, workspaces,
core commerce and transaction-only integration tests use this schema. See the
backend README for supported routes, account behavior and service limitations.

The existing web backend project (routes, controllers/services, models and API
configuration), or its documented API and a development base URL, is needed to
make and verify these mappings. Use the supplied database with that backend;
do not invent working routes or embed a direct MySQL connection in Flutter.

See `../ACCOUNT_AND_FULFILLMENT.md` for the app's proposed account and seller
workflow API requirements. They remain proposed until checked against the web
backend; the SQL archive alone does not enable live email or cross-device sync.
