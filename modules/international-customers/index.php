<?php
declare(strict_types=1);

/**
 * modules/international-customers/index.php
 * Yurtdışı müşteri listesi — özet kartları + detaylı filtre + kayıtlı filtreler
 * + seçili/filtreli kayıtlara toplu mesaj hazırlama (onaylı, spam yok).
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers.php';

auth_boot();
require_permission('international_customers.view');

$uid = current_user_id();

// Kayıtlı filtre yükle
$loadFilter = (int) ($_GET['load_filter'] ?? 0);
if ($loadFilter > 0) {
    $sf = ic_saved_filter_get($loadFilter);
    if ($sf) {
        $p = json_decode((string) $sf['params'], true);
        if (is_array($p)) { $qs = http_build_query($p); redirect('modules/international-customers/index.php' . ($qs ? '?' . $qs : '')); }
    }
    redirect('modules/international-customers/index.php');
}

$arr = static fn(string $k): array => isset($_GET[$k]) ? array_values(array_filter((array) $_GET[$k], static fn($v) => $v !== '')) : [];

$f = [
    'search'               => trim((string) ($_GET['q'] ?? '')),
    'country'              => $arr('country'),
    'city'                 => trim((string) ($_GET['city'] ?? '')),
    'company_role'         => $arr('company_role'),
    'status_id'            => $arr('status_id'),
    'company_type_id'      => $arr('company_type_id'),
    'category_id'          => $arr('category_id'),
    'brand'                => trim((string) ($_GET['brand'] ?? '')),
    'data_source'          => trim((string) ($_GET['data_source'] ?? '')),
    'event_name'           => trim((string) ($_GET['event_name'] ?? '')),
    'contact_permission'   => (string) ($_GET['contact_permission'] ?? ''),
    'has_email'            => !empty($_GET['has_email']) ? 1 : 0,
    'has_phone'            => !empty($_GET['has_phone']) ? 1 : 0,
    'include_blacklist'    => !empty($_GET['include_blacklist']) ? 1 : 0,
    'include_uninterested' => !empty($_GET['include_uninterested']) ? 1 : 0,
    'created_from'         => trim((string) ($_GET['created_from'] ?? '')),
    'created_to'           => trim((string) ($_GET['created_to'] ?? '')),
];

$rows     = ic_list($f, 500);
$total    = ic_count($f);
$counts   = ic_summary_counts();
$statuses = ic_statuses();
$types    = ic_company_types();
$cats     = ic_categories();
$countries = ic_distinct_countries();
$saved    = ic_saved_filters($uid);

// Aktif filtre var mı? (varsayılan dışı)
$hasFilters = $f['search'] !== '' || $f['country'] || $f['city'] !== '' || $f['company_role'] || $f['status_id']
    || $f['company_type_id'] || $f['category_id'] || $f['brand'] !== '' || $f['data_source'] !== ''
    || $f['event_name'] !== '' || $f['contact_permission'] !== '' || $f['has_email'] || $f['has_phone']
    || $f['include_blacklist'] || $f['include_uninterested'] || $f['created_from'] !== '' || $f['created_to'] !== '';

layout_top('Yurtdışı Müşteriler', 'international_customers');
?>
<div class="page-head">
    <h1 class="page-title">Yurtdışı Müşteriler</h1>
    <div class="page-actions">
        <?php if (can('international_customers.import')): ?><a class="btn btn-sm" href="<?= e(url('modules/international-customers/import.php')) ?>"><?= icon('upload') ?>İçe Aktar</a><?php endif; ?>
        <?php if (can('international_customers.export')): ?><a class="btn btn-sm" href="<?= e(url('modules/international-customers/export.php?' . http_build_query($_GET))) ?>"><?= icon('download') ?>Dışa Aktar</a><?php endif; ?>
        <?php if (can('international_customers.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/international-customers/create.php')) ?>"><?= icon('plus') ?>Yeni Müşteri</a><?php endif; ?>
    </div>
</div>
<?= render_flashes() ?>

<div class="stat-row">
    <div class="stat-card"><span class="stat-val"><?= (int) $counts['all'] ?></span><span class="stat-lbl">Aktif Müşteri</span></div>
    <div class="stat-card"><span class="stat-val"><?= (int) $counts['buyer'] ?></span><span class="stat-lbl">Alıcı</span></div>
    <div class="stat-card"><span class="stat-val"><?= (int) $counts['seller'] ?></span><span class="stat-lbl">Satıcı</span></div>
    <div class="stat-card"><span class="stat-val"><?= (int) $counts['both'] ?></span><span class="stat-lbl">Alıcı+Satıcı</span></div>
    <div class="stat-card"><span class="stat-val"><?= (int) $counts['blacklist'] ?></span><span class="stat-lbl">Kara Liste</span></div>
    <div class="stat-card"><span class="stat-val"><?= (int) $counts['no_permission'] ?></span><span class="stat-lbl">İzinsiz</span></div>
</div>

<?php if ($saved): ?>
<div class="saved-filter-row">
    <span class="muted small">Kayıtlı filtreler:</span>
    <?php foreach ($saved as $sf): ?>
        <a class="mini-chip" href="<?= e(url('modules/international-customers/index.php?load_filter=' . (int) $sf['id'])) ?>"><?= icon('filter', 'icon-xs') ?><?= e($sf['name']) ?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<details class="filter-panel"<?= $hasFilters ? ' open' : '' ?>>
    <summary><?= icon('filter') ?>Detaylı Filtre<?= $hasFilters ? ' <span class="badge badge-info">aktif</span>' : '' ?></summary>
    <form method="get" action="<?= e(url('modules/international-customers/index.php')) ?>" class="ic-filter-form">
        <div class="form-row">
            <div class="form-group grow"><label for="q">Ara (firma, web, not, kişi, marka, kategori, ülke…)</label>
                <input type="text" id="q" name="q" value="<?= e($f['search']) ?>" placeholder="Tam metin arama"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Ülke</label>
                <select name="country[]" multiple size="4">
                    <?php foreach ($countries as $co): ?><option value="<?= e($co) ?>"<?= in_array($co, $f['country'], true) ? ' selected' : '' ?>><?= e($co) ?></option><?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>Şehir</label><input type="text" name="city" value="<?= e($f['city']) ?>"></div>
            <div class="form-group"><label>Rol</label>
                <select name="company_role[]" multiple size="4">
                    <?php foreach (ic_company_roles() as $k => $l): ?><option value="<?= e($k) ?>"<?= in_array($k, $f['company_role'], true) ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                </select></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Durum</label>
                <select name="status_id[]" multiple size="4">
                    <?php foreach ($statuses as $s): ?><option value="<?= (int) $s['id'] ?>"<?= in_array((string) $s['id'], $f['status_id'], true) ? ' selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>Şirket Tipi</label>
                <select name="company_type_id[]" multiple size="4">
                    <?php foreach ($types as $t): ?><option value="<?= (int) $t['id'] ?>"<?= in_array((string) $t['id'], $f['company_type_id'], true) ? ' selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>Kategori</label>
                <select name="category_id[]" multiple size="4">
                    <?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>"<?= in_array((string) $c['id'], $f['category_id'], true) ? ' selected' : '' ?>><?= e(ic_category_label((int) $c['id'], $cats)) ?></option><?php endforeach; ?>
                </select></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Marka</label><input type="text" name="brand" value="<?= e($f['brand']) ?>" placeholder="HP, Canon…"></div>
            <div class="form-group"><label>Veri Kaynağı</label><input type="text" name="data_source" value="<?= e($f['data_source']) ?>"></div>
            <div class="form-group"><label>Fuar / Etkinlik</label><input type="text" name="event_name" value="<?= e($f['event_name']) ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>İletişim İzni</label>
                <select name="contact_permission">
                    <option value="">Farketmez</option>
                    <option value="1"<?= $f['contact_permission'] === '1' ? ' selected' : '' ?>>İzinli</option>
                    <option value="0"<?= $f['contact_permission'] === '0' ? ' selected' : '' ?>>İzinsiz</option>
                </select></div>
            <div class="form-group"><label>Eklenme (baş.)</label><input type="date" name="created_from" value="<?= e($f['created_from']) ?>"></div>
            <div class="form-group"><label>Eklenme (bit.)</label><input type="date" name="created_to" value="<?= e($f['created_to']) ?>"></div>
        </div>
        <div class="form-row filter-checks">
            <label class="check-inline"><input type="checkbox" name="has_email" value="1"<?= $f['has_email'] ? ' checked' : '' ?>> E-postası var</label>
            <label class="check-inline"><input type="checkbox" name="has_phone" value="1"<?= $f['has_phone'] ? ' checked' : '' ?>> Telefonu var</label>
            <label class="check-inline"><input type="checkbox" name="include_uninterested" value="1"<?= $f['include_uninterested'] ? ' checked' : '' ?>> İlgilenmeyenler dahil</label>
            <label class="check-inline"><input type="checkbox" name="include_blacklist" value="1"<?= $f['include_blacklist'] ? ' checked' : '' ?>> Kara liste dahil</label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-sm"><?= icon('filter') ?>Filtrele</button>
            <a class="btn btn-sm" href="<?= e(url('modules/international-customers/index.php')) ?>">Temizle</a>
        </div>
    </form>
</details>

<?php if (can('international_customers.filter') && $hasFilters): ?>
<form method="post" action="<?= e(url('modules/international-customers/filter-save.php')) ?>" class="save-filter-inline">
    <?= csrf_field() ?>
    <?php foreach ($_GET as $gk => $gv): if (in_array($gk, ['load_filter'], true)) continue;
        foreach ((array) $gv as $vv): ?><input type="hidden" name="params[<?= e($gk) ?>][]" value="<?= e((string) $vv) ?>"><?php endforeach; endforeach; ?>
    <input type="text" name="name" placeholder="Filtre adı (örn. Almanya yazıcı alıcıları)" required>
    <label class="check-inline"><input type="checkbox" name="is_shared" value="1"> Herkese açık</label>
    <button type="submit" class="btn btn-sm"><?= icon('plus') ?>Bu filtreyi kaydet</button>
</form>
<?php endif; ?>

<?php if (!$rows): ?>
    <div class="card"><div class="card-body"><div class="empty">Müşteri bulunamadı.</div></div></div>
<?php else: ?>
<form method="get" action="<?= e(url('modules/international-customers/message.php')) ?>" id="bulkForm">
    <div class="table-toolbar">
        <span class="muted small"><?= (int) $total ?> kayıt<?= $total > count($rows) ? ' (ilk ' . count($rows) . ' gösteriliyor)' : '' ?></span>
        <?php if (can('international_customers.message_mail') || can('international_customers.message_whatsapp')): ?>
            <button type="submit" class="btn btn-sm"><?= icon('send') ?>Seçililere Mesaj Hazırla</button>
            <label class="check-inline small"><input type="checkbox" id="selAll"> Tümünü seç</label>
        <?php endif; ?>
    </div>
    <div class="table-wrap"><table class="table">
        <thead><tr>
            <th style="width:28px"></th><th>Kayıt No / Firma</th><th>Ülke / Şehir</th><th>Rol</th>
            <th>Durum</th><th>İletişim</th><th class="nowrap">İşlem</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $bl = ic_is_blacklisted($r); ?>
            <tr<?= $bl ? ' class="row-muted"' : '' ?>>
                <td><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" class="rowchk"></td>
                <td>
                    <a href="<?= e(url('modules/international-customers/view.php?id=' . (int) $r['id'])) ?>"><strong><?= e($r['company_name']) ?></strong></a>
                    <div class="small muted"><?= e($r['record_no']) ?><?php if ($bl): ?> · <span class="badge badge-danger">Kara Liste</span><?php endif; ?></div>
                </td>
                <td><?= e((string) ($r['country'] ?? '')) ?: '—' ?><?php if (!empty($r['city'])): ?><div class="small muted"><?= e($r['city']) ?></div><?php endif; ?></td>
                <td><span class="badge badge-info"><?= e(ic_company_role_label((string) $r['company_role'])) ?></span></td>
                <td><?php if (!empty($r['status_name'])): ?><span class="badge <?= e(ic_status_badge_class((string) $r['status_color'])) ?>"><?= e($r['status_name']) ?></span><?php else: ?>—<?php endif; ?></td>
                <td class="small">
                    <?php if (!empty($r['email'])): ?><?= e($r['email']) ?><br><?php endif; ?>
                    <?= e((string) ($r['phone'] ?? '')) ?>
                    <?php if ((int) $r['contact_permission'] === 0): ?> <span class="badge badge-muted" title="İletişim izni yok">İzinsiz</span><?php endif; ?>
                </td>
                <td class="nowrap">
                    <a class="btn btn-xs" href="<?= e(url('modules/international-customers/view.php?id=' . (int) $r['id'])) ?>"><?= icon('eye', 'icon-xs') ?></a>
                    <?php if (can('international_customers.edit')): ?><a class="btn btn-xs" href="<?= e(url('modules/international-customers/edit.php?id=' . (int) $r['id'])) ?>"><?= icon('pencil', 'icon-xs') ?></a><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</form>
<script>
(function(){
    var all=document.getElementById('selAll');
    if(all){all.addEventListener('change',function(){document.querySelectorAll('.rowchk').forEach(function(c){c.checked=all.checked;});});}
})();
</script>
<?php endif; ?>
<?php layout_bottom();
