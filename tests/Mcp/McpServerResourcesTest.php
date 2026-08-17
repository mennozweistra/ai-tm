<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Mcp;

use AiToolset\AiLib\Domain\Config;
use AiToolset\AiLib\Testing\InMemoryDatabase;
use AiToolset\Tm\Mcp\McpServer;
use PhpMcp\Schema\Resource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class McpServerResourcesTest extends TestCase
{
    #[Test]
    public function it_lists_the_four_orientation_resources_with_markdown_mime_and_descriptions(): void
    {
        $pdo = InMemoryDatabase::create();
        $config = Config::default();

        $server = new McpServer()->boot($pdo, $config);

        // Registry::getResources() is exactly what Dispatcher::handleResourcesList()
        // returns to a resources/list call (see vendor/php-mcp/server/src/Dispatcher.php).
        $resources = $server->getRegistry()->getResources();

        $this->assertCount(
            4,
            $resources,
            'Expected exactly four orientation resources from resources/list.',
        );

        $byUri = [];
        foreach ($resources as $resource) {
            $this->assertInstanceOf(Resource::class, $resource);
            $byUri[$resource->uri] = $resource;
        }

        $expectedUris = [
            'tm://overview',
            'tm://entity-model',
            'tm://workflow',
            'tm://anti-patterns',
        ];

        foreach ($expectedUris as $uri) {
            $this->assertArrayHasKey(
                $uri,
                $byUri,
                "Expected resources/list to expose URI '{$uri}'.",
            );

            $resource = $byUri[$uri];
            $this->assertSame(
                'text/markdown',
                $resource->mimeType,
                "Resource '{$uri}' must declare mimeType 'text/markdown'.",
            );
            $this->assertIsString($resource->description);
            $this->assertNotSame(
                '',
                trim((string) $resource->description),
                "Resource '{$uri}' must have a non-empty description.",
            );
        }
    }
}
