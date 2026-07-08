<?php
declare(strict_types=1);

/** modules/forms/approvals.php — Onay bekleyen form kayıtları. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/form-center.php';

auth_boot();
require_permission('forms.approve');

$rows = form_center_submissions(['pending_approval' => true]);

layout_top('Onay Bekleyenler', 'forms');
?>
<div class="page-head">
    <h1 class="page-title">Onay Bekleyenler</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/forms/submissions.php')) ?>"><?= icon('clipboard-list') ?>Tüm Kayıtlar</a></div>
</div>
<?= render_flashes() ?>

<?php if (!$rows): ?>
<div class="empty-state"><?= icon('check-circle', 'icon-lg') ?><p>Onay bekleyen kayıt yok.</p></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Talep No</th><th>Form</th><th>Gönderen</th><th>Firma / Müşteri</th><th class="nowrap">Tarih</th><th class="nowrap">İşlem</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><a href="<?= e(url('modules/forms/submission-view.php?id=' . (int) $r['id'])) ?>"><strong><?= e((string) $r['submission_no']) ?></strong></a></td>
            <td><?= e((string) $r['form_name']) ?></td>
            <td><?= e((string) ($r['submitter_name'] ?? '—')) ?></td>
            <td><?= e(trim((string) ($r['company_name'] ?? '') . ' ' . (string) ($r['customer_name'] ?? ''))) ?: '—' ?></td>
            <td class="nowrap"><?= e(fmt_date((string) $r['created_at'])) ?></td>
            <td class="nowrap">
                <a class="btn btn-xs" href="<?= e(url('modules/forms/submission-view.php?id=' . (int) $r['id'])) ?>"><?= icon('eye', 'icon-xs') ?>İncele</a>
                <form method="post" action="<?= e(url('modules/forms/approve.php')) ?>" style="display:inline" onsubmit="return confirm('Onaylansın mı?')">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button type="submit" class="btn btn-xs btn-primary"><?= icon('check-circle', 'icon-xs') ?>Onayla</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
<?php layout_bottom();
