<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Cli;

use PHPUnit\Framework\Attributes\Test;

final class RequirementCommandsTest extends BaseCliTest
{
    private function addTicket(): int
    {
        $this->ok('project:add', ['--name' => 'proj', '--path' => '/tmp/proj']);
        $data = $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => '1', '--name' => 'my ticket']);

        return $this->intAt($data, 'id');
    }

    #[Test]
    public function it_adds_and_lists_requirements(): void
    {
        $ticketId = $this->addTicket();

        $this->ok('requirement:add', ['--ticket' => (string) $ticketId, '--name' => 'Req A']);
        $this->ok('requirement:add', ['--ticket' => (string) $ticketId, '--name' => 'Req B']);

        $list = $this->ok('requirement:list', ['--ticket' => (string) $ticketId]);
        $requirements = $this->listAt($list, 'requirements');
        $this->assertCount(2, $requirements);
        $this->assertSame('Req A', $this->itemAt($requirements, 0)['name']);
        $this->assertSame('Req B', $this->itemAt($requirements, 1)['name']);
    }

    #[Test]
    public function it_shows_a_requirement(): void
    {
        $ticketId = $this->addTicket();
        $this->ok('requirement:add', ['--ticket' => (string) $ticketId, '--name' => 'Req A', '--verification' => 'unverified']);

        $data = $this->ok('requirement:show', ['--requirement' => '1']);
        $this->assertSame('Req A', $data['name']);
        $this->assertSame('unverified', $data['verification']);
    }

    #[Test]
    public function it_updates_a_requirement(): void
    {
        $ticketId = $this->addTicket();
        $this->ok('requirement:add', ['--ticket' => (string) $ticketId, '--name' => 'Req A']);

        $data = $this->ok('requirement:set', ['--requirement' => '1', '--verification' => 'met']);
        $this->assertSame('met', $data['verification']);
    }

    #[Test]
    public function it_moves_a_requirement(): void
    {
        $ticketId = $this->addTicket();
        $a = $this->ok('requirement:add', ['--ticket' => (string) $ticketId, '--name' => 'A']);
        $b = $this->ok('requirement:add', ['--ticket' => (string) $ticketId, '--name' => 'B']);
        $aId = $this->intAt($a, 'id');
        $bId = $this->intAt($b, 'id');

        $this->ok('requirement:move', ['--requirement' => (string) $bId, '--before' => (string) $aId]);

        $list = $this->ok('requirement:list', ['--ticket' => (string) $ticketId]);
        $requirements = $this->listAt($list, 'requirements');
        $this->assertSame('B', $this->itemAt($requirements, 0)['name']);
        $this->assertSame('A', $this->itemAt($requirements, 1)['name']);
    }

    #[Test]
    public function it_deletes_a_requirement(): void
    {
        $ticketId = $this->addTicket();
        $this->ok('requirement:add', ['--ticket' => (string) $ticketId, '--name' => 'Req A']);

        $data = $this->ok('requirement:delete', ['--requirement' => '1']);
        $this->assertTrue((bool) $data['deleted']);
    }

    #[Test]
    public function it_adds_requirement_with_before_ordering(): void
    {
        $ticketId = $this->addTicket();
        $a = $this->ok('requirement:add', ['--ticket' => (string) $ticketId, '--name' => 'A']);
        $b = $this->ok('requirement:add', ['--ticket' => (string) $ticketId, '--name' => 'B', '--before' => (string) $this->intAt($a, 'id')]);

        $list = $this->ok('requirement:list', ['--ticket' => (string) $ticketId]);
        $requirements = $this->listAt($list, 'requirements');
        $this->assertSame('B', $this->itemAt($requirements, 0)['name']);
        $this->assertSame('A', $this->itemAt($requirements, 1)['name']);
    }

    #[Test]
    public function it_rejects_invalid_verification_on_add(): void
    {
        $ticketId = $this->addTicket();
        $error = $this->err('requirement:add', ['--ticket' => (string) $ticketId, '--name' => 'R', '--verification' => 'nonsense']);
        $this->assertSame('invalid_verification', $error['code']);
    }

    #[Test]
    public function it_rejects_invalid_verification_on_set(): void
    {
        $ticketId = $this->addTicket();
        $this->ok('requirement:add', ['--ticket' => (string) $ticketId, '--name' => 'R']);
        $error = $this->err('requirement:set', ['--requirement' => '1', '--verification' => 'nonsense']);
        $this->assertSame('invalid_verification', $error['code']);
    }
}
