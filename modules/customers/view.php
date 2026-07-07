<?php
declare(strict_types=1);

/**
 * modules/customers/view.php
 * Müşteri detayı + geçmiş teklif/sipariş/servis/iade kayıtları.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/customers.php';

auth_boot();
require_permission('customers.view');

$id = (int) ($_GET['id'] ?? 0);
$c  = get_customer_by_id($id);
if (!$c) { flash('error', 'Müşteri bulunamadı.'); redirect('modules/customers/index.php'); }

/** Basit ilişki sorgusu (tablo yoksa sessiz boş döner). */
$rel = static function (string $sql, array $params): array {
    try { $st = db()->prepare($sql); $st->execute($params); return $st->fetchAll(); }
    catch (Throwable $e) { return []; }
};

$quotes = can('quotes.view') ? $rel('SELECT id, quote_no, quote_date, grand_total, currency, status FROM quotes WHERE customer_id = :id AND is_deleted = 0 ORDER BY id DESC LIMIT 20', [':id' => $id]) : [];
$orders = can('orders.view') ? $rel('SELECT id, order_no, order_date, grand_total, currency, status FROM orders WHERE customer_id = :id AND is_deleted = 0 ORDER BY id DESC LIMIT 20', [':id' => $id]) : [];
$services = can('service.view') ? $rel('SELECT reference_code, status, created_at FROM service_records WHERE is_deleted = 0 AND customer_name = :n ORDER BY id DESC LIMIT 20', [':n' => $c['company_name']]) : [];
$rmas   = can('rma.view') ? $rel('SELECT reference_code, status, created_at FROM rma_records WHERE is_deleted = 0 AND customer_name = :n ORDER BY id DESC LIMIT 20', [':n' => $c['company_name']]) : [];

layout_top('Müşteri: ' . $c['company_name'], 'customers');
$row = static fn(string $l, ?string $v): string => trim((string) $v) !== '' ? '<div class="dl-row"><span class="dl-k">' . e($l) . '</span><span class="dl-v">' . e((string) $v) . '</span></div>' : '';
?>
<div class="page-head">
    <h1 class="page-title"><?= e($c['company_name']) ?></h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/customers/index.php')) ?>">← Müşteriler</a>
        <?php if (can('customers.edit')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/customers/edit.php?id=' . $id)) ?>"><?= icon('pencil') ?>Düzenle</a><?php endif; ?>
    </div>
</div>

<?= render_flashes() ?>

<div class="detail-grid">
    <div class="card">
        <div class="card-header"><h2>Firma Bilgileri</h2>
            <span><?= (int) $c['is_active'] === 1 ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-muted">Pasif</span>' ?>
            <span class="badge badge-info"><?= e(customer_type_label((string) $c['customer_type'])) ?></span></span>
        </div>
        <div class="card-body">
            <div class="dl">
                <?= $row('Cari kod', $c['code']) ?>
                <?= $row('Yetkili kişi', $c['contact_name']) ?>
                <?= $row('Telefon', $c['phone']) ?>
                <?= $row('WhatsApp', $c['whatsapp']) ?>
                <?= $row('E-posta', $c['email']) ?>
                <?= $row('Web sitesi', $c['website']) ?>
                <?= $row('Vergi dairesi', $c['tax_office']) ?>
                <?= $row('Vergi / TC no', $c['tax_no']) ?>
                <?= $row('Ülke', $c['country']) ?>
                <?= $row('Şehir', $c['city']) ?>
                <?= $row('İlçe', $c['district']) ?>
                <?= $row('Adres', $c['address']) ?>
                <?= $row('Kaynak', $c['source']) ?>
                <?= $row('Notlar', $c['notes']) ?>
            </div>
        </div>
    </div>
</div>

<?php
$relTable = static function (string $title, array $rows, callable $render): void {
    echo '<div class="card"><div class="card-header"><h2>' . e($title) . '</h2></div><div class="card-body">';
    if (!$rows) { echo '<div class="empty">Kayıt yok.</div>'; }
    else { echo '<div class="table-wrap"><table class="table">'; $render($rows); echo '</table></div>'; }
    echo '</div></div>';
};

if (can('quotes.view')) {
    $relTable('Teklifler', $quotes, static function (array $rows): void {
        echo '<thead><tr><th>No</th><th>Tarih</th><th>Tutar</th><th>Durum</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td><a href="' . e(url('modules/quotes/view.php?id=' . (int) $r['id'])) . '">' . e($r['quote_no']) . '</a></td>'
               . '<td>' . e((string) ($r['quote_date'] ?? '—')) . '</td>'
               . '<td>' . e(fmt_money((float) $r['grand_total']) . ' ' . $r['currency']) . '</td>'
               . '<td>' . e(function_exists('quote_status_label') ? quote_status_label((string) $r['status']) : (string) $r['status']) . '</td></tr>';
        }
        echo '</tbody>';
    });
}
if (can('orders.view')) {
    $relTable('Siparişler', $orders, static function (array $rows): void {
        echo '<thead><tr><th>No</th><th>Tarih</th><th>Tutar</th><th>Durum</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td><a href="' . e(url('modules/orders/view.php?id=' . (int) $r['id'])) . '">' . e($r['order_no']) . '</a></td>'
               . '<td>' . e((string) ($r['order_date'] ?? '—')) . '</td>'
               . '<td>' . e(fmt_money((float) $r['grand_total']) . ' ' . $r['currency']) . '</td>'
               . '<td>' . e((string) $r['status']) . '</td></tr>';
        }
        echo '</tbody>';
    });
}
if (can('service.view')) {
    $relTable('Servis Kayıtları', $services, static function (array $rows): void {
        echo '<thead><tr><th>Referans</th><th>Durum</th><th>Tarih</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td>' . e((string) $r['reference_code']) . '</td><td>' . e(function_exists('service_status_label') ? service_status_label((string) $r['status']) : (string) $r['status']) . '</td><td>' . e(fmt_date((string) $r['created_at'])) . '</td></tr>';
        }
        echo '</tbody>';
    });
}
if (can('rma.view')) {
    $relTable('İade / Değişim', $rmas, static function (array $rows): void {
        echo '<thead><tr><th>Referans</th><th>Durum</th><th>Tarih</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td>' . e((string) $r['reference_code']) . '</td><td>' . e(function_exists('rma_status_label') ? rma_status_label((string) $r['status']) : (string) $r['status']) . '</td><td>' . e(fmt_date((string) $r['created_at'])) . '</td></tr>';
        }
        echo '</tbody>';
    });
}

layout_bottom();
