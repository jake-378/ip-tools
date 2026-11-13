<?php
defined('IPTOOLS') || exit;

/**
 * Fixed-window limiter backed by small files. Fails open if the directory
 * isn't writable so a hosting quirk never takes the tools down.
 *
 * @return array{allowed: bool, retry_after: int}
 */
function rate_limit(string $key, array $cfg): array
{
    $max    = (int) ($cfg['max_requests'] ?? 30);
    $window = (int) ($cfg['window_secs'] ?? 60);
    $dir    = (string) ($cfg['dir'] ?? sys_get_temp_dir());

    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        return ['allowed' => true, 'retry_after' => 0];
    }

    $fh = @fopen($dir . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json', 'c+');
    if (!$fh) {
        return ['allowed' => true, 'retry_after' => 0];
    }

    flock($fh, LOCK_EX);
    $now   = time();
    $state = json_decode((string) stream_get_contents($fh), true) ?: [];
    if (($state['start'] ?? 0) + $window <= $now) {
        $state = ['start' => $now, 'count' => 0];
    }
    $state['count']++;

    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($state));
    flock($fh, LOCK_UN);
    fclose($fh);

    $allowed = $state['count'] <= $max;
    return [
        'allowed'     => $allowed,
        'retry_after' => $allowed ? 0 : max(1, $state['start'] + $window - $now),
    ];
}
