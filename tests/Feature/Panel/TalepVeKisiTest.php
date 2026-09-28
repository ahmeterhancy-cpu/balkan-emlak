<?php

namespace Tests\Feature\Panel;

use App\Enums\KomisyonDurumu;
use App\Enums\TalepDurumu;
use App\Enums\TalepTuru;
use App\Filament\Resources\Kisiler\Pages\CreateKisi;
use App\Filament\Resources\Kisiler\Pages\ListKisiler;
use App\Filament\Resources\Komisyonlar\Widgets\KomisyonToplamlari;
use App\Filament\Resources\Randevular\Pages\CreateRandevu;
use App\Filament\Resources\Talepler\Pages\EditTalep;
use App\Filament\Resources\Talepler\Pages\ListTalepler;
use App\Filament\Resources\Talepler\RelationManagers\NotlarRelationManager;
use App\Filament\Resources\Talepler\TalepResource;
use App\Models\Kisi;
use App\Models\Komisyon;
use App\Models\Randevu;
use App\Models\Talep;
use Livewire\Livewire;
use Tests\TestCase;

class TalepVeKisiTest extends TestCase
{
    use PanelKurulumu;

    private function talep(array $ek = []): Talep
    {
        return Talep::create([
            'tur' => TalepTuru::IlanBilgi,
            'kaynak' => 'web',
            'ad_soyad' => 'Mehmet Alıcı',
            'telefon' => '0532 444 55 66',
            'eposta' => 'mehmet@example.test',
            'detay' => ['iletisim_tercihi' => 'whatsapp', 'ihtiyaclar' => ['Temizlik', 'Güvenlik']],
            ...$ek,
        ]);
    }

    public function test_danisman_yalniz_atanan_talepleri_gorur_ve_rozet_sayar(): void
    {
        $ben = $this->danisman();
        $benim = $this->talep(['atanan_id' => $ben->id]);
        $atanmamis = $this->talep(['ad_soyad' => 'Atanmamış']);
        $this->actingAs($ben);

        Livewire::test(ListTalepler::class)
            ->assertCanSeeTableRecords([$benim])
            ->assertCanNotSeeTableRecords([$atanmamis]);
        $this->get(TalepResource::getUrl('edit', ['record' => $atanmamis]))->assertNotFound();
        $this->assertSame('1', TalepResource::getNavigationBadge());

        $this->actingAs($this->kullanici('sirket_yoneticisi'));
        $this->assertSame('2', TalepResource::getNavigationBadge());
    }

    public function test_tur_sekmeleri_filtreler(): void
    {
        $this->actingAs($this->yonetici());
        $ilan = $this->talep();
        $teklif = $this->talep(['tur' => TalepTuru::YonetimTeklifi, 'ad_soyad' => 'Site Yöneticisi']);

        Livewire::test(ListTalepler::class)
            ->set('activeTab', TalepTuru::YonetimTeklifi->value)
            ->assertCanSeeTableRecords([$teklif])
            ->assertCanNotSeeTableRecords([$ilan]);
    }

    public function test_detay_okunur_gosterilir_ve_mukerrer_uyarisi_cikar(): void
    {
        $this->actingAs($this->yonetici());
        Kisi::create(['ad_soyad' => 'Mehmet A.', 'telefon' => '+90 532 444 5566']);
        $t = $this->talep();

        Livewire::test(EditTalep::class, ['record' => $t->getRouteKey()])
            ->assertSee('İhtiyaçlar: Temizlik, Güvenlik')
            ->assertSee('İletişim tercihi: whatsapp')
            ->assertSee('Olası mükerrer kayıt')
            ->assertSee('Mehmet A.');
    }

    public function test_kisi_kaydi_olusturulur_ya_da_mevcuda_baglanir(): void
    {
        $this->actingAs($this->yonetici());
        $t = $this->talep();

        Livewire::test(EditTalep::class, ['record' => $t->getRouteKey()])
            ->callAction('kisiyeBagla', data: ['kisi_id' => null])
            ->assertHasNoActionErrors();

        $kisi = Kisi::firstOrFail();
        $this->assertSame('Mehmet Alıcı', $kisi->ad_soyad);
        $this->assertSame('whatsapp', $kisi->iletisim_tercihi);
        $this->assertSame($kisi->id, $t->fresh()->kisi_id);

        // İkinci talep aynı telefonla: mevcut kişi önerilir, yeni kayıt açılmaz
        $t2 = $this->talep(['ad_soyad' => 'M. Alıcı']);
        Livewire::test(EditTalep::class, ['record' => $t2->getRouteKey()])
            ->mountAction('kisiyeBagla')
            ->assertSchemaStateSet(['kisi_id' => $kisi->id], 'mountedActionSchema0')
            ->callMountedAction();

        $this->assertSame(1, Kisi::count());
        $this->assertSame($kisi->id, $t2->fresh()->kisi_id);
    }

