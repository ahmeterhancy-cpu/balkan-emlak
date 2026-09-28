@extends('duzen.site')

@section('baslik', $sayfa->seo_baslik ?: $sayfa->baslik)
@section('aciklama', $sayfa->seo_aciklama ?: ($sayfa->ozet ?: $sayfa->baslik))

@section('icerik')
    @include('parca.sayfa-basi', ['baslik' => $sayfa->baslik, 'kirinti' => [$sayfa->baslik => null]])

    <section class="bolum">
        <div class="kap yasal">
            @if ($sayfa->inceleme_bekliyor)
                <div class="bildirim bildirim-uyari" style="margin-bottom:24px" role="note">
                    <x-simge ad="uyari" />
                    <div><p><strong>Taslak metin:</strong> Bu metin henüz hukuki incelemeden geçmemiştir ve kesinleşmiş değildir.</p></div>
                </div>
            @endif
            <div class="kutu icerik-metni">{!! \App\Support\Html::temizle($sayfa->icerik) !!}</div>
            <p style="margin-top:16px;font-size:13px;color:var(--metin-3)">Son güncelleme: {{ tarih_tr($sayfa->updated_at) }}</p>
        </div>
    </section>
@endsection
