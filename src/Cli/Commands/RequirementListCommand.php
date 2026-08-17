<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\AiLib\Services\RequirementService;
use AiToolset\Tm\Cli\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class RequirementListCommand extends BaseCommand
{
    public function __construct(private readonly RequirementService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('requirement:list')
            ->addOption('ticket', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $requirements = $this->service->list(
                ticketId: $this->requireIntOption($input, 'ticket'),
            );

            return $this->success($output, ['requirements' => $requirements]);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
