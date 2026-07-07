/* assets/js/quotes.js
 * Teklif/sipariş satır yönetimi + T-Soft AJAX ürün arama + canlı toplam.
 * Toplam hesabı sunucuda otoritedir; buradaki hesap yalnızca önizlemedir
 * ve includes/quotes.php quote_compute_totals ile aynı mantığı izler.
 */
(function () {
    'use strict';
    var root = document.getElementById('quoteForm');
    if (!root) { return; }

    var tbody = root.querySelector('#lineRows');
    var tmpl = document.getElementById('lineTemplate');
    var csrf = (root.querySelector('input[name="_csrf"]') || {}).value || '';
    var searchUrl = window.TSOFT_SEARCH_URL || '';

    function num(v) { v = parseFloat(String(v == null ? '' : v).replace(',', '.')); return isFinite(v) ? v : 0; }
    function money(v) { return v.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function rowInputs(tr) {
        return {
            qty: tr.querySelector('[name="item_qty[]"]'),
            price: tr.querySelector('[name="item_price[]"]'),
            vat: tr.querySelector('[name="item_vat[]"]'),
            disc: tr.querySelector('[name="item_disc[]"]'),
            total: tr.querySelector('.line-total')
        };
    }

    function recalc() {
        var vatMode = (root.querySelector('[name="vat_mode"]') || {}).value || 'excl';
        var subtotal = 0, vatSum = 0;
        tbody.querySelectorAll('tr').forEach(function (tr) {
            var f = rowInputs(tr);
            if (!f.qty) { return; }
            var qty = num(f.qty.value), price = num(f.price.value), rate = num(f.vat.value);
            var disc = Math.min(100, Math.max(0, num(f.disc.value)));
            var gross = qty * price * (1 - disc / 100);
            var net, vat;
            if (vatMode === 'incl') { net = rate > 0 ? gross / (1 + rate / 100) : gross; vat = gross - net; }
            else { net = gross; vat = net * rate / 100; }
            subtotal += net; vatSum += vat;
            if (f.total) { f.total.textContent = money(net); }
        });

        var dtype = (root.querySelector('[name="discount_type"]') || {}).value || 'none';
        var dval = num((root.querySelector('[name="discount_value"]') || {}).value);
        var discountTotal = 0;
        if (dtype === 'percent') { discountTotal = subtotal * Math.min(100, Math.max(0, dval)) / 100; }
        else if (dtype === 'amount') { discountTotal = Math.min(subtotal, Math.max(0, dval)); }
        var taxable = subtotal - discountTotal;
        if (subtotal > 0 && discountTotal > 0) { vatSum = vatSum * (taxable / subtotal); }
        var grand = taxable + vatSum;

        set('sumSubtotal', money(subtotal));
        set('sumDiscount', money(discountTotal));
        set('sumVat', money(vatSum));
        set('sumGrand', money(grand));
    }

    function set(id, txt) { var el = document.getElementById(id); if (el) { el.textContent = txt; } }

    function addRow(data) {
        data = data || {};
        var frag = tmpl.content.cloneNode(true);
        var tr = frag.querySelector('tr');
        var map = {
            'item_code[]': data.code, 'item_barcode[]': data.barcode, 'item_name[]': data.name,
            'item_brand[]': data.brand, 'item_price[]': data.price, 'item_vat[]': data.vat, 'item_qty[]': data.qty
        };
        Object.keys(map).forEach(function (n) {
            var inp = tr.querySelector('[name="' + n + '"]');
            if (inp && map[n] != null && map[n] !== '') { inp.value = map[n]; }
        });
        tbody.appendChild(tr);
        recalc();
    }

    // Olaylar
    root.addEventListener('input', function (e) {
        if (e.target.closest('#lineRows') || e.target.name === 'discount_value' || e.target.name === 'discount_type' || e.target.name === 'vat_mode') {
            recalc();
        }
    });
    root.addEventListener('change', function (e) {
        if (e.target.name === 'discount_type' || e.target.name === 'vat_mode') { recalc(); }
    });
    root.addEventListener('click', function (e) {
        if (e.target.closest('.line-remove')) {
            e.preventDefault();
            var tr = e.target.closest('tr');
            if (tr) { tr.remove(); recalc(); }
        }
    });
    var addBtn = document.getElementById('addLineBtn');
    if (addBtn) { addBtn.addEventListener('click', function () { addRow(); }); }

    // T-Soft arama
    var tsBtn = document.getElementById('tsSearchBtn');
    var tsInput = document.getElementById('tsSearchInput');
    var tsBy = document.getElementById('tsSearchBy');
    var tsResults = document.getElementById('tsResults');

    function tsoftSearch() {
        if (!searchUrl || !tsInput) { return; }
        var q = tsInput.value.trim();
        if (q.length < 2) { tsResults.innerHTML = '<div class="ts-note">En az 2 karakter girin.</div>'; return; }
        tsResults.innerHTML = '<div class="ts-note">Aranıyor…</div>';
        var body = 'q=' + encodeURIComponent(q) + '&by=' + encodeURIComponent(tsBy ? tsBy.value : 'all') + '&_csrf=' + encodeURIComponent(csrf);
        fetch(searchUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: body })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) { tsResults.innerHTML = '<div class="ts-note ts-error">' + escapeHtml(d.error || 'Ürün araması başarısız.') + '</div>'; return; }
                if (!d.products || !d.products.length) { tsResults.innerHTML = '<div class="ts-note">Ürün bulunamadı. Manuel satır ekleyebilirsiniz.</div>'; return; }
                var html = '<table class="table ts-table"><thead><tr><th>Kod</th><th>Ürün</th><th>Marka</th><th>Fiyat</th><th></th></tr></thead><tbody>';
                d.products.forEach(function (p, i) {
                    html += '<tr><td>' + escapeHtml(p.code) + '</td><td>' + escapeHtml(p.name) + '</td><td>' + escapeHtml(p.brand) + '</td><td>' + escapeHtml((p.price || '') + ' ' + (p.currency || '')) + '</td>'
                         + '<td><button type="button" class="btn btn-xs ts-add" data-i="' + i + '">Ekle</button></td></tr>';
                });
                html += '</tbody></table>';
                tsResults.innerHTML = html;
                tsResults.querySelectorAll('.ts-add').forEach(function (b) {
                    b.addEventListener('click', function () {
                        var p = d.products[parseInt(b.getAttribute('data-i'), 10)];
                        addRow({ code: p.code, barcode: p.barcode, name: p.name, brand: p.brand, price: p.price });
                    });
                });
            })
            .catch(function () { tsResults.innerHTML = '<div class="ts-note ts-error">Bağlantı hatası.</div>'; });
    }
    if (tsBtn) { tsBtn.addEventListener('click', tsoftSearch); }
    if (tsInput) { tsInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); tsoftSearch(); } }); }

    function escapeHtml(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

    // İlk yükte hesap + en az bir satır
    if (!tbody.querySelector('tr')) { addRow(); }
    recalc();
})();
