<?php

namespace Modules\Demo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared HTTP boundary validation for the Demo architecture proof.
 *
 * Production Web and API may use separate Form Request classes while still
 * sharing the same CreateDemoNoteData DTO and CreateDemoNoteAction.
 */
class StoreDemoNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Proof only — real modules must authorize via Policies/Gates.
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array{title: string, body?: string|null}
     */
    public function demoNoteData(): array
    {
        /** @var array{title: string, body?: string|null} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
