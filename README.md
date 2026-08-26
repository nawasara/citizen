# nawasara/citizen

Identitas dan profil warga untuk framework superapp Nawasara. Menautkan akun
Keycloak realm warga (`ponorogo-citizen`) ke profil lokal, dan menyajikan
layar Akun di aplikasi warga.

## Status v0.2.0

| Fitur | Status |
|---|---|
| Provisioning profil saat login pertama | ✅ siap |
| `GET /citizen/me` — layar Akun + statistik kontribusi | ✅ siap |
| `GET|PATCH /citizen/profile` — alamat domisili | ✅ siap |
| NIK terenkripsi + penanda terverifikasi | ✅ siap |
| Panel Data Warga | ✅ siap |
| Nomor HP dari klaim Keycloak | ✅ siap — null bila mapper belum dipasang |
| Verifikasi NIK ke Dukcapil | ⏳ menyusul |

## Provisioning: sengaja tanpa sync job

Profil dibuat **saat warga login**, bukan disinkronkan dari Keycloak secara
berkala. Konsekuensinya perlu dipahami: panel Data Warga menampilkan **warga
yang pernah memakai aplikasi**, bukan seluruh populasi yang terdaftar di
realm.

Itu keputusan sadar. Menarik seluruh direktori realm berarti menyimpan data
orang yang belum pernah berurusan dengan sistem ini, dan menambah kewajiban
menjaganya tanpa manfaat yang sepadan.

## Setup

```bash
composer require nawasara/citizen
php artisan migrate
php artisan db:seed --class="Nawasara\Citizen\Database\Seeders\PermissionSeeder"
```

Tambahkan di `resources/css/app.css`:

```css
@source "../../vendor/nawasara/citizen";
```

## Endpoint warga

Semuanya di belakang `api.citizen` — JWT Keycloak realm warga, bukan token
`nws_`. Identitas selalu berasal dari klaim `sub` pada token yang sudah
diverifikasi, **tidak pernah dari parameter**. Tidak ada rute yang menerima
id, jadi tidak ada yang dapat ditelusuri orang lain.

```
GET   /api/v1/citizen/me         layar Akun + statistik kontribusi
GET   /api/v1/citizen/profile    profil + alamat
PATCH /api/v1/citizen/profile    ubah alamat domisili
```

### `GET /citizen/me`

```jsonc
{
  "data": {
    "name": "Sedulur Ponorogo",
    "email": "warga@example.id",
    "phone": "0812…",              // null bila Keycloak tidak mengirimnya
    "nik_verified": true,          // PENANDA saja, bukan nomornya
    "address": { "village": "Ngebel", "district": "Ngebel" },
    "stats": {
      "reports": 5,
      "in_progress": 3,
      "supports_given": 47,
      "ratings_given": 3
    }
  }
}
```

Terpisah dari `/citizen/profile` dengan sengaja: yang itu tentang alamat dan
penyuntingannya, yang ini tentang apa yang ditampilkan satu layar. Bentuknya
dipatok untuk aplikasi yang sudah terpasang — mengubah nama kunci setelah
rilis merusak aplikasi lama, dan pemakainya mayoritas.

Hanya alamat yang dapat disunting. Nama dan email berasal dari Keycloak dan
ditimpa ulang saat login berikutnya, jadi menerimanya di sini hanya akan
menghasilkan perubahan yang diam-diam hilang.

## NIK: disimpan terpisah, tidak pernah dikirim

NIK **tidak** ada di tabel profil. Ia hidup di `CitizenNikRecord` — terenkripsi,
dengan hash terpisah untuk pencarian — sehingga membacanya adalah tindakan
yang berbeda dari membaca profil, dan dapat digerbang izin tersendiri.

API warga hanya mengirim `nik_verified`. Mengirim nomornya berarti nomor induk
kependudukan tersimpan di ponsel yang dapat hilang atau dipinjam.

## Statistik kontribusi

`CitizenStats` membaca tabel `nawasara/aspirations` **lewat query builder,
bukan modelnya** — paket ini tidak boleh menuntut aspirations terpasang.
Pemasangan yang hanya memakai SSO warga tanpa pelaporan adalah keadaan yang
sah, dan mengimpor `Report::class` akan menjatuhkan layar Akun dengan
"class not found" pada pemasangan seperti itu.

Keberadaan tabelnya diperiksa lebih dulu; angkanya nol bila tidak ada. Nol
yang jujur lebih baik daripada layar yang jatuh.

## Nomor HP

Dibaca dari beberapa nama klaim (`phone_number`, `phone`, `whatsapp`,
`nomor_hp`), karena mapper Keycloak dapat dipasang dengan nama apa pun dan
portal SSO warga menamainya berbeda dari standar OIDC.

Klaim kosong **tidak pernah menimpa** nomor yang sudah tersimpan: realm yang
belum memasang mapper mengirim klaim kosong pada setiap login, dan menimpanya
akan menghapus nomor yang sudah pernah tercatat.

## Permissions

| Permission | Untuk |
|---|---|
| `citizen.profile.view` | Melihat daftar warga di panel |
| `citizen.nik.view` | Melihat NIK — dipisah karena data pribadi |
| `citizen.nik.verify` | Menandai NIK terverifikasi |

## Roadmap

- Verifikasi NIK terhadap data Dukcapil
- Riwayat login per warga
- Penggabungan akun (warga yang mendaftar dua kali)

## Author

Pringgo J. Saputro — Dinas Kominfo Kabupaten Ponorogo

## License

MIT
