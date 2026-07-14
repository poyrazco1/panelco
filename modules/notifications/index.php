<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/lead_reminders.php';
require_once __DIR__ . '/../../includes/lead_notifications.php';

auth_boot();
require_permission('dashboard'); // tüm giriş yapmış kullanıcılar

$uid = (int) (current_user_id() ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = (string) ($_POST['op'] ?? '');
    if ($op === 'mark_read') { mark_notification_read((int) ($_POST['id'] ?? 0), $uid); flash('success', 'Okundu işaretlendi.'); }
    elseif ($op === 'mark_all') { mark_all_notifications_read($uid); flash('success', 'Tüm bildirimler okundu.'); }
    elseif ($op === 'delete') { delete_user_notification((int) ($_POST['id'] ?? 0), $uid); flash('success', 'Bildirim arşivlendi.'); }
    http_response_code(303);
    redirect('modules/notifications/index.php');
}

// Bu kullanıcı için güncel bildirimleri üret (cron olmasa da çalışır)
generate_daily_hr_notifications(null, $uid);

$filters = [
    'type'         => (string) ($_GET['type'] ?? ''),
    'is_read'      => ($_GET['is_read'] ?? '') === '' ? '' : (int) $_GET['is_read'],
    'date'         => (string) ($_GET['date'] ?? ''),
    'personnel_id' => (int) ($_GET['personnel_id'] ?? 0),
];
$notifs = get_user_notifications($uid, 100, $filters);
$types = notif_types();
$unread = get_unread_notification_count($uid);
$canDetails = notif_can_see_details();

$typeIcon = ['leave' => 'calendar-check', 'birthday' => 'gift', 'anniversary' => 'user-check', 'system' => 'info',
    'lead_followup_soon' => 'bell-ring', 'lead_followup_due' => 'bell-ring', 'lead_followup_overdue' => 'alarm-clock'];
$leadActUrl = e(url('modules/leads/lead-action.php'));

layout_top('Bildirim Merkezi', 'notifications');
?>

<div class="page-head">
    <h1 class="page-title">Bildirim Merkezi</h1>
    <div class="page-actions">
        <?php if ($unread > 0): ?>
        <form method="post" action="<?= e(url('modules/notifications/index.php')) ?>" style="display:inline">
            <?= csrf_field() ?><input type="hidden" name="op" value="mark_all">
            <button type="submit" class="btn btn-sm"><?= icon('check-check') ?>Tümünü Okundu Yap</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?= render_flashes() ?>

