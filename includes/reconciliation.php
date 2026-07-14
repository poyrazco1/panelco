<?php
declare(strict_types=1);

/**
 * includes/reconciliation.php — Mutabakat (cari mutabakat formu) iş mantığı.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/quotes.php'; // quote_currencies / quote_currency_symbol

/** Mutabakat sonucu. */
function recon_agreements(): array
{
    return ['pending' => 'Beklemede', 'agreed' => 'Mutabıkız', 'disagreed' => 'Mutabık Değiliz'];
}
function recon_agreement_label(string $k): string { return recon_agreements()[$k] ?? $k; }
function recon_agreement_class(string $k): string
{
    return match ($k) { 'agreed' => 'badge-success', 'disagreed' => 'badge-danger', default => 'badge-muted' };
}

/** Mutabakat türleri (§5). */
function recon_types(): array
{
    return ['ba' => 'BA Mutabakatı', 'bs' => 'BS Mutabakatı', 'cari' => 'Cari Hesap Mutabakatı', 'bakiye' => 'Bakiye Mutabakatı'];
}
function recon_type_label(string $k): string { return recon_types()[$k] ?? $k; }

function recon_generate_no(): string
{
    $year = date('Y');
    for ($i = 0; $i < 50; $i++) {
        try {
            $st = db()->prepare('SELECT COUNT(*) FROM reconciliations WHERE recon_no LIKE :p');
            $st->execute([':p' => 'MTB-' . $year . '-%']);
            $seq = (int) $st->fetchColumn() + 1 + $i;
        } catch (Throwable $e) { $seq = (int) substr((string) time(), -5) + $i; }
        $no = 'MTB-' . $year . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        try {
            $c = db()->prepare('SELECT COUNT(*) FROM reconciliations WHERE recon_no = :n');
            $c->execute([':n' => $no]);
            if ((int) $c->fetchColumn() === 0) { return $no; }
        } catch (Throwable $e) { return $no; }
    }
    return 'MTB-' . $year . '-' . substr((string) time(), -5);
}

function get_reconciliations(array $f = []): array
{
    $where = ['is_deleted = 0'];
    $params = [];
    if (!empty($f['agreement']) && isset(recon_agreements()[$f['agreement']])) { $where[] = 'agreement = :a'; $params[':a'] = $f['agreement']; }
    if (!empty($f['search'])) { $where[] = '(recon_no LIKE :q OR customer_name LIKE :q OR cari_code LIKE :q)'; $params[':q'] = '%' . $f['search'] . '%'; }
    try {
        $st = db()->prepare('SELECT * FROM reconciliations WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 500');
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('get_reconciliations: ' . $e->getMessage()); return []; }
}

