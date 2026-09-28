<?php

namespace App\Filament\Resources\Siteler\RelationManagers;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\BagimsizBolumler\BagimsizBolumResource;
use App\Models\BagimsizBolum;
use App\Models\Blok;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Sitenin bağımsız bölümleri; toplu oluşturma dahil. */
class BolumlerRelationManager extends RelationManager
{
    protected static string $relationship = 'bolumler';

    protected static ?string $title = 'Bağımsız bölümler';

    protected static ?string $modelLabel = 'bağımsız bölüm';

    public function isReadOnly(): bool
    {
        return ! Gate::allows('update', $this->getOwnerRecord());
    }

    public function form(Schema $schema): Schema
    {
        return BagimsizBolumResource::bolumFormu($schema, fn () => $this->getOwnerRecord()->id, siteSec: false);
    }

    public function table(Table $table): Table
    {
        return BagimsizBolumResource::table($table)
            ->headerActions([
                CreateAction::make()->label('Bölüm ekle'),
                $this->topluOlustur(),
            ])
            ->recordActions([
                ViewAction::make()->label('Aç')->url(fn (BagimsizBolum $record) => BagimsizBolumResource::getUrl('view', ['record' => $record])),
                EditAction::make()->label('Düzenle'),
            ])
            ->filters([]);
    }

    private function topluOlustur(): Action
    {
        return Action::make('topluOlustur')
            ->label('Toplu bölüm oluştur')
            ->icon(Heroicon::OutlinedSquaresPlus)
            ->color('gray')
            ->authorize(fn () => Gate::allows('update', $this->getOwnerRecord()) && Gate::allows('create', BagimsizBolum::class))
            ->modalHeading(fn () => $this->getOwnerRecord()->ad.' — toplu bölüm oluştur')
            ->modalDescription('Numara aralığındaki bölümler aynı türde ve metrekarede açılır; zaten var olan numaralar atlanır. Sonra tek tek düzenleyebilirsiniz.')
            ->schema([
                Select::make('blok_id')->label('Blok')->options(fn () => SiteAlanlari::blokSecenekleri($this->getOwnerRecord()->id))->placeholder('Bloksuz'),
                Select::make('tur')->label('Tür')->options(BagimsizBolum::TURLER)->default('daire')->required(),
                TextInput::make('bas')->label('Başlangıç no')->numeric()->integer()->minValue(1)->required()->default(1),
                TextInput::make('bit')->label('Bitiş no')->numeric()->integer()->minValue(1)->required()->gte('bas')
                    ->rule(fn ($get) => function ($attr, $value, $fail) use ($get) {
                        if ((int) $value - (int) $get('bas') > 499) {
                            $fail('Tek seferde en fazla 500 bölüm oluşturulur.');
                        }
                    }),
                TextInput::make('brut_m2')->label('Brüt m²')->numeric()->minValue(0),
                TextInput::make('bolum_basi_kat')->label('Kat başına bölüm')->numeric()->integer()->minValue(1)
                    ->helperText('Doluysa kat numarası buna göre verilir (ör. 4 → 1–4 zemin: 0).'),
            ])
            ->action(function (array $data) {
                $site = $this->getOwnerRecord();
                $blokId = $data['blok_id'] ? (int) $data['blok_id'] : null;
                SiteAlanlari::ayniSite($site->id, $blokId ? Blok::find($blokId) : null);

                $eklenen = 0;
                $atlanan = 0;
                DB::transaction(function () use ($site, $blokId, $data, &$eklenen, &$atlanan) {
                    for ($no = (int) $data['bas']; $no <= (int) $data['bit']; $no++) {
                        $var = BagimsizBolum::where('site_id', $site->id)->where('blok_id', $blokId)->where('no', (string) $no)->exists();
                        if ($var) {
                            $atlanan++;

                            continue;
                        }
                        BagimsizBolum::create([
                            'site_id' => $site->id, 'blok_id' => $blokId, 'no' => (string) $no, 'tur' => $data['tur'],
                            'brut_m2' => filled($data['brut_m2'] ?? null) ? $data['brut_m2'] : null,
                            'kat' => filled($data['bolum_basi_kat'] ?? null) ? (string) intdiv($no - 1, (int) $data['bolum_basi_kat']) : null,
                        ]);
                        $eklenen++;
                    }
                });

                Notification::make()->success()->title($eklenen.' bölüm oluşturuldu')
                    ->body($atlanan ? $atlanan.' numara zaten vardı, atlandı.' : null)->send();
            });
    }
}
