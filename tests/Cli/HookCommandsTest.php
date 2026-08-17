<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Cli;

use AiToolset\Tm\Cli\Commands\HookDisableCommand;
use AiToolset\Tm\Cli\Commands\HookEnableCommand;
use AiToolset\Tm\Cli\HookSettingsInstaller;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * Exercises hook:enable and hook:disable through the console layer, against a temp
 * settings path — never the real ~/.claude. The behavior itself (idempotency, marker-only
 * removal, malformed-file handling) is covered in HookSettingsInstallerTest; this file
 * only checks the commands wire arguments and the {ok, data} envelope correctly.
 */
final class HookCommandsTest extends TestCase
{
    private string $settingsPath;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/hook-commands-test-' . uniqid('', true);
        mkdir($this->tempDir, 0755, true);
        $this->settingsPath = $this->tempDir . '/settings.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->settingsPath)) {
            unlink($this->settingsPath);
        }
        rmdir($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function hook_enable_reports_written_true_then_false(): void
    {
        $first = $this->runCommand('hook:enable');
        $this->assertTrue((bool) $first['written']);

        $second = $this->runCommand('hook:enable');
        $this->assertFalse((bool) $second['written']);
    }

    #[Test]
    public function hook_disable_reports_written_true_then_false(): void
    {
        $this->runCommand('hook:enable');

        $first = $this->runCommand('hook:disable');
        $this->assertTrue((bool) $first['written']);

        $second = $this->runCommand('hook:disable');
        $this->assertFalse((bool) $second['written']);
    }

    #[Test]
    public function hook_enable_reports_an_error_envelope_on_a_malformed_file(): void
    {
        file_put_contents($this->settingsPath, 'not json {{{');

        $app = new Application();
        $installer = new HookSettingsInstaller($this->settingsPath);
        $app->add(new HookEnableCommand($installer));
        $app->add(new HookDisableCommand($installer));
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'hook:enable']);

        $raw = json_decode($tester->getDisplay(), true);
        $this->assertIsArray($raw);
        $this->assertFalse((bool) $raw['ok']);

        // The malformed file must be left untouched — no partial write.
        $this->assertSame('not json {{{', file_get_contents($this->settingsPath));
    }

    /** @return array<mixed, mixed> */
    private function runCommand(string $command): array
    {
        $app = new Application();
        $installer = new HookSettingsInstaller($this->settingsPath);
        $app->add(new HookEnableCommand($installer));
        $app->add(new HookDisableCommand($installer));
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);
        $tester->run(['command' => $command]);

        $raw = json_decode($tester->getDisplay(), true);
        $this->assertIsArray($raw, 'Expected JSON from ' . $command);
        $this->assertTrue((bool) $raw['ok'], json_encode($raw) ?: '');
        $data = $raw['data'];
        $this->assertIsArray($data);

        return $data;
    }
}