<div class="card">
    <div class="card-body">
        <form method="get" action="<?= e(url('modules/notifications/index.php')) ?>" class="toolbar">
            <div class="form-group">
                <label for="fl-type">Tip</label>
                <select id="fl-type" name="type"><option value="">Tümü</option><?php foreach ($types as $k => $lbl): ?><option value="<?= e($k) ?>"<?= $filters['type'] === $k ? ' selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group">
                <label for="fl-read">Durum</label>
                <select id="fl-read" name="is_read">
                    <option value="">Tümü</option>
                    <option value="0"<?= $filters['is_read'] === 0 ? ' selected' : '' ?>>Okunmamış</option>
                    <option value="1"<?= $filters['is_read'] === 1 ? ' selected' : '' ?>>Okunmuş</option>
                </select>
            </div>
            <div class="form-group"><label for="fl-date">Tarih</label><input type="date" id="fl-date" name="date" value="<?= e($filters['date']) ?>"></div>
            <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Filtrele</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($notifs)): ?>
            <p class="muted">Bildirim yok.</p>
        <?php else: ?>
        <ul class="notif-list">
            <?php foreach ($notifs as $n):
                $type = (string) $n['notification_type'];
                $celebrate = in_array($type, ['birthday', 'anniversary'], true) && (string) $n['related_type'] === 'personnel' && !empty($n['related_id']);
                $waLink = $celebrate ? whatsapp_celebration_link((int) $n['related_id'], $type) : null;
                $isLead = strncmp($type, 'lead_followup', 13) === 0 && (string) $n['related_type'] === 'lead_reminder';
                $leadRem = $isLead ? lead_reminder_get((int) $n['related_id']) : null;
            ?>
                <li class="notif-item<?= (int) $n['is_read'] === 0 ? ' is-unread' : '' ?>">
                    <span class="notif-ic notif-<?= e($type) ?>"><?= icon($typeIcon[$type] ?? 'bell', 'icon-sm') ?></span>
                    <div class="notif-main">
                        <div class="notif-title"><?= e((string) $n['title']) ?><?php if (!empty($n['notification_date'])): ?><span class="notif-date"><?= e(fmt_date((string) $n['notification_date'])) ?></span><?php endif; ?></div>
                        <div class="notif-msg"><?= e((string) $n['message']) ?></div>
                        <div class="notif-actions">
                            <?php if ($isLead): $lid = (int) ($leadRem['lead_id'] ?? 0);
                                $phone = $leadRem ? lead_normalize_phone((string) ($leadRem['phone'] ?? $leadRem['whatsapp'] ?? '')) : '';
                                $waDigits = $phone !== '' ? ltrim(preg_replace('/\D+/', '', $phone) ?? '', '0') : '';
                                $rid = (int) ($n['related_id']); ?>
                                <a class="btn btn-xs btn-primary" href="<?= e(url((string) ($n['action_url'] ?: 'modules/leads/view.php?id=' . $lid))) ?>"><?= icon('eye', 'icon-xs') ?>Lead Detayı</a>
                                <?php if ($phone !== ''): ?>
                                    <a class="btn btn-xs" href="tel:<?= e($phone) ?>"><?= icon('phone', 'icon-xs') ?>Ara</a>
                                    <a class="btn btn-xs btn-wa" href="https://wa.me/<?= e($waDigits) ?>" target="_blank" rel="noopener"><?= icon('message-circle', 'icon-xs') ?>WhatsApp</a>
                                <?php endif; ?>
                                <?php if ($leadRem && (string) $leadRem['status'] !== 'done' && (string) $leadRem['status'] !== 'cancelled' && can('leads.remind')): ?>
                                    <a class="btn btn-xs" href="<?= e(url('modules/leads/view.php?id=' . $lid . '&tab=reminders')) ?>"><?= icon('check', 'icon-xs') ?>Tamamla</a>
                                    <?php foreach (['15' => '15dk', '60' => '1 saat', 'tomorrow' => 'Yarın'] as $po => $pl): ?>
                                    <form method="post" action="<?= $leadActUrl ?>" style="display:inline"><?= csrf_field() ?>
                                        <input type="hidden" name="action" value="reminder_postpone"><input type="hidden" name="id" value="<?= $lid ?>">
                                        <input type="hidden" name="reminder_id" value="<?= $rid ?>"><input type="hidden" name="postpone" value="<?= e($po) ?>">
                                        <input type="hidden" name="return" value="modules/notifications/index.php">
                                        <button class="btn btn-xs" title="Ertele"><?= icon('clock', 'icon-xs') ?><?= e($pl) ?></button>
                                    </form>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($celebrate && $waLink): ?>
                                <a class="btn btn-xs btn-wa" href="<?= e($waLink) ?>" target="_blank" rel="noopener"><?= icon('message-circle', 'icon-xs') ?>WhatsApp ile Kutla</a>
                            <?php elseif ($celebrate): ?>
                                <span class="notif-warn">Telefon yok</span>
                            <?php endif; ?>
                            <?php if ($celebrate && !empty($n['personnel_email'])): ?>
                                <form method="post" action="<?= e(url('modules/notifications/celebrate.php')) ?>" style="display:inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="mail"><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="personnel_id" value="<?= (int) $n['related_id'] ?>">
                                    <button type="submit" class="btn btn-xs"><?= icon('mail', 'icon-xs') ?>Mail ile Kutla</button>
                                </form>
                            <?php elseif ($celebrate): ?>
                                <span class="notif-warn">E-posta yok</span>
                            <?php endif; ?>
                            <?php if ($celebrate): ?>
                                <form method="post" action="<?= e(url('modules/notifications/celebrate.php')) ?>" style="display:inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="mark"><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="personnel_id" value="<?= (int) $n['related_id'] ?>">
                                    <button type="submit" class="btn btn-xs"><?= icon('check-circle', 'icon-xs') ?>Kutlandı</button>
                                </form>
                            <?php endif; ?>
                            <?php if ((int) $n['is_read'] === 0): ?>
                                <form method="post" action="<?= e(url('modules/notifications/index.php')) ?>" style="display:inline">
                                    <?= csrf_field() ?><input type="hidden" name="op" value="mark_read"><input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                                    <button type="submit" class="btn btn-xs">Okundu</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('modules/notifications/index.php')) ?>" style="display:inline" data-confirm="Bildirim arşivlensin mi?">
                                <?= csrf_field() ?><input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                                <button type="submit" class="btn btn-xs action-icon-btn is-danger" title="Arşivle"><?= icon('trash-2', 'icon-xs') ?></button>
                            </form>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$canDetails): ?><p class="field-hint" style="margin-top:12px">İzin açıklamaları ve özel notlar yalnızca İK/Yönetici rollerine gösterilir (KVKK).</p><?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
layout_bottom();
