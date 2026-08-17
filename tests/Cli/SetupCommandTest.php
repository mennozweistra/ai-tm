<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Cli;

use AiToolset\Tm\Cli\Commands\SetupCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

final class SetupCommandTest extends TestCase
{
    private string $dataDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataDir = sys_get_temp_dir() . '/tm_setup_' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dataDir);
        parent::tearDown();
    }

    #[Test]
    public function it_creates_data_directory_when_missing(): void
    {
        $data = $this->runSetup();
        $this->assertTrue((bool) $data['data_directory_created']);
        $this->assertTrue(is_dir($this->dataDir));
    }

    #[Test]
    public function it_does_not_touch_database_file(): void
    {
        mkdir($this->dataDir, 0755, true);
        $dbPath = $this->dataDir . '/store.db';
        file_put_contents($dbPath, 'original');

        $this->runSetup();

        $this->assertFileExists($dbPath);
        $this->assertSame('original', file_get_contents($dbPath));
    }

    /** @return array<mixed, mixed> */
    private function runSetup(): array
    {
        $cmd = new SetupCommand($this->dataDir);
        $app = new Application();
        $app->add($cmd);
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'setup']);

        $raw = json_decode($tester->getDisplay(), true);
        $this->assertIsArray($raw, 'Expected JSON from setup command');
        $this->assertTrue((bool) $raw['ok'], json_encode($raw) ?: '');
        $data = $raw['data'];
        $this->assertIsArray($data);

        return $data;
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
