<?php

namespace App\Http\Controllers;

use App\Models\Referans;
use App\Models\Sayfa;
use App\Models\Sss;
use App\Models\User;
use Illuminate\Contracts\View\View;

class KurumsalController extends Controller
{
    public function hakkimizda(): View
    {
        return view('kurumsal.hakkimizda', [
            'sayfa' => Sayfa::yayinda()->where('slug', 'hakkimizda')->first(),
            'ekip' => User::ekip()->get(),
        ]);
    }

    public function referanslar(): View
    {
        $referanslar = Referans::yayinda()->get();

        return view('kurumsal.referanslar', [
            'projeler' => $referanslar->where('tur', 'proje')->values(),
            'gorusler' => $referanslar->where('tur', 'yorum')->values(),
        ]);
    }

    public function iletisim(): View
    {
        return view('kurumsal.iletisim', [
            'sorular' => Sss::yayinda()->where('grup', 'genel')->get(),
        ]);
    }

    public function sayfa(string $slug): View
    {
        $sayfa = Sayfa::yayinda()->where('slug', $slug)->firstOrFail();

        return view('kurumsal.sayfa', ['sayfa' => $sayfa]);
    }
}
