<?php

namespace App\Policies;

use App\Models\Komisyon;
use App\Models\User;
use App\Support\Yetki;

/** Tek izin: komisyon.goruntule. */
class KomisyonPolicy
{
    public function viewAny(User $k): bool
    {
        return Yetki::var('komisyon.goruntule', $k);
    }

    public function view(User $k, Komisyon $kayit): bool
    {
        return Yetki::var('komisyon.goruntule', $k);
    }

    public function create(User $k): bool
    {
        return Yetki::var('komisyon.goruntule', $k);
    }

    public function update(User $k, Komisyon $kayit): bool
    {
        return Yetki::var('komisyon.goruntule', $k);
    }

    public function delete(User $k, Komisyon $kayit): bool
    {
        return Yetki::var('komisyon.goruntule', $k);
    }

    public function deleteAny(User $k): bool
    {
        return Yetki::var('komisyon.goruntule', $k);
    }
}
