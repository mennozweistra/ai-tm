<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\AiLib\Services\QuestionService;
use AiToolset\Tm\Cli\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class QuestionShowCommand extends BaseCommand
{
    public function __construct(private readonly QuestionService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('question:show')
            ->addOption('question', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $out = $this->service->show($this->requireIntOption($input, 'question'));

            return $this->success($output, $out);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
