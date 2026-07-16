<?php
declare(strict_types=1);

/**
 * modules/maintenance/index.php
 * Bakım Takipleri listesi — filtreler, tablo, toplu işlemler, CSV (§14).
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service_maintenance.php';

auth_boot();
if (!can_maint_view()) { require_permission('maintenance.view'); }

$uid   = current_user_id() ?? 0;
$seeAll = can_maint_view_all();
$scopeUser = $seeAll ? null : $uid;

$f = [
    'search'   => trim((string) ($_GET['search'] ?? '')),
    'customer' => trim((string) ($_GET['customer'] ?? '')),
    'phone'    => trim((string) ($_GET['phone'] ?? '')),
    'email'    => trim((string) ($_GET['email'] ?? '')),
    'brand'    => trim((string) ($_GET['brand'] ?? '')),
    'model'    => trim((string) ($_GET['model'] ?? '')),
    'serial'   => trim((string) ($_GET['serial'] ?? '')),
    'status'   => (string) ($_GET['status'] ?? ''),
    'assigned' => (int) ($_GET['assigned'] ?? 0),
    'channel'  => (string) ($_GET['channel'] ?? ''),
    'due_from' => (string) ($_GET['due_from'] ?? ''),
    'due_to'   => (string) ($_GET['due_to'] ?? ''),
    'flag'     => (string) ($_GET['flag'] ?? ''),
];

$rows     = smaint_reminder_list($f, ['scope_user' => $scopeUser, 'limit' => 1000]);
$counts   = smaint_reminder_counts($scopeUser);
$statuses = smaint_statuses();
$channels = smaint_channels();
$users    = smaint_assignable_users();

$flagLabels = [
    ''             => 'Tümü',
    'overdue'      => 'Gecikenler',
    'msg_sent'     => 'Mesaj gönderilenler',
    'msg_unsent'   => 'Mesaj gönderilmeyenler',
    'appointment'  => 'Randevu oluşturulanlar',
    'not_interested' => 'İlgilenmeyenler',
];

// Filtre query string (CSV ve sayfalama için)
$qs = http_build_query(array_filter($f, static fn($v) => $v !== '' && $v !== 0));

layout_top('Bakım Takipleri', 'service');
?>
<div class="page-head">
    <h1 class="page-title"><?= icon('wrench') ?> Bakım Takipleri</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/maintenance/export.php' . ($qs !== '' ? '?' . $qs : ''))) ?>"><?= icon('download') ?>CSV Dışa Aktar</a>
    </div>
</div>
<?php render_flashes(); ?>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
    <a class="badge badge-info" href="<?= e(url('modules/maintenance/index.php?due_from=' . date('Y-m-d') . '&due_to=' . date('Y-m-d', strtotime('+7 day')))) ?>">Bu hafta: <?= (int) $counts['week'] ?></a>
    <a class="badge badge-warning" href="<?= e(url('modules/maintenance/index.php?due_from=' . date('Y-m-d') . '&due_to=' . date('Y-m-d'))) ?>">Bugün: <?= (int) $counts['today'] ?></a>
    <a class="badge <?= $counts['overdue'] > 0 ? 'badge-danger' : 'badge-muted' ?>" href="<?= e(url('modules/maintenance/index.php?flag=overdue')) ?>">Geciken: <?= (int) $counts['overdue'] ?></a>
    <a class="badge badge-muted" href="<?= e(url('modules/maintenance/index.php?flag=msg_unsent')) ?>">Mesaj bekleyen: <?= (int) $counts['msg_unsent'] ?></a>
    <span class="badge badge-muted">Bekleyen dönüş: <?= (int) $counts['awaiting'] ?></span>
    <span class="badge badge-success">Randevu: <?= (int) $counts['appointment'] ?></span>
</div>

<form method="get" action="<?= e(url('modules/maintenance/index.php')) ?>" class="toolbar">
    <div class="form-group"><label for="search">Ara</label><input type="text" id="search" name="search" value="<?= e($f['search']) ?>" placeholder="Müşteri, telefon, seri, servis no…"></div>
    <div class="form-group"><label for="brand">Marka</label><input type="text" id="brand" name="brand" value="<?= e($f['brand']) ?>"></div>
    <div class="form-group"><label for="model">Model</label><input type="text" id="model" name="model" value="<?= e($f['model']) ?>"></div>
    <div class="form-group"><label for="serial">Seri No</label><input type="text" id="serial" name="serial" value="<?= e($f['serial']) ?>"></div>
    <div class="form-group">
        <label for="status">Durum</label>
        <select id="status" name="status"><option value="">Tümü</option>
            <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="assigned">Personel</label>
        <select id="assigned" name="assigned"><option value="0">Tümü</option>
            <?php foreach ($users as $uidk => $un): ?><option value="<?= (int) $uidk ?>"<?= $f['assigned'] === $uidk ? ' selected' : '' ?>><?= e($un) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="channel">İletişim</label>
        <select id="channel" name="channel"><option value="">Tümü</option>
            <?php foreach ($channels as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['channel'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="flag">Hızlı Filtre</label>
        <select id="flag" name="flag"><?php foreach ($flagLabels as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['flag'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
    </div>
    <div class="form-group"><label for="due_from">Bakım (baş.)</label><input type="date" id="due_from" name="due_from" value="<?= e($f['due_from']) ?>"></div>
    <div class="form-group"><label for="due_to">Bakım (bit.)</label><input type="date" id="due_to" name="due_to" value="<?= e($f['due_to']) ?>"></div>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Filtrele</button></div>
</form>

<?php if (!$rows): ?>
    <div class="card"><div class="card-body"><div class="empty">Bakım takip kaydı bulunamadı.</div></div></div>
<?php else: ?>
<form method="post" action="<?= e(url('modules/maintenance/bulk.php')) ?>" id="bulkForm">
    <?= csrf_field() ?>
    <input type="hidden" name="return" value="modules/maintenance/index.php<?= $qs !== '' ? '?' . e($qs) : '' ?>">
    <div class="toolbar" style="align-items:flex-end">
        <div class="form-group">
            <label for="bulk_action">Toplu İşlem (seçili)</label>
            <select id="bulk_action" name="bulk_action">
                <option value="">— İşlem seç —</option>
                <?php if (can_maint_assign()): ?><option value="assign">Personel ata</option><?php endif; ?>
                <?php if (can_maint_edit()): ?><option value="set_status">Durum değiştir</option><?php endif; ?>
                <?php if (can_maint_edit()): ?><option value="set_due">Bakım tarihini değiştir</option><?php endif; ?>
                <?php if (can_maint_message()): ?><option value="call_list">Arama listesine ekle</option><?php endif; ?>
                <?php if (can_maint_whatsapp()): ?><option value="wa_list">WhatsApp listesine ekle</option><?php endif; ?>
                <?php if (can_maint_email()): ?><option value="email_queue">E-posta kuyruğuna ekle</option><?php endif; ?>
                <?php if (can_maint_cancel()): ?><option value="cancel">İptal et</option><?php endif; ?>
            </select>
        </div>
        <div class="form-group" data-bulk="assign" style="display:none"><label>Kullanıcı</label>
            <select name="assign_user"><option value="0">—</option><?php foreach ($users as $uidk => $un): ?><option value="<?= (int) $uidk ?>"><?= e($un) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-group" data-bulk="set_status" style="display:none"><label>Yeni durum</label>
            <select name="new_status"><?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-group" data-bulk="set_due" style="display:none"><label>Yeni bakım tarihi</label><input type="date" name="new_due"></div>
        <div class="form-group"><button type="submit" class="btn btn-sm btn-primary">Uygula</button></div>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:28px"><input type="checkbox" id="checkAll"></th>
                    <th>Servis No</th>
                    <th>Müşteri</th>
                    <th>Ürün</th>
                    <th>Seri No</th>
                    <th>Teslim</th>
                    <th>Bakım Tarihi</th>
                    <th>Kalan/Geçen</th>
                    <th>Telefon</th>
                    <th>Personel</th>
                    <th>Son İletişim</th>
                    <th>Durum</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r):
                    $days = smaint_days_to_due($r['maintenance_due_date']);
                    $vurl = url('modules/maintenance/view.php?id=' . (int) $r['id']);
                    $device = trim(((string) ($r['brand_name'] ?? '')) . ' ' . ((string) ($r['device_model'] ?? '')));
                    $phone = (string) ($r['phone'] ?? '');
                ?>
                <tr>
                    <td><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" class="rowChk"></td>
                    <td class="nowrap"><?= $r['reference_code'] ? '<strong>' . e((string) $r['reference_code']) . '</strong>' : '—' ?></td>
                    <td class="wrap"><?= e((string) ($r['company_name'] ?: $r['customer_name'] ?: '—')) ?></td>
                    <td class="wrap"><?= e($device !== '' ? $device : '—') ?></td>
                    <td class="nowrap"><?= e((string) ($r['serial_no'] ?? '—')) ?: '—' ?></td>
                    <td class="nowrap"><?= e(fmt_date((string) $r['delivery_date'])) ?></td>
                    <td class="nowrap"><?= e(substr((string) $r['maintenance_due_date'], 0, 10)) ?></td>
                    <td class="nowrap">
                        <?php if ($days === null): ?>—
                        <?php elseif ($days < 0): ?><span class="badge badge-danger"><?= abs($days) ?> gün geçti</span>
                        <?php elseif ($days === 0): ?><span class="badge badge-warning">Bugün</span>
                        <?php else: ?><span class="badge badge-muted"><?= $days ?> gün</span><?php endif; ?>
                    </td>
                    <td class="nowrap"><?= e($phone ?: '—') ?></td>
                    <td class="wrap"><?= e((string) ($r['assignee_name'] ?? '—')) ?: '—' ?></td>
                    <td class="nowrap"><?= $r['last_contact_at'] ? e(fmt_date((string) $r['last_contact_at'])) : '—' ?></td>
                    <td><span class="badge <?= smaint_status_class((string) $r['status']) ?>"><?= e(smaint_status_label((string) $r['status'])) ?></span></td>
                    <td class="nowrap">
                        <a class="btn btn-sm" href="<?= e($vurl) ?>" title="Aç"><?= icon('eye') ?>Aç</a>
                        <?php if ($phone !== ''): ?><a class="btn btn-sm" href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>" title="Ara"><?= icon('phone') ?></a><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</form>
<script>
(function(){
    var all = document.getElementById('checkAll');
    if (all) all.addEventListener('change', function(){
        document.querySelectorAll('.rowChk').forEach(function(c){ c.checked = all.checked; });
    });
    var sel = document.getElementById('bulk_action');
    if (sel) {
        var toggle = function(){
            document.querySelectorAll('[data-bulk]').forEach(function(el){
                el.style.display = (el.getAttribute('data-bulk') === sel.value) ? '' : 'none';
            });
        };
        sel.addEventListener('change', toggle); toggle();
    }
})();
</script>
<?php endif; ?>

<?php layout_bottom(); ?>
