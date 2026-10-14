# nawasara/citizen

Citizen identity and profiles for the Nawasara superapp framework. It links a Keycloak account in the citizen realm (`ponorogo-citizen`) to a local profile, and serves the Account screen in the citizen app.

## Status v0.2.0

| Feature | Status |
|---|---|
| Profile provisioning on first login | ready |
| `GET /citizen/me`, Account screen plus contribution stats | ready |
| `GET|PATCH /citizen/profile`, home address | ready |
| Encrypted NIK plus verified flag | ready |
| Citizen Data panel | ready |
| Phone number from Keycloak claims | ready (null if the mapper is not installed) |
| NIK verification against Dukcapil | not built yet |

## Provisioning without a sync job

Profiles are created when a citizen logs in, not synced from Keycloak on a schedule. One consequence to be aware of: the Citizen Data panel shows the citizens who have used the app, not the whole population registered in the realm.

This is deliberate. Pulling the entire realm directory would mean storing data for people who have never touched this system, and taking on the duty of protecting it for no matching benefit.

## Setup

```bash
composer require nawasara/citizen
php artisan migrate
php artisan db:seed --class="Nawasara\Citizen\Database\Seeders\PermissionSeeder"
```

Add to `resources/css/app.css`:

```css
@source "../../vendor/nawasara/citizen";
```

## Citizen endpoints

All of them sit behind `api.citizen`, a Keycloak JWT from the citizen realm, not an `nws_` token. Identity always comes from the `sub` claim on the verified token, never from a parameter. No route accepts an id, so there is nothing for someone else to enumerate.

```
GET    /api/v1/citizen/me         Account screen plus contribution stats
GET    /api/v1/citizen/profile    profile plus address
PATCH  /api/v1/citizen/profile    update home address
POST   /api/v1/citizen/devices    register a push token
DELETE /api/v1/citizen/devices    forget a push token (on sign-out)
GET    /api/v1/citizen/notification-preferences   notification switches
PATCH  /api/v1/citizen/notification-preferences   change some of them
```

### Notification preferences

Stored as JSON on the profile (`notification_preferences`), topics defined in
`Nawasara\Citizen\Support\NotificationPreferences::TOPICS`. Senders ask
`NotificationPreferences::allows($sub, $topic)` before sending; aspirations'
`ReportNotifier` does so for push and email alike.

- **Only topics that have a sender are listed.** The app mockup shows switches
  for news and job alerts; none of those are sent, and a switch that changes
  nothing teaches people the settings screen is meaningless.
- **The app renders the list from the response**, labels included, so a new
  topic appears without an app release. `locked` topics (meant for disaster
  alerts) are always on and cannot be patched.
- **Only switches the citizen touched are stored.** Changing a default later
  then applies to everyone who never changed it.
- **Unknown topics are allowed by `allows()`.** A new sender that forgets to
  register its topic should still reach people, not go silent.

### Push devices

`nawasara_citizen_devices` holds the FCM tokens of each citizen, keyed by
`keycloak_sub`. The app contract is in `docs/api-superapps/notifikasi.md` of
the app repo.

- **The token is unique across citizens.** When someone else signs in on the
  same phone, the row moves to them. Keeping it under the old account would
  send the first person's report updates to whoever holds the phone now.
- **At most 10 devices per citizen**, oldest dropped first, so tokens from
  reinstalls over months do not pile up.
- **Dead tokens are deleted** when FCM rejects them: the provider listens for
  `Nawasara\Notification\Events\PushTokenRejected` by name, so this package
  does not depend on nawasara/notification.
- **DELETE always answers 204**, matched or not. Sign-out must not fail because
  a token was never registered.

### `GET /citizen/me`

```jsonc
{
  "data": {
    "name": "Sedulur Ponorogo",
    "email": "warga@example.id",
    "phone": "0812…",              // null if Keycloak does not send it
    "nik_verified": true,          // a flag only, not the number
    "address": { "village": "Ngebel", "district": "Ngebel" },
    "stats": {
      "reports": 5,
      "in_progress": 3,
      "supports_given": 47,
      "ratings_given": 3
    }
  }
}
```

This is kept separate from `/citizen/profile` on purpose: that one is about the address and editing it, this one is about what a single screen displays. Its shape is fixed for apps already installed in the field, since renaming a key after release breaks old apps, and most users are on old apps.

Only the address is editable. Name and email come from Keycloak and are overwritten on the next login, so accepting them here would only produce changes that quietly disappear.

## NIK: stored separately, never sent

The NIK is not in the profile table. It lives in `CitizenNikRecord`, encrypted, with a separate hash for lookup, so reading it is a distinct action from reading the profile and can be gated by its own permission.

The citizen API only sends `nik_verified`. Sending the number would mean the national ID sits on a phone that can be lost or borrowed.

## Contribution stats

`CitizenStats` reads the `nawasara/aspirations` tables through the query builder rather than the model, because this package must not require aspirations to be installed. An install that only uses citizen SSO without reporting is a valid state, and importing `Report::class` would break the Account screen with "class not found" on such an install.

The table's existence is checked first; the numbers are zero when it is absent. An honest zero is better than a screen that crashes.

## Phone number

Read from several claim names (`phone_number`, `phone`, `whatsapp`, `nomor_hp`), because a Keycloak mapper can be set up under any name and the citizen SSO portal names it differently from the OIDC standard.

An empty claim never overwrites a stored number: a realm that has not installed the mapper sends an empty claim on every login, and overwriting would erase a number already on record.

## Permissions

| Permission | For |
|---|---|
| `citizen.profile.view` | View the citizen list in the panel |
| `citizen.nik.view` | View the NIK, separated because it is personal data |
| `citizen.nik.verify` | Mark a NIK as verified |

## Roadmap

- NIK verification against Dukcapil data
- Per-citizen login history
- Account merging (citizens who register twice)

## Author

Pringgo J. Saputro, Dinas Kominfo Kabupaten Ponorogo

## License

MIT
