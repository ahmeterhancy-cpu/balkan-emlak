<?php

namespace Tests\Feature\Panel;

use App\Enums\TalepTuru;
use App\Filament\Destek\CsvAktarici;
use App\Filament\Resources\Portfoyler\Pages\EditPortfoy;
use App\Filament\Resources\Portfoyler\Pages\ListPortfoyler;
use App\Filament\Resources\Portfoyler\RelationManagers\BelgelerRelationManager;
use App\Filament\Resources\Portfoyler\RelationManagers\MedyaRelationManager;
use App\Filament\Resources\Talepler\Pages\ListTalepler;
use App\Models\IslemKaydi;
use App\Models\Portfoy;
use App\Models\PortfoyBelgesi;
use App\Models\PortfoyMedya;
use App\Models\Talep;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DosyaVeDisaAktarimTest extends TestCase
{
    use PanelKurulumu;

    /* ---------- Medya ---------- */

    public function test_goruntu_yukleme_medya_diskine_yazar_ve_ilk_foto_kapak_olur(): void
    {
        Storage::fake('medya');
        $danisman = $this->danisman();
        $p = $this->portfoy(['danisman_id' => $danisman->id]);
        $this->actingAs($danisman);

        Livewire::test(MedyaRelationManager::class, ['ownerRecord' => $p, 'pageClass' => EditPortfoy::class])
            ->callTableAction('yukle', data: [
                'dosyalar' => [UploadedFile::fake()->image('salon.jpg', 1600, 1000), UploadedFile::fake()->image('mutfak.png', 400, 300)],
                'tur' => 'foto',
            ])
            ->assertHasNoTableActionErrors();

        $medya = PortfoyMedya::where('portfoy_id', $p->id)->orderBy('sira')->get();
        $this->assertCount(2, $medya);
        foreach ($medya as $m) {
            Storage::disk('medya')->assertExists($m->yol);
            $this->assertStringStartsWith('portfoyler/', $m->yol);
        }
        $this->assertTrue($medya[0]->kapak);
        $this->assertFalse($medya[1]->kapak);

        Livewire::test(MedyaRelationManager::class, ['ownerRecord' => $p, 'pageClass' => EditPortfoy::class])
            ->callTableAction('kapakYap', $medya[1]);
        $this->assertTrue($medya[1]->fresh()->kapak);
        $this->assertFalse($medya[0]->fresh()->kapak);

        $this->assertNotContains('En az bir fotoğraf', $p->fresh()->yayinEksikleri());
    }

    /* ---------- Özel belge ---------- */

    private function belge(Portfoy $p): PortfoyBelgesi
    {
        Storage::disk('local')->put('belgeler/portfoy-'.$p->id.'/x.pdf', '%PDF-1.4 deneme');

        return PortfoyBelgesi::create([
            'portfoy_id' => $p->id, 'tur' => 'yetki', 'ad' => 'Yetki belgesi',
            'yol' => 'belgeler/portfoy-'.$p->id.'/x.pdf', 'orijinal_ad' => 'yetki-belgesi.pdf',
        ]);
    }

    public function test_ozel_belge_yuklenir_ve_yalniz_yetkiliye_iner(): void
    {
        Storage::fake('local');
        $ben = $this->danisman();
        $oteki = $this->danisman();
        $p = $this->portfoy(['danisman_id' => $ben->id]);
        $this->actingAs($ben);

        Livewire::test(BelgelerRelationManager::class, ['ownerRecord' => $p, 'pageClass' => EditPortfoy::class])
            ->callTableAction('create', data: [
                'yol' => UploadedFile::fake()->create('tapu.pdf', 120, 'application/pdf'),
                'ad' => 'Tapu fotokopisi',
                'tur' => 'tapu',
            ])
            ->assertHasNoTableActionErrors();

        $belge = PortfoyBelgesi::firstOrFail();
        Storage::disk('local')->assertExists($belge->yol);
        $this->assertSame('tapu.pdf', $belge->orijinal_ad);
        $this->assertStringStartsWith('belgeler/portfoy-'.$p->id.'/', $belge->yol);
        $this->assertFalse(Storage::disk('medya')->exists($belge->yol));

        // Yetkili indirir ve işlem kaydı düşer
        $this->get(route('panel.belge.indir', $belge))
            ->assertOk()
            ->assertDownload('tapu.pdf');
        $this->assertDatabaseHas('islem_kayitlari', ['olay' => 'belge_indirildi', 'kayit_turu' => 'PortfoyBelgesi', 'kayit_id' => $belge->id, 'user_id' => $ben->id]);

        // Başka danışman 403, içerik editörü 403
        $this->actingAs($oteki)->get(route('panel.belge.indir', $belge))->assertForbidden();
        $this->actingAs($this->kullanici('icerik_editoru'))->get(route('panel.belge.indir', $belge))->assertForbidden();
        $this->assertSame(1, IslemKaydi::where('olay', 'belge_indirildi')->count());
    }

    public function test_misafir_belge_indiremez(): void
    {
        Storage::fake('local');
        $belge = $this->belge($this->portfoy());

        auth()->logout();
        $this->get(route('panel.belge.indir', $belge))->assertRedirect('/admin/login');
    }

    public function test_belge_sekmesi_baskasinin_portfoyunde_gorunmez(): void
    {
        $p = $this->portfoy(['danisman_id' => $this->danisman()->id]);

        $this->actingAs($this->danisman());
        $this->assertFalse(BelgelerRelationManager::canViewForRecord($p, EditPortfoy::class));

        $this->actingAs($this->kullanici('sirket_yoneticisi'));
        $this->assertTrue(BelgelerRelationManager::canViewForRecord($p, EditPortfoy::class));
    }

    /* ---------- CSV ---------- */

    public function test_danisman_disa_aktaramaz(): void
    {
        $danisman = $this->danisman();
        $this->portfoy(['danisman_id' => $danisman->id]);
        $this->actingAs($danisman);

        Livewire::test(ListPortfoyler::class)->assertTableActionHidden('csvIndir');
        Livewire::test(ListTalepler::class)->assertTableActionHidden('csvIndir');
        $this->assertFalse(CsvAktarici::izinliMi());
        $this->assertSame(0, IslemKaydi::where('olay', 'disa_aktarildi')->count());
    }

    public function test_sirket_yoneticisi_csv_indirir_ve_kayda_gecer(): void
    {
        $p = $this->portfoy(['danisman_id' => $this->danisman()->id, 'ic_notlar' => 'Gizli not', 'baslik' => '=HYPERLINK("x")']);
        $this->actingAs($this->kullanici('sirket_yoneticisi'));

        Livewire::test(ListPortfoyler::class)
            ->callTableAction('csvIndir')
            ->assertFileDownloaded();

        $kayit = IslemKaydi::where('olay', 'disa_aktarildi')->firstOrFail();
        $this->assertSame(['kaynak' => 'portfoy', 'adet' => 1], $kayit->sonraki);

        $metin = CsvAktarici::metin(['Başlık'], [[$p->baslik]]);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $metin);
        $this->assertStringContainsString("'=HYPERLINK", $metin); // formül enjeksiyonu etkisiz

        Talep::create(['tur' => TalepTuru::Iletisim, 'ad_soyad' => 'Veli Müşteri', 'telefon' => '05321112233']);
        Livewire::test(ListTalepler::class)
            ->callTableAction('csvIndir')
            ->assertFileDownloaded();
        $this->assertSame(2, IslemKaydi::where('olay', 'disa_aktarildi')->count());
    }

    public function test_ic_bilgi_izni_olmayan_csvde_sahip_telefonu_ve_notu_gormez(): void
    {
        $this->portfoy(['ic_notlar' => 'Gizli not', 'acik_adres' => 'Gizli sokak 5']);
        $this->actingAs($this->kullanici('sirket_yoneticisi'));

        $indir = function (?bool $icBilgi): string {
            ob_start();
            CsvAktarici::portfoyler(Portfoy::query(), $icBilgi)->sendContent();

            return (string) ob_get_clean();
        };

        // Varsayılan: kullanıcının izni (şirket yöneticisinde var)
        $tam = $indir(null);
        $this->assertStringContainsString('0555 111 22 33', $tam);
        $this->assertStringContainsString('Gizli not', $tam);

        // İzin yokken bu sütunlar hiç yazılmaz
        $kisitli = $indir(false);
        $this->assertStringNotContainsString('0555 111 22 33', $kisitli);
        $this->assertStringNotContainsString('Ayşe Sahibi', $kisitli);
        $this->assertStringNotContainsString('Gizli not', $kisitli);
        $this->assertStringNotContainsString('Gizli sokak', $kisitli);
        $this->assertStringContainsString('BLK-', $kisitli);

        // İzni olmayan birinin true geçmesi izni genişletmez
        $this->actingAs($this->kullanici('sirket_yoneticisi', ['aktif' => false]));
        $this->assertFalse(CsvAktarici::izinliMi());
    }
}
