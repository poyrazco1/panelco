<?php
declare(strict_types=1);

/**
 * includes/lead-scan.php — Lead Tarama (scan wizard) iş mantığı + iş yeri taksonomisi.
 *
 * Bu dosya YALNIZCA tarama yapılandırmasını (sektör/bölge/filtre/satış) üretir ve
 * "tarama işi" (lead_scans) olarak saklar. Google Haritalar taraması Chrome
 * eklentisi tarafından yapılır ve sonuçlar api/leads.php'ye POST edilir.
 * Mevcut lead/API mantığı BOZULMAZ; bu katman onun üstüne eklenir.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/leads.php';

/* =========================================================================
 |  İŞ YERİ TAKSONOMİSİ (Ana kategori → Alt kategori → Meslekler)
 * ====================================================================== */
function lead_scan_taxonomy(): array
{
    return [
        'Yeme & İçme' => [
            'Restoran & Lokanta' => ['restoran', 'lokanta', 'kebapçı', 'pideci', 'ev yemekleri', 'balık restoranı', 'steakhouse'],
            'Kafe & Tatlı'       => ['kafe', 'cafe', 'pastane', 'tatlıcı', 'kahveci', 'waffle', 'dondurmacı'],
            'Hızlı Yemek'        => ['fast food', 'burger', 'pizzacı', 'dönerci', 'çiğ köfteci', 'tantuni'],
        ],
        'Sağlık & Güzellik' => [
            'Klinik & Sağlık' => ['diş kliniği', 'diş hekimi', 'estetik klinik', 'fizik tedavi', 'diyetisyen', 'psikolog', 'veteriner'],
            'Güzellik & Bakım' => ['güzellik salonu', 'kuaför', 'berber', 'cilt bakım', 'epilasyon', 'dövme stüdyosu', 'spa'],
            'Medikal'          => ['medikal firma', 'optik gözlük', 'işitme cihazı', 'ortopedik ürünler', 'eczane'],
        ],
        'Otomotiv' => [
            'Servis & Bakım' => ['oto servis', 'oto tamir', 'oto elektrik', 'kaporta boya', 'lastikçi', 'oto yıkama', 'egzoz'],
            'Satış & Kiralama' => ['oto galeri', 'ikinci el araç', 'rent a car', 'oto ekspertiz', 'oto aksesuar'],
        ],
        'İnşaat & Yapı' => [
            'Yapı & Tadilat' => ['inşaat firması', 'tadilat', 'boyacı', 'fayans ustası', 'alçıpan', 'yalıtım', 'çatı ustası'],
            'Tesisat & Teknik' => ['tesisatçı', 'elektrikçi', 'kombi servisi', 'klima servisi', 'asansör firması', 'doğalgaz'],
            'Mimarlık & Proje' => ['mimarlık ofisi', 'iç mimar', 'peyzaj', 'proje ofisi', 'harita mühendisi'],
        ],
        'Ev & Yaşam' => [
            'Temizlik' => ['halı yıkama', 'ev temizliği', 'koltuk yıkama', 'cam temizliği', 'ilaçlama'],
            'Mobilya & Dekor' => ['mobilyacı', 'perde', 'mutfak dolabı', 'yatak firması', 'avize', 'dekorasyon'],
            'Nakliyat' => ['nakliyat', 'evden eve nakliyat', 'kurye', 'depolama'],
        ],
        'Perakende & Ticaret' => [
            'Mağaza' => ['giyim mağazası', 'ayakkabıcı', 'kırtasiye', 'oyuncakçı', 'hediyelik eşya', 'nalbur'],
            'Gıda & Market' => ['market', 'şarküteri', 'manav', 'kuruyemişçi', 'kasap', 'fırın'],
            'Toptan & Tedarik' => ['toptancı', 'tedarikçi firma', 'ambalaj firması', 'medikal toptan'],
        ],
        'Kurumsal & Hizmet' => [
            'Ofis & Danışmanlık' => ['muhasebe ofisi', 'mali müşavir', 'avukat', 'hukuk bürosu', 'danışmanlık', 'sigorta acentesi'],
            'Emlak' => ['emlak ofisi', 'gayrimenkul', 'inşaat ofisi'],
            'Eğitim' => ['özel ders', 'dil kursu', 'etüt merkezi', 'sürücü kursu', 'anaokulu', 'kreş'],
        ],
        'Turizm & Konaklama' => [
            'Konaklama' => ['otel', 'butik otel', 'pansiyon', 'apart otel'],
            'Etkinlik' => ['düğün salonu', 'organizasyon', 'fotoğrafçı', 'kına organizasyon'],
        ],
    ];
}

