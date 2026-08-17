<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli\Commands;

use AiToolset\Tm\Cli\BaseCommand;
use AiToolset\Tm\Cli\HookSettingsInstaller;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class HookDisableCommand extends BaseCommand
{
    public function __construct(private readonly HookSettingsInstaller $installer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('hook:disable')
            ->setDescription("Remove tm's Stop and UserPromptSubmit hooks from the user's Claude settings file.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $written = $this->installer->disable();

            return $this->success($output, ['written' => $written]);
        } catch (\Throwable $e) {
            return $this->handleError($output, $e);
        }
    }
}
