<?php
declare(strict_types=1);

/**
 * login.php
 * Kullanıcı adı veya e-posta ile güvenli giriş.
 * Görünüm: "flowpath" video hero + liquid-glass tasarım (React yok; sade HTML/CSS/JS).
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

// Giriş modalı; hata veya flash mesaj varsa sayfa açılır açılmaz görünür olsun.
$modalOpen = ($error !== '' || !empty($flashes));

// Üst gezinme öğeleri (yalnızca görsel tasarım gereği).
$nav = [
    ['label' => 'Product',   'items' => ['Connections', 'Workflows', 'Insights']],
    ['label' => 'Solutions', 'items' => ['Guides', 'Use cases', 'API reference']],
    ['label' => 'About',     'items' => ['Our story', 'Open roles', 'Reach us']],
    ['label' => 'Plans',     'items' => []],
];
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Giriş · <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/flowpath-login.css')) ?>">
</head>
<body class="bg-[#2C221C] text-white antialiased">

<section class="relative h-screen w-full overflow-hidden bg-[#2C221C]">

    <!-- Arka plan video -->
    <video class="absolute inset-0 w-full h-full object-cover"
           autoplay loop muted playsinline preload="auto"
           aria-hidden="true">
        <source src="https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260703_053131_1ec3dd1c-d627-44fb-ab20-6e1fce41b0d5.mp4" type="video/mp4">
    </video>

    <!-- Hafif koyu katman -->
    <div class="absolute inset-0 bg-black/10"></div>

    <!-- İçerik -->
    <div class="relative z-10 h-full flex flex-col">

        <!-- ============================ NAV ============================ -->
        <nav class="relative w-full px-5 sm:px-6 md:px-12 lg:px-16 py-4 sm:py-5">
            <div class="flex items-center justify-between">

                <!-- Logo -->
                <div class="flex items-center gap-2 select-none">
                    <svg width="28" height="28" viewBox="0 0 28 28" fill="none" aria-hidden="true">
                        <path d="M14 2 L26 14 L14 26 L2 14 Z" fill="#ffffff" fill-opacity="0.9"/>
                        <path d="M14 8 L20 14 L14 20 L8 14 Z" fill="#ffffff" fill-opacity="0.5"/>
                    </svg>
                    <span class="text-white text-lg sm:text-xl font-medium tracking-tight">flowpath</span>
                </div>

                <!-- Masaüstü orta menü -->
                <div class="hidden md:flex items-center gap-6">
                    <?php foreach ($nav as $item): ?>
                        <div class="relative group">
                            <button type="button"
                                    class="flex items-center gap-1 text-white/90 hover:text-white text-sm font-medium transition-colors py-1">
                                <?= e($item['label']) ?>
                                <?php if ($item['items']): ?>
                                    <svg class="w-3.5 h-3.5 transition-transform duration-200 group-hover:rotate-180"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6"/>
                                    </svg>
                                <?php endif; ?>
                            </button>
                            <?php if ($item['items']): ?>
                                <div class="!absolute top-full left-0 hidden group-hover:block animate-dropdown liquid-glass rounded-xl py-3 px-2 min-w-[160px] shadow-xl">
                                    <?php foreach ($item['items'] as $sub): ?>
                                        <a href="#" class="block text-white/80 hover:text-white text-sm rounded-lg hover:bg-white/5 px-3 py-2 transition-colors"><?= e($sub) ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Masaüstü sağ aksiyonlar -->
                <div class="hidden md:flex items-center gap-4">
                    <a href="#loginModal" data-login-open
                       class="text-white/90 hover:text-white text-sm font-medium transition-colors">Log in</a>
                    <a href="#loginModal" data-login-open
                       class="liquid-glass rounded-full px-5 py-2 text-white text-sm font-medium transition-colors hover:bg-white/10">Try it free</a>
                </div>

                <!-- Mobil menü düğmesi (Menu <-> X) -->
                <button id="mobileToggle" type="button" aria-label="Menü" aria-expanded="false"
                        class="md:hidden fp-burger relative w-10 h-10 flex items-center justify-center text-white">
                    <svg class="icon-menu absolute w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 12h16"/><path d="M4 6h16"/><path d="M4 18h16"/>
                    </svg>
                    <svg class="icon-x absolute w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Mobil menü paneli -->
            <div id="mobileMenu"
                 class="fp-mobile-menu md:hidden absolute left-4 right-4 top-full mt-2 z-40 bg-[#2C221C]/95 backdrop-blur-xl rounded-2xl p-6">
                <div class="space-y-4">
                    <?php foreach ($nav as $item): ?>
                        <div>
                            <div class="text-white text-sm font-medium"><?= e($item['label']) ?></div>
                            <?php if ($item['items']): ?>
                                <div class="mt-2 ml-3 flex flex-col gap-1.5">
                                    <?php foreach ($item['items'] as $sub): ?>
                                        <a href="#" class="text-white/60 hover:text-white text-sm transition-colors"><?= e($sub) ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-5 pt-5 border-t border-white/10 flex flex-col gap-3">
                    <a href="#loginModal" data-login-open
                       class="text-white/90 hover:text-white text-sm font-medium transition-colors">Log in</a>
                    <a href="#loginModal" data-login-open
                       class="liquid-glass rounded-full px-5 py-2.5 text-white text-sm font-medium text-center transition-colors hover:bg-white/10">Try it free</a>
                </div>
            </div>
        </nav>

        <!-- ============================ HERO ============================ -->
        <div class="flex-1 flex items-start justify-center px-5 sm:px-6 overflow-y-auto">
            <div class="text-center max-w-3xl pt-16 sm:pt-20 md:pt-24 pb-20">
                <h1 class="text-white text-3xl sm:text-4xl md:text-5xl lg:text-6xl xl:text-7xl leading-[1.05] tracking-[-0.02em]">
                    Bridge the gaps.<br>
                    <span class="text-white/60">Ditch the grindwork.</span>
                </h1>

                <p class="text-white/80 text-sm sm:text-base md:text-lg leading-relaxed max-w-md mx-auto mt-6 sm:mt-8">
                    Flowpath unifies your complete wellness tools, so your crew spends less energy plugging gaps and more on real progress.
                </p>

                <div class="flex flex-wrap items-center justify-center gap-3 sm:gap-4 mt-6 sm:mt-8">
                    <a href="#loginModal" data-login-open
                       class="px-5 sm:px-6 py-2.5 sm:py-3 bg-white text-gray-900 text-sm font-semibold rounded-full hover:bg-white/90 transition-colors">Begin your journey</a>
                    <a href="#loginModal" data-login-open
                       class="px-5 sm:px-6 py-2.5 sm:py-3 liquid-glass rounded-full text-white text-sm font-semibold hover:bg-white/10 transition-colors">See it live</a>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================= GİRİŞ MODALI ======================= -->
    <div id="loginModal"
         class="fp-modal fixed inset-0 z-50 items-center justify-center p-4<?= $modalOpen ? ' is-open' : '' ?>"
         role="dialog" aria-modal="true" aria-labelledby="loginTitle">

        <!-- Arka örtü (JS'siz de kapatabilmek için <a>) -->
        <a href="#" data-login-close aria-label="Kapat"
           class="absolute inset-0 bg-black/50 backdrop-blur-sm"></a>

        <!-- Kart -->
        <div class="relative liquid-glass rounded-2xl w-full max-w-sm p-6 sm:p-7">

            <a href="#" data-login-close aria-label="Kapat"
               class="absolute top-3 right-3 p-1.5 text-white/60 hover:text-white transition-colors">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                </svg>
            </a>

            <div class="flex items-center gap-2 mb-4">
                <svg width="24" height="24" viewBox="0 0 28 28" fill="none" aria-hidden="true">
                    <path d="M14 2 L26 14 L14 26 L2 14 Z" fill="#ffffff" fill-opacity="0.9"/>
                    <path d="M14 8 L20 14 L14 20 L8 14 Z" fill="#ffffff" fill-opacity="0.5"/>
                </svg>
                <span class="text-white text-lg font-medium tracking-tight">flowpath</span>
            </div>

            <h2 id="loginTitle" class="text-white text-xl font-medium tracking-tight">Panele giriş</h2>
            <p class="text-white/60 text-sm mt-1 mb-5">Devam etmek için hesabınıza giriş yapın.</p>

            <?php foreach ($flashes as $f): ?>
                <?php
                    $type = $f['type'] ?? 'info';
                    $cls  = $type === 'success'
                        ? 'bg-emerald-500/15 border-emerald-400/30 text-emerald-100'
                        : ($type === 'error'
                            ? 'bg-red-500/15 border-red-400/30 text-red-100'
                            : 'bg-white/10 border-white/20 text-white/90');
                ?>
                <div class="mb-3 rounded-lg border px-3 py-2 text-sm <?= $cls ?>"><?= e($f['message']) ?></div>
            <?php endforeach; ?>

            <?php if ($error !== ''): ?>
                <div class="mb-3 rounded-lg border border-red-400/30 bg-red-500/15 px-3 py-2 text-sm text-red-100">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('login.php')) ?>" class="space-y-4" novalidate>
                <?= csrf_field() ?>

                <div>
                    <label for="identifier" class="block text-white/70 text-xs font-medium mb-1.5">Kullanıcı adı veya e-posta</label>
                    <input type="text" id="identifier" name="identifier"
                           value="<?= e($identifier) ?>" autocomplete="username" required
                           class="fp-input w-full rounded-lg bg-white/5 border border-white/15 px-4 py-2.5 text-white placeholder-white/40 text-sm focus:outline-none focus:ring-2 focus:ring-white/30 focus:border-transparent transition">
                </div>

                <div>
                    <label for="password" class="block text-white/70 text-xs font-medium mb-1.5">Şifre</label>
                    <div class="relative">
                        <input type="password" id="password" name="password"
                               autocomplete="current-password" required
                               class="fp-input w-full rounded-lg bg-white/5 border border-white/15 px-4 py-2.5 pr-11 text-white placeholder-white/40 text-sm focus:outline-none focus:ring-2 focus:ring-white/30 focus:border-transparent transition">
                        <button type="button" data-toggle-pw aria-label="Şifreyi göster"
                                class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 text-white/50 hover:text-white transition-colors">
                            <svg class="eye w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg class="eye-off w-4 h-4 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/>
                                <path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/>
                                <path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/>
                                <path d="m2 2 20 20"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between text-sm">
                    <label class="inline-flex items-center gap-2 text-white/70 cursor-pointer select-none">
                        <input type="checkbox" name="remember" value="1" class="w-4 h-4 rounded accent-white">
                        <span>Beni hatırla</span>
                    </label>
                    <a href="<?= e(url('forgot-password.php')) ?>" class="text-white/70 hover:text-white transition-colors">Şifremi unuttum</a>
                </div>

                <button type="submit" data-lock
                        class="w-full px-6 py-3 bg-white text-gray-900 text-sm font-semibold rounded-full hover:bg-white/90 transition-colors">
                    Giriş Yap
                </button>
            </form>
        </div>
    </div>
</section>

<script>
(function () {
    // --- Mobil menü ---
    var burger = document.getElementById('mobileToggle');
    var menu   = document.getElementById('mobileMenu');
    if (burger && menu) {
        burger.addEventListener('click', function () {
            var open = menu.classList.toggle('is-open');
            burger.classList.toggle('is-open', open);
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        menu.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                menu.classList.remove('is-open');
                burger.classList.remove('is-open');
                burger.setAttribute('aria-expanded', 'false');
            });
        });
    }

    // --- Şifre göster/gizle ---
    document.querySelectorAll('[data-toggle-pw]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wrap  = btn.closest('.relative');
            var input = wrap ? wrap.querySelector('input') : null;
            if (!input) return;
            var show = input.type === 'password';      // true => görünür yap
            input.type = show ? 'text' : 'password';
            var eye = btn.querySelector('.eye');
            var off = btn.querySelector('.eye-off');
            if (eye) eye.classList.toggle('hidden', show);
            if (off) off.classList.toggle('hidden', !show);
            btn.setAttribute('aria-label', show ? 'Şifreyi gizle' : 'Şifreyi göster');
        });
    });

    // --- Giriş modalı ---
    var modal = document.getElementById('loginModal');
    function focusId() {
        if (!modal) return;
        var f = modal.querySelector('#identifier');
        if (f) setTimeout(function () { f.focus(); }, 60);
    }
    function openModal(e) { if (e) e.preventDefault(); if (modal) { modal.classList.add('is-open'); focusId(); } }
    function closeModal(e) {
        if (e) e.preventDefault();
        if (!modal) return;
        modal.classList.remove('is-open');
        if (location.hash === '#loginModal') {
            history.replaceState(null, '', location.pathname + location.search);
        }
    }
    document.querySelectorAll('[data-login-open]').forEach(function (el) { el.addEventListener('click', openModal); });
    document.querySelectorAll('[data-login-close]').forEach(function (el) { el.addEventListener('click', closeModal); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

    // Sunucu tarafında açık geldiyse (hata/flash) odağı forma taşı
    if (modal && modal.classList.contains('is-open')) focusId();

    // --- Çift göndermeyi önle ---
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            var b = form.querySelector('[data-lock]');
            if (b) setTimeout(function () { b.setAttribute('disabled', 'disabled'); b.classList.add('opacity-70'); }, 0);
        });
    });
})();
</script>
</body>
</html>
