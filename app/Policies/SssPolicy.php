<?php

namespace App\Policies;

use App\Models\Sss;
use App\Models\User;
use App\Support\Yetki;

/** Tek izin: icerik.yonet. */
class SssPolicy
{
    public function viewAny(User $k): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function view(User $k, Sss $kayit): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function create(User $k): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function update(User $k, Sss $kayit): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function delete(User $k, Sss $kayit): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function deleteAny(User $k): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }
}
