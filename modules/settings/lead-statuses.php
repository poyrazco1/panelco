<?php
declare(strict_types=1);

/**
 * modules/settings/lead-statuses.php — Lead durum akışı yönetimi (§6).
 * Durumları ekle/düzenle/sil; renk, sıra, aktif ve sonuç (tamamlandı/başarı/
 * başarısızlık) bayrakları. Kullanımdaki durum silinemez → pasife alınır.
 */

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leads.php';

auth_boot();
require_permission('settings');

$colors = lead_status_color_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add' || $action === 'update') {
        $id = $action === 'update' ? ((int) ($_POST['id'] ?? 0) ?: null) : null;
        $res = lead_status_save([
            'code'         => $_POST['code'] ?? '',
            'name'         => $_POST['name'] ?? '',
            'color'        => $_POST['color'] ?? 'badge-muted',
            'sort_order'   => $_POST['sort_order'] ?? 0,
            'is_active'    => $_POST['is_active'] ?? 0,
            'is_completed' => $_POST['is_completed'] ?? 0,
            'is_success'   => $_POST['is_success'] ?? 0,
            'is_failure'   => $_POST['is_failure'] ?? 0,
        ], $id);
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Durum kaydedildi.' : $res['error']);
    } elseif ($action === 'delete') {
        $res = lead_status_delete((int) ($_POST['id'] ?? 0));
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Durum silindi.' : $res['error']);
    }
    http_response_code(303);
    redirect('modules/settings/lead-statuses.php');
}

$rows = lead_status_manage_list();
layout_top('Lead Durumları', 'settings');
?>
<div class="page-head">
    <h1 class="page-title">Lead Durumları</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Genel Ayarlar</a></div>
</div>
<?= render_flashes() ?>
<p class="muted" style="max-width:820px">Lead satış akışının durumları. <strong>Kod</strong> oluşturulduktan sonra değiştirilemez (mevcut lead'lerle tutarlılık için). “Tamamlandı” işaretli durumlar akışın bittiğini; “Başarı/Başarısızlık” ise sonucunu belirtir (raporlarda kullanılır).</p>

<div class="table-wrap"><table class="table">
    <thead><tr><th>Kod</th><th>İsim</th><th>Renk</th><th>Sıra</th><th>Aktif</th><th>Bitiş</th><th>Başarı</th><th>Başarısız</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr><form method="post" action="<?= e(url('modules/settings/lead-statuses.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <td class="nowrap"><code><?= e((string) $r['code']) ?></code></td>
            <td><input type="text" name="name" value="<?= e((string) $r['name']) ?>" style="min-width:140px"></td>
            <td><select name="color"><?php foreach ($colors as $ck => $cl): ?><option value="<?= e($ck) ?>"<?= $r['color'] === $ck ? ' selected' : '' ?>><?= e($cl) ?></option><?php endforeach; ?></select>
                <span class="badge <?= e((string) $r['color']) ?>" style="margin-left:6px"><?= e((string) $r['name']) ?></span></td>
            <td style="width:80px"><input type="number" name="sort_order" value="<?= (int) $r['sort_order'] ?>" style="width:64px"></td>
            <td><input type="checkbox" name="is_active" value="1"<?= (int) $r['is_active'] === 1 ? ' checked' : '' ?>></td>
            <td><input type="checkbox" name="is_completed" value="1"<?= (int) $r['is_completed'] === 1 ? ' checked' : '' ?>></td>
            <td><input type="checkbox" name="is_success" value="1"<?= (int) $r['is_success'] === 1 ? ' checked' : '' ?>></td>
            <td><input type="checkbox" name="is_failure" value="1"<?= (int) $r['is_failure'] === 1 ? ' checked' : '' ?>></td>
            <td class="nowrap"><button class="btn btn-xs btn-primary">Kaydet</button></form>
                <form method="post" action="<?= e(url('modules/settings/lead-statuses.php')) ?>" style="display:inline" onsubmit="return confirm('Bu durum silinsin mi? (Kullanımdaysa pasife alınır)')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-xs"><?= icon('trash-2', 'icon-xs') ?></button></form></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9" class="muted">Henüz durum yok. Aşağıdan ekleyin.</td></tr><?php endif; ?>
    </tbody>
</table></div>

<div class="card" style="max-width:820px"><div class="card-header"><strong>Yeni Durum</strong></div><div class="card-body">
    <form method="post" action="<?= e(url('modules/settings/lead-statuses.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group"><label>Kod (opsiyonel)</label><input type="text" name="code" placeholder="isimden otomatik"></div>
            <div class="form-group"><label>İsim</label><input type="text" name="name" required></div>
            <div class="form-group"><label>Renk</label><select name="color"><?php foreach ($colors as $ck => $cl): ?><option value="<?= e($ck) ?>"><?= e($cl) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Sıra</label><input type="number" name="sort_order" value="0" style="max-width:90px"></div>
        </div>
        <div class="form-group">
            <label class="checkbox"><input type="checkbox" name="is_active" value="1" checked> Aktif</label>
            <label class="checkbox"><input type="checkbox" name="is_completed" value="1"> Akış tamamlandı</label>
            <label class="checkbox"><input type="checkbox" name="is_success" value="1"> Başarılı sonuç</label>
            <label class="checkbox"><input type="checkbox" name="is_failure" value="1"> Başarısız sonuç</label>
        </div>
        <button class="btn btn-primary btn-sm"><?= icon('plus') ?>Ekle</button>
    </form>
</div></div>
<?php layout_bottom();
