<?php

namespace Tests\Unit\Demo;

use Modules\Demo\Data\CreateDemoNoteData;
use PHPUnit\Framework\TestCase;

class CreateDemoNoteDataTest extends TestCase
{
    public function test_dto_holds_typed_application_input(): void
    {
        $data = CreateDemoNoteData::fromValidated([
            'title' => 'Architecture proof',
            'body' => 'Reusable Action layer',
        ]);

        $this->assertSame('Architecture proof', $data->title);
        $this->assertSame('Reusable Action layer', $data->body);
    }

    public function test_dto_allows_null_body(): void
    {
        $data = new CreateDemoNoteData(title: 'Title only');

        $this->assertSame('Title only', $data->title);
        $this->assertNull($data->body);
    }
}
