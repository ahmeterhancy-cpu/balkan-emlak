# Veritabanı şeması

> Bu belge koddan üretilir (`php artisan balkan:belgeler`); elle düzenlemeyin. Kaynak: `database/migrations`.

Kurallar: tutarlar **kuruş cinsinden tam sayı** (`*_kurus`; mali tablolarda işaretli: tahsilat +, iade −). Saatsiz tarihler `Y-m-d` saklanır (`App\Casts\Gun`). Siteye bağlı her kayıtta `site_id` vardır. Türler geliştirme veritabanından (SQLite) okunur; MySQL'de `varchar`/`bigint`/`date` karşılıklarıdır.

## Emlak

### `fiyat_gecmisi`

İlan fiyat değişiklikleri; yalnız eklenir.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `portfoy_id` | integer |  |  | portfoyler.id (silinince birlikte silinir) |
| `eski_fiyat_kurus` | integer | evet |  |  |
| `yeni_fiyat_kurus` | integer |  |  |  |
| `para_birimi` | varchar |  |  |  |
| `user_id` | integer | evet |  | users.id (silinince boşalır) |
| `created_at` | datetime |  | `CURRENT_TIMESTAMP` |  |

### `komisyonlar`

Şirket komisyon geliri takibi (resmi muhasebe değildir; site paralarından ayrı).

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `portfoy_id` | integer | evet |  | portfoyler.id (silinince boşalır) |
| `danisman_id` | integer | evet |  | users.id (silinince boşalır) |
| `taraf` | varchar |  | `satici` |  |
| `beklenen_kurus` | integer |  |  |  |
| `gerceklesen_kurus` | integer | evet |  |  |
| `para_birimi` | varchar |  | `TRY` |  |
| `odeme_tarihi` | date | evet |  |  |
| `durum` | varchar |  | `bekliyor` |  |
| `aciklama` | text | evet |  |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

### `konumlar`

İl → ilçe → mahalle ağacı.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `ust_id` | integer | evet |  | konumlar.id (silinince birlikte silinir) |
| `tur` | varchar |  |  |  |
| `ad` | varchar |  |  |  |
| `slug` | varchar |  |  |  |
| `sira` | integer |  | `0` |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

Benzersiz: `ust_id, slug`

### `portfoy_belgeleri`

Özel belgeler (tapu, yetki belgesi) — web kökü dışında.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `portfoy_id` | integer |  |  | portfoyler.id (silinince birlikte silinir) |
| `tur` | varchar |  |  |  |
| `ad` | varchar |  |  |  |
| `yol` | varchar |  |  |  |
| `orijinal_ad` | varchar | evet |  |  |
| `gecerlilik_bitis` | date | evet |  |  |
| `yukleyen_id` | integer | evet |  | users.id (silinince boşalır) |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

### `portfoy_medya`

İlan fotoğrafları ve kat planları (kapak, sıra, kullanım hakkı).

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `portfoy_id` | integer |  |  | portfoyler.id (silinince birlikte silinir) |
| `yol` | varchar |  |  |  |
| `tur` | varchar |  | `foto` |  |
| `alt_metin` | varchar | evet |  |  |
| `kapak` | tinyint(1) |  | `0` |  |
| `sira` | integer |  | `0` |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |
| `gorsel_hakki` | varchar | evet |  |  |
| `gorsel_kaynak` | varchar | evet |  |  |

### `portfoyler`

