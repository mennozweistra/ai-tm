<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\AiLib\Schemas\QuestionIn;
use AiToolset\AiLib\Services\QuestionService;
use AiToolset\Tm\Cli\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class QuestionAddCommand extends BaseCommand
{
    public function __construct(private readonly QuestionService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('question:add')
            ->addOption('ticket', null, InputOption::VALUE_REQUIRED)
            ->addOption('task', null, InputOption::VALUE_OPTIONAL)
            ->addOption('name', null, InputOption::VALUE_REQUIRED)
            ->addOption('question', null, InputOption::VALUE_REQUIRED)
            ->addOption('background', null, InputOption::VALUE_OPTIONAL, '', '')
            ->addOption('recommendation', null, InputOption::VALUE_OPTIONAL, '', '')
            ->addOption('kind', null, InputOption::VALUE_REQUIRED)
            ->addOption('model', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $out = $this->service->add(new QuestionIn(
                ticketId: $this->requireIntOption($input, 'ticket'),
                name: $this->requireOption($input, 'name'),
                question: $this->requireOption($input, 'question'),
                kind: $this->requireOption($input, 'kind'),
                model: $this->requireOption($input, 'model'),
                taskId: $this->optionalIntOption($input, 'task'),
                background: is_string($input->getOption('background')) ? $input->getOption('background') : '',
                recommendation: is_string($input->getOption('recommendation')) ? $input->getOption('recommendation') : '',
            ));

            return $this->success($output, $out);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
