<?php
declare(strict_types=1);

/**
 * includes/lead_scan_places.php — Google Places tarama iş mantığı (§2,§3,§4,§5).
 *
 *  - Taramayı çalıştırır (GooglePlacesService), sonuçları önizleme tablosuna yazar.
 *  - Kopya kontrolü: Place ID → normalize telefon → alan adı → ad+adres (öncelik).
 *  - Seçilen sonuçları lead'e dönüştürür (kopyaları atlar/işaretler).
 *  - Aynı kullanıcının arka arkaya taramasını sınırlar (rate limit).
 */

require_once __DIR__ . '/leads.php';
require_once __DIR__ . '/google_places.php';
require_once __DIR__ . '/../classes/GooglePlacesService.php';

/**
 * Aynı kullanıcı için arka arkaya tarama engeli + saatlik üst sınır.
 * @return array{ok:bool, error:string}
 */
function gp_scan_rate_ok(?int $userId): array
{
    if ($userId === null) { return ['ok' => true, 'error' => '']; }
    try {
        // Son 8 saniye içinde bu kullanıcı tarama başlattıysa engelle
        $st = db()->prepare('SELECT COUNT(*) FROM google_places_searches WHERE created_by = :u AND created_at >= (NOW() - INTERVAL 8 SECOND)');
        $st->execute([':u' => $userId]);
        if ((int) $st->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'Çok sık tarama yapıyorsunuz. Lütfen birkaç saniye bekleyin.'];
        }
        // Son 1 saatte en fazla 60 tarama
        $st = db()->prepare('SELECT COUNT(*) FROM google_places_searches WHERE created_by = :u AND created_at >= (NOW() - INTERVAL 1 HOUR)');
        $st->execute([':u' => $userId]);
        if ((int) $st->fetchColumn() >= 60) {
            return ['ok' => false, 'error' => 'Saatlik tarama sınırına ulaştınız. Lütfen sonra tekrar deneyin.'];
        }
    } catch (Throwable $e) { log_error('gp_scan_rate_ok: ' . $e->getMessage()); }
    return ['ok' => true, 'error' => ''];
}

/**
 * Taramayı çalıştırır: Google'dan sonuç toplar (max_results kadar, sayfalı),
 * bir google_places_searches kaydı + sonuç satırları oluşturur, kopyaları işaretler.
 *
 * @param array $p keyword, country, city, district, radius, language, search_type,
 *                 lat, lng, filters (include_no_phone/include_no_website/only_no_website/min_rating)
 * @return array{ok:bool, error:string, search_id:int, summary:array}
 */
