<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\AiLib\Schemas\ProjectIn;
use AiToolset\AiLib\Services\ProjectService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ProjectAddCommand extends BaseCommand
{
    public function __construct(private readonly ProjectService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('project:add')
            ->addOption('name', null, InputOption::VALUE_REQUIRED)
            ->addOption('path', null, InputOption::VALUE_REQUIRED)
            ->addOption('description', null, InputOption::VALUE_OPTIONAL, '', '')
            ->addOption('ai-description', null, InputOption::VALUE_OPTIONAL, '', '')
            ->addOption('auto-status', null, InputOption::VALUE_OPTIONAL, '', 'true');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $autoStatusRaw = $input->getOption('auto-status');
            $out = $this->service->add(new ProjectIn(
                name: $this->requireOption($input, 'name'),
                path: $this->requireOption($input, 'path'),
                description: is_string($input->getOption('description')) ? $input->getOption('description') : '',
                aiDescription: is_string($input->getOption('ai-description')) ? $input->getOption('ai-description') : '',
                autoStatus: $this->parseBool(is_string($autoStatusRaw) ? $autoStatusRaw : 'true', 'auto-status'),
            ));

            return $this->success($output, $out);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
