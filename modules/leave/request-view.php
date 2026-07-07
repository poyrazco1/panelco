<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leave.php';

auth_boot();
require_permission('leave');

$id = (int) ($_GET['id'] ?? 0);
$r = $id > 0 ? get_leave_request($id) : null;
if (!$r) { flash('error', 'Kayıt bulunamadı.'); http_response_code(303); redirect('modules/leave/requests.php'); }

$set = leave_settings();
$breakdown = leave_days_breakdown((string) $r['start_date'], (string) $r['end_date'], [
    'weekend_days' => $set['weekend_days'],
]);
$status = (string) $r['status'];
$actionUrl = url('modules/leave/request-action.php');

layout_top('İzin Talebi ' . $r['request_no'], 'leave');
?>

<div class="page-head">
    <h1 class="page-title">İzin Talebi <?= e((string) $r['request_no']) ?></h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/leave/requests.php')) ?>"><?= icon('chevron-left') ?>İzin Talepleri</a>
        <?php if (in_array($status, ['draft', 'pending'], true)): ?>
            <a class="btn btn-sm" href="<?= e(url('modules/leave/request-form.php?id=' . $id)) ?>"><?= icon('pencil') ?>Düzenle</a>
        <?php endif; ?>
    </div>
</div>

<?= render_flashes() ?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h2>Talep Bilgileri</h2><span class="badge <?= e(leave_status_class($status)) ?>"><?= e(leave_status_label($status)) ?></span></div>
        <div class="card-body">
            <dl class="detail-list">
                <div><dt>Personel</dt><dd><?= e((string) ($r['personnel_name'] ?? '—')) ?><?= !empty($r['personnel_code']) ? ' <span class="muted">(' . e((string) $r['personnel_code']) . ')</span>' : '' ?></dd></div>
                <div><dt>Departman</dt><dd><?= e((string) ($r['department'] ?? '—')) ?></dd></div>
                <div><dt>İzin Türü</dt><dd><?= e((string) ($r['type_name'] ?? '—')) ?><?= (int) $r['type_deducts'] === 1 ? ' <span class="badge badge-info">Bakiyeden düşer</span>' : '' ?></dd></div>
                <div><dt>Başlangıç</dt><dd><?= e(fmt_date((string) $r['start_date'])) ?><?= !empty($r['start_time']) ? ' ' . e(substr((string) $r['start_time'], 0, 5)) : '' ?></dd></div>
                <div><dt>Bitiş</dt><dd><?= e(fmt_date((string) $r['end_date'])) ?><?= !empty($r['end_time']) ? ' ' . e(substr((string) $r['end_time'], 0, 5)) : '' ?></dd></div>
                <div><dt>Gün Sayısı</dt><dd><strong><?= e(rtrim(rtrim(number_format((float) $r['calculated_days'], 2, ',', '.'), '0'), ',')) ?> gün</strong><?= (int) $r['is_half_day'] === 1 ? ' <span class="badge badge-muted">Yarım gün</span>' : '' ?></dd></div>
                <?php if ($r['calculated_hours'] !== null && (float) $r['calculated_hours'] > 0): ?><div><dt>Saat</dt><dd><?= e(rtrim(rtrim(number_format((float) $r['calculated_hours'], 2, ',', '.'), '0'), ',')) ?> saat</dd></div><?php endif; ?>
                <?php if (!empty($r['description'])): ?><div><dt>Açıklama</dt><dd><?= nl2br(e((string) $r['description'])) ?></dd></div><?php endif; ?>
            </dl>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Süreç & Hesap</h2></div>
        <div class="card-body">
            <dl class="detail-list">
                <div><dt>Talep Eden</dt><dd><?= e((string) ($r['requester_name'] ?? '—')) ?></dd></div>
                <div><dt>Oluşturulma</dt><dd><?= e(fmt_date((string) $r['created_at'])) ?></dd></div>
                <?php if (!empty($r['approver_name']) && $status === 'approved'): ?>
                    <div><dt>Onaylayan</dt><dd><?= e((string) $r['approver_name']) ?></dd></div>
                    <div><dt>Onay Tarihi</dt><dd><?= e(fmt_date((string) $r['approved_at'])) ?></dd></div>
                <?php endif; ?>
                <?php if ($status === 'rejected'): ?>
                    <div><dt>Reddeden</dt><dd><?= e((string) ($r['rejecter_name'] ?? '—')) ?></dd></div>
                    <div><dt>Red Tarihi</dt><dd><?= e(fmt_date((string) $r['rejected_at'])) ?></dd></div>
                    <?php if (!empty($r['reject_reason'])): ?><div><dt>Red Nedeni</dt><dd><?= nl2br(e((string) $r['reject_reason'])) ?></dd></div><?php endif; ?>
                <?php endif; ?>
            </dl>
            <hr class="soft">
            <p class="field-hint">
                Hesap: <?= (int) $breakdown['span'] ?> takvim günü · <?= (int) $breakdown['weekend'] ?> hafta sonu ·
                <?= (int) $breakdown['holiday'] ?> resmi tatil düşüldü →
                <strong><?= e(rtrim(rtrim(number_format((float) $breakdown['counted'], 2, ',', '.'), '0'), ',')) ?> gün</strong>.
            </p>
        </div>
    </div>
</div>

<?php if (in_array($status, ['draft', 'pending', 'approved'], true)): ?>
<div class="card">
    <div class="card-header"><h2>İşlemler</h2></div>
    <div class="card-body">
        <div class="action-row">
            <?php if (in_array($status, ['draft', 'pending'], true)): ?>
                <form method="post" action="<?= e($actionUrl) ?>" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="btn btn-primary btn-sm"><?= icon('check-circle') ?>Onayla</button>
                </form>
            <?php endif; ?>

            <?php if (in_array($status, ['draft', 'pending', 'approved'], true)): ?>
                <form method="post" action="<?= e($actionUrl) ?>" style="display:inline" data-confirm="İzin iptal edilsin mi?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="btn btn-sm"><?= icon('ban') ?>İptal Et</button>
                </form>
            <?php endif; ?>

            <form method="post" action="<?= e($actionUrl) ?>" style="display:inline" data-confirm="Bu kayıt arşive alınsın mı?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="btn btn-sm action-icon-btn is-danger" title="Sil / arşive al"><?= icon('trash-2') ?></button>
            </form>
        </div>

        <?php if (in_array($status, ['draft', 'pending'], true)): ?>
        <hr class="soft">
        <form method="post" action="<?= e($actionUrl) ?>" class="reject-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="form-group">
                <label for="rj-reason">Red Nedeni</label>
                <input type="text" id="rj-reason" name="reason" placeholder="Reddetme gerekçesi (opsiyonel)">
            </div>
            <button type="submit" class="btn btn-danger btn-sm"><?= icon('x-circle') ?>Reddet</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php
layout_bottom();
