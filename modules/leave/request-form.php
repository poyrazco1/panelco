<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leave.php';

auth_boot();
require_permission('leave');

$id = (int) ($_GET['id'] ?? 0);
$existing = $id > 0 ? get_leave_request($id) : null;
if ($id > 0 && !$existing) { flash('error', 'Kayıt bulunamadı.'); http_response_code(303); redirect('modules/leave/requests.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $data = [
        'personnel_id'  => (int) ($_POST['personnel_id'] ?? 0),
        'leave_type_id' => (int) ($_POST['leave_type_id'] ?? 0),
        'start_date'    => (string) ($_POST['start_date'] ?? ''),
        'end_date'      => (string) ($_POST['end_date'] ?? ''),
        'start_time'    => (string) ($_POST['start_time'] ?? ''),
        'end_time'      => (string) ($_POST['end_time'] ?? ''),
        'is_half_day'   => isset($_POST['is_half_day']),
        'manual_days'   => (string) ($_POST['manual_days'] ?? ''),
        'calculated_hours' => (string) ($_POST['calculated_hours'] ?? ''),
        'description'   => (string) ($_POST['description'] ?? ''),
        'status'        => (string) ($_POST['status'] ?? 'pending'),
    ];
    $res = $existing ? update_leave_request($id, $data) : create_leave_request($data);
    if ($res['ok']) {
        flash('success', $existing ? 'İzin talebi güncellendi.' : 'İzin talebi oluşturuldu.');
        http_response_code(303);
        redirect('modules/leave/request-view.php?id=' . (int) $res['id']);
    }
    $errors = $res['errors'];
    $existing = array_merge((array) $existing, $data, ['id' => $id]);
}

$preselectPid = (int) ($_GET['personnel_id'] ?? ($existing['personnel_id'] ?? 0));
$personnel = get_personnel_options(false);
$types = get_leave_types(false);
$statuses = leave_statuses();
$set = leave_settings();

$cur = static fn(string $k, $d = '') => $existing[$k] ?? $d;

layout_top($existing && $id ? 'İzin Talebini Düzenle' : 'Yeni İzin Talebi', 'leave');
?>

<div class="page-head">
    <h1 class="page-title"><?= $existing && $id ? 'İzin Talebini Düzenle' : 'Yeni İzin Talebi' ?></h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/leave/requests.php')) ?>"><?= icon('chevron-left') ?>İzin Talepleri</a></div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?= icon('triangle-alert', 'icon-sm') ?> <?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>
<?= render_flashes() ?>

<form method="post" action="<?= e(url('modules/leave/request-form.php' . ($id ? '?id=' . $id : ''))) ?>">
    <?= csrf_field() ?>
    <div class="card" style="max-width:720px">
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="rf-pers">Personel</label>
                    <select id="rf-pers" name="personnel_id" required>
                        <option value="">Seçin…</option>
                        <?php foreach ($personnel as $pid => $pname): ?><option value="<?= (int) $pid ?>"<?= (int) $preselectPid === (int) $pid ? ' selected' : '' ?>><?= e($pname) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="rf-type">İzin Türü</label>
                    <select id="rf-type" name="leave_type_id" required>
                        <option value="">Seçin…</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= (int) $t['id'] ?>"<?= (int) $cur('leave_type_id') === (int) $t['id'] ? ' selected' : '' ?>
                                data-hourly="<?= (int) $t['allow_hourly'] ?>" data-half="<?= (int) $t['allow_half_day'] ?>" data-deducts="<?= (int) $t['deducts_annual_balance'] ?>">
                                <?= e((string) $t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group"><label for="rf-sd">Başlangıç Tarihi</label><input type="date" id="rf-sd" name="start_date" value="<?= e((string) $cur('start_date')) ?>" required></div>
                <div class="form-group"><label for="rf-ed">Bitiş Tarihi</label><input type="date" id="rf-ed" name="end_date" value="<?= e((string) $cur('end_date')) ?>" required></div>
            </div>

            <div class="form-row">
                <div class="form-group"><label for="rf-sti">Başlangıç Saati (saatlik izin)</label><input type="time" id="rf-sti" name="start_time" value="<?= e((string) $cur('start_time')) ?>"></div>
                <div class="form-group"><label for="rf-eti">Bitiş Saati (saatlik izin)</label><input type="time" id="rf-eti" name="end_time" value="<?= e((string) $cur('end_time')) ?>"></div>
            </div>

            <div class="form-row">
                <div class="form-group"><label for="rf-hours">Saat Sayısı (opsiyonel)</label><input type="number" id="rf-hours" name="calculated_hours" step="0.5" min="0" value="<?= e((string) ($cur('calculated_hours') !== null ? $cur('calculated_hours') : '')) ?>"></div>
                <div class="form-group"><label for="rf-manual">Manuel Gün (opsiyonel, hesabı geçersiz kılar)</label><input type="number" id="rf-manual" name="manual_days" step="0.5" min="0" value=""></div>
            </div>

            <div class="form-check"><label><input type="checkbox" name="is_half_day" <?= (int) $cur('is_half_day') === 1 ? 'checked' : '' ?>> Yarım gün izin</label></div>

            <div class="form-group">
                <label for="rf-status">Durum</label>
                <select id="rf-status" name="status">
                    <?php foreach (['draft' => $statuses['draft'], 'pending' => $statuses['pending']] as $sk => $sl): ?>
                        <option value="<?= e($sk) ?>"<?= (string) $cur('status', 'pending') === $sk ? ' selected' : '' ?>><?= e($sl) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="field-hint">Onay/Red işlemleri kayıt oluşturulduktan sonra detay sayfasından yapılır.</p>
            </div>

            <div class="form-group">
                <label for="rf-desc">Açıklama</label>
                <textarea id="rf-desc" name="description" rows="3"><?= e((string) $cur('description')) ?></textarea>
            </div>

            <p class="field-hint">
                Gün sayısı kaydederken otomatik hesaplanır
                (<?= $set['exclude_weekends'] ? 'hafta sonu hariç' : 'hafta sonu dahil' ?>,
                <?= $set['exclude_holidays'] ? 'resmi tatil hariç' : 'resmi tatil dahil' ?>).
            </p>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $existing && $id ? 'Güncelle' : 'Kaydet' ?></button>
                <a class="btn" href="<?= e(url('modules/leave/requests.php')) ?>">Vazgeç</a>
            </div>
        </div>
    </div>
</form>

<?php
layout_bottom();
