<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Ponorogo Hub', 'url' => '#'], ['label' => 'Data Warga']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <livewire:nawasara-citizen.profile.section.table />
    </x-nawasara-ui::page.container>
</div>
