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
3. For production, native SMTP is recommended: set `enabled => true`, `transport => 'smtp'`, and configure the hosting mailbox SMTP host, port, encryption, username, password, From address and Reply-To address.
4. STARTTLS on port 587 is configured with `encryption => 'tls'`; implicit TLS/SMTPS on port 465 uses `encryption => 'ssl'`.
5. Keep TLS certificate verification enabled unless the hosting provider explicitly documents another requirement.
6. Open **Admin → Diagnostics** and run **Test SMTP connection** first, then **Send test email**.
7. The legacy `transport => 'mail'` option remains available for hosts with a working PHP `mail()` setup.
8. Notification delivery is synchronous by design so no queue worker is required.

SMTP is implemented natively with PHP stream sockets and does not require Composer or PHPMailer. The same configured transport is used for transactional order/customer email, account verification/password reset, supplier outreach, newsletter confirmations and newsletter campaigns. A successful SMTP test confirms connection/TLS/authentication; final inbox delivery still depends on the mail host and sender-domain SPF/DKIM/DMARC configuration.

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

## V9 automation, branding and SEO controls

V9 adds supplier prospect-email automation, customer order-status feedback, database-backed branding, and site-wide SEO/robots controls. For an existing V8 database, open `/admin/system.php` and run **V9 automation & branding upgrade**, or import `database/migrations-008-automation-branding-seo.sql` after migration 007.

Capabilities include:

- Admin-managed supplier outreach email templates and campaigns
- Prospect queues filtered by supplier status/region, valid email, and outreach opt-in
- Per-campaign daily limits (hard-capped at 50/day), minimum contact spacing, scheduled starts and follow-up dates
- Supplier-level outreach suppression / opt-out controls
- Manual **Send next batch** control plus optional cron/HTTPS automation using `automation/outreach.php`
- Automatic supplier outreach entries written back to the existing supplier timeline
- Customer order-status event history and branded status emails for order creation, payment confirmation/failure/cancellation, stock-review holds, reservation expiry, dispatch, delivery and processed refunds
- Customer-facing order timeline on secure order-status pages
- Admin-configurable site logo, site name and colour palette without editing CSS/PHP
- The same logo/brand palette used in public navigation, admin navigation and transactional email layout
- Admin-managed SEO defaults, organisation name, Open Graph image, global indexing switch and extra robots exclusions
- Dynamic `robots.txt` that blocks all crawling while indexing is disabled
- Dynamic sitemap publishing only when indexing, sitemap generation and the HTTPS base URL are enabled

### Prospect outreach automation

1. Configure and test outbound email using `config/mail.php` first. Automated prospect sending refuses to run unless `enabled => true` and `transport => 'mail'`.
2. Open **Admin → Outreach** and review/edit the seeded **Formal first contact** template.
3. Create a campaign, select the target supplier status/region, daily limit, minimum contact spacing and optional start time.
4. Creating a campaign snapshots the current subject/body for each eligible supplier into the campaign queue.
5. Set the campaign to **Running**.
6. Either use **Send next batch** in admin or schedule the PHP automation endpoint.

For CLI scheduling:

```bash
php /absolute/path/to/automation/outreach.php
```

For HTTPS scheduling, copy `config/automation.example.php` to `config/automation.php`, generate a long random `outreach_key`, and call `/automation/outreach.php` with:

```text
Authorization: Bearer <outreach_key>
```

No campaign sends while it is Draft or Paused. A supplier with automated outreach disabled is skipped even if they were queued earlier. This is an optional scheduled task; the storefront, cart, checkout, PayFast ITN and order fulfilment flows do not depend on cron.

### Branding

Use **Admin → Brand & SEO** to configure:

- site name;
- uploaded PNG/JPEG/GIF/WebP logo;
- primary and secondary accent colours;
- background and surface colours;
- primary and muted text colours.

V9 automatically uses the latest repository logo asset when it is present; the release ZIP also includes a packaged Moleqra logo fallback under `assets/branding/moleqra-logo.png`. Uploaded logos are written to `assets/branding/`, which blocks PHP-like executable extensions and directory indexing through its `.htaccess` file.

### SEO and robots

The same **Brand & SEO** screen controls the default title suffix, meta description, organisation name, Open Graph image, sitemap availability, global indexing permission, and additional `Disallow` paths.

Indexing defaults to **off**. While disabled, `/robots.txt` returns `Disallow: /` and public pages receive a `noindex,nofollow` meta directive. This is intentional for development and staging. Enable indexing only after the final HTTPS `base_url`, catalogue, legal text, COAs and product metadata have been reviewed.

### Customer order feedback

V9 extends the existing transactional email layer rather than adding another mail system. Important state changes are recorded in `order_status_events`, displayed on the secure customer order-status page, and passed through the existing `mailer_send()` audit/delivery path. Delivery remains synchronous through the configured PHP mail transport and therefore requires no persistent queue worker.

### Fresh database sequence

For a new database apply, in order:

1. `database/schema.sql`
2. `database/migrations-002-supplier-operations.sql`
3. `database/migrations-003-procurement-launch.sql`
4. `database/migrations-004-inventory.sql`
5. `database/migrations-005-commerce.sql`
6. `database/migrations-006-commercial-operations.sql`
7. `database/migrations-007-storefront-hardening.sql`
8. `database/migrations-008-automation-branding-seo.sql`

V9 remains compatible with ordinary shared-hosting PHP + MySQL. There is no Node runtime, npm build, persistent daemon, Redis instance, queue worker or service restart requirement.

## V10 newsletter and research community

V10 adds an optional customer-retention and moderated community layer without changing the existing commerce, inventory or payment model. Existing V9.2 installations can run **Admin → System → V10 newsletter & research community**, or import `database/migrations-010-newsletter-community.sql` after migration 009.