/** Tüm meslekleri (professions) düz liste olarak döndürür (benzersiz, sıralı). */
function lead_scan_all_professions(): array
{
    $out = [];
    foreach (lead_scan_taxonomy() as $subs) {
        foreach ($subs as $profs) {
            foreach ($profs as $p) { $out[$p] = true; }
        }
    }
    $list = array_keys($out);
    sort($list, SORT_NATURAL | SORT_FLAG_CASE);
    return $list;
}

/** Serbest kelime örnek önerileri. */
function lead_scan_keyword_suggestions(): array
{
    return ['kombi servisi', 'güzellik salonu', 'oto ekspertiz', 'halı yıkama', 'medikal firma',
            'diş kliniği', 'mimarlık ofisi', 'nakliyat', 'muhasebe ofisi', 'emlak ofisi'];
}

/** İl → popüler ilçeler (hızlı seçim). */
function lead_scan_popular_districts(): array
{
    return [
        'İstanbul' => ['Kadıköy', 'Beşiktaş', 'Şişli', 'Bakırköy', 'Ümraniye', 'Ataşehir', 'Maltepe', 'Pendik', 'Üsküdar', 'Beylikdüzü', 'Esenyurt', 'Başakşehir'],
        'Ankara'   => ['Çankaya', 'Keçiören', 'Yenimahalle', 'Mamak', 'Etimesgut', 'Sincan', 'Altındağ'],
        'İzmir'    => ['Konak', 'Bornova', 'Karşıyaka', 'Buca', 'Bayraklı', 'Çiğli', 'Gaziemir'],
        'Bursa'    => ['Osmangazi', 'Nilüfer', 'Yıldırım', 'Gürsu'],
        'Antalya'  => ['Muratpaşa', 'Konyaaltı', 'Kepez', 'Alanya', 'Manavgat'],
    ];
}
function lead_scan_cities(): array { return array_keys(lead_scan_popular_districts()); }

/** Satış paketleri. */
function lead_scan_packages(): array
{
    return ['tek_sayfa' => 'Tek Sayfa Web Sitesi', 'tek_sayfa_randevu' => 'Tek Sayfa + Randevu',
            'kurumsal' => 'Kurumsal Web Sitesi', 'panelli' => 'Yönetim Panelli Site',
            'eticaret' => 'E-Ticaret / Özel Yazılım', 'seo_reklam' => 'SEO / Reklam Danışmanlığı'];
}
function lead_scan_package_label(string $k): string { return lead_scan_packages()[$k] ?? $k; }

/** Öncelik seçenekleri. */
function lead_scan_priorities(): array { return ['normal' => 'Normal', 'yuksek' => 'Yüksek', 'acil' => 'Acil']; }

/** Kalite filtreleri tanımı (etiket + tip + varsayılan). */
function lead_scan_quality_filters(): array
{
    return [
        'has_phone'        => ['label' => 'Telefonu olanları al', 'type' => 'check', 'default' => true],
        'prefer_whatsapp'  => ['label' => 'WhatsApp uygun telefonları önceliklendir', 'type' => 'check', 'default' => true],
        'prefer_no_website'=> ['label' => 'Web sitesi olmayanları önceliklendir', 'type' => 'check', 'default' => true],
        'mark_weak_website'=> ['label' => 'Web sitesi zayıf görünenleri işaretle', 'type' => 'check', 'default' => false],
        'has_rating'       => ['label' => 'Puanı olan işletmeleri al', 'type' => 'check', 'default' => false],
        'dedupe_phone'     => ['label' => 'Aynı telefon tekrar kaydolmasın', 'type' => 'check', 'default' => true],
        'dedupe_company'   => ['label' => 'Aynı firma tekrar kaydolmasın', 'type' => 'check', 'default' => true],
        'skip_blacklist'   => ['label' => 'Kara listedekileri alma', 'type' => 'check', 'default' => true],
        'skip_do_not_call' => ['label' => 'Tekrar aranmasın işaretlileri alma', 'type' => 'check', 'default' => true],
    ];
}

/* =========================================================================
 |  TARAMA YAPILANDIRMASI (config) NORMALİZASYONU + KOMBİNASYON
 * ====================================================================== */
