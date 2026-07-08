<?php
declare(strict_types=1);

/**
 * modules/forms/submission-view.php — Form kaydı detayı + işlemler.
 * Cevaplar, dosya ekleri, durum/onay, işlem geçmişi, atama, WhatsApp, yazdır.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/form-center.php';

auth_boot();
require_permission('forms.submissions.view');

$id = (int) ($_GET['id'] ?? 0);
$s = form_center_submission_find($id);
if (!$s) { flash('error', 'Kayıt bulunamadı veya görüntüleme yetkiniz yok.'); redirect('modules/forms/submissions.php'); }

$manage = form_center_can_manage();
$fields = form_center_fields($s);
$data = json_decode((string) ($s['data_json'] ?? '{}'), true) ?: [];
$uploads = form_center_submission_uploads($id);
$logs = $manage ? form_center_submission_logs($id) : [];
$waReceived = form_center_wa_link($s, 'received');

$users = [];
if ($manage) { try { foreach (db()->query('SELECT id, full_name, username FROM users WHERE is_active=1 ORDER BY full_name')->fetchAll() as $u) { $users[(int) $u['id']] = $u['full_name'] ?: $u['username']; } } catch (Throwable $e) {} }

$isPrint = ($_GET['print'] ?? '') === '1';
if ($isPrint) {
    // Sade yazdırma görünümü
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>' . e((string) $s['submission_no']) . '</title>';
    echo '<style>body{font-family:-apple-system,Segoe UI,Arial,sans-serif;color:#1f2937;max-width:720px;margin:24px auto;padding:0 16px}h1{font-size:18px}dt{color:#6b7280;font-size:13px}dd{margin:0 0 10px;font-weight:600}hr{border:none;border-top:1px solid #e5e7eb;margin:14px 0}</style></head><body onload="window.print()">';
    echo '<h1>' . e((string) $s['form_name']) . ' — ' . e((string) $s['submission_no']) . '</h1>';
    echo '<p>Tarih: ' . e((string) $s['created_at']) . ' · Durum: ' . e(form_status_label((string) $s['status'])) . '</p><hr>';
    foreach ($fields as $fld) {
        if ($fld['type'] === 'file') { continue; }
        $v = (string) ($data[$fld['name']] ?? '');
        if ($v === '') { continue; }
        echo '<dt>' . e($fld['label']) . '</dt><dd>' . nl2br(e($v)) . '</dd>';
    }
    echo '</body></html>';
    exit;
}

layout_top($s['submission_no'] . ' · ' . $s['form_name'], 'forms');
?>
<div class="page-head">
    <h1 class="page-title"><?= e((string) $s['form_name']) ?> <span class="muted">#<?= e((string) $s['submission_no']) ?></span></h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/forms/submissions.php')) ?>">← Kayıtlar</a>
        <a class="btn btn-sm" href="<?= e(url('modules/forms/submission-view.php?id=' . $id . '&print=1')) ?>" target="_blank" rel="noopener"><?= icon('printer') ?>Yazdır</a>
        <?php if ($waReceived): ?><a class="btn btn-sm" href="<?= e($waReceived) ?>" target="_blank" rel="noopener"><?= icon('message-circle') ?>WhatsApp</a><?php endif; ?>
    </div>
</div>
<?= render_flashes() ?>

<div class="route-detail-grid">
    <!-- Sol: özet + işlemler -->
    <div class="card">
        <div class="card-header"><strong>Kayıt Özeti</strong></div>
        <div class="card-body">
            <dl class="detail-list">
                <div><dt>Talep No</dt><dd><?= e((string) $s['submission_no']) ?></dd></div>
                <div><dt>Durum</dt><dd><span class="badge <?= e(form_status_class((string) $s['status'])) ?>"><?= e(form_status_label((string) $s['status'])) ?></span></dd></div>
                <?php if ((string) $s['approval_status'] !== 'none'): ?><div><dt>Onay</dt><dd><span class="badge <?= e(form_approval_class((string) $s['approval_status'])) ?>"><?= e(form_approval_label((string) $s['approval_status'])) ?></span></dd></div><?php endif; ?>
                <div><dt>Gönderen</dt><dd><?= e((string) ($s['submitter_name'] ?? ($s['external_token_id'] ? 'Dış Form (token)' : '—'))) ?></dd></div>
                <div><dt>Atanan</dt><dd><?= e((string) ($s['assignee_name'] ?? '')) ?: '—' ?></dd></div>
                <div><dt>Tarih</dt><dd><?= e((string) $s['created_at']) ?></dd></div>
                <?php if (!empty($s['rejection_reason'])): ?><div><dt>Red sebebi</dt><dd><?= e((string) $s['rejection_reason']) ?></dd></div><?php endif; ?>
            </dl>

            <?php if ($manage): ?>
            <div class="form-op-group">
                <!-- Durum değiştir -->
                <form method="post" action="<?= e(url('modules/forms/status.php')) ?>" class="op-inline">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
                    <div class="form-group"><label>Durum</label>
                        <select name="status"><?php foreach (form_statuses() as $k => $l): ?><option value="<?= e($k) ?>"<?= (string) $s['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
                    </div>
                    <button type="submit" class="btn btn-sm">Durumu Güncelle</button>
                </form>
                <!-- Ata -->
                <form method="post" action="<?= e(url('modules/forms/assign.php')) ?>" class="op-inline">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
                    <div class="form-group"><label>Kullanıcıya Ata</label>
                        <select name="assigned_user_id"><option value="0">— Atanmadı —</option><?php foreach ($users as $uid => $un): ?><option value="<?= (int) $uid ?>"<?= (int) $s['assigned_user_id'] === (int) $uid ? ' selected' : '' ?>><?= e($un) ?></option><?php endforeach; ?></select>
                    </div>
                    <button type="submit" class="btn btn-sm">Ata</button>
                </form>
            </div>

            <?php if ((string) $s['approval_status'] === 'pending' && can('forms.approve')): ?>
            <div class="form-approve-row">
                <form method="post" action="<?= e(url('modules/forms/approve.php')) ?>" onsubmit="return confirm('Bu kayıt onaylansın mı?')">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="btn btn-sm btn-primary"><?= icon('check-circle') ?>Onayla</button>
                </form>
                <details class="op-details">
                    <summary class="btn btn-sm btn-ghost"><?= icon('x-circle') ?>Reddet</summary>
                    <form method="post" action="<?= e(url('modules/forms/reject.php')) ?>" class="op-inline" style="margin-top:8px">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
                        <div class="form-group"><label>Red sebebi</label><input type="text" name="reason" placeholder="Sebep" required></div>
                        <button type="submit" class="btn btn-sm btn-ghost">Reddet</button>
                    </form>
                </details>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sağ: cevaplar + ekler -->
    <div class="card">
        <div class="card-header"><strong>Form Cevapları</strong></div>
        <div class="card-body">
            <dl class="detail-list">
                <?php foreach ($fields as $fld): if ($fld['type'] === 'file') { continue; } $v = (string) ($data[$fld['name']] ?? ''); ?>
                    <div><dt><?= e($fld['label']) ?></dt><dd><?= $v !== '' ? nl2br(e($v)) : '—' ?></dd></div>
                <?php endforeach; ?>
            </dl>

            <?php if ($uploads): ?>
            <h3 class="form-sec-title">Dosya Ekleri</h3>
            <div class="form-uploads">
                <?php foreach ($uploads as $up):
                    $isImg = strpos((string) ($up['mime_type'] ?? ''), 'image/') === 0;
                ?>
                    <a class="form-upload-item" href="<?= e(url((string) $up['file_path'])) ?>" target="_blank" rel="noopener">
                        <?php if ($isImg): ?><img src="<?= e(url((string) $up['file_path'])) ?>" alt="ek" loading="lazy">
                        <?php else: ?><span class="form-upload-file"><?= icon('file-text') ?></span><?php endif; ?>
                        <span class="form-upload-name"><?= e((string) ($up['original_name'] ?? 'dosya')) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($manage && $logs): ?>
<div class="card"><div class="card-header"><strong>İşlem Geçmişi</strong></div><div class="card-body">
    <ul class="ship-history">
        <?php foreach ($logs as $l): ?>
            <li><span class="muted small"><?= e((string) $l['created_at']) ?></span>
                <?= e(form_status_label((string) ($l['new_status'] ?? '')) ?: (string) $l['new_status']) ?>
                <?php if (!empty($l['note'])): ?><span class="muted">— <?= e((string) $l['note']) ?></span><?php endif; ?>
                <?php if (!empty($l['full_name'])): ?><span class="muted small">(<?= e((string) $l['full_name']) ?>)</span><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div></div>
<?php endif; ?>
<?php layout_bottom();
