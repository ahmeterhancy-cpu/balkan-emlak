<?php

namespace App\Policies;

use App\Models\Sayfa;
use App\Models\User;
use App\Support\Yetki;

/** Tek izin: icerik.yonet. */
class SayfaPolicy
{
    public function viewAny(User $k): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function view(User $k, Sayfa $kayit): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function create(User $k): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function update(User $k, Sayfa $kayit): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function delete(User $k, Sayfa $kayit): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function deleteAny(User $k): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }
}
