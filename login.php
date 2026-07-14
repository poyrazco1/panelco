<?php
declare(strict_types=1);

/**
 * login.php
 * Kullanıcı adı veya e-posta ile güvenli giriş.
 * Görsel tema: "flowpath" tam ekran hero (video arka planı + cam giriş kartı).
 */
require_once __DIR__ . '/includes/auth.php';

auth_boot();

// Zaten giriş yapılmışsa panele
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error      = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $identifier = input('identifier');
    $password   = $_POST['password'] ?? '';
    $remember   = !empty($_POST['remember']);

    if ($identifier === '' || $password === '') {
        $error = 'Kullanıcı adı/e-posta ve şifre gereklidir.';
    } elseif (auth_login($identifier, $password, $remember)) {
        redirect('dashboard.php');
    } else {
        // Ayrıntısız, güvenli mesaj (deneme login_logs'a yazıldı)
        $error = 'Kullanıcı adı/e-posta veya şifre hatalı. Hesabınız pasif de olabilir.';
    }
}

$flashes = get_flashes();

/* -------------------------------------------------------------------------
 | Marka (logo + ad): Ayarlar → Şirket Bilgileri'nden yönetilir.
 | Logo yüklendiyse görsel, firma adı girildiyse metin buradan gelir.
 | Hiçbiri ayarlı değilse tasarımın varsayılanı ("flowpath" + elmas) kullanılır.
 * ---------------------------------------------------------------------- */
$brandLogo    = function_exists('pub_logo_url') ? pub_logo_url() : null;
$brandFavicon = function_exists('pub_favicon_url') ? pub_favicon_url() : null;
$brandName    = 'flowpath';
try {
    if (function_exists('db')) {
        $st = db()->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'company_name' LIMIT 1");
        $st->execute();
        $cn = trim((string) ($st->fetchColumn() ?: ''));
        if ($cn !== '') { $brandName = $cn; }
    }
} catch (Throwable $e) {
    // Sessiz: veritabanı erişilemezse tasarımın varsayılan markası kullanılır.
}

/* Navigasyon menüsü (dekoratif). Ürün tanıtım başlıkları. */
$navItems = [
    ['label' => 'Product',   'items' => ['Connections', 'Workflows', 'Insights']],
    ['label' => 'Solutions', 'items' => ['Guides', 'Use cases', 'API reference']],
    ['label' => 'About',     'items' => ['Our story', 'Open roles', 'Reach us']],
    ['label' => 'Plans',     'items' => []],
];

/* Lucide tarzı satır içi ikonlar */
$icon_chevron = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Giriş · <?= e(SITE_NAME) ?></title>
    <?php if ($brandFavicon): ?>
    <link rel="icon" type="<?= e(favicon_mime($brandFavicon)) ?>" href="<?= e($brandFavicon) ?>">
    <?php endif; ?>

    <!-- Helvetica Now Text -->
    <link rel="stylesheet" href="https://db.onlinewebfonts.com/c/08e020de1811ec4489f82d1247a42c09?family=Helvetica+Now+Text">
    <!-- Tailwind (Play CDN — projede build adımı yok) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- flowpath özel sınıfları (liquid-glass, animasyonlar, font) -->
    <link rel="stylesheet" href="<?= e(asset('css/flowpath-auth.css')) ?>">
