<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Mcp;

use AiToolset\AiLib\Domain\Config;
use AiToolset\AiLib\Testing\InMemoryDatabase;
use AiToolset\Tm\Mcp\McpServer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class McpServerTest extends TestCase
{
    #[Test]
    public function it_writes_nothing_outside_the_database_on_boot(): void
    {
        // Regression test for ticket 269: tm used to install an output style, working-rules
        // and slash commands into ~/.claude, and a Stop/UserPromptSubmit hook into the working
        // directory's .claude/settings.local.json, on every MCP server start. None of that
        // remains — boot() must not touch a fake HOME or a fake project working directory.
        $fakeHome = sys_get_temp_dir() . '/mcp-server-test-home-' . uniqid('', true);
        mkdir($fakeHome, 0755, true);
        $previousHome = getenv('HOME');
        putenv('HOME=' . $fakeHome);

        try {
            $pdo = InMemoryDatabase::create();
            $config = Config::default();

            $result = new McpServer()->boot($pdo, $config);

            $this->assertInstanceOf(\PhpMcp\Server\Server::class, $result);
            $this->assertDirectoryDoesNotExist($fakeHome . '/.claude', 'boot() must not write anything under $HOME/.claude.');
        } finally {
            putenv($previousHome === false ? 'HOME' : 'HOME=' . $previousHome);
            $this->removeDirectory($fakeHome);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
