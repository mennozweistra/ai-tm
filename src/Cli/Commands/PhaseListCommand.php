<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\AiLib\Services\PhaseService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PhaseListCommand extends BaseCommand
{
    public function __construct(private readonly PhaseService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('phase:list')
            ->addOption('ticket', null, InputOption::VALUE_REQUIRED)
            ->addOption('archived', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $phases = $this->service->list(
                ticketId: $this->requireIntOption($input, 'ticket'),
                includeArchived: (bool) $input->getOption('archived'),
            );

            return $this->success($output, ['phases' => $phases]);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
