<?php

namespace Nawasara\Citizen\Livewire\Profile;

use Livewire\Component;

/**
 * Page shell for the citizen list.
 *
 * Thin on purpose (CLAUDE.md §1b): layout, breadcrumb, and the child
 * component. All querying and filtering lives in Section\Table, so this file
 * stays readable as the page grows.
 */
class Index extends Component
{
    public function render()
    {
        // Without ->layout(...) the page renders bare — no sidebar, no topbar.
        return view('nawasara-citizen::livewire.pages.profile.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
