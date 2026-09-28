@extends('duzen.site')

@section('baslik', 'Referanslar')
@section('aciklama', 'Balkan Emlak ve Yönetim referansları ve müşteri görüşleri.')

@section('icerik')
    @include('parca.sayfa-basi', ['baslik' => 'Referanslar', 'kirinti' => ['Referanslar' => null]])

    <section class="bolum">
        <div class="kap">
            @if ($projeler->isEmpty() && $gorusler->isEmpty())
                {{-- Yalnız yayımlama izni alınmış referanslar gösterilir; yoksa uydurma içerik yerine dürüst boş durum --}}
                <div class="bos-durum">
                    <x-simge ad="bina" />
                    <h2>Referanslarımız yakında burada</h2>
                    <p>Yalnızca yayımlanması için izin aldığımız proje ve görüşleri paylaşıyoruz.</p>
                    <a href="{{ route('yonetim.teklif') }}" class="dgm">Yönetim Teklifi Al</a>
                </div>
            @endif

            @if ($projeler->isNotEmpty())
                <div class="bolum-baslik"><h2>Projeler ve yönetilen yerleşkeler</h2></div>
                <div class="gorus-izgara" style="margin-bottom:64px">
                    @foreach ($projeler as $proje)
                        <article class="proje-kart">
                            @if ($proje->gorselUrl())
                                <x-resim :yol="$proje->gorsel" :alt="$proje->ad" sizes="(min-width: 1100px) 400px, 100vw" />
                            @endif
                            <div class="govde">
                                <h3>{{ $proje->ad }}</h3>
                                @if ($proje->alt_baslik)<p style="margin:0 0 6px;color:var(--metin-3)">{{ $proje->alt_baslik }}</p>@endif
                                @if ($proje->metin)<p style="margin:0">{{ $proje->metin }}</p>@endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            @if ($gorusler->isNotEmpty())
                <div class="bolum-baslik"><h2>Müşteri görüşleri</h2></div>
                <div class="gorus-izgara">
                    @foreach ($gorusler as $gorus)
                        <figure class="gorus-kart">
                            <blockquote>{{ $gorus->metin }}</blockquote>
                            <footer><strong>{{ $gorus->ad }}</strong>@if ($gorus->alt_baslik)<span>{{ $gorus->alt_baslik }}</span>@endif</footer>
                        </figure>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
