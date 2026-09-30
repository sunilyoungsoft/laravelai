<?php

namespace Modules\Demo\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Demo\Actions\CreateDemoNoteAction;
use Modules\Demo\Data\CreateDemoNoteData;
use Modules\Demo\Http\Requests\StoreDemoNoteRequest;
use Modules\Demo\Http\Resources\DemoNoteResource;

class DemoNoteApiController extends Controller
{
    public function store(
        StoreDemoNoteRequest $request,
        CreateDemoNoteAction $action,
    ): JsonResponse {
        $note = $action->execute(
            CreateDemoNoteData::fromValidated($request->demoNoteData())
        );

        return (new DemoNoteResource($note))
            ->response()
            ->setStatusCode(201);
    }
}
