<?php

namespace App\Filament\Resources\Siteler\RelationManagers;

use App\Enums\Rol;
use App\Models\User;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Siteye atanmış kullanıcılar (site_kullanici). Site yöneticisi, muhasebe
 * ve saha personeli yalnız atandığı sitelerin verisini görür. Atamayı
 * yalnız tüm siteleri yöneten kullanıcı değiştirir (SitePolicy::yetkiDuzenle).
 */
class KullanicilarRelationManager extends RelationManager
{
    protected static string $relationship = 'kullanicilar';

    protected static ?string $title = 'Yetkili kullanıcılar';

    protected static ?string $modelLabel = 'kullanıcı';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return ! Gate::allows('yetkiDuzenle', $this->getOwnerRecord());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Ad'),
                TextColumn::make('email')->label('E-posta'),
                TextColumn::make('rol')->label('Rol')->badge(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Kullanıcı ata')
                    ->authorize(fn () => Gate::allows('yetkiDuzenle', $this->getOwnerRecord()))
                    ->recordSelectOptionsQuery(fn ($query) => $query->where('aktif', true)
                        ->whereIn('rol', [Rol::SiteYoneticisi->value, Rol::MuhasebeSorumlusu->value, Rol::SahaPersoneli->value]))
                    ->recordTitle(fn (User $record) => $record->name.' · '.$record->rol?->getLabel())
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label('Yetkiyi kaldır')
                    ->authorize(fn () => Gate::allows('yetkiDuzenle', $this->getOwnerRecord())),
            ])
            ->emptyStateHeading('Atanmış kullanıcı yok')
            ->emptyStateDescription('Site yöneticisi, muhasebe ve saha personeli bu listeye eklenince siteyi görür.');
    }
}
