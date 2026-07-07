<?php
declare(strict_types=1);

/**
 * includes/orders.php
 * Sipariş iş mantığı. Satırlı yapı ve toplam motoru teklif modülüyle paylaşılır
 * (quote_compute_totals / para birimi yardımcıları).
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/quotes.php'; // quote_compute_totals, quote_currencies, quote_currency_symbol

/** Sipariş durumları. */
function order_statuses(): array
{
    return [
        'draft'            => 'Taslak',
        'pending_approval' => 'Onay Bekliyor',
        'preparing'        => 'Hazırlanıyor',
        'shipped'          => 'Kargoya Verildi',
        'delivered'        => 'Teslim Edildi',
        'cancelled'        => 'İptal Edildi',
    ];
}
function order_status_label(string $k): string { return order_statuses()[$k] ?? $k; }
function order_status_class(string $k): string
{
    return match ($k) {
        'delivered' => 'badge-success',
        'cancelled' => 'badge-danger',
        'shipped'   => 'badge-info',
        'preparing', 'pending_approval' => 'badge-leave',
        default     => 'badge-muted',
    };
}

/** Ödeme durumları. */
function order_payment_statuses(): array
{
    return ['pending' => 'Bekliyor', 'partial' => 'Kısmi', 'paid' => 'Ödendi', 'refunded' => 'İade Edildi'];
}

function order_generate_no(): string
{
    $year = date('Y');
    for ($i = 0; $i < 50; $i++) {
        try {
            $st = db()->prepare('SELECT COUNT(*) FROM orders WHERE order_no LIKE :p');
            $st->execute([':p' => 'SIP-' . $year . '-%']);
            $seq = (int) $st->fetchColumn() + 1 + $i;
        } catch (Throwable $e) { $seq = (int) substr((string) time(), -5) + $i; }
        $no = 'SIP-' . $year . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        try {
            $c = db()->prepare('SELECT COUNT(*) FROM orders WHERE order_no = :n');
            $c->execute([':n' => $no]);
            if ((int) $c->fetchColumn() === 0) { return $no; }
        } catch (Throwable $e) { return $no; }
    }
    return 'SIP-' . $year . '-' . substr((string) time(), -5);
}

function get_orders(array $f = []): array
{
    $where = ['is_deleted = 0'];
    $params = [];
    if (!empty($f['status']) && isset(order_statuses()[$f['status']])) { $where[] = 'status = :s'; $params[':s'] = $f['status']; }
    if (!empty($f['search'])) { $where[] = '(order_no LIKE :q OR customer_name LIKE :q OR tracking_no LIKE :q)'; $params[':q'] = '%' . $f['search'] . '%'; }
    try {
        $st = db()->prepare('SELECT * FROM orders WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 500');
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('get_orders: ' . $e->getMessage()); return []; }
}

function get_order(int $id): ?array
{
    if ($id <= 0) { return null; }
    try {
        $st = db()->prepare('SELECT * FROM orders WHERE id = :id AND is_deleted = 0 LIMIT 1');
        $st->execute([':id' => $id]);
        $o = $st->fetch();
        if (!$o) { return null; }
        $its = db()->prepare('SELECT * FROM order_items WHERE order_id = :id ORDER BY sort ASC, id ASC');
        $its->execute([':id' => $id]);
        $o['items'] = $its->fetchAll();
        return $o;
    } catch (Throwable $e) { log_error('get_order: ' . $e->getMessage()); return null; }
}

function order_summary(): array
{
    $out = ['total' => 0, 'open' => 0, 'delivered' => 0];
    try {
        foreach (db()->query('SELECT status, COUNT(*) c FROM orders WHERE is_deleted = 0 GROUP BY status')->fetchAll() as $r) {
            $out['total'] += (int) $r['c'];
            if ((string) $r['status'] === 'delivered') { $out['delivered'] += (int) $r['c']; }
            elseif (!in_array((string) $r['status'], ['cancelled'], true)) { $out['open'] += (int) $r['c']; }
        }
    } catch (Throwable $e) { log_error('order_summary: ' . $e->getMessage()); }
    return $out;
}

