<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Mcp;

use AiToolset\AiLib\Domain\Config;
use AiToolset\AiLib\Domain\SystemClock;
use AiToolset\AiLib\Repositories\GrindRunRepository;
use AiToolset\AiLib\Repositories\LogEntryRepository;
use AiToolset\AiLib\Repositories\PhaseRepository;
use AiToolset\AiLib\Repositories\ProjectRepository;
use AiToolset\AiLib\Repositories\QuestionRepository;
use AiToolset\AiLib\Repositories\RequirementRepository;
use AiToolset\AiLib\Repositories\StatusTransitionRepository;
use AiToolset\AiLib\Repositories\TaskRepository;
use AiToolset\AiLib\Repositories\TemplateRepository;
use AiToolset\AiLib\Repositories\TicketRepository;
use AiToolset\AiLib\Services\GrindRunService;
use AiToolset\AiLib\Services\LogService;
use AiToolset\AiLib\Services\Ordering;
use AiToolset\AiLib\Services\PhaseService;
use AiToolset\AiLib\Services\ProjectService;
use AiToolset\AiLib\Services\QuestionService;
use AiToolset\AiLib\Services\RequirementService;
use AiToolset\AiLib\Services\RollupService;
use AiToolset\AiLib\Services\StatusDerivation;
use AiToolset\AiLib\Services\TaskService;
use AiToolset\AiLib\Services\TicketService;
use AiToolset\AiLib\Services\TransitionRecorder;
use AiToolset\AiLib\Testing\InMemoryDatabase;
use AiToolset\Tm\Mcp\Tools\ConfigTools;
use AiToolset\Tm\Mcp\Tools\GrindRunTools;
use AiToolset\Tm\Mcp\Tools\LogTools;
use AiToolset\Tm\Mcp\Tools\PhaseTools;
use AiToolset\Tm\Mcp\Tools\ProjectTools;
use AiToolset\Tm\Mcp\Tools\QuestionTools;
use AiToolset\Tm\Mcp\Tools\RequirementTools;
use AiToolset\Tm\Mcp\Tools\TaskTools;
use AiToolset\Tm\Mcp\Tools\TicketTools;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class BaseMcpTest extends TestCase
{
    /**
     * Name of the structure-free template written into scaffoldTemplateDir
     * for tests that only need a valid ticket, not specific phases or tasks.
     */
    protected const string SCAFFOLD_TEMPLATE = 'scaffold';

    protected PDO $pdo;
    protected ProjectTools $projectTools;
    protected TicketTools $ticketTools;
    protected TicketService $ticketService;
    protected PhaseTools $phaseTools;
    protected TaskTools $taskTools;
    protected LogTools $logTools;
    protected ConfigTools $configTools;
    protected RequirementTools $requirementTools;
    protected QuestionTools $questionTools;
    protected GrindRunTools $grindRunTools;
    private string $scaffoldTemplateDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = InMemoryDatabase::create();

        $this->scaffoldTemplateDir = sys_get_temp_dir() . '/tm-base-mcp-test-' . uniqid();
        $scaffoldTemplateRepo = new TemplateRepository([$this->scaffoldTemplateDir], $this->scaffoldTemplateDir);
        $scaffoldTemplateRepo->write(self::SCAFFOLD_TEMPLATE, [
            'description' => '',
            'ai_description' => '',
            'phases' => [],
        ]);

        $config = Config::default();
        $clock = new SystemClock();
        $ordering = new Ordering();
        $derivation = new StatusDerivation();

        $projectRepo = new ProjectRepository($this->pdo);
        $ticketRepo = new TicketRepository($this->pdo);
        $phaseRepo = new PhaseRepository($this->pdo);
        $taskRepo = new TaskRepository($this->pdo);
        $logRepo = new LogEntryRepository($this->pdo);
        $transitionRepo = new StatusTransitionRepository($this->pdo);
        $recorder = new TransitionRecorder($this->pdo, $clock);

        $rollup = new RollupService(
            projectRepository: $projectRepo,
            ticketRepository: $ticketRepo,
            phaseRepository: $phaseRepo,
            taskRepository: $taskRepo,
            recorder: $recorder,
            derivation: $derivation,
            clock: $clock,
        );

        $this->projectTools = new ProjectTools(new ProjectService(
            repository: $projectRepo,
            clock: $clock,
            ticketRepository: $ticketRepo,
        ));

        $requirementRepo = new RequirementRepository($this->pdo);

        $this->ticketService = new TicketService(
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
            templateRepository: $scaffoldTemplateRepo,
        );

        $this->ticketTools = new TicketTools($this->ticketService);

        $this->phaseTools = new PhaseTools(new PhaseService(
            pdo: $this->pdo,
            ticketRepository: $ticketRepo,
            repository: $phaseRepo,
            recorder: $recorder,
            ordering: $ordering,
            clock: $clock,
            config: $config,
            rollup: $rollup,
            taskRepository: $taskRepo,
            logEntryRepository: $logRepo,
            statusTransitionRepository: $transitionRepo,
        ));

        $taskService = new TaskService(
            pdo: $this->pdo,
            phaseRepository: $phaseRepo,
            repository: $taskRepo,
            recorder: $recorder,
            ordering: $ordering,
            clock: $clock,
            config: $config,
            rollup: $rollup,
            logEntryRepository: $logRepo,
            statusTransitionRepository: $transitionRepo,
        );

        $this->taskTools = new TaskTools($taskService);

        $this->logTools = new LogTools(new LogService(
            ticketRepository: $ticketRepo,
            phaseRepository: $phaseRepo,
            taskRepository: $taskRepo,
            repository: $logRepo,
            clock: $clock,
            config: $config,
        ));

        $this->configTools = new ConfigTools($config);

        $this->requirementTools = new RequirementTools(new RequirementService(
            pdo: $this->pdo,
            ticketRepository: $ticketRepo,
            repository: $requirementRepo,
            ordering: $ordering,
            config: $config,
        ));

        $this->grindRunTools = new GrindRunTools(new GrindRunService(
            pdo: $this->pdo,
            ticketRepository: $ticketRepo,
            repository: new GrindRunRepository($this->pdo),
        ));

        $this->questionTools = new QuestionTools(new QuestionService(
            pdo: $this->pdo,
            ticketRepository: $ticketRepo,
            repository: new QuestionRepository($this->pdo),
            clock: $clock,
            config: $config,
            taskRepository: $taskRepo,
        ));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (is_file($this->scaffoldTemplateDir . '/' . self::SCAFFOLD_TEMPLATE . '.toml')) {
            unlink($this->scaffoldTemplateDir . '/' . self::SCAFFOLD_TEMPLATE . '.toml');
        }

        if (is_dir($this->scaffoldTemplateDir)) {
            rmdir($this->scaffoldTemplateDir);
        }
    }

    /** @param array<mixed, mixed> $result */
    protected function assertOk(array $result): void
    {
        $this->assertTrue((bool) $result['ok'], 'Expected ok=true, got: ' . json_encode($result));
    }

    /** @param array<mixed, mixed> $result */
    protected function assertFailed(array $result, string $expectedCode): void
    {
        $this->assertFalse((bool) $result['ok'], 'Expected ok=false, got: ' . json_encode($result));
        $error = $result['error'];
        $this->assertIsArray($error);
        $this->assertSame($expectedCode, $error['code']);
    }

    /**
     * @param array<mixed, mixed> $result
     * @return array<mixed, mixed>
     */
    protected function data(array $result): array
    {
        $this->assertOk($result);
        $data = $result['data'];
        $this->assertIsArray($data);

        return $data;
    }
}
