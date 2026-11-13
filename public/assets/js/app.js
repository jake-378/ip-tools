(() => {
  'use strict';

  const currentIp = document.body.dataset.ip || '';
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const IPV4 = /^(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}$/;
  const looksLikeIp = (v) => IPV4.test(v) || (v.includes(':') && /^[0-9a-f:.]+$/i.test(v));

  const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  })[c]);

  // Clean pasted or typed IP strings
  function sanitizeIp(val) {
    if (!val) return '';
    let s = val.trim();
    s = s.replace(/^https?:\/\//i, '');
    s = s.split('/')[0];
    if (/^\d{1,3}(\.\d{1,3}){3}:\d+$/.test(s)) {
      s = s.split(':')[0];
    }
    s = s.replace(/^\[([0-9a-f:.]+)\](?::\d+)?$/i, '$1');
    return s.trim();
  }

  // Toast
  const toast = $('[data-toast]');
  let toastTimer;
  function showToast(msg) {
    if (!toast) return;
    toast.textContent = msg;
    toast.classList.add('is-visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 1800);
  }

  const CHECK_ICON = '<svg class="size-4 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
  const COPY_ICON = '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 8V5.6A1.6 1.6 0 0 1 9.6 4h8.8A1.6 1.6 0 0 1 20 5.6v8.8a1.6 1.6 0 0 1-1.6 1.6H16M5.6 8h8.8A1.6 1.6 0 0 1 16 9.6v8.8a1.6 1.6 0 0 1-1.6 1.6H5.6A1.6 1.6 0 0 1 4 18.4V9.6A1.6 1.6 0 0 1 5.6 8Z"/></svg>';

  // Clipboard copy
  async function copyText(text, triggerBtn = null) {
    try {
      await navigator.clipboard.writeText(text);
    } catch {
      const ta = Object.assign(document.createElement('textarea'), { value: text });
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      ta.remove();
    }
    showToast('Copied to clipboard');

    if (triggerBtn) {
      const origHtml = triggerBtn.innerHTML;
      if (triggerBtn.classList.contains('icon-btn')) {
        triggerBtn.innerHTML = CHECK_ICON;
      } else {
        triggerBtn.innerHTML = `${CHECK_ICON} <span class="text-emerald-400">Copied!</span>`;
      }
      triggerBtn.classList.add('pointer-events-none');
      setTimeout(() => {
        triggerBtn.innerHTML = origHtml;
        triggerBtn.classList.remove('pointer-events-none');
      }, 1500);
    }
  }

  // Manage clear-input buttons
  function syncClearButton(input) {
    const clearBtn = $(`[data-clear-input="#${input.id}"]`);
    if (clearBtn) {
      clearBtn.classList.toggle('hidden', input.value.length === 0);
    }
  }

  // Setup input listeners for sanitization & clear buttons
  $$('input').forEach((input) => {
    syncClearButton(input);

    input.addEventListener('input', () => {
      syncClearButton(input);
    });

    if (input.name === 'ip') {
      input.addEventListener('paste', (e) => {
        const pasteText = (e.clipboardData || window.clipboardData)?.getData('text') || '';
        const cleaned = sanitizeIp(pasteText);
        if (cleaned && cleaned !== pasteText) {
          e.preventDefault();
          input.value = cleaned;
          syncClearButton(input);
        }
      });
    }
  });

  async function api(params, options = {}) {
    const res = await fetch('api.php?' + new URLSearchParams(params), {
      headers: { Accept: 'application/json' },
      cache: 'no-store',
      ...options,
    });
    let data;
    try {
      data = await res.json();
    } catch {
      throw new Error('Unexpected response from server.');
    }
    if (!res.ok || !data.ok) throw new Error(data.error || 'Request failed.');
    return data;
  }

  // Form submit helper
  function submitForm(form) {
    if (!form) return;
    const btn = form.querySelector('[type=submit]');
    if (btn) btn.disabled = false;
    try {
      if (form.requestSubmit) {
        form.requestSubmit();
        return;
      }
    } catch (_) {}
    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
  }

  // Global click delegations
  document.addEventListener('click', (e) => {
    // Copy button
    const copyBtn = e.target.closest('[data-copy]');
    if (copyBtn && copyBtn.dataset.copy) {
      copyText(copyBtn.dataset.copy, copyBtn);
      return;
    }

    // Fill current IP
    const fillBtn = e.target.closest('[data-fill-ip]');
    if (fillBtn) {
      const input = $(fillBtn.dataset.fillIp);
      if (input) {
        input.value = currentIp;
        syncClearButton(input);
        input.focus();
        if (input.id === 'port-ip') {
          const portVal = parseInt($('#port-number')?.value, 10);
          if (portVal >= 1 && portVal <= 65535) {
            submitForm($('[data-port-form]'));
          }
        } else if (input.id === 'rdns-ip') {
          submitForm($('[data-rdns-form]'));
        }
      }
      return;
    }

    // Fill current domain (SSL)
    const fillDomainBtn = e.target.closest('[data-fill-domain]');
    if (fillDomainBtn) {
      const input = $('#ssl-target');
      if (input) {
        input.value = window.location.hostname || '';
        syncClearButton(input);
        input.focus();
        try {
          input.setSelectionRange(input.value.length, input.value.length);
        } catch (_) {}
        if (input.value) {
          checkAndAutoRunSsl(0);
        }
      }
      return;
    }

    // Clear input button
    const clearBtn = e.target.closest('[data-clear-input]');
    if (clearBtn) {
      const input = $(clearBtn.dataset.clearInput);
      if (input) {
        input.value = '';
        syncClearButton(input);
        input.focus();
        if (input.id === 'mac-input') {
          renderMac('<p class="text-sm font-medium text-zinc-400">Enter a MAC address above</p><p class="mt-1 text-xs text-zinc-500">Matches IEEE OUI manufacturer prefix</p>');
        } else if (input.id === 'ssl-target') {
          renderSsl('<p class="text-sm font-medium text-zinc-400">Enter a domain name to inspect</p><p class="mt-1 text-xs text-zinc-500">Checks TLS handshake & expiration</p>');
        } else if (input.id === 'rdns-ip') {
          renderRdns('<p class="text-sm font-medium text-zinc-400">Enter an IP to view PTR record</p><p class="mt-1 text-xs text-zinc-500">Results appear here</p>');
        } else if (input.id === 'port-ip') {
          renderPort('<p class="text-sm font-medium text-zinc-400">Enter an IP and port to test</p><p class="mt-1 text-xs text-zinc-500">TCP handshake test</p>');
        } else if (input.id === 'subnet-input') {
          runSubnetCalc('');
        } else if (input.id === 'ip2l-ip') {
          const err = $('[data-ip2l-error]');
          if (err) err.classList.add('hidden');
        } else if (input.id === 'dns-host') {
          const err = $('[data-dns-error]');
          if (err) err.classList.add('hidden');
        }
      }
      return;
    }

    // Port Checker: Quick set port button (auto-checks)
    const portBtn = e.target.closest('[data-set-port]');
    if (portBtn) {
      const portInput = $('#port-number');
      const ipInput = $('#port-ip');
      if (portInput) {
        portInput.value = portBtn.dataset.setPort;
        if (ipInput && !ipInput.value.trim() && currentIp) {
          ipInput.value = currentIp;
          syncClearButton(ipInput);
        }
        submitForm($('[data-port-form]'));
      }
      return;
    }

    // SSL: Toggle in-widget views
    if (e.target.closest('[data-show-sans]')) {
      const res = $('[data-ssl-result]');
      if (res) {
        res.querySelector('[data-ssl-main]')?.classList.add('hidden');
        res.querySelector('[data-ssl-sans]')?.classList.remove('hidden');
      }
      return;
    }
    if (e.target.closest('[data-hide-sans]')) {
      const res = $('[data-ssl-result]');
      if (res) {
        res.querySelector('[data-ssl-sans]')?.classList.add('hidden');
        res.querySelector('[data-ssl-main]')?.classList.remove('hidden');
      }
      return;
    }

    // SSL: Quick domain preset
    const sslBtn = e.target.closest('[data-set-ssl]');
    if (sslBtn) {
      const targetInput = $('#ssl-target');
      if (targetInput) {
        targetInput.value = sslBtn.dataset.setSsl;
        syncClearButton(targetInput);
        submitForm($('[data-ssl-form]'));
      }
      return;
    }

    // Subnet: Presets
    const subnetBtn = e.target.closest('[data-set-subnet]');
    if (subnetBtn) {
      const input = $('#subnet-input');
      const val = subnetBtn.dataset.setSubnet;
      if (input) {
        if (val.startsWith('/')) {
          const curIp = (input.value.split('/')[0] || '').trim() || '192.168.1.1';
          input.value = curIp + val;
        } else {
          input.value = val;
        }
        syncClearButton(input);
        runSubnetCalc(input.value);
      }
      return;
    }

    // MAC: Examples
    const macBtn = e.target.closest('[data-set-mac]');
    if (macBtn) {
      const input = $('#mac-input');
      if (input) {
        input.value = macBtn.dataset.setMac;
        syncClearButton(input);
        submitForm($('[data-mac-form]'));
      }
      return;
    }
  });

  // Escape shortcut
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      // If covered domains list is open in SSL widget, close it
      const sansView = $('[data-ssl-sans]');
      if (sansView && !sansView.classList.contains('hidden')) {
        sansView.classList.add('hidden');
        $('[data-ssl-main]')?.classList.remove('hidden');
        return;
      }
      if (['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName)) {
        document.activeElement.blur();
      }
      const err = $('[data-ip2l-error]');
      if (err) err.classList.add('hidden');
      const dnsErr = $('[data-dns-error]');
      if (dnsErr) dnsErr.classList.add('hidden');
    }
  });

  // ==========================================
  // Reverse DNS
  // ==========================================
  const rdnsForm = $('[data-rdns-form]');
  const rdnsResult = $('[data-rdns-result]');

  function renderRdns(html) {
    if (rdnsResult) rdnsResult.innerHTML = html;
  }

  function rdnsError(msg) {
    renderRdns(`<p class="m-auto text-center text-sm font-medium text-rose-300">${escapeHtml(msg)}</p>`);
  }

  // Auto-check Reverse DNS when entering a valid IP
  let rdnsDebounceTimer;
  const rdnsInput = $('#rdns-ip');
  function checkAndAutoRunRdns(debounceMs = 650) {
    clearTimeout(rdnsDebounceTimer);
    if (!rdnsForm || !rdnsInput) return;
    const ip = sanitizeIp(rdnsInput.value);
    if (ip && looksLikeIp(ip) && (IPV4.test(ip) || ip.includes(':'))) {
      if (debounceMs === 0) {
        submitForm(rdnsForm);
      } else {
        rdnsDebounceTimer = setTimeout(() => {
          submitForm(rdnsForm);
        }, debounceMs);
      }
    }
  }

  if (rdnsInput && rdnsForm) {
    rdnsInput.addEventListener('input', () => checkAndAutoRunRdns(650));
    rdnsInput.addEventListener('paste', () => {
      setTimeout(() => checkAndAutoRunRdns(0), 20);
    });
  }

  if (rdnsForm) {
    rdnsForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const input = rdnsForm.elements.ip;
      const ip = sanitizeIp(input.value);
      input.value = ip;
      syncClearButton(input);

      if (!ip) return rdnsError('Enter an IP address.');
      if (!looksLikeIp(ip)) return rdnsError('That is not a valid IPv4 or IPv6 address.');

      const btn = rdnsForm.querySelector('[type=submit]');
      btn.disabled = true;
      renderRdns('<div class="m-auto flex items-center justify-center gap-3 text-base font-medium text-zinc-300"><span class="spinner"></span> Looking up…</div>');

      try {
        const d = await api({ action: 'rdns', ip });

        if (!d.hosts || !d.hosts.length) {
          renderRdns(`
            <div class="m-auto text-center px-2">
              <p class="text-base font-medium text-zinc-300">No PTR record found</p>
              <p class="mt-1 font-mono text-xs text-zinc-500">${escapeHtml(d.ip)}</p>
            </div>
          `);
          return;
        }

        if (d.hosts.length === 1) {
          const host = d.hosts[0];
          const len = host.length;
          const fontClass = len > 36 ? 'text-sm sm:text-base' : (len > 24 ? 'text-base sm:text-lg' : 'text-lg sm:text-xl');
          renderRdns(`
            <div class="m-auto flex flex-col items-center justify-center text-center px-2 py-1 max-w-full w-full">
              <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">PTR Hostname</span>
              <div class="mt-2 flex items-center justify-center gap-2 max-w-full">
                <p class="font-mono ${fontClass} font-bold text-white break-all select-all leading-tight">${escapeHtml(host)}</p>
                <button type="button" class="icon-btn shrink-0" data-copy="${escapeHtml(host)}" aria-label="Copy hostname">
                  ${COPY_ICON}
                </button>
              </div>
            </div>
          `);
        } else {
          const rows = d.hosts.map((host) => `
            <div class="flex items-center justify-between gap-3 py-1.5">
              <p class="font-mono text-sm sm:text-base font-bold text-white break-all select-all leading-tight">${escapeHtml(host)}</p>
              <button type="button" class="icon-btn shrink-0" data-copy="${escapeHtml(host)}" aria-label="Copy hostname">
                ${COPY_ICON}
              </button>
            </div>`).join('');
          renderRdns(`
            <div class="my-auto w-full px-2">
              <p class="text-center text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1.5">PTR Hostnames</p>
              <div class="divide-y divide-white/[0.06]">${rows}</div>
            </div>
          `);
        }
      } catch (err) {
        rdnsError(err.message);
      } finally {
        btn.disabled = false;
      }
    });
  }

  // ==========================================
  // IP2Location
  // ==========================================
  const ip2lForm = $('[data-ip2l-form]');
  const ip2lError = $('[data-ip2l-error]');
  if (ip2lForm) {
    ip2lForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const ip = sanitizeIp(ip2lForm.elements.ip.value);
      ip2lForm.elements.ip.value = ip;
      syncClearButton(ip2lForm.elements.ip);

      if (!looksLikeIp(ip)) {
        ip2lError.textContent = ip ? 'That is not a valid IPv4 or IPv6 address.' : 'Enter an IP address.';
        ip2lError.classList.remove('hidden');
        return;
      }
      ip2lError.classList.add('hidden');
      window.open('https://www.ip2location.com/' + encodeURIComponent(ip), '_blank', 'noopener');
    });
  }

  // ==========================================
  // DNS Lookup (whatsMyDNS.net)
  // ==========================================
  const dnsForm = $('[data-dns-form]');
  const dnsError = $('[data-dns-error]');
  if (dnsForm) {
    dnsForm.addEventListener('submit', (e) => {
      e.preventDefault();
      let host = (dnsForm.elements.host?.value || '').trim().replace(/^https?:\/\//i, '').split('/')[0];
      if (dnsForm.elements.host) {
        dnsForm.elements.host.value = host;
        syncClearButton(dnsForm.elements.host);
      }

      const type = (dnsForm.elements.type?.value || 'A').toUpperCase();

      if (!host) {
        if (dnsError) {
          dnsError.textContent = 'Enter a domain name (e.g. example.com).';
          dnsError.classList.remove('hidden');
        }
        return;
      }
      if (dnsError) dnsError.classList.add('hidden');

      window.open('https://www.whatsmydns.net/#' + encodeURIComponent(type) + '/' + encodeURIComponent(host), '_blank', 'noopener');
    });
  }

  // ==========================================
  // Port Checker
  // ==========================================
  const portForm = $('[data-port-form]');
  const portResult = $('[data-port-result]');

  function renderPort(html) {
    if (portResult) portResult.innerHTML = html;
  }

  function portError(msg) {
    renderPort(`<p class="m-auto text-center text-sm font-medium text-rose-300">${escapeHtml(msg)}</p>`);
  }

  // Auto-check port & IP changes
  let portDebounceTimer;
  const portNumberInput = $('#port-number');
  const portIpInput = $('#port-ip');

  function checkAndAutoRunPort(debounceMs = 750) {
    clearTimeout(portDebounceTimer);
    if (!portForm || !portNumberInput || !portIpInput) return;
    const portRaw = portNumberInput.value.trim();
    const portVal = parseInt(portRaw, 10);
    const ipVal = portIpInput.value.trim() || currentIp;

    if (debounceMs > 0 && portRaw.length < 2) {
      // Don't auto-run while typing just a single digit (e.g. user typed 2, waiting for 22)
      return;
    }

    if (portVal >= 1 && portVal <= 65535 && ipVal && looksLikeIp(ipVal)) {
      if (!portIpInput.value.trim() && currentIp) {
        portIpInput.value = currentIp;
        syncClearButton(portIpInput);
      }
      if (debounceMs === 0) {
        submitForm(portForm);
      } else {
        portDebounceTimer = setTimeout(() => {
          submitForm(portForm);
        }, debounceMs);
      }
    }
  }

  if (portNumberInput && portForm) {
    portNumberInput.addEventListener('input', () => checkAndAutoRunPort(750));
    portNumberInput.addEventListener('change', () => checkAndAutoRunPort(0));
    portNumberInput.addEventListener('blur', () => checkAndAutoRunPort(0));
  }

  if (portIpInput && portForm) {
    portIpInput.addEventListener('input', () => checkAndAutoRunPort(750));
    portIpInput.addEventListener('paste', () => {
      setTimeout(() => checkAndAutoRunPort(0), 20);
    });
  }

  if (portForm) {
    portForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const ipInput = portForm.elements.ip;
      const portInput = portForm.elements.port;
      const ip = sanitizeIp(ipInput.value) || currentIp;
      if (ipInput.value !== ip) {
        ipInput.value = ip;
        syncClearButton(ipInput);
      }
      const port = parseInt(portInput.value, 10);

      if (!ip) return portError('Enter an IP address.');
      if (!looksLikeIp(ip)) return portError('That is not a valid IPv4 or IPv6 address.');
      if (!port || port < 1 || port > 65535) return portError('Enter a port number between 1 and 65535.');

      const btn = portForm.querySelector('[type=submit]');
      btn.disabled = true;
      renderPort(`
        <div class="m-auto flex items-center justify-center gap-3 text-base font-medium text-zinc-300">
          <span class="spinner"></span> Testing port ${port}…
        </div>
      `);

      try {
        const d = await api({ action: 'port', ip, port });
        const serviceLabel = d.service ? ` (${escapeHtml(d.service)})` : '';

        if (d.open) {
          renderPort(`
            <div class="m-auto flex flex-col items-center justify-center text-center px-2 py-1">
              <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-semibold text-emerald-300 border border-emerald-500/30 mb-2">
                <span class="size-2 rounded-full bg-emerald-400 animate-pulse"></span> OPEN
              </span>
              <p class="font-mono text-xl font-bold text-white">Port ${d.port}${serviceLabel}</p>
              <p class="mt-2 text-sm font-medium text-emerald-300">Port is open & accepting connections <span class="text-zinc-400 font-mono">(${d.time_ms} ms)</span></p>
            </div>
          `);
        } else {
          renderPort(`
            <div class="m-auto flex flex-col items-center justify-center text-center px-2 py-1">
              <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-500/15 px-3 py-1 text-xs font-semibold text-rose-300 border border-rose-500/30 mb-2">
                <span class="size-2 rounded-full bg-rose-400"></span> CLOSED
              </span>
              <p class="font-mono text-xl font-bold text-white">Port ${d.port}${serviceLabel}</p>
              <p class="mt-2 text-sm font-medium text-zinc-300">Connection refused or filtered <span class="text-zinc-500 font-mono">(${d.time_ms} ms)</span></p>
            </div>
          `);
        }
      } catch (err) {
        portError(err.message);
      } finally {
        btn.disabled = false;
      }
    });
  }

  // ==========================================
  // SSL / TLS Certificate Checker
  // ==========================================
  const sslForm = $('[data-ssl-form]');
  const sslResult = $('[data-ssl-result]');

  function renderSsl(html) {
    if (sslResult) sslResult.innerHTML = html;
  }

  function sslError(msg) {
    let clean = (msg || '').trim();
    if (clean.includes('php_network_getaddresses') || clean.includes('getaddrinfo')) {
      clean = clean.replace(/\(php_network_getaddresses:[^)]+\)/i, '(No such host is known)');
    }
    renderSsl(`<p class="m-auto text-center text-sm font-medium text-rose-300">${escapeHtml(clean)}</p>`);
  }

  // Auto-check SSL when entering a domain
  let sslDebounceTimer;
  let sslAbortController = null;
  const sslInput = $('#ssl-target');

  function checkAndAutoRunSsl(debounceMs = 700) {
    clearTimeout(sslDebounceTimer);
    if (!sslForm || !sslInput) return;
    let target = sslInput.value.trim().replace(/^https?:\/\//i, '').split('/')[0];

    if (!target) {
      if (sslAbortController) {
        sslAbortController.abort();
        sslAbortController = null;
      }
      renderSsl(`
        <p class="text-sm font-medium text-zinc-400">Enter a domain name to inspect</p>
        <p class="mt-1 text-xs text-zinc-500">Checks TLS handshake & expiration</p>
      `);
      return;
    }

    if (target.length >= 3 && (target.includes('.') || target === 'localhost' || looksLikeIp(target)) && !target.endsWith('.')) {
      if (debounceMs === 0) {
        submitForm(sslForm);
      } else {
        sslDebounceTimer = setTimeout(() => {
          submitForm(sslForm);
        }, debounceMs);
      }
    }
  }

  if (sslInput && sslForm) {
    sslInput.addEventListener('input', () => checkAndAutoRunSsl(700));
    sslInput.addEventListener('paste', () => {
      setTimeout(() => checkAndAutoRunSsl(0), 20);
    });
    sslInput.addEventListener('change', () => checkAndAutoRunSsl(0));
  }

  if (sslForm) {
    sslForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const target = (sslForm.elements.target.value || '').trim();
      if (!target) return sslError('Enter a domain or host.');

      if (sslAbortController) {
        sslAbortController.abort();
      }
      sslAbortController = new AbortController();

      const btn = sslForm.querySelector('[type=submit]');
      if (btn) btn.disabled = true;
      renderSsl('<div class="m-auto flex items-center justify-center gap-3 text-base font-medium text-zinc-300"><span class="spinner"></span> Connecting to TLS host…</div>');

      try {
        const d = await api({ action: 'ssl', target }, { signal: sslAbortController.signal });

        let statusBadge = '';
        if (d.cert_type === 'mismatch') {
          statusBadge = `<span class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/15 px-2.5 py-0.5 text-xs font-semibold text-amber-300 border border-amber-500/30"><span class="size-1.5 rounded-full bg-amber-400"></span> Name Mismatch</span>`;
        } else if (d.status === 'valid') {
          statusBadge = `<span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs font-semibold text-emerald-300 border border-emerald-500/30"><span class="size-1.5 rounded-full bg-emerald-400"></span> Valid (${d.days_left} days left)</span>`;
        } else if (d.status === 'expiring_soon') {
          statusBadge = `<span class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/15 px-2.5 py-0.5 text-xs font-semibold text-amber-300 border border-amber-500/30">Expiring Soon (${d.days_left} days left)</span>`;
        } else {
          statusBadge = `<span class="inline-flex items-center gap-1.5 rounded-full bg-rose-500/15 px-2.5 py-0.5 text-xs font-semibold text-rose-300 border border-rose-500/30">Expired (${Math.abs(d.days_left)} days ago)</span>`;
        }

        let certSubtitle = '';
        if (d.cert_type === 'mismatch') {
          certSubtitle = `<p class="mt-0.5 text-xs text-amber-400 font-medium leading-tight"><span class="inline-block">⚠️ Name mismatch</span> <span class="inline-block">• Issued to: <span class="font-mono text-zinc-200 font-semibold">${escapeHtml(d.common_name)}</span></span></p>`;
        } else if (d.cert_type === 'wildcard') {
          certSubtitle = `<p class="mt-0.5 text-xs text-sky-400 font-medium leading-tight">Wildcard Certificate • <span class="font-mono text-zinc-200 font-semibold">${escapeHtml(d.common_name)}</span></p>`;
        } else if (d.sans_count > 1) {
          const isPrimary = d.target.toLowerCase() === d.common_name.toLowerCase();
          if (isPrimary) {
            certSubtitle = `<p class="mt-0.5 text-xs text-sky-400 font-medium leading-tight">Multi-Domain Certificate (${d.sans_count} domains covered)</p>`;
          } else {
            certSubtitle = `<p class="mt-0.5 text-xs text-sky-400 font-medium leading-tight"><span class="inline-block">Multi-Domain Certificate</span> <span class="inline-block">• Primary: <span class="font-mono text-zinc-200 font-semibold">${escapeHtml(d.common_name)}</span></span></p>`;
          }
        }

        const chips = (d.sans || []).map((s) => {
          const isTarget = s.toLowerCase() === d.target.toLowerCase();
          return `<button type="button" class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-mono transition ${
            isTarget
              ? 'bg-sky-500/25 text-sky-200 border border-sky-500/40 font-semibold'
              : 'bg-white/[0.08] text-zinc-200 border border-white/5 hover:bg-white/15 hover:text-white cursor-pointer'
          }" data-set-ssl="${escapeHtml(s)}" title="Click to inspect ${escapeHtml(s)}">${escapeHtml(s)}${isTarget ? ' (current)' : ''}</button>`;
        }).join('');

        renderSsl(`
          <!-- View 1: Certificate Summary -->
          <div class="my-auto flex flex-col items-center justify-center text-center px-1 max-w-full w-full" data-ssl-main>
            <div class="mb-1">${statusBadge}</div>
            <p class="font-mono text-xl sm:text-2xl font-bold text-white break-all select-all leading-tight">${escapeHtml(d.target)}</p>
            ${certSubtitle}
            <p class="mt-1.5 text-xs text-zinc-400 font-medium">Issuer: <span class="text-white font-semibold">${escapeHtml(d.issuer)}</span> • Valid to <span class="font-mono text-zinc-200 font-semibold">${escapeHtml(d.valid_to)}</span></p>
            ${d.sans && d.sans.length > 1 ? `
              <button type="button" class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-white/[0.08] hover:bg-white/15 border border-white/10 px-3 py-1 text-xs font-medium text-sky-300 hover:text-white transition cursor-pointer" data-show-sans>
                <span>View all ${d.sans_count} covered domains →</span>
              </button>
            ` : ''}
          </div>

          <!-- View 2: Covered Domains List (inside the same widget panel) -->
          <div class="hidden flex flex-col w-full h-full text-left" data-ssl-sans>
            <div class="flex items-center justify-between border-b border-white/[0.08] pb-1.5 mb-2 shrink-0">
              <span class="text-xs font-semibold text-zinc-200">Covered Domains (${d.sans_count})</span>
              <button type="button" class="text-xs text-sky-400 hover:text-white flex items-center gap-1 font-medium cursor-pointer transition" data-hide-sans>
                ← Back to Certificate
              </button>
            </div>
            <div class="overflow-y-auto max-h-32 pr-1 grow space-y-1">
              <div class="flex flex-wrap gap-1.5">
                ${chips}
              </div>
            </div>
            <p class="text-[11px] text-zinc-500 pt-1.5 shrink-0 border-t border-white/[0.04]">Click any domain above to inspect it</p>
          </div>
        `);
      } catch (err) {
        if (err.name === 'AbortError') return;
        sslError(err.message);
      } finally {
        if (btn) btn.disabled = false;
      }
    });
  }

  // ==========================================
  // Subnet & CIDR Calculator (Client-side)
  // ==========================================
  const subnetInput = $('#subnet-input');
  const subnetResult = $('[data-subnet-result]');
  const subnetForm = $('[data-subnet-form]');

  function intToIp(int) {
    return [
      (int >>> 24) & 255,
      (int >>> 16) & 255,
      (int >>> 8) & 255,
      int & 255
    ].join('.');
  }

  function ipToInt(ip) {
    return ip.split('.').reduce((acc, oct) => ((acc << 8) + parseInt(oct, 10)) >>> 0, 0);
  }

  function runSubnetCalc(raw, isExplicitSubmit = false) {
    if (!subnetResult) return;
    let str = (raw || '').trim();
    if (!str) {
      subnetResult.innerHTML = `
        <div class="m-auto text-center px-2 py-1">
          <p class="text-sm font-medium text-zinc-400">Enter an IP, subnet, or pick a preset</p>
          <p class="mt-1 text-xs text-zinc-500">Calculates usable range, mask, and router setup</p>
        </div>
      `;
      return;
    }

    if (!str.includes('/') && !isExplicitSubmit) {
      subnetResult.innerHTML = `
        <div class="m-auto text-center px-2 py-1">
          <p class="text-sm font-medium text-zinc-400">Pick a preset below or enter a CIDR mask</p>
          <p class="mt-1 text-xs text-zinc-500">e.g. click /24 (254 IPs) or type /24</p>
        </div>
      `;
      return;
    }

    let ipPart = str;
    let prefix = 24;

    if (str.includes('/')) {
      const parts = str.split('/');
      ipPart = parts[0].trim();
      const p = parseInt(parts[1], 10);
      if (!isNaN(p)) prefix = Math.min(32, Math.max(0, p));
    } else if (isExplicitSubmit) {
      prefix = 24;
      if (subnetInput && ipPart) {
        subnetInput.value = ipPart + '/24';
      }
    }

    if (!ipPart || ipPart.startsWith('/')) {
      ipPart = '192.168.1.1';
    }

    if (!IPV4.test(ipPart)) {
      subnetResult.innerHTML = '<p class="m-auto text-center text-sm font-medium text-rose-300">Enter a valid IPv4 (e.g. 192.168.1.1 or 10.0.0.0/24).</p>';
      return;
    }

    const ipInt = ipToInt(ipPart);
    const maskInt = prefix === 0 ? 0 : ((~0 << (32 - prefix)) >>> 0);
    const wildcardInt = (~maskInt) >>> 0;
    const networkInt = (ipInt & maskInt) >>> 0;
    const broadcastInt = (networkInt | wildcardInt) >>> 0;

    const maskIp = intToIp(maskInt);

    let firstHostInt, lastHostInt, usableHosts;
    if (prefix === 31) {
      firstHostInt = networkInt;
      lastHostInt = broadcastInt;
      usableHosts = 2;
    } else if (prefix === 32) {
      firstHostInt = networkInt;
      lastHostInt = networkInt;
      usableHosts = 1;
    } else {
      firstHostInt = (networkInt + 1) >>> 0;
      lastHostInt = (broadcastInt - 1) >>> 0;
      usableHosts = Math.max(0, Math.pow(2, 32 - prefix) - 2);
    }

    const firstHost = intToIp(firstHostInt);
    const lastHost = intToIp(lastHostInt);
    const isRouterLike = ipPart.endsWith('.1');
    const isPrivate = /^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/.test(ipPart);

    let advice = '';
    if (isRouterLike && isPrivate) {
      const startDhcp = (firstHost === ipPart) ? intToIp((firstHostInt + 1) >>> 0) : firstHost;
      advice = `Router Setup: Router at <span class="font-mono text-white font-bold bg-white/[0.09] px-1 py-0.5 rounded text-xs sm:text-[13px]">${ipPart}</span>. Set DHCP to assign <span class="font-mono text-white font-bold bg-white/[0.09] px-1 py-0.5 rounded text-xs sm:text-[13px] select-all">${startDhcp} – ${lastHost}</span> to devices.`;
    } else if (isPrivate) {
      advice = `Private LAN (RFC 1918): Safe for home/office networks. Supports <span class="font-mono text-white font-bold bg-white/[0.09] px-1 py-0.5 rounded text-xs sm:text-[13px]">${usableHosts.toLocaleString()}</span> connected devices.`;
    } else {
      advice = `Public Internet Block: Routable on open Internet. Mask: <span class="font-mono text-white font-bold bg-white/[0.09] px-1 py-0.5 rounded text-xs sm:text-[13px]">${maskIp}</span>.`;
    }

    subnetResult.innerHTML = `
      <div class="my-auto flex flex-col justify-center space-y-1.5 text-xs sm:text-[13px]">
        <div class="flex flex-wrap items-baseline justify-between border-b border-white/[0.06] pb-1 gap-x-2 gap-y-0.5">
          <span class="font-medium text-zinc-300 shrink-0 text-xs sm:text-[13px]">Usable Range</span>
          <span class="font-mono font-semibold text-white select-all text-xs sm:text-[13px] break-words">${usableHosts > 1 ? firstHost + ' – ' + lastHost : firstHost}</span>
        </div>
        <div class="flex flex-wrap items-baseline justify-between border-b border-white/[0.06] pb-1 gap-x-2 gap-y-0.5">
          <span class="font-medium text-zinc-300 shrink-0 text-xs sm:text-[13px]">Subnet Mask</span>
          <span class="font-mono font-semibold text-zinc-100 select-all text-xs sm:text-[13px]">${maskIp} <span class="font-sans font-normal text-zinc-400">(/${prefix})</span></span>
        </div>
        <div class="flex flex-wrap items-baseline justify-between gap-x-2 gap-y-0.5">
          <span class="font-medium text-zinc-300 shrink-0 text-xs sm:text-[13px]">Usable Devices</span>
          <span class="font-mono font-bold text-white select-all text-xs sm:text-[13px]">${usableHosts.toLocaleString()} hosts</span>
        </div>
        <div class="mt-1.5 rounded-lg bg-white/[0.05] px-2.5 py-1.5 text-xs text-zinc-200 leading-snug border border-white/[0.06] break-words">
          ${advice}
        </div>
      </div>
    `;
  }

  if (subnetInput) {
    subnetInput.addEventListener('input', () => runSubnetCalc(subnetInput.value));
  }
  if (subnetForm) {
    subnetForm.addEventListener('submit', (e) => {
      e.preventDefault();
      runSubnetCalc(subnetInput?.value || '', true);
    });
  }
  // Initialize subnet calc on load
  if (subnetInput) {
    runSubnetCalc(subnetInput.value);
  }

  // ==========================================
  // MAC Address Lookup
  // ==========================================
  const macForm = $('[data-mac-form]');
  const macResult = $('[data-mac-result]');

  function renderMac(html) {
    if (macResult) macResult.innerHTML = html;
  }

  function macError(msg) {
    renderMac(`<p class="m-auto text-center text-sm font-medium text-rose-300">${escapeHtml(msg)}</p>`);
  }

  if (macForm) {
    macForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const mac = (macForm.elements.mac.value || '').trim();
      if (!mac) return macError('Enter a MAC address or OUI prefix.');

      const btn = macForm.querySelector('[type=submit]');
      btn.disabled = true;
      renderMac('<div class="m-auto flex items-center justify-center gap-3 text-base font-medium text-zinc-300"><span class="spinner"></span> Looking up vendor…</div>');

      try {
        const d = await api({ action: 'mac', mac });
        const typeBadge = d.is_local
          ? `<span class="inline-flex items-center rounded-full bg-amber-500/15 px-2.5 py-0.5 text-[11px] font-semibold text-amber-300 border border-amber-500/30">Locally Administered (Private/Randomized)</span>`
          : `<span class="inline-flex items-center rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-300 border border-emerald-500/30">Globally Unique (Hardware OUI)</span>`;

        renderMac(`
          <div class="m-auto flex flex-col items-center justify-center text-center px-2 py-1 max-w-full">
            <p class="font-sans text-xl font-bold text-white break-words select-all">${escapeHtml(d.vendor)}</p>
            <p class="mt-1 font-mono text-sm text-zinc-300">${escapeHtml(d.mac)}</p>
            <div class="mt-2">
              ${typeBadge}
            </div>
          </div>
        `);
      } catch (err) {
        macError(err.message);
      } finally {
        btn.disabled = false;
      }
    });
  }
})();
