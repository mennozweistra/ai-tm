<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\AiLib\Services\SchemaMigrator;
use PDO;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MigrateCommand extends BaseCommand
{
    public function __construct(
        private readonly string $dbPath,
        private readonly string $migrationsPath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('migrate')
            ->setDescription('Report the applied database migrations.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Every entry point migrates its own store at start-up (ticket 269,
            // requirement 685), so by the time this command runs the Application
            // constructor has already applied everything pending. The call below
            // is what makes that true for the injected-PDO case too, and keeps
            // the command's promise — it applies what is pending and reports the
            // resulting revision. The phinx wiring lives in ai-lib's
            // SchemaMigrator; there is no second copy of it here.
            new SchemaMigrator($this->dbPath, $this->migrationsPath)->migrate();

            $files = glob($this->migrationsPath . '/*.php') ?: [];
            $fileMap = [];
            foreach ($files as $f) {
                $base = basename($f);
                $fileMap[substr($base, 0, 14)] = $base;
            }

            $pdo = new PDO("sqlite:{$this->dbPath}");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->query('SELECT version FROM phinxlog ORDER BY version');

            $applied = [];
            $currentRevision = null;

            if ($stmt !== false) {
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $row) {
                    if (!is_int($row) && !is_string($row)) {
                        continue;
                    }
                    $v = sprintf('%014d', (int) $row);
                    $applied[] = $fileMap[$v] ?? $v;
                    $currentRevision = $v;
                }
            }

            return $this->success($output, [
                'applied' => $applied,
                'current_revision' => $currentRevision,
            ]);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
