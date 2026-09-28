<?php

namespace App\Filament\Resources\Kullanicilar;

use App\Enums\Rol;
use App\Filament\Resources\Kullanicilar\Pages\CreateUser;
use App\Filament\Resources\Kullanicilar\Pages\EditUser;
use App\Filament\Resources\Kullanicilar\Pages\ListUsers;
use App\Models\User;
use App\Support\Yetki;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Panel kullanıcıları ve rolleri. Aynı kayıt `ekipte_goster` açıksa
 * sitede ekip kartı olur (yalnız ad, görev, telefon, foto, biyografi).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'kullanicilar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Kullanıcılar ve Yetkiler';

    protected static ?string $modelLabel = 'kullanıcı';

    protected static ?string $pluralModelLabel = 'kullanıcılar';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function rol(Get $get): ?Rol
    {
        $r = $get('rol');

        return $r instanceof Rol ? $r : Rol::tryFrom((string) $r);
    }

    /** Kullanıcı kendi rolünü / aktifliğini değiştirip kendini kilitleyemesin. */
    public static function kendisiMi(?User $record): bool
    {
        return $record !== null && $record->is(auth()->user());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Hesap')->columnSpan(2)->columns(2)->schema([
                TextInput::make('name')->label('Ad soyad')->required()->maxLength(255),
                TextInput::make('email')->label('E-posta')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                Select::make('rol')
                    ->label('Rol')
                    ->options(Rol::secenekler())
                    ->required()
                    ->live()
                    ->disabled(fn (?User $record) => static::kendisiMi($record))
                    ->helperText(fn (?User $record) => static::kendisiMi($record) ? 'Kendi rolünüzü değiştiremezsiniz.' : null),
                Toggle::make('aktif')
                    ->label('Aktif')
                    ->default(true)
                    ->inline(false)
                    ->disabled(fn (?User $record) => static::kendisiMi($record))
                    ->helperText('Pasif kullanıcı panele giremez.'),
                TextInput::make('password')
                    ->label(fn (?User $record) => $record ? 'Yeni parola' : 'Parola')
                    ->password()
                    ->revealable()
                    ->minLength(10)
                    ->maxLength(255)
                    ->required(fn (?User $record) => $record === null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->helperText(fn (?User $record) => $record ? 'Boş bırakılırsa mevcut parola değişmez.' : 'En az 10 karakter.')
                    ->autocomplete('new-password'),
            ]),
            Section::make('Rolün izinleri')->columnSpan(1)->schema([
                TextEntry::make('rol_izinleri')
                    ->hiddenLabel()
                    ->state(fn (Get $get): array => ($rol = static::rol($get))
                        ? array_map(fn (string $izin) => Yetki::IZINLER[$izin] ?? $izin, Yetki::rolIzinleri($rol))
                        : ['Önce rol seçin.'])
                    ->listWithLineBreaks()
                    ->bulleted(),
            ]),
            Section::make('Sitedeki ekip kartı')
                ->description('Yalnız "Ekipte göster" açıksa sitede görünür; e-posta hiçbir zaman gösterilmez.')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextInput::make('gorev')->label('Görev / unvan')->maxLength(255),
                    TextInput::make('telefon')->label('Telefon')->tel()->maxLength(30),
                    TextInput::make('whatsapp')->label('WhatsApp')->tel()->maxLength(30),
                    FileUpload::make('foto')
                        ->label('Fotoğraf')
                        ->disk('medya')
                        ->visibility('public')
                        ->directory('ekip')
                        ->image()
                        ->avatar()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(4096),
                    Textarea::make('biyografi')->label('Kısa biyografi')->rows(4)->maxLength(1000)->columnSpan(2),
                    Toggle::make('ekipte_goster')->label('Ekipte göster')->inline(false),
                    TextInput::make('ekip_sirasi')->label('Ekip sırası')->numeric()->default(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Ad soyad')->searchable()->sortable()->description(fn (User $record) => $record->gorev),
                TextColumn::make('email')->label('E-posta')->searchable(),
                TextColumn::make('rol')->label('Rol')->badge(),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
                IconColumn::make('ekipte_goster')->label('Ekipte')->boolean()->toggleable(),
                TextColumn::make('created_at')->label('Oluşturulma')->date('d.m.Y')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('rol')->label('Rol')->options(Rol::secenekler()),
                TernaryFilter::make('aktif')->label('Aktif'),
            ])
            ->recordActions([EditAction::make()->label('Düzenle')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
