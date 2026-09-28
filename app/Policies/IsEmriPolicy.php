<?php

namespace App\Policies;

use App\Models\IsEmri;
use App\Models\User;
use App\Support\Kapsam;
use App\Support\Yetki;

/**
 * İş emri. Yönetim (operasyon.yonet) atanmış sitelerde her şeyi yapar;
 * saha personeli yalnız KENDİSİNE atanmış iş emrini görür ve tamamlar,
 * kapatamaz / yeniden açamaz (şartname 8.5, 9).
 */
class IsEmriPolicy
{
    public function viewAny(User $k): bool
    {
        return Yetki::var('operasyon.yonet', $k) || Yetki::var('operasyon.saha', $k);
    }

    public function view(User $k, IsEmri $e): bool
    {
        if (! Kapsam::siteErisimi($e->site_id, $k)) {
            return false;
        }

        return Yetki::var('operasyon.yonet', $k) || (Yetki::var('operasyon.saha', $k) && $e->atanan_id === $k->id);
    }

    public function create(User $k): bool
    {
        return Yetki::var('operasyon.yonet', $k);
    }

    public function update(User $k, IsEmri $e): bool
    {
        return Yetki::var('operasyon.yonet', $k) && Kapsam::siteErisimi($e->site_id, $k)
            && $e->durum !== 'kapatildi';
    }

    public function tamamla(User $k, IsEmri $e): bool
    {
        return $this->view($k, $e) && ! in_array($e->durum, ['tamamlandi', 'kapatildi'], true)
            && (Yetki::var('operasyon.yonet', $k) || $e->atanan_id === $k->id);
    }

    public function kapat(User $k, IsEmri $e): bool
    {
        return Yetki::var('operasyon.yonet', $k) && Kapsam::siteErisimi($e->site_id, $k);
    }

    public function delete(User $k, IsEmri $e): bool
    {
        return Yetki::var('operasyon.yonet', $k) && Kapsam::siteErisimi($e->site_id, $k) && $e->durum === 'acik';
    }

    public function deleteAny(User $k): bool
    {
        return false;
    }
}
