<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** İlanın web sitesindeki yayın durumu. Ziyaretçi yalnız `yayinda` görür. */
enum YayinDurumu: string implements HasColor, HasLabel
{
    case Taslak = 'taslak';
    case Incelemede = 'incelemede';
    case Yayinda = 'yayinda';
    case Kaldirildi = 'kaldirildi';
    case Arsiv = 'arsiv';

    public function getLabel(): string
    {
        return match ($this) {
            self::Taslak => 'Taslak',
            self::Incelemede => 'İncelemede',
            self::Yayinda => 'Yayında',
            self::Kaldirildi => 'Yayından kaldırıldı',
            self::Arsiv => 'Arşiv',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Taslak => 'gray',
            self::Incelemede => 'warning',
            self::Yayinda => 'success',
            self::Kaldirildi => 'danger',
            self::Arsiv => 'gray',
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
