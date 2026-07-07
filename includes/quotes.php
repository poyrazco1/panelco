<?php
declare(strict_types=1);

/**
 * includes/quotes.php
 * Teklif iş mantığı: durumlar, listeleme, satırlı CRUD (transaction), toplam motoru.
 * Toplam hesabı SUNUCUDA otoritedir; JS yalnızca canlı önizleme yapar.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

/** Teklif durumları. */
function quote_statuses(): array
{
    return [
        'draft'     => 'Taslak',
        'sent'      => 'Gönderildi',
        'accepted'  => 'Kabul Edildi',
        'rejected'  => 'Reddedildi',
        'cancelled' => 'İptal Edildi',
    ];
}

function quote_status_label(string $k): string { return quote_statuses()[$k] ?? $k; }

function quote_status_class(string $k): string
{
    return match ($k) {
        'accepted'  => 'badge-success',
        'rejected', 'cancelled' => 'badge-danger',
        'sent'      => 'badge-info',
        default     => 'badge-muted',
    };
}

/** Para birimi seçenekleri. */
function quote_currencies(): array { return ['TRY' => 'TL (₺)', 'USD' => 'USD ($)', 'EUR' => 'EUR (€)']; }

function quote_currency_symbol(string $c): string
{
    return ['TRY' => '₺', 'USD' => '$', 'EUR' => '€'][$c] ?? $c;
}

/**
 * Teklif toplam motoru. Her satır: qty, unit_price, vat_rate, discount(%).
 * vat_mode: 'excl' (fiyatlar KDV hariç) | 'incl' (KDV dahil).
 * Teklif geneli iskonto: discount_type none|percent|amount, discount_value.
 *
 * @return array{items:array, subtotal:float, discount_total:float, vat_total:float, grand_total:float}
 */
function quote_compute_totals(array $items, string $vatMode, string $discType, float $discVal): array
{
    $subtotal = 0.0;  // net (KDV hariç, satır iskontolu)
    $vatSum   = 0.0;
    $out = [];

    foreach ($items as $it) {
        $qty   = (float) ($it['qty'] ?? 0);
        $price = (float) ($it['unit_price'] ?? 0);
        $rate  = (float) ($it['vat_rate'] ?? 0);
        $disc  = (float) ($it['discount'] ?? 0);
        if ($disc < 0) { $disc = 0; } if ($disc > 100) { $disc = 100; }

        $gross = $qty * $price * (1 - $disc / 100);
        if ($vatMode === 'incl') {
            $net = $rate > 0 ? $gross / (1 + $rate / 100) : $gross;
            $vat = $gross - $net;
        } else {
            $net = $gross;
            $vat = $net * $rate / 100;
        }
        $subtotal += $net;
        $vatSum   += $vat;

        $line = $it;
        $line['line_total'] = round($net, 2);
        $out[] = $line;
    }

    // Teklif geneli iskonto (net üzerinden)
    $discountTotal = 0.0;
    if ($discType === 'percent') {
        $discountTotal = $subtotal * max(0.0, min(100.0, $discVal)) / 100;
    } elseif ($discType === 'amount') {
        $discountTotal = max(0.0, min($subtotal, $discVal));
    }
    $taxable = $subtotal - $discountTotal;
    // KDV'yi genel iskonto oranında ölçekle
    if ($subtotal > 0 && $discountTotal > 0) {
        $vatSum = $vatSum * ($taxable / $subtotal);
    }
    $grand = $taxable + $vatSum;

    return [
        'items'          => $out,
        'subtotal'       => round($subtotal, 2),
        'discount_total' => round($discountTotal, 2),
        'vat_total'      => round($vatSum, 2),
        'grand_total'    => round($grand, 2),
    ];
}

/** Benzersiz teklif numarası (TKF-YYYY-####). */
function quote_generate_no(): string
{
    $year = date('Y');
    for ($i = 0; $i < 50; $i++) {
        try {
            $st = db()->prepare('SELECT COUNT(*) FROM quotes WHERE quote_no LIKE :p');
            $st->execute([':p' => 'TKF-' . $year . '-%']);
            $seq = (int) $st->fetchColumn() + 1 + $i;
        } catch (Throwable $e) {
            $seq = (int) (substr((string) time(), -5)) + $i;
        }
        $no = 'TKF-' . $year . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        try {
            $c = db()->prepare('SELECT COUNT(*) FROM quotes WHERE quote_no = :n');
            $c->execute([':n' => $no]);
            if ((int) $c->fetchColumn() === 0) { return $no; }
        } catch (Throwable $e) { return $no; }
    }
    return 'TKF-' . $year . '-' . substr((string) time(), -5);
}

