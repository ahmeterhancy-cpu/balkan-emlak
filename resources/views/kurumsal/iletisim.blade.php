@extends('duzen.site')

@section('baslik', 'İletişim')
@section('aciklama', 'Balkan Emlak ve Yönetim iletişim bilgileri ve iletişim formu.')

@php
    $hatalar = $errors->getBag('default');
    $kisiler = collect(ayar('iletisim_kisileri', []))->filter(fn ($k) => filled($k['telefon'] ?? null))->values();
@endphp

@section('icerik')
    @include('parca.sayfa-basi', [
        'baslik' => 'İletişim',
        'aciklama' => 'Emlak ve site yönetimi ile ilgili tüm sorularınız için bize ulaşın.',
        'kirinti' => ['İletişim' => null],
    ])

    <section class="bolum" style="padding-bottom:0">
        <div class="kap">
            <div class="iletisim-izgara">
                @foreach ($kisiler as $kisi)
                    <div class="iletisim-kart">
                        <span class="ilke-simge"><x-simge ad="telefon" /></span>
                        <div>
                            <h3>{{ $kisi['ad'] }}</h3>
                            @if (! empty($kisi['gorev']))<p class="kucuk">{{ $kisi['gorev'] }}</p>@endif
                            <p><a href="{{ tel_baglanti($kisi['telefon']) }}">{{ $kisi['telefon'] }}</a></p>
                            @if (! empty($kisi['whatsapp']))
                                <p><a href="{{ wa_baglanti($kisi['telefon']) }}" target="_blank" rel="noopener">WhatsApp ile yazın</a></p>
                            @endif
                        </div>
                    </div>
                @endforeach
                @if (ayar('eposta'))
                    <div class="iletisim-kart">
                        <span class="ilke-simge"><x-simge ad="eposta" /></span>
                        <div><h3>E-posta</h3><p><a href="mailto:{{ ayar('eposta') }}">{{ ayar('eposta') }}</a></p></div>
                    </div>
                @endif
                @if (ayar('adres'))
                    <div class="iletisim-kart">
                        <span class="ilke-simge"><x-simge ad="konum" /></span>
                        <div><h3>Ofis</h3><p>{{ ayar('adres') }}</p></div>
                    </div>
                @endif
                @if (ayar('calisma_saatleri'))
                    <div class="iletisim-kart">
                        <span class="ilke-simge"><x-simge ad="saat" /></span>
                        <div><h3>Çalışma saatleri</h3><p>{{ ayar('calisma_saatleri') }}</p></div>
                    </div>
                @endif
            </div>
            @if (ayar('acil_iletisim'))
                <div class="bildirim bildirim-uyari" style="margin-top:24px"><x-simge ad="uyari" /><div><p><strong>Yönettiğimiz sitelerde acil arıza:</strong> {{ ayar('acil_iletisim') }}</p></div></div>
            @endif
        </div>
    </section>

    <section class="bolum">
        <div class="kap form-sayfa">
            <div class="kutu">
                <h2>Bize yazın</h2>
                @if (session('basari_form') === 'iletisim')
                    <div class="bildirim bildirim-basari" role="status" style="margin-bottom:22px"><x-simge ad="onay" /><div><p>{{ session('basari') }}</p></div></div>
                @endif
                @if ($hatalar->any())
                    <div class="bildirim bildirim-hata" role="alert" style="margin-bottom:22px"><x-simge ad="uyari" />
                        <div><p>{{ $hatalar->first('genel') ?: 'Lütfen işaretli alanları kontrol edin.' }}</p></div>
                    </div>
                @endif
                <form action="{{ route('iletisim.gonder') }}" method="post" class="form-izgara iki" novalidate data-form>
                    @include('parca.form-koruma')
                    @include('parca.alan', ['ad' => 'ad_soyad', 'etiket' => 'Ad soyad', 'zorunlu' => true, 'otomatik' => 'name', 'on' => 'c', 'sinif' => 'tam'])
                    @include('parca.alan', ['ad' => 'telefon', 'etiket' => 'Telefon', 'tur' => 'tel', 'otomatik' => 'tel', 'on' => 'c', 'ipucu' => 'Telefon ya da e-postadan biri yeterli.'])
                    @include('parca.alan', ['ad' => 'eposta', 'etiket' => 'E-posta', 'tur' => 'email', 'otomatik' => 'email', 'on' => 'c'])
                    @include('parca.alan', ['ad' => 'konu', 'etiket' => 'Konu', 'zorunlu' => true, 'on' => 'c', 'sinif' => 'tam'])
                    <div class="alan tam">
                        <label for="c-mesaj">Mesajınız</label>
                        <textarea class="metin-alani" id="c-mesaj" name="mesaj" rows="6" required @if ($hatalar->has('mesaj')) aria-invalid="true" aria-describedby="c-mesaj-hata" @endif>{{ old('mesaj') }}</textarea>
                        @if ($hatalar->has('mesaj'))<span class="alan-hata" id="c-mesaj-hata">{{ $hatalar->first('mesaj') }}</span>@endif
                    </div>
                    <div class="tam">@include('parca.kvkk-onay', ['on' => 'c'])</div>
                    <div class="tam"><button type="submit" class="dgm">Gönder</button></div>
                </form>
            </div>

            <aside>
                @if (ayar('harita_embed') && Str::startsWith(ayar('harita_embed'), 'https://'))
                    <iframe class="harita" src="{{ ayar('harita_embed') }}" title="Ofis konumu" loading="lazy" referrerpolicy="no-referrer-when-downgrade" style="aspect-ratio:1"></iframe>
                @endif
                @if ($sorular->isNotEmpty())
                    <div class="sss-liste" style="margin-top:24px">
                        @foreach ($sorular as $soru)
                            <details><summary>{{ $soru->soru }} <x-simge ad="asagi" /></summary><div>{{ $soru->cevap }}</div></details>
                        @endforeach
                    </div>
                @endif
                <div class="yan-cagri" style="margin-top:24px">
                    <h3>Hızlı bağlantılar</h3>
                    <ul class="alt-liste" style="gap:10px">
                        <li><a href="{{ route('basvuru.portfoy') }}" style="color:#fff"><x-simge ad="sag" /> Gayrimenkulünüzü bize bırakın</a></li>
                        <li><a href="{{ route('yonetim.teklif') }}" style="color:#fff"><x-simge ad="sag" /> Yönetim teklifi alın</a></li>
                        <li><a href="{{ route('emlak.index') }}" style="color:#fff"><x-simge ad="sag" /> Emlak portföyü</a></li>
                    </ul>
                </div>
            </aside>
        </div>
    </section>
@endsection