Gayrimenkul + ilan. Operasyon ve yayın durumu ayrı; iç bilgiler gizli.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `portfoy_no` | varchar |  |  |  |
| `baslik` | varchar |  |  |  |
| `slug` | varchar |  |  |  |
| `islem` | varchar |  |  |  |
| `kategori` | varchar |  |  |  |
| `tip` | varchar |  |  |  |
| `il_id` | integer | evet |  | konumlar.id (silinince boşalır) |
| `ilce_id` | integer | evet |  | konumlar.id (silinince boşalır) |
| `mahalle_id` | integer | evet |  | konumlar.id (silinince boşalır) |
| `acik_adres` | text | evet |  |  |
| `enlem` | numeric | evet |  |  |
| `boylam` | numeric | evet |  |  |
| `harita_gosterimi` | varchar |  | `yok` |  |
| `adres_goster` | tinyint(1) |  | `0` |  |
| `fiyat_kurus` | integer |  |  |  |
| `para_birimi` | varchar |  | `TRY` |  |
| `depozito_kurus` | integer | evet |  |  |
| `aidat_kurus` | integer | evet |  |  |
| `brut_m2` | integer | evet |  |  |
| `net_m2` | integer | evet |  |  |
| `oda` | varchar | evet |  |  |
| `banyo` | integer | evet |  |  |
| `kat` | varchar | evet |  |  |
| `kat_sayisi` | integer | evet |  |  |
| `bina_yasi` | integer | evet |  |  |
| `isitma` | varchar | evet |  |  |
| `asansor` | tinyint(1) | evet |  |  |
| `otopark` | varchar | evet |  |  |
| `balkon` | tinyint(1) | evet |  |  |
| `esyali` | tinyint(1) | evet |  |  |
| `site_icinde` | tinyint(1) | evet |  |  |
| `ozellikler` | text | evet |  |  |
| `ada` | varchar | evet |  |  |
| `parsel` | varchar | evet |  |  |
| `imar_durumu` | varchar | evet |  |  |
| `aciklama` | text | evet |  |  |
| `video_url` | varchar | evet |  |  |
| `seo_baslik` | varchar | evet |  |  |
| `seo_aciklama` | varchar | evet |  |  |
| `operasyon_durumu` | varchar |  | `aktif` |  |
| `yayin_durumu` | varchar |  | `taslak` |  |
| `yayin_tarihi` | datetime | evet |  |  |
| `one_cikan` | tinyint(1) |  | `0` |  |
| `danisman_id` | integer | evet |  | users.id (silinince boşalır) |
| `mulk_sahibi_id` | integer | evet |  | kisiler.id (silinince boşalır) |
| `ic_notlar` | text | evet |  |  |
| `komisyon_anlasmasi` | varchar | evet |  |  |
| `olusturan_id` | integer | evet |  | users.id (silinince boşalır) |
| `guncelleyen_id` | integer | evet |  | users.id (silinince boşalır) |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

Benzersiz: `portfoy_no`; `slug`

### `randevular`

Yer gösterme randevuları.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `talep_id` | integer | evet |  | talepler.id (silinince boşalır) |
| `portfoy_id` | integer | evet |  | portfoyler.id (silinince boşalır) |
| `danisman_id` | integer | evet |  | users.id (silinince boşalır) |
| `musteri_adi` | varchar |  |  |  |
| `musteri_telefon` | varchar | evet |  |  |
| `baslangic` | datetime |  |  |  |
| `sure_dk` | integer |  | `60` |  |
| `durum` | varchar |  | `planlandi` |  |
| `sonuc_notu` | text | evet |  |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

### `talep_notlari`

Talep görüşme notları.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `talep_id` | integer |  |  | talepler.id (silinince birlikte silinir) |
| `user_id` | integer | evet |  | users.id (silinince boşalır) |
| `not` | text |  |  |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

### `talep_portfoy`

Talebin ilgilendiği portföyler.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `talep_id` | integer |  |  | talepler.id (silinince birlikte silinir) |
| `portfoy_id` | integer |  |  | portfoyler.id (silinince birlikte silinir) |

### `talepler`

