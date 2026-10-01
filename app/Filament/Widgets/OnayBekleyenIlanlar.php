<?php

namespace App\Filament\Widgets;

use App\Enums\YayinDurumu;
use App\Filament\Resources\Portfoyler\PortfoyEylemleri;
use App\Filament\Resources\Portfoyler\PortfoyResource;
use App\Models\Portfoy;
use App\Support\Kapsam;
use App\Support\Yetki;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** İncelemeye gönderilmiş ilanlar; yalnız yayın yetkisi olanlara. */
class OnayBekleyenIlanlar extends TableWidget
{
    protected static ?int $sort = 12;

    protected static ?string $heading = 'Onay bekleyen ilanlar';

    public static function canView(): bool
    {
        return Yetki::var('portfoy.yayimla');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Kapsam::portfoyler(Portfoy::query())
                ->with('danisman')
                ->where('yayin_durumu', YayinDurumu::Incelemede->value)
                ->latest('updated_at'))
            ->paginated([5])
            ->recordUrl(fn (Portfoy $record) => PortfoyResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('portfoy_no')->label('No'),
                TextColumn::make('baslik')->label('Başlık')->limit(40),
                TextColumn::make('danisman.name')->label('Danışman')->placeholder('—'),
                TextColumn::make('eksik')
                    ->label('Eksikler')
                    ->state(fn (Portfoy $record) => ($e = $record->yayinEksikleri()) ? implode(', ', $e) : 'Hazır')
                    ->color(fn (Portfoy $record) => $record->yayinEksikleri() ? 'danger' : 'success')
                    ->wrap(),
            ])
            ->recordActions([PortfoyEylemleri::yayimla()])
            ->emptyStateHeading('Onay bekleyen ilan yok');
    }
}
