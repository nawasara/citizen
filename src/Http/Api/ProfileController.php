<?php

namespace Nawasara\Citizen\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Nawasara\Citizen\Http\Resources\CitizenProfileResource;
use Nawasara\Citizen\Models\CitizenProfile;
use Nawasara\Citizen\Services\CitizenProvisioner;

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
        ]);

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
    protected function normalisePlace(string $value): string
    {
        $value = preg_replace('/^(kec\.?|kecamatan|desa|ds\.?|kel\.?|kelurahan)\s+/iu', '', trim($value));

        return mb_strtolower(trim((string) $value));
    }
}
