<?php

declare(strict_types=1);

namespace AiToolset\Tm\Mcp\Tools;

use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

/**
 * Backs tm's Stop logging hook so the hook command itself stays one line.
 *
 * The hook (see {@see \AiToolset\Tm\Cli\HookSettingsInstaller}, wired via `tm hook:enable`)
 * blocks the end of a turn and tells the agent to call this tool; this tool returns the
 * instruction the agent then follows: record a
 * tm log entry for the ticket the session is working on, or record nothing if the ticket is
 * unclear or nothing is worth keeping. How the reply reports the outcome is the output style's
 * business, not this hook's — writing rules live there and nowhere else (ticket 260).
 */
final class StopHookTool
{
    private const string INSTRUCTION = 'End-of-turn tm log. If this turn produced a decision or a fact worth keeping, record it now with mcp__tm__tm_log_add for the ticket this session is working on. You supply the ticket id from your own context. If you cannot confidently identify the ticket, record nothing and tell the user. If there is nothing worth logging, record nothing and tell the user. Pick the log type (progress, decision, blocker, note) that fits. Your active output style rules how the reply reports this.';

    #[McpTool(name: 'tm_stop_hook', description: "Returns the end-of-turn logging instruction. Called by tm's Stop hook; follow what it returns.")]
    #[Schema(additionalProperties: false)]
    public function instruction(): string
    {
        return self::INSTRUCTION;
    }
}
