<?php

namespace Nawasara\Citizen\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One entry in a citizen's notification inbox. See the migration for why
 * this is not read from the notification log.
 *
 * `type` is a code, not text: the app picks the icon and colour from it, and
 * falls back to a generic icon for codes it does not know, so adding a type
 * here never breaks an older app.
 */
class CitizenNotification extends Model
{
    use HasUuids;

    public const TYPE_REPORT_IN_PROGRESS = 'report_in_progress';

    public const TYPE_REPORT_RESOLVED = 'report_resolved';

    public const TYPE_REPORT_REJECTED = 'report_rejected';

    public const TYPE_REPORT_REOPENED = 'report_reopened';

    protected $table = 'nawasara_citizen_notifications';

    protected $fillable = ['keycloak_sub', 'type', 'title', 'body', 'report_code', 'read_at'];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public static function retentionDays(): int
    {
        return max(1, (int) config('nawasara-citizen.inbox.retention_days', 30));
    }

    /**
     * Never throws into the caller: an inbox write that fails must not undo
     * the status change that caused it (same rule as the push and email).
     */
    public static function record(string $keycloakSub, string $type, string $title, string $body, ?string $reportCode = null): ?self
    {
        if ($keycloakSub === '') {
            return null;
        }

        try {
            return static::create([
                'keycloak_sub' => $keycloakSub,
                'type' => $type,
                'title' => mb_substr($title, 0, 255),
                'body' => $body,
                'report_code' => $reportCode,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('citizen: inbox write failed', [
                'type' => $type, 'report_code' => $reportCode, 'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * The caller's own entries within the retention window. Older rows can
     * still exist until the nightly prune; they must not show in the meantime,
     * or the app's "kept for 30 days" text would be wrong for a few hours.
     */
    public function scopeInboxOf(Builder $query, string $keycloakSub): Builder
    {
        return $query->where('keycloak_sub', $keycloakSub)
            ->where('created_at', '>=', now()->subDays(static::retentionDays()));
    }
}
