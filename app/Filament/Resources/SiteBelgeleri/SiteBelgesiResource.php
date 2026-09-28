<?php

namespace App\Filament\Resources\SiteBelgeleri;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\SiteBelgeleri\Pages\ManageSiteBelgeleri;
use App\Models\SiteBelgesi;
use App\Support\Kapsam;
use App\Support\Yetki;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
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
 * Site belgeleri (yönetim planı, karar, tutanak, sözleşme, bakım belgesi —
 * şartname 8.6). Özel diskte; eski sürümler silinmez. Listede yalnız son
 * sürüm görünür, "Sürümler" tüm geçmişi açar. İndirme yetki denetimli
 * rotadan ve işlem kaydıyla. İç belgeler herkese açık siteye çıkmaz.
 */
class SiteBelgesiResource extends Resource
{
    protected static ?string $model = SiteBelgesi::class;

    protected static ?string $slug = 'site-belgeleri';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static ?string $navigationLabel = 'Site Belgeleri';

    protected static ?string $modelLabel = 'belge';

    protected static ?string $pluralModelLabel = 'site belgeleri';

    protected static string|UnitEnum|null $navigationGroup = 'Site Operasyonu';

    protected static ?int $navigationSort = 6;

    /** SiteBelgesi::gorebilir() kuralının sorgu karşılığı. */
    public static function erisimSuzgeci(Builder $sorgu): Builder
    {
        $izinli = ['herkes'];
        if (Yetki::var('site_belge.goruntule')) {
            $izinli[] = 'yonetim';
        }
        if (Yetki::var('finans.hesap')) {
            $izinli[] = 'muhasebe';
        }

        return $sorgu->whereIn($sorgu->qualifyColumn('erisim'), $izinli);
    }

    public static function getEloquentQuery(): Builder
    {
        $sorgu = Kapsam::siteyeBagli(parent::getEloquentQuery())
            ->whereRaw('site_belgeleri.surum = (SELECT MAX(b2.surum) FROM site_belgeleri b2 WHERE b2.grup = site_belgeleri.grup)');

        return static::erisimSuzgeci($sorgu);
    }

    public static function dosyaAlani(string $siteDizini): FileUpload
    {
        return FileUpload::make('yol')
            ->label('Dosya')
            ->disk(SiteBelgesi::DISK)
            ->visibility('private')
            ->directory($siteDizini)
            ->storeFileNamesIn('orijinal_ad')
            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp',
                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->maxSize(20480)
            ->required()
            ->helperText('PDF, görsel, Word veya Excel; en fazla 20 MB. Dosya herkese açık alana konmaz.')
            ->columnSpanFull();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            SiteAlanlari::site('site_id'),
            Select::make('kategori')->label('Kategori')->options(SiteBelgesi::KATEGORILER)->required(),
            TextInput::make('ad')->label('Belge adı')->required()->maxLength(255),
            Select::make('erisim')->label('Erişim')->options(SiteBelgesi::ERISIMLER)->default('yonetim')->required()->selectablePlaceholder(false),
            DatePicker::make('belge_tarihi')->label('Belge tarihi')->native(false)->displayFormat('d.m.Y')->default(fn () => now()->toDateString()),
            static::dosyaAlani('site-belgeleri/gecici')
                ->directory(fn ($get) => 'site-belgeleri/'.((int) $get('site_id'))),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['site', 'yukleyen']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                SiteAlanlari::siteSutunu(),
                TextColumn::make('ad')->label('Belge')->searchable()->description(fn (SiteBelgesi $record) => $record->orijinal_ad),
                TextColumn::make('kategori')->label('Kategori')->formatStateUsing(fn ($state) => SiteBelgesi::KATEGORILER[$state] ?? $state),
                TextColumn::make('surum')->label('Sürüm')->formatStateUsing(fn ($state) => 'v'.$state),
                TextColumn::make('erisim')->label('Erişim')->badge()->color('gray')->formatStateUsing(fn ($state) => SiteBelgesi::ERISIMLER[$state] ?? $state),
                TextColumn::make('belge_tarihi')->label('Belge tarihi')->date('d.m.Y')->placeholder('—'),
                TextColumn::make('yukleyen.name')->label('Yükleyen')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SiteAlanlari::siteFiltresi(),
                SelectFilter::make('kategori')->label('Kategori')->options(SiteBelgesi::KATEGORILER),
            ])
            ->recordActions([
                Action::make('indir')
                    ->label('İndir')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (SiteBelgesi $record) => Gate::allows('view', $record))
                    ->url(fn (SiteBelgesi $record) => route('panel.site-belgesi.indir', $record), shouldOpenInNewTab: true),
                Action::make('surumler')
                    ->label('Sürümler')
                    ->icon(Heroicon::OutlinedClock)
                    ->color('gray')
                    ->modalHeading(fn (SiteBelgesi $record) => $record->ad.' — sürüm geçmişi')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Kapat')
                    ->modalContent(fn (SiteBelgesi $record) => view('filament.site.belge-surumleri', [
                        'surumler' => SiteBelgesi::where('grup', $record->grup)->where('site_id', $record->site_id)->with('yukleyen')->orderByDesc('surum')->get(),
                    ])),
                Action::make('yeniSurum')
                    ->label('Yeni sürüm yükle')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->authorize(fn (SiteBelgesi $record) => Gate::allows('update', $record))
                    ->modalHeading(fn (SiteBelgesi $record) => $record->site?->ad.' — '.$record->ad.' (yeni sürüm)')
                    ->modalDescription('Önceki sürüm silinmez; geçmişte kalır.')
                    ->schema(fn (SiteBelgesi $record) => [static::dosyaAlani('site-belgeleri/'.$record->site_id)])
                    ->action(function (SiteBelgesi $record, array $data) {
                        SiteAlanlari::erisimDenetle($record->site_id);
                        $yeni = $record->yeniSurum($data['yol'], $data['orijinal_ad'] ?? null);
                        Notification::make()->success()->title('Sürüm '.$yeni->surum.' yüklendi')->send();
                    }),
            ])
            ->emptyStateHeading('Belge yok');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSiteBelgeleri::route('/'),
        ];
    }
}
