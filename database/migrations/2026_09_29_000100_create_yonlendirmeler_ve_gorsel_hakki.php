<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Şartname 10: "eski adresler için yönlendirmeler" ve "görsellerde kullanım hakkı bilgisi".
 */
return new class extends Migration
{
    private const GORSEL_TABLOLARI = ['portfoy_medya', 'yazilar', 'hizmetler', 'referanslar'];

    public function up(): void
    {
        Schema::create('yonlendirmeler', function (Blueprint $table) {
            $table->id();
            $table->string('eski_yol', 500)->unique(); // "/ilan.php?id=5" ya da "/ilan/eski-slug"
            $table->string('hedef', 500);             // "/ilan/yeni-slug" ya da tam adres
            $table->unsignedSmallInteger('kod')->default(301);
            $table->boolean('aktif')->default(true);
            $table->boolean('otomatik')->default(false); // başlık değişince sistem açtı
            $table->unsignedInteger('isabet')->default(0);
            $table->timestamp('son_isabet')->nullable();
            $table->string('not')->nullable();
            $table->timestamps();
        });

        foreach (self::GORSEL_TABLOLARI as $tablo) {
            Schema::table($tablo, function (Blueprint $table) {
                $table->string('gorsel_hakki', 15)->nullable();   // kendi | mulk_sahibi | lisansli | bilinmiyor
                $table->string('gorsel_kaynak', 255)->nullable(); // fotoğrafçı, lisans, izin belgesi notu
            });
        }
    }

    public function down(): void
    {
        foreach (self::GORSEL_TABLOLARI as $tablo) {
            Schema::table($tablo, function (Blueprint $table) {
                $table->dropColumn(['gorsel_hakki', 'gorsel_kaynak']);
            });
        }

        Schema::dropIfExists('yonlendirmeler');
    }
};
