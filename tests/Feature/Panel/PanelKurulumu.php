<?php

namespace Tests\Feature\Panel;

use App\Enums\Islem;
use App\Enums\Kategori;
use App\Enums\YayinDurumu;
use App\Models\Kisi;
use App\Models\Konum;
use App\Models\Portfoy;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

/** Panel testleri için ortak kurulum ve yardımcılar. */
trait PanelKurulumu
{
    use RefreshDatabase;

    protected Konum $il;

    protected Konum $ilce;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->il = Konum::create(['tur' => 'il', 'ad' => 'Edirne']);
        $this->ilce = Konum::create(['tur' => 'ilce', 'ad' => 'Merkez', 'ust_id' => $this->il->id]);
    }

    protected function kullanici(string $rol, array $ek = []): User
    {
        return User::factory()->create(['rol' => $rol, ...$ek]);
    }

    protected function yonetici(): User
    {
        return $this->kullanici('sistem_yoneticisi');
    }

    protected function danisman(): User
    {
        return $this->kullanici('emlak_danismani');
    }

    /** Yayına hazır sayılabilecek (fotoğraf hariç) portföy. */
    protected function portfoy(array $ek = []): Portfoy
    {
        return Portfoy::create([
            'baslik' => 'Şükrüpaşa\'da 3+1 daire',
            'islem' => Islem::Satilik,
            'kategori' => Kategori::Konut,
            'tip' => 'daire',
            'il_id' => $this->il->id,
            'ilce_id' => $this->ilce->id,
            'fiyat_kurus' => 465_000_000,
            'para_birimi' => 'TRY',
            'brut_m2' => 150,
            'net_m2' => 132,
            'oda' => '3+1',
            'aciklama' => '<p>Site içinde, güneş alan cephede geniş 3+1 daire. Okula ve toplu taşımaya yakın.</p>',
            'yayin_durumu' => YayinDurumu::Taslak,
            'mulk_sahibi_id' => Kisi::create(['ad_soyad' => 'Ayşe Sahibi', 'telefon' => '0555 111 22 33'])->id,
            ...$ek,
        ]);
    }
}
