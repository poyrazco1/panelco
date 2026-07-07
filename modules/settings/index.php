<?php
declare(strict_types=1);

/**
 * modules/settings/index.php
 * Genel Ayarlar — kartlı/gruplu ayar merkezi + arama.
 * Sol menüdeki ayar sayfaları buraya taşındı. Yeni ayarlar buraya kart olarak eklenir.
 */
require_once __DIR__ . '/../../includes/permissions.php';

auth_boot();
require_permission('settings');


// Kart grupları. Kart: title, desc, icon, [url], [perm], [soon], [kw]
$groups = [
    'Kullanıcı & Yetki' => [
        ['title' => 'Kullanıcılar', 'icon' => 'users', 'desc' => 'Panel kullanıcılarını ekleyin, düzenleyin, rol atayın ve aktif/pasif yapın.', 'url' => url('modules/settings/users.php'), 'perm' => 'settings', 'kw' => 'kullanici user rol atama sifre aktif pasif'],
        ['title' => 'Roller', 'icon' => 'shield', 'desc' => 'Rolleri ve modül bazlı yetkileri yönetin (Yönetici, Muhasebe, Satış, Depo, Personel).', 'url' => url('modules/settings/roles.php'), 'perm' => 'settings', 'kw' => 'rol yetki izin permission'],
    ],
    'İK / Personel' => [
        ['title' => 'İzin Türleri', 'icon' => 'list-checks', 'perm' => 'leave', 'desc' => 'Yıllık, ücretsiz, mazeret, rapor vb. izin türlerini ve kurallarını yönetin.', 'url' => url('modules/settings/leave-types.php'), 'kw' => 'izin türü leave type yillik ucretsiz mazeret'],
        ['title' => 'Çalışma Takvimi', 'icon' => 'calendar', 'perm' => 'leave', 'desc' => 'Hafta sonu günleri, resmi tatil düşümü ve varsayılan yıllık izin gün sayısı.', 'url' => url('modules/settings/work-calendar.php'), 'kw' => 'calisma takvimi hafta sonu izin gun'],
        ['title' => 'Resmi Tatiller', 'icon' => 'calendar-days', 'perm' => 'leave', 'desc' => 'Resmi ve şirket tatillerini tanımlayın; izin gün hesabından düşülür.', 'url' => url('modules/settings/holidays.php'), 'kw' => 'resmi tatil holiday bayram'],
        ['title' => 'Vardiya / Mesai Ayarları', 'icon' => 'clock', 'perm' => 'attendance', 'desc' => 'Çalışma programları: mesai başlangıç/bitiş, mola ve haftalık çalışma günleri.', 'url' => url('modules/settings/shifts.php'), 'kw' => 'vardiya mesai calisma programi shift schedule'],
        ['title' => 'Puantaj Kuralları', 'icon' => 'sliders-horizontal', 'perm' => 'attendance', 'desc' => 'Puantaj durumları: renk, ücretli/çalışma günü/eksik gün sayımı ve sıralama.', 'url' => url('modules/settings/attendance-statuses.php'), 'kw' => 'puantaj durum kural attendance status'],
        ['title' => 'İK Bildirim Ayarları', 'icon' => 'bell', 'perm' => 'settings', 'desc' => 'Doğum günü, yıl dönümü ve izin bildirimlerini; gün aralıklarını ve mail/WhatsApp modunu yönetin.', 'url' => url('modules/settings/notification-settings.php'), 'kw' => 'bildirim hatirlatma dogum gunu yil donumu notification'],
        ['title' => 'Kutlama Şablonları', 'icon' => 'gift', 'perm' => 'settings', 'desc' => 'Doğum günü ve çalışma yıl dönümü için WhatsApp ve e-posta mesaj şablonları.', 'url' => url('modules/settings/notification-templates.php'), 'kw' => 'sablon template whatsapp mail kutlama'],
        ['title' => 'SMTP / E-posta Testi', 'icon' => 'mail', 'perm' => 'settings', 'desc' => 'SMTP yapılandırmasını görüntüleyin ve gerçek bir test maili göndererek bağlantıyı doğrulayın.', 'url' => url('modules/settings/smtp-test.php'), 'kw' => 'smtp mail eposta test gonderim phpmailer sunucu'],
    ],
    'Genel / Şirket Ayarları' => [
        ['title' => 'Kişisel Görünüm Ayarları', 'icon' => 'palette', 'desc' => 'Tema (açık/koyu/sistem), kenar çubuğu ve panel görünüm tercihlerinizi düzenleyin. Kişiseldir.', 'url' => url('modules/settings/appearance.php'), 'kw' => 'tema dark light system koyu acik sidebar kenar cubugu gorunum kisisel'],
        ['title' => 'Şirket Bilgileri', 'icon' => 'building-2', 'desc' => 'Firma adı, ticari unvan, adres ve vergi bilgileri.', 'url' => url('modules/settings/company.php'), 'perm' => 'settings', 'kw' => 'firma sirket unvan adres vergi'],
        ['title' => 'İletişim Ayarları', 'icon' => 'phone', 'desc' => 'Telefon, WhatsApp, e-posta ve web sitesi bilgileri.', 'url' => url('modules/settings/company.php'), 'perm' => 'settings', 'kw' => 'iletisim telefon whatsapp eposta email web'],
        ['title' => 'Logo & Favicon', 'icon' => 'image', 'desc' => 'Panel logosu, login logosu, favicon ve PDF/form logosu.', 'soon' => true, 'kw' => 'logo favicon pdf form gorsel'],
        ['title' => 'Genel Panel Ayarları', 'icon' => 'sliders-horizontal', 'desc' => 'Panel başlığı, varsayılan dil ve genel görünüm ayarları.', 'soon' => true, 'kw' => 'panel dil gorunum tema baslik'],
        ['title' => 'Ön Bellek Temizle', 'icon' => 'refresh-cw', 'perm' => 'settings', 'desc' => 'Geçici/önbellek dosyalarını güvenle temizleyin. Oturum ve veriler etkilenmez.', 'url' => url('modules/settings/cache.php'), 'kw' => 'onbellek cache temizle tmp gecici'],
    ],
    'Operasyon Ayarları' => [
        ['title' => 'Kargo Yöntemleri', 'icon' => 'truck', 'desc' => 'Kargo firmaları, logolar, desi ücretleri ve ücretsiz kargo limiti.', 'url' => url('modules/settings/shipping.php'), 'perm' => 'shipping', 'kw' => 'kargo desi ucret gonderi'],
        ['title' => 'Markalar', 'icon' => 'tag', 'desc' => 'Marka adı, logo, aktif/pasif durum ve sıra no.', 'url' => url('modules/settings/brands.php'), 'perm' => 'brands', 'kw' => 'marka brand logo'],
        ['title' => 'Personeller', 'icon' => 'users', 'desc' => 'Personel bilgileri, fotoğraf, departman, pozisyon ve panel kullanıcısı bağlantısı.', 'url' => url('modules/settings/personnel.php'), 'perm' => 'personnel', 'kw' => 'personel departman pozisyon calisan'],
    ],
    'Servis Ayarları' => [
        ['title' => 'Dış Tamirciler', 'icon' => 'wrench', 'desc' => 'Dışarıda çalışılan tamircilerin kaydı ve iletişimi.', 'url' => url('modules/service/repairers.php'), 'perm' => 'service', 'kw' => 'servis tamirci dis usta'],
        ['title' => 'Servis Durumları', 'icon' => 'list-checks', 'desc' => 'Servis kabul sürecindeki durum tanımları.', 'url' => url('modules/service/statuses.php'), 'perm' => 'service', 'kw' => 'servis durum status'],
        ['title' => 'Servis Koşulları', 'icon' => 'file-text', 'desc' => 'Servis teslim formundaki koşul metinleri.', 'url' => url('modules/service/terms.php'), 'perm' => 'service', 'kw' => 'servis kosul sozlesme metin'],
        ['title' => 'Servis Mesaj Şablonları', 'icon' => 'message-circle', 'perm' => 'settings', 'desc' => 'Servis durum bilgilendirme için WhatsApp/e-posta şablonları ve SMS altyapı ayarları.', 'url' => url('modules/settings/service-messages.php'), 'kw' => 'servis mesaj sablon durum bilgilendirme whatsapp sms eposta'],
        ['title' => 'Sevkiyat Ayarları', 'icon' => 'truck', 'perm' => 'settings', 'desc' => 'Sevkiyat yönetici WhatsApp numarası ve durum bilgilendirme mesaj şablonu.', 'url' => url('modules/settings/shipment-settings.php'), 'kw' => 'sevkiyat yonetici whatsapp mesaj sablon teslimat toplama'],
        ['title' => 'Servis Formu Ayarları', 'icon' => 'file-pen', 'desc' => 'Servis formu başlığı, logo gösterimi ve imza/onay ayarları.', 'soon' => true, 'kw' => 'servis form imza onay'],
    ],
    'İade-Değişim Ayarları' => [
        ['title' => 'Platformlar', 'icon' => 'store', 'desc' => 'T-Soft, Trendyol, Hepsiburada, N11, Amazon, WhatsApp, Mağaza vb.', 'soon' => true, 'kw' => 'platform pazaryeri trendyol hepsiburada n11 amazon'],
        ['title' => 'İade-Değişim Durumları', 'icon' => 'workflow', 'desc' => 'Talep alındı, ürün bekleniyor, değişim tamamlandı vb.', 'soon' => true, 'kw' => 'iade degisim durum status'],
        ['title' => 'İade-Değişim Sebepleri', 'icon' => 'help-circle', 'desc' => 'Yanlış ürün gönderimi, hasarlı ürün, cayma hakkı, tedarikçi hatası vb.', 'soon' => true, 'kw' => 'iade degisim sebep neden'],
        ['title' => 'Tedarikçiler', 'icon' => 'warehouse', 'desc' => 'Ürün tedarikçilerinin yönetimi.', 'soon' => true, 'kw' => 'tedarikci supplier'],
    ],
    'Entegrasyon Ayarları' => [
        ['title' => 'Entegrasyon Merkezi', 'icon' => 'plug', 'perm' => 'integrations', 'desc' => 'PayTR, Santral, Parmak İzi, Logo ERP ve T-Soft bağlantı ayarları + test.', 'url' => url('modules/integrations/index.php'), 'kw' => 'entegrasyon integration paytr santral parmak izi logo erp tsoft api'],
        ['title' => 'PayTR Ödeme', 'icon' => 'credit-card', 'perm' => 'integrations', 'desc' => 'PayTR merchant bilgileri, test/canlı mod ve dönüş adresleri.', 'url' => url('modules/integrations/edit.php?key=paytr'), 'kw' => 'paytr odeme payment merchant'],
        ['title' => 'T-Soft Entegrasyonu', 'icon' => 'plug', 'desc' => 'İleride ürünler ve siparişler buradan çekilecek.', 'soon' => true, 'kw' => 'tsoft entegrasyon urun siparis'],
        ['title' => 'Kargo Takip Entegrasyonları', 'icon' => 'route', 'desc' => 'Kargo takip link şablonları.', 'soon' => true, 'kw' => 'kargo takip link entegrasyon'],
        ['title' => 'WhatsApp Mesaj Şablonları', 'icon' => 'message-circle', 'desc' => 'Servis ve iade/değişim bilgilendirme mesajları.', 'soon' => true, 'kw' => 'whatsapp mesaj sablon'],
    ],
    'Finans / Fiyatlandırma Ayarları' => [
        ['title' => 'Akıllı Fiyatlandırma Sihirbazı', 'icon' => 'calculator', 'desc' => 'İleride yapılacak fiyatlandırma sihirbazı.', 'soon' => true, 'kw' => 'fiyat fiyatlandirma sihirbaz'],
        ['title' => 'Kur Ayarları', 'icon' => 'coins', 'desc' => 'Kur kaynağı ve önbellek ayarları (Kur Çevirici ile çakışmaz).', 'url' => url('modules/currency/index.php'), 'perm' => 'currency', 'kw' => 'kur doviz usd eur exchange'],
        ['title' => 'Ödeme Yöntemleri', 'icon' => 'credit-card', 'desc' => 'Servis kapanışında ödeme almak için ödeme yöntemleri.', 'soon' => true, 'kw' => 'odeme yontem nakit kart'],
    ],
];

