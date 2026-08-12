<?php

namespace Nawasara\Citizen\Livewire\Profile\Section;

use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Citizen\Models\CitizenProfile;

/**
 * Citizen list with search and filtering.
 *
 * Shows no NIK — not even masked. Anyone who needs the number opens the detail
 * view, which checks citizen.nik.view separately and leaves an audit trail.
 * A list is browsed casually; that is the wrong place for protected data.
 */
class Table extends Component
{
    use WithPagination;

    public string $search = '';
    public string $district = '';
    public string $nikStatus = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'district' => ['except' => ''],
        'nikStatus' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingDistrict(): void
    {
        $this->resetPage();
    }

    public function updatingNikStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        // Re-checked here, not only on the route: a Livewire update is its own
        // request and does not re-run route middleware.
        abort_unless(auth()->user()?->can('citizen.profile.view'), 403);

        $query = CitizenProfile::query()
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('full_name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when($this->district !== '', fn ($q) => $q->where('district', $this->district))
            ->when($this->nikStatus === 'verified',
                fn ($q) => $q->whereHas('nikRecord', fn ($n) => $n->where('is_verified', true)))
            ->when($this->nikStatus === 'unverified',
                fn ($q) => $q->whereDoesntHave('nikRecord', fn ($n) => $n->where('is_verified', true)))
            ->latest('last_login_at');

        return view('nawasara-citizen::livewire.pages.profile.section.table', [
            'profiles' => $query->paginate(config('nawasara-citizen.panel.per_page', 25)),

            // Districts actually present in the data, not the full master list.
            // Most citizens have no address at all — offering all 21 districts
            // would mostly produce empty results.
            'districts' => CitizenProfile::query()
                ->whereNotNull('district')
                ->distinct()
                ->orderBy('district')
                ->pluck('district'),
        ]);
    }
}
