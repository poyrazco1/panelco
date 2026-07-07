<?php
declare(strict_types=1);

/**
 * includes/forms.php
 * Ortak form / belge (teklif, mutabakat, sipariş vb.) çıktı altyapısı.
 * Firma bilgilerini app_settings üzerinden okur, standart kurumsal başlık üretir,
 * yazdırma / PDF için sade HTML sağlar. Harici PDF paketine bağımlı DEĞİLDİR
 * (print-friendly HTML; tarayıcıdan "PDF olarak kaydet" ile alınır).
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

/**
 * Belge/form altbilgisi için firma ayarları (app_settings).
 * @return array<string,string>
 */
function form_company_settings(): array
{
    static $cache = null;
    if ($cache !== null) { return $cache; }

    $keys = [
        'company_name', 'company_legal_name', 'company_tax_office', 'company_tax_no',
        'company_tax', 'company_address', 'company_phone', 'company_whatsapp',
        'company_email', 'company_website', 'company_logo', 'company_favicon',
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

/** Logonun göreli yolu (dosya gerçekten varsa), aksi halde null. */
function form_logo_rel(): ?string
{
    $rel = ltrim((string) (form_company_settings()['company_logo'] ?? ''), '/');
    if ($rel !== '' && is_file(APP_ROOT . '/' . $rel)) { return $rel; }
    return null;
}

/** Favicon göreli yolu (dosya varsa). */
function form_favicon_rel(): ?string
{
    $rel = ltrim((string) (form_company_settings()['company_favicon'] ?? ''), '/');
    if ($rel !== '' && is_file(APP_ROOT . '/' . $rel)) { return $rel; }
    return null;
}

/**
 * Standart kurumsal belge başlığı üretir (echo eder).
 *
 * @param array $opts [
 *   'title'       => 'TEKLİF',
 *   'number'      => 'TKF-2026-0001',
 *   'date'        => '07.07.2026',
 *   'valid_until' => '...',        // opsiyonel ek meta satırları için 'extra'
 *   'prepared_by' => 'Rabia Dilek',
 *   'approved_by' => '',           // opsiyonel
 *   'extra'       => ['Geçerlilik' => '...'],  // ek meta satırları
 * ]
 */
function document_header(array $opts): void
{
    $c = form_company_settings();
    $logo = form_logo_rel();

    $name = $c['company_name'] !== '' ? $c['company_name'] : ($c['company_legal_name'] !== '' ? $c['company_legal_name'] : 'Firma');
    $metaLines = [];
    if ($c['company_address'] !== '') { $metaLines[] = $c['company_address']; }
    $contact = [];
    if ($c['company_phone'] !== '')   { $contact[] = 'Tel: ' . $c['company_phone']; }
    if ($c['company_email'] !== '')   { $contact[] = $c['company_email']; }
    if ($c['company_website'] !== '') { $contact[] = $c['company_website']; }
    if ($contact) { $metaLines[] = implode(' · ', $contact); }
    $taxParts = array_filter([$c['company_tax_office'], $c['company_tax_no'] ?: $c['company_tax']]);
    if ($taxParts) { $metaLines[] = 'VD/VNo: ' . implode(' / ', $taxParts); }

    echo '<div class="doc-header">';
    echo   '<div class="doc-brand">';
    if ($logo !== null) {
        echo '<img class="doc-logo" src="' . e(asset($logo)) . '" alt="' . e($name) . '">';
    }
    echo     '<div class="doc-brand-text">';
    echo       '<p class="doc-company-name">' . e($name) . '</p>';
    echo       '<div class="doc-company-meta">' . nl2br(e(implode("\n", $metaLines))) . '</div>';
    echo     '</div>';
    echo   '</div>';

    echo   '<div class="doc-meta">';
    echo     '<p class="doc-title">' . e((string) ($opts['title'] ?? 'BELGE')) . '</p>';
    if (!empty($opts['number'])) { echo '<div class="doc-meta-row"><span>No:</span><span>' . e((string) $opts['number']) . '</span></div>'; }
    if (!empty($opts['date']))   { echo '<div class="doc-meta-row"><span>Tarih:</span><span>' . e((string) $opts['date']) . '</span></div>'; }
    foreach ((array) ($opts['extra'] ?? []) as $k => $v) {
        if ($v === '' || $v === null) { continue; }
        echo '<div class="doc-meta-row"><span>' . e((string) $k) . ':</span><span>' . e((string) $v) . '</span></div>';
    }
    if (!empty($opts['prepared_by'])) { echo '<div class="doc-meta-row"><span>Hazırlayan:</span><span>' . e((string) $opts['prepared_by']) . '</span></div>'; }
    if (!empty($opts['approved_by'])) { echo '<div class="doc-meta-row"><span>Onaylayan:</span><span>' . e((string) $opts['approved_by']) . '</span></div>'; }
    echo   '</div>';
    echo '</div>';
}

/** Belge altbilgisi (firma adı + web + sayfa notu). */
function document_footer(string $note = ''): void
{
    $c = form_company_settings();
    echo '<div class="doc-footer">';
    echo   '<span>' . e($c['company_name'] !== '' ? $c['company_name'] : SITE_NAME);
    if ($c['company_website'] !== '') { echo ' · ' . e($c['company_website']); }
    echo   '</span>';
    if ($note !== '') { echo '<span>' . e($note) . '</span>'; }
    echo '</div>';
}

/** İmza/kaşe satırı (mutabakat vb.). */
function document_signatures(string $leftLabel = 'Hazırlayan', string $rightLabel = 'Onaylayan / Kaşe-İmza'): void
{
    echo '<div class="doc-sign-row">';
    echo   '<div class="doc-sign-box"><div class="doc-sign-line">' . e($leftLabel) . '</div></div>';
    echo   '<div class="doc-sign-box"><div class="doc-sign-line">' . e($rightLabel) . '</div></div>';
    echo '</div>';
}

/**
 * Yazdırma araç çubuğu (Yazdır / PDF butonu — tarayıcı print()).
 */
function document_actions(): void
{
    echo '<div class="doc-actions no-print">'
       . '<button type="button" class="btn btn-sm" onclick="window.print()">' . icon('printer') . 'Yazdır / PDF</button>'
       . '</div>';
}
