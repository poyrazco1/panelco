<?php
declare(strict_types=1);

/**
 * dashboard.php
 * Genel Bakış — rol/yetki bazlı, aksiyon odaklı günlük karar ekranı.
 *
 * Yapı:
 *   1) Karşılama (selamlama + tarih + kur + bildirim)
 *   2) Kritik Özet Şeridi (yetkiye göre KPI kartları)
 *   3) Aksiyon Gerektirenler (gerçek, tıklanabilir iş listesi)
 *   4) Hızlı Başlat (role göre başlatma butonları)
 *   5) Yaklaşanlar (doğum günü / izin / teslimat) — kompakt
 *   6) Son Hareketler (modüller arası son işlemler) — veri yoksa gizli
 *
 * Veri hazırlığı includes/dashboard.php içindeki yetki-duyarlı, hataya
 * dayanıklı fonksiyonlardadır. Buraya PDO/kur/auth mantığı GÖMÜLMEZ.
 */
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/dashboard.php';

auth_boot();
require_permission('dashboard');

$u    = current_user();
$uid  = current_user_id() ?? 0;
$name = $u['full_name'] !== '' ? $u['full_name'] : $u['username'];

// İK bildirimlerini bu kullanıcı için tazele (cron olmasa da çalışır).
if ($uid > 0) { generate_daily_hr_notifications(null, $uid); }

$kpis       = dashboard_kpi_cards();
$actions    = dashboard_action_items();
$quick      = dashboard_quick_actions();
$upcoming   = dashboard_upcoming_items();
$recent     = dashboard_recent_activity();
$rates      = get_dashboard_exchange_rates();
$todayCtx   = dashboard_today_context();
$notifUnread = $uid > 0 ? get_unread_notification_count($uid) : 0;

// Lead takip (follow-up) kartı verisi (§21) — yalnız yetkili kullanıcı
$leadFollowData = null; $leadFollowCounts = null; $leadTeam = null;
if ($uid > 0 && can('leads.view')) {
    require_once __DIR__ . '/includes/lead_followup_render.php';
    $leadFollowCounts = lead_reminder_counts($uid, false);
    $leadFollowData   = lead_reminder_dashboard($uid, null);
    if (lead_reminders_can_see_team()) { $leadTeam = lead_reminder_team_by_user(); }
}

$fmtRate = static fn($v) => $v !== null ? number_format((float) $v, 2, ',', '.') : '—';

/** Aksiyon/hareket satırındaki tarihi kısa ve okunur biçime çevirir. */
function dash_when(string $dt): string
{
    if ($dt === '') { return ''; }
    try {
        $d = new DateTimeImmutable($dt);
        return $d->format('d.m.Y');
    } catch (Throwable $e) { return e($dt); }
}

layout_top('Genel Bakış', 'dashboard');
?>

<?= render_flashes() ?>

<!-- 1) KARŞILAMA -->
<section class="dash-hero">
    <div class="dash-hero-text">
        <h1 class="dash-hero-title"><?= e(dashboard_greeting()) ?>, <?= e($name) ?></h1>
        <p class="dash-hero-sub">Bugün takip etmen gereken işler burada.</p>
    </div>
    <div class="dash-hero-meta">
        <span class="dash-meta-chip dash-meta-date">
            <?= icon($todayCtx['icon'], 'icon-sm') ?>
            <span><b><?= e($todayCtx['label']) ?></b><span class="dash-meta-sub"><?= e($todayCtx['sub']) ?></span></span>
        </span>
        <?php if (can('currency')): ?>
        <a class="dash-meta-chip dash-meta-kur" href="<?= e(url('modules/currency/index.php')) ?>" title="Kur Çeviriciyi aç">
            <?= icon('coins', 'icon-sm') ?>
            <span class="dash-kur-vals">
                <span><b>USD</b> <?= e($fmtRate($rates['usd_try'] ?? null)) ?></span>
                <span><b>EUR</b> <?= e($fmtRate($rates['eur_try'] ?? null)) ?></span>
            </span>
        </a>
        <?php endif; ?>
        <a class="dash-meta-chip dash-meta-bell" href="<?= e(url('modules/notifications/index.php')) ?>" title="Bildirim Merkezi">
            <?= icon('bell', 'icon-sm') ?>
            <?php if ($notifUnread > 0): ?><span class="dash-bell-badge"><?= $notifUnread > 99 ? '99+' : (int) $notifUnread ?></span><?php endif; ?>
        </a>
    </div>
</section>

<!-- 2) KRİTİK ÖZET ŞERİDİ -->
<?php if ($kpis): ?>
<section class="kpi-strip" aria-label="Kritik özet">
    <?php foreach ($kpis as $k): ?>
        <a class="kpi-card<?= $k['tone'] !== '' ? ' kpi-' . e($k['tone']) : '' ?>" href="<?= e($k['url']) ?>">
            <span class="kpi-ic icon-circle"><?= icon($k['icon'], 'icon-sm') ?></span>
            <span class="kpi-main">
                <span class="kpi-value"><?= e($k['value']) ?></span>
                <span class="kpi-label"><?= e($k['label']) ?></span>
                <span class="kpi-sub"><?= e($k['sub']) ?></span>
            </span>
        </a>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- 2b) BUGÜNKÜ LEAD TAKİPLERİ (§21) -->
