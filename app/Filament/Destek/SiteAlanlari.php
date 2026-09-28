<?php

namespace App\Filament\Destek;

use App\Models\BagimsizBolum;
use App\Models\Blok;
use App\Models\Hesap;
use App\Models\Site;
use App\Models\User;
use App\Support\Donem;
use App\Support\Kapsam;
use App\Support\Para;
use App\Support\Yetki;
use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Site ve apartman yönetimi ekranlarının ortak parçaları (şartname 6, 9).
 *
 *  - Site seçici: her listede "Site" süzgeci (tek siteli kullanıcıda o site
 *    varsayılan), her formda önce site seçilir; bölüm / hesap / blok
 *    seçenekleri yalnız o sitenin ve kullanıcının erişebildiği kayıtlardır.
 *  - Sunucu tarafı denetim: `erisimDenetle()` formdan gelen site kimliğini
 *    Kapsam ile yeniden denetler; seçenek listesini daraltmak tek başına
 *    yetki denetimi sayılmaz.
 *  - `calistir()`: servislerin Türkçe DomainException mesajlarını kırmızı
 *    bildirim olarak gösterir (500 yerine).
 */
class SiteAlanlari
{
    /** Kullanıcının erişebildiği siteler: id => ad. @return array<int, string> */
    public static function siteler(): array
    {
        return Kapsam::siteler(Site::query())->orderBy('ad')->pluck('ad', 'id')->all();
    }

    /** Kullanıcı tek siteye erişiyorsa o sitenin kimliği. */
    public static function tekSiteId(): ?int
    {
        $s = static::siteler();

        return count($s) === 1 ? (int) array_key_first($s) : null;
    }

    public static function siteAdi(?int $siteId): string
    {
        return $siteId ? (static::siteler()[$siteId] ?? (string) Site::whereKey($siteId)->value('ad')) : '—';
    }

    /**
     * Formun başındaki site seçimi. Değişince bağımlı alanlar boşalır.
     *
     * @param  list<string>  $sifirlanacak
     */
    public static function site(string $ad = 'site_id', array $sifirlanacak = []): Select
    {
        return Select::make($ad)
            ->label('Site / apartman')
            ->options(fn (): array => static::siteler())
            ->default(fn (): ?int => static::tekSiteId())
            ->required()
            ->searchable()
            ->live()
            ->afterStateUpdated(function ($set) use ($sifirlanacak) {
                foreach ($sifirlanacak as $alan) {
                    $set($alan, null);
                }
            });
    }

    /** @return array<int, string> */
    public static function bolumSecenekleri(mixed $siteId, bool $yalnizAktif = false): array
    {
        if (! filled($siteId) || ! Kapsam::siteErisimi((int) $siteId)) {
            return [];
        }

        return BagimsizBolum::query()
            ->where('site_id', (int) $siteId)
            ->when($yalnizAktif, fn ($q) => $q->where('aktif', true))
            ->with('blok')
            ->orderBy('blok_id')->orderByRaw('LENGTH(no)')->orderBy('no')
            ->get()
            ->mapWithKeys(fn (BagimsizBolum $b) => [$b->id => $b->etiket()])
            ->all();
    }

    /** @return array<int, string> */
    public static function hesapSecenekleri(mixed $siteId, ?string $paraBirimi = null, bool $yalnizAktif = true): array
    {
        if (! filled($siteId) || ! Kapsam::siteErisimi((int) $siteId)) {
            return [];
        }

        return Hesap::query()
            ->where('site_id', (int) $siteId)
            ->when($yalnizAktif, fn ($q) => $q->where('aktif', true))
            ->when($paraBirimi, fn ($q) => $q->where('para_birimi', $paraBirimi))
            ->orderBy('ad')
            ->get()
            ->mapWithKeys(fn (Hesap $h) => [$h->id => $h->ad.' ('.(Hesap::TURLER[$h->tur] ?? $h->tur).', '.$h->para_birimi.')'])
            ->all();
    }

    /** @return array<int, string> */
    public static function blokSecenekleri(mixed $siteId): array
    {
        if (! filled($siteId) || ! Kapsam::siteErisimi((int) $siteId)) {
            return [];
        }

        return Blok::where('site_id', (int) $siteId)->orderBy('sira')->orderBy('ad')->pluck('ad', 'id')->all();
    }

