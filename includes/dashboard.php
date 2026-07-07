<?php
declare(strict_types=1);

/**
 * includes/dashboard.php
 * Kullanıcıya özel, widget tabanlı Genel Bakış altyapısı.
 * Düzen user_preferences.dashboard_layout içinde JSON olarak saklanır.
 * Tüm veri fonksiyonları hataya dayanıklıdır (fatal yerine 0 / boş döner, log yazar).
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/preferences.php';
require_once __DIR__ . '/service.php';
require_once __DIR__ . '/rma.php';
require_once __DIR__ . '/../kur.php';
require_once __DIR__ . '/notifications.php';

/* =========================================================================
 |  WIDGET KAYIT (whitelist) — yalnızca burada tanımlı ID'ler kabul edilir
 * ====================================================================== */
function dashboard_widget_registry(): array
{
    return [
        // Servis
        'today_service_records'     => ['title' => 'Bugünkü Servis Kayıtları',      'icon' => 'clipboard-plus', 'type' => 'stat', 'group' => 'service'],
        'open_service_records'      => ['title' => 'Açık Servis Kayıtları',         'icon' => 'clipboard-list', 'type' => 'stat', 'group' => 'service'],
        'pending_customer_approval' => ['title' => 'Müşteri Onayı Bekleyenler',      'icon' => 'clock',          'type' => 'stat', 'group' => 'service'],
        'pending_payment_service'   => ['title' => 'Ödeme Bekleyen Servisler',       'icon' => 'credit-card',    'type' => 'stat', 'group' => 'service'],
        'ready_for_delivery'        => ['title' => 'Teslimata Hazır Cihazlar',       'icon' => 'package-check',  'type' => 'stat', 'group' => 'service'],
        'latest_service_records'    => ['title' => 'Son Servis Kayıtları',           'icon' => 'clipboard-list', 'type' => 'list', 'group' => 'service'],
        // İade-Değişim
        'open_rma'                  => ['title' => 'Açık İade-Değişim Süreçleri',    'icon' => 'rotate-ccw',     'type' => 'stat', 'group' => 'rma'],
        'today_rma_records'         => ['title' => 'Bugünkü İade/Değişim Kayıtları', 'icon' => 'rotate-ccw',     'type' => 'stat', 'group' => 'rma'],
        'total_rma_loss'            => ['title' => 'Toplam Zarar',                   'icon' => 'triangle-alert', 'type' => 'stat', 'group' => 'rma'],
        'latest_rma_records'        => ['title' => 'Son İade-Değişim Kayıtları',     'icon' => 'rotate-ccw',     'type' => 'list', 'group' => 'rma'],
        // Kur
        'exchange_rates'            => ['title' => 'Kur Bilgisi',                    'icon' => 'coins',          'type' => 'kur',       'group' => 'currency'],
        'currency_converter'        => ['title' => 'Hızlı Kur Çevirici',            'icon' => 'refresh-cw',     'type' => 'converter', 'group' => 'currency'],
        // İK / Bildirimler
        'hr_today_leaves'           => ['title' => 'Bugünkü İzinler',                'icon' => 'calendar-check', 'type' => 'hrlist', 'group' => 'hr'],
        'hr_upcoming_leaves'        => ['title' => 'Yaklaşan İzinler',               'icon' => 'calendar-days',  'type' => 'hrlist', 'group' => 'hr'],
        'hr_today_birthdays'        => ['title' => 'Bugünkü Doğum Günleri',          'icon' => 'gift',           'type' => 'hrlist', 'group' => 'hr'],
        'hr_upcoming_birthdays'     => ['title' => 'Yaklaşan Doğum Günleri',         'icon' => 'gift',           'type' => 'hrlist', 'group' => 'hr'],
        'hr_work_anniversaries'     => ['title' => 'Çalışma Yıl Dönümleri',          'icon' => 'user-check',     'type' => 'hrlist', 'group' => 'hr'],
        // Satış (Faz B/C)
        'new_customers'             => ['title' => 'Yeni Müşteriler (7 gün)',        'icon' => 'users',          'type' => 'stat', 'group' => 'sales'],
        'pending_quotes'            => ['title' => 'Bekleyen Teklifler',             'icon' => 'file-text',      'type' => 'stat', 'group' => 'sales'],
        'open_orders'               => ['title' => 'Açık Siparişler',                'icon' => 'clipboard-list', 'type' => 'stat', 'group' => 'sales'],
    ];
}

