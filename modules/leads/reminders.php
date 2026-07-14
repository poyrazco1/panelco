<?php
declare(strict_types=1);

/**
 * modules/leads/reminders.php — Lead takip panosu (§21). Kullanıcının takipleri
 * bölümler halinde: geciken, şimdi, bugün, yaklaşan, tamamlanan. Hızlı işlemler.
 */

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/lead_followup_render.php';

auth_boot();
require_permission('leads.view');

$uid    = (int) (current_user_id() ?? 0);
$counts = lead_reminder_counts($uid, false);
$data   = lead_reminder_dashboard($uid, null);
$actUrl = e(url('modules/leads/lead-action.php'));
$ret    = 'modules/leads/reminders.php';

layout_top('Lead Takip Panosu', 'leads');

$sections = [
    'overdue'   => ['Geciken Takipler', 'badge-danger'],
    'now'       => ['Şu Anda Yapılması Gerekenler', 'badge-warning'],
    'today'     => ['Bugün Aranacaklar', 'badge-info'],
    'upcoming'  => ['Yaklaşan Takipler', 'badge-muted'],
    'completed' => ['Bugün Tamamlananlar', 'badge-success'],
];
?>
<div class="page-head">
    <h1 class="page-title">Lead Takip Panosu</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/leads/my-lists.php')) ?>"><?= icon('list-checks') ?>Çalışma Listem</a>
        <?php if (lead_reminders_can_see_team()): ?><a class="btn btn-sm" href="<?= e(url('modules/leads/team-reminders.php')) ?>"><?= icon('users') ?>Ekip Takipleri</a><?php endif; ?>
        <a class="btn btn-sm" href="<?= e(url('modules/leads/index.php')) ?>"><?= icon('arrow-left') ?>Lead Listesi</a>
    </div>
</div>
<?= render_flashes() ?>

<div class="stat-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:18px">
    <div class="card"><div class="card-body"><div class="muted">Bugün aranacak</div><div style="font-size:24px;font-weight:700"><?= (int) $counts['today_open'] ?></div></div></div>
    <div class="card" style="<?= $counts['overdue'] > 0 ? 'border-color:#dc2626' : '' ?>"><div class="card-body"><div class="muted">Geciken</div><div style="font-size:24px;font-weight:700;color:<?= $counts['overdue'] > 0 ? '#b91c1c' : 'inherit' ?>"><?= (int) $counts['overdue'] ?></div></div></div>
    <div class="card"><div class="card-body"><div class="muted">1 saat içinde</div><div style="font-size:24px;font-weight:700"><?= (int) $counts['next_hour'] ?></div></div></div>
    <div class="card"><div class="card-body"><div class="muted">Bugün tamamlanan</div><div style="font-size:24px;font-weight:700;color:#15803d"><?= (int) $counts['done_today'] ?></div></div></div>
    <div class="card"><div class="card-body"><div class="muted">Ulaşılamayan</div><div style="font-size:24px;font-weight:700"><?= (int) $counts['unreachable'] ?></div></div></div>
    <div class="card"><div class="card-body"><div class="muted">Tarih verilecek</div><div style="font-size:24px;font-weight:700"><?= (int) $counts['need_reschedule'] ?></div></div></div>
</div>

<?php foreach ($sections as $key => [$title, $cls]):
    $rows = $data[$key] ?? [];
    $isOverdue = $key === 'overdue' && $rows; ?>
    <div class="card" style="margin-bottom:16px;<?= $isOverdue ? 'border:1px solid #dc2626' : '' ?>">
        <div class="card-header" style="<?= $isOverdue ? 'background:#fef2f2' : '' ?>">
            <h2 style="margin:0"><span class="badge <?= $cls ?>"><?= count($rows) ?></span> <?= e($title) ?></h2>
        </div>
        <div class="card-body">
            <?php if (!$rows): ?><div class="empty" style="padding:10px 0">Kayıt yok.</div>
            <?php else: foreach ($rows as $r) { lead_followup_row_html($r, $actUrl, $ret); } endif; ?>
        </div>
    </div>
<?php endforeach; ?>
<?php layout_bottom();
