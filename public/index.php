<?php
require __DIR__ . '/inc/init.php';

send_common_headers();
header('Content-Type: text/html; charset=utf-8');

$ip       = client_ip($config);
$agent    = parse_user_agent(substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512));
$ipVer    = ip_version($ip);
$ipScope  = ip_scope($ip);
$isPublic = ip_is_public($ip);

$isHttps  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$details = [
    'Browser'          => $agent['browser'],
    'Operating system' => $agent['os'],
    'Device'           => $agent['device'],
    'Connection'       => $isHttps ? 'HTTPS (Secure)' : 'HTTP (Standard)',
];

$copyIcon = '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 8V5.6A1.6 1.6 0 0 1 9.6 4h8.8A1.6 1.6 0 0 1 20 5.6v8.8a1.6 1.6 0 0 1-1.6 1.6H16M5.6 8h8.8A1.6 1.6 0 0 1 16 9.6v8.8a1.6 1.6 0 0 1-1.6 1.6H5.6A1.6 1.6 0 0 1 4 18.4V9.6A1.6 1.6 0 0 1 5.6 8Z"/></svg>';
$clearIcon = '<svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($config['site_name']) ?></title>
    <meta name="description" content="A web dashboard for IP, DNS, port, SSL/TLS, subnet, and MAC address lookups.">
    <meta name="theme-color" content="#1c1c1e">
    <meta name="color-scheme" content="dark">
    <link rel="icon" href="<?= h(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= h(asset('assets/css/app.css')) ?>">
    <script src="<?= h(asset('assets/js/app.js')) ?>" defer></script>
</head>
<body class="min-h-screen bg-[#1c1c1e] font-sans text-zinc-100 antialiased" data-ip="<?= h($ip) ?>">