layout_top('Genel Ayarlar', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">Genel Ayarlar</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('dashboard.php')) ?>">← Geri</a>
        <button type="button" class="btn btn-sm" disabled title="Yakında">Ayar Geçmişi</button>
    </div>
</div>

<div class="settings-toolbar">
    <input type="search" id="settingsSearch" class="settings-search" placeholder="Ayar ara: logo, kargo, servis, iade, platform, personel…" autocomplete="off" aria-label="Ayar ara">
</div>

<div id="settingsResults">
    <?php foreach ($groups as $groupName => $cards): ?>
        <?php
        // yetkisi olmayan kartları çıkar
        $shown = array_values(array_filter($cards, static function ($c) {
            return empty($c['perm']) || can($c['perm']);
        }));
        if (!$shown) { continue; }
        $gKw = mb_strtolower($groupName, 'UTF-8');
        ?>
        <section class="settings-group" data-group="<?= e($gKw) ?>">
            <h2 class="settings-group-title"><?= e($groupName) ?></h2>
            <div class="settings-grid">
                <?php foreach ($shown as $c): ?>
                    <?php
                    $search = mb_strtolower(trim(($c['title'] ?? '') . ' ' . ($c['desc'] ?? '') . ' ' . $groupName . ' ' . ($c['kw'] ?? '')), 'UTF-8');
                    $soon = !empty($c['soon']);
                    $tag = $soon ? 'div' : 'a';
                    $href = (!$soon && !empty($c['url'])) ? ' href="' . e($c['url']) . '"' : '';
                    ?>
                    <<?= $tag ?> class="set-card<?= $soon ? ' is-soon' : '' ?>"<?= $href ?> data-search="<?= e($search) ?>">
                        <span class="set-ic icon-circle"><?= icon($c['icon'] ?? 'settings', 'icon-lg') ?></span>
                        <span class="set-body">
                            <span class="set-title"><?= e($c['title']) ?><?php if ($soon): ?> <span class="set-badge">Yakında</span><?php endif; ?></span>
                            <span class="set-desc"><?= e($c['desc'] ?? '') ?></span>
                        </span>
                    </<?= $tag ?>>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<div id="settingsEmpty" class="settings-empty" hidden>Aradığınız ayara uygun sonuç bulunamadı.</div>

<script>
(function(){
    var input = document.getElementById('settingsSearch');
    var results = document.getElementById('settingsResults');
    var empty = document.getElementById('settingsEmpty');
    if (!input) return;
    function norm(s){ return (s||'').toString().toLowerCase(); }
    input.addEventListener('input', function(){
        var q = norm(input.value).trim();
        var anyVisible = false;
        results.querySelectorAll('.settings-group').forEach(function(group){
            var groupVisible = false;
            group.querySelectorAll('.set-card').forEach(function(card){
                var hit = q === '' || (card.getAttribute('data-search') || '').indexOf(q) !== -1;
                card.style.display = hit ? '' : 'none';
                if (hit) groupVisible = true;
            });
            group.style.display = groupVisible ? '' : 'none';
            if (groupVisible) anyVisible = true;
        });
        empty.hidden = anyVisible;
    });
})();
</script>

<?php
layout_bottom();
