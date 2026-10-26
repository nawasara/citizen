<?php

namespace Nawasara\Citizen\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nawasara\Citizen\Models\CitizenNotification;

/**
 * The signed-in citizen's notification inbox.
 *
 *   GET   /api/v1/citizen/notifications?page=1
 *   PATCH /api/v1/citizen/notifications/{id}/read
 *   PATCH /api/v1/citizen/notifications/read-all
 *
 * Push passes once. A citizen whose phone was in a pocket, who swiped the
 * notice away, or who refused notification permission had no way to see what
 * they missed, including "your report is resolved", which is also their only
 * prompt to rate it.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sub = $this->sub($request);

        $page = CitizenNotification::query()
            ->inboxOf($sub)
            ->latest('created_at')
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'data' => collect($page->items())->map(fn (CitizenNotification $n) => $this->present($n))->all(),
            'meta' => [
                // Across the whole inbox, not this page: the bell badge reads
                // it, and a per-page count goes wrong past the first page.
                'unread' => CitizenNotification::query()->inboxOf($sub)->whereNull('read_at')->count(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'retention_days' => CitizenNotification::retentionDays(),
            ],
        ]);
    }

    /**
     * Idempotent: marking an already-read entry keeps its first read_at.
     * 404 for an id that is not the caller's, same as one that does not
     * exist, so the endpoint cannot be used to probe other citizens' ids.
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = CitizenNotification::query()->inboxOf($this->sub($request))->find($id);

        if (! $notification) {
            return response()->json([
                'error' => ['code' => 'not_found', 'message' => 'Notifikasi tidak ditemukan.'],
            ], 404);
        }

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return response()->json(['data' => $this->present($notification)]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = CitizenNotification::query()
            ->inboxOf($this->sub($request))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['data' => ['updated' => $updated, 'unread' => 0]]);
    }

    /**
     * Allow-list, not toArray(): keycloak_sub and updated_at stay home, and a
     * column added later does not leak by default (AGENTS.md §10a).
     */
    protected function present(CitizenNotification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'body' => $n->body,
            'report_code' => $n->report_code,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }

    /** Set by the api.citizen middleware from the verified JWT, never from input. */
    protected function sub(Request $request): string
    {
        return (string) $request->attributes->get('citizen_sub');
    }
}
