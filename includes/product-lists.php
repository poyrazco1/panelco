<?php
declare(strict_types=1);

/**
 * includes/product-lists.php
 * Ürün Listeleri (Satış Listesi / Talep Listesi) iş mantığı.
 *  - Liste no benzersiz: PL-2026-000001.
 *  - Kalemler (SKU/ad/marka/miktar/birim/fiyat) + alıcılar (gönderim kaydı).
 *  - PDF/CSV/yazdır/mail/WhatsApp metni çıktıları için düz-metin üretimi.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/leave.php';

function pl_types(): array { return ['sale' => 'Satış Listesi', 'request' => 'Talep Listesi']; }
function pl_type_label(string $k): string { return pl_types()[$k] ?? $k; }

function pl_next_no(): string
{
    $year = (int) date('Y');
    $prefix = 'PL-' . $year . '-';
    try {
        $st = db()->prepare('SELECT list_no FROM product_lists WHERE list_no LIKE :p ORDER BY id DESC LIMIT 1');
        $st->execute([':p' => $prefix . '%']);
        $last = (string) ($st->fetchColumn() ?: '');
        $seq = ($last !== '' && preg_match('/(\d+)$/', $last, $m)) ? (int) $m[1] : 0;
        for ($i = 0; $i < 50; $i++) {
            $seq++;
            $cand = $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
            $c = db()->prepare('SELECT COUNT(*) FROM product_lists WHERE list_no = :n'); $c->execute([':n' => $cand]);
            if ((int) $c->fetchColumn() === 0) { return $cand; }
        }
    } catch (Throwable $e) { log_error('pl_next_no: ' . $e->getMessage()); }
    return $prefix . str_pad((string) (time() % 1000000), 6, '0', STR_PAD_LEFT);
}

function pl_list(array $f = []): array
{
    $where = ['is_deleted = 0']; $params = [];
    if (!empty($f['type']) && isset(pl_types()[$f['type']])) { $where[] = 'list_type = :t'; $params[':t'] = $f['type']; }
    if (!empty($f['search'])) { $where[] = '(title LIKE :q OR list_no LIKE :q)'; $params[':q'] = '%' . $f['search'] . '%'; }
    try {
        $st = db()->prepare('SELECT * FROM product_lists WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 500');
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('pl_list: ' . $e->getMessage()); return []; }
}

function pl_get(int $id): ?array
{
    if ($id <= 0) { return null; }
    try {
        $st = db()->prepare('SELECT * FROM product_lists WHERE id = :id AND is_deleted = 0 LIMIT 1');
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { log_error('pl_get: ' . $e->getMessage()); return null; }
}

function pl_items(int $listId): array
{
    try {
        $st = db()->prepare('SELECT * FROM product_list_items WHERE list_id = :l ORDER BY sort_order ASC, id ASC');
        $st->execute([':l' => $listId]);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('pl_items: ' . $e->getMessage()); return []; }
}

function pl_recipients(int $listId): array
{
    try {
        $st = db()->prepare('SELECT r.*, c.company_name, c.record_no FROM product_list_recipients r
            LEFT JOIN international_customers c ON c.id = r.customer_id WHERE r.list_id = :l ORDER BY r.id DESC');
        $st->execute([':l' => $listId]);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('pl_recipients: ' . $e->getMessage()); return []; }
}

function pl_fields_from_input(array $in): array
{
    $type = (string) ($in['list_type'] ?? 'sale');
    if (!isset(pl_types()[$type])) { $type = 'sale'; }
    return [
        'title'       => trim((string) ($in['title'] ?? '')),
        'list_type'   => $type,
        'currency'    => trim((string) ($in['currency'] ?? '')),
        'valid_until' => trim((string) ($in['valid_until'] ?? '')) ?: null,
        'intro'       => trim((string) ($in['intro'] ?? '')),
        'notes'       => trim((string) ($in['notes'] ?? '')),
    ];
}

function pl_create(array $d, ?int $userId): int
{
    try {
        $no = pl_next_no();
        $st = db()->prepare('INSERT INTO product_lists (list_no, title, list_type, currency, valid_until, intro, notes, created_by, updated_by)
            VALUES (:no,:t,:ty,:cur,:vu,:intro,:notes,:cby,:uby)');
        $st->execute([
            ':no' => $no, ':t' => $d['title'], ':ty' => $d['list_type'], ':cur' => $d['currency'] ?: null,
            ':vu' => $d['valid_until'], ':intro' => $d['intro'] ?: null, ':notes' => $d['notes'] ?: null,
            ':cby' => $userId, ':uby' => $userId,
        ]);
        return (int) db()->lastInsertId();
    } catch (Throwable $e) { log_error('pl_create: ' . $e->getMessage()); return 0; }
}

function pl_update(int $id, array $d, ?int $userId): bool
{
    try {
        return db()->prepare('UPDATE product_lists SET title=:t, list_type=:ty, currency=:cur, valid_until=:vu, intro=:intro, notes=:notes, updated_by=:uby WHERE id=:id AND is_deleted = 0')
            ->execute([':t' => $d['title'], ':ty' => $d['list_type'], ':cur' => $d['currency'] ?: null, ':vu' => $d['valid_until'],
                       ':intro' => $d['intro'] ?: null, ':notes' => $d['notes'] ?: null, ':uby' => $userId, ':id' => $id]);
    } catch (Throwable $e) { log_error('pl_update: ' . $e->getMessage()); return false; }
}

function pl_delete(int $id, ?int $userId): bool
{
    try {
        return db()->prepare('UPDATE product_lists SET is_deleted = 1, updated_by = :uby WHERE id = :id')->execute([':uby' => $userId, ':id' => $id]);
    } catch (Throwable $e) { log_error('pl_delete: ' . $e->getMessage()); return false; }
}

/** Kalemleri tümden değiştir (form dizisinden). */
function pl_save_items(int $listId, array $items): void
{
    try {
        db()->prepare('DELETE FROM product_list_items WHERE list_id = :l')->execute([':l' => $listId]);
        $st = db()->prepare('INSERT INTO product_list_items (list_id, sku, name, brand, qty, unit, price, currency, note, sort_order)
            VALUES (:l,:sku,:n,:b,:q,:u,:p,:cur,:note,:so)');
        $so = 0;
        foreach ($items as $it) {
            $name = trim((string) ($it['name'] ?? ''));
            if ($name === '') { continue; }
            $so++;
            $st->execute([
                ':l' => $listId, ':sku' => trim((string) ($it['sku'] ?? '')) ?: null, ':n' => $name,
                ':b' => trim((string) ($it['brand'] ?? '')) ?: null,
                ':q' => ($it['qty'] ?? '') !== '' ? (float) $it['qty'] : null,
                ':u' => trim((string) ($it['unit'] ?? '')) ?: null,
                ':p' => ($it['price'] ?? '') !== '' ? (float) $it['price'] : null,
                ':cur' => trim((string) ($it['currency'] ?? '')) ?: null,
                ':note' => trim((string) ($it['note'] ?? '')) ?: null, ':so' => $so,
            ]);
        }
    } catch (Throwable $e) { log_error('pl_save_items: ' . $e->getMessage()); }
}

/** POST dizilerinden kalem listesi kur. */
function pl_items_from_post(array $in): array
{
    $names = (array) ($in['item_name'] ?? []);
    $out = [];
    foreach ($names as $i => $n) {
        $out[] = [
            'name'     => (string) $n,
            'sku'      => (string) (($in['item_sku'] ?? [])[$i] ?? ''),
            'brand'    => (string) (($in['item_brand'] ?? [])[$i] ?? ''),
            'qty'      => (string) (($in['item_qty'] ?? [])[$i] ?? ''),
            'unit'     => (string) (($in['item_unit'] ?? [])[$i] ?? ''),
            'price'    => (string) (($in['item_price'] ?? [])[$i] ?? ''),
            'currency' => (string) (($in['item_currency'] ?? [])[$i] ?? ''),
            'note'     => (string) (($in['item_note'] ?? [])[$i] ?? ''),
        ];
    }
    return $out;
}

/** Listeyi düz metne çevir (WhatsApp/mail gövdesi için). */
function pl_to_text(array $list, array $items): string
{
    $lines = [];
    $lines[] = strtoupper((string) $list['title']);
    if (!empty($list['intro'])) { $lines[] = (string) $list['intro']; $lines[] = ''; }
    foreach ($items as $i => $it) {
        $parts = [];
        $parts[] = ($i + 1) . '. ' . (string) $it['name'];
        if (!empty($it['brand'])) { $parts[] = '(' . $it['brand'] . ')'; }
        if ($it['qty'] !== null && $it['qty'] !== '') { $parts[] = rtrim(rtrim((string) $it['qty'], '0'), '.') . ' ' . (string) ($it['unit'] ?? ''); }
        if ($it['price'] !== null && $it['price'] !== '') { $parts[] = '- ' . rtrim(rtrim((string) $it['price'], '0'), '.') . ' ' . (string) ($it['currency'] ?: $list['currency'] ?? ''); }
        $lines[] = trim(implode(' ', $parts));
    }
    if (!empty($list['valid_until'])) { $lines[] = ''; $lines[] = 'Geçerlilik: ' . (string) $list['valid_until']; }
    return implode("\n", $lines);
}

/** Alıcı gönderim kaydı ekle. */
function pl_add_recipient(int $listId, ?int $customerId, string $channel, string $status, ?int $userId): void
{
    try {
        db()->prepare('INSERT INTO product_list_recipients (list_id, customer_id, channel, status, created_by) VALUES (:l,:c,:ch,:st,:by)')
            ->execute([':l' => $listId, ':c' => $customerId ?: null, ':ch' => $channel, ':st' => $status, ':by' => $userId]);
    } catch (Throwable $e) { log_error('pl_add_recipient: ' . $e->getMessage()); }
}

/** Seçim için hafif liste (mesaj ekleme). */
function pl_for_select(): array
{
    try {
        return db()->query('SELECT id, list_no, title, list_type FROM product_lists WHERE is_deleted = 0 ORDER BY id DESC LIMIT 300')->fetchAll();
    } catch (Throwable $e) { log_error('pl_for_select: ' . $e->getMessage()); return []; }
}
