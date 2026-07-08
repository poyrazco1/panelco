<?php
declare(strict_types=1);
/** modules/leads/scan-status.php — Tarama ilerlemesi (JSON, panel içi canlı ekran). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/lead-scan.php';
auth_boot();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
if (!is_logged_in() || !can('leads.view')) { http_response_code(403); echo json_encode(['ok' => false]); exit; }
$id = (int) ($_GET['id'] ?? 0);
$p = lead_scan_progress($id);
if (empty($p['ok'])) { http_response_code(404); echo json_encode(['ok' => false]); exit; }
$recent = [];
foreach (lead_scan_recent_leads($id, 12) as $r) {
    $recent[] = [
        'company' => (string) $r['company_name'], 'phone' => (string) ($r['phone'] ?? ''),
        'loc' => trim((string) ($r['district'] ?? '') . ' ' . (string) ($r['city'] ?? '')),
        'rating' => $r['google_rating'] !== null ? (float) $r['google_rating'] : null,
        'reviews' => $r['review_count'] !== null ? (int) $r['review_count'] : null,
        'website' => !empty($r['website']),
    ];
}
echo json_encode(['ok' => true, 'progress' => $p, 'recent' => $recent], JSON_UNESCAPED_UNICODE);
