<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli;

use AiToolset\Tm\Cli\Commands\ConfigListCommand;
use AiToolset\Tm\Cli\Commands\HookDisableCommand;
use AiToolset\Tm\Cli\Commands\HookEnableCommand;
use AiToolset\Tm\Cli\Commands\LogAddCommand;
use AiToolset\Tm\Cli\Commands\LogListCommand;
use AiToolset\Tm\Cli\Commands\MigrateCommand;
use AiToolset\Tm\Cli\Commands\PhaseAddCommand;
use AiToolset\Tm\Cli\Commands\PhaseDeleteCommand;
use AiToolset\Tm\Cli\Commands\PhaseListCommand;
use AiToolset\Tm\Cli\Commands\PhaseMoveCommand;
use AiToolset\Tm\Cli\Commands\PhaseSetCommand;
use AiToolset\Tm\Cli\Commands\PhaseShowCommand;
use AiToolset\Tm\Cli\Commands\ProjectAddCommand;
use AiToolset\Tm\Cli\Commands\ProjectArchiveCommand;
use AiToolset\Tm\Cli\Commands\ProjectListCommand;
use AiToolset\Tm\Cli\Commands\ProjectRestoreCommand;
use AiToolset\Tm\Cli\Commands\ProjectSetCommand;
use AiToolset\Tm\Cli\Commands\ProjectShowCommand;
use AiToolset\Tm\Cli\Commands\QuestionAddCommand;
use AiToolset\Tm\Cli\Commands\QuestionListCommand;
use AiToolset\Tm\Cli\Commands\QuestionProcessCommand;
use AiToolset\Tm\Cli\Commands\QuestionResolveCommand;
use AiToolset\Tm\Cli\Commands\QuestionShowCommand;
use AiToolset\Tm\Cli\Commands\QuestionWithdrawCommand;
use AiToolset\Tm\Cli\Commands\RequirementAddCommand;
use AiToolset\Tm\Cli\Commands\RequirementDeleteCommand;
use AiToolset\Tm\Cli\Commands\RequirementListCommand;
use AiToolset\Tm\Cli\Commands\RequirementMoveCommand;
use AiToolset\Tm\Cli\Commands\RequirementSetCommand;
use AiToolset\Tm\Cli\Commands\RequirementShowCommand;
use AiToolset\Tm\Cli\Commands\SetupCommand;
use AiToolset\Tm\Cli\Commands\TaskAddCommand;
use AiToolset\Tm\Cli\Commands\TaskDeleteCommand;
use AiToolset\Tm\Cli\Commands\TaskListCommand;
use AiToolset\Tm\Cli\Commands\TaskMoveCommand;
use AiToolset\Tm\Cli\Commands\TaskSetCommand;
use AiToolset\Tm\Cli\Commands\TaskShowCommand;
use AiToolset\Tm\Cli\Commands\TemplateListCommand;
use AiToolset\Tm\Cli\Commands\TicketAddCommand;
use AiToolset\Tm\Cli\Commands\TicketArchiveCommand;
use AiToolset\Tm\Cli\Commands\TicketListCommand;
use AiToolset\Tm\Cli\Commands\TicketRestoreCommand;
use AiToolset\Tm\Cli\Commands\TicketSetCommand;
use AiToolset\Tm\Cli\Commands\TicketShowCommand;
use AiToolset\Tm\Cli\HookSettingsInstaller;
use AiToolset\AiLib\Domain\Config;
use AiToolset\AiLib\Domain\SystemClock;
use AiToolset\AiLib\Repositories\LogEntryRepository;
use AiToolset\AiLib\Repositories\PhaseRepository;
use AiToolset\AiLib\Repositories\ProjectRepository;
use AiToolset\AiLib\Repositories\QuestionRepository;
use AiToolset\AiLib\Repositories\RequirementRepository;
use AiToolset\AiLib\Repositories\StatusTransitionRepository;
use AiToolset\AiLib\Repositories\TaskRepository;
use AiToolset\AiLib\Repositories\TemplateRepository;
use AiToolset\AiLib\Repositories\TicketRepository;
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
use Symfony\Component\Console\Application as BaseApplication;

final class Application extends BaseApplication
{
    private PDO $pdo;
    private string $dbPath;
    private string $dataDir;
    private string $hookSettingsPath;
    private readonly string $migrationsPath;

    public function __construct(?PDO $pdo = null, ?Config $config = null)
    {
        parent::__construct('tm', '0.1.0');

        $this->migrationsPath = SchemaChecker::defaultMigrationsPath();

        if ($pdo instanceof \PDO) {
            $this->pdo = $pdo;
            $this->dbPath = ':memory:';
            $this->dataDir = ':memory:';
            $this->hookSettingsPath = ':memory:';
        } else {
            $home = (string) getenv('HOME');
            $this->dataDir = self::dataDir($home);
            $this->dbPath = self::resolveDbPath($home, getenv('TM_DB'));
            // Self-migration (ticket 269, requirement 685): every CLI invocation
            // brings its own store up to date before it opens it — creating the
            // data directory and the file on a first run — so a composer update
            // that ships a migration needs no manual `tm migrate`. Nothing left
            // here can find a stale schema, which is why doRun() no longer gates
            // on SchemaChecker. Throws on a store written by a newer release;
            // bin/tm renders that in the CLI's JSON error shape.
            new SchemaMigrator($this->dbPath, $this->migrationsPath)->migrate();
            $this->pdo = $this->openDatabase($this->dbPath);
            $this->hookSettingsPath = $home . '/.claude/settings.json';
        }

        $config ??= Config::default();

        $this->wireCommands($this->pdo, $config);
    }