<main class="mx-auto max-w-7xl xl:max-w-[1480px] wide-main px-5 py-10 sm:px-8 sm:py-14">
    <header class="mb-8 flex items-center gap-3 sm:gap-4 flex-wrap">
        <h1 class="text-2xl font-semibold tracking-tight text-white">IP Tools</h1>
    </header>

    <div class="grid items-start gap-5 md:grid-cols-2 xl:grid-cols-3 wide-grid min-w-0">

        <section class="card" aria-labelledby="ipinfo-title">
            <h2 id="ipinfo-title" class="card-title">IP Info</h2>
            <p class="card-desc">Your public IP address and device.</p>

            <div class="flex grow flex-col justify-center py-4 sm:py-4.5 min-w-0">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-zinc-300">Your IP address</p>
                    <div class="flex items-center gap-1.5">
                        <?php if ($ipVer > 0): ?>
                            <span class="rounded bg-white/[0.08] px-2 py-0.5 text-[11px] font-mono text-zinc-300">IPv<?= $ipVer ?></span>
                        <?php endif; ?>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-400 border border-emerald-500/20">
                            <span class="size-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            <?= $isPublic ? 'Public' : h($ipScope) ?>
                        </span>
                    </div>
                </div>

                <div class="mt-2 flex items-center justify-between gap-3">
                    <p class="min-w-0 break-all font-mono text-2xl sm:text-3xl font-bold text-white"><?= h($ip ?: 'Unknown') ?></p>
                    <button type="button" class="btn btn-secondary shrink-0 transition" data-copy="<?= h($ip) ?>"><?= $copyIcon ?> Copy</button>
                </div>
                <?php if ($ipVer === 6): ?>
                    <p class="mt-2 text-xs text-amber-300/90">You're connected over IPv6.</p>
                <?php endif; ?>
            </div>

            <div class="panel-slot mt-auto grow-0">
                <dl class="panel flex flex-col justify-center divide-y divide-white/[0.06] py-1 text-[15px]">
                    <?php foreach ($details as $label => $value): ?>
                        <div class="flex items-center justify-between gap-4 py-2">
                            <dt class="text-zinc-300 font-medium"><?= h($label) ?></dt>
                            <dd class="truncate text-right font-semibold text-white" title="<?= h($value) ?>"><?= h($value) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </div>
        </section>

        <section class="card" aria-labelledby="rdns-title">
            <h2 id="rdns-title" class="card-title">Reverse DNS</h2>
            <p class="card-desc">Get the PTR hostname for an IP address.</p>

            <form class="mt-4 sm:mt-5 space-y-2.5 sm:space-y-3" data-rdns-form novalidate>
                <label for="rdns-ip" class="sr-only">IP address</label>
                <div class="relative min-w-0">
                    <input id="rdns-ip" name="ip" type="text" class="field pr-36" placeholder="e.g. 1.1.1.1" autocomplete="off" spellcheck="false" maxlength="64">
                    <button type="button" class="clear-btn absolute top-1/2 right-28 -translate-y-1/2 hidden" data-clear-input="#rdns-ip" aria-label="Clear input">
                        <?= $clearIcon ?>
                    </button>
                    <button type="button" class="chip absolute top-1/2 right-2 -translate-y-1/2" data-fill-ip="#rdns-ip">Current IP</button>
                </div>
                <button type="submit" class="btn btn-primary w-full">Lookup</button>
            </form>

            <div class="panel-slot">
                <div class="panel flex flex-col justify-center items-center p-4 text-center" data-rdns-result aria-live="polite">
                    <p class="text-sm font-medium text-zinc-400">Enter an IP to view PTR record</p>
                    <p class="mt-1 text-xs text-zinc-500">Results appear here</p>
                </div>
            </div>
        </section>

        <div class="flex flex-col gap-5 min-w-0">
            <section class="card card-sm" aria-labelledby="ip2l-title">
                <h2 id="ip2l-title" class="card-title">IP Lookup</h2>
                <p class="card-desc">Location and ISP info via IP2Location.</p>

                <form class="mt-4 space-y-3" data-ip2l-form novalidate>
                    <label for="ip2l-ip" class="sr-only">IP address</label>
                    <div class="relative min-w-0">
                        <input id="ip2l-ip" name="ip" type="text" class="field pr-36" placeholder="e.g. 1.1.1.1" autocomplete="off" spellcheck="false" maxlength="64">
                        <button type="button" class="clear-btn absolute top-1/2 right-28 -translate-y-1/2 hidden" data-clear-input="#ip2l-ip" aria-label="Clear input">
                            <?= $clearIcon ?>
                        </button>
                        <button type="button" class="chip absolute top-1/2 right-2 -translate-y-1/2" data-fill-ip="#ip2l-ip">Current IP</button>
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Lookup on IP2Location</button>
                    <p class="hidden text-sm text-rose-300" data-ip2l-error></p>
                </form>
            </section>

            <section class="card card-sm" aria-labelledby="dns-title">
                <h2 id="dns-title" class="card-title">DNS Lookup</h2>
                <p class="card-desc">Global DNS propagation via whatsMyDNS.net.</p>

                <form class="mt-4 space-y-3" data-dns-form novalidate>
                    <div class="flex gap-2 min-w-0">
                        <div class="relative grow min-w-0">
                            <label for="dns-host" class="sr-only">Domain or host</label>
                        <input id="dns-host" name="host" type="text" class="field pr-9" placeholder="e.g. example.com" autocomplete="off" spellcheck="false" maxlength="128">
                            <button type="button" class="clear-btn absolute top-1/2 right-2.5 -translate-y-1/2 hidden" data-clear-input="#dns-host" aria-label="Clear input">
                                <?= $clearIcon ?>
                            </button>
                        </div>
                        <div class="relative w-24 shrink-0">
                            <label for="dns-type" class="sr-only">Record type</label>
                            <select id="dns-type" name="type" class="field appearance-none pl-3 pr-7 font-mono text-sm font-semibold cursor-pointer">
                                <option value="A" class="bg-[#1c1c1e] text-white" selected>A</option>
                                <option value="AAAA" class="bg-[#1c1c1e] text-white">AAAA</option>
                                <option value="CNAME" class="bg-[#1c1c1e] text-white">CNAME</option>
                                <option value="MX" class="bg-[#1c1c1e] text-white">MX</option>
                                <option value="NS" class="bg-[#1c1c1e] text-white">NS</option>
                                <option value="PTR" class="bg-[#1c1c1e] text-white">PTR</option>
                                <option value="SOA" class="bg-[#1c1c1e] text-white">SOA</option>
                                <option value="SRV" class="bg-[#1c1c1e] text-white">SRV</option>
                                <option value="TXT" class="bg-[#1c1c1e] text-white">TXT</option>
                                <option value="CAA" class="bg-[#1c1c1e] text-white">CAA</option>
                            </select>
                            <div class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-zinc-400" aria-hidden="true">
                                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-full">Lookup on whatsMyDNS</button>
                    <p class="hidden text-sm text-rose-300" data-dns-error></p>
                </form>
            </section>
        </div>

        <section class="card" aria-labelledby="port-title">
            <h2 id="port-title" class="card-title">Port Checker</h2>
            <p class="card-desc">Check if a TCP port is open or closed on an IP.</p>

            <form class="mt-4 sm:mt-5 space-y-2.5 sm:space-y-3" data-port-form novalidate>
                <div class="flex gap-2 min-w-0">
                    <div class="relative grow min-w-0">
                        <label for="port-ip" class="sr-only">IP address</label>
                        <input id="port-ip" name="ip" type="text" class="field pr-32 sm:pr-36" placeholder="e.g. 1.1.1.1" autocomplete="off" spellcheck="false" maxlength="64">
                        <button type="button" class="clear-btn absolute top-1/2 right-24 sm:right-28 -translate-y-1/2 hidden" data-clear-input="#port-ip" aria-label="Clear input">
                            <?= $clearIcon ?>
                        </button>
                        <button type="button" class="chip absolute top-1/2 right-1.5 sm:right-2 -translate-y-1/2" data-fill-ip="#port-ip">Current IP</button>
                    </div>
                    <div class="w-20 sm:w-22 shrink-0">
                        <label for="port-number" class="sr-only">Port</label>
                        <input id="port-number" name="port" type="number" min="1" max="65535" class="field text-center px-1" placeholder="Port" autocomplete="off">
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-1.5 pt-0.5 pb-0.5">
                    <span class="text-[13px] sm:text-sm text-zinc-300 font-medium mr-1 shrink-0">Common:</span>
                    <button type="button" class="chip" data-set-port="80">80 HTTP</button>
                    <button type="button" class="chip" data-set-port="443">443 HTTPS</button>
                    <button type="button" class="chip" data-set-port="22">22 SSH</button>
                    <button type="button" class="chip" data-set-port="3389">3389 RDP</button>
                </div>

                <button type="submit" class="btn btn-primary w-full">Check Port</button>
            </form>

            <div class="panel-slot">
                <div class="panel flex flex-col justify-center items-center p-4 text-center" data-port-result aria-live="polite">
                    <p class="text-sm font-medium text-zinc-400">Enter an IP and port to test</p>
                    <p class="mt-1 text-xs text-zinc-500">TCP handshake test</p>
                </div>
            </div>
        </section>

        <section class="card" aria-labelledby="ssl-title">
            <h2 id="ssl-title" class="card-title">SSL / TLS Certificate</h2>
            <p class="card-desc">Check certificate expiry, issuer, and health.</p>

            <form class="mt-4 sm:mt-5 space-y-2.5 sm:space-y-3" data-ssl-form novalidate>
                <div class="relative min-w-0">
                    <label for="ssl-target" class="sr-only">Domain or host</label>
                    <input id="ssl-target" name="target" type="text" class="field pr-44" placeholder="e.g. google.com" autocomplete="off" spellcheck="false" maxlength="128">
                    <button type="button" class="clear-btn absolute top-1/2 right-36 -translate-y-1/2 hidden" data-clear-input="#ssl-target" aria-label="Clear input">
                        <?= $clearIcon ?>
                    </button>
                    <button type="button" class="chip absolute top-1/2 right-2 -translate-y-1/2" data-fill-domain>Current Domain</button>
                </div>

                <div class="flex flex-wrap items-center gap-1.5 pt-0.5 pb-0.5">
                    <span class="text-[13px] sm:text-sm text-zinc-300 font-medium mr-1 shrink-0">Quick:</span>
                    <button type="button" class="chip" data-set-ssl="google.com">google.com</button>
                    <button type="button" class="chip" data-set-ssl="cloudflare.com">cloudflare.com</button>
                </div>

                <button type="submit" class="btn btn-primary w-full">Inspect Certificate</button>
            </form>

            <div class="panel-slot">
                <div class="panel flex flex-col justify-center items-center p-4 text-center" data-ssl-result aria-live="polite">
                    <p class="text-sm font-medium text-zinc-400">Enter a domain name to inspect</p>
                    <p class="mt-1 text-xs text-zinc-500">Checks TLS handshake & expiration</p>
                </div>
            </div>
        </section>

        <section class="card" aria-labelledby="subnet-title">
            <h2 id="subnet-title" class="card-title">Subnet & CIDR Calculator</h2>
            <p class="card-desc">IP ranges, usable devices, and network guidance.</p>

            <form class="mt-4 sm:mt-5 space-y-2.5 sm:space-y-3" data-subnet-form novalidate>
                <div class="relative min-w-0">
                    <label for="subnet-input" class="sr-only">Subnet or router IP</label>
                    <input id="subnet-input" name="cidr" type="text" class="field pr-9" placeholder="e.g. 192.168.1.1 or 10.0.0.0/24" autocomplete="off" spellcheck="false" maxlength="64">
                    <button type="button" class="clear-btn absolute top-1/2 right-2.5 -translate-y-1/2 hidden" data-clear-input="#subnet-input" aria-label="Clear input">
                        <?= $clearIcon ?>
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-1.5 pt-0.5 pb-0.5">
                    <span class="text-[13px] sm:text-sm text-zinc-300 font-medium mr-1 shrink-0">Presets:</span>
                    <button type="button" class="chip" data-set-subnet="/24">/24 (254)</button>
                    <button type="button" class="chip" data-set-subnet="/16">/16 (65k)</button>
                    <button type="button" class="chip" data-set-subnet="/28">/28 (14)</button>
                    <button type="button" class="chip" data-set-subnet="/8">/8 (16M)</button>
                </div>

                <button type="submit" class="btn btn-primary w-full">Calculate Subnet</button>
            </form>

            <div class="panel-slot">
                <div class="panel flex flex-col justify-center p-4 text-xs" data-subnet-result aria-live="polite">
                    <div class="m-auto text-center px-2 py-1">
                        <p class="text-sm font-medium text-zinc-400">Enter an IP, subnet, or pick a preset</p>
                        <p class="mt-1 text-xs text-zinc-500">Calculates usable range, mask, and router setup</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="card" aria-labelledby="mac-title">
            <h2 id="mac-title" class="card-title">MAC Address Lookup</h2>
            <p class="card-desc">Identify device manufacturer and hardware vendor.</p>

            <form class="mt-4 sm:mt-5 space-y-2.5 sm:space-y-3" data-mac-form novalidate>
                <div class="relative min-w-0">
                    <label for="mac-input" class="sr-only">MAC address</label>
                    <input id="mac-input" name="mac" type="text" class="field pr-12" placeholder="e.g. B8:27:EB or 00:1A:2B:3C:4D:5E" autocomplete="off" spellcheck="false" maxlength="32">
                    <button type="button" class="clear-btn absolute top-1/2 right-3 -translate-y-1/2 hidden" data-clear-input="#mac-input" aria-label="Clear input">
                        <?= $clearIcon ?>
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-1.5 pt-0.5 pb-0.5">
                    <span class="text-[13px] sm:text-sm text-zinc-300 font-medium mr-1 shrink-0">Examples:</span>
                    <button type="button" class="chip" data-set-mac="B8:27:EB">Raspberry Pi</button>
                    <button type="button" class="chip" data-set-mac="B8:C7:5D">Apple</button>
                    <button type="button" class="chip" data-set-mac="24:B6:57">Ubiquiti</button>
                    <button type="button" class="chip" data-set-mac="00:00:0C">Cisco</button>
                </div>

                <button type="submit" class="btn btn-primary w-full">Lookup Vendor</button>
            </form>

            <div class="panel-slot">
                <div class="panel flex flex-col justify-center items-center p-4 text-center" data-mac-result aria-live="polite">
                    <p class="text-sm font-medium text-zinc-400">Enter a MAC address above</p>
                    <p class="mt-1 text-xs text-zinc-500">Matches IEEE OUI manufacturer prefix</p>
                </div>
            </div>
        </section>

    </div>

    <footer class="mt-14 flex items-center justify-between border-t border-white/[0.06] pt-8 text-sm text-zinc-400">
        <span>&copy; <?= date('Y') ?> IP Tools</span>
    </footer>
</main>

<div class="toast" role="status" aria-live="polite" data-toast></div>
</body>
</html>
