<?php
declare(strict_types=1);

/**
 * includes/service_maintenance_reports.php
 * Bakım hatırlatma raporları / metrikleri (§15).
 * Tüm sorgular hataya dayanıklıdır (fatal yerine 0/[] döner).
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/service_maintenance.php';

/** Filtrelerden ortak WHERE (r alias). */
function smaint_report_where(array $f, array &$p): string
{
    $w = ['r.deleted_at IS NULL'];
    if (trim((string) ($f['date_from'] ?? '')) !== '') { $w[] = 'r.maintenance_due_date >= :df'; $p[':df'] = substr((string) $f['date_from'], 0, 10); }
    if (trim((string) ($f['date_to'] ?? '')) !== '')   { $w[] = 'r.maintenance_due_date <= :dt'; $p[':dt'] = substr((string) $f['date_to'], 0, 10); }
    if ((int) ($f['assigned'] ?? 0) > 0)                { $w[] = 'r.assigned_user_id = :au'; $p[':au'] = (int) $f['assigned']; }
    if (trim((string) ($f['brand'] ?? '')) !== '')      { $w[] = 'r.brand_name = :brand'; $p[':brand'] = trim((string) $f['brand']); }
    if (isset(smaint_statuses()[(string) ($f['status'] ?? '')])) { $w[] = 'r.status = :st'; $p[':st'] = (string) $f['status']; }
    return implode(' AND ', $w);
}

/** Özet metrikler (§15). */
function smaint_report_summary(array $f): array
{
    smaint_ensure_schema();
    $p = [];
    $base = smaint_report_where($f, $p);

    $cnt = static function (string $extra) use ($base, $p): int {
        try {
            $st = db()->prepare('SELECT COUNT(*) FROM service_maintenance_reminders r WHERE ' . $base . ' ' . $extra);
            $st->execute($p);
            return (int) $st->fetchColumn();
        } catch (Throwable $e) { log_error('smaint_report_summary cnt: ' . $e->getMessage()); return 0; }
    };
    $cntComm = static function (string $channel) use ($base, $p): int {
        try {
            $st = db()->prepare(
                'SELECT COUNT(DISTINCT r.id) FROM service_maintenance_reminders r
                 JOIN service_maintenance_communications c ON c.reminder_id = r.id
                 WHERE ' . $base . " AND c.channel = :ch AND c.status = 'sent'"
            );
            $st->execute($p + [':ch' => $channel]);
            return (int) $st->fetchColumn();
        } catch (Throwable $e) { log_error('smaint_report_summary comm: ' . $e->getMessage()); return 0; }
    };
    $cntHist = static function (string $newStatus) use ($base, $p): int {
        try {
            $st = db()->prepare(
                'SELECT COUNT(DISTINCT r.id) FROM service_maintenance_reminders r
                 JOIN service_maintenance_status_history h ON h.reminder_id = r.id
                 WHERE ' . $base . ' AND h.new_status = :ns'
            );
            $st->execute($p + [':ns' => $newStatus]);
            return (int) $st->fetchColumn();
        } catch (Throwable $e) { log_error('smaint_report_summary hist: ' . $e->getMessage()); return 0; }
    };

    $total     = $cnt('');
    $converted = $cnt('AND r.converted_service_id IS NOT NULL');
    return [
        'total'        => $total,
        'this_month'   => $cnt("AND r.maintenance_due_date BETWEEN DATE_FORMAT(CURDATE(),'%Y-%m-01') AND LAST_DAY(CURDATE())"),
        'overdue'      => $cnt("AND r.maintenance_due_date < CURDATE() AND r.status NOT IN ('completed','cancelled','service_opened','not_interested')"),
        'called'       => $cntHist('called'),
        'wa_sent'      => $cntComm('whatsapp'),
        'email_sent'   => $cntComm('email'),
        'replies'      => $cnt('AND r.response_at IS NOT NULL'),
        'appointments' => $cnt('AND r.appointment_at IS NOT NULL'),
        'converted'    => $converted,
        'not_interested' => $cnt("AND r.status = 'not_interested'"),
        'unreachable'  => $cntHist('unreachable'),
        'conversion_rate' => $total > 0 ? round($converted / $total * 100, 1) : 0.0,
    ];
}

/** Personel bazlı dönüşüm. */
function smaint_report_by_personnel(array $f): array
{
    smaint_ensure_schema();
    $p = [];
    $base = smaint_report_where($f, $p);
    try {
        $st = db()->prepare(
            'SELECT r.assigned_user_id, u.full_name,
                    COUNT(*) AS total,
                    SUM(CASE WHEN r.converted_service_id IS NOT NULL THEN 1 ELSE 0 END) AS converted
             FROM service_maintenance_reminders r
             LEFT JOIN users u ON u.id = r.assigned_user_id
             WHERE ' . $base . '
             GROUP BY r.assigned_user_id, u.full_name
             ORDER BY total DESC LIMIT 100'
        );
        $st->execute($p);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['rate'] = (int) $r['total'] > 0 ? round((int) $r['converted'] / (int) $r['total'] * 100, 1) : 0.0;
        }
        return $rows;
    } catch (Throwable $e) { log_error('smaint_report_by_personnel: ' . $e->getMessage()); return []; }
}

/** Marka/Model bazlı bakım dönüşümü. */
function smaint_report_by_field(array $f, string $field): array
{
    smaint_ensure_schema();
    if (!in_array($field, ['brand_name', 'device_model'], true)) { return []; }
    $p = [];
    $base = smaint_report_where($f, $p);
    try {
        $st = db()->prepare(
            "SELECT COALESCE(NULLIF(r.$field, ''), '(belirtilmemiş)') AS label,
                    COUNT(*) AS total,
                    SUM(CASE WHEN r.converted_service_id IS NOT NULL THEN 1 ELSE 0 END) AS converted
             FROM service_maintenance_reminders r
             WHERE " . $base . "
             GROUP BY label ORDER BY total DESC LIMIT 50"
        );
        $st->execute($p);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['rate'] = (int) $r['total'] > 0 ? round((int) $r['converted'] / (int) $r['total'] * 100, 1) : 0.0;
        }
        return $rows;
    } catch (Throwable $e) { log_error('smaint_report_by_field: ' . $e->getMessage()); return []; }
}

/** Aylık bakım servis geliri (bakımdan açılan servislerin cirosu). */
function smaint_report_monthly_revenue(array $f): array
{
    smaint_ensure_schema();
    $p = [];
    $base = smaint_report_where($f, $p);
    try {
        $st = db()->prepare(
            "SELECT DATE_FORMAT(sr.created_at, '%Y-%m') AS ym,
                    COUNT(*) AS services,
                    COALESCE(SUM(sr.customer_price), 0) AS revenue
             FROM service_maintenance_reminders r
             JOIN service_records sr ON sr.id = r.converted_service_id
             WHERE " . $base . " AND r.converted_service_id IS NOT NULL
             GROUP BY ym ORDER BY ym DESC LIMIT 24"
        );
        $st->execute($p);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('smaint_report_monthly_revenue: ' . $e->getMessage()); return []; }
}
