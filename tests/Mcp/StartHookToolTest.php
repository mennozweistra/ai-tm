<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Mcp;

use AiToolset\Tm\Mcp\Tools\StartHookTool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StartHookToolTest extends TestCase
{
    #[Test]
    public function it_returns_the_start_of_turn_task_status_instruction(): void
    {
        $instruction = new StartHookTool()->instruction();

        // The instruction must name the task-set tool and mention setting a task to active.
        $this->assertStringContainsString('mcp__tm__tm_task_set', $instruction);
        $this->assertStringContainsString('active', $instruction);
    }
}
