@extends('duzen.site')

@php
    $s = $arama->suzgec;
    $baslik = match ($s['islem'] ?? null) {
        'satilik' => 'Satılık Gayrimenkuller',
        'kiralik' => 'Kiralık Gayrimenkuller',
        default => 'Emlak Portföyü',
    };
    if (isset($s['kategori'])) {
        $baslik = trim((isset($s['islem']) ? \App\Enums\Islem::from($s['islem'])->getLabel().' ' : '').\App\Enums\Kategori::from($s['kategori'])->getLabel().' İlanları');
    }
    $etiketler = $arama->etkinEtiketler();
    $kategori = $s['kategori'] ?? '';
@endphp

@section('baslik', $baslik)
@section('aciklama', 'Balkan Emlak ve Yönetim satılık ve kiralık konut, arsa ve ticari gayrimenkul portföyü.')
{{-- Süzgeçli sayfaların hepsi aynı listeye kanonik — ince içerik çoğalmasın --}}
@section('kanonik', count($etiketler) ? route('emlak.index') : url()->current())

@section('icerik')
    @include('parca.sayfa-basi', [
        'baslik' => $baslik,
        'aciklama' => 'Yayındaki tüm portföyümüz. Süzgeçleri kullanın; sonuç bağlantısını kopyalayıp paylaşabilirsiniz.',
        'kirinti' => ['Emlak Portföyü' => null],
    ])

    <section class="bolum" style="padding-top:40px">
        <div class="kap iki-sutun sol-yan">
            {{-- Süzgeçler (mobilde katlanır) --}}
            <aside>
                <details class="suzgec" data-suzgec open>
                    <summary><span><x-simge ad="suzgec" /> Süzgeçler @if (count($etiketler)) ({{ count($etiketler) }}) @endif</span><x-simge ad="asagi" /></summary>
                    <form class="suzgec-govde" action="{{ route('emlak.index') }}" method="get" data-suzgec-form data-konum-agaci='@json($konumAgaci)' data-tipler='@json($tipler)'>
                        <div class="alan">
                            <label for="s-q">Anahtar kelime / ilan no</label>
                            <input class="girdi" id="s-q" name="q" value="{{ $s['q'] ?? '' }}" placeholder="Örn. BLK-00004">
                        </div>
                        <div class="alan">
                            <label for="s-islem">İşlem</label>
                            <select class="secim" id="s-islem" name="islem">
                                <option value="">Satılık ve kiralık</option>
                                @foreach (\App\Enums\Islem::cases() as $islem)
                                    <option value="{{ $islem->value }}" @selected(($s['islem'] ?? '') === $islem->value)>{{ $islem->getLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="alan">
                            <label for="s-kategori">Gayrimenkul türü</label>
                            <select class="secim" id="s-kategori" name="kategori" data-kategori>
                                <option value="">Tümü</option>
                                @foreach (\App\Enums\Kategori::cases() as $k)
                                    <option value="{{ $k->value }}" @selected($kategori === $k->value)>{{ $k->getLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="alan" data-tip-alani @if (! $kategori) hidden @endif>
                            <label for="s-tip">Alt tür</label>
                            <select class="secim" id="s-tip" name="tip" data-tip data-secili="{{ $s['tip'] ?? '' }}">
                                <option value="">Tümü</option>
                                @if ($kategori)
                                    @foreach ($tipler[$kategori] as $tip => $etiket)
                                        <option value="{{ $tip }}" @selected(($s['tip'] ?? '') === $tip)>{{ $etiket }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="alan">
                            <label for="s-il">İl</label>
                            <select class="secim" id="s-il" name="il" data-il data-secili="{{ $s['il'] ?? '' }}">
                                <option value="">Tümü</option>
                                @foreach ($konumAgaci as $id => $il)
                                    <option value="{{ $id }}" @selected(($s['il'] ?? null) == $id)>{{ $il['ad'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="alan">
                            <label for="s-ilce">İlçe</label>
                            <select class="secim" id="s-ilce" name="ilce" data-ilce data-secili="{{ $s['ilce'] ?? '' }}">
                                <option value="">Tümü</option>
                                @foreach ($konumAgaci as $ilId => $il)
                                    @if (! isset($s['il']) || $s['il'] == $ilId)
                                        @foreach ($il['ilceler'] as $id => $ilce)
                                            <option value="{{ $id }}" @selected(($s['ilce'] ?? null) == $id)>{{ $ilce['ad'] }}</option>
                                        @endforeach
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="alan">
                            <label for="s-mahalle">Mahalle</label>
                            <select class="secim" id="s-mahalle" name="mahalle" data-mahalle data-secili="{{ $s['mahalle'] ?? '' }}">
                                <option value="">Tümü</option>
                                @foreach ($konumAgaci as $il)
                                    @foreach ($il['ilceler'] as $ilceId => $ilce)
                                        @if (($s['ilce'] ?? null) == $ilceId)
                                            @foreach ($ilce['mahalleler'] as $id => $mahalle)
                                                <option value="{{ $id }}" @selected(($s['mahalle'] ?? null) == $id)>{{ $mahalle }}</option>
                                            @endforeach
                                        @endif
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <div class="alan" data-oda-alani @if ($kategori && $kategori !== 'konut') hidden @endif>
                            <label for="s-oda">Oda sayısı</label>
                            <select class="secim" id="s-oda" name="oda">
                                <option value="">Farketmez</option>
                                @foreach (\App\Services\PortfoyArama::ODALAR as $oda)
                                    <option value="{{ $oda }}" @selected(($s['oda'] ?? '') === $oda)>{{ $oda }}</option>
                                @endforeach
                            </select>
                        </div>
                        <fieldset class="alan" style="border:0;margin:0;padding:0">
                            <legend class="alan-baslik">Fiyat (₺)</legend>
                            <div class="suzgec-aralik">
                                <input class="girdi" name="fiyat_min" inputmode="numeric" placeholder="En az" aria-label="En düşük fiyat" value="{{ $arama->formDegeri('fiyat_min') }}">
                                <input class="girdi" name="fiyat_max" inputmode="numeric" placeholder="En çok" aria-label="En yüksek fiyat" value="{{ $arama->formDegeri('fiyat_max') }}">
                            </div>
                        </fieldset>
                        <fieldset class="alan" style="border:0;margin:0;padding:0">
                            <legend class="alan-baslik">Metrekare</legend>
                            <div class="suzgec-aralik">
                                <input class="girdi" name="m2_min" inputmode="numeric" placeholder="En az" aria-label="En az metrekare" value="{{ $s['m2_min'] ?? '' }}">
                                <input class="girdi" name="m2_max" inputmode="numeric" placeholder="En çok" aria-label="En çok metrekare" value="{{ $s['m2_max'] ?? '' }}">
                            </div>
                        </fieldset>
                        @if (($s['sirala'] ?? 'yeni') !== 'yeni')
                            <input type="hidden" name="sirala" value="{{ $s['sirala'] }}">
                        @endif
                        <div class="suzgec-dgmler">
                            <button type="submit" class="dgm dgm-tam"><x-simge ad="arama" /> Sonuçları göster</button>
                            @if (count($etiketler))
                                <a href="{{ route('emlak.index') }}" class="suzgec-temizle">Süzgeçleri temizle</a>
                            @endif
                        </div>
                    </form>
                </details>
            </aside>

            {{-- Sonuçlar --}}
            <div>
                <div class="liste-cubuk">
                    <p role="status">
                        @if ($ilanlar->total())
                            <strong>{{ $ilanlar->total() }}</strong> ilan bulundu
                            @if ($ilanlar->lastPage() > 1) · {{ $ilanlar->firstItem() }}–{{ $ilanlar->lastItem() }} gösteriliyor @endif
                        @else
                            Sonuç yok
                        @endif
                    </p>
                    <form action="{{ route('emlak.index') }}" method="get" data-otomatik-gonder>
                        @foreach (request()->except(['sirala', 'page']) as $anahtar => $deger)
                            @if (is_string($deger) && $deger !== '')
                                <input type="hidden" name="{{ $anahtar }}" value="{{ $deger }}">
                            @endif
                        @endforeach
                        <label for="sirala" class="gorunmez">Sıralama</label>
                        <select class="secim" id="sirala" name="sirala">
                            @foreach (\App\Services\PortfoyArama::SIRALAMALAR as $deger => $etiket)
                                <option value="{{ $deger }}" @selected($s['sirala'] === $deger)>{{ $etiket }}</option>
                            @endforeach
                        </select>
                        <noscript><button type="submit" class="dgm dgm-kucuk">Sırala</button></noscript>
                    </form>
                </div>

                @if (count($etiketler))
                    <ul class="etkin-suzgecler" aria-label="Etkin süzgeçler">
                        @foreach ($etiketler as [$etiket, $adres])
                            <li><a href="{{ $adres }}" aria-label="{{ $etiket }} süzgecini kaldır">{{ $etiket }} <x-simge ad="kapat" /></a></li>
                        @endforeach
                    </ul>
                @endif

                @if ($ilanlar->isEmpty())
                    <div class="bos-durum">
                        <x-simge ad="arama" />
                        <h2>Bu ölçütlere uyan ilan bulunamadı</h2>
                        <p>Süzgeçlerden birkaçını kaldırmayı deneyin. Aradığınız gayrimenkulü bize bildirirseniz portföyümüze uygun bir ilan eklendiğinde size haber verelim.</p>
                        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
                            <a href="{{ route('emlak.index') }}" class="dgm dgm-cizgi">Tüm ilanlar</a>
                            <a href="{{ route('anasayfa') }}#kisa-talep" class="dgm">Aradığımı bildir</a>
                        </div>
                    </div>
                @else
                    <div class="ilan-izgara">
                        @foreach ($ilanlar as $ilan)
                            @include('parca.ilan-kart', ['ilan' => $ilan])
                        @endforeach
                    </div>
                    {{ $ilanlar->links() }}
                @endif
            </div>
        </div>
    </section>
@endsection
