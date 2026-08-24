<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor HP warga, bila Keycloak mengirimkannya.
 *
 * Portal SSO warga sudah meminta nomor WhatsApp saat pendaftaran — verifikasi
 * lewat WA sendiri di-nonaktifkan (WAGO terkena blokir), tetapi nomornya tetap
 * disimpan. Bila mapper Keycloak mengirimkannya sebagai klaim, kolom ini
 * terisi; bila tidak, ia tetap null dan aplikasi menampilkan "belum diisi".
 *
 * ⚠️ Nomor HP adalah data pribadi, dan ia TIDAK boleh ikut keluar dari
 * endpoint publik mana pun. Ia hanya dikirim ke pemiliknya sendiri lewat
 * `/citizen/me`, yang identitasnya berasal dari `sub` pada token — bukan dari
 * parameter yang dapat diganti orang lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nawasara_citizen_profiles', function (Blueprint $table) {
            $table->string('phone', 25)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('nawasara_citizen_profiles', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
