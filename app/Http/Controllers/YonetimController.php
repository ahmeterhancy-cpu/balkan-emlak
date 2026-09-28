<?php

namespace App\Http\Controllers;

use App\Models\Hizmet;
use App\Models\Referans;
use App\Models\Sss;
use Illuminate\Contracts\View\View;

/** Site ve apartman yönetimi tanıtım sayfaları. */
class YonetimController extends Controller
{
    public function index(): View
    {
        return view('yonetim.index', [
            'hizmetler' => Hizmet::yayinda()->get(),
            'sorular' => Sss::yayinda()->where('grup', 'yonetim')->get(),
            'projeler' => Referans::yayinda()->where('tur', 'proje')->limit(6)->get(),
        ]);
    }

    public function hizmet(Hizmet $hizmet): View
    {
        abort_unless($hizmet->yayinda, 404);

        return view('yonetim.hizmet', [
            'hizmet' => $hizmet,
            'hizmetler' => Hizmet::yayinda()->get(['id', 'baslik', 'slug', 'ikon']),
        ]);
    }
}
