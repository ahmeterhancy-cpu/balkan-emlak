# Balkan Emlak ve Yönetim — Canlıya kurulum

Hedef: paylaşımlı cPanel (PHP 8.3+, MySQL/MariaDB, SSH ve Node **olmayabilir**).
Önceki projelerde (Zeys, Ay Parçası) canlıda yaşanan tuzaklar bu listeye işlendi.

## 0. Yayından önce işletmeden alınacaklar (şartname 18)

Ofis adresi, çalışma saatleri, e-posta, WhatsApp kullanımı, kişilerin görevleri, logo ve
kesin renkler, alan adı, hizmet verilen ilçeler, hukukçu onaylı KVKK / gizlilik metinleri,
"Hukuki yönetim" hizmet kapsamı, gerçek ilan fotoğrafları. Bunlar gelmeden `APP_ENV=production`
ile açılırsa site boş alanları **göstermez** (yer tutucu yayımlanmaz), ama demo ilan olmaz.

## 1. Sunucu gereksinimleri

- PHP ≥ 8.3: `pdo_mysql`, `mbstring` (Turhost'ta kapalı olabiliyor — açtırın), `intl`, `gd`, `fileinfo`, `zip`
- MySQL 8 / MariaDB 10.5+, **InnoDB** (config varsayılanı `DB_ENGINE=InnoDB ROW_FORMAT=DYNAMIC`)
- Cron: her dakika `php artisan schedule:run` (şimdilik zamanlanmış iş yok; ileride bildirim/rapor için)

## 2. Yerelde paket hazırlığı (sunucuda composer/Node yoksa)

```bash
npm ci && npm run build                          # public/build üretilir
composer install --no-dev --optimize-autoloader  # vendor üretim sürümü
```

`public/build` ve `vendor` depoya **bilerek** eklenir (`.gitignore`'da; `git add -f`).
TUZAK: `vendor` dev kurulumdan commit'lenirse autoload dosyaları olmayan paketleri (faker) arar,
artisan çıktı vermeden ölür. Doğrulama: `git show HEAD:vendor/composer/autoload_files.php | grep faker` boş olmalı.
Paket commit'lendikten sonra yerelde tekrar `composer install` (dev) çalıştırın.

## 3. Dizin yerleşimi

`public_html` = Laravel'in `public/` klasörü; uygulamanın geri kalanı web kökünün **dışında**
(ör. `/home/KULLANICI/balkan_app`). `public_html/index.php` içindeki yolları buna göre düzeltin.
Ana dizin `/home` olmayabilir (Turhost: `/home2/KULLANICI`) — cPanel "Giriş Dizini"nden okuyun.

- İlan fotoğrafları `public/medya` altına yazılır (`MEDYA_KOK` ile değiştirilebilir); **storage:link gerekmez**.
- Özel belgeler (tapu, yetki belgesi, dekont, site belgeleri) `storage/app/private` — web kökü dışında kalmalı.
- `.env` web kökünde OLMAMALI; tarayıcıdan `/.env` 403/404 vermeli.

## 4. .env (üretim)

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ALAN.ADI
APP_TIMEZONE=Europe/Istanbul
APP_LOCALE=tr
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
MAIL_MAILER=smtp
MAIL_HOST=... MAIL_PORT=587 MAIL_USERNAME=... MAIL_PASSWORD=... MAIL_FROM_ADDRESS=...
```

## 5. İlk kurulum komutları (sırası önemli)

```bash
php artisan key:generate --force           # yalnız ilk kez
php artisan optimize:clear                 # göçten ÖNCE: eski önbellekli ayar göçü yanıltır
php artisan migrate --force
php artisan db:seed --force                # canlıda yalnız Konum, Kullanıcı iskeleti ve İçerik; demo YOK
php artisan balkan:yonetici eposta@ALAN.ADI --ad="Ad Soyad"   # parola bir kez yazılır
php artisan filament:assets                # panel CSS/JS (composer yoksa otomatik yayımlanmaz)
php artisan optimize
```

Tohumdaki `*.test` hesapları canlıda rastgele parolayla oluşur; kullanılmayacaksa panelden **pasife alın**.

## 6. Kurulum sonrası kontrol listesi

- [ ] `/` , `/emlak`, bir ilan, `/site-yonetimi`, `/yonetim-teklifi` 200
- [ ] `/admin` giriş; yönetici tüm menüleri görüyor
- [ ] `/robots.txt` canlıda `Disallow: /admin` + sitemap satırı veriyor; `/sitemap.xml` açılıyor
- [ ] Panel → Site Ayarları: firma bilgileri, iletişim kişileri, bildirim e-postası girildi
- [ ] Bir test başvurusu → panelde "Başvurular"a düştü, bildirim e-postası geldi
- [ ] İlan fotoğrafı yükle → sitede görünüyor (medya diski yazılabilir)
- [ ] Özel belge yükle → doğrudan URL ile erişilemiyor, panelden indiriliyor
- [ ] HTTPS zorunlu (cPanel → Force HTTPS), SSL geçerli
- [ ] Yedekleme: günlük veritabanı + `storage/app/private` + `public/medya`; **geri yükleme test ortamında denendi** (şartname 17)

## 7. Güncelleme (sonraki sürümler)

Yerelde: `npm run build`, üretim `vendor`, commit → sunucuda çek →
`php artisan optimize:clear && php artisan migrate --force && php artisan filament:assets && php artisan optimize`.
