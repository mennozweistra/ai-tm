<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\AiLib\Services\QuestionService;
use AiToolset\Tm\Cli\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class QuestionResolveCommand extends BaseCommand
{
    public function __construct(private readonly QuestionService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('question:resolve')
            ->addOption('question', null, InputOption::VALUE_REQUIRED)
            ->addOption('state', null, InputOption::VALUE_REQUIRED)
            ->addOption('resolution-quality', null, InputOption::VALUE_REQUIRED)
            ->addOption('answer', null, InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $answer = $input->getOption('answer');

            $out = $this->service->resolve(
                id: $this->requireIntOption($input, 'question'),
                state: $this->requireOption($input, 'state'),
                answer: is_string($answer) ? $answer : null,
                resolutionQuality: $this->requireOption($input, 'resolution-quality'),
            );

            return $this->success($output, $out);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