Capabilities include:

- website newsletter subscription form available from the public footer;
- confirmation-based newsletter subscriptions when outbound mail and the public base URL are configured;
- one-click unsubscribe links in newsletter campaigns;
- separate newsletter campaigns with subject/body editing, live preview, scheduling and batch sending;
- optional CLI/HTTPS processing through `automation/newsletter.php`;
- public research-community index and topic pages;
- automatic discussion topic for each public product;
- logged-in customers can create general topics and reply to open topics;
- public forum identities use generated member labels rather than exposing customer email/phone data;
- server-side rejection of external URLs/domains, email addresses, phone/contact details and off-platform contact requests;
- explicit rejection of dosing, administration and human-use instructions;
- posting rate limits plus spam scoring and automatic forum suspension after repeated rejected submissions;
- admin controls to hide/restore posts, open/close/hide topics, and suspend/block/unblock forum access without disabling the customer's commerce account;
- community pages default to `noindex` while the feature matures.

### Newsletter automation

Configure outbound mail first. Newsletter automation refuses to send unless mail delivery and the public `base_url` are configured.

CLI scheduling:

```bash
php /absolute/path/to/automation/newsletter.php
```

For HTTPS scheduling, set a separate `newsletter_key` in `config/automation.php` and call `/automation/newsletter.php` with:

```text
Authorization: Bearer <newsletter_key>
```

Only newsletter campaigns in `Running` state are processed. Subscriber re-activation is not available as an admin shortcut; a previously unsubscribed recipient must use the public subscription/confirmation flow again.

### Community scope

The forum is intended for laboratory, analytical, documentation and research discussion. It is not a channel for medical advice, treatment claims, personal-use reports, dosing, administration guidance, sales outside Moleqra, or exchange of private contact details. Moderation controls are intentionally independent from customer ordering/account status.

### Fresh database sequence

For a new database, apply the existing migrations in order through:

9. `database/migrations-009-campaign-preview-editing.sql`
10. `database/migrations-010-newsletter-community.sql`

V10 remains compatible with ordinary shared-hosting PHP + MySQL. No Node runtime, persistent worker, Redis service or daemon is required; scheduled newsletter delivery is optional and may use cron or an authenticated HTTPS call.

## V11 communications center

V11 unifies customer, supplier and website-enquiry communication in the back office. Existing V10 installations can run **Admin → System → V11 communications center**, or import `database/migrations-011-communications-center.sql` after migration 010.

Capabilities include:

- website contact-form submissions automatically become communication threads when V11 is installed;
- staff can read the original enquiry and reply by email directly from the back office;
- registered customer records link to their recent communication history and a pre-filled compose screen;
- supplier records link to supplier communication history and direct compose;
- automated supplier outreach is mirrored into supplier communication history;
- transactional customer emails sent through the existing mailer are mirrored into customer history when a customer account is known;
- each thread stores inbound/outbound direction, channel, subject, full message content, transport state, timestamps and the administrator responsible for manual sends;
- staff can manually capture inbound phone, WhatsApp, email or other offline communication notes;
- optional native POP3 mailbox synchronization imports incoming business-mailbox messages and associates them with the best matching open customer/supplier/contact thread;
- incoming POP3 bodies are stored/displayed as plain text; raw inbound HTML is not rendered in admin;
- mailbox messages are deduplicated using POP3 UIDL values;
- communication threads can be Open, Waiting or Closed.

### Mail configuration for the current shared host

The current hosting profile uses:

- outbound SMTP: port **25**;
- inbound POP3: port **110**;
- no IMAP dependency.

A matching `config/mail.php` configuration is:

```php
return [
    'enabled' => true,
    'transport' => 'smtp',

    'from_email' => 'support@example.com',
    'from_name' => 'Moleqra',
    'reply_to' => 'support@example.com',

    'smtp' => [
        'host' => 'mail.example.com',
        'port' => 25,
        'encryption' => 'none',
        'auth' => true,
        'auth_mode' => 'auto',
        'username' => 'support@example.com',
        'password' => 'mailbox-password',
        'timeout' => 15,
        'verify_peer' => true,
        'verify_peer_name' => true,
        'allow_self_signed' => false,
        'helo_name' => '',
    ],

    'inbound' => [
        'enabled' => true,
        'protocol' => 'pop3',
        'host' => 'mail.example.com',
        'port' => 110,
        'encryption' => 'none',
        'username' => 'support@example.com',
        'password' => 'mailbox-password',
        'timeout' => 15,
        'verify_peer' => true,
        'verify_peer_name' => true,
        'allow_self_signed' => false,
        'max_messages' => 50,
    ],
];
```

If the hosting provider requires STARTTLS on either port, change that connection's `encryption` value from `none` to `tls`. Use `ssl` only for an implicit TLS port supplied by the hosting provider.

POP3 is implemented directly over PHP stream sockets, so the PHP IMAP extension is not required.

Use **Admin → Diagnostics → Test SMTP connection** for outbound delivery and **Test POP3 connection** for inbound mailbox access. **Admin → Communications → Sync mailbox** performs an immediate POP3 import.

Optional CLI scheduling:

```bash
php /absolute/path/to/automation/mailbox.php
```

For authenticated HTTPS scheduling, set `mailbox_key` in `config/automation.php` and call `/automation/mailbox.php` with:

```text
Authorization: Bearer <mailbox_key>
```

POP3 retrieval is non-destructive: Moleqra uses `RETR` and does not issue `DELE`, so synchronization does not remove mail from the server. No persistent mail listener or queue worker is required.

### Fresh database sequence

For a new database continue the existing migration chain with:

11. `database/migrations-011-communications-center.sql`

