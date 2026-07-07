<?php
declare(strict_types=1);

/**
 * dashboard.php
 * Genel Bakış — widget tabanlı, kullanıcıya özel düzenlenebilir.
 * Widget kayıt/veri/düzen mantığı includes/dashboard.php içindedir.
 * İçine PDO/kur API/auth mantığı GÖMÜLMEZ.
 */
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/dashboard.php';

auth_boot();
require_permission('dashboard');

$u    = current_user();
$uid  = current_user_id() ?? 0;
$name = $u['full_name'] !== '' ? $u['full_name'] : $u['username'];

$layout   = get_dashboard_layout($uid);
$registry = dashboard_widget_registry();
$rates    = get_dashboard_exchange_rates();

$visibleCount = 0;
foreach ($layout as $e) { if (!empty($e['visible'])) { $visibleCount++; } }

/* ---- Widget aksiyon linki (yetki varsa) ---- */
function dash_widget_action_link(string $group): void
{
    if ($group === 'service' && can('service')) {
        echo '<a class="widget-link" href="' . e(url('modules/service/index.php')) . '">Tümünü gör ' . icon('chevron-left', 'icon-xs widget-link-ic') . '</a>';
    } elseif ($group === 'rma' && can('rma')) {
        echo '<a class="widget-link" href="' . e(url('modules/rma/index.php')) . '">Tümünü gör ' . icon('chevron-left', 'icon-xs widget-link-ic') . '</a>';
    } elseif ($group === 'currency' && can('currency')) {
        echo '<a class="widget-link" href="' . e(url('modules/currency/index.php')) . '">Kur çeviriciyi aç ' . icon('chevron-left', 'icon-xs widget-link-ic') . '</a>';
    }
}

/* ---- Liste widget gövdesi ---- */
function dash_render_list(string $id, array $rows): void
{
    if (empty($rows)) {
        echo '<div class="widget-empty">Veri yok</div>';
        return;
    }
    echo '<ul class="widget-list">';
    foreach ($rows as $r) {
        $ref  = (string) ($r['reference_code'] ?? '');
        $cust = (string) ($r['customer_name'] ?? '');
        $stat = (string) ($r['status'] ?? '');
        if ($id === 'latest_rma_records') {
            $label = rma_status_label($stat);
            $cls   = rma_status_class($stat);
            $pt    = (string) ($r['process_type'] ?? '');
            $ptl   = rma_process_types()[$pt] ?? $pt;
            $tag   = $ptl !== '' ? '<span class="widget-tag">' . e($ptl) . '</span>' : '';
        } else {
            $label = service_status_label($stat);
            $cls   = service_status_class($stat);
            $tag   = '';
        }
        echo '<li class="widget-list-row">'
           . '<span class="wlr-ref">' . e($ref) . '</span>'
           . '<span class="wlr-cust">' . e($cust) . '</span>'
           . $tag
           . '<span class="badge ' . e($cls) . '">' . e($label) . '</span>'
           . '</li>';
    }
    echo '</ul>';
}

/* ---- İK bildirim liste widget gövdesi ---- */
function dash_render_hrlist(string $id, array $rows): void
{
    if (empty($rows)) { echo '<div class="widget-empty">Kayıt yok</div>'; return; }
    echo '<ul class="hr-wlist">';
    foreach ($rows as $r) {
        $name = (string) ($r['name'] ?? '');
        $info = (string) ($r['info'] ?? '');
        $wa = null;
        if (!empty($r['ctype']) && !empty($r['pid'])) { $wa = whatsapp_celebration_link((int) $r['pid'], (string) $r['ctype']); }
        echo '<li class="hr-wrow"><span class="hr-wname">' . e($name) . '</span>';
        if ($wa) { echo '<a class="hr-winfo btn-wa" href="' . e($wa) . '" target="_blank" rel="noopener" title="WhatsApp ile kutla">' . icon('message-circle', 'icon-xs') . ' ' . e($info) . '</a>'; }
        else { echo '<span class="hr-winfo">' . e($info) . '</span>'; }
        echo '</li>';
    }
    echo '</ul>';
    echo '<a class="widget-link" href="' . e(url('modules/notifications/index.php')) . '">Bildirim Merkezi ' . icon('chevron-left', 'icon-xs widget-link-ic') . '</a>';
}

