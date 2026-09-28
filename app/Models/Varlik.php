<?php

namespace App\Models;

use App\Models\Concerns\SiteyeBagli;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bakımı izlenen ekipman: asansör, hidrofor, yangın ekipmanı... */
class Varlik extends Model
{
    use SiteyeBagli;

    protected $table = 'varliklar';

    protected $fillable = ['site_id', 'blok_id', 'ad', 'tur', 'marka_model', 'notlar'];

    public const TURLER = [
        'asansor' => 'Asansör', 'hidrofor' => 'Hidrofor', 'yangin' => 'Yangın ekipmanı', 'jenerator' => 'Jeneratör',
        'bahce' => 'Bahçe / peyzaj', 'havuz' => 'Havuz', 'kazan' => 'Kazan / ısıtma', 'diger' => 'Diğer',
    ];

    public function blok(): BelongsTo
    {
        return $this->belongsTo(Blok::class);
    }

    public function bakimPlanlari(): HasMany
    {
        return $this->hasMany(BakimPlani::class);
    }
}
