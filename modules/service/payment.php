<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service.php';
auth_boot();
require_permission('service');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(303); redirect('modules/service/index.php'); }
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
$amountRaw = trim((string) ($_POST['paid_amount'] ?? ''));
$amount = $amountRaw !== '' ? (float) str_replace(',', '.', $amountRaw) : null;
try {
    service_set_payment(
        $id,
        (string) ($_POST['payment_status'] ?? 'pending'),
        (string) ($_POST['payment_method'] ?? '') ?: null,
        $amount,
        null
    );
    flash('success', 'Ödeme bilgisi kaydedildi.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}
http_response_code(303);
redirect('modules/service/view.php?id=' . $id);
