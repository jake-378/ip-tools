<?php
defined('IPTOOLS') || exit;

function ip_normalize(string $ip): string
{
    $ip = trim($ip, " \t\n\r\0\x0B[]");
    if (($pct = strpos($ip, '%')) !== false) {
        $ip = substr($ip, 0, $pct);
    }
    if (stripos($ip, '::ffff:') === 0) {
        $v4 = substr($ip, 7);
        if (filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $v4;
        }
    }
    return $ip;
}

function ip_is_valid(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

function ip_version(string $ip): int
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return 4;
    }
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 6 : 0;
}

function ip_in_cidr(string $ip, string $cidr): bool
{
    [$net, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
    $ipBin  = @inet_pton($ip);
    $netBin = @inet_pton($net);
    if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
        return false;
    }
    $bits = $bits === null ? strlen($ipBin) * 8 : (int) $bits;
    $bytes = intdiv($bits, 8);
    if (strncmp($ipBin, $netBin, $bytes) !== 0) {
        return false;
    }
    $rem = $bits % 8;
    if ($rem === 0) {
        return true;
    }
    $mask = (0xFF << (8 - $rem)) & 0xFF;
    return (ord($ipBin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask);
}

function ip_matches_any(string $ip, array $cidrs): bool
{
    foreach ($cidrs as $cidr) {
        if (ip_in_cidr($ip, $cidr)) {
            return true;
        }
    }
    return false;
}

function ip_is_loopback(string $ip): bool
{
    return ip_matches_any($ip, ['127.0.0.0/8', '::1/128']);
}

/**
 * Real visitor IP. Forwarded headers are only honored when the direct peer is a
 * trusted proxy, otherwise any visitor could spoof their address.
 */
function client_ip(array $config): string
{
    $remote = ip_normalize((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $trusted = $config['trusted_proxies'] ?? [];

    if (!ip_is_valid($remote) || !ip_matches_any($remote, $trusted)) {
        return $remote;
    }

    $realIp = ip_normalize((string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''));
    if (ip_is_valid($realIp) && !ip_matches_any($realIp, $trusted)) {
        return $realIp;
    }

    $chain = array_reverse(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')));
    foreach ($chain as $hop) {
        $hop = ip_normalize($hop);
        if (!ip_is_valid($hop)) {
            break;
        }
        if (!ip_matches_any($hop, $trusted)) {
            return $hop;
        }
    }

    return $remote;
}

function ip_scope(string $ip): string
{
    $ranges = [
        'Loopback'       => ['127.0.0.0/8', '::1/128'],
        'Private'        => ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', 'fc00::/7'],
        'Shared (CGNAT)' => ['100.64.0.0/10'],
        'Link-local'     => ['169.254.0.0/16', 'fe80::/10'],
        'Multicast'      => ['224.0.0.0/4', 'ff00::/8'],
        'Documentation'  => ['192.0.2.0/24', '198.51.100.0/24', '203.0.113.0/24', '2001:db8::/32'],
        'Reserved'       => ['0.0.0.0/8', '240.0.0.0/4', '192.0.0.0/24', '198.18.0.0/15', '::/128'],
    ];
    foreach ($ranges as $label => $cidrs) {
        if (ip_matches_any($ip, $cidrs)) {
            return $label;
        }
    }
    return ip_is_valid($ip) ? 'Public' : 'Unknown';
}

function ip_is_public(string $ip): bool
{
    return ip_scope($ip) === 'Public';
}

function ip_ptr_name(string $ip): ?string
{
    $bin = @inet_pton($ip);
    if ($bin === false) {
        return null;
    }
    if (strlen($bin) === 4) {
        return implode('.', array_reverse(explode('.', $ip))) . '.in-addr.arpa';
    }
    return implode('.', array_reverse(str_split(bin2hex($bin)))) . '.ip6.arpa';
}

/** PTR hostnames for an IP. */
function reverse_dns(string $ip): array
{
    $hosts = [];

    $ptr = @dns_get_record(ip_ptr_name($ip), DNS_PTR);
    if (is_array($ptr)) {
        foreach ($ptr as $r) {
            if (!empty($r['target'])) {
                $hosts[] = rtrim($r['target'], '.');
            }
        }
    } else {
        $host = @gethostbyaddr($ip);
        if ($host && $host !== $ip) {
            $hosts[] = $host;
        }
    }

    return ['ip' => $ip, 'hosts' => array_values(array_unique($hosts))];
}
