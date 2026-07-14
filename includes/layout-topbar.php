<?php
declare(strict_types=1);
/**
 * includes/layout-topbar.php — üst çubuk; .main ve .content alanını açar.
 */
$u       = current_user();
$name    = $u['full_name'] !== '' ? $u['full_name'] : $u['username'];
$initial = mb_strtoupper(mb_substr($name !== '' ? $name : '?', 0, 1, 'UTF-8'), 'UTF-8');

// Header kur mini widget verisi: ÖNBELLEK öncelikli (sayfa açılışını yavaşlatmaz).
// Hiç önbellek yoksa bir kez tam alım yapılır (sonrasında hep önbellekten).
require_once __DIR__ . '/../kur.php';
require_once __DIR__ . '/csrf.php';
$kurRates = null;
try {
    $k = kur_cache_read();
    if ($k === null) { $k = kur_get_rates(); }
    $kurRates = $k;
} catch (Throwable $e) {
    log_error('topbar kur widget: ' . $e->getMessage());
    $kurRates = null;
}
$kurUsdTry = ($kurRates !== null) ? kur_convert($kurRates, 1.0, 'USD', 'TRY') : null;
$kurEurTry = ($kurRates !== null) ? kur_convert($kurRates, 1.0, 'EUR', 'TRY') : null;
$kurUpdated = ($kurRates !== null) ? (($kurRates['fetched_at'] ?? '') !== '' ? $kurRates['fetched_at'] : ($kurRates['date'] ?? '')) : '';

// Header bildirim (İK) — okundu sayısı + son bildirimler (yalnız okuma; üretim dashboard/merkezde)
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/lead_notifications.php';
$notifUid = current_user_id();
$notifUnread = $notifUid ? get_unread_notification_count((int) $notifUid) : 0;
$notifRecent = $notifUid ? get_user_notifications((int) $notifUid, 8) : [];
$notifTypeIcon = ['leave' => 'calendar-check', 'birthday' => 'gift', 'anniversary' => 'user-check', 'system' => 'info',
    'lead_followup_soon' => 'bell-ring', 'lead_followup_due' => 'bell-ring', 'lead_followup_overdue' => 'alarm-clock'];
