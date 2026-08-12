<?php

namespace Nawasara\Citizen\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the app is allowed to see of a citizen profile.
 *
 * An allow-list, not a filter: fields are named explicitly so a column added
 * later is invisible until someone decides it should be exposed. The reverse —
 * dumping the model and removing what is sensitive — leaks by default, and
 * this data is exactly the kind that must not.
 *
 * ⚠️ The NIK is never here. Not masked, not partially — the number lives in
 * its own table precisely so it does not travel with the profile.
 * `nik_verified` answers what the app actually needs to know.
 */
class CitizenProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // The stable identifier the app should use when referring to this
            // citizen — never the database id.
            'sub' => $this->keycloak_sub,

            'full_name' => $this->full_name,
            'email' => $this->email,

            'address' => $this->address,
            'village' => $this->village,
            'district' => $this->district,

            // Whether the address was typed by the citizen or derived. The app
            // uses this to decide whether prompting for it is worthwhile.
            'address_source' => $this->address_source,

            // A marker, never the number. This is the same split Keycloak
            // uses: it stores nik_verified and nothing more.
            'nik_verified' => $this->hasVerifiedNik(),

            'last_login_at' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
