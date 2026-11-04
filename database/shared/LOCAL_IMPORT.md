# Current local connection — November 4, 2026

The user re-imported and selected **`lumear_ecommerce`** in the saved
**Local instance MySQL80** Workbench connection. The mobile adapter now uses
this database at `127.0.0.1:3306`, through ignored `backend/.env.mysql`.
The old database name below is historical and is no longer the app target.

Verification before the mobile profile migration:

- 63 base tables, matching the shared table names.
- 28 users, 9 sellers, 13 products, and 18 orders.
- The user approved SELECT/INSERT/UPDATE/DELETE access for the existing local
  `lumear_app_20261103`@`localhost` app account on `lumear_ecommerce` only.
- Applied `backend/migrations/20261103_user_profiles.sql` to add the missing
  mobile profile table; 64 tables afterward. Existing imported rows were retained.
- All 7 backend unit/integration checks passed; fixtures were rolled back.
- Running API `/api/health` reports MySQL, 64 tables, and database fingerprint
  `b660fe36bb3f` for `lumear_ecommerce`.
- No replacement import, seed accounts, password changes, or data deletion.

The historical SQL design at `database/lumear_ecommerce.sql` is unrelated to
this imported shared schema and must not be run against this database.

---

# Local shared database import

Imported on November 3, 2026 through the saved **Local instance MySQL80**
connection in MySQL Workbench, at the user's request.

- Host: `127.0.0.1` / `localhost`
- Port: `3306`
- Database: `lumear_shared_20261103_7c92`
- Source: `lumear-20261103T124903Z-1-001.zip`

A new database was created; existing databases were not replaced. The import
used the export's table definitions and row data, with no DROP statements.
All table definitions were created before loading the data. Foreign-key checks
were temporarily disabled in the import session for dependency ordering, then
restored, with the other changed session settings restored as well.

Verification results observed in Workbench:

| Check | Result |
| --- | --- |
| Expected / actual base tables | 63 / 63 |
| Expected / actual total rows | 973 / 973 |
| Tables with row-count mismatches | 0 |
| Foreign-key relationships checked | 89 |
| Orphan rows | 0 |

Refresh the Workbench Schemas panel to view the database and expand Tables.
The local import/verification scripts and source row-count manifest are in
`tmp/mysql-import/`, which is excluded from Git by its local `.gitignore`.
Those scripts contain exported account/session data and are not app assets.
Do not rerun the import into this already populated database.

This is a local copy of the export. It is not synchronized with the teammate's
server. The local adapter in `backend/` now connects Flutter to this copy using
a dedicated local account with read/write access only to this database.
The API runs on port 8000; Android uses USB forwarding for local testing.
Database credentials remain outside Flutter source.
