<?php
declare(strict_types=1);

/** modules/commissions/view.php — Prim detayı. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/commissions.php';

auth_boot();
require_permission('commissions.view');

$id = (int) ($_GET['id'] ?? 0);
$c  = get_commission($id);
if (!$c) { flash('error', 'Prim kaydı bulunamadı veya görüntüleme yetkiniz yok.'); redirect('modules/commissions/index.php'); }

$sym = quote_currency_symbol((string) $c['currency']);
layout_top('Prim Detayı', 'commissions');
$row = static fn(string $l, ?string $v): string => trim((string) $v) !== '' ? '<div class="dl-row"><span class="dl-k">' . e($l) . '</span><span class="dl-v">' . e((string) $v) . '</span></div>' : '';
?>
<div class="page-head"><h1 class="page-title">Prim · <?= e((string) ($c['personnel_name'] ?? '')) ?></h1><div class="page-actions">
    <a class="btn btn-sm" href="<?= e(url('modules/commissions/index.php')) ?>">← Primler</a>
    <?php if (can('commissions.print')): ?><a class="btn btn-sm" href="javascript:window.print()"><?= icon('printer') ?>Yazdır</a><?php endif; ?>
    <?php if (can('commissions.edit')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/commissions/edit.php?id=' . $id)) ?>"><?= icon('pencil') ?>Düzenle</a><?php endif; ?>
</div></div>
<?= render_flashes() ?>
<div class="card" style="max-width:720px"><div class="card-header"><h2>Prim Bilgileri</h2></div><div class="card-body"><div class="dl">
    <?= $row('Personel', $c['personnel_name']) ?>
    <?= $row('Dönem', trim((string) ($c['period_start'] ?? '') . ' – ' . (string) ($c['period_end'] ?? ''), ' –')) ?>
    <?= $row('Satış tutarı', fmt_money((float) $c['sales_amount']) . ' ' . $sym) ?>
    <?= $row('Kâr tutarı', fmt_money((float) $c['profit_amount']) . ' ' . $sym) ?>
    <?= $row('Prim oranı', rtrim(rtrim((string) $c['commission_rate'], '0'), '.') . ' %') ?>
    <?= $row('Sabit prim', fmt_money((float) $c['fixed_commission']) . ' ' . $sym) ?>
    <?= $row('Hedef tutarı', fmt_money((float) $c['target_amount']) . ' ' . $sym) ?>
    <?= $row('Hedef gerçekleşme', rtrim(rtrim((string) $c['target_ratio'], '0'), '.') . ' %') ?>
    <?= $row('Hesaplanan prim', fmt_money((float) $c['calculated_commission']) . ' ' . $sym) ?>
    <?= $row('Açıklama', $c['description']) ?>
</div></div></div>
<?php
$docType = 'commission'; $docId = $id; $docRow = $c; $docBackUrl = 'modules/commissions/view.php?id=' . $id;
require __DIR__ . '/../../includes/_document_send_ui.php';
layout_bottom();
