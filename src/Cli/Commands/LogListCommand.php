<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\AiLib\Services\LogService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class LogListCommand extends BaseCommand
{
    public function __construct(private readonly LogService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('log:list')
            ->addOption('ticket', null, InputOption::VALUE_OPTIONAL)
            ->addOption('phase', null, InputOption::VALUE_OPTIONAL)
            ->addOption('task', null, InputOption::VALUE_OPTIONAL)
            ->addOption('sort', null, InputOption::VALUE_OPTIONAL, '', 'asc');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $sortRaw = $input->getOption('sort');
            $sort = is_string($sortRaw) ? $sortRaw : 'asc';

            $ticketId = $this->optionalIntOption($input, 'ticket');
            $phaseId = $this->optionalIntOption($input, 'phase');
            $taskId = $this->optionalIntOption($input, 'task');

            if ($taskId !== null) {
                $logs = $this->service->listByTask($taskId, $sort);
            } elseif ($phaseId !== null) {
                $logs = $this->service->listByPhase($phaseId, $sort);
            } elseif ($ticketId !== null) {
                $logs = $this->service->listByTicket($ticketId, $sort);
            } else {
                throw new \InvalidArgumentException('One of --ticket, --phase, or --task is required.');
            }

            return $this->success($output, ['logs' => $logs]);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
