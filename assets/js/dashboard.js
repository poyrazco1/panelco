(function () {
    'use strict';

    var grid = document.getElementById('dashGrid');
    if (!grid) { return; }

    var editBtn = document.getElementById('dashEditBtn');
    var editActions = document.getElementById('dashEditActions');
    var saveBtn = document.getElementById('dashSaveBtn');
    var cancelBtn = document.getElementById('dashCancelBtn');
    var resetBtn = document.getElementById('dashResetBtn');
    var emptyBox = document.getElementById('dashEmpty');

    var SIZES = ['small', 'medium', 'wide', 'full'];
    var editing = false;
    var dragEl = null;

    function widgets() { return Array.prototype.slice.call(grid.querySelectorAll('.dashboard-widget')); }

    function updateEmpty() {
        if (!emptyBox) { return; }
        var anyVisible = widgets().some(function (w) { return w.getAttribute('data-visible') === '1'; });
        // Düzenleme modunda gizli widget'lar da görünür olduğundan boş mesajı gizle
        emptyBox.hidden = editing || anyVisible;
    }

    function setSizeClass(w, size) {
        SIZES.forEach(function (s) { w.classList.remove('widget-' + s); });
        w.classList.add('widget-' + size);
        w.setAttribute('data-size', size);
    }

    function enterEdit() {
        editing = true;
        document.body.classList.add('dash-editing');
        if (editBtn) { editBtn.hidden = true; }
        if (editActions) { editActions.hidden = false; }
        widgets().forEach(function (w) { w.setAttribute('draggable', 'true'); });
        updateEmpty();
    }

    function reloadPage() { window.location.reload(); }

    function buildLayout() {
        var out = [];
        widgets().forEach(function (w, i) {
            out.push({
                id: w.getAttribute('data-widget-id'),
                visible: w.getAttribute('data-visible') === '1',
                size: w.getAttribute('data-size') || 'small',
                order: i + 1
            });
        });
        return { widgets: out };
    }

    function postDash(action, layoutJson) {
        if (!window.DASH_ENDPOINT || !window.CSRF_TOKEN) { return Promise.reject(); }
        var fd = new FormData();
        fd.append('_csrf', window.CSRF_TOKEN);
        fd.append('action', action);
        if (layoutJson != null) { fd.append('layout', layoutJson); }
        return fetch(window.DASH_ENDPOINT, {
            method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' }
        }).then(function (r) { return r.ok ? r.json() : Promise.reject(); });
    }

    /* ---- Kontroller (event delegation) ---- */
    grid.addEventListener('click', function (e) {
        if (!editing) { return; }
        var visBtn = e.target.closest('.widget-vis');
        var moveBtn = e.target.closest('.widget-move');
        var w = e.target.closest('.dashboard-widget');
        if (!w) { return; }

        if (visBtn) {
            var vis = w.getAttribute('data-visible') === '1';
            w.setAttribute('data-visible', vis ? '0' : '1');
            w.classList.toggle('is-off', vis);
            visBtn.innerHTML = window.__dashIcons ? window.__dashIcons[vis ? 'eyeOff' : 'eye'] : visBtn.innerHTML;
            updateEmpty();
            return;
        }
        if (moveBtn) {
            var dir = moveBtn.getAttribute('data-dir');
            if (dir === 'up' && w.previousElementSibling) {
                grid.insertBefore(w, w.previousElementSibling);
            } else if (dir === 'down' && w.nextElementSibling) {
                grid.insertBefore(w.nextElementSibling, w);
            }
        }
    });

    grid.addEventListener('change', function (e) {
        if (!editing) { return; }
        var sel = e.target.closest('.widget-size-sel');
        if (!sel) { return; }
        var w = e.target.closest('.dashboard-widget');
        if (!w) { return; }
        var size = SIZES.indexOf(sel.value) !== -1 ? sel.value : 'small';
        setSizeClass(w, size);
    });

    /* ---- Sürükle-bırak (masaüstü) ---- */
    grid.addEventListener('dragstart', function (e) {
        if (!editing) { return; }
        dragEl = e.target.closest('.dashboard-widget');
        if (dragEl) { dragEl.classList.add('is-dragging'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', ''); } catch (x) {} }
    });
    grid.addEventListener('dragend', function () {
        if (dragEl) { dragEl.classList.remove('is-dragging'); }
        dragEl = null;
    });
    grid.addEventListener('dragover', function (e) {
        if (!editing || !dragEl) { return; }
        e.preventDefault();
        var after = getDragAfterElement(e.clientX, e.clientY);
        if (after == null) { grid.appendChild(dragEl); }
        else if (after !== dragEl) { grid.insertBefore(dragEl, after); }
    });

    function getDragAfterElement(x, y) {
        var els = widgets().filter(function (w) { return w !== dragEl; });
        var closest = null, closestDist = Number.POSITIVE_INFINITY;
        for (var i = 0; i < els.length; i++) {
            var box = els[i].getBoundingClientRect();
            var cx = box.left + box.width / 2;
            var cy = box.top + box.height / 2;
            // imleç, elemanın merkezinden önce mi? (satır sonra sütun)
            if (y < cy || (Math.abs(y - cy) < box.height / 2 && x < cx)) {
                var dist = Math.hypot(x - cx, y - cy);
                if (dist < closestDist) { closestDist = dist; closest = els[i]; }
            }
        }
        return closest;
    }

    /* ---- Üst aksiyonlar ---- */
    if (editBtn) { editBtn.addEventListener('click', enterEdit); }
    if (cancelBtn) { cancelBtn.addEventListener('click', reloadPage); }
    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            saveBtn.disabled = true;
            postDash('save_layout', JSON.stringify(buildLayout()))
                .then(function (data) { if (data && data.ok) { reloadPage(); } else { saveBtn.disabled = false; alert('Kaydedilemedi. Lütfen tekrar deneyin.'); } })
                .catch(function () { saveBtn.disabled = false; alert('Kaydedilemedi. Lütfen tekrar deneyin.'); });
        });
    }
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (!window.confirm('Dashboard düzeni varsayılana döndürülsün mü?')) { return; }
            resetBtn.disabled = true;
            postDash('reset_layout', null)
                .then(function (data) { if (data && data.ok) { reloadPage(); } else { resetBtn.disabled = false; } })
                .catch(function () { resetBtn.disabled = false; });
        });
    }

    // Görünürlük ikonlarını JS'ten değiştirmek için (eye / eye-off) SVG stringleri
    window.__dashIcons = {
        eye: '<svg class="icon icon-sm" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>',
        eyeOff: '<svg class="icon icon-sm" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>'
    };
}());
