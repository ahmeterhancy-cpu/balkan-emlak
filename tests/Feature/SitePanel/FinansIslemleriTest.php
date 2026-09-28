<?php

namespace Tests\Feature\SitePanel;

use App\Filament\Resources\Borclandirmalar\Pages\CreateBorclandirma;
use App\Filament\Resources\Borclandirmalar\Pages\ViewBorclandirma;
use App\Filament\Resources\Borclar\Pages\ListBorclar;
use App\Filament\Resources\Giderler\Pages\ViewGider;
use App\Filament\Resources\Tahsilatlar\Pages\CreateTahsilat;
use App\Filament\Resources\Tahsilatlar\Pages\ListTahsilatlar;
use App\Models\BagimsizBolum;
use App\Models\Borc;
use App\Models\Borclandirma;
use App\Models\Gider;
use App\Models\GiderOdemesi;
use App\Models\Hesap;
use App\Models\Tahsilat;
use App\Services\SiteFinans\Borclandirici;
use Livewire\Livewire;
use Tests\TestCase;

/** Panel üzerinden mali işlemler: yalnız servislerden geçer, hatalar bildirim olur. */
class FinansIslemleriTest extends TestCase
{
    use SitePanelKurulumu;

    /** Üç aylık aidatının hiçbiri ödenmemiş bir bölüm. */
    private function hicOdememisBolum(): BagimsizBolum
    {
        return BagimsizBolum::where('site_id', $this->yildiz->id)->get()
            ->first(fn (BagimsizBolum $b) => Borc::where('bagimsiz_bolum_id', $b->id)->acik()->count() === 3
                && ! Tahsilat::where('bagimsiz_bolum_id', $b->id)->exists());
    }

    public function test_muhasebe_tahsilati_onizleyip_kaydeder(): void
    {
        $this->giris('muhasebe');
        $bolum = $this->hicOdememisBolum();
        $this->assertNotNull($bolum);
        $banka = Hesap::where('site_id', $this->yildiz->id)->where('tur', 'banka')->first();

        // Yıldız aidatı 1.200 ₺: 2.000 ₺ → Temmuz tamamı + Ağustos'a 800 ₺
        Livewire::test(CreateTahsilat::class)
            ->fillForm([
                'site_id' => $this->yildiz->id,
                'bagimsiz_bolum_id' => $bolum->id,
                'hesap_id' => $banka->id,
                'tarih' => '2026-09-27',
                'tutar_kurus' => '2.000',
                'yontem' => 'havale',
            ])
            ->assertSee('Temmuz 2026 aidatı (2026-07)')
            ->assertSee('800 ₺')
            ->assertSee('Dağıtılmayan (avans):')
            ->call('create')
            ->assertHasNoFormErrors();

        $t = Tahsilat::where('bagimsiz_bolum_id', $bolum->id)->latest('id')->firstOrFail();
        $this->assertSame(200_000, $t->tutar_kurus);
        $this->assertTrue($t->dogrulandi);
        $borclar = Borc::where('bagimsiz_bolum_id', $bolum->id)->orderBy('vade')->get();
        $this->assertSame([0, 40_000, 120_000], $borclar->map->kalanKurus()->all());
        $this->assertSame(200_000, (int) $banka->hareketler()->where('kaynak_id', $t->id)->where('tur', 'tahsilat')->sum('tutar_kurus'));
    }

    public function test_dekontlu_tahsilat_dogrulanana_kadar_islenmez_ve_dogrula_eylemi(): void
    {
        $this->giris('muhasebe');
        $t = Tahsilat::where('site_id', $this->yildiz->id)->where('dogrulandi', false)->firstOrFail();
        $this->assertSame(0, (int) $t->hesap->hareketler()->where('kaynak_id', $t->id)->where('tur', 'tahsilat')->count());
        $this->assertSame(0, $t->dagitilmamisKurus());

        Livewire::test(ListTahsilatlar::class)
            ->assertTableActionVisible('dogrula', $t)
            ->assertTableActionHidden('iade', $t)
            ->callTableAction('dogrula', $t);

        $this->assertTrue($t->refresh()->dogrulandi);
        // Artık kasaya işlendi; borca dağıtılan + avans = tutar
        $this->assertSame($t->tutar_kurus, (int) $t->hesap->hareketler()->where('kaynak_id', $t->id)->where('tur', 'tahsilat')->sum('tutar_kurus'));
        $this->assertSame($t->tutar_kurus, $t->dagitilanKurus() + $t->dagitilmamisKurus());
    }

    public function test_site_yoneticisi_tahsilat_giremez(): void
    {
        $this->giris('siteyonetici');
        $this->get('/admin/tahsilatlar/create')->assertForbidden();
    }

