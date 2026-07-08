<?php
declare(strict_types=1);
/** modules/international-customers/message-report.php — Genel gönderim raporu. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/message-templates.php';
auth_boot();
require_permission('international_customers.view');

$rep = msg_report(30);
$logs = [];
try {
    $logs = db()->query('SELECT l.*, c.company_name, c.record_no FROM message_send_logs l
        LEFT JOIN international_customers c ON c.id = l.customer_id ORDER BY l.id DESC LIMIT 200')->fetchAll();
} catch (Throwable $e) {}
layout_top('Gönderim Raporu', 'international_customers');
?>
<div class="page-head"><h1 class="page-title">Mesaj Gönderim Raporu</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/international-customers/index.php')) ?>">← Liste</a></div></div>
<?= render_flashes() ?>
<div class="stat-row">
    <div class="stat-card"><span class="stat-val"><?= (int) $rep['mail'] ?></span><span class="stat-lbl">Mail (30 gün)</span></div>
    <div class="stat-card"><span class="stat-val"><?= (int) $rep['whatsapp'] ?></span><span class="stat-lbl">WhatsApp Link (30 gün)</span></div>
    <div class="stat-card"><span class="stat-val"><?= (int) $rep['blocked'] ?></span><span class="stat-lbl">Engellenen (kara liste)</span></div>
    <div class="stat-card"><span class="stat-val"><?= msg_mail_remaining() ?></span><span class="stat-lbl">Bugün Kalan Mail Hakkı</span></div>
</div>
<div class="card"><div class="card-header"><strong>Son Gönderimler</strong></div><div class="card-body">
    <?php if ($logs): ?>
    <div class="table-wrap"><table class="table"><thead><tr><th>Tarih</th><th>Müşteri</th><th>Kanal</th><th>Konu</th><th>Durum</th><th>Not</th></tr></thead><tbody>
    <?php foreach ($logs as $l): ?>
        <tr><td class="small nowrap"><?= e((string) $l['created_at']) ?></td>
            <td><?php if (!empty($l['company_name'])): ?><a href="<?= e(url('modules/international-customers/view.php?id=' . (int) $l['customer_id'])) ?>"><?= e($l['company_name']) ?></a><?php else: ?>—<?php endif; ?></td>
            <td><?= $l['channel'] === 'whatsapp' ? 'WhatsApp' : 'Mail' ?></td>
            <td class="small"><?= e((string) ($l['subject'] ?? '')) ?: '—' ?></td>
            <td><span class="badge <?= $l['status'] === 'sent' ? 'badge-success' : ($l['status'] === 'blocked' || $l['status'] === 'failed' ? 'badge-danger' : 'badge-muted') ?>"><?= e((string) $l['status']) ?></span></td>
            <td class="small muted"><?= e((string) ($l['note'] ?? '')) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php else: ?><div class="empty empty-compact">Henüz gönderim yok.</div><?php endif; ?>
</div></div>
<?php layout_bottom();
