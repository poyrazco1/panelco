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

/** TEKNİK SERVİS KABUL FORMU belge gövdesi (müşteriye açık; iç maliyet gizli). */
function service_document_inner_html(array $s): string
{
    require_once __DIR__ . '/service.php';
    ob_start();
    document_header([
        'title'      => 'TEKNİK SERVİS KABUL FORMU',
        'number'     => (string) ($s['reference_code'] ?? ''),
        'date'       => (string) ($s['received_at'] ?? ''),
        'department' => 'Teknik Servis',
        'extra'      => ['Durum' => function_exists('service_status_label') ? service_status_label((string) ($s['status'] ?? '')) : (string) ($s['status'] ?? '')],
    ]);
    $dev = trim((string) ($s['brand_name'] ?? '') . ' ' . (string) ($s['device_model'] ?? ''));
    ?>
    <div class="doc-party"><strong>Teslim eden:</strong> <?= e((string) $s['customer_name']) ?>
        <?php if (!empty($s['customer_phone'])): ?> · <?= e((string) $s['customer_phone']) ?><?php endif; ?>
        <?php if (!empty($s['customer_email'])): ?> · <?= e((string) $s['customer_email']) ?><?php endif; ?>
        <?php if (!empty($s['customer_address'])): ?><br><?= e((string) $s['customer_address']) ?><?php endif; ?>
    </div>
    <table class="table" style="margin-top:14px"><tbody>
        <tr><th style="width:200px">Cihaz türü</th><td><?= e((string) ($s['device_type'] ?? '—')) ?></td></tr>
        <tr><th>Marka / Model</th><td><?= e($dev !== '' ? $dev : '—') ?></td></tr>
        <tr><th>Miktar</th><td><?= (int) ($s['quantity'] ?? 1) ?></td></tr>
        <tr><th>Seri No</th><td><?= e((string) ($s['serial_no'] ?? '—')) ?></td></tr>
        <tr><th>Aksesuarlar</th><td><?= e((string) ($s['accessories'] ?? '—')) ?></td></tr>
        <tr><th>Fiziksel durum</th><td><?= e((string) ($s['physical_condition'] ?? '—')) ?></td></tr>
    </tbody></table>
    <?php if (trim((string) ($s['problem_description'] ?? '')) !== ''): ?>
        <p style="margin-top:14px"><strong>Bildirilen sorun:</strong><br><?= nl2br(e((string) $s['problem_description'])) ?></p>
    <?php endif; ?>
    <p style="margin-top:14px;font-size:12.5px;color:#444">Cihaz yukarıdaki durumda teslim alınmıştır. Teslim tarihinden itibaren yasal süre içinde teslim alınmayan cihazlardan firmamız sorumlu değildir.</p>
    <?php
    document_signatures('Teslim Alan (Yetkili)', 'Teslim Eden (Müşteri)');
    document_footer();
    return (string) ob_get_clean();
}

/** İADE / DEĞİŞİM / İPTAL FORMU belge gövdesi. */
function rma_document_inner_html(array $r): string
{
    require_once __DIR__ . '/rma.php';
    $recv = function_exists('get_rma_received_items') ? get_rma_received_items((int) $r['id']) : [];
    $sent = function_exists('get_rma_sent_items') ? get_rma_sent_items((int) $r['id']) : [];
    $itemName = static fn(array $it): string => (string) ($it['product_name'] ?? $it['name'] ?? $it['title'] ?? $it['description'] ?? '—');
    $itemQty  = static fn(array $it): string => (string) ($it['quantity'] ?? $it['qty'] ?? '');
    ob_start();
    document_header([
        'title'      => 'İADE / DEĞİŞİM / İPTAL FORMU',
        'number'     => (string) ($r['reference_code'] ?? ''),
        'date'       => (string) ($r['process_date'] ?? ''),
        'department' => 'İade / Değişim',
        'extra'      => [
            'İşlem'  => (string) ($r['process_type'] ?? ''),
            'Sebep'  => (string) ($r['reason_type'] ?? ''),
        ],
    ]);
    ?>
    <div class="doc-party"><strong>Sayın:</strong> <?= e((string) $r['customer_name']) ?>
        <?php if (!empty($r['customer_phone'])): ?> · <?= e((string) $r['customer_phone']) ?><?php endif; ?>
        <?php if (!empty($r['customer_email'])): ?> · <?= e((string) $r['customer_email']) ?><?php endif; ?>
        <?php if (!empty($r['invoice_date'])): ?><br>Fatura tarihi: <?= e((string) $r['invoice_date']) ?><?php endif; ?>
        <?php if (!empty($r['platform'])): ?> · Platform: <?= e((string) $r['platform']) ?><?php endif; ?>
    </div>
    <?php if (!empty($recv)): ?>
        <p style="margin-top:14px"><strong>Müşteriden alınan ürünler</strong></p>
        <table class="table"><thead><tr><th>Ürün</th><th class="nowrap" style="width:80px">Adet</th></tr></thead><tbody>
            <?php foreach ($recv as $it): ?><tr><td><?= e($itemName($it)) ?></td><td class="nowrap"><?= e($itemQty($it)) ?></td></tr><?php endforeach; ?>
        </tbody></table>
    <?php endif; ?>
    <?php if (!empty($sent)): ?>
        <p style="margin-top:12px"><strong>Müşteriye gönderilen ürünler</strong></p>
        <table class="table"><thead><tr><th>Ürün</th><th class="nowrap" style="width:80px">Adet</th></tr></thead><tbody>
            <?php foreach ($sent as $it): ?><tr><td><?= e($itemName($it)) ?></td><td class="nowrap"><?= e($itemQty($it)) ?></td></tr><?php endforeach; ?>
        </tbody></table>
    <?php endif; ?>
    <?php if (trim((string) ($r['description'] ?? '')) !== ''): ?>
        <p style="margin-top:14px"><strong>Açıklama:</strong><br><?= nl2br(e((string) $r['description'])) ?></p>
    <?php endif; ?>
    <?php
    document_signatures('Düzenleyen', 'Müşteri / Kaşe-İmza');
    document_footer();
    return (string) ob_get_clean();
}

