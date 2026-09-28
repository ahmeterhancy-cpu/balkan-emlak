<?php

namespace App\Filament\Resources\Hesaplar\RelationManagers;

use App\Filament\Destek\SiteAlanlari;
use App\Models\HesapHareketi;
use App\Services\SiteFinans\HesapServisi;
use App\Support\Para;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/** Kasa/banka defteri. Hareket değişmez; yalnız mutabakat işareti. */
class HareketlerRelationManager extends RelationManager
{
    protected static string $relationship = 'hareketler';

    protected static ?string $title = 'Hareketler';

    protected static ?string $modelLabel = 'hareket';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $pb = $this->getOwnerRecord()->para_birimi;
        $yetkili = fn () => Gate::allows('update', $this->getOwnerRecord());

        return $table
            ->defaultSort('tarih', 'desc')
            ->columns([
                TextColumn::make('tarih')->label('Tarih')->date('d.m.Y')->sortable(),
                TextColumn::make('tur')->label('Tür')->badge()->color('gray')->formatStateUsing(fn ($state) => HesapHareketi::TURLER[$state] ?? $state),
                TextColumn::make('aciklama')->label('Açıklama')->wrap()->placeholder('—')
                    ->description(fn (HesapHareketi $record) => $record->dayanak),
                TextColumn::make('tutar_kurus')->label('Tutar')->alignEnd()
                    ->formatStateUsing(fn ($state) => ($state > 0 ? '+' : '').Para::bicimle((int) $state, $pb))
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success'),
                IconColumn::make('mutabik')->label('Mutabık')->boolean(),
                TextColumn::make('mutabakat_tarihi')->label('Mutabakat')->date('d.m.Y')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('tur')->label('Tür')->options(HesapHareketi::TURLER),
                TernaryFilter::make('mutabik')->label('Mutabık'),
                Filter::make('tarih')
                    ->schema([
                        DatePicker::make('bas')->label('Başlangıç')->native(false)->displayFormat('d.m.Y'),
                        DatePicker::make('bit')->label('Bitiş')->native(false)->displayFormat('d.m.Y'),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['bas'] ?? null, fn ($q, $v) => $q->where('tarih', '>=', $v))
                        ->when($data['bit'] ?? null, fn ($q, $v) => $q->where('tarih', '<=', $v))),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('mutabikIsaretle')
                        ->label('Mutabık işaretle')
                        ->icon(Heroicon::OutlinedCheck)
                        ->authorize($yetkili)
                        ->schema([
                            DatePicker::make('tarih')->label('Mutabakat tarihi')->required()->native(false)->displayFormat('d.m.Y')->default(fn () => now()->toDateString()),
                        ])
                        ->action(function (Collection $records, array $data) {
                            SiteAlanlari::erisimDenetle($this->getOwnerRecord()->site_id);
                            $adet = app(HesapServisi::class)->mutabakat($records->where('hesap_id', $this->getOwnerRecord()->id), true, $data['tarih']);
                            Notification::make()->success()->title($adet.' hareket mutabık işaretlendi')->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('mutabikKaldir')
                        ->label('Mutabakatı kaldır')
                        ->icon(Heroicon::OutlinedXMark)
                        ->color('gray')
                        ->authorize($yetkili)
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            SiteAlanlari::erisimDenetle($this->getOwnerRecord()->site_id);
                            $adet = app(HesapServisi::class)->mutabakat($records->where('hesap_id', $this->getOwnerRecord()->id), false);
                            Notification::make()->success()->title($adet.' hareketin mutabakatı kaldırıldı')->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('Hareket yok');
    }
}
