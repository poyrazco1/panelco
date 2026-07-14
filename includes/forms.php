<?php
declare(strict_types=1);

/**
 * includes/forms.php
 * Ortak form / belge (teklif, mutabakat, sipariş, sevkiyat, ekstre, tahsilat,
 * servis, iade vb.) kurumsal çıktı altyapısı.
 *
 * Firma bilgilerini app_settings üzerinden okur, standart kurumsal başlık/altbilgi
 * ve kaşe-imza alanı üretir. Harici PDF paketine bağımlı DEĞİLDİR (print-friendly
 * HTML; tarayıcıdan "PDF olarak kaydet" ile ya da PdfService ile alınır).
 * Baskı düzeni assets/css/print.css ile gelir.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

/**
 * Belge/form için firma ayarları (app_settings). Tek sorguda okur, önbelleğe alır.
 * @return array<string,string>
 */
function form_company_settings(): array
{
    static $cache = null;
    if ($cache !== null) { return $cache; }

    $keys = [
        'company_name', 'company_legal_name', 'company_tax_office', 'company_tax_no',
        'company_tax', 'company_address', 'company_phone', 'company_whatsapp',
        'company_email', 'company_website', 'company_mersis', 'company_trade_registry',
        'company_logo', 'company_logo_print', 'company_logo_dark', 'company_logo_light',
        'company_favicon', 'company_kase', 'company_signature', 'company_authorized_name',
        'default_currency', 'default_vat', 'mail_from_name', 'mail_from_email',
    ];
    $out = array_fill_keys($keys, '');
    try {
        $in = implode(',', array_fill(0, count($keys), '?'));
        $st = db()->prepare("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ($in)");
        $st->execute($keys);
        foreach ($st->fetchAll() as $r) {
            $out[(string) $r['setting_key']] = (string) ($r['setting_value'] ?? '');
        }
    } catch (Throwable $e) {
        log_error('form_company_settings: ' . $e->getMessage());
    }
    return $cache = $out;
}

/** Verilen app_settings anahtarındaki göreli dosya yolu — dosya gerçekten varsa. */
function form_asset_rel(string $key): ?string
{
    $rel = ltrim((string) (form_company_settings()[$key] ?? ''), '/');
    if ($rel !== '' && is_file(APP_ROOT . '/' . $rel)) { return $rel; }
    return null;
}

/**
 * Belge logosu (öncelik sırası). $forPrint true ise: yazdırma logosu → ana logo.
 * Aksi halde: ana logo → yazdırma logosu. Hiçbiri yoksa null (metin kullanılır).
 */
function form_logo_rel(bool $forPrint = false): ?string
{
    $order = $forPrint
        ? ['company_logo_print', 'company_logo', 'company_logo_light']
        : ['company_logo', 'company_logo_print', 'company_logo_light'];
    foreach ($order as $k) {
        $rel = form_asset_rel($k);
        if ($rel !== null) { return $rel; }
    }
    return null;
}

/** Favicon göreli yolu (dosya varsa). */
function form_favicon_rel(): ?string
{
    return form_asset_rel('company_favicon');
}

/**
 * Standart kurumsal belge başlığı üretir (echo eder).
 *
 * @param array $opts [
 *   'title'       => 'MUTABAKAT',       // belge türü (sağ üst)
 *   'number'      => 'MUT-2026-0001',
 *   'date'        => '07.07.2026',
 *   'reference'   => 'REF-...',         // opsiyonel referans no
 *   'department'  => 'Muhasebe',        // opsiyonel oluşturan departman
 *   'prepared_by' => 'Ad Soyad',        // oluşturan kullanıcı (alias: created_by)
 *   'created_by'  => 'Ad Soyad',
 *   'approved_by' => '',                // opsiyonel
 *   'extra'       => ['Dönem' => '...'],// ek meta satırları
 * ]
 */