/* ---- Kutlama satırları (Doğum Günleri kartı: kişi solda, WhatsApp/Mail sağda) ---- */
function dash_render_celebration_rows(array $people, string $type): void
{
    if (empty($people)) { return; }
    $csrf = csrf_field();
    $celebrateUrl = e(url('modules/notifications/celebrate.php'));
    foreach ($people as $p) {
        $pid   = (int) ($p['id'] ?? 0);
        $name  = (string) ($p['full_name'] ?? '');
        $email = trim((string) ($p['email'] ?? ''));
        $wa    = ($pid > 0) ? whatsapp_celebration_link($pid, $type) : null;

        if ($type === 'birthday') {
            $when = (isset($p['days']) && (int) $p['days'] === 0) ? 'Bugün 🎉' : ((int) ($p['days'] ?? 0) . ' gün sonra');
            $rowIcon = 'gift';
        } else {
            $yers = (int) ($p['years'] ?? 0);
            $when = $yers . '. yıl' . ((isset($p['days']) && (int) $p['days'] === 0) ? ' · bugün' : ' · ' . (int) ($p['days'] ?? 0) . ' gün sonra');
            $rowIcon = 'user-check';
        }

        echo '<div class="birthday-row">';
        echo   '<div class="birthday-person">';
        echo     '<span class="birthday-ic">' . icon($rowIcon, 'icon-sm') . '</span>';
        echo     '<span class="birthday-person-text">';
        echo       '<span class="birthday-name">' . e($name) . '</span>';
        echo       '<span class="birthday-date">' . e($when) . '</span>';
        echo     '</span>';
        echo   '</div>';
        echo   '<div class="birthday-actions">';
        if ($wa) {
            echo '<a class="quick-action-btn quick-action-whatsapp" href="' . e($wa) . '" target="_blank" rel="noopener" title="WhatsApp ile kutla">' . icon('message-circle', 'icon-xs') . '<span>WhatsApp</span></a>';
        } else {
            echo '<span class="birthday-note" title="Telefon numarası kayıtlı değil">Telefon yok</span>';
        }
        if ($email !== '') {
            echo '<form method="post" action="' . $celebrateUrl . '" class="birthday-action-form">'
               . $csrf
               . '<input type="hidden" name="action" value="mail"><input type="hidden" name="type" value="' . e($type) . '">'
               . '<input type="hidden" name="personnel_id" value="' . $pid . '"><input type="hidden" name="return" value="dashboard">'
               . '<button type="submit" class="quick-action-btn quick-action-mail" title="Mail ile kutla">' . icon('mail', 'icon-xs') . '<span>Mail</span></button>'
               . '</form>';
        } else {
            echo '<span class="birthday-note" title="E-posta adresi kayıtlı değil">E-posta yok</span>';
        }
        echo   '</div>';
        echo '</div>';
    }
}

/* ---- Kur bilgisi widget gövdesi ---- */
function dash_render_kur(array $d): void
{
    $fm = static fn($v) => $v !== null ? fmt_money((float) $v, 4) : '—';
    echo '<div class="widget-rate-list">';
    echo '<div class="widget-rate"><span>USD → TRY</span><b>' . e($fm($d['usd_try'] ?? null)) . '</b></div>';
    echo '<div class="widget-rate"><span>EUR → TRY</span><b>' . e($fm($d['eur_try'] ?? null)) . '</b></div>';
    echo '<div class="widget-rate"><span>EUR → USD</span><b>' . e($fm($d['eur_usd'] ?? null)) . '</b></div>';
    echo '</div>';
    echo '<div class="widget-sub">Kaynak: ' . e((string) ($d['source'] ?? '')) . ' · ' . e((string) ($d['date'] ?? '')) . '</div>';
    if (can('currency')) {
        echo '<a class="widget-link" href="' . e(url('modules/currency/index.php')) . '">Kur çeviriciyi aç ' . icon('chevron-left', 'icon-xs widget-link-ic') . '</a>';
    }
}

