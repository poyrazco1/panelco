/* lead-scan.js — Yeni Lead Tara sihirbazı (istemci tarafı durum + render). */
(function () {
    'use strict';
    var D = window.SCAN_DATA || {};
    var $ = function (id) { return document.getElementById(id); };
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };

    var S = {
        terms: new Set(), city: '', districts: new Set(), hood: '',
        filters: {}, minRating: 0, minReviews: 0,
        package: '', priority: 'normal', rep: 0, wa: ($('waInput') ? $('waInput').value : ''),
        limit: parseInt(($('limitInput') && $('limitInput').value) || '100', 10), source: 'Lead Tarama', note: ''
    };
    // filtreleri checkbox varsayılanlarından başlat
    document.querySelectorAll('#qGrid input[data-filter]').forEach(function (cb) { S.filters[cb.getAttribute('data-filter')] = cb.checked; });

    /* ---------- ADIM 1: Sektör ---------- */
    var curMain = null, curSub = null;
    function renderMains(filter) {
        var wrap = $('sectorMains'); wrap.innerHTML = '';
        Object.keys(D.taxonomy || {}).forEach(function (main) {
            if (filter && !matchMain(main, filter)) return;
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'sector-main' + (main === curMain ? ' is-active' : '');
            b.textContent = main; b.title = main;
            b.addEventListener('click', function () { curMain = main; curSub = null; renderMains(filter); renderSubs(); });
            wrap.appendChild(b);
        });
        if (!curMain) { var first = wrap.querySelector('.sector-main'); if (first) { curMain = first.textContent; first.classList.add('is-active'); } }
        renderSubs();
    }
    function matchMain(main, f) {
        if (main.toLowerCase().indexOf(f) !== -1) return true;
        var subs = D.taxonomy[main] || {};
        return Object.keys(subs).some(function (s) { return s.toLowerCase().indexOf(f) !== -1 || (subs[s] || []).some(function (p) { return p.indexOf(f) !== -1; }); });
    }
    function renderSubs() {
        var wrap = $('sectorSubs'); wrap.innerHTML = '';
        if (!curMain || !D.taxonomy[curMain]) { $('sectorProfs').innerHTML = ''; return; }
        Object.keys(D.taxonomy[curMain]).forEach(function (sub) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'sector-sub' + (sub === curSub ? ' is-active' : '');
            b.textContent = sub; b.title = sub;
            b.addEventListener('click', function () { curSub = sub; renderSubs(); });
            wrap.appendChild(b);
        });
        if (!curSub) { var first = wrap.querySelector('.sector-sub'); if (first) { curSub = first.textContent; first.classList.add('is-active'); } }
        renderProfsFor();
    }
    function renderProfsFor() {
        var wrap = $('sectorProfs'); wrap.innerHTML = '';
        if (!curMain || !curSub) return;
        (D.taxonomy[curMain][curSub] || []).forEach(function (p) { wrap.appendChild(profChip(p)); });
    }
    function profChip(p) {
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'sel-chip-btn' + (S.terms.has(p) ? ' is-sel' : '');
        b.title = p;
        b.innerHTML = '<span class="scb-check"></span><span class="scb-text">' + esc(p) + '</span>';
        b.addEventListener('click', function () { toggleTerm(p); });
        return b;
    }
    function toggleTerm(t) { if (S.terms.has(t)) S.terms.delete(t); else S.terms.add(t); syncTermUI(); update(); }

    /* ---------- Meslekler tab ---------- */
    function renderProfGrid(filter) {
        var wrap = $('profGrid'); wrap.innerHTML = '';
        (D.professions || []).forEach(function (p) {
            if (filter && p.indexOf(filter) === -1) return;
            var row = document.createElement('label');
            row.className = 'selection-row' + (S.terms.has(p) ? ' is-sel' : '');
            row.title = p;
            row.innerHTML = '<input type="checkbox"' + (S.terms.has(p) ? ' checked' : '') + '><span class="selection-row-text">' + esc(p) + '</span>';
            row.querySelector('input').addEventListener('change', function () { toggleTerm(p); row.classList.toggle('is-sel', S.terms.has(p)); });
            wrap.appendChild(row);
        });
    }

    /* ---------- Serbest kelimeler ---------- */
    function renderSuggest() {
        var wrap = $('kwSuggest'); wrap.innerHTML = '';
        (D.suggestions || []).forEach(function (k) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'suggest-chip' + (S.terms.has(k) ? ' is-sel' : '');
            b.textContent = '+ ' + k;
            b.addEventListener('click', function () { if (!S.terms.has(k)) { S.terms.add(k); syncTermUI(); update(); } });
            wrap.appendChild(b);
        });
    }
    function addKeyword(v) { v = (v || '').trim(); if (v && !S.terms.has(v)) { S.terms.add(v); syncTermUI(); update(); } }

    /* ---------- Seçili terim chip'leri ---------- */
    function syncTermUI() {
        renderTermChips(); renderSuggest();
        // görünür grid/prof durumlarını tazele
        document.querySelectorAll('#sectorProfs .sel-chip-btn').forEach(function (b) {
            var t = b.querySelector('.scb-text').textContent; b.classList.toggle('is-sel', S.terms.has(t));
        });
        document.querySelectorAll('#profGrid .selection-row').forEach(function (r) {
            var t = r.querySelector('.selection-row-text').textContent; r.classList.toggle('is-sel', S.terms.has(t));
            var cb = r.querySelector('input'); if (cb) cb.checked = S.terms.has(t);
        });
    }
    function renderTermChips() {
        var wrap = $('termChips'); wrap.innerHTML = '';
        if (S.terms.size === 0) { wrap.innerHTML = '<span class="chips-empty">Henüz seçim yok.</span>'; return; }
        S.terms.forEach(function (t) { wrap.appendChild(chip(t, function () { S.terms.delete(t); syncTermUI(); update(); })); });
    }
    function chip(text, onRemove) {
        var c = document.createElement('span');
        c.className = 'selected-chip'; c.title = text;
        c.innerHTML = '<span class="selected-chip-text">' + esc(text) + '</span><button type="button" class="selected-chip-x" aria-label="Kaldır">×</button>';
        c.querySelector('.selected-chip-x').addEventListener('click', onRemove);
        return c;
    }

    /* ---------- ADIM 2: Bölge ---------- */
    function onCityChange() {
        S.city = $('citySel').value;
        var q = $('regionQuick'), pd = $('popularDistricts');
        pd.innerHTML = '';
        var list = (D.popular && D.popular[S.city]) || [];
        if (S.city && list.length) {
            q.hidden = false;
            list.forEach(function (d) {
                var b = document.createElement('button');
                b.type = 'button'; b.className = 'region-pill' + (S.districts.has(d) ? ' is-sel' : '');
                b.textContent = d;
                b.addEventListener('click', function () { toggleDistrict(d); });
                pd.appendChild(b);
            });
        } else { q.hidden = true; }
        update();
    }
    function toggleDistrict(d) { if (S.districts.has(d)) S.districts.delete(d); else S.districts.add(d); syncRegionUI(); update(); }
    function syncRegionUI() {
        var wrap = $('regionChips'); wrap.innerHTML = '';
        if (S.districts.size === 0) { wrap.innerHTML = '<span class="chips-empty">Henüz bölge seçilmedi.</span>'; }
        else { S.districts.forEach(function (d) { wrap.appendChild(chip(d, function () { S.districts.delete(d); syncRegionUI(); update(); })); }); }
        document.querySelectorAll('#popularDistricts .region-pill').forEach(function (b) { b.classList.toggle('is-sel', S.districts.has(b.textContent)); });
    }

    /* ---------- Önizleme + özet + doğrulama ---------- */
    function regionCount() { return S.districts.size || (S.city ? 1 : 0); }
    function comboCount() { return S.terms.size * (S.districts.size || (S.city ? 1 : 0)); }
    function activeFilters() { return Object.keys(S.filters).filter(function (k) { return S.filters[k]; }); }

    function update() {
        // sales alanlarını oku
        S.package = $('pkgSel').value; S.priority = $('prioSel').value; S.rep = parseInt($('repSel').value || '0', 10);
        S.wa = $('waInput').value; S.limit = parseInt($('limitInput').value || '0', 10);
        S.source = $('sourceInput').value.trim() || 'Lead Tarama'; S.note = $('noteInput').value;
        S.minRating = parseFloat($('minRating').value || '0'); S.minReviews = parseInt($('minReviews').value || '0', 10);
        S.hood = $('hoodInput').value.trim();

        $('tsSector').textContent = S.terms.size;
        $('tsRegion').textContent = regionCount();
        $('tsCombo').textContent = comboCount();
        $('tsLimit').textContent = S.limit || 0;

        $('sumTermCount').textContent = S.terms.size;
        $('sumRegionCount').textContent = regionCount();
        $('sumCombo').textContent = comboCount();
        $('sumLimit').textContent = S.limit || 0;
        $('sumPkg').textContent = S.package ? (D.packages[S.package] || S.package) : '—';
        $('sumRep').textContent = S.rep ? (D.personnel[S.rep] || ('#' + S.rep)) : '—';

        fillChips($('sumTerms'), Array.from(S.terms));
        fillChips($('sumRegions'), Array.from(S.districts).concat(S.city && S.districts.size === 0 ? [S.city] : []));
        fillChips($('sumFilters'), activeFilters().map(function (k) { return D.filters[k] || k; }), true);

        // doğrulama
        var missing = [];
        if (S.terms.size === 0) missing.push('En az 1 sektör veya anahtar kelime seçmelisin.');
        if (regionCount() === 0) missing.push('En az 1 bölge seçmelisin.');
        var ok = missing.length === 0;
        $('sumMissing').innerHTML = ok ? '' : missing.map(function (m) { return '<div class="miss-item">' + esc(m) + '</div>'; }).join('');
        $('startBtn').disabled = !ok; $('startBtnMain').disabled = !ok;

        renderPreview();
    }
    function fillChips(wrap, arr, small) {
        wrap.innerHTML = '';
        if (!arr.length) { wrap.innerHTML = '<span class="chips-empty">—</span>'; return; }
        arr.slice(0, 40).forEach(function (t) {
            var s = document.createElement('span'); s.className = 'mini-chip' + (small ? ' mini-chip-soft' : ''); s.textContent = t; s.title = t; wrap.appendChild(s);
        });
    }
    function renderPreview() {
        var regions = Array.from(S.districts);
        if (!regions.length && S.city) regions = [S.city];
        var combos = [];
        S.terms.forEach(function (t) { (regions.length ? regions : ['(bölge yok)']).forEach(function (r) { if (combos.length < 10) combos.push(t + ' ' + r + (S.city && r !== S.city ? ' ' + S.city : '')); }); });
        var html = '';
        html += '<div class="pv-row"><span class="pv-k">Sektör / kelime</span><span class="pv-v">' + S.terms.size + ' adet</span></div>';
        html += '<div class="pv-row"><span class="pv-k">Bölge</span><span class="pv-v">' + regionCount() + ' adet</span></div>';
        html += '<div class="pv-row"><span class="pv-k">Arama kombinasyonu</span><span class="pv-v">' + comboCount() + '</span></div>';
        html += '<div class="pv-row"><span class="pv-k">Filtreler</span><span class="pv-v">' + activeFilters().length + ' aktif</span></div>';
        if (S.minRating > 0) html += '<div class="pv-row"><span class="pv-k">Min. puan</span><span class="pv-v">' + S.minRating + '+</span></div>';
        html += '<div class="pv-row"><span class="pv-k">Kayıt limiti</span><span class="pv-v">' + (S.limit || 0) + '</span></div>';
        html += '<div class="pv-row"><span class="pv-k">Paket</span><span class="pv-v">' + esc(S.package ? (D.packages[S.package] || S.package) : '—') + '</span></div>';
        html += '<div class="pv-row"><span class="pv-k">Temsilci</span><span class="pv-v">' + esc(S.rep ? (D.personnel[S.rep] || '#' + S.rep) : '—') + '</span></div>';
        if (combos.length) { html += '<div class="pv-combos"><span class="pv-k">Örnek aramalar</span><div>' + combos.map(function (c) { return '<span class="mini-chip">' + esc(c) + '</span>'; }).join('') + '</div></div>'; }
        $('previewBox').innerHTML = html;
    }

    /* ---------- Başlat / Kaydet / Temizle ---------- */
    function start() {
        if ($('startBtn').disabled) return;
        update();
        $('fTerms').value = JSON.stringify(Array.from(S.terms));
        $('fCity').value = S.city;
        $('fDistricts').value = JSON.stringify(Array.from(S.districts));
        $('fHood').value = S.hood;
        $('fFilters').value = JSON.stringify(S.filters);
        $('fMinRating').value = S.minRating; $('fMinReviews').value = S.minReviews;
        $('fPackage').value = S.package; $('fPriority').value = S.priority; $('fRep').value = S.rep || '';
        $('fWa').value = S.wa; $('fLimit').value = S.limit; $('fSource').value = S.source; $('fNote').value = S.note;
        $('scanPost').submit();
    }
    function saveDraft() {
        try {
            localStorage.setItem('leadScanDraft', JSON.stringify({
                terms: Array.from(S.terms), city: S.city, districts: Array.from(S.districts), hood: S.hood,
                filters: S.filters, minRating: S.minRating, minReviews: S.minReviews, package: S.package,
                priority: S.priority, rep: S.rep, wa: S.wa, limit: S.limit, source: S.source, note: S.note
            }));
            flash('Seçim tarayıcıya kaydedildi.');
        } catch (e) {}
    }
    function loadDraft() {
        try {
            var raw = localStorage.getItem('leadScanDraft'); if (!raw) return;
            var d = JSON.parse(raw);
            (d.terms || []).forEach(function (t) { S.terms.add(t); });
            S.city = d.city || ''; (d.districts || []).forEach(function (x) { S.districts.add(x); });
            S.hood = d.hood || '';
            if (d.filters) Object.keys(d.filters).forEach(function (k) { S.filters[k] = d.filters[k]; var cb = document.querySelector('#qGrid input[data-filter="' + k + '"]'); if (cb) { cb.checked = d.filters[k]; cb.closest('.qfilter').classList.toggle('is-on', d.filters[k]); } });
            if (d.package) $('pkgSel').value = d.package; if (d.priority) $('prioSel').value = d.priority;
            if (d.rep) $('repSel').value = d.rep; if (d.wa != null) $('waInput').value = d.wa;
            if (d.limit) $('limitInput').value = d.limit; if (d.source) $('sourceInput').value = d.source;
            if (d.note != null) $('noteInput').value = d.note; if (d.hood) $('hoodInput').value = d.hood;
            if (d.minReviews) $('minReviews').value = d.minReviews; if (d.minRating) $('minRating').value = d.minRating;
            if (S.city) { $('citySel').value = S.city; }
        } catch (e) {}
    }
    function clearAll() {
        S.terms.clear(); S.districts.clear(); S.city = ''; $('citySel').value = ''; $('hoodInput').value = '';
        try { localStorage.removeItem('leadScanDraft'); } catch (e) {}
        onCityChange(); syncTermUI(); syncRegionUI(); update();
    }
    function flash(msg) {
        var el = $('scanFlash'); if (!el) { el = document.createElement('div'); el.id = 'scanFlash'; el.className = 'scan-flash'; document.body.appendChild(el); }
        el.textContent = msg; el.classList.add('show'); setTimeout(function () { el.classList.remove('show'); }, 1800);
    }

    /* ---------- Bağlantılar ---------- */
    // tab geçişi
    document.querySelectorAll('.scan-tab').forEach(function (t) {
        t.addEventListener('click', function () {
            document.querySelectorAll('.scan-tab').forEach(function (x) { x.classList.remove('is-active'); });
            document.querySelectorAll('.scan-pane').forEach(function (x) { x.classList.remove('is-active'); });
            t.classList.add('is-active');
            document.querySelector('.scan-pane[data-pane="' + t.getAttribute('data-tab') + '"]').classList.add('is-active');
        });
    });
    $('secSearch').addEventListener('input', function () { renderMains(this.value.toLowerCase().trim()); });
    $('profSearch').addEventListener('input', function () { renderProfGrid(this.value.toLowerCase().trim()); });
    $('profAll').addEventListener('click', function () {
        var f = $('profSearch').value.toLowerCase().trim();
        (D.professions || []).forEach(function (p) { if (!f || p.indexOf(f) !== -1) S.terms.add(p); }); syncTermUI(); update();
    });
    $('profClear').addEventListener('click', function () { (D.professions || []).forEach(function (p) { S.terms.delete(p); }); syncTermUI(); update(); });
    $('kwAdd').addEventListener('click', function () { addKeyword($('kwInput').value); $('kwInput').value = ''; });
    $('kwInput').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addKeyword(this.value); this.value = ''; } });
    $('clearTerms').addEventListener('click', function () { S.terms.clear(); syncTermUI(); update(); });

    $('citySel').addEventListener('change', onCityChange);
    $('distAll').addEventListener('click', function () { ((D.popular && D.popular[S.city]) || []).forEach(function (d) { S.districts.add(d); }); syncRegionUI(); update(); });
    $('distClear').addEventListener('click', function () { S.districts.clear(); syncRegionUI(); update(); });
    $('manualRegionAdd').addEventListener('click', function () { var v = $('manualRegion').value.trim(); if (v) { S.districts.add(v); $('manualRegion').value = ''; syncRegionUI(); update(); } });
    $('manualRegion').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); var v = this.value.trim(); if (v) { S.districts.add(v); this.value = ''; syncRegionUI(); update(); } } });

    $('qGrid').addEventListener('change', function (e) {
        var cb = e.target.closest('input[data-filter]'); if (!cb) return;
        S.filters[cb.getAttribute('data-filter')] = cb.checked;
        cb.closest('.qfilter').classList.toggle('is-on', cb.checked); update();
    });
    ['pkgSel', 'prioSel', 'repSel', 'waInput', 'limitInput', 'sourceInput', 'noteInput', 'minRating', 'minReviews', 'hoodInput'].forEach(function (id) {
        var el = $(id); if (el) el.addEventListener('input', update);
        if (el && el.tagName === 'SELECT') el.addEventListener('change', update);
    });
    $('startBtn').addEventListener('click', start);
    $('startBtnMain').addEventListener('click', start);
    $('saveBtn').addEventListener('click', saveDraft);
    $('clearAllBtn').addEventListener('click', clearAll);

    /* ---------- İlk render ---------- */
    loadDraft();
    renderMains(''); renderProfGrid(''); renderSuggest(); syncTermUI(); syncRegionUI();
    if (S.city) onCityChange();
    update();
})();