/** Teklif listesi (filtreli). */
function get_quotes(array $f = []): array
{
    $where = ['q.is_deleted = 0'];
    $params = [];
    if (!empty($f['status']) && isset(quote_statuses()[$f['status']])) { $where[] = 'q.status = :status'; $params[':status'] = $f['status']; }
    if (!empty($f['date_from'])) { $where[] = 'q.quote_date >= :df'; $params[':df'] = $f['date_from']; }
    if (!empty($f['date_to']))   { $where[] = 'q.quote_date <= :dt'; $params[':dt'] = $f['date_to']; }
    if (!empty($f['search']))    { $where[] = '(q.quote_no LIKE :q OR q.customer_name LIKE :q)'; $params[':q'] = '%' . $f['search'] . '%'; }
    try {
        $sql = 'SELECT q.* FROM quotes q WHERE ' . implode(' AND ', $where) . ' ORDER BY q.id DESC LIMIT 500';
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('get_quotes: ' . $e->getMessage()); return []; }
}

/** Tek teklif + satırları. */
function get_quote(int $id): ?array
{
    if ($id <= 0) { return null; }
    try {
        $st = db()->prepare('SELECT * FROM quotes WHERE id = :id AND is_deleted = 0 LIMIT 1');
        $st->execute([':id' => $id]);
        $q = $st->fetch();
        if (!$q) { return null; }
        $its = db()->prepare('SELECT * FROM quote_items WHERE quote_id = :id ORDER BY sort ASC, id ASC');
        $its->execute([':id' => $id]);
        $q['items'] = $its->fetchAll();
        return $q;
    } catch (Throwable $e) { log_error('get_quote: ' . $e->getMessage()); return null; }
}

/** Teklif özet sayıları. */
function quote_summary(): array
{
    $out = ['total' => 0, 'draft' => 0, 'sent' => 0, 'accepted' => 0];
    try {
        foreach (db()->query('SELECT status, COUNT(*) c FROM quotes WHERE is_deleted = 0 GROUP BY status')->fetchAll() as $r) {
            $out['total'] += (int) $r['c'];
            if (isset($out[(string) $r['status']])) { $out[(string) $r['status']] = (int) $r['c']; }
        }
    } catch (Throwable $e) { log_error('quote_summary: ' . $e->getMessage()); }
    return $out;
}

/** POST'tan teklif başlık alanları. */
function quote_header_from_input(array $in): array
{
    $status = (string) ($in['status'] ?? 'draft');
    if (!isset(quote_statuses()[$status])) { $status = 'draft'; }
    $cur = (string) ($in['currency'] ?? 'TRY');
    if (!isset(quote_currencies()[$cur])) { $cur = 'TRY'; }
    $vatMode = ($in['vat_mode'] ?? 'excl') === 'incl' ? 'incl' : 'excl';
    $discType = in_array(($in['discount_type'] ?? 'none'), ['none', 'percent', 'amount'], true) ? (string) $in['discount_type'] : 'none';
    return [
        'customer_id'    => (int) ($in['customer_id'] ?? 0) ?: null,
        'customer_name'  => trim((string) ($in['customer_name'] ?? '')),
        'contact_name'   => trim((string) ($in['contact_name'] ?? '')),
        'phone'          => trim((string) ($in['phone'] ?? '')),
        'email'          => trim((string) ($in['email'] ?? '')),
        'quote_date'     => trim((string) ($in['quote_date'] ?? '')) ?: null,
        'valid_until'    => trim((string) ($in['valid_until'] ?? '')) ?: null,
        'currency'       => $cur,
        'vat_mode'       => $vatMode,
        'discount_type'  => $discType,
        'discount_value' => (float) str_replace(',', '.', (string) ($in['discount_value'] ?? '0')),
        'notes'          => trim((string) ($in['notes'] ?? '')),
        'terms'          => trim((string) ($in['terms'] ?? '')),
        'status'         => $status,
    ];
}

