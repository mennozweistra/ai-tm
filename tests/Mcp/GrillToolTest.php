<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Mcp;

use AiToolset\Tm\Mcp\Tools\GrillTool;
use PHPUnit\Framework\Attributes\Test;

final class GrillToolTest extends BaseMcpTest
{
    #[Test]
    public function it_returns_protocol_text_for_a_valid_ticket(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'Test', template: self::SCAFFOLD_TEMPLATE);

        $tool = new GrillTool($this->ticketService);
        $result = $tool->protocol(ticket: 1);

        $this->assertIsString($result);
        $this->assertStringContainsString('mcp__tm__tm_requirement_add', $result);
        $this->assertStringContainsString('mcp__tm__tm_requirement_set', $result);
        $this->assertStringContainsString('mcp__tm__tm_requirement_delete', $result);
        $this->assertStringContainsString('recommendation', $result);
        $this->assertStringContainsString('alignment', $result);
        $this->assertStringContainsString('sub-agent', $result);
        $this->assertStringContainsString('unverified', $result);
        $this->assertStringContainsString('mcp__tm__tm_ticket_set', $result);
        $this->assertStringContainsString('1', $result);

        // Ticket 192: the gear-based interview replaces the one-question-per-turn rule.
        $this->assertStringNotContainsString('one question at a time', $result);
        $this->assertStringNotContainsString('one question per turn', $result);
        $this->assertStringContainsString('two gears', $result);
        $this->assertStringContainsString('one per turn', $result);
        // A question the agent can answer itself never reaches the user.
        $this->assertStringContainsString('Settled', $result);
        $this->assertStringContainsString('mcp__tm__tm_log_add', $result);
        $this->assertStringNotContainsString('numbered batch', $result);

        // Ticket 192: every interview question is stored and closed as a question entity.
        $this->assertStringContainsString('mcp__tm__tm_question_add', $result);
        $this->assertStringContainsString('mcp__tm__tm_question_resolve', $result);
        $this->assertStringContainsString('mcp__tm__tm_question_process', $result);
        $this->assertStringContainsString('direct', $result);
        $this->assertStringContainsString('clarified', $result);
        $this->assertStringContainsString('deepened', $result);

        // Ticket 158: the Devil's Advocate review is now its own separate tm task, no longer
        // an internal step of tm_grill's protocol.
        $this->assertStringNotContainsString("Devil's Advocate", $result);
        $this->assertStringNotContainsString('spin off a fresh Devil\'s Advocate sub-agent', $result);
    }

    #[Test]
    public function it_embeds_the_ticket_id_in_the_returned_protocol(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'Ticket One', template: self::SCAFFOLD_TEMPLATE);
        $this->ticketTools->add(project: 1, name: 'Ticket Two', template: self::SCAFFOLD_TEMPLATE);

        $tool = new GrillTool($this->ticketService);

        $result1 = $tool->protocol(ticket: 1);
        $result2 = $tool->protocol(ticket: 2);

        $this->assertIsString($result1);
        $this->assertIsString($result2);

        // The placeholder is replaced per call, not left in place.
        $this->assertStringNotContainsString('<ticket.id>', $result1);
        $this->assertStringNotContainsString('<ticket.id>', $result2);

        // The two results are different, confirming the replacement is per-call.
        $this->assertNotSame($result1, $result2);
    }

    #[Test]
    public function it_returns_not_found_error_for_a_missing_ticket(): void
    {
        $tool = new GrillTool($this->ticketService);
        $result = $tool->protocol(ticket: 999999);

        $this->assertIsArray($result);
        $this->assertFailed($result, 'not_found');
    }

    #[Test]
    public function it_returns_error_for_an_archived_ticket(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'To Archive', template: self::SCAFFOLD_TEMPLATE);
        $this->ticketTools->archive(ticket: 1);

        $tool = new GrillTool($this->ticketService);
        $result = $tool->protocol(ticket: 1);

        $this->assertIsArray($result);
        $this->assertFailed($result, 'invalid_argument');
    }
}
