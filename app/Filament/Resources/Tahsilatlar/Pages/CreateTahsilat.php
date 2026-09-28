<?php

namespace App\Filament\Resources\Tahsilatlar\Pages;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\Tahsilatlar\TahsilatResource;
use App\Models\BagimsizBolum;
use App\Models\Borc;
use App\Models\Hesap;
use App\Services\SiteFinans\TahsilatServisi;
use App\Support\Para;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Tahsilat girişi: kayıt TahsilatServisi::kaydet ile yapılır (Eloquent
 * create değil). Bölüm sayfasındaki "Tahsilat al" ?site=&bolum= ile gelir.
 */
class CreateTahsilat extends CreateRecord
{
    protected static string $resource = TahsilatResource::class;

    protected static bool $canCreateAnother = false;

    public function getSubheading(): ?string
    {
        return 'Önce siteyi seçin; bölüm ve hesap yalnız o sitenin kayıtlarıdır.';
    }

    protected function fillForm(): void
    {
        parent::fillForm();

        $bolumId = (int) request()->query('bolum');
        $bolum = $bolumId ? BagimsizBolum::find($bolumId) : null;
        if ($bolum && in_array($bolum->site_id, array_keys(SiteAlanlari::siteler()), false)) {
            $this->form->fill([
                ...$this->form->getRawState(),
                'site_id' => $bolum->site_id,
                'bagimsiz_bolum_id' => $bolum->id,
                'hesap_id' => array_key_first(SiteAlanlari::hesapSecenekleri($bolum->site_id)),
            ]);
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        $site = SiteAlanlari::erisimDenetle($data['site_id'] ?? null);
        $bolum = BagimsizBolum::findOrFail((int) $data['bagimsiz_bolum_id']);
        $hesap = Hesap::findOrFail((int) $data['hesap_id']);
        SiteAlanlari::ayniSite($site->id, $bolum, $hesap);

        // Seçilen borçlar ve ödeyen de aynı bölüme ait olmalı
        $sira = array_values(array_map('intval', (array) ($data['borc_sirasi'] ?? [])));
        if ($sira !== []) {
            abort_unless(Borc::whereIn('id', $sira)->where('bagimsiz_bolum_id', $bolum->id)->count() === count($sira), 403);
        }
        $kisiId = filled($data['kisi_id'] ?? null) ? (int) $data['kisi_id'] : null;
        if ($kisiId) {
            abort_unless($bolum->iliskiler()->where('kisi_id', $kisiId)->exists(), 403);
        }

        $belge = $data['belge_yolu'] ?? null;
        if (is_array($belge)) {
            $belge = array_values($belge)[0] ?? null;
        }

        try {
            return app(TahsilatServisi::class)->kaydet([
                'hesap_id' => $hesap->id,
                'bagimsiz_bolum_id' => $bolum->id,
                'tarih' => $data['tarih'],
                'tutar_kurus' => (int) $data['tutar_kurus'],
                'yontem' => $data['yontem'],
                'kisi_id' => $kisiId,
                'aciklama' => $data['aciklama'] ?? null,
                'belge_yolu' => $belge,
                // Dekont varsa ancak "doğrulandı" işaretliyse işlenir; dekontsuz (nakit, kasada görülen) kayıt doğrudur
                'dogrulandi' => filled($belge) ? (bool) ($data['dogrulandi'] ?? false) : true,
                'gerekce' => $data['gerekce'] ?? null,
                'borc_sirasi' => $sira ?: null,
            ]);
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Tahsilat kaydedilemedi')->body($e->getMessage())->persistent()->send();

            throw new Halt;
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        $t = $this->getRecord();

        return Para::bicimle($t->tutar_kurus, $t->para_birimi).' tahsilat kaydedildi'.($t->dogrulandi ? '' : ' (doğrulama bekliyor)');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
