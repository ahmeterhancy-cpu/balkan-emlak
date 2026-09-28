<?php

namespace App\Http\Controllers;

use App\Models\Hizmet;
use App\Models\Portfoy;
use App\Models\Sayfa;
use App\Models\Yazi;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $adresler = collect([
            [route('anasayfa'), null],
            [route('emlak.index'), null],
            [route('yonetim.index'), null],
            [route('yonetim.teklif'), null],
            [route('basvuru.portfoy'), null],
            [route('hakkimizda'), null],
            [route('bilgi.index'), null],
            [route('iletisim'), null],
        ]);

        Portfoy::yayinda()->select(['slug', 'updated_at'])->each(function ($p) use ($adresler) {
            $adresler->push([route('ilan.detay', $p->slug), $p->updated_at]);
        });
        Hizmet::yayinda()->get(['slug', 'updated_at'])->each(fn ($h) => $adresler->push([route('yonetim.hizmet', $h->slug), $h->updated_at]));
        Yazi::yayinda()->get(['slug', 'updated_at'])->each(fn ($y) => $adresler->push([route('bilgi.detay', $y->slug), $y->updated_at]));
        Sayfa::yayinda()->where('grup', 'yasal')->get(['slug', 'updated_at'])->each(fn ($s) => $adresler->push([route('sayfa', $s->slug), $s->updated_at]));

        return response()
            ->view('parca.sitemap', ['adresler' => $adresler])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /** Canlı dışı ortamlar tümüyle kapalı; panel ve önizleme her zaman kapalı. */
    public function robots(): Response
    {
        $satirlar = app()->isProduction()
            ? ['User-agent: *', 'Disallow: /admin', 'Disallow: /onizleme', 'Sitemap: '.route('sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $satirlar)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
