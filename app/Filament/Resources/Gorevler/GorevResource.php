<?php

namespace App\Filament\Resources\Gorevler;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\Gorevler\Pages\ManageGorevler;
use App\Models\Gorev;
use App\Support\Kapsam;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Personel görevleri (şartname 8.6). Saha personeli yalnız kendi
 * görevlerini görür ve tamamlar; bordro / özlük kapsam dışı.
 */
class GorevResource extends Resource
{
    protected static ?string $model = Gorev::class;

    protected static ?string $slug = 'gorevler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Personel ve Görevler';

    protected static ?string $modelLabel = 'görev';

    protected static ?string $pluralModelLabel = 'görevler';

    protected static string|UnitEnum|null $navigationGroup = 'Site Operasyonu';

    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        return Kapsam::operasyon(parent::getEloquentQuery(), 'user_id');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            SiteAlanlari::site('site_id', ['user_id'])->disabledOn('edit'),
            Select::make('user_id')->label('Personel')->options(fn ($get) => SiteAlanlari::personelSecenekleri($get('site_id')))->required(),
            TextInput::make('baslik')->label('Görev')->required()->maxLength(255),
            DatePicker::make('tarih')->label('Tarih')->required()->native(false)->displayFormat('d.m.Y')->default(fn () => now()->toDateString()),
            Textarea::make('aciklama')->label('Açıklama')->rows(2)->columnSpanFull(),
            Select::make('durum')->label('Durum')->options(['bekliyor' => 'Bekliyor', 'iptal' => 'İptal'])->default('bekliyor')->required()
                ->selectablePlaceholder(false)->hiddenOn('create'),
        ]);
    }

    /** Formdan gelen personelin siteye atanmış olduğunu denetle. */
    public static function denetle(array $data, int $siteId): array
    {
        abort_unless(array_key_exists((int) ($data['user_id'] ?? 0), SiteAlanlari::personelSecenekleri($siteId)), 403);

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['site', 'personel']))
            ->defaultSort('tarih')
            ->columns([
                SiteAlanlari::siteSutunu(),
                TextColumn::make('tarih')->label('Tarih')->date('d.m.Y')->sortable()
                    ->color(fn (Gorev $record) => $record->durum === 'bekliyor' && $record->tarih->isPast() && ! $record->tarih->isToday() ? 'danger' : null),
                TextColumn::make('baslik')->label('Görev')->searchable()->wrap()->description(fn (Gorev $record) => $record->aciklama),
                TextColumn::make('personel.name')->label('Personel'),
                TextColumn::make('durum')->label('Durum')->badge()
                    ->formatStateUsing(fn ($state) => Gorev::DURUMLAR[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'tamamlandi' => 'success', 'iptal' => 'gray', default => 'warning'
                    }),
                TextColumn::make('tamamlanma_notu')->label('Tamamlanma notu')->placeholder('—')->toggleable()->wrap(),
            ])
            ->filters([
                SiteAlanlari::siteFiltresi(),
                SelectFilter::make('durum')->label('Durum')->options(Gorev::DURUMLAR)->default('bekliyor'),
            ])
            ->recordActions([
                static::tamamla(),
                EditAction::make()->label('Düzenle')
                    ->mutateDataUsing(function (array $data, Gorev $record) {
                        unset($data['site_id']);

                        return static::denetle($data, $record->site_id);
                    }),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Görev yok');
    }

    public static function tamamla(): Action
    {
        return Action::make('tamamla')
            ->label('Tamamla')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Gorev $record) => $record->durum === 'bekliyor')
            ->authorize(fn (Gorev $record) => Gate::allows('tamamla', $record))
            ->modalHeading(fn (Gorev $record) => $record->baslik)
            ->schema([
                Textarea::make('not')->label('Tamamlanma notu')->rows(2)->maxLength(1000),
            ])
            ->action(function (Gorev $record, array $data) {
                $record->tamamla($data['not'] ?? null);
                Notification::make()->success()->title('Görev tamamlandı')->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGorevler::route('/'),
        ];
    }
}
