<?php

namespace Nawasara\Citizen\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Nawasara\Citizen\Http\Resources\CitizenMeResource;
use Nawasara\Citizen\Http\Resources\CitizenProfileResource;
use Nawasara\Citizen\Models\CitizenProfile;
use Nawasara\Citizen\Services\CitizenProvisioner;
use Nawasara\Citizen\Services\CitizenStats;

/**
 * The citizen's own profile. Behind api.citizen (Keycloak JWT).
 *
 * A citizen can only ever reach their own row: the identity comes from the
 * verified `sub` on the request, never from a parameter. There is no route
 * that takes an id, so there is nothing to enumerate.
 */
class ProfileController
{
    public function __construct(
        protected CitizenProvisioner $provisioner,
    ) {}

    /** GET /api/v1/citizen/profile */
    public function show(Request $request): JsonResponse
    {
        $profile = $this->resolve($request);

        if ($profile === null) {
            return response()->json([
                'error' => ['code' => 'profile_unavailable', 'message' => 'Profil tidak dapat dimuat.'],
            ], 404);
        }

        return response()->json(['data' => new CitizenProfileResource($profile)]);
    }

    /**
     * GET /api/v1/citizen/me
     *
     * Layar Akun di aplikasi warga: profil ringkas beserta angka kontribusi.
     *
     * Terpisah dari `/citizen/profile` dengan sengaja. Yang itu tentang
     * alamat dan penyuntingannya; yang ini tentang apa yang ditampilkan satu
     * layar, dan bentuknya dipatok untuk aplikasi yang sudah terpasang
     * (`docs/teknis/rencana/endpoint-yang-dibutuhkan.md` §2). Menggabungkan
     * keduanya berarti setiap perubahan pada salah satu layar memaksa
     * perubahan pada yang lain.
     */
    public function me(Request $request, CitizenStats $stats): JsonResponse
    {
        $profile = $this->resolve($request);

        if ($profile === null) {
            return response()->json([
                'error' => ['code' => 'profile_unavailable', 'message' => 'Profil tidak dapat dimuat.'],
            ], 404);
        }

        return response()->json([
            'data' => new CitizenMeResource(
                $profile,
                $stats->for($profile->keycloak_sub),
            ),
        ]);
    }

    /** PATCH /api/v1/citizen/profile */
    public function update(Request $request): JsonResponse
    {
        $profile = $this->resolve($request);

        if ($profile === null) {
            return response()->json([
                'error' => ['code' => 'profile_unavailable', 'message' => 'Profil tidak dapat dimuat.'],
            ], 404);
        }

        // Only the domicile address is editable here. Name and email come from
        // Keycloak and are overwritten on the next sign-in, so accepting them
        // would produce a change that quietly disappears.
        $data = $request->validate([
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'village' => ['sometimes', 'nullable', 'string', 'max:255'],
            'district' => ['sometimes', 'nullable', 'string', 'max:255'],

            // Kode wilayah — INI yang menjadi kebenaran; teks di atas hanya
            // untuk ditampilkan. Selama alamat berupa teks bebas, "Ngrayun"
            // dan "Ngerayun" menjadi dua kecamatan berbeda, dan laporan
            // tersalur ke OPD yang keliru tanpa ada yang menyadarinya.
            //
            // Boleh null: ribuan profil lama tidak punya kode, dan menebaknya
            // dari ejaan lama justru risiko yang hendak dihindari.
            'district_code' => ['sometimes', 'nullable', 'string', 'size:6'],
            'village_code' => ['sometimes', 'nullable', 'string', 'size:10'],
        ]);

        if ($galat = $this->validateRegionCodes($data)) {
            return response()->json(['error' => $galat], 422);
        }

        if ($data === []) {
            return response()->json(['data' => new CitizenProfileResource($profile)]);
        }

        // Normalised on the way in — lowercase, no "Kec."/"Desa" prefix — so
        // that grouping by district later does not split the same place across
        // several spellings.
        foreach (['village', 'district'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = $this->normalisePlace($data[$field]);
            }
        }

        $data['address_source'] = CitizenProfile::SOURCE_MANUAL;

        $profile->fill($data)->save();

        return response()->json(['data' => new CitizenProfileResource($profile)]);
    }

    /**
     * Profile for the authenticated citizen, created on the spot if this is
     * their first request. Provisioning here rather than at sign-in keeps the
     * package independent of how the citizen arrived.
     */
    protected function resolve(Request $request): ?CitizenProfile
    {
        $claims = (array) $request->attributes->get('citizen_claims', []);

        return $this->provisioner->fromClaims($claims);
    }

    /** "Kec. Jenangan" and "JENANGAN" both become "jenangan". */
    /**
     * Kode kecamatan harus nyata, dan kode desa harus MILIK kecamatan itu.
     *
     * Kode desa Kemendagri selalu berawalan kode kecamatannya (`350217` →
     * `3502171001`), jadi pasangan yang tidak cocok dapat ditolak tanpa join
     * ke tabel desa sama sekali.
     *
     * Ditolak dengan 422, bukan diperbaiki diam-diam. Alamat yang ditebak
     * server adalah persis cara laporan tersalur ke kecamatan yang salah.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,string>|null
     */
    protected function validateRegionCodes(array $data): ?array
    {
        $district = $data['district_code'] ?? null;
        $village = $data['village_code'] ?? null;

        if ($district !== null && $district !== '') {
            $ada = DB::table('nawasara_aspirations_districts')
                ->where('code', $district)
                ->exists();

            if (! $ada) {
                return [
                    'code' => 'unknown_district',
                    'message' => 'Kode kecamatan tidak dikenali.',
                ];
            }
        }

        if ($village !== null && $village !== '') {
            if ($district === null || $district === '') {
                return [
                    'code' => 'district_required',
                    'message' => 'Kode desa harus disertai kode kecamatannya.',
                ];
            }

            if (! str_starts_with($village, $district)) {
                return [
                    'code' => 'village_district_mismatch',
                    'message' => 'Kode desa tidak berada di kecamatan yang dipilih.',
                ];
            }
        }

        return null;
    }

    protected function normalisePlace(string $value): string
    {
        $value = preg_replace('/^(kec\.?|kecamatan|desa|ds\.?|kel\.?|kelurahan)\s+/iu', '', trim($value));

        return mb_strtolower(trim((string) $value));
    }
}
