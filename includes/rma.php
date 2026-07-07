<?php
declare(strict_types=1);

/**
 * includes/rma.php
 * İade-Değişim Yönetimi (RMA / satış sonrası süreç) iş mantığı.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

/** Süreç durumları (anahtar => etiket), akış sırasına göre. */
function rma_statuses(): array
{
    return [
        'requested'         => 'Talep Alındı',
        'reviewing'         => 'İnceleme Bekliyor',
        'awaiting_product'  => 'Müşteriden Ürün Bekleniyor',
        'product_received'  => 'Ürün Teslim Alındı',
        'inspecting'        => 'Ürün Kontrol Ediliyor',
        'awaiting_supplier' => 'Tedarikçi Bekleniyor',
        'awaiting_exchange' => 'Değişim Ürünü Bekleniyor',
        'to_ship'           => 'Müşteriye Gönderilecek',
        'shipped'           => 'Müşteriye Gönderildi',
        'refund_approved'   => 'İade Onaylandı',
        'refund_rejected'   => 'İade Reddedildi',
        'cancel_done'       => 'İptal Tamamlandı',
        'exchange_done'     => 'Değişim Tamamlandı',
        'closed'            => 'Süreç Kapandı',
    ];
}

function rma_status_label(string $key): string
{
    return rma_statuses()[$key] ?? $key;
}

/** Kapanmış sayılan durumlar (özet kartları için). */
function rma_closed_statuses(): array
{
    return ['refund_approved', 'refund_rejected', 'cancel_done', 'exchange_done', 'closed'];
}

function rma_status_class(string $key): string
{
    if (in_array($key, ['refund_rejected'], true)) { return 'badge-danger'; }
    if (in_array($key, ['refund_approved', 'cancel_done', 'exchange_done', 'shipped'], true)) { return 'badge-success'; }
    if ($key === 'closed') { return 'badge-muted'; }
    if (in_array($key, ['awaiting_product', 'awaiting_supplier', 'awaiting_exchange', 'to_ship'], true)) { return 'badge-leave'; }
    return 'badge-info';
}

/** İşlem tipleri. */
function rma_process_types(): array
{
    return ['iade' => 'İade', 'degisim' => 'Değişim', 'iptal' => 'İptal'];
}

/** Varsayılan platformlar (ileride Ayarlar > Platformlar'a taşınabilir). */
function rma_platforms(): array
{
    return ['T-Soft', 'Trendyol', 'Hepsiburada', 'N11', 'Amazon', 'Pazarama',
            'Çiçeksepeti', 'PttAVM', 'WhatsApp', 'Telefon', 'Mağaza', 'Diğer'];
}

/** Kargo firması seçenekleri (Kargo Yöntemleri modülünden). */
function rma_cargo_options(): array
{
    if (!function_exists('shipping_all')) {
        $sh = __DIR__ . '/shipping.php';
        if (is_file($sh)) { require_once $sh; }
    }
    $out = [];
    if (function_exists('shipping_all')) {
        try {
            foreach (shipping_all('', 'active') as $m) {
                $out[] = (string) $m['name'];
            }
        } catch (Throwable $e) {
            log_error('rma_cargo_options: ' . $e->getMessage());
        }
    }
    return $out;
}

/** Bilinen kargolar için takip linki üretir; manuel URL öncelikli. */
function rma_cargo_link(?string $carrier, ?string $trackingNo, ?string $manualUrl): ?string
{
    if ($manualUrl !== null && trim($manualUrl) !== '') {
        return trim($manualUrl);
    }
    $no = trim((string) $trackingNo);
    if ($no === '') { return null; }
    $c = mb_strtolower(trim((string) $carrier), 'UTF-8');
    $enc = rawurlencode($no);
    if ($c === '') { return null; }
    if (strpos($c, 'yurtiçi') !== false || strpos($c, 'yurtici') !== false) {
        return 'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code=' . $enc;
    }
    if (strpos($c, 'aras') !== false) {
        return 'https://kargotakip.araskargo.com.tr/mainpage.aspx?code=' . $enc;
    }
    if (strpos($c, 'mng') !== false) {
        return 'https://kargotakip.mngkargo.com.tr/?takipNo=' . $enc;
    }
    if (strpos($c, 'ptt') !== false) {
        return 'https://gonderitakip.ptt.gov.tr/Track/Verify?q=' . $enc;
    }
    if (strpos($c, 'sürat') !== false || strpos($c, 'surat') !== false) {
        return 'https://www.suratkargo.com.tr/KargoTakip/?kargotakipno=' . $enc;
    }
    if (strpos($c, 'ups') !== false) {
        return 'https://www.ups.com/track?tracknum=' . $enc;
    }
    return null;
}

