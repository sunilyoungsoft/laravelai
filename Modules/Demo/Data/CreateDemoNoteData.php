<?php

namespace Modules\Demo\Data;

/**
 * Typed application input for creating a demo note.
 * HTTP/tool validation stays at the entry boundary; this DTO is the Action contract.
 */
readonly class CreateDemoNoteData
{
    public function __construct(
        public string $title,
        public ?string $body = null,
    ) {}

    /**
     * @param  array{title: string, body?: string|null}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            title: $validated['title'],
            body: $validated['body'] ?? null,
        );
    }
}
