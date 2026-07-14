<?php
declare(strict_types=1);

/**
 * classes/GooglePlacesService.php
 * Google Places API (New) ile konuşan TEK backend servisi (spec §1, §15, §18).
 *
 * GÜVENLİK:
 *  - API anahtarı yalnızca burada, gp_api_key() ile (şifreli kayıttan çözülerek)
 *    okunur ve YALNIZCA HTTP başlığında (X-Goog-Api-Key) gönderilir — URL'de,
 *    log'da veya istisna mesajında ASLA yer almaz.
 *  - Google'ın ham hata çıktısı son kullanıcıya gösterilmez; teknik ayrıntı
 *    güvenli biçimde loglanır, kullanıcıya dostça Türkçe mesaj döner.
 *  - Zaman aşımı + en fazla 2 kontrollü yeniden deneme uygulanır.
 *  - FieldMask ile yalnızca ihtiyaç duyulan alanlar istenir (maliyet + gizlilik).
 *
 * Kullanım:
 *   $svc = new GooglePlacesService();
 *   $res = $svc->textSearch('dişçi', ['city'=>'İzmir', 'language'=>'tr']);
 *   // $res => ['ok'=>bool, 'error'=>string, 'places'=>array, 'http'=>int, ...]
 */

require_once __DIR__ . '/../includes/google_places.php';

final class GooglePlacesService
{
    private const BASE = 'https://places.googleapis.com/v1/';
    private const TIMEOUT = 20;      // saniye
    private const CONNECT = 8;       // saniye
    private const MAX_RETRY = 2;     // kontrollü yeniden deneme

    /** Text Search istenen alanlar (yalnızca gerekli olanlar). */
    private const FIELD_MASK =
        'places.id,places.displayName,places.formattedAddress,places.nationalPhoneNumber,' .
        'places.internationalPhoneNumber,places.websiteUri,places.location,places.rating,' .
        'places.userRatingCount,places.primaryType,places.primaryTypeDisplayName,places.types,' .
        'places.businessStatus,places.googleMapsUri,places.addressComponents,' .
        'places.regularOpeningHours.weekdayDescriptions';

    /** Place Details alan maskesi (tek yer için, ön ek yok). */
    private const DETAILS_MASK =
        'id,displayName,formattedAddress,nationalPhoneNumber,internationalPhoneNumber,websiteUri,' .
        'location,rating,userRatingCount,primaryType,primaryTypeDisplayName,types,businessStatus,' .
        'googleMapsUri,addressComponents,regularOpeningHours.weekdayDescriptions';

    private ?int $userId;

    public function __construct(?int $userId = null)
    {
        $this->userId = $userId ?? (function_exists('current_user_id') ? current_user_id() : null);
    }

    /**
     * Kayıtlı (şifreli) anahtarla küçük bir test araması yapar. is_active şartı
     * ARANMAZ (etkinleştirmeden önce de test edilebilir). Anahtar sızmaz.
     * @return array{ok:bool, message:string, count:int}
     */
    public function ping(): array
    {
        if (!function_exists('curl_init')) { return ['ok' => false, 'message' => 'Sunucuda cURL etkin değil.', 'count' => 0]; }
        if (!gp_has_api_key()) { return ['ok' => false, 'message' => 'API anahtarı tanımlı değil. Önce anahtarı kaydedin.', 'count' => 0]; }
        if (gp_api_key() === null) { return ['ok' => false, 'message' => 'API anahtarı çözülemedi (şifreleme anahtarı değişmiş olabilir).', 'count' => 0]; }
        $s = gp_settings();
        $city = trim((string) ($s['default_city'] ?? ''));
        $query = 'restoran' . ($city !== '' ? ' ' . $city : '');
        $res = $this->call('places:searchText', [
            'textQuery' => $query, 'languageCode' => (string) ($s['default_language'] ?? 'tr'), 'maxResultCount' => 1,
        ], self::FIELD_MASK, 'test', null);
        if ($res['ok']) {
            return ['ok' => true, 'message' => 'Bağlantı başarılı — Google Places yanıt verdi.', 'count' => count($res['places'])];
        }
        return ['ok' => false, 'message' => $res['error'], 'count' => 0];
    }

