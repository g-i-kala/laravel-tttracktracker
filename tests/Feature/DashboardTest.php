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

    public function test_navigation_links_to_every_screen(): void
    {
        $this->get('/')
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('href="'.route('imports.index').'"', false)
            ->assertSee('href="'.route('exports.index').'"', false)
            ->assertDontSee('title="Coming soon"', false);
    }

    public function test_nav_link_without_a_route_is_shown_as_coming_soon(): void
    {
        $this->blade('<x-nav-link route="not.built.yet">Later</x-nav-link>')
            ->assertSee('title="Coming soon">Later</span>', false);
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