/** WhatsApp linki (wa.me). */
function rma_whatsapp_link(?string $phone, string $message): ?string
{
    if (!$phone) { return null; }
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits === '') { return null; }
    if (strlen($digits) === 11 && $digits[0] === '0') {
        $digits = '90' . substr($digits, 1);
    } elseif (strlen($digits) === 10) {
        $digits = '90' . $digits;
    }
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
}

/** Hazır WhatsApp mesajları. */
function rma_wa_messages(array $rec): array
{
    $model = (string) ($rec['product_model'] ?? '');
    $sentNo = (string) ($rec['sent_cargo_tracking_no'] ?? '');
    return [
        'awaiting' => "Merhaba, iade/değişim süreciniz için ürünü tarafımıza göndermeniz beklenmektedir.\nÜrün: $model\nPoyraz Toner",
        'received' => "Merhaba, $model ürününüz tarafımıza ulaşmıştır. Kontrol süreci başlatılmıştır.\nPoyraz Toner",
        'sent'     => "Merhaba, değişim ürününüz kargoya verilmiştir.\nKargo Takip No: $sentNo\nPoyraz Toner",
        'closed'   => "Merhaba, $model ürününüz için iade/değişim süreciniz tamamlanmıştır.\nPoyraz Toner",
    ];
}

/* ----------------------- Sorgular ----------------------- */

/**
 * Liste (filtreli). $f: date_from, date_to, customer, phone, platform, type,
 * supplier, status, tracking, model.
 */
function get_rma_records(array $f = []): array
{
    $sql = 'SELECT r.*,
                (SELECT COUNT(*) FROM rma_received_items ri WHERE ri.rma_record_id = r.id) AS received_count,
                (SELECT COUNT(*) FROM rma_sent_items si WHERE si.rma_record_id = r.id) AS sent_count,
                (SELECT COALESCE(SUM(ri.quantity),0) FROM rma_received_items ri WHERE ri.rma_record_id = r.id) AS received_qty,
                (SELECT COALESCE(SUM(si.quantity),0) FROM rma_sent_items si WHERE si.rma_record_id = r.id) AS sent_qty,
                (SELECT GROUP_CONCAT(NULLIF(CONCAT_WS(" ", ri.product_name, ri.product_model), "") SEPARATOR " | ")
                   FROM rma_received_items ri WHERE ri.rma_record_id = r.id) AS received_names,
                (SELECT GROUP_CONCAT(NULLIF(si.cargo_tracking_no, "") SEPARATOR " | ")
                   FROM rma_sent_items si WHERE si.rma_record_id = r.id) AS sent_tracks,
                (SELECT GROUP_CONCAT(NULLIF(ri.cargo_tracking_no, "") SEPARATOR " | ")
                   FROM rma_received_items ri WHERE ri.rma_record_id = r.id) AS received_tracks,
                (SELECT GROUP_CONCAT(NULLIF(CONCAT_WS(" ", si.product_name, si.product_model), "") SEPARATOR " | ")
                   FROM rma_sent_items si WHERE si.rma_record_id = r.id) AS sent_names
            FROM rma_records r WHERE 1=1 AND r.is_deleted = 0';
    $p = [];
    if (!empty($f['date_from'])) { $sql .= ' AND r.process_date >= :df'; $p[':df'] = $f['date_from']; }
    if (!empty($f['date_to'])) { $sql .= ' AND r.process_date <= :dt'; $p[':dt'] = $f['date_to']; }
    if (!empty($f['customer'])) { $sql .= ' AND r.customer_name LIKE :cn'; $p[':cn'] = '%' . $f['customer'] . '%'; }
    if (!empty($f['phone'])) { $sql .= ' AND r.customer_phone LIKE :ph'; $p[':ph'] = '%' . $f['phone'] . '%'; }
    if (!empty($f['platform'])) { $sql .= ' AND r.platform = :pl'; $p[':pl'] = $f['platform']; }
    if (!empty($f['type']) && array_key_exists($f['type'], rma_process_types())) { $sql .= ' AND r.process_type = :ty'; $p[':ty'] = $f['type']; }
    if (!empty($f['supplier'])) { $sql .= ' AND r.supplier LIKE :su'; $p[':su'] = '%' . $f['supplier'] . '%'; }
    if (!empty($f['status']) && array_key_exists($f['status'], rma_statuses())) { $sql .= ' AND r.status = :st'; $p[':st'] = $f['status']; }
    if (!empty($f['tracking'])) {
        $sql .= ' AND (EXISTS(SELECT 1 FROM rma_received_items ri WHERE ri.rma_record_id = r.id AND ri.cargo_tracking_no LIKE :t1)
                       OR EXISTS(SELECT 1 FROM rma_sent_items si WHERE si.rma_record_id = r.id AND si.cargo_tracking_no LIKE :t2))';
        $p[':t1'] = '%' . $f['tracking'] . '%'; $p[':t2'] = '%' . $f['tracking'] . '%';
    }
    if (!empty($f['model'])) {
        $sql .= ' AND (r.product_model LIKE :md
                       OR EXISTS(SELECT 1 FROM rma_received_items ri WHERE ri.rma_record_id = r.id AND (ri.product_model LIKE :md2 OR ri.product_name LIKE :md3))
                       OR EXISTS(SELECT 1 FROM rma_sent_items si WHERE si.rma_record_id = r.id AND (si.product_model LIKE :md4 OR si.product_name LIKE :md5)))';
        $like = '%' . $f['model'] . '%';
        foreach (['md', 'md2', 'md3', 'md4', 'md5'] as $k) { $p[':' . $k] = $like; }
    }

    // Genel arama (search)
    if (!empty($f['search'])) {
        $sql .= ' AND (r.customer_name LIKE :s1 OR r.customer_phone LIKE :s2 OR r.reference_code LIKE :s3
                       OR r.supplier LIKE :s4 OR r.description LIKE :s5
                       OR EXISTS(SELECT 1 FROM rma_received_items ri WHERE ri.rma_record_id = r.id AND (ri.product_name LIKE :s6 OR ri.product_model LIKE :s7 OR ri.cargo_tracking_no LIKE :s8))
                       OR EXISTS(SELECT 1 FROM rma_sent_items si WHERE si.rma_record_id = r.id AND (si.product_name LIKE :s9 OR si.product_model LIKE :s10 OR si.cargo_tracking_no LIKE :s11)))';
        $like = '%' . $f['search'] . '%';
        foreach (['s1','s2','s3','s4','s5','s6','s7','s8','s9','s10','s11'] as $k) { $p[':' . $k] = $like; }
    }

    $sql .= ' ORDER BY r.id DESC';
    try {
        $st = db()->prepare($sql);
        $st->execute($p);
        return $st->fetchAll();
    } catch (Throwable $e) {
        log_error('get_rma_records: ' . $e->getMessage());
        return [];
    }
}

