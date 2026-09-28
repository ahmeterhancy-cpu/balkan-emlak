<?php

namespace App\Filament\Resources\BakimPlanlari;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\BakimPlanlari\Pages\ManageBakimPlanlari;
use App\Filament\Resources\IsEmirleri\IsEmriResource;
use App\Models\BakimPlani;
use App\Models\IsEmri;
use App\Models\Tedarikci;
use App\Models\Varlik;
use App\Support\Kapsam;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/** Periyodik bakım planları; gecikenler kırmızı. İş emri tamamlanınca plan ilerler. */
class BakimPlaniResource extends Resource
{
    protected static ?string $model = BakimPlani::class;

    protected static ?string $slug = 'bakim-planlari';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Bakım Planları';

    protected static ?string $modelLabel = 'bakım planı';

    protected static ?string $pluralModelLabel = 'bakım planları';

    protected static string|UnitEnum|null $navigationGroup = 'Site Operasyonu';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return Kapsam::siteyeBagli(parent::getEloquentQuery());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            SiteAlanlari::site('site_id', ['varlik_id', 'sorumlu_id'])->disabledOn('edit'),
            Select::make('varlik_id')->label('Varlık')
                ->options(fn ($get) => filled($get('site_id')) && Kapsam::siteErisimi((int) $get('site_id'))
                    ? Varlik::where('site_id', (int) $get('site_id'))->orderBy('ad')->pluck('ad', 'id')->all() : []),
            TextInput::make('ad')->label('Plan adı')->required()->maxLength(255),
            TextInput::make('periyot_gun')->label('Periyot (gün)')->numeric()->integer()->minValue(1)->maxValue(3650)->required()->default(30),
            DatePicker::make('son_bakim')->label('Son bakım')->native(false)->displayFormat('d.m.Y'),
            DatePicker::make('sonraki_bakim')->label('Sonraki bakım')->native(false)->displayFormat('d.m.Y')->required(),
            Select::make('tedarikci_id')->label('Tedarikçi')->options(fn () => Tedarikci::where('aktif', true)->orderBy('ad')->pluck('ad', 'id')->all())->searchable(),
            Select::make('sorumlu_id')->label('Sorumlu personel')->options(fn ($get) => SiteAlanlari::personelSecenekleri($get('site_id'))),
            Toggle::make('aktif')->label('Aktif')->default(true)->inline(false),
        ]);
    }

    public static function gecikti(BakimPlani $p): bool
    {
        return $p->aktif && $p->sonraki_bakim->toDateString() < now()->toDateString();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['site', 'varlik', 'tedarikci', 'sorumlu']))
            ->defaultSort('sonraki_bakim')
            ->columns([
                SiteAlanlari::siteSutunu(),
                TextColumn::make('ad')->label('Plan')->searchable()->description(fn (BakimPlani $record) => $record->varlik?->ad),
                TextColumn::make('periyot_gun')->label('Periyot')->formatStateUsing(fn ($state) => $state.' gün'),
                TextColumn::make('son_bakim')->label('Son bakım')->date('d.m.Y')->placeholder('—'),
                TextColumn::make('sonraki_bakim')->label('Sonraki bakım')->date('d.m.Y')->sortable()->weight('bold')
                    ->color(fn (BakimPlani $record) => static::gecikti($record) ? 'danger'
                        : ($record->aktif && $record->sonraki_bakim->toDateString() <= now()->addDays(14)->toDateString() ? 'warning' : null))
                    ->description(fn (BakimPlani $record) => static::gecikti($record) ? 'Gecikti' : null),
                TextColumn::make('sorumlu')->label('Tedarikçi / sorumlu')->state(fn (BakimPlani $record) => $record->tedarikci?->ad ?? $record->sorumlu?->name)->placeholder('—'),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->filters([
                SiteAlanlari::siteFiltresi(),
                Filter::make('geciken')->label('Yalnız gecikenler')->query(fn ($query) => $query->where('aktif', true)->where('sonraki_bakim', '<', now()->toDateString())),
            ])
            ->recordActions([
                static::isEmriOlustur(),
                EditAction::make()->label('Düzenle')->mutateDataUsing(function (array $data) {
                    unset($data['site_id']);

                    return $data;
                }),
            ])
            ->emptyStateHeading('Bakım planı yok');
    }

    public static function isEmriOlustur(): Action
    {
        return Action::make('isEmriOlustur')
            ->label('Bakım iş emri oluştur')
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->color('primary')
            ->authorize(fn () => Gate::allows('create', IsEmri::class))
            ->requiresConfirmation()
            ->modalDescription(fn (BakimPlani $record) => $record->ad.' için iş emri açılır; tamamlanınca planın sonraki bakım tarihi ilerler.')
            ->action(function (BakimPlani $record) {
                SiteAlanlari::erisimDenetle($record->site_id);
                $personel = SiteAlanlari::personelSecenekleri($record->site_id);
                $atanan = $record->sorumlu_id && array_key_exists($record->sorumlu_id, $personel) ? $record->sorumlu_id : null;

                $e = IsEmri::create([
                    'site_id' => $record->site_id,
                    'bakim_plani_id' => $record->id,
                    'blok_id' => $record->varlik?->blok_id,
                    'konu' => $record->ad,
                    'aciklama' => 'Periyodik bakım ('.$record->periyot_gun.' günde bir). Planlanan tarih: '.$record->sonraki_bakim->format('d.m.Y'),
                    'oncelik' => 'normal',
                    'atanan_id' => $atanan,
                    'tedarikci_id' => $record->tedarikci_id,
                    'hedef_tarih' => $record->sonraki_bakim->toDateString(),
                ]);

                Notification::make()->success()->title('İş emri oluşturuldu')
                    ->actions([Action::make('ac')->label('Aç')->url(IsEmriResource::getUrl('view', ['record' => $e]))])
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBakimPlanlari::route('/'),
        ];
    }
}
