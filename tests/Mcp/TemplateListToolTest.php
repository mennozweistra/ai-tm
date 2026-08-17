<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Mcp;

use AiToolset\AiLib\Domain\Config;
use AiToolset\AiLib\Domain\SystemClock;
use AiToolset\AiLib\Repositories\PhaseRepository;
use AiToolset\AiLib\Repositories\ProjectRepository;
use AiToolset\AiLib\Repositories\TaskRepository;
use AiToolset\AiLib\Repositories\TemplateRepository;
use AiToolset\AiLib\Repositories\TicketRepository;
use AiToolset\AiLib\Services\TicketService;
use AiToolset\AiLib\Services\TransitionRecorder;
use AiToolset\AiLib\Testing\InMemoryDatabase;
use AiToolset\Tm\Mcp\Tools\TemplateListTool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests tm_template_list against a temp templates directory.
 * No real data is touched — the tool delegates entirely to
 * TicketService::listTemplates(), which forwards to TemplateRepository::list().
 */
final class TemplateListToolTest extends TestCase
{
    private string $tempDir;
    private TemplateListTool $listTool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/tm_list_tool_test_' . uniqid();

        $pdo = InMemoryDatabase::create();
        $config = Config::default();
        $clock = new SystemClock();
        $recorder = new TransitionRecorder($pdo, $clock);

        $ticketService = new TicketService(
            pdo: $pdo,
            projectRepository: new ProjectRepository($pdo),
            repository: new TicketRepository($pdo),
            recorder: $recorder,
            clock: $clock,
            config: $config,
            phaseRepository: new PhaseRepository($pdo),
            taskRepository: new TaskRepository($pdo),
            templateRepository: new TemplateRepository([$this->tempDir], $this->tempDir),
        );

        $this->listTool = new TemplateListTool($ticketService);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDir($this->tempDir);
    }

    #[Test]
    public function it_returns_an_empty_list_when_no_templates_exist(): void
    {
        $result = $this->listTool->list();

        $this->assertTrue((bool) $result['ok'], 'Expected ok=true, got: ' . json_encode($result));
        $data = $result['data'];
        $this->assertIsArray($data);
        $this->assertSame([], $data['templates']);
    }

    #[Test]
    public function it_returns_template_names_sorted_alphabetically(): void
    {
        mkdir($this->tempDir, 0755, true);
        file_put_contents($this->tempDir . '/zebra.toml', '');
        file_put_contents($this->tempDir . '/alpha.toml', '');
        file_put_contents($this->tempDir . '/middle.toml', '');

        $result = $this->listTool->list();

        $this->assertTrue((bool) $result['ok'], 'Expected ok=true, got: ' . json_encode($result));
        $data = $result['data'];
        $this->assertIsArray($data);
        $this->assertSame(['alpha', 'middle', 'zebra'], $data['templates']);
    }

    private function removeDir(string $dir): void
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
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
