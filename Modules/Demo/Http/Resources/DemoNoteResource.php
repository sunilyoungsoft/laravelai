<?php

namespace Modules\Demo\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Demo\Models\DemoNote;

/**
 * @mixin DemoNote
 */
class DemoNoteResource extends JsonResource
{
    /**
     * @return array{id: string, title: string, body: string|null, created_at: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
