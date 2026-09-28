<?php

namespace App\Policies;

use App\Models\Tedarikci;
use App\Models\User;
use App\Support\Yetki;

/** Tedarikçiler tüm sitelerde ortak liste. Gideri olan tedarikçi silinmez. */
class TedarikciPolicy
{
    public function viewAny(User $k): bool
    {
        return Yetki::var('finans.goruntule', $k) || Yetki::var('operasyon.yonet', $k);
    }

    public function view(User $k, Tedarikci $t): bool
    {
        return $this->viewAny($k);
    }

    public function create(User $k): bool
    {
        return Yetki::var('finans.gider', $k) || Yetki::var('operasyon.yonet', $k);
    }

    public function update(User $k, Tedarikci $t): bool
    {
        return $this->create($k);
    }

    public function delete(User $k, Tedarikci $t): bool
    {
        return Yetki::var('finans.gider', $k) && ! $t->giderler()->exists();
    }

    public function deleteAny(User $k): bool
    {
        return false;
    }
}
