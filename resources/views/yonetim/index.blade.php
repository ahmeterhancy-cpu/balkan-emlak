@extends('duzen.site')

@section('baslik', 'Site ve Apartman Yönetimi')
@section('aciklama', 'Genel, teknik, temizlik ve bahçe yönetiminden şeffaf aidat takibine kadar profesyonel site ve apartman yönetimi.')
@section('og_gorsel', site_gorsel('yonetim_gorsel', 'varsayilan-yonetim.jpg'))

@section('icerik')
    @include('parca.sayfa-basi', [
        'baslik' => 'Site ve Apartman Yönetimi',
        'aciklama' => ayar('yonetim_slogani').' Ortak alanların bakımından aidat takibine kadar yönetimin her adımı kayıt altında.',
        'kirinti' => ['Site Yönetimi' => null],
        'gorsel' => site_gorsel_kaynak('yonetim_gorsel', 'varsayilan-yonetim.jpg'),
    ])

    <section class="bolum bolum-gri">
        <div class="kap">
            <div class="bolum-baslik">
                <span class="ust-yazi">{{ ayar('alt_marka') }}</span>
                <h2>Hizmetlerimiz</h2>
                <p>İhtiyacınıza göre tek tek ya da birlikte sunulan yönetim hizmetleri.</p>
            </div>
            <div class="hizmet-izgara">
                @foreach ($hizmetler as $hizmet)
                    <article class="hizmet-kart">
                        <span class="hizmet-simge"><x-simge :ad="$hizmet->ikon ?: 'yonetim'" /></span>
                        <h3><a href="{{ route('yonetim.hizmet', $hizmet) }}">{{ $hizmet->baslik }}</a></h3>
                        <p>{{ $hizmet->ozet }}</p>
                        <span class="devam">Kapsamı gör <x-simge ad="ok" /></span>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bolum bolum-beyaz">
        <div class="kap">
            <div class="bolum-baslik">
                <h2>Nasıl çalışırız?</h2>
                <p>Yönetime geçiş, mevcut durumun kayda alınmasıyla başlar.</p>
            </div>
            <ol class="adimlar">
                <li><h3>Keşif ve ihtiyaç</h3><p>Site ya da apartmanı yerinde görür, yönetim planını ve mevcut sözleşmeleri inceleriz.</p></li>
                <li><h3>Yazılı teklif</h3><p>Hizmet kapsamı ve ücretlendirme yazılı teklifle netleşir.</p></li>
                <li><h3>Devir ve kurulum</h3><p>Bloklar, bağımsız bölümler, malik ve kiracılar ile açılış bakiyeleri kayda alınır.</p></li>
                <li><h3>Düzenli raporlama</h3><p>Aidat, tahsilat, gider ve bakım işleri dönemsel raporla paylaşılır.</p></li>
            </ol>
        </div>
    </section>

    <section class="bolum">
        <div class="kap ilkeler">
            <div class="ilkeler-gorsel">
                @php $ilke = site_gorsel_kaynak('yonetim_gorsel', 'varsayilan-yonetim.jpg'); @endphp
                <x-resim :yol="$ilke[0]" :kok="$ilke[1]" sizes="(min-width: 992px) 600px, 100vw" />
                <div class="rozet-kutu">
                    <strong>{{ ayar('yonetim_slogani') }}</strong>
                </div>
            </div>
            <div>
                <div class="bolum-baslik sola" style="margin-bottom:0">
                    <span class="ust-yazi">Neden profesyonel yönetim?</span>
                    <h2>Kayıtlı, izlenebilir, hesap verebilir</h2>
                </div>
                <ul class="ilke-liste">
                    <li><span class="ilke-simge"><x-simge ad="aidat" /></span><div><h3>Şeffaf aidat</h3><p>Her bağımsız bölümün borcu, ödemesi ve bakiyesi ayrı izlenir; dağıtım yöntemi raporda yazılıdır.</p></div></li>
                    <li><span class="ilke-simge"><x-simge ad="bakim" /></span><div><h3>Planlı bakım</h3><p>Asansör, hidrofor, yangın ekipmanı ve bahçe için bakım takvimi tutulur.</p></div></li>
                    <li><span class="ilke-simge"><x-simge ad="belge" /></span><div><h3>Belgeli giderler</h3><p>Giderler belge ve tedarikçi bilgisiyle kaydedilir; ödeme durumu takip edilir.</p></div></li>
                </ul>
                <div style="margin-top:28px;display:flex;gap:12px;flex-wrap:wrap">
                    <a href="{{ route('yonetim.teklif') }}" class="dgm">Yönetim Teklifi Al</a>
                    @if (ayar('telefon'))
                        <a href="{{ tel_baglanti(ayar('telefon')) }}" class="dgm dgm-cizgi"><x-simge ad="telefon" /> {{ ayar('telefon') }}</a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    @if ($projeler->isNotEmpty())
        <section class="bolum bolum-beyaz">
            <div class="kap">
                <div class="bolum-baslik"><h2>Yönettiğimiz yerleşkelerden</h2></div>
                <div class="gorus-izgara">
                    @foreach ($projeler as $proje)
                        <article class="proje-kart">
                            @if ($proje->gorselUrl())
                                <x-resim :yol="$proje->gorsel" :alt="$proje->ad" sizes="(min-width: 1100px) 400px, 100vw" />
                            @endif
                            <div class="govde"><h3>{{ $proje->ad }}</h3>@if ($proje->alt_baslik)<p style="margin:0">{{ $proje->alt_baslik }}</p>@endif</div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($sorular->isNotEmpty())
        <section class="bolum bolum-gri">
            <div class="kap" style="max-width:880px">
                <div class="bolum-baslik"><h2>Sık sorulan sorular</h2></div>
                <div class="sss-liste">
                    @foreach ($sorular as $soru)
                        <details>
                            <summary>{{ $soru->soru }} <x-simge ad="asagi" /></summary>
                            <div>{{ $soru->cevap }}</div>
                        </details>
                    @endforeach
                </div>
                @if (ayar('acil_iletisim'))
                    <div class="bildirim bildirim-uyari" style="margin-top:24px"><x-simge ad="uyari" /><div><p><strong>Acil durumlar için:</strong> {{ ayar('acil_iletisim') }}</p></div></div>
                @endif
            </div>
        </section>
    @endif

    @php $cagri = site_gorsel_kaynak('cta_gorsel', 'varsayilan-cagri.jpg'); @endphp
    <section class="cagri">
        <x-resim :yol="$cagri[0]" :kok="$cagri[1]" class="arka-resim" sizes="100vw" />
        <div class="kap">
            <h2>Siteniz ya da apartmanınız için teklif alın</h2>
            <p>Blok ve bağımsız bölüm sayısını, ihtiyaç duyduğunuz hizmetleri iletin; keşif sonrasında yazılı teklifimizi hazırlayalım.</p>
            <div class="dgmler"><a href="{{ route('yonetim.teklif') }}" class="dgm">Yönetim Teklifi Al</a></div>
        </div>
    </section>
@endsection
