<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leave.php';

auth_boot();
require_permission('leave');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pid = (int) ($_POST['personnel_id'] ?? 0);
    $res = create_leave_balance_adjustment([
        'personnel_id'    => $pid,
        'adjustment_type' => (string) ($_POST['adjustment_type'] ?? ''),
        'days'            => (float) str_replace(',', '.', (string) ($_POST['days'] ?? '0')),
        'description'     => (string) ($_POST['description'] ?? ''),
    ]);
    if ($res['ok']) { flash('success', 'Bakiye düzeltmesi kaydedildi.'); }
    else { flash('error', implode(' ', $res['errors'])); }
    http_response_code(303);
    redirect('modules/leave/balance-adjust.php' . ($pid > 0 ? '?personnel_id=' . $pid : ''));
}

$pid = (int) ($_GET['personnel_id'] ?? 0);
$person = $pid > 0 ? get_personnel_by_id($pid) : null;
$personnel = get_personnel_options(false);
$adjLabels = leave_adjustment_labels();
$summary = $person ? get_personnel_leave_summary($pid) : null;
$history = $person ? get_personnel_adjustments($pid) : [];

layout_top('Bakiye Düzeltme', 'leave');
?>

<div class="page-head">
    <h1 class="page-title">Manuel Bakiye Düzeltme</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/leave/index.php')) ?>"><?= icon('chevron-left') ?>Yıllık İzin Takibi</a></div>
</div>

<?= render_flashes() ?>

<?php if ($summary): ?>
<div class="card">
    <div class="card-header"><h2><?= e((string) $person['full_name']) ?> — Güncel Bakiye (<?= (int) $summary['year'] ?>)</h2></div>
    <div class="card-body">
        <div class="stat-grid">
            <div class="stat"><span class="stat-label">Hak Edilen</span><span class="stat-value"><?= e(rtrim(rtrim(number_format($summary['entitled'], 2, ',', '.'), '0'), ',')) ?></span></div>
            <div class="stat"><span class="stat-label">Devreden</span><span class="stat-value"><?= e(rtrim(rtrim(number_format($summary['carry_over'], 2, ',', '.'), '0'), ',')) ?></span></div>
            <div class="stat"><span class="stat-label">Manuel</span><span class="stat-value"><?= e(rtrim(rtrim(number_format($summary['manual'], 2, ',', '.'), '0'), ',')) ?></span></div>
            <div class="stat"><span class="stat-label">Kullanılan</span><span class="stat-value"><?= e(rtrim(rtrim(number_format($summary['used'], 2, ',', '.'), '0'), ',')) ?></span></div>
            <div class="stat"><span class="stat-label">Onay Bekleyen</span><span class="stat-value"><?= e(rtrim(rtrim(number_format($summary['pending'], 2, ',', '.'), '0'), ',')) ?></span></div>
            <div class="stat stat-accent"><span class="stat-label">Kalan</span><span class="stat-value"><?= e(rtrim(rtrim(number_format($summary['remaining'], 2, ',', '.'), '0'), ',')) ?></span></div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h2>Yeni Düzeltme</h2></div>
        <div class="card-body">
            <form method="post" action="<?= e(url('modules/leave/balance-adjust.php')) ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="ba-pers">Personel</label>
                    <select id="ba-pers" name="personnel_id" required>
                        <option value="">Seçin…</option>
                        <?php foreach ($personnel as $id => $name): ?><option value="<?= (int) $id ?>"<?= $pid === (int) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ba-type">Düzeltme Tipi</label>
                    <select id="ba-type" name="adjustment_type" required>
                        <?php foreach ($adjLabels as $k => $lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?>
                    </select>
                    <p class="field-hint">"İzin düş" seçilen gün kadar bakiyeyi azaltır. Diğerleri gün kadar artırır (eksi girilebilir).</p>
                </div>
                <div class="form-group">
                    <label for="ba-days">Gün Sayısı</label>
                    <input type="number" id="ba-days" name="days" step="0.5" value="" required>
                </div>
                <div class="form-group">
                    <label for="ba-desc">Açıklama</label>
                    <textarea id="ba-desc" name="description" rows="2" placeholder="Neden / referans"></textarea>
                </div>
                <div class="form-actions"><button type="submit" class="btn btn-primary">Kaydet</button></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Düzeltme Geçmişi</h2></div>
        <div class="card-body">
            <?php if (!$person): ?>
                <p class="muted">Geçmişi görmek için personel seçin.</p>
            <?php elseif (empty($history)): ?>
                <p class="muted">Bu personel için düzeltme kaydı yok.</p>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Tarih</th><th>Tip</th><th class="num">Gün</th><th>Açıklama</th><th>Yapan</th></tr></thead>
                    <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td><?= e(fmt_date((string) $h['created_at'])) ?></td>
                            <td><?= e($adjLabels[$h['adjustment_type']] ?? (string) $h['adjustment_type']) ?></td>
                            <td class="num"><?= e(rtrim(rtrim(number_format((float) $h['days'], 2, ',', '.'), '0'), ',')) ?></td>
                            <td><?= e((string) ($h['description'] ?? '')) ?></td>
                            <td><?= e((string) ($h['created_by_name'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
layout_bottom();
