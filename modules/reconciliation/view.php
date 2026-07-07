<?php
declare(strict_types=1);

/** modules/reconciliation/view.php — Mutabakat detayı + aksiyonlar. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/reconciliation.php';
require_once __DIR__ . '/../../includes/notifications.php';

auth_boot();
require_permission('reconciliation.view');

$id = (int) ($_GET['id'] ?? 0);
$r  = get_reconciliation($id);
if (!$r) { flash('error', 'Mutabakat bulunamadı.'); redirect('modules/reconciliation/index.php'); }

$sym = quote_currency_symbol((string) $r['currency']);
$wa = null;
layout_top('Mutabakat ' . $r['recon_no'], 'reconciliation');
$row = static fn(string $l, ?string $v): string => trim((string) $v) !== '' ? '<div class="dl-row"><span class="dl-k">' . e($l) . '</span><span class="dl-v">' . e((string) $v) . '</span></div>' : '';
?>
<div class="page-head"><h1 class="page-title">Mutabakat · <?= e((string) $r['recon_no']) ?></h1><div class="page-actions">
    <a class="btn btn-sm" href="<?= e(url('modules/reconciliation/index.php')) ?>">← Mutabakat</a>
    <?php if (can('reconciliation.print') || can('reconciliation.pdf')): ?><a class="btn btn-sm" href="<?= e(url('modules/reconciliation/print.php?id=' . $id)) ?>" target="_blank"><?= icon('printer') ?>Yazdır / PDF</a><?php endif; ?>
    <?php if (can('reconciliation.edit')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/reconciliation/edit.php?id=' . $id)) ?>"><?= icon('pencil') ?>Düzenle</a><?php endif; ?>
</div></div>
<?= render_flashes() ?>
<div class="card" style="max-width:820px"><div class="card-header"><h2><?= e($r['customer_name']) ?></h2>
    <span class="badge <?= e(recon_agreement_class((string) $r['agreement'])) ?>"><?= e(recon_agreement_label((string) $r['agreement'])) ?></span></div>
    <div class="card-body"><div class="dl">
        <?= $row('Cari kod', $r['cari_code']) ?>
        <?= $row('Dönem', $r['period']) ?>
        <?= $row('Tarih', $r['recon_date']) ?>
        <?= $row('Borç', fmt_money((float) $r['debit']) . ' ' . $sym) ?>
        <?= $row('Alacak', fmt_money((float) $r['credit']) . ' ' . $sym) ?>
        <?= $row('Bakiye', fmt_money((float) $r['balance']) . ' ' . $sym) ?>
        <?= $row('Yetkili', $r['authorized_name']) ?>
        <?= $row('Açıklama', $r['description']) ?>
    </div></div>
</div>
<?php if (can('reconciliation.mail') && ($cid = (int) ($r['customer_id'] ?? 0))): ?>
<div class="card" style="max-width:820px"><div class="card-header"><h2>İşlemler</h2></div><div class="card-body">
    <form method="post" action="<?= e(url('modules/reconciliation/send-mail.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
        <button type="submit" class="btn btn-sm"><?= icon('mail') ?>Mutabakatı Mail Gönder</button>
        <span class="birthday-note">Müşteri kartındaki e-postaya gönderilir.</span>
    </form>
</div></div>
<?php endif; ?>
<?php layout_bottom();
