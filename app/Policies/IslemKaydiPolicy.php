<?php

namespace App\Policies;

use App\Models\IslemKaydi;
use App\Models\User;
use App\Support\Yetki;

/** Salt okunur: kimse oluşturamaz, düzenleyemez, silemez. */
class IslemKaydiPolicy
{
    public function viewAny(User $k): bool
    {
        return Yetki::var('islem_gecmisi.goruntule', $k);
    }

    public function view(User $k, IslemKaydi $kayit): bool
    {
        return Yetki::var('islem_gecmisi.goruntule', $k);
    }

    public function create(User $k): bool
    {
        return false;
    }

    public function update(User $k, IslemKaydi $kayit): bool
    {
        return false;
    }

    public function delete(User $k, IslemKaydi $kayit): bool
    {
        return false;
    }

    public function deleteAny(User $k): bool
    {
        return false;
    }
}
