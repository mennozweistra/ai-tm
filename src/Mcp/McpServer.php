<?php

declare(strict_types=1);

namespace AiToolset\Tm\Mcp;

use AiToolset\AiLib\Domain\Config;
use AiToolset\AiLib\Domain\SystemClock;
use AiToolset\Tm\Mcp\Resources\ResourcesProvider;
use AiToolset\Tm\Mcp\Tools\ConfigTools;
use AiToolset\Tm\Mcp\Tools\GrindRunTools;
use AiToolset\Tm\Mcp\Tools\GrillTool;
use AiToolset\Tm\Mcp\Tools\GrindTool;
use AiToolset\Tm\Mcp\Tools\LogTools;
use AiToolset\Tm\Mcp\Tools\PhaseTools;
use AiToolset\Tm\Mcp\Tools\ProjectTools;
use AiToolset\Tm\Mcp\Tools\QuestionTools;
use AiToolset\Tm\Mcp\Tools\RequirementTools;
use AiToolset\Tm\Mcp\Tools\StartHookTool;
use AiToolset\Tm\Mcp\Tools\StopHookTool;
use AiToolset\Tm\Mcp\Tools\TaskTools;
use AiToolset\Tm\Mcp\Tools\TemplateExportTool;
use AiToolset\Tm\Mcp\Tools\TemplateListTool;
use AiToolset\Tm\Mcp\Tools\TicketTools;
use AiToolset\AiLib\Repositories\GrindRunRepository;
use AiToolset\AiLib\Repositories\LogEntryRepository;
use AiToolset\AiLib\Repositories\PhaseRepository;
use AiToolset\AiLib\Repositories\ProjectRepository;
use AiToolset\AiLib\Repositories\QuestionRepository;
use AiToolset\AiLib\Repositories\RequirementRepository;
use AiToolset\AiLib\Repositories\StatusTransitionRepository;
use AiToolset\AiLib\Repositories\TaskRepository;
use AiToolset\AiLib\Repositories\TemplateRepository as AiLibTemplateRepository;
use AiToolset\AiLib\Repositories\TicketRepository;
use AiToolset\AiLib\Services\GrindRunService;
use AiToolset\AiLib\Services\LogService;
use AiToolset\AiLib\Services\Ordering;
use AiToolset\AiLib\Services\PhaseService;
use AiToolset\AiLib\Services\ProjectService;
use AiToolset\AiLib\Services\QuestionService;
use AiToolset\AiLib\Services\RequirementService;
use AiToolset\AiLib\Services\RollupService;
use AiToolset\AiLib\Services\SchemaChecker;
use AiToolset\AiLib\Services\SchemaMigrator;
use AiToolset\AiLib\Services\StatusDerivation;
use AiToolset\AiLib\Services\TaskService;
use AiToolset\AiLib\Services\TicketService;
use AiToolset\AiLib\Services\TransitionRecorder;
use PDO;
use PhpMcp\Server\Defaults\BasicContainer;
use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StdioServerTransport;

final class McpServer
{
    private const string INSTRUCTIONS = <<<'TXT'
        tm is a thin workflow manager built on top of ai-lib's entity model. It exposes
        that data plane to humans through a CLI and to AI agents through this MCP
        server. tm has no entities of its own: every project, ticket, phase, task, and
        log entry you create or read here is stored, validated, and rolled up by
        ai-lib. Treat tm as a mechanical surface — it executes the operation you ask
        for and returns structured JSON; it does not infer intent, fill in missing
        fields, or pick ids for you.

        The entity hierarchy is project -> ticket -> phase -> task -> log: a project
        owns tickets, a ticket owns phases, a phase owns tasks, and tasks (and the
        levels above them) accept append-only log entries.

        The status lifecycle for every level is pending -> active -> done. tm enforces
        no workflow rules of its own on top of ai-lib; any valid status is reachable
        from any other.

        The planning shape mirrors the hierarchy: features are tickets, milestones
        inside a feature are phases, and the concrete steps inside a milestone are
        tasks; long-form intent goes in the description and ai_description fields,
        and the outcome of a task goes in result when it transitions to done.