Web başvuruları ve müşteri talepleri (iletişim, ilan bilgi, portföy başvurusu, yönetim teklifi).

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `tur` | varchar |  |  |  |
| `kaynak` | varchar |  | `web` |  |
| `ad_soyad` | varchar |  |  |  |
| `telefon` | varchar | evet |  |  |
| `eposta` | varchar | evet |  |  |
| `konu` | varchar | evet |  |  |
| `mesaj` | text | evet |  |  |
| `portfoy_id` | integer | evet |  | portfoyler.id (silinince boşalır) |
| `kisi_id` | integer | evet |  | kisiler.id (silinince boşalır) |
| `tercih_edilen_zaman` | datetime | evet |  |  |
| `detay` | text | evet |  |  |
| `aranan_islem` | varchar | evet |  |  |
| `aranan_kategori` | varchar | evet |  |  |
| `butce_min_kurus` | integer | evet |  |  |
| `butce_max_kurus` | integer | evet |  |  |
| `aranan_konum` | varchar | evet |  |  |
| `durum` | varchar |  | `yeni` |  |
| `atanan_id` | integer | evet |  | users.id (silinince boşalır) |
| `sonraki_takip` | date | evet |  |  |
| `sonuc` | varchar | evet |  |  |
| `kvkk_onay` | tinyint(1) |  | `0` |  |
| `pazarlama_izni` | tinyint(1) |  | `0` |  |
| `ip` | varchar | evet |  |  |
| `tarayici` | varchar | evet |  |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

## Ortak

### `kisiler`

Kişi / kurum: mülk sahibi, müşteri, malik, kiracı. Herkese açık değil.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `tur` | varchar |  | `bireysel` |  |
| `ad_soyad` | varchar |  |  |  |
| `telefon` | varchar | evet |  |  |
| `eposta` | varchar | evet |  |  |
| `iletisim_tercihi` | varchar | evet |  |  |
| `pazarlama_izni` | tinyint(1) |  | `0` |  |
| `notlar` | text | evet |  |  |
| `olusturan_id` | integer | evet |  | users.id (silinince boşalır) |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

## Site yönetimi

### `bagimsiz_bolumler`

Bağımsız bölümler: m², arsa payı, tür.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `site_id` | integer |  |  | siteler.id (silinince birlikte silinir) |
| `blok_id` | integer | evet |  | bloklar.id (silinince boşalır) |
| `no` | varchar |  |  |  |
| `kat` | varchar | evet |  |  |
| `tur` | varchar |  | `daire` |  |
| `brut_m2` | numeric | evet |  |  |
| `arsa_payi` | integer | evet |  |  |
| `arsa_payi_payda` | integer | evet |  |  |
| `aktif` | tinyint(1) |  | `1` |  |
| `notlar` | text | evet |  |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

Benzersiz: `site_id, blok_id, no`

### `bloklar`

Bloklar.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `site_id` | integer |  |  | siteler.id (silinince birlikte silinir) |
| `ad` | varchar |  |  |  |
| `sira` | integer |  | `0` |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

Benzersiz: `site_id, ad`

### `bolum_iliskileri`

Malik / kiracı ilişkisi, başlangıç–bitiş tarihli; borç sorumlusu.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `site_id` | integer |  |  | siteler.id (silinince birlikte silinir) |
| `bagimsiz_bolum_id` | integer |  |  | bagimsiz_bolumler.id (silinince birlikte silinir) |
| `kisi_id` | integer |  |  | kisiler.id (bağlıyken silinemez) |
| `rol` | varchar |  |  |  |
| `hisse_pay` | integer | evet |  |  |
| `hisse_payda` | integer | evet |  |  |
| `baslangic` | date |  |  |  |
| `bitis` | date | evet |  |  |
| `borc_sorumlusu` | tinyint(1) |  | `0` |  |
| `notlar` | text | evet |  |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

### `site_kullanici`

Kullanıcı ↔ site yetki ataması.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `site_id` | integer |  |  | siteler.id (silinince birlikte silinir) |
| `user_id` | integer |  |  | users.id (silinince birlikte silinir) |

### `siteler`

