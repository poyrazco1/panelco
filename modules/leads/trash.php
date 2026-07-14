<?php
declare(strict_types=1);

/**
 * modules/leads/trash.php — Lead çöp kutusu (§10). Yumuşak silinmiş lead'ler;
 * geri yükle veya (yalnızca Süper Admin, 2. onayla) kalıcı sil.
 */

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leads_crm.php';

auth_boot();
require_permission('leads.trash');

$isSuper = lead_is_super_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'restore') {
        require_action('leads', 'restore');
        $ok = lead_restore($id, current_user_id());
        flash($ok ? 'success' : 'error', $ok ? 'Lead geri yüklendi.' : 'Geri yükleme başarısız.');
    } elseif ($action === 'purge') {
        // KALICI silme: yalnızca Süper Admin + 2. onay
        if (!$isSuper) {
            flash('error', 'Kalıcı silme yalnızca Süper Admin yetkisiyle yapılabilir.');
        } elseif ((string) ($_POST['confirm'] ?? '') !== 'KALICI') {
            flash('error', 'Kalıcı silme için ekstra onay gerekli.');
        } else {
            $ok = lead_purge($id, current_user_id());
            flash($ok ? 'success' : 'error', $ok ? 'Lead kalıcı olarak silindi (denetim kaydı korundu).' : 'Kalıcı silme başarısız.');
        }
    }
    http_response_code(303);
    redirect('modules/leads/trash.php');
}

// restore/purge above call functions twice for the message; guard: re-fetch clean list
$search = trim((string) ($_GET['q'] ?? ''));
$rows = get_trashed_leads($search);

layout_top('Lead Çöp Kutusu', 'leads');
?>
<div class="page-head">
    <h1 class="page-title">Lead Çöp Kutusu</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/leads/index.php')) ?>"><?= icon('arrow-left') ?>Lead Listesi</a></div>
</div>
<?= render_flashes() ?>
<form method="get" action="<?= e(url('modules/leads/trash.php')) ?>" class="toolbar">
    <div class="form-group"><label for="q">Ara</label><input type="text" id="q" name="q" value="<?= e($search) ?>" placeholder="Firma, telefon, şehir"></div>
    <div class="form-group"><button class="btn btn-sm"><?= icon('search') ?>Ara</button></div>
</form>
<?php if (!$rows): ?>
    <div class="card"><div class="card-body"><div class="empty">Çöp kutusu boş.</div></div></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Firma</th><th class="nowrap">Telefon</th><th>Şehir</th><th class="nowrap">Silinme</th><th>Silen</th><th>Sebep</th><th class="nowrap">İşlem</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><strong><?= e((string) $r['company_name']) ?></strong></td>
            <td class="nowrap"><?= e((string) ($r['phone'] ?? '')) ?: '—' ?></td>
            <td><?= e((string) ($r['city'] ?? '')) ?: '—' ?></td>
            <td class="nowrap"><?= e((string) ($r['deleted_at'] ?? '')) ?></td>
            <td><?= e((string) ($r['deleted_by_name'] ?? '')) ?: '—' ?></td>
            <td><?= e((string) ($r['delete_reason'] ?? '')) ?: '—' ?></td>
            <td class="nowrap">
                <?php if (can('leads.restore')): ?>
                <form method="post" action="<?= e(url('modules/leads/trash.php')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-xs"><?= icon('rotate-ccw', 'icon-xs') ?>Geri Yükle</button></form>
                <?php endif; ?>
                <?php if ($isSuper): ?>
                <form method="post" action="<?= e(url('modules/leads/trash.php')) ?>" style="display:inline" onsubmit="return confirm('DİKKAT: Bu lead ve tüm ilişkili kayıtları KALICI olarak silinecek. Geri alınamaz. Devam edilsin mi?');">
                    <?= csrf_field() ?><input type="hidden" name="action" value="purge"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <input type="hidden" name="confirm" value="KALICI">
                    <button class="btn btn-xs btn-danger" title="Kalıcı sil (Süper Admin)"><?= icon('trash-2', 'icon-xs') ?>Kalıcı Sil</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<p class="muted small" style="margin-top:12px">Kalıcı silme yalnızca Süper Admin tarafından, ekstra onayla yapılabilir; işlem denetim kaydına (lead_audit_logs) yazılır ve geri alınamaz.</p>
<?php endif; ?>
<?php layout_bottom();