        When the user asks you to grind one or more tickets (for example
        "grind ticket 89" or "grind 12 13"), call tm_grind before taking any other
        action and follow the protocol it returns. Do not begin executing the
        ticket's tasks from this conversation: the protocol runs each task in its own
        subagent so your context stays small.

        When the user asks to grill a ticket or start its Discovery phase (for
        example "grill ticket 89" or "start Discovery for 12"), call tm_grill with
        the ticket id and follow the protocol it returns.

        When you have a question for the user in conversation, store it with
        tm_question_add before discussing it — kind "ask" (or "check" if already
        decided and acted), self-contained for a cold reader, with a recommendation
        and your model; task empty unless one is active. A multi-issue reply stores
        every item first, so later ones survive the first eating the session. When
        the user answers, resolve it that turn with tm_question_resolve
        (resolution_quality: direct/clarified/deepened), then tm_question_process
        unless follow-up work remains. A question needs a ticket in play; with
        none, don't store one — say so if it matters.

        When the user talks about a protocol or a rule ("the protocol says X",
        "you should do X"), he is opening a conversation — usually to examine
        what went wrong and decide together how to improve the protocol. Respond
        as a discussion partner; do not execute X, and do not apply conclusions
        unilaterally. Execution starts or resumes only on an explicit instruction
        ("continue", "resume", "go ahead", "grind it"). When a message could be
        read either way, ask one short question instead of acting. An entity's
        status field is authoritative: never override it based on inference from
        other fields (for example, a done task with an empty result was closed
        deliberately, not by mistake).

        Common anti-patterns to avoid:
          - inventing string slugs for ids; tm uses integer ids only.
          - adding tasks to a ticket without first creating a phase to hold them.
          - amending a task's result after it has been set to done.
          - calling tm_grind or tm_start_hook expecting orientation text.
          - writing planning notes in scratch files outside tm.

        For the long form of any of the above, fetch these MCP resources:
        tm://overview, tm://entity-model, tm://workflow, tm://anti-patterns.
        TXT;

    public function run(?PDO $pdo = null, ?Config $config = null): void
    {
        $server = $this->boot($pdo, $config);

        $transport = new StdioServerTransport();
        $server->listen($transport);
    }

    /**
     * Initialises the server without starting to listen on stdio.
     *
     * Migrates the store up to the schema this release ships, then builds and discovers the
     * server. Writes nothing outside ~/.ai-tm: tm no longer installs anything into ~/.claude
     * or a project's .claude/ on startup (ticket 269) — the Claude Code hooks are opt-in only,
     * via `tm hook:enable`. Self-migration replaces the schema check this used to run
     * (requirement 685): refusing to start on a pending migration was safe but useless under a
     * composer install, where nobody runs `tm migrate`. It stays a fail-to-start gate in the one
     * case that cannot be fixed automatically — a store written by a newer release, which throws
     * SchemaAheadException and maps to `schema_ahead` in BaseTools.
     *
     * A caller that supplies its own PDO owns its schema: the connection carries no path to
     * migrate, so nothing runs. Exposed as a separate method so tests can exercise startup
     * logic without blocking on stdio.
     */
    public function boot(?PDO $pdo = null, ?Config $config = null): Server
    {
        if (!$pdo instanceof PDO) {
            $path = self::resolveDbPath((string) getenv('HOME'), getenv('TM_DB'));
            new SchemaMigrator($path, SchemaChecker::defaultMigrationsPath())->migrate();
            $pdo = $this->openDatabase($path);
        }

        $config ??= Config::default();

        $container = $this->buildContainer($pdo, $config);

        $server = Server::make()
            ->withServerInfo('tm', '0.1.0')
            ->withInstructions(self::INSTRUCTIONS)
            ->withSession('array', PHP_INT_MAX)
            ->withContainer($container)
            ->build();

        $server->discover(
            basePath: dirname(__DIR__),
            scanDirs: ['Mcp/Tools', 'Mcp/Resources'],
        );

        return $server;
    }

