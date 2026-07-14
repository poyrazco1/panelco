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
require_once __DIR__ . '/service.php';      // get_service_record
require_once __DIR__ . '/rma.php';          // get_rma_record
require_once __DIR__ . '/commissions.php';  // get_commission
require_once __DIR__ . '/finance.php';      // fin_movement_get
require_once __DIR__ . '/customers.php';    // get_customer_by_id (makbuz alıcı e-postası)
require_once __DIR__ . '/orders.php';       // get_order
require_once __DIR__ . '/shipments.php';    // get_shipment

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

        'service' => [
            'label'       => 'Teknik Servis',
            'module'      => 'service',
            'index'       => 'modules/service/index.php',
            'orientation' => 'portrait',
            'load'        => static fn(int $id) => get_service_record($id),
            'no'          => static fn(array $r) => (string) ($r['reference_code'] ?? ''),
            'party'       => static fn(array $r) => (string) ($r['customer_name'] ?? ''),
            'to_email'    => static fn(array $r) => (string) ($r['customer_email'] ?? ''),
            'inner'       => static fn(array $r) => service_document_inner_html($r),
            'vars'        => static fn(array $s, array $actor, ?array $dept) => document_common_vars('Teknik Servis Kabul',
                (string) ($s['reference_code'] ?? ''), (string) ($s['customer_name'] ?? ''), (string) ($s['customer_name'] ?? ''),
                (string) ($s['received_at'] ?? ''), $actor, $dept),
        ],

        'rma' => [
            'label'       => 'İade / Değişim',
            'module'      => 'rma',
            'index'       => 'modules/rma/index.php',
            'orientation' => 'portrait',
            'load'        => static fn(int $id) => get_rma_record($id),
            'no'          => static fn(array $r) => (string) ($r['reference_code'] ?? ''),
            'party'       => static fn(array $r) => (string) ($r['customer_name'] ?? ''),
            'to_email'    => static fn(array $r) => (string) ($r['customer_email'] ?? ''),
            'inner'       => static fn(array $r) => rma_document_inner_html($r),
            'vars'        => static fn(array $r, array $actor, ?array $dept) => document_common_vars('İade / Değişim / İptal',
                (string) ($r['reference_code'] ?? ''), (string) ($r['customer_name'] ?? ''), (string) ($r['customer_name'] ?? ''),
                (string) ($r['process_date'] ?? ''), $actor, $dept),
        ],

        'commission' => [
            'label'       => 'Prim',
            'module'      => 'commissions',
            'index'       => 'modules/commissions/index.php',
            'orientation' => 'portrait',
            'load'        => static fn(int $id) => get_commission($id),
            'no'          => static fn(array $r) => 'PRM-' . (string) ($r['id'] ?? ''),
            'party'       => static fn(array $r) => (string) ($r['personnel_name'] ?? ''),
            'to_email'    => static fn(array $r) => '',
            'inner'       => static fn(array $r) => commission_document_inner_html($r),
            'vars'        => static fn(array $c, array $actor, ?array $dept) => document_common_vars('Prim',
                'PRM-' . (string) ($c['id'] ?? ''), (string) ($c['personnel_name'] ?? ''), (string) ($c['personnel_name'] ?? ''),
                (string) ($c['created_at'] ?? ''), $actor, $dept),
        ],

        'payment' => [
            'label'       => 'Makbuz',
            'module'      => 'finance',
            'index'       => 'modules/finance/index.php',
            'orientation' => 'portrait',
            'load'        => static fn(int $id) => fin_movement_get($id),
            'no'          => static fn(array $r) => (string) ($r['receipt_no'] ?? ''),
            'party'       => static fn(array $r) => (string) ($r['customer_name'] ?? ''),
            'to_email'    => static function (array $r): string {
                $cid = (int) ($r['customer_id'] ?? 0);
                if ($cid <= 0) { return ''; }
                $c = get_customer_by_id($cid);
                return (string) ($c['email'] ?? '');
            },
            'inner'       => static fn(array $r) => payment_document_inner_html($r),
            'vars'        => static fn(array $m, array $actor, ?array $dept) => document_common_vars(
                fin_doc_type_label((string) ($m['doc_type'] ?? 'manual')), (string) ($m['receipt_no'] ?? ''),
                (string) ($m['customer_name'] ?? ''), (string) ($m['customer_name'] ?? ''),
                (string) ($m['movement_date'] ?? ''), $actor, $dept),
        ],

        'order' => [
            'label'       => 'Sipariş',
            'module'      => 'orders',
            'index'       => 'modules/orders/index.php',
            'orientation' => 'portrait',
            'load'        => static fn(int $id) => get_order($id),
            'no'          => static fn(array $r) => (string) ($r['order_no'] ?? ''),
            'party'       => static fn(array $r) => (string) ($r['customer_name'] ?? ''),
            'to_email'    => static function (array $r): string {
                $cid = (int) ($r['customer_id'] ?? 0);
                if ($cid <= 0) { return (string) ($r['email'] ?? ''); }
                $c = get_customer_by_id($cid);
                return (string) ($c['email'] ?? '');
            },
            'inner'       => static fn(array $r) => order_document_inner_html($r),
            'vars'        => static fn(array $o, array $actor, ?array $dept) => document_common_vars('Sipariş',
                (string) ($o['order_no'] ?? ''), (string) ($o['customer_name'] ?? ''), (string) ($o['customer_name'] ?? ''),
                (string) ($o['order_date'] ?? ''), $actor, $dept),
        ],

        'shipment' => [
            'label'       => 'Sevkiyat',
            'module'      => 'shipments',
            'index'       => 'modules/shipments/index.php',
            'orientation' => 'portrait',
            'load'        => static fn(int $id) => get_shipment($id),
            'no'          => static fn(array $r) => (string) ($r['shipment_no'] ?? ''),
            'party'       => static fn(array $r) => (string) ($r['customer_name'] ?? ''),
            'to_email'    => static fn(array $r) => (string) ($r['email'] ?? ''),
            'inner'       => static fn(array $r) => shipment_document_inner_html($r),
            'vars'        => static fn(array $s, array $actor, ?array $dept) => document_common_vars('Sevkiyat',
                (string) ($s['shipment_no'] ?? ''), (string) ($s['customer_name'] ?? ''), (string) ($s['customer_name'] ?? ''),
                (string) ($s['shipment_date'] ?? ''), $actor, $dept),
        ],
    ];
    return $types;
}

/** Ortak şablon değişkenleri (belgeye özel değerler eklenerek genişletilebilir). */
function document_common_vars(string $belgeTuru, string $belgeNo, string $firma, string $yetkili, string $tarih, array $actor, ?array $dept): array
{
    return [
        'firma_unvani'   => $firma,
        'musteri_adi'    => $firma,
        'yetkili_adi'    => $yetkili,
        'belge_turu'     => $belgeTuru,
        'belge_no'       => $belgeNo,
        'belge_tarihi'   => $tarih,
        'donem_baslangic'=> '',
        'donem_bitis'    => '',
        'borc'           => '',
        'alacak'         => '',
        'bakiye'         => '',
        'para_birimi'    => '',
        'personel_adi'   => $actor['full_name'] ?: $actor['username'],
        'personel_email' => $actor['email'],
        'departman'      => (string) ($dept['department_name'] ?? ''),
        'sirket_adi'     => function_exists('pub_brand_name') ? pub_brand_name() : $firma,
        'sirket_telefon' => (string) pub_setting('company_phone', ''),
        'sirket_email'   => (string) pub_setting('company_email', ''),
    ];
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