$notifLastId  = $notifUid ? latest_notification_id((int) $notifUid) : 0;
$notifPrefs   = $notifUid ? lead_notif_prefs((int) $notifUid) : lead_notif_pref_defaults();
?>
<div class="main">
    <header class="topbar">
        <button type="button" class="menu-btn" id="menuBtn"
                aria-label="Menüyü aç/kapat" aria-controls="sidebar" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
        <div class="topbar-title"><?= e(page_title()) ?></div>
        <div class="topbar-spacer"></div>

        <div class="notif-widget" id="notifWidget">
            <button type="button" class="notif-trigger" id="notifTrigger" aria-haspopup="dialog" aria-expanded="false" title="Bildirimler">
                <?= icon('bell') ?>
                <?php if ($notifUnread > 0): ?><span class="notif-badge" id="notifBadge"><?= $notifUnread > 99 ? '99+' : (int) $notifUnread ?></span><?php endif; ?>
            </button>
            <div class="notif-pop" id="notifPop" hidden>
                <div class="notif-pop-head">
                    <strong>Bildirimler</strong>
                    <button type="button" class="notif-markall" id="notifMarkAll">Tümünü oku</button>
                </div>
                <div class="notif-pop-body" id="notifPopBody">
                    <?php if (empty($notifRecent)): ?>
                        <div class="notif-empty">Yeni bildirim yok.</div>
                    <?php else: ?>
                        <?php foreach ($notifRecent as $n): ?>
                            <div class="notif-row<?= (int) $n['is_read'] === 0 ? ' is-unread' : '' ?>">
                                <span class="notif-ic notif-<?= e((string) $n['notification_type']) ?>"><?= icon($notifTypeIcon[$n['notification_type']] ?? 'bell', 'icon-xs') ?></span>
                                <div class="notif-row-main">
                                    <div class="notif-row-title"><?= e((string) $n['title']) ?></div>
                                    <div class="notif-row-msg"><?= e((string) $n['message']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="notif-pop-foot">
                    <a href="<?= e(url('modules/notifications/index.php')) ?>">Bildirim Merkezi</a>
                </div>
            </div>
        </div>

        <?php if ($kurRates !== null): ?>
        <div class="kur-widget" id="kurWidget">
            <button type="button" class="kur-trigger" id="kurTrigger" aria-haspopup="dialog" aria-expanded="false" title="Hızlı kur çevirici">
                <?= icon('coins', 'icon-sm') ?>
                <span class="kur-vals">
                    <?php if ($kurUsdTry !== null): ?><span class="kur-pair"><b>USD</b>&nbsp;<span id="hdrUsd"><?= number_format($kurUsdTry, 2, ',', '.') ?></span></span><?php endif; ?>
                    <?php if ($kurUsdTry !== null && $kurEurTry !== null): ?><span class="kur-sep">·</span><?php endif; ?>
                    <?php if ($kurEurTry !== null): ?><span class="kur-pair"><b>EUR</b>&nbsp;<span id="hdrEur"><?= number_format($kurEurTry, 2, ',', '.') ?></span></span><?php endif; ?>
                </span>
            </button>
            <div class="kur-pop" id="kurPop" role="dialog" aria-label="Hızlı kur çevirici" hidden>
                <div class="kur-pop-head"><?= icon('coins', 'icon-sm') ?> Hızlı Kur Çevirici</div>
                <div class="kur-field">
                    <label for="kw-amount">Tutar</label>
                    <input type="text" id="kw-amount" inputmode="decimal" value="1000" autocomplete="off">
                </div>
                <div class="kur-field-row">
                    <div class="kur-field">
                        <label for="kw-from">Kaynak</label>
                        <select id="kw-from"><option value="TRY">TRY</option><option value="USD">USD</option><option value="EUR">EUR</option></select>
                    </div>
                    <button type="button" class="kur-swap action-icon-btn btn btn-sm" id="kw-swap" title="Yönü değiştir" aria-label="Yönü değiştir"><?= icon('refresh-cw', 'icon-sm') ?></button>
                    <div class="kur-field">
                        <label for="kw-to">Hedef</label>
                        <select id="kw-to"><option value="USD">USD</option><option value="TRY">TRY</option><option value="EUR">EUR</option></select>
                    </div>
                </div>
                <div class="kur-result">Sonuç: <strong id="kw-result">—</strong></div>
                <div class="kur-pop-foot">
                    <span class="muted small" id="kw-updated">Son güncelleme: <?= e($kurUpdated) ?></span>
                    <button type="button" class="btn btn-sm" id="kw-refresh"><?= icon('refresh-cw', 'icon-sm') ?>Güncelle</button>
                </div>
            </div>
        </div>
        <script>
            window.KUR_RATES = <?= json_encode(['rates' => $kurRates['rates'] ?? [], 'updated' => $kurUpdated], JSON_UNESCAPED_UNICODE) ?>;
            window.KUR_ENDPOINT = <?= json_encode(url('kur.php'), JSON_UNESCAPED_UNICODE) ?>;
        </script>
        <?php else: ?>
        <div class="kur-widget kur-err" title="Kur bilgisi alınamadı">
            <?= icon('coins', 'icon-sm') ?><span class="kur-vals muted small">Kur bilgisi alınamadı</span>
        </div>
        <?php endif; ?>

        <?php
        $themeIcon = ['light' => 'moon', 'dark' => 'sun', 'system' => 'monitor'][$uiTheme] ?? 'monitor';
        ?>
        <button type="button" class="hdr-btn sidebar-toggle" id="sidebarBtn" title="Kenar çubuğu" aria-label="Kenar çubuğunu daralt/gizle">
            <?= icon('panel-left-close') ?>
        </button>

        <div class="hdr-menu" id="themeMenu">
            <button type="button" class="hdr-btn" id="themeBtn" title="Tema" aria-haspopup="menu" aria-expanded="false" aria-label="Tema seç">
                <span id="themeIcon"><?= icon($themeIcon) ?></span>
            </button>
            <div class="hdr-pop" id="themePop" role="menu" aria-label="Tema" hidden>
                <button type="button" class="hdr-opt" role="menuitemradio" data-theme-value="light"<?= $uiTheme === 'light' ? ' aria-checked="true"' : '' ?>><?= icon('sun', 'icon-sm') ?> Light</button>
                <button type="button" class="hdr-opt" role="menuitemradio" data-theme-value="dark"<?= $uiTheme === 'dark' ? ' aria-checked="true"' : '' ?>><?= icon('moon', 'icon-sm') ?> Dark</button>
                <button type="button" class="hdr-opt" role="menuitemradio" data-theme-value="system"<?= $uiTheme === 'system' ? ' aria-checked="true"' : '' ?>><?= icon('monitor', 'icon-sm') ?> Sistem Varsayılanı</button>
            </div>
        </div>

        <script>
            window.CSRF_TOKEN = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;
            window.PREF_ENDPOINT = <?= json_encode(url('api/user-preferences.php'), JSON_UNESCAPED_UNICODE) ?>;
            window.NOTIF_ENDPOINT = <?= json_encode(url('api/notifications.php'), JSON_UNESCAPED_UNICODE) ?>;
            window.APP_BASE = <?= json_encode(url(''), JSON_UNESCAPED_UNICODE) ?>;
            window.NOTIF_LAST_ID = <?= (int) $notifLastId ?>;
            window.NOTIF_POLL_MS = 60000;
            window.NOTIF_SOUND = <?= !empty($notifPrefs['sound_enabled']) ? 'true' : 'false' ?>;
            window.UI_THEME = <?= json_encode($uiTheme, JSON_UNESCAPED_UNICODE) ?>;
            window.UI_SIDEBAR = <?= json_encode($uiSidebar, JSON_UNESCAPED_UNICODE) ?>;
        </script>

        <div class="topbar-user">
            <div class="user-meta">
                <span class="user-name"><?= e($name) ?></span>
                <span class="user-role muted small"><?= e($u['role_name'] !== '' ? $u['role_name'] : 'Rol atanmamış') ?></span>
            </div>
            <span class="user-avatar" aria-hidden="true"><?= e($initial) ?></span>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('logout.php')) ?>"><?= icon('log-out') ?>Çıkış</a>
        </div>
    </header>
    <main class="content">
        <?php render_flashes(); ?>
