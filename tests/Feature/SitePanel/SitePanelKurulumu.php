<?php

namespace Tests\Feature\SitePanel;

use App\Models\Site;
use App\Models\User;
use Database\Seeders\DemoSiteSeeder;
use Database\Seeders\KonumSeeder;
use Database\Seeders\KullaniciSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Site paneli testleri: demo site verisi (iki site, üç aylık aidat,
 * tahsilat, gider, iş emri) bellek içi veritabanına tohumlanır.
 */
trait SitePanelKurulumu
{
    use RefreshDatabase;

    protected Site $camlik;

    protected Site $yildiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([KonumSeeder::class, KullaniciSeeder::class, DemoSiteSeeder::class]);
        Filament::setCurrentPanel('admin');

        $this->camlik = Site::where('ad', 'like', 'Çamlık%')->firstOrFail();
        $this->yildiz = Site::where('ad', 'like', 'Yıldız%')->firstOrFail();
    }

    protected function giris(string $eposta): User
    {
        $this->flushSession(); // AuthenticateSession önceki kullanıcının parola özetini tutar
        $k = User::where('email', $eposta.'@balkanemlak.test')->firstOrFail();
        $this->actingAs($k);

        return $k;
    }
}
