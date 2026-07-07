<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leave.php';

auth_boot();
require_permission('leave');

$pid = (int) ($_GET['personnel_id'] ?? 0);
$person = $pid > 0 ? get_personnel_by_id($pid) : null;
if (!$person) { flash('error', 'Personel bulunamadı.'); http_response_code(303); redirect('modules/leave/index.php'); }

$summary = get_personnel_leave_summary($pid);
$requests = get_personnel_leave_requests($pid);
$adjustments = get_personnel_adjustments($pid);
$adjLabels = leave_adjustment_labels();

$fmt = static fn($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');

layout_top('İzin Geçmişi — ' . $person['full_name'], 'leave');
?>

<div class="page-head">
    <h1 class="page-title">İzin Geçmişi — <?= e((string) $person['full_name']) ?></h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/leave/index.php')) ?>"><?= icon('chevron-left') ?>Yıllık İzin Takibi</a>
        <a class="btn btn-sm" href="<?= e(url('modules/leave/balance-adjust.php?personnel_id=' . $pid)) ?>"><?= icon('sliders-horizontal') ?>Bakiye Düzelt</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('modules/leave/request-form.php?personnel_id=' . $pid)) ?>"><?= icon('plus') ?>Yeni İzin</a>
    </div>
</div>

<?= render_flashes() ?>

<div class="card">
    <div class="card-header"><h2>Bakiye Özeti (<?= (int) $summary['year'] ?>)</h2></div>
    <div class="card-body">
        <div class="stat-grid">
            <div class="stat"><span class="stat-label">Hak Edilen</span><span class="stat-value"><?= e($fmt($summary['entitled'])) ?></span></div>
            <div class="stat"><span class="stat-label">Devreden</span><span class="stat-value"><?= e($fmt($summary['carry_over'])) ?></span></div>
            <div class="stat"><span class="stat-label">Manuel</span><span class="stat-value"><?= e($fmt($summary['manual'])) ?></span></div>
            <div class="stat"><span class="stat-label">Kullanılan</span><span class="stat-value"><?= e($fmt($summary['used'])) ?></span></div>
            <div class="stat"><span class="stat-label">Onay Bekleyen</span><span class="stat-value"><?= e($fmt($summary['pending'])) ?></span></div>
            <div class="stat stat-accent"><span class="stat-label">Kalan</span><span class="stat-value"><?= e($fmt($summary['remaining'])) ?></span></div>
        </div>
        <p class="field-hint" style="margin-top:12px">Departman: <?= e((string) ($person['department'] ?? '—')) ?> · İşe giriş: <?= e(fmt_date((string) ($person['hire_date'] ?? ''))) ?></p>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>İzin Talepleri</h2></div>
    <div class="card-body">
        <?php if (empty($requests)): ?>
            <p class="muted">Bu personel için izin kaydı yok.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Talep No</th><th>Tür</th><th>Başlangıç</th><th>Bitiş</th><th class="num">Gün</th><th>Durum</th><th style="width:60px"></th></tr></thead>
                <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><?= e((string) $r['request_no']) ?></td>
                        <td><?= e((string) ($r['type_name'] ?? '—')) ?></td>
                        <td><?= e(fmt_date((string) $r['start_date'])) ?></td>
                        <td><?= e(fmt_date((string) $r['end_date'])) ?></td>
                        <td class="num"><?= e($fmt($r['calculated_days'])) ?></td>
                        <td><span class="badge <?= e(leave_status_class((string) $r['status'])) ?>"><?= e(leave_status_label((string) $r['status'])) ?></span></td>
                        <td><a class="btn btn-sm action-icon-btn" href="<?= e(url('modules/leave/request-view.php?id=' . (int) $r['id'])) ?>" title="Görüntüle"><?= icon('eye') ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Bakiye Düzeltmeleri</h2></div>
    <div class="card-body">
        <?php if (empty($adjustments)): ?>
            <p class="muted">Düzeltme kaydı yok.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Tip</th><th class="num">Gün</th><th>Açıklama</th><th>Yapan</th></tr></thead>
                <tbody>
                <?php foreach ($adjustments as $h): ?>
                    <tr>
                        <td><?= e(fmt_date((string) $h['created_at'])) ?></td>
                        <td><?= e($adjLabels[$h['adjustment_type']] ?? (string) $h['adjustment_type']) ?></td>
                        <td class="num"><?= e($fmt($h['days'])) ?></td>
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

<?php
layout_bottom();