/** POST'tan gelen config'i güvenle normalize eder. */
function lead_scan_normalize_config(array $in): array
{
    $strArr = static function ($v): array {
        if (is_string($v)) { $v = json_decode($v, true); }
        if (!is_array($v)) { return []; }
        $out = [];
        foreach ($v as $x) { $x = trim((string) $x); if ($x !== '' && mb_strlen($x) <= 120) { $out[$x] = true; } }
        return array_values(array_keys($out));
    };
    $filters = [];
    $rawFilters = $in['filters'] ?? [];
    if (is_string($rawFilters)) { $rawFilters = json_decode($rawFilters, true) ?: []; }
    foreach (array_keys(lead_scan_quality_filters()) as $fk) { $filters[$fk] = !empty($rawFilters[$fk]); }

    $pkg = (string) ($in['package'] ?? '');
    if ($pkg !== '' && !isset(lead_scan_packages()[$pkg])) { $pkg = ''; }
    $prio = (string) ($in['priority'] ?? 'normal');
    if (!isset(lead_scan_priorities()[$prio])) { $prio = 'normal'; }

    return [
        'terms'    => $strArr($in['terms'] ?? []),      // sektör + meslek + serbest kelime birleşik
        'city'     => trim((string) ($in['city'] ?? '')),
        'districts'=> $strArr($in['districts'] ?? []),
        'neighborhood' => trim((string) ($in['neighborhood'] ?? '')),
        'filters'  => $filters,
        'min_rating' => (float) ($in['min_rating'] ?? 0),
        'min_reviews'=> (int) ($in['min_reviews'] ?? 0),
        'package'  => $pkg,
        'priority' => $prio,
        'assigned_personnel_id' => (int) ($in['assigned_personnel_id'] ?? 0) ?: null,
        'wa_template' => trim((string) ($in['wa_template'] ?? '')),
        'source'   => trim((string) ($in['source'] ?? 'Lead Tarama')) ?: 'Lead Tarama',
        'note'     => trim((string) ($in['note'] ?? '')),
        'target_limit' => max(0, min(100000, (int) ($in['target_limit'] ?? 0))),
    ];
}

/** Arama kombinasyon sayısı = terim × bölge (bölge yoksa şehir). */
function lead_scan_combos_count(array $config): int
{
    $terms = max(1, count($config['terms'] ?? []));
    $regions = count($config['districts'] ?? []);
    if ($regions === 0) { $regions = ($config['city'] ?? '') !== '' ? 1 : 0; }
    if ($regions === 0) { $regions = 1; }
    return count($config['terms'] ?? []) * $regions; // terim yoksa 0
}

/** Örnek arama kombinasyonları (önizleme için, ilk N). */
function lead_scan_sample_combos(array $config, int $limit = 12): array
{
    $regions = $config['districts'] ?? [];
    if (!$regions && ($config['city'] ?? '') !== '') { $regions = [$config['city']]; }
    if (!$regions) { $regions = ['(bölge yok)']; }
    $out = [];
    foreach ($config['terms'] ?? [] as $t) {
        foreach ($regions as $r) {
            $out[] = trim($t . ' ' . $r . (($config['city'] ?? '') !== '' && !in_array($r, [$config['city']], true) ? ' ' . $config['city'] : ''));
            if (count($out) >= $limit) { return $out; }
        }
    }
    return $out;
}

/* =========================================================================
 |  TARAMA İŞİ (lead_scans) CRUD
 * ====================================================================== */