Yönetilen site / apartman; onay limiti, kapalı dönem.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `ad` | varchar |  |  |  |
| `tur` | varchar |  | `site` |  |
| `adres` | text | evet |  |  |
| `il_id` | integer | evet |  | konumlar.id (silinince boşalır) |
| `ilce_id` | integer | evet |  | konumlar.id (silinince boşalır) |
| `yonetim_baslangic` | date | evet |  |  |
| `yonetim_bitis` | date | evet |  |  |
| `sorumlu_id` | integer | evet |  | users.id (silinince boşalır) |
| `iletisim_kisi` | varchar | evet |  |  |
| `iletisim_telefon` | varchar | evet |  |  |
| `para_birimi` | varchar |  | `TRY` |  |
| `varsayilan_vade_gunu` | integer |  | `10` |  |
| `onay_limiti_kurus` | integer | evet |  |  |
| `kapali_donem` | varchar | evet |  |  |
| `notlar` | text | evet |  |  |
| `aktif` | tinyint(1) |  | `1` |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

## Site finansı

### `borclandirmalar`

Toplu tahakkuk işi; (site, dönem, tür, etiket) benzersiz — tekrar çalışmaz.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `site_id` | integer |  |  | siteler.id (silinince birlikte silinir) |
| `donem` | varchar |  |  |  |
| `tur` | varchar |  |  |  |
| `etiket` | varchar |  | `` |  |
| `aciklama` | varchar |  |  |  |
| `vade` | date |  |  |  |
| `dagitim` | varchar |  |  |  |
| `birim_kurus` | integer | evet |  |  |
| `toplam_kurus` | integer | evet |  |  |
| `ozel_tutarlar` | text | evet |  |  |
| `bolum_turleri` | text | evet |  |  |
| `para_birimi` | varchar |  | `TRY` |  |
| `durum` | varchar |  | `taslak` |  |
| `olusturan_id` | integer | evet |  | users.id (silinince boşalır) |
| `kesinlestiren_id` | integer | evet |  | users.id (silinince boşalır) |
| `kesinlesme_zamani` | datetime | evet |  |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

Benzersiz: `site_id, donem, tur, etiket`

### `borclar`

Bölüm borçları; düzeltme işaretli karşı kayıt. Değişmez.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `site_id` | integer |  |  | siteler.id (silinince birlikte silinir) |
| `bagimsiz_bolum_id` | integer |  |  | bagimsiz_bolumler.id (bağlıyken silinemez) |
| `kisi_id` | integer | evet |  | kisiler.id (silinince boşalır) |
| `borclandirma_id` | integer | evet |  | borclandirmalar.id (bağlıyken silinemez) |
| `duzeltilen_borc_id` | integer | evet |  | borclar.id (bağlıyken silinemez) |
| `tur` | varchar |  |  |  |
| `donem` | varchar |  |  |  |
| `aciklama` | varchar |  |  |  |
| `vade` | date |  |  |  |
| `tutar_kurus` | integer |  |  |  |
| `para_birimi` | varchar |  | `TRY` |  |
| `gerekce` | varchar | evet |  |  |
| `olusturan_id` | integer | evet |  | users.id (silinince boşalır) |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

Benzersiz: `borclandirma_id, bagimsiz_bolum_id`

### `gider_odemeleri`

Gider ödemeleri (+) ve geri almalar (−). Değişmez.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `gider_id` | integer |  |  | giderler.id (bağlıyken silinemez) |
| `hesap_id` | integer |  |  | hesaplar.id (bağlıyken silinemez) |
| `tarih` | date |  |  |  |
| `tutar_kurus` | integer |  |  |  |
| `aciklama` | varchar | evet |  |  |
| `iptal_edilen_id` | integer | evet |  | gider_odemeleri.id (bağlıyken silinemez) |
| `olusturan_id` | integer | evet |  | users.id (silinince boşalır) |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

### `giderler`

