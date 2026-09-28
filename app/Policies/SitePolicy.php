<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;
use App\Support\Kapsam;
use App\Support\Yetki;

/**
 * Site kaydı. Yeni site açmak ve silmek yalnız tüm sitelere erişen
 * yönetime açık; atanmış site yöneticisi kendi sitesinin bilgisini düzenler.
 */
class SitePolicy
{
    private const GOR = ['site.yonet', 'finans.goruntule', 'operasyon.yonet', 'operasyon.saha', 'site_rapor', 'site_belge.goruntule'];

    public function viewAny(User $k): bool
    {
        foreach (self::GOR as $izin) {
            if (Yetki::var($izin, $k)) {
                return true;
            }
        }

        return false;
    }

    public function view(User $k, Site $s): bool
    {
        return $this->viewAny($k) && Kapsam::siteErisimi($s->id, $k);
    }

    public function create(User $k): bool
    {
        return Yetki::var('site.yonet', $k) && Yetki::var('site.goruntule_tumu', $k);
    }

    public function update(User $k, Site $s): bool
    {
        return Yetki::var('site.yonet', $k) && Kapsam::siteErisimi($s->id, $k);
    }

    /** Kullanıcı–site ataması ve onay limiti: yalnız tüm siteleri yöneten. */
    public function yetkiDuzenle(User $k, Site $s): bool
    {
        return Yetki::var('site.yonet', $k) && Yetki::var('site.goruntule_tumu', $k);
    }

    public function donemKapat(User $k, Site $s): bool
    {
        return Yetki::var('donem.kapat', $k) && Kapsam::siteErisimi($s->id, $k);
    }

    /** Mali kaydı olan site silinmez; yönetim bitişi girilip pasife alınır. */
    public function delete(User $k, Site $s): bool
    {
        return false;
    }

    public function deleteAny(User $k): bool
    {
        return false;
    }
}
