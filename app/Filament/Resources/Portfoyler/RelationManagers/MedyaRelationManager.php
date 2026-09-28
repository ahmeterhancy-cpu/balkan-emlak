<?php

namespace App\Filament\Resources\Portfoyler\RelationManagers;

use App\Models\PortfoyMedya;
use App\Support\GorselHakki;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * İlan fotoğrafları ve kat planları. Dosyalar `medya` diskine (public/medya)
 * gider; kart için 800 px kopya model olayıyla üretilir.
 */
class MedyaRelationManager extends RelationManager
{
    protected static string $relationship = 'medya';

    protected static ?string $title = 'Fotoğraflar ve planlar';

    protected static ?string $modelLabel = 'görsel';

    public const TURLER = ['foto' => 'Fotoğraf', 'plan' => 'Kat planı'];

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return parent::isReadOnly() || ! Gate::allows('update', $this->getOwnerRecord());
    }

    public static function yukleme(string $ad = 'dosyalar'): FileUpload
    {
        return FileUpload::make($ad)
            ->label('Görseller')
            ->disk('medya')
            ->visibility('public')
            ->directory('portfoyler/'.date('Y/m'))
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(8192)
            ->multiple()
            ->reorderable()
            ->maxFiles(40)
            ->required()
            ->helperText('JPEG, PNG veya WebP; dosya başına en fazla 8 MB. Sıra sonradan sürükleyerek değiştirilebilir.');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tur')->label('Tür')->options(self::TURLER)->required(),
            TextInput::make('alt_metin')
                ->label('Alternatif metin')
                ->maxLength(255)
                ->helperText('Görme engelli ziyaretçiler için kısa açıklama: "Salon, güney cephe".'),
            ...GorselHakki::alanlar(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // İlişkinin kendi sıralaması (kapak önce) sürükle-bırak sırasını bozmasın
            ->modifyQueryUsing(fn ($query) => $query->reorder())
            ->reorderable('sira')
            ->defaultSort('sira')
            ->columns([
                ImageColumn::make('onizleme')
                    ->label('Görsel')
                    ->state(fn (PortfoyMedya $record): string => $record->kucukUrl())
                    ->imageHeight(72),
                TextColumn::make('tur')
                    ->label('Tür')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => self::TURLER[$state] ?? $state),
                IconColumn::make('kapak')->label('Kapak')->boolean(),
                TextColumn::make('alt_metin')->label('Alternatif metin')->placeholder('— eklenmedi')->limit(50),
                TextColumn::make('gorsel_hakki')
                    ->label('Kullanım hakkı')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => GorselHakki::etiket($state))
                    ->color(fn (?string $state) => match ($state) {
                        'kendi', 'mulk_sahibi' => 'success',
                        'lisansli' => 'info',
                        default => 'warning',
                    })
                    ->placeholder('— girilmedi')
                    ->tooltip(fn (PortfoyMedya $record) => $record->gorsel_kaynak),
            ])
            ->headerActions([
                Action::make('yukle')
                    ->label('Görsel yükle')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->visible(fn (): bool => ! $this->isReadOnly())
                    ->authorize(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
                    ->modalHeading('Görsel yükle')
                    ->schema([
                        static::yukleme(),
                        Select::make('tur')->label('Tür')->options(self::TURLER)->default('foto')->required(),
                        ...GorselHakki::alanlar(), // yüklenen tüm görsellere uygulanır
                    ])
                    ->action(function (array $data): void {
                        $portfoy = $this->getOwnerRecord();
                        $sira = (int) $portfoy->medya()->max('sira');
                        $kapakVar = $portfoy->medya()->where('kapak', true)->exists();

                        foreach (array_values((array) $data['dosyalar']) as $i => $yol) {
                            PortfoyMedya::create([
                                'portfoy_id' => $portfoy->id,
                                'yol' => $yol,
                                'tur' => $data['tur'] ?? 'foto',
                                'sira' => ++$sira,
                                'kapak' => ! $kapakVar && $i === 0 && ($data['tur'] ?? 'foto') === 'foto',
                                'gorsel_hakki' => $data['gorsel_hakki'] ?? null,
                                'gorsel_kaynak' => $data['gorsel_kaynak'] ?? null,
                            ]);
                        }

                        Notification::make()->success()->title(count((array) $data['dosyalar']).' görsel eklendi')->send();
                    }),
            ])
            ->recordActions([
                Action::make('kapakYap')
                    ->label('Kapak yap')
                    ->icon(Heroicon::OutlinedStar)
                    ->color('gray')
                    ->visible(fn (PortfoyMedya $record): bool => ! $this->isReadOnly() && ! $record->kapak && $record->tur === 'foto')
                    ->authorize(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
                    ->action(function (PortfoyMedya $record): void {
                        $record->update(['kapak' => true]);
                        Notification::make()->success()->title('Kapak görseli değişti')->send();
                    }),
                EditAction::make()->label('Düzenle'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->emptyStateHeading('Henüz görsel yok')
            ->emptyStateDescription('Yayın için en az bir fotoğraf gerekir. İlk yüklenen fotoğraf kapak olur.');
    }
}
