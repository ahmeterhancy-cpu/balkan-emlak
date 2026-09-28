<?php

namespace App\Policies;

use App\Models\SiteBelgesi;
use App\Models\User;
use App\Support\Kapsam;
use App\Support\Yetki;

/** Site belgesi: site erişimi + belge düzeyi erişim (SiteBelgesi::gorebilir). Sürümler silinmez. */
class SiteBelgesiPolicy
{
    public function viewAny(User $k): bool
    {
        return Yetki::var('site_belge.goruntule', $k) || Yetki::var('finans.goruntule', $k);
    }

    public function view(User $k, SiteBelgesi $b): bool
    {
        return Kapsam::siteErisimi($b->site_id, $k) && $b->gorebilir($k);
    }

    public function create(User $k): bool
    {
        return Yetki::var('site.yonet', $k) || Yetki::var('finans.gider', $k) || Yetki::var('operasyon.yonet', $k);
    }

    public function update(User $k, SiteBelgesi $b): bool
    {
        return $this->create($k) && $this->view($k, $b);
    }

    public function delete(User $k, SiteBelgesi $b): bool
    {
        return false;
    }

    public function deleteAny(User $k): bool
    {
        return false;
    }
}