function get_rma_record(int $id): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM rma_records WHERE id = :id AND is_deleted = 0 LIMIT 1');
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) {
        log_error('get_rma_record: ' . $e->getMessage());
        return null;
    }
}

function rma_log_status(int $recordId, ?string $old, string $new, ?string $note = null): void
{
    try {
        db()->prepare(
            'INSERT INTO rma_status_history (rma_record_id, old_status, new_status, note, changed_by_user_id)
             VALUES (:rid, :o, :n, :note, :uid)'
        )->execute([
            ':rid' => $recordId, ':o' => $old, ':n' => $new, ':note' => $note,
            ':uid' => function_exists('current_user_id') ? current_user_id() : null,
        ]);
    } catch (Throwable $e) {
        log_error('rma_log_status: ' . $e->getMessage());
    }
}

function get_rma_history(int $recordId): array
{
    try {
        $st = db()->prepare(
            'SELECT h.*, u.username AS by_username
             FROM rma_status_history h
             LEFT JOIN users u ON u.id = h.changed_by_user_id
             WHERE h.rma_record_id = :rid ORDER BY h.id DESC'
        );
        $st->execute([':rid' => $recordId]);
        return $st->fetchAll();
    } catch (Throwable $e) {
        log_error('get_rma_history: ' . $e->getMessage());
        return [];
    }
}

/** Özet kartları: toplam, açık, kapanan, toplam zarar, tip sayıları. */
function rma_summary(): array
{
    $out = ['total' => 0, 'open' => 0, 'closed' => 0, 'loss' => 0.0, 'iade' => 0, 'degisim' => 0, 'iptal' => 0];
    try {
        $out['total'] = (int) db()->query('SELECT COUNT(*) FROM rma_records')->fetchColumn();
        $out['loss']  = (float) db()->query('SELECT COALESCE(SUM(loss_amount),0) FROM rma_records')->fetchColumn();

        $closed = rma_closed_statuses();
        $in = implode(',', array_fill(0, count($closed), '?'));
        $stc = db()->prepare("SELECT COUNT(*) FROM rma_records WHERE status IN ($in)");
        $stc->execute($closed);
        $out['closed'] = (int) $stc->fetchColumn();
        $out['open'] = $out['total'] - $out['closed'];

        foreach (db()->query('SELECT process_type, COUNT(*) c FROM rma_records GROUP BY process_type')->fetchAll() as $r) {
            $t = (string) $r['process_type'];
            if (isset($out[$t])) { $out[$t] = (int) $r['c']; }
        }
    } catch (Throwable $e) {
        log_error('rma_summary: ' . $e->getMessage());
    }
    return $out;
}

