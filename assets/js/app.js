/* =========================================================================
   PoyrazTech — ortak JavaScript (sade, gerektiği kadar)
   ========================================================================= */
(function () {
    'use strict';

    var body = document.body;

    /* ---- Mobil sidebar aç/kapat ---- */
    var menuBtn = document.getElementById('menuBtn');
    var overlay = document.getElementById('sidebarOverlay');

    function openNav() {
        body.classList.add('nav-open');
        if (overlay) { overlay.hidden = false; }
        if (menuBtn) { menuBtn.setAttribute('aria-expanded', 'true'); }
    }
    function closeNav() {
        body.classList.remove('nav-open');
        if (overlay) { overlay.hidden = true; }
        if (menuBtn) { menuBtn.setAttribute('aria-expanded', 'false'); }
    }
    function toggleNav() {
        if (body.classList.contains('nav-open')) { closeNav(); } else { openNav(); }
    }
    if (menuBtn) { menuBtn.addEventListener('click', toggleNav); }
    if (overlay) { overlay.addEventListener('click', closeNav); }
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') { closeNav(); }
    });
    var navLinks = document.querySelectorAll('.sidebar-nav a');
    for (var n = 0; n < navLinks.length; n++) {
        navLinks[n].addEventListener('click', function () {
            if (window.innerWidth <= 900) { closeNav(); }
        });
    }

    /* ---- Şifre göster/gizle ----
       <button data-toggle-password data-target="#password">Göster</button> */
    var toggles = document.querySelectorAll('[data-toggle-password]');
    for (var i = 0; i < toggles.length; i++) {
        toggles[i].addEventListener('click', function () {
            var sel = this.getAttribute('data-target');
            var input = sel ? document.querySelector(sel) : null;
            if (!input) { return; }
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            this.textContent = show ? 'Gizle' : 'Göster';
            this.setAttribute('aria-pressed', show ? 'true' : 'false');
        });
    }

    /* ---- Çift gönderim kilidi ---- */
    var lockForms = document.querySelectorAll('form[data-lock-on-submit]');
    for (var j = 0; j < lockForms.length; j++) {
        lockForms[j].addEventListener('submit', function () {
            var btn = this.querySelector('button[type="submit"], input[type="submit"]');
            if (btn) { setTimeout(function () { btn.disabled = true; }, 0); }
        });
    }

    /* ---- Silme onayı ---- */
    var confirmForms = document.querySelectorAll('form[data-confirm]');
    for (var k = 0; k < confirmForms.length; k++) {
        confirmForms[k].addEventListener('submit', function (ev) {
            var msg = this.getAttribute('data-confirm') || 'Bu işlemi onaylıyor musunuz?';
            if (!window.confirm(msg)) { ev.preventDefault(); }
        });
    }

    /* ---- Kur çevirici (anlık hesap; veri kur.php'den gelen PANEL_RATES) ---- */
    var conv = document.getElementById('converter');
    if (conv && window.PANEL_RATES) {
        var rates = window.PANEL_RATES;
        var amountEl = document.getElementById('cv-amount');
        var fromEl = document.getElementById('cv-from');
        var toEl = document.getElementById('cv-to');
        var outEl = document.getElementById('cv-result');
        var swapEl = document.getElementById('cv-swap');

        var calc = function () {
            var amount = parseFloat(String(amountEl.value).replace(',', '.'));
            var from = fromEl.value, to = toEl.value;
            if (isNaN(amount) || !rates[from] || !rates[to]) {
                outEl.textContent = '—';
                return;
            }
            var result = amount * (rates[to] / rates[from]);
            outEl.textContent = result.toLocaleString('tr-TR', {
                minimumFractionDigits: 2, maximumFractionDigits: 2
            }) + ' ' + to;
        };

        conv.addEventListener('submit', function (ev) { ev.preventDefault(); calc(); });
        if (amountEl) { amountEl.addEventListener('input', calc); }
        if (fromEl) { fromEl.addEventListener('change', calc); }
        if (toEl) { toEl.addEventListener('change', calc); }
        if (swapEl) {
            swapEl.addEventListener('click', function () {
                var t = fromEl.value; fromEl.value = toEl.value; toEl.value = t; calc();
            });
        }
        calc();
    }

    /* ---- Header kur mini widget ---- */
    var kurTrigger = document.getElementById('kurTrigger');
    var kurPop = document.getElementById('kurPop');
    if (kurTrigger && kurPop) {
        var kwAmount = document.getElementById('kw-amount');
        var kwFrom = document.getElementById('kw-from');
        var kwTo = document.getElementById('kw-to');
        var kwResult = document.getElementById('kw-result');
        var kwUpdated = document.getElementById('kw-updated');
        var kwSwap = document.getElementById('kw-swap');
        var kwRefresh = document.getElementById('kw-refresh');
        var hdrUsd = document.getElementById('hdrUsd');
        var hdrEur = document.getElementById('hdrEur');

        var kwRates = function () { return (window.KUR_RATES && window.KUR_RATES.rates) ? window.KUR_RATES.rates : null; };
        var fmt2 = function (v) { return v.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

        var kwCalc = function () {
            var r = kwRates();
            var amt = parseFloat(String(kwAmount.value).replace(',', '.'));
            if (!r || isNaN(amt) || !r[kwFrom.value] || !r[kwTo.value]) { kwResult.textContent = '—'; return; }
            var out = amt * (r[kwTo.value] / r[kwFrom.value]);
            kwResult.textContent = fmt2(out) + ' ' + kwTo.value;
        };
        var kwUpdateHeader = function (r) {
            try {
                if (hdrUsd && r.USD && r.TRY) { hdrUsd.textContent = fmt2(r.TRY / r.USD); }
                if (hdrEur && r.EUR && r.TRY) { hdrEur.textContent = fmt2(r.TRY / r.EUR); }
            } catch (e) { /* yoksay */ }
        };
        var kwOpen = function () { kurPop.hidden = false; kurTrigger.setAttribute('aria-expanded', 'true'); kwCalc(); };
        var kwClose = function () { kurPop.hidden = true; kurTrigger.setAttribute('aria-expanded', 'false'); };

        kurTrigger.addEventListener('click', function (e) { e.stopPropagation(); if (kurPop.hidden) { kwOpen(); } else { kwClose(); } });
        kurPop.addEventListener('click', function (e) { e.stopPropagation(); });
        document.addEventListener('click', function () { if (!kurPop.hidden) { kwClose(); } });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !kurPop.hidden) { kwClose(); } });

        [kwAmount, kwFrom, kwTo].forEach(function (el) { if (el) { el.addEventListener('input', kwCalc); el.addEventListener('change', kwCalc); } });
        if (kwSwap) { kwSwap.addEventListener('click', function () { var t = kwFrom.value; kwFrom.value = kwTo.value; kwTo.value = t; kwCalc(); }); }

        if (kwRefresh) {
            kwRefresh.addEventListener('click', function () {
                if (!window.KUR_ENDPOINT) { return; }
                kwRefresh.disabled = true;
                var url = window.KUR_ENDPOINT + (window.KUR_ENDPOINT.indexOf('?') === -1 ? '?' : '&') + 'format=json&refresh=1';
                fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (resp) { return resp.ok ? resp.json() : Promise.reject(); })
                    .then(function (data) {
                        if (data && data.rates) {
                            window.KUR_RATES = { rates: data.rates, updated: data.fetched_at || '' };
                            kwCalc();
                            kwUpdateHeader(data.rates);
                            if (kwUpdated) { kwUpdated.textContent = 'Son güncelleme: ' + (data.fetched_at || data.date || ''); }
                        } else {
                            kwResult.textContent = 'Kur bilgisi alınamadı';
                        }
                    })
                    .catch(function () { kwResult.textContent = 'Kur bilgisi alınamadı'; })
                    .finally(function () { kwRefresh.disabled = false; });
            });
        }
    }

    /* ---- Kullanıcı arayüz tercihleri: tema + sidebar ---- */
    var PREF_ICONS = {
        moon: '<svg class="icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>',
        sun: '<svg class="icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>',
        monitor: '<svg class="icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>'
    };

    function postPref(action, value) {
        if (!window.PREF_ENDPOINT || !window.CSRF_TOKEN) { return; }
        var fd = new FormData();
        fd.append('_csrf', window.CSRF_TOKEN);
        fd.append('action', action);
        fd.append('value', value);
        fetch(window.PREF_ENDPOINT, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
            .catch(function () { /* UI zaten uygulandı; sessizce geç */ });
    }

    /* Tema seçimi (dropdown) */
    var themeBtn = document.getElementById('themeBtn');
    var themePop = document.getElementById('themePop');
    var themeIconEl = document.getElementById('themeIcon');
    var themeIconName = { light: 'moon', dark: 'sun', system: 'monitor' };
    if (themeBtn && themePop) {
        var closeTheme = function () { themePop.hidden = true; themeBtn.setAttribute('aria-expanded', 'false'); };
        var openTheme = function () { themePop.hidden = false; themeBtn.setAttribute('aria-expanded', 'true'); };
        themeBtn.addEventListener('click', function (e) { e.stopPropagation(); if (themePop.hidden) { openTheme(); } else { closeTheme(); } });
        themePop.addEventListener('click', function (e) { e.stopPropagation(); });
        document.addEventListener('click', function () { if (!themePop.hidden) { closeTheme(); } });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !themePop.hidden) { closeTheme(); } });
        var opts = themePop.querySelectorAll('[data-theme-value]');
        opts.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var val = btn.getAttribute('data-theme-value');
                document.documentElement.setAttribute('data-theme', val);
                if (themeIconEl && PREF_ICONS[themeIconName[val]]) { themeIconEl.innerHTML = PREF_ICONS[themeIconName[val]]; }
                opts.forEach(function (b) { b.removeAttribute('aria-checked'); });
                btn.setAttribute('aria-checked', 'true');
                window.UI_THEME = val;
                closeTheme();
                postPref('set_theme', val);
            });
        });
    }

    /* Sidebar durumu (döngü: geniş -> dar -> gizli) */
    var sidebarBtn = document.getElementById('sidebarBtn');
    if (sidebarBtn) {
        var sbOrder = ['expanded', 'collapsed', 'hidden'];
        sidebarBtn.addEventListener('click', function () {
            var cur = window.UI_SIDEBAR || 'expanded';
            var idx = sbOrder.indexOf(cur); if (idx < 0) { idx = 0; }
            var next = sbOrder[(idx + 1) % sbOrder.length];
            document.body.classList.remove('sidebar-expanded', 'sidebar-collapsed', 'sidebar-hidden');
            document.body.classList.add('sidebar-' + next);
            window.UI_SIDEBAR = next;
            postPref('set_sidebar_state', next);
        });
    }
})();

