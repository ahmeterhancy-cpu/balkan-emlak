# Balkan Emlak ve Yönetim — Durum

Son güncelleme: 2026-09-28 · **77 test** (33 vitrin + 44 panel) yeşil
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
| Sonraki tur | Site ve apartman yönetimi paneli: siteler/bloklar/bağımsız bölümler, aidat-borçlandırma, tahsilat, gider, kasa/banka, iş emri, personel, duyuru (şartname 8). Roller (site yöneticisi, muhasebe, saha) şimdiden tanımlı, izinleri yok. |
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
- Konum ağacı Edirne varsayımıyla (Merkez, Keşan, Uzunköprü, Havsa) — Emlak → Konumlar'dan değişir.
- Referans ve müşteri görüşü yok → bölümler ve menü öğesi gizli.

## Demo veri

`DemoPortfoySeeder` / `DemoTalepSeeder` yalnız canlı dışında çalışır. 14 ilan (12 yayında, 1 taslak, 1 incelemede,
1 satıldı, 1 rezerve), fiyatlar temsilidir. Fotoğraflar Unsplash (`database/seeders/demo-gorseller/KAYNAK.md`) —
**gerçek portföy değildir**. `public/img/varsayilan-*.jpg` sayfa başlığı görselleri de temsilidir; panelden değiştirilir.

## Bilinen eksikler / sonraki adımlar

1. Site ve apartman yönetimi modülü (sonraki tur).
2. E-posta: `MAIL_*` boş; başvuru bildirimi ancak Site Ayarları → bildirim e-postası + SMTP girilince gider.
3. Yönetici hesaplarında çok faktörlü doğrulama (şartname 15) henüz yok — Filament MFA eklenmeli.
4. Canlı kurulum paketi (cPanel: `public/build` depoya, `vendor` no-dev, InnoDB, `filament:assets`) hazırlanmadı.
5. Yedekleme ve geri yükleme denemesi yapılmadı.
6. Dosya yüklemede zararlı dosya taraması yok (tür/boyut kontrolü var).
