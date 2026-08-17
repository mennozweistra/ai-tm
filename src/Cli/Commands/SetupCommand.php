<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SetupCommand extends BaseCommand
{
    public function __construct(private readonly string $dataDir)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('setup')
            ->setDescription('Initialise the tm data directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $dirCreated = false;
            if (!is_dir($this->dataDir)) {
                mkdir($this->dataDir, 0755, true);
                $dirCreated = true;
            }

            return $this->success($output, [
                'data_directory_created' => $dirCreated,
            ]);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
