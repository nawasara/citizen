<div>
    <x-nawasara-ui::page-header
        title="Data Warga"
        description="Warga yang terdaftar melalui aplikasi PRAMANA"
        :count="$profiles->total()">

        <div class="flex items-center gap-2">
            <x-nawasara-ui::search-input
                wire:model.live.debounce.400ms="search"
                placeholder="Cari nama atau email…" />

            <x-nawasara-ui::filter-panel>
                <x-nawasara-ui::filter-group label="Kecamatan" wire:model.live="district">
                    <option value="">Semua kecamatan</option>
                    @foreach ($districts as $item)
                        <option value="{{ $item }}">{{ ucwords($item) }}</option>
                    @endforeach
                </x-nawasara-ui::filter-group>

                <x-nawasara-ui::filter-group label="Status NIK" wire:model.live="nikStatus">
                    <option value="">Semua</option>
                    <option value="verified">NIK terverifikasi</option>
                    <option value="unverified">Belum terverifikasi</option>
                </x-nawasara-ui::filter-group>
            </x-nawasara-ui::filter-panel>
        </div>
    </x-nawasara-ui::page-header>

    @if ($profiles->isEmpty())
        <x-nawasara-ui::empty-state
            icon="users-round"
            title="Belum ada warga terdaftar"
            description="Warga akan muncul di sini setelah masuk pertama kali lewat aplikasi." />
    @else
        {{-- Tanpa stickyLast: tabel ini belum punya kolom aksi, dan sticky
             cell membuat konteks tumpukan yang memotong tooltip. --}}
        <x-nawasara-ui::table :headers="['Nama', 'Email', 'Domisili', 'NIK', 'Masuk terakhir']">
            <x-slot:table>
                @foreach ($profiles as $profile)
                    <tr>
                        <td class="px-4 py-2.5 text-sm font-medium text-neutral-800 dark:text-neutral-100">
                            {{ $profile->full_name ?: '—' }}
                        </td>

                        <td class="px-4 py-2.5 text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $profile->email ?: '—' }}
                        </td>

                        {{-- Sebagian besar warga tidak punya alamat: pendaftaran
                             tidak pernah memintanya. displayAddress() sudah
                             mengembalikan "—" untuk kasus itu. --}}
                        <td class="px-4 py-2.5 text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $profile->displayAddress() }}
                        </td>

                        {{-- Penanda saja, bukan nomornya. Nomor NIK tidak pernah
                             muncul di daftar — lihat catatan di Table.php. --}}
                        <td class="px-4 py-2.5">
                            @if ($profile->hasVerifiedNik())
                                <x-nawasara-ui::badge color="success">Terverifikasi</x-nawasara-ui::badge>
                            @else
                                <x-nawasara-ui::badge color="neutral">Belum</x-nawasara-ui::badge>
                            @endif
                        </td>

                        <td class="px-4 py-2.5 text-sm text-neutral-500 dark:text-neutral-400">
                            {{ $profile->last_login_at?->diffForHumans() ?? '—' }}
                        </td>
                    </tr>
                @endforeach
            </x-slot:table>
        </x-nawasara-ui::table>

        <div class="mt-4">
            {{ $profiles->links() }}
        </div>
    @endif
</div>
