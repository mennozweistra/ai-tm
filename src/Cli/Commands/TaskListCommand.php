<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\AiLib\Services\TaskService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class TaskListCommand extends BaseCommand
{
    public function __construct(private readonly TaskService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('task:list')
            ->addOption('phase', null, InputOption::VALUE_OPTIONAL)
            ->addOption('project', null, InputOption::VALUE_OPTIONAL)
            ->addOption('archived', null, InputOption::VALUE_NONE)
            ->addOption('created-from', null, InputOption::VALUE_OPTIONAL)
            ->addOption('created-to', null, InputOption::VALUE_OPTIONAL)
            ->addOption('started-from', null, InputOption::VALUE_OPTIONAL)
            ->addOption('started-to', null, InputOption::VALUE_OPTIONAL)
            ->addOption('finished-from', null, InputOption::VALUE_OPTIONAL)
            ->addOption('finished-to', null, InputOption::VALUE_OPTIONAL)
            ->addOption('order-by', null, InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $orderBy = $input->getOption('order-by');

            $tasks = $this->service->list(
                phaseId: $this->optionalIntOption($input, 'phase'),
                includeArchived: (bool) $input->getOption('archived'),
                projectId: $this->optionalIntOption($input, 'project'),
                createdFrom: is_string($input->getOption('created-from')) ? $input->getOption('created-from') : null,
                createdTo: is_string($input->getOption('created-to')) ? $input->getOption('created-to') : null,
                startedFrom: is_string($input->getOption('started-from')) ? $input->getOption('started-from') : null,
                startedTo: is_string($input->getOption('started-to')) ? $input->getOption('started-to') : null,
                finishedFrom: is_string($input->getOption('finished-from')) ? $input->getOption('finished-from') : null,
                finishedTo: is_string($input->getOption('finished-to')) ? $input->getOption('finished-to') : null,
                orderBy: is_string($orderBy) ? $orderBy : 'order',
            );

            return $this->success($output, ['tasks' => $tasks]);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
