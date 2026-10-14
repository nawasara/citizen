<?php

namespace Nawasara\Citizen\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nawasara\Citizen\Models\CitizenProfile;
use Nawasara\Citizen\Services\CitizenProvisioner;
use Nawasara\Citizen\Support\NotificationPreferences;

/**
 * Pengaturan notifikasi warga yang sedang masuk.
 *
 *   GET   /api/v1/citizen/notification-preferences
 *   PATCH /api/v1/citizen/notification-preferences   { "report_progress": false }
 *
 * Keduanya menjawab bentuk yang sama: daftar topik lengkap dengan label dan
 * nilai yang berlaku, sehingga aplikasi cukup menggambar ulang dari jawaban.
 *
 * Pilihan ini berlaku untuk SEMUA kanal (push dan email). Warga yang mematikan
 * "Perkembangan laporan" tidak mengharapkan kabar yang sama tetap datang
 * lewat surel.
 */
class NotificationPreferenceController
{
    public function __construct(
        protected CitizenProvisioner $provisioner,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $profile = $this->resolve($request);

        if ($profile === null) {
            return $this->unavailable();
        }

        return $this->respond($profile);
    }

    public function update(Request $request): JsonResponse
    {
        $profile = $this->resolve($request);

        if ($profile === null) {
            return $this->unavailable();
        }

        $input = $request->json()->all();

        // Kunci yang tidak dikenal DITOLAK, bukan diabaikan. Aplikasi yang
        // salah eja kunci akan mengira pilihannya tersimpan padahal tidak.
        foreach ($input as $key => $value) {
            $topic = NotificationPreferences::TOPICS[$key] ?? null;

            if ($topic === null) {
                return $this->invalid('unknown_topic', "Topik '{$key}' tidak dikenal.");
            }
            if ($topic['locked']) {
                return $this->invalid('topic_locked', "{$topic['label']} tidak dapat dimatikan.");
            }
            if (! is_bool($value)) {
                return $this->invalid('invalid_value', "Nilai untuk '{$key}' harus true atau false.");
            }
        }

        if ($input !== []) {
            $profile->forceFill([
                'notification_preferences' => NotificationPreferences::merge(
                    $profile->notification_preferences, $input,
                ),
            ])->save();
        }

        return $this->respond($profile);
    }

    protected function respond(CitizenProfile $profile): JsonResponse
    {
        return response()->json(['data' => [
            'preferences' => NotificationPreferences::describe($profile->notification_preferences),
        ]]);
    }

    protected function resolve(Request $request): ?CitizenProfile
    {
        return $this->provisioner->fromClaims((array) $request->attributes->get('citizen_claims', []));
    }

    protected function unavailable(): JsonResponse
    {
        return response()->json([
            'error' => ['code' => 'profile_unavailable', 'message' => 'Profil tidak dapat dimuat.'],
        ], 404);
    }

    protected function invalid(string $code, string $message): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], 422);
    }
}
