<?php

namespace App\Policies;

use App\Models\Gorev;
use App\Models\User;
use App\Support\Kapsam;
use App\Support\Yetki;

/** Personel görevi. Saha personeli yalnız kendi görevini görür ve tamamlar. */
class GorevPolicy
{
    public function viewAny(User $k): bool
    {
        return Yetki::var('operasyon.yonet', $k) || Yetki::var('operasyon.saha', $k);
    }

    public function view(User $k, Gorev $g): bool
    {
        return Kapsam::siteErisimi($g->site_id, $k)
            && (Yetki::var('operasyon.yonet', $k) || $g->user_id === $k->id);
    }

    public function create(User $k): bool
    {
        return Yetki::var('operasyon.yonet', $k);
    }

    public function update(User $k, Gorev $g): bool
    {
        return Yetki::var('operasyon.yonet', $k) && Kapsam::siteErisimi($g->site_id, $k);
    }

    public function tamamla(User $k, Gorev $g): bool
    {
        return $this->view($k, $g) && $g->durum === 'bekliyor';
    }

    public function delete(User $k, Gorev $g): bool
    {
        return $this->update($k, $g) && $g->durum === 'bekliyor';
    }

    public function deleteAny(User $k): bool
    {
        return false;
    }
}