/* ----------------------- Yaz/Güncelle ----------------------- */

/** $data'dan alanları normalize eder + doğrular. */
function rma_normalize(array $d): array
{
    $errors = [];

    $name = trim((string) ($d['customer_name'] ?? ''));
    if ($name === '') { $errors[] = 'Firma / Ad Soyad boş olamaz.'; }

    $type = (string) ($d['process_type'] ?? 'iade');
    if (!array_key_exists($type, rma_process_types())) { $type = 'iade'; }

    $lossRaw = trim((string) ($d['loss_amount'] ?? ''));
    $loss = $lossRaw === '' ? 0.0 : (float) str_replace(',', '.', $lossRaw);
    if ($loss < 0) { $errors[] = 'Zarar negatif olamaz.'; $loss = 0.0; }

    $platform = trim((string) ($d['platform'] ?? ''));

    $email = trim((string) ($d['customer_email'] ?? ''));
    if ($email !== '' && !is_valid_email($email)) { $errors[] = 'Geçerli bir e-posta girin.'; }

    $reason = trim((string) ($d['reason_type'] ?? ''));

    $status = (string) ($d['status'] ?? 'requested');
    if (!array_key_exists($status, rma_statuses())) { $status = 'requested'; }

    $dfmt = static function ($v): ?string {
        $v = trim((string) $v);
        return ($v !== '' && strtotime($v)) ? date('Y-m-d', (int) strtotime($v)) : null;
    };

    $fields = [
        'process_date'         => $dfmt($d['process_date'] ?? '') ?? date('Y-m-d'),
        'customer_name'        => $name,
        'customer_phone'       => (trim((string) ($d['customer_phone'] ?? '')) !== '' ? trim((string) $d['customer_phone']) : null),
        'customer_email'       => ($email !== '' ? $email : null),
        'invoice_date'         => $dfmt($d['invoice_date'] ?? ''),
        'platform'             => ($platform !== '' ? $platform : null),
        'product_model'        => (trim((string) ($d['product_model'] ?? '')) !== '' ? trim((string) $d['product_model']) : null),
        'quantity'             => max(1, (int) ($d['quantity'] ?? 1)),
        'process_type'         => $type,
        'reason_type'          => ($reason !== '' ? $reason : null),
        'description'          => (trim((string) ($d['description'] ?? '')) !== '' ? trim((string) $d['description']) : null),
        'supplier'             => (trim((string) ($d['supplier'] ?? '')) !== '' ? trim((string) $d['supplier']) : null),
        'loss_amount'          => round($loss, 2),
        'internal_note'        => (trim((string) ($d['internal_note'] ?? '')) !== '' ? trim((string) $d['internal_note']) : null),
        'customer_public_note' => (trim((string) ($d['customer_public_note'] ?? '')) !== '' ? trim((string) $d['customer_public_note']) : null),
        'status'               => $status,
    ];

    return ['errors' => $errors, 'fields' => $fields];
}

/** Yeni kayıt. Dönen: yeni id. */
function create_rma_record(array $d): int
{
    $prep = rma_normalize($d);
    if ($prep['errors']) { throw new RuntimeException(implode(' ', $prep['errors'])); }
    $f = $prep['fields'];
    $f['reference_code'] = rma_generate_reference();
    $f['public_token'] = rma_make_token();
    $f['public_tracking_enabled'] = isset($d['public_tracking_enabled']) ? (!empty($d['public_tracking_enabled']) ? 1 : 0) : 1;
    $f['created_by_user_id'] = function_exists('current_user_id') ? current_user_id() : null;
    $f['updated_by_user_id'] = $f['created_by_user_id'];
    try {
        $cols = array_keys($f);
        $ph = array_map(fn ($c) => ':' . $c, $cols);
        $bind = [];
        foreach ($f as $k => $v) { $bind[':' . $k] = $v; }
        db()->prepare('INSERT INTO rma_records (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')')->execute($bind);
        $id = (int) db()->lastInsertId();
        rma_save_items($id, (array) ($d['ritem'] ?? []), 'received');
        rma_save_items($id, (array) ($d['sitem'] ?? []), 'sent');
        rma_log_status($id, null, $f['status'], 'Kayıt oluşturuldu');
        return $id;
    } catch (Throwable $e) {
        log_error('create_rma_record: ' . $e->getMessage());
        throw new RuntimeException('Kayıt oluşturulamadı.');
    }
}

