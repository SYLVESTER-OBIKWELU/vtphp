<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VtPhp\OpenApi\RulesSchema;

final class RulesSchemaTest extends TestCase
{
    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function json(array $schema): array
    {
        return json_decode((string) json_encode($schema), true);
    }

    public function test_types_formats_and_limits(): void
    {
        $schema = $this->json(RulesSchema::fromRules([
            'title' => 'required|string|min:3|max:100',
            'email' => ['required', 'email'],
            'age' => 'integer|min:18|max:130',
            'price' => 'numeric|max:9.5',
            'done' => 'boolean',
            'status' => 'in:draft,published',
            'due' => 'nullable|date',
            'tags' => 'array|max:5',
        ]));

        $this->assertSame(['title', 'email'], $schema['required']);
        $this->assertSame(['type' => 'string', 'minLength' => 3, 'maxLength' => 100], $schema['properties']['title']);
        $this->assertSame(['type' => 'string', 'format' => 'email'], $schema['properties']['email']);
        $this->assertSame(['type' => 'integer', 'minimum' => 18, 'maximum' => 130], $schema['properties']['age']);
        $this->assertSame(['type' => 'number', 'maximum' => 9.5], $schema['properties']['price']);
        $this->assertSame(['type' => 'boolean'], $schema['properties']['done']);
        $this->assertSame(['draft', 'published'], $schema['properties']['status']['enum']);
        $this->assertSame(['string', 'null'], $schema['properties']['due']['type']);
        $this->assertSame('date-time', $schema['properties']['due']['format']);
        $this->assertSame(['type' => 'array', 'items' => [], 'maxItems' => 5], $schema['properties']['tags']);
    }

    public function test_confirmed_adds_the_confirmation_field_and_notes_are_described(): void
    {
        $schema = $this->json(RulesSchema::fromRules([
            'password' => 'required|string|min:8|max_bytes:72|confirmed',
            'email' => 'sometimes|email|unique:users,email',
            'team_id' => 'exists:teams,id',
        ]));

        $this->assertSame(['password', 'password_confirmation'], $schema['required']);
        $this->assertSame('Must match password.', $schema['properties']['password_confirmation']['description']);
        $this->assertStringContainsString('72 bytes', $schema['properties']['password']['description']);
        $this->assertSame('Must not already be taken.', $schema['properties']['email']['description']);
        $this->assertSame('Must match an existing record.', $schema['properties']['team_id']['description']);
    }

    public function test_dotted_rules_nest(): void
    {
        $schema = $this->json(RulesSchema::fromRules([
            'tags' => 'required|array',
            'tags.*' => 'string|max:20',
            'address.city' => 'required|string',
            'address.zip' => 'string',
            'items.*.sku' => 'required|string',
            'items.*.qty' => 'integer|min:1',
        ]));

        $this->assertSame(['type' => 'array', 'items' => ['type' => 'string', 'maxLength' => 20]], $schema['properties']['tags']);
        $this->assertSame(['city'], $schema['properties']['address']['required']);
        $this->assertSame('string', $schema['properties']['address']['properties']['zip']['type']);
        $this->assertSame('array', $schema['properties']['items']['type']);
        $this->assertSame(['sku'], $schema['properties']['items']['items']['required']);
        $this->assertSame(['type' => 'integer', 'minimum' => 1], $schema['properties']['items']['items']['properties']['qty']);
    }
}
