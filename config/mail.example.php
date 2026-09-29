<?php

declare(strict_types=1);

return [
    'enabled' => false,

    // log | mail | smtp
    // - log: record notifications only; do not deliver
    // - mail: use the hosting server's PHP mail() configuration
    // - smtp: connect directly to the configured SMTP server
    'transport' => 'smtp',

    'from_email' => 'noreply@example.com',
    'from_name' => 'Moleqra',
    'reply_to' => 'support@example.com',

    'smtp' => [
        'host' => 'mail.example.com',
        'port' => 25,

        // none = plain SMTP (commonly port 25 on shared hosting)
        // tls = STARTTLS
        // ssl = implicit TLS / SMTPS
        'encryption' => 'none',

        'auth' => true,

        // auto | login | plain
        'auth_mode' => 'auto',

        'username' => 'noreply@example.com',
        'password' => 'replace-with-hosting-mailbox-password',

        'timeout' => 15,

        // Keep certificate verification enabled in production.
        'verify_peer' => true,
        'verify_peer_name' => true,
        'allow_self_signed' => false,

        // Optional. Leave blank to use the hosting machine hostname.
        'helo_name' => '',
    ],

    // Optional inbound mailbox capture for the Communications back office.
    // Native POP3 client; no PHP IMAP extension is required.
    'inbound' => [
        'enabled' => false,
        'protocol' => 'pop3',
        'host' => 'mail.example.com',
        'port' => 110,

        // none | tls | ssl
        // Port 110 commonly uses none or STLS; port 995 commonly uses ssl.
        'encryption' => 'none',

        'username' => 'support@example.com',
        'password' => 'replace-with-hosting-mailbox-password',

        'timeout' => 15,
        'verify_peer' => true,
        'verify_peer_name' => true,
        'allow_self_signed' => false,

        // Maximum recent messages inspected during each sync.
        'max_messages' => 50,
    ],
];
