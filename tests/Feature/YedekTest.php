<?php

namespace Tests\Feature;

use App\Models\Kisi;
use App\Services\Yedek\Yedekleyici;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\Feature\SiteFinans\SiteKurulumu;
use Tests\TestCase;
use ZipArchive;

/** Şartname 15 (şifreli yedek) ve 17 (geri yükleme test ortamında doğrulanır). */
class YedekTest extends TestCase
{
    // Geri yükleme yabancı anahtarları kapatıp tabloları boşaltıyor; açık bir test transaction'ı içinde olmamalı
    use DatabaseMigrations, SiteKurulumu;

    private string $kok;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kok = storage_path('framework/testing/yedek-'.bin2hex(random_bytes(3)));
        File::ensureDirectoryExists($this->kok.'/private/belgeler');
        File::ensureDirectoryExists($this->kok.'/medya/portfoyler');
        File::put($this->kok.'/private/belgeler/tapu.pdf', 'GIZLI-TAPU-ICERIGI');
        File::put($this->kok.'/medya/portfoyler/foto.jpg', 'foto');

        config([
            'filesystems.disks.local.root' => $this->kok.'/private',
            'filesystems.disks.medya.root' => $this->kok.'/medya',
            'app.yedek_parola' => 'test-parolasi-123',
        ]);

        // Mali veri: aidat + tahsilat, kontrol toplamları boş olmasın
        $this->yonetici();
        ['site' => $site, 'bolumler' => $b, 'hesap' => $hesap] = $this->site();
        $this->aidat($site, '2026-09', 1000);
        $this->tahsil($hesap, $b[0], 400);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->kok);
        File::deleteDirectory(storage_path('app/yedekler-test'));
        parent::tearDown();
    }

    private function servis(): Yedekleyici
    {
        return new class extends Yedekleyici
        {
            public function klasor(): string
            {
                return storage_path('app/yedekler-test');
            }
        };
    }

    public function test_yedek_sifreli_ve_dogrulama_basarili(): void
    {
        $yol = $this->servis()->al();

        // Parolasız açılamaz
        $zip = new ZipArchive;
        $zip->open($yol);
        $this->assertFalse($zip->getFromName('manifest.json'));
        $this->assertFalse($zip->getFromName('dosyalar/private/belgeler/tapu.pdf'));
        $zip->close();

        $sonuc = $this->servis()->dogrula($yol);

        $this->assertTrue($sonuc['basarili'], implode("\n", $sonuc['hatalar']));
        $this->assertSame(2, $sonuc['dosyalar']);
    }

    public function test_parola_yoksa_yedek_alinmaz(): void
    {
        config(['app.yedek_parola' => null]);

        $this->expectException(RuntimeException::class);
        $this->servis()->al();
    }

    public function test_yanlis_parolayla_dogrulama_basarisiz(): void
    {
        $yol = $this->servis()->al();
        config(['app.yedek_parola' => 'yanlis']);

        $this->expectException(RuntimeException::class);
        $this->servis()->dogrula($yol);
    }

    public function test_eksik_satir_ve_bozuk_dosya_yakalanir(): void
    {
        $yol = $this->servis()->al();

        // Yedekten bir tahsilat satırını ve bir dosyanın içeriğini boz
        $zip = new ZipArchive;
        $zip->open($yol);
        $zip->setPassword('test-parolasi-123');
        $satirlar = array_filter(explode("\n", $zip->getFromName('veri/borclar.jsonl')));
        array_pop($satirlar);
        foreach (['veri/borclar.jsonl' => implode("\n", $satirlar)."\n", 'dosyalar/medya/portfoyler/foto.jpg' => 'degisti'] as $ad => $icerik) {
            $zip->addFromString($ad, $icerik);
            $zip->setEncryptionName($ad, ZipArchive::EM_AES_256, 'test-parolasi-123');
        }
        $zip->close();

        $sonuc = $this->servis()->dogrula($yol);

        $this->assertFalse($sonuc['basarili']);
        $metin = implode("\n", $sonuc['hatalar']);
        $this->assertStringContainsString('borclar: yedekte 3 satır, geri yüklenen 2', $metin);
        $this->assertStringContainsString('borclar.tutar_kurus', $metin);
        $this->assertStringContainsString('Dosya bozuk: dosyalar/medya/portfoyler/foto.jpg', $metin);
    }

    public function test_geri_yukleme_veriyi_ve_dosyalari_yedekteki_haline_dondurur(): void
    {
        $yol = $this->servis()->al();
        $kisiSayisi = Kisi::count();
        $borcToplami = (int) \DB::table('borclar')->sum('tutar_kurus');

        // Yedekten sonra veri değişti / silindi
        Kisi::create(['ad_soyad' => 'Sonradan eklenen']);
        File::delete($this->kok.'/private/belgeler/tapu.pdf');

        $guvenlik = $this->servis()->geriYukle($yol);

        $this->assertSame($kisiSayisi, Kisi::count());
        $this->assertNull(Kisi::where('ad_soyad', 'Sonradan eklenen')->first());
        $this->assertSame($borcToplami, (int) \DB::table('borclar')->sum('tutar_kurus'));
        $this->assertSame('GIZLI-TAPU-ICERIGI', File::get($this->kok.'/private/belgeler/tapu.pdf'));
        $this->assertFileExists($guvenlik); // geri dönüş için önceki durum saklandı
        $this->assertFileExists($yol);      // geri yüklenen yedeğin üzerine yazılmadı
        $this->assertNotSame($yol, $guvenlik);
        $this->assertSame($yol, $this->servis()->sonYedek()); // güvenlik yedeği "en son yedek" sayılmaz
    }

    public function test_ayni_saniyede_alinan_yedekler_birbirinin_uzerine_yazmaz_temizlik_guvenligi_silmez(): void
    {
        $s = $this->servis();
        $yedekler = [$s->al(), $s->al(), $s->al()];
        $guvenlik = $s->al(tur: 'guvenlik');

        $this->assertCount(3, array_unique($yedekler));

        $this->assertSame(2, $s->temizle(1));
        $this->assertFileExists($guvenlik);
        $this->assertSame(1, count(glob($s->klasor().'/balkan-2*.zip')));
    }

    public function test_komutlar(): void
    {
        $this->artisan('balkan:geri-yukle', ['dosya' => 'yok.zip'])->assertFailed(); // --evet olmadan çalışmaz
    }
}
