<?php
declare(strict_types=1);

/**
 * includes/service_maintenance_render.php
 * "Yaklaşan Bakımlar" dashboard kartı + satır çizimi (§4). Dashboard ile Bakım
 * Takipleri listesi arasında ortak kullanılır.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/service_maintenance.php';

/** Tek bakım satırı (kart içi). */
function smaint_maint_row_html(array $r, string $actUrl, string $ret): void
{
    $id     = (int) $r['id'];
    $days   = smaint_days_to_due((string) ($r['maintenance_due_date'] ?? ''));
    $device = trim(((string) ($r['brand_name'] ?? '')) . ' ' . ((string) ($r['device_model'] ?? '')));
    $who    = trim((string) ($r['company_name'] ?? '')) !== '' ? (string) $r['company_name'] : (string) ($r['customer_name'] ?? '—');
    $phone  = trim((string) ($r['phone'] ?? ''));
    $vurl   = url('modules/maintenance/view.php?id=' . $id);

    $waLink = null;
    if (($r['whatsapp'] ?? $phone) !== '') {
        if (!function_exists('build_whatsapp_message_link')) { require_once __DIR__ . '/notifications.php'; }
        $waLink = build_whatsapp_message_link((string) ($r['whatsapp'] ?: $phone), 'Periyodik bakım hatırlatması');
    }

    $dayBadge = '';
    if ($days === null) { $dayBadge = '<span class="text-muted">—</span>'; }
    elseif ($days < 0)  { $dayBadge = '<span class="badge badge-danger">' . abs($days) . ' gün geçti</span>'; }
    elseif ($days === 0) { $dayBadge = '<span class="badge badge-warning">Bugün</span>'; }
    else { $dayBadge = '<span class="badge badge-muted">' . $days . ' gün</span>'; }
    ?>
    <div class="fu-row" style="display:flex;gap:8px;align-items:flex-start;padding:6px 0;border-top:1px solid #f0f0f0">
        <div style="flex:1;min-width:0">
            <div style="font-weight:600"><?= e($who) ?> <?= $dayBadge ?></div>
            <div class="text-muted" style="font-size:13px">
                <?= e($device !== '' ? $device : 'Cihaz') ?>
                <?php if (!empty($r['serial_no'])): ?> · SN: <?= e((string) $r['serial_no']) ?><?php endif; ?>
                · Bakım: <?= e(substr((string) $r['maintenance_due_date'], 0, 10)) ?>
                <?php if ($phone !== ''): ?> · <?= e($phone) ?><?php endif; ?>
            </div>
            <div class="text-muted" style="font-size:12px">
                <?= e((string) ($r['assignee_name'] ?? 'Atanmamış')) ?>
                · <?= e(smaint_status_label((string) $r['status'])) ?>
                <?php if (!empty($r['last_contact_at'])): ?> · Son iletişim: <?= e(fmt_date((string) $r['last_contact_at'])) ?><?php endif; ?>
            </div>
        </div>
        <div style="display:flex;gap:4px;flex-wrap:wrap;align-items:center">
            <a class="btn btn-sm" href="<?= e($vurl) ?>" title="Aç"><?= icon('eye') ?></a>
            <?php if ($phone !== ''): ?><a class="btn btn-sm" href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>" title="Ara"><?= icon('phone') ?></a><?php endif; ?>
            <?php if ($waLink): ?><a class="btn btn-sm btn-wa" href="<?= e($waLink) ?>" target="_blank" rel="noopener" title="WhatsApp"><?= icon('message-circle') ?></a><?php endif; ?>
            <?php if (can_maint_message()): ?>
            <form method="post" action="<?= e($actUrl) ?>" style="display:inline"><?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($ret) ?>">
                <input type="hidden" name="action" value="mark_called">
                <button class="btn btn-sm" type="submit" title="Arandı"><?= icon('check-circle') ?></button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/** "Yaklaşan Bakımlar" dashboard kartı (§4). */
function smaint_dashboard_card(array $data, array $counts, string $actUrl, string $ret, string $heading = 'Yaklaşan Bakımlar'): void
{
    $sections = [
        'overdue'     => ['Geciken Bakımlar', 'badge-danger'],
        'today'       => ['Bugün Bakım Zamanı Gelenler', 'badge-warning'],
        'week'        => ['Bu Hafta Bakım Zamanı Gelenler', 'badge-info'],
        'need_msg'    => ['Mesaj Gönderilmesi Gerekenler', 'badge-muted'],
        'awaiting'    => ['Müşteri Dönüşü Beklenenler', 'badge-info'],
        'appointment' => ['Bakım Randevusu Oluşturulanlar', 'badge-success'],
    ];
    $hasOverdue = !empty($data['overdue']);
    ?>
    <section class="panel maint-panel" style="<?= $hasOverdue ? 'border:1px solid #dc2626;border-radius:8px' : '' ?>">
        <div class="panel-head" style="<?= $hasOverdue ? 'background:#fef2f2' : '' ?>">
            <h2 class="panel-title"><?= icon('wrench', 'icon-sm') ?> <?= e($heading) ?>
                <?php if ($hasOverdue): ?><span class="badge badge-danger" style="margin-left:6px"><?= count($data['overdue']) ?> geciken!</span><?php endif; ?>
            </h2>
            <a class="btn btn-sm" href="<?= e(url('modules/maintenance/index.php')) ?>">Tümü</a>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;padding:8px 12px">
            <span class="badge badge-warning">Bugün: <?= (int) ($counts['today'] ?? 0) ?></span>
            <span class="badge badge-info">Bu hafta: <?= (int) ($counts['week'] ?? 0) ?></span>
            <span class="badge <?= ($counts['overdue'] ?? 0) > 0 ? 'badge-danger' : 'badge-muted' ?>">Geciken: <?= (int) ($counts['overdue'] ?? 0) ?></span>
            <span class="badge badge-muted">Mesaj bekleyen: <?= (int) ($counts['msg_unsent'] ?? 0) ?></span>
            <span class="badge badge-info">Bekleyen dönüş: <?= (int) ($counts['awaiting'] ?? 0) ?></span>
            <span class="badge badge-success">Randevu: <?= (int) ($counts['appointment'] ?? 0) ?></span>
        </div>
        <div style="padding:0 12px 10px">
            <?php
            $any = false;
            foreach ($sections as $key => [$title, $cls]) {
                $rows = $data[$key] ?? [];
                if (!$rows) { continue; }
                $any = true;
                echo '<div style="margin-top:8px"><div style="font-weight:600;font-size:13px;margin-bottom:2px"><span class="badge ' . $cls . '">' . count($rows) . '</span> ' . e($title) . '</div>';
                foreach ($rows as $row) { smaint_maint_row_html($row, $actUrl, $ret); }
                echo '</div>';
            }
            if (!$any) { echo '<div class="empty-state empty-compact"><p>Yaklaşan bakım kaydınız yok.</p></div>'; }
            ?>
        </div>
    </section>
    <?php
}
