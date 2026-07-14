<?php
declare(strict_types=1);

/** modules/finance/view.php — Tahsilat/Ödeme makbuzu detayı + çıktı/e-posta. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/finance.php';

auth_boot();
require_permission('finance');

$id = (int) ($_GET['id'] ?? 0);
$m  = fin_movement_get($id);
if (!$m) { flash('error', 'Kayıt bulunamadı.'); redirect('modules/finance/index.php'); }

$sym = quote_currency_symbol((string) $m['currency']);
$methods = fin_methods();
layout_top('Makbuz ' . $m['receipt_no'], 'finance');
$row = static fn(string $l, ?string $v): string => trim((string) $v) !== '' ? '<div class="dl-row"><span class="dl-k">' . e($l) . '</span><span class="dl-v">' . e((string) $v) . '</span></div>' : '';
?>
<div class="page-head"><h1 class="page-title"><?= e(fin_doc_type_label((string) $m['doc_type'])) ?> · <?= e((string) $m['receipt_no']) ?></h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/finance/index.php')) ?>"><?= icon('chevron-left') ?>Cari Hareketler</a></div>
</div>
<?= render_flashes() ?>

<div class="card" style="max-width:820px"><div class="card-header"><h2><?= e((string) $m['customer_name']) ?></h2></div>
    <div class="card-body"><div class="dl">
        <?= $row('Tarih', (string) $m['movement_date']) ?>
        <?= $row('Tür', fin_doc_type_label((string) $m['doc_type'])) ?>
        <?= $row('Yön', (string) $m['direction'] === 'debit' ? 'Borç' : 'Alacak') ?>
        <?= $row('Tutar', fmt_money((float) $m['amount']) . ' ' . $sym) ?>
        <?= $row('Ödeme yöntemi', $methods[$m['method']] ?? (string) $m['method']) ?>
        <?= $row('Referans', (string) $m['reference']) ?>
        <?= $row('Açıklama', (string) $m['description']) ?>
    </div></div>
</div>

<?php
$docType = 'payment'; $docId = $id; $docRow = $m; $docBackUrl = 'modules/finance/view.php?id=' . $id;
require __DIR__ . '/../../includes/_document_send_ui.php';
layout_bottom();
