<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli;

/**
 * Reads and writes tm's three optional Claude Code hook entries in the user-level
 * settings file (by default ~/.claude/settings.json — not the per-project
 * settings.local.json the retired Mcp\HookInstaller used to write automatically on
 * every MCP server start). Requirement 682 (ticket 269): tm no longer wires hooks
 * silently. enable() and disable() are the only two operations a user has; there is no
 * install-on-startup path any more.
 *
 * The three shell commands and the instruction text they carry were originally copied
 * from Mcp\HookInstaller rather than imported from it, since Deptrac forbids a
 * Cli-layer class from depending on Mcp. Mcp\HookInstaller is now deleted (ticket 269):
 * this class is the only source of truth for the text.
 *
 * enable() is idempotent: a marker whose command already matches exactly is left alone; a
 * marker present with a stale command is replaced in place; a missing marker is appended.
 * Running it twice writes nothing new. disable() removes exactly the three marked entries
 * and leaves everything else — including hooks tm did not write — untouched; a hook group
 * left empty by a removal is dropped rather than left behind as `"hooks": []`.
 *
 * Both throw \RuntimeException, without writing anything, when the settings file exists
 * and is not valid JSON.
 */
final readonly class HookSettingsInstaller
{
    /** Stable substring identifying tm's Stop logging hook in any command string. */
    public const string HOOK_MARKER = 'tm:logging-hook';

    /** Stable substring identifying tm's UserPromptSubmit start hook in any command string. */
    public const string START_HOOK_MARKER = 'tm:start-hook';

    /** Stable substring identifying tm's UserPromptSubmit grind hook in any command string. */
    public const string GRIND_HOOK_MARKER = 'tm:grind-hook';

    /**
     * The start hook's additionalContext. Copied from Mcp\Tools\StartHookTool::INSTRUCTION —
     * that class stays in the MCP server (it also backs the tm_start_hook tool an agent calls
     * when the hook fires), so this is a snapshot, not the canonical source.
     */
    private const string START_INSTRUCTION = 'Start-of-turn tm check. If you are about to start work on a specific task that is currently pending, set it to active with mcp__tm__tm_task_set before doing anything else. If the task is already active, or this turn is a discussion or non-implementation turn, do nothing and proceed.';

    /**
     * The context injected only when the user's message contains the word "grind".
     * Copied from HookInstaller::GRIND_CONTEXT.
     */
    private const string GRIND_CONTEXT = 'Your message contains the word "grind". If you are asking to grind one or more tm tickets, call mcp__tm__tm_grind before doing anything else and follow the protocol it returns. If "grind" is unrelated to tm here, ignore this.';

    /**
     * The Stop hook's `reason` string — kept to one line because Claude Code prints it every
     * turn. Copied from HookInstaller::HOOK_REASON. The actual logging instruction lives in
     * Mcp\Tools\StopHookTool, reached through the tm_stop_hook tool the reason points at.
     */
    private const string HOOK_REASON = 'Call mcp__tm__tm_stop_hook now and follow what it returns.';

    public function __construct(private string $settingsPath) {}

    /**
     * Adds tm's three hook entries to the settings file, creating it (and its parent
     * directory) if missing. Returns true when the file was written, false when all three
     * were already present with their current exact command (a no-op).
     */
    public function enable(): bool
    {
        $settings = $this->readSettings();

        $stopPresent = $this->hookGroupContainsCommand($settings, 'Stop', $this->buildStopCommand());
        $startPresent = $this->hookGroupContainsCommand($settings, 'UserPromptSubmit', $this->buildStartCommand());
        $grindPresent = $this->hookGroupContainsCommand($settings, 'UserPromptSubmit', $this->buildGrindCommand());

        if ($stopPresent && $startPresent && $grindPresent) {
            return false;
        }

        if (!$stopPresent) {
            $settings = $this->replaceOrAppendHookGroup($settings, 'Stop', self::HOOK_MARKER, $this->buildStopCommand());
        }

        if (!$startPresent) {
            $settings = $this->replaceOrAppendHookGroup($settings, 'UserPromptSubmit', self::START_HOOK_MARKER, $this->buildStartCommand());
        }

        if (!$grindPresent) {
            $settings = $this->replaceOrAppendHookGroup($settings, 'UserPromptSubmit', self::GRIND_HOOK_MARKER, $this->buildGrindCommand());
        }

        $this->write($settings);

        return true;
    }

    /**
     * Removes tm's three marked hook entries from the settings file. Returns true when the
     * file was written (at least one marked entry was present), false when none were found
     * (a no-op; includes a missing settings file, treated as empty).
     */
    public function disable(): bool
    {
        $settings = $this->readSettings();

        $removedAny = false;
        foreach ([
            ['Stop', self::HOOK_MARKER],
            ['UserPromptSubmit', self::START_HOOK_MARKER],
            ['UserPromptSubmit', self::GRIND_HOOK_MARKER],
        ] as [$event, $marker]) {
            [$settings, $removed] = $this->removeHookByMarker($settings, $event, $marker);
            $removedAny = $removedAny || $removed;
        }

        if (!$removedAny) {
            return false;
        }

        $this->write($settings);

        return true;
    }

    /**
     * Reads and decodes the settings file. An absent or empty file reads as {}.
     *
     * @return array<mixed, mixed>
     */
    private function readSettings(): array
    {
        if (!file_exists($this->settingsPath)) {
            return [];
        }

        $raw = file_get_contents($this->settingsPath);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException("Settings file is not valid JSON: {$this->settingsPath}");
        }

        return $decoded;
    }

    /** @param array<mixed, mixed> $settings */
    private function write(array $settings): void
    {
        $dir = dirname($this->settingsPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->settingsPath,
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
        );
    }

    /**
     * Returns true when any hook group for $event contains a command that matches $command
     * exactly.
     *
     * @param array<mixed, mixed> $settings
     */
    private function hookGroupContainsCommand(array $settings, string $event, string $command): bool
    {
        if (!isset($settings['hooks']) || !is_array($settings['hooks'])) {
            return false;
        }

        $hooksSection = $settings['hooks'];
        if (!isset($hooksSection[$event]) || !is_array($hooksSection[$event])) {
            return false;
        }

        foreach ($hooksSection[$event] as $group) {
            if (!is_array($group) || !isset($group['hooks']) || !is_array($group['hooks'])) {
                continue;
            }

            foreach ($group['hooks'] as $hook) {
                if (
                    is_array($hook)
                    && isset($hook['command'])
                    && is_string($hook['command'])
                    && $hook['command'] === $command
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Finds the first hook group under $settings['hooks'][$event] that contains a hook whose
     * command str_contains($marker), replaces that hook's command with $command, and returns
     * the updated settings. If no such group is found, appends a new group.
     *
     * @param array<mixed, mixed> $settings
     * @return array<mixed, mixed>
     */
    private function replaceOrAppendHookGroup(array $settings, string $event, string $marker, string $command): array
    {
        if (!isset($settings['hooks']) || !is_array($settings['hooks'])) {
            $settings['hooks'] = [];
        }

        if (!isset($settings['hooks'][$event]) || !is_array($settings['hooks'][$event])) {
            $settings['hooks'][$event] = [];
        }

        $newGroups = [];
        $replaced = false;

        foreach ($settings['hooks'][$event] as $group) {
            if (!$replaced && is_array($group) && isset($group['hooks']) && is_array($group['hooks'])) {
                $newHooks = [];
                foreach ($group['hooks'] as $hook) {
                    if (
                        !$replaced
                        && is_array($hook)
                        && isset($hook['command'])
                        && is_string($hook['command'])
                        && str_contains($hook['command'], $marker)
                    ) {
                        $hook['command'] = $command;
                        $replaced = true;
                    }
                    $newHooks[] = $hook;
                }
                $group['hooks'] = $newHooks;
            }
            $newGroups[] = $group;
        }

        if (!$replaced) {
            $newGroups[] = [
                'hooks' => [
                    [
                        'type' => 'command',
                        'command' => $command,
                    ],
                ],
            ];
        }

        $settings['hooks'][$event] = $newGroups;

        return $settings;
    }

    /**
     * Removes any hook whose command contains $marker from $event's groups. A group left
     * without hooks is dropped entirely rather than kept as an empty group.
     *
     * @param array<mixed, mixed> $settings
     * @return array{0: array<mixed, mixed>, 1: bool}
     */
    private function removeHookByMarker(array $settings, string $event, string $marker): array
    {
        if (!isset($settings['hooks']) || !is_array($settings['hooks'])) {
            return [$settings, false];
        }

        $hooksSection = $settings['hooks'];
        if (!isset($hooksSection[$event]) || !is_array($hooksSection[$event])) {
            return [$settings, false];
        }

        $removed = false;
        $newGroups = [];

        foreach ($hooksSection[$event] as $group) {
            if (!is_array($group) || !isset($group['hooks']) || !is_array($group['hooks'])) {
                $newGroups[] = $group;
                continue;
            }

            $newHooks = [];
            foreach ($group['hooks'] as $hook) {
                if (
                    is_array($hook)
                    && isset($hook['command'])
                    && is_string($hook['command'])
                    && str_contains($hook['command'], $marker)
                ) {
                    $removed = true;
                    continue;
                }
                $newHooks[] = $hook;
            }

            if ($newHooks === [] && $group['hooks'] !== []) {
                // The marked hook was the group's only member — drop the group.
                continue;
            }

            $group['hooks'] = $newHooks;
            $newGroups[] = $group;
        }

        $hooksSection[$event] = $newGroups;
        $settings['hooks'] = $hooksSection;

        return [$settings, $removed];
    }

    /**
     * Builds the Stop hook one-liner with the marker embedded as a comment.
     *
     * The guard on stop_hook_active prevents an infinite loop: when the agent calls
     * tm_stop_hook and then stops, the Stop hook fires again but with
     * stop_hook_active=true in the input, so the command exits 0 and does not block a
     * second time.
     */
    private function buildStopCommand(): string
    {
        $inner = json_encode(
            [
                'decision' => 'block',
                'reason' => self::HOOK_REASON,
            ],
            JSON_UNESCAPED_UNICODE,
        );

        return "if jq -e '.stop_hook_active == true' >/dev/null 2>&1; then exit 0; else echo "
            . escapeshellarg((string) $inner) . '; fi # ' . self::HOOK_MARKER;
    }

    /**
     * Builds the UserPromptSubmit start hook one-liner with the start marker embedded as a
     * comment. Uses non-blocking additionalContext injection so the hook never deadlocks
     * when the MCP server is unavailable.
     */
    private function buildStartCommand(): string
    {
        $inner = json_encode(
            [
                'hookSpecificOutput' => [
                    'hookEventName' => 'UserPromptSubmit',
                    'additionalContext' => self::START_INSTRUCTION,
                ],
            ],
            JSON_UNESCAPED_UNICODE,
        );

        return 'echo ' . escapeshellarg((string) $inner) . ' # ' . self::START_HOOK_MARKER;
    }

    /**
     * Builds the UserPromptSubmit grind hook one-liner with the grind marker embedded as a
     * comment. Inspects the turn's prompt (read from stdin with jq) and injects
     * additionalContext only when the prompt contains the whole word "grind"; otherwise it
     * produces no output.
     */
    private function buildGrindCommand(): string
    {
        $inner = json_encode(
            [
                'hookSpecificOutput' => [
                    'hookEventName' => 'UserPromptSubmit',
                    'additionalContext' => self::GRIND_CONTEXT,
                ],
            ],
            JSON_UNESCAPED_UNICODE,
        );

        $prefix = 'prompt=$(jq -r \'.prompt // ""\'); if printf \'%s\' "$prompt" | grep -qiw grind; then echo ';

        return $prefix . escapeshellarg((string) $inner) . '; fi # ' . self::GRIND_HOOK_MARKER;
    }
}
