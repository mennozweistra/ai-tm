<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Mcp;

use AiToolset\AiLib\Domain\Config;
use AiToolset\AiLib\Domain\SystemClock;
use AiToolset\AiLib\Repositories\LogEntryRepository;
use AiToolset\AiLib\Repositories\PhaseRepository;
use AiToolset\AiLib\Repositories\ProjectRepository;
use AiToolset\AiLib\Repositories\RequirementRepository;
use AiToolset\AiLib\Repositories\StatusTransitionRepository;
use AiToolset\AiLib\Repositories\TaskRepository;
use AiToolset\AiLib\Repositories\TemplateRepository;
use AiToolset\AiLib\Repositories\TicketRepository;
use AiToolset\AiLib\Services\TicketService;
use AiToolset\AiLib\Services\TransitionRecorder;
use AiToolset\Tm\Mcp\Tools\TicketTools;
use PHPUnit\Framework\Attributes\Test;

final class TicketToolsTest extends BaseMcpTest
{
    private const string BASIC_TEMPLATE = 'basic';

    private string $templateDir;
    private TicketTools $templatedTicketTools;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templateDir = sys_get_temp_dir() . '/tm_ticket_tools_test_' . uniqid();
        mkdir($this->templateDir, 0755, true);

        $repo = new TemplateRepository([$this->templateDir], $this->templateDir);
        $repo->write(self::BASIC_TEMPLATE, [
            'description' => '',
            'ai_description' => '',
            'phases' => [
                ['name' => 'Phase One', 'description' => '', 'ai_description' => '', 'order' => 1, 'tasks' => [
                    ['name' => 'Task A', 'description' => '', 'ai_description' => '', 'actor' => 'agent', 'order' => 1],
                ]],
            ],
        ]);

