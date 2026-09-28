<?php

namespace App\Policies;

use App\Models\Referans;
use App\Models\User;
use App\Support\Yetki;

/** Tek izin: icerik.yonet. */
class ReferansPolicy
{
    public function viewAny(User $k): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function view(User $k, Referans $kayit): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function create(User $k): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function update(User $k, Referans $kayit): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function delete(User $k, Referans $kayit): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function deleteAny(User $k): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }
}
