<?php
declare(strict_types=1);

/**
 * includes/reports.php — Rapor tanımları ve çalıştırıcı.
 * Her rapor: yetki + tarih/durum filtresi + kolonlar. Modül ve export.php paylaşır.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

/**
 * Rapor tanımları.
 * key => [label, perm, table, date_col, status_col, status_labels(callable|null),
 *         columns => [başlık => alan], currency_col(optional)]
 */
function report_definitions(): array
{
    $quoteStatus = function_exists('quote_statuses') ? quote_statuses() : [];
    return [
        'customers' => [
            'label' => 'Müşteri Raporu', 'perm' => 'customers.view',
            'table' => 'customers', 'date_col' => 'created_at', 'status_col' => null, 'status_labels' => null,
            'columns' => ['Firma' => 'company_name', 'Yetkili' => 'contact_name', 'Telefon' => 'phone', 'E-posta' => 'email', 'Şehir' => 'city', 'Tip' => 'customer_type', 'Kayıt' => 'created_at'],
        ],
        'quotes' => [
            'label' => 'Teklif Raporu', 'perm' => 'quotes.view',
            'table' => 'quotes', 'date_col' => 'quote_date', 'status_col' => 'status', 'status_labels' => 'quote_status_label',
            'columns' => ['Teklif No' => 'quote_no', 'Müşteri' => 'customer_name', 'Tarih' => 'quote_date', 'Tutar' => 'grand_total', 'Durum' => 'status'],
        ],
        'orders' => [
            'label' => 'Sipariş Raporu', 'perm' => 'orders.view',
            'table' => 'orders', 'date_col' => 'order_date', 'status_col' => 'status', 'status_labels' => 'order_status_label',
            'columns' => ['Sipariş No' => 'order_no', 'Müşteri' => 'customer_name', 'Tarih' => 'order_date', 'Tutar' => 'grand_total', 'Durum' => 'status'],
        ],
        'service' => [
            'label' => 'Servis Raporu', 'perm' => 'service.view',
            'table' => 'service_records', 'date_col' => 'created_at', 'status_col' => 'status', 'status_labels' => 'service_status_label',
            'columns' => ['Referans' => 'reference_code', 'Müşteri' => 'customer_name', 'Durum' => 'status', 'Tarih' => 'created_at'],
        ],
        'rma' => [
            'label' => 'İade / Değişim Raporu', 'perm' => 'rma.view',
            'table' => 'rma_records', 'date_col' => 'created_at', 'status_col' => 'status', 'status_labels' => 'rma_status_label',
            'columns' => ['Referans' => 'reference_code', 'Müşteri' => 'customer_name', 'Durum' => 'status', 'Tarih' => 'created_at'],
        ],
        'suppliers' => [
            'label' => 'Tedarikçi Raporu', 'perm' => 'suppliers.view',
            'table' => 'suppliers', 'date_col' => 'created_at', 'status_col' => null, 'status_labels' => null,
            'columns' => ['Firma' => 'company_name', 'Yetkili' => 'contact_name', 'Telefon' => 'phone', 'Ürün Grupları' => 'product_groups', 'Para' => 'currency'],
        ],
        'inventory' => [
            'label' => 'Envanter Raporu', 'perm' => 'inventory.view',
            'table' => 'inventory_items', 'date_col' => 'created_at', 'status_col' => 'status', 'status_labels' => 'inventory_status_label',
            'columns' => ['Ad' => 'name', 'Kategori' => 'category', 'Marka' => 'brand', 'Seri No' => 'serial_no', 'Lokasyon' => 'location', 'Durum' => 'status'],
        ],
    ];
}

/** Erişilebilir rapor tanımları (yetkiye göre). */
function reports_available(): array
{
    $out = [];
    foreach (report_definitions() as $key => $def) {
        if (can($def['perm'])) { $out[$key] = $def; }
    }
    return $out;
}

/** Bir raporu çalıştırır; ['columns'=>..., 'rows'=>...] döndürür. */
function report_run(string $key, array $f): array
{
    $defs = report_definitions();
    if (!isset($defs[$key]) || !can($defs[$key]['perm'])) { return ['columns' => [], 'rows' => []]; }
    $def = $defs[$key];

    $where = ['is_deleted = 0'];
    $params = [];
    if (!empty($f['date_from']) && $def['date_col']) { $where[] = $def['date_col'] . ' >= :df'; $params[':df'] = $f['date_from'] . ' 00:00:00'; }
    if (!empty($f['date_to']) && $def['date_col'])   { $where[] = $def['date_col'] . ' <= :dt'; $params[':dt'] = $f['date_to'] . ' 23:59:59'; }
    if (!empty($f['status']) && $def['status_col'])  { $where[] = $def['status_col'] . ' = :st'; $params[':st'] = $f['status']; }

    $fields = array_values($def['columns']);
    // is_deleted olmayan tablolar için güvenlik: hepsinde is_deleted var (bizim tablolar).
    try {
        $sql = 'SELECT ' . implode(', ', array_map(static fn($c) => '`' . $c . '`', $fields))
             . ' FROM `' . $def['table'] . '` WHERE ' . implode(' AND ', $where)
             . ' ORDER BY ' . ($def['date_col'] ? '`' . $def['date_col'] . '`' : $fields[0]) . ' DESC LIMIT 2000';
        $st = db()->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
    } catch (Throwable $e) {
        log_error('report_run(' . $key . '): ' . $e->getMessage());
        $rows = [];
    }

    return ['columns' => $def['columns'], 'rows' => $rows, 'def' => $def];
}

/** Kolon değerini biçimlendirir (durum etiketi vb.). */
function report_format(string $field, $value, array $def): string
{
    $value = (string) ($value ?? '');
    if ($def['status_col'] && $field === $def['status_col'] && $def['status_labels'] && function_exists($def['status_labels'])) {
        return ($def['status_labels'])($value);
    }
    if (in_array($field, ['grand_total', 'subtotal'], true) && $value !== '') {
        return fmt_money((float) $value);
    }
    return $value;
}
