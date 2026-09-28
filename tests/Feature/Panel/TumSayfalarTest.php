<?php

namespace Tests\Feature\Panel;

use App\Models\Borclandirma;
use App\Models\Referans;
use App\Models\Site;
use App\Models\User;
use App\Models\Yonlendirme;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Panelin HER sayfası (kaynak listeleri, oluştur/düzenle/görüntüle, raporlar,
 * ayarlar) tohumlu veride yöneticiyle hatasız açılır. Yeni sayfa eklenince
 * ayrıca listeye yazmak gerekmez.
 */
class TumSayfalarTest extends TestCase
{
    use RefreshDatabase;

    public function test_yonetici_panelin_tum_sayfalarini_acabilir(): void
    {
        Storage::fake('medya');
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
        $this->actingAs(User::where('email', 'yonetici@balkanemlak.test')->firstOrFail());

        // Tohumdaki borçlandırmalar kesinleşmiş; düzenleme formu için bir taslak.
        Borclandirma::create([
            'site_id' => Site::query()->value('id'), 'donem' => '2027-01', 'tur' => 'aidat', 'aciklama' => 'Taslak',
            'vade' => '2027-01-10', 'dagitim' => 'sabit', 'birim_kurus' => 100_00,
        ]);
        // Tohumda hiç olmayanlar (gerçek referans yok, yönlendirme canlıda girilir).
        Referans::create(['ad' => 'Deneme Sitesi']);
        Yonlendirme::create(['eski_yol' => '/eski-sayfa', 'hedef' => '/']);

        $adresler = [];
        foreach ($panel->getPages() as $sayfa) {
            $adresler[$sayfa] = $sayfa::getUrl();
        }

        $kayitsiz = [];
        foreach ($panel->getResources() as $kaynak) {
            $kayit = $kaynak::getModel()::query()->first();
            foreach (array_keys($kaynak::getPages()) as $ad) {
                try {
                    $adresler["$kaynak@$ad"] = $kaynak::getUrl($ad);
                } catch (UrlGenerationException) {
                    if (! $kayit) {
                        $kayitsiz[] = "$kaynak@$ad";

                        continue;
                    }
                    // Kesinleşmiş borçlandırma / onaylı gider bilerek düzenlenemez (403);
                    // düzenleme sayfası için düzenlenebilir bir kayıt seçilir.
                    $hedef = $ad === 'edit'
                        ? $kaynak::getModel()::query()->get()->first(fn ($r) => auth()->user()->can('update', $r)) ?? $kayit
                        : $kayit;
                    $adresler["$kaynak@$ad"] = $kaynak::getUrl($ad, ['record' => $hedef]);
                }
            }
        }

        $hatalar = [];
        foreach ($adresler as $ad => $url) {
            $durum = $this->get($url)->getStatusCode();
            if ($durum !== 200) {
                $hatalar[] = "$durum  $ad  $url";
            }
        }

        $this->assertGreaterThan(40, count($adresler));
        $this->assertSame([], $hatalar, implode("\n", $hatalar));
        $this->assertSame([], $kayitsiz, 'Tohumda kaydı olmayan kaynak sayfası denenemedi: '.implode(', ', $kayitsiz));
    }
}
