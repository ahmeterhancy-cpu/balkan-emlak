<?php

namespace App\Filament\Resources\Randevular\Pages;

use App\Models\Portfoy;
use App\Models\Randevu;
use App\Models\Talep;
use App\Support\Kapsam;
use App\Support\Yetki;
use Illuminate\Validation\ValidationException;

/** Danışman randevuyu yalnız kendine ve kendi kapsamındaki portföy/talebe bağlayabilir. */
trait RandevuVerisi
{
    protected function randevuSuz(array $data, ?Randevu $kayit): array
    {
        if (! Yetki::var('randevu.goruntule_tumu')) {
            $data['danisman_id'] = $kayit?->danisman_id ?? auth()->id();
        }

        if (filled($data['portfoy_id'] ?? null) && ! Kapsam::portfoyler(Portfoy::query())->whereKey($data['portfoy_id'])->exists()) {
            throw ValidationException::withMessages(['data.portfoy_id' => 'Bu portföyü seçme yetkiniz yok.']);
        }

        if (filled($data['talep_id'] ?? null) && ! Kapsam::talepler(Talep::query())->whereKey($data['talep_id'])->exists()) {
            throw ValidationException::withMessages(['data.talep_id' => 'Bu talebi seçme yetkiniz yok.']);
        }

        return $data;
    }
}
