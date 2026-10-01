<?php

namespace App\Filament\Widgets;

use App\Filament\Destek\SiteGostergeSuzgeci;
use App\Filament\Pages\GostergePaneli;
use App\Filament\Resources\BakimPlanlari\BakimPlaniResource;
use App\Models\BakimPlani;
use App\Support\Yetki;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

/** Gecikmiş ve 14 gün içindeki bakımlar. */
class YaklasanBakimlar extends TableWidget
{
    use InteractsWithPageFilters, SiteGostergeSuzgeci;

    protected static ?int $sort = 4;

    protected static ?string $heading = 'Yaklaşan ve geciken bakımlar (14 gün)';

    public static function canView(): bool
    {
        return GostergePaneli::siteGostergeleri() && Yetki::var('operasyon.yonet');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => BakimPlani::query()->whereIn('site_id', $this->siteIdleri())->where('aktif', true)
                ->where('sonraki_bakim', '<=', now()->addDays(14)->toDateString())
                ->with(['site', 'varlik'])->orderBy('sonraki_bakim'))
            ->paginated([5])
            ->columns([
                TextColumn::make('site.ad')->label('Site')->badge()->color('gray'),
                TextColumn::make('ad')->label('Bakım')->description(fn (BakimPlani $record) => $record->varlik?->ad),
                TextColumn::make('sonraki_bakim')->label('Tarih')->date('d.m.Y')
                    ->color(fn (BakimPlani $record) => BakimPlaniResource::gecikti($record) ? 'danger' : 'warning')
                    ->description(fn (BakimPlani $record) => BakimPlaniResource::gecikti($record) ? 'Gecikti' : null),
            ])
            ->recordActions([BakimPlaniResource::isEmriOlustur()])
            ->emptyStateHeading('Yaklaşan bakım yok');
    }
}
