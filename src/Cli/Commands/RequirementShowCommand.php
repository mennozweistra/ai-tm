<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\AiLib\Services\RequirementService;
use AiToolset\Tm\Cli\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class RequirementShowCommand extends BaseCommand
{
    public function __construct(private readonly RequirementService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('requirement:show')
            ->addOption('requirement', null, InputOption::VALUE_REQUIRED)
            ->addOption('deep', null, InputOption::VALUE_NONE)
            ->addOption('human', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $id = $this->requireIntOption($input, 'requirement');
            $out = $input->getOption('deep') ? $this->service->showDeep($id) : $this->service->show($id);

            return $this->success($output, $out);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
