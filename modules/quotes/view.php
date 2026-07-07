<?php
declare(strict_types=1);

/** modules/quotes/view.php — Teklif detayı + aksiyonlar (yazdır/mail/whatsapp/durum). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/quotes.php';
require_once __DIR__ . '/../../includes/notifications.php'; // build_whatsapp_message_link

auth_boot();
require_permission('quotes.view');

$id = (int) ($_GET['id'] ?? 0);
$q  = get_quote($id);
if (!$q) { flash('error', 'Teklif bulunamadı.'); redirect('modules/quotes/index.php'); }

$sym = quote_currency_symbol((string) $q['currency']);
$wa  = null;
if (can('quotes.whatsapp') && trim((string) ($q['phone'] ?? '')) !== '') {
    $wa = build_whatsapp_message_link((string) $q['phone'], quote_text_summary($q));
}

layout_top('Teklif ' . $q['quote_no'], 'quotes');
?>
<div class="page-head">
    <h1 class="page-title">Teklif · <?= e((string) $q['quote_no']) ?></h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/quotes/index.php')) ?>">← Teklifler</a>
        <?php if (can('quotes.print') || can('quotes.pdf')): ?><a class="btn btn-sm" href="<?= e(url('modules/quotes/print.php?id=' . $id)) ?>" target="_blank"><?= icon('printer') ?>Yazdır / PDF</a><?php endif; ?>
        <?php if ($wa): ?><a class="btn btn-sm quick-action-btn quick-action-whatsapp" href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon('message-circle', 'icon-xs') ?>WhatsApp</a><?php endif; ?>
        <?php if (can('quotes.edit')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/quotes/edit.php?id=' . $id)) ?>"><?= icon('pencil') ?>Düzenle</a><?php endif; ?>
    </div>
</div>

<?= render_flashes() ?>

<div class="card" style="max-width:980px">
    <div class="card-header"><h2><?= e($q['customer_name']) ?></h2>
        <span class="badge <?= e(quote_status_class((string) $q['status'])) ?>"><?= e(quote_status_label((string) $q['status'])) ?></span>
    </div>
    <div class="card-body">
        <div class="dl">
            <?php $row = static fn(string $l, ?string $v): string => trim((string) $v) !== '' ? '<div class="dl-row"><span class="dl-k">' . e($l) . '</span><span class="dl-v">' . e((string) $v) . '</span></div>' : ''; ?>
            <?= $row('Yetkili', $q['contact_name']) ?>
            <?= $row('Telefon', $q['phone']) ?>
            <?= $row('E-posta', $q['email']) ?>
            <?= $row('Teklif tarihi', $q['quote_date']) ?>
            <?= $row('Geçerlilik', $q['valid_until']) ?>
        </div>
    </div>
</div>

<div class="card" style="max-width:980px">
    <div class="card-header"><h2>Satırlar</h2></div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Kod</th><th>Ürün</th><th>Marka</th><th class="nowrap">Adet</th><th class="nowrap">B.Fiyat</th><th class="nowrap">KDV%</th><th class="nowrap">İsk%</th><th class="nowrap">Tutar</th></tr></thead>
                <tbody>
                    <?php foreach (($q['items'] ?? []) as $it): ?>
                        <tr>
                            <td class="nowrap"><?= e((string) ($it['product_code'] ?? '')) ?></td>
                            <td><?= e((string) $it['name']) ?><?php if (!empty($it['description'])): ?><div class="small muted"><?= e((string) $it['description']) ?></div><?php endif; ?></td>
                            <td><?= e((string) ($it['brand'] ?? '')) ?></td>
                            <td class="nowrap"><?= e(rtrim(rtrim((string) $it['qty'], '0'), '.')) ?></td>
                            <td class="nowrap"><?= e(fmt_money((float) $it['unit_price'])) ?></td>
                            <td class="nowrap"><?= e(rtrim(rtrim((string) $it['vat_rate'], '0'), '.')) ?></td>
                            <td class="nowrap"><?= e(rtrim(rtrim((string) $it['discount'], '0'), '.')) ?></td>
                            <td class="nowrap"><?= e(fmt_money((float) $it['line_total']) . ' ' . $sym) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <table class="quote-total-table" style="margin-left:auto">
            <tr><td>Ara Toplam</td><td><?= e(fmt_money((float) $q['subtotal']) . ' ' . $sym) ?></td></tr>
            <tr><td>İskonto</td><td><?= e(fmt_money((float) $q['discount_total']) . ' ' . $sym) ?></td></tr>
            <tr><td>KDV</td><td><?= e(fmt_money((float) $q['vat_total']) . ' ' . $sym) ?></td></tr>
            <tr class="grand"><td>Genel Toplam</td><td><?= e(fmt_money((float) $q['grand_total']) . ' ' . $sym) ?></td></tr>
        </table>
    </div>
</div>

<?php if (trim((string) ($q['notes'] ?? '')) !== '' || trim((string) ($q['terms'] ?? '')) !== ''): ?>
<div class="card" style="max-width:980px"><div class="card-body">
    <?php if (trim((string) $q['notes']) !== ''): ?><p><strong>Notlar:</strong><br><?= nl2br(e((string) $q['notes'])) ?></p><?php endif; ?>
    <?php if (trim((string) $q['terms']) !== ''): ?><p><strong>Şartlar:</strong><br><?= nl2br(e((string) $q['terms'])) ?></p><?php endif; ?>
</div></div>
<?php endif; ?>

<div class="card" style="max-width:980px"><div class="card-header"><h2>İşlemler</h2></div>
    <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <?php if (can('quotes.status')): ?>
        <form method="post" action="<?= e(url('modules/quotes/status.php')) ?>" style="display:flex;gap:8px;align-items:flex-end">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <div class="form-group" style="margin:0"><label for="new_status">Durumu değiştir</label>
                <select id="new_status" name="status"><?php foreach (quote_statuses() as $sv => $sl): ?><option value="<?= e($sv) ?>"<?= (string) $q['status'] === $sv ? ' selected' : '' ?>><?= e($sl) ?></option><?php endforeach; ?></select>
            </div>
            <button type="submit" class="btn btn-sm">Güncelle</button>
        </form>
        <?php endif; ?>

        <?php if (can('quotes.mail') && trim((string) ($q['email'] ?? '')) !== ''): ?>
        <form method="post" action="<?= e(url('modules/quotes/send-mail.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <button type="submit" class="btn btn-sm"><?= icon('mail') ?>Teklifi Mail Gönder</button>
        </form>
        <?php elseif (can('quotes.mail')): ?>
            <span class="birthday-note">Mail için müşteri e-postası gerekli</span>
        <?php endif; ?>
    </div>
</div>

<?php layout_bottom();
