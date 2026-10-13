<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devices that receive push notifications for a citizen.
 *
 * Keyed by `keycloak_sub`, like the rest of this package: a citizen exists
 * here because they signed in, not because a profile row was made first.
 *
 * bigint id: the row is never referenced from outside (see AGENTS.md §10a).
 * The app addresses a device by its token, which it already holds.
 *
 * The token is UNIQUE across citizens on purpose. One phone holds one token;
 * if a second person signs in on it, the row moves to them. Leaving the old
 * row would send the first person's report updates to whoever holds the
 * phone now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_citizen_devices', function (Blueprint $table) {
            $table->id();
            $table->string('keycloak_sub', 64)->index();

            // FCM tokens run to ~160 characters today; 512 leaves room for
            // Google lengthening them and stays inside the utf8mb4 index limit.
            $table->string('token', 512)->unique();

            $table->string('platform', 16)->default('android');
            $table->string('app_version', 32)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_citizen_devices');
    }
};
