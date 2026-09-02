<?php

namespace Nawasara\Citizen\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Pasangan kode kecamatan–desa.
 *
 * Alamat menentukan ke OPD mana laporan disalurkan, jadi pasangan yang keliru
 * bukan sekadar data kotor — ia menyalurkan laporan warga ke perangkat daerah
 * yang salah, dan warganya tidak pernah tahu.
 *
 * Yang diuji di sini adalah sifat kode BPS yang membuat pemeriksaan ini
 * mungkin **tanpa tabel desa sama sekali**: kode desa selalu BERAWALAN kode
 * kecamatannya. Itu penting selama data desa belum ada — pemeriksaannya sudah
 * bekerja lebih dulu.
 */
class RegionCodePairingTest extends TestCase
{
    /** Cerminan aturan di ProfileController::validateRegionCodes(). */
    private function pasangan(?string $district, ?string $village): string
    {
        if ($village === null || $village === '') {
            return 'diterima';
        }

        if ($district === null || $district === '') {
            return 'district_required';
        }

        return str_starts_with($village, $district) ? 'diterima' : 'village_district_mismatch';
    }

    public function test_desa_di_kecamatannya_diterima(): void
    {
        $this->assertSame('diterima', $this->pasangan('3502110', '3502110001'));
    }

    /** Inti pemeriksaannya. */
    public function test_desa_dari_kecamatan_lain_ditolak(): void
    {
        $this->assertSame(
            'village_district_mismatch',
            $this->pasangan('3502110', '3502020001'),
            'Desa Slahung tidak boleh lolos saat kecamatannya Ponorogo.'
        );
    }

    /** Desa sendirian tidak dapat diperiksa, jadi tidak boleh diterima. */
    public function test_desa_tanpa_kecamatan_ditolak(): void
    {
        $this->assertSame('district_required', $this->pasangan(null, '3502110001'));
    }

    /**
     * Profil lama tidak punya kode sama sekali — harus tetap dapat disimpan.
     *
     * Menolaknya berarti warga yang sudah terdaftar tidak bisa lagi menyunting
     * alamatnya sampai memilih ulang wilayahnya.
     */
    public function test_tanpa_kode_sama_sekali_diterima(): void
    {
        $this->assertSame('diterima', $this->pasangan(null, null));
        $this->assertSame('diterima', $this->pasangan('3502110', null));
    }

    /**
     * Kode BPS harus diperlakukan sebagai TEKS.
     *
     * Sebagian kode wilayah berawalan nol; disimpan sebagai angka, nol itu
     * hilang dan kodenya tidak lagi cocok dengan apa pun.
     */
    public function test_kode_diperlakukan_sebagai_teks(): void
    {
        $this->assertSame('diterima', $this->pasangan('0102030', '0102030001'));
        $this->assertNotSame('0102030', (string) (int) '0102030');
    }
}
