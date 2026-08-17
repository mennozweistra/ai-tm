<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Mcp;

use PHPUnit\Framework\Attributes\Test;

final class GrindRunToolsTest extends BaseMcpTest
{
    private function scaffold(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'ticket', template: self::SCAFFOLD_TEMPLATE);
    }

    #[Test]
    public function it_adds_a_grind_run(): void
    {
        $this->scaffold();

        $result = $this->grindRunTools->add(ticket_id: 1, type: 'grind');
        $data = $this->data($result);

        $this->assertSame(1, $data['ticket_id']);
        $this->assertSame('grind', $data['type']);
    }

    #[Test]
    public function it_returns_already_exists_when_adding_duplicate_grind_run(): void
    {
        $this->scaffold();

        $this->grindRunTools->add(ticket_id: 1, type: 'grind');

        $this->assertFailed(
            $this->grindRunTools->add(ticket_id: 1, type: 'grind'),
            'already_exists',
        );
    }
}