    private function buildContainer(PDO $pdo, Config $config): BasicContainer
    {
        $clock = new SystemClock();
        $ordering = new Ordering();
        $derivation = new StatusDerivation();

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
        $userTemplatesDir = self::userTemplatesPath((string) getenv('HOME'));
        $packageTemplatesDir = dirname(__DIR__, 2) . '/templates';
        // Two-source resolution (ticket 269, requirement 692): the user's own
        // template directory, next to store.db, is read before the package's
        // shipped templates/ and is the only place export() writes to. A
        // composer update replaces $packageTemplatesDir but never touches
        // $userTemplatesDir, and a same-named user template always wins.
        $aiLibTemplateRepo = new AiLibTemplateRepository(
            readPaths: [$userTemplatesDir, $packageTemplatesDir],
            writePath: $userTemplatesDir,
        );

        $rollup = new RollupService(
            projectRepository: $projectRepo,
            ticketRepository: $ticketRepo,
            phaseRepository: $phaseRepo,
            taskRepository: $taskRepo,
            recorder: $recorder,
            derivation: $derivation,
            clock: $clock,
        );

        $projectService = new ProjectService(
            repository: $projectRepo,
            clock: $clock,
            ticketRepository: $ticketRepo,
        );

        $ticketService = new TicketService(
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
            templateRepository: $aiLibTemplateRepo,
            questionRepository: $questionRepo,
        );

        $phaseService = new PhaseService(
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
        );

        $taskService = new TaskService(
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
        );

        $logService = new LogService(
            ticketRepository: $ticketRepo,
            phaseRepository: $phaseRepo,
            taskRepository: $taskRepo,
            repository: $logRepo,
            clock: $clock,
            config: $config,
        );

        $requirementService = new RequirementService(
            pdo: $pdo,
            ticketRepository: $ticketRepo,
            repository: $requirementRepo,
            ordering: $ordering,
            config: $config,
        );

        $grindRunService = new GrindRunService(
            pdo: $pdo,
            ticketRepository: $ticketRepo,
            repository: $grindRunRepo,
        );

        $questionService = new QuestionService(
            pdo: $pdo,
            ticketRepository: $ticketRepo,
            repository: $questionRepo,
            clock: $clock,
            config: $config,
            taskRepository: $taskRepo,
        );

        $container = new BasicContainer();
        $container->set(ProjectTools::class, new ProjectTools($projectService));

        $container->set(TicketTools::class, new TicketTools($ticketService));
        $container->set(PhaseTools::class, new PhaseTools($phaseService));
        $container->set(RequirementTools::class, new RequirementTools($requirementService));
        $container->set(QuestionTools::class, new QuestionTools($questionService));
        $container->set(GrindRunTools::class, new GrindRunTools($grindRunService));
        $container->set(TaskTools::class, new TaskTools($taskService));
        $container->set(LogTools::class, new LogTools($logService));
        $container->set(ConfigTools::class, new ConfigTools($config));
        $container->set(StopHookTool::class, new StopHookTool());
        $container->set(StartHookTool::class, new StartHookTool());
        $container->set(GrindTool::class, new GrindTool());
        $container->set(GrillTool::class, new GrillTool($ticketService));
        $container->set(ResourcesProvider::class, new ResourcesProvider());

        $container->set(TemplateExportTool::class, new TemplateExportTool($ticketService));
        $container->set(TemplateListTool::class, new TemplateListTool($ticketService));

        return $container;
    }

    /**
     * Resolves the database path from the TM_DB environment variable, falling
     * back to the default store under $home when TM_DB is unset or empty.
     * Pure and testable: takes env values as parameters, never calls getenv.
     */
    public static function resolveDbPath(string $home, string|false $tmDb): string
    {
        return ($tmDb !== false && $tmDb !== '') ? $tmDb : $home . '/.ai-tm/store.db';
    }

    /**
     * The user's own template directory, next to the store.db default
     * location under $home (ticket 269, requirement 692). Pure and testable:
     * takes $home as a parameter, mirroring resolveDbPath().
     */
    public static function userTemplatesPath(string $home): string
    {
        return $home . '/.ai-tm/templates';
    }

    private function openDatabase(string $path): PDO
    {
        $pdo = new PDO("sqlite:{$path}");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }
}
