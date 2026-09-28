<?php

namespace Tests\Feature\SitePanel;

use App\Filament\Destek\CsvAktarici;
use App\Filament\Destek\SiteRaporlari;
use App\Filament\Pages\SiteRaporlari\DonemOzeti;
use App\Filament\Pages\SiteRaporlari\GiderOzeti;
use App\Models\IslemKaydi;
use App\Services\SiteFinans\SiteRaporu;
use App\Support\Para;
use Livewire\Livewire;
use Tests\TestCase;

/** Dışa aktarılan raporun filtreleri ve toplamları ekrandakiyle aynıdır (şartname 17). */
class SiteRaporTest extends TestCase
{
    use SitePanelKurulumu;

    /** CSV metnini satır/hücre dizisine çevir. */
    private function csvOku(string $metin): array
    {
        $metin = preg_replace('/^\xEF\xBB\xBF/', '', $metin);

        return array_map(fn ($s) => str_getcsv($s, ';', '"', ''), preg_split('/\r?\n/', trim($metin)));
    }

    private function sayi(string $hucre): int
    {
        return (int) round(((float) str_replace(',', '.', $hucre)) * 100);
    }

    public function test_donem_ozeti_csv_toplamlari_site_raporu_ile_ayni(): void
    {
        $this->giris('muhasebe');

        $rapor = SiteRaporlari::uret('donem-ozeti', ['site_id' => $this->camlik->id, 'donem' => '2026-09']);
        [$baslik, $satirlar] = SiteRaporlari::csvSatirlari($rapor);
        $csv = $this->csvOku(CsvAktarici::metin($baslik, $satirlar));

        $beklenen = app(SiteRaporu::class)->donemOzeti($this->camlik, '2026-09')['TRY'];

        $sutunlar = array_search(['Para birimi', 'Tahakkuk', 'Döneme dağıtılan tahsilat', 'Kalan', 'Tahsilat oranı', 'Geçmiş dönem borçlarına tahsilat', 'Aydaki tahsilat', 'Onaylı gider'], $csv, true);
        $this->assertNotFalse($sutunlar);
        $try = $csv[$sutunlar + 1];

        $this->assertSame('TRY', $try[0]);
        $this->assertSame($beklenen['tahakkuk_kurus'], $this->sayi($try[1]));
        $this->assertSame($beklenen['doneme_dagitilan_kurus'], $this->sayi($try[2]));
        $this->assertSame($beklenen['kalan_kurus'], $this->sayi($try[3]));
        $this->assertSame(round($beklenen['tahsilat_orani'] * 100, 1), (float) str_replace(',', '.', $try[4]));
        $this->assertSame($beklenen['gecmis_donem_tahsilati_kurus'], $this->sayi($try[5]));
        $this->assertSame($beklenen['aydaki_tahsilat_kurus'], $this->sayi($try[6]));
        $this->assertSame($beklenen['onayli_gider_kurus'], $this->sayi($try[7]));
        $this->assertSame(3_600_000, $beklenen['tahakkuk_kurus']);

        // Kasa/banka toplamı (TRY) = bakiyeler toplamı
        $toplamSatiri = collect($csv)->first(fn ($s) => ($s[0] ?? null) === 'Toplam (TRY)');
        $bakiyeler = app(SiteRaporu::class)->bakiyeler($this->camlik)->sum('bakiye_kurus');
        $this->assertSame($bakiyeler, $this->sayi($toplamSatiri[3]));

        // Ekran aynı yapıdan: gösterilen metinler aynı tutarları taşır
        Livewire::test(DonemOzeti::class)
            ->set('filtre.site_id', $this->camlik->id)
            ->set('filtre.donem', '2026-09')
            ->assertSee(Para::bicimle($beklenen['tahakkuk_kurus'], 'TRY'))
            ->assertSee(Para::bicimle($beklenen['aydaki_tahsilat_kurus'], 'TRY'))
            ->assertSee('Site paraları — şirketin emlak komisyon gelirleri bu raporda yer almaz')
            ->assertSee('payda sıfırken oran gösterilmez')
            ->callAction('csv');

        $this->assertTrue(IslemKaydi::where('olay', 'disa_aktarildi')->where('ozet', 'like', 'Site raporu CSV%')->exists());
    }

    public function test_tahakkuk_yoksa_oran_gosterilmez(): void
    {
        $this->giris('muhasebe');
        $rapor = SiteRaporlari::uret('donem-ozeti', ['site_id' => $this->camlik->id, 'donem' => '2026-01']);

        $this->assertNotEmpty($rapor['tablolar'][0]['satirlar']);
        foreach ($rapor['tablolar'][0]['satirlar'] as $s) {
            $this->assertSame('—', SiteRaporlari::metin($s[4]));
        }
    }

    public function test_rapor_sayfalari_ve_yazdirma(): void
    {
        $this->giris('siteyonetici');

        Livewire::test(GiderOzeti::class)
            ->set('filtre.bas_donem', '2026-07')
            ->set('filtre.bit_donem', '2026-09')
            ->assertSee('Asansör')
            ->assertSee('Demo Temizlik Hizmetleri')
            // Site yöneticisinde dışa aktarma izni yok
            ->assertActionHidden('csv');

        foreach (array_keys(SiteRaporlari::RAPORLAR) as $r) {
            $this->get(route('panel.site-rapor.yazdir', ['rapor' => $r, 'site_id' => $this->camlik->id]))
                ->assertOk()->assertSee('window.print()', false);
        }

        $this->giris('saha');
        $this->get(route('panel.site-rapor.yazdir', ['rapor' => 'donem-ozeti', 'site_id' => $this->camlik->id]))->assertForbidden();
        $this->get('/admin/site-raporlari/donem-ozeti')->assertForbidden();
    }
}