/** POST dizilerinden satırları normalize eder (boş satırları atar). */
function quote_items_from_input(array $in): array
{
    $names = (array) ($in['item_name'] ?? []);
    $items = [];
    foreach ($names as $i => $name) {
        $name = trim((string) $name);
        $qty  = (float) str_replace(',', '.', (string) (($in['item_qty'][$i] ?? '')));
        if ($name === '' && $qty <= 0) { continue; }
        $items[] = [
            'product_code' => trim((string) ($in['item_code'][$i] ?? '')),
            'barcode'      => trim((string) ($in['item_barcode'][$i] ?? '')),
            'name'         => $name !== '' ? $name : 'Ürün',
            'brand'        => trim((string) ($in['item_brand'][$i] ?? '')),
            'description'  => trim((string) ($in['item_desc'][$i] ?? '')),
            'qty'          => $qty > 0 ? $qty : 1,
            'unit_price'   => (float) str_replace(',', '.', (string) ($in['item_price'][$i] ?? '0')),
            'vat_rate'     => (float) str_replace(',', '.', (string) ($in['item_vat'][$i] ?? '0')),
            'discount'     => (float) str_replace(',', '.', (string) ($in['item_disc'][$i] ?? '0')),
        ];
    }
    return $items;
}

function quote_validate(array $h, array $items): array
{
    $errors = [];
    if (($h['customer_name'] ?? '') === '') { $errors[] = 'Müşteri adı zorunludur.'; }
    if (($h['email'] ?? '') !== '' && !is_valid_email($h['email'])) { $errors[] = 'Geçerli bir e-posta girin.'; }
    if (!$items) { $errors[] = 'En az bir teklif satırı ekleyin.'; }
    return $errors;
}

/** Teklif kaydeder (yeni). Satırlarla birlikte transaction içinde. Id döndürür. */
function create_quote(array $h, array $items, ?int $userId): int
{
    $calc = quote_compute_totals($items, $h['vat_mode'], $h['discount_type'], $h['discount_value']);
    try {
        $pdo = db();
        $pdo->beginTransaction();
        $no = quote_generate_no();
        $st = $pdo->prepare(
            'INSERT INTO quotes
                (quote_no, customer_id, customer_name, contact_name, phone, email, quote_date, valid_until,
                 currency, vat_mode, discount_type, discount_value, subtotal, discount_total, vat_total,
                 grand_total, notes, terms, status, prepared_by, created_by, updated_by)
             VALUES
                (:no,:cid,:cname,:contact,:phone,:email,:qdate,:valid,:cur,:vmode,:dtype,:dval,
                 :sub,:disc,:vat,:grand,:notes,:terms,:status,:prep,:cby,:uby)'
        );
        $st->execute(quote_header_bind($h, $no, $calc, $userId, true));
        $qid = (int) $pdo->lastInsertId();
        quote_insert_items($pdo, $qid, $calc['items']);
        $pdo->commit();
        return $qid;
    } catch (Throwable $e) {
        if (db()->inTransaction()) { db()->rollBack(); }
        log_error('create_quote: ' . $e->getMessage());
        return 0;
    }
}

/** Teklif günceller (başlık + satırları yeniden yaz). */
function update_quote(int $id, array $h, array $items, ?int $userId): bool
{
    $calc = quote_compute_totals($items, $h['vat_mode'], $h['discount_type'], $h['discount_value']);
    try {
        $pdo = db();
        $pdo->beginTransaction();
        $st = $pdo->prepare(
            'UPDATE quotes SET
                customer_id=:cid, customer_name=:cname, contact_name=:contact, phone=:phone, email=:email,
                quote_date=:qdate, valid_until=:valid, currency=:cur, vat_mode=:vmode, discount_type=:dtype,
                discount_value=:dval, subtotal=:sub, discount_total=:disc, vat_total=:vat, grand_total=:grand,
                notes=:notes, terms=:terms, status=:status, updated_by=:uby
             WHERE id=:id AND is_deleted = 0'
        );
        $params = quote_header_bind($h, null, $calc, $userId, false);
        $params[':id'] = $id;
        $st->execute($params);
        $pdo->prepare('DELETE FROM quote_items WHERE quote_id = :id')->execute([':id' => $id]);
        quote_insert_items($pdo, $id, $calc['items']);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if (db()->inTransaction()) { db()->rollBack(); }
        log_error('update_quote: ' . $e->getMessage());
        return false;
    }
}

