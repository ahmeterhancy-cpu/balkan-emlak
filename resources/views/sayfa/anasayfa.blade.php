@extends('duzen.site')

@section('aciklama', ayar('seo_aciklama'))
@section('og_gorsel', site_gorsel('hero_gorsel', 'varsayilan-kahraman.jpg'))

@push('yapisal_veri')
    <script type="application/ld+json">{!! json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'RealEstateAgent',
        'name' => ayar('firma_adi'),
        'url' => route('anasayfa'),
        'telephone' => ayar('telefon'),
        'email' => ayar('eposta'),
        'address' => ayar('adres'),
        'areaServed' => ['@type' => 'AdministrativeArea', 'name' => 'Edirne'],
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('icerik')
    {{-- 1. Kahraman: marka mesajı, iki ana düğme, hızlı arama --}}
    @php $kahraman = site_gorsel_kaynak('hero_gorsel', 'varsayilan-kahraman.jpg'); @endphp
    <section class="kahraman">
        <x-resim :yol="$kahraman[0]" :kok="$kahraman[1]" class="arka-resim" sizes="100vw" :oncelikli="true" />
        <div class="kap">
            <div class="kahraman-metin">
                <div class="kahraman-ust"><span>Emlak</span><span>Site ve Apartman Yönetimi</span></div>
                <h1>{{ ayar('ana_baslik') }}</h1>
                <p>{{ ayar('ana_aciklama') }}</p>
                <div class="kahraman-dgmler">
                    <a href="{{ route('emlak.index') }}" class="dgm">Emlak Portföyünü İncele <x-simge ad="ok" /></a>
                    <a href="{{ route('yonetim.teklif') }}" class="dgm dgm-beyaz-cizgi">Yönetim Teklifi Al</a>
                </div>
            </div>

            <form class="arama" action="{{ route('emlak.index') }}" method="get" role="search" aria-label="İlan ara" data-arama>
                <input type="hidden" name="islem" value="" data-arama-islem>
                <div class="arama-sekmeler" role="group" aria-label="İşlem türü">
                    <button type="button" class="arama-sekme" aria-pressed="true" data-islem="">Tümü</button>
                    <button type="button" class="arama-sekme" aria-pressed="false" data-islem="satilik">Satılık</button>
                    <button type="button" class="arama-sekme" aria-pressed="false" data-islem="kiralik">Kiralık</button>
                </div>
                <div class="arama-kutu">
                    <div class="alan">
                        <label for="a-konum">Konum</label>
                        <select class="secim" id="a-konum" name="konum">
                            <option value="">Tüm konumlar</option>
                            @foreach ($konumlar as $il)
                                <optgroup label="{{ $il->ad }}">
                                    <option value="il-{{ $il->id }}">Tüm {{ $il->ad }}</option>
                                    @foreach ($il->altlar as $ilce)
                                        <option value="ilce-{{ $ilce->id }}">{{ $ilce->ad }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="alan">
                        <label for="a-tur">Gayrimenkul türü</label>
                        <select class="secim" id="a-tur" name="kategori" data-kategori-secimi>
                            <option value="">Tüm türler</option>
                            @foreach (\App\Enums\Kategori::cases() as $k)
                                <optgroup label="{{ $k->getLabel() }}">
                                    <option value="{{ $k->value }}">Tüm {{ mb_strtolower($k->getLabel()) }}</option>
                                    @foreach ($k->tipler() as $tip => $etiket)
                                        <option value="{{ $k->value }}:{{ $tip }}">{{ $etiket }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="alan" data-oda-alani>
                        <label for="a-oda">Oda sayısı</label>
                        <select class="secim" id="a-oda" name="oda">
                            <option value="">Farketmez</option>
                            @foreach (\App\Services\PortfoyArama::ODALAR as $oda)
                                <option value="{{ $oda }}">{{ $oda }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="alan">
                        <label for="a-fiyat">En yüksek fiyat (₺)</label>
                        <input class="girdi" id="a-fiyat" name="fiyat_max" inputmode="numeric" placeholder="Örn. 5.000.000" autocomplete="off">
                    </div>
                    <button type="submit" class="dgm"><x-simge ad="arama" /> Ara</button>
                </div>
            </form>
        </div>
    </section>

    {{-- İki eşit giriş: emlak ve site yönetimi ilk ekranda --}}
    <section class="iki-giris" aria-label="Hizmet alanlarımız">
        <div class="kap iki-giris-ic">
            <a href="{{ route('emlak.index') }}" class="giris-kart">
                <span class="giris-simge"><x-simge ad="ev" /></span>
                <span>
                    <h2>Emlak</h2>
                    <p>Satılık ve kiralık konut, arsa ve ticari portföyü inceleyin; danışmanımızla yer göstermesi planlayın.</p>
                    <span class="devam">Portföyü incele <x-simge ad="ok" /></span>
                </span>
            </a>
            <a href="{{ route('yonetim.index') }}" class="giris-kart">
                <span class="giris-simge"><x-simge ad="bina" /></span>
                <span>
                    <h2>Site ve Apartman Yönetimi</h2>
                    <p>{{ ayar('yonetim_slogani') }} Aidat, bakım, temizlik ve teknik işler tek elden, şeffaf raporlamayla.</p>
                    <span class="devam">Hizmetleri gör <x-simge ad="ok" /></span>
                </span>
            </a>
        </div>
    </section>

    {{-- 2. Site ve apartman yönetimi hizmetleri --}}
    @if ($hizmetler->isNotEmpty())
        <section class="bolum bolum-gri" id="site-yonetimi">
            <div class="kap">
                <div class="bolum-baslik">
                    <span class="ust-yazi">{{ ayar('alt_marka') }}</span>
                    <h2>Site ve Apartman Yönetimi</h2>
                    <p>Ortak alanların bakımından aidat takibine kadar yönetimin her adımı kayıt altında ve raporlanır.</p>
                </div>
                <div class="hizmet-izgara">
                    @foreach ($hizmetler as $hizmet)
                        <article class="hizmet-kart">
                            <span class="hizmet-simge"><x-simge :ad="$hizmet->ikon ?: 'yonetim'" /></span>
                            <h3><a href="{{ route('yonetim.hizmet', $hizmet) }}">{{ $hizmet->baslik }}</a></h3>
                            <p>{{ $hizmet->ozet }}</p>
                            <span class="devam">Detaylı bilgi <x-simge ad="ok" /></span>
                        </article>
                    @endforeach
                </div>
                <div class="yonetim-slogan">
                    <div>
                        <strong>{{ ayar('yonetim_slogani') }}</strong>
                        <p>Sitenizin ya da apartmanınızın ihtiyaçlarını dinleyelim, keşif sonrasında yazılı teklifimizi hazırlayalım.</p>
                    </div>
                    <a href="{{ route('yonetim.teklif') }}" class="dgm">Yönetim Teklifi Al</a>
                </div>
            </div>
        </section>
    @endif

    {{-- 3. Son eklenen ilanlar --}}
    @if ($sonIlanlar->isNotEmpty())
        <section class="bolum bolum-beyaz">
            <div class="kap">
                <div class="bolum-baslik">
                    <h2>Son Eklenen İlanlar</h2>
                    <p>Portföyümüze yeni katılan satılık ve kiralık gayrimenkuller.</p>
                </div>
                <div class="ilan-izgara uc">
                    @foreach ($sonIlanlar as $ilan)
                        @include('parca.ilan-kart', ['ilan' => $ilan])
                    @endforeach
                </div>
                <div class="bolum-alt">
                    <a href="{{ route('emlak.index') }}" class="dgm dgm-cizgi">Tüm ilanları gör <x-simge ad="ok" /></a>
                </div>
            </div>
        </section>
    @endif

    {{-- 4. Portföy başvurusu çağrısı --}}
    @php $cagri = site_gorsel_kaynak('cta_gorsel', 'varsayilan-cagri.jpg'); @endphp
    <section class="cagri">
        <x-resim :yol="$cagri[0]" :kok="$cagri[1]" class="arka-resim" sizes="100vw" />
        <div class="kap">
            <h2>Gayrimenkulünüzü satmak ya da kiraya vermek mi istiyorsunuz?</h2>
            <p>Bilgilerinizi bırakın; danışmanımız gayrimenkulünüzü birlikte değerlendirmek, fotoğraf ve ilan hazırlığını planlamak için sizi arasın.</p>
            <div class="dgmler">
                <a href="{{ route('basvuru.portfoy') }}" class="dgm">Gayrimenkulünüzü Bize Bırakın</a>
                <a href="{{ route('emlak.index') }}" class="dgm dgm-beyaz-cizgi">İlanlara Göz At</a>
            </div>
        </div>
    </section>

    {{-- 5. Öne çıkan ilanlar --}}
    @if ($oneCikanlar->isNotEmpty())
        <section class="bolum">
            <div class="kap">
                <div class="bolum-baslik">
                    <h2>Öne Çıkan İlanlar</h2>
                    <p>Danışmanlarımızın öne çıkardığı portföyler.</p>
                </div>
                <div class="kaydirici" data-kaydirici aria-roledescription="carousel" aria-label="Öne çıkan ilanlar">
                    <div class="kaydirici-ray">
                        @foreach ($oneCikanlar as $i => $ilan)
                            <div class="kaydirici-slayt {{ $i === 0 ? 'etkin' : '' }}" role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} / {{ $oneCikanlar->count() }}" @if ($i > 0) aria-hidden="true" @endif>
                                <article class="one-cikan">
                                    <a href="{{ route('ilan.detay', $ilan) }}" class="one-cikan-gorsel" tabindex="-1" aria-hidden="true">
                                        @if ($ilan->kapak)
                                            <x-resim :yol="$ilan->kapak->yol" sizes="(min-width: 1280px) 1200px, 100vw" />
                                        @endif
                                    </a>
                                    <div class="one-cikan-kart">
                                        <span class="rozet rozet-yesil"><x-simge ad="yildiz" /> Öne çıkan</span>
                                        <h3><a href="{{ route('ilan.detay', $ilan) }}" @if ($i > 0) tabindex="-1" @endif>{{ $ilan->baslik }}</a></h3>
                                        <p class="ilan-konum"><x-simge ad="konum" /> {{ $ilan->konumMetni() }}</p>
                                        <p class="ozet">{{ Str::limit(strip_tags((string) $ilan->aciklama), 120) }}</p>
                                        <ul class="ilan-ozet">
                                            @if ($ilan->kategori->odaAlani() && $ilan->oda)
                                                <li>Oda <b><x-simge ad="yatak" /> {{ $ilan->oda }}</b></li>
                                            @endif
                                            @if ($ilan->kategori->yapiAlanlari() && $ilan->banyo)
                                                <li>Banyo <b><x-simge ad="banyo" /> {{ $ilan->banyo }}</b></li>
                                            @endif
                                            @if ($ilan->alanMetni())
                                                <li>Alan <b><x-simge ad="alan" /> {{ $ilan->alanMetni() }}</b></li>
                                            @endif
                                        </ul>
                                        <div class="ilan-kart-alt">
                                            <div>
                                                <span class="ilan-islem">{{ $ilan->islem->getLabel() }}</span>
                                                <span class="fiyat">{{ $ilan->fiyatMetni() }}@if ($ilan->islem->value === 'kiralik')<small> / ay</small>@endif</span>
                                            </div>
                                            <span class="ilan-no">{{ $ilan->portfoy_no }}</span>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        @endforeach
                    </div>
                    @if ($oneCikanlar->count() > 1)
                        <button type="button" class="kaydirici-ok onceki" data-onceki aria-label="Önceki ilan"><x-simge ad="sol" /></button>
                        <button type="button" class="kaydirici-ok sonraki" data-sonraki aria-label="Sonraki ilan"><x-simge ad="sag" /></button>
                        <div class="kaydirici-noktalar" data-noktalar>
                            @foreach ($oneCikanlar as $i => $ilan)
                                <button type="button" aria-label="{{ $i + 1 }}. ilan" @if ($i === 0) aria-current="true" @endif></button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    {{-- 6. Gayrimenkul kategorileri (yalnız ilanı olanlar) --}}
    @if ($kategoriler->count() > 1)
        <section class="bolum bolum-beyaz">
            <div class="kap">
                <div class="bolum-baslik">
                    <h2>Gayrimenkul Kategorileri</h2>
                    <p>Aradığınız türe göre portföyü daraltın.</p>
                </div>
                <div class="kategori-izgara">
                    @foreach ($kategoriler as $k)
                        <a href="{{ route('emlak.index', ['kategori' => $k['kategori']->value]) }}" class="kategori-kart"
                           @if ($k['gorsel']) style="background-image:url('{{ $k['gorsel'] }}')" @endif>
                            <div>
                                <h3>{{ $k['kategori']->getLabel() }}</h3>
                                <span>{{ $k['adet'] }} ilan</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 7. Çalışma ilkeleri --}}
    <section class="bolum bolum-gri">
        <div class="kap ilkeler">
            <div class="ilkeler-gorsel">
                @php $ilke = site_gorsel_kaynak('yonetim_gorsel', 'varsayilan-ilkeler.jpg'); @endphp
                <x-resim :yol="$ilke[0]" :kok="$ilke[1]" sizes="(min-width: 992px) 600px, 100vw" />
                <div class="rozet-kutu">
                    <strong>Tek marka, iki uzmanlık</strong>
                    <span>Emlak danışmanlığı ve profesyonel yönetim</span>
                </div>
            </div>
            <div>
                <div class="bolum-baslik sola" style="margin-bottom:0">
                    <span class="ust-yazi">Çalışma ilkelerimiz</span>
                    <h2>Güven, düzen ve açık iletişim</h2>
                </div>
                <ul class="ilke-liste">
                    <li>
                        <span class="ilke-simge"><x-simge ad="rapor" /></span>
                        <div><h3>Şeffaf raporlama</h3><p>Aidat, gider ve tahsilatlar belgeleriyle kayda alınır; dönem raporu maliklerle paylaşılır.</p></div>
                    </li>
                    <li>
                        <span class="ilke-simge"><x-simge ad="bakim" /></span>
                        <div><h3>Düzenli bakım</h3><p>Asansör, hidrofor ve yangın ekipmanı gibi sistemler takvime bağlı bakım planıyla izlenir.</p></div>
                    </li>
                    <li>
                        <span class="ilke-simge"><x-simge ad="sohbet" /></span>
                        <div><h3>Kolay iletişim</h3><p>Talebiniz kayda alınır, ilgili kişiye atanır ve sonuçlanana kadar takip edilir.</p></div>
                    </li>
                    <li>
                        <span class="ilke-simge"><x-simge ad="onay" /></span>
                        <div><h3>Doğru bilgi</h3><p>İlanlarda yalnız teyit edilmiş bilgiler yayımlanır; mülk sahibinin gizliliği korunur.</p></div>
                    </li>
                </ul>
            </div>
        </div>
    </section>

    {{-- 8. İzinli müşteri görüşleri (yoksa bölüm hiç çizilmez) --}}
    @if ($gorusler->isNotEmpty())
        <section class="bolum">
            <div class="kap">
                <div class="bolum-baslik"><h2>Müşterilerimiz Ne Diyor?</h2></div>
                <div class="gorus-izgara">
                    @foreach ($gorusler as $gorus)
                        <figure class="gorus-kart">
                            <blockquote>{{ $gorus->metin }}</blockquote>
                            <footer><strong>{{ $gorus->ad }}</strong>@if ($gorus->alt_baslik)<span>{{ $gorus->alt_baslik }}</span>@endif</footer>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Danışmanlar (yalnız yayımlama izni verilmiş ekip) --}}
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

    {{-- 9. Kısa talep formu + iletişim --}}
    @php
        $kisiler = collect(ayar('iletisim_kisileri', []))->filter(fn ($k) => filled($k['telefon'] ?? null));
        $hatalar = $errors->kisa;
    @endphp
    @php $formArka = site_gorsel_kaynak('cta_gorsel', 'varsayilan-form.jpg'); @endphp
    <section class="form-bolumu" id="kisa-talep">
        <x-resim :yol="$formArka[0]" :kok="$formArka[1]" class="arka-resim" sizes="100vw" />
        <div class="kap">
            <div class="form-kart">
                <h2>Size ulaşalım</h2>
                <p>Kısa bilgi bırakın; ilgili ekibimiz sizi arasın.</p>

                @if (session('basari_form') === 'kisa')
                    <div class="bildirim bildirim-basari" role="status" style="margin-bottom:16px"><x-simge ad="onay" /><div><p>{{ session('basari') }}</p></div></div>
                @endif
                @if ($errors->has('genel'))
                    <div class="bildirim bildirim-hata" role="alert" style="margin-bottom:16px"><x-simge ad="uyari" /><div><p>{{ $errors->first('genel') }}</p></div></div>
                @endif

                <form action="{{ route('basvuru.kisa') }}#kisa-talep" method="post" class="form-izgara" novalidate data-form>
                    @include('parca.form-koruma')
                    <fieldset style="border:0;margin:0;padding:0">
                        <legend class="gorunmez">Talep türü</legend>
                        <div class="secim-sekme">
                            <label><input type="radio" name="talep_turu" value="emlak" @checked(old('talep_turu', 'emlak') === 'emlak')><span>Emlak talebi</span></label>
                            <label><input type="radio" name="talep_turu" value="yonetim" @checked(old('talep_turu') === 'yonetim')><span>Yönetim teklifi</span></label>
                        </div>
                    </fieldset>
                    @include('parca.alan', ['ad' => 'ad_soyad', 'etiket' => 'Ad soyad', 'zorunlu' => true, 'otomatik' => 'name', 'on' => 'k'])
                    @include('parca.alan', ['ad' => 'telefon', 'etiket' => 'Telefon', 'tur' => 'tel', 'zorunlu' => true, 'otomatik' => 'tel', 'on' => 'k'])
                    <div class="alan">
                        <label for="k-mesaj">Kısaca ihtiyacınız <span class="istege-bagli">(isteğe bağlı)</span></label>
                        <textarea class="metin-alani" id="k-mesaj" name="mesaj" rows="3" style="min-height:90px">{{ old('mesaj') }}</textarea>
                    </div>
                    @include('parca.kvkk-onay', ['on' => 'k'])
                    <button type="submit" class="dgm dgm-tam">Gönder</button>
                </form>
            </div>

            <div class="form-bolumu-metin">
                <h2>Emlak ve yönetim ihtiyaçlarınız için tek adres</h2>
                <p>Ayrıntılı başvuru için ilgili formları da kullanabilirsiniz.</p>
                <ul>
                    <li><x-simge ad="ev" /><div><strong><a href="{{ route('basvuru.portfoy') }}">Gayrimenkulünüzü bize bırakın</a></strong>Satış ya da kiralama için portföy başvurusu.</div></li>
                    <li><x-simge ad="bina" /><div><strong><a href="{{ route('yonetim.teklif') }}">Yönetim teklifi alın</a></strong>Blok ve bağımsız bölüm bilgileriyle ayrıntılı teklif talebi.</div></li>
                    @foreach ($kisiler as $kisi)
                        <li><x-simge ad="telefon" /><div><strong>{{ $kisi['ad'] }}</strong><a href="{{ tel_baglanti($kisi['telefon']) }}">{{ $kisi['telefon'] }}</a></div></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>
@endsection
