<?php
declare(strict_types=1);

/**
 * modules/leads/team-reminders.php — Ekip Lead Takipleri (§21, yönetici).
 * Personel bazlı özet + tüm ekip takipleri; takip yeniden atama.
 */

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/lead_followup_render.php';

auth_boot();
require_permission('leads.view');
if (!lead_reminders_can_see_team()) {
    require_permission('leads.team_reminders'); // yetki yoksa 403
}

$team    = lead_reminder_team_by_user();
$counts  = lead_reminder_counts(0, true);
$data    = lead_reminder_dashboard(0, 1); // teamAll
$users   = lead_assignable_users();
$overPostponed = can_followup_reports() ? lead_reminders_over_postponed(3, 100) : [];
$actUrl  = e(url('modules/leads/lead-action.php'));
$ret     = 'modules/leads/team-reminders.php';

layout_top('Ekip Lead Takipleri', 'leads');
?>
<div class="page-head">
    <h1 class="page-title">Ekip Lead Takipleri</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/leads/reminders.php')) ?>"><?= icon('arrow-left') ?>Kendi Panom</a></div>
</div>
<?= render_flashes() ?>

<div class="stat-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:18px">
    <div class="card"><div class="card-body"><div class="muted">Bugün aranacak</div><div style="font-size:24px;font-weight:700"><?= (int) $counts['today_open'] ?></div></div></div>
    <div class="card" style="<?= $counts['overdue'] > 0 ? 'border-color:#dc2626' : '' ?>"><div class="card-body"><div class="muted">Geciken</div><div style="font-size:24px;font-weight:700;color:<?= $counts['overdue'] > 0 ? '#b91c1c' : 'inherit' ?>"><?= (int) $counts['overdue'] ?></div></div></div>
    <div class="card"><div class="card-body"><div class="muted">1 saat içinde</div><div style="font-size:24px;font-weight:700"><?= (int) $counts['next_hour'] ?></div></div></div>
    <div class="card"><div class="card-body"><div class="muted">Bugün tamamlanan</div><div style="font-size:24px;font-weight:700;color:#15803d"><?= (int) $counts['done_today'] ?></div></div></div>
</div>

<div class="card"><div class="card-header"><h2>Personel Özeti</h2></div><div class="card-body">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Personel</th><th class="nowrap">Bugün</th><th class="nowrap">Geciken</th><th class="nowrap">Tamamlanan</th><th class="nowrap">Ulaşılamayan</th><th class="nowrap">Ertelenen</th></tr></thead>
        <tbody>
        <?php if (!$team): ?><tr><td colspan="6" class="muted">Kayıt yok.</td></tr><?php endif; ?>
        <?php foreach ($team as $t): ?>
            <tr>
                <td><?= e((string) ($t['full_name'] ?? '(atanmamış)')) ?></td>
                <td class="nowrap"><?= (int) $t['today_open'] ?></td>
                <td class="nowrap"<?= (int) $t['overdue'] > 0 ? ' style="color:#b91c1c;font-weight:600"' : '' ?>><?= (int) $t['overdue'] ?></td>
                <td class="nowrap"><?= (int) $t['done_today'] ?></td>
                <td class="nowrap"><?= (int) $t['unreachable'] ?></td>
                <td class="nowrap"><?= (int) $t['postponed'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div></div>

<?php if ($overPostponed): ?>
<div class="card" style="border-color:#f59e0b"><div class="card-header"><h2><?= icon('triangle-alert', 'icon-sm') ?> Fazla Ertelenen Takipler (3+)</h2></div><div class="card-body">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Firma</th><th>Personel</th><th class="nowrap">Erteleme</th><th class="nowrap">Zaman</th><th class="nowrap">Yeniden ata</th></tr></thead>
        <tbody>
        <?php foreach ($overPostponed as $r): ?>
            <tr>
                <td><a href="<?= e(url('modules/leads/view.php?id=' . (int) $r['lead_id'])) ?>"><?= e((string) $r['company_name']) ?></a></td>
                <td><?= e((string) ($r['assignee'] ?? '')) ?: '—' ?></td>
                <td class="nowrap"><span class="badge badge-warning"><?= (int) $r['postpone_count'] ?>x</span></td>
                <td class="nowrap"><?= e((string) $r['remind_at']) ?></td>
                <td class="nowrap">
                    <form method="post" action="<?= $actUrl ?>" style="display:flex;gap:4px"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="reminder_reassign"><input type="hidden" name="id" value="<?= (int) $r['lead_id'] ?>">
                        <input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="return" value="<?= e($ret) ?>">
                        <select name="new_user_id" class="btn-xs"><?php foreach ($users as $uido => $un): ?><option value="<?= (int) $uido ?>"<?= (int) ($r['assigned_user_id'] ?? 0) === (int) $uido ? ' selected' : '' ?>><?= e($un) ?></option><?php endforeach; ?></select>
                        <button class="btn btn-xs"><?= icon('user-check', 'icon-xs') ?>Ata</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div></div>
<?php endif; ?>

<?php
$sections = ['overdue' => ['Geciken Takipler', 'badge-danger'], 'now' => ['Şu Anda', 'badge-warning'],
             'today' => ['Bugün', 'badge-info'], 'upcoming' => ['Yaklaşan', 'badge-muted']];
foreach ($sections as $key => [$title, $cls]):
    $rows = $data[$key] ?? []; ?>
    <div class="card" style="margin-bottom:14px<?= $key === 'overdue' && $rows ? ';border:1px solid #dc2626' : '' ?>">
        <div class="card-header"><h2 style="margin:0"><span class="badge <?= $cls ?>"><?= count($rows) ?></span> <?= e($title) ?></h2></div>
        <div class="card-body">
            <?php if (!$rows): ?><div class="empty" style="padding:8px 0">Kayıt yok.</div>
            <?php else: foreach ($rows as $r) { lead_followup_row_html($r, $actUrl, $ret); } endif; ?>
        </div>
    </div>
<?php endforeach; ?>
<?php layout_bottom();
