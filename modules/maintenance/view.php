<?php
declare(strict_types=1);

/**
 * modules/maintenance/view.php
 * Bakım hatırlatma detayı: bilgiler, durum/işlem paneli, geçmişler (§2, §9).
 * İletişim (e-posta/WhatsApp), randevu ve servise dönüştürme bölümleri ayrı
 * partial'larda (Faz 5-6) eklenir.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service_maintenance.php';

auth_boot();
if (!can_maint_view()) { require_permission('maintenance.view'); }

$id  = (int) ($_GET['id'] ?? 0);
$r   = smaint_reminder_get($id);
if (!$r) { http_response_code(404); flash('error', 'Bakım kaydı bulunamadı.'); redirect('modules/maintenance/index.php'); }

$uid = current_user_id() ?? 0;
if (!can_maint_view_all() && (int) ($r['assigned_user_id'] ?? 0) !== $uid) {
    require_permission('maintenance.view_all');
}

$statuses = smaint_statuses();
$users    = smaint_assignable_users();
$history  = smaint_status_history($id);
$postpones = smaint_postpone_history($id);
$days     = smaint_days_to_due((string) $r['maintenance_due_date']);
$device   = trim(((string) ($r['brand_name'] ?? '')) . ' ' . ((string) ($r['device_model'] ?? '')));
$actUrl   = url('modules/maintenance/action.php');
$ret      = 'modules/maintenance/view.php?id=' . $id;

layout_top('Bakım Kaydı · ' . ($r['reference_code'] ?: ('#' . $id)), 'service');
?>
<div class="page-head">
    <h1 class="page-title"><?= icon('wrench') ?> Bakım Kaydı
        <span class="badge <?= smaint_status_class((string) $r['status']) ?>"><?= e(smaint_status_label((string) $r['status'])) ?></span>
    </h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/maintenance/index.php')) ?>"><?= icon('clipboard-list') ?>Listeye Dön</a>
        <?php if (!empty($r['service_id'])): ?>
        <a class="btn btn-sm" href="<?= e(url('modules/service/view.php?id=' . (int) $r['service_id'])) ?>"><?= icon('eye') ?>Servis Kaydını Görüntüle</a>
        <?php endif; ?>
    </div>
</div>
<?php render_flashes(); ?>

<div class="grid-2" style="display:grid;grid-template-columns:2fr 1fr;gap:16px;align-items:start">
  <div>
    <div class="card">
        <div class="card-header"><h2>Müşteri &amp; Cihaz</h2></div>
        <div class="card-body">
            <div class="kv"><span>Servis No</span><strong><?= e((string) ($r['reference_code'] ?? '—')) ?></strong></div>
            <div class="kv"><span>Müşteri / Firma</span><strong><?= e((string) ($r['company_name'] ?: $r['customer_name'] ?: '—')) ?></strong></div>
            <?php if (!empty($r['contact_name'])): ?><div class="kv"><span>Yetkili</span><span><?= e((string) $r['contact_name']) ?></span></div><?php endif; ?>
            <div class="kv"><span>Telefon</span><span><?= e((string) ($r['phone'] ?? '—')) ?: '—' ?></span></div>
            <div class="kv"><span>WhatsApp</span><span><?= e((string) ($r['whatsapp'] ?? '—')) ?: '—' ?></span></div>
            <div class="kv"><span>E-posta</span><span><?= e((string) ($r['email'] ?? '—')) ?: '—' ?></span></div>
            <div class="kv"><span>Ürün</span><span><?= e($device !== '' ? $device : '—') ?></span></div>
            <div class="kv"><span>Seri No</span><span><?= e((string) ($r['serial_no'] ?? '—')) ?: '—' ?></span></div>
            <?php if (!empty($r['work_done'])): ?><div class="kv"><span>Yapılan İşlem</span><span><?= e((string) $r['work_done']) ?></span></div><?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Bakım Planı</h2></div>
        <div class="card-body">
            <div class="kv"><span>Teslim Tarihi</span><span><?= e(fmt_date((string) $r['delivery_date'])) ?></span></div>
            <div class="kv"><span>Planlanan Bakım</span><strong><?= e(substr((string) $r['maintenance_due_date'], 0, 10)) ?></strong></div>
            <div class="kv"><span>Kalan/Geçen</span><span>
                <?php if ($days === null): ?>—
                <?php elseif ($days < 0): ?><span class="badge badge-danger"><?= abs($days) ?> gün geçti</span>
                <?php elseif ($days === 0): ?><span class="badge badge-warning">Bugün</span>
                <?php else: ?><span class="badge badge-muted"><?= $days ?> gün kaldı</span><?php endif; ?>
            </span></div>
            <div class="kv"><span>Periyot</span><span><?= e(smaint_period_options()[(string) ($r['period_key'] ?? '6m')] ?? '6 ay') ?></span></div>
            <div class="kv"><span>Atanan Personel</span><span><?= e((string) ($r['assignee_name'] ?? '—')) ?: '—' ?></span></div>
            <div class="kv"><span>Hatırlatma Sayısı</span><span><?= (int) $r['reminder_count'] ?></span></div>
            <div class="kv"><span>Erteleme Sayısı</span><span><?= (int) $r['postpone_count'] ?></span></div>
            <?php if (!empty($r['next_contact_at'])): ?><div class="kv"><span>Sonraki İletişim</span><span><?= e(fmt_date((string) $r['next_contact_at'])) ?></span></div><?php endif; ?>
            <div class="kv"><span>İletişim İzni</span><span>
                <?= (int) ($r['email_consent'] ?? 0) === 1 ? '<span class="badge badge-success">E-posta</span> ' : '' ?>
                <?= (int) ($r['whatsapp_consent'] ?? 0) === 1 ? '<span class="badge badge-success">WhatsApp</span> ' : '' ?>
                <?= (int) ($r['phone_consent'] ?? 0) === 1 ? '<span class="badge badge-success">Telefon</span>' : '' ?>
                <?= ((int) ($r['email_consent'] ?? 0) + (int) ($r['whatsapp_consent'] ?? 0) + (int) ($r['phone_consent'] ?? 0)) === 0 ? '<span class="badge badge-muted">İzin yok</span>' : '' ?>
            </span></div>
        </div>
    </div>

    <?php /* İLETİŞİM (e-posta/WhatsApp) ve RANDEVU/DÖNÜŞTÜRME bölümleri Faz 5-6'da eklenir. */ ?>
    <?php $commPartial = __DIR__ . '/_comm-panel.php'; if (is_file($commPartial)) { include $commPartial; } ?>
    <?php $apptPartial = __DIR__ . '/_appointment-panel.php'; if (is_file($apptPartial)) { include $apptPartial; } ?>

    <div class="card">
        <div class="card-header"><h2>Durum Geçmişi</h2></div>
        <div class="card-body">
            <?php if (!$history): ?><div class="empty">Kayıt yok.</div>
            <?php else: ?>
            <table class="table">
                <thead><tr><th>Tarih</th><th>Eski</th><th>Yeni</th><th>Kullanıcı</th><th>Açıklama</th></tr></thead>
                <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td class="nowrap"><?= e(fmt_date((string) $h['created_at'])) ?></td>
                        <td><?= e($h['old_status'] !== '' ? smaint_status_label((string) $h['old_status']) : '—') ?></td>
                        <td><?= e(smaint_status_label((string) $h['new_status'])) ?></td>
                        <td><?= e((string) ($h['by_name'] ?? '—')) ?: '—' ?></td>
                        <td class="wrap"><?= e((string) ($h['note'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($postpones): ?>
    <div class="card">
        <div class="card-header"><h2>Erteleme Geçmişi</h2></div>
        <div class="card-body">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Eski</th><th>Yeni</th><th>Kullanıcı</th><th>Sebep</th></tr></thead>
                <tbody>
                <?php foreach ($postpones as $p): ?>
                    <tr>
                        <td class="nowrap"><?= e(fmt_date((string) $p['created_at'])) ?></td>
                        <td class="nowrap"><?= e((string) ($p['old_contact_at'] ?: $p['old_due_date'] ?: '—')) ?></td>
                        <td class="nowrap"><?= e((string) ($p['new_contact_at'] ?: $p['new_due_date'] ?: '—')) ?></td>
                        <td><?= e((string) ($p['by_name'] ?? '—')) ?: '—' ?></td>
                        <td class="wrap"><?= e((string) ($p['reason'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
  </div>

  <div>
    <div class="card">
        <div class="card-header"><h2>Hızlı İşlemler</h2></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
            <?php $phoneClean = preg_replace('/\s+/', '', (string) ($r['phone'] ?? '')); ?>
            <?php if ($phoneClean): ?><a class="btn btn-sm" href="tel:<?= e($phoneClean) ?>"><?= icon('phone') ?>Müşteriyi Ara</a><?php endif; ?>

            <?php if (can_maint_message()): ?>
            <form method="post" action="<?= e($actUrl) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($ret) ?>">
                <input type="hidden" name="action" value="mark_called"><button class="btn btn-sm" type="submit"><?= icon('check-circle') ?>Arandı Olarak İşaretle</button></form>
            <form method="post" action="<?= e($actUrl) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($ret) ?>">
                <input type="hidden" name="action" value="mark_unreachable"><button class="btn btn-sm" type="submit"><?= icon('x') ?>Ulaşılamadı</button></form>
            <form method="post" action="<?= e($actUrl) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($ret) ?>">
                <input type="hidden" name="action" value="mark_awaiting"><button class="btn btn-sm" type="submit"><?= icon('clock') ?>Müşteri Dönüşü Bekleniyor</button></form>
            <?php endif; ?>

            <?php if (can_maint_edit()): ?>
            <form method="post" action="<?= e($actUrl) ?>" onsubmit="return this.new_contact_at.value!=''"><?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($ret) ?>"><input type="hidden" name="action" value="postpone">
                <label class="text-muted">Daha Sonra Hatırlat</label>
                <input type="datetime-local" name="new_contact_at" required>
                <input type="text" name="reason" placeholder="Sebep (ops.)">
                <button class="btn btn-sm" type="submit"><?= icon('clock') ?>Ertele</button>
            </form>
            <form method="post" action="<?= e($actUrl) ?>"><?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($ret) ?>"><input type="hidden" name="action" value="set_due">
                <label class="text-muted">Bakım Tarihini Değiştir</label>
                <input type="date" name="new_due" value="<?= e(substr((string) $r['maintenance_due_date'], 0, 10)) ?>" required>
                <button class="btn btn-sm" type="submit"><?= icon('calendar') ?>Güncelle</button>
            </form>
            <form method="post" action="<?= e($actUrl) ?>"><?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($ret) ?>"><input type="hidden" name="action" value="set_status">
                <label class="text-muted">Durum Değiştir</label>
                <select name="new_status"><?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>"<?= $r['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
                <button class="btn btn-sm" type="submit">Uygula</button>
            </form>
            <?php endif; ?>

            <?php if (can_maint_assign()): ?>
            <form method="post" action="<?= e($actUrl) ?>"><?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($ret) ?>"><input type="hidden" name="action" value="assign">
                <label class="text-muted">Personel Ata</label>
                <select name="assign_user"><option value="0">—</option><?php foreach ($users as $uidk => $un): ?><option value="<?= (int) $uidk ?>"<?= (int) ($r['assigned_user_id'] ?? 0) === $uidk ? ' selected' : '' ?>><?= e($un) ?></option><?php endforeach; ?></select>
                <button class="btn btn-sm" type="submit"><?= icon('user') ?>Ata</button>
            </form>
            <?php endif; ?>

            <?php if (can_maint_edit()): ?>
            <form method="post" action="<?= e($actUrl) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($ret) ?>">
                <input type="hidden" name="action" value="complete"><button class="btn btn-sm btn-primary" type="submit"><?= icon('check-circle') ?>Bakımı Tamamla</button></form>
            <form method="post" action="<?= e($actUrl) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($ret) ?>">
                <input type="hidden" name="action" value="not_interested"><button class="btn btn-sm" type="submit">İlgilenmiyor</button></form>
            <?php endif; ?>

            <?php if (can_maint_cancel()): ?>
            <form method="post" action="<?= e($actUrl) ?>" onsubmit="return confirm('Bakım kaydı iptal edilsin mi?')"><?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($ret) ?>"><input type="hidden" name="action" value="cancel">
                <input type="text" name="reason" placeholder="İptal sebebi (ops.)">
                <button class="btn btn-sm btn-danger" type="submit"><?= icon('x') ?>Bakım Kaydını Kapat / İptal</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
  </div>
</div>

<?php layout_bottom(); ?>
