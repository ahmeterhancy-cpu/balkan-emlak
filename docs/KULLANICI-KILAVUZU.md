# Balkan Emlak ve Yönetim — Panel Kullanıcı Kılavuzu

Panel adresi: `https://ALAN-ADINIZ/admin`. E-posta ve parolanızla girersiniz. Gördüğünüz menüler rolünüze göre
değişir; görmediğiniz bir ekran için yetkiniz yoktur (ayrıntı: [Yetki tablosu](YETKI-TABLOSU.md)).

**Temel kurallar**
- Para tutarlarını nokta ve virgülle yazabilirsiniz: `1.250.000` ya da `1250000,50`.
- **Mali kayıtlar silinmez ve tutarları değiştirilemez.** Yanlış bir tahsilat ya da borç "İade" veya "Düzelt / iptal et"
  ile gerekçe yazılarak düzeltilir. Asıl kayıt ve düzeltme birlikte görünür; hesap ekstresi tutarlı kalır.
- Her işlem **İşlem Geçmişi**'ne kim, ne zaman, önceki/sonraki değer olarak yazılır.
- Hata mesajları kırmızı bildirim olarak çıkar ve ne yapılması gerektiğini söyler (ör. "Bu dönem kapalı").

---

## 1. Rollere göre hızlı rehber

| Rol | Ne yapar | Başlangıç ekranı |
|---|---|---|
| Sistem yöneticisi | Her şey; kullanıcılar ve site ayarları | Gösterge Paneli |
| Şirket yöneticisi | Emlak ve site yönetiminin tamamı, ilan yayını, limit üstü gider onayı, dönem düzeltme | Gösterge Paneli |
| Emlak danışmanı | Kendi portföyleri, kendisine atanan talepler, randevular | Portföyler ve İlanlar |
| İçerik editörü | Sayfalar, hizmetler, bilgi merkezi, referanslar, SSS, yönlendirmeler | Web İçerikleri |
| Site yöneticisi | Atandığı sitelerin yapısı, operasyonu, giderleri (limit içi onay), raporları | Gösterge Paneli |
| Muhasebe sorumlusu | Atandığı sitelerde borçlandırma, tahsilat, gider ödemesi, kasa/banka, dönem kapama | Tahsilatlar |
| Saha personeli | Kendisine atanan iş emirleri ve görevler | Gösterge Paneli ("Açık iş emirlerim") |

---

## 2. Emlak

### 2.1 Yeni portföyün yayımlanması (şartname 12)
1. **Emlak Yönetimi → Portföyler ve İlanlar → Yeni portföy.**
2. Başlık, işlem (satılık/kiralık), kategori ve alt türü seçin. Kategoriye göre alanlar değişir: arsada oda sayısı
   yoktur, ada/parsel vardır.
3. Konum: il → ilçe → mahalle. **Açık adres ve harita noktası sitede gösterilmez**; "Açık adresi göster" ya da
   "Harita: tam nokta" yalnız mülk sahibi izin verdiyse seçilir. Varsayılan "yaklaşık bölge" gerçek noktayı
   ~500 m'lik bir alana yuvarlar.
4. Fiyat, para birimi, metrekare ve özellikleri girin. Mülk sahibini seçin (listede yoksa aynı pencereden ekleyin).
   İç notlar ve komisyon anlaşması hiçbir zaman sitede görünmez.
5. Kaydedin, sonra **Fotoğraflar ve planlar** sekmesinden görselleri yükleyin. İlk fotoğraf kapak olur;
   sürükleyerek sıralayın, "Kapak yap" ile değiştirin. Her görsele **alternatif metin** ve **kullanım hakkı**
   (kendi çekimimiz / mülk sahibinin izni / lisanslı) girin.
6. Tapu, yetki belgesi gibi dosyaları **Özel belgeler** sekmesine yükleyin. Bunlar sitede asla yayımlanmaz.
7. **Önizle** ile ilanı sitede nasıl görüneceğini görün.
8. Danışmansanız **İncelemeye gönder**. Yayın yetkisi olan kişi **Yayımla**'ya basar. Eksik varsa (fotoğraf,
   açıklama, metrekare...) sistem yayımlamaz ve eksikleri listeler.