    /** Siteye atanmış ve operasyon izni olan personel (iş emri / görev ataması). @return array<int, string> */
    public static function personelSecenekleri(mixed $siteId): array
    {
        if (! filled($siteId) || ! Kapsam::siteErisimi((int) $siteId)) {
            return [];
        }

        $atanmis = User::query()->where('aktif', true)
            ->whereHas('siteler', fn ($q) => $q->whereKey((int) $siteId))
            ->orderBy('name')->get();

        return $atanmis
            ->filter(fn (User $k) => Yetki::var('operasyon.saha', $k) || Yetki::var('operasyon.yonet', $k))
            ->mapWithKeys(fn (User $k) => [$k->id => $k->name])
            ->all();
    }

    /** Liste süzgeci: "Site". Tek siteli kullanıcıda varsayılan o site. */
    public static function siteFiltresi(string $sutun = 'site_id'): SelectFilter
    {
        return SelectFilter::make($sutun)
            ->label('Site')
            ->options(fn (): array => static::siteler())
            ->default(static::tekSiteId())
            ->searchable();
    }

    public static function siteSutunu(string $iliski = 'site.ad'): TextColumn
    {
        return TextColumn::make($iliski)
            ->label('Site')
            ->badge()
            ->color('gray')
            ->sortable()
            ->toggleable();
    }

    /** Para sütunu (kuruş → biçimli, para birimi ayrı sütundan). */
    public static function paraSutunu(string $ad, string $etiket, ?Closure $durum = null, string $pbSutunu = 'para_birimi'): TextColumn
    {
        return TextColumn::make($ad)
            ->label($etiket)
            ->state($durum ?? fn ($record) => $record->{$ad})
            ->formatStateUsing(fn ($state, $record) => Para::bicimle($state === null ? null : (int) $state, (string) ($record->{$pbSutunu} ?? 'TRY')))
            ->alignEnd();
    }

    /**
     * Formdan gelen site kimliğini sunucu tarafında yeniden denetle.
     */
    public static function erisimDenetle(mixed $siteId): Site
    {
        abort_unless(filled($siteId) && Kapsam::siteErisimi((int) $siteId), 403, 'Bu siteye erişiminiz yok.');

        return Site::findOrFail((int) $siteId);
    }

    /** Kayıtların hepsi aynı (erişilebilir) siteye mi ait? Değilse 403. */
    public static function ayniSite(int $siteId, ?object ...$kayitlar): void
    {
        foreach ($kayitlar as $k) {
            if ($k !== null) {
                abort_unless((int) $k->site_id === $siteId, 403, 'Seçilen kayıt başka bir siteye ait.');
            }
        }
    }

    /**
     * Kapalı döneme yazarken istenen gerekçe. Yalnız sitenin kapalı dönemi
     * varsa ve kullanıcı `donem.duzelt` iznine sahipse görünür.
     *
     * @param  Closure  $siteId  fn (...) => ?int — Filament enjeksiyonuyla değerlendirilir
     */
    public static function donemGerekcesi(Closure $siteId, string $ad = 'donem_gerekcesi'): Textarea
    {
        $site = function (Textarea $component) use ($siteId): ?Site {
            $id = $component->evaluate($siteId);

            return $id ? Site::find((int) $id) : null;
        };

        return Textarea::make($ad)
            ->label('Kapalı dönem gerekçesi')
            ->rows(2)
            ->maxLength(255)
            ->visible(fn (Textarea $component) => Yetki::var('donem.duzelt') && $site($component)?->kapali_donem !== null)
            ->helperText(fn (Textarea $component) => ($s = $site($component)) && $s->kapali_donem
                ? Donem::ad($s->kapali_donem).' dahil önceki dönemler kapalı. Kapalı döneme kayıt yalnız gerekçeyle yapılır; açık döneme kayıtta boş bırakın.'
                : null);
    }

    /**
     * Servis çağrısı: başarıda yeşil, DomainException'da kırmızı bildirim.
     * Hata olursa eylem penceresi açık kalır (halt).
     */
    public static function calistir(Closure $is, string $basari, ?Action $eylem = null, ?string $govde = null): mixed
    {
        try {
            $sonuc = $is();
        } catch (DomainException $e) {
            Notification::make()->danger()->title('İşlem yapılamadı')->body($e->getMessage())->persistent()->send();

            $eylem?->halt();

            return null;
        }

        Notification::make()->success()->title($basari)->body($govde)->send();

        return $sonuc ?? true;
    }

    /** Sorgu: tek site süzgeci için yardımcı (raporlar). */
    public static function kapsamli(Builder $sorgu): Builder
    {
        return Kapsam::siteyeBagli($sorgu);
    }
}
