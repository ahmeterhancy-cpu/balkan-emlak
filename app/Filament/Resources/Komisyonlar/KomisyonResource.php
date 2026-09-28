<?php

namespace App\Filament\Resources\Komisyonlar;

use App\Enums\KomisyonDurumu;
use App\Filament\Destek\Alanlar;
use App\Filament\Resources\Komisyonlar\Pages\CreateKomisyon;
use App\Filament\Resources\Komisyonlar\Pages\EditKomisyon;
use App\Filament\Resources\Komisyonlar\Pages\ListKomisyonlar;
use App\Filament\Resources\Randevular\RandevuResource;
use App\Models\Komisyon;
use App\Models\User;
use App\Support\Para;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Komisyon takibi. Resmi muhasebe belgesi DEĞİLDİR. Farklı para birimleri
 * hiçbir toplamda birleştirilmez; özet para birimine göre gruplanır.
 */
class KomisyonResource extends Resource
{
    protected static ?string $model = Komisyon::class;

    protected static ?string $slug = 'komisyonlar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Komisyon Takibi';

    protected static ?string $modelLabel = 'komisyon kaydı';

    protected static ?string $pluralModelLabel = 'komisyon kayıtları';

    protected static string|UnitEnum|null $navigationGroup = 'Emlak Yönetimi';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Komisyon')
                ->description('Bu kayıt iç takip içindir; resmi muhasebe belgesi değildir.')
                ->columns(3)
                ->schema([
                    Select::make('portfoy_id')
                        ->label('Portföy')
                        ->options(fn () => RandevuResource::portfoySecenekleri())
                        ->searchable()
                        ->columnSpan(2),
                    Select::make('danisman_id')
                        ->label('Danışman')
                        ->options(fn () => User::query()->danismanlar()->pluck('name', 'id')->all())
                        ->searchable(),
                    Select::make('taraf')->label('Ödeyen taraf')->options(Komisyon::TARAFLAR)->default('satici')->required(),
                    Select::make('para_birimi')->label('Para birimi')->options(Para::BIRIMLER)->default('TRY')->selectablePlaceholder(false)->required(),
                    Select::make('durum')->label('Tahsilat durumu')->options(KomisyonDurumu::class)->default(KomisyonDurumu::Bekliyor->value)->selectablePlaceholder(false)->required(),
                    Alanlar::para('beklenen_kurus', 'Beklenen tutar')->required(),
                    Alanlar::para('gerceklesen_kurus', 'Gerçekleşen tutar'),
                    DatePicker::make('odeme_tarihi')->label('Ödeme tarihi')->native(false)->displayFormat('d.m.Y'),
                    Textarea::make('aciklama')->label('Açıklama')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['portfoy', 'danisman']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('portfoy.portfoy_no')->label('Portföy')->placeholder('—')
                    ->description(fn (Komisyon $record) => str($record->portfoy?->baslik)->limit(40)),
                TextColumn::make('danisman.name')->label('Danışman')->placeholder('—'),
                TextColumn::make('taraf')->label('Taraf')->formatStateUsing(fn (?string $state) => Komisyon::TARAFLAR[$state] ?? $state),
                TextColumn::make('beklenen_kurus')->label('Beklenen')->alignEnd()->sortable()
                    ->formatStateUsing(fn ($state, Komisyon $record) => Para::bicimle($state, $record->para_birimi)),
                TextColumn::make('gerceklesen_kurus')->label('Gerçekleşen')->alignEnd()->placeholder('—')
                    ->formatStateUsing(fn ($state, Komisyon $record) => Para::bicimle($state, $record->para_birimi)),
                TextColumn::make('para_birimi')->label('Birim')->toggleable(),
                TextColumn::make('durum')->label('Durum')->badge(),
                TextColumn::make('odeme_tarihi')->label('Ödeme')->date('d.m.Y')->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('durum')->label('Durum')->options(KomisyonDurumu::class),
                SelectFilter::make('para_birimi')->label('Para birimi')->options(Para::BIRIMLER),
                SelectFilter::make('danisman_id')->label('Danışman')->options(fn () => User::query()->danismanlar()->pluck('name', 'id')->all()),
            ])
            ->recordActions([EditAction::make()->label('Düzenle')])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKomisyonlar::route('/'),
            'create' => CreateKomisyon::route('/create'),
            'edit' => EditKomisyon::route('/{record}/edit'),
        ];
    }
}
