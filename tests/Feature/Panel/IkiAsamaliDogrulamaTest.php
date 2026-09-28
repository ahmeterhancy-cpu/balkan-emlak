<?php

namespace Tests\Feature\Panel;

use App\Enums\Rol;
use App\Models\IslemKaydi;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/** Şartname 15: yönetici hesaplarında çok faktörlü doğrulama. */
class IkiAsamaliDogrulamaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['balkan.mfa_zorunlu' => true]);
        Filament::setCurrentPanel('admin');
    }

    public function test_yonetici_rolu_kurulum_yapmadan_panele_giremez_profile_yonlenir(): void
    {
        $k = User::factory()->create(['rol' => Rol::SistemYoneticisi]);

        $this->actingAs($k)->get('/admin')->assertRedirect('/admin/profile');
        $this->actingAs($k)->get('/admin/portfoyler')->assertRedirect('/admin/profile');
        $this->actingAs($k)->get('/admin/profile')->assertOk()->assertSee('İki aşamalı', false);
    }

    public function test_muhasebe_de_zorunlu_danisman_ve_saha_isteğe_bagli(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::MuhasebeSorumlusu]))
            ->get('/admin')->assertRedirect('/admin/profile');

        foreach ([Rol::EmlakDanismani, Rol::SahaPersoneli, Rol::IcerikEditoru] as $rol) {
            $this->flushSession();
            $this->actingAs(User::factory()->create(['rol' => $rol]))->get('/admin')->assertOk();
        }
    }

    public function test_kurulum_yapan_yonetici_panele_girer(): void
    {
        $k = User::factory()->create(['rol' => Rol::SistemYoneticisi]);
        $k->saveAppAuthenticationSecret((new Google2FA)->generateSecretKey());

        $this->actingAs($k)->get('/admin')->assertOk();
    }

    public function test_ayar_kapaliyken_zorlanmaz(): void
    {
        config(['balkan.mfa_zorunlu' => false]);

        $this->actingAs(User::factory()->create(['rol' => Rol::SistemYoneticisi]))->get('/admin')->assertOk();
    }

    public function test_gizli_anahtar_ve_kurtarma_kodlari_sifreli_ve_islem_gecmisine_yazilmaz(): void
    {
        $k = User::factory()->create(['rol' => Rol::SistemYoneticisi]);
        $gizli = (new Google2FA)->generateSecretKey();

        $k->saveAppAuthenticationSecret($gizli);
        $k->saveAppAuthenticationRecoveryCodes(['KOD-1111-AAAA', 'KOD-2222-BBBB']);

        $ham = \DB::table('users')->where('id', $k->id)->first();
        $this->assertNotSame($gizli, $ham->app_authentication_secret);
        $this->assertStringNotContainsString('KOD-1111', (string) $ham->app_authentication_recovery_codes);

        $gecmis = IslemKaydi::where('kayit_turu', 'User')->where('kayit_id', $k->id)->get()
            ->map(fn ($g) => json_encode([$g->onceki, $g->sonraki]))->implode(' ');

        $this->assertStringNotContainsString($gizli, $gecmis);
        $this->assertStringNotContainsString('KOD-1111', $gecmis);
        $this->assertStringContainsString('gizli', $gecmis);
    }

    public function test_giris_dogru_kod_olmadan_tamamlanmaz(): void
    {
        $gizli = (new Google2FA)->generateSecretKey();
        $k = User::factory()->create(['rol' => Rol::SistemYoneticisi, 'email' => 'mfa@example.com', 'password' => 'Parola-12345']);
        $k->saveAppAuthenticationSecret($gizli);

        $sayfa = Livewire::test(Login::class)
            ->set('data.email', 'mfa@example.com')
            ->set('data.password', 'Parola-12345')
            ->call('authenticate');

        // Parola doğru ama henüz oturum açılmadı: kod bekleniyor
        $this->assertGuest();
        $this->assertNotNull($sayfa->get('userUndertakingMultiFactorAuthentication'));

        $sayfa->set('data.multiFactor.app.code', '000000')->call('authenticate');
        $this->assertGuest();

        $sayfa->set('data.multiFactor.app.code', (new Google2FA)->getCurrentOtp($gizli))->call('authenticate');
        $this->assertAuthenticatedAs($k);
    }
}