    private function wireCommands(PDO $pdo, Config $config): void
    {
        $hookSettingsInstaller = new HookSettingsInstaller($this->hookSettingsPath);

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
        $templateRepo = new TemplateRepository(
            readPaths: [self::userTemplatesPath($this->dataDir), self::templatesPath()],
            writePath: self::userTemplatesPath($this->dataDir),
        );

        $recorder = new TransitionRecorder($pdo, $clock);

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
            templateRepository: $templateRepo,
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

        $questionService = new QuestionService(
            pdo: $pdo,
            ticketRepository: $ticketRepo,
            repository: $questionRepo,
            clock: $clock,
            config: $config,
            taskRepository: $taskRepo,
        );

        $this->addCommands([
            new SetupCommand($this->dataDir),
            new MigrateCommand($this->dbPath, $this->migrationsPath),
            new HookEnableCommand($hookSettingsInstaller),
            new HookDisableCommand($hookSettingsInstaller),
            new ProjectAddCommand($projectService),
            new ProjectListCommand($projectService),
            new ProjectShowCommand($projectService),
            new ProjectSetCommand($projectService),
            new ProjectArchiveCommand($projectService),
            new ProjectRestoreCommand($projectService),
            new TicketAddCommand($ticketService),
            new TicketListCommand($ticketService),
            new TicketShowCommand($ticketService),
            new TicketSetCommand($ticketService),
            new TicketArchiveCommand($ticketService),
            new TicketRestoreCommand($ticketService),
            new PhaseAddCommand($phaseService),
            new PhaseListCommand($phaseService),
            new PhaseShowCommand($phaseService),
            new PhaseSetCommand($phaseService),
            new PhaseMoveCommand($phaseService),
            new PhaseDeleteCommand($phaseService),
            new TaskAddCommand($taskService),
            new TaskListCommand($taskService),
            new TaskShowCommand($taskService),
            new TaskSetCommand($taskService),
            new TaskMoveCommand($taskService),
            new TaskDeleteCommand($taskService),
            new LogAddCommand($logService),
            new LogListCommand($logService),
            new RequirementAddCommand($requirementService),
            new RequirementListCommand($requirementService),
            new RequirementShowCommand($requirementService),
            new RequirementSetCommand($requirementService),
            new RequirementMoveCommand($requirementService),
            new RequirementDeleteCommand($requirementService),
            new QuestionAddCommand($questionService),
            new QuestionListCommand($questionService),
            new QuestionShowCommand($questionService),
            new QuestionResolveCommand($questionService),
            new QuestionWithdrawCommand($questionService),
            new QuestionProcessCommand($questionService),
            new ConfigListCommand($config),
            new TemplateListCommand($ticketService),
        ]);
    }

    /**
     * The package's own root directory, found from this file's own location
     * rather than guessed from a consumer's directory layout (ticket 269 task
     * 3654). Works unchanged whether tm is a development checkout or a
     * composer global vendor install.
     */
    public static function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * The package's shipped templates directory. Read-only from tm's point of
     * view: under a composer install this sits inside the vendor tree, so a
     * `composer update` can silently replace its contents (ticket 269). Public
     * and static so tests can seed shipped-template fixtures at the exact path
     * the CLI itself will read, without instantiating an Application first.
     */
    public static function templatesPath(): string
    {
        return self::packageRoot() . '/templates';
    }

    /**
     * The data directory tm's data lives in — store.db, the user's templates,
     * and (elsewhere, via a different path) the hook settings file. Pure and
     * testable: takes $home as a parameter, mirroring resolveDbPath().
     */
    public static function dataDir(string $home): string
    {
        return $home . '/.ai-tm';
    }

    /**
     * True when this package's own vendor/autoload.php is absent from its
     * root — i.e. it is installed as a dependency inside a consumer's vendor
     * tree (`composer global require ai-toolset/tm`) rather than run from a
     * development checkout that carries its own vendor/ (ticket 269 task
     * 3654, the info command's install-layout fact). Mirrors bin/tm's own two-candidate
     * autoload probe. Pure and testable: takes the package root as a
     * parameter instead of reading __DIR__ directly.
     */
    public static function isVendorInstall(string $packageRoot): bool
    {
        return !is_file($packageRoot . '/vendor/autoload.php');
    }

    /**
     * The user's own template directory, next to the data directory that also
     * holds store.db (ticket 269, requirement 692). This is where export
     * writes and where a template of a colliding name takes priority over the
     * shipped one in templatesPath() — a composer update never touches it.
     * Pure and testable: takes the data directory as a parameter, mirroring
     * resolveDbPath().
     */
    public static function userTemplatesPath(string $dataDir): string
    {
        return $dataDir . '/templates';
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

    private function openDatabase(string $path): PDO
    {
        $pdo = new PDO("sqlite:{$path}");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }
}
