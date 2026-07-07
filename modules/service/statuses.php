<?php
declare(strict_types=1);

/**
 * modules/service/statuses.php
 * Servis Durumları — sabit durum listesi ve her durumdaki kayıt sayısı.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service.php';

auth_boot();
require_permission('service');

$statuses = service_statuses();
$counts = [];
try {
    foreach (db()->query('SELECT status, COUNT(*) c FROM service_records GROUP BY status')->fetchAll() as $row) {
        $counts[(string) $row['status']] = (int) $row['c'];
    }
} catch (Throwable $e) {
    log_error('statuses count: ' . $e->getMessage());
}

layout_top('Servis Durumları', 'service');
?>

<div class="page-head">
    <h1 class="page-title">Servis Durumları</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/service/index.php')) ?>">← Servis</a></div>
</div>

<p class="muted">Sistemdeki servis durumları ve her birindeki kayıt sayısı. Durumlar sabittir; kayıt akışı bu adımlarda ilerler.</p>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Durum</th><th>Anahtar</th><th>Kayıt sayısı</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($statuses as $k => $label): ?>
                <tr>
                    <td><span class="badge <?= service_status_class($k) ?>"><?= e($label) ?></span></td>
                    <td><code><?= e($k) ?></code></td>
                    <td><?= (int) ($counts[$k] ?? 0) ?></td>
                    <td><a class="btn btn-sm" href="<?= e(url('modules/service/index.php?status=' . urlencode($k))) ?>">Bu durumdakiler</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
layout_bottom();
