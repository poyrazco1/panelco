<?php
declare(strict_types=1);

/**
 * includes/international-customers-io.php
 * Yurtdışı müşteri CSV içe/dışa aktarım altyapısı.
 *  - UTF-8 BOM + noktalı virgül (Excel-Türkçe güvenli).
 *  - Örnek CSV, içe aktarım (tekrar tespiti + güncelle/atla), dışa aktarım.
 *  - Marka formatı: "HP:Distribütörü; Canon:Satıyor"
 *  - contact_1 / contact_2 kişi sütunları.
 */

require_once __DIR__ . '/international-customers.php';

/** Kanonik CSV başlıkları (sıra korunur). */
function ic_csv_headers(): array
{
    return [
        'firma_adi', 'rol', 'ulke', 'sehir', 'adres', 'website', 'email', 'telefon', 'whatsapp',
        'vergi_no', 'para_birimi', 'dil', 'veri_kaynagi', 'fuar_etkinlik', 'iliski_durumu',
        'iletisim_durumu', 'iletisim_izni', 'durum', 'kategoriler', 'sirket_tipleri', 'markalar',
        'contact1_ad', 'contact1_unvan', 'contact1_email', 'contact1_telefon',
        'contact2_ad', 'contact2_unvan', 'contact2_email', 'contact2_telefon', 'notlar',
    ];
}

/** Örnek CSV satırları (spec 27.14/27.18). */
function ic_csv_sample_rows(): array
{
    return [
        [
            'Global Office Supplies Ltd', 'Alıcı', 'Germany', 'Berlin', 'Alexanderplatz 1', 'www.globaloffice.de',
            'info@globaloffice.de', '+49 30 1234567', '+49 170 1234567', 'DE123456789', 'EUR', 'German',
            'Fuar', 'Paperworld 2026', 'Yeni', 'Görüşüldü', 'Evet', 'Potansiyel', 'Yazıcı & Sarf', 'Alıcı;İthalatçı',
            'HP:Distribütörü; Canon:Satıyor',
            'Hans Müller', 'Satın Alma Müdürü', 'hans@globaloffice.de', '+49 30 1234568', '', '', '', '',
            'HP toner ve kartuş alımıyla ilgileniyor.',
        ],
        [
            'Dubai Print Trading LLC', 'Hem Alıcı Hem Satıcı', 'UAE', 'Dubai', 'Sheikh Zayed Rd', 'www.dubaiprint.ae',
            'sales@dubaiprint.ae', '+971 4 1234567', '+971 50 1234567', '', 'USD', 'English',
            'Referans', 'Gulfood 2026', 'Aktif', 'Teklif Bekliyor', 'Evet', 'İletişimde', 'Ofis Malzemeleri', 'Alıcı;Satıcı;Distribütör',
            'Canon:Distribütörü; Brother:Alıyor',
            'Ahmed Khan', 'General Manager', 'ahmed@dubaiprint.ae', '+971 4 1234568',
            'Sara Ali', 'Procurement', 'sara@dubaiprint.ae', '+971 50 7654321',
            'Hem alım hem satım yapıyor; toplu sipariş potansiyeli.',
        ],
        [
            'IT Parts Wholesale GmbH', 'Satıcı', 'Germany', 'München', 'Landsberger Str. 100', 'www.itparts.de',
            'kontakt@itparts.de', '+49 89 9876543', '', 'DE987654321', 'EUR', 'German',
            'Web', '', 'Yeni', 'Bekliyor', 'Hayır', 'Potansiyel', 'Bilişim / IT', 'Satıcı;Tedarikçi',
            'HP:Satıyor; Lexmark:Satıyor',
            'Klaus Weber', 'Sales Director', 'klaus@itparts.de', '+49 89 9876544', '', '', '', '',
            'RAM ve yedek parça toptan satışı.',
        ],
    ];
}

/** CSV çıktısı yaz (UTF-8 BOM + ; ayraç). */
function ic_csv_stream(array $header, array $rows, string $filename): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $header, ';');
    foreach ($rows as $r) { fputcsv($out, $r, ';'); }
    fclose($out);
}

