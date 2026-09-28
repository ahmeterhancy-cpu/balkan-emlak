<?php

namespace App\Filament\Resources\Portfoyler\RelationManagers;

use App\Models\PortfoyBelgesi;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Özel belgeler (yetki belgesi, tapu, sözleşme). Dosya `local` diskine
 * (storage/app/private) yazılır; web kökünde değildir. İndirme yalnız
 * `panel.belge.indir` rotasından, politika denetimi ve işlem kaydıyla.
 */
class BelgelerRelationManager extends RelationManager
{
    protected static string $relationship = 'belgeler';

    protected static ?string $title = 'Özel belgeler';

    protected static ?string $modelLabel = 'belge';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('viewAny', PortfoyBelgesi::class) && Gate::allows('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return parent::isReadOnly() || ! Gate::allows('update', $this->getOwnerRecord());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('ad')->label('Belge adı')->required()->maxLength(255)->placeholder('Satış yetki belgesi 2026'),
            Select::make('tur')->label('Tür')->options(PortfoyBelgesi::TURLER)->required(),
            DatePicker::make('gecerlilik_bitis')->label('Geçerlilik bitişi')->native(false)->displayFormat('d.m.Y'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('ad')->label('Belge')->searchable()->description(fn (PortfoyBelgesi $record) => $record->orijinal_ad),
                TextColumn::make('tur')->label('Tür')->badge()->formatStateUsing(fn (?string $state) => PortfoyBelgesi::TURLER[$state] ?? $state),
                TextColumn::make('gecerlilik_bitis')
                    ->label('Geçerlilik')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->color(fn (PortfoyBelgesi $record) => $record->gecerlilik_bitis?->isPast() ? 'danger' : null),
                TextColumn::make('yukleyen.name')->label('Yükleyen')->placeholder('—'),
                TextColumn::make('created_at')->label('Yüklenme')->dateTime('d.m.Y H:i'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Belge yükle')
                    ->modalHeading('Özel belge yükle')
                    ->schema(fn (Schema $schema) => $this->form($schema)->components([
                        FileUpload::make('yol')
                            ->label('Dosya')
                            ->disk(PortfoyBelgesi::DISK)
                            ->visibility('private')
                            ->directory('belgeler/portfoy-'.$this->getOwnerRecord()->id)
                            ->storeFileNamesIn('orijinal_ad')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(20480)
                            ->required()
                            ->helperText('PDF veya görsel, en fazla 20 MB. Dosya herkese açık alana konmaz.')
                            ->columnSpanFull(),
                        TextInput::make('ad')->label('Belge adı')->required()->maxLength(255),
                        Select::make('tur')->label('Tür')->options(PortfoyBelgesi::TURLER)->required(),
                        DatePicker::make('gecerlilik_bitis')->label('Geçerlilik bitişi')->native(false)->displayFormat('d.m.Y'),
                    ])),
            ])
            ->recordActions([
                Action::make('indir')
                    ->label('İndir')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (PortfoyBelgesi $record) => Gate::allows('view', $record))
                    ->url(fn (PortfoyBelgesi $record) => route('panel.belge.indir', $record), shouldOpenInNewTab: true),
                EditAction::make()->label('Düzenle'),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Belge yok')
            ->emptyStateDescription('Yetki belgesi, tapu ve sözleşmeler burada saklanır; yalnız yetkili kullanıcılar indirebilir.');
    }
}
