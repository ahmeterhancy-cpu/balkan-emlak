@extends('duzen.site')

@section('baslik', 'Gayrimenkulünüzü Bize Bırakın')
@section('aciklama', 'Satmak ya da kiraya vermek istediğiniz gayrimenkul için portföy başvurusu yapın; danışmanımız sizi arasın.')

@php $hatalar = $errors->getBag('default'); @endphp

@section('icerik')
    @include('parca.sayfa-basi', [
        'baslik' => 'Gayrimenkulünüzü Bize Bırakın',
        'aciklama' => 'Satış ya da kiralama için gayrimenkulünüzün bilgilerini iletin; danışmanımız sizi arayarak birlikte değerlendirsin.',
        'kirinti' => ['Emlak Portföyü' => route('emlak.index'), 'Portföy başvurusu' => null],
        'gorsel' => site_gorsel('cta_gorsel', 'varsayilan-cagri.jpg'),
    ])

    <section class="bolum">
        <div class="kap form-sayfa">
            <div class="kutu">
                @if (session('basari_form') === 'portfoy')
                    <div class="bildirim bildirim-basari" role="status" style="margin-bottom:22px"><x-simge ad="onay" /><div><p>{{ session('basari') }}</p></div></div>
                @endif
                @if ($hatalar->any())
                    <div class="bildirim bildirim-hata" role="alert" style="margin-bottom:22px"><x-simge ad="uyari" />
                        <div><p>{{ $hatalar->first('genel') ?: 'Lütfen işaretli alanları kontrol edin.' }}</p></div>
                    </div>
                @endif

                <form action="{{ route('basvuru.portfoy.gonder') }}" method="post" class="form-izgara iki" novalidate data-form>
                    @include('parca.form-koruma')

                    <h2 class="form-bolum-baslik tam">İletişim bilgileriniz</h2>
                    @include('parca.alan', ['ad' => 'ad_soyad', 'etiket' => 'Ad soyad', 'zorunlu' => true, 'otomatik' => 'name', 'on' => 'p', 'sinif' => 'tam'])
                    @include('parca.alan', ['ad' => 'telefon', 'etiket' => 'Telefon', 'tur' => 'tel', 'zorunlu' => true, 'otomatik' => 'tel', 'on' => 'p'])
                    @include('parca.alan', ['ad' => 'eposta', 'etiket' => 'E-posta', 'tur' => 'email', 'otomatik' => 'email', 'on' => 'p'])

                    <h2 class="form-bolum-baslik tam">Gayrimenkul</h2>
                    <fieldset class="alan" style="border:0;margin:0;padding:0">
                        <legend class="alan-baslik">Ne yapmak istiyorsunuz?</legend>
                        <div class="onay-grup" style="grid-template-columns:1fr 1fr">
                            <label class="onay-kutu"><input type="radio" name="islem" value="satis" required @checked(old('islem', 'satis') === 'satis')> Satmak</label>
                            <label class="onay-kutu"><input type="radio" name="islem" value="kiralama" @checked(old('islem') === 'kiralama')> Kiraya vermek</label>
                        </div>
                        @if ($hatalar->has('islem'))<span class="alan-hata">{{ $hatalar->first('islem') }}</span>@endif
                    </fieldset>
                    <div class="alan">
                        <label for="p-kategori">Gayrimenkul türü</label>
                        <select class="secim" id="p-kategori" name="kategori" required @if ($hatalar->has('kategori')) aria-invalid="true" @endif>
                            @foreach (\App\Enums\Kategori::cases() as $k)
                                <option value="{{ $k->value }}" @selected(old('kategori') === $k->value)>{{ $k->getLabel() }}</option>
                            @endforeach
                        </select>
                        @if ($hatalar->has('kategori'))<span class="alan-hata">{{ $hatalar->first('kategori') }}</span>@endif
                    </div>
                    @include('parca.alan', ['ad' => 'konum', 'etiket' => 'Genel konum', 'zorunlu' => true, 'on' => 'p', 'sinif' => 'tam',
                        'ipucu' => 'Mahalle ve ilçe yeterli; açık adresi görüşmede paylaşabilirsiniz.', 'ek' => ['placeholder' => 'Örn. Şükrüpaşa, Edirne Merkez']])
                    <div class="alan tam">
                        <label for="p-mesaj">Kısa açıklama <span class="istege-bagli">(isteğe bağlı)</span></label>
                        <textarea class="metin-alani" id="p-mesaj" name="mesaj" rows="5" placeholder="Metrekare, oda sayısı, beklediğiniz fiyat, uygun olduğunuz saatler...">{{ old('mesaj') }}</textarea>
                    </div>

                    <div class="tam">@include('parca.kvkk-onay', ['on' => 'p'])</div>
                    <div class="tam"><button type="submit" class="dgm">Başvurumu gönder</button></div>
                </form>
            </div>

            <aside>
                <div class="yan-cagri">
                    <h2>Süreç nasıl işler?</h2>
                    <ol class="surec-cizgi" style="color:#fff">
                        <li>Danışmanımız sizi arar</li>
                        <li>Gayrimenkulü birlikte görür, belgeleri inceleriz</li>
                        <li>Fiyat ve ilan hazırlığı (fotoğraf, açıklama)</li>
                        <li>İlan yayına alınır, talepler size raporlanır</li>
                    </ol>
                </div>
                <div class="kutu" style="margin-top:24px">
                    <h2 class="kutu-baslik" style="font-size:17px">Gizliliğiniz</h2>
                    <p style="margin:0;font-size:14.5px">İlanlarda açık adres ve mülk sahibi bilgileri yayımlanmaz; yalnız genel konum görünür.</p>
                </div>
            </aside>
        </div>
    </section>
@endsection