function order_header_from_input(array $in): array
{
    $status = (string) ($in['status'] ?? 'draft');
    if (!isset(order_statuses()[$status])) { $status = 'draft'; }
    $pay = (string) ($in['payment_status'] ?? 'pending');
    if (!isset(order_payment_statuses()[$pay])) { $pay = 'pending'; }
    $cur = (string) ($in['currency'] ?? 'TRY');
    if (!isset(quote_currencies()[$cur])) { $cur = 'TRY'; }
    return [
        'customer_id'      => (int) ($in['customer_id'] ?? 0) ?: null,
        'customer_name'    => trim((string) ($in['customer_name'] ?? '')),
        'order_date'       => trim((string) ($in['order_date'] ?? '')) ?: null,
        'currency'         => $cur,
        'cargo_company'    => trim((string) ($in['cargo_company'] ?? '')),
        'tracking_no'      => trim((string) ($in['tracking_no'] ?? '')),
        'shipping_address' => trim((string) ($in['shipping_address'] ?? '')),
        'billing_address'  => trim((string) ($in['billing_address'] ?? '')),
        'payment_method'   => trim((string) ($in['payment_method'] ?? '')),
        'payment_status'   => $pay,
        'status'           => $status,
        'notes'            => trim((string) ($in['notes'] ?? '')),
    ];
}

function order_items_from_input(array $in): array
{
    $names = (array) ($in['item_name'] ?? []);
    $items = [];
    foreach ($names as $i => $name) {
        $name = trim((string) $name);
        $qty  = (float) str_replace(',', '.', (string) ($in['item_qty'][$i] ?? ''));
        if ($name === '' && $qty <= 0) { continue; }
        $items[] = [
            'product_code' => trim((string) ($in['item_code'][$i] ?? '')),
            'barcode'      => trim((string) ($in['item_barcode'][$i] ?? '')),
            'name'         => $name !== '' ? $name : 'Ürün',
            'brand'        => trim((string) ($in['item_brand'][$i] ?? '')),
            'qty'          => $qty > 0 ? $qty : 1,
            'unit_price'   => (float) str_replace(',', '.', (string) ($in['item_price'][$i] ?? '0')),
            'vat_rate'     => (float) str_replace(',', '.', (string) ($in['item_vat'][$i] ?? '0')),
            'discount'     => 0.0,
        ];
    }
    return $items;
}

function order_validate(array $h, array $items): array
{
    $errors = [];
    if (($h['customer_name'] ?? '') === '') { $errors[] = 'Müşteri adı zorunludur.'; }
    if (!$items) { $errors[] = 'En az bir sipariş satırı ekleyin.'; }
    return $errors;
}

function create_order(array $h, array $items, ?int $userId): int
{
    $calc = quote_compute_totals($items, 'excl', 'none', 0);
    try {
        $pdo = db();
        $pdo->beginTransaction();
        $no = order_generate_no();
        $st = $pdo->prepare(
            'INSERT INTO orders
                (order_no, customer_id, customer_name, order_date, currency, subtotal, vat_total, grand_total,
                 cargo_company, tracking_no, shipping_address, billing_address, payment_method, payment_status,
                 status, notes, created_by, updated_by)
             VALUES
                (:no,:cid,:cname,:odate,:cur,:sub,:vat,:grand,:cargo,:track,:ship,:bill,:pmethod,:pstatus,
                 :status,:notes,:cby,:uby)'
        );
        $st->execute(order_bind($h, $no, $calc, $userId, true));
        $oid = (int) $pdo->lastInsertId();
        order_insert_items($pdo, $oid, $calc['items']);
        $pdo->commit();
        return $oid;
    } catch (Throwable $e) {
        if (db()->inTransaction()) { db()->rollBack(); }
        log_error('create_order: ' . $e->getMessage());
        return 0;
    }
}

