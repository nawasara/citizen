<?php

namespace Nawasara\Citizen\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Angka kontribusi warga untuk layar Akun.
 *
 * ⚠️ Membaca tabel milik `nawasara/aspirations` LANGSUNG lewat query builder,
 * bukan lewat modelnya.
 *
 * Alasannya: `nawasara/citizen` tidak boleh menuntut `nawasara/aspirations`
 * terpasang. Keduanya paket terpisah, dan pemasangan yang hanya memakai SSO
 * warga tanpa pelaporan adalah keadaan yang sah. Mengimpor `Report::class`
 * akan membuat layar Akun jatuh dengan "class not found" pada pemasangan
 * seperti itu — kegagalan yang sama sekali tidak berhubungan dengan apa yang
 * sedang dilihat warga.
 *
 * Karena itu keberadaan tabelnya diperiksa lebih dulu, dan angkanya nol bila
 * tidak ada. Nol yang jujur lebih baik daripada layar yang jatuh.
 */
class CitizenStats
{
    protected const TABLE_REPORTS = 'nawasara_aspirations_reports';
    protected const TABLE_SUPPORTS = 'nawasara_aspirations_supports';

    /**
     * @return array{reports:int, in_progress:int, supports_given:int, ratings_given:int}
     */
    public function for(string $keycloakSub): array
    {
        if (! Schema::hasTable(self::TABLE_REPORTS)) {
            return $this->kosong();
        }

        $laporan = DB::table(self::TABLE_REPORTS)->where('keycloak_sub', $keycloakSub);

        return [
            'reports' => (clone $laporan)->count(),

            // "Sedang diproses" menurut pemahaman warga, bukan menurut status
            // internal: mereka tidak membedakan `dispatched` dari
            // `in_progress` dari `awaiting_verification`, dan tidak perlu.
            // Yang mereka tanyakan hanya "sudah selesai atau belum".
            'in_progress' => (clone $laporan)->whereIn('status', [
                'dispatched',
                'in_progress',
                'awaiting_verification',
            ])->count(),

            'supports_given' => Schema::hasTable(self::TABLE_SUPPORTS)
                ? DB::table(self::TABLE_SUPPORTS)->where('keycloak_sub', $keycloakSub)->count()
                : 0,

            // Penilaian yang BENAR-BENAR diberikan. `rating` null berarti
            // warga memang tidak menilai — dan itu dibedakan dari nilai nol
            // dengan sengaja di skema aspirations.
            'ratings_given' => (clone $laporan)->whereNotNull('rating')->count(),
        ];
    }

    /** @return array{reports:int, in_progress:int, supports_given:int, ratings_given:int} */
    protected function kosong(): array
    {
        return [
            'reports' => 0,
            'in_progress' => 0,
            'supports_given' => 0,
            'ratings_given' => 0,
        ];
    }
}
