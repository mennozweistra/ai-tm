<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\AiLib\Schemas\RequirementIn;
use AiToolset\AiLib\Services\RequirementService;
use AiToolset\Tm\Cli\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class RequirementAddCommand extends BaseCommand
{
    public function __construct(private readonly RequirementService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('requirement:add')
            ->addOption('ticket', null, InputOption::VALUE_REQUIRED)
            ->addOption('name', null, InputOption::VALUE_REQUIRED)
            ->addOption('description', null, InputOption::VALUE_OPTIONAL, '', '')
            ->addOption('ai-description', null, InputOption::VALUE_OPTIONAL, '', '')
            ->addOption('verification', null, InputOption::VALUE_OPTIONAL, '', 'unverified')
            ->addOption('before', null, InputOption::VALUE_OPTIONAL)
            ->addOption('after', null, InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $verificationRaw = $input->getOption('verification');
            $out = $this->service->add(new RequirementIn(
                ticketId: $this->requireIntOption($input, 'ticket'),
                name: $this->requireOption($input, 'name'),
                description: is_string($input->getOption('description')) ? $input->getOption('description') : '',
                aiDescription: is_string($input->getOption('ai-description')) ? $input->getOption('ai-description') : '',
                verification: is_string($verificationRaw) ? $verificationRaw : 'unverified',
                beforeId: $this->optionalIntOption($input, 'before'),
                afterId: $this->optionalIntOption($input, 'after'),
            ));

            return $this->success($output, $out);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
