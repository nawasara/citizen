<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Citizen\Livewire\Profile\Index as ProfileIndex;
use Spatie\Permission\Middleware\PermissionMiddleware;

/*
| Staff-facing panel. Citizens never reach these — they use the API.
|
| Permission is enforced here AND in the components: the route guard stops a
| stray URL, and the component guard stops anything that reaches it another
| way (a Livewire request carries its own lifecycle and does not re-run route
| middleware on every update).
*/

Route::middleware(['web', 'auth'])->prefix('nawasara-citizen')->group(function () {
    Route::get('profiles', ProfileIndex::class)
        ->middleware(PermissionMiddleware::using('citizen.profile.view'))
        ->name('nawasara-citizen.profiles.index');
});
