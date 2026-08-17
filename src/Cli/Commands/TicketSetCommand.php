<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\AiLib\Services\TicketService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class TicketSetCommand extends BaseCommand
{
    public function __construct(private readonly TicketService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ticket:set')
            ->addOption('ticket', null, InputOption::VALUE_REQUIRED)
            ->addOption('name', null, InputOption::VALUE_OPTIONAL)
            ->addOption('description', null, InputOption::VALUE_OPTIONAL)
            ->addOption('ai-description', null, InputOption::VALUE_OPTIONAL)
            ->addOption('status', null, InputOption::VALUE_OPTIONAL)
            ->addOption('type', null, InputOption::VALUE_OPTIONAL)
            ->addOption('priority', null, InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $out = $this->service->set(
                id: $this->requireIntOption($input, 'ticket'),
                name: is_string($input->getOption('name')) ? $input->getOption('name') : null,
                description: is_string($input->getOption('description')) ? $input->getOption('description') : null,
                aiDescription: is_string($input->getOption('ai-description')) ? $input->getOption('ai-description') : null,
                status: is_string($input->getOption('status')) ? $input->getOption('status') : null,
                type: is_string($input->getOption('type')) ? $input->getOption('type') : null,
                priority: $this->parsePriorityOption($input),
            );

            return $this->success($output, $out);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }

    private function parsePriorityOption(InputInterface $input): ?int
    {
        $raw = $input->getOption('priority');
        if ($raw === null) {
            return null;
        }

        if (!is_string($raw)) {
            throw new \InvalidArgumentException('Option --priority must be one of: low, medium, high.');
        }

        return match ($raw) {
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            default => throw new \InvalidArgumentException('Option --priority must be one of: low, medium, high.'),
        };
    }
}