/** PRİM FORMU / BORDROSU belge gövdesi (iç belge). */
function commission_document_inner_html(array $c): string
{
    $sym = quote_currency_symbol((string) ($c['currency'] ?? 'TRY'));
    $period = trim((string) ($c['period_start'] ?? '') . ' – ' . (string) ($c['period_end'] ?? ''), ' –');
    ob_start();
    document_header([
        'title'      => 'PRİM FORMU',
        'number'     => 'PRM-' . (string) ($c['id'] ?? ''),
        'date'       => (string) ($c['created_at'] ?? ''),
        'department' => 'Muhasebe',
        'extra'      => ['Dönem' => $period],
    ]);
    ?>
    <div class="doc-party"><strong>Personel:</strong> <?= e((string) ($c['personnel_name'] ?? '')) ?></div>
    <table class="table" style="margin-top:14px"><tbody>
        <tr><th style="width:220px">Satış tutarı</th><td><?= e(fmt_money((float) ($c['sales_amount'] ?? 0)) . ' ' . $sym) ?></td></tr>
        <tr><th>Kâr tutarı</th><td><?= e(fmt_money((float) ($c['profit_amount'] ?? 0)) . ' ' . $sym) ?></td></tr>
        <tr><th>Prim oranı</th><td><?= e(rtrim(rtrim((string) ($c['commission_rate'] ?? '0'), '0'), '.') . ' %') ?></td></tr>
        <tr><th>Sabit prim</th><td><?= e(fmt_money((float) ($c['fixed_commission'] ?? 0)) . ' ' . $sym) ?></td></tr>
        <tr><th>Hedef tutarı</th><td><?= e(fmt_money((float) ($c['target_amount'] ?? 0)) . ' ' . $sym) ?></td></tr>
        <tr><th>Hedef gerçekleşme</th><td><?= e(rtrim(rtrim((string) ($c['target_ratio'] ?? '0'), '0'), '.') . ' %') ?></td></tr>
        <tr><th>Hesaplanan prim</th><td><strong><?= e(fmt_money((float) ($c['calculated_commission'] ?? 0)) . ' ' . $sym) ?></strong></td></tr>
    </tbody></table>
    <?php if (trim((string) ($c['description'] ?? '')) !== ''): ?>
        <p style="margin-top:14px"><strong>Açıklama:</strong><br><?= nl2br(e((string) $c['description'])) ?></p>
    <?php endif; ?>
    <?php
    document_signatures('Hazırlayan', 'Personel');
    document_footer();
    return (string) ob_get_clean();
}

