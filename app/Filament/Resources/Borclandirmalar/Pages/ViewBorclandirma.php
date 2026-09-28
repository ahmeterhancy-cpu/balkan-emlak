<?php

namespace App\Filament\Resources\Borclandirmalar\Pages;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\Borclandirmalar\BorclandirmaResource;
use App\Models\Borclandirma;
use App\Services\SiteFinans\Borclandirici;
use App\Support\Donem;
use App\Support\Para;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ViewBorclandirma extends ViewRecord
{
    protected static string $resource = BorclandirmaResource::class;

    public function getTitle(): string
    {
        /** @var Borclandirma $b */
        $b = $this->getRecord();

        return $b->aciklama.' — '.$b->site?->ad;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->kesinlestir(),
            EditAction::make()->label('Düzenle'),
            $this->iptalEt(),
        ];
    }

    private function kesinlestir(): Action
    {
        return Action::make('kesinlestir')
            ->label('Kesinleştir')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (Borclandirma $record) => $record->durum === 'taslak')
            ->authorize(fn (Borclandirma $record) => Gate::allows('update', $record))
            ->requiresConfirmation()
            ->modalHeading(fn (Borclandirma $record) => $record->site?->ad.' — borçlandırmayı kesinleştir')
            ->modalDescription(function (Borclandirma $record) {
                $o = BorclandirmaResource::onizleme($record);
                if ($o['hata']) {
                    return 'Önizleme hatalı: '.$o['hata'];
                }

                return Donem::ad($record->donem).' için '.$o['adet'].' bölüme toplam '.Para::bicimle($o['toplam'], $o['pb'])
                    .' borç açılacak.'.($o['uyari'] ? ' '.$o['uyari'].' bölümde borç sorumlusu yok.' : '')
                    .' Kesinleşen borç silinmez; değişiklik yalnız gerekçeli düzeltme kaydıyla yapılır.';
            })
            ->modalSubmitActionLabel('Kesinleştir')
            ->schema(fn (Borclandirma $record) => [
                SiteAlanlari::donemGerekcesi(fn () => $record->site_id),
            ])
            ->action(function (Borclandirma $record, array $data, Action $action) {
                SiteAlanlari::erisimDenetle($record->site_id);

                $once = $record->borclar()->count();
                $sonuc = SiteAlanlari::calistir(
                    fn () => app(Borclandirici::class)->kesinlestir($record, $data['donem_gerekcesi'] ?? null),
                    'Borçlandırma kesinleşti',
                    $action,
                );

                if ($sonuc) {
                    $record->refresh();
                    Notification::make()->info()->title(($record->borclar()->count() - $once).' borç açıldı')->send();
                    $this->redirect(BorclandirmaResource::getUrl('view', ['record' => $record]));
                }
            });
    }

    private function iptalEt(): Action
    {
        return Action::make('iptal')
            ->label('İptal et')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Borclandirma $record) => $record->durum === 'taslak')
            ->authorize(fn (Borclandirma $record) => Gate::allows('update', $record))
            ->requiresConfirmation()
            ->modalDescription('Taslak iptal edilir; hiçbir borç açılmaz. Kayıt silinmez.')
            ->action(function (Borclandirma $record) {
                $record->forceFill(['durum' => 'iptal'])->save();
                Notification::make()->success()->title('Borçlandırma iptal edildi')->send();
            });
    }
}
