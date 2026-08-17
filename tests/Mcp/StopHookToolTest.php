<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Mcp;

use AiToolset\Tm\Mcp\Tools\StopHookTool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StopHookToolTest extends TestCase
{
    #[Test]
    public function it_returns_the_end_of_turn_logging_instruction(): void
    {
        $instruction = new StopHookTool()->instruction();

        // The instruction must name the logging tool, and it must carry no reply wording:
        // how the reply reports the outcome belongs to the output style (ticket 260).
        $this->assertStringContainsString('mcp__tm__tm_log_add', $instruction);
        $this->assertStringNotContainsString('Nothing to log this turn.', $instruction);
        $this->assertStringNotContainsString('briefly', $instruction);
    }
}
