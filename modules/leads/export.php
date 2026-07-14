<?php
declare(strict_types=1);

/** modules/leads/export.php — Lead listesi CSV (Excel/UTF-8 BOM). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leads.php';

auth_boot();
require_permission('leads.export');

$f = ['status' => (string) ($_GET['status'] ?? ''), 'search' => trim((string) ($_GET['q'] ?? ''))];
$rows = get_leads($f);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="leadler-' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Firma', 'Yetkili', 'Telefon', 'WhatsApp', 'E-posta', 'Web', 'Alan adı', 'Kategori', 'Ülke', 'İl', 'İlçe',
               'Adres', 'Puan', 'Yorum', 'Kaynak', 'Aranan kelime', 'Durum', 'Son iletişim', 'Sonraki aksiyon', 'Place ID'], ';');
foreach ($rows as $r) {
    fputcsv($out, [
        (string) $r['company_name'], (string) ($r['contact_name'] ?? ''), (string) ($r['phone'] ?? ''),
        (string) ($r['whatsapp'] ?? ''), (string) ($r['email'] ?? ''), (string) ($r['website'] ?? ''),
        (string) ($r['domain'] ?? ''), (string) ($r['main_category'] ?? $r['sector'] ?? ''),
        (string) ($r['country'] ?? ''), (string) ($r['city'] ?? ''), (string) ($r['district'] ?? ''),
        (string) ($r['address'] ?? ''), (string) ($r['google_rating'] ?? ''), (string) ($r['review_count'] ?? ''),
        (string) ($r['source'] ?? ''), (string) ($r['search_keyword'] ?? ''), lead_status_label((string) $r['status']),
        (string) ($r['last_contact_at'] ?? ''), (string) ($r['next_action_at'] ?? ''), (string) ($r['place_id'] ?? ''),
    ], ';');
}
fclose($out);
exit;
