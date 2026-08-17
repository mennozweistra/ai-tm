<?php

declare(strict_types=1);

namespace AiToolset\Tm\Mcp\Tools;

use AiToolset\AiLib\Domain\Config;
use AiToolset\Tm\Mcp\BaseTools;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

final class ConfigTools extends BaseTools
{
    public function __construct(private readonly Config $config)
    {
        parent::__construct();
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_config_list', description: 'Show the current tm configuration.')]
    #[Schema(additionalProperties: false)]
    public function list(): array
    {
        try {
            return $this->success($this->config);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }
}
