<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pilihan notifikasi warga.
 *
 * JSON, bukan satu kolom per sakelar: daftar topiknya akan bertambah
 * (peringatan bencana, pengumuman) dan tiap tambahan tidak perlu migrasi.
 *
 * NULL berarti "belum pernah diubah", dan yang berlaku adalah bawaan di
 * NotificationPreferences::TOPICS. Hanya sakelar yang pernah diubah warga yang
 * disimpan, sehingga mengubah bawaan kelak berlaku bagi semua warga yang
 * belum menyentuhnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nawasara_citizen_profiles', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable()->after('address_source');
        });
    }

    public function down(): void
    {
        Schema::table('nawasara_citizen_profiles', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
