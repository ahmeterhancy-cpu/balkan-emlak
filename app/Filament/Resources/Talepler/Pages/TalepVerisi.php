<?php

namespace App\Filament\Resources\Talepler\Pages;

use App\Models\Portfoy;
use App\Models\Talep;
use App\Support\Kapsam;
use App\Support\Yetki;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Atama yetkisi olmayan talebi yalnız kendine açar; ilan seçimi kapsam içinde olmalı. */
trait TalepVerisi
{
    protected function talepSuz(array $data, ?Talep $kayit): array
    {
        $ataYetkisi = $kayit ? Gate::allows('ata', $kayit) : Yetki::var('talep.ata');

        if (! $ataYetkisi) {
            $data['atanan_id'] = $kayit?->atanan_id ?? auth()->id();
        }

        $portfoy = $data['portfoy_id'] ?? null;
        if (filled($portfoy) && (int) $portfoy !== (int) $kayit?->portfoy_id
            && ! Kapsam::portfoyler(Portfoy::query())->whereKey($portfoy)->exists()) {
            throw ValidationException::withMessages(['data.portfoy_id' => 'Bu ilanı seçme yetkiniz yok.']);
        }

        return $data;
    }
}