function gp_scan_run(array $p, ?int $userId): array
{
    $rate = gp_scan_rate_ok($userId);
    if (!$rate['ok']) { return ['ok' => false, 'error' => $rate['error'], 'search_id' => 0, 'summary' => []]; }

    $svc = new GooglePlacesService($userId);
    $ready = $svc->isReady();
    if (!$ready['ok']) { return ['ok' => false, 'error' => $ready['error'], 'search_id' => 0, 'summary' => []]; }

    $s = gp_settings();
    $type    = ($p['search_type'] ?? 'text') === 'nearby' ? 'nearby' : 'text';
    $keyword = trim((string) ($p['keyword'] ?? ''));
    $country = trim((string) ($p['country'] ?? $s['default_country']));
    $city    = trim((string) ($p['city'] ?? $s['default_city']));
    $district = trim((string) ($p['district'] ?? ''));
    $radius  = (int) ($p['radius'] ?? $s['default_radius']);
    $language = trim((string) ($p['language'] ?? $s['default_language'])) ?: 'tr';
    $maxWant = (int) ($p['max_results'] ?? $s['max_results']);
    $maxWant = max(1, min(200, $maxWant));
    $lat = isset($p['lat']) && $p['lat'] !== '' ? (float) $p['lat'] : null;
    $lng = isset($p['lng']) && $p['lng'] !== '' ? (float) $p['lng'] : null;

    if ($type === 'text' && $keyword === '') {
        return ['ok' => false, 'error' => 'Arama kelimesi girin (örn. "dişçi", "kuaför").', 'search_id' => 0, 'summary' => []];
    }
    if ($type === 'nearby' && ($lat === null || $lng === null)) {
        return ['ok' => false, 'error' => 'Yakın çevre araması için konum (enlem/boylam) gerekli.', 'search_id' => 0, 'summary' => []];
    }

    $filters = [
        'include_no_phone'   => isset($p['include_no_phone']) ? (int) (bool) $p['include_no_phone'] : (int) $s['include_no_phone'],
        'include_no_website' => isset($p['include_no_website']) ? (int) (bool) $p['include_no_website'] : (int) $s['include_no_website'],
        'only_no_website'    => (int) (bool) ($p['only_no_website'] ?? 0),
        'min_rating'         => (float) ($p['min_rating'] ?? 0),
    ];

    // Arama kaydı oluştur (running)
    $searchId = 0;
    try {
        db()->prepare(
            'INSERT INTO google_places_searches (search_type, keyword, country, city, district, lat, lng, radius, filters_json, status, created_by)
             VALUES (:t,:kw,:co,:ci,:di,:lat,:lng,:rad,:fj,\'running\',:by)'
        )->execute([
            ':t' => $type, ':kw' => $keyword, ':co' => $country, ':ci' => $city, ':di' => $district,
            ':lat' => $lat, ':lng' => $lng, ':rad' => $radius,
            ':fj' => json_encode($filters, JSON_UNESCAPED_UNICODE), ':by' => $userId,
        ]);
        $searchId = (int) db()->lastInsertId();
    } catch (Throwable $e) {
        log_error('gp_scan_run insert: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Tarama kaydı oluşturulamadı.', 'search_id' => 0, 'summary' => []];
    }

    // Google'dan topla (sayfalı; her sayfa ≤20). En fazla 5 sayfa (~100).
    $collected = [];
    $apiCalls = 0;
    $pageToken = null;
    $errMsg = '';
    for ($page = 0; $page < 6 && count($collected) < $maxWant; $page++) {
        $opts = [
            'country' => $country, 'city' => $city, 'district' => $district,
            'language' => $language, 'maxResults' => min(20, $maxWant - count($collected)),
            'radius' => $radius, 'lat' => $lat, 'lng' => $lng, 'searchId' => $searchId,
        ];
        if ($pageToken !== null) { $opts['pageToken'] = $pageToken; }
        $res = $type === 'nearby'
            ? $svc->nearbySearch((float) $lat, (float) $lng, $opts)
            : $svc->textSearch($keyword, $opts);
        $apiCalls++;
        if (!$res['ok']) { $errMsg = $res['error']; break; }
        foreach ($res['places'] as $pl) { $collected[] = $pl; }
        $pageToken = $res['next_page_token'] ?? null;
        if ($pageToken === null) { break; }
    }

    if ($collected === [] && $errMsg !== '') {
        gp_search_finish($searchId, 'error', 0, 0, 0, $apiCalls, $errMsg);
        return ['ok' => false, 'error' => $errMsg, 'search_id' => $searchId, 'summary' => []];
    }

    // Filtreleri uygula + kopya işaretle + sonuç satırlarını yaz
    $newCount = 0; $dupCount = 0; $stored = 0;
    foreach ($collected as $pl) {
        // Filtre: telefon/website
        $hasPhone = trim((string) ($pl['phone'] ?? '')) !== '' || trim((string) ($pl['intl_phone'] ?? '')) !== '';
        $hasWeb   = trim((string) ($pl['website'] ?? '')) !== '';
        if (!$filters['include_no_phone'] && !$hasPhone) { continue; }
        if (!$filters['include_no_website'] && !$hasWeb) { continue; }
        if ($filters['only_no_website'] && $hasWeb) { continue; }
        if ($filters['min_rating'] > 0 && (float) ($pl['rating'] ?? 0) < $filters['min_rating']) { continue; }

        $dupId = lead_find_duplicate($pl);
        if ($dupId > 0) { $dupCount++; } else { $newCount++; }

        try {
            db()->prepare(
                'INSERT INTO google_places_search_results
                    (search_id, place_id, name, main_category, phone, intl_phone, website, maps_url, address,
                     city, district, lat, lng, rating, review_count, working_status, raw_json, is_duplicate, dup_lead_id)
                 VALUES (:sid,:pid,:name,:cat,:ph,:iph,:web,:maps,:addr,:city,:dist,:lat,:lng,:rt,:rc,:ws,:raw,:dup,:dupid)'
            )->execute([
                ':sid' => $searchId, ':pid' => (string) ($pl['place_id'] ?? ''), ':name' => (string) ($pl['name'] ?? ''),
                ':cat' => (string) ($pl['main_category'] ?? ''), ':ph' => (string) ($pl['phone'] ?? ''),
                ':iph' => (string) ($pl['intl_phone'] ?? ''), ':web' => (string) ($pl['website'] ?? ''),
                ':maps' => (string) ($pl['maps_url'] ?? ''), ':addr' => (string) ($pl['address'] ?? ''),
                ':city' => (string) ($pl['city'] ?? ''), ':dist' => (string) ($pl['district'] ?? ''),
                ':lat' => $pl['lat'] ?? null, ':lng' => $pl['lng'] ?? null,
                ':rt' => $pl['rating'] ?? null, ':rc' => $pl['review_count'] ?? null,
                ':ws' => (string) ($pl['working_status'] ?? ''),
                ':raw' => json_encode($pl['raw'] ?? $pl, JSON_UNESCAPED_UNICODE),
                ':dup' => $dupId > 0 ? 1 : 0, ':dupid' => $dupId > 0 ? $dupId : null,
            ]);
            $stored++;
        } catch (Throwable $e) { log_error('gp_scan_run result insert: ' . $e->getMessage()); }
    }

    gp_search_finish($searchId, 'done', $stored, $newCount, $dupCount, $apiCalls, $errMsg);

    return [
        'ok' => true, 'error' => '', 'search_id' => $searchId,
        'summary' => ['total' => $stored, 'new' => $newCount, 'dup' => $dupCount, 'api_calls' => $apiCalls, 'warning' => $errMsg],
    ];
}

/** Arama kaydını tamamlar (sayaçlar + durum + maliyet). */
function gp_search_finish(int $searchId, string $status, int $total, int $new, int $dup, int $apiCalls, string $error): void
{
    try {
        db()->prepare(
            'UPDATE google_places_searches SET status=:st, result_count=:rc, new_count=:nc, dup_count=:dc,
                    api_calls=:ac, est_cost=:cost, error_message=:err WHERE id=:id'
        )->execute([
            ':st' => $status, ':rc' => $total, ':nc' => $new, ':dc' => $dup, ':ac' => $apiCalls,
            ':cost' => round($apiCalls * 0.032, 4), ':err' => mb_substr($error, 0, 250), ':id' => $searchId,
        ]);
    } catch (Throwable $e) { log_error('gp_search_finish: ' . $e->getMessage()); }
}

/**
 * Kopya lead bulur. Öncelik: Place ID → normalize telefon → alan adı → ad+adres.
 * Silinmiş (is_deleted) kayıtlar hariç. Bulursa lead id, yoksa 0.
 */
function lead_find_duplicate(array $r): int
{
    try {
        // 1) Place ID (en kesin)
        $pid = trim((string) ($r['place_id'] ?? ''));
        if ($pid !== '') {
            $st = db()->prepare('SELECT id FROM leads WHERE place_id = :p AND is_deleted = 0 LIMIT 1');
            $st->execute([':p' => $pid]);
            $id = (int) ($st->fetchColumn() ?: 0);
            if ($id > 0) { return $id; }
        }

        // 2) Normalize telefon (son 10 haneye göre)
        $phone = trim((string) ($r['phone'] ?? '')) ?: trim((string) ($r['intl_phone'] ?? ''));
        $norm = lead_normalize_phone($phone);
        $digits = preg_replace('/\D+/', '', $norm) ?? '';
        if (strlen($digits) >= 7) {
            $tail = substr($digits, -10);
            $like = '%' . $tail;
            $st = db()->prepare(
                "SELECT id FROM leads WHERE is_deleted = 0 AND (
                    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''),' ',''),'-',''),'(',''),')',''),'+','') LIKE :t1
                 OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(whatsapp,''),' ',''),'-',''),'(',''),')',''),'+','') LIKE :t2
                 OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(alt_phone,''),' ',''),'-',''),'(',''),')',''),'+','') LIKE :t3
                ) LIMIT 1"
            );
            $st->execute([':t1' => $like, ':t2' => $like, ':t3' => $like]);
            $id = (int) ($st->fetchColumn() ?: 0);
            if ($id > 0) { return $id; }
        }

        // 3) Alan adı
        $domain = (string) ($r['domain'] ?? lead_domain_from_url((string) ($r['website'] ?? '')));
        if ($domain !== '') {
            $st = db()->prepare('SELECT id FROM leads WHERE domain = :d AND is_deleted = 0 LIMIT 1');
            $st->execute([':d' => $domain]);
            $id = (int) ($st->fetchColumn() ?: 0);
            if ($id > 0) { return $id; }
        }

        // 4) İşletme adı + adres/şehir
        $name = trim((string) ($r['name'] ?? ''));
        $city = trim((string) ($r['city'] ?? ''));
        if ($name !== '') {
            $sql = 'SELECT id FROM leads WHERE is_deleted = 0 AND LOWER(company_name) = LOWER(:n)';
            $params = [':n' => $name];
            if ($city !== '') { $sql .= ' AND (city = :c OR address LIKE :addr)'; $params[':c'] = $city; $params[':addr'] = '%' . $city . '%'; }
            $sql .= ' LIMIT 1';
            $st = db()->prepare($sql);
            $st->execute($params);
            $id = (int) ($st->fetchColumn() ?: 0);
            if ($id > 0) { return $id; }
        }
    } catch (Throwable $e) { log_error('lead_find_duplicate: ' . $e->getMessage()); }
    return 0;
}