/** Rol etiketi → anahtar. */
function ic_role_from_label(string $s): string
{
    $s = mb_strtolower(trim($s));
    if ($s === '') { return 'buyer'; }
    if (in_array($s, ['buyer', 'alıcı', 'alici'], true)) { return 'buyer'; }
    if (in_array($s, ['seller', 'satıcı', 'satici'], true)) { return 'seller'; }
    if (strpos($s, 'hem') !== false || $s === 'both') { return 'both'; }
    return 'buyer';
}

/** "Evet/Hayır/1/0" → 0|1. */
function ic_bool_from_label(string $s): int
{
    $s = mb_strtolower(trim($s));
    return in_array($s, ['evet', 'yes', '1', 'true', 'var', 'izinli'], true) ? 1 : 0;
}

/** Durum adından status_id (aktif durumlar arasından). */
function ic_status_id_from_name(string $name): ?int
{
    $name = mb_strtolower(trim($name));
    if ($name === '') { return null; }
    foreach (ic_statuses() as $s) { if (mb_strtolower((string) $s['name']) === $name) { return (int) $s['id']; } }
    return null;
}

/** Şirket tipi adlarını (";" ayraçlı) id listesine çevir. */
function ic_type_ids_from_names(string $s): array
{
    $ids = [];
    $map = [];
    foreach (ic_company_types() as $t) { $map[mb_strtolower((string) $t['name'])] = (int) $t['id']; }
    foreach (preg_split('/\s*[;,]\s*/', trim($s)) as $n) {
        $k = mb_strtolower(trim($n));
        if ($k !== '' && isset($map[$k])) { $ids[] = $map[$k]; }
    }
    return array_values(array_unique($ids));
}

/** Kategori adlarını (";" ayraçlı) id listesine çevir (yoksa oluşturmaz, eşleşmeyeni atlar). */
function ic_category_ids_from_names(string $s): array
{
    $ids = [];
    $map = [];
    foreach (ic_categories() as $c) { $map[mb_strtolower((string) $c['name'])] = (int) $c['id']; }
    foreach (preg_split('/\s*;\s*/', trim($s)) as $n) {
        $k = mb_strtolower(trim($n));
        // "Ana › Alt" verildiyse son parçayı al
        if (strpos($k, '›') !== false) { $parts = explode('›', $k); $k = trim(end($parts)); }
        if ($k !== '' && isset($map[$k])) { $ids[] = $map[$k]; }
    }
    return array_values(array_unique($ids));
}

/** Tekrar tespiti: email VEYA telefon VEYA firma+ülke eşleşmesi → mevcut id. */
function ic_find_duplicate(string $company, string $country, string $email, string $phone): ?int
{
    try {
        $conds = []; $params = [];
        if ($email !== '') { $conds[] = 'email = :em'; $params[':em'] = $email; }
        if ($phone !== '') { $conds[] = 'phone = :ph'; $params[':ph'] = $phone; }
        if ($company !== '') { $conds[] = '(company_name = :cn AND COALESCE(country,"") = :co)'; $params[':cn'] = $company; $params[':co'] = $country; }
        if (!$conds) { return null; }
        $st = db()->prepare('SELECT id FROM international_customers WHERE is_deleted = 0 AND (' . implode(' OR ', $conds) . ') ORDER BY id ASC LIMIT 1');
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v ? (int) $v : null;
    } catch (Throwable $e) { log_error('ic_find_duplicate: ' . $e->getMessage()); return null; }
}

/**
 * CSV satırlarını işle.
 * @param array $rows Ham satır dizileri (başlıksız), $headerMap sıralı anahtarlar.
 * @param array $headerKeys Dosyadaki sütunların kanonik anahtar eşlemesi (index => key).
 * @param string $mode 'skip' | 'update'
 * @return array [import_id, total, inserted, updated, skipped, errors(array of [row_no,reason,raw])]
 */
