<?php
declare(strict_types=1);

/**
 * includes/document_registry.php
 * Belge türleri kaydı: yükleme, gövde üretici, belge no/taraf/alıcı ve şablon
 * değişkenleri. Generic PDF/e-posta/geçmiş uçları (modules/documents/*) ve ortak
 * gönderim arayüzü (_document_send_ui.php) bu kaydı kullanır.
 *
 * Onay/token akışı bu generic sistemde YOKTUR (yalnızca mutabakata özgü).
 */

require_once __DIR__ . '/document_builders.php';
require_once __DIR__ . '/email_system.php';

/** Tüm belge türü tanımları. Yeni bir forma uygulamak için buraya bir giriş eklenir. */
function document_types(): array
{
    static $types = null;
    if ($types !== null) { return $types; }

    $types = [
        'quote' => [
            'label'       => 'Teklif',
            'module'      => 'quotes',
            'index'       => 'modules/quotes/index.php',
            'orientation' => 'portrait',
            'load'        => static fn(int $id) => get_quote($id),
            'no'          => static fn(array $r) => (string) $r['quote_no'],
            'party'       => static fn(array $r) => (string) $r['customer_name'],
            'to_email'    => static fn(array $r) => (string) ($r['email'] ?? ''),
            'inner'       => static fn(array $r) => quote_document_inner_html($r),
            'vars'        => static function (array $q, array $actor, ?array $dept): array {
                $sym = quote_currency_symbol((string) $q['currency']);
                return [
                    'firma_unvani'   => (string) $q['customer_name'],
                    'musteri_adi'    => (string) $q['customer_name'],
                    'yetkili_adi'    => (string) ($q['contact_name'] ?? ''),
                    'belge_turu'     => 'Teklif',
                    'belge_no'       => (string) $q['quote_no'],
                    'belge_tarihi'   => (string) ($q['quote_date'] ?? ''),
                    'donem_baslangic'=> '',
                    'donem_bitis'    => (string) ($q['valid_until'] ?? ''),
                    'borc'           => '',
                    'alacak'         => '',
                    'bakiye'         => fmt_money((float) $q['grand_total']),
                    'para_birimi'    => $sym,
                    'personel_adi'   => $actor['full_name'] ?: $actor['username'],
                    'personel_email' => $actor['email'],
                    'departman'      => (string) ($dept['department_name'] ?? ''),
                    'sirket_adi'     => function_exists('pub_brand_name') ? pub_brand_name() : (string) $q['customer_name'],
                    'sirket_telefon' => (string) pub_setting('company_phone', ''),
                    'sirket_email'   => (string) pub_setting('company_email', ''),
                ];
            },
        ],
    ];
    return $types;
}

function document_type_meta(string $type): ?array
{
    return document_types()[$type] ?? null;
}

/** Belge türü + id → satır (yoksa null). */
function document_type_load(string $type, int $id): ?array
{
    $m = document_type_meta($type);
    if (!$m) { return null; }
    try { return ($m['load'])($id) ?: null; }
    catch (Throwable $e) { log_error('document_type_load: ' . $e->getMessage()); return null; }
}

function document_type_inner(string $type, array $row): string
{
    $m = document_type_meta($type);
    return $m ? (string) ($m['inner'])($row) : '';
}

function document_type_vars(string $type, array $row, array $actor, ?array $dept): array
{
    $m = document_type_meta($type);
    return $m ? (array) ($m['vars'])($row, $actor, $dept) : [];
}
