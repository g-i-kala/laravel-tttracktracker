<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Dashboard page. Placeholder for now: recent imports, recent exports and
 * items needing attention come in the "Build dashboard" task.
 */
#[Title('Dashboard')]
class Dashboard extends Component
{
    public bool $showHowItWorks = false;

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
