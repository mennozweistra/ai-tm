<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Cli;

use AiToolset\Tm\Cli\Application;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApplicationDbPathTest extends TestCase
{
    #[Test]
    public function it_uses_tm_db_when_set_to_a_non_empty_string(): void
    {
        $this->assertSame(
            '/tmp/review.db',
            Application::resolveDbPath('/home/menno', '/tmp/review.db'),
        );
    }

    #[Test]
    public function it_falls_back_to_default_store_when_tm_db_is_unset(): void
    {
        $this->assertSame(
            '/home/menno/.ai-tm/store.db',
            Application::resolveDbPath('/home/menno', false),
        );
    }

    #[Test]
    public function it_falls_back_to_default_store_when_tm_db_is_empty(): void
    {
        $this->assertSame(
            '/home/menno/.ai-tm/store.db',
            Application::resolveDbPath('/home/menno', ''),
        );
    }
}
