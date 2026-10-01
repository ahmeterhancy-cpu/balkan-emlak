<?php

namespace App\Filament\Widgets;

use App\Filament\Destek\SiteGostergeSuzgeci;
use App\Filament\Pages\GostergePaneli;
use App\Filament\Resources\Giderler\GiderResource;
use App\Models\Gider;
use App\Services\SiteFinans\GiderServisi;
use App\Support\Para;
use App\Support\Yetki;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

/** Onay bekleyen giderler; limit aşanlarda "şirket yöneticisi onayı" uyarısı. */
class OnayBekleyenGiderler extends TableWidget
{
    use InteractsWithPageFilters, SiteGostergeSuzgeci;

    protected static ?int $sort = 3;

    protected static ?string $heading = 'Onay bekleyen giderler';

    public static function canView(): bool
    {
        return GostergePaneli::siteGostergeleri() && (Yetki::var('gider.onayla') || Yetki::var('gider.onayla_limitsiz'));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Gider::query()->whereIn('site_id', $this->siteIdleri())->where('onay_durumu', 'onay_bekliyor')
                ->with(['site', 'tedarikci'])->oldest('belge_tarihi'))
            ->paginated([5])
            ->recordUrl(fn (Gider $record) => GiderResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('site.ad')->label('Site')->badge()->color('gray'),
                TextColumn::make('aciklama')->label('Gider')->limit(34)->description(fn (Gider $record) => $record->tedarikci?->ad),
                TextColumn::make('tutar_kurus')->label('Tutar')->alignEnd()->formatStateUsing(fn ($state, Gider $record) => Para::bicimle((int) $state, $record->para_birimi)),
                TextColumn::make('limit')->label('Onay')
                    ->state(fn (Gider $record) => app(GiderServisi::class)->onaylayabilirMi($record) ? 'Onaylayabilirsiniz'
                        : 'Limit aşıyor — şirket yöneticisi onayı gerekir')
                    ->color(fn ($state) => $state === 'Onaylayabilirsiniz' ? 'success' : 'danger')
                    ->wrap(),
            ])
            ->recordActions([GiderResource::onayla()])
            ->emptyStateHeading('Onay bekleyen gider yok');
    }
}
