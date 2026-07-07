<?php
declare(strict_types=1);

/** modules/shipments/print.php — Sevkiyat irsaliye/fiş yazdırma. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';
require_once __DIR__ . '/../../includes/forms.php';

auth_boot();
require_permission('shipments.view');

$id = (int) ($_GET['id'] ?? 0);
$s  = get_shipment($id);
if (!$s) { flash('error', 'Sevkiyat bulunamadı.'); redirect('modules/shipments/index.php'); }
$fav = function_exists('pub_favicon_url') ? pub_favicon_url() : null;
?>
<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow"><title>Sevkiyat <?= e((string) $s['shipment_no']) ?></title>
<?php if ($fav): ?><link rel="icon" href="<?= e($fav) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"></head><body>
<?php document_actions(); ?>
<div class="doc-sheet">
    <?php document_header(['title' => 'SEVKİYAT FİŞİ', 'number' => (string) $s['shipment_no'], 'date' => (string) ($s['shipment_date'] ?? ''), 'extra' => ['Tip' => shipment_type_label((string) $s['shipment_type'])]]); ?>
    <div class="doc-party"><strong>Müşteri:</strong> <?= e((string) ($s['customer_name'] ?? ($s['addr_company'] ?? ''))) ?>
        <br>Adres: <?= e(trim((string) ($s['addr_text'] ?? '') . ' ' . (string) ($s['addr_district'] ?? '') . ' ' . (string) ($s['addr_city'] ?? ''))) ?>
        <?php if (!empty($s['courier_name'])): ?><br>Sevkiyatçı: <?= e((string) $s['courier_name']) ?><?php endif; ?>
    </div>
    <?php if (!empty($s['items'])): ?>
    <table class="table" style="margin-top:14px"><thead><tr><th>Ürün</th><th>Kod</th><th class="nowrap">Adet</th></tr></thead><tbody>
        <?php foreach ($s['items'] as $it): ?><tr><td><?= e((string) $it['name']) ?></td><td><?= e((string) ($it['product_code'] ?? '')) ?></td><td class="nowrap"><?= e(rtrim(rtrim((string) $it['qty'], '0'), '.')) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
    <p style="margin-top:14px">Durum: <strong><?= e(shipment_status_label((string) $s['status'])) ?></strong></p>
    <?php document_signatures('Teslim Eden', 'Teslim Alan'); ?>
    <?php document_footer(); ?>
</div></body></html>
