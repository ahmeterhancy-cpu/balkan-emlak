<?php

namespace App\Policies;

use App\Models\Kisi;
use App\Models\User;
use App\Support\Kapsam;
use App\Support\Yetki;

/** Kişiler (mülk sahibi / müşteri). Görünürlük kuralı App\Support\Kapsam::kisiler. */
class KisiPolicy
{
    public function viewAny(User $k): bool
    {
        return Yetki::var('kisi.goruntule_tumu', $k) || Yetki::var('portfoy.olustur', $k);
    }

    public function view(User $k, Kisi $kisi): bool
    {
        return Kapsam::kisiler(Kisi::query(), $k)->whereKey($kisi->id)->exists();
    }

    public function create(User $k): bool
    {
        return $this->viewAny($k);
    }

    public function update(User $k, Kisi $kisi): bool
    {
        return $this->view($k, $kisi);
    }

    public function delete(User $k, Kisi $kisi): bool
    {
        return Yetki::var('kisi.goruntule_tumu', $k) && Yetki::var('portfoy.sil', $k);
    }

    public function deleteAny(User $k): bool
    {
        return Yetki::var('kisi.goruntule_tumu', $k) && Yetki::var('portfoy.sil', $k);
    }
}
