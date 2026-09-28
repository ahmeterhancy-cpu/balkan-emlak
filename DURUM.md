# Balkan Emlak ve Yönetim — Durum

Son güncelleme: 2026-09-28 · **152 test** yeşil
Şartname: `balkan-emlak-ve-yonetim-proje.md` (kullanıcıdan). Canlıya çıkılmadı, uzak depo yok.

## Yerel çalıştırma

```bash
composer install && npm install && npm run build
php artisan migrate:fresh --seed     # demo verisiyle
php artisan serve --port 8144        # launch.json adı: balkan
php artisan test
```

Panel: `/admin` — yerel hesaplar ve parola `database/seeders/KullaniciSeeder.php` başında.
Canlıda yönetici: `php artisan balkan:yonetici eposta@alan.adi` (rastgele parola bir kez yazılır).

## Alınan kararlar

| Konu | Karar |
|---|---|
| Teknik yol | Laravel 13 + Filament 5. RealHomes Modern 5 (demo05) **tasarım referansı** olarak yeniden yazıldı; WordPress/tema lisansı kullanılmadı. Tek veri kaynağı bu uygulama. |
| Bu turun kapsamı | Kurumsal site + emlak modülü + başvurular + web içerikleri + kullanıcı/rol + işlem geçmişi. |
| 2. tur | Site ve apartman yönetimi (şartname 8) tamamlandı — aşağıda. |
| Renk / yazı | Şartnamedeki önerilen palet (lacivert #142D3E, yeşil #739B16) + Rubik (referansın yazısı). Kesin marka kimliği gelince yalnız `resources/css/temel.css` jetonları ve panel rampası değişir. Beyaz yazılı düğmelerde erişilebilirlik için koyu yeşil #5A7A10. |
| Saat dilimi | Europe/Istanbul (şartname: Türkiye saat dilimi). |

## Ne çalışıyor

**Vitrin**: ana sayfa (iki eşit giriş: emlak + site yönetimi ilk ekranda, sekmeli hızlı arama, son ilanlar,
portföy çağrısı, öne çıkanlar kaydırıcısı, kategoriler, 8 hizmet, çalışma ilkeleri, kısa talep formu),
portföy listesi (paylaşılabilir süzgeçler: işlem, tür/alt tür, il→ilçe→mahalle, fiyat, m², oda — oda arsa/ticaride
gizli ve yok sayılır; 5 sıralama; boş sonuç açıklaması), ilan detay (galeri, özellik tablosu, olanaklar, kat planı,
yaklaşık harita, danışman kartı, bilgi/randevu formu, benzer ilanlar; satıldı/kiralandı ise form kapanır), site yönetimi
+ 8 hizmet detayı, yönetim teklifi formu, portföy başvurusu, hakkımızda, referanslar, bilgi merkezi, iletişim, yasal
sayfalar, 404, sitemap.xml, robots.txt (canlı dışında kapalı), JSON-LD.

**Panel** (Filament): gösterge (emlak sayıları, yaklaşan randevular, onay bekleyen ilanlar), Portföyler (yayın akışı:
taslak → incelemeye gönder → yayımla; eksik kontrolü; önizleme; medya, özel belgeler, fiyat geçmişi), Mülk sahipleri ve
müşteriler (mükerrer uyarısı, otomatik birleştirme yok), Randevular, Komisyon takibi (para birimine göre ayrı toplam),
Konumlar, Başvurular ve talepler (türe göre sekmeler, not, bana ata, kişiye bağla, randevu oluştur, CSV), Sayfalar,
Hizmetler, Bilgi merkezi, Referanslar (izinsiz yayımlanamaz), SSS, Kullanıcılar ve yetkiler, İşlem geçmişi, Site ayarları.

**Güvenlik**: izin haritası `app/Support/Yetki.php`, kayıt kapsamı `app/Support/Kapsam.php`, politikalar
`app/Policies`. Danışman yalnız kendi portföyünü/atanan talebini görür; başkasının kaydına doğrudan adresle de erişemez.
Özel belgeler web kökü dışında, indirme politika denetimli ve kayıtlı. Vitrin mülk sahibi, açık adres, iç not, komisyon
ve tam koordinatı sızdırmaz (testli). Panel HTML'i vitrine temizlenerek basılır. Formlar: bal küpü + şifreli form
zamanı + hız sınırı (6/dk, 40/gün).

## Site ve apartman yönetimi (2. tur)

**Muhasebe çekirdeği** `app/Services/SiteFinans` — panel yalnız bu servisleri çağırır:
- Borçlandırma: sabit / m² / arsa payı / özel dağıtım; önizleme; kesinleştirme **tekrar çalıştırılamaz**
  (kilit + iki benzersiz anahtar). m²/arsa payında kuruş "en büyük kalan" ile toplama tam eşit.
- Tahsilat: en eski vadeli borçtan kapanır (elle öncelik verilebilir), dağıtım önizlemesi, fazla ödeme **avans**
  (gelir sayılmaz, yeni borçta otomatik mahsup). Dekontlu tahsilat doğrulanana kadar kasaya/borca işlenmez.
- Mali kayıt silinmez / tutarı değişmez (`DegismezMaliKayit`); iade ve borç düzeltmesi **gerekçeli karşı kayıt**.
- Gider: taslak → onay bekliyor → onaylandı/reddedildi; ödeme ayrı (kısmi, geri alma). Site onay limitini aşanı
  yalnız şirket yöneticisi onaylar.
- Kasa/banka defteri, açılış bakiyesi (dayanaklı), transfer (gelir/gider sayılmaz), mutabakat, dönem kapama.
- Borç sorumlusu tarihli malik/kiracı ilişkisinden; taşınmada geçmiş korunur.
- Para birimleri hiçbir toplamda karışmaz; şirket komisyonları site raporlarında yok.

**Yetki**: rol izni + `site_kullanici` ataması. Site yöneticisi / muhasebe yalnız atandığı siteyi görür (başka sitenin
kaydı adresle 404); saha personeli yalnız kendine atanan iş emri ve görevi görür, tamamlar, kapatamaz.
Tarayıcıda doğrulandı (siteyonetici@, saha@).

**Panel**: Site Yönetimi (siteler, bloklar, bölümler, malik/kiracı, ekstre), Site Finansı (borçlandırma,
borçlar, tahsilat, giderler, tedarikçiler, kasa/banka, transferler), Site Operasyonu (iş emirleri, bakım planları,
varlıklar, görevler, duyurular, sürümlü belgeler), Site Raporları (dönem özeti, bölüm bakiyeleri / vadesi
geçmiş, ekstre, gider özeti + tedarikçi borçları, kasa/banka dökümü, operasyon) — ekran, CSV ve yazdır/PDF
aynı üreticiden. Gösterge panelinde site + dönem seçici.

**Demo**: `DemoSiteSeeder` — Çamlık Sitesi (24 daire) ve Yıldız Apartmanı (8 daire + dükkân), 3 aylık aidat,
kısmi/fazla ödemeler, doğrulanmayı bekleyen dekont, limit üstü onay bekleyen gider, Ağustos kapalı.
Hesaplar: siteyonetici@, muhasebe@, saha@ (`@balkanemlak.test`).

**Bilinen sınırlar**: ayrı "Malikler ve Kiracılar" listesi yok (bölüm sayfasında); tahsilatta öncelikli borçlar
kendi aralarında vade sırasıyla kapanır; üst çubukta kalıcı "aktif site" seçici yok (gösterge panelinde var);
gecikme bedeli kapalı (şartname 8.2 — ayrıca kapsamlandırılacak); sakin portalı, çevrimiçi ödeme, banka
entegrasyonu faz 3.

## Hız ve erişilebilirlik ölçümü (şartname 15) — 2026-09-29

Lighthouse 12, mobil profil (yavaş 4G benzetimi), yerel `artisan serve` (tek iş parçacığı, hata ayıklama açık —
canlıdan kötümser; sıkıştırma/önbellek başlıkları canlıda `public/.htaccess` ile gelir).

| Sayfa | Önce: hız / erişilebilirlik / LCP / CLS | Sonra |
|---|---|---|
| Ana sayfa | 79 / 89 / 5,5 sn / 0 | 97 / 100 / 2,5 sn / 0 |
| Portföy listesi | 65 / 94 / 4,3 sn / **0,447** | 98 / 100 / 2,3 sn / 0 |
| İlan detayı | 64 / 93 / **8,0 sn** / 0 | 99 / 100 / 2,0 sn / 0 |
| Site yönetimi | 88 / – / 3,9 sn / 0 | 99 / 100 / 1,8 sn / 0 |
| Teklif, portföy başvurusu, iletişim, hakkımızda, bilgi merkezi, yazı, hizmet, KVKK | – | 98–100 / 100 / 1,4–2,3 sn / 0 |

Yapılanlar: duyarlı görseller (400/800/1600 + WebP, `<x-resim>`; yüklemede üretilir), kahraman ve sayfa başlığı
görselleri CSS arka planı yerine öncelikli `<img>`, ilan galerisinde yalnız gösterilen fotoğraf iner (önce tüm galeri
iniyordu), süzgeç kutusunun sonradan kapanmasından doğan düzen kayması giderildi, ikincil metin rengi #626d74
(kontrast 3,77 → 5,3), metin içi bağlantılar altı çizili, kaydırıcı noktaları 24 px dokunma hedefi, başlık sırası.
SEO puanındaki tek bulgu yerelde bilerek konan `noindex`. INP (etkileşim) laboratuvarda ölçülemez; sayfalarda uzun
görev yok (TBT 0 ms).

## Teslim belgeleri (şartname 19)

- [docs/KULLANICI-KILAVUZU.md](docs/KULLANICI-KILAVUZU.md) — rol rehberi ve şartname 12'deki iş akışları adım adım
- [docs/YETKI-TABLOSU.md](docs/YETKI-TABLOSU.md) ve [docs/VERITABANI.md](docs/VERITABANI.md) — **koddan üretilir**
  (`php artisan balkan:belgeler`); izin ya da tablo değişip belge yenilenmezse `BelgelerTest` kırmızı yanar
- `DEPLOY.md` — kurulum, yedek, kontrol listesi

## Şartname 10 ekleri
Eski adres yönlendirmeleri (Web İçerikleri → Yönlendirmeler; yalnız 404 olacak isteklerde, başlık değişince otomatik
301, zincir/döngü yok), Sayfa/Yazı/Hizmet önizlemesi, görsellerde kullanım hakkı ve kaynak notu.

## Yedekleme (şartname 15, 17)

`balkan:yedek` (her gece 03.15), `balkan:yedek-dogrula` (her pazar), `balkan:geri-yukle`. Tek AES-256 zip:
veritabanı (tablo tablo JSONL) + özel belgeler + medya + manifest (satır sayıları, mali kontrol toplamları,
dosya sha1). Doğrulama yedeği boş geçici bir SQLite veritabanına göçlerle kurup yükler ve karşılaştırır — canlıya
dokunmaz. Geri yükleme önce doğrular, güvenlik yedeği alır, tek transaction'da yükler. Yerelde 10,7 MB'lık
gerçek yedek alınıp doğrulandı. Sunucu dışına kopyalama işletmenin yedek düzenine kalır (DEPLOY.md).

## İki aşamalı doğrulama

**Kullanıcı kararıyla yapılmadı (2026-09-28).** Şartname 15 yönetici hesaplarında çok faktörlü doğrulama
öneriyor; eklenip kaldırıldı. İleride istenirse Filament'in AppAuthentication sağlayıcısıyla (git geçmişinde
"Yönetici rollerinde iki aşamalı doğrulama" commit'i) geri alınabilir.

## Bilerek boş bırakılanlar (yayından önce teyit — şartname 2 ve 18)

Boş ayar sitede **hiç çizilmez**; yer tutucu metin yayımlanmaz.

- Ofis adresi, çalışma saatleri, e-posta, harita, sosyal medya → **Site Ayarları** boş.
- "7/24" ifadesi hiçbir yerde yok; acil iletişim alanı boş.
- WhatsApp **kapalı** (kullanılabilirliği teyit edilmedi); Site Ayarları → iletişim kişilerinde açılır.
- Emrullah Cam, Abdurrahman Seçen, Muhammed Ünal: ad/telefon tanıtım görselinden. Görevleri boş, ekip bölümünde
  gösterilmiyorlar (`ekipte_goster` kapalı). Demo ilanlarda danışman olarak görünüyorlar.
- Logo yok → metin logo ("BALKAN / Emlak ve Yönetim"). Favicon geçici.
- "Hukuki yönetim" metni temkinli taslak; işletmenin gerçek kapsamıyla yeniden yazılmalı.
- KVKK / gizlilik / çerez metinleri taslak, `[TEYİT BEKLİYOR]` işaretli, sitede uyarı şeridiyle görünüyor.
- Hizmet bölgesi **Edirne** (işletme teyit etti): konum ağacında ilin 9 ilçesi var; mahalleler yalnız örnek,
  eksikleri Emlak → Konumlar'dan eklenir.
- Referans ve müşteri görüşü yok → bölümler ve menü öğesi gizli.

## Demo veri

`DemoPortfoySeeder` / `DemoTalepSeeder` yalnız canlı dışında çalışır. 14 ilan (12 yayında, 1 taslak, 1 incelemede,
1 satıldı, 1 rezerve), fiyatlar temsilidir. Fotoğraflar Unsplash (`database/seeders/demo-gorseller/KAYNAK.md`) —
**gerçek portföy değildir**. `public/img/varsayilan-*.jpg` sayfa başlığı görselleri de temsilidir; panelden değiştirilir.

## Bilinen eksikler / sonraki adımlar

2. E-posta: `MAIL_*` boş; başvuru bildirimi ancak Site Ayarları → bildirim e-postası + SMTP girilince gider.
4. Canlı kurulum paketi (cPanel: `public/build` depoya, `vendor` no-dev, InnoDB, `filament:assets`) hazırlanmadı.
6. Dosya yüklemede zararlı dosya taraması yok (tür/boyut kontrolü var).
