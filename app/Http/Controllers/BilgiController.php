<?php

namespace App\Http\Controllers;

use App\Models\Yazi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Bilgi merkezi (editör onaylı yazılar). */
class BilgiController extends Controller
{
    public function index(Request $istek): View
    {
        $kategori = array_key_exists((string) $istek->query('kategori'), Yazi::KATEGORILER) ? $istek->query('kategori') : null;

        return view('bilgi.index', [
            'yazilar' => Yazi::yayinda()->when($kategori, fn ($q) => $q->where('kategori', $kategori))->paginate(9)->withQueryString(),
            'kategori' => $kategori,
        ]);
    }

    public function show(Yazi $yazi): View
    {
        abort_unless($yazi->durum === 'yayinda' && $yazi->yayin_tarihi && $yazi->yayin_tarihi->isPast(), 404);

        return view('bilgi.detay', [
            'yazi' => $yazi,
            'digerleri' => Yazi::yayinda()->whereKeyNot($yazi->id)->where('kategori', $yazi->kategori)->limit(3)->get(),
        ]);
    }
}
