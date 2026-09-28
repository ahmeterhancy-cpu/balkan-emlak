<?php

namespace App\Models\Concerns;

use App\Models\Site;
use App\Support\Kapsam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** site_id taşıyan modeller. `yetkili()` kapsamı kullanıcının sitelerine süzer. */
trait SiteyeBagli
{
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @param Builder<static> $sorgu */
    public function scopeYetkili(Builder $sorgu): void
    {
        Kapsam::siteyeBagli($sorgu);
    }
}