/** Kaydı günceller (durum değişimini tarihçeye yazar + ürün satırlarını değiştirir). */
function update_rma_record(int $id, array $d): void
{
    $current = get_rma_record($id);
    if (!$current) { throw new RuntimeException('Kayıt bulunamadı.'); }
    $prep = rma_normalize($d);
    if ($prep['errors']) { throw new RuntimeException(implode(' ', $prep['errors'])); }
    $f = $prep['fields'];
    if (isset($d['public_tracking_enabled'])) {
        $f['public_tracking_enabled'] = !empty($d['public_tracking_enabled']) ? 1 : 0;
    }
    $f['updated_by_user_id'] = function_exists('current_user_id') ? current_user_id() : null;
    try {
        $sets = [];
        $bind = [':id' => $id];
        foreach ($f as $k => $v) { $sets[] = "$k = :$k"; $bind[':' . $k] = $v; }
        db()->prepare('UPDATE rma_records SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($bind);
        // ürün satırlarını yeniden yaz (form gönderdiyse)
        if (array_key_exists('ritem', $d)) { rma_save_items($id, (array) $d['ritem'], 'received'); }
        if (array_key_exists('sitem', $d)) { rma_save_items($id, (array) $d['sitem'], 'sent'); }
        if ($current['status'] !== $f['status']) {
            rma_log_status($id, $current['status'], $f['status'], 'Kayıt güncellendi');
        }
    } catch (Throwable $e) {
        log_error('update_rma_record: ' . $e->getMessage());
        throw new RuntimeException('Kayıt güncellenemedi.');
    }
}

/** Sadece durum değiştirir + tarihçe. */
function rma_change_status(int $id, string $newStatus, ?string $note = null): void
{
    $rec = get_rma_record($id);
    if (!$rec) { throw new RuntimeException('Kayıt bulunamadı.'); }
    if (!array_key_exists($newStatus, rma_statuses())) { throw new InvalidArgumentException('Geçersiz durum.'); }
    try {
        db()->prepare('UPDATE rma_records SET status = :s, updated_by_user_id = :uid WHERE id = :id')
            ->execute([':s' => $newStatus, ':uid' => function_exists('current_user_id') ? current_user_id() : null, ':id' => $id]);
        rma_log_status($id, $rec['status'], $newStatus, $note);
    } catch (Throwable $e) {
        log_error('rma_change_status: ' . $e->getMessage());
        throw new RuntimeException('Durum güncellenemedi.');
    }
}

/**
 * RMA kaydını ARŞİVLER (soft delete). Ürün satırları / durum geçmişi korunur;
 * kayıt listeden ve public takipten kalkar. Transaction + denetim logu.
 */
function delete_rma_record(int $id, ?int $userId = null): void
{
    if ($id <= 0) { throw new RuntimeException('Geçersiz kayıt.'); }
    $rec = get_rma_record($id); // yalnızca is_deleted=0
    if (!$rec) { throw new RuntimeException('Kayıt bulunamadı veya zaten arşivlenmiş.'); }
    $ref = (string) ($rec['reference_code'] ?? '');
    $uid = $userId ?? (function_exists('current_user_id') ? current_user_id() : null);

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare(
            'UPDATE rma_records
                SET is_deleted = 1, deleted_at = NOW(), deleted_by_user_id = :uid
              WHERE id = :id AND is_deleted = 0'
        );
        $st->execute([':uid' => $uid, ':id' => $id]);
        if ($st->rowCount() < 1) {
            $pdo->rollBack();
            throw new RuntimeException('Kayıt güncellenemedi.');
        }
        $pdo->commit();
        log_activity('rma_record_delete_success', 'rma', $id, $ref, 'success');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        log_error('delete_rma_record: ' . $e->getMessage());
        log_activity('rma_record_delete_failed', 'rma', $id, $ref, 'failed', $e->getMessage());
        throw new RuntimeException('Kayıt silinemedi.');
    }
}