Giderler; onay durumu sütunda, ödeme durumu ödemelerden hesaplanır.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `site_id` | integer |  |  | siteler.id (silinince birlikte silinir) |
| `tedarikci_id` | integer | evet |  | tedarikciler.id (silinince boşalır) |
| `kategori` | varchar |  |  |  |
| `belge_tarihi` | date |  |  |  |
| `belge_no` | varchar | evet |  |  |
| `donem` | varchar |  |  |  |
| `vade` | date | evet |  |  |
| `tutar_kurus` | integer |  |  |  |
| `para_birimi` | varchar |  | `TRY` |  |
| `aciklama` | varchar |  |  |  |
| `belge_yolu` | varchar | evet |  |  |
| `onay_durumu` | varchar |  | `taslak` |  |
| `onaylayan_id` | integer | evet |  | users.id (silinince boşalır) |
| `onay_zamani` | datetime | evet |  |  |
| `red_gerekcesi` | varchar | evet |  |  |
| `olusturan_id` | integer | evet |  | users.id (silinince boşalır) |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

### `hesap_hareketleri`

Kasa/banka defteri; bakiye = toplam. Değişmez (mutabakat işareti hariç).

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `site_id` | integer |  |  | siteler.id (silinince birlikte silinir) |
| `hesap_id` | integer |  |  | hesaplar.id (bağlıyken silinemez) |
| `tarih` | date |  |  |  |
| `tutar_kurus` | integer |  |  |  |
| `tur` | varchar |  |  |  |
| `kaynak_type` | varchar | evet |  |  |
| `kaynak_id` | integer | evet |  |  |
| `aciklama` | varchar | evet |  |  |
| `dayanak` | varchar | evet |  |  |
| `mutabik` | tinyint(1) |  | `0` |  |
| `mutabakat_tarihi` | date | evet |  |  |
| `olusturan_id` | integer | evet |  | users.id (silinince boşalır) |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

### `hesaplar`

Sitenin kasa / banka hesapları.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `site_id` | integer |  |  | siteler.id (silinince birlikte silinir) |
| `ad` | varchar |  |  |  |
| `tur` | varchar |  |  |  |
| `banka_adi` | varchar | evet |  |  |
| `iban` | varchar | evet |  |  |
| `para_birimi` | varchar |  | `TRY` |  |
| `aktif` | tinyint(1) |  | `1` |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

### `tahsilat_dagitimlari`

Tahsilat → borç dağıtımı; geri alma eksi satırla. Değişmez.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `tahsilat_id` | integer |  |  | tahsilatlar.id (bağlıyken silinemez) |
| `borc_id` | integer |  |  | borclar.id (bağlıyken silinemez) |
| `tutar_kurus` | integer |  |  |  |
| `tarih` | date |  |  |  |
| `olusturan_id` | integer | evet |  | users.id (silinince boşalır) |
| `created_at` | datetime |  | `CURRENT_TIMESTAMP` |  |

### `tahsilatlar`

Tahsilat (+) ve iade (−); dekontlu olan doğrulanana kadar işlenmez. Değişmez.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `site_id` | integer |  |  | siteler.id (silinince birlikte silinir) |
| `hesap_id` | integer |  |  | hesaplar.id (bağlıyken silinemez) |
| `bagimsiz_bolum_id` | integer |  |  | bagimsiz_bolumler.id (bağlıyken silinemez) |
| `kisi_id` | integer | evet |  | kisiler.id (silinince boşalır) |
| `tur` | varchar |  | `tahsilat` |  |
| `iade_edilen_id` | integer | evet |  | tahsilatlar.id (bağlıyken silinemez) |
| `tarih` | date |  |  |  |
| `tutar_kurus` | integer |  |  |  |
| `para_birimi` | varchar |  | `TRY` |  |
| `yontem` | varchar |  |  |  |
| `aciklama` | varchar | evet |  |  |
| `belge_yolu` | varchar | evet |  |  |
| `dogrulandi` | tinyint(1) |  | `1` |  |
| `gerekce` | varchar | evet |  |  |
| `olusturan_id` | integer | evet |  | users.id (silinince boşalır) |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

