<?php

namespace Nawasara\Citizen\Tests;

use Nawasara\Citizen\Support\NotificationPreferences as Prefs;
use PHPUnit\Framework\TestCase;

/**
 * Sakelar notifikasi warga per kanal dan topik: bawaan, penggabungan
 * perubahan, dan bentuk yang dikirim ke aplikasi.
 */
class NotificationPreferencesTest extends TestCase
{
    private const PUSH_PROGRESS = 'push.report_progress';

    private const PUSH_RESULT = 'push.report_result';

    private const EMAIL_PROGRESS = 'email.report_progress';

    private const EMAIL_RESULT = 'email.report_result';

    public function test_belum_pernah_diubah_semua_menyala(): void
    {
        $this->assertSame(
            [self::PUSH_PROGRESS => true, self::PUSH_RESULT => true, self::EMAIL_PROGRESS => true, self::EMAIL_RESULT => true],
            Prefs::resolve(null),
        );
    }

    /** Inti permintaannya: email dapat dimatikan tanpa ikut mematikan push. */
    public function test_kanal_berdiri_sendiri(): void
    {
        $resolved = Prefs::resolve([self::EMAIL_PROGRESS => false, self::EMAIL_RESULT => false]);

        $this->assertTrue($resolved[self::PUSH_PROGRESS]);
        $this->assertTrue($resolved[self::PUSH_RESULT]);
        $this->assertFalse($resolved[self::EMAIL_PROGRESS]);
        $this->assertFalse($resolved[self::EMAIL_RESULT]);
    }

    public function test_kunci_asing_dan_nilai_bukan_boolean_diabaikan(): void
    {
        $resolved = Prefs::resolve(['promo' => true, self::PUSH_RESULT => 'x', 'report_result' => false]);

        $this->assertArrayNotHasKey('promo', $resolved);
        $this->assertTrue($resolved[self::PUSH_RESULT]);
    }

    /** Mengubah satu sakelar tidak boleh mengembalikan sakelar lain ke bawaan. */
    public function test_merge_hanya_mengubah_kunci_yang_dikirim(): void
    {
        $merged = Prefs::merge([self::PUSH_PROGRESS => false], [self::EMAIL_RESULT => false]);

        $this->assertSame([self::PUSH_PROGRESS => false, self::EMAIL_RESULT => false], $merged);
    }

    public function test_describe_dikelompokkan_per_kanal_berurutan(): void
    {
        $rows = Prefs::describe([self::EMAIL_PROGRESS => false]);

        $this->assertSame(
            [self::PUSH_PROGRESS, self::PUSH_RESULT, self::EMAIL_PROGRESS, self::EMAIL_RESULT],
            array_column($rows, 'key'),
        );
        $this->assertSame(
            ['Notifikasi Ponsel', 'Notifikasi Ponsel', 'Email', 'Email'],
            array_column($rows, 'group'),
        );
        $this->assertFalse($rows[2]['enabled']);
        $this->assertSame(['key', 'group', 'label', 'description', 'enabled', 'locked'], array_keys($rows[0]));
    }

    /** Pengirim atau kanal baru yang belum terdaftar tetap mengirim. */
    public function test_topik_atau_kanal_tak_dikenal_diizinkan(): void
    {
        $this->assertTrue(Prefs::allows('sub-apa-saja', 'topik_baru', Prefs::CHANNEL_PUSH));
        $this->assertTrue(Prefs::allows('sub-apa-saja', Prefs::REPORT_RESULT, 'whatsapp'));
    }

    public function test_tanpa_sub_memakai_bawaan(): void
    {
        $this->assertTrue(Prefs::allows(null, Prefs::REPORT_RESULT, Prefs::CHANNEL_EMAIL));
    }
}
