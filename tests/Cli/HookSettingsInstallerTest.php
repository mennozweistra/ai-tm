<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Cli;

use AiToolset\Tm\Cli\HookSettingsInstaller;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HookSettingsInstallerTest extends TestCase
{
    private string $tempDir;
    private string $settingsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/hook-settings-installer-test-' . uniqid('', true);
        mkdir($this->tempDir, 0755, true);
        $this->settingsPath = $this->tempDir . '/settings.json';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory($this->tempDir);
    }

    #[Test]
    public function it_creates_the_file_and_writes_all_three_hooks_when_absent(): void
    {
        $installer = new HookSettingsInstaller($this->settingsPath);
        $result = $installer->enable();

        $this->assertTrue($result);
        $data = $this->readJson();
        $this->assertHooksPresent($data);
    }

    #[Test]
    public function enable_twice_is_a_noop(): void
    {
        $installer = new HookSettingsInstaller($this->settingsPath);
        $installer->enable();

        $before = (string) file_get_contents($this->settingsPath);
        $result = $installer->enable();

        $this->assertFalse($result, 'a second enable() must report nothing written');
        $after = (string) file_get_contents($this->settingsPath);
        $this->assertSame($before, $after, 'file must be byte-for-byte unchanged on a no-op enable');
    }

    #[Test]
    public function disable_removes_only_the_marked_entries(): void
    {
        file_put_contents($this->settingsPath, (string) json_encode([
            'hooks' => [
                'Stop' => [
                    ['hooks' => [['type' => 'command', 'command' => 'echo unrelated-stop-hook']]],
                ],
            ],
        ]));

        $installer = new HookSettingsInstaller($this->settingsPath);
        $installer->enable();

        $result = $installer->disable();
        $this->assertTrue($result);

        $data = $this->readJson();
        $this->assertHooksAbsent($data);

        // The unrelated Stop hook that pre-dated tm's install must survive.
        $hooks = $data['hooks'];
        $this->assertIsArray($hooks);
        $stopGroups = $hooks['Stop'];
        $this->assertIsArray($stopGroups);
        /** @var array<int, array{hooks: array<int, array{type: string, command: string}>}> $stopGroups */
        $commands = array_column(array_merge(...array_column($stopGroups, 'hooks')), 'command');
        $this->assertContains('echo unrelated-stop-hook', $commands);
    }

    #[Test]
    public function disable_is_a_noop_when_nothing_is_installed(): void
    {
        $installer = new HookSettingsInstaller($this->settingsPath);
        $result = $installer->disable();

        $this->assertFalse($result);
        $this->assertFileDoesNotExist($this->settingsPath, 'disable() must not create a file it has nothing to change in');
    }

    #[Test]
    public function unrelated_settings_survive_enable_and_disable(): void
    {
        file_put_contents($this->settingsPath, (string) json_encode([
            'permissions' => ['allow' => ['Bash(git log:*)']],
            'spinnerTipsEnabled' => false,
        ]));

        $installer = new HookSettingsInstaller($this->settingsPath);
        $installer->enable();

        $afterEnable = $this->readJson();
        $this->assertSame(['allow' => ['Bash(git log:*)']], $afterEnable['permissions']);
        $this->assertFalse($afterEnable['spinnerTipsEnabled']);

        $installer->disable();

        $afterDisable = $this->readJson();
        $this->assertSame(['allow' => ['Bash(git log:*)']], $afterDisable['permissions']);
        $this->assertFalse($afterDisable['spinnerTipsEnabled']);
    }

    #[Test]
    public function a_malformed_file_is_left_untouched_by_enable(): void
    {
        file_put_contents($this->settingsPath, 'this is not valid JSON {{{');

        $installer = new HookSettingsInstaller($this->settingsPath);

        $this->expectException(\RuntimeException::class);
        try {
            $installer->enable();
        } finally {
            $this->assertSame('this is not valid JSON {{{', file_get_contents($this->settingsPath));
        }
    }

    #[Test]
    public function a_malformed_file_is_left_untouched_by_disable(): void
    {
        file_put_contents($this->settingsPath, 'this is not valid JSON {{{');

        $installer = new HookSettingsInstaller($this->settingsPath);

        $this->expectException(\RuntimeException::class);
        try {
            $installer->disable();
        } finally {
            $this->assertSame('this is not valid JSON {{{', file_get_contents($this->settingsPath));
        }
    }

    /** @return array<mixed, mixed> */
    private function readJson(): array
    {
        $data = json_decode((string) file_get_contents($this->settingsPath), true);
        $this->assertIsArray($data);

        return $data;
    }

    /** @param array<mixed, mixed> $data */
    private function assertHooksPresent(array $data): void
    {
        $this->assertMarkerPresent($data, 'Stop', HookSettingsInstaller::HOOK_MARKER);
        $this->assertMarkerPresent($data, 'UserPromptSubmit', HookSettingsInstaller::START_HOOK_MARKER);
        $this->assertMarkerPresent($data, 'UserPromptSubmit', HookSettingsInstaller::GRIND_HOOK_MARKER);
    }

    /** @param array<mixed, mixed> $data */
    private function assertHooksAbsent(array $data): void
    {
        $contents = (string) json_encode($data);
        $this->assertStringNotContainsString(HookSettingsInstaller::HOOK_MARKER, $contents);
        $this->assertStringNotContainsString(HookSettingsInstaller::START_HOOK_MARKER, $contents);
        $this->assertStringNotContainsString(HookSettingsInstaller::GRIND_HOOK_MARKER, $contents);
    }

    /** @param array<mixed, mixed> $data */
    private function assertMarkerPresent(array $data, string $event, string $marker): void
    {
        $this->assertArrayHasKey('hooks', $data);
        $hooks = $data['hooks'];
        $this->assertIsArray($hooks);
        $this->assertArrayHasKey($event, $hooks);
        $groups = $hooks[$event];
        $this->assertIsArray($groups);

        $found = false;
        foreach ($groups as $group) {
            if (!is_array($group) || !isset($group['hooks']) || !is_array($group['hooks'])) {
                continue;
            }
            foreach ($group['hooks'] as $hook) {
                if (is_array($hook) && isset($hook['command']) && is_string($hook['command']) && str_contains($hook['command'], $marker)) {
                    $found = true;
                }
            }
        }

        $this->assertTrue($found, "expected marker {$marker} under hooks.{$event}");
    }

    private function removeDirectory(string $dir): void
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
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