function update_order(int $id, array $h, array $items, ?int $userId): bool
{
    $calc = quote_compute_totals($items, 'excl', 'none', 0);
    try {
        $pdo = db();
        $pdo->beginTransaction();
        $st = $pdo->prepare(
            'UPDATE orders SET
                customer_id=:cid, customer_name=:cname, order_date=:odate, currency=:cur, subtotal=:sub,
                vat_total=:vat, grand_total=:grand, cargo_company=:cargo, tracking_no=:track,
                shipping_address=:ship, billing_address=:bill, payment_method=:pmethod, payment_status=:pstatus,
                status=:status, notes=:notes, updated_by=:uby
             WHERE id=:id AND is_deleted = 0'
        );
        $params = order_bind($h, null, $calc, $userId, false);
        $params[':id'] = $id;
        $st->execute($params);
        $pdo->prepare('DELETE FROM order_items WHERE order_id = :id')->execute([':id' => $id]);
        order_insert_items($pdo, $id, $calc['items']);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if (db()->inTransaction()) { db()->rollBack(); }
        log_error('update_order: ' . $e->getMessage());
        return false;
    }
}

function order_bind(array $h, ?string $no, array $calc, ?int $userId, bool $isNew): array
{
    $p = [
        ':cid' => $h['customer_id'], ':cname' => $h['customer_name'], ':odate' => $h['order_date'],
        ':cur' => $h['currency'], ':sub' => $calc['subtotal'], ':vat' => $calc['vat_total'], ':grand' => $calc['grand_total'],
        ':cargo' => $h['cargo_company'] ?: null, ':track' => $h['tracking_no'] ?: null,
        ':ship' => $h['shipping_address'] ?: null, ':bill' => $h['billing_address'] ?: null,
        ':pmethod' => $h['payment_method'] ?: null, ':pstatus' => $h['payment_status'],
        ':status' => $h['status'], ':notes' => $h['notes'] ?: null, ':uby' => $userId,
    ];
    if ($isNew) { $p[':no'] = $no; $p[':cby'] = $userId; }
    return $p;
}

function order_insert_items(PDO $pdo, int $orderId, array $items): void
{
    $st = $pdo->prepare(
        'INSERT INTO order_items (order_id, product_code, barcode, name, brand, qty, unit_price, vat_rate, line_total, sort)
         VALUES (:oid,:code,:barcode,:name,:brand,:qty,:price,:vat,:total,:sort)'
    );
    $sort = 0;
    foreach ($items as $it) {
        $st->execute([
            ':oid' => $orderId, ':code' => $it['product_code'] ?: null, ':barcode' => $it['barcode'] ?: null,
            ':name' => $it['name'], ':brand' => $it['brand'] ?: null, ':qty' => $it['qty'],
            ':price' => $it['unit_price'], ':vat' => $it['vat_rate'], ':total' => $it['line_total'] ?? 0, ':sort' => $sort++,
        ]);
    }
}

function delete_order(int $id, ?int $userId): bool
{
    try {
        return db()->prepare('UPDATE orders SET is_deleted = 1, updated_by = :uby WHERE id = :id')
            ->execute([':uby' => $userId, ':id' => $id]);
    } catch (Throwable $e) { log_error('delete_order: ' . $e->getMessage()); return false; }
}

function set_order_status(int $id, string $status, ?int $userId): bool
{
    if (!isset(order_statuses()[$status])) { return false; }
    try {
        return db()->prepare('UPDATE orders SET status = :s, updated_by = :uby WHERE id = :id AND is_deleted = 0')
            ->execute([':s' => $status, ':uby' => $userId, ':id' => $id]);
    } catch (Throwable $e) { log_error('set_order_status: ' . $e->getMessage()); return false; }
}
