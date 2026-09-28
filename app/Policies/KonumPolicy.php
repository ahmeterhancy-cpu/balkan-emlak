<?php

namespace App\Policies;

use App\Models\Konum;
use App\Models\User;
use App\Support\Yetki;

/** Tek izin: konum.yonet. */
class KonumPolicy
{
    public function viewAny(User $k): bool
    {
        return Yetki::var('konum.yonet', $k);
    }

    public function view(User $k, Konum $kayit): bool
    {
        return Yetki::var('konum.yonet', $k);
    }

    public function create(User $k): bool
    {
        return Yetki::var('konum.yonet', $k);
    }

    public function update(User $k, Konum $kayit): bool
    {
        return Yetki::var('konum.yonet', $k);
    }

    public function delete(User $k, Konum $kayit): bool
    {
        return Yetki::var('konum.yonet', $k);
    }

    public function deleteAny(User $k): bool
    {
        return Yetki::var('konum.yonet', $k);
    }
}
