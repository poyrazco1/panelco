<?php
declare(strict_types=1);

/**
 * includes/navigation.php
 * ------------------------------------------------------------------
 * Panelin bilgi mimarisi (IA) için TEK KAYNAK.
 *
 *  - nav_sidebar_groups(): Sol menü — yalnızca GÜNLÜK OPERASYON modülleri.
 *  - nav_settings_catalog(): Genel Ayarlar — tanım / ayar / entegrasyon /
 *    şablon / kural / sistem yapılandırması olan her şey.
 *
 * Sidebar ve Ayarlar aynı yapıdan beslenir; menü bilgisi tekrar edilmez.
 * Standart alanlar:
 *   title, url, icon, perm, desc (ayar), status (ayar), match (sidebar)
 *
 * status: 'active' (çalışır) | 'soon' (yakında/taslak)
 * match : sidebar aktiflik kuralı — ['exact'=>[], 'prefix'=>[], 'not'=>[], 'not_prefix'=>[]]
 *
 * NOT: URL'ler url() ile üretilir → alt klasör kurulumları korunur.
 */

/* =========================================================================
 |  SOL MENÜ (SIDEBAR) — GÜNLÜK OPERASYON
 |  Yalnızca her gün açılan iş modülleri. Ayar/tanım/entegrasyon burada YOK.
 * ====================================================================== */