function get_reconciliation(int $id): ?array
{
    if ($id <= 0) { return null; }
    try {
        $st = db()->prepare('SELECT * FROM reconciliations WHERE id = :id AND is_deleted = 0 LIMIT 1');
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { log_error('get_reconciliation: ' . $e->getMessage()); return null; }
}

function recon_fields_from_input(array $in): array
{
    $ag = (string) ($in['agreement'] ?? 'pending');
    if (!isset(recon_agreements()[$ag])) { $ag = 'pending'; }
    $cur = (string) ($in['currency'] ?? 'TRY');
    if (!isset(quote_currencies()[$cur])) { $cur = 'TRY'; }
    $debit  = (float) str_replace(',', '.', (string) ($in['debit'] ?? '0'));
    $credit = (float) str_replace(',', '.', (string) ($in['credit'] ?? '0'));
    $rtype  = (string) ($in['recon_type'] ?? 'cari');
    if (!isset(recon_types()[$rtype])) { $rtype = 'cari'; }
    return [
        'customer_id'     => (int) ($in['customer_id'] ?? 0) ?: null,
        'customer_name'   => trim((string) ($in['customer_name'] ?? '')),
        'cari_code'       => trim((string) ($in['cari_code'] ?? '')),
        'recon_type'      => $rtype,
        'period'          => trim((string) ($in['period'] ?? '')),
        'period_start'    => trim((string) ($in['period_start'] ?? '')) ?: null,
        'period_end'      => trim((string) ($in['period_end'] ?? '')) ?: null,
        'debit'           => $debit,
        'credit'          => $credit,
        'balance'         => round($debit - $credit, 2),
        'currency'        => $cur,
        'agreement'       => $ag,
        'description'     => trim((string) ($in['description'] ?? '')),
        'extra_note'      => trim((string) ($in['extra_note'] ?? '')),
        'authorized_name' => trim((string) ($in['authorized_name'] ?? '')),
        'recon_date'      => trim((string) ($in['recon_date'] ?? '')) ?: null,
    ];
}

function recon_validate(array $d): array
{
    $errors = [];
    if (($d['customer_name'] ?? '') === '') { $errors[] = 'Firma / müşteri adı zorunludur.'; }
    return $errors;
}

function recon_bind(array $d, ?string $no, ?int $userId, bool $isNew): array
{
    $p = [
        ':cid' => $d['customer_id'], ':cname' => $d['customer_name'], ':cari' => $d['cari_code'] ?: null,
        ':rtype' => $d['recon_type'] ?? 'cari',
        ':period' => $d['period'] ?: null, ':pstart' => $d['period_start'] ?? null, ':pend' => $d['period_end'] ?? null,
        ':debit' => $d['debit'], ':credit' => $d['credit'],
        ':balance' => $d['balance'], ':cur' => $d['currency'], ':ag' => $d['agreement'],
        ':desc' => $d['description'] ?: null, ':enote' => ($d['extra_note'] ?? '') ?: null,
        ':auth' => $d['authorized_name'] ?: null,
        ':approved' => (($d['agreement'] ?? 'pending') !== 'pending') ? $userId : null,
        ':rdate' => $d['recon_date'], ':uby' => $userId,
    ];
    if ($isNew) { $p[':no'] = $no; $p[':prep'] = $userId; $p[':cby'] = $userId; }
    return $p;
}

function create_reconciliation(array $d, ?int $userId): int
{
    try {
        $no = recon_generate_no();
        $st = db()->prepare(
            'INSERT INTO reconciliations
                (recon_no, customer_id, customer_name, cari_code, recon_type, period, period_start, period_end,
                 debit, credit, balance, currency, agreement, description, extra_note, authorized_name,
                 recon_date, prepared_by, approved_by, created_by, updated_by)
             VALUES
                (:no,:cid,:cname,:cari,:rtype,:period,:pstart,:pend,:debit,:credit,:balance,:cur,:ag,:desc,:enote,:auth,:rdate,:prep,:approved,:cby,:uby)'
        );
        $st->execute(recon_bind($d, $no, $userId, true));
        return (int) db()->lastInsertId();
    } catch (Throwable $e) { log_error('create_reconciliation: ' . $e->getMessage()); return 0; }
}

function update_reconciliation(int $id, array $d, ?int $userId): bool
{
    try {
        $st = db()->prepare(
            'UPDATE reconciliations SET
                customer_id=:cid, customer_name=:cname, cari_code=:cari, recon_type=:rtype, period=:period,
                period_start=:pstart, period_end=:pend, debit=:debit, credit=:credit, balance=:balance,
                currency=:cur, agreement=:ag, description=:desc, extra_note=:enote, authorized_name=:auth,
                recon_date=:rdate, approved_by=:approved, updated_by=:uby
             WHERE id=:id AND is_deleted = 0'
        );
        $params = recon_bind($d, null, $userId, false);
        $params[':id'] = $id;
        return $st->execute($params);
    } catch (Throwable $e) { log_error('update_reconciliation: ' . $e->getMessage()); return false; }
}

function delete_reconciliation(int $id, ?int $userId): bool
{
    try {
        return db()->prepare('UPDATE reconciliations SET is_deleted = 1, updated_by = :uby WHERE id = :id')
            ->execute([':uby' => $userId, ':id' => $id]);
    } catch (Throwable $e) { log_error('delete_reconciliation: ' . $e->getMessage()); return false; }
}

/** Mutabakat metni (mail/WhatsApp gövdesi). */
function recon_text_summary(array $r): string
{
    $sym = quote_currency_symbol((string) $r['currency']);
    $lines = [];
    $lines[] = 'Sayın ' . ($r['customer_name'] ?? '') . ',';
    $lines[] = '';
    $lines[] = ($r['period'] ? $r['period'] . ' dönemi ' : '') . 'cari mutabakat bilgileriniz:';
    $lines[] = 'Borç: ' . fmt_money((float) $r['debit']) . ' ' . $sym;
    $lines[] = 'Alacak: ' . fmt_money((float) $r['credit']) . ' ' . $sym;
    $lines[] = 'Bakiye: ' . fmt_money((float) $r['balance']) . ' ' . $sym;
    $lines[] = '';
    $lines[] = 'Mutabakatınızı bildirmenizi rica ederiz.';
    return implode("\n", $lines);
}
