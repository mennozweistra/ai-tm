<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\AiLib\Services\TicketService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class TicketListCommand extends BaseCommand
{
    public function __construct(private readonly TicketService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ticket:list')
            ->addOption('project', null, InputOption::VALUE_REQUIRED)
            ->addOption('archived', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $tickets = $this->service->list(
                projectId: $this->requireIntOption($input, 'project'),
                includeArchived: (bool) $input->getOption('archived'),
            );

            return $this->success($output, ['tickets' => $tickets]);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
