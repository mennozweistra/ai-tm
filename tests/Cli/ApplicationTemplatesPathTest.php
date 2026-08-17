<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Cli;

use AiToolset\Tm\Cli\Application;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers Application::userTemplatesPath(), the pure function behind the
 * user-templates half of the two-source template resolution (ticket 269,
 * requirement 692). The resolution order and collision rule themselves are
 * covered against real files in ai-lib's TemplateRepositoryTest; this only
 * checks the path Application derives it from.
 */
final class ApplicationTemplatesPathTest extends TestCase
{
    #[Test]
    public function it_places_the_user_templates_directory_next_to_the_data_directory(): void
    {
        $this->assertSame(
            '/home/menno/.ai-tm/templates',
            Application::userTemplatesPath('/home/menno/.ai-tm'),
        );
    }
}
