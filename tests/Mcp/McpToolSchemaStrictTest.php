<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Mcp;

use AiToolset\AiLib\Domain\Config;
use AiToolset\AiLib\Testing\InMemoryDatabase;
use AiToolset\Tm\Mcp\McpServer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class McpToolSchemaStrictTest extends TestCase
{
    /**
     * Every tool's input schema must set additionalProperties:false, so an unknown
     * argument (for example a mistyped parameter name) is rejected by the validator
     * instead of being silently dropped. A new tool added without the
     * #[Schema(additionalProperties: false)] attribute will fail this test.
     */
    #[Test]
    public function it_marks_every_tool_input_schema_as_strict(): void
    {
        $server = new McpServer()->boot(InMemoryDatabase::create(), Config::default());

        $tools = $server->getRegistry()->getTools();

        $this->assertNotEmpty($tools, 'Expected the registry to expose tools.');

        foreach ($tools as $tool) {
            $this->assertFalse(
                $this->additionalPropertiesOf($tool->inputSchema),
                "Tool '{$tool->name}' must set additionalProperties:false to reject unknown arguments.",
            );
        }
    }

    /**
     * Reads the additionalProperties keyword from a tool input schema. Returns null
     * when the keyword is absent, so a missing keyword also fails the assertFalse check.
     *
     * @param array<string, mixed> $schema
     */
    private function additionalPropertiesOf(array $schema): mixed
    {
        return $schema['additionalProperties'] ?? null;
    }
}
