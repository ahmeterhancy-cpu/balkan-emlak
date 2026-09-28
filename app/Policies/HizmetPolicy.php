<?php

namespace App\Policies;

use App\Models\Hizmet;
use App\Models\User;
use App\Support\Yetki;

/** Tek izin: icerik.yonet. */
class HizmetPolicy
{
    public function viewAny(User $k): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function view(User $k, Hizmet $kayit): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function create(User $k): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function update(User $k, Hizmet $kayit): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function delete(User $k, Hizmet $kayit): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function deleteAny(User $k): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }
}
