<?php

namespace Nawasara\Citizen\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Nawasara\Citizen\Models\CitizenDevice;

/**
 * Push-notification devices of the signed-in citizen.
 *
 *   POST   /api/v1/citizen/devices   { token, platform, app_version? }
 *   DELETE /api/v1/citizen/devices   { token }
 *
 * The app registers after sign-in and whenever FCM hands it a new token, and
 * deletes on sign-out. That delete matters: without it, whoever uses the
 * phone next receives the previous person's report updates.
 *
 * Nothing here takes a device id. The citizen is always the one on the token,
 * and the app addresses a device by the FCM token it already holds.
 */
class DeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:32', 'max:512', 'regex:/^[A-Za-z0-9_\-:]+$/'],
            'platform' => ['nullable', 'string', 'in:'.implode(',', CitizenDevice::PLATFORMS)],
            'app_version' => ['nullable', 'string', 'max:32'],
        ]);

        $device = CitizenDevice::register(
            $this->sub($request),
            $data['token'],
            $data['platform'] ?? 'android',
            $data['app_version'] ?? null,
        );

        // Deliberately minimal. Echoing the token back adds nothing the app
        // does not already hold, and the id is internal (AGENTS.md §10a).
        return response()->json(['data' => [
            'platform' => $device->platform,
            'registered_at' => $device->created_at?->toIso8601String(),
        ]], $device->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Only the caller's own token is removed. 204 even when nothing matched:
     * sign-out must succeed whether or not the token was ever registered, or
     * the app is stuck retrying a delete on a session that has ended.
     */
    public function destroy(Request $request): Response
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        CitizenDevice::where('keycloak_sub', $this->sub($request))
            ->where('token', $data['token'])
            ->delete();

        return response()->noContent();
    }

    /** Set by the api.citizen middleware from the verified JWT, never from input. */
    protected function sub(Request $request): string
    {
        return (string) $request->attributes->get('citizen_sub');
    }
}
