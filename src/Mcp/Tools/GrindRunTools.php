<?php

declare(strict_types=1);

namespace AiToolset\Tm\Mcp\Tools;

use AiToolset\AiLib\Schemas\GrindRunIn;
use AiToolset\AiLib\Services\GrindRunService;
use AiToolset\Tm\Mcp\BaseTools;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

/**
 * The grind_run record's only remaining consumer is the prompt-original grind
 * protocol (`GrindTool::PROTOCOL` §3c): it calls `add()` once per ticket to tell
 * a fresh grind from a resume (requirement 299). The read-back and mutation
 * tools that existed for the retired orchestrator engines (show/set/list/delete)
 * are gone — nothing calls them any more (ticket 269 task 3646).
 */
final class GrindRunTools extends BaseTools
{
    public function __construct(private readonly GrindRunService $service)
    {
        parent::__construct();
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_grind_run_add', description: 'Add a grind run record for a ticket.')]
    #[Schema(additionalProperties: false)]
    public function add(int $ticket_id, string $type): array
    {
        try {
            return $this->success($this->service->add(new GrindRunIn(
                ticketId: $ticket_id,
                type: $type,
            )));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }
}
