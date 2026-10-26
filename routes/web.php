<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('gis.index');
});

Route::get('/gis', function () {
    return view('gis.index');
})->name('gis.index');