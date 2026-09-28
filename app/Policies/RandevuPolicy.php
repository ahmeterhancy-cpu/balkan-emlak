<?php

namespace App\Policies;

use App\Models\Randevu;
use App\Models\User;
use App\Support\Yetki;

class RandevuPolicy
{
    public function viewAny(User $k): bool
    {
        return Yetki::var('randevu.goruntule_tumu', $k) || Yetki::var('portfoy.olustur', $k);
    }

    public function view(User $k, Randevu $r): bool
    {
        return Yetki::var('randevu.goruntule_tumu', $k) || $r->danisman_id === $k->id;
    }

    public function create(User $k): bool
    {
        return $this->viewAny($k);
    }

    public function update(User $k, Randevu $r): bool
    {
        return $this->view($k, $r);
    }

    public function delete(User $k, Randevu $r): bool
    {
        return Yetki::var('randevu.goruntule_tumu', $k);
    }

    public function deleteAny(User $k): bool
    {
        return Yetki::var('randevu.goruntule_tumu', $k);
    }
}