</head>
<body class="antialiased">
    <section class="relative h-screen w-full overflow-hidden flex flex-col">
        <!-- Arka plan videosu -->
        <video class="absolute inset-0 w-full h-full object-cover" autoplay loop muted playsinline aria-hidden="true">
            <source src="https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260703_053131_1ec3dd1c-d627-44fb-ab20-6e1fce41b0d5.mp4" type="video/mp4">
        </video>
        <!-- Karartma katmanı -->
        <div class="absolute inset-0 bg-black/10"></div>

        <!-- ===================== NAVIGASYON ===================== -->
        <nav class="relative z-50 w-full px-5 sm:px-6 md:px-12 lg:px-16 py-4 sm:py-5">
            <div class="flex items-center justify-between">
                <!-- Logo -->
                <a href="<?= e(url('login.php')) ?>" class="flex items-center gap-2 shrink-0">
                    <?php if ($brandLogo): ?>
                        <img src="<?= e($brandLogo) ?>" alt="<?= e($brandName) ?>" class="h-7 sm:h-8 w-auto object-contain">
                    <?php else: ?>
                        <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M14 2 L26 14 L14 26 L2 14 Z" fill="#ffffff" opacity="0.9"/>
                            <path d="M14 8 L20 14 L14 20 L8 14 Z" fill="#ffffff" opacity="0.5"/>
                        </svg>
                    <?php endif; ?>
                    <span class="text-white text-lg sm:text-xl font-medium tracking-tight"><?= e($brandName) ?></span>
                </a>

                <!-- Masaüstü menü -->
                <div class="hidden md:flex items-center gap-1">
                    <?php foreach ($navItems as $item): ?>
                        <?php if (!empty($item['items'])): ?>
                            <div class="relative group">
                                <button type="button" class="flex items-center gap-1 text-white/90 hover:text-white text-sm font-medium transition-colors px-3 py-2">
                                    <?= e($item['label']) ?>
                                    <span class="w-3.5 h-3.5 inline-flex transition-transform duration-200 group-hover:rotate-180"><?= $icon_chevron ?></span>
                                </button>
                                <div class="hidden group-hover:block animate-dropdown !absolute top-full left-0 liquid-glass rounded-xl py-3 px-2 min-w-[160px] shadow-xl">
                                    <?php foreach ($item['items'] as $sub): ?>
                                        <a href="#" class="block text-white/80 hover:text-white text-sm rounded-lg hover:bg-white/5 px-3 py-2 transition-colors"><?= e($sub) ?></a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <a href="#" class="text-white/90 hover:text-white text-sm font-medium transition-colors px-3 py-2"><?= e($item['label']) ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Masaüstü CTA -->
                <div class="hidden md:flex items-center gap-3 shrink-0">
                    <a href="<?= e(url('login.php')) ?>" class="text-white/90 hover:text-white text-sm font-medium transition-colors">Log in</a>
                    <a href="#" class="liquid-glass rounded-full px-5 py-2 text-white text-sm font-medium hover:bg-white/10 transition-colors">Try it free</a>
                </div>

                <!-- Mobil menü butonu -->
                <button type="button" id="mobileToggle" aria-label="Menü" aria-expanded="false" aria-controls="mobileMenu"
                        class="md:hidden relative w-10 h-10 flex items-center justify-center text-white">
                    <span id="iconMenu" class="absolute inset-0 flex items-center justify-center transition-all duration-300">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/></svg>
                    </span>
                    <span id="iconX" class="absolute inset-0 flex items-center justify-center transition-all duration-300 opacity-0 -rotate-90 scale-0">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </span>
                </button>
            </div>

            <!-- Mobil menü paneli -->
            <div id="mobileMenu" class="mobile-menu ease-flow duration-400 md:hidden absolute left-5 right-5 sm:left-6 sm:right-6 top-full mt-2 z-50">
                <div class="bg-[#2C221C]/95 backdrop-blur-xl rounded-2xl p-6">
                    <?php foreach ($navItems as $item): ?>
                        <div class="mb-1">
                            <div class="text-white text-base font-medium py-2"><?= e($item['label']) ?></div>
                            <?php if (!empty($item['items'])): ?>
                                <div class="pl-3 flex flex-col">
                                    <?php foreach ($item['items'] as $sub): ?>
                                        <a href="#" class="text-white/70 hover:text-white text-sm py-1.5 transition-colors"><?= e($sub) ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="mt-4 pt-4 border-t border-white/10 flex flex-col gap-3">
                        <a href="<?= e(url('login.php')) ?>" class="text-white/90 hover:text-white text-sm font-medium">Log in</a>
                        <a href="#" class="liquid-glass rounded-full px-5 py-2.5 text-white text-sm font-medium text-center hover:bg-white/10 transition-colors">Try it free</a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- ===================== HERO + PANEL GİRİŞİ ===================== -->
        <div class="relative z-10 flex-1 flex items-start justify-center px-5 sm:px-6 md:px-12 lg:px-16 pt-16 sm:pt-20 md:pt-24 overflow-y-auto pb-10">
            <div class="text-center max-w-3xl w-full min-w-0">
                <h1 class="text-white text-3xl sm:text-4xl md:text-5xl lg:text-6xl xl:text-7xl leading-[1.05] tracking-[-0.02em]">
                    Bridge the<br>gaps. <span class="text-white/60">Ditch the<br>grindwork.</span>
                </h1>

                <p class="text-white/80 text-sm sm:text-base md:text-lg leading-relaxed max-w-md mx-auto mt-6 sm:mt-8">
                    Flowpath unifies your complete wellness tools, so your crew spends less energy plugging gaps and more on real progress.
                </p>

                <!-- Panel girişi kartı (gerçek oturum açma formu, bu tasarımın içinde) -->
                <form class="liquid-glass rounded-2xl p-6 sm:p-7 max-w-sm w-full mx-auto mt-6 sm:mt-8 text-left"
                      method="post" action="<?= e(url('login.php')) ?>" data-lock-on-submit novalidate>
                    <?= csrf_field() ?>

                    <div class="relative z-10">
                        <h2 class="text-white text-lg font-semibold tracking-tight">Panel Girişi</h2>
                        <p class="text-white/60 text-sm mt-1 mb-5">Hesabınıza giriş yapın</p>

                        <?php foreach ($flashes as $f):
                            $tone = ($f['type'] === 'success') ? 'border-emerald-300/30 bg-emerald-400/10 text-emerald-50'
                                  : (($f['type'] === 'error') ? 'border-red-300/30 bg-red-400/10 text-red-50'
                                  : 'border-white/15 bg-white/10 text-white/90'); ?>
                            <div class="mb-3 rounded-xl border <?= $tone ?> px-3.5 py-2.5 text-sm"><?= e($f['message']) ?></div>
                        <?php endforeach; ?>

                        <?php if ($error !== ''): ?>
                            <div class="mb-3 rounded-xl border border-red-300/30 bg-red-400/10 text-red-50 px-3.5 py-2.5 text-sm"><?= e($error) ?></div>
                        <?php endif; ?>

                        <div class="mb-4">
                            <label for="identifier" class="block text-white/80 text-xs font-medium mb-1.5">Kullanıcı adı veya e-posta</label>
                            <input type="text" id="identifier" name="identifier" value="<?= e($identifier) ?>"
                                   autocomplete="username" autofocus required
                                   class="w-full bg-white/5 border border-white/15 rounded-xl px-4 py-2.5 text-white text-sm placeholder-white/40 focus:outline-none focus:border-white/40 focus:bg-white/10 transition-colors">
                        </div>

                        <div class="mb-4">
                            <label for="password" class="block text-white/80 text-xs font-medium mb-1.5">Şifre</label>
                            <div class="relative">
                                <input type="password" id="password" name="password" autocomplete="current-password" required
                                       class="w-full bg-white/5 border border-white/15 rounded-xl pl-4 pr-16 py-2.5 text-white text-sm placeholder-white/40 focus:outline-none focus:border-white/40 focus:bg-white/10 transition-colors">
                                <button type="button" data-toggle-password data-target="#password" aria-pressed="false"
                                        class="absolute top-1/2 right-1.5 -translate-y-1/2 text-white/80 hover:text-white text-xs font-medium px-2.5 py-1.5 rounded-lg hover:bg-white/10 transition-colors">Göster</button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3 flex-wrap mb-5">
                            <label class="inline-flex items-center gap-2 text-white/80 text-sm cursor-pointer select-none">
                                <input type="checkbox" name="remember" value="1" class="w-4 h-4 rounded accent-white">
                                <span>Beni hatırla</span>
                            </label>
                            <a href="<?= e(url('forgot-password.php')) ?>" class="text-white/80 hover:text-white text-sm transition-colors">Şifremi unuttum</a>
                        </div>

                        <button type="submit"
                                class="w-full px-5 sm:px-6 py-2.5 sm:py-3 bg-white text-gray-900 text-sm font-semibold rounded-full hover:bg-white/90 transition-colors">
                            Giriş Yap
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <script>
    (function () {
        var toggle   = document.getElementById('mobileToggle');
        var menu     = document.getElementById('mobileMenu');
        var iconMenu = document.getElementById('iconMenu');
        var iconX    = document.getElementById('iconX');
        if (!toggle || !menu) { return; }

        var open = false;
        function setOpen(v) {
            open = v;
            menu.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');

            iconMenu.classList.toggle('opacity-0', open);
            iconMenu.classList.toggle('rotate-90', open);
            iconMenu.classList.toggle('scale-0', open);

            iconX.classList.toggle('opacity-0', !open);
            iconX.classList.toggle('-rotate-90', !open);
            iconX.classList.toggle('scale-0', !open);
        }
        toggle.addEventListener('click', function () { setOpen(!open); });
    })();
    </script>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