    /** Servis çağrılabilir durumda mı? (anahtar var + aktif + cURL). */
    public function isReady(): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'Sunucuda cURL etkin değil. Sistem yöneticisine başvurun.'];
        }
        $s = gp_settings();
        if (empty($s['is_active'])) {
            return ['ok' => false, 'error' => 'Google Places taraması ayarlardan etkinleştirilmemiş.'];
        }
        if (!gp_has_api_key()) {
            return ['ok' => false, 'error' => 'Google API anahtarı tanımlı değil. Ayarlar → Lead Tarama.'];
        }
        if (gp_api_key() === null) {
            return ['ok' => false, 'error' => 'API anahtarı çözülemedi (şifreleme anahtarı değişmiş olabilir).'];
        }
        return ['ok' => true, 'error' => ''];
    }

    /**
     * Metin araması (places:searchText).
     * @param array{country?:string,city?:string,district?:string,language?:string,
     *              maxResults?:int,lat?:float,lng?:float,radius?:int,searchId?:int} $opts
     * @return array{ok:bool,error:string,places:array,http:int,next_page_token:?string}
     */
    public function textSearch(string $query, array $opts = []): array
    {
        $ready = $this->isReady();
        if (!$ready['ok']) { return $this->fail($ready['error']); }

        $s = gp_settings();
        $lang = (string) ($opts['language'] ?? $s['default_language'] ?? 'tr');
        $maxRes = (int) ($opts['maxResults'] ?? $s['max_results'] ?? 20);
        $maxRes = max(1, min(20, $maxRes)); // Google tek sayfada ≤20 döner

        // Konum + bölge bağlamı arama metnine eklenir (daha isabetli sonuç).
        $locBits = array_filter([
            trim((string) ($opts['district'] ?? '')),
            trim((string) ($opts['city'] ?? '')),
            trim((string) ($opts['country'] ?? '')),
        ]);
        $textQuery = trim($query . ' ' . implode(' ', $locBits));

        $body = [
            'textQuery'      => $textQuery,
            'languageCode'   => $lang,
            'maxResultCount' => $maxRes,
        ];
        if (!empty($opts['regionCode'])) { $body['regionCode'] = (string) $opts['regionCode']; }
        if (!empty($opts['pageToken'])) { $body['pageToken'] = (string) $opts['pageToken']; }
        // Konum sapması (varsa) — dairesel
        if (isset($opts['lat'], $opts['lng']) && $opts['lat'] !== null && $opts['lng'] !== null) {
            $body['locationBias'] = ['circle' => [
                'center' => ['latitude' => (float) $opts['lat'], 'longitude' => (float) $opts['lng']],
                'radius' => (float) max(1, min(50000, (int) ($opts['radius'] ?? $s['default_radius']))),
            ]];
        }

        return $this->call('places:searchText', $body, self::FIELD_MASK, 'text_search', $opts['searchId'] ?? null);
    }

    /**
     * Yakın çevre araması (places:searchNearby). Koordinat + yarıçap zorunlu.
     * @return array{ok:bool,error:string,places:array,http:int,next_page_token:?string}
     */
    public function nearbySearch(float $lat, float $lng, array $opts = []): array
    {
        $ready = $this->isReady();
        if (!$ready['ok']) { return $this->fail($ready['error']); }

        $s = gp_settings();
        $lang = (string) ($opts['language'] ?? $s['default_language'] ?? 'tr');
        $maxRes = (int) ($opts['maxResults'] ?? 20);
        $maxRes = max(1, min(20, $maxRes));
        $radius = (float) max(1, min(50000, (int) ($opts['radius'] ?? $s['default_radius'])));

        $body = [
            'languageCode'   => $lang,
            'maxResultCount' => $maxRes,
            'locationRestriction' => ['circle' => [
                'center' => ['latitude' => $lat, 'longitude' => $lng],
                'radius' => $radius,
            ]],
        ];
        if (!empty($opts['includedTypes']) && is_array($opts['includedTypes'])) {
            $body['includedTypes'] = array_values($opts['includedTypes']);
        }

        return $this->call('places:searchNearby', $body, self::FIELD_MASK, 'nearby_search', $opts['searchId'] ?? null);
    }

    /**
     * Tek yerin ayrıntısı (places/{PLACE_ID}). GET.
     * @return array{ok:bool,error:string,place:?array,http:int}
     */
    public function placeDetails(string $placeId, array $opts = []): array
    {
        $ready = $this->isReady();
        if (!$ready['ok']) { return ['ok' => false, 'error' => $ready['error'], 'place' => null, 'http' => 0]; }
        $placeId = trim($placeId);
        if ($placeId === '') { return ['ok' => false, 'error' => 'Geçersiz yer kimliği.', 'place' => null, 'http' => 0]; }

        $s = gp_settings();
        $lang = (string) ($opts['language'] ?? $s['default_language'] ?? 'tr');
        $endpoint = 'places/' . rawurlencode($placeId) . '?languageCode=' . rawurlencode($lang);
        $res = $this->call($endpoint, null, self::DETAILS_MASK, 'place_details', $opts['searchId'] ?? null);
        if (!$res['ok']) { return ['ok' => false, 'error' => $res['error'], 'place' => null, 'http' => $res['http']]; }
        $place = $this->mapPlace($res['raw'] ?? []);
        return ['ok' => true, 'error' => '', 'place' => $place, 'http' => $res['http']];
    }

    /* ------------------------------------------------------------------ *
     |  Çekirdek HTTP çağrısı — anahtar yalnızca başlıkta, log'a yazılmaz.
     * ------------------------------------------------------------------ */

    /**
     * @param ?array $body  null → GET; dizi → JSON POST gövdesi
     * @return array{ok:bool,error:string,places:array,raw:array,http:int,next_page_token:?string}
     */
    private function call(string $endpoint, ?array $body, string $fieldMask, string $opType, ?int $searchId): array
    {
        $key = gp_api_key();
        if ($key === null || $key === '') { return $this->fail('API anahtarı okunamadı.'); }

        if (gp_daily_limit_reached())  { return $this->fail('Günlük Google tarama kotası doldu. Yarın tekrar deneyin.'); }
        if (gp_monthly_limit_reached()) { return $this->fail('Aylık Google tarama kotası doldu.'); }

        $url = self::BASE . $endpoint;
        $headers = [
            'Content-Type: application/json',
            'X-Goog-Api-Key: ' . $key,          // yalnızca başlıkta; loglanmaz
            'X-Goog-FieldMask: ' . $fieldMask,
        ];

        $attempt = 0;
        $lastHttp = 0;
        $lastErr = '';
        $lastGoogleStatus = '';

        while ($attempt <= self::MAX_RETRY) {
            $attempt++;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => self::CONNECT,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_USERAGENT      => 'PoyrazTechPanel/1.0',
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
            }
            $resp  = curl_exec($ch);
            $errno = curl_errno($ch);
            $cerr  = curl_error($ch);
            $http  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $lastHttp = $http;

            // Taşıma hatası → yeniden dene (kontrollü)
            if ($errno !== 0 || $resp === false) {
                $lastErr = 'curl_errno=' . $errno;
                // zaman aşımı/bağlantı — kısa bekleyip yeniden dene
                if ($attempt <= self::MAX_RETRY) { usleep(300000 * $attempt); continue; }
                break;
            }

            $data = json_decode((string) $resp, true);
            if (!is_array($data)) { $data = []; }

            if ($http >= 200 && $http < 300) {
                gp_log_usage($endpoint, $opType, true, $http, '', $this->countPlaces($data), $this->estCost($opType), $searchId, $this->userId);
                gp_settings_mark(true);
                return [
                    'ok' => true, 'error' => '', 'http' => $http,
                    'places' => $this->mapPlaces($data['places'] ?? []),
                    'raw' => $data,
                    'next_page_token' => isset($data['nextPageToken']) ? (string) $data['nextPageToken'] : null,
                ];
            }

            // Google hata gövdesi: {error:{code,status,message}}
            $lastGoogleStatus = (string) ($data['error']['status'] ?? '');
            $lastErr = 'http=' . $http . ' status=' . $lastGoogleStatus;

            // 429 / 5xx → yeniden denenebilir; 4xx (403/400/404) → deneme
            $retryable = ($http === 429 || $http >= 500);
            if ($retryable && $attempt <= self::MAX_RETRY) { usleep(400000 * $attempt); continue; }
            break;
        }

        // Buraya düştüyse başarısız. Teknik ayrıntı güvenli loglanır (ANAHTAR YOK).
        gp_log_usage($endpoint, $opType, false, $lastHttp, $lastGoogleStatus, 0, 0.0, $searchId, $this->userId);
        $friendly = $this->friendlyError($lastHttp, $lastGoogleStatus);
        gp_settings_mark(false, $friendly);
        log_error(sprintf('GooglePlaces %s başarısız: %s (%s)', $opType, $lastErr, $endpoint));
        return $this->fail($friendly, $lastHttp);
    }

    /** Google HTTP/durum kodunu son kullanıcıya dostça Türkçe mesaja çevirir. */
    private function friendlyError(int $http, string $googleStatus): string
    {
        if ($http === 0) {
            return 'Google servisine ulaşılamadı (zaman aşımı / ağ). Lütfen tekrar deneyin.';
        }
        return match (true) {
            $http === 400 => 'Arama isteği geçersiz. Lütfen arama kriterlerini gözden geçirin.',
            $http === 401 || $http === 403 => 'Google API erişimi reddedildi. Anahtar/izin ayarlarını kontrol edin.',
            $http === 404 => 'Aranan kayıt Google tarafında bulunamadı.',
            $http === 429 => 'Google istek sınırı aşıldı. Kısa bir süre sonra tekrar deneyin.',
            $http >= 500 => 'Google servisinde geçici bir hata oluştu. Lütfen tekrar deneyin.',
            default => 'Arama tamamlanamadı. Lütfen daha sonra tekrar deneyin.',
        };
    }

    private function countPlaces(array $data): int
    {
        if (isset($data['places']) && is_array($data['places'])) { return count($data['places']); }
        return isset($data['id']) ? 1 : 0;
    }

    /** İşlem türüne göre yaklaşık maliyet (USD) tahmini — raporlama içindir. */
    private function estCost(string $opType): float
    {
        return match ($opType) {
            'text_search'   => 0.032,
            'nearby_search' => 0.032,
            'place_details' => 0.017,
            default         => 0.0,
        };
    }

    /** @return array<int,array> */
    private function mapPlaces(array $places): array
    {
        $out = [];
        foreach ($places as $p) {
            if (is_array($p)) { $out[] = $this->mapPlace($p); }
        }
        return $out;
    }

    /** Google place nesnesini panel içi normalize edilmiş diziye çevirir. */
    private function mapPlace(array $p): array
    {
        $addr = $this->addressParts($p['addressComponents'] ?? []);
        $website = (string) ($p['websiteUri'] ?? '');
        $hours = '';
        if (!empty($p['regularOpeningHours']['weekdayDescriptions']) && is_array($p['regularOpeningHours']['weekdayDescriptions'])) {
            $hours = implode("\n", array_map('strval', $p['regularOpeningHours']['weekdayDescriptions']));
        }
        return [
            'place_id'       => (string) ($p['id'] ?? ''),
            'name'           => (string) ($p['displayName']['text'] ?? ''),
            'main_category'  => (string) ($p['primaryTypeDisplayName']['text'] ?? ($p['primaryType'] ?? '')),
            'other_categories' => implode(', ', array_slice(array_map('strval', (array) ($p['types'] ?? [])), 0, 8)),
            'phone'          => (string) ($p['nationalPhoneNumber'] ?? ''),
            'intl_phone'     => (string) ($p['internationalPhoneNumber'] ?? ''),
            'website'        => $website,
            'domain'         => lead_domain_from_url($website),
            'maps_url'       => (string) ($p['googleMapsUri'] ?? ''),
            'address'        => (string) ($p['formattedAddress'] ?? ''),
            'country'        => $addr['country'],
            'city'           => $addr['city'],
            'district'       => $addr['district'],
            'neighborhood'   => $addr['neighborhood'],
            'postal_code'    => $addr['postal_code'],
            'lat'            => isset($p['location']['latitude']) ? (float) $p['location']['latitude'] : null,
            'lng'            => isset($p['location']['longitude']) ? (float) $p['location']['longitude'] : null,
            'rating'         => isset($p['rating']) ? (float) $p['rating'] : null,
            'review_count'   => isset($p['userRatingCount']) ? (int) $p['userRatingCount'] : null,
            'working_status' => (string) ($p['businessStatus'] ?? ''),
            'working_hours'  => $hours,
            'raw'            => $p,
        ];
    }

    /** addressComponents'ten ülke/il/ilçe/mahalle/posta kodu ayıklar. */
    private function addressParts(array $components): array
    {
        $out = ['country' => '', 'city' => '', 'district' => '', 'neighborhood' => '', 'postal_code' => ''];
        foreach ($components as $c) {
            if (!is_array($c)) { continue; }
            $types = (array) ($c['types'] ?? []);
            $name  = (string) ($c['longText'] ?? ($c['shortText'] ?? ''));
            if (in_array('country', $types, true))                       { $out['country'] = $name; }
            elseif (in_array('administrative_area_level_1', $types, true)) { $out['city'] = $name; }
            elseif (in_array('administrative_area_level_2', $types, true)) { $out['district'] = $out['district'] ?: $name; }
            elseif (in_array('sublocality', $types, true) || in_array('sublocality_level_1', $types, true)) { $out['neighborhood'] = $name; }
            elseif (in_array('locality', $types, true))                   { $out['district'] = $out['district'] ?: $name; }
            elseif (in_array('postal_code', $types, true))                { $out['postal_code'] = $name; }
        }
        return $out;
    }

    private function fail(string $error, int $http = 0): array
    {
        return ['ok' => false, 'error' => $error, 'places' => [], 'raw' => [], 'http' => $http, 'next_page_token' => null];
    }
}
