<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('terms');
});
Route::get('/landing', function () {
    return view('landing');
});
Route::get('/terms-privacy', function () {
    return view('terms');
});
Route::get('games/dot-rescue', function () {
    return redirect('/dot-rescue/index.html');
});
Route::get('games/orbits', function () {
    return redirect('/orbits/index.html');
});
Route::get('games/fruit-ninja', function () {
    return redirect('/fruit-ninja/index.html');
});
