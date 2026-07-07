<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leave.php';

auth_boot();
require_permission('leave');

$f = [
    'personnel_id' => (int) ($_GET['personnel_id'] ?? 0),
    'department'   => (string) ($_GET['department'] ?? ''),
    'leave_type_id'=> (int) ($_GET['leave_type_id'] ?? 0),
    'status'       => (string) ($_GET['status'] ?? ''),
    'approver_id'  => (int) ($_GET['approver_id'] ?? 0),
    'date_from'    => (string) ($_GET['date_from'] ?? ''),
    'date_to'      => (string) ($_GET['date_to'] ?? ''),
    'this_month'   => !empty($_GET['this_month']),
    'this_year'    => !empty($_GET['this_year']),
    'search'       => trim((string) ($_GET['q'] ?? '')),
];
$rows = get_leave_requests($f);
$personnel = get_personnel_options(false);
$departments = personnel_departments();
$types = leave_type_options(false);
$statuses = leave_statuses();
$approvers = personnel_user_options();

$qs = http_build_query(array_filter([
    'personnel_id' => $f['personnel_id'] ?: null,
    'department'   => $f['department'],
    'leave_type_id'=> $f['leave_type_id'] ?: null,
    'status'       => $f['status'],
    'approver_id'  => $f['approver_id'] ?: null,
    'date_from'    => $f['date_from'],
    'date_to'      => $f['date_to'],
    'this_month'   => $f['this_month'] ? 1 : null,
    'this_year'    => $f['this_year'] ? 1 : null,
    'q'            => $f['search'],
]));

layout_top('İzin Talepleri', 'leave');
?>

<div class="page-head">
    <h1 class="page-title">İzin Talepleri</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/leave/export.php?type=requests' . ($qs ? '&' . $qs : ''))) ?>"><?= icon('download') ?>CSV Dışa Aktar</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('modules/leave/request-form.php')) ?>"><?= icon('plus') ?>Yeni İzin Talebi</a>
    </div>
</div>

<?= render_flashes() ?>

<div class="card">
    <div class="card-body">
        <form method="get" action="<?= e(url('modules/leave/requests.php')) ?>" class="toolbar">
            <div class="form-group">
                <label for="fl-pers">Personel</label>
                <select id="fl-pers" name="personnel_id">
                    <option value="0">Tümü</option>
                    <?php foreach ($personnel as $pid => $pname): ?><option value="<?= (int) $pid ?>"<?= $f['personnel_id'] === (int) $pid ? ' selected' : '' ?>><?= e($pname) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="fl-dep">Departman</label>
                <select id="fl-dep" name="department">
                    <option value="">Tümü</option>
                    <?php foreach ($departments as $dep): ?><option value="<?= e($dep) ?>"<?= $f['department'] === $dep ? ' selected' : '' ?>><?= e($dep) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="fl-type">İzin Türü</label>
                <select id="fl-type" name="leave_type_id">
                    <option value="0">Tümü</option>
                    <?php foreach ($types as $tid => $tname): ?><option value="<?= (int) $tid ?>"<?= $f['leave_type_id'] === (int) $tid ? ' selected' : '' ?>><?= e($tname) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="fl-status">Durum</label>
                <select id="fl-status" name="status">
                    <option value="">Tümü</option>
                    <?php foreach ($statuses as $sk => $sl): ?><option value="<?= e($sk) ?>"<?= $f['status'] === $sk ? ' selected' : '' ?>><?= e($sl) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="fl-ap">Onaylayan</label>
                <select id="fl-ap" name="approver_id">
                    <option value="0">Tümü</option>
                    <?php foreach ($approvers as $a): ?><option value="<?= (int) $a['id'] ?>"<?= $f['approver_id'] === (int) $a['id'] ? ' selected' : '' ?>><?= e((string) $a['username']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label for="fl-df">Başlangıç</label><input type="date" id="fl-df" name="date_from" value="<?= e($f['date_from']) ?>"></div>
            <div class="form-group"><label for="fl-dt">Bitiş</label><input type="date" id="fl-dt" name="date_to" value="<?= e($f['date_to']) ?>"></div>
            <div class="form-group"><label for="fl-q">Ara</label><input type="text" id="fl-q" name="q" value="<?= e($f['search']) ?>" placeholder="Ad, kod, açıklama, no"></div>
            <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Filtrele</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($rows)): ?>
            <p class="muted">İzin talebi bulunamadı.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Talep No</th><th>Personel</th><th>İzin Türü</th><th>Başlangıç</th><th>Bitiş</th><th class="num">Gün</th><th>Durum</th><th>Talep Eden</th><th>Onaylayan</th><th style="width:120px">İşlemler</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): $id = (int) $r['id']; ?>
                    <tr>
                        <td><strong><?= e((string) $r['request_no']) ?></strong></td>
                        <td><?= e((string) ($r['personnel_name'] ?? '—')) ?><?php if (!empty($r['department'])): ?><br><span class="muted small"><?= e((string) $r['department']) ?></span><?php endif; ?></td>
                        <td><?= e((string) ($r['type_name'] ?? '—')) ?><?= (int) $r['is_half_day'] === 1 ? ' <span class="badge badge-muted">½</span>' : '' ?></td>
                        <td><?= e(fmt_date((string) $r['start_date'])) ?></td>
                        <td><?= e(fmt_date((string) $r['end_date'])) ?></td>
                        <td class="num"><?= e(rtrim(rtrim(number_format((float) $r['calculated_days'], 2, ',', '.'), '0'), ',')) ?></td>
                        <td><span class="badge <?= e(leave_status_class((string) $r['status'])) ?>"><?= e(leave_status_label((string) $r['status'])) ?></span></td>
                        <td><?= e((string) ($r['requester_name'] ?? '—')) ?></td>
                        <td><?= e((string) ($r['approver_name'] ?? '—')) ?></td>
                        <td>
                            <div class="row-actions">
                                <a class="btn btn-sm action-icon-btn" href="<?= e(url('modules/leave/request-view.php?id=' . $id)) ?>" title="Görüntüle"><?= icon('eye') ?></a>
                                <?php if (in_array($r['status'], ['draft', 'pending'], true)): ?>
                                    <a class="btn btn-sm action-icon-btn" href="<?= e(url('modules/leave/request-form.php?id=' . $id)) ?>" title="Düzenle"><?= icon('pencil') ?></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
layout_bottom();