function quote_header_bind(array $h, ?string $no, array $calc, ?int $userId, bool $isNew): array
{
    $p = [
        ':cid' => $h['customer_id'], ':cname' => $h['customer_name'], ':contact' => $h['contact_name'] ?: null,
        ':phone' => $h['phone'] ?: null, ':email' => $h['email'] ?: null,
        ':qdate' => $h['quote_date'], ':valid' => $h['valid_until'], ':cur' => $h['currency'],
        ':vmode' => $h['vat_mode'], ':dtype' => $h['discount_type'], ':dval' => $h['discount_value'],
        ':sub' => $calc['subtotal'], ':disc' => $calc['discount_total'], ':vat' => $calc['vat_total'],
        ':grand' => $calc['grand_total'], ':notes' => $h['notes'] ?: null, ':terms' => $h['terms'] ?: null,
        ':status' => $h['status'], ':uby' => $userId,
    ];
    if ($isNew) { $p[':no'] = $no; $p[':prep'] = $userId; $p[':cby'] = $userId; }
    return $p;
}

function quote_insert_items(PDO $pdo, int $quoteId, array $items): void
{
    $st = $pdo->prepare(
        'INSERT INTO quote_items (quote_id, product_code, barcode, name, brand, description, qty, unit_price, vat_rate, discount, line_total, sort)
         VALUES (:qid,:code,:barcode,:name,:brand,:desc,:qty,:price,:vat,:disc,:total,:sort)'
    );
    $sort = 0;
    foreach ($items as $it) {
        $st->execute([
            ':qid' => $quoteId, ':code' => $it['product_code'] ?: null, ':barcode' => $it['barcode'] ?: null,
            ':name' => $it['name'], ':brand' => $it['brand'] ?: null, ':desc' => $it['description'] ?: null,
            ':qty' => $it['qty'], ':price' => $it['unit_price'], ':vat' => $it['vat_rate'],
            ':disc' => $it['discount'], ':total' => $it['line_total'] ?? 0, ':sort' => $sort++,
        ]);
    }
}

function delete_quote(int $id, ?int $userId): bool
{
    try {
        return db()->prepare('UPDATE quotes SET is_deleted = 1, updated_by = :uby WHERE id = :id')
            ->execute([':uby' => $userId, ':id' => $id]);
    } catch (Throwable $e) { log_error('delete_quote: ' . $e->getMessage()); return false; }
}

function set_quote_status(int $id, string $status, ?int $userId): bool
{
    if (!isset(quote_statuses()[$status])) { return false; }
    try {
        return db()->prepare('UPDATE quotes SET status = :s, updated_by = :uby WHERE id = :id AND is_deleted = 0')
            ->execute([':s' => $status, ':uby' => $userId, ':id' => $id]);
    } catch (Throwable $e) { log_error('set_quote_status: ' . $e->getMessage()); return false; }
}

/** Teklif metni (WhatsApp / mail gövdesi için sade özet). */
function quote_text_summary(array $q): string
{
    $sym = quote_currency_symbol((string) $q['currency']);
    $lines = [];
    $lines[] = 'Sayın ' . ($q['customer_name'] ?? '') . ',';
    $lines[] = '';
    $lines[] = $q['quote_no'] . ' numaralı teklifimiz:';
    foreach (($q['items'] ?? []) as $it) {
        $lines[] = '• ' . $it['name'] . ' x' . rtrim(rtrim((string) $it['qty'], '0'), '.') . ' = ' . fmt_money((float) $it['line_total']) . ' ' . $sym;
    }
    $lines[] = '';
    $lines[] = 'Genel Toplam: ' . fmt_money((float) $q['grand_total']) . ' ' . $sym;
    if (!empty($q['valid_until'])) { $lines[] = 'Geçerlilik: ' . $q['valid_until']; }
    return implode("\n", $lines);
}
