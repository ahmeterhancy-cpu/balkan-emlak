<?php

namespace App\Filament\Resources\Portfoyler\Pages;

use App\Enums\YayinDurumu;
use App\Models\Kisi;
use App\Models\Portfoy;
use App\Support\Kapsam;
use App\Support\Yetki;
use Illuminate\Validation\ValidationException;

/**
 * Formdan gelen veriyi yetkiye göre süzer. Arayüzde gizli/kapalı alanlar
 * zaten gönderilmez; bu katman elle hazırlanmış Livewire isteğine karşı
 * sunucu tarafı güvencedir (şartname 9: "yalnız menü gizleyerek değil").
 */
trait PortfoyVerisi
{
    protected function yetkiyeGoreSuz(array $data, ?Portfoy $kayit): array
    {
        if (! Yetki::var('portfoy.goruntule_tumu')) {
            $data['danisman_id'] = $kayit?->danisman_id ?? auth()->id();
        }

        if (! Yetki::var('portfoy.yayimla')) {
            unset($data['one_cikan'], $data['yayin_durumu']);
            if (! $kayit) {
                $data['yayin_durumu'] = YayinDurumu::Taslak->value;
            }
        }

        // Yayına alma yalnız "Yayımla" eylemiyle (zorunlu alan denetimi orada)
        $yeni = $data['yayin_durumu'] ?? null;
        $yeni = $yeni instanceof YayinDurumu ? $yeni->value : $yeni;
        if ($yeni === YayinDurumu::Yayinda->value && $kayit?->yayin_durumu !== YayinDurumu::Yayinda) {
            $data['yayin_durumu'] = $kayit?->yayin_durumu?->value ?? YayinDurumu::Taslak->value;
        }

        $icBilgi = $kayit ? auth()->user()->can('icBilgi', $kayit) : Yetki::var('portfoy.ic_bilgi');
        if (! $icBilgi) {
            unset($data['mulk_sahibi_id'], $data['ic_notlar'], $data['komisyon_anlasmasi']);
        }

        // Başkasının kişisini bağlayıp o kişiye erişim kazanmasın
        $sahip = $data['mulk_sahibi_id'] ?? null;
        if (filled($sahip) && (int) $sahip !== (int) $kayit?->mulk_sahibi_id
            && ! Kapsam::kisiler(Kisi::query())->whereKey($sahip)->exists()) {
            throw ValidationException::withMessages(['data.mulk_sahibi_id' => 'Bu kişiyi seçme yetkiniz yok.']);
        }

        return $data;
    }
}
