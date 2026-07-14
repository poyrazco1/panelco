<?php
declare(strict_types=1);

/**
 * includes/lead_followup_render.php — Lead takip satırı/kartı görsel bileşenleri (§21).
 * Dashboard kartı ve takip panosu aynı satır çizimini paylaşır (DRY).
 */

require_once __DIR__ . '/lead_reminders.php';

/** Bir takip satırını hızlı işlem butonlarıyla basar (echo). */
function lead_followup_row_html(array $r, string $actUrl, string $ret): void
{
    $rid  = (int) $r['id'];
    $lead = (int) $r['lead_id'];
    $rawPhone = (string) ($r['phone'] ?? '') ?: (string) ($r['whatsapp'] ?? '');
    $phone = lead_normalize_phone($rawPhone);
    $waDigits = ltrim(preg_replace('/\D+/', '', $phone) ?? '', '0');
    $done = (string) $r['status'] === 'done';
    $overdue = !$done && strtotime((string) $r['remind_at']) < time();
    $delay = '';
    if ($overdue) {
        $mins = max(0, (int) round((time() - strtotime((string) $r['remind_at'])) / 60));
        $delay = $mins >= 1440 ? floor($mins / 1440) . ' gün' : ($mins >= 60 ? floor($mins / 60) . ' saat' : $mins . ' dk');
    }
    $csrf = csrf_field();
    ?>
    <div class="followup-row" style="padding:9px 0;border-bottom:1px solid var(--border,#eee)">
        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:flex-start">
            <div style="min-width:220px;flex:1">
                <a href="<?= e(url('modules/leads/view.php?id=' . $lead)) ?>"><strong><?= e((string) $r['company_name']) ?></strong></a>
                <span class="badge <?= e(lead_reminder_priority_class((string) $r['priority'])) ?>"><?= e(lead_reminder_priorities()[$r['priority']] ?? $r['priority']) ?></span>
                <?php if ($done && !empty($r['completion_result'])): ?><span class="badge badge-success"><?= e(lead_reminder_result_label((string) $r['completion_result'])) ?></span><?php endif; ?>
                <div class="muted" style="font-size:12px">
                    <?= icon('phone', 'icon-xs') ?> <?= e($rawPhone !== '' ? $rawPhone : '—') ?>
                    · <?= e(lead_reminder_type_label((string) $r['reminder_type'])) ?>
                    · <?= e((string) $r['remind_at']) ?>
                    <?php if (!empty($r['lead_status'])): ?> · <span class="badge <?= e(lead_status_class((string) $r['lead_status'])) ?>"><?= e(lead_status_label((string) $r['lead_status'])) ?></span><?php endif; ?>
                    <?php if ($delay): ?> · <span style="color:#b91c1c;font-weight:600"><?= e($delay) ?> gecikme</span><?php endif; ?>
                    <?php if (!empty($r['assignee'])): ?> · <?= e((string) $r['assignee']) ?><?php endif; ?>
                </div>
                <?php if (!empty($r['note'])): ?><div style="font-size:13px"><?= e((string) $r['note']) ?></div><?php endif; ?>
            </div>
            <div style="display:flex;gap:5px;align-items:flex-start;flex-wrap:wrap">
                <?php if ($phone !== ''): ?>
                    <a class="btn btn-xs" href="tel:<?= e($phone) ?>" title="Ara"><?= icon('phone', 'icon-xs') ?></a>
                    <a class="btn btn-xs quick-action-whatsapp" href="https://wa.me/<?= e($waDigits) ?>" target="_blank" rel="noopener" title="WhatsApp"><?= icon('message-circle', 'icon-xs') ?></a>
                <?php endif; ?>
                <a class="btn btn-xs" href="<?= e(url('modules/leads/view.php?id=' . $lead)) ?>" title="Lead detayı"><?= icon('eye', 'icon-xs') ?></a>
                <?php if (!$done && can('leads.remind')): ?>
                    <a class="btn btn-xs btn-primary" href="<?= e(url('modules/leads/view.php?id=' . $lead . '&tab=reminders')) ?>" title="Tamamla (sonuç + not)"><?= icon('check', 'icon-xs') ?>Tamamla</a>
                    <!-- Ulaşılamadı: hızlı tamamlama -->
                    <form method="post" action="<?= $actUrl ?>" style="display:inline" title="Ulaşılamadı olarak işaretle"><?= $csrf ?>
                        <input type="hidden" name="action" value="reminder_complete"><input type="hidden" name="id" value="<?= $lead ?>">
                        <input type="hidden" name="reminder_id" value="<?= $rid ?>"><input type="hidden" name="completion_result" value="unreachable">
                        <input type="hidden" name="return" value="<?= e($ret) ?>">
                        <button class="btn btn-xs"><?= icon('phone-off', 'icon-xs') ?>Ulaşılamadı</button>
                    </form>
                    <!-- Ertele: hızlı -->
                    <form method="post" action="<?= $actUrl ?>" style="display:inline-flex;gap:3px" title="Ertele"><?= $csrf ?>
                        <input type="hidden" name="action" value="reminder_postpone"><input type="hidden" name="id" value="<?= $lead ?>">
                        <input type="hidden" name="reminder_id" value="<?= $rid ?>"><input type="hidden" name="return" value="<?= e($ret) ?>">
                        <select name="postpone" class="btn-xs" style="padding:2px"><?php foreach (lead_reminder_postpone_options() as $ok => $ol): if ($ok === 'custom') continue; ?><option value="<?= e($ok) ?>"<?= $ok === '60' ? ' selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?></select>
                        <button class="btn btn-xs"><?= icon('clock', 'icon-xs') ?>Ertele</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Dashboard "Bugünkü Lead Takipleri" kartını basar.
 * @param array $data lead_reminder_dashboard() çıktısı
 * @param array $counts lead_reminder_counts() çıktısı
 */
function lead_followup_dashboard_card(array $data, array $counts, string $actUrl, string $ret, string $heading = 'Bugünkü Lead Takipleri'): void
{
    $sections = [
        'overdue'   => ['Geciken', 'badge-danger'],
        'now'       => ['Şu An Yapılacaklar', 'badge-warning'],
        'today'     => ['Bugün Aranacaklar', 'badge-info'],
        'upcoming'  => ['Yaklaşanlar', 'badge-muted'],
        'completed' => ['Tamamlananlar', 'badge-success'],
    ];
    $hasOverdue = !empty($data['overdue']);
    ?>
    <section class="panel followup-panel" style="<?= $hasOverdue ? 'border:1px solid #dc2626;border-radius:8px' : '' ?>">
        <div class="panel-head" style="<?= $hasOverdue ? 'background:#fef2f2' : '' ?>">
            <h2 class="panel-title"><?= icon('bell-ring', 'icon-sm') ?> <?= e($heading) ?>
                <?php if ($hasOverdue): ?><span class="badge badge-danger" style="margin-left:6px"><?= count($data['overdue']) ?> geciken!</span><?php endif; ?>
            </h2>
            <a class="btn btn-sm" href="<?= e(url('modules/leads/reminders.php')) ?>">Tümü</a>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;padding:8px 12px">
            <span class="badge badge-info">Bugün: <?= (int) ($counts['today_open'] ?? 0) ?></span>
            <span class="badge <?= ($counts['overdue'] ?? 0) > 0 ? 'badge-danger' : 'badge-muted' ?>">Geciken: <?= (int) ($counts['overdue'] ?? 0) ?></span>
            <span class="badge badge-warning">1 saat içinde: <?= (int) ($counts['next_hour'] ?? 0) ?></span>
            <span class="badge badge-success">Tamamlanan: <?= (int) ($counts['done_today'] ?? 0) ?></span>
            <span class="badge badge-muted">Ulaşılamayan: <?= (int) ($counts['unreachable'] ?? 0) ?></span>
            <span class="badge badge-muted">Tarih verilecek: <?= (int) ($counts['need_reschedule'] ?? 0) ?></span>
        </div>
        <div style="padding:0 12px 10px">
            <?php
            $any = false;
            foreach ($sections as $key => [$title, $cls]) {
                $rows = $data[$key] ?? [];
                if (!$rows) { continue; }
                $any = true;
                echo '<div style="margin-top:8px"><div style="font-weight:600;font-size:13px;margin-bottom:2px"><span class="badge ' . $cls . '">' . count($rows) . '</span> ' . e($title) . '</div>';
                foreach ($rows as $r) { lead_followup_row_html($r, $actUrl, $ret); }
                echo '</div>';
            }
            if (!$any) { echo '<div class="empty-state empty-compact"><p>Bugün için planlı lead takibiniz yok.</p></div>'; }
            ?>
        </div>
    </section>
    <?php
}
