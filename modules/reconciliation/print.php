<?php
declare(strict_types=1);

/** modules/reconciliation/print.php — Mutabakat yazdır/PDF (imza-kaşe alanlı). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/reconciliation_document.php';

auth_boot();
require_permission('reconciliation.view');

$id = (int) ($_GET['id'] ?? 0);
$r  = get_reconciliation($id);
if (!$r) { flash('error', 'Mutabakat bulunamadı.'); redirect('modules/reconciliation/index.php'); }

$fav = function_exists('pub_favicon_url') ? pub_favicon_url() : null;
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Mutabakat <?= e((string) $r['recon_no']) ?></title>
    <?php if ($fav): ?><link rel="icon" href="<?= e($fav) ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/print.css')) ?>">
</head>
<body>
<?php document_actions(); ?>
<div class="doc-sheet">
    <?= recon_document_inner_html($r) ?>
</div>
</body>
</html>
