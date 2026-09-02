<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode wilayah pada profil warga.
 *
 * Selama ini desa dan kecamatan disimpan sebagai TEKS BEBAS, dan itu bukan
 * sekadar data yang kotor: alamat menentukan ke OPD mana laporan disalurkan.
 * "Ngrayun", "Ngerayun", dan "Ngrayon" menjadi tiga kecamatan berbeda di basis
 * data padahal maksudnya satu — dan warga yang salah ketik tidak pernah tahu
 * laporannya salah alamat.
 *
 * Kolom teksnya TETAP ADA dan tetap diisi. Nama yang dipakai untuk ditampilkan,
 * kode yang menjadi kebenaran. Membuang kolom teks berarti menghapus alamat
 * warga yang sudah terlanjur mengisi.
 *
 * Dikerjakan sekarang justru karena baru ada 2 profil di produksi: mengubahnya
 * setelah ribuan warga mendaftar berarti memigrasi tebakan ejaan, dan tebakan
 * yang keliru menyalurkan laporan ke kecamatan yang salah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nawasara_citizen_profiles', function (Blueprint $table) {
            // Kode BPS: kecamatan 7 digit (3502110), desa 10 digit
            // (3502110001). Disimpan sebagai string, bukan angka — angka
            // membuang nol di depan, dan sebagian kode wilayah memilikinya.
            $table->string('district_code', 10)->nullable()->after('district')->index();
            $table->string('village_code', 13)->nullable()->after('district_code');
        });
    }

    public function down(): void
    {
        Schema::table('nawasara_citizen_profiles', function (Blueprint $table) {
            $table->dropIndex(['district_code']);
            $table->dropColumn(['district_code', 'village_code']);
        });
    }
};
