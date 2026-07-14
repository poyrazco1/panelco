# CLAUDE CODE ANA ÜRETİM PROMPTU
## CASE EIGHT: UNWRITTEN — Python ile geliştirilecek 3D psikolojik korku oyunu

Sen yalnızca fikir veren veya örnek kod yazan bir sohbet asistanı değilsin. Bu projede terminale, dosya sistemine ve geliştirme araçlarına erişebilen ana geliştirme ajanı olarak çalışacaksın.

Bu görev normal Claude sohbeti için değil, **Claude Code** için hazırlanmıştır.

## 0. BAĞLAYICI KAYNAK BELGE

Bu promptla birlikte kullanıcı tarafından verilen:

`Yapıştırılan metin(11).txt`

dosyasını önce eksiksiz oku.

Bu dosya CASE EIGHT: UNWRITTEN oyununun bağlayıcı ana tasarım belgesidir. Dosyada bulunan aşağıdaki bilgiler korunacaktır:

- Oyunun adı ve evreni
- ROBOKAR Games
- Department Eight
- NARRATOR-8
- Eğitim sistemi
- Karargâh
- Ana oynanış döngüsü
- Tam olarak 8 ana harita ve bu haritaların adları, bölümleri ve açılma seviyeleri
- Tam olarak 20 paranormal varlık görünümü
- Tam olarak 8 araştırma karavanı
- Tam olarak 8 kişisel araç
- Tam olarak 32 ekipman
- Telefon sistemi
- Yakınlık sesi, telsiz ve telefon görüşmesi
- Hunt sistemi
- Saklanma sistemi
- Kanıt, fotoğraf, video replay ve ritüel sistemleri
- Equipment Return Scanner
- Equipment Recovery Phase
- Seviye, ekonomi, prestij ve Challenge Mode
- Tek oyuncu ve 1–4 kişilik çevrim içi oynanış
- Yerel prosedürel vaka üreticisi
- İsteğe bağlı dış yapay zekâ sağlayıcısı
- Kayıt, backend, test ve teslim kriterleri
- Bütün sayı, isim, fiyat, seviye, süre, kural ve yasaklar

Kaynak dosyadaki içerikleri özetleyip azaltma. Kaynak belgede belirtilen özellikleri mümkün olduğu ölçüde aynen koru.

Ancak kaynak belge 2D ve Unity için yazılmıştır. Aşağıdaki 3D/Python dönüştürme kuralları, kaynak belgedeki 2D ve Unity ile ilgili bütün maddelerin yerine geçer.

---

# 1. TEMEL DÖNÜŞÜM KURALLARI

## 1.1 Oyun 3D olacaktır

Oyun kesinlikle 2D olmayacaktır.

Yeni oyun yapısı:

- Birinci şahıs 3D psikolojik korku
- Tek oyunculu ve 1–4 kişilik çevrim içi iş birliği
- Serbest 3D hareket
- Gerçek 3D odalar, koridorlar, merdivenler, bodrumlar ve dış alanlar
- Gerçek 3D kapılar, çekmeceler, dolaplar ve saklanma noktaları
- Ağ üzerinden senkronize tam gövdeli oyuncu karakterleri
- 3D paranormal varlıklar
- 3D ekipmanlar
- 3D araçlar ve araştırma karavanları
- Dinamik ışıklar, gölgeler, sis, parçacıklar ve ekran efektleri
- 3D uzamsal ses
- Fare ve klavye ile birinci şahıs kontrol
- Kontrolcü desteğine uygun giriş soyutlaması

Kamera:

- Oyuncunun göz hizasında birinci şahıs kamera
- Ayarlanabilir FOV
- Yumuşak kafa hareketi
- İsteğe bağlı head bob
- Eğilme
- Çömelme
- Koşma
- Nefes tutma
- Saklanırken sınırlı bakış
- Hunt sırasında kontrollü kamera sarsıntısı
- Fotosensitif erişilebilirlik ayarları
- Kamera hareketi azaltma seçeneği

## 1.2 Unity kullanılmayacaktır

Aşağıdaki teknolojileri kullan:

### Ana oyun motoru
- Python
- Panda3D
- Panda3D Bullet fizik sistemi
- Panda3D’nin sahne grafiği, animasyon, ses, parçacık, shader ve ağ sistemleri

### 3D üretim
- Blender
- Blender Python API
- Blender’ın komut satırı/headless modu
- Modelleri mümkün olduğunca Python betikleriyle üret
- Çıktı biçimi olarak öncelikle `.glb` veya `.gltf`
- Gerekirse Panda3D için optimize edilmiş `.bam`
- Kaynak `.blend` dosyalarını sakla

### Backend
- Python
- FastAPI
- WebSocket
- Pydantic
- SQLAlchemy veya SQLModel
- Geliştirme ve yerel kullanım için SQLite
- Üretim için isteğe bağlı PostgreSQL
- Alembic veri tabanı migrasyonları

### Test
- pytest
- pytest-asyncio
- unittest gerektiğinde
- Panda3D için başlatılabilen smoke testleri
- Ağ protokol testleri
- İçerik doğrulama testleri
- Asset referans doğrulama testleri

### Paketleme
- Panda3D build araçları
- Windows 10/11 64-bit için kendi kendine çalışan dağıtım
- Gerekirse PyInstaller yalnızca yardımcı araçlar için
- Ana oyun paketlemesinde Panda3D’nin önerilen dağıtım yolunu tercih et

Kodun ana dili Python olacaktır. Blender model üretim betikleri de Python olacaktır. Shader kodları gerektiğinde GLSL olabilir. Düşük seviyeli zorunlu bir entegrasyon dışında C#, Unity veya Unreal kullanma.

---

# 2. GÖRSEL HEDEF VE GERÇEKÇİ KAPSAM

Oyunun görsel hedefi:

- Karanlık, kasvetli, stilize gerçekçi 3D
- Düşük kaliteli blok prototip görünümünde kalmayan
- Orta poligon yoğunluğunda optimize modeller
- Tutarlı PBR malzemeler
- Kir, pas, nem, küf, çizik ve yaşlanma detayları
- Korku atmosferine uygun sınırlı renk paleti
- Dinamik ışık ve gölgeler
- Sis, toz, yağmur, buhar ve paranormal parçacıklar
- İç mekânda gerilim odaklı aydınlatma
- Düşük ve orta seviye bilgisayarlarda çalışabilecek optimizasyon

