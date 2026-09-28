@extends('duzen.site')

@php
    $kiralik = $ilan->islem->value === 'kiralik';
    $fotolar = $ilan->fotograflar;
    $nokta = $ilan->haritaNoktasi();
    $danisman = $ilan->danisman;
    $hatalar = $errors->ilan;
    $evet = fn ($d) => $d === null ? null : ($d ? 'Var' : 'Yok');

    // Özellik tablosu: yalnız dolu ve kategoriye uygun alanlar
    $ozellikler = array_filter([
        'İlan no' => $ilan->portfoy_no,
        'İşlem' => $ilan->islem->getLabel(),
        'Tür' => $ilan->tipEtiketi(),
        'Brüt alan' => $ilan->brut_m2 ? number_format($ilan->brut_m2, 0, ',', '.').' m²' : null,
        'Net alan' => $ilan->net_m2 ? number_format($ilan->net_m2, 0, ',', '.').' m²' : null,
        'Oda sayısı' => $ilan->kategori->odaAlani() ? $ilan->oda : null,
        'Banyo' => $ilan->kategori->yapiAlanlari() ? $ilan->banyo : null,
        'Bulunduğu kat' => $ilan->kategori->yapiAlanlari() ? $ilan->kat : null,
        'Kat sayısı' => $ilan->kategori->yapiAlanlari() ? $ilan->kat_sayisi : null,
        'Bina yaşı' => $ilan->kategori->yapiAlanlari() && $ilan->bina_yasi !== null ? ($ilan->bina_yasi === 0 ? 'Yeni' : $ilan->bina_yasi) : null,
        'Isıtma' => $ilan->kategori->yapiAlanlari() ? (\App\Models\Portfoy::ISITMA[$ilan->isitma] ?? null) : null,
        'Asansör' => $ilan->kategori->yapiAlanlari() ? $evet($ilan->asansor) : null,
        'Otopark' => $ilan->kategori->yapiAlanlari() ? (\App\Models\Portfoy::OTOPARK[$ilan->otopark] ?? null) : null,
        'Balkon' => $ilan->kategori->odaAlani() ? $evet($ilan->balkon) : null,
        'Eşyalı' => $ilan->kategori->odaAlani() ? ($ilan->esyali === null ? null : ($ilan->esyali ? 'Evet' : 'Hayır')) : null,
        'Site içinde' => $ilan->kategori->odaAlani() ? ($ilan->site_icinde === null ? null : ($ilan->site_icinde ? 'Evet' : 'Hayır')) : null,
        'Aidat' => $ilan->aidat_kurus ? \App\Support\Para::bicimle($ilan->aidat_kurus, $ilan->para_birimi).' / ay' : null,
        'Depozito' => $kiralik && $ilan->depozito_kurus ? \App\Support\Para::bicimle($ilan->depozito_kurus, $ilan->para_birimi) : null,
        'Ada / parsel' => $ilan->kategori->arsaAlanlari() && ($ilan->ada || $ilan->parsel) ? trim(($ilan->ada ?? '—').' / '.($ilan->parsel ?? '—')) : null,
        'İmar durumu' => $ilan->kategori->arsaAlanlari() ? $ilan->imar_durumu : null,
    ], fn ($v) => $v !== null && $v !== '');

    $mesajOnerisi = $ilan->portfoy_no.' numaralı "'.$ilan->baslik.'" ilanı hakkında bilgi almak istiyorum.';
@endphp

@section('baslik', $ilan->seo_baslik ?: $ilan->baslik)
@section('aciklama', $ilan->seo_aciklama ?: Str::limit(trim(strip_tags((string) $ilan->aciklama)), 155))
@section('og_tur', 'article')
@if ($fotolar->isNotEmpty())
    @section('og_gorsel', $fotolar->first()->url())
@endif
@if ($onizleme)
    @section('robots', 'noindex, nofollow')
    @section('serit')
        <div class="onizleme-seridi" role="status">
            <strong>Önizleme:</strong> Bu ilan şu an <strong>{{ $ilan->yayin_durumu->getLabel() }}</strong> durumunda; ziyaretçiler göremez.
        </div>
    @endsection
@endif

