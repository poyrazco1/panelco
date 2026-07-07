<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leave.php';

auth_boot();
require_permission('leave');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    leave_settings_save([
        'annual_default_days' => (float) ($_POST['annual_default_days'] ?? 14),
        'exclude_weekends'    => isset($_POST['exclude_weekends']),
        'exclude_holidays'    => isset($_POST['exclude_holidays']),
        'weekend_days'        => array_map('intval', (array) ($_POST['weekend_days'] ?? [])),
    ]);
    flash('success', 'Çalışma takvimi ayarları kaydedildi.');
    http_response_code(303);
    redirect('modules/settings/work-calendar.php');
}

$set = leave_settings();
$labels = leave_weekday_labels();

layout_top('Çalışma Takvimi', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">Çalışma Takvimi</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>"><?= icon('chevron-left') ?>Genel Ayarlar</a></div>
</div>

<?= render_flashes() ?>

<form method="post" action="<?= e(url('modules/settings/work-calendar.php')) ?>">
    <?= csrf_field() ?>
    <div class="card" style="max-width:640px">
        <div class="card-header"><h2>İzin Hesaplama Kuralları</h2></div>
        <div class="card-body">
            <div class="form-group">
                <label for="wc-days">Varsayılan Yıllık İzin (gün)</label>
                <input type="number" id="wc-days" name="annual_default_days" step="0.5" min="0" value="<?= e((string) $set['annual_default_days']) ?>">
                <p class="field-hint">Her personel için temel hak ediş. Kişiye özel farklar "Bakiye Düzelt" ile ayarlanır.</p>
            </div>

            <div class="form-check"><label><input type="checkbox" name="exclude_weekends" <?= $set['exclude_weekends'] ? 'checked' : '' ?>> Hafta sonlarını izin gününden düş</label></div>
            <div class="form-check"><label><input type="checkbox" name="exclude_holidays" <?= $set['exclude_holidays'] ? 'checked' : '' ?>> Resmi tatilleri izin gününden düş</label></div>

            <div class="form-group" style="margin-top:12px">
                <label>Hafta Sonu Günleri</label>
                <div class="check-inline">
                    <?php foreach ($labels as $w => $lbl): ?>
                        <label><input type="checkbox" name="weekend_days[]" value="<?= (int) $w ?>" <?= in_array($w, $set['weekend_days'], true) ? 'checked' : '' ?>> <?= e($lbl) ?></label>
                    <?php endforeach; ?>
                </div>
                <p class="field-hint">İşaretli günler çalışma dışı sayılır; "hafta sonlarını düş" açıksa bu günler izinden düşülmez.</p>
            </div>

            <div class="form-actions"><button type="submit" class="btn btn-primary">Kaydet</button></div>
        </div>
    </div>
</form>

<?php
layout_bottom();
