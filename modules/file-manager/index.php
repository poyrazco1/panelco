<?php
declare(strict_types=1);

/**
 * modules/file-manager/index.php — Güvenli dosya yöneticisi (uploads/ kökü).
 * Gözat + yükle + klasör oluştur + sil. Tüm POST işlemleri CSRF korumalı.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/filemanager.php';

auth_boot();
require_permission('file_manager.view');

$rel = (string) ($_GET['p'] ?? '');
$abs = fm_resolve($rel);
if ($abs === null || !is_dir($abs)) { $abs = fm_root(); $rel = ''; }
else { $rel = fm_relative($abs); }

// ---- POST işlemleri ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $curAbs = fm_resolve((string) ($_POST['p'] ?? ''));
    if ($curAbs === null || !is_dir($curAbs)) { $curAbs = fm_root(); }
    $curRel = fm_relative($curAbs);

    if ($action === 'upload' && can('file_manager.upload')) {
        if (empty($_FILES['file']['name']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'Geçerli bir dosya seçin.');
        } elseif (($_FILES['file']['size'] ?? 0) > 20 * 1024 * 1024) {
            flash('error', 'Dosya en fazla 20 MB olabilir.');
        } else {
            $name = basename((string) $_FILES['file']['name']);
            if (!fm_safe_name($name) || fm_ext_blocked($name)) {
                flash('error', 'İzin verilmeyen dosya adı veya türü. PHP/çalıştırılabilir dosyalar yüklenemez.');
            } else {
                $dest = $curAbs . '/' . $name;
                if (is_file($dest)) { $name = pathinfo($name, PATHINFO_FILENAME) . '-' . date('His') . '.' . pathinfo($name, PATHINFO_EXTENSION); $dest = $curAbs . '/' . $name; }
                if (move_uploaded_file((string) $_FILES['file']['tmp_name'], $dest)) {
                    log_activity('file_upload', 'file', null, $curRel . '/' . $name, 'success', 'Dosya yüklendi');
                    flash('success', 'Dosya yüklendi: ' . $name);
                } else { flash('error', 'Dosya kaydedilemedi.'); }
            }
        }
    } elseif ($action === 'mkdir' && can('file_manager.upload')) {
        $folder = trim((string) ($_POST['folder'] ?? ''));
        if (!fm_safe_name($folder)) {
            flash('error', 'Geçersiz klasör adı.');
        } else {
            $newDir = $curAbs . '/' . $folder;
            if (is_dir($newDir)) { flash('error', 'Bu klasör zaten var.'); }
            elseif (@mkdir($newDir, 0775)) { log_activity('file_mkdir', 'file', null, $curRel . '/' . $folder, 'success', 'Klasör oluşturuldu'); flash('success', 'Klasör oluşturuldu.'); }
            else { flash('error', 'Klasör oluşturulamadı.'); }
        }
    } elseif ($action === 'delete' && can('file_manager.delete')) {
        $target = fm_resolve((string) ($_POST['target'] ?? ''));
        $name = $target !== null ? basename($target) : '';
        if ($target === null || !is_file($target) || fm_protected_name($name)) {
            flash('error', 'Bu dosya silinemez.');
        } elseif (@unlink($target)) {
            log_activity('file_delete', 'file', null, fm_relative($target), 'success', 'Dosya silindi');
            flash('success', 'Dosya silindi.');
        } else { flash('error', 'Silme başarısız.'); }
    }
    http_response_code(303);
    redirect('modules/file-manager/index.php?p=' . rawurlencode($curRel));
}

$list = fm_list($abs);
$crumbs = fm_breadcrumbs($rel);

layout_top('Dosya Yöneticisi', 'file_manager');
?>
<div class="page-head"><h1 class="page-title">Dosya Yöneticisi</h1></div>
<?= render_flashes() ?>

<div class="alert alert-info">Kök klasör <code>uploads/</code>’tur. PHP ve çalıştırılabilir dosyalar yüklenemez; sistem klasörlerine erişilemez.</div>

<div class="fm-crumbs">
    <?php foreach ($crumbs as $i => $c): ?>
        <?php if ($i > 0): ?><span class="fm-sep">/</span><?php endif; ?>
        <a href="<?= e(url('modules/file-manager/index.php?p=' . rawurlencode($c['rel']))) ?>"><?= e($c['name']) ?></a>
    <?php endforeach; ?>
</div>

<?php if (can('file_manager.upload')): ?>
<div class="fm-actions">
    <form method="post" action="<?= e(url('modules/file-manager/index.php')) ?>" enctype="multipart/form-data" class="fm-inline">
        <?= csrf_field() ?><input type="hidden" name="action" value="upload"><input type="hidden" name="p" value="<?= e($rel) ?>">
        <input type="file" name="file" required>
        <button type="submit" class="btn btn-sm"><?= icon('upload') ?>Yükle</button>
    </form>
    <form method="post" action="<?= e(url('modules/file-manager/index.php')) ?>" class="fm-inline">
        <?= csrf_field() ?><input type="hidden" name="action" value="mkdir"><input type="hidden" name="p" value="<?= e($rel) ?>">
        <input type="text" name="folder" placeholder="Yeni klasör adı">
        <button type="submit" class="btn btn-sm"><?= icon('plus') ?>Klasör</button>
    </form>
</div>
<?php endif; ?>

<div class="table-wrap"><table class="table">
    <thead><tr><th>Ad</th><th class="nowrap">Boyut</th><th class="nowrap">Değiştirilme</th><th class="nowrap"></th></tr></thead>
    <tbody>
        <?php if ($rel !== ''): ?>
            <?php $parent = strpos($rel, '/') !== false ? substr($rel, 0, strrpos($rel, '/')) : ''; ?>
            <tr><td><a href="<?= e(url('modules/file-manager/index.php?p=' . rawurlencode($parent))) ?>"><?= icon('chevron-left', 'icon-xs') ?> ..</a></td><td>—</td><td>—</td><td></td></tr>
        <?php endif; ?>
        <?php foreach ($list['dirs'] as $d): ?>
            <tr>
                <td><a href="<?= e(url('modules/file-manager/index.php?p=' . rawurlencode($d['rel']))) ?>"><strong><?= icon('warehouse', 'icon-xs') ?> <?= e($d['name']) ?></strong></a></td>
                <td>—</td><td>—</td><td></td>
            </tr>
        <?php endforeach; ?>
        <?php foreach ($list['files'] as $file): ?>
            <tr>
                <td><?= e($file['name']) ?></td>
                <td class="nowrap"><?= e(fm_human_size((int) $file['size'])) ?></td>
                <td class="nowrap"><?= e($file['modified'] ? date('d.m.Y H:i', (int) $file['modified']) : '—') ?></td>
                <td class="nowrap">
                    <a class="btn btn-xs" href="<?= e(url('modules/file-manager/download.php?f=' . rawurlencode($file['rel']))) ?>"><?= icon('download', 'icon-xs') ?></a>
                    <?php if (can('file_manager.delete') && !$file['is_htaccess']): ?>
                    <form method="post" action="<?= e(url('modules/file-manager/index.php')) ?>" style="display:inline" onsubmit="return confirm('Bu dosya silinsin mi?');">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="p" value="<?= e($rel) ?>"><input type="hidden" name="target" value="<?= e($file['rel']) ?>">
                        <button type="submit" class="btn btn-xs btn-danger"><?= icon('trash-2', 'icon-xs') ?></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$list['dirs'] && !$list['files']): ?>
            <tr><td colspan="4"><div class="empty">Bu klasör boş.</div></td></tr>
        <?php endif; ?>
    </tbody>
</table></div>
<?php layout_bottom();
