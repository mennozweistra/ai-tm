<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Mcp;

use AiToolset\Tm\Mcp\Tools\GrindTool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GrindToolTest extends TestCase
{
    #[Test]
    public function it_returns_the_interpreted_protocol_one_subagent_per_task(): void
    {
        // req 527: the prompt-original protocol is the AI-prompt-driven loop the agent
        // runs in-session — the only mode left after the background PHP orchestrator
        // engines were retired (ticket 269).
        $protocol = new GrindTool()->protocol();

        $this->assertStringContainsString('one subagent per task', $protocol);
        $this->assertStringContainsString('Subagent prompt template', $protocol);
        $this->assertStringContainsString('mcp__tm__tm_task_set', $protocol);
        $this->assertStringContainsString('fix applied: yes', $protocol);
        $this->assertStringContainsString('helicopter view', $protocol);
        // The terminal tokens the coordinator reads from each subagent.
        $this->assertStringContainsString('STOPPED:', $protocol);
        $this->assertStringContainsString('REVIEW:', $protocol);

        // It is the interpreted loop, not a background-process dispatch: no shell-out line.
        $this->assertStringNotContainsString('grind:run <ticket-ids>', $protocol);
    }

    #[Test]
    public function it_tells_the_agent_to_replay_stored_questions_verbatim_on_request(): void
    {
        // reqs 477, 496: on-demand question listing replays stored text unchanged.
        $protocol = new GrindTool()->protocol();

        $this->assertStringContainsString('mcp__tm__tm_question_list', $protocol);
        $this->assertStringContainsString('Never summarize, rephrase, or reconstruct a question from a task result or from memory', $protocol);
        $this->assertStringContainsString('replay the stored `question` and `recommendation` text verbatim', $protocol);
    }

    #[Test]
    public function prompt_original_treats_a_skipped_task_as_terminal_and_exempts_it_from_the_phase_reset(): void
    {
        // ticket 183: the task walk (§3d) must not fall through to "otherwise, run
        // it" for a skipped task, and the check-phase reset rule (§7a) must not
        // silently un-skip it on the next pass. Text-only regression — this
        // protocol is instructions for an agent, not executable logic.
        $protocol = new GrindTool()->protocol();

        $this->assertStringContainsString('If `task.status` is `skipped`, skip it.', $protocol);
        $this->assertStringContainsString('except a task whose status is `skipped`', $protocol);
        $this->assertStringContainsString('<s> skipped', $protocol);
    }

    #[Test]
    public function an_ordinary_tasks_own_failure_stops_its_ticket(): void
    {
        // req 513, mcp.md §14.3/§14.7: a non-budgeted task in an ordinary phase that
        // ends `failed` stops the current ticket and moves on to the next requested
        // one — it never enters the self-fixing check model. §4's generic "otherwise,
        // move to the next task" step must not swallow this for a direct §3d dispatch.
        // Text-only regression — this protocol is instructions for an agent, not
        // executable logic.
        $protocol = new GrindTool()->protocol();

        $this->assertStringContainsString('An ordinary task\'s own failure stops its ticket (req 513)', $protocol);
        $this->assertStringContainsString('This bullet does not apply inside a §7 pass or a §8 run', $protocol);
    }
}