/** Geçerli widget boyutları. */
function dashboard_widget_sizes(): array
{
    return ['small', 'medium', 'wide', 'full'];
}

/** Kullanılabilir widget'lar (kullanıcıya göre; şimdilik tümü dashboard yetkisi olana). */
function get_available_dashboard_widgets(int $userId): array
{
    return dashboard_widget_registry();
}

/** Varsayılan dashboard düzeni (yeni kullanıcı / sıfırlama). */
function dashboard_default_layout(): array
{
    return [
        ['id' => 'today_service_records',     'visible' => true, 'size' => 'small',  'order' => 1],
        ['id' => 'open_service_records',      'visible' => true, 'size' => 'small',  'order' => 2],
        ['id' => 'pending_customer_approval', 'visible' => true, 'size' => 'small',  'order' => 3],
        ['id' => 'pending_payment_service',   'visible' => true, 'size' => 'small',  'order' => 4],
        ['id' => 'open_rma',                  'visible' => true, 'size' => 'small',  'order' => 5],
        ['id' => 'new_customers',             'visible' => true, 'size' => 'small',  'order' => 6],
        ['id' => 'pending_quotes',            'visible' => true, 'size' => 'small',  'order' => 7],
        ['id' => 'open_orders',               'visible' => true, 'size' => 'small',  'order' => 8],
        ['id' => 'exchange_rates',            'visible' => true, 'size' => 'medium', 'order' => 9],
        ['id' => 'latest_service_records',    'visible' => true, 'size' => 'wide',   'order' => 10],
        ['id' => 'latest_rma_records',        'visible' => true, 'size' => 'wide',   'order' => 11],
    ];
}

/**
 * Gelen düzeni whitelist + tip doğrulamasıyla normalize eder.
 * - Yalnızca tanımlı widget ID'leri kalır (rastgele ID reddedilir).
 * - size whitelist, visible boolean, order integer.
 * - Kayıtlı düzende olmayan yeni widget'lar sona eklenir (varsayılan görünürlükle);
 *   böylece yeni widget eklenince eski kullanıcı düzeni bozulmaz.
 */
function normalize_dashboard_layout(array $layout, array $available): array
{
    $sizes = dashboard_widget_sizes();
    $items = $layout['widgets'] ?? (isset($layout[0]) ? $layout : []);
    if (!is_array($items)) { $items = []; }

    $defaults = [];
    foreach (dashboard_default_layout() as $d) { $defaults[$d['id']] = $d; }

    $seen = [];
    $out  = [];
    foreach ($items as $w) {
        if (!is_array($w)) { continue; }
        $id = (string) ($w['id'] ?? '');
        if ($id === '' || !isset($available[$id]) || isset($seen[$id])) { continue; }
        $seen[$id] = true;
        $size = (string) ($w['size'] ?? 'small');
        if (!in_array($size, $sizes, true)) { $size = 'small'; }
        $out[] = [
            'id'      => $id,
            'visible' => (bool) ($w['visible'] ?? false),
            'size'    => $size,
            'order'   => (int) ($w['order'] ?? (count($out) + 1)),
        ];
    }

    // Eksik (yeni) widget'ları ekle
    $maxOrder = 0;
    foreach ($out as $o) { $maxOrder = max($maxOrder, $o['order']); }
    foreach ($available as $id => $meta) {
        if (isset($seen[$id])) { continue; }
        $def = $defaults[$id] ?? null;
        $maxOrder++;
        $out[] = [
            'id'      => $id,
            'visible' => $def ? (bool) $def['visible'] : false,
            'size'    => $def ? $def['size'] : 'small',
            'order'   => $def ? (int) $def['order'] : $maxOrder,
        ];
    }

    usort($out, static fn($a, $b) => $a['order'] <=> $b['order']);
    $i = 1;
    foreach ($out as &$o) { $o['order'] = $i++; }
    unset($o);

    return $out;
}