function lead_scan_create(array $config, ?int $userId): int
{
    $name = trim((string) ($config['name'] ?? ''));
    if ($name === '') {
        $t = $config['terms'][0] ?? 'Tarama';
        $reg = $config['districts'][0] ?? ($config['city'] ?? '');
        $name = trim($t . ($reg !== '' ? ' · ' . $reg : ''));
    }
    try {
        $st = db()->prepare('INSERT INTO lead_scans
            (name, config_json, combos_count, target_limit, status, package, source, assigned_personnel_id, created_by, updated_by, started_at)
            VALUES (:n,:cj,:cc,:tl,:st,:pkg,:src,:ap,:cby,:uby,NOW())');
        $st->execute([
            ':n' => mb_substr($name, 0, 190), ':cj' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':cc' => lead_scan_combos_count($config), ':tl' => (int) ($config['target_limit'] ?? 0),
            ':st' => 'running', ':pkg' => $config['package'] ?: null, ':src' => $config['source'] ?: 'Lead Tarama',
            ':ap' => $config['assigned_personnel_id'] ?: null, ':cby' => $userId, ':uby' => $userId,
        ]);
        return (int) db()->lastInsertId();
    } catch (Throwable $e) { log_error('lead_scan_create: ' . $e->getMessage()); return 0; }
}

function lead_scan_find(int $id): ?array
{
    if ($id <= 0) { return null; }
    try {
        $st = db()->prepare('SELECT s.*, p.full_name AS assignee_name FROM lead_scans s
                             LEFT JOIN personnel p ON p.id = s.assigned_personnel_id
                             WHERE s.id = :id AND s.deleted_at IS NULL LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        if (!$row) { return null; }
        $row['config'] = json_decode((string) ($row['config_json'] ?? '{}'), true) ?: [];
        return $row;
    } catch (Throwable $e) { log_error('lead_scan_find: ' . $e->getMessage()); return null; }
}

function lead_scans_list(array $f = []): array
{
    $where = ['s.deleted_at IS NULL'];
    $params = [];
    if (!empty($f['status'])) { $where[] = 's.status = :st'; $params[':st'] = $f['status']; }
    try {
        $st = db()->prepare('SELECT s.*, p.full_name AS assignee_name FROM lead_scans s
                             LEFT JOIN personnel p ON p.id = s.assigned_personnel_id
                             WHERE ' . implode(' AND ', $where) . ' ORDER BY s.id DESC LIMIT 200');
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('lead_scans_list: ' . $e->getMessage()); return []; }
}

function lead_scan_statuses(): array
{
    return ['draft' => 'Taslak', 'running' => 'Çalışıyor', 'paused' => 'Duraklatıldı', 'done' => 'Tamamlandı', 'cancelled' => 'İptal'];
}
function lead_scan_status_label(string $k): string { return lead_scan_statuses()[$k] ?? $k; }
function lead_scan_status_class(string $k): string
{
    return match ($k) {
        'done' => 'badge-success', 'running' => 'badge-leave', 'paused' => 'badge-info',
        'cancelled' => 'badge-danger', default => 'badge-muted',
    };
}

function lead_scan_set_status(int $id, string $status, ?int $userId): bool
{
    if (!isset(lead_scan_statuses()[$status])) { return false; }
    try {
        $extra = in_array($status, ['done', 'cancelled'], true) ? ', completed_at = NOW()' : '';
        return db()->prepare("UPDATE lead_scans SET status = :s{$extra}, updated_by = :uby WHERE id = :id AND deleted_at IS NULL")
            ->execute([':s' => $status, ':uby' => $userId, ':id' => $id]);
    } catch (Throwable $e) { log_error('lead_scan_set_status: ' . $e->getMessage()); return false; }
}

function lead_scan_delete(int $id, ?int $userId): bool
{
    try { return db()->prepare('UPDATE lead_scans SET deleted_at = NOW(), updated_by = :uby WHERE id = :id')->execute([':uby' => $userId, ':id' => $id]); }
    catch (Throwable $e) { log_error('lead_scan_delete: ' . $e->getMessage()); return false; }
}

/** Sayaç artırma (API tarafından çağrılır: found/saved/duplicate/skipped/error). */
function lead_scan_bump(int $id, string $counter): void
{
    $map = ['found' => 'found_count', 'saved' => 'saved_count', 'duplicate' => 'duplicate_count', 'skipped' => 'skipped_count', 'error' => 'error_count'];
    $col = $map[$counter] ?? null;
    if (!$col || $id <= 0) { return; }
    try { db()->prepare("UPDATE lead_scans SET {$col} = {$col} + 1 WHERE id = :id")->execute([':id' => $id]); }
    catch (Throwable $e) { log_error('lead_scan_bump: ' . $e->getMessage()); }
}

/** Tarama ilerleme özeti (canlı ekran için). */
function lead_scan_progress(int $id): array
{
    $s = lead_scan_find($id);
    if (!$s) { return ['ok' => false]; }
    $target = (int) $s['target_limit'];
    $saved = (int) $s['saved_count'];
    $pct = $target > 0 ? min(100, (int) round($saved / $target * 100)) : 0;
    return [
        'ok' => true, 'status' => (string) $s['status'], 'status_label' => lead_scan_status_label((string) $s['status']),
        'combos' => (int) $s['combos_count'], 'target' => $target,
        'found' => (int) $s['found_count'], 'saved' => $saved, 'duplicate' => (int) $s['duplicate_count'],
        'skipped' => (int) $s['skipped_count'], 'error' => (int) $s['error_count'], 'percent' => $pct,
    ];
}

/** Bu taramada kaydedilen son lead'ler. */
function lead_scan_recent_leads(int $id, int $limit = 15): array
{
    $limit = max(1, min(50, $limit));
    try {
        $st = db()->prepare('SELECT id, company_name, phone, city, district, sector, google_rating, review_count, website, created_at
                             FROM leads WHERE scan_id = :id AND is_deleted = 0 ORDER BY id DESC LIMIT ' . $limit);
        $st->execute([':id' => $id]);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('lead_scan_recent_leads: ' . $e->getMessage()); return []; }
}
