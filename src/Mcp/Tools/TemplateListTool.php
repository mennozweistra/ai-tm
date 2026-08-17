<?php

declare(strict_types=1);

namespace AiToolset\Tm\Mcp\Tools;

use AiToolset\AiLib\Services\TicketService;
use AiToolset\Tm\Mcp\BaseTools;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

final class TemplateListTool extends BaseTools
{
    public function __construct(
        private readonly TicketService $ticketService,
    ) {
        parent::__construct();
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_template_list', description: 'Return the names of all available templates (filenames without .toml), sorted alphabetically. Returns an empty list when no templates exist.')]
    #[Schema(additionalProperties: false)]
    public function list(): array
    {
        try {
            return $this->success(['templates' => $this->ticketService->listTemplates()]);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }
}
