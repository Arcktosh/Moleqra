<?php
$pageTitle = 'Privacy | Moleqra';
$pageDescription = 'Moleqra website and commerce privacy notice.';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><div class="container"><div class="eyebrow">Privacy</div><h1>Privacy notice.</h1><p>This working notice describes the information the current site architecture can process. It should receive final POPIA/legal review before public launch.</p></div></section>
<section class="section"><div class="container grid-2">
<div class="card"><h2>Enquiries and accounts</h2><p>Moleqra may process names, email addresses, phone numbers, organisation details, account credentials stored as password hashes, and correspondence submitted through the site.</p></div>
<div class="card"><h2>Orders and delivery</h2><p>Checkout can process contact and delivery information, saved addresses, order contents, research-use acknowledgements, shipping-rule results, courier/tracking records, transaction references, fulfilment records and customer notes required to administer an order.</p></div>
<div class="card"><h2>Payments</h2><p>The Moleqra checkout does not collect payment-card or online-banking credentials. Hosted payment processing is performed by the configured payment provider. Moleqra stores gateway references, status information and payment audit results needed for reconciliation and order administration.</p></div>
<div class="card"><h2>Security and abuse prevention</h2><p>Sessions, CSRF tokens, password hashing, restricted admin access and payment-notification validation are used to protect the site. Limited technical information may be processed for security, diagnostics and abuse prevention.</p></div>
<div class="card"><h2>Notifications and service providers</h2><p>Moleqra may use configured email and delivery service providers to send order, payment, refund and dispatch information or to arrange and track delivery. Only information reasonably required for those services should be shared.</p></div><div class="card"><h2>Storage and access</h2><p>Application data is stored in the configured hosting database and protected file areas. Access should be limited to authorised personnel, and production backup, retention and deletion procedures should be documented before launch.</p></div>
<div class="card"><h2>Privacy requests</h2><p>Privacy requests can be submitted through the contact route until a dedicated privacy contact is configured. Identity verification may be required before account or order information is disclosed or changed.</p></div>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