        $this->templatedTicketTools = $this->buildTicketTools($repo);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDir($this->templateDir);
    }

    private function scaffold(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'ticket', description: 'ticket desc', ai_description: 'ticket ai desc', template: self::SCAFFOLD_TEMPLATE);
        $this->phaseTools->add(ticket: 1, name: 'Phase A', description: 'phase desc', ai_description: 'phase ai desc');
        $this->taskTools->add(phase: 1, name: 'Task A', model: 'sonnet', description: 'task desc', ai_description: 'task ai desc');
        $this->requirementTools->add(ticket: 1, name: 'Req A', description: 'req desc', ai_description: 'req ai desc');
    }

    #[Test]
    public function it_rejects_ticket_creation_when_template_is_null(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');

        $this->assertFailed(
            $this->templatedTicketTools->add(project: 1, name: 'no template'),
            'invalid_argument',
        );
    }

    #[Test]
    public function it_rejects_ticket_creation_when_template_is_an_empty_string(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');

        $this->assertFailed(
            $this->templatedTicketTools->add(project: 1, name: 'empty template', template: ''),
            'invalid_argument',
        );
    }

    #[Test]
    public function it_rejects_ticket_creation_when_template_name_is_unknown(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');

        $this->assertFailed(
            $this->templatedTicketTools->add(project: 1, name: 'ghost template', template: 'ghost'),
            'invalid_argument',
        );
    }

    #[Test]
    public function it_creates_a_ticket_with_phases_and_tasks_from_a_valid_template(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');

        $result = $this->templatedTicketTools->add(project: 1, name: 'templated ticket', template: self::BASIC_TEMPLATE);
        $data = $this->data($result);
        $this->assertSame('templated ticket', $data['name']);
        $ticketId = $data['id'];
        $this->assertIsInt($ticketId);

        $deep = $this->data($this->ticketTools->show(ticket: $ticketId, deep: true));
        $phases = $deep['phases'];
        $this->assertIsArray($phases);
        $this->assertCount(1, $phases);

        $phase = $phases[0];
        $this->assertIsArray($phase);
        $this->assertSame('Phase One', $phase['name']);

        $tasks = $phase['tasks'];
        $this->assertIsArray($tasks);
        $this->assertCount(1, $tasks);
        $task = $tasks[0];
        $this->assertIsArray($task);
        $this->assertSame('Task A', $task['name']);
    }

    private function buildTicketTools(TemplateRepository $repo): TicketTools
    {
        $config = Config::default();
        $clock = new SystemClock();

        $projectRepo = new ProjectRepository($this->pdo);
        $ticketRepo = new TicketRepository($this->pdo);
        $phaseRepo = new PhaseRepository($this->pdo);
        $taskRepo = new TaskRepository($this->pdo);
        $logRepo = new LogEntryRepository($this->pdo);
        $transitionRepo = new StatusTransitionRepository($this->pdo);
        $requirementRepo = new RequirementRepository($this->pdo);
        $recorder = new TransitionRecorder($this->pdo, $clock);

        $ticketService = new TicketService(
            pdo: $this->pdo,
            projectRepository: $projectRepo,
            repository: $ticketRepo,
            recorder: $recorder,
            clock: $clock,
            config: $config,
            phaseRepository: $phaseRepo,
            taskRepository: $taskRepo,
            logEntryRepository: $logRepo,
            statusTransitionRepository: $transitionRepo,
            requirementRepository: $requirementRepo,
            templateRepository: $repo,
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

    #[Test]
    public function it_returns_the_default_shallow_shape_when_outline_and_deep_are_both_false(): void
    {
        $this->scaffold();

        $data = $this->data($this->ticketTools->show(ticket: 1));

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('ai_description', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('type', $data);
        $this->assertArrayNotHasKey('phases', $data);
        $this->assertArrayNotHasKey('requirements', $data);
        $this->assertArrayNotHasKey('logs', $data);
        $this->assertArrayNotHasKey('transitions', $data);
    }

    #[Test]
    public function it_returns_the_existing_deep_shape_when_outline_is_false_and_deep_is_true(): void
    {
        $this->scaffold();

        $data = $this->data($this->ticketTools->show(ticket: 1, deep: true));

        $this->assertArrayHasKey('phases', $data);
        $this->assertArrayHasKey('requirements', $data);
        $this->assertArrayHasKey('logs', $data);
        $this->assertArrayHasKey('transitions', $data);

        $phases = $data['phases'];
        $this->assertIsArray($phases);
        $phase = $phases[0];
        $this->assertIsArray($phase);
        $this->assertArrayHasKey('description', $phase);
        $this->assertArrayHasKey('ai_description', $phase);
    }

    #[Test]
    public function it_returns_the_outline_shape_with_child_fields_omitted_when_outline_is_true(): void
    {
        $this->scaffold();

        $data = $this->data($this->ticketTools->show(ticket: 1, outline: true));

        $this->assertArrayHasKey('phases', $data);
        $this->assertArrayHasKey('requirements', $data);
        $this->assertArrayNotHasKey('logs', $data);
        $this->assertArrayNotHasKey('transitions', $data);

        $phases = $data['phases'];
        $this->assertIsArray($phases);
        $phase = $phases[0];
        $this->assertIsArray($phase);
        $this->assertArrayNotHasKey('description', $phase);
        $this->assertArrayNotHasKey('ai_description', $phase);

        $tasks = $phase['tasks'];
        $this->assertIsArray($tasks);
        $task = $tasks[0];
        $this->assertIsArray($task);
        $this->assertArrayNotHasKey('description', $task);
        $this->assertArrayNotHasKey('ai_description', $task);
        $this->assertArrayNotHasKey('result', $task);
        $this->assertArrayNotHasKey('ai_result', $task);

        $requirements = $data['requirements'];
        $this->assertIsArray($requirements);
        $requirement = $requirements[0];
        $this->assertIsArray($requirement);
        $this->assertArrayNotHasKey('description', $requirement);
        $this->assertArrayNotHasKey('ai_description', $requirement);
    }

    #[Test]
    public function it_prefers_outline_over_deep_when_both_flags_are_true(): void
    {
        $this->scaffold();

        $data = $this->data($this->ticketTools->show(ticket: 1, deep: true, outline: true));

        $this->assertArrayNotHasKey('logs', $data);
        $this->assertArrayNotHasKey('transitions', $data);

        $phases = $data['phases'];
        $this->assertIsArray($phases);
        $phase = $phases[0];
        $this->assertIsArray($phase);
        $this->assertArrayNotHasKey('description', $phase);
        $this->assertArrayNotHasKey('ai_description', $phase);
    }
}
