<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Web içeriği (sayfalar, hizmetler, bilgi merkezi, referanslar, SSS),
 * site ayarları ve işlem geçmişi.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Hakkımızda, gizlilik, KVKK, çerez... Taslak/yayın akışlı.
        Schema::create('sayfalar', function (Blueprint $table) {
            $table->id();
            $table->string('baslik');
            $table->string('slug')->unique();
            $table->string('ozet', 500)->nullable();
            $table->longText('icerik')->nullable();
            $table->string('grup', 20)->default('kurumsal'); // kurumsal | yasal
            $table->boolean('yayinda')->default(false);
            $table->boolean('inceleme_bekliyor')->default(false); // yasal metin henüz onaylanmadı
            $table->string('seo_baslik')->nullable();
            $table->string('seo_aciklama', 300)->nullable();
            $table->timestamps();
        });

        // Site ve apartman yönetimi hizmetleri (8 hizmet)
        Schema::create('hizmetler', function (Blueprint $table) {
            $table->id();
            $table->string('baslik');
            $table->string('slug')->unique();
            $table->string('ikon', 40)->nullable();
            $table->string('ozet', 400);
            $table->longText('kapsam')->nullable();
            $table->json('surec')->nullable(); // ["Keşif", "Plan", ...]
            $table->string('gorsel')->nullable();
            $table->boolean('yayinda')->default(true);
            $table->unsignedSmallInteger('sira')->default(0);
            $table->string('seo_aciklama', 300)->nullable();
            $table->timestamps();
        });

        // Bilgi merkezi
        Schema::create('yazilar', function (Blueprint $table) {
            $table->id();
            $table->string('baslik');
            $table->string('slug')->unique();
            $table->string('kategori', 20)->default('emlak'); // emlak | yonetim
            $table->string('ozet', 500)->nullable();
            $table->longText('icerik');
            $table->string('gorsel')->nullable();
            $table->string('gorsel_alt')->nullable();
            $table->string('durum', 15)->default('taslak'); // taslak | yayinda | arsiv
            $table->timestamp('yayin_tarihi')->nullable();
            $table->foreignId('yazar_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('seo_aciklama', 300)->nullable();
            $table->timestamps();
        });

        // Yalnız yayımlama izni alınmış referans ve yorumlar
        Schema::create('referanslar', function (Blueprint $table) {
            $table->id();
            $table->string('tur', 10)->default('proje'); // proje | yorum
            $table->string('ad'); // site adı veya yorum sahibi
            $table->string('alt_baslik')->nullable(); // "Site sakini", "120 bağımsız bölüm"
            $table->text('metin')->nullable();
            $table->string('gorsel')->nullable();
            $table->boolean('izin_alindi')->default(false);
            $table->date('izin_tarihi')->nullable();
            $table->boolean('yayinda')->default(false);
            $table->unsignedSmallInteger('sira')->default(0);
            $table->timestamps();
        });

        Schema::create('sss', function (Blueprint $table) {
            $table->id();
            $table->string('grup', 20)->default('genel'); // genel | emlak | yonetim
            $table->string('soru');
            $table->text('cevap');
            $table->boolean('yayinda')->default(true);
            $table->unsignedSmallInteger('sira')->default(0);
            $table->timestamps();
        });

        Schema::create('ayarlar', function (Blueprint $table) {
            $table->id();
            $table->string('anahtar')->unique();
            $table->text('deger')->nullable();
            $table->timestamps();
        });

        // Kim, ne zaman, neyi, önceki/sonraki değer. Silinmez.
        Schema::create('islem_kayitlari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('olay', 30); // olusturuldu | guncellendi | silindi | yayimlandi | disa_aktarildi | belge_indirildi ...
            $table->string('kayit_turu', 60)->nullable();
            $table->unsignedBigInteger('kayit_id')->nullable();
            $table->string('ozet')->nullable();
            $table->json('onceki')->nullable();
            $table->json('sonraki')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['kayit_turu', 'kayit_id']);
        });
    }

    public function down(): void
    {
        foreach (['islem_kayitlari', 'ayarlar', 'sss', 'referanslar', 'yazilar', 'hizmetler', 'sayfalar'] as $tablo) {
            Schema::dropIfExists($tablo);
        }
    }
};
