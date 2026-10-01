<?php

namespace App\Filament\Widgets;

use App\Enums\RandevuDurumu;
use App\Filament\Resources\Randevular\RandevuResource;
use App\Models\Randevu;
use App\Support\Kapsam;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Gate;

class YaklasanRandevular extends TableWidget
{
    protected static ?int $sort = 11;

    protected static ?string $heading = 'Yaklaşan randevular';

    public static function canView(): bool
    {
        return Gate::allows('viewAny', Randevu::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Kapsam::randevular(Randevu::query())
                ->with(['portfoy', 'danisman'])
                ->where('durum', RandevuDurumu::Planlandi->value)
                ->where('baslangic', '>=', now())
                ->orderBy('baslangic'))
            ->paginated([5])
            ->recordUrl(fn (Randevu $record) => RandevuResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('baslangic')->label('Zaman')->dateTime('d.m.Y H:i'),
                TextColumn::make('musteri_adi')->label('Müşteri'),
                TextColumn::make('portfoy.portfoy_no')->label('Portföy')->placeholder('—'),
                TextColumn::make('danisman.name')->label('Danışman')->placeholder('—'),
            ])
            ->emptyStateHeading('Planlanmış randevu yok');
    }
}
