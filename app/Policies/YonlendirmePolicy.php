<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Yonlendirme;
use App\Support\Yetki;

/** Yönlendirmeler web içeriği sayılır: icerik.yonet. */
class YonlendirmePolicy
{
    public function viewAny(User $k): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function view(User $k, Yonlendirme $y): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function create(User $k): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function update(User $k, Yonlendirme $y): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function delete(User $k, Yonlendirme $y): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }

    public function deleteAny(User $k): bool
    {
        return Yetki::var('icerik.yonet', $k);
    }
}