/* ---- Header bildirim dropdown (İK) ---- */
(function () {
    'use strict';
    var trigger = document.getElementById('notifTrigger');
    var pop = document.getElementById('notifPop');
    if (!trigger || !pop) { return; }
    var markAll = document.getElementById('notifMarkAll');
    var badge = document.getElementById('notifBadge');

    function open() { pop.hidden = false; trigger.setAttribute('aria-expanded', 'true'); }
    function close() { pop.hidden = true; trigger.setAttribute('aria-expanded', 'false'); }

    trigger.addEventListener('click', function (e) { e.stopPropagation(); if (pop.hidden) { open(); } else { close(); } });
    pop.addEventListener('click', function (e) { e.stopPropagation(); });
    document.addEventListener('click', function () { if (!pop.hidden) { close(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !pop.hidden) { close(); } });

    if (markAll) {
        markAll.addEventListener('click', function () {
            if (!window.NOTIF_ENDPOINT || !window.CSRF_TOKEN) { return; }
            var fd = new FormData();
            fd.append('_csrf', window.CSRF_TOKEN);
            fd.append('action', 'mark_all');
            fetch(window.NOTIF_ENDPOINT, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (data) {
                    if (data && data.ok) {
                        if (badge) { badge.remove(); }
                        pop.querySelectorAll('.notif-row.is-unread').forEach(function (el) { el.classList.remove('is-unread'); });
                    }
                })
                .catch(function () {});
        });
    }
}());