/* ---- Hızlı kur çevirici widget gövdesi (anlık; app.js #converter) ---- */
function dash_render_converter(): void
{
    $symbols = kur_symbols();
    ?>
    <form id="converter" method="post" action="<?= e(url('modules/currency/converter.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="cv-amount">Tutar</label>
            <input type="text" id="cv-amount" name="amount" inputmode="decimal" value="1">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="cv-from">Kaynak</label>
                <select id="cv-from" name="from">
                    <?php foreach ($symbols as $s): ?><option value="<?= e($s) ?>"<?= $s === 'USD' ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="cv-to">Hedef</label>
                <select id="cv-to" name="to">
                    <?php foreach ($symbols as $s): ?><option value="<?= e($s) ?>"<?= $s === 'TRY' ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="swap-row">
            <button type="button" class="btn btn-sm" id="cv-swap"><?= icon('refresh-cw', 'icon-sm') ?>Yönü değiştir</button>
            <button type="submit" class="btn btn-primary btn-sm">Çevir</button>
        </div>
        <div style="margin-top:12px"><label>Sonuç</label><div class="converter-result" id="cv-result">—</div></div>
    </form>
    <?php
}

/* ---- Tek bir widget kartı ---- */
function dash_render_widget(string $id, array $meta, array $entry): void
{
    $uid     = current_user_id() ?? 0;
    $size    = (string) $entry['size'];
    $visible = !empty($entry['visible']);
    $data    = get_dashboard_widget_data($id, $uid);
    $sizeLabels = ['small' => 'Küçük', 'medium' => 'Orta', 'wide' => 'Geniş', 'full' => 'Tam'];
    ?>
    <div class="dashboard-widget widget-<?= e($size) ?><?= $visible ? '' : ' is-off' ?>"
         data-widget-id="<?= e($id) ?>" data-size="<?= e($size) ?>" data-visible="<?= $visible ? '1' : '0' ?>">
        <div class="widget-edit-bar">
            <span class="widget-drag" title="Sürükle" aria-hidden="true"><?= icon('menu', 'icon-sm') ?></span>
            <span class="widget-eb-title"><?= e($meta['title']) ?></span>
            <span class="widget-eb-controls">
                <select class="widget-size-sel" aria-label="Boyut">
                    <?php foreach ($sizeLabels as $sv => $sl): ?><option value="<?= e($sv) ?>"<?= $sv === $size ? ' selected' : '' ?>><?= e($sl) ?></option><?php endforeach; ?>
                </select>
                <button type="button" class="widget-move" data-dir="up" title="Yukarı taşı" aria-label="Yukarı taşı"><?= icon('chevron-left', 'icon-sm') ?></button>
                <button type="button" class="widget-move" data-dir="down" title="Aşağı taşı" aria-label="Aşağı taşı"><?= icon('chevron-left', 'icon-sm') ?></button>
                <button type="button" class="widget-vis" title="Göster/Gizle" aria-label="Göster/Gizle"><?= icon($visible ? 'eye' : 'eye-off', 'icon-sm') ?></button>
            </span>
        </div>
        <div class="widget-body">
            <div class="widget-head"><span class="widget-ic"><?= icon($meta['icon']) ?></span><span class="widget-title"><?= e($meta['title']) ?></span></div>
            <?php
            switch ($meta['type']) {
                case 'stat':
                    echo '<div class="widget-value">' . e((string) ($data['value'] ?? '0')) . '</div>';
                    echo '<div class="widget-sub">' . e((string) ($data['sub'] ?? '')) . '</div>';
                    dash_widget_action_link($meta['group']);
                    break;
                case 'list':
                    dash_render_list($id, $data['list'] ?? []);
                    dash_widget_action_link($meta['group']);
                    break;
                case 'hrlist':
                    dash_render_hrlist($id, $data['list'] ?? []);
                    break;
                case 'kur':
                    dash_render_kur($data);
                    break;
                case 'converter':
                    dash_render_converter();
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}

// İK bildirimleri: bu kullanıcı için üret (cron olmasa da çalışır) + günün özeti
if ($uid > 0) { generate_daily_hr_notifications(null, $uid); }
$hrSummary = get_daily_summary();
$hrSummaryActive = ((int) $hrSummary['today_leaves'] + (int) $hrSummary['today_birthdays'] + (int) $hrSummary['today_anniversaries'] + (int) $hrSummary['upcoming_anniv'] + (int) $hrSummary['upcoming_birthdays'] + (int) $hrSummary['pending_leaves']) > 0;

// Kutlama detayları (kim + WhatsApp/Mail): Günün Özeti içinde gösterilir.
$hrSet = notif_settings();
$bdToday    = $hrSet['birthday_enabled'] ? get_today_birthdays() : [];
$bdUpcoming = $hrSet['birthday_enabled'] ? get_upcoming_birthdays($hrSet['birthday_days_before']) : [];
$annToday   = $hrSet['anniversary_enabled'] ? get_today_work_anniversaries() : [];
$annUpcoming = $hrSet['anniversary_enabled'] ? get_upcoming_work_anniversaries($hrSet['anniversary_days_before']) : [];
$hasCelebrations = ($bdToday || $bdUpcoming || $annToday || $annUpcoming);

layout_top('Genel Bakış', 'dashboard');
?>

<div class="dash-head">
    <div class="dash-head-text">
        <h1 class="page-title">Genel Bakış</h1>
        <p class="muted">Hoş geldiniz, <?= e($name) ?>. Panelinizi kişiselleştirmek için widget'ları düzenleyebilirsiniz.</p>
    </div>
    <div class="dash-actions">
        <button type="button" id="dashEditBtn" class="btn btn-sm"><?= icon('sliders-horizontal') ?>Dashboard'u Düzenle</button>
        <div class="dash-edit-actions" id="dashEditActions" hidden>
            <button type="button" id="dashResetBtn" class="btn btn-sm"><?= icon('rotate-ccw') ?>Varsayılana Sıfırla</button>
            <button type="button" id="dashCancelBtn" class="btn btn-sm">Vazgeç</button>
            <button type="button" id="dashSaveBtn" class="btn btn-primary btn-sm"><?= icon('check-circle') ?>Kaydet</button>
        </div>
    </div>
</div>

<?= render_flashes() ?>

<?php
$notifCount = (int) $hrSummary['today_leaves'] + (int) $hrSummary['today_birthdays']
    + (int) $hrSummary['upcoming_birthdays'] + (int) $hrSummary['today_anniversaries']
    + (int) $hrSummary['upcoming_anniv'] + (int) $hrSummary['pending_leaves'];
?>
<section class="dashboard-summary">
    <div class="dashboard-summary-header">
        <h2 class="dashboard-summary-title">Günün Özeti</h2>
        <p class="dashboard-summary-subtitle">Hoş geldin, <?= e($name) ?>.</p>
    </div>

    <div class="dashboard-summary-grid">
        <!-- Kart 1: Yaklaşan Doğum Günleri -->
        <article class="summary-card birthday-card">
            <div class="birthday-card-header">
                <span class="summary-card-ic"><?= icon('gift', 'icon-sm') ?></span>
                <h3 class="summary-card-title">Yaklaşan Doğum Günleri</h3>
            </div>
            <?php if ($hasCelebrations): ?>
            <div class="birthday-list">
                <?php
                dash_render_celebration_rows($bdToday, 'birthday');
                dash_render_celebration_rows($bdUpcoming, 'birthday');
                dash_render_celebration_rows($annToday, 'anniversary');
                dash_render_celebration_rows($annUpcoming, 'anniversary');
                ?>
            </div>
            <?php else: ?>
            <p class="summary-empty">Bugün ve yaklaşan günlerde kayıtlı doğum günü bulunmuyor.</p>
            <?php endif; ?>
        </article>

        <!-- Kart 2: Bildirim Merkezi -->
        <article class="summary-card notification-card">
            <div class="birthday-card-header">
                <span class="summary-card-ic"><?= icon('bell', 'icon-sm') ?></span>
                <h3 class="summary-card-title">Bildirim Merkezi</h3>
            </div>
            <?php if ($notifCount > 0): ?>
            <div class="notification-chips">
                <?php if ((int) $hrSummary['today_leaves'] > 0): ?><span class="summary-chip"><?= icon('calendar-check', 'icon-xs') ?> Bugün <?= (int) $hrSummary['today_leaves'] ?> personel izinde</span><?php endif; ?>
                <?php if ((int) $hrSummary['today_birthdays'] > 0): ?><span class="summary-chip"><?= icon('gift', 'icon-xs') ?> <?= (int) $hrSummary['today_birthdays'] ?> doğum günü bugün</span><?php endif; ?>
                <?php if ((int) $hrSummary['upcoming_birthdays'] > 0): ?><span class="summary-chip"><?= icon('gift', 'icon-xs') ?> <?= (int) $hrSummary['upcoming_birthdays'] ?> yaklaşan doğum günü</span><?php endif; ?>
                <?php if ((int) $hrSummary['today_anniversaries'] > 0): ?><span class="summary-chip"><?= icon('user-check', 'icon-xs') ?> <?= (int) $hrSummary['today_anniversaries'] ?> yıl dönümü bugün</span><?php endif; ?>
                <?php if ((int) $hrSummary['upcoming_anniv'] > 0): ?><span class="summary-chip"><?= icon('user-check', 'icon-xs') ?> <?= (int) $hrSummary['upcoming_anniv'] ?> yaklaşan yıl dönümü</span><?php endif; ?>
                <?php if ((int) $hrSummary['pending_leaves'] > 0): ?><a class="summary-chip summary-chip-link" href="<?= e(url('modules/leave/index.php')) ?>"><?= icon('clock', 'icon-xs') ?> <?= (int) $hrSummary['pending_leaves'] ?> onay bekleyen izin</a><?php endif; ?>
            </div>
            <?php else: ?>
            <p class="summary-empty">Yeni bildiriminiz bulunmuyor.</p>
            <?php endif; ?>
            <a class="summary-card-link" href="<?= e(url('modules/notifications/index.php')) ?>"><?= icon('bell', 'icon-xs') ?> Bildirim Merkezi'ni aç</a>
        </article>

        <!-- Kart 3: Hızlı İşlemler -->
        <article class="summary-card quick-actions-card">
            <div class="birthday-card-header">
                <span class="summary-card-ic"><?= icon('sliders-horizontal', 'icon-sm') ?></span>
                <h3 class="summary-card-title">Hızlı İşlemler</h3>
            </div>
            <div class="quick-actions-list">
                <a class="summary-quick-link" href="<?= e(url('modules/notifications/index.php')) ?>"><?= icon('bell', 'icon-xs') ?> <span>Bildirim Merkezi</span></a>
                <?php if (can('leave')): ?><a class="summary-quick-link" href="<?= e(url('modules/leave/index.php')) ?>"><?= icon('calendar-days', 'icon-xs') ?> <span>İzin / İK</span></a><?php endif; ?>
                <?php if (can('service')): ?><a class="summary-quick-link" href="<?= e(url('modules/service/index.php')) ?>"><?= icon('clipboard-list', 'icon-xs') ?> <span>Teknik Servis</span></a><?php endif; ?>
                <?php if (can('settings')): ?><a class="summary-quick-link" href="<?= e(url('modules/settings/smtp-test.php')) ?>"><?= icon('mail', 'icon-xs') ?> <span>SMTP Test</span></a><?php endif; ?>
            </div>
        </article>
    </div>
</section>

<div class="dashboard-grid" id="dashGrid">
    <?php foreach ($layout as $entry):
        $id = $entry['id'];
        $meta = $registry[$id] ?? null;
        if ($meta === null) { continue; }
        dash_render_widget($id, $meta, $entry);
    endforeach; ?>
</div>

<div class="dash-empty" id="dashEmpty"<?= $visibleCount > 0 ? ' hidden' : '' ?>>
    <?= icon('layout-dashboard', 'icon-lg') ?>
    <p>Dashboard'da gösterilecek widget yok.<br><strong>Dashboard'u Düzenle</strong> butonuyla widget ekleyebilirsiniz.</p>
</div>

<script>
    window.PANEL_RATES = <?= json_encode($rates['rates'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    window.DASH_ENDPOINT = <?= json_encode(url('api/dashboard-layout.php'), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= e(asset('js/dashboard.js')) ?>"></script>

<?php
layout_bottom();
