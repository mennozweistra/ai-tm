<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\AiLib\Services\ProjectService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ProjectShowCommand extends BaseCommand
{
    public function __construct(private readonly ProjectService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('project:show')
            ->addOption('project', null, InputOption::VALUE_REQUIRED)
            ->addOption('deep', null, InputOption::VALUE_NONE)
            ->addOption('human', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $id = $this->requireIntOption($input, 'project');
            $out = $input->getOption('deep') ? $this->service->showDeep($id) : $this->service->show($id);

            return $this->success($output, $out);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
