<?php
declare(strict_types=1);

/**
 * classes/PdfService.php
 * Kurumsal belgeleri (mutabakat, teklif, sipariş vb.) sunucu tarafında PDF'e
 * dönüştürür. Dompdf (vendor/) kullanır — harici servis/Node gerektirmez.
 *
 * Belge gövdesi (.doc-sheet içeriği) forms.php'deki document_* fonksiyonlarıyla
 * üretilir; PdfService bunu Dompdf-uyumlu (flexbox yerine tablo tabanlı) CSS ile
 * sarıp A4 PDF üretir. Türkçe karakterler için DejaVu Sans gömülüdür.
 *
 * Güvenlik: uzak (http) kaynak yükleme KAPALIDIR; yalnızca yerel görseller
 * data-URI olarak gömülür. Üretilen dosyalar tahmin edilemez adla ve web'den
 * doğrudan erişilemeyen bir klasöre kaydedilir (bkz. document_storage_dir()).
 */

final class PdfService
{
    /** Belge gövdesini (inner HTML) A4 PDF baytlarına çevirir. */
    public static function render(string $innerHtml, array $opts = []): string
    {
        require_once __DIR__ . '/../vendor/autoload.php';

        $orientation = ($opts['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
        $title       = (string) ($opts['title'] ?? 'Belge');

        $html = self::wrapHtml($innerHtml, $title);
        $html = self::embedLocalImages($html);

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);       // http kaynak yükleme kapalı
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');    // Türkçe destekli gömülü font
        $options->set('defaultPaperSize', 'A4');
        $options->set('defaultPaperOrientation', $orientation);
        if (defined('APP_ROOT')) {
            $options->set('chroot', APP_ROOT);          // dosya erişimini kök dizinle sınırla
        }

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        // Otomatik sayfa numarası (§4): her sayfanın altına "Sayfa X / Y".
        try {
            $canvas = $dompdf->getCanvas();
            $font   = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
            if ($font !== null) {
                $canvas->page_text(
                    $canvas->get_width() - 105, $canvas->get_height() - 26,
                    'Sayfa {PAGE_NUM} / {PAGE_COUNT}', $font, 8, [0.45, 0.45, 0.45]
                );
            }
        } catch (Throwable $e) {
            // sayfa numarası eklenemese de PDF üretimi sürer
        }

        return (string) $dompdf->output();
    }

    /** PDF'i güvenli bir dosyaya yazar; mutlak dosya yolunu döndürür. */
    public static function save(string $innerHtml, string $absPath, array $opts = []): string
    {
        $dir = dirname($absPath);
        if (!is_dir($dir)) { @mkdir($dir, 0770, true); }
        $bytes = self::render($innerHtml, $opts);
        if (file_put_contents($absPath, $bytes) === false) {
            throw new RuntimeException('PDF dosyası yazılamadı.');
        }
        return $absPath;
    }

    /**
     * Belge PDF'leri için depolama klasörü (web'den doğrudan erişilemez).
     * uploads/documents altında .htaccess ile korunur (bkz. install.sql/migration).
     */
    public static function storageDir(): string
    {
        $root = defined('APP_ROOT') ? APP_ROOT : (__DIR__ . '/..');
        return $root . '/uploads/documents';
    }

    /**
     * Belge türü + no + firma kısa adından tahmin EDİLEBİLİR görünen ad üretir
     * (indirilen dosyanın adı için). Sunucudaki gerçek ad rastgele olmalıdır.
     */
    public static function friendlyName(string $type, string $number, string $party = ''): string
    {
        $slug = static function (string $s): string {
            $s = mb_strtolower(trim($s), 'UTF-8');
            $tr = ['ç'=>'c','ğ'=>'g','ı'=>'i','ö'=>'o','ş'=>'s','ü'=>'u','İ'=>'i'];
            $s = strtr($s, $tr);
            $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
            return trim($s, '-');
        };
        $parts = array_filter([$slug($type), $slug($number), $slug($party)]);
        $name  = implode('-', $parts);
        return ($name !== '' ? $name : 'belge') . '.pdf';
    }

    /** Rastgele, tahmin edilemez sunucu dosya adı. */
    public static function randomStoredName(): string
    {
        return bin2hex(random_bytes(20)) . '.pdf';
    }

    /* --------------------------------------------------------------------- */

    /** Belge gövdesini Dompdf-uyumlu tam HTML sayfaya sarar. */
    private static function wrapHtml(string $innerHtml, string $title): string
    {
        $css = self::pdfCss();
        $t   = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>' . $t . '</title>'
             . '<style>' . $css . '</style></head><body><div class="doc-sheet">' . $innerHtml . '</div></body></html>';
    }

    /**
     * Dompdf-uyumlu belge stili. Flexbox YOKTUR; iki sütunlu düzenler tablo
     * (display:table/table-cell) ile kurulur. app.css/print.css'teki görünümü
     * baskı için birebir taklit eder.
     */
    public static function pdfCss(): string
    {
        return <<<CSS
* { box-sizing: border-box; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #1a1a1a; margin: 0; }
.doc-sheet { padding: 0; }

.doc-header { display: table; width: 100%; border-bottom: 2px solid #1F2A44; padding-bottom: 12px; margin-bottom: 14px; }
.doc-brand { display: table-cell; vertical-align: top; width: 62%; }
.doc-meta  { display: table-cell; vertical-align: top; text-align: right; font-size: 10.5px; color: #333; }
.doc-logo  { max-height: 60px; max-width: 190px; }
.doc-company-name { font-size: 15px; font-weight: bold; color: #1F2A44; margin: 0 0 3px; }
.doc-company-legal { font-size: 10.5px; color: #555; margin: 0 0 3px; }
.doc-company-meta { font-size: 10.5px; color: #555; line-height: 1.45; }
.doc-title { font-size: 16px; font-weight: bold; color: #1F2A44; margin: 0 0 6px; text-transform: uppercase; letter-spacing: .03em; }
.doc-meta-row { margin-bottom: 2px; }
.doc-meta-row span:first-child { color: #777; }
.doc-meta-row span + span { font-weight: bold; margin-left: 4px; }

.doc-party { font-size: 12px; color: #222; padding: 8px 0; border-bottom: 1px solid #eee; }

table.table { width: 100%; border-collapse: collapse; margin-top: 10px; }
table.table th, table.table td { border: 1px solid #e2e2e2; padding: 7px 9px; text-align: left; font-size: 11px; vertical-align: top; }
table.table th { background: #f5f6f8; color: #333; font-weight: bold; }

.doc-sign-row { display: table; width: 100%; margin-top: 34px; }
.doc-sign-box { display: table-cell; width: 50%; text-align: center; vertical-align: bottom; padding: 0 20px; }
.doc-sign-media { height: 74px; text-align: center; }
.doc-sign-media img { max-height: 72px; max-width: 190px; }
.doc-sign-line { border-top: 1px solid #333; margin-top: 6px; padding-top: 5px; font-size: 11px; color: #444; }
.doc-sign-name { font-size: 11px; font-weight: bold; color: #222; margin-top: 3px; }

.doc-footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 9.5px; color: #666; }
.doc-footer-line { display: table; width: 100%; }
.doc-footer-line span { display: table-cell; }
.doc-footer-line span:last-child { text-align: right; }
.doc-footer-note { margin-top: 3px; font-size: 9px; color: #999; }
CSS;
    }

    /**
     * HTML içindeki yerel görselleri (uploads/... vb.) data-URI olarak gömer.
     * Böylece Dompdf'te uzak yükleme kapalıyken de logo/kaşe/imza görünür.
     */
    public static function embedLocalImages(string $html): string
    {
        $root = defined('APP_ROOT') ? APP_ROOT : (__DIR__ . '/..');
        return (string) preg_replace_callback(
            '/<img\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>/i',
            static function (array $m) use ($root): string {
                $src = $m[1];
                if (stripos($src, 'data:') === 0) { return $m[0]; }
                // URL yolunu yerel dosya yoluna indir
                $path = parse_url($src, PHP_URL_PATH);
                if (!is_string($path) || $path === '') { return $m[0]; }
                $file = $root . '/' . ltrim($path, '/');
                if (!is_file($file)) { return $m[0]; }
                $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
                $mime = match ($ext) {
                    'png' => 'image/png', 'jpg', 'jpeg' => 'image/jpeg',
                    'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
                    default => 'application/octet-stream',
                };
                $data = @file_get_contents($file);
                if ($data === false) { return $m[0]; }
                $uri = 'data:' . $mime . ';base64,' . base64_encode($data);
                return (string) str_replace($src, $uri, $m[0]);
            },
            $html
        );
    }
}
