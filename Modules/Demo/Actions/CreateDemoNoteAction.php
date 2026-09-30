<?php

namespace Modules\Demo\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Demo\Data\CreateDemoNoteData;
use Modules\Demo\Models\DemoNote;

/**
 * Application operation: create a DemoNote.
 * Reusable from Web, API, CLI, and (later) AI/MCP entry points.
 */
class CreateDemoNoteAction
{
    public function execute(CreateDemoNoteData $data): DemoNote
    {
        return DB::connection('platform')->transaction(function () use ($data) {
            $note = new DemoNote;
            $note->setConnection('platform');
            $note->fill([
                'title' => $data->title,
                'body' => $data->body,
            ]);
            $note->save();

            return $note->fresh();
        });
    }
}
