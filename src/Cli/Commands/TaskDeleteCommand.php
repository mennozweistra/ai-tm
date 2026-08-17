<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\AiLib\Services\TaskService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class TaskDeleteCommand extends BaseCommand
{
    public function __construct(private readonly TaskService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('task:delete')
            ->addOption('task', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->service->delete($this->requireIntOption($input, 'task'));

            return $this->success($output, ['deleted' => true]);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
