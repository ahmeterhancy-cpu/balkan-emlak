<?php

namespace App\Console\Commands;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Canlıda ilk sistem yöneticisini açmak (ya da parolasını sıfırlamak)
 * için. Parola rastgele üretilir ve YALNIZ BİR KEZ ekrana yazılır;
 * hiçbir yere kaydedilmez.
 */
class YoneticiOlustur extends Command
{
    protected $signature = 'balkan:yonetici {email : Yöneticinin e-posta adresi} {--ad=Yönetici : Görünen ad}';

    protected $description = 'Sistem yöneticisi oluşturur ya da günceller; rastgele parolayı bir kez yazar';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => 'required|email'])->fails()) {
            $this->error('Geçerli bir e-posta adresi girin.');

            return self::FAILURE;
        }

        $parola = Str::password(16, symbols: false);
        $kullanici = User::firstOrNew(['email' => $email]);
        $yeni = ! $kullanici->exists;

        $kullanici->fill([
            'name' => $yeni ? (string) $this->option('ad') : ($kullanici->name ?: (string) $this->option('ad')),
            'rol' => Rol::SistemYoneticisi,
            'aktif' => true,
            'password' => $parola, // `hashed` cast şifreler
        ]);
        $kullanici->email_verified_at ??= now();
        $kullanici->save();

        $this->info($yeni ? 'Sistem yöneticisi oluşturuldu.' : 'Mevcut kullanıcı sistem yöneticisi yapıldı ve parolası yenilendi.');
        $this->line('E-posta : '.$email);
        $this->line('Parola  : '.$parola);
        $this->warn('Bu parola bir daha gösterilmez; güvenli bir yere not edin ve ilk girişte değiştirin.');

        return self::SUCCESS;
    }
}
