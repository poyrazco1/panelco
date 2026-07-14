<?php
declare(strict_types=1);

/**
 * includes/lead_reports.php — Lead raporlama toplulaştırmaları (§14).
 * Tüm sorgular yalnızca aktif (silinmemiş) lead'ler üzerindedir.
 */

require_once __DIR__ . '/leads.php';
require_once __DIR__ . '/google_places.php';

/** Duruma göre lead dağılımı (etiketli). */
function lead_report_by_status(): array
{
    $out = [];
    try {
        foreach (db()->query('SELECT status, COUNT(*) c FROM leads WHERE is_deleted = 0 GROUP BY status')->fetchAll() as $r) {
            $code = (string) $r['status'];
            $out[] = ['code' => $code, 'label' => lead_status_label($code), 'class' => lead_status_class($code), 'count' => (int) $r['c']];
        }
    } catch (Throwable $e) { log_error('lead_report_by_status: ' . $e->getMessage()); }
    usort($out, static fn($a, $b) => $b['count'] <=> $a['count']);
    return $out;
}

/** Kaynağa göre dağılım. */
function lead_report_by_source(int $limit = 20): array
{
    try {
        $st = db()->query("SELECT COALESCE(NULLIF(source,''),'(belirtilmemiş)') src, COUNT(*) c FROM leads WHERE is_deleted = 0 GROUP BY src ORDER BY c DESC LIMIT " . max(1, $limit));
        return array_map(static fn($r) => ['source' => (string) $r['src'], 'count' => (int) $r['c']], $st->fetchAll());
    } catch (Throwable $e) { log_error('lead_report_by_source: ' . $e->getMessage()); return []; }
}

/** Sorumlu personele göre dağılım. */
function lead_report_by_personnel(int $limit = 30): array
{
    try {
        $st = db()->query(
            "SELECT COALESCE(p.full_name,'(atanmamış)') name, COUNT(*) c
             FROM leads l LEFT JOIN personnel p ON p.id = l.assigned_personnel_id
             WHERE l.is_deleted = 0 GROUP BY name ORDER BY c DESC LIMIT " . max(1, $limit)
        );
        return array_map(static fn($r) => ['name' => (string) $r['name'], 'count' => (int) $r['c']], $st->fetchAll());
    } catch (Throwable $e) { log_error('lead_report_by_personnel: ' . $e->getMessage()); return []; }
}

/** Şehre göre dağılım. */
function lead_report_by_city(int $limit = 15): array
{
    try {
        $st = db()->query("SELECT COALESCE(NULLIF(city,''),'(bilinmiyor)') city, COUNT(*) c FROM leads WHERE is_deleted = 0 GROUP BY city ORDER BY c DESC LIMIT " . max(1, $limit));
        return array_map(static fn($r) => ['city' => (string) $r['city'], 'count' => (int) $r['c']], $st->fetchAll());
    } catch (Throwable $e) { return []; }
}

/** Genel özet: toplam, başarılı (converted), başarısız, dönüşüm oranı, çöp. */
function lead_report_summary(): array
{
    $total = 0; $success = 0; $failure = 0; $open = 0;
    try {
        $rows = db()->query('SELECT status, COUNT(*) c FROM leads WHERE is_deleted = 0 GROUP BY status')->fetchAll();
        foreach ($rows as $r) {
            $c = (int) $r['c']; $total += $c;
            $meta = lead_status_meta((string) $r['status']);
            if ($meta && (int) ($meta['is_success'] ?? 0) === 1) { $success += $c; }
            elseif ($meta && (int) ($meta['is_failure'] ?? 0) === 1) { $failure += $c; }
            else { $open += $c; }
        }
    } catch (Throwable $e) { log_error('lead_report_summary: ' . $e->getMessage()); }
    return [
        'total' => $total, 'success' => $success, 'failure' => $failure, 'open' => $open,
        'conversion' => $total > 0 ? round($success / $total * 100, 1) : 0.0,
        'trashed' => trashed_lead_count(),
    ];
}

/** Tarama & API kullanım özeti (maliyet dahil). */
function lead_report_scan_stats(): array
{
    $out = ['scans' => 0, 'results' => 0, 'new' => 0, 'dup' => 0, 'api_calls' => 0, 'est_cost' => 0.0,
            'usage_today' => gp_usage_today(), 'usage_month' => gp_usage_month()];
    try {
        $r = db()->query('SELECT COUNT(*) scans, COALESCE(SUM(result_count),0) results, COALESCE(SUM(new_count),0) new_c, COALESCE(SUM(dup_count),0) dup_c, COALESCE(SUM(api_calls),0) calls, COALESCE(SUM(est_cost),0) cost FROM google_places_searches')->fetch();
        if ($r) {
            $out['scans'] = (int) $r['scans']; $out['results'] = (int) $r['results'];
            $out['new'] = (int) $r['new_c']; $out['dup'] = (int) $r['dup_c'];
            $out['api_calls'] = (int) $r['calls']; $out['est_cost'] = (float) $r['cost'];
        }
        // Bu ayki tahmini maliyet
        $mc = db()->query('SELECT COALESCE(SUM(est_cost),0) FROM google_places_api_usage WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())')->fetchColumn();
        $out['cost_month'] = (float) $mc;
    } catch (Throwable $e) { log_error('lead_report_scan_stats: ' . $e->getMessage()); }
    return $out;
}

/** Son N günün günlük yeni lead sayısı (basit trend). */
function lead_report_daily_new(int $days = 14): array
{
    try {
        $st = db()->prepare('SELECT DATE(created_at) d, COUNT(*) c FROM leads WHERE is_deleted = 0 AND created_at >= (CURDATE() - INTERVAL :d DAY) GROUP BY DATE(created_at) ORDER BY d ASC');
        $st->bindValue(':d', max(1, min(90, $days)), PDO::PARAM_INT);
        $st->execute();
        return array_map(static fn($r) => ['date' => (string) $r['d'], 'count' => (int) $r['c']], $st->fetchAll());
    } catch (Throwable $e) { return []; }
}
