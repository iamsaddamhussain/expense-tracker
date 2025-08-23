<?php

use Illuminate\Support\Facades\Route;

// Forward all web routes to the vue base page
// https://router.vuejs.org/guide/essentials/history-mode.html
Route::get('/{vue?}', function () {
    if (!request()->expectsJson()) {
        return view('app');
    }
    abort(404);
})->where('vue', '[\/\w\.-]*');
