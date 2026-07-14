<?php
declare(strict_types=1);

/**
 * modules/leads/places-scan.php — Google Places ile lead tarama (§2,§3,§4).
 * Form → tarama çalıştır → önizleme (kopya işaretli) → seç → kaydet.
 * Google anahtarı hiçbir zaman istemciye gönderilmez; tüm çağrılar backend.
 */

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/lead_scan_places.php';
require_once __DIR__ . '/../../includes/personnel.php';

auth_boot();
require_permission('leads.create');

$s = gp_settings();
$people = get_personnel_options(false);
$searchId = (int) ($_GET['search_id'] ?? 0);
$runSummary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $res = gp_scan_run([
        'search_type'        => $_POST['search_type'] ?? 'text',
        'keyword'            => $_POST['keyword'] ?? '',
        'country'            => $_POST['country'] ?? '',
        'city'               => $_POST['city'] ?? '',
        'district'           => $_POST['district'] ?? '',
        'radius'             => $_POST['radius'] ?? $s['default_radius'],
        'max_results'        => $_POST['max_results'] ?? $s['max_results'],
        'language'           => $_POST['language'] ?? $s['default_language'],
        'lat'                => $_POST['lat'] ?? '',
        'lng'                => $_POST['lng'] ?? '',
        'include_no_phone'   => isset($_POST['include_no_phone']) ? 1 : 0,
        'include_no_website' => isset($_POST['include_no_website']) ? 1 : 0,
        'only_no_website'    => isset($_POST['only_no_website']) ? 1 : 0,
        'min_rating'         => $_POST['min_rating'] ?? 0,
    ], current_user_id());

    if ($res['ok']) {
        $sum = $res['summary'];
        flash('success', sprintf('Tarama tamamlandı: %d sonuç (%d yeni, %d kopya).', $sum['total'], $sum['new'], $sum['dup']));
        if (!empty($sum['warning'])) { flash('warning', 'Uyarı: ' . $sum['warning']); }
        http_response_code(303);
        redirect('modules/leads/places-scan.php?search_id=' . $res['search_id']);
    } else {
        flash('error', $res['error']);
        http_response_code(303);
        redirect('modules/leads/places-scan.php');
    }
}

$search  = $searchId > 0 ? gp_get_search($searchId) : null;
$results = $search ? gp_get_search_results($searchId) : [];

layout_top('Lead Tara (Google Places)', 'leads');
?>
<div class="page-head">
    <h1 class="page-title">Lead Tara <span class="muted" style="font-weight:400">· Google Places</span></h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/leads/places-searches.php')) ?>"><?= icon('history') ?>Tarama Geçmişi</a>
        <a class="btn btn-sm" href="<?= e(url('modules/leads/index.php')) ?>"><?= icon('arrow-left') ?>Lead Listesi</a>
    </div>
</div>
<?= render_flashes() ?>

<?php if (empty($s['is_active']) || !gp_has_api_key()): ?>
    <div class="alert alert-warning" style="max-width:900px">
        Google Places taraması henüz hazır değil.
        <?php if (can('settings')): ?>
            <a href="<?= e(url('modules/settings/lead-scan-settings.php')) ?>">Ayarlardan</a> API anahtarını tanımlayıp etkinleştirin.
        <?php else: ?>
            Sistem yöneticisinin ayarlardan API anahtarını tanımlaması gerekiyor.
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card" style="max-width:900px"><div class="card-header"><h2>Arama Kriterleri</h2></div><div class="card-body">
    <form method="post" action="<?= e(url('modules/leads/places-scan.php')) ?>" id="scanForm">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group"><label for="search_type">Arama tipi</label>
                <select id="search_type" name="search_type">
                    <option value="text">Metin araması (kelime + bölge)</option>
                    <option value="nearby">Yakın çevre (konum + yarıçap)</option>
                </select>
            </div>
            <div class="form-group" style="flex:2"><label for="keyword">Arama kelimesi</label>
                <input type="text" id="keyword" name="keyword" placeholder="Örn. dişçi, kuaför, oto tamir" autofocus>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="country">Ülke</label><input type="text" id="country" name="country" value="<?= e((string) $s['default_country']) ?>"></div>
            <div class="form-group"><label for="city">Şehir</label><input type="text" id="city" name="city" value="<?= e((string) $s['default_city']) ?>" placeholder="İzmir"></div>
            <div class="form-group"><label for="district">İlçe / semt</label><input type="text" id="district" name="district" placeholder="Bornova"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="lat">Enlem (nearby)</label><input type="text" id="lat" name="lat" placeholder="38.4192" inputmode="decimal"></div>
            <div class="form-group"><label for="lng">Boylam (nearby)</label><input type="text" id="lng" name="lng" placeholder="27.1287" inputmode="decimal"></div>
            <div class="form-group"><label for="radius">Yarıçap (m)</label><input type="number" id="radius" name="radius" min="50" max="50000" value="<?= e((string) $s['default_radius']) ?>"></div>
            <div class="form-group"><label for="max_results">Maks. sonuç</label><input type="number" id="max_results" name="max_results" min="1" max="200" value="<?= e((string) $s['max_results']) ?>"></div>
        </div>
        <div class="form-group">
            <label class="checkbox"><input type="checkbox" name="include_no_phone" value="1" <?= !empty($s['include_no_phone']) ? 'checked' : '' ?>> Telefonu olmayanları dahil et</label>
            <label class="checkbox"><input type="checkbox" name="include_no_website" value="1" <?= !empty($s['include_no_website']) ? 'checked' : '' ?>> Web sitesi olmayanları dahil et</label>
            <label class="checkbox"><input type="checkbox" name="only_no_website" value="1"> Yalnızca web sitesi olmayanlar</label>
            <label class="checkbox" style="margin-left:12px">Min. puan <input type="number" name="min_rating" value="0" min="0" max="5" step="0.1" style="width:70px;margin-left:6px"></label>
        </div>
        <button type="submit" class="btn btn-primary"><?= icon('search') ?>Taramayı Başlat</button>
        <span class="field-hint" style="margin-left:10px">Sonuçlar önce önizlenir; lead'e dönüştürmeden önce seçim yaparsınız.</span>
    </form>