“AAA fotogerçekçilik” iddiasında bulunma. Gerçekten üretilebilecek, tutarlı ve oynanabilir stilize gerçekçi kaliteyi hedefle.

Geçici prototiplerde basit geometri kullanılabilir. Ancak geçici geometri:

- Açıkça `PLACEHOLDER` olarak işaretlenmeli
- Nihai içerik olarak gösterilmemeli
- Durum raporunda sayılmalı
- İlgili aşamanın kabulünden önce değiştirilmelidir

---

# 3. CLAUDE CODE ÇALIŞMA DİSİPLİNİ

## 3.1 Plan yazıp durma; gerçek dosya üret

Yalnızca yapılacaklar listesi, mimari rapor veya kod parçaları sunma.

Terminal ve dosya sistemi erişimin varsa:

- Klasörleri oluştur
- Sanal ortamı oluştur
- Bağımlılıkları kur
- Python dosyalarını yaz
- Blender betiklerini yaz
- Modelleri üret
- Testleri çalıştır
- Oyunu başlat
- Hataları düzelt
- Build al
- Raporları kaydet

Kullanıcıdan kod yazmasını, dosya oluşturmasını, model yapmasını, Blender’da elle işlem yapmasını veya hata düzeltmesini isteme.

Yalnızca kullanıcı onayı gerektiren lisans, hesap girişi, ücretli servis, Steamworks erişimi veya güvenlik açısından zorunlu bir işlem varsa bunu açıkça belirt.

## 3.2 Asla sahte başarı raporu verme

Aşağıdakiler yasaktır:

- Çalıştırılmayan testi başarılı göstermek
- Oluşturulmayan modeli varmış gibi göstermek
- Açılmayan oyunu çalışıyor göstermek
- Eksik sahneyi tamamlanmış göstermek
- Placeholder modeli nihai model diye sunmak
- Bağlantı kurulmadan çok oyunculu sistemi test edilmiş göstermek
- Windows build alınmadan EXE hazır demek
- Blender kurulu değilken gerçek model üretildiğini söylemek
- Ses dosyası oluşturulmadan ses sistemi tamamlandı demek
- Steam SDK erişimi yokken Steam entegrasyonu tamamlandı demek
- İnternet veya API erişimi yokken dış servisi test edilmiş göstermek

Her raporda şu durum etiketlerinden birini kullan:

- `DONE_AND_TESTED`
- `DONE_NOT_FULLY_TESTED`
- `IMPLEMENTED_WITH_PLACEHOLDER`
- `BLOCKED_BY_ENVIRONMENT`
- `NOT_STARTED`

## 3.3 Proje hafızası oluştur

İlk aşamada şu dosyaları oluştur ve her oturumda güncelle:

- `CLAUDE.md`
- `docs/SOURCE_DESIGN_2D.txt`
- `docs/MASTER_3D_SPEC.md`
- `docs/ARCHITECTURE.md`
- `docs/ROADMAP.md`
- `docs/STATUS.md`
- `docs/DECISIONS.md`
- `docs/KNOWN_ISSUES.md`
- `docs/ASSET_LICENSES.md`
- `docs/CONTROLS.md`
- `reports/ENVIRONMENT_AUDIT.md`
- `reports/TEST_REPORT.md`
- `reports/BUILD_REPORT.md`
- `reports/PLACEHOLDER_REPORT.md`

Kaynak tasarım dosyasının bir kopyasını `docs/SOURCE_DESIGN_2D.txt` içine koy.

`docs/MASTER_3D_SPEC.md` dosyasında kaynak belgedeki bütün 2D/Unity ifadelerini 3D/Python karşılıklarına dönüştür. Oyunun sayılarını ve içeriklerini değiştirme.

Her yeni oturumda önce:

1. `CLAUDE.md`
2. `docs/STATUS.md`
3. `docs/KNOWN_ISSUES.md`
4. Son test raporu

dosyalarını oku ve kaldığın yerden devam et.

---

# 4. İLK GÖREV: GERÇEK ORTAM DENETİMİ

İlk yanıtta doğrudan üretime başlamadan önce gerçek terminal komutlarıyla aşağıdakileri denetle:

- İşletim sistemi ve sürüm
- CPU ve çekirdek sayısı
- RAM
- Boş disk alanı
- GPU
- OpenGL sürümü
- Vulkan desteği
- Ses giriş/çıkış aygıtlarının görülebilirliği
- Python sürümü
- `pip`
- `venv`
- Git
- Git LFS
- C/C++ build araçları
- Panda3D kurulu mu
- Panda3D örnek penceresi açılabiliyor mu
- Panda3D headless test çalışıyor mu
- Panda3D Bullet kullanılabiliyor mu
- Blender kurulu mu
- Blender komut satırından açılabiliyor mu
- Blender Python betiği çalıştırılabiliyor mu
- `.blend` ve `.glb` üretilebiliyor mu
- FFmpeg
- Node.js yalnızca yardımcı ihtiyaçlar için
- SQLite
- PostgreSQL erişimi
- İnternet erişimi
- Dosya yazma izni
- Alt süreç çalıştırma izni
- Port açma ve localhost bağlantısı
- Windows build üretme yeteneği
- Steamworks SDK erişimi
- Mikrofon testi yapma olanağı
- Birden fazla oyun istemcisi çalıştırma olanağı

Denetim sonuçlarını `reports/ENVIRONMENT_AUDIT.md` dosyasına yaz.

Eksik araç varsa ve yetkin varsa gerçekten kurmayı dene. Kurulum başarısız olursa hatayı kaydet. Kurulmamış bir aracı kurulmuş gibi gösterme.

Ortam denetiminden sonra kullanıcıdan beklemeden, mümkün olan en yakın üretim aşamasına geç.

---

# 5. ZORUNLU PROJE YAPISI

Aşağıdaki yapıyı oluştur:

```text
CaseEightUnwritten3D/
├── CLAUDE.md
├── README.md
├── pyproject.toml
├── requirements.lock
├── .gitignore
├── config/
│   ├── default.toml
│   ├── development.toml
│   └── production.toml
├── game/
│   ├── __init__.py
│   ├── main.py
│   ├── bootstrap/
│   ├── core/
│   ├── rendering/
│   ├── shaders/
│   ├── input/
│   ├── player/
│   ├── characters/
│   ├── interaction/
│   ├── inventory/
│   ├── equipment/
│   ├── evidence/
│   ├── cases/
│   ├── narrator8/
│   ├── entities/
│   ├── hunt/
│   ├── hiding/
│   ├── phone/
│   ├── voice/
│   ├── networking/
│   ├── lobby/
│   ├── vehicles/
│   ├── caravans/
│   ├── economy/
│   ├── progression/
│   ├── saving/
│   ├── localization/
│   ├── accessibility/
│   ├── audio/
│   ├── ui/
│   └── diagnostics/
├── server/
│   ├── app/
│   ├── api/
│   ├── websocket/
│   ├── authoritative/
│   ├── persistence/
│   ├── migrations/
│   └── tests/
├── tools/
│   ├── blender/
│   ├── asset_pipeline/
│   ├── validators/
│   ├── localization/
│   ├── packaging/
│   └── diagnostics/
├── assets/
│   ├── source/
│   │   ├── blender/
│   │   ├── textures/
│   │   ├── audio/
│   │   └── references/
│   ├── models/
│   │   ├── characters/
│   │   ├── entities/
│   │   ├── maps/
│   │   ├── equipment/
│   │   ├── vehicles/
│   │   ├── caravans/
│   │   └── props/
│   ├── animations/
│   ├── materials/
│   ├── textures/
│   ├── audio/
│   ├── fonts/
│   ├── ui/
│   └── licenses/
├── data/
│   ├── catalogs/
│   ├── localization/
│   ├── case_templates/
│   ├── map_manifests/
│   ├── entity_profiles/
│   ├── equipment/
│   ├── vehicles/
│   └── caravans/
├── scenes/
│   ├── bootstrap/
│   ├── headquarters/
│   ├── training/
│   ├── maps/
│   └── tests/
├── saves/
├── docs/
├── reports/
├── tests/
│   ├── unit/
│   ├── integration/
│   ├── gameplay/
│   ├── networking/
│   ├── assets/
│   └── build/
├── builds/
│   └── windows/
└── scripts/
    ├── setup.ps1
    ├── setup.sh
    ├── run_game.ps1
    ├── run_server.ps1
    ├── test_all.ps1
    └── build_windows.ps1
```

Tek bir devasa Python dosyası oluşturma. Her sistemi ayrı modüllere böl.

---

# 6. KOD KALİTESİ VE MİMARİ

Aşağıdaki ilkeleri uygula:

- Python type hint kullan
- `dataclass`, `Enum`, `Protocol` ve soyut arayüzleri uygun yerde kullan
- Pydantic ile ağ ve kayıt şemalarını doğrula
- Oyun mantığını render kodundan ayır
- Sunucu yetkili sistemleri istemci kodundan ayır
- Global değişken kullanımını azalt
- Dependency injection veya açık servis kayıt sistemi kullan
- Her ana sistem için log üret
- Yapılandırmayı kod içine gömme
- Sabit değerleri veri kataloglarında tut
- Network mesajlarını sürümlendir
- Save dosyalarına sürüm ve migrasyon ekle
- Hileye açık değerleri istemciye bırakma
- İstemciden gelen bütün önemli mesajları sunucuda doğrula
- Dosya yollarını platform bağımsız oluştur
- Gizli anahtarları kaynak koda yazma
- `.env.example` oluştur; gerçek anahtarları commit etme

Ana servis örnekleri:

- `GameBootstrap`
- `GameStateManager`
- `SceneManager3D`
- `AssetManager`
- `InputManager`
- `FirstPersonController`
- `InteractionSystem`
- `InventoryService`
- `EquipmentService`
- `EvidenceService`
- `CaseService`
- `LocalCaseGenerator`
- `CaseValidator`
- `Narrator8Provider`
- `EntityStateMachine`
- `EntityPerceptionSystem`
- `EntityNavigationSystem`
- `HuntManager`
- `HidingSystem`
- `PhoneService`
- `TextChatService`
- `VoiceChatService`
- `NetworkClient`
- `AuthoritativeGameServer`
- `LobbyService`
- `CaravanService`
- `VehicleService`
- `ReturnScannerService`
- `RecoveryPhaseService`
- `EconomyService`
- `ProgressionService`
- `SaveService`
- `LocalizationService`
- `AccessibilityService`
- `AudioService`
- `UIService`
- `DiagnosticsService`

---

# 7. 3D OYUNCU SİSTEMİ

Oyuncu özellikleri:

- Birinci şahıs hareket
- Yürüme
- Koşma
- Çömelme
- Eğilme
- Zıplama yalnızca tasarım açısından gerekliyse; korku oyununda gereksizse kapalı
- Merdiven kullanma
- Kapı açma
- Çekmece açma
- Nesne alma
- Nesne bırakma
- Nesne taşıma
- Ekipman kullanma
- Telefon kullanma
- Telsiz kullanma
- Fotoğraf çekme
- Video kamera yerleştirme
- Saklanma
- Dolap kapağını tutma
- Nefes tutma
- Yaralanma
- Ölüm
- Yeniden bağlanma sonrası durum geri yükleme

Fizik:

- Bullet character controller veya güvenli özel kapsül denetleyici
- Merdiven ve basamak toleransı
- Eğim sınırı
- Duvar içinden geçmeyi önleme
- Ağ düzeltmesi
- Hareketli kapılarla çakışma güvenliği

Çok oyunculu görünüm:

- Her oyuncunun ağ üzerinden görünen tam gövdeli 3D modeli
- Baş, kol ve eldeki ekipman yönlendirmesi
- Idle, walk, run, crouch, use-item, phone, radio, hide, hurt ve death animasyonları
- Yerel birinci şahıs kolları ile uzak oyuncu tam gövdesini ayrı yönet
- Oyuncunun kendi gövdesinin kamerayı kapatmamasını sağla

Karakter özelleştirme kaynak belgedeki seçenekleri koruyacak şekilde modüler 3D olarak uygulanmalıdır.

---

# 8. 3D HARİTALAR

