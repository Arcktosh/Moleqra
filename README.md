# Moleqra

Moleqra is a static-first PHP website for conventional shared hosting. It uses PHP, HTML, CSS, vanilla JavaScript and optional MySQL. There is **no Node.js runtime, build command, daemon, queue worker or service to start/restart**.

## Hosting requirements

- PHP 8.1+ recommended
- PDO MySQL extension for admin/database features
- MySQL 5.7+/MariaDB 10.4+ recommended
- Apache `.htaccess` support recommended
- PHP file uploads enabled if COA PDFs will be managed through the admin area

## Initial deployment

1. Upload the repository contents to the hosting document root.
2. Ensure `storage/` and `storage/coa/` are writable by PHP.
3. The public site works immediately without MySQL. Enquiries fall back to `storage/enquiries.jsonl`.
4. To enable the admin/database layer, create a MySQL database and user.
5. Import `database/schema.sql`.
6. Copy `config/database.example.php` to `config/database.php` and enter the production credentials.
7. Visit `/admin/setup.php` once and create the first administrator account.
8. Sign in at `/admin/login.php`.

`config/database.php`, enquiry fallback data and uploaded COA PDFs are intentionally excluded from Git.

## Admin capabilities

- Product catalogue management with draft/public states
- Supplier prospect and qualification tracking
- Batch-specific COA PDF upload
- SHA-256 digest recording for uploaded COAs
- Public/private COA publication controls
- Enquiry inbox with workflow status and internal notes
- Dashboard counts

Public catalogue and COA pages read only records marked public. Private COAs can be viewed only by an authenticated administrator.

## Security notes

- Admin passwords use PHP `password_hash()` / `password_verify()`.
- Admin state uses PHP sessions and CSRF tokens.
- The initial setup page locks itself as soon as an administrator exists.
- `config/` and `storage/` contain `.htaccess` deny rules.
- COA files are stored below `storage/` and served through `coa-download.php`, rather than linked directly.
- Uploaded COAs are limited to 10 MB and validated as PDFs before storage.
- Public contact forms use CSRF protection, a honeypot and basic session throttling.

For production, HTTPS should be mandatory and the hosting control panel should use a supported PHP release.

## Repository layout

- `/admin` — request-driven PHP admin interface
- `/assets` — CSS and browser JavaScript
- `/config` — application/database configuration
- `/database` — MySQL schema
- `/includes` — shared PHP bootstrap, database, auth and layout helpers
- `/storage` — protected runtime data and COA files

## Commerce scope

The current build is intentionally an informational and supplier-onboarding site. It does not implement checkout, payments, dosing guidance or human-use instructions.

## Supplier operations layer

The supplier operations layer adds internal sourcing tools without exposing supplier pricing or evaluation data on the public site.

For an existing V2 database, sign in and open `/admin/system.php`, then run **Supplier operations upgrade**. Alternatively import `database/migrations-002-supplier-operations.sql` in the hosting database tool.

Capabilities include:

- Evidence-based supplier qualification checklist with 11 explicit controls
- Supplier-product relationships and commercial terms
- Wholesale price, currency, MOQ and lead-time tracking
- COA/private-label/dropship capability flags
- Outreach history, outcomes and follow-up dates
- Test-order tracking for packaging and document/batch checks
- Sourcing dashboard with due follow-ups and commercial offer matrix
- Optional non-destructive seed file for the initial SA and US supplier prospect list

The readiness percentage shown in admin is only checklist completion. It does not certify a supplier or replace independent verification.
