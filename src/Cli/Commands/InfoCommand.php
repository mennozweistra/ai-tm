<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\Application;
use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\Tm\Cli\HookSettingsInstaller;
use AiToolset\AiLib\Services\SchemaChecker;
use Composer\InstalledVersions;
use PDO;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Read-only installation report for support requests (ticket 269, requirement
 * 694). A user with a problem runs `tm info` and pastes the output to the
 * owner; the owner diagnoses from it instead of asking the user for the
 * installation facts one at a time.
 *
 * Deliberately not wired through Cli\Application: that constructor
 * self-migrates the store before opening it (ticket 269, requirement 685),
 * which would create ~/.ai-tm and store.db on a machine that has none — the
 * opposite of a read-only diagnostic. bin/tm detects `info` on argv before
 * constructing Application at all and runs this command through a bare
 * Symfony console app instead. This command reads $home, TM_DB and the working
 * directory (the working directory carries the two directory-scoped MCP
 * registrations, see mcpRegistrationReport()) directly
 * and touches the filesystem only with stat-like calls (is_file, is_dir,
 * file_get_contents) and, when the database file already exists, one PDO
 * connection running only SELECTs — never SchemaMigrator, never a write.
 *
 * No scoring, no advice engine: a flat map of facts, matching the CLI's
 * existing {ok, data} envelope. Every fact here is an installation detail —
 * paths, versions, hook markers — never a secret or a user's own data.
 */
