# Yetki tablosu

> Bu belge koddan üretilir (`php artisan balkan:belgeler`); elle düzenlemeyin. Kaynak: `app/Support/Yetki.php`.

Menü gizlemek yetmez: her izin sunucu tarafında politikalarla (`app/Policies`) da denetlenir.
"Tümünü görme" izni olmayan kullanıcı yalnız kendi kayıtlarını görür (danışman: sorumlu olduğu portföy, kendisine atanan talep, kendi randevusu).
Site yönetiminde "Tüm siteleri görme" izni yoksa kullanıcı yalnız **atandığı siteleri** görür (Siteler → Kullanıcılar); saha personeli yalnız kendisine atanan iş emri ve görevi görür.

| İzin | Sistem yöneticisi | Şirket yöneticisi | Emlak danışmanı | İçerik editörü | Site yöneticisi | Muhasebe sorumlusu | Saha personeli |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Panele giriş <br><sub>`panel`</sub> | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Tüm portföyleri görme <br><sub>`portfoy.goruntule_tumu`</sub> | ✓ | ✓ |  |  |  |  |  |
| Portföy oluşturma <br><sub>`portfoy.olustur`</sub> | ✓ | ✓ | ✓ |  |  |  |  |
| Başkasının portföyünü düzenleme <br><sub>`portfoy.duzenle_tumu`</sub> | ✓ | ✓ |  |  |  |  |  |
| İlan yayımlama / yayından kaldırma <br><sub>`portfoy.yayimla`</sub> | ✓ | ✓ |  |  |  |  |  |
| Portföy silme <br><sub>`portfoy.sil`</sub> | ✓ | ✓ |  |  |  |  |  |
| Mülk sahibi, iç not ve komisyon anlaşmasını görme <br><sub>`portfoy.ic_bilgi`</sub> | ✓ | ✓ | ✓ |  |  |  |  |
| Özel belgeleri görme / indirme <br><sub>`belge.goruntule`</sub> | ✓ | ✓ | ✓ |  |  |  |  |
| Tüm kişileri görme <br><sub>`kisi.goruntule_tumu`</sub> | ✓ | ✓ |  |  |  |  |  |
| Tüm talepleri görme <br><sub>`talep.goruntule_tumu`</sub> | ✓ | ✓ |  |  |  |  |  |
| Talep atama <br><sub>`talep.ata`</sub> | ✓ | ✓ |  |  |  |  |  |
| Tüm randevuları görme <br><sub>`randevu.goruntule_tumu`</sub> | ✓ | ✓ |  |  |  |  |  |
| Komisyon kayıtları <br><sub>`komisyon.goruntule`</sub> | ✓ | ✓ |  |  |  |  |  |
| Konum listesi <br><sub>`konum.yonet`</sub> | ✓ | ✓ |  |  |  |  |  |
| Web içerikleri <br><sub>`icerik.yonet`</sub> | ✓ | ✓ |  | ✓ |  |  |  |
| Kullanıcılar ve roller <br><sub>`kullanici.yonet`</sub> | ✓ |  |  |  |  |  |  |
| Site ayarları <br><sub>`ayar.yonet`</sub> | ✓ |  |  |  |  |  |  |
| İşlem geçmişi <br><sub>`islem_gecmisi.goruntule`</sub> | ✓ | ✓ |  |  |  |  |  |
| Dışa aktarma <br><sub>`disa_aktar`</sub> | ✓ | ✓ |  |  |  | ✓ |  |
| Tüm siteleri görme <br><sub>`site.goruntule_tumu`</sub> | ✓ | ✓ |  |  |  |  |  |
| Site, blok, bağımsız bölüm, malik/kiracı kaydı <br><sub>`site.yonet`</sub> | ✓ | ✓ |  |  | ✓ |  |  |
| Borç, tahsilat, gider ve hesapları görme <br><sub>`finans.goruntule`</sub> | ✓ | ✓ |  |  | ✓ | ✓ |  |
| Borçlandırma (önizleme ve kesinleştirme), borç düzeltme <br><sub>`finans.borclandir`</sub> | ✓ | ✓ |  |  |  | ✓ |  |
| Tahsilat kaydı, iade, dekont doğrulama <br><sub>`finans.tahsilat`</sub> | ✓ | ✓ |  |  |  | ✓ |  |
| Gider kaydı ve ödeme <br><sub>`finans.gider`</sub> | ✓ | ✓ |  |  | ✓ | ✓ |  |
| Kasa/banka, açılış bakiyesi, transfer, mutabakat <br><sub>`finans.hesap`</sub> | ✓ | ✓ |  |  |  | ✓ |  |
| Gider onayı (site onay limitine kadar) <br><sub>`gider.onayla`</sub> | ✓ | ✓ |  |  | ✓ |  |  |
| Onay limitini aşan gider onayı <br><sub>`gider.onayla_limitsiz`</sub> | ✓ | ✓ |  |  |  |  |  |
| Dönem kapama <br><sub>`donem.kapat`</sub> | ✓ | ✓ |  |  |  | ✓ |  |
| Kapalı dönemde gerekçeli düzeltme <br><sub>`donem.duzelt`</sub> | ✓ | ✓ |  |  |  |  |  |
| İş emri, bakım planı, görev, duyuru yönetimi <br><sub>`operasyon.yonet`</sub> | ✓ | ✓ |  |  | ✓ |  |  |
| Kendisine atanan görev ve iş emirlerini tamamlama <br><sub>`operasyon.saha`</sub> | ✓ |  |  |  |  |  | ✓ |
| Site belgeleri <br><sub>`site_belge.goruntule`</sub> | ✓ | ✓ |  |  | ✓ | ✓ |  |
| Site raporları <br><sub>`site_rapor`</sub> | ✓ | ✓ |  |  | ✓ | ✓ |  |

## Değişmeyen kurallar (role bağlı değil)

- Mali kayıtlar (borç, tahsilat, dağıtım, hesap hareketi, gider ödemesi, transfer) kimse tarafından silinemez ya da tutarı değiştirilemez; düzeltme ve iade gerekçeli karşı kayıtla yapılır.
- Onaylanmış gider düzenlenemez; site onay limitini aşan gideri yalnız "Onay limitini aşan gider onayı" izni olan onaylar.
- Kapalı döneme kayıt yalnız "Kapalı dönemde gerekçeli düzeltme" izni ve gerekçeyle.
- İşlem geçmişi yalnız okunur. Kullanıcı kendi rolünü ve etkinliğini değiştiremez, kendini silemez.
- Özel belgeler ve dekontlar web kökü dışında; indirme her seferinde yetki denetimi ve işlem kaydıyla.
