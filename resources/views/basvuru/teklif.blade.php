@extends('duzen.site')

@section('baslik', 'Yönetim Teklifi Alın')
@section('aciklama', 'Siteniz ya da apartmanınız için profesyonel yönetim teklifi isteyin: blok, bağımsız bölüm sayısı ve ihtiyaçlarınızı iletin.')

@php
    $hatalar = $errors->getBag('default');
    $secililer = old('ihtiyaclar', $seciliHizmet ? $hizmetler->where('slug', $seciliHizmet)->pluck('baslik')->all() : []);
@endphp

@section('icerik')
    @include('parca.sayfa-basi', [
        'baslik' => 'Yönetim Teklifi Alın',
        'aciklama' => 'Yapınızın bilgilerini ve ihtiyaçlarınızı iletin; keşif sonrasında yazılı teklifimizi hazırlayalım.',
        'kirinti' => ['Site Yönetimi' => route('yonetim.index'), 'Yönetim teklifi' => null],
        'gorsel' => site_gorsel_kaynak('yonetim_gorsel', 'varsayilan-yonetim.jpg'),
    ])

    <section class="bolum">
        <div class="kap form-sayfa">
            <div class="kutu">
                @if (session('basari_form') === 'teklif')
                    <div class="bildirim bildirim-basari" role="status" style="margin-bottom:22px"><x-simge ad="onay" /><div><p>{{ session('basari') }}</p></div></div>
                @endif
                @if ($hatalar->any())
                    <div class="bildirim bildirim-hata" role="alert" style="margin-bottom:22px"><x-simge ad="uyari" />
                        <div><p>{{ $hatalar->first('genel') ?: 'Lütfen işaretli alanları kontrol edin.' }}</p></div>
                    </div>
                @endif

                <form action="{{ route('yonetim.teklif.gonder') }}" method="post" class="form-izgara iki" novalidate data-form>
                    @include('parca.form-koruma')

                    <h2 class="form-bolum-baslik tam">İletişim bilgileriniz</h2>
                    @include('parca.alan', ['ad' => 'ad_soyad', 'etiket' => 'Ad soyad', 'zorunlu' => true, 'otomatik' => 'name', 'on' => 't', 'sinif' => 'tam'])
                    @include('parca.alan', ['ad' => 'telefon', 'etiket' => 'Telefon', 'tur' => 'tel', 'otomatik' => 'tel', 'on' => 't',
                        'ipucu' => 'Telefon ya da e-postadan biri yeterli.'])
                    @include('parca.alan', ['ad' => 'eposta', 'etiket' => 'E-posta', 'tur' => 'email', 'otomatik' => 'email', 'on' => 't'])
                    <div class="alan tam">
                        <label for="t-iletisim_tercihi">Size nasıl ulaşalım? <span class="istege-bagli">(isteğe bağlı)</span></label>
                        <select class="secim" id="t-iletisim_tercihi" name="iletisim_tercihi">
                            <option value="">Farketmez</option>
                            <option value="telefon" @selected(old('iletisim_tercihi') === 'telefon')>Telefon</option>
                            <option value="eposta" @selected(old('iletisim_tercihi') === 'eposta')>E-posta</option>
                            <option value="whatsapp" @selected(old('iletisim_tercihi') === 'whatsapp')>WhatsApp</option>
                        </select>
                    </div>

                    <h2 class="form-bolum-baslik tam">Yapı bilgileri</h2>
                    @include('parca.alan', ['ad' => 'site_adi', 'etiket' => 'Site / apartman adı', 'zorunlu' => true, 'on' => 't'])
                    @include('parca.alan', ['ad' => 'konum', 'etiket' => 'Konum', 'zorunlu' => true, 'on' => 't', 'ek' => ['placeholder' => 'Mahalle, ilçe']])
                    @include('parca.alan', ['ad' => 'blok_sayisi', 'etiket' => 'Blok sayısı', 'tur' => 'number', 'on' => 't', 'ek' => ['min' => 1, 'inputmode' => 'numeric']])
                    @include('parca.alan', ['ad' => 'bagimsiz_bolum_sayisi', 'etiket' => 'Bağımsız bölüm sayısı', 'tur' => 'number', 'zorunlu' => true, 'on' => 't', 'ek' => ['min' => 1, 'inputmode' => 'numeric']])

                    <fieldset class="alan tam" style="border:0;margin:0;padding:0">
                        <legend class="alan-baslik">İhtiyaç duyduğunuz hizmetler <span class="istege-bagli">(birden fazla seçebilirsiniz)</span></legend>
                        <div class="onay-grup">
                            @foreach ($hizmetler as $h)
                                <label class="onay-kutu"><input type="checkbox" name="ihtiyaclar[]" value="{{ $h->baslik }}" @checked(in_array($h->baslik, $secililer, true))> {{ $h->baslik }}</label>
                            @endforeach
                        </div>
                        @if ($hatalar->has('ihtiyaclar.*') || $hatalar->has('ihtiyaclar'))<span class="alan-hata">{{ $hatalar->first('ihtiyaclar.*') ?: $hatalar->first('ihtiyaclar') }}</span>@endif
                    </fieldset>

                    <div class="alan tam">
                        <label for="t-mesaj">Eklemek istedikleriniz <span class="istege-bagli">(isteğe bağlı)</span></label>
                        <textarea class="metin-alani" id="t-mesaj" name="mesaj" rows="5" placeholder="Mevcut yönetim durumu, personel, özel ihtiyaçlar...">{{ old('mesaj') }}</textarea>
                    </div>

                    <div class="tam">@include('parca.kvkk-onay', ['on' => 't'])</div>
                    <div class="tam"><button type="submit" class="dgm">Teklif talebimi gönder</button></div>
                </form>
            </div>

            <aside>
                <div class="yan-cagri">
                    <h2>{{ ayar('yonetim_slogani') }}</h2>
                    <p>Teklif süreci: ihtiyaç görüşmesi, yerinde keşif ve yazılı teklif. Formu gönderdikten sonra sizi arayacağız.</p>
                    @foreach (collect(ayar('iletisim_kisileri', []))->filter(fn ($k) => filled($k['telefon'] ?? null)) as $kisi)
                        <a class="tel" href="{{ tel_baglanti($kisi['telefon']) }}"><x-simge ad="telefon" /> {{ $kisi['telefon'] }} <span style="font-weight:400;opacity:.8">· {{ $kisi['ad'] }}</span></a>
                    @endforeach
                </div>
                @if (ayar('acil_iletisim'))
                    <div class="bildirim bildirim-uyari" style="margin-top:24px"><x-simge ad="uyari" /><div><p><strong>Acil arıza için</strong> formu değil telefonu kullanın: {{ ayar('acil_iletisim') }}</p></div></div>
                @else
                    <div class="bildirim bildirim-bilgi" style="margin-top:24px"><x-simge ad="bilgi" /><div><p>Bu form acil arıza bildirimi için değildir; acil durumlarda lütfen telefonla ulaşın.</p></div></div>
                @endif
            </aside>
        </div>
    </section>
@endsection
