<?php

$prefix = 'nawasara-citizen';

/*
| Sidebar menu for citizen data.
|
| Workspace id `ponorogo-hub` is shared with every Ponorogo Hub service
| package (aspirations, news, jobs, ...) so they appear together rather than
| scattered among the infrastructure menus. WorkspaceManager merges entries
| with the same id and takes the label from whichever loads first
| alphabetically — nawasara-aspirations sorts before nawasara-citizen, so keep
| the label and icon identical across all of them or the heading will change
| depending on which packages are installed.
|
| ⚠️ `group` must be one of WorkspaceManager::GROUP_ORDER. Anything else lands
| silently under "Lainnya". 'Layanan' is used here rather than a new
| "Ponorogo Hub" group, which would require changing that constant and
| releasing nawasara/ui.
*/

return [
    [
        'workspace' => 'ponorogo-hub',
        'label' => 'Ponorogo Hub',
        'icon' => 'lucide-landmark',
        'group' => 'Layanan',
        'url' => '',
        'permission' => 'citizen.profile.view',
        'submenu' => [
            // Section heading of its own — WITHOUT it this entry is appended
            // after the last section contributed by nawasara-aspirations, so
            // "Data Warga" renders underneath that package's "Pengaturan"
            // heading and reads as a setting rather than as citizen data.
            [
                'section' => 'Warga',
                'icon' => 'lucide-users-round',
                'permission' => 'citizen.profile.view',
            ],
            [
                'label' => 'Data Warga',
                'icon' => 'lucide-users-round',
                'url' => url($prefix.'/profiles'),
                'permission' => 'citizen.profile.view',
                'navigate' => true,
            ],
        ],
    ],
];
