# Moleqra

Moleqra is a static-first PHP website for conventional shared hosting. It uses PHP, HTML, CSS, vanilla JavaScript and optional MySQL. There is **no Node.js runtime, build command, daemon, queue worker or service to start/restart**.

## Hosting requirements

- PHP 8.1+ recommended
- PDO MySQL extension for admin/database features
- MySQL 5.7+/MariaDB 10.4+ recommended
- Apache `.htaccess` support recommended
- PHP file uploads enabled if COA PDFs will be managed through the admin area
- Outbound HTTPS support through PHP cURL or `allow_url_fopen` for PayFast ITN server validation

## Initial deployment

1. Upload the repository contents to the hosting document root.
2. Ensure `storage/` and `storage/coa/` are writable by PHP.
3. The public site works immediately without MySQL. Enquiries fall back to `storage/enquiries.jsonl`.
4. To enable the admin/database layer, create a MySQL database and user.
5. Import `database/schema.sql`.
6. Copy `config/database.example.php` to `config/database.php` and enter the production credentials.
7. Visit `/admin/setup.php` once and create the first administrator account.
8. Sign in at `/admin/login.php`.

`config/database.php`, `config/payment.php`, enquiry fallback data and uploaded COA PDFs are intentionally excluded from Git.

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

## V4 procurement and launch controls

V4 separates supplier sourcing, landed-cost planning and public catalogue governance.

For an existing V3 database, sign in and open `/admin/system.php`, then run **V4 procurement & launch controls**. Alternatively import `database/migrations-003-procurement-launch.sql` after the supplier-operations migration. After the migration exists, current `is_public=1` products are filtered from the public catalogue until their configured launch gate passes.

New capabilities:

- Procurement quote scenarios linked to supplier-product offers
- Quantity, quote currency and manual FX-to-base-currency tracking
- Shipping, duty/tax estimate, independent testing, packaging/label, payment-fee and miscellaneous cost inputs
- Calculated landed total and landed unit cost in the configured base currency
- Product-by-market review records with an explicit listing hold
- Sourcing decisions that select a supplier offer without automatically publishing a product
- Publication gate requiring the configured market review, approved sourcing decision, qualified supplier status, core supplier evidence controls and a public batch COA
- Read-time catalogue gate as a second safeguard against direct database changes bypassing the admin workflow

The publication gate is an internal process control only. A `PASS` state is **not** a representation of regulatory approval, legal compliance, safety, suitability for use, or permission to market a material. External legal/regulatory review remains a separate business responsibility.

The default primary market is `ZA` and the default base currency is `ZAR`. Both can be changed in `config/app.php`.


## V5 inventory and batch operations

V5 adds internal stock traceability without adding public checkout or human-use functionality. For an existing V4 database, open `/admin/system.php` and run **V5 inventory & batch control**, or import `database/migrations-004-inventory.sql` after the earlier migrations.

Capabilities include:

- Purchase orders linked to qualified/prospective suppliers
- PO line items with ordered and received quantities
- Receipt of each physical lot into `Quarantine`
- Product, supplier, PO-line, batch/lot and optional COA traceability
- Expiry/retest dates and storage-location records
- Internal receipt/release checklist with exact batch-number-to-COA matching
- Disposition states: `Quarantine`, `Released`, `Hold`, `Rejected`, `Depleted`
- Stock movement ledger for adjustments, samples, write-offs, returns and corrections
- Prevention of negative on-hand quantities
- Automatic PO progress updates as lots are received
- Dashboard counts for open purchase orders and quarantined lots

The inventory `Released` state is an **internal operational disposition only**. It is not regulatory approval, a safety determination, clinical authorization, or permission for human use.


## V6 commerce, customer accounts and PayFast checkout

V6 adds the first complete transactional storefront layer while preserving the same request-driven PHP/MySQL hosting model. For an existing V5 database, open `/admin/system.php` and run **V6 commerce & checkout**, or import `database/migrations-005-commerce.sql` after the earlier migrations. Fresh installs can import `database/schema.sql` and then apply `database/migrations-005-commerce.sql` for the commerce tables.

Capabilities include:

- Public product detail pages and server-side shopping cart
- Optional customer registration/login plus guest checkout
- Product-specific retail price, tax and order-quantity controls
- Online availability calculated only from V5 inventory in `Released` disposition
- Time-limited stock reservations at checkout with lazy expiry (no background worker required)
- Sales orders with customer/delivery snapshots and research-use acknowledgement
- Hosted PayFast custom integration with sandbox/live modes
- PayFast signature generation, merchant/amount checks, source validation, server confirmation and idempotent payment transaction logging
- Customer order-status links and account order history
- Admin order queue with exact inventory-batch reservations
- One-click fulfilment that consumes the reserved lots and writes inventory `Sale` movements
- Courier/service/tracking shipment records

### PayFast configuration

1. Copy `config/payment.example.php` to `config/payment.php`.
2. Enter the sandbox Merchant ID, Merchant Key and passphrase from your PayFast Sandbox account.
3. Set `config/app.php` `base_url` to the publicly reachable HTTPS URL used during testing. PayFast must be able to reach `/payment/payfast-itn.php`.
4. Keep `sandbox => true` until the full payment and ITN flow has been tested.
5. In admin, open **Commerce** to confirm payment configuration status.
6. Only switch to live credentials and `sandbox => false` after the PayFast merchant account is approved and production launch checks are complete.

The gateway integration uses PayFast hosted checkout. Payment-card and online-banking credentials are not collected or stored by Moleqra. The site only stores transaction references/statuses and the security-check results needed to reconcile an order.

### Cart and stock behaviour

Cart visibility requires all of the following:

- the V4 publication gate passes;
- the product is public;
- V6 commerce is enabled for that product with a price; and
- V5 has released, non-expired, non-retest-due inventory available after active reservations are deducted.

Submitting checkout creates an order and reserves specific inventory batches for the configured reservation window. A validated successful PayFast ITN changes those reservations from `Active` to `Confirmed`. Admin fulfilment then consumes those exact batches and records the stock movements. Expired unpaid reservations are released lazily on later commerce requests, which avoids any requirement for cron or a long-running worker.

The checkout remains explicitly research-use-only. It does not provide dosing, administration, treatment or therapeutic guidance. The working Terms and Privacy pages should receive final South African legal/POPIA review before public launch.
