<?php

namespace App\Console\Commands;

use App\Enums\Rol;
use App\Models\Konum;
use App\Models\Sayfa;
use App\Models\User;
use Database\Seeders\IcerikSeeder;
use Database\Seeders\KonumSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

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

        $eposta = mb_strtolower(trim((string) config('app.ilk_yonetici.eposta')));
        $parola = (string) config('app.ilk_yonetici.parola');

        if (User::where('rol', Rol::SistemYoneticisi->value)->exists()) {
            return $this->parolaSifirla($eposta, $parola);
        }

        if ($eposta === '') {
            $this->warn('İlk yönetici açılmadı: .env içine ILK_YONETICI_EPOSTA yazın.');

            return self::SUCCESS;
        }

        // Parola verilmediyse rastgele üretilir ve YALNIZ bu çıktıda (deploy günlüğü,
        // web dışı) bir kez görünür; ilk girişte değiştirilmeli.
        $uretildi = $parola === '';
        if ($uretildi) {
            $parola = Str::password(16, symbols: false);
        } elseif (mb_strlen($parola) < 12) {
            $this->warn('İlk yönetici açılmadı: ILK_YONETICI_PAROLA en az 12 karakter olmalı (ya da boş bırakın, üretilsin).');

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

        $this->info('İlk sistem yöneticisi açıldı ('.$eposta.').');
        if ($uretildi) {
            $this->line('Parola = '.$parola.'   (bir daha gösterilmez; ilk girişte değiştirin)');
        } else {
            $this->line('Girişten sonra ILK_YONETICI_PAROLA satırını .env dosyasından silin.');
        }

        return self::SUCCESS;
    }

    /**
     * SSH yokken parola kurtarma: .env'de YONETICI_PAROLA_SIFIRLA=true ve
     * ILK_YONETICI_PAROLA (kullanıcının kendi seçtiği, ≥12 karakter) varsa
     * ILK_YONETICI_EPOSTA'daki sistem yöneticisinin parolası buna ayarlanır.
     * Parola hiçbir yere yazılmaz. İki satır silinene kadar her deploy uyarır.
     */
    private function parolaSifirla(string $eposta, string $parola): int
    {
        if (! config('app.ilk_yonetici.sifirla')) {
            $this->line('Sistem yöneticisi zaten var; dokunulmadı.');

            return self::SUCCESS;
        }

        $yonetici = User::where('email', $eposta)->where('rol', Rol::SistemYoneticisi->value)->first();

        if (! $yonetici || mb_strlen($parola) < 12) {
            $this->warn('Parola sıfırlanmadı: ILK_YONETICI_EPOSTA bir sistem yöneticisi olmalı ve ILK_YONETICI_PAROLA en az 12 karakter.');

            return self::SUCCESS;
        }

        $yonetici->forceFill(['password' => $parola, 'aktif' => true])->save();
        $this->info('Yönetici parolası .env\'deki değere ayarlandı ('.$eposta.').');
        $this->warn('ŞİMDİ .env\'den YONETICI_PAROLA_SIFIRLA ve ILK_YONETICI_PAROLA satırlarını silin; yoksa her deploy parolayı yeniden bu değere çeker.');

        return self::SUCCESS;
    }
}
