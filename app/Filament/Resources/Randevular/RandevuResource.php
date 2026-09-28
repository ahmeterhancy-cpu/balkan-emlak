<?php

namespace App\Filament\Resources\Randevular;

use App\Enums\RandevuDurumu;
use App\Filament\Destek\Alanlar;
use App\Filament\Resources\Randevular\Pages\CreateRandevu;
use App\Filament\Resources\Randevular\Pages\EditRandevu;
use App\Filament\Resources\Randevular\Pages\ListRandevular;
use App\Models\Portfoy;
use App\Models\Randevu;
use App\Models\Talep;
use App\Models\User;
use App\Support\Kapsam;
use App\Support\Yetki;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RandevuResource extends Resource
{
    protected static ?string $model = Randevu::class;

    protected static ?string $slug = 'randevular';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Randevular';

    protected static ?string $modelLabel = 'randevu';

    protected static ?string $pluralModelLabel = 'randevular';

    protected static string|UnitEnum|null $navigationGroup = 'Emlak Yönetimi';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return Kapsam::randevular(parent::getEloquentQuery());
    }

    /** Kapsam içindeki portföyler: "BLK-00012 — Başlık". */
    public static function portfoySecenekleri(): array
    {
        return Kapsam::portfoyler(Portfoy::query())
            ->orderByDesc('id')
            ->limit(500)
            ->get(['id', 'portfoy_no', 'baslik'])
            ->mapWithKeys(fn (Portfoy $p) => [$p->id => $p->portfoy_no.' — '.$p->baslik])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Randevu')
                ->columns(2)
                ->schema([
                    Alanlar::danisman('danisman_id', 'Danışman', 'randevu.goruntule_tumu')->required(),
                    Select::make('portfoy_id')
                        ->label('Portföy')
                        ->options(fn () => static::portfoySecenekleri())
                        ->searchable(),
                    TextInput::make('musteri_adi')->label('Müşteri adı')->required()->maxLength(255),
                    TextInput::make('musteri_telefon')->label('Müşteri telefonu')->tel()->maxLength(30),
                    DateTimePicker::make('baslangic')
                        ->label('Başlangıç')
                        ->seconds(false)
                        ->native(false)
                        ->displayFormat('d.m.Y H:i')
                        ->minutesStep(5)
                        ->required(),
                    TextInput::make('sure_dk')->label('Süre (dakika)')->numeric()->minValue(5)->maxValue(600)->default(60)->required(),
                    Select::make('durum')->label('Durum')->options(RandevuDurumu::class)->default(RandevuDurumu::Planlandi->value)->selectablePlaceholder(false)->required(),
                    Select::make('talep_id')
                        ->label('İlgili talep')
                        ->options(fn () => Kapsam::talepler(Talep::query())->latest()->limit(300)->get()
                            ->mapWithKeys(fn (Talep $t) => [$t->id => $t->ad_soyad.' · '.$t->created_at?->format('d.m.Y')])->all())
                        ->searchable(),
                    Textarea::make('sonuc_notu')->label('Sonuç notu')->rows(3)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['danisman', 'portfoy']))
            ->defaultSort('baslangic', 'asc')
            ->columns([
                TextColumn::make('baslangic')->label('Zaman')->dateTime('d.m.Y H:i')->sortable()
                    ->description(fn (Randevu $record) => $record->sure_dk.' dk'),
                TextColumn::make('musteri_adi')->label('Müşteri')->searchable()
                    ->description(fn (Randevu $record) => $record->musteri_telefon),
                TextColumn::make('portfoy.portfoy_no')->label('Portföy')->placeholder('—')
                    ->description(fn (Randevu $record) => str($record->portfoy?->baslik)->limit(40)),
                TextColumn::make('danisman.name')->label('Danışman')->placeholder('—'),
                TextColumn::make('durum')->label('Durum')->badge(),
            ])
            ->filters([
                TernaryFilter::make('zaman')
                    ->label('Zaman')
                    ->placeholder('Tümü')
                    ->trueLabel('Yaklaşan')
                    ->falseLabel('Geçmiş')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->where('baslangic', '>=', now()->startOfDay()),
                        false: fn (Builder $query) => $query->where('baslangic', '<', now()->startOfDay()),
                        blank: fn (Builder $query) => $query,
                    ),
                SelectFilter::make('durum')->label('Durum')->options(RandevuDurumu::class),
                SelectFilter::make('danisman_id')
                    ->label('Danışman')
                    ->options(fn () => User::query()->danismanlar()->pluck('name', 'id')->all())
                    ->visible(fn () => Yetki::var('randevu.goruntule_tumu')),
            ])
            ->recordActions([EditAction::make()->label('Düzenle')])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('Randevu yok');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRandevular::route('/'),
            'create' => CreateRandevu::route('/create'),
            'edit' => EditRandevu::route('/{record}/edit'),
        ];
    }
}
