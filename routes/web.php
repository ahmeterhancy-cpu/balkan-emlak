<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/panel.php';

Route::get('/', function () {
    return view('welcome');
});