final class InfoCommand extends BaseCommand
{
    public function __construct(
        private readonly string $home,
        private readonly string|false $tmDb,
        private readonly string $cwd,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('info')
            ->setDescription('Report installation facts for a support request. Read-only: never creates ~/.ai-tm or writes any file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $packageRoot = Application::packageRoot();
            $isVendorInstall = Application::isVendorInstall($packageRoot);
            $dataDir = Application::dataDir($this->home);
            $dbPath = Application::resolveDbPath($this->home, $this->tmDb);
            $userTemplatesDir = Application::userTemplatesPath($dataDir);
            $tmDbEnv = ($this->tmDb !== false && $this->tmDb !== '') ? $this->tmDb : null;

            return $this->success($output, [
                'tm_version' => $this->versionFact($packageRoot, $isVendorInstall),
                'php_version' => PHP_VERSION,
                'os' => match (PHP_OS_FAMILY) {
                    'Darwin' => 'macOS',
                    'Linux' => 'Linux',
                    default => PHP_OS_FAMILY,
                },
                'install_layout' => $isVendorInstall ? 'composer vendor install' : 'development checkout',
                'data_directory' => [
                    'path' => $dataDir,
                    'present' => is_dir($dataDir),
                ],
                'database' => $this->databaseReport($dbPath),
                'hooks' => $this->hooksReport($this->home),
                'mcp_registration' => $this->mcpRegistrationReport($this->home, $this->cwd),
                'user_templates_directory' => [
                    'path' => $userTemplatesDir,
                    'present' => is_dir($userTemplatesDir),
                ],
                'tm_db_env' => $tmDbEnv,
            ]);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }

    /**
     * "development checkout" plus the git short sha in a checkout layout — the
     * cheap, honest fact available with no tags and no release process yet.
     * In a vendor install, Composer\InstalledVersions carries the version and
     * commit composer itself resolved, which is the equivalent honest fact
     * for that layout (ticket 269 task 3654 decision).
     */
    private function versionFact(string $packageRoot, bool $isVendorInstall): string
    {
        if ($isVendorInstall) {
            if (!InstalledVersions::isInstalled('ai-toolset/tm')) {
                return 'unknown (composer metadata not found)';
            }

            $reference = InstalledVersions::getReference('ai-toolset/tm');
            $suffix = $reference !== null ? '@' . substr($reference, 0, 7) : '';

            return (InstalledVersions::getPrettyVersion('ai-toolset/tm') ?? 'unknown version') . $suffix;
        }

        $sha = $this->gitShortSha($packageRoot);

        return 'development checkout' . ($sha !== null ? " ({$sha})" : '');
    }

    private function gitShortSha(string $packageRoot): ?string
    {
        if (!file_exists($packageRoot . '/.git')) {
            return null;
        }

        $sha = shell_exec('git -C ' . escapeshellarg($packageRoot) . ' rev-parse --short HEAD 2>/dev/null');
        $sha = is_string($sha) ? trim($sha) : '';

        return $sha !== '' ? $sha : null;
    }

    /**
     * Reports whether the store exists and, only when it does, its migration
     * level via ai-lib's SchemaChecker — pending versions this release ships
     * that phinxlog has not recorded, and unknown versions phinxlog records
     * that this release ships no file for (a downgrade). Never constructs
     * SchemaMigrator: this is the pre-migration state, not the state after
     * self-migration a normal command would report (ticket 269 task 3654
     * decision). When the file does not exist, PDO is never opened — SQLite's
     * driver creates the file on connect, which is exactly the side effect a
     * read-only command must not have.
     *
     * @return array<string, mixed>
     */
    private function databaseReport(string $dbPath): array
    {
        $present = is_file($dbPath);
        $report = [
            'path' => $dbPath,
            'present' => $present,
        ];

        if (!$present) {
            return $report;
        }

        try {
            $pdo = new PDO("sqlite:{$dbPath}");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $checker = new SchemaChecker($pdo, SchemaChecker::defaultMigrationsPath());

            $report['readable'] = true;
            $report['pending_migrations'] = $checker->pendingVersions();
            $report['unknown_migrations'] = $checker->unknownVersions();
        } catch (\Throwable $e) {
            $report['readable'] = false;
            $report['error'] = $e->getMessage();
        }

        return $report;
    }

    /**
     * Checks both ~/.claude/settings.json and ~/.claude/settings.local.json —
     * Claude Code merges both, so a marker present in both means the hook
     * fires twice, exactly the state the owner's own machine reaches after
     * hook:enable runs once (requirement 694). Reports which file(s) each of
     * the three marker-tagged entries (Cli\HookSettingsInstaller's constants)
     * was found in.
     *
     * @return array<string, mixed>
     */
    private function hooksReport(string $home): array
    {
        $paths = [
            'settings_json' => $home . '/.claude/settings.json',
            'settings_local_json' => $home . '/.claude/settings.local.json',
        ];

        $files = [];
        $decoded = [];
        foreach ($paths as $key => $path) {
            [$status, $data] = $this->readJsonFile($path);
            $files[$key] = ['path' => $path, ...$status];
            $decoded[$key] = $data;
        }

        $markers = [
            'logging_hook' => HookSettingsInstaller::HOOK_MARKER,
            'start_hook' => HookSettingsInstaller::START_HOOK_MARKER,
            'grind_hook' => HookSettingsInstaller::GRIND_HOOK_MARKER,
        ];

        $markerReport = [];
        foreach ($markers as $name => $marker) {
            $foundIn = [];
            foreach (array_keys($paths) as $key) {
                if ($this->settingsContainsMarker($decoded[$key], $marker)) {
                    $foundIn[] = $key;
                }
            }
            $markerReport[$name] = ['found_in' => $foundIn];
        }

        return [
            'files' => $files,
            'markers' => $markerReport,
        ];
    }

    /**
     * Reports whether the `tm` MCP server is registered with Claude Code, in
     * each of the three scopes `claude mcp add` writes to: user (the
     * `mcpServers` map in ~/.claude.json), local (the same file's
     * `projects.<working directory>.mcpServers`, private to one directory) and
     * project (a `.mcp.json` in the working directory, checked into the
     * repository). The install instructions in the README use the user scope,
     * but a report that looked only there would call a working local- or
     * project-scope registration "not registered" and send support down the
     * wrong path.
     *
     * Per scope it reports the file's existence and JSON validity, and — when a
     * `tm` entry is present — the command and arguments Claude Code launches,
     * which is where a wrong or stale binary path becomes visible. The entry's
     * `env` block is deliberately left out: it can hold an API key, and this
     * report is meant to be pasted to someone else (requirement 694).
     *
     * @return array<string, mixed>
     */
    private function mcpRegistrationReport(string $home, string $cwd): array
    {
        $claudeJsonPath = $home . '/.claude.json';
        $mcpJsonPath = $cwd . '/.mcp.json';

        [$claudeJsonStatus, $claudeJson] = $this->readJsonFile($claudeJsonPath);
        [$mcpJsonStatus, $mcpJson] = $this->readJsonFile($mcpJsonPath);

        $projects = $claudeJson['projects'] ?? null;
        $localServers = is_array($projects) && isset($projects[$cwd]) && is_array($projects[$cwd])
            ? ($projects[$cwd]['mcpServers'] ?? null)
            : null;

        return [
            'user' => [
                'path' => $claudeJsonPath,
                ...$claudeJsonStatus,
                'tm_entry' => $this->tmServerEntry($claudeJson['mcpServers'] ?? null),
            ],
            'local' => [
                'path' => $claudeJsonPath,
                'directory' => $cwd,
                ...$claudeJsonStatus,
                'tm_entry' => $this->tmServerEntry($localServers),
            ],
            'project' => [
                'path' => $mcpJsonPath,
                ...$mcpJsonStatus,
                'tm_entry' => $this->tmServerEntry($mcpJson['mcpServers'] ?? null),
            ],
        ];
    }

    /**
     * @return array{command: string|null, args: list<string>}|null
     */
    private function tmServerEntry(mixed $servers): ?array
    {
        if (!is_array($servers) || !isset($servers['tm']) || !is_array($servers['tm'])) {
            return null;
        }

        $entry = $servers['tm'];
        $command = isset($entry['command']) && is_string($entry['command']) ? $entry['command'] : null;
        $args = isset($entry['args']) && is_array($entry['args'])
            ? array_values(array_filter($entry['args'], is_string(...)))
            : [];

        return ['command' => $command, 'args' => $args];
    }

    /**
     * @return array{0: array{exists: bool, valid_json: bool|null}, 1: array<mixed, mixed>}
     */
    private function readJsonFile(string $path): array
    {
        if (!is_file($path)) {
            return [['exists' => false, 'valid_json' => null], []];
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return [['exists' => true, 'valid_json' => true], []];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [['exists' => true, 'valid_json' => false], []];
        }

        return [['exists' => true, 'valid_json' => true], $decoded];
    }

    /** @param array<mixed, mixed> $settings */
    private function settingsContainsMarker(array $settings, string $marker): bool
    {
        if (!isset($settings['hooks']) || !is_array($settings['hooks'])) {
            return false;
        }

        foreach ($settings['hooks'] as $groups) {
            if (!is_array($groups)) {
                continue;
            }

            foreach ($groups as $group) {
                if (!is_array($group) || !isset($group['hooks']) || !is_array($group['hooks'])) {
                    continue;
                }

                foreach ($group['hooks'] as $hook) {
                    if (
                        is_array($hook)
                        && isset($hook['command'])
                        && is_string($hook['command'])
                        && str_contains($hook['command'], $marker)
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
