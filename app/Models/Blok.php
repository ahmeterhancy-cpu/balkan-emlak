<?php

namespace App\Models;

use App\Models\Concerns\SiteyeBagli;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Blok extends Model
{
    use SiteyeBagli;

    protected $table = 'bloklar';

    protected $fillable = ['site_id', 'ad', 'sira'];

    public function bolumler(): HasMany
    {
        return $this->hasMany(BagimsizBolum::class);
    }
}
