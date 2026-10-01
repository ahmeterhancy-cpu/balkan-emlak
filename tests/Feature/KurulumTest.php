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

    public function test_parola_kisa_ya_da_eksikse_yonetici_acilmaz(): void
    {
        config(['app.ilk_yonetici' => ['eposta' => 'a@b.example', 'parola' => 'kisa', 'ad' => 'X']]);

        $this->artisan('balkan:kurulum')->assertSuccessful();
        $this->assertSame(0, User::count());
    }
}
