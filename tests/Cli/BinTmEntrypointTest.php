<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Cli;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * bin/tm's contract is clean JSON on stdout (BaseCommand::success()) or
 * stderr (BaseCommand::handleError()) with no surrounding diagnostics.
 * Unlike the rest of this suite, which drives Application in-process via
 * ApplicationTester, this test shells out to the real bin/tm executable
 * so it exercises the actual entrypoint script.
 */
final class BinTmEntrypointTest extends TestCase
{
    private string $home;

    protected function setUp(): void
    {
        parent::setUp();
        // Deliberately bare: no ~/.ai-tm, no store.db. This is a fresh install.
        $this->home = sys_get_temp_dir() . '/tm-bin-entrypoint-test-' . uniqid('', true);
        mkdir($this->home, recursive: true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory($this->home);
    }

    /**
     * The first run after a composer install: an empty home, and a command that
     * is neither `setup` nor `migrate`. It must create ~/.ai-tm, create and
     * migrate the store, and answer — with clean JSON and nothing else on stdout
     * (ticket 269, requirement 685).
     */
    #[Test]
    public function it_creates_and_migrates_its_store_on_a_first_run(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runBinTm('project:list');

        self::assertSame(0, $exitCode, 'bin/tm failed on a bare home: ' . $stderr);
        self::assertSame('', $stderr);
        self::assertFileExists($this->home . '/.ai-tm/store.db');

        $decoded = json_decode(trim($stdout), true);
        self::assertIsArray(
            $decoded,
            "Expected bin/tm's entire stdout to decode as JSON with no surrounding text, got: " . $stdout,
        );
        self::assertTrue($decoded['ok'] ?? null);
    }

    /**
     * The downgrade case: the store carries a migration this release does not
     * ship. bin/tm must refuse in the CLI's JSON error shape rather than die
     * with a PHP fatal, and must not touch the schema.
     */
    #[Test]
    public function it_refuses_in_json_when_the_store_is_ahead_of_this_release(): void
    {
        $this->runBinTm('project:list');

        $pdo = new \PDO('sqlite:' . $this->home . '/.ai-tm/store.db');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            "INSERT INTO phinxlog (version, migration_name, start_time, end_time, breakpoint) "
            . "VALUES (29990101000000, 'FromTheFuture', '2999-01-01 00:00:00', '2999-01-01 00:00:00', 0)",
        );

        [$exitCode, $stdout, $stderr] = $this->runBinTm('project:list');

        self::assertSame(1, $exitCode);
        self::assertSame('', $stdout);

        $decoded = json_decode(trim($stderr), true);
        self::assertIsArray($decoded, 'Expected a JSON error on stderr, got: ' . $stderr);
        self::assertFalse($decoded['ok'] ?? null);
        self::assertIsArray($decoded['error'] ?? null);
        self::assertSame('schema_ahead', $decoded['error']['code'] ?? null);
    }

    /**
     * `tm info` is the one command bin/tm dispatches before it ever
     * constructs Cli\Application, specifically so it never triggers
     * self-migration (ticket 269, requirement 694). This is the end-to-end
     * proof of that: a bare home, the real bin/tm entrypoint, and an
     * assertion that ~/.ai-tm still does not exist afterward.
     */
    #[Test]
    public function it_leaves_no_trace_on_a_bare_home_when_running_info(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runBinTm('info');

        self::assertSame(0, $exitCode, 'bin/tm info failed on a bare home: ' . $stderr);
        self::assertSame('', $stderr);
        self::assertDirectoryDoesNotExist($this->home . '/.ai-tm');

        $decoded = json_decode(trim($stdout), true);
        self::assertIsArray($decoded, 'Expected JSON from bin/tm info, got: ' . $stdout);
        self::assertTrue($decoded['ok'] ?? null);
        $data = $this->arrayAt($decoded, 'data');
        self::assertFalse($this->arrayAt($data, 'data_directory')['present'] ?? null);
        self::assertFalse($this->arrayAt($data, 'database')['present'] ?? null);
    }

    /**
     * bin/tm probes two candidate autoloader paths (requirement 685): a sibling
     * vendor/ next to bin/ (development checkout) and vendor/autoload.php three
     * levels above bin/ (a composer global vendor install, where this package
     * lands at vendor/ai-toolset/tm/bin). Only the checkout candidate is real on
     * this machine, so this test builds the second shape — vendor/ai-toolset/tm/bin
     * holding an unmodified copy of the real bin/tm, with no vendor/ sibling of its
     * own, and vendor/autoload.php three levels up delegating to the real
     * autoloader by absolute path — and runs it exactly like BinTmEntrypointTest
     * runs the checkout copy. If the candidate order or the level count in
     * bin/tm's `../../../autoload.php` ever drifts from composer's actual
     * vendor/<vendor>/<package>/bin install depth, this fails the same way a
     * fresh `composer global require` install would.
     */
    #[Test]
    public function it_finds_the_autoloader_three_levels_up_in_a_vendor_install_layout(): void
    {
        $fakeRoot = sys_get_temp_dir() . '/tm-vendor-layout-test-' . uniqid('', true);
        $fakeBinDir = $fakeRoot . '/vendor/ai-toolset/tm/bin';
        mkdir($fakeBinDir, 0755, true);

        $realBinTm = dirname(__DIR__, 2) . '/bin/tm';
        copy($realBinTm, $fakeBinDir . '/tm');

        $realAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        self::assertFileExists($realAutoload);
        file_put_contents(
            $fakeRoot . '/vendor/autoload.php',
            '<?php return require ' . var_export(realpath($realAutoload), true) . ';' . "\n",
        );

        // Sanity check: the checkout candidate must be absent, or this run would
        // prove nothing about the vendor-install candidate.
        self::assertFileDoesNotExist($fakeBinDir . '/../vendor/autoload.php');

        try {
            [$exitCode, $stdout, $stderr] = $this->runBinTmAt($fakeBinDir . '/tm', 'project:list');

            self::assertSame(0, $exitCode, 'bin/tm failed to find the vendor-install autoloader: ' . $stderr);
            self::assertSame('', $stderr);
            $decoded = json_decode(trim($stdout), true);
            self::assertIsArray($decoded, 'Expected JSON from bin/tm, got: ' . $stdout);
            self::assertTrue($decoded['ok'] ?? null);
        } finally {
            $this->removeDirectory($fakeRoot);
        }
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

    /** @return array{0: int, 1: string, 2: string} */
    private function runBinTm(string $command): array
    {
        return $this->runBinTmAt(dirname(__DIR__, 2) . '/bin/tm', $command);
    }

    /** @return array{0: int, 1: string, 2: string} */
    private function runBinTmAt(string $binTm, string $command): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open([PHP_BINARY, $binTm, $command], $descriptors, $pipes, null, ['HOME' => $this->home]);
        self::assertIsResource($process, 'Failed to start bin/tm.');

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [$exitCode, $stdout !== false ? $stdout : '', $stderr !== false ? $stderr : ''];
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
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
