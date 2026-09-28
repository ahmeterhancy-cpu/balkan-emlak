<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Randevu durumu. */
enum RandevuDurumu: string implements HasColor, HasLabel
{
    case Planlandi = 'planlandi';
    case Tamamlandi = 'tamamlandi';
    case Iptal = 'iptal';
    case Gelmedi = 'gelmedi';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planlandi => 'Planlandı',
            self::Tamamlandi => 'Tamamlandı',
            self::Iptal => 'İptal',
            self::Gelmedi => 'Gelmedi',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planlandi => 'info',
            self::Tamamlandi => 'success',
            self::Iptal => 'gray',
            self::Gelmedi => 'danger',
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
