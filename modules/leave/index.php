<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leave.php';

auth_boot();
require_permission('leave');

$f = [
    'department' => (string) ($_GET['department'] ?? ''),
    'search'     => trim((string) ($_GET['q'] ?? '')),
    'low_balance'=> !empty($_GET['low_balance']),
    'this_month' => !empty($_GET['this_month']),
    'this_year'  => !empty($_GET['this_year']),
];
$rows = get_all_personnel_leave_summaries($f);
$departments = personnel_departments();
$qs = http_build_query(array_filter([
    'department' => $f['department'],
    'q'          => $f['search'],
    'low_balance'=> $f['low_balance'] ? 1 : null,
    'this_month' => $f['this_month'] ? 1 : null,
    'this_year'  => $f['this_year'] ? 1 : null,
]));

layout_top('Yıllık İzin Takibi', 'leave');
?>

<div class="page-head">
    <h1 class="page-title">Yıllık İzin Takibi</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/leave/export.php?type=annual' . ($qs ? '&' . $qs : ''))) ?>"><?= icon('download') ?>CSV Dışa Aktar</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('modules/leave/request-form.php')) ?>"><?= icon('plus') ?>Yeni İzin</a>
    </div>
</div>

<?= render_flashes() ?>

<div class="card">
    <div class="card-body">
        <form method="get" action="<?= e(url('modules/leave/index.php')) ?>" class="toolbar">
            <div class="form-group">
                <label for="fl-dep">Departman</label>
                <select id="fl-dep" name="department">
                    <option value="">Tümü</option>
                    <?php foreach ($departments as $dep): ?><option value="<?= e($dep) ?>"<?= $f['department'] === $dep ? ' selected' : '' ?>><?= e($dep) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="fl-q">Ara</label>
                <input type="text" id="fl-q" name="q" value="<?= e($f['search']) ?>" placeholder="Ad, kod, departman">
            </div>
            <div class="form-group"><label class="chk"><input type="checkbox" name="low_balance" value="1" <?= $f['low_balance'] ? 'checked' : '' ?>> Kalanı düşük (≤5)</label></div>
            <div class="form-group"><label class="chk"><input type="checkbox" name="this_month" value="1" <?= $f['this_month'] ? 'checked' : '' ?>> Bu ay izinli</label></div>
            <div class="form-group"><label class="chk"><input type="checkbox" name="this_year" value="1" <?= $f['this_year'] ? 'checked' : '' ?>> Bu yıl izinli</label></div>
            <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Filtrele</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($rows)): ?>
            <p class="muted">Kayıt bulunamadı. Aktif personel yoksa önce Ayarlar → Personeller'den ekleyin.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Personel</th><th>Departman</th><th>İşe Giriş</th>
                        <th class="num">Hak Edilen</th><th class="num">Devreden</th><th class="num">Kullanılan</th>
                        <th class="num">Onay Bekleyen</th><th class="num">Kalan</th><th>Son İzin</th>
                        <th style="width:150px">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): $p = $row['personnel']; $s = $row['summary']; $pid = (int) $p['id']; ?>
                    <tr>
                        <td>
                            <strong><?= e((string) $p['full_name']) ?></strong>
                            <?php if (!empty($p['personnel_code'])): ?><br><span class="muted small"><?= e((string) $p['personnel_code']) ?></span><?php endif; ?>
                        </td>
                        <td><?= e((string) ($p['department'] ?? '')) ?></td>
                        <td><?= e(fmt_date((string) ($p['hire_date'] ?? ''))) ?></td>
                        <td class="num"><?= e(rtrim(rtrim(number_format($s['entitled'], 2, ',', '.'), '0'), ',')) ?></td>
                        <td class="num"><?= e(rtrim(rtrim(number_format($s['carry_over'], 2, ',', '.'), '0'), ',')) ?></td>
                        <td class="num"><?= e(rtrim(rtrim(number_format($s['used'], 2, ',', '.'), '0'), ',')) ?></td>
                        <td class="num"><?php if ($s['pending'] > 0): ?><span class="badge badge-leave"><?= e(rtrim(rtrim(number_format($s['pending'], 2, ',', '.'), '0'), ',')) ?></span><?php else: ?>0<?php endif; ?></td>
                        <td class="num"><strong class="<?= $s['remaining'] <= 0 ? 'text-danger' : '' ?>"><?= e(rtrim(rtrim(number_format($s['remaining'], 2, ',', '.'), '0'), ',')) ?></strong></td>
                        <td><?= $s['last_leave_date'] ? e(fmt_date((string) $s['last_leave_date'])) : '<span class="muted">—</span>' ?></td>
                        <td>
                            <div class="row-actions">
                                <a class="btn btn-sm action-icon-btn" href="<?= e(url('modules/leave/history.php?personnel_id=' . $pid)) ?>" title="İzin geçmişi / detay"><?= icon('eye') ?></a>
                                <a class="btn btn-sm action-icon-btn" href="<?= e(url('modules/leave/request-form.php?personnel_id=' . $pid)) ?>" title="Yeni izin ekle"><?= icon('plus') ?></a>
                                <a class="btn btn-sm action-icon-btn" href="<?= e(url('modules/leave/balance-adjust.php?personnel_id=' . $pid)) ?>" title="Bakiye düzelt"><?= icon('sliders-horizontal') ?></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="field-hint" style="margin-top:12px">Onay bekleyen izinler kalan izinden ayrı gösterilir; kalan izine dahil edilmez.</p>
        <?php endif; ?>
    </div>
</div>

<?php
layout_bottom();
