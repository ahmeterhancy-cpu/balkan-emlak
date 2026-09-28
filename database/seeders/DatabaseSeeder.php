<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Kurulum sırası. `IcerikSeeder` ve `KonumSeeder` canlıda da çalışabilir
 * (gerçek içerik iskeleti). `Demo*` tohumları yalnız geliştirme / tanıtım
 * içindir: ilanlar, fiyatlar ve fotoğraflar GERÇEK DEĞİLDİR.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            KonumSeeder::class,
            KullaniciSeeder::class,
            IcerikSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call([
                DemoPortfoySeeder::class,
                DemoTalepSeeder::class,
            ]);
        }
    }
}
