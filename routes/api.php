<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Citizen\Http\Api\DeviceController;
use Nawasara\Citizen\Http\Api\NotificationController;
use Nawasara\Citizen\Http\Api\NotificationPreferenceController;
use Nawasara\Citizen\Http\Api\ProfileController;

/*
|--------------------------------------------------------------------------
| Citizen endpoints — behind the Keycloak JWT guard (api.citizen)
|--------------------------------------------------------------------------
| Mounted under the same /api/v1/citizen prefix that nawasara/api owns, so
| the app sees one base URL for everything a citizen can do.
|
| The citizen is always the one on the token. The only id taken from the
| request (a notification UUID) is looked up inside the caller's own inbox,
| so there is still no other citizen's row to reach.
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

// Kotak masuk notifikasi (lonceng di beranda). read-all didaftarkan SEBELUM
// {id}/read; id dibatasi UUID supaya "read-all" tidak pernah tertangkap
// sebagai id.
Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])
    ->whereUuid('id')
    ->name('notifications.read');

// Sakelar notifikasi per topik; berlaku untuk push dan email.
Route::get('/notification-preferences', [NotificationPreferenceController::class, 'show'])->name('notification-preferences.show');
Route::patch('/notification-preferences', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');
