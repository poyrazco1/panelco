<?php
declare(strict_types=1);

/**
 * modules/rma/sample-csv.php — İçe aktarma için örnek CSV şablonu (başlık + 1 örnek satır).
 * Excel uyumlu (UTF-8 BOM, ';' ayraç). Kullanıcı bunu doldurup import.php ile yükler.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/rma.php';

auth_boot();
require_permission('rma');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="iade-degisim-ornek.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$headers = rma_csv_headers();

// Başlıklara karşılık gelen örnek satır (kullanıcıya biçim göstermek için).
$sample = [];
foreach ($headers as $h) {
    $sample[] = match (true) {
        stripos($h, 'FİRMA') !== false || stripos($h, 'AD-SOYAD') !== false => 'Örnek Müşteri Ltd.',
        stripos($h, 'TELEFON') !== false => '05001234567',
        stripos($h, 'İŞLEM TARİHİ') !== false, stripos($h, 'FATURA TARİHİ') !== false => date('d.m.Y'),
        stripos($h, 'PLATFORM') !== false => 'Trendyol',
        stripos($h, 'ÜRÜN') !== false || stripos($h, 'MODEL') !== false => 'Örnek Ürün Modeli',
        stripos($h, 'ADET') !== false => '1',
        stripos($h, 'İADE') !== false || stripos($h, 'DEĞİŞİM') !== false || stripos($h, 'TÜR') !== false => 'İade',
        stripos($h, 'ZARAR') !== false => '0',
        default => '',
    };
}

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, $headers, ';');
fputcsv($out, $sample, ';');
fclose($out);
exit;
