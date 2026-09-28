<?php

namespace App\Policies;

use App\Enums\YayinDurumu;
use App\Models\Portfoy;
use App\Models\User;
use App\Support\Yetki;

/**
 * Danışman: kendi portföyünü görür/düzenler, incelemeye gönderir.
 * Yayına alma, yayından kaldırma ve silme ayrı izinlerdir.
 */
class PortfoyPolicy
{
    public function viewAny(User $k): bool
    {
        return Yetki::var('portfoy.goruntule_tumu', $k) || Yetki::var('portfoy.olustur', $k);
    }

    public function view(User $k, Portfoy $p): bool
    {
        return Yetki::var('portfoy.goruntule_tumu', $k) || $p->danisman_id === $k->id;
    }

    public function create(User $k): bool
    {
        return Yetki::var('portfoy.olustur', $k);
    }

    public function update(User $k, Portfoy $p): bool
    {
        if (Yetki::var('portfoy.duzenle_tumu', $k)) {
            return true;
        }

        // Kendi portföyü; yayındaki ilanı yayın yetkisi olmayan değiştiremez
        // (değişiklik yayın onayını atlatmasın diye önce yayından kaldırılır).
        return Yetki::var('portfoy.olustur', $k)
            && $p->danisman_id === $k->id
            && $p->yayin_durumu !== YayinDurumu::Yayinda;
    }

    public function yayimla(User $k, Portfoy $p): bool
    {
        return Yetki::var('portfoy.yayimla', $k);
    }

    public function incelemeyeGonder(User $k, Portfoy $p): bool
    {
        return $this->update($k, $p) && in_array($p->yayin_durumu, [YayinDurumu::Taslak, YayinDurumu::Kaldirildi], true);
    }

    public function icBilgi(User $k, Portfoy $p): bool
    {
        return Yetki::var('portfoy.ic_bilgi', $k) && $this->view($k, $p);
    }

    public function delete(User $k, Portfoy $p): bool
    {
        return Yetki::var('portfoy.sil', $k);
    }

    public function deleteAny(User $k): bool
    {
        return Yetki::var('portfoy.sil', $k);
    }
}
