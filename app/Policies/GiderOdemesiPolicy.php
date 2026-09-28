<?php

namespace App\Policies;

use App\Models\GiderOdemesi;
use App\Models\User;
use App\Support\Kapsam;
use App\Support\Yetki;

/** Gider ödemesi: değişmez; oluşturma ve geri alma GiderServisi üzerinden. */
class GiderOdemesiPolicy
{
    public function viewAny(User $k): bool
    {
        return Yetki::var('finans.goruntule', $k);
    }

    public function view(User $k, GiderOdemesi $o): bool
    {
        return Yetki::var('finans.goruntule', $k) && Kapsam::siteErisimi((int) $o->gider->site_id, $k);
    }

    public function create(User $k): bool
    {
        return Yetki::var('finans.gider', $k);
    }

    public function update(User $k, GiderOdemesi $o): bool
    {
        return false;
    }

    public function delete(User $k, GiderOdemesi $o): bool
    {
        return false;
    }

    public function deleteAny(User $k): bool
    {
        return false;
    }
}
