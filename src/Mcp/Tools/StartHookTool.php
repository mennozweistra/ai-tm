<?php

declare(strict_types=1);

namespace AiToolset\Tm\Mcp\Tools;

use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

/**
 * Backs tm's start-of-turn hook so the hook command itself stays one line.
 *
 * The hook (see {@see \AiToolset\Tm\Cli\HookSettingsInstaller}, wired via `tm hook:enable`) runs
 * at the start of each turn and tells the agent to call this tool; this tool returns a
 * conditional instruction: if the agent is about
 * to start work on a pending task, set it to active first; if finishing, set it to done.
 */
final class StartHookTool
{
    public const string INSTRUCTION = 'Start-of-turn tm check. If you are about to start work on a specific task that is currently pending, set it to active with mcp__tm__tm_task_set before doing anything else. If the task is already active, or this turn is a discussion or non-implementation turn, do nothing and proceed.';

    #[McpTool(name: 'tm_start_hook', description: "Returns the start-of-turn task-status instruction. Called by tm's start hook; follow what it returns.")]
    #[Schema(additionalProperties: false)]
    public function instruction(): string
    {
        return self::INSTRUCTION;
    }
}
