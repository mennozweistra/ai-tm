<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\AiLib\Services\ProjectService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ProjectSetCommand extends BaseCommand
{
    public function __construct(private readonly ProjectService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('project:set')
            ->addOption('project', null, InputOption::VALUE_REQUIRED)
            ->addOption('name', null, InputOption::VALUE_OPTIONAL)
            ->addOption('description', null, InputOption::VALUE_OPTIONAL)
            ->addOption('ai-description', null, InputOption::VALUE_OPTIONAL)
            ->addOption('path', null, InputOption::VALUE_OPTIONAL)
            ->addOption('auto-status', null, InputOption::VALUE_OPTIONAL)
            ->addOption('status', null, InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $id = $this->requireIntOption($input, 'project');
            $autoStatusRaw = $input->getOption('auto-status');
            $autoStatus = is_string($autoStatusRaw) ? $this->parseBool($autoStatusRaw, 'auto-status') : null;

            $out = $this->service->set(
                id: $id,
                name: is_string($input->getOption('name')) ? $input->getOption('name') : null,
                description: is_string($input->getOption('description')) ? $input->getOption('description') : null,
                aiDescription: is_string($input->getOption('ai-description')) ? $input->getOption('ai-description') : null,
                path: is_string($input->getOption('path')) ? $input->getOption('path') : null,
                autoStatus: $autoStatus,
                status: is_string($input->getOption('status')) ? $input->getOption('status') : null,
            );

            return $this->success($output, $out);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
