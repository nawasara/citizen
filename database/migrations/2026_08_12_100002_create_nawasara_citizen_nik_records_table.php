<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * NIK — deliberately its own table, not a column on profiles.
     *
     * As a column, every `SELECT *` carries it along: into logs, into API
     * responses, into debug dumps, into anything that ever serialises a
     * profile. A separate table makes reading a NIK a deliberate act rather
     * than a side effect, which is the point — it is protected personal data
     * under UU 27/2022, and access to it has to be defensible.
     *
     * Keycloak holds only the `nik_verified` marker, never the number itself:
     * its attributes are stored as plain text, appear in the event log, and
     * are awkward to erase selectively when a citizen asks for deletion.
     */
    public function up(): void
    {
        Schema::create('nawasara_citizen_nik_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('citizen_profile_id')
                ->constrained('nawasara_citizen_profiles')
                ->cascadeOnDelete();

            // Encrypted at the application layer (Laravel's encrypted cast).
            // text, not string: ciphertext is far longer than the 16 digits
            // it protects, and a varchar(255) would silently truncate it.
            $table->text('nik_encrypted');

            // Blind index. Encryption is non-deterministic, so two rows
            // holding the same NIK produce different ciphertext and cannot be
            // compared — this hash makes "is this NIK already registered?"
            // answerable without decrypting anything.
            //
            // Unique: one NIK belongs to one person. A duplicate means either
            // an error or an attempt to claim someone else's identity, and
            // both deserve to fail loudly here rather than be discovered later.
            $table->string('nik_hash', 64)->unique();

            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();

            // Who verified it. Nullable because verification is manual for now
            // — a Dukcapil/SIAK integration would leave this null and record
            // the source elsewhere.
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // One NIK record per profile.
            $table->unique('citizen_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_citizen_nik_records');
    }
};
