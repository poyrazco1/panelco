<?php
declare(strict_types=1);

/**
 * includes/commissions.php — Personel prim hesaplama iş mantığı.
 * Bu modül AKILLI FİYAT SİHİRBAZI DEĞİLDİR; yalnızca personel primi hesaplar.
 * Görünürlük: personel yalnızca kendi primini görür; yönetici/İK/muhasebe tümünü.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/personnel.php';
require_once __DIR__ . '/quotes.php'; // quote_currencies / quote_currency_symbol

/**
 * Kullanıcı tüm personellerin primini görebilir mi?
 * commissions.edit veya personnel yetkisi olan (yönetici/İK/muhasebe) tümünü görür;
 * yalnızca commissions.view olan personel yalnızca kendi kaydını görür.
 */
function commission_can_see_all(): bool
{
    return can('commissions.edit') || can('personnel');
}

/** Oturumdaki kullanıcının bağlı olduğu personel id'si (yoksa 0). */
function commission_current_personnel_id(): int
{
    $uid = (int) (current_user_id() ?? 0);
    if ($uid <= 0) { return 0; }
    $p = get_personnel_by_user_id($uid);
    return $p ? (int) $p['id'] : 0;
}

/** Prim tutarını hesaplar: satış/kâr × oran + sabit prim, hedef oranıyla ölçekli değil (bilgi amaçlı). */
function commission_calculate(array $d): float
{
    $base = (float) ($d['profit_amount'] ?? 0) > 0 ? (float) $d['profit_amount'] : (float) ($d['sales_amount'] ?? 0);
    $rate = (float) ($d['commission_rate'] ?? 0);
    $fixed = (float) ($d['fixed_commission'] ?? 0);
    return round($base * $rate / 100 + $fixed, 2);
}

/** Hedef gerçekleşme oranı (%). */
function commission_target_ratio(array $d): float
{
    $target = (float) ($d['target_amount'] ?? 0);
    if ($target <= 0) { return 0.0; }
    return round((float) ($d['sales_amount'] ?? 0) / $target * 100, 2);
}

/** Prim listesi (filtreli + görünürlük kısıtı). */
function get_commissions(array $f = []): array
{
    $where = ['c.is_deleted = 0'];
    $params = [];

    if (!commission_can_see_all()) {
        $pid = commission_current_personnel_id();
        $where[] = 'c.personnel_id = :own';
        $params[':own'] = $pid > 0 ? $pid : -1; // bağlı personel yoksa hiçbir şey görme
    } elseif (!empty($f['personnel_id'])) {
        $where[] = 'c.personnel_id = :pid';
        $params[':pid'] = (int) $f['personnel_id'];
    }
    if (!empty($f['date_from'])) { $where[] = 'c.period_end >= :df'; $params[':df'] = $f['date_from']; }
    if (!empty($f['date_to']))   { $where[] = 'c.period_start <= :dt'; $params[':dt'] = $f['date_to']; }

    try {
        $sql = 'SELECT c.*, p.full_name AS personnel_name
                FROM commissions c LEFT JOIN personnel p ON p.id = c.personnel_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY c.id DESC LIMIT 1000';
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('get_commissions: ' . $e->getMessage()); return []; }
}

