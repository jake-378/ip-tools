<?php
defined('IPTOOLS') || exit;

function inspect_ssl_certificate(string $target, int $port = 443): array
{
    $target = trim($target);
    $target = preg_replace('#^https?://#i', '', $target);
    $target = explode('/', $target)[0];
    if (strpos($target, ':') !== false && substr($target, 0, 1) !== '[') {
        [$target, $customPort] = explode(':', $target, 2);
        if (is_numeric($customPort) && (int)$customPort > 0) {
            $port = (int) $customPort;
        }
    }
    $target = trim($target, "[] \t\n\r\0\x0B");

    if ($target === '') {
        throw new InvalidArgumentException('Enter a domain name or IP address.');
    }

    $isIp = filter_var($target, FILTER_VALIDATE_IP) !== false;
    $isWildcard = (substr($target, 0, 2) === '*.');
    $checkDomain = $isWildcard ? substr($target, 2) : $target;

    if (!$isIp && !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i', $checkDomain)) {
        throw new InvalidArgumentException('Enter a valid domain name (e.g. google.com) or public IP.');
    }

    $connectHost = $isWildcard ? $checkDomain : $target;
    $hostHeader = $isIp ? '' : $connectHost;
    $sslHost = (filter_var($connectHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) ? "[{$connectHost}]" : $connectHost;

    $ctx = stream_context_create([
        'ssl' => [
            'capture_peer_cert' => true,
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'peer_name'         => $hostHeader,
            'SNI_enabled'       => true,
            'SNI_server_name'   => $hostHeader,
        ],
    ]);

    $errno = 0;
    $errstr = '';
    $timeout = 4.0;
    $client = @stream_socket_client("ssl://{$sslHost}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if (!$client) {
        $reason = 'Connection failed';
        if (stripos($errstr, 'php_network_getaddresses') !== false || stripos($errstr, 'getaddrinfo') !== false) {
            $reason = 'No such host is known';
        } elseif (stripos($errstr, 'timed out') !== false) {
            $reason = 'Connection timed out';
        } elseif (stripos($errstr, 'refused') !== false) {
            $reason = 'Connection refused';
        } elseif ($errstr) {
            $cleaned = preg_replace('/^php_[^:]+:\s*/i', '', trim($errstr));
            $reason = trim($cleaned, " \t\n\r\0\x0B.");
        }
        throw new RuntimeException("Could not connect to {$target}:{$port} ({$reason}).");
    }

    $params = stream_context_get_params($client);
    fclose($client);

    $peerCert = $params['options']['ssl']['peer_certificate'] ?? null;
    if (!$peerCert) {
        throw new RuntimeException("No SSL certificate was returned by {$target}.");
    }

    $cert = openssl_x509_parse($peerCert);
    if (!$cert) {
        throw new RuntimeException("Unable to parse SSL certificate data.");
    }

    $validFrom = (int) ($cert['validFrom_time_t'] ?? 0);
    $validTo   = (int) ($cert['validTo_time_t'] ?? 0);
    $now       = time();
    $daysLeft  = (int) floor(($validTo - $now) / 86400);

    $issuer = $cert['issuer']['O'] ?? $cert['issuer']['CN'] ?? 'Unknown CA';
    $subject = $cert['subject']['CN'] ?? $target;

    $sansRaw = $cert['extensions']['subjectAltName'] ?? '';
    $sans = [];
    if ($sansRaw) {
        foreach (explode(',', $sansRaw) as $part) {
            $part = trim($part);
            if (stripos($part, 'DNS:') === 0) {
                $sans[] = substr($part, 4);
            } elseif (stripos($part, 'IP Address:') === 0) {
                $sans[] = substr($part, 11);
            }
        }
    }

    $status = 'valid';
    if ($daysLeft < 0) {
        $status = 'expired';
    } elseif ($daysLeft <= 14) {
        $status = 'expiring_soon';
    }

    // Determine certificate type & relationship to requested target
    $targetLower = strtolower($target);
    $subjectLower = strtolower($subject);
    $targetIsWildcard = (substr($targetLower, 0, 2) === '*.');
    $subjectIsWildcard = (substr($subjectLower, 0, 2) === '*.');

    $directMatch = ($targetLower === $subjectLower);

    // Collect all wildcard patterns that ACTUALLY exist on this certificate
    $certWildcards = [];
    if ($subjectIsWildcard) {
        $certWildcards[] = $subjectLower;
    }
    foreach ($sans as $san) {
        $sanLower = strtolower($san);
        if (substr($sanLower, 0, 2) === '*.') {
            $certWildcards[] = $sanLower;
        }
    }
    $certWildcards = array_values(array_unique($certWildcards));

    // Check if target is legitimately covered by a wildcard on the certificate
    $coveredByWildcard = false;
    foreach ($certWildcards as $wc) {
        if ($targetLower === $wc) {
            $coveredByWildcard = true;
            break;
        }
        $base = substr($wc, 2);
        if ($targetLower === $base || preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*\.' . preg_quote($base, '/') . '$/i', $targetLower)) {
            $coveredByWildcard = true;
            break;
        }
    }

    // Check if target matches any SAN entry directly
    $matchedInSans = false;
    foreach ($sans as $san) {
        if (strtolower($san) === $targetLower) {
            $matchedInSans = true;
            break;
        }
    }

    if ($targetIsWildcard) {
        // User searched for *.example.com - only valid if the cert actually has a wildcard for it
        $certType = $coveredByWildcard ? 'wildcard' : 'mismatch';
    } elseif ($coveredByWildcard) {
        $certType = 'wildcard';
    } elseif ($directMatch) {
        $certType = 'direct';
    } elseif ($matchedInSans) {
        $certType = 'san';
    } else {
        $certType = 'mismatch';
    }

    $sans = array_values(array_unique($sans));
    natcasesort($sans);
    $sans = array_values($sans);

    return [
        'target'      => $target,
        'port'        => $port,
        'common_name' => $subject,
        'cert_type'   => $certType,
        'issuer'      => $issuer,
        'valid_from'  => date('M j, Y', $validFrom),
        'valid_to'    => date('M j, Y', $validTo),
        'days_left'   => $daysLeft,
        'status'      => $status,
        'sans_count'  => count($sans),
        'sans'        => array_slice($sans, 0, 100),
    ];
}
