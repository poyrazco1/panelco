<?php
declare(strict_types=1);

/**
 * includes/document_builders.php
 * Belge gövdesi üreticileri (.doc-sheet içeriği). Her belge türü için tek kaynak;
 * yazdırma, PDF ve e-posta AYNI çıktıyı kullanır. forms.php document_* ile üretir.
 */

require_once __DIR__ . '/forms.php';
require_once __DIR__ . '/quotes.php';

/** Kullanıcı adını id'den güvenle çözer (hazırlayan alanı için). */
function doc_user_name(?int $userId): string
{
    if (!$userId) { return ''; }
    try {
        $st = db()->prepare('SELECT full_name FROM users WHERE id = ? LIMIT 1');
        $st->execute([$userId]);
        return (string) ($st->fetchColumn() ?: '');
    } catch (Throwable $e) { return ''; }
}

/** TEKLİF belge gövdesi. */
function quote_document_inner_html(array $q): string
{
    $sym  = quote_currency_symbol((string) $q['currency']);
    $prep = doc_user_name((int) ($q['prepared_by'] ?? 0));
    ob_start();
    document_header([
        'title'       => 'TEKLİF',
        'number'      => (string) $q['quote_no'],
        'date'        => (string) ($q['quote_date'] ?? ''),
        'prepared_by' => $prep,
        'extra'       => ['Geçerlilik' => (string) ($q['valid_until'] ?? '')],
    ]);
    ?>
    <div class="doc-party">
        <strong>Sayın:</strong> <?= e((string) $q['customer_name']) ?>
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

    <?php
    document_footer('Bu teklif bilgilendirme amaçlıdır.');
    return (string) ob_get_clean();
}
