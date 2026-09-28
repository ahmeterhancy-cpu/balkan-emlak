<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Komisyon tahsilat durumu. Resmi muhasebe kaydı değildir. */
enum KomisyonDurumu: string implements HasColor, HasLabel
{
    case Bekliyor = 'bekliyor';
    case Kismi = 'kismi';
    case TahsilEdildi = 'tahsil_edildi';
    case Iptal = 'iptal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bekliyor => 'Bekliyor',
            self::Kismi => 'Kısmen tahsil edildi',
            self::TahsilEdildi => 'Tahsil edildi',
            self::Iptal => 'İptal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Bekliyor => 'warning',
            self::Kismi => 'info',
            self::TahsilEdildi => 'success',
            self::Iptal => 'gray',
        };
    }

    /** @return array<string, string> değer => etiket */
    public static function secenekler(): array
    {
        $sonuc = [];
        foreach (self::cases() as $durum) {
            $sonuc[$durum->value] = $durum->getLabel();
        }

        return $sonuc;
    }
}
