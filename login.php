<?php
declare(strict_types=1);

/**
 * login.php
 * Kullanıcı adı veya e-posta ile güvenli giriş.
 * Arayüz: "flowpath" tam ekran hero tasarımı (video arka plan + liquid-glass).
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
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Giriş · <?= e(SITE_NAME) ?></title>

    <!-- Helvetica Now Text -->
    <link rel="stylesheet" href="https://db.onlinewebfonts.com/c/08e020de1811ec4489f82d1247a42c09?family=Helvetica+Now+Text">

    <!-- Tailwind (no build step; utility runtime) -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        * {
            font-family: "Helvetica Now Text", -apple-system, BlinkMacSystemFont,
                         'Segoe UI', Roboto, sans-serif;
        }

        html, body { margin: 0; padding: 0; }

        /* Liquid glass yüzey */
        .liquid-glass {
            background: rgba(255, 255, 255, 0.01);
            background-blend-mode: luminosity;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            border: none;
            box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }

        .liquid-glass::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            padding: 1.4px;
            background: linear-gradient(180deg,
                rgba(255,255,255,0.45) 0%, rgba(255,255,255,0.15) 20%,
                rgba(255,255,255,0) 40%, rgba(255,255,255,0) 60%,
                rgba(255,255,255,0.15) 80%, rgba(255,255,255,0.45) 100%);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
                    mask-composite: exclude;
            pointer-events: none;
        }

        @keyframes dropdown-in {
            from { opacity: 0; transform: translateY(-4px) scale(0.96); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        .animate-dropdown { animation: dropdown-in 0.2s ease-out; }
        .duration-400 { transition-duration: 400ms; }

        /* Cam yüzey üzerindeki inputların otomatik dolgu rengini bastır */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-text-fill-color: #fff;
            transition: background-color 9999s ease-in-out 0s;
        }
    </style>
</head>
<body>
    <section class="relative h-screen w-full overflow-hidden">
        <!-- Arka plan videosu -->
        <video
            class="absolute inset-0 w-full h-full object-cover"
            autoplay muted loop playsinline
            src="https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260703_053131_1ec3dd1c-d627-44fb-ab20-6e1fce41b0d5.mp4"></video>

        <!-- Karartma katmanı -->
        <div class="absolute inset-0 bg-black/10"></div>

        <!-- İçerik -->
        <div class="relative z-10 h-full flex flex-col">
            <!-- NAV -->
            <nav class="relative w-full px-5 sm:px-6 md:px-12 lg:px-16 py-4 sm:py-5 flex items-center justify-between">
                <!-- Logo -->
                <a href="<?= e(url('index.php')) ?>" class="flex items-center gap-2 shrink-0">
                    <svg width="28" height="28" viewBox="0 0 28 28" fill="none" aria-hidden="true">
                        <path d="M14 2 L26 14 L14 26 L2 14 Z" fill="#fff" opacity="0.9"/>
                        <path d="M14 8.5 L19.5 14 L14 19.5 L8.5 14 Z" fill="#fff" opacity="0.5"/>
                    </svg>
                    <span class="text-white text-lg sm:text-xl font-medium tracking-tight">flowpath</span>
                </a>

                <!-- Masaüstü menü -->
                <div class="hidden md:flex items-center gap-1">
                    <!-- Product -->
                    <div class="relative group">
                        <button type="button" class="text-white/90 hover:text-white text-sm font-medium flex items-center gap-1 px-3 py-2">
                            Product
                            <svg class="w-3.5 h-3.5 transition-transform duration-200 group-hover:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="!absolute top-full left-0 hidden group-hover:block animate-dropdown liquid-glass rounded-xl py-3 px-2 min-w-[160px] shadow-xl">
                            <a href="#" class="block text-white/80 hover:text-white text-sm rounded-lg hover:bg-white/5 px-3 py-2">Connections</a>
                            <a href="#" class="block text-white/80 hover:text-white text-sm rounded-lg hover:bg-white/5 px-3 py-2">Workflows</a>
                            <a href="#" class="block text-white/80 hover:text-white text-sm rounded-lg hover:bg-white/5 px-3 py-2">Insights</a>
                        </div>
                    </div>

                    <!-- Solutions -->
                    <div class="relative group">
                        <button type="button" class="text-white/90 hover:text-white text-sm font-medium flex items-center gap-1 px-3 py-2">
                            Solutions
                            <svg class="w-3.5 h-3.5 transition-transform duration-200 group-hover:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="!absolute top-full left-0 hidden group-hover:block animate-dropdown liquid-glass rounded-xl py-3 px-2 min-w-[160px] shadow-xl">
                            <a href="#" class="block text-white/80 hover:text-white text-sm rounded-lg hover:bg-white/5 px-3 py-2">Guides</a>
                            <a href="#" class="block text-white/80 hover:text-white text-sm rounded-lg hover:bg-white/5 px-3 py-2">Use cases</a>
                            <a href="#" class="block text-white/80 hover:text-white text-sm rounded-lg hover:bg-white/5 px-3 py-2">API reference</a>
                        </div>
                    </div>

                    <!-- About -->
                    <div class="relative group">
                        <button type="button" class="text-white/90 hover:text-white text-sm font-medium flex items-center gap-1 px-3 py-2">
                            About
                            <svg class="w-3.5 h-3.5 transition-transform duration-200 group-hover:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="!absolute top-full left-0 hidden group-hover:block animate-dropdown liquid-glass rounded-xl py-3 px-2 min-w-[160px] shadow-xl">
                            <a href="#" class="block text-white/80 hover:text-white text-sm rounded-lg hover:bg-white/5 px-3 py-2">Our story</a>
                            <a href="#" class="block text-white/80 hover:text-white text-sm rounded-lg hover:bg-white/5 px-3 py-2">Open roles</a>
                            <a href="#" class="block text-white/80 hover:text-white text-sm rounded-lg hover:bg-white/5 px-3 py-2">Reach us</a>
                        </div>
                    </div>

                    <!-- Plans -->
                    <a href="#" class="text-white/90 hover:text-white text-sm font-medium px-3 py-2">Plans</a>
                </div>

                <!-- Masaüstü CTA -->
                <div class="hidden md:flex items-center gap-4">
                    <a href="#giris" class="text-white/90 hover:text-white text-sm font-medium">Log in</a>
                    <a href="#giris" class="liquid-glass rounded-full px-5 py-2 text-white text-sm font-medium">Try it free</a>
                </div>

                <!-- Mobil menü butonu -->
                <button id="mobileToggle" type="button"
                        class="md:hidden relative w-6 h-6 text-white"
                        aria-label="Menüyü aç/kapat" aria-expanded="false">
                    <span id="iconMenu" class="absolute inset-0 flex items-center justify-center transition-all duration-300">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                    </span>
                    <span id="iconX" class="absolute inset-0 flex items-center justify-center transition-all duration-300 opacity-0 -rotate-90 scale-50">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </span>
                </button>

                <!-- Mobil menü paneli -->
                <div id="mobileMenu"
                     class="md:hidden absolute top-full left-0 right-0 mx-4 mt-2 z-20 bg-[#2C221C]/95 backdrop-blur-xl rounded-2xl p-6 transition-all duration-400 opacity-0 -translate-y-2 pointer-events-none"
                     style="transition-timing-function: cubic-bezier(0.16,1,0.3,1);">
                    <div class="space-y-4">
                        <div>
                            <span class="text-white text-sm font-medium">Product</span>
                            <div class="mt-2 ml-3 flex flex-col gap-2">
                                <a href="#" class="text-white/70 hover:text-white text-sm">Connections</a>
                                <a href="#" class="text-white/70 hover:text-white text-sm">Workflows</a>
                                <a href="#" class="text-white/70 hover:text-white text-sm">Insights</a>
                            </div>
                        </div>
                        <div>
                            <span class="text-white text-sm font-medium">Solutions</span>
                            <div class="mt-2 ml-3 flex flex-col gap-2">
                                <a href="#" class="text-white/70 hover:text-white text-sm">Guides</a>
                                <a href="#" class="text-white/70 hover:text-white text-sm">Use cases</a>
                                <a href="#" class="text-white/70 hover:text-white text-sm">API reference</a>
                            </div>
                        </div>
                        <div>
                            <span class="text-white text-sm font-medium">About</span>
                            <div class="mt-2 ml-3 flex flex-col gap-2">
                                <a href="#" class="text-white/70 hover:text-white text-sm">Our story</a>
                                <a href="#" class="text-white/70 hover:text-white text-sm">Open roles</a>
                                <a href="#" class="text-white/70 hover:text-white text-sm">Reach us</a>
                            </div>
                        </div>
                        <div>
                            <a href="#" class="text-white text-sm font-medium">Plans</a>
                        </div>
                    </div>
                    <div class="border-t border-white/10 mt-5 pt-5 flex flex-col gap-3">
                        <a href="#giris" class="text-white/90 hover:text-white text-sm font-medium">Log in</a>
                        <a href="#giris" class="liquid-glass rounded-full px-5 py-2.5 text-white text-sm font-medium text-center">Try it free</a>
                    </div>
                </div>
            </nav>

            <!-- HERO -->
            <div class="flex-1 flex items-start justify-center overflow-y-auto px-5 sm:px-6 pt-16 sm:pt-20 md:pt-24 pb-10">
                <div class="text-center max-w-3xl w-full">
                    <h1 class="text-white text-3xl sm:text-4xl md:text-5xl lg:text-6xl xl:text-7xl leading-[1.05] tracking-[-0.02em]">
                        Bridge the gaps.<br>
                        <span class="text-white/60">Ditch the grindwork.</span>
                    </h1>

                    <p class="text-white/80 text-sm sm:text-base md:text-lg leading-relaxed max-w-md mx-auto mt-6 sm:mt-8">
                        Flowpath unifies your complete wellness tools, so your crew spends less
                        energy plugging gaps and more on real progress.
                    </p>

                    <!-- PANEL GİRİŞİ -->
                    <div id="giris" class="liquid-glass rounded-2xl p-6 sm:p-7 max-w-sm mx-auto mt-8 sm:mt-10 text-left">
                        <h2 class="text-white text-lg font-medium mb-1">Panele giriş</h2>
                        <p class="text-white/60 text-sm mb-5">Hesabınıza giriş yapın</p>

                        <?php foreach ($flashes as $f): ?>
                            <?php
                            $ftype = in_array($f['type'], ['success', 'error', 'info'], true) ? $f['type'] : 'info';
                            $fcls = $ftype === 'error'
                                ? 'bg-red-500/15 border-red-400/30 text-red-100'
                                : ($ftype === 'success'
                                    ? 'bg-emerald-500/15 border-emerald-400/30 text-emerald-100'
                                    : 'bg-white/10 border-white/20 text-white/90');
                            ?>
                            <div class="border <?= $fcls ?> text-sm rounded-lg px-3 py-2.5 mb-4"><?= e($f['message']) ?></div>
                        <?php endforeach; ?>

                        <?php if ($error !== ''): ?>
                            <div class="border bg-red-500/15 border-red-400/30 text-red-100 text-sm rounded-lg px-3 py-2.5 mb-4"><?= e($error) ?></div>
                        <?php endif; ?>

                        <form method="post" action="<?= e(url('login.php')) ?>" data-lock-on-submit novalidate class="space-y-4">
                            <?= csrf_field() ?>

                            <div>
                                <label for="identifier" class="block text-white/80 text-sm font-medium mb-1.5">Kullanıcı adı veya e-posta</label>
                                <input type="text" id="identifier" name="identifier"
                                       value="<?= e($identifier) ?>" autocomplete="username" autofocus required
                                       class="w-full bg-white/10 border border-white/15 rounded-lg px-4 py-2.5 text-white text-sm placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-white/30 focus:border-white/30">
                            </div>

                            <div>
                                <label for="password" class="block text-white/80 text-sm font-medium mb-1.5">Şifre</label>
                                <div class="relative">
                                    <input type="password" id="password" name="password"
                                           autocomplete="current-password" required
                                           class="w-full bg-white/10 border border-white/15 rounded-lg px-4 py-2.5 pr-16 text-white text-sm placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-white/30 focus:border-white/30">
                                    <button type="button"
                                            data-toggle-password data-target="#password" aria-pressed="false"
                                            class="absolute top-1/2 right-2 -translate-y-1/2 text-white/70 hover:text-white text-xs font-medium px-2 py-1 rounded-md hover:bg-white/10">Göster</button>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-3 flex-wrap">
                                <label class="inline-flex items-center gap-2 text-white/80 text-sm cursor-pointer select-none">
                                    <input type="checkbox" name="remember" value="1"
                                           class="w-4 h-4 rounded border-white/30 bg-white/10 accent-white">
                                    <span>Beni hatırla</span>
                                </label>
                                <a href="<?= e(url('forgot-password.php')) ?>" class="text-white/70 hover:text-white text-sm">Şifremi unuttum</a>
                            </div>

                            <button type="submit"
                                    class="w-full px-6 py-3 bg-white text-gray-900 text-sm font-semibold rounded-full hover:bg-white/90 transition-colors">
                                Giriş Yap
                            </button>
                        </form>
                    </div>

                    <p class="text-white/50 text-xs mt-6">&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?></p>
                </div>
            </div>
        </div>
    </section>

    <script>
    (function () {
        'use strict';
        var btn = document.getElementById('mobileToggle');
        var menu = document.getElementById('mobileMenu');
        var iconMenu = document.getElementById('iconMenu');
        var iconX = document.getElementById('iconX');
        if (!btn || !menu) { return; }

        var open = false;
        function setOpen(v) {
            open = v;
            if (open) {
                menu.classList.remove('opacity-0', '-translate-y-2', 'pointer-events-none');
                menu.classList.add('opacity-100', 'translate-y-0');
                iconMenu.classList.add('opacity-0', 'rotate-90', 'scale-50');
                iconX.classList.remove('opacity-0', '-rotate-90', 'scale-50');
                btn.setAttribute('aria-expanded', 'true');
            } else {
                menu.classList.add('opacity-0', '-translate-y-2', 'pointer-events-none');
                menu.classList.remove('opacity-100', 'translate-y-0');
                iconMenu.classList.remove('opacity-0', 'rotate-90', 'scale-50');
                iconX.classList.add('opacity-0', '-rotate-90', 'scale-50');
                btn.setAttribute('aria-expanded', 'false');
            }
        }
        btn.addEventListener('click', function () { setOpen(!open); });
        // Menüdeki bir bağlantıya tıklanınca kapat
        menu.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () { setOpen(false); });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && open) { setOpen(false); }
        });
    })();
    </script>

    <!-- Panel ortak JS: şifre göster/gizle + çift gönderim kilidi -->
    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