function ic_import_rows(array $rows, array $headerKeys, string $mode, ?int $userId, string $filename = ''): array
{
    $inserted = 0; $updated = 0; $skipped = 0; $errors = [];
    $total = count($rows);

    // import kaydı
    $importId = 0;
    try {
        db()->prepare('INSERT INTO international_customer_imports (filename, total_rows, mode, created_by) VALUES (:f,:t,:m,:by)')
            ->execute([':f' => $filename ?: null, ':t' => $total, ':m' => $mode, ':by' => $userId]);
        $importId = (int) db()->lastInsertId();
    } catch (Throwable $e) { log_error('ic_import create: ' . $e->getMessage()); }

    $get = static function (array $row, array $headerKeys, string $key): string {
        $idx = array_search($key, $headerKeys, true);
        if ($idx === false || !isset($row[$idx])) { return ''; }
        return trim((string) $row[$idx]);
    };

    foreach ($rows as $i => $row) {
        $rowNo = $i + 2; // başlık + 1 tabanlı
        $company = $get($row, $headerKeys, 'firma_adi');
        if ($company === '') {
            $errors[] = [$rowNo, 'Firma adı boş.', $row];
            continue;
        }
        $email = $get($row, $headerKeys, 'email');
        $phone = $get($row, $headerKeys, 'telefon');
        $country = $get($row, $headerKeys, 'ulke');

        $data = ic_fields_from_input([
            'company_name'         => $company,
            'company_role'         => ic_role_from_label($get($row, $headerKeys, 'rol')),
            'country'              => $country,
            'city'                 => $get($row, $headerKeys, 'sehir'),
            'address'              => $get($row, $headerKeys, 'adres'),
            'website'              => $get($row, $headerKeys, 'website'),
            'email'                => $email,
            'phone'                => $phone,
            'whatsapp'             => $get($row, $headerKeys, 'whatsapp'),
            'tax_no'               => $get($row, $headerKeys, 'vergi_no'),
            'currency'             => $get($row, $headerKeys, 'para_birimi'),
            'language'             => $get($row, $headerKeys, 'dil'),
            'data_source'          => $get($row, $headerKeys, 'veri_kaynagi'),
            'event_name'           => $get($row, $headerKeys, 'fuar_etkinlik'),
            'relationship_status'  => $get($row, $headerKeys, 'iliski_durumu'),
            'communication_status' => $get($row, $headerKeys, 'iletisim_durumu'),
            'notes'                => $get($row, $headerKeys, 'notlar'),
        ]);
        $data['status_id'] = ic_status_id_from_name($get($row, $headerKeys, 'durum'));
        $data['contact_permission'] = ic_bool_from_label($get($row, $headerKeys, 'iletisim_izni') ?: 'Evet');

        if ($email !== '' && !is_valid_email($email)) {
            $errors[] = [$rowNo, 'Geçersiz e-posta: ' . $email, $row];
            continue;
        }

        $dupId = ic_find_duplicate($company, $country, $email, $phone);
        $customerId = 0;
        try {
            if ($dupId !== null) {
                if ($mode !== 'update') { $skipped++; continue; }
                ic_update($dupId, $data, $userId);
                $customerId = $dupId;
                $updated++;
            } else {
                $customerId = ic_create($data, $userId);
                if ($customerId <= 0) { $errors[] = [$rowNo, 'Kayıt oluşturulamadı.', $row]; continue; }
                $inserted++;
            }

            // İlişkiler
            ic_set_company_types($customerId, ic_type_ids_from_names($get($row, $headerKeys, 'sirket_tipleri')));
            ic_set_categories($customerId, ic_category_ids_from_names($get($row, $headerKeys, 'kategoriler')));

            // Markalar (güncellemede tekrar eklememek için temizle)
            $brandStr = $get($row, $headerKeys, 'markalar');
            if ($brandStr !== '') {
                db()->prepare('DELETE FROM international_customer_brands WHERE customer_id = :c')->execute([':c' => $customerId]);
                foreach (ic_parse_brand_string($brandStr) as [$bn, $rel]) {
                    $bid = null;
                    try { $bs = db()->prepare('SELECT id FROM brands WHERE name = :n LIMIT 1'); $bs->execute([':n' => $bn]); $bid = ($v = $bs->fetchColumn()) ? (int) $v : null; } catch (Throwable $e) {}
                    ic_brand_add($customerId, $bn, $rel, $bid, $userId);
                }
            }

            // Kişiler 1/2 (güncellemede çift eklememek için: mevcut aynı adlı kişi varsa atla)
            foreach (['contact1', 'contact2'] as $cp) {
                $cName = $get($row, $headerKeys, $cp . '_ad');
                if ($cName === '') { continue; }
                $exists = false;
                if ($dupId !== null) {
                    foreach (ic_contacts($customerId) as $ec) { if (mb_strtolower((string) $ec['full_name']) === mb_strtolower($cName)) { $exists = true; break; } }
                }
                if ($exists) { continue; }
                ic_contact_save($customerId, [
                    'full_name' => $cName,
                    'title'     => $get($row, $headerKeys, $cp . '_unvan'),
                    'email'     => $get($row, $headerKeys, $cp . '_email'),
                    'phone'     => $get($row, $headerKeys, $cp . '_telefon'),
                    'is_primary' => ($cp === 'contact1' && $dupId === null) ? '1' : null,
                ], null, $userId);
            }
        } catch (Throwable $e) {
            log_error('ic_import row ' . $rowNo . ': ' . $e->getMessage());
            $errors[] = [$rowNo, 'İşlem hatası.', $row];
        }
    }

    // import özetini güncelle + hataları yaz
    try {
        db()->prepare('UPDATE international_customer_imports SET inserted=:i, updated=:u, skipped=:s, errors=:e WHERE id=:id')
            ->execute([':i' => $inserted, ':u' => $updated, ':s' => $skipped, ':e' => count($errors), ':id' => $importId]);
        if ($errors) {
            $st = db()->prepare('INSERT INTO international_customer_import_errors (import_id, row_no, reason, raw) VALUES (:im,:r,:re,:raw)');
            foreach ($errors as [$rn, $reason, $raw]) {
                $st->execute([':im' => $importId, ':r' => $rn, ':re' => mb_substr($reason, 0, 250), ':raw' => json_encode($raw, JSON_UNESCAPED_UNICODE)]);
            }
        }
    } catch (Throwable $e) { log_error('ic_import finalize: ' . $e->getMessage()); }

    return [
        'import_id' => $importId, 'total' => $total, 'inserted' => $inserted,
        'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors,
    ];
}

