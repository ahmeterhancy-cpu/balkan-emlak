<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Talep hattı (şartname 7.2). */
enum TalepDurumu: string implements HasColor, HasLabel
{
    case Yeni = 'yeni';
    case IletisimeGecildi = 'iletisime_gecildi';
    case RandevuPlanlandi = 'randevu_planlandi';
    case GorusmeSuruyor = 'gorusme_suruyor';
    case Sonuclandi = 'sonuclandi';
    case Kaybedildi = 'kaybedildi';

    public function getLabel(): string
    {
        return match ($this) {
            self::Yeni => 'Yeni',
            self::IletisimeGecildi => 'İletişime geçildi',
            self::RandevuPlanlandi => 'Randevu planlandı',
            self::GorusmeSuruyor => 'Görüşme sürüyor',
            self::Sonuclandi => 'Sonuçlandı',
            self::Kaybedildi => 'Kaybedildi',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Yeni => 'danger',
            self::IletisimeGecildi => 'warning',
            self::RandevuPlanlandi => 'info',
            self::GorusmeSuruyor => 'primary',
            self::Sonuclandi => 'success',
            self::Kaybedildi => 'gray',
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
