<?php
declare(strict_types=1);

/**
 * modules/forms/submissions.php — Form gönderimleri listesi (filtreli).
 * Yönetici tümünü; normal kullanıcı yalnızca kendi/atanan kayıtları görür.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/form-center.php';

auth_boot();
require_permission('forms.submissions.view');

$manage = form_center_can_manage();
$f = [
    'template_id'      => (int) ($_GET['template_id'] ?? 0),
    'category_id'      => (int) ($_GET['category_id'] ?? 0),
    'status'          => (string) ($_GET['status'] ?? ''),
    'approval_status' => (string) ($_GET['approval_status'] ?? ''),
    'assigned_user_id' => (int) ($_GET['assigned_user_id'] ?? 0),
    'form_type'       => (string) ($_GET['form_type'] ?? ''),
    'date_from'       => (string) ($_GET['date_from'] ?? ''),
    'date_to'         => (string) ($_GET['date_to'] ?? ''),
    'phone'           => trim((string) ($_GET['phone'] ?? '')),
    'customer'        => trim((string) ($_GET['customer'] ?? '')),
];
$rows = form_center_submissions($f);
$templates = form_center_templates(['only_active' => false]);
$categories = form_center_categories();
$users = [];
try { foreach (db()->query('SELECT id, full_name, username FROM users WHERE is_active=1 ORDER BY full_name')->fetchAll() as $u) { $users[(int) $u['id']] = $u['full_name'] ?: $u['username']; } } catch (Throwable $e) {}

$exportQs = http_build_query(array_filter($f));

layout_top('Form Kayıtları', 'forms');
?>
<div class="page-head">
    <h1 class="page-title">Form Kayıtları</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/forms/index.php')) ?>">← Form Merkezi</a>
        <a class="btn btn-sm" href="<?= e(url('modules/forms/export.php?' . $exportQs)) ?>"><?= icon('download') ?>CSV</a>
    </div>
</div>
<?= render_flashes() ?>

<form method="get" action="<?= e(url('modules/forms/submissions.php')) ?>" class="toolbar">
    <div class="form-group"><label for="template_id">Form</label><select id="template_id" name="template_id"><option value="0">Tümü</option>
        <?php foreach ($templates as $t): ?><option value="<?= (int) $t['id'] ?>"<?= $f['template_id'] === (int) $t['id'] ? ' selected' : '' ?>><?= e((string) $t['form_name']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="category_id">Kategori</label><select id="category_id" name="category_id"><option value="0">Tümü</option>
        <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $f['category_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e((string) $c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="status">Durum</label><select id="status" name="status"><option value="">Tümü</option>
        <?php foreach (form_statuses() as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="approval_status">Onay</label><select id="approval_status" name="approval_status"><option value="">Tümü</option>
        <?php foreach (form_approval_statuses() as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['approval_status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="form_type">Tip</label><select id="form_type" name="form_type"><option value="">Tümü</option>
        <?php foreach (form_types() as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['form_type'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <?php if ($manage): ?>
    <div class="form-group"><label for="assigned_user_id">Atanan</label><select id="assigned_user_id" name="assigned_user_id"><option value="0">Tümü</option>
        <?php foreach ($users as $uid => $un): ?><option value="<?= (int) $uid ?>"<?= $f['assigned_user_id'] === (int) $uid ? ' selected' : '' ?>><?= e($un) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <div class="form-group"><label for="customer">Müşteri</label><input type="text" id="customer" name="customer" value="<?= e($f['customer']) ?>"></div>
    <div class="form-group"><label for="phone">Telefon</label><input type="text" id="phone" name="phone" value="<?= e($f['phone']) ?>"></div>
    <div class="form-group"><label for="date_from">Başlangıç</label><input type="date" id="date_from" name="date_from" value="<?= e($f['date_from']) ?>"></div>
    <div class="form-group"><label for="date_to">Bitiş</label><input type="date" id="date_to" name="date_to" value="<?= e($f['date_to']) ?>"></div>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Filtrele</button></div>
</form>

<?php if (!$rows): ?>
<div class="empty-state empty-compact"><p>Kayıt bulunamadı.</p></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Talep No</th><th>Form</th><th>Gönderen</th><th>Firma / Müşteri</th><th>Telefon</th><th>Durum</th><th>Onay</th><th>Atanan</th><th class="nowrap">Tarih</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><a href="<?= e(url('modules/forms/submission-view.php?id=' . (int) $r['id'])) ?>"><strong><?= e((string) $r['submission_no']) ?></strong></a></td>
            <td><?= e((string) $r['form_name']) ?></td>
            <td><?= e((string) ($r['submitter_name'] ?? ($r['external_token_id'] ? 'Dış Form' : '—'))) ?></td>
            <td><?= e(trim((string) ($r['company_name'] ?? '') . ' ' . (string) ($r['customer_name'] ?? ''))) ?: '—' ?></td>
            <td class="nowrap"><?= e((string) ($r['phone'] ?? '')) ?: '—' ?></td>
            <td><span class="badge <?= e(form_status_class((string) $r['status'])) ?>"><?= e(form_status_label((string) $r['status'])) ?></span></td>
            <td><?php if ((string) $r['approval_status'] !== 'none'): ?><span class="badge <?= e(form_approval_class((string) $r['approval_status'])) ?>"><?= e(form_approval_label((string) $r['approval_status'])) ?></span><?php else: ?>—<?php endif; ?></td>
            <td><?= e((string) ($r['assignee_name'] ?? '')) ?: '—' ?></td>
            <td class="nowrap"><?= e(fmt_date((string) $r['created_at'])) ?></td>
            <td class="nowrap"><a class="btn btn-xs" href="<?= e(url('modules/forms/submission-view.php?id=' . (int) $r['id'])) ?>"><?= icon('eye', 'icon-xs') ?></a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
<?php layout_bottom();
