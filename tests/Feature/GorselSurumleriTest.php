<?php

namespace Tests\Feature;

use App\Models\Portfoy;
use App\Models\PortfoyMedya;
use App\Support\Gorsel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Vitrin\VitrinTestYardimcisi;
use Tests\TestCase;

/** Şartname 15 (LCP): duyarlı görsel sürümleri ve <picture> çıktısı. */
class GorselSurumleriTest extends TestCase
{
    use RefreshDatabase, VitrinTestYardimcisi;

    public function test_yuklenen_fotografin_webp_ve_boyut_surumleri_uretilir_silinince_hepsi_silinir(): void
    {
        $this->iskeletKur(); // medya diski sahte
        Storage::disk('medya')->put('portfoyler/buyuk.jpg', UploadedFile::fake()->image('buyuk.jpg', 1920, 1280)->getContent());
        $p = $this->ilan([], foto: false);

        $m = PortfoyMedya::create(['portfoy_id' => $p->id, 'yol' => 'portfoyler/buyuk.jpg', 'kapak' => true]);

        foreach (['buyuk-400.jpg', 'buyuk-800.jpg', 'buyuk-1600.jpg', 'buyuk-400.webp', 'buyuk-800.webp', 'buyuk-1600.webp', 'buyuk.webp'] as $f) {
            Storage::disk('medya')->assertExists('portfoyler/'.$f);
        }

        $set = Gorsel::set('portfoyler/buyuk.jpg');
        $this->assertSame([400, 800, 1600], $set['surumler']);
        $this->assertSame(1920, $set['genislik']);

        $m->delete();
        $this->assertSame([], Storage::disk('medya')->files('portfoyler'));
    }

    public function test_kucuk_gorselden_buyuk_surum_uretilmez_bozuk_dosya_sayfayi_dusurmez(): void
    {
        Storage::fake('medya');
        Storage::disk('medya')->put('k/kucuk.png', UploadedFile::fake()->image('kucuk.png', 300, 200)->getContent());
        Storage::disk('medya')->put('k/bozuk.jpg', 'bu bir görsel değil');

        $this->assertSame([], Gorsel::set('k/kucuk.png')['surumler']);
        Storage::disk('medya')->assertMissing('k/kucuk-400.png');

        $bozuk = Gorsel::set('k/bozuk.jpg');
        $this->assertSame([], $bozuk['surumler']);
        $this->assertStringEndsWith('k/bozuk.jpg', $bozuk['asil']);
    }

    public function test_bilesen_picture_webp_srcset_ve_oncelik_yazar(): void
    {
        Storage::fake('medya');
        Storage::disk('medya')->put('a/foto.jpg', UploadedFile::fake()->image('foto.jpg', 1600, 900)->getContent());

        $html = Blade::render('<x-resim yol="a/foto.jpg" alt="Salon" sizes="100vw" :oncelikli="true" class="deneme" />');

        $this->assertStringContainsString('<source type="image/webp"', $html);
        $this->assertStringContainsString('foto-800.webp 800w', $html);
        $this->assertStringContainsString('foto-400.jpg 400w', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringContainsString('width="1600" height="900"', $html); // CLS: boyut bildirilir
        $this->assertStringContainsString('alt="Salon"', $html);
        $this->assertStringNotContainsString('loading="lazy"', $html);

        $tembel = Blade::render('<x-resim yol="a/foto.jpg" />');
        $this->assertStringContainsString('loading="lazy"', $tembel);
    }
}
