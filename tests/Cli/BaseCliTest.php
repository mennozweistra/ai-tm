<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Cli;

use AiToolset\Tm\Cli\Application;
use AiToolset\AiLib\Domain\Config;
use AiToolset\AiLib\Repositories\TemplateRepository;
use AiToolset\AiLib\Testing\InMemoryDatabase;
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\ApplicationTester;

abstract class BaseCliTest extends TestCase
{
    /**
     * Name of the structure-free template written into the CLI's real
     * templates directory for tests that only need a valid ticket, not
     * specific phases or tasks. `Application` always reads templates from
     * that fixed directory (`Application::templatesPath()`); unlike the MCP
     * test fixtures this cannot be pointed at a temp directory instead, so
     * the file is written before each test and removed after, mirroring the
     * MCP scaffold template's lifecycle.
     */
    protected const string SCAFFOLD_TEMPLATE = 'cli_test_scaffold';

    protected PDO $pdo;
    protected ApplicationTester $tester;
    private string $scaffoldTemplatePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = InMemoryDatabase::create();

        $this->scaffoldTemplatePath = Application::templatesPath() . '/' . self::SCAFFOLD_TEMPLATE . '.toml';
        if (is_file($this->scaffoldTemplatePath)) {
            unlink($this->scaffoldTemplatePath);
        }
        new TemplateRepository([Application::templatesPath()], Application::templatesPath())->write(self::SCAFFOLD_TEMPLATE, [
            'description' => '',
            'ai_description' => '',
            'phases' => [],
        ]);

        $app = new Application($this->pdo, Config::default());
        $app->setAutoExit(false);
        $this->tester = new ApplicationTester($app);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (is_file($this->scaffoldTemplatePath)) {
            unlink($this->scaffoldTemplatePath);
        }
    }

    /**
     * @param array<string, string|null> $args
     * @return array<mixed, mixed>
     */
    protected function exec(string $command, array $args = []): array
    {
        $this->tester->run(array_merge(['command' => $command], $args));
        $raw = json_decode($this->tester->getDisplay(), true);
        $this->assertIsArray($raw, 'Expected JSON array from command output');

        return $raw;
    }

    /**
     * @param array<string, string|null> $args
     * @return array<mixed, mixed>
     */
    protected function ok(string $command, array $args = []): array
    {
        $result = $this->exec($command, $args);
        $this->assertTrue((bool) $result['ok'], 'Expected ok=true, got: ' . json_encode($result));
        $data = $result['data'];
        $this->assertIsArray($data, 'Expected data to be array');

        return $data;
    }

    /**
     * @param array<string, string|null> $args
     * @return array<mixed, mixed>
     */
    protected function err(string $command, array $args = []): array
    {
        $result = $this->exec($command, $args);
        $this->assertFalse((bool) $result['ok'], 'Expected ok=false, got: ' . json_encode($result));
        $error = $result['error'];
        $this->assertIsArray($error, 'Expected error to be array');

        return $error;
    }

    /** @param array<mixed, mixed> $data */
    protected function intAt(array $data, string $key): int
    {
        $v = $data[$key];
        $this->assertIsInt($v, "Expected '$key' to be int");

        return $v;
    }

    /**
     * @param array<mixed, mixed> $data
     * @return array<mixed, mixed>
     */
    protected function listAt(array $data, string $key): array
    {
        $v = $data[$key];
        $this->assertIsArray($v, "Expected '$key' to be array");

        return $v;
    }

    /**
     * @param array<mixed, mixed> $list
     * @return array<mixed, mixed>
     */
    protected function itemAt(array $list, int $index): array
    {
        $v = $list[$index];
        $this->assertIsArray($v, "Expected index $index to be array");

        return $v;
    }
}
