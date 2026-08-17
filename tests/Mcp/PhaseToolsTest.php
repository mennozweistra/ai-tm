<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Mcp;

use PHPUnit\Framework\Attributes\Test;

final class PhaseToolsTest extends BaseMcpTest
{
    private function scaffold(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'ticket', template: self::SCAFFOLD_TEMPLATE);
    }

    #[Test]
    public function it_adds_a_phase_with_max_attempts(): void
    {
        $this->scaffold();
        $data = $this->data($this->phaseTools->add(ticket: 1, name: 'Phase A', max_attempts: 3));

        $this->assertSame(3, $data['max_attempts']);
        $this->assertSame(0, $data['attempts']);
    }

    #[Test]
    public function it_sets_max_attempts_on_a_phase(): void
    {
        $this->scaffold();
        $this->phaseTools->add(ticket: 1, name: 'Phase A');

        $data = $this->data($this->phaseTools->set(phase: 1, max_attempts: 5));
        $this->assertSame(5, $data['max_attempts']);
    }

    #[Test]
    public function it_sets_attempts_on_a_phase(): void
    {
        $this->scaffold();
        $this->phaseTools->add(ticket: 1, name: 'Phase A');

        $data = $this->data($this->phaseTools->set(phase: 1, attempts: 2));
        $this->assertSame(2, $data['attempts']);
    }

    #[Test]
    public function it_shows_max_attempts_and_attempts_in_phase_show(): void
    {
        $this->scaffold();
        $this->phaseTools->add(ticket: 1, name: 'Phase A', max_attempts: 4);
        $this->phaseTools->set(phase: 1, attempts: 1);

        $data = $this->data($this->phaseTools->show(phase: 1));
        $this->assertSame(4, $data['max_attempts']);
        $this->assertSame(1, $data['attempts']);
    }

    #[Test]
    public function it_returns_max_attempts_and_attempts_in_phase_list(): void
    {
        $this->scaffold();
        $this->phaseTools->add(ticket: 1, name: 'Phase A', max_attempts: 2);

        $list = $this->data($this->phaseTools->list(ticket: 1));
        $phases = $list['phases'];
        $this->assertIsArray($phases);
        $this->assertCount(1, $phases);
        $phase = $phases[0];
        $this->assertIsArray($phase);
        $this->assertSame(2, $phase['max_attempts']);
        $this->assertSame(0, $phase['attempts']);
    }
}