/** Aynı kayıt var mı? (telefon + ürün modeli + işlem tarihi) */
function rma_duplicate_exists(?string $phone, ?string $model, ?string $processDate): bool
{
    try {
        $st = db()->prepare(
            'SELECT COUNT(*) FROM rma_records
             WHERE IFNULL(customer_phone,\'\') = :ph
               AND IFNULL(product_model,\'\') = :md
               AND IFNULL(process_date,\'\') = :pd'
        );
        $st->execute([
            ':ph' => (string) ($phone ?? ''),
            ':md' => (string) ($model ?? ''),
            ':pd' => (string) ($processDate ?? ''),
        ]);
        return (int) $st->fetchColumn() > 0;
    } catch (Throwable $e) {
        log_error('rma_duplicate_exists: ' . $e->getMessage());
        return false;
    }
}

/* ----------------------- CSV Aktarma ----------------------- */

/** Dışa/İçe aktarma başlıkları (mevcut tablo başlıklarıyla aynı sırada). */
function rma_csv_headers(): array
{
    return [
        'İŞLEM TARİHİ', 'FİRMA / AD-SOYAD', 'TELEFON NUMARASI', 'FATURA TARİHİ', 'PLATFORM',
        'ÜRÜN MODELİ', 'ADET', 'İADE / DEĞİŞİM / İPTAL', 'SEBEP', 'AÇIKLAMA', 'TEDARİKÇİ', 'ZARAR',
        'MÜŞTERİDEN ALINAN ÜRÜN', 'MÜŞTERİDEN ALINAN ÜRÜN KARGO TAKİP NUMARASI',
        'MÜŞTERİYE GÖNDERİLEN ÜRÜN', 'MÜŞTERİYE GÖNDERİLEN ÜRÜN KARGO TAKİP NUMARASI', 'DURUM',
    ];
}

/** Bir kaydı CSV satırına çevirir (başlık sırasıyla). */
function rma_record_to_row(array $r): array
{
    $types = rma_process_types();
    $recvNames = (string) ($r['received_names'] ?? '');
    $sentNames = (string) ($r['sent_names'] ?? '');
    $model = (string) ($r['product_model'] ?? '');
    if ($model === '') { $model = $recvNames; }
    return [
        (string) ($r['process_date'] ?? ''),
        (string) ($r['customer_name'] ?? ''),
        (string) ($r['customer_phone'] ?? ''),
        (string) ($r['invoice_date'] ?? ''),
        (string) ($r['platform'] ?? ''),
        $model,
        (string) ($r['quantity'] ?? ''),
        (string) ($types[$r['process_type']] ?? $r['process_type'] ?? ''),
        (string) ($r['reason_type'] ?? ''),
        (string) ($r['description'] ?? ''),
        (string) ($r['supplier'] ?? ''),
        number_format((float) ($r['loss_amount'] ?? 0), 2, '.', ''),
        $recvNames,
        (string) ($r['received_tracks'] ?? ''),
        $sentNames,
        (string) ($r['sent_tracks'] ?? ''),
        (string) rma_status_label((string) ($r['status'] ?? '')),
    ];
}

/** Türkçe işlem tipi etiketini enum'a çevirir. */
function rma_type_from_label(string $label): string
{
    $l = mb_strtolower(trim($label), 'UTF-8');
    if (strpos($l, 'değişim') !== false || strpos($l, 'degisim') !== false) { return 'degisim'; }
    if (strpos($l, 'iptal') !== false) { return 'iptal'; }
    return 'iade';
}

/**
 * CSV satır dizisini (başlık => değer) kayıt verisine çevirir.
 * $map: başlık indeksleri.
 */
function rma_row_to_data(array $row, array $headerIndex): array
{
    $get = static function (string $key) use ($row, $headerIndex) {
        $i = $headerIndex[$key] ?? null;
        return ($i !== null && isset($row[$i])) ? trim((string) $row[$i]) : '';
    };
    $recvName = $get('MÜŞTERİDEN ALINAN ÜRÜN');
    $recvTrk  = $get('MÜŞTERİDEN ALINAN ÜRÜN KARGO TAKİP NUMARASI');
    $sentName = $get('MÜŞTERİYE GÖNDERİLEN ÜRÜN');
    $sentTrk  = $get('MÜŞTERİYE GÖNDERİLEN ÜRÜN KARGO TAKİP NUMARASI');
    $model    = $get('ÜRÜN MODELİ');
    $qty      = $get('ADET') !== '' ? $get('ADET') : '1';

    $data = [
        'process_date'  => $get('İŞLEM TARİHİ'),
        'customer_name' => $get('FİRMA / AD-SOYAD'),
        'customer_phone'=> $get('TELEFON NUMARASI'),
        'invoice_date'  => $get('FATURA TARİHİ'),
        'platform'      => $get('PLATFORM'),
        'product_model' => $model,
        'quantity'      => $qty,
        'process_type'  => rma_type_from_label($get('İADE / DEĞİŞİM / İPTAL')),
        'reason_type'   => $get('SEBEP'),
        'description'   => $get('AÇIKLAMA'),
        'supplier'      => $get('TEDARİKÇİ'),
        'loss_amount'   => $get('ZARAR'),
    ];
    // Tekil CSV sütunlarını çoklu-ürün alt satırlarına çevir
    if ($recvName !== '' || $recvTrk !== '') {
        $data['ritem'] = [[
            'product_name' => $recvName !== '' ? $recvName : $model,
            'product_model' => $model,
            'quantity' => $qty,
            'cargo_tracking_no' => $recvTrk,
        ]];
    }
    if ($sentName !== '' || $sentTrk !== '') {
        $data['sitem'] = [[
            'product_name' => $sentName,
            'quantity' => '1',
            'cargo_tracking_no' => $sentTrk,
        ]];
    }
    return $data;
}

/* ----------------------- Sebep / Referans / Token ----------------------- */

/** Süreç sebebi seçenekleri. */
function rma_reason_types(): array
{
    return [
        'Yanlış Ürün Gönderimi', 'Eksik Ürün Gönderimi', 'Hasarlı Ürün', 'Arızalı Ürün',
        'Müşteri Cayma Hakkı', 'Müşteri Yanlış Sipariş Verdi', 'Tedarikçi Hatası',
        'Platform Kaynaklı Problem', 'Kargo Hasarı', 'Ürün Uyumsuzluğu',
        'Stok / Tedarik Problemi', 'Fatura / Sipariş Hatası', 'Diğer',
    ];
}

/** Benzersiz referans kodu: RMA-YYYYMMDD-0001 */
function rma_generate_reference(): string
{
    $date = date('Ymd');
    try {
        $st = db()->prepare("SELECT reference_code FROM rma_records WHERE reference_code LIKE :p ORDER BY id DESC LIMIT 1");
        $st->execute([':p' => 'RMA-' . $date . '-%']);
        $last = (string) ($st->fetchColumn() ?: '');
        $next = 1;
        if ($last !== '' && preg_match('/(\d+)$/', $last, $m)) { $next = (int) $m[1] + 1; }
        for ($i = 0; $i < 50; $i++) {
            $code = sprintf('RMA-%s-%04d', $date, $next);
            $c = db()->prepare('SELECT COUNT(*) FROM rma_records WHERE reference_code = :c');
            $c->execute([':c' => $code]);
            if ((int) $c->fetchColumn() === 0) { return $code; }
            $next++;
        }
    } catch (Throwable $e) {
        log_error('rma_generate_reference: ' . $e->getMessage());
    }
    return sprintf('RMA-%s-%04d', $date, (int) (microtime(true) * 1000) % 10000);
}

/** Güvenli public token (64 hex). */
function rma_make_token(): string
{
    try {
        return bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        return hash('sha256', uniqid('rma', true) . mt_rand());
    }
}

/* ----------------------- Ürün Satırları ----------------------- */

function get_rma_received_items(int $recordId): array
{
    try {
        $st = db()->prepare('SELECT * FROM rma_received_items WHERE rma_record_id = :id ORDER BY id ASC');
        $st->execute([':id' => $recordId]);
        return $st->fetchAll();
    } catch (Throwable $e) {
        log_error('get_rma_received_items: ' . $e->getMessage());
        return [];
    }
}

function get_rma_sent_items(int $recordId): array
{
    try {
        $st = db()->prepare('SELECT * FROM rma_sent_items WHERE rma_record_id = :id ORDER BY id ASC');
        $st->execute([':id' => $recordId]);
        return $st->fetchAll();
    } catch (Throwable $e) {
        log_error('get_rma_sent_items: ' . $e->getMessage());
        return [];
    }
}

/**
 * Ürün satırlarını (received|sent) yeniden yazar: önce siler, sonra dolu satırları ekler.
 * $rows: [['product_name'=>..,'quantity'=>..,'cargo_tracking_no'=>..,...], ...]
 */
function rma_save_items(int $recordId, array $rows, string $which): void
{
    $table = $which === 'sent' ? 'rma_sent_items' : 'rma_received_items';
    $dfmt = static function ($v): ?string {
        $v = trim((string) $v);
        return ($v !== '' && strtotime($v)) ? date('Y-m-d', (int) strtotime($v)) : null;
    };
    try {
        db()->prepare("DELETE FROM $table WHERE rma_record_id = :id")->execute([':id' => $recordId]);

        foreach ($rows as $row) {
            if (!is_array($row)) { continue; }
            $name    = trim((string) ($row['product_name'] ?? ''));
            $model   = trim((string) ($row['product_model'] ?? ''));
            $trackNo = trim((string) ($row['cargo_tracking_no'] ?? ''));
            $serial  = trim((string) ($row['serial_no'] ?? ''));
            // tamamen boş satırları atla
            if ($name === '' && $model === '' && $trackNo === '' && $serial === '') { continue; }

            $carrier = trim((string) ($row['cargo_company'] ?? ''));
            $brandId = (int) ($row['brand_id'] ?? 0);
            $common = [
                'rma_record_id'      => $recordId,
                'product_name'       => ($name !== '' ? $name : null),
                'product_model'      => ($model !== '' ? $model : null),
                'brand_id'           => $brandId > 0 ? $brandId : null,
                'brand_name'         => (trim((string) ($row['brand_name'] ?? '')) !== '' ? trim((string) $row['brand_name']) : null),
                'quantity'           => max(1, (int) ($row['quantity'] ?? 1)),
                'serial_no'          => ($serial !== '' ? $serial : null),
                'cargo_company'      => ($carrier !== '' ? $carrier : null),
                'cargo_tracking_no'  => ($trackNo !== '' ? $trackNo : null),
                'cargo_tracking_url' => rma_cargo_link($carrier, $trackNo, (string) ($row['cargo_tracking_url'] ?? '')),
                'note'               => (trim((string) ($row['note'] ?? '')) !== '' ? trim((string) $row['note']) : null),
            ];
            if ($which === 'sent') {
                $common['sent_at']      = $dfmt($row['sent_at'] ?? '');
                $common['delivered_at'] = $dfmt($row['delivered_at'] ?? '');
                $common['sent_status']  = (trim((string) ($row['sent_status'] ?? '')) !== '' ? trim((string) $row['sent_status']) : null);
            } else {
                $common['condition_note']  = (trim((string) ($row['condition_note'] ?? '')) !== '' ? trim((string) $row['condition_note']) : null);
                $common['received_status'] = (trim((string) ($row['received_status'] ?? '')) !== '' ? trim((string) $row['received_status']) : null);
                $common['received_at']     = $dfmt($row['received_at'] ?? '');
            }
            $cols = array_keys($common);
            $ph = array_map(fn ($c) => ':' . $c, $cols);
            $bind = [];
            foreach ($common as $k => $v) { $bind[':' . $k] = $v; }
            db()->prepare("INSERT INTO $table (" . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')')->execute($bind);
        }
    } catch (Throwable $e) {
        log_error('rma_save_items: ' . $e->getMessage());
        throw new RuntimeException('Ürün satırları kaydedilemedi.');
    }
}

/* ----------------------- Public Takip ----------------------- */

function rma_public_url(array $rec): ?string
{
    if (empty($rec['public_token']) || empty($rec['reference_code'])) { return null; }
    $base = rtrim(base_url(), '/');
    return $base . '/rma-takip.php?code=' . rawurlencode((string) $rec['reference_code'])
         . '&token=' . rawurlencode((string) $rec['public_token']);
}

function rma_regenerate_token(int $id): string
{
    $token = rma_make_token();
    try {
        db()->prepare('UPDATE rma_records SET public_token = :t WHERE id = :id')->execute([':t' => $token, ':id' => $id]);
    } catch (Throwable $e) {
        log_error('rma_regenerate_token: ' . $e->getMessage());
        throw new RuntimeException('Takip linki yenilenemedi.');
    }
    return $token;
}

function rma_toggle_public(int $id, bool $enabled): void
{
    try {
        db()->prepare('UPDATE rma_records SET public_tracking_enabled = :e WHERE id = :id')
            ->execute([':e' => $enabled ? 1 : 0, ':id' => $id]);
    } catch (Throwable $e) {
        log_error('rma_toggle_public: ' . $e->getMessage());
        throw new RuntimeException('Ayar güncellenemedi.');
    }
}

function rma_get_public(string $code, string $token): ?array
{
    if ($code === '' || $token === '') { return null; }
    try {
        $st = db()->prepare('SELECT * FROM rma_records WHERE reference_code = :c AND is_deleted = 0 LIMIT 1');
        $st->execute([':c' => $code]);
        $rec = $st->fetch();
        if (!$rec) { return null; }
        if ((int) ($rec['public_tracking_enabled'] ?? 0) !== 1) { return null; }
        if (!hash_equals((string) ($rec['public_token'] ?? ''), $token)) { return null; }
        return $rec;
    } catch (Throwable $e) {
        log_error('rma_get_public: ' . $e->getMessage());
        return null;
    }
}

/** Public durum geçmişi (is_public=1). */
function get_rma_public_history(int $recordId): array
{
    try {
        $st = db()->prepare(
            'SELECT old_status, new_status, note, created_at
             FROM rma_status_history WHERE rma_record_id = :rid AND is_public = 1 ORDER BY id ASC'
        );
        $st->execute([':rid' => $recordId]);
        return $st->fetchAll();
    } catch (Throwable $e) {
        log_error('get_rma_public_history: ' . $e->getMessage());
        return [];
    }
}

/** Takip linki WhatsApp mesajı. */
function rma_tracking_wa(array $rec): string
{
    $url = rma_public_url($rec) ?? '';
    return "Merhaba, iade/değişim sürecinizi aşağıdaki linkten takip edebilirsiniz.\n\n"
         . 'Referans No: ' . (string) ($rec['reference_code'] ?? '') . "\n"
         . 'Takip Linki: ' . $url . "\n\nPoyraz Toner";
}
