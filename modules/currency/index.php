<?php
declare(strict_types=1);

/**
 * modules/currency/index.php
 * Kur çevirici sayfası. Veri kur.php'den gelir; anlık hesap JS ile yapılır.
 * JS kapalıysa form converter.php'ye gönderilir (sunucu tarafı hesap).
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../kur.php';

auth_boot();
require_permission('currency');

$force   = isset($_GET['refresh']);
$rates   = kur_get_rates($force);
$symbols = kur_symbols();
$source  = kur_source_label($rates['source'] ?? '');

$usdTry = kur_convert($rates, 1, 'USD', 'TRY');
$eurTry = kur_convert($rates, 1, 'EUR', 'TRY');
$eurUsd = kur_convert($rates, 1, 'EUR', 'USD');

layout_top('Kur Çevirici', 'currency');
?>

<div class="page-head">
    <h1 class="page-title">Kur Çevirici</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/currency/index.php?refresh=1')) ?>">Kurları yenile</a>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h2>TRY / USD / EUR Çevirici</h2></div>
        <div class="card-body">
            <form id="converter" method="post" action="<?= e(url('modules/currency/converter.php')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="cv-amount">Tutar</label>
                    <input type="text" id="cv-amount" name="amount" inputmode="decimal" value="1">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="cv-from">Kaynak</label>
                        <select id="cv-from" name="from">
                            <?php foreach ($symbols as $s): ?>
                                <option value="<?= e($s) ?>"<?= $s === 'USD' ? ' selected' : '' ?>><?= e($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="cv-to">Hedef</label>
                        <select id="cv-to" name="to">
                            <?php foreach ($symbols as $s): ?>
                                <option value="<?= e($s) ?>"<?= $s === 'TRY' ? ' selected' : '' ?>><?= e($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="swap-row">
                    <button type="button" class="btn btn-sm" id="cv-swap">↕ Yönü değiştir</button>
                    <button type="submit" class="btn btn-primary btn-sm">Çevir</button>
                </div>
                <div style="margin-top:14px">
                    <label>Sonuç</label>
                    <div class="converter-result" id="cv-result">—</div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Güncel Kurlar</h2>
            <span class="badge badge-info"><?= e($source) ?></span>
        </div>
        <div class="card-body">
            <div class="rate-list">
                <div class="rate-item">
                    <div class="rate-pair">USD → TRY</div>
                    <div class="rate-value"><?= $usdTry !== null ? fmt_money($usdTry, 4) : '—' ?></div>
                </div>
                <div class="rate-item">
                    <div class="rate-pair">EUR → TRY</div>
                    <div class="rate-value"><?= $eurTry !== null ? fmt_money($eurTry, 4) : '—' ?></div>
                </div>
                <div class="rate-item">
                    <div class="rate-pair">EUR → USD</div>
                    <div class="rate-value"><?= $eurUsd !== null ? fmt_money($eurUsd, 4) : '—' ?></div>
                </div>
            </div>
            <p class="field-hint" style="margin-top:14px">
                Kaynak tarihi: <?= e($rates['date'] ?? '') ?>. Kurlar en fazla
                <?= (int) (KUR_CACHE_TTL / 60) ?> dakika önbellekte tutulur, sonra yenilenir.
            </p>
        </div>
    </div>
</div>

<script>
    window.PANEL_RATES = <?= json_encode($rates['rates'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php
layout_bottom();
