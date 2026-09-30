<?php

namespace Modules\Demo\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Demo\Actions\CreateDemoNoteAction;
use Modules\Demo\Data\CreateDemoNoteData;
use Modules\Demo\Http\Requests\StoreDemoNoteRequest;

class DemoNoteController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Demo/Notes/Create');
    }

    public function store(
        StoreDemoNoteRequest $request,
        CreateDemoNoteAction $action,
    ): RedirectResponse {
        $note = $action->execute(
            CreateDemoNoteData::fromValidated($request->demoNoteData())
        );

        return redirect()
            ->route('demo-notes.create')
            ->with('success', 'Demo note created.')
            ->with('demo_note_id', $note->id);
    }
}