function document_header(array $opts): void
{
    $c    = form_company_settings();
    $logo = form_logo_rel(true);

    $name = $c['company_legal_name'] !== '' ? $c['company_legal_name']
          : ($c['company_name'] !== '' ? $c['company_name'] : 'Firma');

    // Firma bilgi bloğu (adres, iletişim, vergi, mersis, ticaret sicil)
    $metaLines = [];
    if ($c['company_address'] !== '') { $metaLines[] = $c['company_address']; }
    $contact = [];
    if ($c['company_phone'] !== '')   { $contact[] = 'Tel: ' . $c['company_phone']; }
    if ($c['company_email'] !== '')   { $contact[] = $c['company_email']; }
    if ($c['company_website'] !== '') { $contact[] = $c['company_website']; }
    if ($contact) { $metaLines[] = implode(' · ', $contact); }
    $taxParts = array_filter([$c['company_tax_office'], $c['company_tax_no'] ?: $c['company_tax']]);
    if ($taxParts) { $metaLines[] = 'VD/VNo: ' . implode(' / ', $taxParts); }
    $reg = [];
    if ($c['company_mersis'] !== '')         { $reg[] = 'MERSİS: ' . $c['company_mersis']; }
    if ($c['company_trade_registry'] !== '') { $reg[] = 'Tic.Sic.No: ' . $c['company_trade_registry']; }
    if ($reg) { $metaLines[] = implode(' · ', $reg); }

    echo '<div class="doc-header">';
    echo   '<div class="doc-brand">';
    if ($logo !== null) {
        echo '<img class="doc-logo" src="' . e(url($logo)) . '" alt="' . e($name) . '">';
    }
    echo     '<div class="doc-brand-text">';
    echo       '<p class="doc-company-name">' . e($c['company_name'] !== '' ? $c['company_name'] : $name) . '</p>';
    if ($c['company_legal_name'] !== '' && $c['company_legal_name'] !== $c['company_name']) {
        echo   '<p class="doc-company-legal">' . e($c['company_legal_name']) . '</p>';
    }
    echo       '<div class="doc-company-meta">' . nl2br(e(implode("\n", $metaLines))) . '</div>';
    echo     '</div>';
    echo   '</div>';

    $preparedBy = (string) ($opts['prepared_by'] ?? ($opts['created_by'] ?? ''));

    echo   '<div class="doc-meta">';
    echo     '<p class="doc-title">' . e((string) ($opts['title'] ?? 'BELGE')) . '</p>';
    if (!empty($opts['number']))    { echo '<div class="doc-meta-row"><span>No:</span><span>' . e((string) $opts['number']) . '</span></div>'; }
    if (!empty($opts['date']))      { echo '<div class="doc-meta-row"><span>Tarih:</span><span>' . e((string) $opts['date']) . '</span></div>'; }
    if (!empty($opts['reference'])) { echo '<div class="doc-meta-row"><span>Referans:</span><span>' . e((string) $opts['reference']) . '</span></div>'; }
    foreach ((array) ($opts['extra'] ?? []) as $k => $v) {
        if ($v === '' || $v === null) { continue; }
        echo '<div class="doc-meta-row"><span>' . e((string) $k) . ':</span><span>' . e((string) $v) . '</span></div>';
    }
    if (!empty($opts['department'])) { echo '<div class="doc-meta-row"><span>Departman:</span><span>' . e((string) $opts['department']) . '</span></div>'; }
    if ($preparedBy !== '')          { echo '<div class="doc-meta-row"><span>Hazırlayan:</span><span>' . e($preparedBy) . '</span></div>'; }
    if (!empty($opts['approved_by'])){ echo '<div class="doc-meta-row"><span>Onaylayan:</span><span>' . e((string) $opts['approved_by']) . '</span></div>'; }
    echo   '</div>';
    echo '</div>';
}

