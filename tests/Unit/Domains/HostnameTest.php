<?php

namespace Tests\Unit\Domains;

use App\Support\Platform\Hostname;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HostnameTest extends TestCase
{
    public function test_normalizes_trim_lowercase_and_trailing_dot(): void
    {
        $this->assertSame('acme.example.com', (string) Hostname::fromString('  ACME.Example.com.  '));
    }

    public function test_normalize_helper_matches(): void
    {
        $this->assertSame('acme.example.com', Hostname::normalize('  ACME.Example.COM. '));
    }

    #[DataProvider('invalidHostnames')]
    public function test_rejects_invalid_hostnames(string $raw): void
    {
        $this->expectException(InvalidArgumentException::class);
        Hostname::fromString($raw);
    }

    public static function invalidHostnames(): array
    {
        return [
            'protocol' => ['https://acme.example.com'],
            'path' => ['acme.example.com/app'],
            'port' => ['acme.example.com:8080'],
            'wildcard' => ['*.example.com'],
            'embedded wildcard' => ['ac*me.example.com'],
            'empty' => ['   '],
            'leading hyphen label' => ['-acme.example.com'],
            'trailing hyphen label' => ['acme-.example.com'],
            'double dot' => ['acme..example.com'],
            'too long label' => [str_repeat('a', 64).'.example.com'],
        ];
    }

    public function test_accepts_valid_hostnames(): void
    {
        $this->assertSame('a-b-c.example.co.uk', (string) Hostname::fromString('A-B-C.Example.CO.uk'));
        $this->assertSame('sub.domain123.example.com', (string) Hostname::fromString('sub.domain123.example.com'));
    }
}
