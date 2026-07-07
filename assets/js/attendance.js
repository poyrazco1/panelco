(function () {
    'use strict';

    var ATT = window.ATT || {};
    var statusById = {};
    (ATT.statuses || []).forEach(function (s) { statusById[String(s.id)] = s; });

    /* ---- Toplu işlem paneli ---- */
    var bulkToggle = document.getElementById('attBulkToggle');
    var bulkForm = document.getElementById('attBulkForm');
    if (bulkToggle && bulkForm) {
        bulkToggle.addEventListener('click', function () { bulkForm.hidden = !bulkForm.hidden; });
    }
    var bkMode = document.getElementById('bk-mode');
    if (bkMode) {
        var syncRange = function () {
            var isRange = bkMode.value === 'range';
            document.querySelectorAll('.bk-range').forEach(function (el) { el.style.display = isRange ? '' : 'none'; });
        };
        bkMode.addEventListener('change', syncRange);
        syncRange();
    }
    var selectAll = document.getElementById('attSelectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.att-row-check').forEach(function (c) { c.checked = selectAll.checked; });
        });
    }

    /* ---- Hücre düzenleme modalı ---- */
    var modal = document.getElementById('attModal');
    var form = document.getElementById('attCellForm');
    if (!modal || !form) { return; }

    var elPid = document.getElementById('cf-pid');
    var elDate = document.getElementById('cf-date');
    var elStatus = document.getElementById('cf-status');
    var elCi = document.getElementById('cf-ci');
    var elCo = document.getElementById('cf-co');
    var elBreak = document.getElementById('cf-break');
    var elOt = document.getElementById('cf-ot');
    var elMiss = document.getElementById('cf-miss');
    var elNote = document.getElementById('cf-note');
    var elSub = document.getElementById('attModalSub');
    var elMsg = document.getElementById('attModalMsg');
    var currentCell = null;

    function openModal(cell) {
        currentCell = cell;
        elMsg.textContent = '';
        elPid.value = cell.getAttribute('data-pid') || '';
        elDate.value = cell.getAttribute('data-date') || '';
        var sid = cell.getAttribute('data-status') || '';
        if (sid && elStatus.querySelector('option[value="' + sid + '"]')) { elStatus.value = sid; }
        elCi.value = cell.getAttribute('data-ci') || '';
        elCo.value = cell.getAttribute('data-co') || '';
        elBreak.value = cell.getAttribute('data-break') || '';
        elOt.value = cell.getAttribute('data-ot') || '';
        elMiss.value = cell.getAttribute('data-miss') || '';
        elNote.value = cell.getAttribute('data-note') || '';
        elSub.textContent = (cell.getAttribute('data-pname') || '') + ' · ' + (cell.getAttribute('data-date') || '');
        modal.hidden = false;
        document.body.classList.add('att-modal-open');
        elStatus.focus();
    }
    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('att-modal-open');
        currentCell = null;
    }

    document.querySelectorAll('.att-cell').forEach(function (cell) {
        cell.addEventListener('click', function () { openModal(cell); });
    });

    document.getElementById('attModalClose').addEventListener('click', closeModal);
    document.getElementById('attModalCancel').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) { closeModal(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) { closeModal(); } });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!currentCell) { return; }
        var fd = new FormData(form);
        fd.append('_csrf', ATT.csrf || '');
        elMsg.textContent = 'Kaydediliyor…';
        fetch(ATT.saveUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw new Error(j.error || 'Hata'); }); })
            .then(function (data) {
                if (!data.ok) { throw new Error(data.error || 'Hata'); }
                var st = data.status;
                if (st && currentCell) {
                    currentCell.textContent = st.short || '';
                    currentCell.style.background = st.color || '';
                    currentCell.setAttribute('data-status', st.id);
                    currentCell.setAttribute('title', st.name || '');
                    currentCell.setAttribute('data-ci', elCi.value || '');
                    currentCell.setAttribute('data-co', elCo.value || '');
                    currentCell.setAttribute('data-break', elBreak.value || '');
                    currentCell.setAttribute('data-ot', elOt.value || '');
                    currentCell.setAttribute('data-miss', elMiss.value || '');
                    currentCell.setAttribute('data-note', elNote.value || '');
                }
                closeModal();
            })
            .catch(function (err) { elMsg.textContent = err.message || 'Kaydedilemedi.'; });
    });
}());
