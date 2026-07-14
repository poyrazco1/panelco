<?php
declare(strict_types=1);

/**
 * classes/DocumentService.php
 * Belge (mutabakat, teklif vb.) çıktı yardımcıları: belge gövdesini yakalama
 * ve güvenli depolamaya PDF üretme. Yazdırma/PDF/e-posta AYNI gövdeyi kullanır.
 */

require_once __DIR__ . '/PdfService.php';

final class DocumentService
{
    /** document_* fonksiyonlarının echo çıktısını yakalayıp string döndürür. */
    public static function renderInner(callable $fn): string
    {
        ob_start();
        try {
            $fn();
        } finally {
            $html = ob_get_clean();
        }
        return (string) $html;
    }

    /**
     * Belge gövdesini PDF'e çevirip güvenli klasöre (uploads/documents) rastgele
     * adla kaydeder.
     * @param array $meta ['type','number','party','orientation','title']
     * @return array{abs:string, rel:string, friendly:string}
     */
    public static function pdfToStorage(string $inner, array $meta): array
    {
        $dir = PdfService::storageDir();
        if (!is_dir($dir)) { @mkdir($dir, 0770, true); }
        $stored = PdfService::randomStoredName();
        $abs = rtrim($dir, '/') . '/' . $stored;

        PdfService::save($inner, $abs, [
            'orientation' => (string) ($meta['orientation'] ?? 'portrait'),
            'title'       => (string) ($meta['title'] ?? 'Belge'),
        ]);

        return [
            'abs'      => $abs,
            'rel'      => 'uploads/documents/' . $stored,
            'friendly' => PdfService::friendlyName(
                (string) ($meta['type'] ?? 'belge'),
                (string) ($meta['number'] ?? ''),
                (string) ($meta['party'] ?? '')
            ),
        ];
    }

    /** Depolanan PDF'i tarayıcıya (indirme veya satır içi) akıtır. */
    public static function streamPdf(string $pdfBytes, string $downloadName, bool $inline = false): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $downloadName) . '"');
        header('Content-Length: ' . strlen($pdfBytes));
        header('X-Content-Type-Options: nosniff');
        echo $pdfBytes;
    }
}
