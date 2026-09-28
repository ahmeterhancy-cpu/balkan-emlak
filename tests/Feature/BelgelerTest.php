<?php

namespace Tests\Feature;

use App\Console\Commands\BelgeUret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Teslim belgeleri kodla uyumlu mu? İzin, rol ya da tablo değişip
 * `php artisan balkan:belgeler` çalıştırılmazsa bu test kırmızı yanar.
 */
class BelgelerTest extends TestCase
{
    use RefreshDatabase;

    public function test_yetki_tablosu_ve_veritabani_belgesi_guncel(): void
    {
        $this->artisan('balkan:belgeler', ['--kontrol' => true])
            ->expectsOutput('Belgeler güncel.')
            ->assertSuccessful();
    }

    public function test_her_tablonun_aciklamasi_var(): void
    {
        $eksik = collect(Schema::getTableListing(schemaQualified: false))
            ->reject(fn ($t) => str_starts_with($t, 'sqlite_') || isset(BelgeUret::TABLOLAR[$t]))
            ->values()->all();

        $this->assertSame([], $eksik, 'BelgeUret::TABLOLAR içine açıklama ekleyin.');
    }
}
