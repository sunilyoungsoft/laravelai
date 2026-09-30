<?php

namespace Tests\Feature\Demo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Demo\Models\DemoNote;
use Tests\TestCase;

class CreateDemoNoteWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_renders_via_inertia(): void
    {
        $this->get(route('demo-notes.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Demo/Notes/Create'));
    }

    public function test_store_validates_input(): void
    {
        $this->from(route('demo-notes.create'))
            ->post(route('demo-notes.store'), [
                'title' => '',
            ])
            ->assertRedirect(route('demo-notes.create'))
            ->assertSessionHasErrors(['title']);

        $this->assertSame(0, DemoNote::query()->count());
    }

    public function test_store_delegates_to_action_and_redirects(): void
    {
        $response = $this->from(route('demo-notes.create'))
            ->post(route('demo-notes.store'), [
                'title' => 'Web created note',
                'body' => 'Via Inertia form',
            ]);

        $response->assertRedirect(route('demo-notes.create'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('demo_notes', [
            'title' => 'Web created note',
            'body' => 'Via Inertia form',
        ], 'platform');
    }
}
