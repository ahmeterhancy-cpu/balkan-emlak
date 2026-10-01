<?php

namespace App\Console\Commands;

use App\Enums\Rol;
use App\Models\Konum;
use App\Models\Sayfa;
use App\Models\User;
use Database\Seeders\IcerikSeeder;
use Database\Seeders\KonumSeeder;
use Illuminate\Console\Command;

/**
 * cPanel deploy'unda her seferinde çalışır; SSH olmadan ilk kurulumu yapar.
 * Tekrar çalıştırmak güvenlidir:
 *  - Konum ağacı ve içerik iskeleti YALNIZ tablolar boşken eklenir
 *    (panelden silinen sayfa geri gelmez).
 *  - İlk sistem yöneticisi .env'deki ILK_YONETICI_EPOSTA / _PAROLA ile
 *    YALNIZ hiç sistem yöneticisi yokken açılır; parolası günlüğe yazılmaz.
 * Demo veri ve *.test hesapları canlıda HİÇ oluşturulmaz.
 */
class Kurulum extends Command
{
    protected $signature = 'balkan:kurulum';

    protected $description = 'Canlı ilk kurulum: temel içerik ve ilk yönetici (tekrar çalıştırmak güvenli)';

    public function handle(): int
    {
        if (Konum::query()->doesntExist()) {
            $this->call('db:seed', ['--class' => KonumSeeder::class, '--force' => true]);
            $this->line('Konum ağacı eklendi.');
        }

        if (Sayfa::query()->doesntExist()) {
            $this->call('db:seed', ['--class' => IcerikSeeder::class, '--force' => true]);
            $this->line('İçerik iskeleti eklendi.');
        }

        if (User::where('rol', Rol::SistemYoneticisi->value)->exists()) {
            $this->line('Sistem yöneticisi zaten var; dokunulmadı.');

            return self::SUCCESS;
        }

        $eposta = mb_strtolower(trim((string) config('app.ilk_yonetici.eposta')));
        $parola = (string) config('app.ilk_yonetici.parola');

        if ($eposta === '' || mb_strlen($parola) < 12) {
            $this->warn('İlk yönetici açılmadı: .env içine ILK_YONETICI_EPOSTA ve en az 12 karakterlik ILK_YONETICI_PAROLA yazın.');

            return self::SUCCESS;
        }

        $yonetici = User::firstOrNew(['email' => $eposta]);
        $yonetici->fill([
            'name' => (string) config('app.ilk_yonetici.ad'),
            'rol' => Rol::SistemYoneticisi,
            'aktif' => true,
            'password' => $parola, // `hashed` cast şifreler
        ]);
        $yonetici->email_verified_at ??= now();
        $yonetici->save();

        $this->info('İlk sistem yöneticisi açıldı ('.$eposta.'). Girişten sonra ILK_YONETICI_PAROLA satırını .env\'den silin.');

        return self::SUCCESS;
    }
}