9. Satış/kiralama olunca **Operasyon durumu**nu "Satıldı" / "Kiralandı" yapın. İlan sitede durumuyla kalır,
   talep formu kapanır. İstenirse **Yayından kaldır** ya da **Arşivle**.

> Başlığı değiştirirseniz ilanın adresi de değişir; eski adres otomatik olarak yenisine yönlendirilir
> (Web İçerikleri → Yönlendirmeler'de "Otomatik" olarak görünür). Paylaşılmış bağlantılar kırılmaz.

### 2.2 Başvurular ve talepler
- Web sitesindeki formlardan gelen her başvuru **Başvurular → Başvurular ve Talepler**'e "Yeni" durumunda düşer.
  Menüdeki sayı yeni talep sayısıdır. İlan sayfasından gelen talep o ilanın danışmanına otomatik atanır.
- Üstteki sekmeler türe göre ayırır: portföy başvurusu, yönetim teklifi, ilan bilgi/randevu, iletişim, müşteri arayışı.
- Talebi açın: **Bana ata**, **Kişi kaydına bağla / oluştur**, **Randevu oluştur**. Görüşme notlarını "Notlar"
  sekmesine yazın, **Sonraki takip** tarihi girin, durumu ilerletin (Yeni → İletişime geçildi → ... → Sonuçlandı / Kaybedildi).
- Aynı telefon ya da e-postayla kayıtlı kişi varsa sarı uyarı çıkar. Sistem kayıtları **otomatik birleştirmez**; siz karar verirsiniz.

### 2.3 Yönetim teklifi talebi (şartname 12)
1. Talep "Yönetim teklifi" sekmesinde görünür; formda yazılan site adı, blok ve bağımsız bölüm sayısı, ihtiyaçlar talebin ayrıntısındadır.
2. Sorumlu kişiye atayın, görüşme ve keşif notlarını ekleyin.
3. Verilen teklifin özetini (tutar, kapsam, tarih) talebe not olarak yazın, **Sonraki takip** tarihi girin.
   Talep notlarına dosya eklenmez; teklif belgesini site açıldıktan sonra **Site Belgeleri**'ne yükleyin.
4. Sonucu **Kabul / Ret / Beklemede** olarak işaretleyin. Kabul edilen için **Site Yönetimi → Siteler / Apartmanlar**'da siteyi açın (bölüm 3.1).

### 2.4 Randevular ve komisyon
- **Randevular**: danışman, müşteri, portföy, zaman, sonuç notu. Varsayılan liste yaklaşan randevulardır.
- **Komisyon Takibi**: beklenen ve gerçekleşen tutar, ödeme tarihi. Toplamlar para birimine göre ayrı gösterilir.
  Bu kayıtlar resmi muhasebe belgesi değildir ve site paralarıyla hiçbir raporda birleşmez.

---

## 3. Site ve apartman yönetimi

> Her ekranda **Site** süzgeci vardır; yalnız bir siteye atanmışsanız otomatik seçilir. Mali işlem pencerelerinin
> başlığında hangi sitede işlem yaptığınız yazar.

### 3.1 Bir sitenin yönetime alınması (şartname 12)
1. **Site Yönetimi → Siteler / Apartmanlar → Yeni**: ad, adres, yönetim başlangıcı, sorumlu, para birimi,
   varsayılan vade günü, **onay limiti** (bu tutarın üstündeki gideri yalnız şirket yöneticisi onaylar).
2. Sitenin sayfasında **Kullanıcılar** sekmesinden site yöneticisi, muhasebe sorumlusu ve saha personelini **Kullanıcı ata**
   ile ekleyin. Atanmayan kullanıcı bu siteyi göremez.
3. **Bloklar** sekmesinde blokları, **Bağımsız bölümler** sekmesinde bölümleri girin. Çok sayıda daire için
   **Toplu bölüm oluştur** (blok, başlangıç–bitiş no, tür, m²). Arsa payı ve m² dağıtım için gereklidir.
4. Her bölümün sayfasında malik ve kiracıları başlangıç tarihiyle ekleyin. Aidattan kiracı sorumluysa
   "Borç sorumlusu"nu işaretleyin (işaretli kimse yoksa malik sorumludur). Taşınmada eski kaydı silmeyin:
   **İlişkiyi sonlandır** ile bitiş tarihi girin, yeni kişiyi ekleyin. Geçmiş dönem borçları eski kişide kalır.
5. **Kasa / Banka**: hesapları açın, her birine **Açılış bakiyesi** girin (dayanağıyla: devir tutanağı, banka dökümü).
6. Önceki yönetimden devreden bölüm borçları için bölüm sayfasında **Borç ekle → tür: Açılış bakiyesi (devir)**.
7. İlk dönem borçlandırmasını yapın (3.2).

### 3.2 Aidat ve borçlandırma
1. **Site Finansı → Aidat ve Borçlandırma → Yeni**: site, dönem (`2026-10`), tür (aylık aidat, ek aidat, gider payı,
   demirbaş), vade, dağıtım yöntemi:
   - **Sabit**: bölüm başı tutar.
   - **Metrekareye göre** / **Arsa payına göre**: dağıtılacak toplam; sistem kuruşu kuruşuna toplamı tutturur.
   - **Onaylı özel dağıtım**: bölüm bölüm tutar.
   Aynı dönemde ikinci bir ek aidat için **etiket** yazın (ör. "cati").
2. Kaydedince **önizleme tablosu** açılır: bölüm, sorumlu kişi, tutar, uyarılar (ör. "malik kaydı yok"), toplam.
   Kontrol edin; gerekirse düzenleyin (yalnız taslakken).
3. **Kesinleştir**. Borçlar açılır. Aynı işi ikinci kez kesinleştirmek **mükerrer borç oluşturmaz**.
4. Bölümün önceden ödediği avans varsa yeni borca otomatik mahsup edilir.
5. Yanlış borç: **Borçlar** listesinde **Düzelt / iptal et** → yeni tutar (iptal için 0) ve gerekçe.
   Ödenen kısım yeni tutarı aşıyorsa fazlası avansa döner.

> Gecikme bedeli bu sürümde kapalıdır.

### 3.3 Tahsilat
1. **Site Finansı → Tahsilatlar → Yeni** (ya da bölüm sayfasında **Tahsilat al**): site → bölüm → hesap, tarih,
   tutar, yöntem.
2. Ekranda **dağıtım önizlemesi** görünür: ödeme **en eski vadeli borçtan** başlayarak hangi borca ne kadar gidecek,
   ne kadar **avans** kalacak. "Önce kapatılacak borçlar" ile belirli borçları öne alabilirsiniz.
   Örnek: 1.000 TL borca 400 TL ödeme → 600 TL kalan. 1.500 TL ödeme → borç kapanır, 500 TL avans olarak izlenir
   (gelir sayılmaz, sonraki borca mahsup edilir).
3. Dekont yüklediyseniz ve henüz bankadan teyit etmediyseniz "Dekont doğrulandı mı?"yı kapalı bırakın. Tahsilat
   kaydedilir ama **kasaya ve borca işlenmez**; banka ekstresiyle karşılaştırınca **Doğrula**'ya basın.
4. Hatalı tahsilat: **İade et** → tamamı (borç dağıtımları geri alınır) ya da yalnız avans kısmından tutar, tarih ve gerekçe.

### 3.4 Giderler
1. **Site Finansı → Giderler → Yeni**: site, tedarikçi, kategori, belge tarihi/no, dönem, vade, tutar, belge (fatura taraması).
2. **Onaya gönder** → yetkili **Onayla** ya da **Reddet** (gerekçeyle). Site onay limitini aşan gideri yalnız şirket
   yöneticisi onaylayabilir; site yöneticisine "Şirket yöneticisi onayı gerekir" yazar.
3. Onaylanan gider için **Öde**: hesap, tutar (kısmi olabilir), tarih. Durum "Kısmen ödendi" / "Ödendi" olur.
   Yanlış ödeme: Ödemeler sekmesinde **Geri al**. Onaylanmış giderin tutarı değiştirilemez.

### 3.5 Kasa / banka
- Bakiye her hesap için ayrı gösterilir. **Transfer**: kasadan bankaya para yatırma gibi; gelir veya gider sayılmaz.
- **Hareketler** sekmesinde banka ekstresiyle karşılaştırdığınız satırları seçip **Mutabık işaretle**.
- Sayım farkı gibi durumlar için **Düzeltme kaydı** (gerekçeyle).

### 3.6 Dönem kapama
- Sitenin sayfasında **Dönemi kapat** → `2026-09`. O aya kadar hiçbir mali kayıt girilemez/değiştirilemez.
- Zorunlu düzeltme gerekirse: şirket yöneticisi kayıt penceresinde **Kapalı dönem gerekçesi** yazarak işlem yapar
  ya da **Dönemi yeniden aç** (gerekçeyle). Her ikisi de işlem geçmişine yazılır.
- Önerilen sıra: ay sonunda tahsilat ve giderler girilir → banka mutabakatı → **Dönem özeti** raporu → dönem kapatılır.

### 3.7 Arıza, bakım ve görevler
- **Bakım, Arıza ve İş Emirleri → Yeni**: site, blok/bölüm, konu, fotoğraf, öncelik, atanan personel ya da tedarikçi,
  hedef tarih. Durumlar: Açık → Atandı → İşlemde → Dış hizmet bekleniyor → Tamamlandı → Kapatıldı.
- Saha personeli kendi iş emrini açar, **Tamamla** ile yapılan işi ve varsa fotoğrafı girer. **Kapat** ve **Yeniden aç**
  yöneticiye aittir.
- **Bakım Planları**: asansör, hidrofor, yangın ekipmanı gibi **Varlıklar** için periyot (gün). Sonraki bakımı yaklaşan
  ve geçen planlar listede öne çıkar; plana bağlı iş emri tamamlanınca sonraki tarih kendiliğinden ilerler.
- **Personel ve Görevler**: günlük görevler; personel **Tamamla** ile not düşer. Bordro ve özlük bu sistemde yoktur.
- **Duyurular**: site ya da blok için taslak → yayında → arşiv.
- **Site Belgeleri**: yönetim planı, karar, tutanak, sözleşme. Değişiklikte **Yeni sürüm yükle**; eski sürümler silinmez.
  "Yalnız muhasebe" erişimli belgeyi site yöneticisi göremez.

> Acil durumlarda form değil telefon kullanılmalıdır; web formu acil müdahale garantisi değildir.

### 3.8 Raporlar (Site Raporları)
| Rapor | İçerik |
|---|---|
| Dönem özeti | Tahakkuk, döneme dağıtılan tahsilat, kalan, **tahsilat oranı**, geçmiş dönem tahsilatı (ayrı), onaylı gider, kasa/banka bakiyeleri |
| Bölüm bakiyeleri ve vadesi geçmiş borçlar | Bölüm bazında borç, tahsilat, bakiye, avans; vadesi geçmiş kalanlar |
| Bölüm hesap ekstresi | Seçilen bölümün tüm borç, düzeltme, tahsilat, iade satırları ve yürüyen bakiye |
| Gider özeti ve tedarikçi borçları | Kategori bazında onaylı/ödenen; tedarikçiye kalan borç |
| Kasa / banka dökümü | Dönem başı bakiye, hareketler, dönem sonu; transfer hariç giriş/çıkış ayrıca |
| İş emri, bakım ve görev raporu | Açık/tamamlanan iş emirleri, geciken bakımlar, görevler |

**Tahsilat oranı** = seçili dönem tahakkuklarına dağıtılmış tahsilat ÷ seçili dönem tahakkuk toplamı. Tahakkuk yoksa oran
gösterilmez ("—"). Farklı para birimleri ayrı satırlarda durur; şirket komisyonları bu raporlarda yoktur.
Her raporda **CSV indir** (Excel'de açılır) ve **Yazdır / PDF** (tarayıcının "PDF olarak kaydet"i) vardır; ekran, CSV ve
çıktıdaki toplamlar aynıdır. Dışa aktarma işlem geçmişine yazılır.

---

## 4. Web içerikleri
- **Sayfalar**: Hakkımızda ve yasal metinler. "İnceleme bekliyor" işaretli yasal metin sitede "taslak" uyarısıyla görünür;
  hukukçu onayından sonra işareti kaldırın.
- **Hizmetler**: site yönetimi hizmet kartları (ikon, özet, kapsam, süreç adımları, görsel).
- **Bilgi Merkezi**: yazılar; ileri tarihli yayın desteklenir.
- **Referanslar**: yalnız **yayımlama izni alındı** işaretliyse yayına alınabilir.
- **Sık Sorulan Sorular**: gruplara göre (genel / emlak / site yönetimi).
- **Yönlendirmeler**: eski web sitesinin adresleri (`/ilan.php?id=15` → `/emlak`). Yalnız sitede karşılığı olmayan
  adresler yönlendirilir. "Kullanım" sütunu kaç kez işe yaradığını gösterir.
- Sayfa, yazı ve hizmet düzenlerken **Önizle** kaydedilmiş hali yeni sekmede gösterir (yayında olmasa da).
- Görsellerde **alternatif metin** ve **kullanım hakkı** doldurun. Stok görseli gerçek ekip ya da yönettiğimiz site gibi sunmayın.

## 5. Sistem (sistem yöneticisi)
- **Kullanıcılar ve Yetkiler**: yeni kullanıcı, rol, etkin/pasif. Ayrılan çalışanı silmek yerine **pasife alın** (geçmiş
  kayıtlarındaki adı korunur). Seçilen rolün izinleri formda listelenir. Site rolleri için siteye atamayı unutmayın (3.1).
  Kişinin sitede "Ekibimiz" bölümünde görünmesi için "Ekipte göster" ve görev bilgisi gerekir; yayımlama izni alın.
- **Site Ayarları**: firma unvanı, telefonlar, iletişim kişileri (WhatsApp açık/kapalı), e-posta, adres, çalışma saatleri,
  bildirim e-postası (web başvuruları buraya bildirilir), logo ve görseller. **Teyit edilmemiş bilgiyi girmeyin**; boş alan
  sitede hiç görünmez.
- **İşlem Geçmişi**: tüm değişiklikler; "Görüntüle" önceki/sonraki değerleri yan yana gösterir.

## 6. Yedek (teknik sorumlu)
```
php artisan balkan:yedek              # her gece 03.15'te kendiliğinden
php artisan balkan:yedek-dogrula      # her pazar kendiliğinden; sonucu İşlem Geçmişi'nde
php artisan balkan:geri-yukle DOSYA --evet
```
Yedekleri düzenli olarak sunucu dışına kopyalayın; yedek parolasını ayrı ve güvenli bir yerde saklayın. Ayrıntı: `DEPLOY.md`.

## 7. Sık karşılaşılan durumlar
| Durum | Ne yapmalı |
|---|---|
| "Bu dönem kapalı" | Tarih doğru mu? Doğruysa şirket yöneticisi gerekçeyle düzeltir (3.6). |
| Tahsilat kasada görünmüyor | Dekontlu tahsilat doğrulanmamış olabilir → **Doğrula**. |
| "Şu bölümlerin metrekaresi girilmemiş" | Bağımsız bölümlerde m² / arsa payını tamamlayın, sonra borçlandırın. |
| "Onay limitini aşıyor" | Gider şirket yöneticisinin onayını bekler. |
| Danışman bir ilanı düzenleyemiyor | Yayındaki ilan önce yayından kaldırılmalı ya da yetkili düzenlemeli. |
| İlan "Yayımla" demiyor | Açılan uyarıdaki eksikleri tamamlayın (fotoğraf, açıklama, metrekare, danışman...). |
| Eski bağlantı 404 veriyor | **Yönlendirmeler**'e eski adresi ve yenisini ekleyin. |
