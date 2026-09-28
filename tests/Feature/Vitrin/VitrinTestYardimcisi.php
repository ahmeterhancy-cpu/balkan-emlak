<?php

namespace Tests\Feature\Vitrin;

use App\Enums\Rol;
use App\Models\Konum;
use App\Models\Portfoy;
use App\Models\PortfoyMedya;
use App\Models\User;
use Database\Seeders\IcerikSeeder;
use Database\Seeders\KonumSeeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/** Vitrin testlerinin ortak kurulumu: konumlar, içerik iskeleti, ilan üretici. */
trait VitrinTestYardimcisi
{
    protected function iskeletKur(): void
    {
        Storage::fake('medya');
        $this->seed([KonumSeeder::class, IcerikSeeder::class]);
    }

    protected function danisman(array $alanlar = []): User
    {
        return User::factory()->create($alanlar + ['rol' => Rol::EmlakDanismani, 'telefon' => '0532 000 00 01']);
    }

    /** @param array<string, mixed> $alanlar */
    protected function ilan(array $alanlar = [], bool $foto = true): Portfoy
    {
        $il = Konum::where('tur', 'il')->first();
        $ilce = Konum::where('tur', 'ilce')->where('ust_id', $il->id)->first();

        $p = Portfoy::create($alanlar + [
            'baslik' => 'Test dairesi '.fake()->unique()->numberBetween(1, 99999),
            'islem' => 'satilik',
            'kategori' => 'konut',
            'tip' => 'daire',
            'il_id' => $il->id,
            'ilce_id' => $ilce->id,
            'fiyat_kurus' => 3_000_000_00,
            'para_birimi' => 'TRY',
            'brut_m2' => 120,
            'net_m2' => 100,
            'oda' => '3+1',
            'aciklama' => '<p>Güneş alan, bakımlı ve merkezi konumda geniş bir daire.</p>',
            'yayin_durumu' => 'yayinda',
            'operasyon_durumu' => 'aktif',
            'danisman_id' => $this->danisman()->id,
            'acik_adres' => 'GIZLI-ACIK-ADRES Sokak No 7',
            'ic_notlar' => 'GIZLI-IC-NOT',
            'komisyon_anlasmasi' => 'GIZLI-KOMISYON',
        ]);

        if ($foto) {
            Storage::disk('medya')->put('portfoyler/t-'.$p->id.'.jpg', 'x');
            PortfoyMedya::create(['portfoy_id' => $p->id, 'yol' => 'portfoyler/t-'.$p->id.'.jpg', 'kapak' => true]);
        }

        return $p;
    }

    /** Spam korumasını geçen form alanları (form 10 sn önce açılmış gibi). */
    protected function formKorumasi(): array
    {
        return ['form_zamani' => Crypt::encryptString((string) now()->subSeconds(10)->timestamp), 'web_sitesi' => ''];
    }
}
