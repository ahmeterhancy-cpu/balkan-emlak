{{--
    Duyarlı görsel: WebP + JPEG, 400/800/1600 genişlik (App\Support\Gorsel).
    <x-resim yol="portfoyler/a.jpg" alt="..." sizes="(min-width: 1100px) 380px, 100vw" />
    kok: 'medya' (yüklenen) | 'public' (public/img). oncelikli: LCP görseli → eager + fetchpriority=high.
    tembel: false ise loading="lazy" yazılmaz (galeride gizli görseller için JS kendisi yönetir).
--}}
@props(['yol', 'kok' => 'medya', 'alt' => '', 'sizes' => '100vw', 'oncelikli' => false, 'tembel' => true])
@php
    $set = \App\Support\Gorsel::set($yol, $kok);
    $jpgVarsayilan = $set['jpg'][800] ?? $set['asil'];
@endphp
<picture>
    @if ($set['webp'])
        <source type="image/webp" srcset="{{ \App\Support\Gorsel::srcset($set['webp']) }}" sizes="{{ $sizes }}">
    @endif
    <img src="{{ $jpgVarsayilan }}"
         @if (count($set['jpg']) > 1) srcset="{{ \App\Support\Gorsel::srcset($set['jpg']) }}" sizes="{{ $sizes }}" @endif
         alt="{{ $alt }}"
         @if ($set['genislik']) width="{{ $set['genislik'] }}" height="{{ $set['yukseklik'] }}" @endif
         @if ($oncelikli) fetchpriority="high" loading="eager" @elseif ($tembel) loading="lazy" @endif
         decoding="async"
         {{ $attributes }}>
</picture>
