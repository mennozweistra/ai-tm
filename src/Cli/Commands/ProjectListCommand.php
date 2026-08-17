<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\AiLib\Services\ProjectService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ProjectListCommand extends BaseCommand
{
    public function __construct(private readonly ProjectService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('project:list')
            ->addOption('archived', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $projects = $this->service->list(includeArchived: (bool) $input->getOption('archived'));

            return $this->success($output, ['projects' => $projects]);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
