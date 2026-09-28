<?php

namespace App\Http\Controllers;

use App\Models\Hizmet;
use App\Models\Sayfa;
use App\Models\Yazi;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Panelden "Önizle": sayfa, bilgi merkezi yazısı ve hizmet kaydedilmiş haliyle,
 * yayında olmasa da yetkili kullanıcıya vitrin tasarımında gösterilir
 * (şartname 10: taslak / önizleme / yayın akışı). Arama motorlarına kapalı.
 * İlan önizlemesi PortfoyController::onizleme'de.
 */
class OnizlemeController extends Controller
{
    public function sayfa(Sayfa $sayfa): View
    {
        Gate::authorize('view', $sayfa);

        return view('kurumsal.sayfa', [
            'sayfa' => $sayfa,
            'icerikOnizleme' => $sayfa->yayinda ? 'yayında' : 'yayında değil (taslak)',
        ]);
    }

    public function yazi(Yazi $yazi): View
    {
        Gate::authorize('view', $yazi);

        $durum = match (true) {
            $yazi->durum === 'yayinda' && $yazi->yayin_tarihi?->isFuture() => $yazi->yayin_tarihi->translatedFormat('j F Y H:i').' tarihinde yayına girecek',
            default => mb_strtolower(\App\Models\Yazi::DURUMLAR[$yazi->durum] ?? $yazi->durum),
        };

        return view('bilgi.detay', [
            'yazi' => $yazi,
            'digerleri' => Yazi::yayinda()->whereKeyNot($yazi->id)->where('kategori', $yazi->kategori)->limit(3)->get(),
            'icerikOnizleme' => $durum,
        ]);
    }

    public function hizmet(Hizmet $hizmet): View
    {
        Gate::authorize('view', $hizmet);

        return view('yonetim.hizmet', [
            'hizmet' => $hizmet,
            'hizmetler' => Hizmet::yayinda()->get(['id', 'baslik', 'slug', 'ikon']),
            'icerikOnizleme' => $hizmet->yayinda ? 'yayında' : 'yayında değil',
        ]);
    }
}
