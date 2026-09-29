<?php

declare(strict_types=1);

return [
    // Generate a long random secret before enabling web-triggered automation.
    'outreach_key' => '',
    'batch_limit' => 5,
    // Newsletter uses a separate key so supplier outreach and customer marketing can be scheduled independently.
    'newsletter_key' => '',
    'newsletter_batch_limit' => 50,
];
