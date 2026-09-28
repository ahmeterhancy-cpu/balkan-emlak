<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Gayrimenkulün operasyon durumu (yayın durumundan bağımsız). */
enum OperasyonDurumu: string implements HasColor, HasLabel
{
    case Aktif = 'aktif';
    case Rezerve = 'rezerve';
    case Satildi = 'satildi';
    case Kiralandi = 'kiralandi';
    case Cekildi = 'cekildi';

    public function getLabel(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Rezerve => 'Rezerve',
            self::Satildi => 'Satıldı',
            self::Kiralandi => 'Kiralandı',
            self::Cekildi => 'Portföyden çekildi',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Aktif => 'success',
            self::Rezerve => 'warning',
            self::Satildi => 'gray',
            self::Kiralandi => 'gray',
            self::Cekildi => 'danger',
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
