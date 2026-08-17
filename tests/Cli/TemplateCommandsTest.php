<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Cli;

use PHPUnit\Framework\Attributes\Test;

final class TemplateCommandsTest extends BaseCliTest
{
    #[Test]
    public function it_lists_available_templates_sorted_alphabetically(): void
    {
        $data = $this->ok('template:list');
        $templates = $this->listAt($data, 'templates');

        $this->assertContains(self::SCAFFOLD_TEMPLATE, $templates);
        $sorted = $templates;
        sort($sorted);
        $this->assertSame($sorted, $templates);
    }
}
