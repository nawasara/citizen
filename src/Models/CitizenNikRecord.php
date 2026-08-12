<?php

namespace Nawasara\Citizen\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A citizen's NIK, encrypted, in its own table.
 *
 * ⚠️ Every read of the plaintext number is an act someone may have to account
 * for under UU 27/2022. Use nik() and accept that it is logged; do not reach
 * for the raw attribute to sidestep that.
 */
class CitizenNikRecord extends Model
{
    use LogsActivity;

    protected $table = 'nawasara_citizen_nik_records';

    protected $fillable = [
        'citizen_profile_id',
        'nik_encrypted',
        'nik_hash',
        'is_verified',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        // Laravel encrypts on write and decrypts on read. Non-deterministic,
        // which is why nik_hash exists for comparisons.
        'nik_encrypted' => 'encrypted',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * `nik_encrypted` is NOT logged, and must never be added here — the
     * activity log is exactly the kind of place a NIK should not end up. Only
     * the verification state is worth recording.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['is_verified', 'verified_at', 'verified_by'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Data NIK warga {$eventName}");
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CitizenProfile::class, 'citizen_profile_id');
    }

    /**
     * Blind index for a NIK.
     *
     * Salted with APP_KEY so the hashes are useless outside this application:
     * a bare sha256 of a 16-digit number is trivially reversible by brute
     * force — the whole space is only 10^16 and NIK has structure that shrinks
     * it much further.
     *
     * ⚠️ Changing APP_KEY invalidates every stored hash. Duplicate detection
     * would silently stop working while everything else kept running, so a key
     * rotation has to rehash this column.
     */
    public static function hashFor(string $nik): string
    {
        return hash_hmac('sha256', preg_replace('/\D/', '', $nik), (string) config('app.key'));
    }

    /** Masked form, safe for lists and logs: 3502•••••••••1234 */
    public function masked(): string
    {
        $nik = (string) $this->nik_encrypted;

        if (strlen($nik) < 8) {
            return '••••';
        }

        return substr($nik, 0, 4).str_repeat('•', max(0, strlen($nik) - 8)).substr($nik, -4);
    }
}
