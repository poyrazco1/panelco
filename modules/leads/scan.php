<?php
declare(strict_types=1);

/**
 * modules/leads/scan.php — Yeni Lead Tara (modern lead arama sihirbazı).
 * Sektör/meslek/kelime → bölge → kalite filtreleri → satış ayarı → önizleme.
 * Tüm seçim durumu JS'te tutulur; sağ sticky panel canlı güncellenir. Başlat
 * → scan-start.php'ye JSON config POST edilir (tarama işi oluşur).
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/lead-scan.php';

auth_boot();
require_permission('leads.create');

$taxonomy    = lead_scan_taxonomy();
$professions = lead_scan_all_professions();
$popular     = lead_scan_popular_districts();
$cities      = lead_scan_cities();
$packages    = lead_scan_packages();
$priorities  = lead_scan_priorities();
$qFilters    = lead_scan_quality_filters();
$suggestions = lead_scan_keyword_suggestions();
$waTemplate  = lead_wa_template();
$targetDefault = (int) app_setting_get('lead_scan_target_default', '100');

$personnel = [];
try { foreach (db()->query('SELECT id, full_name FROM personnel WHERE is_active = 1 ORDER BY full_name')->fetchAll() as $p) { $personnel[(int) $p['id']] = (string) $p['full_name']; } } catch (Throwable $e) {}

$JS = static fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

layout_top('Yeni Lead Tara', 'leads');
?>
<div class="page-head">
    <div>
        <h1 class="page-title">Yeni Lead Tara</h1>
        <p class="muted" style="margin:2px 0 0">Sektör, bölge ve filtreleri seç; uygun işletmeleri CRM'e aktar.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/leads/scans.php')) ?>"><?= icon('list-checks') ?>Taramalar</a>
        <a class="btn btn-sm" href="<?= e(url('modules/leads/index.php')) ?>">← Lead Yönetimi</a>
    </div>
</div>
<?= render_flashes() ?>

<!-- Üst mini özet -->
<div class="scan-topstats">
    <div class="scan-topstat"><span class="scan-topstat-val" id="tsSector">0</span><span class="scan-topstat-lbl">Sektör / Kelime</span></div>
    <div class="scan-topstat"><span class="scan-topstat-val" id="tsRegion">0</span><span class="scan-topstat-lbl">Bölge</span></div>
    <div class="scan-topstat"><span class="scan-topstat-val" id="tsCombo">0</span><span class="scan-topstat-lbl">Tahmini Arama</span></div>
    <div class="scan-topstat"><span class="scan-topstat-val" id="tsLimit"><?= (int) $targetDefault ?></span><span class="scan-topstat-lbl">Hedef Limit</span></div>
</div>

<div class="scan-layout">
    <!-- SOL: adımlar -->
    <div class="scan-main">
        <!-- ADIM 1 — SEKTÖR & HİZMET -->
        <section class="step-card">
            <header class="step-head"><span class="step-number">1</span><h2 class="step-title">Sektör & Hizmet Seç</h2></header>
            <div class="step-body">
                <div class="scan-tabs" id="secTabs">
                    <button type="button" class="scan-tab is-active" data-tab="sectors">Hazır Sektörler</button>
                    <button type="button" class="scan-tab" data-tab="prof">Meslekler</button>
                    <button type="button" class="scan-tab" data-tab="kw">Serbest Kelimeler</button>
                </div>

                <!-- Hazır Sektörler -->
                <div class="scan-pane is-active" data-pane="sectors">
                    <input type="search" class="scan-search" id="secSearch" placeholder="Sektör, meslek veya anahtar kelime ara">
                    <div class="sector-browser">
                        <div class="sector-mains" id="sectorMains"></div>
                        <div class="sector-detail">
                            <div class="sector-subs" id="sectorSubs"></div>
                            <div class="sector-profs" id="sectorProfs"></div>
                        </div>
                    </div>
                </div>

                <!-- Meslekler -->
                <div class="scan-pane" data-pane="prof">
                    <input type="search" class="scan-search" id="profSearch" placeholder="Meslek ara">
                    <div class="scan-toolbtns">
                        <button type="button" class="btn btn-xs" id="profAll">Tümünü seç</button>
                        <button type="button" class="btn btn-xs" id="profClear">Seçimi temizle</button>
                    </div>
                    <div class="selection-grid" id="profGrid"></div>
                </div>

                <!-- Serbest Kelimeler -->
                <div class="scan-pane" data-pane="kw">
                    <div class="kw-input-row">
                        <input type="text" class="scan-search" id="kwInput" placeholder="Anahtar kelime yaz ve Enter'a bas">
                        <button type="button" class="btn btn-sm" id="kwAdd"><?= icon('plus') ?>Ekle</button>
                    </div>
                    <div class="kw-suggest" id="kwSuggest"></div>
                </div>

                <div class="selected-block">
                    <div class="selected-block-head"><span>Seçilen sektör / meslek / kelimeler</span>
                        <button type="button" class="btn btn-xs" id="clearTerms">Tümünü temizle</button></div>
                    <div class="chips" id="termChips"><span class="chips-empty">Henüz seçim yok.</span></div>
                </div>
            </div>
        </section>

        <!-- ADIM 2 — BÖLGE -->
        <section class="step-card">
            <header class="step-head"><span class="step-number">2</span><h2 class="step-title">Bölge Seç</h2></header>
            <div class="step-body">
                <div class="form-row">
                    <div class="form-group"><label for="citySel">Şehir</label>
                        <select id="citySel"><option value="">— Şehir seç —</option>
                            <?php foreach ($cities as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label for="hoodInput">Mahalle / Semt (opsiyonel)</label>
                        <input type="text" id="hoodInput" placeholder="Örn. Caferağa"></div>
                </div>
                <div class="region-quick" id="regionQuick" hidden>
                    <div class="selected-block-head"><span>Popüler ilçeler</span>
                        <span class="scan-toolbtns"><button type="button" class="btn btn-xs" id="distAll">Tüm ilçeleri seç</button>
                        <button type="button" class="btn btn-xs" id="distClear">Temizle</button></span></div>
                    <div class="pill-row" id="popularDistricts"></div>
                </div>
                <div class="kw-input-row" style="margin-top:10px">
                    <input type="text" class="scan-search" id="manualRegion" placeholder="Manuel ilçe/bölge yaz ve Enter'a bas">
                    <button type="button" class="btn btn-sm" id="manualRegionAdd"><?= icon('plus') ?>Ekle</button>
                </div>
                <div class="selected-block">
                    <div class="selected-block-head"><span>Seçilen bölgeler</span></div>
                    <div class="chips" id="regionChips"><span class="chips-empty">Henüz bölge seçilmedi.</span></div>
                </div>
            </div>
        </section>

        <!-- ADIM 3 — KALİTE FİLTRELERİ -->
        <section class="step-card">
            <header class="step-head"><span class="step-number">3</span><h2 class="step-title">Lead Kalite Filtreleri</h2></header>
            <div class="step-body">
                <div class="quality-filter-grid" id="qGrid">
                    <?php foreach ($qFilters as $fk => $meta): ?>
                        <label class="qfilter<?= !empty($meta['default']) ? ' is-on' : '' ?>" data-fk="<?= e($fk) ?>">
                            <input type="checkbox" data-filter="<?= e($fk) ?>"<?= !empty($meta['default']) ? ' checked' : '' ?>>
                            <span class="qfilter-text"><?= e($meta['label']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="form-row" style="margin-top:12px;max-width:520px">
                    <div class="form-group"><label for="minRating">Minimum Google puanı</label>
                        <select id="minRating">
                            <option value="0">Fark etmez</option>
                            <?php foreach (['3.0','3.5','4.0','4.5'] as $r): ?><option value="<?= $r ?>"><?= $r ?> ve üzeri</option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label for="minReviews">Minimum yorum sayısı</label>
                        <input type="number" id="minReviews" min="0" value="0"></div>
                </div>
            </div>
        </section>

        <!-- ADIM 4 — SATIŞ AYARI -->
        <section class="step-card">
            <header class="step-head"><span class="step-number">4</span><h2 class="step-title">Satış Ayarı</h2></header>
            <div class="step-body">
                <div class="form-row">
                    <div class="form-group"><label for="repSel">Satış temsilcisi</label>
                        <select id="repSel"><option value="0">— Seçilmedi —</option>
                            <?php foreach ($personnel as $pid => $pn): ?><option value="<?= (int) $pid ?>"><?= e($pn) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="pkgSel">İlgili paket</label>
                        <select id="pkgSel"><option value="">— Seçilmedi —</option>
                            <?php foreach ($packages as $pk => $pl): ?><option value="<?= e($pk) ?>"><?= e($pl) ?></option><?php endforeach; ?>
                        </select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="prioSel">Öncelik başlangıcı</label>
                        <select id="prioSel"><?php foreach ($priorities as $pk => $pl): ?><option value="<?= e($pk) ?>"><?= e($pl) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label for="limitInput">Hedef kayıt limiti</label>
                        <input type="number" id="limitInput" min="1" value="<?= (int) $targetDefault ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="sourceInput">Lead kaynağı</label>
                        <input type="text" id="sourceInput" value="Lead Tarama"></div>
                </div>
                <div class="form-group"><label for="waInput">İlk WhatsApp mesaj şablonu</label>
                    <textarea id="waInput" rows="2"><?= e($waTemplate) ?></textarea>
                    <div class="field-hint">Değişkenler: <code>{firma}</code>, <code>{yetkili}</code>, <code>{sehir}</code></div>
                </div>
                <div class="form-group"><label for="noteInput">Not</label><textarea id="noteInput" rows="2"></textarea></div>
            </div>
        </section>

        <!-- ADIM 5 — ÖN İZLEME -->
        <section class="step-card">
            <header class="step-head"><span class="step-number">5</span><h2 class="step-title">Ön İzleme & Başlat</h2></header>
            <div class="step-body">
                <div class="preview-box" id="previewBox"></div>
                <div class="step-actions">
                    <button type="button" class="btn btn-primary" id="startBtnMain" disabled><?= icon('search') ?>Lead Taramayı Başlat</button>
                    <button type="button" class="btn" id="saveBtn"><?= icon('check-circle') ?>Seçimi Kaydet</button>
                    <button type="button" class="btn btn-ghost" id="clearAllBtn"><?= icon('rotate-ccw') ?>Temizle</button>
                </div>
            </div>
        </section>
    </div>

    <!-- SAĞ: sticky özet -->
    <aside class="scan-side">
        <div class="sticky-summary" id="stickySummary">
            <h3 class="sum-title">Tarama Özeti</h3>
            <div class="sum-sec"><span class="sum-label">Sektör / Kelime (<b id="sumTermCount">0</b>)</span><div class="sum-chips" id="sumTerms"><span class="chips-empty">—</span></div></div>
            <div class="sum-sec"><span class="sum-label">Bölge (<b id="sumRegionCount">0</b>)</span><div class="sum-chips" id="sumRegions"><span class="chips-empty">—</span></div></div>
            <div class="sum-sec"><span class="sum-label">Filtreler</span><div class="sum-chips" id="sumFilters"><span class="chips-empty">—</span></div></div>
            <div class="sum-grid">
                <div><span class="sum-k">Paket</span><span class="sum-v" id="sumPkg">—</span></div>
                <div><span class="sum-k">Temsilci</span><span class="sum-v" id="sumRep">—</span></div>
                <div><span class="sum-k">Kombinasyon</span><span class="sum-v" id="sumCombo">0</span></div>
                <div><span class="sum-k">Limit</span><span class="sum-v" id="sumLimit"><?= (int) $targetDefault ?></span></div>
            </div>
            <div class="sum-missing" id="sumMissing"></div>
            <button type="button" class="btn btn-primary btn-block" id="startBtn" disabled><?= icon('search') ?>Lead Taramayı Başlat</button>
        </div>
    </aside>
</div>

<!-- Gizli gönderim formu -->
<form method="post" action="<?= e(url('modules/leads/scan-start.php')) ?>" id="scanPost" hidden>
    <?= csrf_field() ?>
    <input type="hidden" name="terms" id="fTerms">
    <input type="hidden" name="city" id="fCity">
    <input type="hidden" name="districts" id="fDistricts">
    <input type="hidden" name="neighborhood" id="fHood">
    <input type="hidden" name="filters" id="fFilters">
    <input type="hidden" name="min_rating" id="fMinRating">
    <input type="hidden" name="min_reviews" id="fMinReviews">
    <input type="hidden" name="package" id="fPackage">
    <input type="hidden" name="priority" id="fPriority">
    <input type="hidden" name="assigned_personnel_id" id="fRep">
    <input type="hidden" name="wa_template" id="fWa">
    <input type="hidden" name="target_limit" id="fLimit">
    <input type="hidden" name="source" id="fSource">
    <input type="hidden" name="note" id="fNote">
</form>

<script>
window.SCAN_DATA = {
    taxonomy: <?= $JS($taxonomy) ?>,
    professions: <?= $JS($professions) ?>,
    popular: <?= $JS($popular) ?>,
    suggestions: <?= $JS($suggestions) ?>,
    packages: <?= $JS($packages) ?>,
    filters: <?= $JS(array_map(fn($m) => $m['label'], $qFilters)) ?>,
    personnel: <?= $JS($personnel) ?>
};
</script>
<script src="<?= e(asset('js/lead-scan.js')) ?>"></script>
<?php layout_bottom();
