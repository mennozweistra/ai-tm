<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Cli;

use AiToolset\Tm\Cli\Application;
use AiToolset\Tm\Cli\Commands\InfoCommand;
use AiToolset\Tm\Cli\HookSettingsInstaller;
use AiToolset\AiLib\Services\SchemaChecker;
use AiToolset\AiLib\Services\SchemaMigrator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * InfoCommand is deliberately never wired through Cli\Application in these
 * tests (see the class docblock): the whole point of the command is to stay
 * read-only even when the store does not exist yet, which Application's
 * self-migrating constructor cannot guarantee.
 */
final class InfoCommandTest extends TestCase
{
    private string $home;

    private string $cwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->home = sys_get_temp_dir() . '/tm_info_' . uniqid('', true);
        mkdir($this->home, 0755, true);
        $this->cwd = $this->home . '/project';
        mkdir($this->cwd, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->home);
        parent::tearDown();
    }

    #[Test]
    public function it_reports_a_bare_home_as_absent_and_creates_nothing(): void
    {
        $data = $this->runInfo(tmDb: false);
        $database = $this->arrayAt($data, 'database');

        self::assertFalse($this->arrayAt($data, 'data_directory')['present']);
        self::assertFalse($database['present']);
        self::assertArrayNotHasKey('readable', $database);

        // The read-only guarantee this command exists to make: nothing was
        // created on the way to reporting that nothing exists.
        self::assertDirectoryDoesNotExist($this->home . '/.ai-tm');
    }

    #[Test]
    public function it_reports_pending_and_unknown_migrations_for_an_up_to_date_store(): void
    {
        $dbPath = $this->home . '/.ai-tm/store.db';
        mkdir(dirname($dbPath), 0755, true);
        new SchemaMigrator($dbPath, SchemaChecker::defaultMigrationsPath())->migrate();

        $data = $this->runInfo(tmDb: false);
        $database = $this->arrayAt($data, 'database');

        self::assertTrue($database['present']);
        self::assertTrue($database['readable']);
        self::assertSame([], $database['pending_migrations']);
        self::assertSame([], $database['unknown_migrations']);
    }

    #[Test]
    public function it_reports_a_non_sqlite_file_as_unreadable_without_touching_it(): void
    {
        $dbPath = $this->home . '/.ai-tm/store.db';
        mkdir(dirname($dbPath), 0755, true);
        file_put_contents($dbPath, 'not a database');

        $data = $this->runInfo(tmDb: false);
        $database = $this->arrayAt($data, 'database');

        self::assertTrue($database['present']);
        self::assertFalse($database['readable']);
        self::assertArrayHasKey('error', $database);
        self::assertSame('not a database', file_get_contents($dbPath));
    }

    #[Test]
    public function it_reports_which_settings_file_each_hook_marker_was_found_in(): void
    {
        mkdir($this->home . '/.claude', 0755, true);
        file_put_contents(
            $this->home . '/.claude/settings.json',
            json_encode([
                'hooks' => [
                    'Stop' => [
                        ['hooks' => [['type' => 'command', 'command' => 'echo x # ' . HookSettingsInstaller::HOOK_MARKER]]],
                    ],
                    'UserPromptSubmit' => [
                        ['hooks' => [['type' => 'command', 'command' => 'echo y # ' . HookSettingsInstaller::START_HOOK_MARKER]]],
                    ],
                ],
            ]),
        );
        file_put_contents(
            $this->home . '/.claude/settings.local.json',
            json_encode([
                'hooks' => [
                    'UserPromptSubmit' => [
                        ['hooks' => [['type' => 'command', 'command' => 'echo y # ' . HookSettingsInstaller::START_HOOK_MARKER]]],
                    ],
                ],
            ]),
        );

        $data = $this->runInfo(tmDb: false);
        $hooks = $this->arrayAt($data, 'hooks');
        $files = $this->arrayAt($hooks, 'files');
        $markers = $this->arrayAt($hooks, 'markers');
        $settingsJson = $this->arrayAt($files, 'settings_json');
        $settingsLocalJson = $this->arrayAt($files, 'settings_local_json');

        self::assertTrue($settingsJson['exists']);
        self::assertTrue($settingsJson['valid_json']);
        self::assertTrue($settingsLocalJson['exists']);

        self::assertSame(['settings_json'], $this->arrayAt($markers, 'logging_hook')['found_in']);
        // Present in both files — the double-fire state hook:enable leaves an
        // owner's machine in after the first run, which is exactly what a
        // support report needs to surface.
        self::assertSame(['settings_json', 'settings_local_json'], $this->arrayAt($markers, 'start_hook')['found_in']);
        self::assertSame([], $this->arrayAt($markers, 'grind_hook')['found_in']);
    }

    #[Test]
    public function it_reports_a_malformed_settings_file_without_raising(): void
    {
        mkdir($this->home . '/.claude', 0755, true);
        file_put_contents($this->home . '/.claude/settings.json', 'not json {{{');

        $data = $this->runInfo(tmDb: false);
        $hooks = $this->arrayAt($data, 'hooks');
        $settingsJson = $this->arrayAt($this->arrayAt($hooks, 'files'), 'settings_json');
        $loggingHook = $this->arrayAt($this->arrayAt($hooks, 'markers'), 'logging_hook');

        self::assertTrue($settingsJson['exists']);
        self::assertFalse($settingsJson['valid_json']);
        self::assertSame([], $loggingHook['found_in']);
        // Untouched — info never writes.
        self::assertSame('not json {{{', file_get_contents($this->home . '/.claude/settings.json'));
    }

    #[Test]
    public function it_reports_tm_db_when_set_and_null_when_unset(): void
    {
        $withEnv = $this->runInfo(tmDb: '/tmp/review.db');
        self::assertSame('/tmp/review.db', $withEnv['tm_db_env']);
        self::assertSame('/tmp/review.db', $this->arrayAt($withEnv, 'database')['path']);

        $withoutEnv = $this->runInfo(tmDb: false);
        self::assertNull($withoutEnv['tm_db_env']);
        self::assertSame($this->home . '/.ai-tm/store.db', $this->arrayAt($withoutEnv, 'database')['path']);
    }

    #[Test]
    public function it_reports_a_development_checkout_version_fact_for_this_repository(): void
    {
        // This test suite runs inside a development checkout (it has its own
        // vendor/autoload.php), never a composer global vendor install.
        self::assertFalse(Application::isVendorInstall(Application::packageRoot()));

        $data = $this->runInfo(tmDb: false);
        $version = $data['tm_version'];
        self::assertIsString($version);

        self::assertStringStartsWith('development checkout', $version);
        self::assertSame('development checkout', $data['install_layout']);
    }

    #[Test]
    public function it_reports_the_tm_mcp_entry_per_scope_and_never_its_env(): void
    {
        file_put_contents(
            $this->home . '/.claude.json',
            json_encode([
                'mcpServers' => [
                    'tm' => [
                        'command' => '/home/user/.composer/vendor/bin/tm-mcp',
                        'args' => [],
                        'env' => ['SOME_TOKEN' => 'sk-must-never-be-reported'],
                    ],
                    'playwright' => ['command' => 'npx'],
                ],
                'projects' => [
                    $this->cwd => ['mcpServers' => ['tm' => ['command' => 'php', 'args' => ['bin/tm-mcp']]]],
                ],
            ]),
        );
        file_put_contents(
            $this->cwd . '/.mcp.json',
            json_encode(['mcpServers' => ['tm' => ['command' => './bin/tm-mcp']]]),
        );

        $data = $this->runInfo(tmDb: false);
        $mcp = $this->arrayAt($data, 'mcp_registration');

        $user = $this->arrayAt($mcp, 'user');
        self::assertTrue($user['exists']);
        self::assertTrue($user['valid_json']);
        self::assertSame(
            ['command' => '/home/user/.composer/vendor/bin/tm-mcp', 'args' => []],
            $user['tm_entry'],
        );

        // The two directory-scoped registrations `claude mcp add --scope local`
        // and a repository's own .mcp.json: without them a working install in
        // either scope would read as "not registered".
        self::assertSame(
            ['command' => 'php', 'args' => ['bin/tm-mcp']],
            $this->arrayAt($mcp, 'local')['tm_entry'],
        );
        self::assertSame(
            ['command' => './bin/tm-mcp', 'args' => []],
            $this->arrayAt($mcp, 'project')['tm_entry'],
        );

        // The report is meant to be pasted to someone else (requirement 694),
        // and an MCP env block can hold an API key.
        self::assertStringNotContainsString('sk-must-never-be-reported', json_encode($data) ?: '');
    }

    #[Test]
    public function it_reports_no_tm_mcp_entry_when_claude_is_not_configured(): void
    {
        $data = $this->runInfo(tmDb: false);
        $mcp = $this->arrayAt($data, 'mcp_registration');

        foreach (['user', 'local', 'project'] as $scope) {
            self::assertFalse($this->arrayAt($mcp, $scope)['exists'], $scope);
            self::assertNull($this->arrayAt($mcp, $scope)['tm_entry'], $scope);
        }
    }

    /** @return array<mixed, mixed> */
    private function runInfo(string|false $tmDb): array
    {
        $app = new ConsoleApplication('tm');
        $app->add(new InfoCommand($this->home, $tmDb, $this->cwd));
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'info']);

        $raw = json_decode($tester->getDisplay(), true);
        self::assertIsArray($raw, 'Expected JSON from info: ' . $tester->getDisplay());
        self::assertTrue((bool) $raw['ok'], json_encode($raw) ?: '');
        $data = $raw['data'];
        self::assertIsArray($data);

        return $data;
    }

    /**
     * @param array<mixed, mixed> $data
     * @return array<mixed, mixed>
     */
    private function arrayAt(array $data, string $key): array
    {
        $v = $data[$key];
        self::assertIsArray($v, "Expected '$key' to be array");

        return $v;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = scandir($dir) ?: [];
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $path = $dir . '/' . $f;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
