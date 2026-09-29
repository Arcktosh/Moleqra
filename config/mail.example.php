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
        'port' => 587,

        // tls = STARTTLS (normally port 587)
        // ssl = implicit TLS / SMTPS (normally port 465)
        // none = unencrypted SMTP; use only when your hosting provider explicitly requires it
        'encryption' => 'tls',

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
];
