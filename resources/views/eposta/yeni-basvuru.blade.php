Web sitesinden yeni bir başvuru geldi.

Tür: {{ $talep->tur->getLabel() }}
Ad soyad: {{ $talep->ad_soyad }}
@if ($talep->telefon)
Telefon: {{ $talep->telefon }}
@endif
@if ($talep->eposta)
E-posta: {{ $talep->eposta }}
@endif
@if ($talep->konu)
Konu: {{ $talep->konu }}
@endif
@if ($talep->portfoy)
İlan: {{ $talep->portfoy->portfoy_no }} — {{ $talep->portfoy->baslik }}
@endif
@if ($talep->tercih_edilen_zaman)
Tercih edilen zaman: {{ tarih_tr($talep->tercih_edilen_zaman, true) }}
@endif
@foreach (($talep->detay ?? []) as $anahtar => $deger)
{{ \App\Models\Talep::DETAY_ETIKETLERI[$anahtar] ?? $anahtar }}: {{ is_array($deger) ? implode(', ', $deger) : $deger }}
@endforeach
@if ($talep->mesaj)

Mesaj:
{{ $talep->mesaj }}
@endif

Panelde görüntüle: {{ url('/admin') }}
