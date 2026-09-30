<?php

namespace Tests\Feature\Demo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Demo\Actions\CreateDemoNoteAction;
use Modules\Demo\Data\CreateDemoNoteData;
use Modules\Demo\Models\DemoNote;
use RuntimeException;
use Tests\TestCase;

class CreateDemoNoteActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_creates_demo_note_without_http(): void
    {
        $note = app(CreateDemoNoteAction::class)->execute(
            new CreateDemoNoteData(
                title: 'Direct Action call',
                body: 'No controller required',
            )
        );

        $this->assertInstanceOf(DemoNote::class, $note);
        $this->assertSame(26, strlen($note->id));
        $this->assertSame('Direct Action call', $note->title);
        $this->assertSame('No controller required', $note->body);
        $this->assertSame('platform', $note->getConnectionName());

        $this->assertDatabaseHas('demo_notes', [
            'id' => $note->id,
            'title' => 'Direct Action call',
        ], 'platform');
    }

    public function test_transaction_rolls_back_when_operation_fails_at_test_boundary(): void
    {
        try {
            DB::connection('platform')->transaction(function (): void {
                app(CreateDemoNoteAction::class)->execute(
                    new CreateDemoNoteData(title: 'Should not persist')
                );

                // Clean test-level failure: outer transaction aborts after Action work.
                // Nested Laravel transactions use savepoints, so this rolls back the insert.
                throw new RuntimeException('Simulated failure after create');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated failure after create', $exception->getMessage());
        }

        $this->assertDatabaseMissing('demo_notes', [
            'title' => 'Should not persist',
        ], 'platform');

        $this->assertSame(0, DemoNote::query()->count());
    }

    public function test_action_uses_platform_transaction(): void
    {
        $levels = [];

        DemoNote::creating(function () use (&$levels): void {
            $levels[] = DB::connection('platform')->transactionLevel();
        });

        app(CreateDemoNoteAction::class)->execute(
            new CreateDemoNoteData(title: 'Txn proof')
        );

        $this->assertNotEmpty($levels);
        $this->assertGreaterThanOrEqual(1, max($levels));
    }
}