    public function test_randevu_olustur_talep_durumunu_gunceller(): void
    {
        $ben = $this->danisman();
        $p = $this->portfoy(['danisman_id' => $ben->id]);
        $t = $this->talep(['atanan_id' => $ben->id, 'portfoy_id' => $p->id]);
        $this->actingAs($ben);

        Livewire::test(EditTalep::class, ['record' => $t->getRouteKey()])
            ->assertActionHidden('banaAta')
            ->callAction('randevuOlustur', data: [
                'baslangic' => now()->addDays(2)->setTime(14, 0)->format('Y-m-d H:i:s'),
                'sure_dk' => 45,
                'portfoy_id' => $p->id,
            ])
            ->assertHasNoActionErrors();

        $r = Randevu::firstOrFail();
        $this->assertSame($ben->id, $r->danisman_id);
        $this->assertSame($t->id, $r->talep_id);
        $this->assertSame('Mehmet Alıcı', $r->musteri_adi);
        $this->assertSame(TalepDurumu::RandevuPlanlandi, $t->fresh()->durum);
    }

    public function test_bana_ata_ve_not_ekleme(): void
    {
        $mudur = $this->kullanici('sirket_yoneticisi');
        $t = $this->talep();
        $this->actingAs($mudur);

        Livewire::test(EditTalep::class, ['record' => $t->getRouteKey()])->callAction('banaAta');
        $this->assertSame($mudur->id, $t->fresh()->atanan_id);

        Livewire::test(NotlarRelationManager::class, ['ownerRecord' => $t, 'pageClass' => EditTalep::class])
            ->callTableAction('create', data: ['not' => 'Aradım, akşam dönecek.'])
            ->assertHasNoTableActionErrors();
        $this->assertDatabaseHas('talep_notlari', ['talep_id' => $t->id, 'user_id' => $mudur->id, 'not' => 'Aradım, akşam dönecek.']);
    }

    public function test_danisman_randevuyu_baskasi_adina_ya_da_baskasinin_portfoyune_acamaz(): void
    {
        $ben = $this->danisman();
        $onunPortfoyu = $this->portfoy(['danisman_id' => $this->danisman()->id]);
        $this->actingAs($ben);

        Livewire::test(CreateRandevu::class)
            ->fillForm(['musteri_adi' => 'Ali', 'baslangic' => now()->addDay()->format('Y-m-d H:i:s'), 'sure_dk' => 30, 'portfoy_id' => $onunPortfoyu->id])
            ->call('create')
            ->assertHasFormErrors(['portfoy_id']);

        Livewire::test(CreateRandevu::class)
            ->fillForm(['musteri_adi' => 'Ali', 'baslangic' => now()->addDay()->format('Y-m-d H:i:s'), 'sure_dk' => 30])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame($ben->id, Randevu::firstOrFail()->danisman_id);
    }

    public function test_kisi_kapsami_ve_mukerrer_uyarisi(): void
    {
        $ben = $this->danisman();
        $baskasinin = Kisi::create(['ad_soyad' => 'Başkasının Müşterisi', 'telefon' => '05329998877']);
        $this->actingAs($ben);

        Livewire::test(ListKisiler::class)->assertCanNotSeeTableRecords([$baskasinin]);

        Livewire::test(CreateKisi::class)
            ->fillForm(['ad_soyad' => 'Yeni Kişi', 'telefon' => '0532 999 88 77'])
            ->assertSee('Olası mükerrer kayıt')
            ->assertSee('erişiminiz dışında')
            ->assertDontSee('Başkasının Müşterisi')
            ->call('create')
            ->assertHasNoFormErrors();

        $yeni = Kisi::where('ad_soyad', 'Yeni Kişi')->firstOrFail();
        $this->assertSame($ben->id, $yeni->olusturan_id);
        $this->assertSame(2, Kisi::count()); // otomatik birleştirme yok
    }

    public function test_komisyon_toplamlari_para_birimine_gore_ayri(): void
    {
        Komisyon::create(['beklenen_kurus' => 100_000_00, 'gerceklesen_kurus' => 40_000_00, 'para_birimi' => 'TRY', 'durum' => KomisyonDurumu::Kismi]);
        Komisyon::create(['beklenen_kurus' => 50_000_00, 'para_birimi' => 'TRY', 'durum' => KomisyonDurumu::Bekliyor]);
        Komisyon::create(['beklenen_kurus' => 2_000_00, 'para_birimi' => 'EUR', 'durum' => KomisyonDurumu::Bekliyor]);
        Komisyon::create(['beklenen_kurus' => 9_999_00, 'para_birimi' => 'EUR', 'durum' => KomisyonDurumu::Iptal]);

        $gruplar = collect(KomisyonToplamlari::gruplar())->keyBy('para_birimi');
        $this->assertSame(150_000_00, $gruplar['TRY']['beklenen']);
        $this->assertSame(40_000_00, $gruplar['TRY']['gerceklesen']);
        $this->assertSame(2_000_00, $gruplar['EUR']['beklenen']);
        $this->assertCount(2, $gruplar);

        $this->actingAs($this->kullanici('sirket_yoneticisi'));
        Livewire::test(KomisyonToplamlari::class)
            ->assertSee('150.000 ₺')
            ->assertSee('€2.000');
    }
}
