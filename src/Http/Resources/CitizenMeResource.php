<?php

namespace Nawasara\Citizen\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nawasara\Citizen\Models\CitizenProfile;

/**
 * Layar Akun — apa yang warga lihat tentang DIRINYA SENDIRI.
 *
 * Bentuknya mengikuti `docs/teknis/rencana/endpoint-yang-dibutuhkan.md` §2,
 * dan dipatok untuk aplikasi yang sudah terpasang: mengubah nama kunci setelah
 * rilis merusak aplikasi lama, dan pemakainya mayoritas.
 *
 * Berbeda dari [CitizenProfileResource] yang melayani `/citizen/profile` —
 * yang itu tentang alamat dan penyuntingannya, yang ini tentang tampilan
 * layar Akun beserta angka kontribusinya.
 *
 * ⚠️ **NIK tidak pernah dikirim, hanya penandanya.** Aplikasi hanya
 * menampilkan "terverifikasi" atau "belum"; mengirim nomornya berarti nomor
 * induk kependudukan warga tersimpan di ponsel yang dapat hilang atau
 * dipinjam.
 *
 * @mixin CitizenProfile
 */
class CitizenMeResource extends JsonResource
{
    /** @var array{reports:int, in_progress:int, resolved:int, supports_given:int, ratings_given:int} */
    protected array $stats;

    /**
     * @param  array{reports:int, in_progress:int, resolved:int, supports_given:int, ratings_given:int}  $stats
     */
    public function __construct(CitizenProfile $profile, array $stats)
    {
        parent::__construct($profile);

        $this->stats = $stats;
    }

    public function toArray(Request $request): array
    {
        return [
            'name' => $this->full_name,
            'email' => $this->email,

            // Null bila Keycloak tidak mengirimkan nomornya. Aplikasi
            // menuliskannya nullable dan menampilkan "belum diisi" — jauh
            // lebih baik daripada mengarang.
            'phone' => $this->phone,

            'nik_verified' => $this->hasVerifiedNik(),

            'address' => [
                'village' => $this->village,
                'district' => $this->district,
            ],

            'stats' => $this->stats,
        ];
    }
}
