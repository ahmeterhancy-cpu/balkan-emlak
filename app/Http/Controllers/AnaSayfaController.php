<?php

namespace App\Http\Controllers;

use App\Enums\Kategori;
use App\Models\Hizmet;
use App\Models\Konum;
use App\Models\Portfoy;
use App\Models\Referans;
use App\Models\User;
use Illuminate\Contracts\View\View;

class AnaSayfaController extends Controller
{
    public function __invoke(): View
    {
        $iliskiler = ['il:id,ad', 'ilce:id,ad', 'mahalle:id,ad', 'kapak', 'danisman:id,name,foto'];

        $sonIlanlar = Portfoy::yayinda()
            ->whereIn('operasyon_durumu', ['aktif', 'rezerve'])
            ->with($iliskiler)->withCount('fotograflar')
            ->latest('yayin_tarihi')->latest('id')
            ->limit(6)->get();

        $oneCikanlar = Portfoy::yayinda()
            ->where('one_cikan', true)
            ->whereIn('operasyon_durumu', ['aktif', 'rezerve'])
            ->with($iliskiler)->withCount('fotograflar')
            ->latest('yayin_tarihi')
            ->limit(6)->get();

        // Kategori kutuları: yalnız ilanı olan kategoriler, kapak o kategorinin bir ilanından
        $kategoriler = collect(Kategori::cases())->map(function (Kategori $k) {
            $sorgu = Portfoy::yayinda()->where('kategori', $k->value)->whereIn('operasyon_durumu', ['aktif', 'rezerve']);
            $adet = (clone $sorgu)->count();
            $ornek = $adet ? (clone $sorgu)->whereHas('kapak')->with('kapak')->latest('yayin_tarihi')->first() : null;

            return ['kategori' => $k, 'adet' => $adet, 'gorsel' => $ornek?->kapak?->kucukUrl()];
        })->filter(fn ($k) => $k['adet'] > 0)->values();

        return view('sayfa.anasayfa', [
            'sonIlanlar' => $sonIlanlar,
            'oneCikanlar' => $oneCikanlar,
            'kategoriler' => $kategoriler,
            'hizmetler' => Hizmet::yayinda()->get(),
            'gorusler' => Referans::yayinda()->where('tur', 'yorum')->limit(6)->get(),
            'ekip' => User::ekip()->limit(4)->get(),
            'konumlar' => Konum::iller()->with(['altlar' => fn ($q) => $q->where('tur', 'ilce')])->get(),
        ]);
    }
}
