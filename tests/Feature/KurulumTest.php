<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Konum;
use App\Models\Sayfa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** cPanel deploy'unda her seferinde çalışan ilk kurulum komutu. */
class KurulumTest extends TestCase
{
    use RefreshDatabase;

    public function test_ilk_kurulum_icerigi_ve_yoneticiyi_bir_kez_acar_tekrar_calistirmak_guvenli(): void
    {
        config(['app.ilk_yonetici' => ['eposta' => 'Patron@Balkan.example', 'parola' => 'cok-gizli-parola-123', 'ad' => 'Patron']]);

        $this->artisan('balkan:kurulum')->assertSuccessful();

        $y = User::where('email', 'patron@balkan.example')->firstOrFail();
        $this->assertSame(Rol::SistemYoneticisi, $y->rol);
        $this->assertTrue(Hash::check('cok-gizli-parola-123', $y->password));
        $this->assertNotNull($y->email_verified_at);
        $this->assertTrue(Konum::exists());
        $this->assertTrue(Sayfa::exists());
        $this->assertFalse(User::where('email', 'like', '%.test')->exists()); // demo hesap yok

        // Panelden silinen sayfa geri gelmez; yöneticinin değiştirdiği parola ezilmez
        Sayfa::where('slug', 'hakkimizda')->delete();
        $y->update(['password' => 'yeni-parola-degisti']);
        config(['app.ilk_yonetici.parola' => 'baska-parola-12345']);

        $this->artisan('balkan:kurulum')->assertSuccessful();
        $this->assertFalse(Sayfa::where('slug', 'hakkimizda')->exists());
        $this->assertTrue(Hash::check('yeni-parola-degisti', $y->fresh()->password));
        $this->assertSame(1, User::count());
    }

    public function test_parola_kurtarma_yalniz_bayrakla_ve_yalniz_sistem_yoneticisinde(): void
    {
        config(['app.ilk_yonetici' => ['eposta' => 'patron@balkan.example', 'parola' => '', 'ad' => 'Patron', 'sifirla' => false]]);
        $this->artisan('balkan:kurulum')->assertSuccessful();
        $y = User::where('email', 'patron@balkan.example')->firstOrFail();
        $eski = $y->password;

        // Bayrak yokken .env'deki parola yöneticiye dokunmaz
        config(['app.ilk_yonetici.parola' => 'kendi-sectigim-parola']);
        $this->artisan('balkan:kurulum')->assertSuccessful();
        $this->assertSame($eski, $y->fresh()->password);

        config(['app.ilk_yonetici.sifirla' => true]);
        $this->artisan('balkan:kurulum')->expectsOutputToContain('satırlarını silin')->assertSuccessful();
        $this->assertTrue(Hash::check('kendi-sectigim-parola', $y->fresh()->password));
        $this->artisan('balkan:kurulum')->doesntExpectOutputToContain('kendi-sectigim-parola')->assertSuccessful();
    }

    public function test_parola_kisaysa_acilmaz_bossa_uretilip_bir_kez_yazilir(): void
    {
        config(['app.ilk_yonetici' => ['eposta' => 'a@b.example', 'parola' => 'kisa', 'ad' => 'X']]);
        $this->artisan('balkan:kurulum')->assertSuccessful();
        $this->assertSame(0, User::count());

        config(['app.ilk_yonetici.parola' => '']);
        $this->artisan('balkan:kurulum')->expectsOutputToContain('Parola = ')->assertSuccessful();
        $this->assertSame(1, User::where('email', 'a@b.example')->count());
    }
}
