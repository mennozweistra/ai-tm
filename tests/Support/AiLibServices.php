<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Support;

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
use AiToolset\AiLib\Repositories\TicketRepository;
use AiToolset\AiLib\Services\GrindRunService;
use AiToolset\AiLib\Services\Ordering;
use AiToolset\AiLib\Services\PhaseService;
use AiToolset\AiLib\Services\ProjectService;
use AiToolset\AiLib\Services\QuestionService;
use AiToolset\AiLib\Services\RollupService;
use AiToolset\AiLib\Services\StatusDerivation;
use AiToolset\AiLib\Services\TaskService;
use AiToolset\AiLib\Services\TicketService;
use AiToolset\AiLib\Services\TransitionRecorder;
use PDO;

/**
 * Builds the real `ai-lib` services the grind loop depends on, wired against a
 * single in-memory SQLite PDO (no mocks — see AGENTS.md test discipline). Shared
 * by the grind-loop tests here and, later, the budgeted-procedure tests.
 */
final readonly class AiLibServices
{
    public function __construct(
        public ProjectService $projects,
        public TicketService $tickets,
        public PhaseService $phases,
        public TaskService $tasks,
        public GrindRunService $grindRuns,
        public QuestionService $questions,
    ) {}

    public static function build(PDO $pdo): self
    {
        $clock = new SystemClock();
        $ordering = new Ordering();
        $config = Config::default();

        $projectRepo = new ProjectRepository($pdo);
        $ticketRepo = new TicketRepository($pdo);
        $phaseRepo = new PhaseRepository($pdo);
        $taskRepo = new TaskRepository($pdo);
        $logRepo = new LogEntryRepository($pdo);
        $transitionRepo = new StatusTransitionRepository($pdo);
        $requirementRepo = new RequirementRepository($pdo);
        $questionRepo = new QuestionRepository($pdo);
        $grindRunRepo = new GrindRunRepository($pdo);

        $recorder = new TransitionRecorder($pdo, $clock);
        $rollup = new RollupService(
            projectRepository: $projectRepo,
            ticketRepository: $ticketRepo,
            phaseRepository: $phaseRepo,
            taskRepository: $taskRepo,
            recorder: $recorder,
            derivation: new StatusDerivation(),
            clock: $clock,
        );

        return new self(
            projects: new ProjectService(
                repository: $projectRepo,
                clock: $clock,
                ticketRepository: $ticketRepo,
            ),
            tickets: new TicketService(
                pdo: $pdo,
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
                questionRepository: $questionRepo,
            ),
            phases: new PhaseService(
                pdo: $pdo,
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
            ),
            tasks: new TaskService(
                pdo: $pdo,
                phaseRepository: $phaseRepo,
                repository: $taskRepo,
                recorder: $recorder,
                ordering: $ordering,
                clock: $clock,
                config: $config,
                rollup: $rollup,
                logEntryRepository: $logRepo,
                statusTransitionRepository: $transitionRepo,
            ),
            grindRuns: new GrindRunService(
                pdo: $pdo,
                ticketRepository: $ticketRepo,
                repository: $grindRunRepo,
            ),
            questions: new QuestionService(
                pdo: $pdo,
                ticketRepository: $ticketRepo,
                repository: $questionRepo,
                clock: $clock,
                config: $config,
                taskRepository: $taskRepo,
            ),
        );
    }
}
