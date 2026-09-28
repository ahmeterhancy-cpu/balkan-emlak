<?php

namespace App\Filament\Resources\Portfoyler\Tables;

use App\Enums\Islem;
use App\Enums\Kategori;
use App\Enums\OperasyonDurumu;
use App\Enums\YayinDurumu;
use App\Filament\Destek\CsvAktarici;
use App\Filament\Resources\Portfoyler\PortfoyEylemleri;
use App\Models\Konum;
use App\Models\Portfoy;
use App\Models\User;
use App\Support\Yetki;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PortfoylerTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['kapak', 'il', 'ilce', 'mahalle', 'danisman']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('kapak_gorsel')
                    ->label('')
                    ->state(fn (Portfoy $record): ?string => $record->kapak?->kucukUrl())
                    ->imageWidth(72)
                    ->imageHeight(52)
                    ->extraImgAttributes(['class' => 'rounded object-cover', 'alt' => '']),
                TextColumn::make('portfoy_no')
                    ->label('No')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->size('sm'),
                TextColumn::make('baslik')
                    ->label('Başlık')
                    ->searchable()
                    ->sortable()
                    ->limit(48)
                    ->tooltip(fn (Portfoy $record) => $record->baslik)
                    ->description(fn (Portfoy $record): string => trim($record->kategori?->getLabel().' · '.$record->tipEtiketi(), ' ·')),
                TextColumn::make('islem')
                    ->label('İşlem')
                    ->badge(),
                TextColumn::make('fiyat_kurus')
                    ->label('Fiyat')
                    ->state(fn (Portfoy $record): string => $record->fiyatMetni())
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('fiyat_kurus', $direction))
                    ->alignEnd(),
                TextColumn::make('konum')
                    ->label('Konum')
                    ->state(fn (Portfoy $record): string => $record->konumMetni())
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('danisman.name')
                    ->label('Danışman')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('operasyon_durumu')
                    ->label('Operasyon')
                    ->badge(),
                TextColumn::make('yayin_durumu')
                    ->label('Yayın')
                    ->badge(),
                IconColumn::make('one_cikan')
                    ->label('Öne çıkan')
                    ->boolean()
                    ->trueIcon(Heroicon::Star)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Oluşturulma')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('islem')->label('İşlem')->options(Islem::class),
                SelectFilter::make('kategori')->label('Gayrimenkul türü')->options(Kategori::class),
                SelectFilter::make('yayin_durumu')->label('Yayın durumu')->options(YayinDurumu::class)->multiple(),
                SelectFilter::make('operasyon_durumu')->label('Operasyon durumu')->options(OperasyonDurumu::class)->multiple(),
                SelectFilter::make('danisman_id')
                    ->label('Danışman')
                    ->options(fn () => User::query()->danismanlar()->pluck('name', 'id')->all())
                    ->visible(fn () => Yetki::var('portfoy.goruntule_tumu')),
                Filter::make('konum')
                    ->schema([
                        Select::make('il_id')
                            ->label('İl')
                            ->options(fn () => Konum::secenekler('il'))
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('ilce_id', null)),
                        Select::make('ilce_id')
                            ->label('İlçe')
                            ->options(fn (Get $get) => $get('il_id') ? Konum::secenekler('ilce', (int) $get('il_id')) : []),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['il_id'] ?? null, fn ($w, $il) => $w->where('il_id', $il))
                        ->when($data['ilce_id'] ?? null, fn ($w, $ilce) => $w->where('ilce_id', $ilce)))
                    ->indicateUsing(function (array $data): array {
                        $etiket = [];
                        if ($data['il_id'] ?? null) {
                            $etiket[] = 'İl: '.Konum::find($data['il_id'])?->ad;
                        }
                        if ($data['ilce_id'] ?? null) {
                            $etiket[] = 'İlçe: '.Konum::find($data['ilce_id'])?->ad;
                        }

                        return $etiket;
                    }),
            ])
            ->headerActions([
                Action::make('csvIndir')
                    ->label('CSV indir')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->authorize(fn (): bool => CsvAktarici::izinliMi())
                    ->action(fn ($livewire) => CsvAktarici::portfoyler($livewire->getFilteredSortedTableQuery())),
            ])
            ->recordActions([
                ViewAction::make()->label('Görüntüle'),
                EditAction::make()->label('Düzenle'),
                ActionGroup::make(PortfoyEylemleri::hepsi()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('csvSecilenler')
                        ->label('Seçilenleri CSV indir')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->authorize(fn (): bool => CsvAktarici::izinliMi())
                        ->action(fn (Collection $records) => CsvAktarici::portfoyler($records)),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Henüz portföy yok')
            ->emptyStateDescription('Yeni portföy ekleyerek başlayın; kayıt taslak olarak açılır ve sitede görünmez.');
    }
}