<?php if ($leadFollowData !== null):
    $lfActUrl = e(url('modules/leads/lead-action.php'));
    lead_followup_dashboard_card($leadFollowData, $leadFollowCounts, $lfActUrl, 'dashboard.php');
    if ($leadTeam): ?>
    <section class="panel followup-team-panel">
        <div class="panel-head">
            <h2 class="panel-title"><?= icon('users', 'icon-sm') ?> Ekip Lead Takipleri</h2>
            <a class="btn btn-sm" href="<?= e(url('modules/leads/team-reminders.php')) ?>">Detay</a>
        </div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Personel</th><th class="nowrap">Bugün</th><th class="nowrap">Geciken</th><th class="nowrap">Tamamlanan</th><th class="nowrap">Ulaşılamayan</th><th class="nowrap">Ertelenen</th></tr></thead>
            <tbody>
            <?php foreach ($leadTeam as $t): ?>
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
    </section>
    <?php endif; ?>
<?php endif; ?>

<div class="dash-columns">
    <div class="dash-col-main">
        <!-- 3) AKSİYON GEREKTİRENLER -->
        <section class="panel action-panel">
            <div class="panel-head">
                <h2 class="panel-title"><?= icon('triangle-alert', 'icon-sm') ?> Aksiyon Gerektirenler</h2>
                <?php if ($actions): ?><span class="panel-count"><?= count($actions) ?></span><?php endif; ?>
            </div>
            <?php if ($actions): ?>
            <ul class="dact-list">
                <?php foreach ($actions as $a): ?>
                <li class="dact-row<?= $a['tone'] !== '' ? ' dact-' . e($a['tone']) : '' ?>">
                    <span class="dact-ic"><?= icon($a['icon'], 'icon-sm') ?></span>
                    <span class="dact-body">
                        <span class="dact-top">
                            <span class="dact-cat"><?= e($a['cat']) ?></span>
                            <?php if ($a['date'] !== ''): ?><span class="dact-date"><?= e(dash_when($a['date'])) ?></span><?php endif; ?>
                        </span>
                        <span class="dact-title"><?= e($a['title']) ?></span>
                        <span class="dact-desc"><?= e($a['desc']) ?></span>
                    </span>
                    <a class="btn btn-sm dact-go" href="<?= e($a['url']) ?>">Git</a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <div class="empty-state">
                <?= icon('check-circle', 'icon-lg') ?>
                <p>Kritik bekleyen iş bulunmuyor.</p>
            </div>
            <?php endif; ?>
        </section>

        <!-- 6) SON HAREKETLER (veri yoksa gizli) -->
        <?php if ($recent): ?>
        <section class="panel recent-panel">
            <div class="panel-head">
                <h2 class="panel-title"><?= icon('refresh-cw', 'icon-sm') ?> Son Hareketler</h2>
            </div>
            <ul class="recent-list">
                <?php foreach ($recent as $r): ?>
                <li class="recent-row">
                    <span class="recent-ic"><?= icon($r['icon'], 'icon-sm') ?></span>
                    <span class="recent-type"><?= e($r['type']) ?></span>
                    <a class="recent-desc" href="<?= e($r['url']) ?>"><?= e($r['desc']) ?></a>
                    <span class="recent-when"><?= e(dash_when($r['when'])) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
    </div>

    <div class="dash-col-side">
        <!-- 4) HIZLI BAŞLAT -->
        <?php if ($quick): ?>
        <section class="panel quick-panel">
            <div class="panel-head">
                <h2 class="panel-title"><?= icon('plus', 'icon-sm') ?> Hızlı Başlat</h2>
            </div>
            <div class="quick-grid">
                <?php foreach ($quick as $q): ?>
                <a class="quick-card" href="<?= e($q['url']) ?>">
                    <span class="quick-ic icon-circle"><?= icon($q['icon'], 'icon-sm') ?></span>
                    <span class="quick-text">
                        <span class="quick-title"><?= e($q['title']) ?></span>
                        <span class="quick-desc"><?= e($q['desc']) ?></span>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- 5) YAKLAŞANLAR -->
        <section class="panel upcoming-panel">
            <div class="panel-head">
                <h2 class="panel-title"><?= icon('calendar-days', 'icon-sm') ?> Yaklaşanlar</h2>
            </div>
            <?php if ($upcoming): ?>
            <ul class="upcoming-list">
                <?php foreach ($upcoming as $it): ?>
                <li class="upcoming-row">
                    <span class="upcoming-ic"><?= icon($it['icon'], 'icon-sm') ?></span>
                    <a class="upcoming-body" href="<?= e($it['url']) ?>">
                        <span class="upcoming-title"><?= e($it['title']) ?></span>
                        <span class="upcoming-sub"><?= e($it['sub']) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <div class="empty-state empty-compact">
                <p>Yaklaşan kayıt bulunmuyor.</p>
            </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php
layout_bottom();
