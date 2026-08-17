<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\AiLib\Services\PhaseService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PhaseDeleteCommand extends BaseCommand
{
    public function __construct(private readonly PhaseService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('phase:delete')
            ->addOption('phase', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->service->delete($this->requireIntOption($input, 'phase'));

            return $this->success($output, ['deleted' => true]);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
