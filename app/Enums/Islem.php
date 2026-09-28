<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** İşlem türü */
enum Islem: string implements HasColor, HasLabel
{
    case Satilik = 'satilik';
    case Kiralik = 'kiralik';

    public function getLabel(): string
    {
        return match ($this) {
            self::Satilik => 'Satılık',
            self::Kiralik => 'Kiralık',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Satilik => 'success',
            self::Kiralik => 'info',
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
