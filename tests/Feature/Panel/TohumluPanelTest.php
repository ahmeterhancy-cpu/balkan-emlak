<?php

namespace Tests\Feature\Panel;

use App\Filament\Resources\Kisiler\KisiResource;
use App\Filament\Resources\Portfoyler\PortfoyResource;
use App\Filament\Resources\Talepler\TalepResource;
use App\Models\Kisi;
use App\Models\Portfoy;
use App\Models\Talep;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Demo tohumlarıyla panelin gerçekçi veride açıldığını doğrular. */
class TohumluPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_tohumlu_veride_rollerin_sayfalari_acilir(): void
    {
        Storage::fake('medya');
        $this->seed(DatabaseSeeder::class);
        Filament::setCurrentPanel('admin');

        $yonetici = User::where('email', 'yonetici@balkanemlak.test')->firstOrFail();
        $this->actingAs($yonetici);

        foreach (['/admin', '/admin/portfoyler', '/admin/kisiler', '/admin/randevular', '/admin/komisyonlar', '/admin/konumlar',
            '/admin/talepler', '/admin/sayfalar', '/admin/hizmetler', '/admin/bilgi-merkezi', '/admin/referanslar', '/admin/sss',
            '/admin/kullanicilar', '/admin/islem-gecmisi', '/admin/site-ayarlari'] as $yol) {
            $this->get($yol)->assertOk();
        }

        foreach (Portfoy::all() as $p) {
            $this->get(PortfoyResource::getUrl('edit', ['record' => $p]))->assertOk();
        }
        foreach (Talep::all() as $t) {
            $this->get(TalepResource::getUrl('edit', ['record' => $t]))->assertOk();
        }
        $this->get(KisiResource::getUrl('edit', ['record' => Kisi::first()]))->assertOk();

        $danisman = User::where('email', 'emrullah@balkanemlak.test')->firstOrFail();
        $this->flushSession(); // AuthenticateSession önceki kullanıcının parola özetini tutar
        $this->actingAs($danisman);
        $this->get('/admin/portfoyler')->assertOk();
        $baskasi = Portfoy::where('danisman_id', '!=', $danisman->id)->first();
        if ($baskasi) {
            $this->get(PortfoyResource::getUrl('edit', ['record' => $baskasi]))->assertNotFound();
        }
        $this->get('/admin/komisyonlar')->assertForbidden();

        $this->flushSession();
        $this->actingAs(User::where('email', 'editor@balkanemlak.test')->firstOrFail());
        $this->get('/admin/portfoyler')->assertForbidden();
        $this->get('/admin/sayfalar')->assertOk();
    }
}
