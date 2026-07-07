<?php
declare(strict_types=1);
/** modules/help/search.php — Yardım araması (AJAX JSON, canlı öneri). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/help.php';
auth_boot();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
if (!is_logged_in() || !can('help.view')) { http_response_code(403); echo json_encode(['ok' => false, 'error' => 'Yetkisiz']); exit; }
$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) { echo json_encode(['ok' => true, 'items' => []]); exit; }
$manage = can('help.edit') || can('help.create');
$rows = get_help_articles(['search' => $q], !$manage);
$items = [];
foreach (array_slice($rows, 0, 10) as $r) {
    $items[] = ['id' => (int) $r['id'], 'title' => (string) $r['title'], 'category' => help_category_label((string) $r['category'])];
}
echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
