<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Citizen\Http\Api\DeviceController;
use Nawasara\Citizen\Http\Api\NotificationPreferenceController;
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

// Layar Akun aplikasi warga — profil + angka kontribusi dalam satu panggilan.
// Lihat catatan pada ProfileController::me() untuk alasan ia terpisah dari
// /profile.
Route::get('/me', [ProfileController::class, 'me'])->name('me');

Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

// Perangkat penerima push notification. Didaftarkan setelah masuk, dihapus
// saat keluar akun; lihat DeviceController.
Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
Route::delete('/devices', [DeviceController::class, 'destroy'])->name('devices.destroy');

// Sakelar notifikasi per topik; berlaku untuk push dan email.
Route::get('/notification-preferences', [NotificationPreferenceController::class, 'show'])->name('notification-preferences.show');
Route::patch('/notification-preferences', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');
