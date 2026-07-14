<?php
declare(strict_types=1);

/**
 * modules/settings/email-logs.php
 * Belge gönderim geçmişi (global, salt-okunur). Belge bazlı geçmiş + yeniden
 * gönderim ilgili belgenin detay sayfasındadır.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/email_system.php';

auth_boot();
if (!can('settings')) { require_permission('email.logs_view'); }

$logs = document_email_logs_recent(200);

layout_top('Belge Gönderim Geçmişi', 'settings');
?>
<div class="page-head">
    <h1 class="page-title">Belge Gönderim Geçmişi</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>"><?= icon('chevron-left') ?>Genel Ayarlar</a></div>
</div>

<div class="card">
    <div class="card-header"><h2>Son 200 Gönderim</h2></div>
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <p class="muted">Henüz belge e-postası gönderilmemiş.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Belge</th><th>Alıcı</th><th>Gönderen</th><th>PDF</th><th>Durum</th><th>Not / Hata</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td><?= e(date('d.m.Y H:i', strtotime((string) $l['created_at']))) ?></td>
                        <td><?= e((string) $l['document_type']) ?><?= $l['document_no'] !== '' ? ' · ' . e((string) $l['document_no']) : '' ?></td>
                        <td><?= e((string) $l['to_email']) ?></td>
                        <td><?= e((string) ($l['sent_by_name'] ?: '—')) ?><?= $l['department'] !== '' ? ' <span class="muted">(' . e((string) $l['department']) . ')</span>' : '' ?></td>
                        <td><?= (int) $l['has_pdf'] === 1 ? '<span class="badge badge-info">Ek</span>' : '<span class="muted">—</span>' ?></td>
                        <td><?= $l['status'] === 'sent' ? '<span class="badge badge-success">Gönderildi</span>' : '<span class="badge badge-danger">Başarısız</span>' ?></td>
                        <td><?= $l['status'] === 'sent' ? e((string) $l['from_email']) : '<span class="muted">' . e(mb_strimwidth((string) ($l['error_message'] ?? ''), 0, 80, '…', 'UTF-8')) . '</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="field-hint" style="margin-top:10px">Belge bazlı ayrıntılı geçmiş ve başarısız gönderimleri yeniden gönderme, ilgili belgenin detay sayfasındaki "Gönderim Geçmişi" bölümündedir.</p>
        <?php endif; ?>
    </div>
</div>

<?php
layout_bottom();
