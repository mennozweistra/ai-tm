<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\AiLib\Services\RequirementService;
use AiToolset\Tm\Cli\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class RequirementMoveCommand extends BaseCommand
{
    public function __construct(private readonly RequirementService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('requirement:move')
            ->addOption('requirement', null, InputOption::VALUE_REQUIRED)
            ->addOption('before', null, InputOption::VALUE_OPTIONAL)
            ->addOption('after', null, InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $requirementId = $this->requireIntOption($input, 'requirement');
            $beforeId = $this->optionalIntOption($input, 'before');
            $afterId = $this->optionalIntOption($input, 'after');

            if ($beforeId === null && $afterId === null) {
                throw new \InvalidArgumentException('Either --before or --after is required for requirement:move.');
            }

            $movingRequirement = $this->service->show($requirementId);
            $siblingId = $beforeId ?? $afterId;
            $sibling = $this->service->show((int) $siblingId);

            $adjustedOrder = $sibling->order > $movingRequirement->order ? $sibling->order - 1 : $sibling->order;
            $toOrder = $beforeId !== null ? $adjustedOrder : $adjustedOrder + 1;

            return $this->success($output, $this->service->move($requirementId, $toOrder));
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