    public function test_borclandirma_olustur_onizle_kesinlestir_ve_tekrar_calistirma(): void
    {
        $this->giris('muhasebe');

        Livewire::test(CreateBorclandirma::class)
            ->fillForm([
                'site_id' => $this->camlik->id,
                'donem' => '2026-10',
                'tur' => 'aidat',
                'aciklama' => 'Ekim 2026 aidatı',
                'vade' => '2026-10-10',
                'dagitim' => 'sabit',
                'birim_kurus' => '1.600',
                'para_birimi' => 'TRY',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $b = Borclandirma::where('site_id', $this->camlik->id)->where('donem', '2026-10')->firstOrFail();
        $this->assertSame('taslak', $b->durum);
        $this->assertSame(160_000, $b->birim_kurus);
        $this->assertSame(0, $b->borclar()->count());

        // Önizleme: 24 bölüm × 1.600 ₺ = 38.400 ₺, hiçbir kayıt yazılmaz
        Livewire::test(ViewBorclandirma::class, ['record' => $b->getRouteKey()])
            ->assertSee('38.400 ₺')
            ->assertSee('Bölüm sayısı')
            ->callAction('kesinlestir')
            ->assertHasNoActionErrors();

        $this->assertSame('kesin', $b->refresh()->durum);
        $this->assertSame(24, $b->borclar()->count());
        $this->assertSame(3_840_000, (int) $b->borclar()->sum('tutar_kurus'));

        // Tekrar: eylem gizli, elle çağrı da ve servis de mükerrer oluşturmaz
        Livewire::test(ViewBorclandirma::class, ['record' => $b->getRouteKey()])
            ->assertActionHidden('kesinlestir')
            ->call('mountAction', 'kesinlestir')
            ->call('callMountedAction');
        app(Borclandirici::class)->kesinlestir($b->refresh());
        $this->assertSame(24, $b->borclar()->count());

        // Aynı site/dönem/tür/etiket ikinci kez: form hatası (500 değil)
        Livewire::test(CreateBorclandirma::class)
            ->fillForm(['site_id' => $this->camlik->id, 'donem' => '2026-10', 'tur' => 'aidat', 'aciklama' => 'Tekrar', 'vade' => '2026-10-10', 'dagitim' => 'sabit', 'birim_kurus' => '1.600'])
            ->call('create')
            ->assertHasFormErrors(['donem']);
    }

    public function test_borc_duzeltme_eylemi_karsi_kayit_acar(): void
    {
        $this->giris('muhasebe');
        $borc = Borc::where('site_id', $this->yildiz->id)->where('donem', '2026-09')->acik()->firstOrFail();

        Livewire::test(ListBorclar::class)
            ->callTableAction('duzelt', $borc, ['yeni_net' => '1.000', 'gerekce' => 'Genel kurul indirimi'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(100_000, $borc->refresh()->netKurus());
        $this->assertSame(-20_000, (int) Borc::where('duzeltilen_borc_id', $borc->id)->value('tutar_kurus'));
    }

    public function test_limit_ustu_gider_onayi_site_yoneticisinde_engelli_sirket_yoneticisinde_serbest(): void
    {
        $gider = Gider::where('site_id', $this->camlik->id)->where('onay_durumu', 'onay_bekliyor')->firstOrFail();
        $this->assertGreaterThan($this->camlik->onay_limiti_kurus, $gider->tutar_kurus);

        $this->giris('siteyonetici');
        Livewire::test(ViewGider::class, ['record' => $gider->getRouteKey()])
            ->assertActionVisible('onayla')
            ->assertActionDisabled('onayla')
            ->call('mountAction', 'onayla')
            ->call('callMountedAction');
        $this->assertSame('onay_bekliyor', $gider->refresh()->onay_durumu);

        $this->giris('mudur');
        Livewire::test(ViewGider::class, ['record' => $gider->getRouteKey()])
            ->assertActionEnabled('onayla')
            ->callAction('onayla');
        $this->assertSame('onaylandi', $gider->refresh()->onay_durumu);
    }

    public function test_servis_hatasi_500_degil_bildirim_olur(): void
    {
        $this->giris('muhasebe');
        $gider = Gider::where('site_id', $this->yildiz->id)->where('onay_durumu', 'onaylandi')->get()
            ->first(fn (Gider $g) => $g->kalanKurus() > 0);
        $banka = Hesap::where('site_id', $this->yildiz->id)->where('tur', 'banka')->first();
        $once = GiderOdemesi::count();

        // Kalandan fazla ödeme → GiderServisi DomainException → kırmızı bildirim
        Livewire::test(ViewGider::class, ['record' => $gider->getRouteKey()])
            ->callAction('ode', ['hesap_id' => $banka->id, 'tutar' => '999.999', 'tarih' => '2026-09-27'])
            ->assertNotified('İşlem yapılamadı');

        $this->assertSame($once, GiderOdemesi::count());

        // Başka sitenin hesabıyla ödeme denemesi: seçenek dışı → doğrulama hatası, kayıt yok
        $camlikHesap = Hesap::where('site_id', $this->camlik->id)->first();
        Livewire::test(ViewGider::class, ['record' => $gider->getRouteKey()])
            ->callAction('ode', ['hesap_id' => $camlikHesap->id, 'tutar' => '100', 'tarih' => '2026-09-27'])
            ->assertHasActionErrors(['hesap_id']);
        $this->assertSame($once, GiderOdemesi::count());

        // Geçerli ödeme
        Livewire::test(ViewGider::class, ['record' => $gider->getRouteKey()])
            ->callAction('ode', ['hesap_id' => $banka->id, 'tutar' => '100', 'tarih' => '2026-09-27'])
            ->assertHasNoActionErrors()
            ->assertNotified('Ödeme kaydedildi');
        $this->assertSame($once + 1, GiderOdemesi::count());
    }
}