### `tedarikciler`

Tedarikçiler (tüm sitelerde ortak).

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `ad` | varchar |  |  |  |
| `kategori` | varchar | evet |  |  |
| `vergi_no` | varchar | evet |  |  |
| `telefon` | varchar | evet |  |  |
| `eposta` | varchar | evet |  |  |
| `iban` | varchar | evet |  |  |
| `notlar` | text | evet |  |  |
| `aktif` | tinyint(1) |  | `1` |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

### `transferler`

Aynı sitenin hesapları arası transfer; gelir/gider değildir.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `site_id` | integer |  |  | siteler.id (silinince birlikte silinir) |
| `kaynak_hesap_id` | integer |  |  | hesaplar.id (bağlıyken silinemez) |
| `hedef_hesap_id` | integer |  |  | hesaplar.id (bağlıyken silinemez) |
| `tarih` | date |  |  |  |
| `tutar_kurus` | integer |  |  |  |
| `aciklama` | varchar | evet |  |  |
| `olusturan_id` | integer | evet |  | users.id (silinince boşalır) |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

## Site operasyonu

### `bakim_planlari`

Periyodik bakım planları.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `site_id` | integer |  |  | siteler.id (silinince birlikte silinir) |
| `varlik_id` | integer | evet |  | varliklar.id (silinince boşalır) |
| `ad` | varchar |  |  |  |
| `periyot_gun` | integer |  |  |  |
| `son_bakim` | date | evet |  |  |
| `sonraki_bakim` | date |  |  |  |
| `tedarikci_id` | integer | evet |  | tedarikciler.id (silinince boşalır) |
| `sorumlu_id` | integer | evet |  | users.id (silinince boşalır) |
| `aktif` | tinyint(1) |  | `1` |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

### `duyurular`

Site / blok duyuruları.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `site_id` | integer |  |  | siteler.id (silinince birlikte silinir) |
| `blok_id` | integer | evet |  | bloklar.id (silinince boşalır) |
| `baslik` | varchar |  |  |  |
| `icerik` | text |  |  |  |
| `durum` | varchar |  | `taslak` |  |
| `yayin_tarihi` | datetime | evet |  |  |
| `bitis_tarihi` | date | evet |  |  |
| `olusturan_id` | integer | evet |  | users.id (silinince boşalır) |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

### `gorevler`

Personel görevleri.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `site_id` | integer |  |  | siteler.id (silinince birlikte silinir) |
| `user_id` | integer |  |  | users.id (silinince birlikte silinir) |
| `baslik` | varchar |  |  |  |
| `aciklama` | text | evet |  |  |
| `tarih` | date |  |  |  |
| `durum` | varchar |  | `bekliyor` |  |
| `tamamlanma_notu` | text | evet |  |  |
| `tamamlanma_zamani` | datetime | evet |  |  |
| `olusturan_id` | integer | evet |  | users.id (silinince boşalır) |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

### `is_emirleri`

Arıza / iş emirleri.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `site_id` | integer |  |  | siteler.id (silinince birlikte silinir) |
| `blok_id` | integer | evet |  | bloklar.id (silinince boşalır) |
| `bagimsiz_bolum_id` | integer | evet |  | bagimsiz_bolumler.id (silinince boşalır) |
| `bakim_plani_id` | integer | evet |  | bakim_planlari.id (silinince boşalır) |
| `konu` | varchar |  |  |  |
| `aciklama` | text | evet |  |  |
| `fotograflar` | text | evet |  |  |
| `bildiren_ad` | varchar | evet |  |  |
| `bildiren_telefon` | varchar | evet |  |  |
| `oncelik` | varchar |  | `normal` |  |
| `durum` | varchar |  | `acik` |  |
| `atanan_id` | integer | evet |  | users.id (silinince boşalır) |
| `tedarikci_id` | integer | evet |  | tedarikciler.id (silinince boşalır) |
| `hedef_tarih` | date | evet |  |  |
| `yapilan_islem` | text | evet |  |  |
| `tamamlanma_fotograflari` | text | evet |  |  |
| `gider_id` | integer | evet |  | giderler.id (silinince boşalır) |
| `tamamlanma_zamani` | datetime | evet |  |  |
| `kapatan_id` | integer | evet |  | users.id (silinince boşalır) |
| `kapanma_zamani` | datetime | evet |  |  |
| `olusturan_id` | integer | evet |  | users.id (silinince boşalır) |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

