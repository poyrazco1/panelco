<?php
declare(strict_types=1);

/**
 * includes/finance.php
 * Cari hareketler (tahsilat/ödeme/borç/alacak) ve cari ekstre iş mantığı.
 * Bakiye = Σ(borç) − Σ(alacak). Pozitif bakiye = müşterinin firmaya borcu.
 * Tüm sorgular prepared; hatalar loglanır, fatal atılmaz.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/quotes.php';    // quote_currency_symbol / fmt_money (helpers)

/** Belge/hareket türleri. */
function fin_doc_types(): array
{
    return [
        'collection' => 'Tahsilat',
        'payment'    => 'Ödeme',
        'invoice'    => 'Fatura / Satış',
        'manual'     => 'Manuel Kayıt',
    ];
}
function fin_doc_type_label(string $k): string { return fin_doc_types()[$k] ?? $k; }

/** Ödeme yöntemleri. */
function fin_methods(): array
{
    return ['nakit' => 'Nakit', 'havale' => 'Havale / EFT', 'kredi_karti' => 'Kredi Kartı', 'cek' => 'Çek', 'senet' => 'Senet', 'diger' => 'Diğer'];
}

/** Makbuz numarası üretir (THS/ODE/FAT/MAN-YYYY-000N). */
function fin_generate_receipt_no(string $docType): string
{
    $prefix = match ($docType) { 'collection' => 'THS', 'payment' => 'ODE', 'invoice' => 'FAT', default => 'MAN' };
    $year = date('Y');
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM cari_movements WHERE receipt_no LIKE :p');
        $st->execute([':p' => $prefix . '-' . $year . '-%']);
        $seq = (int) $st->fetchColumn() + 1;
    } catch (Throwable $e) { $seq = (int) substr((string) time(), -4); }
    return $prefix . '-' . $year . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

/** Hareket listesi (filtre: customer_id, search, from, to). */
function fin_movements(array $f = []): array
{
    $where = ['is_deleted = 0']; $p = [];
    if (!empty($f['customer_id'])) { $where[] = 'customer_id = :cid'; $p[':cid'] = (int) $f['customer_id']; }
    if (!empty($f['search']))      { $where[] = '(customer_name LIKE :q OR receipt_no LIKE :q OR description LIKE :q)'; $p[':q'] = '%' . $f['search'] . '%'; }
    if (!empty($f['from']))        { $where[] = 'movement_date >= :from'; $p[':from'] = $f['from']; }
    if (!empty($f['to']))          { $where[] = 'movement_date <= :to'; $p[':to'] = $f['to']; }
    try {
        $st = db()->prepare('SELECT * FROM cari_movements WHERE ' . implode(' AND ', $where) . ' ORDER BY movement_date DESC, id DESC LIMIT 1000');
        $st->execute($p);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('fin_movements: ' . $e->getMessage()); return []; }
}

function fin_movement_get(int $id): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM cari_movements WHERE id = ? AND is_deleted = 0 LIMIT 1');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { log_error('fin_movement_get: ' . $e->getMessage()); return null; }
}

