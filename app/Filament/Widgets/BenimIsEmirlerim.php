<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\IsEmirleri\IsEmriResource;
use App\Models\IsEmri;
use App\Support\Kapsam;
use App\Support\Yetki;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Saha personeli: yalnız kendisine atanmış açık iş emirleri (şartname 9). */
class BenimIsEmirlerim extends TableWidget
{
    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Açık iş emirlerim';

    public static function canView(): bool
    {
        return Yetki::var('operasyon.saha') && ! Yetki::var('operasyon.yonet');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Kapsam::siteyeBagli(IsEmri::query())->where('atanan_id', auth()->id())
                ->whereNotIn('durum', ['tamamlandi', 'kapatildi'])
                ->with(['site', 'blok', 'bolum.blok'])->orderByRaw('hedef_tarih IS NULL')->orderBy('hedef_tarih'))
            ->recordUrl(fn (IsEmri $record) => IsEmriResource::getUrl('view', ['record' => $record]))
            ->paginated([10])
            ->columns([
                TextColumn::make('site.ad')->label('Site')->badge()->color('gray'),
                TextColumn::make('konu')->label('Konu')->wrap()->description(fn (IsEmri $record) => $record->bolum?->etiket() ?? $record->blok?->ad),
                TextColumn::make('oncelik')->label('Öncelik')->badge()
                    ->formatStateUsing(fn ($state) => IsEmri::ONCELIKLER[$state] ?? $state)->color(fn ($state) => IsEmriResource::oncelikRengi($state)),
                TextColumn::make('hedef_tarih')->label('Hedef')->date('d.m.Y')->placeholder('—'),
            ])
            ->recordActions([IsEmriResource::tamamla()])
            ->emptyStateHeading('Açık iş emriniz yok');
    }
}
