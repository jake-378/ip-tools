<?php
defined('IPTOOLS') || exit;

/** Rough browser / OS / device detection; good enough for a display tile. */
function parse_user_agent(string $ua): array
{
    $browsers = [
        'Edge'             => '/Edg(?:e|A|iOS)?\/([\d.]+)/',
        'Opera'            => '/(?:OPR|Opera)\/([\d.]+)/',
        'Samsung Internet' => '/SamsungBrowser\/([\d.]+)/',
        'Vivaldi'          => '/Vivaldi\/([\d.]+)/',
        'Firefox'          => '/(?:Firefox|FxiOS)\/([\d.]+)/',
        'Chrome'           => '/(?:Chrome|CriOS)\/([\d.]+)/',
        'Safari'           => '/Version\/([\d.]+).*Safari/',
    ];
    $browser = 'Unknown';
    foreach ($browsers as $name => $re) {
        if (preg_match($re, $ua, $m)) {
            $browser = $name . ' ' . explode('.', $m[1])[0];
            break;
        }
    }

    $os = 'Unknown';
    if (preg_match('/Windows NT 10/', $ua)) {
        $os = 'Windows 10/11';
    } elseif (preg_match('/Windows NT ([\d.]+)/', $ua, $m)) {
        $os = 'Windows NT ' . $m[1];
    } elseif (preg_match('/iPad/', $ua)) {
        $os = 'iPadOS';
    } elseif (preg_match('/(?:iPhone|iPod).*OS (\d+)/', $ua, $m)) {
        $os = 'iOS ' . $m[1];
    } elseif (preg_match('/Android ([\d.]+)/', $ua, $m)) {
        $os = 'Android ' . explode('.', $m[1])[0];
    } elseif (preg_match('/CrOS/', $ua)) {
        $os = 'ChromeOS';
    } elseif (preg_match('/Mac OS X/', $ua)) {
        $os = 'macOS';
    } elseif (preg_match('/Linux/', $ua)) {
        $os = 'Linux';
    }

    $device = 'Desktop';
    if (preg_match('/iPad|Tablet|^(?=.*Android)(?!.*Mobile)/', $ua)) {
        $device = 'Tablet';
    } elseif (preg_match('/Mobi|iPhone|iPod/', $ua)) {
        $device = 'Mobile';
    }
    if (preg_match('/bot|crawl|spider|curl|wget|python-requests/i', $ua)) {
        $device = 'Bot / Script';
    }

    return ['browser' => $browser, 'os' => $os, 'device' => $device];
}
