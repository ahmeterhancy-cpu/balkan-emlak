<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Gorevler\GorevResource;
use App\Models\Gorev;
use App\Support\Kapsam;
use App\Support\Yetki;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Saha personeli: bugünkü (ve geciken) bekleyen görevleri. */
class BugunkuGorevlerim extends TableWidget
{
    protected static ?int $sort = 21;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Bugünkü görevlerim';

    public static function canView(): bool
    {
        return Yetki::var('operasyon.saha') && ! Yetki::var('operasyon.yonet');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Kapsam::siteyeBagli(Gorev::query())->where('user_id', auth()->id())
                ->where('durum', 'bekliyor')->where('tarih', '<=', now()->toDateString())
                ->with('site')->orderBy('tarih'))
            ->paginated([10])
            ->columns([
                TextColumn::make('site.ad')->label('Site')->badge()->color('gray'),
                TextColumn::make('baslik')->label('Görev')->wrap()->description(fn (Gorev $record) => $record->aciklama),
                TextColumn::make('tarih')->label('Tarih')->date('d.m.Y')
                    ->color(fn (Gorev $record) => $record->tarih->isToday() ? null : 'danger'),
            ])
            ->recordActions([GorevResource::tamamla()])
            ->emptyStateHeading('Bugün için bekleyen görev yok');
    }
}