Kaynak belgede tanımlanan tam olarak 8 ana harita korunacaktır:

1. Hollow Creek Residence
2. Grey Pines Motel
3. Morrow Farmstead
4. Blackridge High School
5. Northvale Memorial Hospital
6. Bellweather Grand Hotel
7. Kestrel Underground Research Site
8. Crimson Crown Palace / Kızıl Taç Sarayı

Eğitim alanı ve Department Eight karargâhı bu sekize dahil değildir.

Kaynak belgedeki her haritanın bütün oda ve alanları korunmalıdır.

Her harita:

- Ayrı sahne/veri manifestine sahip olmalı
- 3D mimari olarak Blender Python betikleriyle üretilebilmeli
- Duvar, zemin, tavan, kapı, pencere, merdiven ve ana mobilyaları içermeli
- Collision mesh içermeli
- Navigation graph veya navigation mesh içermeli
- Evidence socket noktaları içermeli
- Saklanma noktaları içermeli
- Entity spawn ve patrol noktaları içermeli
- Paranormal event socket noktaları içermeli
- Ses bölgeleri içermeli
- Işık bölgeleri içermeli
- Occlusion ve performans bölgeleri içermeli
- Harita doğrulama testi içermeli

Haritaları tek dosyada birleştirme. İç alanları mantıksal bölgelere ayır.

İlk tam harita Hollow Creek Residence olmalıdır. Önce bu haritayı gerçekten oynanabilir hale getir, sonra diğer haritaları aynı araç zinciriyle üret.

Blender betiklerinde:

- Ölçü birimini metre kabul et
- Kapı, koridor ve merdiven ölçülerini oyuncu kapsülüne göre doğrula
- Duvar kalınlığı kullan
- UV oluştur
- Materyal slotları oluştur
- LOD gerektiğinde üret
- Collision için ayrı düşük detay mesh üret
- Spawn/socket noktalarını Blender Empty veya isimlendirilmiş node olarak dışa aktar
- Dışa aktarımdan sonra otomatik doğrulama yap

---

# 9. 3D VARLIKLAR

Kaynak belgede bulunan tam olarak 20 paranormal varlığın adlarını ve temalarını koru.

Her varlık için:

- Kaynak `.blend`
- Oyun `.glb`
- Optimize mesh
- UV
- PBR materyal
- İskelet
- Idle
- Walk
- Stalk
- Manifest
- Hunt
- Search
- Attack
- Disappear
- Final animasyonları
- En az üç materyal/renk varyasyonu
- En az iki kıyafet veya yüzey varyasyonu
- Normal görünüm
- Termal görünüm materyali
- UV tepki materyali
- Fotoğraf bozulma profili
- Video glitch profili
- Ses profili
- Entity davranış profili
- Collision ve saldırı hacimleri
oluştur.

Varlık görünümü ile davranış tipi birbirinden ayrılmalıdır. Aynı görünüm farklı vaka davranışlarıyla kullanılabilmelidir.

Varlık gerçek zamanlı olarak bir dil modeli tarafından hareket ettirilmemelidir.

Durum makinesi kaynak belgedeki durumları korumalıdır:

- Dormant
- Aware
- Disturbance
- Manifestation
- Stalking
- HuntPreparation
- Hunt
- Search
- Cooldown
- Enraged
- FinalEncounter

Algılama sistemi:

- Oyuncu görüşü
- Adım sesi
- Koşma
- Kapı sesi
- Düşen eşya
- Yakınlık mikrofonu
- Telsiz
- Telefon görüşmesi
- Telefon zil ve titreşim sesi
- Açık elektronik cihaz
- Lanetli nesne
- Tekrar kullanılan saklanma alanı
- Son görülen konum
- Son duyulan konum

Varlık AI’sı sunucu yetkili olmalıdır.

---

# 10. HUNT VE SAKLANMA

Kaynak belgedeki Hunt kurallarını 3D ortama dönüştür.

Hunt öncesi 3D etkiler:

- Işıkların fiziksel olarak titremesi
- Ampullerin patlayabilmesi
- Telefon ekranında shader bozulması
- Telsiz paraziti
- Saatlerin durması
- Kapıların kapanması
- Karavan ekranlarının kapanması
- Kısa varlık görüntüsü
- Görüş daralması
- Uzamsal ses değişimi
- Sis yoğunluğunun artması

Hunt sırasında:

- Çıkışlar sunucu tarafından kilitlenir
- Karavan güvenli alan olmaz
- Araçlar kullanılamaz
- Return Scanner kapanır
- Telefon ve telsiz bozulur
- Varlık oyuncuları arar
- Saklanma noktaları risk hafızasına sahip olur
- Mikrofon gürültüsü isteğe bağlı ve izinli biçimde tehdit sistemine girebilir

Kaynak belgede listelenen bütün saklanma türlerini uygun 3D karşılıklarıyla oluştur.

Saklanırken:

- Oyuncu fiziksel olarak saklanma noktasına yerleştirilir
- Hareket kısıtlanır
- Kapı tutma mekaniği olur
- Nefes tutma mekaniği olur
- Telefon sessize alınabilir
- Ekipman kapatılabilir
- Görüş gerçekçi olarak daralır
- Varlık dolap kapağını zorlayabilir
- Aynı noktanın tekrar kullanımı riski artırır

---

# 11. EKİPMAN, ENVANTER VE SAHİPLİK

Kaynak belgedeki tam olarak 32 ekipmanı koru.

Her ekipman için:

- Kaynak Blender modeli
- Oyun modeli
- Birinci şahıs elde tutma görünümü
- Dünya görünümü
- Envanter ikonu
- Açık/kapalı durumu
- Animasyon
- Ses
- Pil veya kullanım hakkı
- Fizik/collision
- Veri tanımı
- Ağ mesajları
- Sunucu doğrulaması
- Gerçek oynanış işlevi
- Test

Her satın alınmış ekipman için benzersiz `ItemInstanceID` oluştur.

Durumlar:

- Available
- InMission
- Returned
- Lost
- Consumed
- LockedInScanner

Başka oyuncu ekipmanı kullanabilmeli fakat sahiplik değişmemelidir.

Return Scanner ve Equipment Recovery Phase kaynak belgedeki süre ve kurallarla uygulanmalıdır.

