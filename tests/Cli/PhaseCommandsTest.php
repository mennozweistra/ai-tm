<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Cli;

use PHPUnit\Framework\Attributes\Test;

final class PhaseCommandsTest extends BaseCliTest
{
    private function addTicket(): int
    {
        $this->ok('project:add', ['--name' => 'proj', '--path' => '/tmp/proj']);
        $data = $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => '1', '--name' => 'my ticket']);

        return $this->intAt($data, 'id');
    }

    #[Test]
    public function it_adds_and_lists_phases(): void
    {
        $ticketId = $this->addTicket();

        $this->ok('phase:add', ['--ticket' => (string) $ticketId, '--name' => 'Phase A']);
        $this->ok('phase:add', ['--ticket' => (string) $ticketId, '--name' => 'Phase B']);

        $list = $this->ok('phase:list', ['--ticket' => (string) $ticketId]);
        $phases = $this->listAt($list, 'phases');
        $this->assertCount(2, $phases);
        $this->assertSame('Phase A', $this->itemAt($phases, 0)['name']);
        $this->assertSame('Phase B', $this->itemAt($phases, 1)['name']);
    }

    #[Test]
    public function it_shows_a_phase(): void
    {
        $ticketId = $this->addTicket();
        $this->ok('phase:add', ['--ticket' => (string) $ticketId, '--name' => 'Phase A']);

        $data = $this->ok('phase:show', ['--phase' => '1']);
        $this->assertSame('Phase A', $data['name']);
    }

    #[Test]
    public function it_updates_a_phase(): void
    {
        $ticketId = $this->addTicket();
        $this->ok('phase:add', ['--ticket' => (string) $ticketId, '--name' => 'Phase A']);

        $data = $this->ok('phase:set', ['--phase' => '1', '--status' => 'active']);
        $this->assertSame('active', $data['status']);
    }

    #[Test]
    public function it_moves_a_phase(): void
    {
        $ticketId = $this->addTicket();
        $a = $this->ok('phase:add', ['--ticket' => (string) $ticketId, '--name' => 'A']);
        $b = $this->ok('phase:add', ['--ticket' => (string) $ticketId, '--name' => 'B']);
        $aId = $this->intAt($a, 'id');
        $bId = $this->intAt($b, 'id');

        $this->ok('phase:move', ['--phase' => (string) $bId, '--before' => (string) $aId]);

        $list = $this->ok('phase:list', ['--ticket' => (string) $ticketId]);
        $phases = $this->listAt($list, 'phases');
        $this->assertSame('B', $this->itemAt($phases, 0)['name']);
        $this->assertSame('A', $this->itemAt($phases, 1)['name']);
    }

    #[Test]
    public function it_deletes_a_phase(): void
    {
        $ticketId = $this->addTicket();
        $this->ok('phase:add', ['--ticket' => (string) $ticketId, '--name' => 'Phase A']);

        $data = $this->ok('phase:delete', ['--phase' => '1']);
        $this->assertTrue((bool) $data['deleted']);
    }

    #[Test]
    public function it_adds_a_phase_with_max_attempts(): void
    {
        $ticketId = $this->addTicket();
        $data = $this->ok('phase:add', ['--ticket' => (string) $ticketId, '--name' => 'Phase A', '--max-attempts' => '3']);
        $this->assertSame(3, $data['max_attempts']);
        $this->assertSame(0, $data['attempts']);
    }

    #[Test]
    public function it_sets_max_attempts_on_a_phase(): void
    {
        $ticketId = $this->addTicket();
        $this->ok('phase:add', ['--ticket' => (string) $ticketId, '--name' => 'Phase A']);

        $data = $this->ok('phase:set', ['--phase' => '1', '--max-attempts' => '5']);
        $this->assertSame(5, $data['max_attempts']);
    }

    #[Test]
    public function it_sets_attempts_on_a_phase(): void
    {
        $ticketId = $this->addTicket();
        $this->ok('phase:add', ['--ticket' => (string) $ticketId, '--name' => 'Phase A']);

        $data = $this->ok('phase:set', ['--phase' => '1', '--attempts' => '2']);
        $this->assertSame(2, $data['attempts']);
    }

    #[Test]
    public function it_shows_max_attempts_and_attempts_in_phase_show(): void
    {
        $ticketId = $this->addTicket();
        $this->ok('phase:add', ['--ticket' => (string) $ticketId, '--name' => 'Phase A', '--max-attempts' => '4']);
        $this->ok('phase:set', ['--phase' => '1', '--attempts' => '1']);

        $data = $this->ok('phase:show', ['--phase' => '1']);
        $this->assertSame(4, $data['max_attempts']);
        $this->assertSame(1, $data['attempts']);
    }
}