### `site_belgeleri`

Yönetim planı, karar, tutanak... sürümlü, özel diskte.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `site_id` | integer |  |  | siteler.id (silinince birlikte silinir) |
| `kategori` | varchar |  |  |  |
| `ad` | varchar |  |  |  |
| `grup` | varchar |  |  |  |
| `surum` | integer |  | `1` |  |
| `yol` | varchar |  |  |  |
| `orijinal_ad` | varchar | evet |  |  |
| `erisim` | varchar |  | `yonetim` |  |
| `belge_tarihi` | date | evet |  |  |
| `yukleyen_id` | integer | evet |  | users.id (silinince boşalır) |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

Benzersiz: `grup, surum`

### `varliklar`

Bakımı izlenen ekipman (asansör, hidrofor...).

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `site_id` | integer |  |  | siteler.id (silinince birlikte silinir) |
| `blok_id` | integer | evet |  | bloklar.id (silinince boşalır) |
| `ad` | varchar |  |  |  |
| `tur` | varchar |  |  |  |
| `marka_model` | varchar | evet |  |  |
| `notlar` | text | evet |  |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

## Web içeriği

### `hizmetler`

Site yönetimi hizmetleri (vitrindeki 8 hizmet).

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `baslik` | varchar |  |  |  |
| `slug` | varchar |  |  |  |
| `ikon` | varchar | evet |  |  |
| `ozet` | varchar |  |  |  |
| `kapsam` | text | evet |  |  |
| `surec` | text | evet |  |  |
| `gorsel` | varchar | evet |  |  |
| `yayinda` | tinyint(1) |  | `1` |  |
| `sira` | integer |  | `0` |  |
| `seo_aciklama` | varchar | evet |  |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |
| `gorsel_hakki` | varchar | evet |  |  |
| `gorsel_kaynak` | varchar | evet |  |  |

Benzersiz: `slug`

### `referanslar`

Referans ve müşteri görüşleri; izin alınmadan yayımlanamaz.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `tur` | varchar |  | `proje` |  |
| `ad` | varchar |  |  |  |
| `alt_baslik` | varchar | evet |  |  |
| `metin` | text | evet |  |  |
| `gorsel` | varchar | evet |  |  |
| `izin_alindi` | tinyint(1) |  | `0` |  |
| `izin_tarihi` | date | evet |  |  |
| `yayinda` | tinyint(1) |  | `0` |  |
| `sira` | integer |  | `0` |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |
| `gorsel_hakki` | varchar | evet |  |  |
| `gorsel_kaynak` | varchar | evet |  |  |

### `sayfalar`

Kurumsal ve yasal sayfalar; yasal metinde "inceleme bekliyor" işareti.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `baslik` | varchar |  |  |  |
| `slug` | varchar |  |  |  |
| `ozet` | varchar | evet |  |  |
| `icerik` | text | evet |  |  |
| `grup` | varchar |  | `kurumsal` |  |
| `yayinda` | tinyint(1) |  | `0` |  |
| `inceleme_bekliyor` | tinyint(1) |  | `0` |  |
| `seo_baslik` | varchar | evet |  |  |
| `seo_aciklama` | varchar | evet |  |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

Benzersiz: `slug`

### `sss`

