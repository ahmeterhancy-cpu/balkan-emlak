<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Kapsam;
use App\Support\Yetki;
use Illuminate\Database\Eloquent\Model;

/**
 * Siteye bağlı kayıtların ortak politikası: rol izni VE kaydın sitesine
 * erişim (site_kullanici ataması ya da "site.goruntule_tumu"). Başka
 * sitenin kaydına doğrudan adresle gelen istek de reddedilir (şartname 9, 17).
 *
 * Alt sınıflar izin adlarını ve mali kayıtlarda düzenle/sil kapatmayı belirler.
 */
abstract class SiteKaydiPolicy
{
    /** Görüntüleme için bunlardan biri yeter. @var list<string> */
    protected array $gorIzinleri = ['finans.goruntule'];

    /** Oluşturma / düzenleme izni. */
    protected string $yazIzni = 'site.yonet';

    /** Mali kayıt: düzenleme ve silme hiç yok (karşı kayıt servisleri kullanılır). */
    protected bool $degismez = false;

    protected function siteId(Model $kayit): int
    {
        return (int) $kayit->site_id;
    }

    protected function gorebilir(User $k): bool
    {
        foreach ($this->gorIzinleri as $izin) {
            if (Yetki::var($izin, $k)) {
                return true;
            }
        }

        return Yetki::var($this->yazIzni, $k);
    }

    public function viewAny(User $k): bool
    {
        return $this->gorebilir($k);
    }

    public function view(User $k, Model $kayit): bool
    {
        return $this->gorebilir($k) && Kapsam::siteErisimi($this->siteId($kayit), $k);
    }

    public function create(User $k): bool
    {
        return Yetki::var($this->yazIzni, $k);
    }

    public function update(User $k, Model $kayit): bool
    {
        return ! $this->degismez && Yetki::var($this->yazIzni, $k) && Kapsam::siteErisimi($this->siteId($kayit), $k);
    }

    public function delete(User $k, Model $kayit): bool
    {
        return $this->update($k, $kayit);
    }

    public function deleteAny(User $k): bool
    {
        return false;
    }
}
