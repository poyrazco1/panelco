<?php
declare(strict_types=1);

/** modules/quotes/print.php — Kurumsal yazdır/PDF görünümü (includes/forms.php). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/quotes.php';
require_once __DIR__ . '/../../includes/forms.php';

auth_boot();
require_permission('quotes.view');

$id = (int) ($_GET['id'] ?? 0);
$q  = get_quote($id);
if (!$q) { flash('error', 'Teklif bulunamadı.'); redirect('modules/quotes/index.php'); }

$sym = quote_currency_symbol((string) $q['currency']);
$fav = function_exists('pub_favicon_url') ? pub_favicon_url() : null;
$prep = '';
try {
    $st = db()->prepare('SELECT full_name FROM users WHERE id = ? LIMIT 1');
    $st->execute([(int) ($q['prepared_by'] ?? 0)]);
    $prep = (string) ($st->fetchColumn() ?: '');
} catch (Throwable $e) { /* sessiz */ }
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Teklif <?= e((string) $q['quote_no']) ?></title>
    <?php if ($fav): ?><link rel="icon" href="<?= e($fav) ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<?php document_actions(); ?>
<div class="doc-sheet">
    <?php
    document_header([
        'title'       => 'TEKLİF',
        'number'      => (string) $q['quote_no'],
        'date'        => (string) ($q['quote_date'] ?? ''),
        'prepared_by' => $prep,
        'extra'       => ['Geçerlilik' => (string) ($q['valid_until'] ?? '')],
    ]);
    ?>

    <div class="doc-party">
        <strong>Sayın:</strong> <?= e($q['customer_name']) ?>
        <?php if (!empty($q['contact_name'])): ?> · <?= e((string) $q['contact_name']) ?><?php endif; ?>
        <?php if (!empty($q['phone'])): ?><br><?= e((string) $q['phone']) ?><?php endif; ?>
        <?php if (!empty($q['email'])): ?> · <?= e((string) $q['email']) ?><?php endif; ?>
    </div>

    <table class="table" style="margin-top:14px">
        <thead><tr><th>Kod</th><th>Ürün</th><th>Marka</th><th class="nowrap">Adet</th><th class="nowrap">B.Fiyat</th><th class="nowrap">KDV%</th><th class="nowrap">Tutar</th></tr></thead>
        <tbody>
            <?php foreach (($q['items'] ?? []) as $it): ?>
                <tr>
                    <td class="nowrap"><?= e((string) ($it['product_code'] ?? '')) ?></td>
                    <td><?= e((string) $it['name']) ?></td>
                    <td><?= e((string) ($it['brand'] ?? '')) ?></td>
                    <td class="nowrap"><?= e(rtrim(rtrim((string) $it['qty'], '0'), '.')) ?></td>
                    <td class="nowrap"><?= e(fmt_money((float) $it['unit_price'])) ?></td>
                    <td class="nowrap"><?= e(rtrim(rtrim((string) $it['vat_rate'], '0'), '.')) ?></td>
                    <td class="nowrap"><?= e(fmt_money((float) $it['line_total']) . ' ' . $sym) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table class="quote-total-table" style="margin-left:auto;margin-top:12px">
        <tr><td>Ara Toplam</td><td><?= e(fmt_money((float) $q['subtotal']) . ' ' . $sym) ?></td></tr>
        <tr><td>İskonto</td><td><?= e(fmt_money((float) $q['discount_total']) . ' ' . $sym) ?></td></tr>
        <tr><td>KDV</td><td><?= e(fmt_money((float) $q['vat_total']) . ' ' . $sym) ?></td></tr>
        <tr class="grand"><td>Genel Toplam</td><td><?= e(fmt_money((float) $q['grand_total']) . ' ' . $sym) ?></td></tr>
    </table>

    <?php if (trim((string) ($q['notes'] ?? '')) !== ''): ?><p style="margin-top:14px"><strong>Notlar:</strong><br><?= nl2br(e((string) $q['notes'])) ?></p><?php endif; ?>
    <?php if (trim((string) ($q['terms'] ?? '')) !== ''): ?><p><strong>Şartlar ve Koşullar:</strong><br><?= nl2br(e((string) $q['terms'])) ?></p><?php endif; ?>

    <?php document_footer('Bu teklif bilgilendirme amaçlıdır.'); ?>
</div>
</body>
</html>
