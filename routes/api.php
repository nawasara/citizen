<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Citizen\Http\Api\ProfileController;

/*
|--------------------------------------------------------------------------
| Citizen endpoints — behind the Keycloak JWT guard (api.citizen)
|--------------------------------------------------------------------------
| Mounted under the same /api/v1/citizen prefix that nawasara/api owns, so
| the app sees one base URL for everything a citizen can do.
|
| No route takes an id: the citizen is always the one on the token. That is
| what makes these endpoints safe to expose without per-row authorisation —
| there is no other row to reach.
*/

Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