</div></div>

<?php if ($search): ?>
    <div class="card" style="margin-top:18px"><div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">Önizleme · #<?= (int) $search['id'] ?>
            <span class="muted" style="font-weight:400;font-size:13px">
                “<?= e((string) $search['keyword']) ?>” · <?= e(trim(($search['district'] ? $search['district'] . ', ' : '') . $search['city'] . ' ' . $search['country'])) ?>
                · <?= (int) $search['result_count'] ?> sonuç, <?= (int) $search['new_count'] ?> yeni, <?= (int) $search['dup_count'] ?> kopya
            </span>
        </h2>
    </div><div class="card-body">
    <?php if (!$results): ?>
        <div class="empty">Bu aramada sonuç yok.</div>
    <?php else: ?>
        <form method="post" action="<?= e(url('modules/leads/places-scan-save.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="search_id" value="<?= (int) $search['id'] ?>">
            <input type="hidden" name="keyword" value="<?= e((string) $search['keyword']) ?>">
            <div class="toolbar" style="gap:14px;align-items:flex-end;flex-wrap:wrap">
                <div class="form-group"><label>Sorumlu personel</label>
                    <select name="assigned_personnel_id"><option value="0">— Yok —</option>
                        <?php foreach ($people as $pid => $pname): ?><option value="<?= (int) $pid ?>"><?= e($pname) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Başlangıç durumu</label>
                    <select name="status"><?php foreach (lead_statuses(true) as $k => $lab): ?><option value="<?= e($k) ?>"<?= $k === 'new' ? ' selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?></select>
                </div>
                <div class="form-group"><label>Paket / not</label><input type="text" name="package" placeholder="opsiyonel"></div>
                <div class="form-group"><label>Kaynak</label><input type="text" name="source" value="google_places"></div>
            </div>
            <div class="form-group">
                <label class="check-inline"><input type="checkbox" id="selAll"> Tümünü seç</label>
                <label class="check-inline" style="margin-left:14px"><input type="checkbox" name="allow_duplicates" value="1"> Kopyaları da kaydet</label>
            </div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th style="width:34px"></th><th>Firma</th><th>Kategori</th><th class="nowrap">Telefon</th><th>Web</th><th>Şehir/İlçe</th><th class="nowrap">Puan</th><th>Durum</th></tr></thead>
                <tbody>
                <?php foreach ($results as $r):
                    $dup = (int) $r['is_duplicate'] === 1;
                    $saved = (int) ($r['saved_lead_id'] ?? 0) > 0; ?>
                    <tr class="<?= $dup ? 'row-muted' : '' ?>">
                        <td><?php if ($saved): ?><?= icon('check', 'icon-xs') ?><?php else: ?><input type="checkbox" name="result_ids[]" value="<?= (int) $r['id'] ?>" class="rowChk" <?= $dup ? '' : 'checked' ?>><?php endif; ?></td>
                        <td><strong><?= e((string) $r['name']) ?></strong><?php if ($r['maps_url']): ?> <a href="<?= e((string) $r['maps_url']) ?>" target="_blank" rel="noopener" title="Google Haritalar"><?= icon('map-pin', 'icon-xs') ?></a><?php endif; ?>
                            <div class="muted" style="font-size:12px"><?= e((string) $r['address']) ?></div></td>
                        <td><?= e((string) $r['main_category']) ?: '—' ?></td>
                        <td class="nowrap"><?= e((string) $r['phone']) ?: '<span class="muted">—</span>' ?></td>
                        <td><?php if ($r['website']): ?><a href="<?= e((string) $r['website']) ?>" target="_blank" rel="noopener">web</a><?php else: ?><span class="muted">yok</span><?php endif; ?></td>
                        <td><?= e(trim(((string) $r['district'] ? $r['district'] . ' / ' : '') . (string) $r['city'])) ?: '—' ?></td>
                        <td class="nowrap"><?= $r['rating'] !== null ? e(number_format((float) $r['rating'], 1)) . ' <span class="muted">(' . (int) $r['review_count'] . ')</span>' : '—' ?></td>
                        <td><?php if ($saved): ?><span class="badge badge-success">Kaydedildi</span><?php elseif ($dup): ?><a class="badge badge-warning" href="<?= e(url('modules/leads/view.php?id=' . (int) $r['dup_lead_id'])) ?>" title="Mevcut lead">Kopya</a><?php else: ?><span class="badge badge-info">Yeni</span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary"><?= icon('user-plus') ?>Seçilenleri Lead Olarak Kaydet</button></div>
        </form>
        <script>
        (function(){
            var all = document.getElementById('selAll');
            if (all) all.addEventListener('change', function(){
                document.querySelectorAll('.rowChk').forEach(function(c){ c.checked = all.checked; });
            });
        })();
        </script>
    <?php endif; ?>
    </div></div>
<?php endif; ?>
<?php layout_bottom();