/** Bir müşteriyi CSV satırına (kanonik sıra) dönüştür. */
function ic_customer_to_csv_row(array $c): array
{
    $id = (int) $c['id'];
    $contacts = ic_contacts($id);
    $c1 = $contacts[0] ?? [];
    $c2 = $contacts[1] ?? [];
    $typeNames = [];
    $typeIds = ic_customer_type_ids($id);
    foreach (ic_company_types() as $t) { if (in_array((int) $t['id'], $typeIds, true)) { $typeNames[] = $t['name']; } }
    $catNames = [];
    foreach (ic_customer_category_ids($id) as $ci) { $catNames[] = ic_category_label($ci); }
    $brandParts = [];
    foreach (ic_brands($id) as $b) { $brandParts[] = $b['brand_name'] . ':' . ic_brand_relation_label((string) $b['relation_type']); }

    return [
        (string) $c['company_name'], ic_company_role_label((string) $c['company_role']),
        (string) ($c['country'] ?? ''), (string) ($c['city'] ?? ''), (string) ($c['address'] ?? ''),
        (string) ($c['website'] ?? ''), (string) ($c['email'] ?? ''), (string) ($c['phone'] ?? ''), (string) ($c['whatsapp'] ?? ''),
        (string) ($c['tax_no'] ?? ''), (string) ($c['currency'] ?? ''), (string) ($c['language'] ?? ''),
        (string) ($c['data_source'] ?? ''), (string) ($c['event_name'] ?? ''), (string) ($c['relationship_status'] ?? ''),
        (string) ($c['communication_status'] ?? ''), (int) $c['contact_permission'] === 1 ? 'Evet' : 'Hayır',
        (string) ($c['status_name'] ?? ''), implode('; ', array_filter($catNames)), implode(';', $typeNames),
        implode('; ', $brandParts),
        (string) ($c1['full_name'] ?? ''), (string) ($c1['title'] ?? ''), (string) ($c1['email'] ?? ''), (string) ($c1['phone'] ?? ''),
        (string) ($c2['full_name'] ?? ''), (string) ($c2['title'] ?? ''), (string) ($c2['email'] ?? ''), (string) ($c2['phone'] ?? ''),
        (string) ($c['notes'] ?? ''),
    ];
}
