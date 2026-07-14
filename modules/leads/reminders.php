<?php
declare(strict_types=1);

/**
 * modules/leads/reminders.php — Hatırlatma panosu (§13): vadesi gelen/geçen
 * lead hatırlatmaları. Kullanıcı kendine (ve atanmamış) olanları görür.
 */

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leads_crm.php';

auth_boot();
require_permission('leads.view');

$uid = current_user_id();
$due = lead_due_reminders($uid, 100);

layout_top('Lead Hatırlatmaları', 'leads');
$actUrl = e(url('modules/leads/lead-action.php'));
?>
<div class="page-head">
    <h1 class="page-title">Lead Hatırlatmaları</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/leads/my-lists.php')) ?>"><?= icon('list-checks') ?>Çalışma Listem</a>
        <a class="btn btn-sm" href="<?= e(url('modules/leads/index.php')) ?>"><?= icon('arrow-left') ?>Lead Listesi</a>
    </div>
</div>
<?= render_flashes() ?>
<p class="muted">Bugün ve gecikmiş hatırlatmalar. Tamamladıkça listeden düşer.</p>
<?php if (!$due): ?>
    <div class="card"><div class="card-body"><div class="empty">Bekleyen hatırlatma yok. 🎉</div></div></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Tarih/saat</th><th>Tür</th><th>Firma</th><th>Not</th><th class="nowrap">İşlem</th></tr></thead>
    <tbody>
    <?php foreach ($due as $r):
        $overdue = strtotime((string) $r['remind_at']) < time(); ?>
        <tr class="<?= $overdue ? 'row-danger' : '' ?>">
            <td class="nowrap"><strong><?= e((string) $r['remind_at']) ?></strong><?php if ($overdue): ?> <span class="badge badge-danger">gecikmiş</span><?php endif; ?></td>
            <td><span class="badge badge-muted"><?= e(lead_reminder_types()[$r['type']] ?? $r['type']) ?></span></td>
            <td><a href="<?= e(url('modules/leads/view.php?id=' . (int) $r['lead_id'] . '&tab=reminders')) ?>"><?= e((string) $r['company_name']) ?></a></td>
            <td><?= e((string) ($r['note'] ?? '')) ?: '—' ?></td>
            <td class="nowrap">
                <a class="btn btn-xs" href="<?= e(url('modules/leads/view.php?id=' . (int) $r['lead_id'])) ?>"><?= icon('eye', 'icon-xs') ?></a>
                <?php if (can('leads.remind')): ?>
                <form method="post" action="<?= $actUrl ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="reminder_done"><input type="hidden" name="id" value="<?= (int) $r['lead_id'] ?>"><input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>"><button class="btn btn-xs btn-primary"><?= icon('check', 'icon-xs') ?>Tamam</button></form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
<?php layout_bottom();
