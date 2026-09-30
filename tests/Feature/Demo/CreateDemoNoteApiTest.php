<?php

namespace Tests\Feature\Demo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Demo\Models\DemoNote;
use Tests\TestCase;

class CreateDemoNoteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_store_validates_input(): void
    {
        $this->postJson(route('api.demo-notes.store'), [
            'title' => '',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);

        $this->assertSame(0, DemoNote::query()->count());
    }

    public function test_api_store_reuses_action_and_returns_resource(): void
    {
        $response = $this->postJson(route('api.demo-notes.store'), [
            'title' => 'API created note',
            'body' => 'Same Action as Web',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'API created note')
            ->assertJsonPath('data.body', 'Same Action as Web');

        $this->assertDatabaseHas('demo_notes', [
            'id' => $response->json('data.id'),
            'title' => 'API created note',
        ], 'platform');
    }
}