@unless ($onizleme)
    @push('yapisal_veri')
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'RealEstateListing',
            'name' => $ilan->baslik,
            'url' => route('ilan.detay', $ilan),
            'datePosted' => $ilan->yayin_tarihi?->toAtomString(),
            'image' => $fotolar->take(5)->map->url()->values()->all(),
            'offers' => [
                '@type' => 'Offer',
                'price' => $ilan->fiyat_kurus / 100,
                'priceCurrency' => $ilan->para_birimi,
                'availability' => $ilan->talepAlabilir() ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endpush
@endunless

@section('icerik')
    <section class="ilan-bas">
        <div class="kap">
            <nav aria-label="Konumunuz">
                <ol class="kirinti koyu">
                    <li><a href="{{ route('anasayfa') }}">Ana Sayfa</a></li>
                    <li><a href="{{ route('emlak.index', ['islem' => $ilan->islem->value]) }}">{{ $ilan->islem->getLabel() }}</a></li>
                    <li><a href="{{ route('emlak.index', ['kategori' => $ilan->kategori->value]) }}">{{ $ilan->kategori->getLabel() }}</a></li>
                    <li><span aria-current="page">{{ $ilan->portfoy_no }}</span></li>
                </ol>
            </nav>
            <div class="ilan-bas-ic">
                <div>
                    <h1>{{ $ilan->baslik }}</h1>
                    <p class="ilan-konum"><x-simge ad="konum" />
                        {{ $ilan->adres_goster && $ilan->acik_adres ? $ilan->acik_adres.' — ' : '' }}{{ $ilan->konumMetni() }}
                    </p>
                </div>
                <div class="ilan-bas-fiyat">
                    <div class="rozetler">
                        @if ($ilan->operasyon_durumu->value !== 'aktif')
                            <span class="rozet {{ $ilan->operasyon_durumu->value === 'rezerve' ? 'rozet-uyari' : 'rozet-gri' }}">{{ $ilan->operasyon_durumu->getLabel() }}</span>
                        @endif
                        @if ($ilan->one_cikan)
                            <span class="rozet rozet-yesil"><x-simge ad="yildiz" /> Öne çıkan</span>
                        @endif
                    </div>
                    <span class="ilan-islem">{{ $ilan->islem->getLabel() }}</span>
                    <span class="fiyat">{{ $ilan->fiyatMetni() }}@if ($kiralik)<small> / ay</small>@endif</span>
                </div>
            </div>
        </div>
    </section>

    @if ($fotolar->isNotEmpty())
        <section class="galeri" aria-label="Fotoğraflar">
            <div class="kap">
                <div class="galeri-cerceve" data-galeri>
                    <div class="galeri-ana">
                        @foreach ($fotolar as $i => $foto)
                            <img src="{{ $foto->url() }}" alt="{{ $foto->alt_metin ?: $ilan->baslik.' — fotoğraf '.($i + 1) }}"
                                 @if ($i > 0) loading="lazy" @else fetchpriority="high" @endif decoding="async" width="1600" height="900"
                                 class="{{ $i === 0 ? 'etkin' : '' }}" data-galeri-gorsel>
                        @endforeach
                        @if ($fotolar->count() > 1)
                            <button type="button" class="kaydirici-ok onceki" data-onceki aria-label="Önceki fotoğraf"><x-simge ad="sol" /></button>
                            <button type="button" class="kaydirici-ok sonraki" data-sonraki aria-label="Sonraki fotoğraf"><x-simge ad="sag" /></button>
                            <span class="rozet rozet-acik galeri-sayac" aria-live="polite"><x-simge ad="kamera" /> <span data-galeri-sayac>1</span> / {{ $fotolar->count() }}</span>
                        @endif
                    </div>
                    @if ($fotolar->count() > 1)
                        <div class="galeri-kucukler">
                            @foreach ($fotolar as $i => $foto)
                                <button type="button" data-galeri-kucuk="{{ $i }}" aria-label="Fotoğraf {{ $i + 1 }}" @if ($i === 0) aria-current="true" @endif>
                                    <img src="{{ $foto->kucukUrl() }}" alt="" loading="lazy" width="110" height="82">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    <section class="bolum" style="padding-top:40px">
        <div class="kap iki-sutun">
            <div>
                <div class="kutu">
                    <div class="ilan-ust-satir">
                        <span class="no">İlan no: <b>{{ $ilan->portfoy_no }}</b>
                            @if ($ilan->yayin_tarihi) · Eklendi: {{ tarih_tr($ilan->yayin_tarihi) }} @endif
                        </span>
                        <div class="paylas">
                            <a href="https://wa.me/?text={{ rawurlencode($ilan->baslik.' '.route('ilan.detay', $ilan)) }}" target="_blank" rel="noopener" aria-label="WhatsApp ile paylaş"><x-simge ad="whatsapp" /></a>
                            <button type="button" data-kopyala="{{ route('ilan.detay', $ilan) }}" aria-label="Bağlantıyı kopyala"><x-simge ad="baglanti" /></button>
                        </div>
                    </div>

                    <ul class="ilan-ozet buyuk">
                        @if ($ilan->kategori->odaAlani() && $ilan->oda)
                            <li>Oda <b><x-simge ad="yatak" /> {{ $ilan->oda }}</b></li>
                        @endif
                        @if ($ilan->kategori->yapiAlanlari() && $ilan->banyo)
                            <li>Banyo <b><x-simge ad="banyo" /> {{ $ilan->banyo }}</b></li>
                        @endif
                        @if ($ilan->alanMetni())
                            <li>{{ $ilan->net_m2 ? 'Net alan' : 'Alan' }} <b><x-simge ad="alan" /> {{ $ilan->alanMetni() }}</b></li>
                        @endif
                        @if ($ilan->kategori->yapiAlanlari() && $ilan->kat)
                            <li>Kat <b><x-simge ad="kat" /> {{ $ilan->kat }}</b></li>
                        @endif
                        @if ($ilan->kategori->yapiAlanlari() && $ilan->bina_yasi !== null)
                            <li>Bina yaşı <b><x-simge ad="takvim" /> {{ $ilan->bina_yasi === 0 ? 'Yeni' : $ilan->bina_yasi }}</b></li>
                        @endif
                        @if ($ilan->kategori->arsaAlanlari() && $ilan->imar_durumu)
                            <li>İmar <b><x-simge ad="harita" /> {{ $ilan->imar_durumu }}</b></li>
                        @endif
                    </ul>

                    @if (filled(strip_tags((string) $ilan->aciklama)))
                        <h2 class="kutu-baslik-yesil">Açıklama</h2>
                        <div class="icerik-metni">{!! \App\Support\Html::temizle($ilan->aciklama) !!}</div>
                    @endif
                </div>

                <div class="kutu">
                    <h2>Özellikler</h2>
                    <dl class="ozellik-tablosu">
                        @foreach ($ozellikler as $etiket => $deger)
                            <div><dt>{{ $etiket }}</dt><dd>{{ $deger }}</dd></div>
                        @endforeach
                    </dl>

                    @if (! empty($ilan->ozellikler))
                        <h3 class="kutu-baslik-yesil" style="margin-top:26px">Olanaklar</h3>
                        <ul class="isaret-listesi">
                            @foreach ($ilan->ozellikler as $ozellik)
                                <li><x-simge ad="onay" /> {{ $ozellik }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                @if ($ilan->planlar->isNotEmpty())
                    <div class="kutu">
                        <h2>Kat planı</h2>
                        @foreach ($ilan->planlar as $plan)
                            <img src="{{ $plan->url() }}" alt="{{ $plan->alt_metin ?: 'Kat planı' }}" loading="lazy" style="border-radius:var(--radius)">
                        @endforeach
                    </div>
                @endif

                @if ($ilan->video_url && Str::startsWith($ilan->video_url, 'https://'))
                    <div class="kutu">
                        <h2>Video</h2>
                        <a href="{{ $ilan->video_url }}" target="_blank" rel="noopener" class="dgm dgm-cizgi"><x-simge ad="youtube" /> Videoyu izle</a>
                    </div>
                @endif

                <div class="kutu">
                    <h2>Konum</h2>
                    <p class="ilan-konum" style="margin-bottom:16px"><x-simge ad="konum" /> {{ $ilan->konumMetni() }}</p>
                    @if ($nokta)
                        @php
                            [$enlem, $boylam, $tam] = $nokta;
                            $pay = $tam ? 0.006 : 0.012;
                            $bbox = implode(',', [$boylam - $pay * 1.6, $enlem - $pay, $boylam + $pay * 1.6, $enlem + $pay]);
                            $harita = 'https://www.openstreetmap.org/export/embed.html?bbox='.$bbox.'&layer=mapnik'.($tam ? '&marker='.$enlem.','.$boylam : '');
                        @endphp
                        <iframe class="harita" src="{{ $harita }}" title="Harita: {{ $ilan->konumMetni() }}" loading="lazy" referrerpolicy="no-referrer"></iframe>
                        <p class="harita-not">
                            {{ $tam ? 'Harita noktası mülk sahibinin onayıyla gösterilmektedir.' : 'Harita yaklaşık bölgeyi gösterir; açık adres randevu sırasında paylaşılır.' }}
                            Harita verisi © OpenStreetMap katkıcıları.
                        </p>
                    @else
                        <p style="margin:0">Açık adres, mülk sahibinin gizliliği için randevu sırasında danışmanımız tarafından paylaşılır.</p>
                    @endif
                </div>
            </div>

            {{-- Yan sütun: danışman + talep formu --}}
            <aside class="yapiskan">
                <div class="kutu danisman-kart">
                    @if ($danisman)
                        <span class="avatar">
                            @if ($danisman->fotoUrl())
                                <img src="{{ $danisman->fotoUrl() }}" alt="" width="88" height="88">
                            @else
                                {{ $danisman->basHarfler() }}
                            @endif
                        </span>
                        <h3>{{ $danisman->name }}</h3>
                        <span class="gorev">{{ $danisman->gorev ?: 'İlgili danışman' }}</span>
                        <div class="danisman-iletisim">
                            @if ($danisman->telefon)
                                <a href="{{ tel_baglanti($danisman->telefon) }}" class="dgm dgm-lacivert"><x-simge ad="telefon" /> {{ $danisman->telefon }}</a>
                            @endif
                            @if ($danisman->whatsapp)
                                <a href="{{ wa_baglanti($danisman->whatsapp, $mesajOnerisi) }}" class="dgm" target="_blank" rel="noopener"><x-simge ad="whatsapp" /> WhatsApp</a>
                            @endif
                        </div>
                    @elseif (ayar('telefon'))
                        <h3>{{ ayar('firma_adi') }}</h3>
                        <div class="danisman-iletisim" style="margin-top:12px">
                            <a href="{{ tel_baglanti(ayar('telefon')) }}" class="dgm dgm-lacivert"><x-simge ad="telefon" /> {{ ayar('telefon') }}</a>
                        </div>
                    @endif

                    @if ($ilan->talepAlabilir())
                        <div id="talep" style="text-align:left">
                            <h2 class="kutu-baslik" style="font-size:17px;margin-bottom:12px">Bilgi al / yer göstermesi iste</h2>

                            @if (session('basari_form') === 'ilan')
                                <div class="bildirim bildirim-basari" role="status" style="margin-bottom:14px"><x-simge ad="onay" /><div><p>{{ session('basari') }}</p></div></div>
                            @endif
                            @if ($errors->has('genel'))
                                <div class="bildirim bildirim-hata" role="alert" style="margin-bottom:14px"><x-simge ad="uyari" /><div><p>{{ $errors->first('genel') }}</p></div></div>
                            @endif

                            @if ($onizleme)
                                <p class="bildirim bildirim-uyari">Önizlemede form gönderilemez.</p>
                            @else
                                <form action="{{ route('ilan.talep', $ilan) }}#talep" method="post" class="form-izgara" novalidate data-form>
                                    @include('parca.form-koruma')
                                    @include('parca.alan', ['ad' => 'ad_soyad', 'etiket' => 'Ad soyad', 'zorunlu' => true, 'otomatik' => 'name', 'on' => 'i'])
                                    @include('parca.alan', ['ad' => 'telefon', 'etiket' => 'Telefon', 'tur' => 'tel', 'zorunlu' => true, 'otomatik' => 'tel', 'on' => 'i'])
                                    @include('parca.alan', ['ad' => 'eposta', 'etiket' => 'E-posta', 'tur' => 'email', 'otomatik' => 'email', 'on' => 'i'])
                                    @include('parca.alan', ['ad' => 'tercih_edilen_zaman', 'etiket' => 'Tercih ettiğiniz tarih ve saat', 'tur' => 'datetime-local', 'on' => 'i',
                                        'ipucu' => 'Randevu, danışmanın onayıyla kesinleşir.', 'ek' => ['min' => now()->addHour()->format('Y-m-d\TH:00')]])
                                    <div class="alan">
                                        <label for="i-mesaj">Mesajınız <span class="istege-bagli">(isteğe bağlı)</span></label>
                                        <textarea class="metin-alani" id="i-mesaj" name="mesaj" rows="4">{{ old('mesaj', $mesajOnerisi) }}</textarea>
                                        @if ($hatalar->has('mesaj'))<span class="alan-hata">{{ $hatalar->first('mesaj') }}</span>@endif
                                    </div>
                                    @include('parca.kvkk-onay', ['on' => 'i'])
                                    <button type="submit" class="dgm dgm-tam">Talebimi gönder</button>
                                </form>
                            @endif
                        </div>
                    @else
                        <div class="durum-kutu" style="margin-top:16px">
                            <strong>Bu gayrimenkul {{ mb_strtolower($ilan->operasyon_durumu->getLabel()) }}.</strong>
                            <p style="margin:0 0 12px">Bu ilan için artık talep alınmıyor. Benzer ilanlara göz atabilirsiniz.</p>
                            <a href="{{ route('emlak.index', ['kategori' => $ilan->kategori->value, 'islem' => $ilan->islem->value]) }}" class="dgm dgm-kucuk">Benzer ilanlar</a>
                        </div>
                    @endif
                </div>
            </aside>
        </div>
    </section>

    @if ($benzerler->isNotEmpty())
        <section class="bolum bolum-beyaz">
            <div class="kap">
                <div class="bolum-baslik"><h2>Benzer İlanlar</h2></div>
                <div class="ilan-izgara uc">
                    @foreach ($benzerler as $benzer)
                        @include('parca.ilan-kart', ['ilan' => $benzer])
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
