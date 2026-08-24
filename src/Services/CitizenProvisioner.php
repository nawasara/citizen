<?php

namespace Nawasara\Citizen\Services;

use Illuminate\Support\Facades\DB;
use Nawasara\Citizen\Models\CitizenProfile;

/**
 * The one supported way from "a citizen in Keycloak" to "a citizen profile
 * here".
 *
 * Everything that creates a citizen row goes through this. On the staff side
 * the same logic once lived in two places — the SSO controller and a Zoom
 * page — and the second copy forgot to assign a role, producing users with no
 * access at all. One entry point avoids repeating that.
 *
 * Usage:
 *   $profile = app(CitizenProvisioner::class)->fromClaims($request->attributes->get('citizen_claims'));
 */
class CitizenProvisioner
{
    /**
     * Ensure a profile exists for these verified JWT claims.
     *
     * Idempotent: calling it on every request is fine and is in fact the
     * intent — profiles fill in as citizens arrive, rather than being pulled
     * in bulk. See the migration for why there is no sync job.
     *
     * @param  array  $claims  Already verified by AuthenticateCitizenJwt.
     *                         This method does not re-verify anything; passing
     *                         unverified claims here creates whatever they say.
     */
    public function fromClaims(array $claims): ?CitizenProfile
    {
        $sub = $claims['sub'] ?? null;

        if (! is_string($sub) || $sub === '') {
            return null;
        }

        return DB::transaction(function () use ($sub, $claims) {
            $profile = CitizenProfile::forSub($sub)->lockForUpdate()->first();

            if ($profile === null) {
                return CitizenProfile::create([
                    'keycloak_sub' => $sub,
                    'full_name' => $this->nameFrom($claims),
                    'email' => $claims['email'] ?? null,
                    'phone' => $this->phoneFrom($claims),
                    'address_source' => CitizenProfile::SOURCE_EMPTY,
                    'last_login_at' => now(),
                ]);
            }

            // Refresh the copied fields on each visit so a citizen who changes
            // their name or email in Keycloak is not left with a stale profile
            // here. The address is untouched: it is theirs to set, and nothing
            // in Keycloak is authoritative about where they live.
            $profile->fill([
                'full_name' => $this->nameFrom($claims) ?? $profile->full_name,
                'email' => $claims['email'] ?? $profile->email,

                // Nomor lama DIPERTAHANKAN bila klaimnya kosong. Realm yang
                // belum memasang mapper akan mengirim klaim kosong pada
                // setiap login, dan menimpanya dengan null akan menghapus
                // nomor yang sudah pernah tercatat.
                'phone' => $this->phoneFrom($claims) ?? $profile->phone,
                'last_login_at' => now(),
            ])->save();

            return $profile;
        });
    }

    /**
     * Name from the claims.
     *
     * The citizen realm keeps a single "Nama Lengkap" in firstName, so `name`
     * and `given_name` usually carry the same value. Google-linked accounts
     * fill both from the Google profile.
     */
    /**
     * Nomor HP dari klaim, bila realm mengirimkannya.
     *
     * Beberapa nama diperiksa karena mapper Keycloak dapat dipasang dengan
     * nama klaim apa pun, dan portal SSO warga menamainya berbeda dari
     * standar OIDC (`phone_number`). Yang mana pun yang terpasang, nomornya
     * terbaca tanpa perlu mengubah kode di sini.
     *
     * Null itu SAH: verifikasi WhatsApp dinonaktifkan (WAGO terkena blokir),
     * jadi tidak semua warga punya nomor tercatat. Aplikasi menuliskannya
     * nullable.
     */
    protected function phoneFrom(array $claims): ?string
    {
        foreach (['phone_number', 'phone', 'whatsapp', 'nomor_hp'] as $key) {
            $value = $claims[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    protected function nameFrom(array $claims): ?string
    {
        foreach (['name', 'given_name', 'preferred_username'] as $key) {
            $value = $claims[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
