<?php
declare(strict_types=1);
/** modules/product-lists/view.php — Ürün listesi görünümü + çıktı/gönderim aksiyonları. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/product-lists.php';
auth_boot();
require_permission('product_lists.view');

$id = (int) ($_GET['id'] ?? 0);
$pl = pl_get($id);
if (!$pl) { flash('error', 'Liste bulunamadı.'); redirect('modules/product-lists/index.php'); }
$items = pl_items($id);
$recipients = pl_recipients($id);
$text = pl_to_text($pl, $items);

layout_top($pl['title'], 'product_lists');
?>
<div class="page-head">
    <h1 class="page-title"><?= e($pl['title']) ?> <span class="badge <?= $pl['list_type'] === 'sale' ? 'badge-success' : 'badge-info' ?>"><?= e(pl_type_label((string) $pl['list_type'])) ?></span></h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/product-lists/index.php')) ?>">← Liste</a>
        <?php if (can('product_lists.print') || can('product_lists.pdf')): ?><a class="btn btn-sm" href="<?= e(url('modules/product-lists/print.php?id=' . $id)) ?>" target="_blank"><?= icon('file-text') ?>Yazdır / PDF</a><?php endif; ?>
        <?php if (can('product_lists.export')): ?><a class="btn btn-sm" href="<?= e(url('modules/product-lists/export.php?id=' . $id)) ?>"><?= icon('download') ?>CSV</a><?php endif; ?>
        <?php if (can('product_lists.edit')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/product-lists/edit.php?id=' . $id)) ?>"><?= icon('pencil') ?>Düzenle</a><?php endif; ?>
    </div>
</div>
<div class="muted small" style="margin:-6px 0 12px"><?= e($pl['list_no']) ?><?= !empty($pl['valid_until']) ? ' · Geçerlilik: ' . e($pl['valid_until']) : '' ?><?= !empty($pl['currency']) ? ' · ' . e($pl['currency']) : '' ?></div>
<?= render_flashes() ?>

<?php if (!empty($pl['intro'])): ?><div class="card"><div class="card-body"><?= nl2br(e((string) $pl['intro'])) ?></div></div><?php endif; ?>

<div class="card"><div class="card-header"><strong>Kalemler</strong> <span class="muted small">(<?= count($items) ?>)</span></div><div class="card-body">
    <?php if ($items): ?>
    <div class="table-wrap"><table class="table"><thead><tr><th>#</th><th>Ürün</th><th>SKU</th><th>Marka</th><th>Miktar</th><th>Fiyat</th></tr></thead><tbody>
    <?php foreach ($items as $i => $it): ?>
        <tr><td><?= $i + 1 ?></td><td><strong><?= e((string) $it['name']) ?></strong><?= !empty($it['note']) ? '<div class="small muted">' . e((string) $it['note']) . '</div>' : '' ?></td>
            <td class="small"><?= e((string) ($it['sku'] ?? '')) ?: '—' ?></td><td><?= e((string) ($it['brand'] ?? '')) ?: '—' ?></td>
            <td><?= $it['qty'] !== null ? e(rtrim(rtrim((string) $it['qty'], '0'), '.')) . ' ' . e((string) ($it['unit'] ?? '')) : '—' ?></td>
            <td><?= $it['price'] !== null ? e(rtrim(rtrim((string) $it['price'], '0'), '.')) . ' ' . e((string) ($it['currency'] ?: $pl['currency'] ?? '')) : '—' ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php else: ?><div class="empty empty-compact">Kalem yok.</div><?php endif; ?>
</div></div>

<div class="form-grid-2">
<div class="card"><div class="card-header"><strong>WhatsApp / Mail Metni</strong></div><div class="card-body">
    <textarea id="plText" rows="10" readonly style="width:100%;font-family:monospace;font-size:12px"><?= e($text) ?></textarea>
    <div class="form-actions">
        <button type="button" class="btn btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('plText').value);this.textContent='Kopyalandı ✓'"><?= icon('file-text') ?>Metni Kopyala</button>
        <?php if (can('international_customers.message_mail') || can('international_customers.message_whatsapp')): ?>
            <a class="btn btn-sm btn-primary" href="<?= e(url('modules/international-customers/message.php?product_list_id=' . $id)) ?>"><?= icon('send') ?>Müşterilere Gönder</a>
        <?php endif; ?>
    </div>
</div></div>

<div class="card"><div class="card-header"><strong>Gönderim Geçmişi</strong></div><div class="card-body">
    <?php if ($recipients): ?>
    <div class="table-wrap"><table class="table"><thead><tr><th>Tarih</th><th>Müşteri</th><th>Kanal</th><th>Durum</th></tr></thead><tbody>
    <?php foreach ($recipients as $r): ?>
        <tr><td class="small nowrap"><?= e((string) $r['created_at']) ?></td>
            <td><?= e((string) ($r['company_name'] ?? '—')) ?></td>
            <td><?= $r['channel'] === 'whatsapp' ? 'WhatsApp' : 'Mail' ?></td>
            <td><span class="badge badge-muted"><?= e((string) $r['status']) ?></span></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php else: ?><div class="empty empty-compact">Henüz gönderilmedi.</div><?php endif; ?>
</div></div>
</div>
<?php layout_bottom();