/** Tek prim (görünürlük kontrollü). */
function get_commission(int $id): ?array
{
    if ($id <= 0) { return null; }
    try {
        $st = db()->prepare('SELECT c.*, p.full_name AS personnel_name FROM commissions c LEFT JOIN personnel p ON p.id = c.personnel_id WHERE c.id = :id AND c.is_deleted = 0 LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        if (!$row) { return null; }
        if (!commission_can_see_all() && (int) $row['personnel_id'] !== commission_current_personnel_id()) {
            return null; // başka personelin primini göremez
        }
        return $row;
    } catch (Throwable $e) { log_error('get_commission: ' . $e->getMessage()); return null; }
}

function commission_fields_from_input(array $in): array
{
    $d = [
        'personnel_id'     => (int) ($in['personnel_id'] ?? 0),
        'period_start'     => trim((string) ($in['period_start'] ?? '')) ?: null,
        'period_end'       => trim((string) ($in['period_end'] ?? '')) ?: null,
        'sales_amount'     => (float) str_replace(',', '.', (string) ($in['sales_amount'] ?? '0')),
        'profit_amount'    => (float) str_replace(',', '.', (string) ($in['profit_amount'] ?? '0')),
        'commission_rate'  => (float) str_replace(',', '.', (string) ($in['commission_rate'] ?? '0')),
        'fixed_commission' => (float) str_replace(',', '.', (string) ($in['fixed_commission'] ?? '0')),
        'target_amount'    => (float) str_replace(',', '.', (string) ($in['target_amount'] ?? '0')),
        'currency'         => isset(quote_currencies()[$in['currency'] ?? 'TRY']) ? (string) $in['currency'] : 'TRY',
        'description'      => trim((string) ($in['description'] ?? '')),
    ];
    $d['target_ratio'] = commission_target_ratio($d);
    $d['calculated_commission'] = commission_calculate($d);
    return $d;
}

function commission_validate(array $d): array
{
    $errors = [];
    if ((int) ($d['personnel_id'] ?? 0) <= 0) { $errors[] = 'Personel seçiniz.'; }
    return $errors;
}

function commission_bind(array $d, ?int $userId, bool $isNew): array
{
    $p = [
        ':pid' => $d['personnel_id'], ':ps' => $d['period_start'], ':pe' => $d['period_end'],
        ':sales' => $d['sales_amount'], ':profit' => $d['profit_amount'], ':rate' => $d['commission_rate'],
        ':fixed' => $d['fixed_commission'], ':target' => $d['target_amount'], ':ratio' => $d['target_ratio'],
        ':calc' => $d['calculated_commission'], ':cur' => $d['currency'], ':desc' => $d['description'] ?: null,
        ':uby' => $userId,
    ];
    if ($isNew) { $p[':cby'] = $userId; }
    return $p;
}

function create_commission(array $d, ?int $userId): int
{
    try {
        $st = db()->prepare(
            'INSERT INTO commissions
                (personnel_id, period_start, period_end, sales_amount, profit_amount, commission_rate,
                 fixed_commission, target_amount, target_ratio, calculated_commission, currency, description,
                 created_by, updated_by)
             VALUES
                (:pid,:ps,:pe,:sales,:profit,:rate,:fixed,:target,:ratio,:calc,:cur,:desc,:cby,:uby)'
        );
        $st->execute(commission_bind($d, $userId, true));
        return (int) db()->lastInsertId();
    } catch (Throwable $e) { log_error('create_commission: ' . $e->getMessage()); return 0; }
}

function update_commission(int $id, array $d, ?int $userId): bool
{
    try {
        $st = db()->prepare(
            'UPDATE commissions SET
                personnel_id=:pid, period_start=:ps, period_end=:pe, sales_amount=:sales, profit_amount=:profit,
                commission_rate=:rate, fixed_commission=:fixed, target_amount=:target, target_ratio=:ratio,
                calculated_commission=:calc, currency=:cur, description=:desc, updated_by=:uby
             WHERE id=:id AND is_deleted = 0'
        );
        $params = commission_bind($d, $userId, false);
        $params[':id'] = $id;
        return $st->execute($params);
    } catch (Throwable $e) { log_error('update_commission: ' . $e->getMessage()); return false; }
}

function delete_commission(int $id, ?int $userId): bool
{
    try {
        return db()->prepare('UPDATE commissions SET is_deleted = 1, updated_by = :uby WHERE id = :id')
            ->execute([':uby' => $userId, ':id' => $id]);
    } catch (Throwable $e) { log_error('delete_commission: ' . $e->getMessage()); return false; }
}
