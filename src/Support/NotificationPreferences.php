<?php

namespace Nawasara\Citizen\Support;

use Nawasara\Citizen\Models\CitizenProfile;

/**
 * Topik notifikasi yang dapat diatur warga, dan apakah sebuah topik boleh
 * dikirim ke warga tertentu.
 *
 * ⚠️ Hanya topik yang BENAR-BENAR ada pengirimnya yang dicantumkan. Mockup
 * aplikasi memuat sakelar "Diteruskan ke OPD", "Berita penting", dan
 * "Lowongan kerja"; tidak satu pun dikirim server saat ini. Sakelar yang tidak
 * mengubah apa pun mengajari warga bahwa pengaturan ini tidak berarti.
 *
 * Daftar ini dikirim ke aplikasi apa adanya (label, keterangan, kelompok),
 * sehingga topik baru muncul di layar tanpa menunggu rilis aplikasi.
 *
 * Topik `locked` selalu menyala dan tidak dapat dimatikan. Disiapkan untuk
 * peringatan bencana: keselamatan tidak boleh bergantung pada sakelar yang
 * pernah dimatikan warga karena merasa terganggu.
 */
final class NotificationPreferences
{
    /** Sedang ditangani, dibuka kembali. */
    public const REPORT_PROGRESS = 'report_progress';

    /** Selesai, ditolak. */
    public const REPORT_RESULT = 'report_result';

    /**
     * @var array<string, array{group:string, label:string, description:string, default:bool, locked:bool}>
     */
    public const TOPICS = [
        self::REPORT_PROGRESS => [
            'group' => 'Laporan Saya',
            'label' => 'Perkembangan laporan',
            'description' => 'Saat petugas mulai menangani laporan, atau laporan dibuka kembali',
            'default' => true,
            'locked' => false,
        ],
        self::REPORT_RESULT => [
            'group' => 'Laporan Saya',
            'label' => 'Hasil laporan',
            'description' => 'Saat laporan dinyatakan selesai atau tidak dapat diproses',
            'default' => true,
            'locked' => false,
        ],
    ];

    /**
     * Nilai yang berlaku: pilihan tersimpan di atas bawaan.
     *
     * Kunci tersimpan yang tidak lagi dikenal diabaikan, dan topik terkunci
     * selalu menyala apa pun isi basis datanya.
     *
     * @param  array<string,mixed>|null  $stored
     * @return array<string,bool>
     */
    public static function resolve(?array $stored): array
    {
        $resolved = [];

        foreach (self::TOPICS as $key => $topic) {
            $resolved[$key] = $topic['locked']
                ? true
                : (is_bool($stored[$key] ?? null) ? $stored[$key] : $topic['default']);
        }

        return $resolved;
    }

    /**
     * Bentuk untuk aplikasi, berurutan sesuai TOPICS.
     *
     * @param  array<string,mixed>|null  $stored
     * @return array<int, array{key:string, group:string, label:string, description:string, enabled:bool, locked:bool}>
     */
    public static function describe(?array $stored): array
    {
        $resolved = self::resolve($stored);
        $out = [];

        foreach (self::TOPICS as $key => $topic) {
            $out[] = [
                'key' => $key,
                'group' => $topic['group'],
                'label' => $topic['label'],
                'description' => $topic['description'],
                'enabled' => $resolved[$key],
                'locked' => $topic['locked'],
            ];
        }

        return $out;
    }

    /**
     * Gabungkan perubahan dari aplikasi ke pilihan tersimpan.
     *
     * Hanya kunci yang dikirim yang berubah; sakelar lain tetap. Topik
     * terkunci tidak pernah disimpan.
     *
     * @param  array<string,mixed>|null  $stored
     * @param  array<string,bool>  $changes  sudah divalidasi pemanggil
     * @return array<string,bool>
     */
    public static function merge(?array $stored, array $changes): array
    {
        $merged = [];

        foreach (self::TOPICS as $key => $topic) {
            if ($topic['locked']) {
                continue;
            }
            if (array_key_exists($key, $changes)) {
                $merged[$key] = (bool) $changes[$key];
            } elseif (is_bool($stored[$key] ?? null)) {
                $merged[$key] = $stored[$key];
            }
        }

        return $merged;
    }

    /**
     * Boleh mengirim topik ini ke warga ini?
     *
     * Warga tanpa profil memakai bawaan. Topik yang tidak dikenal DIIZINKAN:
     * pengirim baru yang lupa mendaftarkan topiknya lebih baik tetap sampai
     * daripada diam-diam tidak terkirim ke siapa pun.
     */
    public static function allows(?string $keycloakSub, string $topic): bool
    {
        if (! array_key_exists($topic, self::TOPICS)) {
            return true;
        }

        if ($keycloakSub === null || $keycloakSub === '') {
            return self::TOPICS[$topic]['default'];
        }

        $stored = CitizenProfile::forSub($keycloakSub)->value('notification_preferences');

        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        return self::resolve(is_array($stored) ? $stored : null)[$topic];
    }
}
