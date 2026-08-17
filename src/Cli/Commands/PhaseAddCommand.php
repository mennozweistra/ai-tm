<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\AiLib\Schemas\PhaseIn;
use AiToolset\AiLib\Services\PhaseService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PhaseAddCommand extends BaseCommand
{
    public function __construct(private readonly PhaseService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('phase:add')
            ->addOption('ticket', null, InputOption::VALUE_REQUIRED)
            ->addOption('name', null, InputOption::VALUE_REQUIRED)
            ->addOption('description', null, InputOption::VALUE_OPTIONAL, '', '')
            ->addOption('ai-description', null, InputOption::VALUE_OPTIONAL, '', '')
            ->addOption('status', null, InputOption::VALUE_OPTIONAL, '', 'pending')
            ->addOption('max-attempts', null, InputOption::VALUE_OPTIONAL)
            ->addOption('before', null, InputOption::VALUE_OPTIONAL)
            ->addOption('after', null, InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $statusRaw = $input->getOption('status');
            $out = $this->service->add(new PhaseIn(
                ticketId: $this->requireIntOption($input, 'ticket'),
                name: $this->requireOption($input, 'name'),
                description: is_string($input->getOption('description')) ? $input->getOption('description') : '',
                aiDescription: is_string($input->getOption('ai-description')) ? $input->getOption('ai-description') : '',
                status: is_string($statusRaw) ? $statusRaw : 'pending',
                maxAttempts: $this->optionalIntOption($input, 'max-attempts') ?? 0,
                beforeId: $this->optionalIntOption($input, 'before'),
                afterId: $this->optionalIntOption($input, 'after'),
            ));

            return $this->success($output, $out);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
