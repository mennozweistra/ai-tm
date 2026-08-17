<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Mcp;

use AiToolset\Tm\Mcp\McpServer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers McpServer::userTemplatesPath(), the pure function behind the
 * user-templates half of the two-source template resolution (ticket 269,
 * requirement 692). The resolution order and collision rule themselves are
 * covered against real files in ai-lib's TemplateRepositoryTest; this only
 * checks the path McpServer derives it from, and that it lands next to
 * store.db's default location the same way resolveDbPath() does.
 */
final class McpServerTemplatesPathTest extends TestCase
{
    #[Test]
    public function it_places_the_user_templates_directory_under_the_default_ai_tm_data_directory(): void
    {
        $this->assertSame(
            '/home/menno/.ai-tm/templates',
            McpServer::userTemplatesPath('/home/menno'),
        );
    }
}
