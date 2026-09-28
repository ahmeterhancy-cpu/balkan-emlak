@extends('duzen.site')

@section('baslik', $hizmet->baslik)
@section('aciklama', $hizmet->seo_aciklama ?: $hizmet->ozet)
@if ($hizmet->gorselUrl())
    @section('og_gorsel', $hizmet->gorselUrl())
@endif

@section('icerik')
    @include('parca.sayfa-basi', [
        'baslik' => $hizmet->baslik,
        'aciklama' => $hizmet->ozet,
        'kirinti' => ['Site Yönetimi' => route('yonetim.index'), $hizmet->baslik => null],
        'gorsel' => site_gorsel_kaynak('yonetim_gorsel', 'varsayilan-yonetim.jpg'),
    ])

    <section class="bolum">
        <div class="kap iki-sutun">
            <div>
                <div class="kutu">
                    @if ($hizmet->gorselUrl())
                        <x-resim :yol="$hizmet->gorsel" class="hizmet-bas-gorsel" sizes="(min-width: 992px) 800px, 100vw" />
                    @endif
                    <div style="display:flex;gap:14px;align-items:center;margin-bottom:18px">
                        <span class="hizmet-simge" style="margin:0"><x-simge :ad="$hizmet->ikon ?: 'yonetim'" /></span>
                        <h2 style="margin:0;font-size:24px;font-weight:500">Hizmet kapsamı</h2>
                    </div>
                    <div class="icerik-metni">{!! \App\Support\Html::temizle($hizmet->kapsam) !!}</div>
                </div>

                @if (! empty($hizmet->surec))
                    <div class="kutu">
                        <h2>Süreç</h2>
                        <ol class="surec-cizgi">
                            @foreach ($hizmet->surec as $adim)
                                <li>{{ $adim }}</li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            </div>

            <aside class="yapiskan">
                <div class="yan-cagri">
                    <h2>Bu hizmet için teklif alın</h2>
                    <p>Sitenizin bilgilerini iletin, kapsamı birlikte netleştirelim.</p>
                    <a href="{{ route('yonetim.teklif', ['hizmet' => $hizmet->slug]) }}" class="dgm dgm-tam" style="margin-bottom:16px">Yönetim Teklifi Al</a>
                    @foreach (collect(ayar('iletisim_kisileri', []))->filter(fn ($k) => filled($k['telefon'] ?? null))->take(3) as $kisi)
                        <a class="tel" href="{{ tel_baglanti($kisi['telefon']) }}"><x-simge ad="telefon" /> {{ $kisi['telefon'] }}</a>
                    @endforeach
                </div>

                <div class="kutu" style="margin-top:24px;padding:10px 0">
                    <h2 class="kutu-baslik" style="padding:10px 18px 4px;margin:0;font-size:17px">Tüm hizmetler</h2>
                    <ul class="yan-menu">
                        @foreach ($hizmetler as $h)
                            <li><a href="{{ route('yonetim.hizmet', $h) }}" @if ($h->is($hizmet)) aria-current="page" @endif>{{ $h->baslik }} <x-simge ad="sag" /></a></li>
                        @endforeach
                    </ul>
                </div>
            </aside>
        </div>
    </section>
@endsection
