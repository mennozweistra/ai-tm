<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Mcp;

use AiToolset\AiLib\Domain\Config;
use AiToolset\AiLib\Testing\InMemoryDatabase;
use AiToolset\Tm\Mcp\McpServer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class McpServerInstructionsTest extends TestCase
{
    #[Test]
    public function it_exposes_orientation_instructions_in_the_initialize_response(): void
    {
        $pdo = InMemoryDatabase::create();
        $config = Config::default();

        $server = new McpServer()->boot($pdo, $config);

        $instructions = $server->getConfiguration()->instructions;

        $this->assertIsString($instructions);
        $this->assertNotSame('', $instructions);

        $length = strlen($instructions);
        $this->assertGreaterThanOrEqual(
            1000,
            $length,
            "Instructions should be at least 1000 chars, got {$length}.",
        );
        $this->assertLessThanOrEqual(
            3500,
            $length,
            "Instructions should be at most 3500 chars, got {$length}.",
        );

        // The four entity nouns must appear, case-insensitive, so the agent learns
        // the entity model from the initialize response alone.
        foreach (['project', 'ticket', 'phase', 'task'] as $noun) {
            $this->assertMatchesRegularExpression(
                '/' . preg_quote($noun, '/') . '/i',
                $instructions,
                "Instructions should mention '{$noun}' (case-insensitive).",
            );
        }

        // The four resource URIs must appear verbatim so the agent knows where
        // to fetch the long-form orientation documents.
        foreach (
            [
                'tm://overview',
                'tm://entity-model',
                'tm://workflow',
                'tm://anti-patterns',
            ] as $uri
        ) {
            $this->assertStringContainsString(
                $uri,
                $instructions,
                "Instructions should name resource URI '{$uri}'.",
            );
        }

        // The tm_grill trigger must appear so the agent knows to call it when
        // the user asks to grill a ticket or start its Discovery phase (req 79).
        $this->assertStringContainsString('tm_grill', $instructions);
        $this->assertStringContainsString('grill', $instructions);

        // Live-conversation question capture must be covered: store before
        // discussing, resolve and process in the same turn (reqs 364, 365, 375, 381).
        $this->assertStringContainsString('tm_question_add', $instructions);
        $this->assertStringContainsString('tm_question_resolve', $instructions);
        $this->assertStringContainsString('tm_question_process', $instructions);
    }
}
