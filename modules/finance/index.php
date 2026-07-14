<?php
declare(strict_types=1);

/** modules/finance/index.php — Cari hareketler (tahsilat/ödeme/borç/alacak) listesi + ekle/düzenle. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/finance.php';

auth_boot();
require_permission('finance');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = (string) ($_POST['op'] ?? '');
    if ($op === 'save' && can('finance.create')) {
        $in = $_POST;
        // Müşteri seçildiyse adı karttan doldur (boşsa).
        $cid = (int) ($_POST['customer_id'] ?? 0);
        if ($cid > 0 && trim((string) ($_POST['customer_name'] ?? '')) === '') {
            require_once __DIR__ . '/../../includes/customers.php';
            $c = get_customer_by_id($cid);
            $in['customer_name'] = (string) ($c['company_name'] ?? '');
        }
        $res = fin_movement_save($in, current_user_id());
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Cari hareket kaydedildi.' : implode(' ', $res['errors']));
    } elseif ($op === 'delete' && can('finance.delete')) {
        fin_movement_delete((int) ($_POST['id'] ?? 0));
        flash('success', 'Hareket silindi.');
    }
    http_response_code(303);
    redirect('modules/finance/index.php');
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit   = $editId > 0 ? fin_movement_get($editId) : null;
$search = trim((string) ($_GET['q'] ?? ''));
$movements = fin_movements(['search' => $search]);

$customers = [];
try { $customers = db()->query('SELECT id, company_name FROM customers WHERE is_deleted = 0 AND is_active = 1 ORDER BY company_name ASC LIMIT 2000')->fetchAll(); }
catch (Throwable $e) { /* sessiz */ }

layout_top('Cari Hareketler', 'finance');
?>
<div class="page-head">
    <h1 class="page-title">Cari Hareketler</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/finance/statement.php')) ?>"><?= icon('file-text') ?>Cari Ekstre</a>
    </div>
</div>
<?= render_flashes() ?>

