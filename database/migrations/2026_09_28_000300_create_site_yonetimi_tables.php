<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site ve apartman yönetimi (şartname 8).
 *
 * Mali kurallar şemada:
 *  - Tutarlar kuruş, İŞARETLİ tam sayı. Tahsilat +, iade −; dağıtım satırı +/−;
 *    hesap hareketi giriş +, çıkış −. Silme yok: düzeltme karşı kayıtla yapılır.
 *  - Siteye bağlı her kayıtta site_id var; ilişkili kayıtların aynı siteye ait
 *    olduğu servis katmanında denetlenir (App\Services\SiteFinans\*).
 *  - Aynı borçlandırma iki kez kesinleşemez: borclandirmalar(site, dönem, tür,
 *    etiket) ve borclar(borclandirma, bölüm) benzersiz.
 *  - Şirketin emlak komisyonları (komisyonlar) ile site paraları ayrı tablolarda;
 *    hiçbir rapor ikisini birleştirmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siteler', function (Blueprint $table) {
            $table->id();
            $table->string('ad');
            $table->string('tur', 10)->default('site'); // site | apartman
            $table->text('adres')->nullable();
            $table->foreignId('il_id')->nullable()->constrained('konumlar')->nullOnDelete();
            $table->foreignId('ilce_id')->nullable()->constrained('konumlar')->nullOnDelete();
            $table->date('yonetim_baslangic')->nullable();
            $table->date('yonetim_bitis')->nullable();
            $table->foreignId('sorumlu_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('iletisim_kisi')->nullable();
            $table->string('iletisim_telefon', 30)->nullable();
            $table->string('para_birimi', 3)->default('TRY');
            $table->unsignedTinyInteger('varsayilan_vade_gunu')->default(10);
            // Bu tutarın üstündeki gider şirket yöneticisi onayı ister (null = sınırsız yetki yok, her gider onaylı rolden geçer)
            $table->unsignedBigInteger('onay_limiti_kurus')->nullable();
            // Bu aya kadar (dahil) dönemler kapalı: "2026-08"
            $table->string('kapali_donem', 7)->nullable();
            $table->text('notlar')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        // Kullanıcı ↔ site yetkisi (site yöneticisi, muhasebe, saha personeli)
        Schema::create('site_kullanici', function (Blueprint $table) {
            $table->foreignId('site_id')->constrained('siteler')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['site_id', 'user_id']);
        });

        Schema::create('bloklar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('siteler')->cascadeOnDelete();
            $table->string('ad', 60);
            $table->unsignedSmallInteger('sira')->default(0);
            $table->timestamps();
            $table->unique(['site_id', 'ad']);
        });

        Schema::create('bagimsiz_bolumler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('siteler')->cascadeOnDelete();
            $table->foreignId('blok_id')->nullable()->constrained('bloklar')->nullOnDelete();
            $table->string('no', 20);
            $table->string('kat', 20)->nullable();
            $table->string('tur', 15)->default('daire'); // daire | dukkan | ofis | depo | otopark | diger
            $table->decimal('brut_m2', 9, 2)->nullable();
            $table->unsignedInteger('arsa_payi')->nullable();      // pay
            $table->unsignedInteger('arsa_payi_payda')->nullable(); // payda
            $table->boolean('aktif')->default(true);
            $table->text('notlar')->nullable();
            $table->timestamps();
            $table->unique(['site_id', 'blok_id', 'no']);
        });

        // Malik / kiracı ilişkisi — tarihli; taşınmada eski kayıt kapanır, üzerine yazılmaz
        Schema::create('bolum_iliskileri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('siteler')->cascadeOnDelete();
            $table->foreignId('bagimsiz_bolum_id')->constrained('bagimsiz_bolumler')->cascadeOnDelete();
            $table->foreignId('kisi_id')->constrained('kisiler')->restrictOnDelete();
            $table->string('rol', 10); // malik | kiraci
            $table->unsignedSmallInteger('hisse_pay')->nullable();   // çoklu malikte
            $table->unsignedSmallInteger('hisse_payda')->nullable();
            $table->date('baslangic');
            $table->date('bitis')->nullable();
            $table->boolean('borc_sorumlusu')->default(false);
            $table->text('notlar')->nullable();
            $table->timestamps();
            $table->index(['bagimsiz_bolum_id', 'baslangic']);
        });

        Schema::create('hesaplar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('siteler')->cascadeOnDelete();
            $table->string('ad');
            $table->string('tur', 10); // kasa | banka
            $table->string('banka_adi')->nullable();
            $table->string('iban', 40)->nullable();
            $table->string('para_birimi', 3)->default('TRY');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        // Borçlandırma işi (toplu tahakkuk). Önizleme → kesin.
        Schema::create('borclandirmalar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('siteler')->cascadeOnDelete();
            $table->string('donem', 7);           // 2026-09
            $table->string('tur', 20);            // aidat | ek_aidat | gider_payi | demirbas | diger
            $table->string('etiket', 60)->default(''); // aynı dönemde ikinci ek aidat için ayırıcı
            $table->string('aciklama');
            $table->date('vade');
            $table->string('dagitim', 12);        // sabit | m2 | arsa_payi | ozel
            $table->unsignedBigInteger('birim_kurus')->nullable();   // sabit: bölüm başı
            $table->unsignedBigInteger('toplam_kurus')->nullable();  // m2 / arsa payı: dağıtılacak toplam
            $table->json('ozel_tutarlar')->nullable();               // ozel: {bolum_id: kurus}
            $table->json('bolum_turleri')->nullable();               // yalnız bu türler (null = hepsi)
            $table->string('para_birimi', 3)->default('TRY');
            $table->string('durum', 10)->default('taslak'); // taslak | kesin | iptal
            $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('kesinlestiren_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('kesinlesme_zamani')->nullable();
            $table->timestamps();
            $table->unique(['site_id', 'donem', 'tur', 'etiket']);
        });

        Schema::create('borclar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('siteler')->cascadeOnDelete();
            $table->foreignId('bagimsiz_bolum_id')->constrained('bagimsiz_bolumler')->restrictOnDelete();
            $table->foreignId('kisi_id')->nullable()->constrained('kisiler')->nullOnDelete(); // borç sorumlusu (o tarihteki)
            $table->foreignId('borclandirma_id')->nullable()->constrained('borclandirmalar')->restrictOnDelete();
            // Düzeltme / iptal karşı kaydı: işaretli tutar, asıl borca bağlı
            $table->foreignId('duzeltilen_borc_id')->nullable()->constrained('borclar')->restrictOnDelete();
            $table->string('tur', 20);
            $table->string('donem', 7);
            $table->string('aciklama');
            $table->date('vade');
            $table->bigInteger('tutar_kurus');
            $table->string('para_birimi', 3)->default('TRY');
            $table->string('gerekce')->nullable(); // düzeltmede zorunlu
            $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['borclandirma_id', 'bagimsiz_bolum_id']);
            $table->index(['site_id', 'donem']);
            $table->index(['bagimsiz_bolum_id', 'vade']);
        });

        Schema::create('tahsilatlar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('siteler')->cascadeOnDelete();
            $table->foreignId('hesap_id')->constrained('hesaplar')->restrictOnDelete();
            $table->foreignId('bagimsiz_bolum_id')->constrained('bagimsiz_bolumler')->restrictOnDelete();
            $table->foreignId('kisi_id')->nullable()->constrained('kisiler')->nullOnDelete(); // ödeyen
            $table->string('tur', 10)->default('tahsilat'); // tahsilat | iade
            $table->foreignId('iade_edilen_id')->nullable()->constrained('tahsilatlar')->restrictOnDelete();
            $table->date('tarih');
            $table->bigInteger('tutar_kurus'); // tahsilat +, iade −
            $table->string('para_birimi', 3)->default('TRY');
            $table->string('yontem', 15); // nakit | havale | eft | pos | diger
            $table->string('aciklama')->nullable();
            $table->string('belge_yolu')->nullable(); // dekont (özel disk)
            $table->boolean('dogrulandi')->default(true); // dekontla gelen, doğrulanana kadar false
            $table->string('gerekce')->nullable();
            $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['bagimsiz_bolum_id', 'tarih']);
        });

        // Tahsilat → borç dağıtımı. İade eksi satırla geri alınır.
        Schema::create('tahsilat_dagitimlari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahsilat_id')->constrained('tahsilatlar')->restrictOnDelete();
            $table->foreignId('borc_id')->constrained('borclar')->restrictOnDelete();
            $table->bigInteger('tutar_kurus');
            $table->date('tarih');
            $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index('borc_id');
            $table->index('tahsilat_id');
        });

        Schema::create('tedarikciler', function (Blueprint $table) {
            $table->id();
            $table->string('ad');
            $table->string('kategori', 30)->nullable();
            $table->string('vergi_no', 20)->nullable();
            $table->string('telefon', 30)->nullable();
            $table->string('eposta')->nullable();
            $table->string('iban', 40)->nullable();
            $table->text('notlar')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('giderler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('siteler')->cascadeOnDelete();
            $table->foreignId('tedarikci_id')->nullable()->constrained('tedarikciler')->nullOnDelete();
            $table->string('kategori', 20);
            $table->date('belge_tarihi');
            $table->string('belge_no', 40)->nullable();
            $table->string('donem', 7);
            $table->date('vade')->nullable();
            $table->unsignedBigInteger('tutar_kurus');
            $table->string('para_birimi', 3)->default('TRY');
            $table->string('aciklama');
            $table->string('belge_yolu')->nullable();
            $table->string('onay_durumu', 15)->default('taslak'); // taslak | onay_bekliyor | onaylandi | reddedildi
            $table->foreignId('onaylayan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('onay_zamani')->nullable();
            $table->string('red_gerekcesi')->nullable();
            $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['site_id', 'donem']);
        });

        Schema::create('gider_odemeleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gider_id')->constrained('giderler')->restrictOnDelete();
            $table->foreignId('hesap_id')->constrained('hesaplar')->restrictOnDelete();
            $table->date('tarih');
            $table->bigInteger('tutar_kurus'); // ödeme +, geri alma −
            $table->string('aciklama')->nullable();
            $table->foreignId('iptal_edilen_id')->nullable()->constrained('gider_odemeleri')->restrictOnDelete();
            $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('transferler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('siteler')->cascadeOnDelete();
            $table->foreignId('kaynak_hesap_id')->constrained('hesaplar')->restrictOnDelete();
            $table->foreignId('hedef_hesap_id')->constrained('hesaplar')->restrictOnDelete();
            $table->date('tarih');
            $table->unsignedBigInteger('tutar_kurus');
            $table->string('aciklama')->nullable();
            $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Kasa/banka defteri: bakiye = SUM(tutar). Her para hareketi buraya bir (transferde iki) satır yazar.
        Schema::create('hesap_hareketleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('siteler')->cascadeOnDelete();
            $table->foreignId('hesap_id')->constrained('hesaplar')->restrictOnDelete();
            $table->date('tarih');
            $table->bigInteger('tutar_kurus');
            $table->string('tur', 20); // acilis | tahsilat | tahsilat_iade | gider_odemesi | transfer | duzeltme
            $table->nullableMorphs('kaynak');
            $table->string('aciklama')->nullable();
            $table->string('dayanak')->nullable(); // açılış bakiyesi / düzeltme belgesi
            $table->boolean('mutabik')->default(false);
            $table->date('mutabakat_tarihi')->nullable();
            $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['hesap_id', 'tarih']);
        });

        /* ---------- Operasyon ---------- */

        Schema::create('varliklar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('siteler')->cascadeOnDelete();
            $table->foreignId('blok_id')->nullable()->constrained('bloklar')->nullOnDelete();
            $table->string('ad');
            $table->string('tur', 20); // asansor | hidrofor | yangin | jenerator | bahce | havuz | diger
            $table->string('marka_model')->nullable();
            $table->text('notlar')->nullable();
            $table->timestamps();
        });

        Schema::create('bakim_planlari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('siteler')->cascadeOnDelete();
            $table->foreignId('varlik_id')->nullable()->constrained('varliklar')->nullOnDelete();
            $table->string('ad');
            $table->unsignedSmallInteger('periyot_gun');
            $table->date('son_bakim')->nullable();
            $table->date('sonraki_bakim');
            $table->foreignId('tedarikci_id')->nullable()->constrained('tedarikciler')->nullOnDelete();
            $table->foreignId('sorumlu_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('is_emirleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('siteler')->cascadeOnDelete();
            $table->foreignId('blok_id')->nullable()->constrained('bloklar')->nullOnDelete();
            $table->foreignId('bagimsiz_bolum_id')->nullable()->constrained('bagimsiz_bolumler')->nullOnDelete();
            $table->foreignId('bakim_plani_id')->nullable()->constrained('bakim_planlari')->nullOnDelete();
            $table->string('konu');
            $table->text('aciklama')->nullable();
            $table->json('fotograflar')->nullable(); // özel disk yolları
            $table->string('bildiren_ad')->nullable();
            $table->string('bildiren_telefon', 30)->nullable();
            $table->string('oncelik', 10)->default('normal'); // dusuk | normal | yuksek | acil
            $table->string('durum', 15)->default('acik'); // acik | atandi | islemde | dis_hizmet | tamamlandi | kapatildi
            $table->foreignId('atanan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tedarikci_id')->nullable()->constrained('tedarikciler')->nullOnDelete();
            $table->date('hedef_tarih')->nullable();
            $table->text('yapilan_islem')->nullable();
            $table->json('tamamlanma_fotograflari')->nullable();
            $table->foreignId('gider_id')->nullable()->constrained('giderler')->nullOnDelete();
            $table->timestamp('tamamlanma_zamani')->nullable();
            $table->foreignId('kapatan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('kapanma_zamani')->nullable();
            $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['site_id', 'durum']);
        });

        Schema::create('gorevler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('siteler')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // personel
            $table->string('baslik');
            $table->text('aciklama')->nullable();
            $table->date('tarih');
            $table->string('durum', 12)->default('bekliyor'); // bekliyor | tamamlandi | iptal
            $table->text('tamamlanma_notu')->nullable();
            $table->timestamp('tamamlanma_zamani')->nullable();
            $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'tarih']);
        });

        Schema::create('duyurular', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('siteler')->cascadeOnDelete();
            $table->foreignId('blok_id')->nullable()->constrained('bloklar')->nullOnDelete();
            $table->string('baslik');
            $table->text('icerik');
            $table->string('durum', 10)->default('taslak'); // taslak | yayinda | arsiv
            $table->timestamp('yayin_tarihi')->nullable();
            $table->date('bitis_tarihi')->nullable();
            $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Yönetim planı, karar, tutanak, sözleşme... Sürümlü: aynı `grup` altında artan `surum`
        Schema::create('site_belgeleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('siteler')->cascadeOnDelete();
            $table->string('kategori', 20);
            $table->string('ad');
            $table->uuid('grup');
            $table->unsignedSmallInteger('surum')->default(1);
            $table->string('yol');
            $table->string('orijinal_ad')->nullable();
            $table->string('erisim', 12)->default('yonetim'); // yonetim | muhasebe | herkes (panel içi)
            $table->date('belge_tarihi')->nullable();
            $table->foreignId('yukleyen_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['grup', 'surum']);
        });
    }

    public function down(): void
    {
        foreach (['site_belgeleri', 'duyurular', 'gorevler', 'is_emirleri', 'bakim_planlari', 'varliklar',
            'hesap_hareketleri', 'transferler', 'gider_odemeleri', 'giderler', 'tedarikciler',
            'tahsilat_dagitimlari', 'tahsilatlar', 'borclar', 'borclandirmalar', 'hesaplar',
            'bolum_iliskileri', 'bagimsiz_bolumler', 'bloklar', 'site_kullanici', 'siteler'] as $tablo) {
            Schema::dropIfExists($tablo);
        }
    }
};
