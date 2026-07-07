<?php
declare(strict_types=1);

/** modules/quotes/index.php — Teklif listesi + özet + filtre. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/quotes.php';

auth_boot();
require_permission('quotes.view');

$f = [
    'status'    => (string) ($_GET['status'] ?? ''),
    'date_from' => (string) ($_GET['date_from'] ?? ''),
    'date_to'   => (string) ($_GET['date_to'] ?? ''),
    'search'    => trim((string) ($_GET['q'] ?? '')),
];
$rows = get_quotes($f);
$sum  = quote_summary();
$statuses = quote_statuses();

layout_top('Teklifler', 'quotes');
?>
<div class="page-head">
    <h1 class="page-title">Teklifler</h1>
    <div class="page-actions">
        <?php if (can('quotes.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/quotes/create.php')) ?>"><?= icon('plus') ?>Yeni Teklif</a><?php endif; ?>
    </div>
</div>

<?= render_flashes() ?>

<div class="rma-stats">
    <div class="rma-stat"><div class="rs-label">Toplam</div><div class="rs-value"><?= (int) $sum['total'] ?></div></div>
    <div class="rma-stat"><div class="rs-label">Taslak</div><div class="rs-value"><?= (int) $sum['draft'] ?></div></div>
    <div class="rma-stat is-open"><div class="rs-label">Gönderildi</div><div class="rs-value"><?= (int) $sum['sent'] ?></div></div>
    <div class="rma-stat is-closed"><div class="rs-label">Kabul</div><div class="rs-value"><?= (int) $sum['accepted'] ?></div></div>
</div>

<form method="get" action="<?= e(url('modules/quotes/index.php')) ?>" class="toolbar">
    <div class="form-group"><label for="q">Ara</label><input type="text" id="q" name="q" value="<?= e($f['search']) ?>" placeholder="Teklif no, müşteri"></div>
    <div class="form-group"><label for="status">Durum</label>
        <select id="status" name="status"><option value="">Tümü</option>
            <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group"><label for="date_from">Başlangıç</label><input type="date" id="date_from" name="date_from" value="<?= e($f['date_from']) ?>"></div>
    <div class="form-group"><label for="date_to">Bitiş</label><input type="date" id="date_to" name="date_to" value="<?= e($f['date_to']) ?>"></div>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Filtrele</button></div>
</form>

<?php if (!$rows): ?>
    <div class="card"><div class="card-body"><div class="empty">Teklif bulunamadı.</div></div></div>
<?php else: ?>
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Teklif No</th><th>Müşteri</th><th class="nowrap">Tarih</th><th class="nowrap">Tutar</th><th>Durum</th><th class="nowrap">İşlem</th></tr></thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a href="<?= e(url('modules/quotes/view.php?id=' . (int) $r['id'])) ?>"><strong><?= e($r['quote_no']) ?></strong></a></td>
                    <td><?= e($r['customer_name']) ?></td>
                    <td class="nowrap"><?= e((string) ($r['quote_date'] ?? '—')) ?></td>
                    <td class="nowrap"><?= e(fmt_money((float) $r['grand_total']) . ' ' . quote_currency_symbol((string) $r['currency'])) ?></td>
                    <td><span class="badge <?= e(quote_status_class((string) $r['status'])) ?>"><?= e(quote_status_label((string) $r['status'])) ?></span></td>
                    <td class="nowrap">
                        <a class="btn btn-xs" href="<?= e(url('modules/quotes/view.php?id=' . (int) $r['id'])) ?>"><?= icon('eye', 'icon-xs') ?></a>
                        <?php if (can('quotes.edit')): ?><a class="btn btn-xs" href="<?= e(url('modules/quotes/edit.php?id=' . (int) $r['id'])) ?>"><?= icon('pencil', 'icon-xs') ?></a><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php layout_bottom();
