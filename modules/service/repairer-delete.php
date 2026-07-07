<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service.php';
auth_boot();
require_permission('service');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(303); redirect('modules/service/repairers.php'); }
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
try { if ($id > 0) { delete_repairer($id); flash('success', 'Tamirci silindi.'); } }
catch (Throwable $e) { flash('error', $e->getMessage()); }
http_response_code(303);
redirect('modules/service/repairers.php');
