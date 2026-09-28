<?php

namespace App\Http\Controllers;

use App\Enums\Kategori;
use App\Models\Konum;
use App\Models\Portfoy;
use App\Services\PortfoyArama;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PortfoyController extends Controller
{
    public function index(Request $istek): View
    {
        $arama = new PortfoyArama($istek);
        $ilanlar = $arama->sorgu()->paginate(12)->withQueryString();

        return view('emlak.index', [
            'arama' => $arama,
            'ilanlar' => $ilanlar,
            'konumAgaci' => $this->konumAgaci(),
            'tipler' => collect(Kategori::cases())->mapWithKeys(fn (Kategori $k) => [$k->value => $k->tipler()])->all(),
        ]);
    }

    /** Ziyaretçi yalnız yayındaki ilanı görür; diğerleri 404 (varlığı da sızmaz). */
    public function show(Portfoy $portfoy): View
    {
        abort_unless($portfoy->yayin_durumu?->value === 'yayinda', 404);

        return $this->detay($portfoy, false);
    }

    /** Panelden "Önizle": yayında olmayan ilanı yetkili kullanıcı görür. */
    public function onizleme(Portfoy $portfoy): View
    {
        Gate::authorize('view', $portfoy);

        return $this->detay($portfoy, true);
    }

    private function detay(Portfoy $portfoy, bool $onizleme): View
    {
        $portfoy->load(['il:id,ad', 'ilce:id,ad', 'mahalle:id,ad', 'fotograflar', 'planlar', 'danisman']);

        $benzerler = Portfoy::yayinda()
            ->whereKeyNot($portfoy->id)
            ->where('kategori', $portfoy->kategori)
            ->where('islem', $portfoy->islem)
            ->whereIn('operasyon_durumu', ['aktif', 'rezerve'])
            ->with(['il:id,ad', 'ilce:id,ad', 'mahalle:id,ad', 'kapak', 'danisman:id,name,foto'])
            ->withCount('fotograflar')
            ->orderByRaw('CASE WHEN ilce_id = ? THEN 0 ELSE 1 END', [$portfoy->ilce_id ?? 0])
            ->latest('yayin_tarihi')
            ->limit(3)
            ->get();

        return view('emlak.detay', [
            'ilan' => $portfoy,
            'benzerler' => $benzerler,
            'onizleme' => $onizleme,
        ]);
    }

    /**
     * Bağımlı il → ilçe → mahalle seçimleri için ağaç (JS'e gömülür).
     *
     * @return array<int, array{ad:string, ilceler: array<int, array{ad:string, mahalleler: array<int,string>}>}>
     */
    private function konumAgaci(): array
    {
        $tumu = Konum::query()->orderBy('sira')->orderBy('ad')->get(['id', 'ust_id', 'tur', 'ad']);
        $agac = [];

        foreach ($tumu->where('tur', 'il') as $il) {
            $ilceler = [];
            foreach ($tumu->where('tur', 'ilce')->where('ust_id', $il->id) as $ilce) {
                $ilceler[$ilce->id] = [
                    'ad' => $ilce->ad,
                    'mahalleler' => $tumu->where('tur', 'mahalle')->where('ust_id', $ilce->id)->pluck('ad', 'id')->all(),
                ];
            }
            $agac[$il->id] = ['ad' => $il->ad, 'ilceler' => $ilceler];
        }

        return $agac;
    }
}
