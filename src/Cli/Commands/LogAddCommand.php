<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\AiLib\Schemas\LogEntryIn;
use AiToolset\AiLib\Services\LogService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class LogAddCommand extends BaseCommand
{
    public function __construct(private readonly LogService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('log:add')
            ->addOption('ticket', null, InputOption::VALUE_OPTIONAL)
            ->addOption('phase', null, InputOption::VALUE_OPTIONAL)
            ->addOption('task', null, InputOption::VALUE_OPTIONAL)
            ->addOption('type', null, InputOption::VALUE_REQUIRED)
            ->addOption('title', null, InputOption::VALUE_OPTIONAL, '', '')
            ->addOption('ai-content', null, InputOption::VALUE_OPTIONAL, '', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $typeRaw = $input->getOption('type');
            if (!is_string($typeRaw) || $typeRaw === '') {
                throw new \InvalidArgumentException('Option --type is required.');
            }

            $titleRaw = $input->getOption('title');
            $aiContentRaw = $input->getOption('ai-content');

            $out = $this->service->add(new LogEntryIn(
                logType: $typeRaw,
                title: is_string($titleRaw) ? $titleRaw : '',
                aiContent: is_string($aiContentRaw) ? $aiContentRaw : '',
                ticketId: $this->optionalIntOption($input, 'ticket'),
                phaseId: $this->optionalIntOption($input, 'phase'),
                taskId: $this->optionalIntOption($input, 'task'),
            ));

            return $this->success($output, $out);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
