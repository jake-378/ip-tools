<?php
require __DIR__ . '/inc/init.php';

send_common_headers();
header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    respond(405, ['ok' => false, 'error' => 'Method not allowed.']);
}

$visitor = client_ip($config);
$limit = rate_limit('api|' . $visitor, $config['rate_limit']);
if (!$limit['allowed']) {
    header('Retry-After: ' . $limit['retry_after']);
    respond(429, ['ok' => false, 'error' => "Too many lookups. Try again in {$limit['retry_after']}s."]);
}

$action = (string) ($_GET['action'] ?? '');

switch ($action) {
    case 'rdns':
        $ip = ip_normalize(substr((string) ($_GET['ip'] ?? ''), 0, 64));
        if ($ip === '') {
            respond(400, ['ok' => false, 'error' => 'Enter an IP address.']);
        }
        if (!ip_is_valid($ip)) {
            respond(400, ['ok' => false, 'error' => 'That is not a valid IPv4 or IPv6 address.']);
        }
        if (!ip_is_public($ip)) {
            respond(400, ['ok' => false, 'error' => ip_scope($ip) . ' addresses have no public reverse DNS.']);
        }
        respond(200, ['ok' => true] + reverse_dns($ip));

    case 'port':
        $ip = ip_normalize(substr((string) ($_GET['ip'] ?? ''), 0, 64));
        $port = filter_var($_GET['port'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535]
        ]);
        if ($ip === '') {
            respond(400, ['ok' => false, 'error' => 'Enter an IP address.']);
        }
        if (!ip_is_valid($ip)) {
            respond(400, ['ok' => false, 'error' => 'That is not a valid IPv4 or IPv6 address.']);
        }
        if (!ip_is_public($ip)) {
            respond(400, ['ok' => false, 'error' => 'Only public IP addresses can be scanned.']);
        }
        if ($port === false) {
            respond(400, ['ok' => false, 'error' => 'Port must be a number between 1 and 65535.']);
        }

        $serviceNames = [
            20 => 'FTP-Data', 21 => 'FTP', 22 => 'SSH', 23 => 'Telnet', 25 => 'SMTP',
            53 => 'DNS', 80 => 'HTTP', 110 => 'POP3', 143 => 'IMAP', 443 => 'HTTPS',
            465 => 'SMTPS', 587 => 'Submission', 993 => 'IMAPS', 995 => 'POP3S',
            3306 => 'MySQL', 3389 => 'RDP', 5432 => 'PostgreSQL', 8080 => 'HTTP-Alt',
            8443 => 'HTTPS-Alt',
        ];

        $target = ip_version($ip) === 6 ? "tcp://[{$ip}]:{$port}" : "tcp://{$ip}:{$port}";
        $errno = 0;
        $errstr = '';
        $start = microtime(true);
        $ctx = stream_context_create();
        $fp = @stream_socket_client($target, $errno, $errstr, 1.4, STREAM_CLIENT_CONNECT, $ctx);
        $timeMs = round((microtime(true) - $start) * 1000, 1);

        $isOpen = is_resource($fp);
        if ($isOpen) {
            fclose($fp);
        }

        respond(200, [
            'ok'      => true,
            'ip'      => $ip,
            'port'    => $port,
            'service' => $serviceNames[$port] ?? null,
            'open'    => $isOpen,
            'time_ms' => $timeMs,
        ]);

    case 'ssl':
        $target = substr((string) ($_GET['target'] ?? ''), 0, 128);
        try {
            $data = inspect_ssl_certificate($target);
            respond(200, ['ok' => true] + $data);
        } catch (InvalidArgumentException $e) {
            respond(400, ['ok' => false, 'error' => $e->getMessage()]);
        } catch (Throwable $e) {
            respond(502, ['ok' => false, 'error' => $e->getMessage()]);
        }

    case 'mac':
        $mac = substr((string) ($_GET['mac'] ?? ''), 0, 64);
        try {
            $data = lookup_mac_vendor($mac);
            respond(200, ['ok' => true] + $data);
        } catch (InvalidArgumentException $e) {
            respond(400, ['ok' => false, 'error' => $e->getMessage()]);
        } catch (Throwable $e) {
            respond(500, ['ok' => false, 'error' => 'MAC lookup failed.']);
        }

    default:
        respond(404, ['ok' => false, 'error' => 'Unknown action.']);
}
