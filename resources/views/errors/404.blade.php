@extends('duzen.site')

@section('baslik', 'Sayfa bulunamadı')
@section('robots', 'noindex, nofollow')

@section('icerik')
    <section class="hata-sayfa">
        <div class="kap">
            <div class="kod">404</div>
            <h1>Aradığınız sayfa bulunamadı</h1>
            <p>İlan yayından kaldırılmış ya da adres değişmiş olabilir.</p>
            <div class="dgmler">
                <a href="{{ route('emlak.index') }}" class="dgm">Emlak portföyü</a>
                <a href="{{ route('yonetim.index') }}" class="dgm dgm-cizgi">Site yönetimi</a>
                <a href="{{ route('anasayfa') }}" class="dgm dgm-cizgi">Ana sayfa</a>
            </div>
        </div>
    </section>
@endsection