/** Kullanıcının dashboard düzenini döndürür (bozuk/yoksa varsayılan). */
function get_dashboard_layout(int $userId): array
{
    $available = get_available_dashboard_widgets($userId);
    $raw = get_user_preference($userId, 'dashboard_layout', null);

    $layout = null;
    if (is_string($raw) && $raw !== '') {
        $dec = json_decode($raw, true);
        if (is_array($dec)) { $layout = $dec; }
    }
    if ($layout === null) {
        $layout = ['widgets' => dashboard_default_layout()];
    }
    return normalize_dashboard_layout($layout, $available);
}

/** Düzeni kaydeder (normalize + JSON). */
function save_dashboard_layout(int $userId, array $layout): bool
{
    if ($userId <= 0) { return false; }
    $available = get_available_dashboard_widgets($userId);
    $norm = normalize_dashboard_layout($layout, $available);
    $json = json_encode(['widgets' => $norm], JSON_UNESCAPED_UNICODE);
    if ($json === false) { return false; }
    return set_user_preference($userId, 'dashboard_layout', $json);
}

/** Düzeni varsayılana döndürür (tercihi siler → varsayılan uygulanır). */
function reset_dashboard_layout(int $userId): bool
{
    if ($userId <= 0) { return false; }
    return delete_user_preference($userId, 'dashboard_layout');
}

/* =========================================================================
 |  WIDGET VERİ FONKSİYONLARI (hepsi hataya dayanıklı)
 * ====================================================================== */
function get_today_service_count(): int
{
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM service_records WHERE is_deleted = 0 AND DATE(created_at) = CURDATE()');
        $st->execute();
        return (int) $st->fetchColumn();
    } catch (Throwable $e) { log_error('widget today_service: ' . $e->getMessage()); return 0; }
}

function get_open_service_count(): int
{
    try {
        return (int) db()->query("SELECT COUNT(*) FROM service_records WHERE is_deleted = 0 AND status NOT IN ('delivered','cancelled','closed')")->fetchColumn();
    } catch (Throwable $e) { log_error('widget open_service: ' . $e->getMessage()); return 0; }
}

function get_pending_customer_approval_count(): int
{
    try {
        return (int) db()->query("SELECT COUNT(*) FROM service_records WHERE is_deleted = 0 AND status = 'awaiting_approval'")->fetchColumn();
    } catch (Throwable $e) { log_error('widget pending_approval: ' . $e->getMessage()); return 0; }
}

function get_pending_payment_service_count(): int
{
    try {
        return (int) db()->query("SELECT COUNT(*) FROM service_records WHERE is_deleted = 0 AND status = 'awaiting_payment'")->fetchColumn();
    } catch (Throwable $e) { log_error('widget pending_payment: ' . $e->getMessage()); return 0; }
}

function get_ready_for_delivery_service_count(): int
{
    try {
        return (int) db()->query("SELECT COUNT(*) FROM service_records WHERE is_deleted = 0 AND status = 'ready'")->fetchColumn();
    } catch (Throwable $e) { log_error('widget ready_delivery: ' . $e->getMessage()); return 0; }
}

function get_open_rma_count(): int
{
    try {
        $closed = rma_closed_statuses();
        $in = implode(',', array_fill(0, count($closed), '?'));
        $st = db()->prepare("SELECT COUNT(*) FROM rma_records WHERE is_deleted = 0 AND status NOT IN ($in)");
        $st->execute($closed);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) { log_error('widget open_rma: ' . $e->getMessage()); return 0; }
}

