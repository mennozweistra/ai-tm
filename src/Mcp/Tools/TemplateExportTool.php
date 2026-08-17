<?php

declare(strict_types=1);

namespace AiToolset\Tm\Mcp\Tools;

use AiToolset\AiLib\Services\TicketService;
use AiToolset\Tm\Mcp\BaseTools;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

final class TemplateExportTool extends BaseTools
{
    public function __construct(
        private readonly TicketService $ticketService,
    ) {
        parent::__construct();
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_template_export', description: 'Save a ticket\'s phase/task structure as a named TOML template file. The name is the filename without extension. Returns the written file path on success. An existing file with the same name is overwritten.')]
    #[Schema(additionalProperties: false)]
    public function export(int $ticket, string $name): array
    {
        try {
            $path = $this->ticketService->exportTemplate($ticket, $name);

            return $this->success(['path' => $path]);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }
}