/**
 * Belge altbilgisi: firma iletişim özeti + sistem üretim notu + tarih/saat.
 * @param string $note Belgeye özel ek açıklama (ör. "Bu teklif bilgilendirme amaçlıdır.")
 */
function document_footer(string $note = ''): void
{
    $c = form_company_settings();
    $left = $c['company_name'] !== '' ? $c['company_name'] : SITE_NAME;
    if ($c['company_website'] !== '') { $left .= ' · ' . $c['company_website']; }
    $contact = array_filter([$c['company_phone'] ?: '', $c['company_email'] ?: '']);

    echo '<div class="doc-footer">';
    echo   '<div class="doc-footer-line">';
    echo     '<span>' . e($left) . '</span>';
    if ($contact) { echo '<span>' . e(implode(' · ', $contact)) . '</span>'; }
    echo   '</div>';
    if ($note !== '') {
        echo '<div class="doc-footer-note">' . e($note) . '</div>';
    }
    echo   '<div class="doc-footer-note">Bu belge ' . e($c['company_name'] !== '' ? $c['company_name'] : SITE_NAME)
         . ' yönetim paneli tarafından ' . e(date('d.m.Y H:i')) . ' tarihinde otomatik oluşturulmuştur.</div>';
    echo '</div>';
}

/**
 * Kaşe / imza satırı. Yüklü kaşe ve imza görselleri düzenleyen (sol) kutuda
 * gösterilir; yoksa el ile imza için boşluk bırakılır.
 *
 * @param string $leftLabel   Sol kutu etiketi (düzenleyen taraf)
 * @param string $rightLabel  Sağ kutu etiketi (karşı taraf / müşteri)
 * @param array  $opts [
 *   'stamp'      => true,   // firma kaşe/imza görsellerini sol kutuda göster (varsayılan true)
 *   'left_name'  => '',     // sol kutu altına yetkili adı (boşsa ayarlardaki yetkili)
 *   'right_name' => '',     // sağ kutu altına ad
 * ]
 */
function document_signatures(string $leftLabel = 'Hazırlayan', string $rightLabel = 'Onaylayan / Kaşe-İmza', array $opts = []): void
{
    $c        = form_company_settings();
    $showStamp = $opts['stamp'] ?? true;
    $kase      = $showStamp ? form_asset_rel('company_kase') : null;
    $sign      = $showStamp ? form_asset_rel('company_signature') : null;
    $leftName  = (string) ($opts['left_name'] ?? ($showStamp ? $c['company_authorized_name'] : ''));
    $rightName = (string) ($opts['right_name'] ?? '');

    $box = static function (string $label, ?string $img1, ?string $img2, string $name): void {
        echo '<div class="doc-sign-box">';
        echo   '<div class="doc-sign-media">';
        if ($img1 !== null) { echo '<img src="' . e(url($img1)) . '" alt="Kaşe">'; }
        if ($img2 !== null) { echo '<img src="' . e(url($img2)) . '" alt="İmza">'; }
        echo   '</div>';
        echo   '<div class="doc-sign-line">' . e($label) . '</div>';
        if ($name !== '') { echo '<div class="doc-sign-name">' . e($name) . '</div>'; }
        echo '</div>';
    };

    echo '<div class="doc-sign-row">';
    $box($leftLabel, $kase, $sign, $leftName);
    $box($rightLabel, null, null, $rightName);
    echo '</div>';
}

/**
 * Yazdırma araç çubuğu (ekranda görünür, baskıda gizli).
 * PdfService entegre edilince "PDF indir" ve "E-posta gönder" butonları
 * document_actions_ext() ile eklenir; bu temel sürüm yalnızca tarayıcı yazdırmasıdır.
 */
function document_actions(): void
{
    echo '<div class="doc-actions no-print">'
       . '<button type="button" class="btn btn-sm" onclick="window.print()">' . icon('printer') . 'Yazdır / PDF</button>'
       . '</div>';
}