Sık sorulan sorular.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `grup` | varchar |  | `genel` |  |
| `soru` | varchar |  |  |  |
| `cevap` | text |  |  |  |
| `yayinda` | tinyint(1) |  | `1` |  |
| `sira` | integer |  | `0` |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

### `yazilar`

Bilgi merkezi yazıları; taslak / yayında / arşiv, ileri tarihli yayın.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `baslik` | varchar |  |  |  |
| `slug` | varchar |  |  |  |
| `kategori` | varchar |  | `emlak` |  |
| `ozet` | varchar | evet |  |  |
| `icerik` | text |  |  |  |
| `gorsel` | varchar | evet |  |  |
| `gorsel_alt` | varchar | evet |  |  |
| `durum` | varchar |  | `taslak` |  |
| `yayin_tarihi` | datetime | evet |  |  |
| `yazar_id` | integer | evet |  | users.id (silinince boşalır) |
| `seo_aciklama` | varchar | evet |  |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |
| `gorsel_hakki` | varchar | evet |  |  |
| `gorsel_kaynak` | varchar | evet |  |  |

Benzersiz: `slug`

### `yonlendirmeler`

Eski adres → yeni adres (301/302). Yalnız 404 olacak isteklerde çalışır.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `eski_yol` | varchar |  |  |  |
| `hedef` | varchar |  |  |  |
| `kod` | integer |  | `301` |  |
| `aktif` | tinyint(1) |  | `1` |  |
| `otomatik` | tinyint(1) |  | `0` |  |
| `isabet` | integer |  | `0` |  |
| `son_isabet` | datetime | evet |  |  |
| `not` | varchar | evet |  |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

Benzersiz: `eski_yol`

## Sistem

### `ayarlar`

Site ayarları (iletişim, sosyal medya, görseller) — anahtar/değer.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `anahtar` | varchar |  |  |  |
| `deger` | text | evet |  |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

Benzersiz: `anahtar`

### `islem_kayitlari`

İşlem geçmişi: kim, ne zaman, önceki/sonraki değer. Yalnız eklenir.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `user_id` | integer | evet |  | users.id (silinince boşalır) |
| `olay` | varchar |  |  |  |
| `kayit_turu` | varchar | evet |  |  |
| `kayit_id` | integer | evet |  |  |
| `ozet` | varchar | evet |  |  |
| `onceki` | text | evet |  |  |
| `sonraki` | text | evet |  |  |
| `ip` | varchar | evet |  |  |
| `created_at` | datetime |  | `CURRENT_TIMESTAMP` |  |

### `users`

Panel kullanıcıları; rol, etkinlik ve herkese açık ekip profili.

| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |
|---|---|:---:|---|---|
| `id` | integer |  |  |  |
| `name` | varchar |  |  |  |
| `email` | varchar |  |  |  |
| `email_verified_at` | datetime | evet |  |  |
| `password` | varchar |  |  |  |
| `rol` | varchar |  | `emlak_danismani` |  |
| `aktif` | tinyint(1) |  | `1` |  |
| `gorev` | varchar | evet |  |  |
| `telefon` | varchar | evet |  |  |
| `whatsapp` | varchar | evet |  |  |
| `foto` | varchar | evet |  |  |
| `biyografi` | text | evet |  |  |
| `ekipte_goster` | tinyint(1) |  | `0` |  |
| `ekip_sirasi` | integer |  | `0` |  |
| `remember_token` | varchar | evet |  |  |
| `created_at` | datetime | evet |  |  |
| `updated_at` | datetime | evet |  |  |

Benzersiz: `email`

## Altyapı

### `cache`

Önbellek.

### `cache_locks`

Önbellek kilitleri.

### `failed_jobs`

Başarısız kuyruk işleri.

### `job_batches`

Kuyruk iş grupları.

### `jobs`

Kuyruk işleri.

### `migrations`

Laravel göç kayıtları.

### `password_reset_tokens`

Parola sıfırlama jetonları.

### `sessions`

Oturumlar.

