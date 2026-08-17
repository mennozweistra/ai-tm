<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\AiLib\Schemas\TicketIn;
use AiToolset\AiLib\Services\TicketService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class TicketAddCommand extends BaseCommand
{
    public function __construct(private readonly TicketService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ticket:add')
            ->addOption('project', null, InputOption::VALUE_REQUIRED)
            ->addOption('name', null, InputOption::VALUE_REQUIRED)
            ->addOption('description', null, InputOption::VALUE_OPTIONAL, '', '')
            ->addOption('ai-description', null, InputOption::VALUE_OPTIONAL, '', '')
            ->addOption('status', null, InputOption::VALUE_OPTIONAL, '', 'pending')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, '', 'feature')
            ->addOption('priority', null, InputOption::VALUE_REQUIRED)
            ->addOption('template', null, InputOption::VALUE_OPTIONAL, '', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $statusRaw = $input->getOption('status');
            $typeRaw = $input->getOption('type');
            $templateRaw = $input->getOption('template');
            $out = $this->service->addFromTemplate(new TicketIn(
                projectId: $this->requireIntOption($input, 'project'),
                name: $this->requireOption($input, 'name'),
                description: is_string($input->getOption('description')) ? $input->getOption('description') : '',
                aiDescription: is_string($input->getOption('ai-description')) ? $input->getOption('ai-description') : '',
                status: is_string($statusRaw) ? $statusRaw : 'pending',
                type: is_string($typeRaw) ? $typeRaw : 'feature',
                priority: $this->parsePriorityOption($input),
            ), is_string($templateRaw) ? $templateRaw : null);

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