/** Arama kaydı. */
function gp_get_search(int $id): ?array
{
    try {
        $st = db()->prepare('SELECT s.*, u.full_name AS created_by_name FROM google_places_searches s LEFT JOIN users u ON u.id = s.created_by WHERE s.id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { log_error('gp_get_search: ' . $e->getMessage()); return null; }
}

/** Arama sonuç satırları. */
function gp_get_search_results(int $searchId, bool $onlyNew = false): array
{
    try {
        $sql = 'SELECT * FROM google_places_search_results WHERE search_id = :s';
        if ($onlyNew) { $sql .= ' AND is_duplicate = 0'; }
        $sql .= ' ORDER BY id ASC';
        $st = db()->prepare($sql);
        $st->execute([':s' => $searchId]);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('gp_get_search_results: ' . $e->getMessage()); return []; }
}

/** Son taramalar (liste). */
function gp_recent_searches(int $limit = 30): array
{
    try {
        $st = db()->prepare('SELECT s.*, u.full_name AS created_by_name FROM google_places_searches s LEFT JOIN users u ON u.id = s.created_by ORDER BY s.id DESC LIMIT ' . max(1, min(100, $limit)));
        $st->execute();
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('gp_recent_searches: ' . $e->getMessage()); return []; }
}

/**
 * Seçilen sonuç satırlarını lead'e dönüştürür. block_duplicates açıkken kopyalar
 * atlanır. Zaten kaydedilmiş (saved_lead_id) satırlar atlanır.
 *
 * @param int[] $resultIds
 * @return array{saved:int, skipped_dup:int, skipped_saved:int, failed:int, lead_ids:int[]}
 */
function gp_scan_save_results(int $searchId, array $resultIds, array $opts, ?int $userId): array
{
    $out = ['saved' => 0, 'skipped_dup' => 0, 'skipped_saved' => 0, 'failed' => 0, 'lead_ids' => []];
    $blockDup = !empty(gp_settings()['block_duplicates']);
    $forceDup = !empty($opts['allow_duplicates']); // kullanıcı bilinçli olarak kopyayı da kaydedebilir
    $assignTo = (int) ($opts['assigned_personnel_id'] ?? 0) ?: null;
    $source   = trim((string) ($opts['source'] ?? 'google_places'));
    $package  = trim((string) ($opts['package'] ?? ''));
    $status   = (string) ($opts['status'] ?? 'new');
    if (!isset(lead_status_rows()[$status])) { $status = 'new'; }

    $ids = array_values(array_unique(array_map('intval', $resultIds)));
    if (!$ids) { return $out; }
    $place = implode(',', array_fill(0, count($ids), '?'));

    try {
        $st = db()->prepare("SELECT * FROM google_places_search_results WHERE search_id = ? AND id IN ($place)");
        $st->execute(array_merge([$searchId], $ids));
        $rows = $st->fetchAll();
    } catch (Throwable $e) { log_error('gp_scan_save_results select: ' . $e->getMessage()); return $out; }

    foreach ($rows as $row) {
        if ((int) ($row['saved_lead_id'] ?? 0) > 0) { $out['skipped_saved']++; continue; }
        $isDup = (int) ($row['is_duplicate'] ?? 0) === 1;
        if ($isDup && $blockDup && !$forceDup) { $out['skipped_dup']++; continue; }

        // raw_json'dan zengin alanları çöz
        $raw = json_decode((string) ($row['raw_json'] ?? ''), true);
        $raw = is_array($raw) ? $raw : [];
        $phone = (string) ($row['phone'] ?? '');
        $website = (string) ($row['website'] ?? '');

        $leadData = [
            'place_id'         => (string) ($row['place_id'] ?? '') ?: null,
            'company_name'     => (string) ($row['name'] ?? ''),
            'contact_name'     => null,
            'phone'            => $phone ?: null,
            'whatsapp'         => $phone ?: null,
            'email'            => null,
            'website'          => $website ?: null,
            'domain'           => (string) ($raw['domain'] ?? lead_domain_from_url($website)) ?: null,
            'maps_url'         => (string) ($row['maps_url'] ?? '') ?: null,
            'google_rating'    => $row['rating'] !== null ? (float) $row['rating'] : null,
            'review_count'     => $row['review_count'] !== null ? (int) $row['review_count'] : null,
            'has_website'      => $website !== '' ? 1 : 0,
            'main_category'    => (string) ($row['main_category'] ?? '') ?: null,
            'other_categories' => (string) ($raw['other_categories'] ?? '') ?: null,
            'sector'           => (string) ($row['main_category'] ?? '') ?: null,
            'country'          => (string) ($raw['country'] ?? '') ?: null,
            'city'             => (string) ($row['city'] ?? '') ?: null,
            'district'         => (string) ($row['district'] ?? '') ?: null,
            'neighborhood'     => (string) ($raw['neighborhood'] ?? '') ?: null,
            'postal_code'      => (string) ($raw['postal_code'] ?? '') ?: null,
            'address'          => (string) ($row['address'] ?? '') ?: null,
            'lat'              => $row['lat'] !== null ? (float) $row['lat'] : null,
            'lng'              => $row['lng'] !== null ? (float) $row['lng'] : null,
            'working_status'   => (string) ($row['working_status'] ?? '') ?: null,
            'working_hours'    => (string) ($raw['working_hours'] ?? '') ?: null,
            'search_keyword'   => (string) ($opts['keyword'] ?? '') ?: null,
            'source'           => $source ?: null,
            'package'          => $package ?: null,
            'status'           => $status,
            'assigned_personnel_id' => $assignTo,
        ];

        $leadId = gp_create_lead_from_place($leadData, $userId);
        if ($leadId > 0) {
            $out['saved']++;
            $out['lead_ids'][] = $leadId;
            try {
                db()->prepare('UPDATE google_places_search_results SET saved_lead_id = :lid WHERE id = :id')
                    ->execute([':lid' => $leadId, ':id' => (int) $row['id']]);
            } catch (Throwable $e) { log_error('gp_scan_save_results update: ' . $e->getMessage()); }
            lead_activity_add($leadId, 'created', 'Google Places taramasından eklendi', ['search_id' => $searchId], $userId);
            lead_audit_log($leadId, 'lead_create_from_scan', 'Arama #' . $searchId, $userId);
        } else {
            $out['failed']++;
        }
    }
    return $out;
}

/**
 * Google Places verisinden lead oluşturur (genişletilmiş alanlarla). place_id
 * benzersizdir; çakışırsa (yarış durumu) mevcut kaydı döndürür.
 */
function gp_create_lead_from_place(array $d, ?int $userId): int
{
    try {
        $st = db()->prepare(
            'INSERT INTO leads
                (place_id, company_name, contact_name, phone, whatsapp, email, website, domain, maps_url,
                 google_rating, review_count, has_website, sector, main_category, other_categories,
                 country, city, district, neighborhood, postal_code, address, lat, lng, working_status,
                 working_hours, search_keyword, source, package, status, assigned_personnel_id, created_by, updated_by)
             VALUES
                (:place_id,:company,:contact,:phone,:whatsapp,:email,:website,:domain,:maps,
                 :rating,:reviews,:haswww,:sector,:maincat,:othercat,
                 :country,:city,:district,:neigh,:postal,:address,:lat,:lng,:wstatus,
                 :whours,:keyword,:source,:package,:status,:assigned,:cby,:uby)'
        );
        $st->execute([
            ':place_id' => $d['place_id'], ':company' => $d['company_name'], ':contact' => $d['contact_name'],
            ':phone' => $d['phone'], ':whatsapp' => $d['whatsapp'], ':email' => $d['email'],
            ':website' => $d['website'], ':domain' => $d['domain'], ':maps' => $d['maps_url'],
            ':rating' => $d['google_rating'], ':reviews' => $d['review_count'], ':haswww' => $d['has_website'],
            ':sector' => $d['sector'], ':maincat' => $d['main_category'], ':othercat' => $d['other_categories'],
            ':country' => $d['country'], ':city' => $d['city'], ':district' => $d['district'],
            ':neigh' => $d['neighborhood'], ':postal' => $d['postal_code'], ':address' => $d['address'],
            ':lat' => $d['lat'], ':lng' => $d['lng'], ':wstatus' => $d['working_status'],
            ':whours' => $d['working_hours'], ':keyword' => $d['search_keyword'], ':source' => $d['source'],
            ':package' => $d['package'], ':status' => $d['status'], ':assigned' => $d['assigned_personnel_id'],
            ':cby' => $userId, ':uby' => $userId,
        ]);
        return (int) db()->lastInsertId();
    } catch (Throwable $e) {
        // place_id UNIQUE çakışması → mevcut kaydı bul
        if ($d['place_id']) {
            try {
                $st = db()->prepare('SELECT id FROM leads WHERE place_id = :p LIMIT 1');
                $st->execute([':p' => $d['place_id']]);
                $id = (int) ($st->fetchColumn() ?: 0);
                if ($id > 0) { return 0; } // zaten var → yeni kayıt sayma
            } catch (Throwable $e2) {}
        }
        log_error('gp_create_lead_from_place: ' . $e->getMessage());
        return 0;
    }
}

/** Lead denetim (audit) kaydı — kim/ne/ne zaman/IP. */
function lead_audit_log(?int $leadId, string $action, string $detail, ?int $userId): void
{
    try {
        db()->prepare('INSERT INTO lead_audit_logs (lead_id, action, detail, user_id, ip_address) VALUES (:l,:a,:d,:u,:ip)')
            ->execute([
                ':l' => $leadId, ':a' => mb_substr($action, 0, 60), ':d' => mb_substr($detail, 0, 2000),
                ':u' => $userId, ':ip' => function_exists('client_ip') ? client_ip() : '',
            ]);
    } catch (Throwable $e) { log_error('lead_audit_log: ' . $e->getMessage()); }
}
