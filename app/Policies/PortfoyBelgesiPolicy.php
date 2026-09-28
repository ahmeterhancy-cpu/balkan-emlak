<?php

namespace App\Policies;

use App\Models\PortfoyBelgesi;
use App\Models\User;
use App\Support\Yetki;

/** Özel belge: belge izni + ilgili portföyü görebilme, ikisi birden. */
class PortfoyBelgesiPolicy
{
    public function viewAny(User $k): bool
    {
        return Yetki::var('belge.goruntule', $k);
    }

    public function view(User $k, PortfoyBelgesi $b): bool
    {
        return Yetki::var('belge.goruntule', $k)
            && (new PortfoyPolicy)->view($k, $b->portfoy);
    }

    public function create(User $k): bool
    {
        return Yetki::var('belge.goruntule', $k);
    }

    public function update(User $k, PortfoyBelgesi $b): bool
    {
        return $this->view($k, $b) && (new PortfoyPolicy)->update($k, $b->portfoy);
    }

    public function delete(User $k, PortfoyBelgesi $b): bool
    {
        return $this->update($k, $b);
    }

    public function deleteAny(User $k): bool
    {
        return Yetki::var('portfoy.sil', $k);
    }
}
