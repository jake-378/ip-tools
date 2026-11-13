<?php
defined('IPTOOLS') || exit;

return [
    'site_name' => 'IP Tools',

    // Proxies allowed to tell us the real visitor IP via X-Real-IP / X-Forwarded-For.
    // Add only proxies that should be trusted to supply forwarding headers. CIDR ranges are allowed.
    'trusted_proxies' => [
        '127.0.0.1',
        '::1',
    ],

    // Per-visitor limit on API lookups.
    'rate_limit' => [
        'max_requests' => 30,
        'window_secs'  => 60,
        'dir'          => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'iptools_rl',
    ],
];
