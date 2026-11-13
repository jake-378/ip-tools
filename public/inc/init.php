<?php
define('IPTOOLS', true);

$config = require __DIR__ . '/config.php';
require __DIR__ . '/ip.php';
require __DIR__ . '/ua.php';
require __DIR__ . '/ratelimit.php';
require __DIR__ . '/ssl.php';
require __DIR__ . '/mac.php';

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function send_common_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header('Cache-Control: no-store, max-age=0');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; "
        . "script-src 'self'; connect-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
}

function asset(string $path): string
{
    $file = __DIR__ . '/../' . $path;
    return $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}
