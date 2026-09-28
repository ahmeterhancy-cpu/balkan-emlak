<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('baslik')@yield('baslik') | {{ ayar('firma_adi') }}@else{{ ayar('firma_adi') }} — Emlak ve Site Yönetimi @endif</title>
    <meta name="description" content="@yield('aciklama', ayar('seo_aciklama'))">
    <meta name="robots" content="@yield('robots', app()->isProduction() ? 'index, follow' : 'noindex, nofollow')">
    <link rel="canonical" href="@yield('kanonik', url()->current())">
    <meta name="theme-color" content="#142d3e">

    <meta property="og:site_name" content="{{ ayar('firma_adi') }}">
    <meta property="og:type" content="@yield('og_tur', 'website')">
    <meta property="og:title" content="@yield('baslik', ayar('firma_adi'))">
    <meta property="og:description" content="@yield('aciklama', ayar('seo_aciklama'))">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="tr_TR">
    @hasSection('og_gorsel')
        <meta property="og:image" content="@yield('og_gorsel')">
        <meta name="twitter:card" content="summary_large_image">
    @endif

    @if (ayar('logo'))
        <link rel="icon" href="{{ Storage::disk('medya')->url(ayar('logo')) }}">
    @else
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @endif

    @stack('yapisal_veri')

    <script>document.documentElement.classList.add('js')</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a href="#icerik" class="gorunmez gorunmez-odak">İçeriğe atla</a>

    @yield('serit')

    @php
        $anaTelefon = ayar('telefon');
        $kisiler = collect(ayar('iletisim_kisileri', []))->filter(fn ($k) => filled($k['telefon'] ?? null))->values();
        $sosyal = collect(['instagram' => 'Instagram', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube'])
            ->filter(fn ($ad, $anahtar) => filled(ayar($anahtar)));
        $emlakEtkin = request()->routeIs('emlak.*', 'ilan.*', 'basvuru.portfoy*');
        $yonetimEtkin = request()->routeIs('yonetim.*');
    @endphp

    <header class="ust">
        <div class="kap ust-ic">
            <a href="{{ route('anasayfa') }}" class="logo" aria-label="{{ ayar('firma_adi') }} — ana sayfa">
                @if (ayar('logo'))
                    <img src="{{ Storage::disk('medya')->url(ayar('logo')) }}" alt="{{ ayar('firma_adi') }}" width="200" height="56">
                @else
                    <span class="logo-ad">Balkan</span>
                    <span class="logo-alt">Emlak ve Yönetim</span>
                @endif
            </a>

            <div class="ust-bilgiler">
                @if ($anaTelefon)
                    <div class="ust-bilgi">
                        <span class="ust-bilgi-simge"><x-simge ad="telefon" /></span>
                        <div>
                            <strong><a href="{{ tel_baglanti($anaTelefon) }}">{{ $anaTelefon }}</a></strong>
                            <span>Bizi arayın</span>
                        </div>
                    </div>
                @endif
                @if (ayar('adres'))
                    <div class="ust-bilgi">
                        <span class="ust-bilgi-simge"><x-simge ad="konum" /></span>
                        <div><strong>{{ Str::limit(ayar('adres'), 34) }}</strong><span>Ofisimiz</span></div>
                    </div>
                @endif
                @if (ayar('calisma_saatleri'))
                    <div class="ust-bilgi">
                        <span class="ust-bilgi-simge"><x-simge ad="saat" /></span>
                        <div><strong>{{ ayar('calisma_saatleri') }}</strong><span>Çalışma saatleri</span></div>
                    </div>
                @endif
                <div class="ust-bilgi">
                    <span class="ust-bilgi-simge"><x-simge ad="yonetim" /></span>
                    <div>
                        <strong><a href="{{ route('yonetim.teklif') }}">Yönetim Teklifi Al</a></strong>
                        <span>Apartman ve site yönetimi</span>
                    </div>
                </div>
            </div>

            <div class="ust-mobil">
                @if ($anaTelefon)
                    <a href="{{ tel_baglanti($anaTelefon) }}" class="simge-dugme" aria-label="Ara: {{ $anaTelefon }}"><x-simge ad="telefon" /></a>
                @endif
                <button type="button" class="simge-dugme" data-cekmece-ac aria-controls="cekmece" aria-expanded="false" aria-label="Menüyü aç">
                    <x-simge ad="menu" />
                </button>
            </div>
        </div>
    </header>

    <nav class="menu-bandi" aria-label="Ana menü">
        <div class="kap menu-bandi-ic">
            <ul class="ana-menu">
                <li><a href="{{ route('anasayfa') }}" @if (request()->routeIs('anasayfa')) aria-current="page" @endif>Ana Sayfa</a></li>
                <li><a href="{{ route('hakkimizda') }}" @if (request()->routeIs('hakkimizda')) aria-current="page" @endif>Hakkımızda</a></li>
                <li class="{{ $emlakEtkin ? 'etkin' : '' }}">
                    <a href="{{ route('emlak.index') }}" aria-haspopup="true">Emlak Portföyü <x-simge ad="asagi" /></a>
                    <ul class="alt-menu">
                        <li><a href="{{ route('emlak.index') }}">Tüm ilanlar</a></li>
                        <li><a href="{{ route('emlak.index', ['islem' => 'satilik']) }}">Satılık</a></li>
                        <li><a href="{{ route('emlak.index', ['islem' => 'kiralik']) }}">Kiralık</a></li>
                        <li class="ayrac" role="separator"></li>
                        <li><a href="{{ route('emlak.index', ['kategori' => 'konut']) }}">Konut</a></li>
                        <li><a href="{{ route('emlak.index', ['kategori' => 'arsa']) }}">Arsa</a></li>
                        <li><a href="{{ route('emlak.index', ['kategori' => 'ticari']) }}">Ticari</a></li>
                        <li class="ayrac" role="separator"></li>
                        <li class="vurgulu"><a href="{{ route('basvuru.portfoy') }}">Gayrimenkulünüzü Bize Bırakın</a></li>
                    </ul>
                </li>
                <li class="{{ $yonetimEtkin ? 'etkin' : '' }}">
                    <a href="{{ route('yonetim.index') }}" aria-haspopup="true">Site Yönetimi <x-simge ad="asagi" /></a>
                    <ul class="alt-menu">
                        <li><a href="{{ route('yonetim.index') }}">Site ve Apartman Yönetimi</a></li>
                        <li class="ayrac" role="separator"></li>
                        @foreach ($menuHizmetleri as $hizmet)
                            <li><a href="{{ route('yonetim.hizmet', $hizmet) }}">{{ $hizmet->baslik }}</a></li>
                        @endforeach
                        <li class="ayrac" role="separator"></li>
                        <li class="vurgulu"><a href="{{ route('yonetim.teklif') }}">Yönetim Teklifi Al</a></li>
                    </ul>
                </li>
                @if ($referansVar)
                    <li><a href="{{ route('referanslar') }}" @if (request()->routeIs('referanslar')) aria-current="page" @endif>Referanslar</a></li>
                @endif
                <li><a href="{{ route('bilgi.index') }}" @if (request()->routeIs('bilgi.*')) aria-current="page" @endif>Bilgi Merkezi</a></li>
                <li><a href="{{ route('iletisim') }}" @if (request()->routeIs('iletisim')) aria-current="page" @endif>İletişim</a></li>
            </ul>
            <div class="menu-sag">
                @foreach ($sosyal as $anahtar => $ad)
                    <a href="{{ ayar($anahtar) }}" target="_blank" rel="noopener" aria-label="{{ $ad }}"><x-simge :ad="$anahtar" /></a>
                @endforeach
                <a href="{{ route('iletisim') }}" class="dgm dgm-kucuk">Bize Ulaşın</a>
            </div>
        </div>
    </nav>

    {{-- Mobil çekmece --}}
    <div class="cekmece" id="cekmece" data-cekmece aria-hidden="true">
        <div class="cekmece-perde" data-cekmece-kapat></div>
        <div class="cekmece-panel" role="dialog" aria-modal="true" aria-label="Menü">
            <div class="cekmece-ust">
                <a href="{{ route('anasayfa') }}" class="logo" style="color:#fff">
                    <span class="logo-ad" style="font-size:20px">Balkan</span>
                    <span class="logo-alt" style="color:var(--yesil-acik)">Emlak ve Yönetim</span>
                </a>
                <button type="button" class="simge-dugme" data-cekmece-kapat aria-label="Menüyü kapat"><x-simge ad="kapat" /></button>
            </div>
            <ul class="cekmece-menu">
                <li><a href="{{ route('anasayfa') }}">Ana Sayfa</a></li>
                <li><a href="{{ route('hakkimizda') }}">Hakkımızda</a></li>
                <li>
                    <details @if ($emlakEtkin) open @endif>
                        <summary>Emlak Portföyü <x-simge ad="asagi" /></summary>
                        <ul>
                            <li><a href="{{ route('emlak.index') }}">Tüm ilanlar</a></li>
                            <li><a href="{{ route('emlak.index', ['islem' => 'satilik']) }}">Satılık</a></li>
                            <li><a href="{{ route('emlak.index', ['islem' => 'kiralik']) }}">Kiralık</a></li>
                            <li><a href="{{ route('basvuru.portfoy') }}">Gayrimenkulünüzü Bize Bırakın</a></li>
                        </ul>
                    </details>
                </li>
                <li>
                    <details @if ($yonetimEtkin) open @endif>
                        <summary>Site Yönetimi <x-simge ad="asagi" /></summary>
                        <ul>
                            <li><a href="{{ route('yonetim.index') }}">Site ve Apartman Yönetimi</a></li>
                            @foreach ($menuHizmetleri as $hizmet)
                                <li><a href="{{ route('yonetim.hizmet', $hizmet) }}">{{ $hizmet->baslik }}</a></li>
                            @endforeach
                            <li><a href="{{ route('yonetim.teklif') }}">Yönetim Teklifi Al</a></li>
                        </ul>
                    </details>
                </li>
                @if ($referansVar)
                    <li><a href="{{ route('referanslar') }}">Referanslar</a></li>
                @endif
                <li><a href="{{ route('bilgi.index') }}">Bilgi Merkezi</a></li>
                <li><a href="{{ route('iletisim') }}">İletişim</a></li>
            </ul>
            <div class="cekmece-alt">
                @foreach ($kisiler as $kisi)
                    <a href="{{ tel_baglanti($kisi['telefon']) }}"><x-simge ad="telefon" /> {{ $kisi['telefon'] }} · {{ $kisi['ad'] }}</a>
                @endforeach
                <a href="{{ route('yonetim.teklif') }}" class="dgm">Yönetim Teklifi Al</a>
            </div>
        </div>
    </div>

    <main id="icerik">
        @if (session('basari') && ! session('basari_form'))
            <div class="kap" style="padding-top:24px">
                <div class="bildirim bildirim-basari" role="status"><x-simge ad="onay" /><div><p>{{ session('basari') }}</p></div></div>
            </div>
        @endif

        @yield('icerik')
    </main>

    <footer class="alt">
        <div class="kap alt-ust">
            <div class="alt-logo">
                <a href="{{ route('anasayfa') }}" class="logo">
                    @if (ayar('logo_acik'))
                        <img src="{{ Storage::disk('medya')->url(ayar('logo_acik')) }}" alt="{{ ayar('firma_adi') }}" width="200" height="56">
                    @else
                        <span class="logo-ad">Balkan</span>
                        <span class="logo-alt">Emlak ve Yönetim</span>
                    @endif
                </a>
                <p>Satılık ve kiralık gayrimenkul danışmanlığı ile apartman ve site yönetimi — tek marka, tek muhatap.</p>
                @if ($sosyal->isNotEmpty())
                    <div class="alt-sosyal">
                        @foreach ($sosyal as $anahtar => $ad)
                            <a href="{{ ayar($anahtar) }}" target="_blank" rel="noopener" aria-label="{{ $ad }}"><x-simge :ad="$anahtar" /></a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                <h3>Emlak</h3>
                <ul class="alt-liste">
                    <li><a href="{{ route('emlak.index', ['islem' => 'satilik']) }}"><x-simge ad="sag" /> Satılık ilanlar</a></li>
                    <li><a href="{{ route('emlak.index', ['islem' => 'kiralik']) }}"><x-simge ad="sag" /> Kiralık ilanlar</a></li>
                    <li><a href="{{ route('basvuru.portfoy') }}"><x-simge ad="sag" /> Gayrimenkulünüzü bize bırakın</a></li>
                    <li><a href="{{ route('hakkimizda') }}"><x-simge ad="sag" /> Hakkımızda</a></li>
                    <li><a href="{{ route('bilgi.index') }}"><x-simge ad="sag" /> Bilgi merkezi</a></li>
                </ul>
            </div>

            <div>
                <h3>Site Yönetimi</h3>
                <ul class="alt-liste">
                    @foreach ($menuHizmetleri->take(6) as $hizmet)
                        <li><a href="{{ route('yonetim.hizmet', $hizmet) }}"><x-simge ad="sag" /> {{ $hizmet->baslik }}</a></li>
                    @endforeach
                    <li><a href="{{ route('yonetim.teklif') }}"><x-simge ad="sag" /> Yönetim teklifi al</a></li>
                </ul>
            </div>

            <div>
                <h3>İletişim</h3>
                <ul class="alt-iletisim">
                    @foreach ($kisiler as $kisi)
                        <li>
                            <x-simge ad="telefon" />
                            <div>
                                <strong>{{ $kisi['ad'] }}</strong>
                                <a href="{{ tel_baglanti($kisi['telefon']) }}">{{ $kisi['telefon'] }}</a>
                            </div>
                        </li>
                    @endforeach
                    @if (ayar('eposta'))
                        <li><x-simge ad="eposta" /><a href="mailto:{{ ayar('eposta') }}">{{ ayar('eposta') }}</a></li>
                    @endif
                    @if (ayar('adres'))
                        <li><x-simge ad="konum" /><span>{{ ayar('adres') }}</span></li>
                    @endif
                </ul>
            </div>
        </div>
        <div class="alt-alt">
            <div class="kap">
                <span>© {{ now()->year }} {{ ayar('firma_unvani') ?: ayar('firma_adi') }}. Tüm hakları saklıdır.</span>
                <ul>
                    <li><a href="{{ route('sayfa', 'kvkk-aydinlatma-metni') }}">KVKK Aydınlatma Metni</a></li>
                    <li><a href="{{ route('sayfa', 'gizlilik-politikasi') }}">Gizlilik</a></li>
                    <li><a href="{{ route('sayfa', 'cerez-politikasi') }}">Çerez Politikası</a></li>
                </ul>
            </div>
        </div>
    </footer>

    <a href="#" class="yukari" data-yukari aria-label="Sayfanın başına dön"><x-simge ad="sag" /></a>

    @stack('son')
</body>
</html>
