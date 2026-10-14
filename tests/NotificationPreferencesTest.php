<?php

namespace Nawasara\Citizen\Tests;

use Nawasara\Citizen\Support\NotificationPreferences as Prefs;
use PHPUnit\Framework\TestCase;

/**
 * Pilihan notifikasi warga: bawaan, penggabungan perubahan, dan bentuk yang
 * dikirim ke aplikasi.
 */
class NotificationPreferencesTest extends TestCase
{
    public function test_belum_pernah_diubah_memakai_bawaan(): void
    {
        $this->assertSame(
            [Prefs::REPORT_PROGRESS => true, Prefs::REPORT_RESULT => true],
            Prefs::resolve(null),
        );
    }

    public function test_pilihan_tersimpan_menimpa_bawaan_dan_kunci_asing_diabaikan(): void
    {
        $resolved = Prefs::resolve([Prefs::REPORT_PROGRESS => false, 'promo' => true, Prefs::REPORT_RESULT => 'x']);

        $this->assertFalse($resolved[Prefs::REPORT_PROGRESS]);
        $this->assertTrue($resolved[Prefs::REPORT_RESULT]);
        $this->assertArrayNotHasKey('promo', $resolved);
    }

    /** Mengubah satu sakelar tidak boleh mengembalikan sakelar lain ke bawaan. */
    public function test_merge_hanya_mengubah_kunci_yang_dikirim(): void
    {
        $merged = Prefs::merge([Prefs::REPORT_PROGRESS => false], [Prefs::REPORT_RESULT => false]);

        $this->assertSame([Prefs::REPORT_PROGRESS => false, Prefs::REPORT_RESULT => false], $merged);
    }

    /** Yang belum disentuh tidak disimpan, supaya perubahan bawaan kelak tetap berlaku. */
    public function test_merge_tidak_menyimpan_yang_tidak_disentuh(): void
    {
        $this->assertSame([Prefs::REPORT_RESULT => false], Prefs::merge(null, [Prefs::REPORT_RESULT => false]));
    }

    public function test_describe_memuat_semua_topik_berurutan(): void
    {
        $rows = Prefs::describe([Prefs::REPORT_PROGRESS => false]);

        $this->assertSame(array_keys(Prefs::TOPICS), array_column($rows, 'key'));
        $this->assertFalse($rows[0]['enabled']);
        $this->assertSame(['key', 'group', 'label', 'description', 'enabled', 'locked'], array_keys($rows[0]));
    }

    /** Pengirim baru yang lupa mendaftarkan topiknya tetap mengirim. */
    public function test_topik_tak_dikenal_diizinkan(): void
    {
        $this->assertTrue(Prefs::allows('sub-apa-saja', 'topik_baru'));
    }

    public function test_tanpa_sub_memakai_bawaan(): void
    {
        $this->assertTrue(Prefs::allows(null, Prefs::REPORT_RESULT));
    }
}
