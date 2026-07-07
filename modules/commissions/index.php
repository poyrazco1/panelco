<?php
declare(strict_types=1);

/** modules/commissions/index.php — Prim listesi (görünürlük kısıtlı) + filtre + export. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/commissions.php';

auth_boot();
require_permission('commissions.view');

$seeAll = commission_can_see_all();
$f = [
    'personnel_id' => $seeAll ? (int) ($_GET['personnel_id'] ?? 0) : 0,
    'date_from'    => (string) ($_GET['date_from'] ?? ''),
    'date_to'      => (string) ($_GET['date_to'] ?? ''),
];
$rows = get_commissions($f);
$people = $seeAll ? get_personnel_options(false) : [];
$total = 0.0;
foreach ($rows as $r) { $total += (float) $r['calculated_commission']; }
$exportQs = http_build_query(array_filter($f, static fn ($v) => $v !== '' && $v !== 0));

layout_top('Primler', 'commissions');
?>
<div class="page-head"><h1 class="page-title">Primler</h1><div class="page-actions">
    <?php if (can('commissions.export')): ?><a class="btn btn-sm" href="<?= e(url('modules/commissions/export.php' . ($exportQs !== '' ? '?' . $exportQs : ''))) ?>"><?= icon('download') ?>CSV</a><?php endif; ?>
    <?php if (can('commissions.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/commissions/create.php')) ?>"><?= icon('plus') ?>Yeni Prim</a><?php endif; ?>
</div></div>
<?= render_flashes() ?>

<?php if (!$seeAll): ?><div class="alert alert-info">Yalnızca kendi prim kayıtlarınızı görüntülüyorsunuz.</div><?php endif; ?>

<form method="get" action="<?= e(url('modules/commissions/index.php')) ?>" class="toolbar">
    <?php if ($seeAll): ?>
    <div class="form-group"><label for="personnel_id">Personel</label>
        <select id="personnel_id" name="personnel_id"><option value="0">Tümü</option>
            <?php foreach ($people as $pid => $pname): ?><option value="<?= (int) $pid ?>"<?= $f['personnel_id'] === (int) $pid ? ' selected' : '' ?>><?= e($pname) ?></option><?php endforeach; ?>
        </select></div>
    <?php endif; ?>
    <div class="form-group"><label for="date_from">Başlangıç</label><input type="date" id="date_from" name="date_from" value="<?= e($f['date_from']) ?>"></div>
    <div class="form-group"><label for="date_to">Bitiş</label><input type="date" id="date_to" name="date_to" value="<?= e($f['date_to']) ?>"></div>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Filtrele</button></div>
</form>

<?php if (!$rows): ?><div class="card"><div class="card-body"><div class="empty">Prim kaydı bulunamadı.</div></div></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Personel</th><th>Dönem</th><th class="nowrap">Satış</th><th class="nowrap">Hedef %</th><th class="nowrap">Prim</th><th class="nowrap">İşlem</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $sym = quote_currency_symbol((string) $r['currency']); ?>
        <tr>
            <td><a href="<?= e(url('modules/commissions/view.php?id=' . (int) $r['id'])) ?>"><strong><?= e((string) ($r['personnel_name'] ?? '—')) ?></strong></a></td>
            <td class="nowrap"><?= e((string) ($r['period_start'] ?? '')) ?> – <?= e((string) ($r['period_end'] ?? '')) ?></td>
            <td class="nowrap"><?= e(fmt_money((float) $r['sales_amount']) . ' ' . $sym) ?></td>
            <td class="nowrap"><?= e(rtrim(rtrim((string) $r['target_ratio'], '0'), '.')) ?: '0' ?> %</td>
            <td class="nowrap"><strong><?= e(fmt_money((float) $r['calculated_commission']) . ' ' . $sym) ?></strong></td>
            <td class="nowrap"><a class="btn btn-xs" href="<?= e(url('modules/commissions/view.php?id=' . (int) $r['id'])) ?>"><?= icon('eye', 'icon-xs') ?></a>
            <?php if (can('commissions.edit')): ?><a class="btn btn-xs" href="<?= e(url('modules/commissions/edit.php?id=' . (int) $r['id'])) ?>"><?= icon('pencil', 'icon-xs') ?></a><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="4" style="text-align:right"><strong>Toplam</strong></td><td class="nowrap"><strong><?= e(fmt_money($total)) ?></strong></td><td></td></tr></tfoot>
</table></div>
<?php endif; ?>
<?php layout_bottom();
