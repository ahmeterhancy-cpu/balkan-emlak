@extends('duzen.site')

@section('baslik', $sayfa?->seo_baslik ?: 'Hakkımızda')
@section('aciklama', $sayfa?->seo_aciklama ?: ($sayfa?->ozet ?: 'Balkan Emlak ve Yönetim hakkında.'))

@section('icerik')
    @include('parca.sayfa-basi', [
        'baslik' => 'Hakkımızda',
        'aciklama' => $sayfa?->ozet,
        'kirinti' => ['Hakkımızda' => null],
    ])

    <section class="bolum bolum-beyaz">
        <div class="kap ilkeler">
            <div>
                <div class="bolum-baslik sola">
                    <span class="ust-yazi">{{ ayar('firma_adi') }}</span>
                    <h2>Emlak ve yönetim, tek çatı altında</h2>
                </div>
                <div class="icerik-metni">
                    @if ($sayfa)
                        {!! \App\Support\Html::temizle($sayfa->icerik) !!}
                    @endif
                </div>
            </div>
            <div class="ilkeler-gorsel">
                @php $ilke = site_gorsel_kaynak('yonetim_gorsel', 'varsayilan-ilkeler.jpg'); @endphp
                <x-resim :yol="$ilke[0]" :kok="$ilke[1]" sizes="(min-width: 992px) 600px, 100vw" />
            </div>
        </div>
    </section>

    <section class="bolum">
        <div class="kap">
            <div class="bolum-baslik"><h2>İki alanda hizmet</h2></div>
            <div class="iki-giris-ic" style="display:grid;gap:24px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">
                <a href="{{ route('emlak.index') }}" class="giris-kart kutu" style="padding:28px">
                    <span class="giris-simge"><x-simge ad="ev" /></span>
                    <span><h2>Emlak danışmanlığı</h2><p>Satılık ve kiralık portföy, yer göstermesi, fiyat ve ilan hazırlığı.</p><span class="devam">Portföy <x-simge ad="ok" /></span></span>
                </a>
                <a href="{{ route('yonetim.index') }}" class="giris-kart kutu" style="padding:28px;margin:0">
                    <span class="giris-simge"><x-simge ad="bina" /></span>
                    <span><h2>Site ve apartman yönetimi</h2><p>Genel, teknik, temizlik ve bahçe yönetimi; şeffaf aidat takibi.</p><span class="devam">Hizmetler <x-simge ad="ok" /></span></span>
                </a>
            </div>
        </div>
    </section>

    @if ($ekip->isNotEmpty())
        <section class="bolum bolum-beyaz">
            <div class="kap">
                <div class="bolum-baslik"><h2>Ekibimiz</h2></div>
                <div class="ekip-izgara">
                    @foreach ($ekip as $kisi)
                        @include('parca.ekip-kart', ['kisi' => $kisi])
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