İstemci yalnızca “eşya bıraktım” mesajı gönderir. Eşyanın gerçekten scanner bölgesinde olup olmadığını, kimliğini, sahibini ve çoğaltılmış olup olmadığını sunucu doğrular.

---

# 12. TELEFON, MESAJLAŞMA VE İLETİŞİM

Oyun içi telefon gerçek 3D elde tutulan bir model ve ayrı UI katmanı olarak uygulanmalıdır.

Telefon açıldığında oyun durmamalıdır.

Kaynak belgedeki bütün telefon uygulamalarını koru:

- Team Chat
- Direct Messages
- Contacts
- Voice Calls
- Evidence
- Photos
- Video Clips
- Map
- Objectives
- Case Notes
- Player Status
- Settings
- Emergency
- Report Player
- Blocked Players

Telefon:

- Pil tüketir
- Karavanda şarj olur
- Power bank kullanır
- Düşük pilde parlaklığı azalır
- Sinyalsiz bölgelerde mesajı geciktirir
- Hunt sırasında ses çıkararak oyuncuyu ele verebilir
- Sessiz ve titreşim moduna sahip olur

Paranormal mesajlar gerçek oyuncu hesabını taklit etmemelidir. Sistem sahte mesajları açık biçimde ayrı paranormal event kimliğiyle üretmeli, ancak oyuncuya korku amacıyla arayüz bozulması gösterebilir.

İletişim katmanları:

1. Yakınlık sesi
2. Telsiz
3. Özel telefon görüşmesi
4. Yazılı takım sohbeti
5. Özel yazılı mesaj

Sesli iletişim tam olarak uygulanamıyorsa:

- Metin iletişimi çalışır halde teslim edilmeli
- Ses sistemi için soyut arayüz ve yerel prototip oluşturulmalı
- Çalışmayan özellik tamamlandı diye işaretlenmemeli
- Mikrofon izni ve gizlilik açık biçimde yönetilmeli
- Ham mikrofon sesi kalıcı olarak kaydedilmemeli

---

# 13. FOTOĞRAF VE VİDEO REPLAY

Fotoğraf sistemi yalnızca ekran görüntüsü almakla sınırlı kalmamalıdır.

Fotoğraf çekildiğinde sunucu şu verileri doğrulasın:

- Kamera konumu ve yönü
- Görüş konisi
- Oyuncular
- Varlık
- Kanıt
- Paranormal event
- Oda/alan kimliği
- Zaman damgası
- Görüş engeli
- Işık durumu

Fotoğraf için oyun içi render-to-texture veya gerçek framebuffer görüntüsü kullanılabilir. Kanıt değeri sunucu tarafından olay verileriyle doğrulanmalıdır.

Video sistemi sürekli ağır video kaydı yerine olay tabanlı replay kullanmalıdır.

Kaynak belgedeki replay kontrollerini koru:

- Oynat
- Duraklat
- Geri sar
- İleri sar
- Kare adımlama
- Yavaşlat
- Hızlandır
- Yakınlaştır
- Parlaklık
- Gece görüşü
- Termal görünüm
- Kanıt işaretleme

Replay verisi sıkıştırılmış zaman damgalı durum örnekleri ve olay günlüğü olarak saklanmalıdır.

---

# 14. NARRATOR-8 VAKA SİSTEMİ

Oyun dış yapay zekâ servisi olmadan tamamen oynanabilir olmalıdır.

## 14.1 Yerel üretici

Yerel prosedürel vaka üreticisi:

- Seed tabanlı deterministik üretim
- Kişiler
- Meslekler
- İlişkiler
- Ölüm
- Kayıp
- Yalan
- Sahte belge
- Gerçek kanıt
- Yanıltıcı kanıt
- Varlık kökeni
- Varlık amacı
- Ritüel
- Final
- Rapor soruları
- Harita socket eşleştirmeleri

üretmelidir.

Her kritik gerçek en az üç bağımsız kanıtla desteklenmelidir.

`CaseValidator` şunları doğrulamalıdır:

- Çözülebilirlik
- En az üç bağımsız kanıt
- Çelişki bulunmaması veya tasarlanmış çelişkinin işaretlenmesi
- Bütün referans kimliklerinin geçerli olması
- Kanıtların haritada erişilebilir olması
- Ritüelin uygulanabilir olması
- Fail ve success finallerinin tanımlanması
- Seed ile tekrar üretilebilirlik

## 14.2 İsteğe bağlı dış AI

Dış AI yalnızca vaka metnini ve atmosferik yazıları zenginleştirebilir.

Dış AI:

- Dünya koordinatı belirleyemez
- Para veya XP belirleyemez
- Oyuncu öldüremez
- Hasar veremez
- Eşya sahipliğini değiştiremez
- Varlığı gerçek zamanda kontrol edemez
- Sunucu kurallarını değiştiremez

API anahtarı istemciye konulmayacaktır. Backend üzerinden kullanılacaktır. Servis başarısız olursa yerel üretici devreye girer.

Claude API kullanımı zorunlu değildir. Sağlayıcı bağımsız bir `NarrativeProvider` arayüzü oluştur.

---

# 15. ÇOK OYUNCULU MİMARİ

Destek:

- Tek oyuncu
- 2 oyuncu
- 3 oyuncu
- 4 oyuncu

Önce yerel ağ ve localhost üzerinde çalışan sistem üret. Sonra internet/relay katmanını ekle.

Sunucu yetkili sistemler:

- Oyuncu bağlantısı ve oturum
- Oyuncu konumu doğrulaması
- Para
- XP
- Ekipman
- ItemInstanceID
- Varlık AI
- Hunt
- Kanıt
- Kapılar
- Ölüm
- Görev sonucu
- Eşya kurtarma
- Vaka seed’i
- Ritüel sonucu

Ağ katmanı:

- Oyun içi gerçek zamanlı durum için Panda3D datagram veya güvenli Python socket tabanlı protokol
- Lobi, kimlik doğrulama, metin sohbeti ve yönetim için FastAPI WebSocket/HTTP
- Mesaj tipleri için Pydantic şemaları
- Protokol sürümü
- Sequence number
- Timestamp
- Rate limit
- Boyut limiti
- Kimlik doğrulama
- Yetki doğrulama
- Hatalı pakete karşı güvenli kapatma
- Yeniden bağlanma
- Beş dakikalık oturum geri kazanma
- Host migration yalnızca güvenilir şekilde yapılabiliyorsa

