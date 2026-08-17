<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Mcp;

use AiToolset\AiLib\Domain\Config;
use AiToolset\AiLib\Testing\InMemoryDatabase;
use AiToolset\Tm\Mcp\McpServer;
use PhpMcp\Schema\Content\TextResourceContents;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class McpServerResourceReadTest extends TestCase
{
    #[Test]
    public function it_reads_each_orientation_resource_as_markdown_starting_with_its_stable_heading(): void
    {
        $pdo = InMemoryDatabase::create();
        $config = Config::default();

        $server = new McpServer()->boot($pdo, $config);

        // RegisteredResource::read() is exactly what Dispatcher::handleResourceRead()
        // invokes for a resources/read call (see vendor/php-mcp/server/src/Dispatcher.php).
        $registry = $server->getRegistry();
        $container = $server->getConfiguration()->container;

        $expected = [
            'tm://overview' => '# tm — Overview',
            'tm://entity-model' => '# tm — Entity Model',
            'tm://workflow' => '# tm — Workflow',
            'tm://anti-patterns' => '# tm — Anti-patterns',
        ];

        foreach ($expected as $uri => $heading) {
            $resource = $registry->getResource($uri);
            $this->assertNotNull(
                $resource,
                "Expected resources/read to find a registered resource for URI '{$uri}'.",
            );

            $contents = $resource->read($container, $uri);

            $this->assertCount(
                1,
                $contents,
                "Expected resources/read of '{$uri}' to return one content item.",
            );

            $item = $contents[0];
            $this->assertInstanceOf(
                TextResourceContents::class,
                $item,
                "Resource '{$uri}' must return text content, not blob.",
            );

            $this->assertSame(
                'text/markdown',
                $item->mimeType,
                "Resource '{$uri}' must report mimeType 'text/markdown'.",
            );

            $this->assertGreaterThanOrEqual(
                200,
                strlen($item->text),
                "Resource '{$uri}' must carry at least 200 characters of body text.",
            );

            $this->assertStringStartsWith(
                $heading,
                $item->text,
                "Resource '{$uri}' must start with its stable level-1 heading '{$heading}'.",
            );
        }
    }
}
