<?php

namespace App\Policies;

use App\Models\Talep;
use App\Models\User;
use App\Support\Yetki;

/** Talepler: tümünü görme izni olmayan yalnız kendisine atananları görür. */
class TalepPolicy
{
    public function viewAny(User $k): bool
    {
        return Yetki::var('talep.goruntule_tumu', $k) || Yetki::var('portfoy.olustur', $k);
    }

    public function view(User $k, Talep $t): bool
    {
        return Yetki::var('talep.goruntule_tumu', $k) || $t->atanan_id === $k->id;
    }

    public function create(User $k): bool
    {
        return $this->viewAny($k);
    }

    public function update(User $k, Talep $t): bool
    {
        return $this->view($k, $t);
    }

    public function ata(User $k, Talep $t): bool
    {
        return Yetki::var('talep.ata', $k);
    }

    public function delete(User $k, Talep $t): bool
    {
        return Yetki::var('talep.ata', $k);
    }

    public function deleteAny(User $k): bool
    {
        return Yetki::var('talep.ata', $k);
    }
}
