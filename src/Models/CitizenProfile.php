<?php

namespace Nawasara\Citizen\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A citizen's local profile, keyed to their Keycloak account by `sub`.
 *
 * Deliberately holds no NIK — that lives in CitizenNikRecord so reading it is
 * a separate, auditable act. See the migration for why.
 */
class CitizenProfile extends Model
{
    use LogsActivity;

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_GEOCODING = 'geocoding';
    public const SOURCE_EMPTY = 'empty';

    protected $table = 'nawasara_citizen_profiles';

    protected $fillable = [
        'keycloak_sub',
        'full_name',
        'email',
        'address',
        'village',
        'district',
        // Kode wilayah — yang menjadi kebenaran; kolom teks di atas untuk
        // ditampilkan. Lihat migrasi add_region_codes_to_citizen_profiles.
        'district_code',
        'village_code',
        'village_id',
        'address_source',
        'last_login_at',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    /**
     * `keycloak_sub` is deliberately absent from the logged attributes: it
     * never changes, so logging it only adds noise. The address fields are
     * logged because a citizen changing their domicile is exactly the kind of
     * edit someone may later need to account for.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['full_name', 'email', 'address', 'village', 'district', 'address_source'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Profil warga {$eventName}");
    }

    public function nikRecord(): HasOne
    {
        return $this->hasOne(CitizenNikRecord::class, 'citizen_profile_id');
    }

    /** Whether this citizen has a verified NIK, without touching the number. */
    public function hasVerifiedNik(): bool
    {
        return $this->nikRecord()->where('is_verified', true)->exists();
    }

    /**
     * Display address. Falls back through what is actually known rather than
     * rendering an empty string — most citizens will have no address at all,
     * since registration never asks for one.
     */
    public function displayAddress(): string
    {
        $parts = array_filter([
            $this->address,
            $this->village ? 'Desa '.ucwords($this->village) : null,
            $this->district ? 'Kec. '.ucwords($this->district) : null,
        ]);

        return $parts === [] ? '—' : implode(', ', $parts);
    }

    public function scopeForSub($query, string $sub)
    {
        return $query->where('keycloak_sub', $sub);
    }
}
