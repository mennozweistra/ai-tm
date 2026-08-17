<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\AiLib\Services\RequirementService;
use AiToolset\Tm\Cli\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class RequirementSetCommand extends BaseCommand
{
    public function __construct(private readonly RequirementService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('requirement:set')
            ->addOption('requirement', null, InputOption::VALUE_REQUIRED)
            ->addOption('name', null, InputOption::VALUE_OPTIONAL)
            ->addOption('description', null, InputOption::VALUE_OPTIONAL)
            ->addOption('ai-description', null, InputOption::VALUE_OPTIONAL)
            ->addOption('verification', null, InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $out = $this->service->set(
                id: $this->requireIntOption($input, 'requirement'),
                name: is_string($input->getOption('name')) ? $input->getOption('name') : null,
                description: is_string($input->getOption('description')) ? $input->getOption('description') : null,
                aiDescription: is_string($input->getOption('ai-description')) ? $input->getOption('ai-description') : null,
                verification: is_string($input->getOption('verification')) ? $input->getOption('verification') : null,
            );

            return $this->success($output, $out);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