function get_today_rma_count(): int
{
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM rma_records WHERE is_deleted = 0 AND DATE(created_at) = CURDATE()');
        $st->execute();
        return (int) $st->fetchColumn();
    } catch (Throwable $e) { log_error('widget today_rma: ' . $e->getMessage()); return 0; }
}

function get_total_rma_loss(): float
{
    try {
        return (float) db()->query('SELECT COALESCE(SUM(loss_amount),0) FROM rma_records WHERE is_deleted = 0')->fetchColumn();
    } catch (Throwable $e) { log_error('widget total_loss: ' . $e->getMessage()); return 0.0; }
}

function get_latest_service_records(int $limit = 5): array
{
    $limit = max(1, min(20, $limit));
    try {
        $st = db()->prepare('SELECT reference_code, customer_name, status, created_at FROM service_records WHERE is_deleted = 0 ORDER BY id DESC LIMIT ' . $limit);
        $st->execute();
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('widget latest_service: ' . $e->getMessage()); return []; }
}

function get_latest_rma_records(int $limit = 5): array
{
    $limit = max(1, min(20, $limit));
    try {
        $st = db()->prepare('SELECT reference_code, customer_name, status, process_type, created_at FROM rma_records WHERE is_deleted = 0 ORDER BY id DESC LIMIT ' . $limit);
        $st->execute();
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('widget latest_rma: ' . $e->getMessage()); return []; }
}

function get_new_customers_count(): int
{
    try { return (int) db()->query("SELECT COUNT(*) FROM customers WHERE is_deleted = 0 AND created_at >= (CURDATE() - INTERVAL 7 DAY)")->fetchColumn(); }
    catch (Throwable $e) { log_error('widget new_customers: ' . $e->getMessage()); return 0; }
}
function get_pending_quotes_count(): int
{
    try { return (int) db()->query("SELECT COUNT(*) FROM quotes WHERE is_deleted = 0 AND status IN ('draft','sent')")->fetchColumn(); }
    catch (Throwable $e) { log_error('widget pending_quotes: ' . $e->getMessage()); return 0; }
}
function get_open_orders_count(): int
{
    try { return (int) db()->query("SELECT COUNT(*) FROM orders WHERE is_deleted = 0 AND status NOT IN ('delivered','cancelled')")->fetchColumn(); }
    catch (Throwable $e) { log_error('widget open_orders: ' . $e->getMessage()); return 0; }
}

function get_dashboard_exchange_rates(): array
{
    try {
        $r = kur_get_rates();
        return [
            'usd_try' => kur_convert($r, 1.0, 'USD', 'TRY'),
            'eur_try' => kur_convert($r, 1.0, 'EUR', 'TRY'),
            'eur_usd' => kur_convert($r, 1.0, 'EUR', 'USD'),
            'source'  => kur_source_label($r['source'] ?? ''),
            'date'    => (string) ($r['date'] ?? ''),
            'rates'   => $r['rates'] ?? [],
        ];
    } catch (Throwable $e) {
        log_error('widget rates: ' . $e->getMessage());
        return ['usd_try' => null, 'eur_try' => null, 'eur_usd' => null, 'source' => '', 'date' => '', 'rates' => []];
    }
}

