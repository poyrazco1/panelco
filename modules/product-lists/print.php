<?php
declare(strict_types=1);
/** modules/product-lists/print.php — Yazdırılabilir / PDF (tarayıcıdan kaydet) çıktısı. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/product-lists.php';
auth_boot();
require_permission('product_lists.print');

$id = (int) ($_GET['id'] ?? 0);
$pl = pl_get($id);
if (!$pl) { http_response_code(404); echo 'Liste bulunamadı.'; exit; }
$items = pl_items($id);
$company = (string) app_setting_get('company_name', SITE_NAME);
?>
<!doctype html>
<html lang="tr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pl['list_no']) ?> · <?= e($pl['title']) ?></title>
<style>
    body{font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:#1f2937;margin:28px;font-size:13px}
    .head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #1F2A44;padding-bottom:12px;margin-bottom:16px}
    .head h1{font-size:19px;margin:0 0 4px}.muted{color:#6b7280}
    table{width:100%;border-collapse:collapse;margin-top:10px}
    th,td{border:1px solid #d1d5db;padding:7px 9px;text-align:left;font-size:12px}
    th{background:#f3f4f6}
    .intro{margin:8px 0 4px;white-space:pre-line}
    .foot{margin-top:22px;color:#6b7280;font-size:11px;border-top:1px solid #e5e7eb;padding-top:8px}
    .toolbar{margin-bottom:14px}
    @media print{.toolbar{display:none}body{margin:0}}
    .btn{padding:8px 14px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;cursor:pointer;text-decoration:none;color:#1f2937}
</style></head><body>
<div class="toolbar"><button class="btn" onclick="window.print()">Yazdır / PDF olarak kaydet</button></div>
<div class="head">
    <div><h1><?= e($pl['title']) ?></h1><div class="muted"><?= e(pl_type_label((string) $pl['list_type'])) ?> · <?= e($pl['list_no']) ?></div></div>
    <div style="text-align:right"><strong><?= e($company) ?></strong><br><span class="muted"><?= date('d.m.Y') ?></span>
        <?php if (!empty($pl['valid_until'])): ?><br><span class="muted">Geçerlilik: <?= e($pl['valid_until']) ?></span><?php endif; ?></div>
</div>
<?php if (!empty($pl['intro'])): ?><div class="intro"><?= e((string) $pl['intro']) ?></div><?php endif; ?>
<table>
    <thead><tr><th>#</th><th>Ürün</th><th>SKU</th><th>Marka</th><th>Miktar</th><th>Fiyat</th></tr></thead>
    <tbody>
    <?php foreach ($items as $i => $it): ?>
        <tr><td><?= $i + 1 ?></td><td><?= e((string) $it['name']) ?><?= !empty($it['note']) ? '<br><span class="muted">' . e((string) $it['note']) . '</span>' : '' ?></td>
            <td><?= e((string) ($it['sku'] ?? '')) ?></td><td><?= e((string) ($it['brand'] ?? '')) ?></td>
            <td><?= $it['qty'] !== null ? e(rtrim(rtrim((string) $it['qty'], '0'), '.')) . ' ' . e((string) ($it['unit'] ?? '')) : '' ?></td>
            <td><?= $it['price'] !== null ? e(rtrim(rtrim((string) $it['price'], '0'), '.')) . ' ' . e((string) ($it['currency'] ?: $pl['currency'] ?? '')) : '' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php if (!empty($pl['notes'])): ?><div class="intro" style="margin-top:14px"><strong>Notlar:</strong><br><?= e((string) $pl['notes']) ?></div><?php endif; ?>
<div class="foot"><?= e($company) ?> · <?= e($pl['list_no']) ?> · <?= date('d.m.Y H:i') ?></div>
</body></html>
