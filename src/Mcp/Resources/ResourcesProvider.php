<?php

declare(strict_types=1);

namespace AiToolset\Tm\Mcp\Resources;

use PhpMcp\Server\Attributes\McpResource;
use RuntimeException;

/**
 * Exposes the four orientation markdown documents as MCP resources.
 *
 * Each method returns the contents of the markdown file colocated with this
 * class. The files are the long form of the orientation summary that
 * {@see \AiToolset\Tm\Mcp\McpServer::INSTRUCTIONS} points agents at.
 */
final class ResourcesProvider
{
    #[McpResource(
        uri: 'tm://overview',
        description: 'tm overview: what tm is, what it is not, and the surface it exposes.',
        mimeType: 'text/markdown',
    )]
    public function overview(): string
    {
        return $this->read('overview.md');
    }

    #[McpResource(
        uri: 'tm://entity-model',
        description: 'tm entity model: projects, tickets, phases, tasks, log entries, and the fields each carries.',
        mimeType: 'text/markdown',
    )]
    public function entityModel(): string
    {
        return $this->read('entity-model.md');
    }

    #[McpResource(
        uri: 'tm://workflow',
        description: 'tm workflow: the pending -> active -> done lifecycle and the daily-usage shape agents follow.',
        mimeType: 'text/markdown',
    )]
    public function workflow(): string
    {
        return $this->read('workflow.md');
    }

    #[McpResource(
        uri: 'tm://anti-patterns',
        description: 'tm anti-patterns: common mistakes agents make against the tm surface and how to avoid them.',
        mimeType: 'text/markdown',
    )]
    public function antiPatterns(): string
    {
        return $this->read('anti-patterns.md');
    }

    private function read(string $filename): string
    {
        $path = __DIR__ . '/' . $filename;
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Failed to read tm orientation resource at {$path}.");
        }

        return $contents;
    }
}
