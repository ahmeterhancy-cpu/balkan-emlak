<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emlak modülü: konum ağacı, kişiler (mülk sahibi / müşteri), portföyler,
 * medya, fiyat geçmişi, özel belgeler, talepler, randevular, komisyonlar.
 *
 * Para: kuruş cinsinden tam sayı (bigInteger). Ondalık kayması yok.
 * Operasyon durumu ile yayın durumu AYRI sütunlardır (şartname 7.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        // İl → ilçe → mahalle. Filtreler ve formlar aynı ağaçtan beslenir.
        Schema::create('konumlar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ust_id')->nullable()->constrained('konumlar')->cascadeOnDelete();
            $table->string('tur', 10); // il | ilce | mahalle
            $table->string('ad');
            $table->string('slug');
            $table->unsignedSmallInteger('sira')->default(0);
            $table->timestamps();

            $table->unique(['ust_id', 'slug']);
            $table->index(['tur', 'ad']);
        });

        // Mülk sahipleri ve müşteriler. Herkese açık hiçbir yerde gösterilmez.
        Schema::create('kisiler', function (Blueprint $table) {
            $table->id();
            $table->string('tur', 10)->default('bireysel'); // bireysel | kurum
            $table->string('ad_soyad');
            $table->string('telefon', 30)->nullable()->index();
            $table->string('eposta')->nullable()->index();
            $table->string('iletisim_tercihi', 20)->nullable(); // telefon | whatsapp | eposta
            $table->boolean('pazarlama_izni')->default(false);
            $table->text('notlar')->nullable();
            $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('portfoyler', function (Blueprint $table) {
            $table->id();
            $table->string('portfoy_no', 20)->unique(); // BLK-00001
            $table->string('baslik');
            $table->string('slug')->unique();

            $table->string('islem', 10);      // satilik | kiralik
            $table->string('kategori', 10);   // konut | arsa | ticari
            $table->string('tip', 30);        // daire, mustakil, villa, arsa, tarla, dukkan, ofis...

            // Konum — il/ilçe/mahalle herkese açık; açık adres ve tam nokta ÖZEL
            $table->foreignId('il_id')->nullable()->constrained('konumlar')->nullOnDelete();
            $table->foreignId('ilce_id')->nullable()->constrained('konumlar')->nullOnDelete();
            $table->foreignId('mahalle_id')->nullable()->constrained('konumlar')->nullOnDelete();
            $table->text('acik_adres')->nullable();
            $table->decimal('enlem', 10, 7)->nullable();
            $table->decimal('boylam', 10, 7)->nullable();
            $table->string('harita_gosterimi', 10)->default('yok'); // yok | yaklasik | tam
            $table->boolean('adres_goster')->default(false);

            // Ticari
            $table->unsignedBigInteger('fiyat_kurus');
            $table->string('para_birimi', 3)->default('TRY');
            $table->unsignedBigInteger('depozito_kurus')->nullable();
            $table->unsignedBigInteger('aidat_kurus')->nullable();

            // Fiziksel
            $table->unsignedInteger('brut_m2')->nullable();
            $table->unsignedInteger('net_m2')->nullable();
            $table->string('oda', 10)->nullable();          // 3+1
            $table->unsignedTinyInteger('banyo')->nullable();
            $table->string('kat', 20)->nullable();          // "Bahçe katı", "3"
            $table->unsignedTinyInteger('kat_sayisi')->nullable();
            $table->unsignedSmallInteger('bina_yasi')->nullable();
            $table->string('isitma', 30)->nullable();
            $table->boolean('asansor')->nullable();
            $table->string('otopark', 20)->nullable();
            $table->boolean('balkon')->nullable();
            $table->boolean('esyali')->nullable();
            $table->boolean('site_icinde')->nullable();
            $table->json('ozellikler')->nullable();        // etiket listesi: "Güvenlik", "Havuz"...

            // Arsa
            $table->string('ada', 20)->nullable();
            $table->string('parsel', 20)->nullable();
            $table->string('imar_durumu')->nullable();

            // İçerik
            $table->text('aciklama')->nullable();
            $table->string('video_url')->nullable();
            $table->string('seo_baslik')->nullable();
            $table->string('seo_aciklama', 300)->nullable();

            // Durumlar
            $table->string('operasyon_durumu', 15)->default('aktif');
            $table->string('yayin_durumu', 15)->default('taslak');
            $table->timestamp('yayin_tarihi')->nullable();
            $table->boolean('one_cikan')->default(false);

            // İlişkiler
            $table->foreignId('danisman_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('mulk_sahibi_id')->nullable()->constrained('kisiler')->nullOnDelete();

            // İç bilgiler — asla herkese açık veriye girmez
            $table->text('ic_notlar')->nullable();
            $table->string('komisyon_anlasmasi')->nullable();

            $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('guncelleyen_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['yayin_durumu', 'islem', 'kategori']);
            $table->index(['yayin_durumu', 'one_cikan']);
        });

        Schema::create('portfoy_medya', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfoy_id')->constrained('portfoyler')->cascadeOnDelete();
            $table->string('yol');
            $table->string('tur', 10)->default('foto'); // foto | plan
            $table->string('alt_metin')->nullable();
            $table->boolean('kapak')->default(false);
            $table->unsignedSmallInteger('sira')->default(0);
            $table->timestamps();

            $table->index(['portfoy_id', 'sira']);
        });

        Schema::create('fiyat_gecmisi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfoy_id')->constrained('portfoyler')->cascadeOnDelete();
            $table->unsignedBigInteger('eski_fiyat_kurus')->nullable();
            $table->unsignedBigInteger('yeni_fiyat_kurus');
            $table->string('para_birimi', 3);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // Özel belgeler (yetki belgesi, tapu, sözleşme). Disk: local (storage/app/private)
        Schema::create('portfoy_belgeleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfoy_id')->constrained('portfoyler')->cascadeOnDelete();
            $table->string('tur', 20); // yetki | tapu | sozlesme | diger
            $table->string('ad');
            $table->string('yol');
            $table->string('orijinal_ad')->nullable();
            $table->date('gecerlilik_bitis')->nullable();
            $table->foreignId('yukleyen_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        /*
         * Web formlarından gelen başvurular ve panelde açılan müşteri talepleri
         * tek tabloda: iletişim, ilan bilgisi/randevu, portföy başvurusu,
         * yönetim teklifi. Forma özgü alanlar `detay` JSON'unda.
         */
        Schema::create('talepler', function (Blueprint $table) {
            $table->id();
            $table->string('tur', 20)->index();
            $table->string('kaynak', 20)->default('web'); // web | telefon | ofis | referans | ilan_sitesi | diger
            $table->string('ad_soyad');
            $table->string('telefon', 30)->nullable()->index();
            $table->string('eposta')->nullable()->index();
            $table->string('konu')->nullable();
            $table->text('mesaj')->nullable();
            $table->foreignId('portfoy_id')->nullable()->constrained('portfoyler')->nullOnDelete();
            $table->foreignId('kisi_id')->nullable()->constrained('kisiler')->nullOnDelete();
            $table->timestamp('tercih_edilen_zaman')->nullable();
            $table->json('detay')->nullable();

            // Müşteri talebi (arayış) alanları
            $table->string('aranan_islem', 10)->nullable();
            $table->string('aranan_kategori', 10)->nullable();
            $table->unsignedBigInteger('butce_min_kurus')->nullable();
            $table->unsignedBigInteger('butce_max_kurus')->nullable();
            $table->string('aranan_konum')->nullable();

            $table->string('durum', 20)->default('yeni')->index();
            $table->foreignId('atanan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('sonraki_takip')->nullable();
            $table->string('sonuc', 20)->nullable(); // kabul | ret | beklemede (yönetim teklifi)

            $table->boolean('kvkk_onay')->default(false);
            $table->boolean('pazarlama_izni')->default(false);
            $table->string('ip', 45)->nullable();
            $table->string('tarayici', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('talep_notlari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('talep_id')->constrained('talepler')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('not');
            $table->timestamps();
        });

        // Talebin ilgilendiği diğer portföyler
        Schema::create('talep_portfoy', function (Blueprint $table) {
            $table->foreignId('talep_id')->constrained('talepler')->cascadeOnDelete();
            $table->foreignId('portfoy_id')->constrained('portfoyler')->cascadeOnDelete();
            $table->primary(['talep_id', 'portfoy_id']);
        });

        Schema::create('randevular', function (Blueprint $table) {
            $table->id();
            $table->foreignId('talep_id')->nullable()->constrained('talepler')->nullOnDelete();
            $table->foreignId('portfoy_id')->nullable()->constrained('portfoyler')->nullOnDelete();
            $table->foreignId('danisman_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('musteri_adi');
            $table->string('musteri_telefon', 30)->nullable();
            $table->timestamp('baslangic');
            $table->unsignedSmallInteger('sure_dk')->default(60);
            $table->string('durum', 15)->default('planlandi'); // planlandi | tamamlandi | iptal | gelmedi
            $table->text('sonuc_notu')->nullable();
            $table->timestamps();

            $table->index(['danisman_id', 'baslangic']);
        });

        // Şirketin komisyon geliri. Resmi muhasebe belgesi değildir.
        Schema::create('komisyonlar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfoy_id')->nullable()->constrained('portfoyler')->nullOnDelete();
            $table->foreignId('danisman_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('taraf', 10)->default('satici'); // satici | alici | kiraci | ev_sahibi
            $table->unsignedBigInteger('beklenen_kurus');
            $table->unsignedBigInteger('gerceklesen_kurus')->nullable();
            $table->string('para_birimi', 3)->default('TRY');
            $table->date('odeme_tarihi')->nullable();
            $table->string('durum', 15)->default('bekliyor'); // bekliyor | kismi | tahsil_edildi | iptal
            $table->text('aciklama')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['komisyonlar', 'randevular', 'talep_portfoy', 'talep_notlari', 'talepler',
            'portfoy_belgeleri', 'fiyat_gecmisi', 'portfoy_medya', 'portfoyler', 'kisiler', 'konumlar'] as $tablo) {
            Schema::dropIfExists($tablo);
        }
    }
};
