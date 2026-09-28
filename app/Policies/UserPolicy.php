<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Yetki;

class UserPolicy
{
    public function viewAny(User $k): bool
    {
        return Yetki::var('kullanici.yonet', $k);
    }

    public function view(User $k, User $hedef): bool
    {
        return Yetki::var('kullanici.yonet', $k) || $k->is($hedef);
    }

    public function create(User $k): bool
    {
        return Yetki::var('kullanici.yonet', $k);
    }

    public function update(User $k, User $hedef): bool
    {
        return Yetki::var('kullanici.yonet', $k);
    }

    /** Kendini silemez; son sistem yöneticisini kilitlememek için. */
    public function delete(User $k, User $hedef): bool
    {
        return Yetki::var('kullanici.yonet', $k) && ! $k->is($hedef);
    }

    public function deleteAny(User $k): bool
    {
        return false;
    }
}
