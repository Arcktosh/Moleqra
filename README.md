# Moleqra

Launch-stage website for Moleqra, built for conventional shared hosting with PHP, HTML, CSS and vanilla JavaScript. No Node.js runtime, build step, daemon, worker or process manager is required.

## Requirements

- PHP 8.1+ recommended
- Apache or compatible PHP hosting
- Writable `storage/` directory for the enquiry form
- MySQL is optional for a later migration; `database/schema.sql` is included

## Deploy

1. Upload the repository contents to the domain's document root.
2. Ensure `storage/` is writable by PHP but not directly web-accessible.
3. Confirm the host honours the included `.htaccess` rules for `storage/` and `config/`.
4. Update `config/app.php` with the final company details and contact address when available.
5. Open `index.php` in the browser.
6. Submit a test enquiry and verify a line is appended to `storage/enquiries.jsonl`.

There is no service to start or restart. Each request is handled by PHP through the web server.

## Structure

- `index.php` — homepage
- `catalog.php` — launch catalogue preview
- `quality.php` — supplier/batch quality framework
- `coa.php` — future public COA library
- `suppliers.php` — supplier partnership requirements
- `contact.php` — enquiry form with CSRF, honeypot and basic rate limiting
- `about.php`, `terms.php`, `privacy.php` — company and policy pages
- `includes/` — shared PHP layout/bootstrap
- `assets/` — CSS and vanilla JS
- `storage/` — enquiry records (gitignored)
- `database/` — optional MySQL schema

## Production notes

The initial enquiry form writes JSON Lines to `storage/enquiries.jsonl` so the site is functional before database credentials or mail delivery are configured. Before public launch, migrate enquiries to MySQL or an approved mail/CRM workflow if preferred, and confirm hosting permissions and backups.

The product catalogue is deliberately marked as pre-launch. Do not change product status to available until supplier qualification and applicable legal/compliance review are complete.
