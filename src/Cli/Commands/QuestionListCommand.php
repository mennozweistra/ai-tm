<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\AiLib\Services\QuestionService;
use AiToolset\Tm\Cli\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class QuestionListCommand extends BaseCommand
{
    public function __construct(private readonly QuestionService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('question:list')
            ->addOption('ticket', null, InputOption::VALUE_REQUIRED)
            ->addOption('state', null, InputOption::VALUE_OPTIONAL)
            ->addOption('group', null, InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $state = $input->getOption('state');
            $group = $input->getOption('group');

            $questions = $this->service->list(
                ticketId: $this->requireIntOption($input, 'ticket'),
                state: is_string($state) ? $state : null,
                group: is_string($group) ? $group : null,
            );

            return $this->success($output, ['questions' => $questions]);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
