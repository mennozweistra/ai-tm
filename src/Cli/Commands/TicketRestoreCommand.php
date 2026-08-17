<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\AiLib\Services\TicketService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class TicketRestoreCommand extends BaseCommand
{
    public function __construct(private readonly TicketService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ticket:restore')
            ->addOption('ticket', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            return $this->success($output, $this->service->restore($this->requireIntOption($input, 'ticket')));
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
