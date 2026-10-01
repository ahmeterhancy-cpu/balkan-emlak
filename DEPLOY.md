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
- Cron: her dakika `php artisan schedule:run` — **zorunlu**: her gece 03.15 şifreli yedek, her pazar yedek doğrulaması

## 2. cPanel Git ile kurulum (genel tarif: `F:\Yazılımlar\CPANEL-GIT-DEPLOY.md`)

Yerleşim (alan adının kök dizini değiştirilemiyor varsayımı):

```
public_html/              index.php (deploy/cpanel/index.php), .htaccess, build/, img/, medya/, css|js|fonts/filament
public_html/balkan_app/   Laravel'in tamamı + .env   ← balkan_app/.htaccess "Require all denied"
```

`public_path()` = `public_html` (deploy `balkan_app/.public_yolu` yazar, `bootstrap/app.php` okur):
ilan fotoğrafları `public_html/medya`'ya yazılır ve **deploy onlara dokunmaz**. Özel belgeler ve yedekler
`balkan_app/storage/app` altında, web'e kapalı.

**Bir kez:**
1. GitHub deposu (cPanel klon adresinde parola kabul etmediği için pratikte public; geçmişte `.env`/anahtar yok — denetlendi).
2. cPanel → MySQL Databases: veritabanı + kullanıcı, "ALL PRIVILEGES".
3. cPanel → Git Version Control → Create: Clone URL `https://github.com/.../balkan-emlak.git`, Repository Path `repositories/balkan-emlak`.
4. `.cpanel.yml` içindeki `/home/KULLANICI` → gerçek ana dizin (Turhost'ta `/home2/...` olabilir).
5. Dosya Yöneticisi → Show Hidden Files → `public_html/balkan_app/.env` = `.env.production.example` doldurulmuş hali
   (APP_KEY yerelde `php artisan key:generate --show`; `ILK_YONETICI_*` ilk yönetici için).
   İlk deploy'dan önce `balkan_app` klasörü yoksa elle oluşturun.
6. cPanel → Cron Jobs, her dakika:
   `/usr/local/bin/php /home/balk1576/public_html/balkan_app/artisan schedule:run >> /dev/null 2>&1`
   (yedek her gece 03.15, doğrulama her pazar 04.30).

**Her yayın:**
1. Yerelde `npm run build`; composer yoksa üretim `vendor` (aşağıda) → commit → `git push`
2. cPanel → Git Version Control → Manage → **Update from Remote** → **Deploy HEAD Commit** (iki ayrı düğme)
3. "Last Deployed" son commit mi? `~/deploy-balkan.log` sonunda `=== DEPLOY BITTI ===` var mı?

Deploy sırası: dosyalar → storage iskeleti → `.htaccess` → web kökü → `migrate --force` →
`balkan:kurulum` (içerik iskeleti yalnız boşken, ilk yönetici yalnız hiç yokken; demo veri ve `*.test` hesap YOK) →
`filament:assets` → `optimize`. `migrate:fresh` / `db:seed` bilerek yok.

**Composer sunucuda yoksa** `vendor` depoya girer:
```bash
composer install --no-dev --optimize-autoloader
git add -f vendor && git commit -m "vendor (uretim)"
composer install          # yerel geliştirmeye geri dön
```
TUZAK: dev kurulumdan commit'lenen `vendor` artisan'ı sessizce öldürür.
Doğrulama: `git show HEAD:vendor/composer/autoload_files.php | grep -c faker` → 0.
`composer.json` platformu PHP 8.3.33'e sabit; sunucu daha eskiyse değiştirin.

## 3. .env (üretim)

Şablon: `.env.production.example`. Hatırlatmalar: `DB_HOST=localhost` (127.0.0.1 değil), `APP_DEBUG=false`,
`YEDEK_PAROLA` sunucu dışında da saklanmalı. `env()` yalnız `config/` içinde kullanılıyor (önbellekte boş dönmesin).

## 4. Kurulumdan hemen sonra

- `https://ALANADI/balkan_app/.env` → **403** olmalı (değilse hemen düzeltin, APP_KEY ve DB parolasını yenileyin)
- `/admin` → `ILK_YONETICI_EPOSTA` ile giriş → `.env`'den `ILK_YONETICI_PAROLA` satırını silin
- Panel → Site Ayarları: firma bilgileri, iletişim kişileri, bildirim e-postası
- Panel → Konumlar: mahalleler (ilçeler hazır)

## 5. SSH varsa ek araçlar

```bash
php artisan balkan:yonetici eposta@ALAN.ADI --ad="Ad Soyad"   # yönetici aç / parola sıfırla (bir kez gösterir)
php artisan down / up                                         # bakım kipi
```

## 6. Kurulum sonrası kontrol listesi

- [ ] `/` , `/emlak`, bir ilan, `/site-yonetimi`, `/yonetim-teklifi` 200
- [ ] `/admin` giriş; yönetici tüm menüleri görüyor
- [ ] `/robots.txt` canlıda `Disallow: /admin` + sitemap satırı veriyor; `/sitemap.xml` açılıyor
- [ ] Panel → Site Ayarları: firma bilgileri, iletişim kişileri, bildirim e-postası girildi
- [ ] Bir test başvurusu → panelde "Başvurular"a düştü, bildirim e-postası geldi
- [ ] İlan fotoğrafı yükle → sitede görünüyor (medya diski yazılabilir)
- [ ] Özel belge yükle → doğrudan URL ile erişilemiyor, panelden indiriliyor
- [ ] HTTPS zorunlu (cPanel → Force HTTPS), SSL geçerli
- [ ] `php artisan balkan:yedek` çalıştı; `php artisan balkan:yedek-dogrula` "✓ Geri yükleme doğrulandı" dedi (şartname 17)
- [ ] `storage/app/yedekler` içeriği düzenli olarak sunucu DIŞINA kopyalanıyor (cPanel yedeği / indirme); YEDEK_PAROLA ayrı yerde saklı

## 7. Yedek ve geri yükleme

```bash
php artisan balkan:yedek                       # veritabanı + özel belgeler + medya → storage/app/yedekler/*.zip (AES-256)
php artisan balkan:yedek-dogrula [dosya]       # geçici veritabanına geri yükler; satır sayısı, mali toplamlar, dosya özetleri
php artisan balkan:geri-yukle DOSYA --evet     # CANLI veriyi yedektekiyle değiştirir; önce doğrular ve güvenlik yedeği alır
```

Yedek mysqldump gerektirmez. Doğrulama sonucu panelde İşlem Geçmişi'ne düşer. Parolası kaybolan yedek açılamaz.

## 8. Güncelleme

Bkz. 2. bölüm "Her yayın". Göçler deploy'da otomatik çalışır.
