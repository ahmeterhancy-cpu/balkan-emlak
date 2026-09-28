<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Malik / kiracı ilişkisi. Silinmez; bitiş tarihi girilir. */
class BolumIliskisiPolicy extends SiteKaydiPolicy
{
    protected array $gorIzinleri = ['finans.goruntule'];

    protected string $yazIzni = 'site.yonet';

    public function delete(User $k, Model $kayit): bool
    {
        return false;
    }
}