function nav_sidebar_groups(): array
{
    return [
        'Genel' => [
            ['perm' => 'dashboard', 'title' => 'Genel Bakış', 'path' => 'dashboard.php', 'icon' => 'layout-dashboard',
             'match' => ['exact' => ['dashboard.php']]],
            ['perm' => 'dashboard', 'title' => 'Bildirim Merkezi', 'path' => 'modules/notifications/index.php', 'icon' => 'bell',
             'match' => ['prefix' => ['modules/notifications/']]],
            ['perm' => 'currency', 'title' => 'Kur Çevirici', 'path' => 'modules/currency/index.php', 'icon' => 'coins',
             'match' => ['prefix' => ['modules/currency/']]],
        ],

        'Satış / CRM' => [
            ['perm' => 'customers', 'title' => 'Müşteriler', 'path' => 'modules/customers/index.php', 'icon' => 'users',
             'match' => ['prefix' => ['modules/customers/']]],
            ['perm' => 'leads', 'title' => 'Lead Yönetimi', 'path' => 'modules/leads/index.php', 'icon' => 'user-check',
             'match' => ['prefix' => ['modules/leads/']]],
            ['perm' => 'quotes', 'title' => 'Teklifler', 'path' => 'modules/quotes/index.php', 'icon' => 'file-text',
             'match' => ['prefix' => ['modules/quotes/']]],
            ['perm' => 'orders', 'title' => 'Siparişler', 'path' => 'modules/orders/index.php', 'icon' => 'clipboard-list',
             'match' => ['prefix' => ['modules/orders/']]],
            ['perm' => 'reconciliation', 'title' => 'Mutabakat', 'path' => 'modules/reconciliation/index.php', 'icon' => 'file-pen',
             'match' => ['prefix' => ['modules/reconciliation/']]],
            ['perm' => 'tsoft_products', 'title' => 'T-Soft Ürünler', 'path' => 'modules/tsoft-products/index.php', 'icon' => 'store',
             'match' => ['prefix' => ['modules/tsoft-products/']]],
        ],

        'Sevkiyat' => [
            ['perm' => 'shipments', 'title' => 'Sevkiyat Takibi', 'path' => 'modules/shipments/index.php', 'icon' => 'truck',
             'match' => ['prefix' => ['modules/shipments/'],
                         'not_prefix' => ['modules/shipments/addresses', 'modules/shipments/address-']]],
            ['perm' => 'shipment_addresses', 'title' => 'Sevkiyat Adresleri', 'path' => 'modules/shipments/addresses.php', 'icon' => 'route',
             'match' => ['prefix' => ['modules/shipments/addresses', 'modules/shipments/address-']]],
        ],

        'Teknik Servis' => [
            ['perm' => 'service', 'title' => 'Servis Kabul', 'path' => 'modules/service/intake.php', 'icon' => 'clipboard-plus',
             'match' => ['exact' => ['modules/service/intake.php']]],
            ['perm' => 'service', 'title' => 'Servis Kayıtları', 'path' => 'modules/service/index.php', 'icon' => 'clipboard-list',
             'match' => ['prefix' => ['modules/service/'],
                         'not'    => ['modules/service/intake.php'],
                         'not_prefix' => ['modules/service/repairer', 'modules/service/_repairer',
                                          'modules/service/statuses', 'modules/service/terms']]],
            ['perm' => 'rma', 'title' => 'İade-Değişim Yönetimi', 'path' => 'modules/rma/index.php', 'icon' => 'rotate-ccw',
             'match' => ['prefix' => ['modules/rma/']]],
        ],

        'İK / Personel' => [
            ['perm' => 'leave', 'title' => 'İzin Talepleri', 'path' => 'modules/leave/requests.php', 'icon' => 'clipboard-list',
             'match' => ['prefix' => ['modules/leave/requests.php', 'modules/leave/request-']]],
            ['perm' => 'leave', 'title' => 'Yıllık İzin Takibi', 'path' => 'modules/leave/index.php', 'icon' => 'calendar-check',
             'match' => ['exact'  => ['modules/leave/index.php'],
                         'prefix' => ['modules/leave/history.php', 'modules/leave/balance-adjust.php']]],
            ['perm' => 'attendance', 'title' => 'Puantaj', 'path' => 'modules/attendance/index.php', 'icon' => 'calendar-days',
             'match' => ['exact'  => ['modules/attendance/index.php'],
                         'prefix' => ['modules/attendance/print.php']]],
            ['perm' => 'attendance', 'title' => 'Puantaj Raporları', 'path' => 'modules/attendance/reports.php', 'icon' => 'file-text',
             'match' => ['prefix' => ['modules/attendance/reports.php']]],
            ['perm' => 'commissions', 'title' => 'Primler', 'path' => 'modules/commissions/index.php', 'icon' => 'calculator',
             'match' => ['prefix' => ['modules/commissions/']]],
        ],

        'Form Merkezi' => [
            ['perm' => 'forms.view', 'title' => 'Formlar', 'path' => 'modules/forms/index.php', 'icon' => 'file-pen',
             'match' => ['prefix' => ['modules/forms/index.php', 'modules/forms/submit.php']]],
            ['perm' => 'forms.submissions.view', 'title' => 'Form Kayıtları', 'path' => 'modules/forms/submissions.php', 'icon' => 'clipboard-list',
             'match' => ['prefix' => ['modules/forms/submissions.php', 'modules/forms/submission-view.php']]],
            ['perm' => 'forms.approve', 'title' => 'Onay Bekleyenler', 'path' => 'modules/forms/approvals.php', 'icon' => 'check-circle',
             'match' => ['prefix' => ['modules/forms/approvals.php']]],
        ],

        'Varlık & Araçlar' => [
            ['perm' => 'inventory', 'title' => 'Envanter / Demirbaş', 'path' => 'modules/inventory/index.php', 'icon' => 'package-check',
             'match' => ['prefix' => ['modules/inventory/']]],
            ['perm' => 'reports', 'title' => 'Raporlar', 'path' => 'modules/reports/index.php', 'icon' => 'file-text',
             'match' => ['prefix' => ['modules/reports/']]],
            ['perm' => 'file_manager', 'title' => 'Dosya Yöneticisi', 'path' => 'modules/file-manager/index.php', 'icon' => 'file-down',
             'match' => ['prefix' => ['modules/file-manager/']]],
            ['perm' => 'password_vault', 'title' => 'Şifre Kasası', 'path' => 'modules/password-vault/index.php', 'icon' => 'shield',
             'match' => ['prefix' => ['modules/password-vault/']]],
        ],

        'Sistem' => [
            ['perm' => 'help', 'title' => 'Yardım Merkezi', 'path' => 'modules/help/index.php', 'icon' => 'help-circle',
             'match' => ['prefix' => ['modules/help/']]],
            ['perm' => 'settings', 'title' => 'Genel Ayarlar', 'path' => 'modules/settings/index.php', 'icon' => 'settings',
             'match' => ['prefix' => ['modules/settings/', 'modules/integrations/',
                                      // Ayar niteliğindeki alt sayfalar da Genel Ayarlar'ı aktif etsin
                                      'modules/service/repairer', 'modules/service/_repairer',
                                      'modules/service/statuses', 'modules/service/terms']]],
        ],
    ];
}

