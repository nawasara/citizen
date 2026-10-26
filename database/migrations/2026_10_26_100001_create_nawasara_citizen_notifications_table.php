<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The citizen's notification inbox: what the app shows behind the bell.
 *
 * Why not read nawasara_notification_logs instead: that table holds one row
 * per channel per address (an FCM token, an email), carries no keycloak_sub,
 * and has NO row at all for a citizen who turned push and email off or has no
 * registered device — the very people who need an inbox most, because push is
 * the thing they are missing. Its bodies are also stripped after 30 days by
 * the notification prune. One row here per event per citizen, written whatever
 * the channel switches say.
 *
 * UUID id: the app sends it back in PATCH /notifications/{id}/read, so it is
 * referenced from outside (AGENTS.md §10a). Every query is still scoped to the
 * caller's keycloak_sub; the UUID is a second layer, not the guard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_citizen_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('keycloak_sub', 64);
            $table->string('type', 40);
            $table->string('title', 255);
            $table->text('body');
            $table->string('report_code', 32)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // The two questions the app asks: "my latest" and "how many unread".
            $table->index(['keycloak_sub', 'created_at']);
            $table->index(['keycloak_sub', 'read_at']);
            $table->index('created_at'); // retention prune
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_citizen_notifications');
    }
};