İlk çok oyunculu kabul testi:

- Aynı bilgisayarda iki istemci
- Ayrı process
- Ayrı profil
- Bir kapının senkron açılması
- Bir ekipmanın yalnızca bir oyuncuda bulunması
- Varlığın tek sunucu örneği
- Hunt başlangıcının eş zamanlı görünmesi
- Bağlantı kopması ve tekrar bağlanma
- Görev sonucunun aynı olması

Dört oyunculu test mümkün olduğunda dört ayrı istemci process ile yapılmalıdır.

---

# 16. KARARGÂH, EĞİTİM, ARAÇLAR VE KARAVANLAR

Kaynak belgedeki Department Eight karargâhının bütün alanlarını 3D olarak oluştur.

İlk oynanabilir sürümde en az şu alanlar gerçek işlevli olmalıdır:

- Ana giriş
- Eğitim girişi
- Görev seçim merkezi
- Ekipman mağazası
- Ekipman odası
- Karavan garajı
- Araç garajı
- Karakter özelleştirme
- Fotoğraf/video arşivi
- Challenge terminali
- Mikrofon test alanı
- Basketbol alanı

Basketbol sosyal amaçlıdır; para ve XP vermez.

Eğitim, kaynak belgedeki `Department Eight Field Certification` sırasını korumalıdır. Oyuncu eğitimi tamamlamadan çevrim içi normal göreve katılamamalıdır.

Kaynak belgedeki tam olarak 8 araştırma karavanı ve 8 kişisel araç korunacaktır.

Her araç ve karavan için:

- Kaynak `.blend`
- Oyun `.glb`
- Dış model
- İç mekân gerekiyorsa iç model
- Collision
- Kapı animasyonu
- Far ve ışık animasyonu
- Renk varyasyonları
- İkon
- Veri kataloğu
- Oyun içi fiyat ve seviye
- Ağ durumu
- Test

Karavan her görevde zorunludur. Karavan olmadan görev başlatılamaz. Karavan Hunt sırasında güvenli alan değildir.

Konvoy sahnesini gerçek zamanlı 3D veya önceden hazırlanmış kamera sinematiği olarak oluştur. Seçilen karavan, kişisel araçlar, renkler, hava ve harita görünmelidir.

---

# 17. SES TASARIMI

3D uzamsal ses kullan.

Her harita için:

- Ortam sesi
- Rüzgâr
- Yağmur
- Su damlası
- Bina gıcırtısı
- Elektrik
- Kapı
- Zemin türüne göre adım
- Telsiz paraziti
- Telefon
- Varlık
- Hunt
- Gerilim katmanı
- Sessizlik/ducking bölgeleri

oluştur.

Sesleri başka oyunlardan kopyalama.

İnternetten kullanılan her ses için:

- Kaynak bağlantısı
- Lisans
- Yazar
- Değişiklik
- Kullanım koşulu

`docs/ASSET_LICENSES.md` içinde kaydedilmelidir.

İnternet yoksa telifsiz ses varmış gibi davranma. Python ile sentezlenebilen basit efektleri üret; diğerlerini `BLOCKED_BY_ENVIRONMENT` veya açık placeholder olarak işaretle.

---

# 18. ASSET ÜRETİM KURALLARI

## 18.1 Blender otomasyonu

Her ana asset kategorisi için tekrar çalıştırılabilir Blender Python betikleri oluştur:

- Mimari parça üretici
- Kapı/pencere üretici
- Mobilya üretici
- Ekipman üretici
- Araç temel gövde üretici
- Karavan iç/dış üretici
- İnsan karakter temel modeli
- Paranormal varlık temel üretici
- UV ve materyal kurucu
- Collision mesh üretici
- LOD üretici
- GLB exporter
- Asset thumbnail renderer

Betikler aynı seed ile aynı sonucu üretebilmelidir.

Elle düzenleme yapılırsa kaynak `.blend` dosyasını sakla ve değişikliğin nedenini `docs/DECISIONS.md` dosyasına yaz.

## 18.2 Asset doğrulaması

Her `.glb` için otomatik kontrol:

- Dosya açılıyor mu
- Mesh sayısı
- Poligon sayısı
- Materyal sayısı
- Eksik texture
- NaN transform
- Aşırı ölçek
- Negatif scale
- Collision var mı
- Gerekli socket var mı
- Animasyon isimleri doğru mu
- Boyut sınırı
- Lisans kaydı var mı

Bozuk asset build’e girmemelidir.

---

# 19. UI, DİL VE ERİŞİLEBİLİRLİK

Arayüz 1920×1080 temel çözünürlükte tasarlanacak, farklı oranlarda bozulmayacaktır.

İlk tam diller:

- Türkçe
- İngilizce

Kaynak belgedeki diğer diller veri yapısında desteklenmeli, ancak eksik çeviri tamamlanmış gibi gösterilmemelidir.

Ayrı seçenekler:

- Arayüz dili
- Altyazı dili
- Vaka dili
- Telefon dili

Erişilebilirlik:

- Altyazı boyutu
- Altyazı arka planı
- Renk körlüğü profilleri
- Kamera sarsıntısı
- Head bob
- Glitch azaltma
- Parlaklık
- Karanlık seviyesi
- Ses yönü göstergesi
- Mikrofon kapatma
- Yazılı sohbet
- Kontrolcü
- Tuş değiştirme
- Fare hassasiyeti
- FOV
- Fotosensitif efekt azaltma

---

# 20. KAYIT VE GÜVENLİK

Yerel sürüm:

- SQLite
- Kullanıcı profili
- Save sürümü
- Atomik kayıt
- Yedek save
- Bozuk save kurtarma
- Basit bütünlük kontrolü

Sunucu sürümü:

- PostgreSQL seçeneği
- Migrasyon
- Kullanıcı oturumu
- Sunucu taraflı ekonomi
- Sunucu taraflı envanter
- Audit log
- Rate limit
- Girdi doğrulama
- SQL injection koruması
- Path traversal koruması
- Deserialization güvenliği

Parolaları düz metin tutma. API anahtarlarını istemciye veya repoya yazma.

