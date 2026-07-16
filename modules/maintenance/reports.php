<?php
declare(strict_types=1);

/**
 * modules/maintenance/reports.php
 * Bakım hatırlatma raporları (§15). Tarih, personel, marka, durum filtreleri.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service_maintenance.php';
require_once __DIR__ . '/../../includes/service_maintenance_reports.php';

auth_boot();
if (!can_maint_reports()) { require_permission('maintenance.reports'); }

$f = [
    'date_from' => (string) ($_GET['date_from'] ?? ''),
    'date_to'   => (string) ($_GET['date_to'] ?? ''),
    'assigned'  => (int) ($_GET['assigned'] ?? 0),
    'brand'     => trim((string) ($_GET['brand'] ?? '')),
    'status'    => (string) ($_GET['status'] ?? ''),
];

$sum       = smaint_report_summary($f);
$byUser    = smaint_report_by_personnel($f);
$byBrand   = smaint_report_by_field($f, 'brand_name');
$byModel   = smaint_report_by_field($f, 'device_model');
$revenue   = smaint_report_monthly_revenue($f);
$users     = smaint_assignable_users();
$statuses  = smaint_statuses();

$fmtMoney = static fn($v) => number_format((float) $v, 2, ',', '.');

$cards = [
    ['Toplam bakım hatırlatması', $sum['total']],
    ['Bu ay bakım zamanı gelenler', $sum['this_month']],
    ['Geciken bakımlar', $sum['overdue']],
    ['Aranan müşteriler', $sum['called']],
    ['WhatsApp gönderilenler', $sum['wa_sent']],
    ['E-posta gönderilenler', $sum['email_sent']],
    ['Müşteri dönüşü alınanlar', $sum['replies']],
    ['Bakım randevusu oluşturulanlar', $sum['appointments']],
    ['Yeni servise dönüşenler', $sum['converted']],
    ['İlgilenmeyen müşteriler', $sum['not_interested']],
    ['Ulaşılamayan müşteriler', $sum['unreachable']],
    ['Servise dönüşüm oranı', $sum['conversion_rate'] . '%'],
];

layout_top('Bakım Raporları', 'service');
?>
<div class="page-head">
    <h1 class="page-title"><?= icon('wrench') ?> Bakım Raporları</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/maintenance/index.php')) ?>"><?= icon('clipboard-list') ?>Bakım Takipleri</a></div>
</div>
<?php render_flashes(); ?>

<form method="get" action="<?= e(url('modules/maintenance/reports.php')) ?>" class="toolbar">
    <div class="form-group"><label for="date_from">Başlangıç (bakım)</label><input type="date" id="date_from" name="date_from" value="<?= e($f['date_from']) ?>"></div>
    <div class="form-group"><label for="date_to">Bitiş (bakım)</label><input type="date" id="date_to" name="date_to" value="<?= e($f['date_to']) ?>"></div>
    <div class="form-group"><label for="assigned">Personel</label>
        <select id="assigned" name="assigned"><option value="0">Tümü</option>
            <?php foreach ($users as $uidk => $un): ?><option value="<?= (int) $uidk ?>"<?= $f['assigned'] === $uidk ? ' selected' : '' ?>><?= e($un) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group"><label for="brand">Marka</label><input type="text" id="brand" name="brand" value="<?= e($f['brand']) ?>"></div>
    <div class="form-group"><label for="status">Durum</label>
        <select id="status" name="status"><option value="">Tümü</option>
            <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Filtrele</button></div>
</form>

<div class="kpi-strip" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-bottom:16px">
    <?php foreach ($cards as [$label, $val]): ?>
        <div class="card"><div class="card-body"><div class="text-muted" style="font-size:13px"><?= e($label) ?></div><div style="font-size:22px;font-weight:700"><?= e((string) $val) ?></div></div></div>
    <?php endforeach; ?>
</div>

<div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start">
    <div class="card">
        <div class="card-header"><h2>Personel Bazlı Dönüşüm</h2></div>
        <div class="card-body">
            <?php if (!$byUser): ?><div class="empty">Veri yok.</div><?php else: ?>
            <table class="table"><thead><tr><th>Personel</th><th>Toplam</th><th>Dönüşen</th><th>Oran</th></tr></thead><tbody>
                <?php foreach ($byUser as $r): ?><tr>
                    <td><?= e((string) ($r['full_name'] ?? '(atanmamış)')) ?></td>
                    <td><?= (int) $r['total'] ?></td><td><?= (int) $r['converted'] ?></td><td><?= e((string) $r['rate']) ?>%</td>
                </tr><?php endforeach; ?>
            </tbody></table>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Aylık Bakım Servis Geliri</h2></div>
        <div class="card-body">
            <?php if (!$revenue): ?><div class="empty">Veri yok.</div><?php else: ?>
            <table class="table"><thead><tr><th>Ay</th><th>Servis</th><th>Gelir</th></tr></thead><tbody>
                <?php foreach ($revenue as $r): ?><tr>
                    <td><?= e((string) $r['ym']) ?></td><td><?= (int) $r['services'] ?></td><td><?= e($fmtMoney($r['revenue'])) ?> ₺</td>
                </tr><?php endforeach; ?>
            </tbody></table>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Marka Bazlı Bakım Dönüşümü</h2></div>
        <div class="card-body">
            <?php if (!$byBrand): ?><div class="empty">Veri yok.</div><?php else: ?>
            <table class="table"><thead><tr><th>Marka</th><th>Toplam</th><th>Dönüşen</th><th>Oran</th></tr></thead><tbody>
                <?php foreach ($byBrand as $r): ?><tr>
                    <td><?= e((string) $r['label']) ?></td><td><?= (int) $r['total'] ?></td><td><?= (int) $r['converted'] ?></td><td><?= e((string) $r['rate']) ?>%</td>
                </tr><?php endforeach; ?>
            </tbody></table>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Model Bazlı Bakım Dönüşümü</h2></div>
        <div class="card-body">
            <?php if (!$byModel): ?><div class="empty">Veri yok.</div><?php else: ?>
            <table class="table"><thead><tr><th>Model</th><th>Toplam</th><th>Dönüşen</th><th>Oran</th></tr></thead><tbody>
                <?php foreach ($byModel as $r): ?><tr>
                    <td><?= e((string) $r['label']) ?></td><td><?= (int) $r['total'] ?></td><td><?= (int) $r['converted'] ?></td><td><?= e((string) $r['rate']) ?>%</td>
                </tr><?php endforeach; ?>
            </tbody></table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php layout_bottom(); ?>
