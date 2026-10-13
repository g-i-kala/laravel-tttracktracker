<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_dashboard_page_renders_inside_the_app_layout(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeLivewire(Dashboard::class)
            ->assertSee('<title>Dashboard · '.config('app.name').'</title>', false)
            ->assertSee('data-accent="'.config('app.accent').'"', false);
    }

    public function test_navigation_marks_screens_without_routes_as_coming_soon(): void
    {
        $this->get('/')
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('href="'.route('imports.index').'"', false)
            ->assertSee('title="Coming soon">Exports</span>', false);
    }

    public function test_accent_comes_from_config(): void
    {
        config(['app.accent' => 'indigo']);

        $this->get('/')->assertSee('data-accent="indigo"', false);
    }

    public function test_how_it_works_modal_is_bound_to_a_property(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSet('showHowItWorks', false)
            ->assertSeeHtml('wire:model="showHowItWorks"')
            ->set('showHowItWorks', true)
            ->assertSet('showHowItWorks', true);
    }
}
