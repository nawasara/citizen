<?php

namespace Nawasara\Citizen\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Staff-facing panel: the citizen list and detail.
            'citizen.profile.view',

            // Reading the NIK is split from viewing the profile on purpose.
            // Most people who need to look up a citizen never need the number,
            // and bundling the two would hand it to everyone by default.
            'citizen.nik.view',

            // Marking a NIK verified — for now a manual act, so it carries its
            // own permission rather than riding along with viewing.
            'citizen.nik.verify',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $role = Role::where('name', 'developer')->first();

        if ($role) {
            $role->givePermissionTo($permissions);
        }
    }
}
