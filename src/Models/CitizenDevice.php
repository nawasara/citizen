<?php

namespace Nawasara\Citizen\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A device that receives push notifications for a citizen.
 *
 * See the migration for why the token is unique across citizens.
 */
class CitizenDevice extends Model
{
    protected $table = 'nawasara_citizen_devices';

    protected $fillable = ['keycloak_sub', 'token', 'platform', 'app_version', 'last_seen_at'];

    protected $casts = ['last_seen_at' => 'datetime'];

    /**
     * A citizen reinstalling the app over months collects tokens that FCM
     * eventually rejects anyway; this cap keeps a forgotten tablet from
     * pushing out the phone actually in use.
     */
    public const MAX_PER_CITIZEN = 10;

    public const PLATFORMS = ['android', 'ios'];

    /**
     * Register or refresh a token for a citizen.
     *
     * Moves the row if the token belonged to someone else (the phone changed
     * hands), and trims the citizen's oldest devices beyond the cap.
     */
    public static function register(string $sub, string $token, string $platform, ?string $appVersion): self
    {
        $device = self::updateOrCreate(
            ['token' => $token],
            [
                'keycloak_sub' => $sub,
                'platform' => $platform,
                'app_version' => $appVersion,
                'last_seen_at' => now(),
            ],
        );

        $keep = self::where('keycloak_sub', $sub)
            ->orderByDesc('last_seen_at')->orderByDesc('id')
            ->limit(self::MAX_PER_CITIZEN)
            ->pluck('id');

        self::where('keycloak_sub', $sub)->whereNotIn('id', $keep)->delete();

        return $device;
    }

    /** @return Collection<int,string> every token to notify for this citizen */
    public static function tokensFor(string $sub): Collection
    {
        return self::where('keycloak_sub', $sub)->pluck('token');
    }
}