---

# 21. ÜRETİM AŞAMALARI

Bütün oyunu tek seferde bitmiş göstermeye çalışma. Aşağıdaki aşamalarda gerçek çalışan dosyalar üret.

## Aşama 0 — Ortam ve araç zinciri

- Ortam denetimi
- Git deposu
- Python sanal ortamı
- Panda3D pencere testi
- Bullet testi
- Blender headless testi
- GLB üretim ve yükleme testi
- FastAPI localhost testi
- Test altyapısı
- Durum dosyaları

Kabul:
- Basit Panda3D penceresi açılır
- Blender betiği bir test odası üretir
- Test odası Panda3D içinde yüklenir
- Testler gerçekten çalışır

## Aşama 1 — 3D temel prototip

- FirstPersonController
- Input
- Kamera
- Bullet collision
- Interaction raycast
- Kapı
- Çekmece
- Eşya alma/bırakma
- Basit envanter
- Ses
- Ayarlar
- Save
- Test odası

Kabul:
- Oyuncu test odasında yürür
- Kapıyı açar
- Eşyayı alır/bırakır
- Kaydedip yükler
- Kritik hata yoktur

## Aşama 2 — Eğitim vertical slice

- Karargâhın temel bölümü
- Eğitim alanı
- Karakter seçimi
- Başlangıç karavanı
- Başlangıç aracı
- Temel 7 ekipman
- Telefon
- Metin sohbeti prototipi
- Kontrollü Hunt
- Saklanma
- Return Scanner
- Görev raporu

Kabul:
- Yeni profil eğitimi baştan sona tamamlar
- Eğitim ödülleri verilir
- Çevrim içi görev kilidi açılır

## Aşama 3 — İlk tam vaka

- Hollow Creek Residence
- Bir tam 3D varlık
- Yerel vaka üretici
- Kanıt sistemi
- Fotoğraf
- Basit replay
- Ritüel
- Final
- Recovery Phase
- Görev sonucu

Kabul:
- Tek oyunculu görev baştan sona tamamlanır
- En az üç kanıtla çözülebilir vaka oluşur
- Doğru ve yanlış ritüel sonuçları çalışır
- Ekipman kurtarma doğru hesaplanır

## Aşama 4 — Çok oyunculu vertical slice

- Lobi
- İki oyuncu
- Oyuncu replikasyonu
- Kapılar
- Ekipman
- Varlık
- Hunt
- Metin sohbeti
- Yeniden bağlanma
- Dört oyuncuya genişleme

Kabul:
- İki gerçek istemci aynı görevi tamamlar
- Sonuçlar sunucuda aynıdır
- Ekipman çoğaltılamaz
- Varlık tek sunucu örneğidir

## Aşama 5 — İçerik üretim hattı

- 8 harita
- 20 varlık
- 32 ekipman
- 8 karavan
- 8 araç
- Karargâh
- Eğitim
- Asset üretim ve doğrulama araçları

Her içerik gerçek katalog, model ve test ile eklenir.

## Aşama 6 — İlerleme ve sosyal sistemler

- Ekonomi
- Seviye
- Prestij
- Mağaza
- Kozmetik
- Araç modifikasyonu
- Challenge Mode
- Rozetler
- Arşiv
- Basketbol

## Aşama 7 — Ses, dil, optimizasyon ve build

- Türkçe/İngilizce
- Ses tamamlama
- LOD
- Occlusion
- Asset streaming
- Ağ optimizasyonu
- Save migrasyonları
- Windows build
- Temiz bilgisayar testi
- Lisans kontrolü
- ZIP teslimi

Her aşamanın sonunda:

1. Testleri çalıştır
2. Sonuçları raporla
3. Hataları düzelt
4. `docs/STATUS.md` güncelle
5. Placeholder raporunu güncelle
6. Bir sonraki eksik aşamaya geç

Kullanıcı açıkça dur demedikçe sadece rapor verip bırakma; erişimin izin verdiği ölçüde üretime devam et.

---

# 22. TEST ZORUNLULUKLARI

## Birim testleri

- Vaka seed üretimi
- Vaka doğrulama
- Üç bağımsız kanıt kuralı
- ItemInstanceID
- Eşya sahipliği
- Return Scanner
- Ekonomi
- XP
- Seviye
- Save migrasyonu
- Karavan zorunluluğu
- Recovery süresi
- Ağ mesajı şemaları

## Entegrasyon testleri

- Blender → GLB → Panda3D
- Harita manifesti
- Kapı collision
- Ekipman alma/bırakma
- Fotoğraf kanıtı
- Ritüel
- Varlık state machine
- Hunt
- Saklanma
- Telefon
- Backend
- SQLite

## Çok oyunculu testler

- 2 istemci
- 4 istemci
- Hareket
- Kapı
- Ekipman
- ItemInstanceID
- Varlık
- Hunt
- Mesajlaşma
- Bağlantı kopması
- Yeniden bağlanma
- Görev sonucu
- Return Scanner
- Hileli paket reddi

## Asset testleri

- 8 harita manifesti
- 20 varlık kaydı
- 32 ekipman kaydı
- 8 karavan kaydı
- 8 araç kaydı
- Eksik GLB
- Eksik texture
- Eksik materyal
- Eksik animasyon
- Bozuk collision
- Geçersiz socket
- Aşırı asset boyutu
- Placeholder sayısı

## Build testleri

- Development build
- Release build
- Windows 64-bit
- İlk açılış
- Yeni profil
- Save/load
- Eğitim
- Tek oyunculu görev
- İki oyunculu görev
- Temiz klasörden çalışma
- Eksik bağımlılık olmaması

Test edilmemiş bir maddeyi “başarılı” yazma.

---

# 23. PERFORMANS HEDEFLERİ

Varsayılan hedef:

- 1920×1080
- Orta ayarlar
- Makul orta seviye Windows bilgisayarda 60 FPS hedefi
- Düşük ayarlarda 30 FPS altına düşmemeyi hedefle

Ölçüm yapılmadan performans iddiasında bulunma.

Uygula:

- LOD
- Frustum culling
- Occlusion bölgeleri
- Işık sayısı sınırı
- Gölge kalite seviyeleri
- Texture çözünürlük seviyeleri
- Asset streaming
- Nesne havuzu
- Ağ tick oranı
- Snapshot interpolation
- Ses kaynağı havuzu
- Parçacık kalite seçenekleri