/** TAHSİLAT / ÖDEME MAKBUZU belge gövdesi (tek cari hareket). */
function payment_document_inner_html(array $m): string
{
    require_once __DIR__ . '/finance.php';
    $sym = quote_currency_symbol((string) ($m['currency'] ?? 'TRY'));
    $title = match ((string) ($m['doc_type'] ?? 'manual')) {
        'collection' => 'TAHSİLAT MAKBUZU',
        'payment'    => 'ÖDEME MAKBUZU',
        'invoice'    => 'FATURA / SATIŞ BELGESİ',
        default      => 'CARİ HAREKET BELGESİ',
    };
    $methods = fin_methods();
    ob_start();
    document_header([
        'title'      => $title,
        'number'     => (string) ($m['receipt_no'] ?? ''),
        'date'       => (string) ($m['movement_date'] ?? ''),
        'department' => 'Muhasebe',
        'extra'      => ['Tür' => fin_doc_type_label((string) ($m['doc_type'] ?? 'manual'))],
    ]);
    ?>
    <div class="doc-party"><strong>Sayın:</strong> <?= e((string) ($m['customer_name'] ?? '')) ?></div>
    <table class="table" style="margin-top:14px"><tbody>
        <tr><th style="width:200px">Tutar</th><td><strong><?= e(fmt_money((float) ($m['amount'] ?? 0)) . ' ' . $sym) ?></strong></td></tr>
        <tr><th>Yön</th><td><?= (string) ($m['direction'] ?? 'debit') === 'debit' ? 'Borç' : 'Alacak' ?></td></tr>
        <?php if (!empty($m['method'])): ?><tr><th>Ödeme yöntemi</th><td><?= e($methods[$m['method']] ?? (string) $m['method']) ?></td></tr><?php endif; ?>
        <?php if (!empty($m['reference'])): ?><tr><th>Referans</th><td><?= e((string) $m['reference']) ?></td></tr><?php endif; ?>
    </tbody></table>
    <?php if (trim((string) ($m['description'] ?? '')) !== ''): ?>
        <p style="margin-top:14px"><strong>Açıklama:</strong><br><?= nl2br(e((string) $m['description'])) ?></p>
    <?php endif; ?>
    <?php
    document_signatures('Teslim Eden', 'Teslim Alan / Kaşe-İmza');
    document_footer();
    return (string) ob_get_clean();
}

/**
 * CARİ HESAP EKSTRESİ belge gövdesi.
 * @param array $p ['customer_name','from','to','currency','stmt'=>fin_statement()]
 */
function statement_document_inner_html(array $p): string
{
    $sym  = quote_currency_symbol((string) ($p['currency'] ?? 'TRY'));
    $stmt = (array) ($p['stmt'] ?? ['opening'=>0,'rows'=>[],'total_debit'=>0,'total_credit'=>0,'closing'=>0]);
    $period = trim((string) ($p['from'] ?? '') . ' – ' . (string) ($p['to'] ?? ''), ' –');
    ob_start();
    document_header([
        'title'      => 'CARİ HESAP EKSTRESİ',
        'number'     => (string) ($p['customer_name'] ?? ''),
        'date'       => date('d.m.Y'),
        'department' => 'Muhasebe',
        'extra'      => ['Dönem' => $period !== '' ? $period : 'Tümü'],
    ]);
    ?>
    <div class="doc-party"><strong>Sayın:</strong> <?= e((string) ($p['customer_name'] ?? '')) ?></div>
    <table class="table" style="margin-top:14px">
        <thead><tr><th style="width:90px">Tarih</th><th>Belge / Açıklama</th><th class="nowrap">Borç</th><th class="nowrap">Alacak</th><th class="nowrap">Bakiye</th></tr></thead>
        <tbody>
            <tr><td></td><td><em>Devir / Açılış bakiyesi</em></td><td></td><td></td><td class="nowrap"><strong><?= e(fmt_money((float) $stmt['opening']) . ' ' . $sym) ?></strong></td></tr>
            <?php foreach ($stmt['rows'] as $m): ?>
                <tr>
                    <td class="nowrap"><?= e((string) ($m['movement_date'] ?? '')) ?></td>
                    <td><?= e(trim((string) ($m['receipt_no'] ?? '') . ' ' . (string) ($m['description'] ?? ''))) ?: '—' ?></td>
                    <td class="nowrap"><?= (float) $m['_debit'] > 0 ? e(fmt_money((float) $m['_debit'])) : '' ?></td>
                    <td class="nowrap"><?= (float) $m['_credit'] > 0 ? e(fmt_money((float) $m['_credit'])) : '' ?></td>
                    <td class="nowrap"><?= e(fmt_money((float) $m['_balance']) . ' ' . $sym) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><th colspan="2" style="text-align:right">Dönem Toplamı</th>
                <th class="nowrap"><?= e(fmt_money((float) $stmt['total_debit'])) ?></th>
                <th class="nowrap"><?= e(fmt_money((float) $stmt['total_credit'])) ?></th>
                <th class="nowrap"><?= e(fmt_money((float) $stmt['closing']) . ' ' . $sym) ?></th></tr>
        </tfoot>
    </table>
    <p style="margin-top:14px;font-size:12.5px;color:#444">Kapanış bakiyesi: <strong><?= e(fmt_money((float) $stmt['closing']) . ' ' . $sym) ?></strong>
        (pozitif bakiye, tarafınızın firmamıza olan borcunu gösterir). İşbu ekstreye <strong>7 gün</strong> içinde itiraz edilmezse mutabık sayılır.</p>
    <?php
    document_signatures('Düzenleyen', 'Mutabık / Kaşe-İmza');
    document_footer();
    return (string) ob_get_clean();
}