/* =========================================================================
 |  GENEL AYARLAR KATALOĞU — tanım/ayar/entegrasyon/şablon/kural/sistem
 |  Kart: title, icon, desc, [url], [perm], [status], [kw]
 |  status: 'active' | 'soon'  (varsayılan: url varsa active, yoksa soon)
 * ====================================================================== */
function nav_settings_catalog(): array
{
    return [
        'Kullanıcı & Yetki' => [
            'icon' => 'shield',
            'cards' => [
                ['title' => 'Kullanıcılar', 'icon' => 'users', 'perm' => 'settings', 'url' => 'modules/settings/users.php',
                 'desc' => 'Panel kullanıcılarını ekleyin, düzenleyin, rol atayın ve aktif/pasif yapın.',
                 'kw' => 'kullanici user rol atama sifre aktif pasif hesap'],
                ['title' => 'Roller', 'icon' => 'shield', 'perm' => 'settings', 'url' => 'modules/settings/roles.php',
                 'desc' => 'Rolleri ve modül bazlı yetkileri yönetin (Yönetici, Muhasebe, Satış, Depo, Personel).',
                 'kw' => 'rol yetki izin permission role'],
                ['title' => 'Personel Tanımları', 'icon' => 'user-check', 'perm' => 'personnel', 'url' => 'modules/settings/personnel.php',
                 'desc' => 'Personel bilgileri, fotoğraf, departman, pozisyon ve panel kullanıcısı bağlantısı.',
                 'kw' => 'personel departman pozisyon calisan staff'],
            ],
        ],

        'Şirket & Görünüm' => [
            'icon' => 'building-2',
            'cards' => [
                ['title' => 'Şirket Bilgileri', 'icon' => 'building-2', 'perm' => 'settings', 'url' => 'modules/settings/company.php',
                 'desc' => 'Firma adı, ticari unvan, adres ve vergi bilgileri.',
                 'kw' => 'firma sirket unvan adres vergi company'],
                ['title' => 'İletişim Ayarları', 'icon' => 'phone', 'perm' => 'settings', 'url' => 'modules/settings/company.php',
                 'desc' => 'Telefon, WhatsApp, e-posta ve web sitesi bilgileri.',
                 'kw' => 'iletisim telefon whatsapp eposta email web contact'],
                ['title' => 'Logo & Favicon', 'icon' => 'image', 'perm' => 'settings', 'url' => 'modules/settings/company.php',
                 'desc' => 'Panel logosu, login logosu, favicon ve PDF/form logosu yükleyin.',
                 'kw' => 'logo favicon pdf form gorsel marka'],
                ['title' => 'Kişisel Görünüm Ayarları', 'icon' => 'palette', 'url' => 'modules/settings/appearance.php',
                 'desc' => 'Tema (açık/koyu/sistem), kenar çubuğu ve panel görünüm tercihleriniz. Kişiseldir.',
                 'kw' => 'tema dark light system koyu acik sidebar kenar cubugu gorunum kisisel'],
                ['title' => 'Genel Panel Ayarları', 'icon' => 'sliders-horizontal', 'status' => 'soon',
                 'desc' => 'Panel başlığı, varsayılan dil ve genel görünüm ayarları.',
                 'kw' => 'panel dil gorunum tema baslik'],
            ],
        ],

        'Operasyon Ayarları' => [
            'icon' => 'truck',
            'cards' => [
                ['title' => 'Kargo Yöntemleri', 'icon' => 'truck', 'perm' => 'shipping', 'url' => 'modules/settings/shipping.php',
                 'desc' => 'Kargo firmaları, logolar, desi ücretleri ve ücretsiz kargo limiti.',
                 'kw' => 'kargo desi ucret gonderi cargo'],
                ['title' => 'Markalar', 'icon' => 'tag', 'perm' => 'brands', 'url' => 'modules/settings/brands.php',
                 'desc' => 'Marka adı, logo, aktif/pasif durum ve sıra no.',
                 'kw' => 'marka brand logo'],
                ['title' => 'Tedarikçiler', 'icon' => 'warehouse', 'perm' => 'suppliers', 'url' => 'modules/suppliers/index.php',
                 'desc' => 'Ürün tedarikçilerinin tanımı, iletişim bilgileri ve yönetimi.',
                 'kw' => 'tedarikci supplier satin alma vendor'],
                ['title' => 'Sevkiyat Ayarları', 'icon' => 'route', 'perm' => 'settings', 'url' => 'modules/settings/shipment-settings.php',
                 'desc' => 'Sevkiyat yönetici WhatsApp numarası ve durum bilgilendirme mesaj şablonu.',
                 'kw' => 'sevkiyat yonetici whatsapp mesaj sablon teslimat toplama'],
            ],
        ],

        'Teknik Servis Ayarları' => [
            'icon' => 'wrench',
            'cards' => [
                ['title' => 'Dış Tamirciler', 'icon' => 'wrench', 'perm' => 'service', 'url' => 'modules/service/repairers.php',
                 'desc' => 'Dışarıda çalışılan tamircilerin kaydı ve iletişimi.',
                 'kw' => 'servis tamirci dis usta repairer'],
                ['title' => 'Servis Durumları', 'icon' => 'list-checks', 'perm' => 'service', 'url' => 'modules/service/statuses.php',
                 'desc' => 'Servis kabul sürecindeki durum tanımları.',
                 'kw' => 'servis durum status'],
                ['title' => 'Servis Koşulları', 'icon' => 'file-text', 'perm' => 'service', 'url' => 'modules/service/terms.php',
                 'desc' => 'Servis teslim formundaki koşul metinleri.',
                 'kw' => 'servis kosul sozlesme metin terms'],
                ['title' => 'Servis Mesaj Şablonları', 'icon' => 'message-circle', 'perm' => 'settings', 'url' => 'modules/settings/service-messages.php',
                 'desc' => 'Servis durum bilgilendirme için WhatsApp/e-posta şablonları ve SMS altyapı ayarları.',
                 'kw' => 'servis mesaj sablon durum bilgilendirme whatsapp sms eposta'],
                ['title' => 'Servis Formu Ayarları', 'icon' => 'file-pen', 'status' => 'soon',
                 'desc' => 'Servis formu başlığı, logo gösterimi ve imza/onay ayarları.',
                 'kw' => 'servis form imza onay'],
            ],
        ],

        'İade-Değişim Ayarları' => [
            'icon' => 'rotate-ccw',
            'cards' => [
                ['title' => 'Platformlar', 'icon' => 'store', 'status' => 'soon',
                 'desc' => 'T-Soft, Trendyol, Hepsiburada, N11, Amazon, WhatsApp, Mağaza vb.',
                 'kw' => 'platform pazaryeri trendyol hepsiburada n11 amazon'],
                ['title' => 'İade-Değişim Durumları', 'icon' => 'workflow', 'status' => 'soon',
                 'desc' => 'Talep alındı, ürün bekleniyor, değişim tamamlandı vb.',
                 'kw' => 'iade degisim durum status'],
                ['title' => 'İade-Değişim Sebepleri', 'icon' => 'help-circle', 'status' => 'soon',
                 'desc' => 'Yanlış ürün gönderimi, hasarlı ürün, cayma hakkı, tedarikçi hatası vb.',
                 'kw' => 'iade degisim sebep neden reason'],
            ],
        ],

        'Form Ayarları' => [
            'icon' => 'file-pen',
            'cards' => [
                ['title' => 'Form Şablonları', 'icon' => 'file-pen', 'perm' => 'forms.templates.manage', 'url' => 'modules/forms/templates.php',
                 'desc' => 'İç ve dış formların alanlarını, aktiflik durumunu ve onay gereksinimini yönetin.',
                 'kw' => 'form şablon template iç dış alan field'],
                ['title' => 'Form Kategorileri', 'icon' => 'folder', 'perm' => 'forms.categories.manage', 'url' => 'modules/forms/categories.php',
                 'desc' => 'Formları şirket içi, müşteri, teknik servis, sevkiyat, İK ve muhasebe kategorilerine ayırın.',
                 'kw' => 'form kategori category'],
                ['title' => 'Dış Form Linkleri', 'icon' => 'link', 'perm' => 'forms.external.manage', 'url' => 'modules/forms/external-links.php',
                 'desc' => 'Müşterilere gönderilecek tokenlı dış form linklerini oluşturun ve yönetin.',
                 'kw' => 'dış form public token link müşteri'],
                ['title' => 'Form Mesaj Şablonları', 'icon' => 'message-circle', 'perm' => 'forms.templates.manage', 'url' => 'modules/forms/message-templates.php',
                 'desc' => 'Form alındı, onaylandı, reddedildi gibi WhatsApp/e-posta mesaj metinlerini yönetin.',
                 'kw' => 'form mesaj whatsapp eposta şablon'],
            ],
        ],

        'İK / Personel Ayarları' => [
            'icon' => 'calendar-check',
            'cards' => [
                ['title' => 'İzin Türleri', 'icon' => 'list-checks', 'perm' => 'leave', 'url' => 'modules/settings/leave-types.php',
                 'desc' => 'Yıllık, ücretsiz, mazeret, rapor vb. izin türlerini ve kurallarını yönetin.',
                 'kw' => 'izin türü leave type yillik ucretsiz mazeret'],
                ['title' => 'Çalışma Takvimi', 'icon' => 'calendar', 'perm' => 'leave', 'url' => 'modules/settings/work-calendar.php',
                 'desc' => 'Hafta sonu günleri, resmi tatil düşümü ve varsayılan yıllık izin gün sayısı.',
                 'kw' => 'calisma takvimi hafta sonu izin gun calendar'],
                ['title' => 'Resmi Tatiller', 'icon' => 'calendar-days', 'perm' => 'leave', 'url' => 'modules/settings/holidays.php',
                 'desc' => 'Resmi ve şirket tatillerini tanımlayın; izin gün hesabından düşülür.',
                 'kw' => 'resmi tatil holiday bayram'],
                ['title' => 'Vardiya / Mesai Ayarları', 'icon' => 'clock', 'perm' => 'attendance', 'url' => 'modules/settings/shifts.php',
                 'desc' => 'Çalışma programları: mesai başlangıç/bitiş, mola ve haftalık çalışma günleri.',
                 'kw' => 'vardiya mesai calisma programi shift schedule'],
                ['title' => 'Puantaj Kuralları', 'icon' => 'sliders-horizontal', 'perm' => 'attendance', 'url' => 'modules/settings/attendance-statuses.php',
                 'desc' => 'Puantaj durumları: renk, ücretli/çalışma günü/eksik gün sayımı ve sıralama.',
                 'kw' => 'puantaj durum kural attendance status'],
                ['title' => 'İK Bildirim Ayarları', 'icon' => 'bell', 'perm' => 'settings', 'url' => 'modules/settings/notification-settings.php',
                 'desc' => 'Doğum günü, yıl dönümü ve izin bildirimleri; gün aralıkları ve mail/WhatsApp modu.',
                 'kw' => 'bildirim hatirlatma dogum gunu yil donumu notification'],
                ['title' => 'Kutlama Şablonları', 'icon' => 'gift', 'perm' => 'settings', 'url' => 'modules/settings/notification-templates.php',
                 'desc' => 'Doğum günü ve çalışma yıl dönümü için WhatsApp ve e-posta mesaj şablonları.',
                 'kw' => 'sablon template whatsapp mail kutlama'],
            ],
        ],

        'Entegrasyon Ayarları' => [
            'icon' => 'plug',
            'cards' => [
                ['title' => 'Entegrasyon Merkezi', 'icon' => 'plug', 'perm' => 'integrations', 'url' => 'modules/integrations/index.php',
                 'desc' => 'PayTR, Santral, Parmak İzi, Logo ERP ve T-Soft bağlantı ayarları + test.',
                 'kw' => 'entegrasyon integration paytr santral parmak izi logo erp tsoft api'],
                ['title' => 'T-Soft Entegrasyonu', 'icon' => 'store', 'perm' => 'integrations', 'url' => 'modules/integrations/edit.php?key=tsoft',
                 'desc' => 'T-Soft API bağlantısı; ürün ve sipariş entegrasyonu ayarları.',
                 'kw' => 'tsoft entegrasyon urun siparis api'],
                ['title' => 'PayTR Ödeme', 'icon' => 'credit-card', 'perm' => 'integrations', 'url' => 'modules/integrations/edit.php?key=paytr',
                 'desc' => 'PayTR merchant bilgileri, test/canlı mod ve dönüş adresleri.',
                 'kw' => 'paytr odeme payment merchant'],
                ['title' => 'SMTP / E-posta Testi', 'icon' => 'mail', 'perm' => 'settings', 'url' => 'modules/settings/smtp-test.php',
                 'desc' => 'SMTP yapılandırmasını görüntüleyin ve gerçek bir test maili göndererek doğrulayın.',
                 'kw' => 'smtp mail eposta test gonderim phpmailer sunucu'],
                ['title' => 'WhatsApp Ayarları', 'icon' => 'message-circle', 'status' => 'soon',
                 'desc' => 'WhatsApp bildirim/mesaj gönderim altyapısı ve varsayılan şablon ayarları.',
                 'kw' => 'whatsapp mesaj sablon api'],
                ['title' => 'Kargo Takip Entegrasyonları', 'icon' => 'route', 'status' => 'soon',
                 'desc' => 'Kargo takip link şablonları ve otomatik durum güncelleme.',
                 'kw' => 'kargo takip link entegrasyon tracking'],
            ],
        ],

        'Finans / Fiyatlandırma Ayarları' => [
            'icon' => 'coins',
            'cards' => [
                ['title' => 'Kur Ayarları', 'icon' => 'coins', 'perm' => 'currency', 'url' => 'modules/currency/index.php',
                 'desc' => 'Kur kaynağı ve önbellek ayarları (Kur Çevirici ile çakışmaz).',
                 'kw' => 'kur doviz usd eur exchange'],
                ['title' => 'Ödeme Yöntemleri', 'icon' => 'credit-card', 'status' => 'soon',
                 'desc' => 'Servis kapanışında ödeme almak için ödeme yöntemleri.',
                 'kw' => 'odeme yontem nakit kart payment'],
                ['title' => 'Akıllı Fiyatlandırma Ayarları', 'icon' => 'calculator', 'status' => 'soon',
                 'desc' => 'Otomatik/kurallı fiyatlandırma sihirbazı ve marj ayarları.',
                 'kw' => 'fiyat fiyatlandirma sihirbaz marj'],
            ],
        ],

        'Sistem Bakımı' => [
            'icon' => 'settings',
            'cards' => [
                ['title' => 'Ön Bellek Temizle', 'icon' => 'refresh-cw', 'perm' => 'settings', 'url' => 'modules/settings/cache.php',
                 'desc' => 'Geçici/önbellek dosyalarını güvenle temizleyin. Oturum ve veriler etkilenmez.',
                 'kw' => 'onbellek cache temizle tmp gecici'],
                ['title' => 'Log Görüntüleme', 'icon' => 'file-text', 'status' => 'soon',
                 'desc' => 'Sistem ve işlem loglarını panel üzerinden görüntüleme.',
                 'kw' => 'log kayit gunluk hata'],
                ['title' => 'Sistem Kontrolü', 'icon' => 'check-circle', 'status' => 'soon',
                 'desc' => 'Kurulum, veritabanı ve yapılandırma sağlık kontrolü.',
                 'kw' => 'sistem kontrol saglik kurulum install check'],
            ],
        ],
    ];
}