<div class="grid grid-2">
    <?php if (can('finance.create')): ?>
    <div class="card">
        <div class="card-header"><h2><?= $edit ? 'Hareketi Düzenle' : 'Yeni Hareket (Tahsilat / Ödeme)' ?></h2></div>
        <div class="card-body">
            <form method="post" action="<?= e(url('modules/finance/index.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="op" value="save">
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                <div class="form-group"><label for="f-cust">Müşteri (kayıtlı)</label>
                    <select id="f-cust" name="customer_id">
                        <option value="">— Serbest / manuel —</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"<?= (int) ($edit['customer_id'] ?? 0) === (int) $c['id'] ? ' selected' : '' ?>><?= e((string) $c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select><div class="field-hint">Ekstre alabilmek için kayıtlı müşteri seçin. Serbest kayıtta ad girin.</div></div>
                <div class="form-group"><label for="f-name">Cari adı</label>
                    <input type="text" id="f-name" name="customer_name" value="<?= e((string) ($edit['customer_name'] ?? '')) ?>" placeholder="Müşteri seçtiyseniz boş bırakabilirsiniz"></div>
                <div class="form-row">
                    <div class="form-group"><label for="f-type">Tür</label>
                        <select id="f-type" name="doc_type">
                            <?php foreach (fin_doc_types() as $k => $l): ?><option value="<?= e($k) ?>"<?= ($edit['doc_type'] ?? 'collection') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="f-dir">Yön</label>
                        <select id="f-dir" name="direction">
                            <option value="credit"<?= ($edit['direction'] ?? 'credit') === 'credit' ? ' selected' : '' ?>>Alacak (tahsilat)</option>
                            <option value="debit"<?= ($edit['direction'] ?? '') === 'debit' ? ' selected' : '' ?>>Borç (fatura/ödeme)</option>
                        </select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="f-amount">Tutar</label><input type="text" id="f-amount" name="amount" value="<?= e((string) ($edit['amount'] ?? '')) ?>" inputmode="decimal" required></div>
                    <div class="form-group"><label for="f-cur">Para birimi</label>
                        <select id="f-cur" name="currency">
                            <?php foreach (['TRY'=>'TL (₺)','USD'=>'USD ($)','EUR'=>'EUR (€)'] as $cv=>$cl): ?><option value="<?= e($cv) ?>"<?= ($edit['currency'] ?? 'TRY') === $cv ? ' selected' : '' ?>><?= e($cl) ?></option><?php endforeach; ?>
                        </select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="f-date">Tarih</label><input type="date" id="f-date" name="movement_date" value="<?= e((string) ($edit['movement_date'] ?? date('Y-m-d'))) ?>"></div>
                    <div class="form-group"><label for="f-method">Ödeme yöntemi</label>
                        <select id="f-method" name="method">
                            <option value="">—</option>
                            <?php foreach (fin_methods() as $k=>$l): ?><option value="<?= e($k) ?>"<?= ($edit['method'] ?? '') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select></div>
                </div>
                <div class="form-group"><label for="f-ref">Referans</label><input type="text" id="f-ref" name="reference" value="<?= e((string) ($edit['reference'] ?? '')) ?>"></div>
                <div class="form-group"><label for="f-desc">Açıklama</label><textarea id="f-desc" name="description" rows="2"><?= e((string) ($edit['description'] ?? '')) ?></textarea></div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= $edit ? 'Güncelle' : 'Ekle' ?></button>
                    <?php if ($edit): ?><a class="btn" href="<?= e(url('modules/finance/index.php')) ?>">Vazgeç</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h2>Hareketler</h2></div>
        <div class="card-body">
            <form method="get" action="<?= e(url('modules/finance/index.php')) ?>" style="margin-bottom:10px">
                <input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari, makbuz no, açıklama ara…" class="settings-search" style="max-width:280px">
            </form>
            <?php if (empty($movements)): ?>
                <p class="muted">Kayıt yok.</p>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Tarih</th><th>Makbuz</th><th>Cari</th><th>Tür</th><th class="nowrap">Borç</th><th class="nowrap">Alacak</th><th style="width:110px">İşlem</th></tr></thead>
                    <tbody>
                    <?php foreach ($movements as $m): $sym = quote_currency_symbol((string) $m['currency']); ?>
                        <tr>
                            <td class="nowrap"><?= e((string) $m['movement_date']) ?></td>
                            <td><a href="<?= e(url('modules/finance/view.php?id=' . (int) $m['id'])) ?>"><?= e((string) $m['receipt_no']) ?></a></td>
                            <td><?= e((string) $m['customer_name']) ?></td>
                            <td><?= e(fin_doc_type_label((string) $m['doc_type'])) ?></td>
                            <td class="nowrap"><?= fin_debit($m) > 0 ? e(fmt_money(fin_debit($m)) . ' ' . $sym) : '' ?></td>
                            <td class="nowrap"><?= fin_credit($m) > 0 ? e(fmt_money(fin_credit($m)) . ' ' . $sym) : '' ?></td>
                            <td>
                                <div class="row-actions">
                                    <a class="btn btn-sm action-icon-btn" href="<?= e(url('modules/finance/view.php?id=' . (int) $m['id'])) ?>" title="Makbuz"><?= icon('eye') ?></a>
                                    <?php if (can('finance.edit')): ?><a class="btn btn-sm action-icon-btn" href="<?= e(url('modules/finance/index.php?edit=' . (int) $m['id'])) ?>" title="Düzenle"><?= icon('pencil') ?></a><?php endif; ?>
                                    <?php if (can('finance.delete')): ?>
                                    <form method="post" action="<?= e(url('modules/finance/index.php')) ?>" style="display:inline" data-confirm="Bu hareket silinsin mi?">
                                        <?= csrf_field() ?><input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                        <button type="submit" class="btn btn-sm action-icon-btn is-danger" title="Sil"><?= icon('trash-2') ?></button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php layout_bottom();
