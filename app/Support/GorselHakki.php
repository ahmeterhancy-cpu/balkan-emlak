<?php

namespace App\Support;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Görsel kullanım hakkı (şartname 10). Stok görsel gerçek ekip veya
 * yönetilen site gibi sunulmamalı (4.3); kimin çektiği ve hangi izinle
 * kullanıldığı kayıtlı olsun.
 */
class GorselHakki
{
    public const SECENEKLER = [
        'kendi' => 'Kendi çekimimiz',
        'mulk_sahibi' => 'Mülk sahibinin / yönetimin izniyle',
        'lisansli' => 'Lisanslı / stok görsel',
        'bilinmiyor' => 'Bilinmiyor — yayından önce teyit edin',
    ];

    /** @return array{0: Select, 1: TextInput} Filament form alanları */
    public static function alanlar(): array
    {
        return [
            Select::make('gorsel_hakki')
                ->label('Görsel kullanım hakkı')
                ->options(self::SECENEKLER)
                ->placeholder('Seçilmedi'),
            TextInput::make('gorsel_kaynak')
                ->label('Kaynak / izin notu')
                ->maxLength(255)
                ->placeholder('Fotoğrafçı, lisans adı ya da izin tarihi')
                ->helperText(fn (Get $get) => $get('gorsel_hakki') === 'lisansli' ? 'Lisans adını ve kaynağını yazın (ör. "Unsplash lisansı, fotoğrafçı adı").' : null),
        ];
    }

    public static function etiket(?string $deger): ?string
    {
        return $deger ? (self::SECENEKLER[$deger] ?? $deger) : null;
    }
}
