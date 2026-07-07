<?php
declare(strict_types=1);

/** modules/integrations/test.php — Bağlantı testi çalıştır. POST + CSRF. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/integrations.php';

auth_boot();
require_permission('integrations.edit');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('modules/integrations/index.php'); }
csrf_check();

$key = (string) ($_POST['key'] ?? '');
if (!integration_def($key)) { flash('error', 'Tanımsız entegrasyon.'); redirect('modules/integrations/index.php'); }

$res = integration_test($key);
$res['ok'] ? flash('success', $res['message']) : flash('error', $res['message']);
redirect('modules/integrations/edit.php?key=' . $key);