---

# 24. STEAM VE DIŞ SERVİSLER

Steam entegrasyonunu soyut bir servis arayüzünün arkasına koy:

- `PlatformService`
- `OfflinePlatformService`
- `SteamPlatformService`

Steamworks SDK, uygulama kimliği veya gerekli erişim yoksa:

- Offline/LAN sistemini çalışır halde tut
- Steam adapter iskeletini oluştur
- Eksikliği açıkça raporla
- Steam arkadaş davetini tamamlanmış gösterme
- Sahte AppID kullanarak final entegrasyon iddiasında bulunma

Oyun, Steam olmadan yerel geliştirme ve tek oyunculu modda açılmalıdır.

---

# 25. NİHAİ KABUL KRİTERLERİ

Tam oyun ancak aşağıdakiler gerçek olarak doğrulandığında tamamlandı sayılır:

- Python projesi temiz kurulumla kurulabiliyor
- Panda3D oyunu açılıyor
- Windows 64-bit build çalışıyor
- Kritik Python exception yok
- Eksik model referansı yok
- Eksik texture referansı yok
- Eksik materyal referansı yok
- Bozuk GLB yok
- Kaynak Blender dosyaları mevcut
- Eğitim tamamlanabiliyor
- Karargâh çalışıyor
- Tam olarak 8 ana harita oynanabilir
- Tam olarak 20 varlık mevcut
- Tam olarak 32 ekipman gerçek işleve sahip
- Tam olarak 8 karavan mevcut
- Tam olarak 8 kişisel araç mevcut
- Telefon mesajlaşması çalışıyor
- Hunt çalışıyor
- Saklanma çalışıyor
- Fotoğraf kanıtı çalışıyor
- Video replay çalışıyor
- Return Scanner çalışıyor
- Haritada kalan eşya kayboluyor
- Scanner veya karavana dönen eşya sahibine dönüyor
- Yerel vaka üretici dış API olmadan çalışıyor
- Tek oyunculu görev tamamlanıyor
- En az iki oyunculu görev gerçek istemcilerle tamamlanıyor
- Dört oyunculu test durumu dürüstçe raporlanıyor
- Türkçe ve İngilizce temel arayüz tamam
- Kritik placeholder yok
- Lisanssız/korsan asset yok
- Test raporu mevcut
- Build raporu mevcut
- Bilinen sorunlar mevcut
- ZIP teslimi mevcut

Nihai klasör:

```text
CaseEightUnwritten3D/
```

Nihai Windows çıktısı hedefi:

```text
builds/windows/CaseEightUnwritten3D.exe
```

Nihai teslim ZIP’i:

```text
CaseEightUnwritten3D_Windows_Source_and_Build.zip
```

---

# 26. KESİN YASAKLAR

- Oyunu 2D yapma
- Unity kullanma
- Unreal Engine kullanma
- Oyunu başka bir oyunun kopyası yapma
- Başka oyundan çıkarılmış model, ses, texture veya kod kullanma
- Korsan içerik kullanma
- Gerçek araç markası veya logosu kullanma
- Kaynak belgedeki kesin sayıları değiştirme
- 8’den az veya fazla ana harita oluşturma
- 20’den az veya fazla ana varlık görünümü oluşturma
- 8’den az veya fazla araştırma karavanı oluşturma
- 8’den az veya fazla kişisel araç oluşturma
- 32’den az veya fazla ana ekipman oluşturma
- Karavan olmadan görev başlatma
- Karavanı güvenli alan yapma
- Haritada bırakılan ekipmanı otomatik geri verme
- Ekipman sahipliğini istemciye bırakma
- Varlığı gerçek zamanlı dil modeliyle yürütme
- API anahtarını istemciye koyma
- Bütün kodu tek Python dosyasına yazma
- Bütün haritaları tek sahneye doldurma
- Sahte test sonucu yazma
- Çalışmayan özelliği tamamlanmış gösterme
- Placeholder asseti final asset diye gösterme
- Yalnızca belge üretip gerçek projeyi oluşturmadan durma
- Kullanıcıdan kodlama, modelleme veya sahne düzenleme isteme

---

# 27. ŞİMDİ BAŞLATILACAK İŞ

Bu promptu aldıktan sonra şu sırayla hareket et:

1. Ekli kaynak tasarım dosyasını oku.
2. Tasarımın tam olarak alındığını doğrulamak için sayı ve ana sistem kontrolü yap.
3. Gerçek ortam denetimini terminal komutlarıyla çalıştır.
4. `reports/ENVIRONMENT_AUDIT.md` oluştur.
5. Proje klasörünü ve Git deposunu oluştur.
6. `CLAUDE.md` ve kalıcı durum belgelerini oluştur.
7. Python sanal ortamını ve bağımlılık yönetimini kur.
8. Panda3D smoke testini çalıştır.
9. Blender Python ile basit bir 3D test odası üret.
10. Test odasını Panda3D içinde yükle.
11. Bullet collision ve birinci şahıs hareket prototipini oluştur.
12. Otomatik testleri çalıştır.
13. Hataları düzelt.
14. Sonuçları dosyalara kaydet.
15. Ortam izin verdiği sürece Aşama 1’e devam et.

İlk yanıtında uzun bir tasarım özeti yazma. Önce gerçek denetim sonuçlarını, oluşturduğun dosyaları, çalıştırdığın testleri ve varsa engelleri bildir.

Sadece “bu proje çok büyük” diyerek görevi bırakma. Tam oyunun tek oturumda bitmeyeceğini dürüstçe söyleyebilirsin; ancak erişimin olan araçlarla gerçek üretime hemen başla ve her oturumda çalışan projeyi ilerlet.

Ana hedef:

Kullanıcının Python kaynak kodlarıyla geliştirilmiş, Panda3D üzerinde çalışan, Blender Python araç zinciriyle gerçek 3D içeriği üretilmiş, tek oyunculu ve 1–4 kişilik çevrim içi oynanışı hedefleyen, Windows için paketlenebilen CASE EIGHT: UNWRITTEN oyun projesini gerçek dosyalar ve testlerle oluşturmak.
