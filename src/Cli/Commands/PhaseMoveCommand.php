<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\AiLib\Services\PhaseService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PhaseMoveCommand extends BaseCommand
{
    public function __construct(private readonly PhaseService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('phase:move')
            ->addOption('phase', null, InputOption::VALUE_REQUIRED)
            ->addOption('before', null, InputOption::VALUE_OPTIONAL)
            ->addOption('after', null, InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $phaseId = $this->requireIntOption($input, 'phase');
            $beforeId = $this->optionalIntOption($input, 'before');
            $afterId = $this->optionalIntOption($input, 'after');

            if ($beforeId === null && $afterId === null) {
                throw new \InvalidArgumentException('Either --before or --after is required for phase:move.');
            }

            $movingPhase = $this->service->show($phaseId);
            $siblingId = $beforeId ?? $afterId;
            $sibling = $this->service->show((int) $siblingId);

            $adjustedOrder = $sibling->order > $movingPhase->order ? $sibling->order - 1 : $sibling->order;
            $toOrder = $beforeId !== null ? $adjustedOrder : $adjustedOrder + 1;

            return $this->success($output, $this->service->move($phaseId, $toOrder));
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
