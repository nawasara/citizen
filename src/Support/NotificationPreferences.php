<?php

namespace Nawasara\Citizen\Support;

use Nawasara\Citizen\Models\CitizenProfile;

/**
 * Sakelar notifikasi warga, per TOPIK dan per KANAL, dan apakah sebuah kabar
 * boleh dikirim ke warga tertentu.
 *
 * Kuncinya `<kanal>.<topik>`, misalnya `email.report_progress`. Tiap kanal
 * menjadi satu kelompok di layar aplikasi, berisi topik yang sama. Warga yang
 * terganggu email dapat mematikannya tanpa ikut kehilangan push, dan
 * sebaliknya: satu sakelar untuk dua kanal memaksa pilihan semua-atau-tidak.
 *
 * ⚠️ Hanya topik yang BENAR-BENAR ada pengirimnya yang dicantumkan. Mockup
 * aplikasi memuat sakelar "Diteruskan ke OPD", "Berita penting", dan
 * "Lowongan kerja"; tidak satu pun dikirim server saat ini. Sakelar yang tidak
 * mengubah apa pun mengajari warga bahwa pengaturan ini tidak berarti.
 *
 * Daftar ini dikirim ke aplikasi apa adanya (label, keterangan, kelompok),
 * sehingga topik atau kanal baru muncul di layar tanpa menunggu rilis aplikasi.
 *
 * Topik `locked` selalu menyala di semua kanal dan tidak dapat dimatikan.
 * Disiapkan untuk peringatan bencana: keselamatan tidak boleh bergantung pada
 * sakelar yang pernah dimatikan warga karena merasa terganggu.
 */
final class NotificationPreferences
{
    public const CHANNEL_PUSH = 'push';

    public const CHANNEL_EMAIL = 'email';

    /** Sedang ditangani, dibuka kembali. */
    public const REPORT_PROGRESS = 'report_progress';

    /** Selesai, ditolak. */
    public const REPORT_RESULT = 'report_result';

    /** Urutan di sini menjadi urutan kelompok di layar. */
    public const CHANNELS = [
        self::CHANNEL_PUSH => 'Notifikasi Ponsel',
        self::CHANNEL_EMAIL => 'Email',
    ];

    /**
     * @var array<string, array{label:string, description:string, default:bool, locked:bool}>
     */
    public const TOPICS = [
        self::REPORT_PROGRESS => [
            'label' => 'Perkembangan laporan',
            'description' => 'Saat petugas mulai menangani laporan, atau laporan dibuka kembali',
            'default' => true,
            'locked' => false,
        ],
        self::REPORT_RESULT => [
            'label' => 'Hasil laporan',
            'description' => 'Saat laporan dinyatakan selesai atau tidak dapat diproses',
            'default' => true,
            'locked' => false,
        ],
    ];

    public static function key(string $channel, string $topic): string
    {
        return $channel.'.'.$topic;
    }

    /**
     * Semua sakelar, berurutan: kanal demi kanal, topik demi topik.
     *
     * @return array<string, array{channel:string, topic:string, label:string, description:string, default:bool, locked:bool}>
     */
    public static function switches(): array
    {
        $out = [];

        foreach (self::CHANNELS as $channel => $group) {
            foreach (self::TOPICS as $topic => $def) {
                $out[self::key($channel, $topic)] = ['channel' => $channel, 'topic' => $topic] + $def;
            }
        }

        return $out;
    }

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

        foreach (self::switches() as $key => $switch) {
            $resolved[$key] = $switch['locked']
                ? true
                : (is_bool($stored[$key] ?? null) ? $stored[$key] : $switch['default']);
        }

        return $resolved;
    }

    /**
     * Bentuk untuk aplikasi.
     *
     * @param  array<string,mixed>|null  $stored
     * @return array<int, array{key:string, group:string, label:string, description:string, enabled:bool, locked:bool}>
     */
    public static function describe(?array $stored): array
    {
        $resolved = self::resolve($stored);
        $out = [];

        foreach (self::switches() as $key => $switch) {
            $out[] = [
                'key' => $key,
                'group' => self::CHANNELS[$switch['channel']],
                'label' => $switch['label'],
                'description' => $switch['description'],
                'enabled' => $resolved[$key],
                'locked' => $switch['locked'],
            ];
        }

        return $out;
    }

    /**
     * Gabungkan perubahan dari aplikasi ke pilihan tersimpan.
     *
     * Hanya kunci yang dikirim yang berubah; sakelar lain tetap. Yang belum
     * pernah disentuh tidak disimpan, sehingga mengubah bawaan kelak berlaku
     * bagi semua warga yang belum menyentuhnya. Topik terkunci tidak pernah
     * disimpan.
     *
     * @param  array<string,mixed>|null  $stored
     * @param  array<string,bool>  $changes  sudah divalidasi pemanggil
     * @return array<string,bool>
     */
    public static function merge(?array $stored, array $changes): array
    {
        $merged = [];

        foreach (self::switches() as $key => $switch) {
            if ($switch['locked']) {
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
     * Boleh mengirim topik ini lewat kanal ini ke warga ini?
     *
     * Warga tanpa profil memakai bawaan. Topik atau kanal yang tidak dikenal
     * DIIZINKAN: pengirim baru yang lupa mendaftarkannya lebih baik tetap
     * sampai daripada diam-diam tidak terkirim ke siapa pun.
     */
    public static function allows(?string $keycloakSub, string $topic, string $channel): bool
    {
        $switch = self::switches()[self::key($channel, $topic)] ?? null;

        if ($switch === null) {
            return true;
        }

        if ($keycloakSub === null || $keycloakSub === '') {
            return $switch['locked'] || $switch['default'];
        }

        $stored = CitizenProfile::forSub($keycloakSub)->value('notification_preferences');

        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        return self::resolve(is_array($stored) ? $stored : null)[self::key($channel, $topic)];
    }
}
