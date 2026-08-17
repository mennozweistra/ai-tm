<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\AiLib\Services\PhaseService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PhaseSetCommand extends BaseCommand
{
    public function __construct(private readonly PhaseService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('phase:set')
            ->addOption('phase', null, InputOption::VALUE_REQUIRED)
            ->addOption('name', null, InputOption::VALUE_OPTIONAL)
            ->addOption('description', null, InputOption::VALUE_OPTIONAL)
            ->addOption('ai-description', null, InputOption::VALUE_OPTIONAL)
            ->addOption('status', null, InputOption::VALUE_OPTIONAL)
            ->addOption('max-attempts', null, InputOption::VALUE_OPTIONAL)
            ->addOption('attempts', null, InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $out = $this->service->set(
                id: $this->requireIntOption($input, 'phase'),
                name: is_string($input->getOption('name')) ? $input->getOption('name') : null,
                description: is_string($input->getOption('description')) ? $input->getOption('description') : null,
                aiDescription: is_string($input->getOption('ai-description')) ? $input->getOption('ai-description') : null,
                status: is_string($input->getOption('status')) ? $input->getOption('status') : null,
                maxAttempts: $this->optionalIntOption($input, 'max-attempts'),
                attempts: $this->optionalIntOption($input, 'attempts'),
            );

            return $this->success($output, $out);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
