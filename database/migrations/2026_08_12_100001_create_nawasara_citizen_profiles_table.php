<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Citizen profile — the local half of a Keycloak citizen account.
     *
     * Rows are created when a citizen first signs in, not by a bulk sync job.
     * At tens of thousands of citizens a periodic pull would be expensive and
     * most of the data would never be read; someone who never signs in has no
     * reason to occupy a row.
     */
    public function up(): void
    {
        Schema::create('nawasara_citizen_profiles', function (Blueprint $table) {
            $table->id();

            // The Keycloak `sub`. This is the join key for every citizen-owned
            // record across Nawasara, and it never changes — not when the
            // citizen edits their email, not when they later link a Google
            // account. Matching on username or email instead has already
            // broken silently once (see commit fa37d75 on the staff side).
            $table->string('keycloak_sub')->unique();

            $table->string('full_name')->nullable();
            $table->string('email')->nullable()->index();

            // ── Domicile address ──────────────────────────────────────────
            // NOT the location of anything the citizen reports. Someone living
            // in Babadan can report illegal parking at the Alun-alun; filling
            // this from that report's geocoding would make it wrong, and
            // wronger with every report they file.
            //
            // Nullable throughout: registration never asks for an address.
            // Nothing in Fase 1 needs it, and asking for data before its
            // purpose is visible is what drives people away mid-signup.
            $table->text('address')->nullable();

            // Kept as plain strings alongside the FK, deliberately. When these
            // are filled from geocoding the spelling is whatever the provider
            // returned, and a village_id lookup may fail — the raw value stays
            // as evidence of what was actually received.
            $table->string('village')->nullable();
            $table->string('district')->nullable()->index();

            // Nullable, and NO cascade: tidying the master village list must
            // never delete a citizen's profile. A failed match leaves this
            // null and costs nothing.
            $table->foreignId('village_id')->nullable();

            // 'manual' | 'geocoding' | 'empty' — tells a later reader whether
            // an address was typed by the citizen or derived, which matters
            // when judging how much to trust an aggregate.
            $table->string('address_source', 16)->default('empty');

            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_citizen_profiles');
    }
};
