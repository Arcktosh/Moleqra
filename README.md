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

`config/database.php`, `config/payment.php`, `config/mail.php`, `config/courier.php`, enquiry fallback data and uploaded COA PDFs are intentionally excluded from Git.

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

## V7 commercial operations

V7 adds the commercial operations layer around the V6 cart and payment flow. For an existing V6 database, open `/admin/system.php` and run **V7 commercial operations**, or import `database/migrations-006-commercial-operations.sql` after the V6 migration.

Capabilities include:

- Saved customer delivery addresses with a default-address workflow
- Destination/order-value shipping rules managed from admin
- Immutable invoice records issued after confirmed payment
- Customer-facing printable invoices plus lightweight PDF downloads
- Transactional notification logging for order, payment, dispatch and refund events
- Optional synchronous PHP `mail()` delivery without any queue worker requirement
- Payment reconciliation records separate from gateway audit records
- Full/partial refund request tracking with refund receipts and gateway reference capture
- Customer account administration and account enable/disable controls
- Unpaid-order filtering and manual cleanup of expired stock reservations
- Admin visibility of order emails, invoices, refunds, payment validation and shipping-rule selection
- Manual courier fulfilment retained behind a courier provider configuration boundary

### Email configuration

1. Copy `config/mail.example.php` to `config/mail.php`.
2. Leave `enabled => false` or `transport => 'log'` during development. Notifications are still recorded in the database.
3. When the host's outbound PHP mail is verified, set `enabled => true`, `transport => 'mail'`, and configure a valid From address.
4. Notification delivery is synchronous by design so no worker or cron process is required.

### Shipping configuration

The V7 migration creates a default South Africa shipping rule equivalent to the previous R120 flat rate with free shipping from R3,500. Admin can create more specific province-level rules; exact province matches take precedence over the all-provinces fallback.

`config/courier.example.php` provides a courier-provider boundary. Manual dispatch/tracking remains the production-safe default. A ShipLogic configuration slot is included for later account-specific API integration; do not enable an undocumented endpoint contract.

### Refund and reconciliation workflow

Moleqra does not automatically initiate PayFast merchant refunds. Admin records a full/partial refund request, processes the actual refund through the payment provider's approved merchant workflow, then marks the record processed with the gateway reference. The order payment state, stock reservation state, customer refund receipt and notification log are updated from that internal record.

Payment reconciliation is separate: each gateway notification can be marked `Matched`, `Reviewed` or `Exception` with finance notes. This keeps payment security validation, accounting review and refunds as distinct audit trails.

## V8 storefront and launch hardening

V8 adds the storefront presentation and pre-launch hardening layer without changing the V5 batch-ledger model. For an existing V7 database, open `/admin/system.php` and run **V8 storefront upgrade**, or import `database/migrations-007-storefront-hardening.sql` after the V7 migration.

Capabilities include:

- Sellable pack-size variants with their own SKU, label, price, tax, min/max order controls and sort order
- Variant purchases reserve and consume the correct number of underlying released inventory units rather than creating a second stock ledger
- Storefront-specific product slugs, short descriptions, search keywords, meta titles/descriptions, low-stock messaging and related materials
- Public catalogue search, category filtering and price/name sorting
- Product canonical URLs and Product/Offer structured data when a public HTTPS `base_url` is configured
- Dynamic `sitemap.xml` and `robots.txt` routes through Apache rewrite rules
- Saved public product URLs using slugs while retaining ID lookup compatibility
- Password-reset and email-verification tokens stored only as SHA-256 hashes, with expiry and single-use handling
- Generic password-reset request responses to reduce account enumeration
- Optional verified-email enforcement through `config/commerce.php`
- Admin audit logging for high-impact commerce, customer, storefront and system actions
- Authenticated CSV exports for orders, customers, products and inventory, with spreadsheet-formula escaping
- Pre-launch diagnostics for HTTPS, database/schema state, payment configuration, mail dependencies, catalogue/COA/variant readiness and SEO slugs
- Public stock messaging that avoids exposing exact inventory quantities

### Account recovery and verification

Password reset and verification links require the final public HTTPS `base_url` so generated links resolve correctly. Keep `require_verified_email => false` until outbound mail has been configured and tested. When verification is enforced, V8 diagnostics treats missing HTTPS/mail dependencies as a launch failure rather than allowing an account-flow deadlock.

The default migration marks existing customer accounts as already verified so the V8 upgrade does not unexpectedly lock out existing users. New registrations receive an unverified security record and can be verified using the email-token flow.

### SEO routes

With Apache `mod_rewrite` enabled, root `.htaccess` maps:

- `/sitemap.xml` to `sitemap.php`
- `/robots.txt` to `robots.php`

Set `config/app.php` `base_url` to the final HTTPS origin before indexing. Account, payment and token-bearing order/invoice/refund routes are marked `noindex,nofollow`. Product search result pages are also noindexed to avoid indexing arbitrary internal search combinations.

### Storefront administration

Use **Admin → Storefront** for presentation-only and sellable-variant configuration. Keep the core **Products** area as the research-material master record and V6 **Commerce** settings as the fallback/base offer. This separation avoids mixing procurement/inventory data with merchandising metadata.

### Fresh database sequence

For a new database apply, in order:

1. `database/schema.sql`
2. `database/migrations-002-supplier-operations.sql`
3. `database/migrations-003-procurement-launch.sql`
4. `database/migrations-004-inventory.sql`
5. `database/migrations-005-commerce.sql`
6. `database/migrations-006-commercial-operations.sql`
7. `database/migrations-007-storefront-hardening.sql`

The entire V8 runtime remains ordinary PHP requests plus MySQL. There is no Node runtime, Composer runtime dependency, daemon, queue worker, process manager or required service restart.