/** Hareket kaydeder (ekle/güncelle). @return array{ok:bool,id:int,errors:array} */
function fin_movement_save(array $in, ?int $userId): array
{
    $errors = [];
    $id       = (int) ($in['id'] ?? 0);
    $custId   = (int) ($in['customer_id'] ?? 0) ?: null;
    $custName = trim((string) ($in['customer_name'] ?? ''));
    $date     = trim((string) ($in['movement_date'] ?? ''));
    $docType  = (string) ($in['doc_type'] ?? 'manual');
    $dir      = ($in['direction'] ?? 'debit') === 'credit' ? 'credit' : 'debit';
    $amount   = (float) str_replace(',', '.', (string) ($in['amount'] ?? '0'));
    $currency = trim((string) ($in['currency'] ?? 'TRY')) ?: 'TRY';
    $method   = trim((string) ($in['method'] ?? ''));
    $ref      = trim((string) ($in['reference'] ?? ''));
    $desc     = trim((string) ($in['description'] ?? ''));

    if (!isset(fin_doc_types()[$docType])) { $docType = 'manual'; }
    // Tahsilat = alacak (credit), Ödeme/Fatura = borç (debit) varsayılanı; yine de $dir öncelikli.
    if ($custName === '') { $errors[] = 'Müşteri/cari adı zorunludur.'; }
    if ($amount <= 0)     { $errors[] = 'Tutar 0’dan büyük olmalıdır.'; }
    if ($date === '')     { $date = date('Y-m-d'); }
    if ($errors) { return ['ok' => false, 'id' => $id, 'errors' => $errors]; }

    try {
        if ($id > 0) {
            db()->prepare(
                'UPDATE cari_movements SET customer_id=:cid, customer_name=:cn, movement_date=:dt, doc_type=:dtp,
                    direction=:dir, amount=:amt, currency=:cur, method=:m, reference=:ref, description=:desc WHERE id=:id'
            )->execute([':cid'=>$custId, ':cn'=>$custName, ':dt'=>$date, ':dtp'=>$docType, ':dir'=>$dir, ':amt'=>$amount,
                        ':cur'=>$currency, ':m'=>$method, ':ref'=>$ref, ':desc'=>$desc, ':id'=>$id]);
        } else {
            $receipt = fin_generate_receipt_no($docType);
            db()->prepare(
                'INSERT INTO cari_movements (customer_id, customer_name, movement_date, doc_type, direction, amount,
                    currency, method, reference, receipt_no, description, created_by)
                 VALUES (:cid,:cn,:dt,:dtp,:dir,:amt,:cur,:m,:ref,:rno,:desc,:cb)'
            )->execute([':cid'=>$custId, ':cn'=>$custName, ':dt'=>$date, ':dtp'=>$docType, ':dir'=>$dir, ':amt'=>$amount,
                        ':cur'=>$currency, ':m'=>$method, ':ref'=>$ref, ':rno'=>$receipt, ':desc'=>$desc, ':cb'=>$userId]);
            $id = (int) db()->lastInsertId();
        }
        return ['ok' => true, 'id' => $id, 'errors' => []];
    } catch (Throwable $e) {
        log_error('fin_movement_save: ' . $e->getMessage());
        return ['ok' => false, 'id' => $id, 'errors' => ['Kaydetme sırasında bir sorun oluştu.']];
    }
}

function fin_movement_delete(int $id): bool
{
    try {
        db()->prepare('UPDATE cari_movements SET is_deleted = 1 WHERE id = ?')->execute([$id]);
        return true;
    } catch (Throwable $e) { log_error('fin_movement_delete: ' . $e->getMessage()); return false; }
}

/** Bir hareketin borç/alacak tutarları. */
function fin_debit(array $m): float  { return $m['direction'] === 'debit'  ? (float) $m['amount'] : 0.0; }
function fin_credit(array $m): float { return $m['direction'] === 'credit' ? (float) $m['amount'] : 0.0; }

/**
 * Cari ekstre: açılış bakiyesi + dönem hareketleri (yürüyen bakiye) + toplamlar.
 * @return array{opening:float, rows:array, total_debit:float, total_credit:float, closing:float}
 */
function fin_statement(int $customerId, string $from = '', string $to = ''): array
{
    $opening = 0.0; $rows = []; $td = 0.0; $tc = 0.0;
    try {
        if ($from !== '') {
            $st = db()->prepare("SELECT COALESCE(SUM(CASE WHEN direction='debit' THEN amount ELSE -amount END),0)
                                 FROM cari_movements WHERE is_deleted=0 AND customer_id=? AND movement_date < ?");
            $st->execute([$customerId, $from]);
            $opening = (float) $st->fetchColumn();
        }
        $where = ['is_deleted=0', 'customer_id=:cid']; $p = [':cid'=>$customerId];
        if ($from !== '') { $where[] = 'movement_date >= :from'; $p[':from'] = $from; }
        if ($to !== '')   { $where[] = 'movement_date <= :to';   $p[':to'] = $to; }
        $st = db()->prepare('SELECT * FROM cari_movements WHERE ' . implode(' AND ', $where) . ' ORDER BY movement_date ASC, id ASC');
        $st->execute($p);
        $running = $opening;
        foreach ($st->fetchAll() as $m) {
            $d = fin_debit($m); $c = fin_credit($m);
            $running += $d - $c; $td += $d; $tc += $c;
            $m['_debit'] = $d; $m['_credit'] = $c; $m['_balance'] = $running;
            $rows[] = $m;
        }
        return ['opening'=>$opening, 'rows'=>$rows, 'total_debit'=>$td, 'total_credit'=>$tc, 'closing'=>$running];
    } catch (Throwable $e) {
        log_error('fin_statement: ' . $e->getMessage());
        return ['opening'=>0.0, 'rows'=>[], 'total_debit'=>0.0, 'total_credit'=>0.0, 'closing'=>0.0];
    }
}
