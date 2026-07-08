<?php
declare(strict_types=1);

/**
 * modules/leads/scan-view.php — Lead tarama sonuç/ilerleme ekranı.
 * Canlı sayaçlar + progress bar + son bulunan işletmeler (JS polling).
 * Google Haritalar taraması Chrome eklentisi tarafından yapılır; bu ekran
 * yalnızca ilerlemeyi gösterir ve durdur/devam/tamamla kontrolleri sunar.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/lead-scan.php';

auth_boot();
require_permission('leads.view');

$id = (int) ($_GET['id'] ?? 0);
$scan = lead_scan_find($id);
if (!$scan) { flash('error', 'Tarama bulunamadı.'); redirect('modules/leads/scans.php'); }

$cfg = $scan['config'] ?? [];
$p = lead_scan_progress($id);
$canEdit = can('leads.edit');

layout_top('Tarama: ' . ($scan['name'] ?? ('#' . $id)), 'leads');
?>
<div class="page-head">
    <h1 class="page-title"><?= e((string) ($scan['name'] ?? ('Tarama #' . $id))) ?>
        <span class="badge <?= e(lead_scan_status_class((string) $scan['status'])) ?>" id="scanStatusBadge"><?= e(lead_scan_status_label((string) $scan['status'])) ?></span></h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/leads/scans.php')) ?>">← Taramalar</a>
        <a class="btn btn-sm" href="<?= e(url('modules/leads/index.php?scan_id=' . $id)) ?>"><?= icon('users') ?>Bu Taramanın Leadleri</a>
    </div>
</div>
<?= render_flashes() ?>

<div class="scan-result-grid">
    <!-- İlerleme -->
    <div class="progress-panel">
        <div class="progress-head">
            <span>Kaydedilen: <b id="pgSaved"><?= (int) $p['saved'] ?></b> / <?= (int) $p['target'] ?></span>
            <span id="pgPct">%<?= (int) $p['percent'] ?></span>
        </div>
        <div class="progress-bar"><span id="pgBar" style="width:<?= (int) $p['percent'] ?>%"></span></div>

        <div class="scan-counters">
            <div class="scan-counter"><span class="sc-val" id="cFound"><?= (int) $p['found'] ?></span><span class="sc-lbl">Bulundu</span></div>
            <div class="scan-counter sc-good"><span class="sc-val" id="cSaved"><?= (int) $p['saved'] ?></span><span class="sc-lbl">Kaydedildi</span></div>
            <div class="scan-counter"><span class="sc-val" id="cDup"><?= (int) $p['duplicate'] ?></span><span class="sc-lbl">Zaten Vardı</span></div>
            <div class="scan-counter"><span class="sc-val" id="cSkip"><?= (int) $p['skipped'] ?></span><span class="sc-lbl">Uygun Değil</span></div>
            <div class="scan-counter sc-bad"><span class="sc-val" id="cErr"><?= (int) $p['error'] ?></span><span class="sc-lbl">Hata</span></div>
            <div class="scan-counter"><span class="sc-val"><?= (int) $p['combos'] ?></span><span class="sc-lbl">Kombinasyon</span></div>
        </div>

        <?php if ($canEdit): ?>
        <div class="scan-controls" id="scanControls">
            <?php $ctl = static function (string $act, string $label, string $icon, string $cls = 'btn-sm') use ($id) {
                echo '<form method="post" action="' . e(url('modules/leads/scan-control.php')) . '" style="display:inline">'
                   . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="' . e($act) . '">'
                   . '<button type="submit" class="btn ' . e($cls) . '">' . icon($icon) . e($label) . '</button></form>';
            }; ?>
            <?php if ((string) $scan['status'] === 'running') { $ctl('pause', 'Duraklat', 'clock'); }
                  elseif ((string) $scan['status'] === 'paused') { $ctl('resume', 'Devam Et', 'refresh-cw'); } ?>
            <?php if (in_array((string) $scan['status'], ['running', 'paused'], true)) { $ctl('done', 'Tamamlandı', 'check-circle'); $ctl('cancel', 'İptal', 'x-circle', 'btn-sm btn-ghost'); } ?>
        </div>
        <?php endif; ?>
        <p class="muted small" style="margin-top:10px">Chrome eklentisi bu taramayı işlerken sonuçlar buraya canlı düşer. Ekranı açık tutabilirsiniz.</p>
    </div>

    <!-- Yapılandırma özeti -->
    <div class="card">
        <div class="card-header"><strong>Tarama Ayarları</strong></div>
        <div class="card-body">
            <dl class="detail-list">
                <div><dt>Sektör / Kelime</dt><dd><?= (int) count($cfg['terms'] ?? []) ?> adet</dd></div>
                <div><dt>Bölge</dt><dd><?= e((string) ($cfg['city'] ?? '')) ?><?= !empty($cfg['districts']) ? ' · ' . (int) count($cfg['districts']) . ' ilçe' : '' ?></dd></div>
                <div><dt>Paket</dt><dd><?= e($scan['package'] ? lead_scan_package_label((string) $scan['package']) : '—') ?></dd></div>
                <div><dt>Temsilci</dt><dd><?= e((string) ($scan['assignee_name'] ?? '—')) ?></dd></div>
                <div><dt>Kaynak</dt><dd><?= e((string) ($scan['source'] ?? '—')) ?></dd></div>
            </dl>
            <?php if (!empty($cfg['terms'])): ?>
            <div class="scan-terms-preview">
                <?php foreach (array_slice($cfg['terms'], 0, 20) as $t): ?><span class="mini-chip"><?= e((string) $t) ?></span><?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Son bulunan işletmeler -->
<div class="card">
    <div class="card-header"><strong>Son Bulunan İşletmeler</strong></div>
    <div class="card-body">
        <div id="recentEmpty" class="empty-state empty-compact"<?= (int) $p['saved'] > 0 ? ' hidden' : '' ?>><p>Henüz kayıt yok. Tarama ilerledikçe burada görünecek.</p></div>
        <div class="table-wrap" id="recentWrap"<?= (int) $p['saved'] > 0 ? '' : ' hidden' ?>>
            <table class="table"><thead><tr><th>Firma</th><th>Telefon</th><th>Bölge</th><th>Puan</th><th>Web</th></tr></thead>
            <tbody id="recentBody"></tbody></table>
        </div>
    </div>
</div>

<script>
(function () {
    var url = <?= json_encode(url('modules/leads/scan-status.php?id=' . $id), JSON_UNESCAPED_UNICODE) ?>;
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    function set(id, v) { var el = document.getElementById(id); if (el) el.textContent = v; }
    function poll() {
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d.ok) return;
            var p = d.progress;
            set('pgSaved', p.saved); set('pgPct', '%' + p.percent);
            document.getElementById('pgBar').style.width = p.percent + '%';
            set('cFound', p.found); set('cSaved', p.saved); set('cDup', p.duplicate); set('cSkip', p.skipped); set('cErr', p.error);
            var badge = document.getElementById('scanStatusBadge'); if (badge) badge.textContent = p.status_label;
            var body = document.getElementById('recentBody');
            if (d.recent && d.recent.length) {
                document.getElementById('recentEmpty').hidden = true; document.getElementById('recentWrap').hidden = false;
                body.innerHTML = d.recent.map(function (r) {
                    var rating = r.rating != null ? (r.rating + (r.reviews != null ? ' (' + r.reviews + ')' : '')) : '—';
                    return '<tr><td><strong>' + esc(r.company) + '</strong></td><td class="nowrap">' + esc(r.phone || '—') + '</td><td>' + esc(r.loc || '—') + '</td><td>' + esc(rating) + '</td><td>' + (r.website ? 'Var' : '<span class="muted">Yok</span>') + '</td></tr>';
                }).join('');
            }
            // durma durumlarında polling'i seyrelt
            if (['done', 'cancelled'].indexOf(p.status) !== -1) { clearInterval(timer); }
        }).catch(function () {});
    }
    poll();
    var timer = setInterval(poll, 4000);
})();
</script>
<?php layout_bottom();
