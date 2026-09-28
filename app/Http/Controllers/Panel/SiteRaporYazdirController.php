<?php

namespace App\Http\Controllers\Panel;

use App\Filament\Destek\SiteRaporlari;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Site raporunun yazdırılabilir görünümü. Tarayıcının "Yazdır → PDF olarak
 * kaydet" özelliğiyle PDF alınır; harici PDF kütüphanesi yok. Veri ekrandaki
 * raporla aynı üreticiden gelir; yetki ve site erişimi yeniden denetlenir.
 */
class SiteRaporYazdirController extends Controller
{
    public function __invoke(Request $istek, string $rapor): View
    {
        abort_unless(SiteRaporlari::izinli($rapor), 403);

        $girdi = collect($istek->query())->only(['site_id', 'donem', 'bas_donem', 'bit_donem', 'bas', 'bit', 'gun', 'bolum_id', 'hesap_id'])
            ->map(fn ($d) => is_scalar($d) ? (string) $d : null)->all();

        return view('filament.rapor-yazdir', ['rapor' => SiteRaporlari::uret($rapor, $girdi)]);
    }
}