/** Bir widget'ın render için ihtiyaç duyduğu veriyi döndürür. */
function get_dashboard_widget_data(string $widgetId, int $userId): array
{
    switch ($widgetId) {
        case 'today_service_records':     return ['value' => get_today_service_count(),            'sub' => 'Bugün açılan servis kaydı'];
        case 'open_service_records':      return ['value' => get_open_service_count(),             'sub' => 'İşlem bekleyen servis kayıtları'];
        case 'pending_customer_approval': return ['value' => get_pending_customer_approval_count(),'sub' => 'Müşteri onayı bekleyen'];
        case 'pending_payment_service':   return ['value' => get_pending_payment_service_count(),  'sub' => 'Ödeme bekleyen servis'];
        case 'ready_for_delivery':        return ['value' => get_ready_for_delivery_service_count(),'sub' => 'Teslimata hazır cihaz'];
        case 'open_rma':                  return ['value' => get_open_rma_count(),                 'sub' => 'Devam eden iade/değişim'];
        case 'today_rma_records':         return ['value' => get_today_rma_count(),                'sub' => 'Bugün açılan iade/değişim'];
        case 'total_rma_loss':            return ['value' => fmt_money(get_total_rma_loss(), 2) . ' ₺', 'sub' => 'Toplam zarar tutarı'];
        case 'new_customers':             return ['value' => get_new_customers_count(),            'sub' => 'Son 7 günde eklenen müşteri'];
        case 'pending_quotes':            return ['value' => get_pending_quotes_count(),           'sub' => 'Taslak / gönderilmiş teklif'];
        case 'open_orders':               return ['value' => get_open_orders_count(),              'sub' => 'Devam eden sipariş'];
        case 'latest_service_records':    return ['list'  => get_latest_service_records(6)];
        case 'latest_rma_records':        return ['list'  => get_latest_rma_records(6)];
        case 'exchange_rates':
        case 'currency_converter':        return get_dashboard_exchange_rates();
        case 'hr_today_leaves':           return ['list' => dashboard_hr_today_leaves()];
        case 'hr_upcoming_leaves':        return ['list' => dashboard_hr_upcoming_leaves()];
        case 'hr_today_birthdays':        return ['list' => dashboard_hr_today_birthdays()];
        case 'hr_upcoming_birthdays':     return ['list' => dashboard_hr_upcoming_birthdays()];
        case 'hr_work_anniversaries':     return ['list' => dashboard_hr_anniversaries()];
        default:                          return [];
    }
}

/* ---- İK bildirim widget verileri (notifications.php'den) ---- */
function dashboard_hr_today_leaves(): array
{
    $out = [];
    foreach (get_today_leave_notifications() as $l) {
        $out[] = ['name' => (string) $l['personnel_name'], 'info' => ((string) ($l['type_name'] ?? 'İzin')), 'pid' => (int) $l['personnel_id'], 'ctype' => null];
    }
    return $out;
}
function dashboard_hr_upcoming_leaves(): array
{
    $s = notif_settings();
    $out = [];
    foreach (get_upcoming_leave_notifications($s['leave_days_before']) as $l) {
        $out[] = ['name' => (string) $l['personnel_name'], 'info' => fmt_date((string) $l['start_date']), 'pid' => (int) $l['personnel_id'], 'ctype' => null];
    }
    return $out;
}
function dashboard_hr_today_birthdays(): array
{
    $out = [];
    foreach (get_today_birthdays() as $p) {
        $out[] = ['name' => (string) $p['full_name'], 'info' => 'Bugün 🎉', 'pid' => (int) $p['id'], 'ctype' => 'birthday'];
    }
    return $out;
}
function dashboard_hr_upcoming_birthdays(): array
{
    $s = notif_settings();
    $out = [];
    foreach (get_upcoming_birthdays($s['birthday_days_before']) as $p) {
        $out[] = ['name' => (string) $p['full_name'], 'info' => (int) $p['days'] . ' gün', 'pid' => (int) $p['id'], 'ctype' => 'birthday'];
    }
    return $out;
}
function dashboard_hr_anniversaries(): array
{
    $s = notif_settings();
    $out = [];
    foreach (get_today_work_anniversaries() as $p) {
        $out[] = ['name' => (string) $p['full_name'], 'info' => (int) $p['years'] . '. yıl · bugün', 'pid' => (int) $p['id'], 'ctype' => 'anniversary'];
    }
    foreach (get_upcoming_work_anniversaries($s['anniversary_days_before']) as $p) {
        $out[] = ['name' => (string) $p['full_name'], 'info' => (int) $p['years'] . '. yıl · ' . (int) $p['days'] . ' gün', 'pid' => (int) $p['id'], 'ctype' => 'anniversary'];
    }
    return $out;
}
