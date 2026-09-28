<?php

namespace App\Filament\Destek;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Düzenleme sayfalarındaki "Önizle" düğmesi: kaydedilmiş hali vitrin
 * tasarımında yeni sekmede açar (routes/web.php → onizleme.*). Kaydedilmemiş
 * değişiklik önizlemede görünmez; düğme bunu belirtir.
 */
class OnizleAksiyonu
{
    public static function make(string $rota): Action
    {
        return Action::make('onizle')
            ->label('Önizle')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->tooltip('Kaydedilmiş hali sitede nasıl görünüyor — önce kaydedin')
            ->url(fn (Model $record): string => route($rota, $record->getKey()), shouldOpenInNewTab: true);
    }
}
