<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\AiLib\Domain\Config;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ConfigListCommand extends BaseCommand
{
    public function __construct(private readonly Config $config)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('config:list');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->success($output, $this->config);
    }
}
