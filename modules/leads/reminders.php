<?php
declare(strict_types=1);

/**
 * modules/leads/reminders.php — Lead takip panosu (§21). Kullanıcının takipleri
 * bölümler halinde: geciken, şimdi, bugün, yaklaşan, tamamlanan. Hızlı işlemler.
 */

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leads_crm.php';

auth_boot();
require_permission('leads.view');

$uid    = (int) (current_user_id() ?? 0);
$counts = lead_reminder_counts($uid, false);
$data   = lead_reminder_dashboard($uid, null);
$actUrl = e(url('modules/leads/lead-action.php'));
$ret    = 'modules/leads/reminders.php';

layout_top('Lead Takip Panosu', 'leads');

/** Bir takip satırını hızlı işlem butonlarıyla basar. */
function render_followup_row(array $r, string $actUrl, string $ret): void
{
    $rid = (int) $r['id'];
    $lead = (int) $r['lead_id'];
    $phone = lead_normalize_phone((string) ($r['phone'] ?? $r['whatsapp'] ?? ''));
    $overdue = strtotime((string) $r['remind_at']) < time() && (string) $r['status'] !== 'done';
    $delay = '';
    if ($overdue) {
        $mins = max(0, (int) round((time() - strtotime((string) $r['remind_at'])) / 60));
        $delay = $mins >= 1440 ? floor($mins / 1440) . ' gün' : ($mins >= 60 ? floor($mins / 60) . ' saat' : $mins . ' dk');
    }
    ?>
    <div class="followup-row" style="padding:9px 0;border-bottom:1px solid var(--border,#eee);display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
        <div style="min-width:220px">
            <a href="<?= e(url('modules/leads/view.php?id=' . $lead)) ?>"><strong><?= e((string) $r['company_name']) ?></strong></a>
            <span class="badge <?= e(lead_reminder_priority_class((string) $r['priority'])) ?>"><?= e(lead_reminder_priorities()[$r['priority']] ?? $r['priority']) ?></span>
            <div class="muted" style="font-size:12px">
                <?= e(lead_reminder_type_label((string) $r['reminder_type'])) ?> · <?= e((string) $r['remind_at']) ?>
                <?php if ($delay): ?> · <span style="color:#b91c1c"><?= e($delay) ?> gecikme</span><?php endif; ?>
                <?php if (!empty($r['assignee'])): ?> · <?= e((string) $r['assignee']) ?><?php endif; ?>
            </div>
            <?php if (!empty($r['note'])): ?><div style="font-size:13px"><?= e((string) $r['note']) ?></div><?php endif; ?>
        </div>
        <div style="display:flex;gap:6px;align-items:flex-start;flex-wrap:wrap">
            <?php if ($phone !== ''): ?><a class="btn btn-xs" href="tel:<?= e($phone) ?>" title="Ara"><?= icon('phone', 'icon-xs') ?></a><?php endif; ?>
            <?php if ($phone !== ''): ?><a class="btn btn-xs quick-action-whatsapp" href="https://wa.me/<?= e(ltrim(preg_replace('/\D+/', '', $phone) ?? '', '0')) ?>" target="_blank" rel="noopener" title="WhatsApp"><?= icon('message-circle', 'icon-xs') ?></a><?php endif; ?>
            <a class="btn btn-xs" href="<?= e(url('modules/leads/view.php?id=' . $lead . '&tab=reminders')) ?>" title="Detay"><?= icon('eye', 'icon-xs') ?></a>
            <?php if ((string) $r['status'] !== 'done' && can('leads.remind')): ?>
                <a class="btn btn-xs btn-primary" href="<?= e(url('modules/leads/view.php?id=' . $lead . '&tab=reminders')) ?>" title="Tamamla / Ertele"><?= icon('check', 'icon-xs') ?>İşlem</a>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

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
            <?php else: foreach ($rows as $r) { render_followup_row($r, $actUrl, $ret); } endif; ?>
        </div>
    </div>
<?php endforeach; ?>
<?php layout_bottom();
