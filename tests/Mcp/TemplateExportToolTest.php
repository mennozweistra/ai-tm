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
use AiToolset\Tm\Mcp\Tools\TemplateExportTool;
use AiToolset\Tm\Mcp\Tools\TicketTools;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests tm_template_export against an in-memory SQLite fixture and a temp
 * templates directory, following the same no-mock approach as the other
 * Mcp tests.
 */
final class TemplateExportToolTest extends BaseMcpTest
{
    private string $tempDir;
    private TemplateExportTool $exportTool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/tm_export_tool_test_' . uniqid();

        $config = Config::default();
        $clock = new SystemClock();
        $recorder = new TransitionRecorder($this->pdo, $clock);

        $exportTicketService = new TicketService(
            pdo: $this->pdo,
            projectRepository: new ProjectRepository($this->pdo),
            repository: new TicketRepository($this->pdo),
            recorder: $recorder,
            clock: $clock,
            config: $config,
            phaseRepository: new PhaseRepository($this->pdo),
            taskRepository: new TaskRepository($this->pdo),
            templateRepository: new TemplateRepository([$this->tempDir], $this->tempDir),
        );

        $this->exportTool = new TemplateExportTool($exportTicketService);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDir($this->tempDir);
    }

    #[Test]
    public function it_exports_a_ticket_structure_to_a_toml_file(): void
    {
        $this->seedTicketWithPhasesAndTasks();

        $result = $this->exportTool->export(ticket: 1, name: 'my-template');

        $this->assertOk($result);
        $data = $result['data'];
        $this->assertIsArray($data);
        $this->assertArrayHasKey('path', $data);
        $path = $data['path'];
        $this->assertIsString($path);
        $this->assertStringEndsWith('my-template.toml', $path);
        $this->assertFileExists($path);
    }

    #[Test]
    public function it_exports_only_structural_fields_and_excludes_ids_and_statuses(): void
    {
        $this->seedTicketWithPhasesAndTasks();

        $this->exportTool->export(ticket: 1, name: 'structural-check');

        $repo = new TemplateRepository([$this->tempDir], $this->tempDir);
        $decoded = $repo->read('structural-check');

        $phases = $decoded['phases'];
        $this->assertIsArray($phases);
        $this->assertCount(2, $phases);

        $phase = $phases[0];
        $this->assertIsArray($phase);
        $this->assertArrayHasKey('name', $phase);
        $this->assertArrayHasKey('description', $phase);
        $this->assertArrayHasKey('ai_description', $phase);
        $this->assertArrayHasKey('order', $phase);
        $this->assertArrayNotHasKey('id', $phase);
        $this->assertArrayNotHasKey('status', $phase);
        $this->assertArrayNotHasKey('created_at', $phase);

        $tasks = $phase['tasks'];
        $this->assertIsArray($tasks);
        $this->assertCount(1, $tasks);

        $task = $tasks[0];
        $this->assertIsArray($task);
        $this->assertArrayHasKey('name', $task);
        $this->assertArrayHasKey('description', $task);
        $this->assertArrayHasKey('ai_description', $task);
        $this->assertArrayHasKey('order', $task);
        $this->assertArrayHasKey('max_attempts', $task);
        $this->assertArrayNotHasKey('id', $task);
        $this->assertArrayNotHasKey('status', $task);
        $this->assertArrayNotHasKey('result', $task);
    }

    #[Test]
    public function it_preserves_phase_and_task_field_values(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'ticket', template: self::SCAFFOLD_TEMPLATE);
        $this->phaseTools->add(
            ticket: 1,
            name: 'Foundation',
            description: 'The base',
            ai_description: 'Creates the scaffold',
        );
        $this->taskTools->add(
            phase: 1,
            name: 'Init repo',
            model: 'sonnet',
            description: 'Run git init',
            ai_description: 'Initialise the repository',
        );

        $this->exportTool->export(ticket: 1, name: 'values-check');

        $repo = new TemplateRepository([$this->tempDir], $this->tempDir);
        $decoded = $repo->read('values-check');

        $phases = $decoded['phases'];
        $this->assertIsArray($phases);
        $phase = $phases[0];
        $this->assertIsArray($phase);
        $this->assertSame('Foundation', $phase['name']);
        $this->assertSame('The base', $phase['description']);
        $this->assertSame('Creates the scaffold', $phase['ai_description']);

        $tasks = $phase['tasks'];
        $this->assertIsArray($tasks);
        $task = $tasks[0];
        $this->assertIsArray($task);
        $this->assertSame('Init repo', $task['name']);
        $this->assertSame('Run git init', $task['description']);
        $this->assertSame('Initialise the repository', $task['ai_description']);
    }

    #[Test]
    public function it_preserves_ticket_description_and_ai_description(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(
            project: 1,
            name: 'ticket',
            description: 'Human description of the ticket',
            ai_description: 'AI detail for the ticket',
            template: self::SCAFFOLD_TEMPLATE,
        );

        $this->exportTool->export(ticket: 1, name: 'desc-check');

        $repo = new TemplateRepository([$this->tempDir], $this->tempDir);
        $decoded = $repo->read('desc-check');

        $this->assertSame('Human description of the ticket', $decoded['description']);
        $this->assertSame('AI detail for the ticket', $decoded['ai_description']);
    }

    #[Test]
    public function it_returns_an_error_when_the_ticket_does_not_exist(): void
    {
        $result = $this->exportTool->export(ticket: 999, name: 'ghost');

        $this->assertFailed($result, 'not_found');
    }

    #[Test]
    public function it_overwrites_an_existing_template_file_on_repeat_export(): void
    {
        $this->seedTicketWithPhasesAndTasks();

        $firstResult = $this->exportTool->export(ticket: 1, name: 'duplicate');
        $this->assertOk($firstResult);
        $firstData = $firstResult['data'];
        $this->assertIsArray($firstData);
        $firstPath = $firstData['path'];
        $this->assertIsString($firstPath);

        $this->phaseTools->add(ticket: 1, name: 'Phase C', description: 'Added before re-export', ai_description: '');

        $secondResult = $this->exportTool->export(ticket: 1, name: 'duplicate');
        $this->assertOk($secondResult);
        $secondData = $secondResult['data'];
        $this->assertIsArray($secondData);
        $secondPath = $secondData['path'];
        $this->assertIsString($secondPath);

        $this->assertSame($firstPath, $secondPath);

        $repo = new TemplateRepository([$this->tempDir], $this->tempDir);
        $decoded = $repo->read('duplicate');
        $phases = $decoded['phases'];
        $this->assertIsArray($phases);
        $this->assertCount(3, $phases);
        $lastPhase = $phases[2];
        $this->assertIsArray($lastPhase);
        $this->assertSame('Phase C', $lastPhase['name']);
    }

    #[Test]
    public function it_exports_actor_human_to_toml_and_preserves_it_on_import(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'ticket', template: self::SCAFFOLD_TEMPLATE);
        $this->phaseTools->add(ticket: 1, name: 'Phase A', description: '', ai_description: '');
        $this->taskTools->add(phase: 1, name: 'Human task', model: null, description: '', ai_description: '', actor: 'human');

        $this->exportTool->export(ticket: 1, name: 'actor-round-trip');

        // Verify the raw TOML contains the actor field.
        $repo = new TemplateRepository([$this->tempDir], $this->tempDir);
        $tomlPath = $repo->path('actor-round-trip');
        $this->assertFileExists($tomlPath);
        $rawToml = (string) file_get_contents($tomlPath);
        $this->assertStringContainsString('actor = "human"', $rawToml);

        // Import the template into a new project and ticket, then verify the actor.
        $this->projectTools->add(name: 'proj2', path: '/tmp/proj2');
        $importTools = $this->buildTicketToolsForImport();
        $importResult = $importTools->add(project: 2, name: 'Imported Ticket', template: 'actor-round-trip');
        $this->assertOk($importResult);

        $taskData = $this->data($this->taskTools->show(task: 2));
        $this->assertSame('human', $taskData['actor']);
    }

    #[Test]
    public function it_exports_max_attempts_value_to_toml_and_preserves_it_on_import(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'ticket', template: self::SCAFFOLD_TEMPLATE);
        $this->phaseTools->add(ticket: 1, name: 'Phase A', description: '', ai_description: '');
        $this->taskTools->add(phase: 1, name: 'Grindable task', model: 'sonnet', description: '', ai_description: '', max_attempts: 3);

        $this->exportTool->export(ticket: 1, name: 'max-attempts-round-trip');

        // Verify the raw TOML contains the correct max_attempts value.
        $repo = new TemplateRepository([$this->tempDir], $this->tempDir);
        $tomlPath = $repo->path('max-attempts-round-trip');
        $this->assertFileExists($tomlPath);
        $rawToml = (string) file_get_contents($tomlPath);
        $this->assertStringContainsString('max_attempts = 3', $rawToml);

        // Import the template into a new project and ticket, then verify the value is preserved.
        $this->projectTools->add(name: 'proj2', path: '/tmp/proj2');
        $importTools = $this->buildTicketToolsForImport();
        $importResult = $importTools->add(project: 2, name: 'Imported Ticket', template: 'max-attempts-round-trip');
        $this->assertOk($importResult);

        $taskData = $this->data($this->taskTools->show(task: 2));
        $this->assertSame(3, $taskData['max_attempts']);
    }

    private function seedTicketWithPhasesAndTasks(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'ticket', template: self::SCAFFOLD_TEMPLATE);
        $this->phaseTools->add(ticket: 1, name: 'Phase A', description: 'First phase', ai_description: '');
        $this->taskTools->add(phase: 1, name: 'Task 1', model: 'sonnet', description: '', ai_description: '');
        $this->phaseTools->add(ticket: 1, name: 'Phase B', description: '', ai_description: 'AI desc');
    }

    /**
     * Builds a TicketTools instance backed by a fresh TicketService whose
     * templateRepository points at this test's tempDir — the same directory
     * TemplateExportTool wrote the round-trip fixture into. This exercises
     * tm_ticket_add's real template-import path (TicketService::addFromTemplate())
     * rather than the removed standalone import tool.
     */
    private function buildTicketToolsForImport(): TicketTools
    {
        $config = Config::default();
        $clock = new SystemClock();
        $recorder = new TransitionRecorder($this->pdo, $clock);

        $ticketService = new TicketService(
            pdo: $this->pdo,
            projectRepository: new ProjectRepository($this->pdo),
            repository: new TicketRepository($this->pdo),
            recorder: $recorder,
            clock: $clock,
            config: $config,
            phaseRepository: new PhaseRepository($this->pdo),
            taskRepository: new TaskRepository($this->pdo),
            templateRepository: new TemplateRepository([$this->tempDir], $this->tempDir),
        );

        return new TicketTools($ticketService);
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
