# Implementation Decisions Log

This file records small judgment calls made during implementation that the architecture documents did not anticipate. Reviewed by the user between phases.

For the format and what counts as "small", see `../AGENTS.md` ("Recording small judgment calls").

---

## 2026-05-08 — Phase 0 — Deptrac package selection

**Decision:** Used `qossmic/deptrac-shim ^1.0` rather than `deptrac/deptrac`.

**Reason:** `deptrac/deptrac` on Packagist has only dev-track versions (3.x-dev, 4.x-dev) with no stable release. `qossmic/deptrac-shim` 1.0.2 is the current stable shim and wraps a stable deptrac binary. Using it unblocks Phase 0 without altering the architecture.

**Where:** `composer.json` require-dev section.

---

## 2026-05-08 — Phase 0 — Stub classes for PHPStan and bin entry points

**Decision:** Created minimal stub classes `src/Cli/Application.php` and `src/Mcp/McpServer.php` at Phase 0.

**Reason:** PHPStan 2.x exits with error code 1 when there are no files to analyze. The bin entry points also reference these classes. Creating stubs satisfies PHPStan and makes the bin files syntactically complete without adding any Phase 8/9 logic.

**Where:** `src/Cli/Application.php`, `src/Mcp/McpServer.php`.

---

## 2026-05-12 — Ticket 21 Phase 1 — tm_grind takes no parameters

**Decision:** `tm_grind` mirrors `tm_stop_hook`: no parameters, returns a static block of protocol text. It does not take the target ticket ids and substitute them into the returned text.

**Reason:** The agent calling `tm_grind` already knows which ticket ids the user named when asking for a grind, so passing them back through the tool adds nothing. Substitution would only matter if the protocol needed the ids embedded literally, which it does not — the protocol refers to "the ticket ids the user named". Keeping it parameter-free matches the established `tm_stop_hook` shape.

**Where:** `src/Mcp/Tools/GrindTool.php`, registered in `src/Mcp/McpServer.php` `buildContainer()`.

---

## 2026-05-12 — Ticket 21 Phase 1 — tm_grind supersedes the grind-php slash command

**Decision:** The `tm_grind` MCP tool supersedes the standalone `~/.claude/commands/grind-php.md` slash command. `tm` does not auto-install or auto-remove slash-command files; it ships the grind protocol through the MCP tool instead. The old `grind-php.md` file is now redundant and should be deleted by hand on each machine that has it (this Linux machine and the Mac).

**Reason:** Shipping the protocol through an MCP tool means there is no install step and no per-machine file to keep in sync. Auto-removing a stale `grind-php.md` on server startup — mirroring how `HookInstaller` manages the logging hook — was considered and judged not worth the complexity for a one-time cleanup of a single file; a doc note is sufficient.

**Where:** `src/Mcp/Tools/GrindTool.php` (the tool that replaces it); manual deletion of `~/.claude/commands/grind-php.md` on each machine.

---

## 2026-05-12 — Ticket 23 Task 118 — Left the grind protocol text using `content` placeholder untouched

**Decision:** Task 118 renamed the `tm_log_add` MCP tool's `content` parameter to `ai_content` and added a `title` parameter, and updated the CLI `log:add` command (`--content` → `--ai-content`, added `--title`) and the docs. The grind protocol text in `src/Mcp/Tools/GrindTool.php` step `e` still refers to a `content:` argument and carries a conditional sentence "When `tm` log entries gain a `title` field ... put the short line in `title` and the fuller summary in `ai_content`". I left that text as is rather than rewriting it to use `title:` and `ai_content:` directly.

**Reason:** Ticket 23 is titled "Expose log title/detail in `tm_log_add` and use a title when grinding" — the "use a title when grinding" half is a separate task in the phase, and rewriting the grind protocol now would collide with it. The conditional sentence already tells a grinding agent what to do once the field exists, so the protocol is not broken in the meantime.

**Where:** `src/Mcp/Tools/GrindTool.php` step `e` (left unchanged).

---

## 2026-05-12 — Ticket 32 Phase 54 — Template layer namespace

**Decision:** Added `src/Template/` as a new `Template` layer in `deptrac.yaml`, allowed for both `Cli` and `Mcp`. `TemplateRepository` lives there.

**Reason:** The architecture says tm has no Repositories of its own, meaning no data-plane repositories delegated to ai-lib. `TemplateRepository` is a file-system helper that reads and writes TOML files; it has no database connection and is not an entity repository. Placing it in `Cli` or `Mcp` would make it inaccessible to the other adapter. A dedicated `Template/` layer, registered in deptrac so both adapters can import from it, is the least-invasive extension of the existing three-layer structure. The deptrac ruleset for `Template` is `~` (no outward dependencies allowed) so the layer stays self-contained.

**Where:** `src/Template/TemplateRepository.php`, `deptrac.yaml` (Template layer entry and ruleset).

## 2026-06-02 — User request — Reject unknown MCP tool arguments (additionalProperties:false)

**Decision:** Every MCP tool method now carries `#[Schema(additionalProperties: false)]`, so an unknown argument is rejected by the schema validator instead of being silently dropped. A new test, `tests/Mcp/McpToolSchemaStrictTest.php`, boots the server and asserts every registered tool's input schema sets `additionalProperties:false`; it fails if a future tool is added without the attribute. Chose the per-method attribute over a central post-discovery pass because `PhpMcp\Schema\Tool` and its `inputSchema` are `readonly`, so a central rewrite would mean rebuilding and re-registering every tool through library internals — fragile across upgrades. The guard test gives the robustness the central approach would have, without the fragility.

**Reason:** An agent called `tm_log_add` with the old parameter name `content` (renamed to `ai_content` in ticket 23 task 118). The auto-generated schema did not set `additionalProperties:false`, so `opis/json-schema` accepted the extra field, the handler ran with `ai_content` defaulting to empty, and two log entries were created with empty bodies and no error. Rejecting unknown arguments turns that silent data loss into an explicit validation error the caller sees.

**Where:** `#[Schema(additionalProperties: false)]` on all 34 methods in `src/Mcp/Tools/*.php`; guard test at `tests/Mcp/McpToolSchemaStrictTest.php`.

## 2026-07-01 — ticket-129 — max_attempts optional in write() PHPDoc

**Decision:** Used `max_attempts?: int` (optional key) in the `TemplateRepository::write()` PHPDoc rather than making it required and updating every test caller.

**Reason:** The `encode()` method already defaults to 0 via `$this->int($task, 'max_attempts')` when the key is absent, which is the correct backward-compat behaviour for templates created before this field existed. Making the PHPDoc match that reality — the key is optional — is more accurate than requiring it everywhere and patching every existing test call. `TemplateExportTool` was updated to supply it explicitly so exported templates always capture the current value.

**Where:** `src/Template/TemplateRepository.php` line 33 (PHPDoc for `write()`).

## 2026-07-02 — ticket-141 task 1138 — Wired `RequirementRepository` into the shared MCP test fixture's `TicketService`

**Decision:** `tests/Mcp/BaseMcpTest.php` built `TicketService` without passing `requirementRepository` (an optional, nullable constructor argument in `ai-lib`'s `TicketService`). Added it, reusing the same `RequirementRepository` instance already constructed there for `RequirementTools`, instead of leaving it unset.

**Reason:** Writing the new outline-mode test (`tests/Mcp/TicketToolsTest.php`) requires asserting that a requirement's `description` and `ai_description` fields are genuinely absent from the outline response. With `requirementRepository` unset, `TicketService::showOutline()` (and `showDeep()`) always return an empty `requirements` array regardless of what requirements exist on the ticket, since the service falls back to `[]` when the repository is null. This was a pre-existing gap in the shared fixture — it silently meant no MCP test using this base class could ever exercise the `requirements` field on a deep or outline ticket response. Fixing it in the fixture is a one-line, non-behavioural change (production code is unaffected) and was necessary for the new test to be meaningful; leaving it unfixed would have meant testing outline mode's requirement-field omission was impossible.

**Where:** `tests/Mcp/BaseMcpTest.php` (added `$requirementRepo` shared variable, passed as `requirementRepository:` to `TicketService`, reused for `RequirementTools`).

## 2026-07-02 — ticket-141 task 1139 — Widened `$args` PHPDoc type in `BaseCliTest` to allow `null` values

**Decision:** Widened the `@param array<string, string> $args` PHPDoc on `BaseCliTest::exec()`, `ok()`, and `err()` to `array<string, string|null>`.

**Reason:** Symfony's `ArrayInput` only resolves a `VALUE_NONE` option (a boolean flag like `--deep` or the new `--outline`) to `true` when its array value is `null`; passing `''` or omitting the key does not set the flag. No existing CLI test exercised a `VALUE_NONE` flag before this task, so the docblock's `string`-only value type had never been hit. The new outline tests pass `'--outline' => null` and `'--deep' => null` to exercise the flags, which PHPStan (level max) correctly flagged against the old docblock. Widening the type is the accurate fix — production code (`TicketShowCommand`, `Application`) is unaffected — rather than working around it with a different value that would not actually set the flag.

**Where:** `tests/Cli/BaseCliTest.php` lines 30, 43, 57 (PHPDoc for `exec()`, `ok()`, `err()`).

---

## 2026-07-02 — Ticket 143 — Grind is now a pure execution engine

**Decision:** `tm_grind` no longer has any built-in planning. Its requirements-detection check, the Discovery gate that guarded it, the requirements-to-tasks planner subagent, and the plan-only/full run modes are all removed; `tm_grind` now takes no parameters. Planning happens as an ordinary task — typically the feature template's Planning-phase task — whose `ai_description` instructs the worker subagent to create the ticket's other `tm` tasks. To make that possible, a worker subagent may now call any `tm` MCP tool, including write tools such as `tm_task_add`, `tm_phase_add`, and `tm_task_set`, with no per-task allowlist. The worker also judges its own success against its task's own goal rather than against whether it produced a commit, so a task that legitimately makes no file changes — a planning task, or a check that only reports findings — is a valid successful outcome, not a failure and not grounds for `REVIEW:`. The must-pass fix-and-recheck loop and its fix-work planner subagent are unchanged.

**Reason:** The built-in planning stage duplicated planning that the feature template's own Planning phase already does, and its Discovery gate created a second, competing gate on top of the template's own phase ordering. Running both meant a ticket could be planned twice through two different mechanisms, and the built-in planner's narrow read-only tool allowlist meant it could not participate in the same review chains ordinary tasks go through. Making grind a pure executor — it runs the tasks that exist, and does not decide what tasks should exist — removes the double-planning path and lets a planning task be reviewed and gated like any other task. See ticket 143 for the full design discussion.

**Where:** `src/Mcp/Tools/GrindTool.php`.

## 2026-07-02 — Ticket 142 task 1149 — Rebuilt the shared MCP test fixture around a mandatory-template `TicketTools::add()`

**Decision:** `tests/Mcp/BaseMcpTest.php` now writes one structure-free template (constant `SCAFFOLD_TEMPLATE`, name `scaffold`: empty description, no phases) into a per-test temp directory and wires it into the shared `TicketService` via `ai-lib`'s `TemplateRepository`, since `TicketTools::add()` no longer creates a ticket without a template. Every existing `ticketTools->add()` call across `tests/Mcp/*` that previously relied on the removed blank-ticket path now passes `template: self::SCAFFOLD_TEMPLATE` explicitly. `GrillToolTest`'s calls with the old `template: 'none'` sentinel (the removed backdoor value) were changed to the same real template. `McpToolsTest`'s three tests covering the deleted `choose_template` gate (`it_blocks_ticket_creation_when_templates_exist_and_no_template_is_given`, `it_creates_blank_ticket_when_template_none_is_passed_and_templates_exist`, `it_creates_blank_ticket_without_gating_when_no_templates_exist`) were deleted rather than adapted, since they tested behaviour that no longer exists. The new required-template behaviour (null/empty-string/unknown template rejected as `invalid_argument`; a valid template creates the ticket with its phases and tasks) is covered by four new tests in `tests/Mcp/TicketToolsTest.php`, which build a second `TicketTools` instance against a dedicated template fixture containing one real template (`basic`, one phase, one task) so the assertions can check actual template content without disturbing the shared scaffold fixture used by every other test file.

**Reason:** Task 1149 makes `template` mandatory at the `TicketService` level (`addFromTemplate()` rejects null, empty-string, and unknown names identically). Six test files across `tests/Mcp/` scaffold tickets through the shared fixture without caring about template content, so leaving the fixture's template directory empty — as it was before this task, deliberately, to keep the old gate from firing — would have made every one of those scaffold calls fail. Standardising on a single named, phase-free template for pure scaffolding keeps those tests focused on what they actually check (phases, tasks, requirements, grind runs, priorities) without asserting anything about template content, while `TicketToolsTest` gets a separate, richer template fixture for the tests that specifically verify template-driven ticket creation.

**Where:** `tests/Mcp/BaseMcpTest.php`, `tests/Mcp/TicketToolsTest.php`, `tests/Mcp/McpToolsTest.php`, `tests/Mcp/GrillToolTest.php`, `tests/Mcp/GrindRunToolsTest.php`, `tests/Mcp/TaskToolsTest.php`, `tests/Mcp/TemplateExportToolTest.php`.

---

## 2026-07-02 — Ticket 142 task 1152 — CLI scaffold template written into the real templates directory, not a temp directory

**Decision:** `ticket:add` now requires `--template` and calls `TicketService::addFromTemplate()`, wired through a new `AiToolset\AiLib\Repositories\TemplateRepository` constructed in `Application::wireCommands()` at a fixed path, exposed as the public static `Application::templatesPath()` (`dirname(__DIR__, 2) . '/templates'`, the same base path the MCP server already used). `tests/Cli/BaseCliTest.php` writes a structure-free `cli_test_scaffold` template into that exact real directory in `setUp()` and deletes it in `tearDown()`, then every existing `ticket:add` call across `tests/Cli/*` that previously created a blank ticket now passes `--template` with that constant. This mirrors task 1149's MCP-side fixture pattern, but MCP's `BaseMcpTest` could point its `TemplateRepository` at a per-test temp directory because it constructs `TicketService` directly; the CLI's `BaseCliTest` instantiates the real `Application` class, which reads templates from a path fixed at construction time (matching the MCP server's own fixed path, per this task's brief), so there is no constructor seam to redirect it to a temp directory for tests. Writing the fixture file into the real directory and removing it after each test is the closest equivalent given that constraint. The three tests the task named (missing template, unknown template with the available-names message, valid template with real phases and tasks) went into a new `tests/Cli/TicketAddCommandTest.php`, which seeds its own second real-directory template (`cli_ticket_add_test_template`) for the content-bearing case, leaving `TicketCommandsTest.php` and the other command test files scaffolding purely through the shared `SCAFFOLD_TEMPLATE`.

**Reason:** Making `--template` mandatory removes the blank-ticket path every other CLI test file relied on for scaffolding (creating a ticket to then add phases, tasks, requirements, log entries, or grind runs against it). Without a shared, content-free template, every one of those incidental `ticket:add` calls would start failing with `invalid_argument`, and using a real content-bearing template like `feature.toml` instead would seed 6 phases and 15+ tasks per scaffolded ticket, breaking every hardcoded id assumption (`--phase '1'`, `--task '1'`) in the unrelated Phase/Task/Log/Requirement/GrindRun command tests. A dedicated phase-free template, scoped to tests only and cleaned up after each test, keeps those tests focused on what they actually check.

**Where:** `src/Cli/Application.php` (`templatesPath()`, `TemplateRepository` wiring), `src/Cli/Commands/TicketAddCommand.php`, `src/Cli/BaseCommand.php` (`InvalidTemplateException => 'invalid_argument'`), `tests/Cli/BaseCliTest.php`, `tests/Cli/TicketAddCommandTest.php` (new), `tests/Cli/TicketCommandsTest.php`, `tests/Cli/PhaseCommandsTest.php`, `tests/Cli/TaskCommandsTest.php`, `tests/Cli/LogCommandsTest.php`, `tests/Cli/RequirementCommandsTest.php`, `tests/Cli/GrindRunCommandsTest.php`.

## 2026-07-04 — Ticket 161 task 1566 — TM_DB as the single database selector across bin/tm, tm-mcp, and the dashboard

**Decision:** `TM_DB` is the one environment variable that selects the SQLite database for every launchable binary in the toolset — `bin/tm`, `bin/tm-mcp`, and the dashboard's `public/index.php`. When set and non-empty it is opened directly; when unset or empty, all three fall back to `~/.ai-tm/store.db`. There is no `--path` flag on `tm migrate` or any other command: one environment variable covers every subcommand, so there is nothing to keep in sync between a flag and the variable. `Cli\Application::resolveDbPath()` and `Mcp\McpServer::resolveDbPath()` each implement the identical `($tmDb !== false && $tmDb !== '') ? $tmDb : $home . '/.ai-tm/store.db'` predicate as a small public static method, rather than sharing one helper. `ai-dashboard/public/index.php` carries a third copy of the same predicate, inline rather than as a named method, for the same Deptrac-shaped reason: the dashboard has no legal way to depend on `tm`'s classes any more than `tm` can push this policy down into `ai-lib`, so the four-line predicate is duplicated a third time instead of shared. `TM_DB` scopes only the database file: `~/.ai-tm/config.toml` and the `~/.ai-tm/` data directory `tm setup` creates are unaffected by it in all three consumers. The parent directory of a `TM_DB` path must already exist; SQLite creates the database file but not its directory, so a path in a missing directory fails at process start with `unable to open database file`.

**Reason:** Duplicating the predicate instead of extracting a shared helper is a Deptrac consequence, not a preference: `tm`'s only Deptrac-legal shared dependency is `ai-lib`, and `ai-lib` is the data plane — it owns the entity model, not process launch policy. Putting an environment-variable resolution rule (which env var, which fallback path, how `false` vs `''` are treated) into `ai-lib` would mean the data plane deciding adapter-level policy, which is the layer smell `tm`'s architecture guardrails exist to prevent (see `AGENTS.md`, "Architectural guardrails"). Two four-line static methods, each covered by its own test (`tests/Cli/ApplicationDbPathTest.php`, `tests/Mcp/McpServerDbPathTest.php`), cost less than introducing a shared home for a rule this small. Keeping `config.toml` and the data directory pinned to `~/.ai-tm` regardless of `TM_DB` means a test-database review still uses the same config (valid statuses, log types) as production, and `tm setup` never needs to know about `TM_DB` at all.

**Where:** `src/Cli/Application.php` (`resolveDbPath()`, used in the constructor), `src/Mcp/McpServer.php` (`resolveDbPath()`, used in `run()`), `tests/Cli/ApplicationDbPathTest.php`, `tests/Mcp/McpServerDbPathTest.php`. Dashboard side (`ai-dashboard/public/index.php`, `ai-dashboard/docs/decisions.md`) is a sibling task on the same ticket. Documented in `docs/api/cli.md` §1.5, `docs/api/mcp.md` §8, and `spec.md` §7.

## 2026-07-10 — Ticket 192 task 2218 — Question service exceptions get their own error codes, not `internal`

**Decision:** `BaseTools::errorCode()` and `BaseCommand::errorCode()` each got four new match arms mapping `QuestionService`'s domain exceptions to specific codes: `InvalidQuestionKindException` → `invalid_question_kind`, `InvalidQuestionStateException` → `invalid_question_state`, `InvalidResolutionQualityException` → `invalid_resolution_quality`, `ForbiddenQuestionTransitionException` → `forbidden_question_transition`. Without these, all four fall through the generic `DomainException => 'internal'` arm.

**Reason:** The task brief for question tools/commands didn't mention error codes, and the four exceptions already existed (added by the prior ai-lib task) as plain `DomainException` subclasses with no code assigned anywhere. Adding a specific arm per exception is the same move already made for every other validation exception in this file (`InvalidVerificationException => 'invalid_verification'`, `InvalidLogTypeException => 'invalid_log_type'`, `InvalidStatusException => 'invalid_status'`) — it is extending an existing, established table with the new question exceptions, not inventing a new error-code scheme. Leaving question validation failures mapped to `internal` would make `tm_question_add`/`tm_question_resolve` less useful to a calling agent than every sibling tool, for no reason tied to the task's scope.

**Where:** `src/Mcp/BaseTools.php`, `src/Cli/BaseCommand.php`. Exercised in `tests/Cli/QuestionCommandsTest.php::it_rejects_invalid_kind_on_add` (asserts `invalid_question_kind`).

**Residual gap:** `ApplicationDbPathTest` and `McpServerDbPathTest` cover `resolveDbPath()` as a pure function; neither exercises the wiring that calls it from `Application`'s constructor or `McpServer::boot()` and hands the result to `openDatabase()`. There is no test asserting that a `TM_DB` value set in the process environment actually ends up as the PDO connection's path end to end. This is a documented, accepted gap rather than an oversight — closing it needs a test harness that can fork or subprocess to control the environment before construction, which is out of scope for this ticket.

---

## 2026-07-09 — Ticket 172 — Removed `~/.ai-tm/config.toml` and `ConfigLoader`; `tm` now always uses `Config::default()`

**Decision:** `ai-lib`'s `ConfigLoader` and its test are deleted. `Config::default()` is now the sole source of the status lists, log types, and requirement verifications: `ticketStatuses`, `phaseStatuses`, and `taskStatuses` each hold the same seven values (`pending`, `active`, `done`, `blocked`, `failed`, `review`, `skipped`), and `logTypes` gained `assumption` alongside the existing five. `Application` and `McpServer` construct `Config::default()` directly instead of loading and parsing `~/.ai-tm/config.toml`; `SetupCommand` no longer writes that file, only the data directory. `tm config list` / `tm_config_list` still work, now simply returning `Config::default()`. `ConfigInvalidException` is not deleted — `ai-lib`'s `LogService` still throws it, now for a different reason (an inconsistent or missing log-entry scope: a `--task` that belongs to a different phase than the given `--phase`, or none of ticket/phase/task given at all), so the `config_invalid` error code survives with a narrower meaning.

**Reason:** The config file gave every installation an identical value in practice — nobody had a documented reason to run with a narrower status set or a different log-type list — so it was a customisation point nobody used, plus a startup failure mode (`config_invalid`) and TOML-parsing surface to maintain for both `tm-mcp` and `bin/tm`. Collapsing the status lists to one canonical seven-value set for all three entity types also removes a stale carve-out: `Config::default()` previously shipped a narrower baseline (no `failed`/`review` on tickets, `skipped` scoped to phases only) documented as a backward-compatible default that predated the priority-order derivation rule: see the `ticket 172` note in `ai-lib/docs/data-model.md` §7. Removing the file and hardcoding the full seven-value default removes that carve-out along with the file.

**Where:** `ai-lib/src/Domain/Config.php`, `ai-lib/src/Services/ConfigLoader.php` (deleted), `ai-tm/src/Cli/Application.php`, `ai-tm/src/Cli/Commands/SetupCommand.php`, `ai-tm/src/Mcp/McpServer.php`. Documented in `docs/architecture/architecture.md` §7-8, `docs/api/cli.md` §1.4/§1.5/§7/§8, `docs/api/mcp.md` §6/§8, `docs/pre-merge-checklist.md`, `spec.md` §3.1/§3.2/§6/§7, and `ai-lib/docs/data-model.md` §7.

---

## 2026-07-10 — Ticket 167 — Deptrac package selection, superseded

**Decision:** `qossmic/deptrac-shim ^1.0` is replaced by `deptrac/deptrac ^4.6` (the real package, not a shim) across `ai-lib`, `ai-tm`, and `ai-dashboard`. This supersedes the 2026-05-08 "Deptrac package selection" entry above; that entry is left in place, not deleted, per this file's append-only convention.

**Reason:** The 2026-05-08 decision's stated reason — that `deptrac/deptrac` on Packagist had only dev-track versions with no stable release — is false as of this ticket: `deptrac/deptrac` has stable releases through 4.6.2. Ticket 167 exists because the shim exposed a more serious problem than the stability gap it was chosen to work around: `qossmic/deptrac-shim` silently dropped any file it could not parse from the dependency graph and still exited 0, so a green architecture check never proved those files' layer boundaries actually held. Across the three repositories this silently excluded 7 files in `ai-tm`, 4 in `ai-dashboard` (all in `Http`), and 4 in `ai-lib` (including `src/Serializer.php`, the sole member of the `Utilities` layer, which had therefore never been enforced at all). Switching to the real package removes the shim layer entirely; the composer `deptrac` script in all three repositories is also rewritten to grep Deptrac's console output for "Syntax Error" and exit non-zero when found, since Deptrac exposes no machine-readable signal for an unparseable file. See `ai-lib/AGENTS.md` "Quality gates" for the resulting guarantee: a passing `composer deptrac` now means every file under `deptrac.yaml`'s scanned paths was actually parsed, not just that no violation was found among the files the tool happened to read.

**Where:** `composer.json` require-dev and `deptrac` script in `ai-lib`, `ai-tm`, and `ai-dashboard`; `tests/Tooling/DeptracGuardTest.php` (new, one per repository); `templates/feature.toml` and `templates/simple-feature.toml` (dropped the `vendor/bin/deptrac analyse` alternative from the Architecture check task, since the raw binary bypasses the parse guard).

---

## 2026-07-10 — Ticket 167 — Uncovered-dependency policy left unsettled

**Decision:** This ticket does not adopt Deptrac's `--fail-on-uncovered` option or any other policy on uncovered dependencies (a dependency on a class that belongs to no layer defined in `deptrac.yaml`, mostly vendor classes). The composer `deptrac` scripts across all three repositories continue to fail only on a layer violation or an unparseable file; an uncovered dependency count, however large, does not fail the gate.

**Reason:** Deptrac 4.x counts uncovered dependencies differently from the 1.x-era shim, so the Uncovered figure reported by `composer deptrac` changed across this ticket's package swap in all three repositories — not because any code changed, but because the counting method changed. Whether uncovered dependencies should be policed at all is a separate architectural question from the silent-skip bug this ticket fixes (an unparseable file being excluded from the graph, not a class being outside any defined layer), and it was not raised or settled during this ticket's Discovery. Deferring it keeps this ticket scoped to the parse-guard fix.

**Where:** No code change. `composer.json` `deptrac` scripts in `ai-lib`, `ai-tm`, and `ai-dashboard` are unchanged with respect to uncovered dependencies.

---

## 2026-07-11 — Ticket 194 task 2333 — WorkerRunner method shapes and FakeRunner location

**Decision:** `AiToolset\Tm\Grind\WorkerRunner` has exactly two methods: `run(string $prompt, string $model, string $workingDirectory, int $timeoutSeconds): WorkerSession` (start a session and block until it answers or times out — start and await are one call, not two, since the orchestrator never does anything between them) and `stop(WorkerSession $session): void`. There is no separate `start()`/`await()` pair and no follow-up/send operation, matching ticket 194 question 25 (no re-prompt on a malformed outcome) and requirements 396/427/428. `WorkerSession` is a small readonly value object (`id`, `answer` — nullable, `null` meaning timeout — and `transcript`) returned by `run()`; `stop()` takes that same object back so a runner can terminate the underlying process/tmux session using whatever handle it stashed in `id`. `FakeRunner` (the test double, not a test case) lives in `tests/Support/FakeRunner.php`, alongside the existing `BaseCliTest`/`BaseMcpTest` fixtures, rather than under `tests/Grind/`; its own test, `tests/Grind/FakeRunnerTest.php`, sits with the other Grind-layer tests per the existing `tests/<Layer>/` convention.

**Reason:** Requirement 396 describes a "start-await-stop lifecycle" but ticket 2333's task brief explicitly narrows it to "two production concerns: run, stop" — collapsing start+await into one blocking call is the natural reading, since nothing in the orchestrator's loop needs to observe a session between starting it and getting its answer (no polling, no partial-read step is specified anywhere in ticket 194's requirements). Keeping `stop()` separate is required by requirements 428 and 499: a timed-out session must be terminated before the next task's worker starts, and every session the orchestrator started must be stopped at end-of-run, both independent of whether `run()` returned an answer or a timeout. `WorkerSession::transcript` is populated by the runner at the moment `run()` returns (not retrieved via a separate call) so a timed-out session's partial transcript is inspectable without adding a third interface method.

**Where:** `src/Grind/WorkerRunner.php`, `src/Grind/WorkerSession.php`, `tests/Support/FakeRunner.php`, `tests/Grind/FakeRunnerTest.php`, `deptrac.yaml` (new `Grind` layer, allowed to depend on `AiLib` same as `Cli`/`Mcp`; `Cli` allowed to depend on `Grind`; `Mcp` not).

---

## 2026-07-11 — Ticket 194 task 2336 — Loop core, budgeted seam, and data-plane retry

**Decision:** `AiToolset\Tm\Grind\Orchestrator` is the deterministic loop core. `run(string $ticketIdList)` parses/validates the id list, emits one run-start marker, and walks tickets → phases → tasks, owning every `tm` read/write in-process through injected `ai-lib` services. Design choices made here:

- **Seam for budgeted work.** Budgeted phases and budgeted tasks (`max_attempts` > 0) are classified in the core (`processTicket` classifies each phase; `processOrdinaryPhase` classifies each task) but delegated *wholesale* to two collaborator classes, `BudgetedPhaseProcedure` and `BudgetedTaskProcedure`, each with a single `process(...): LoopSignal` method that currently throws a `LogicException` (stubs for tasks 2337 / 2338). All budgeted behaviour — including its own skip/human/reset rules and the phase-pass reset of active tasks (req 460) — belongs inside those stubs, not the core; the core's plain-task procedure only handles `max_attempts` 0 tasks. The collaborators are injected (constructor params default to `new BudgetedPhaseProcedure()` / `new BudgetedTaskProcedure()`), so the CLI wiring task passes the real ones once they gain dependencies, without touching the core.

- **`LoopSignal` enum** (`Proceed` / `StopTicket` / `AbortRun`) is the shared control-flow return of the plain-task procedure and both budgeted stubs, so the core's outer loop never needs to know which handled a step. `StopTicket` ends the current ticket only (human-actor task req 420, missing-model req 403); `AbortRun` ends the whole run (prohibited-action refusal req 430, and the budgeted terminal cases the stubs will return).

- **Data-plane retry wrapper.** A private generic `retry(string $operation, \Closure $call)` attempts each `ai-lib` call up to 3 times with a 1-second pause between attempts (Planning policy for req 481); on the third failure it throws `DataPlaneFailedException` naming the operation and its ticket/task, which `run()` catches to emit the abort line and stop. The pause is an injected `\Closure(int): void $sleeper` (defaults to real `sleep()`) so tests do not wait. The grind-run creation and the ticket-existence lookup catch their *expected* domain exceptions (`AlreadyExistsException` = resume, `NotFoundException` = unknown id) *inside* the retry closure, so those normal outcomes are never mistaken for retryable failures.

- **Deep-to-plain mapping.** The walk uses the deep fetches the requirements mandate (`TicketService::showDeep` req 407, `PhaseService::showDeep` req 410); `WorkerPromptBuilder` needs the plain `TicketOut`/`PhaseOut`/`TaskOut`, so the core maps deep→plain with three private pure methods rather than making extra retry-wrapped `show()` round-trips.

- **No final-report aggregation yet.** `run()` returns void and drives all observable output through the injected `emit` sink; the skipped-human counts and the run-completion report (reqs 420/491/etc.) are left for the report/CLI-wiring tasks. Exact wording of the non-pinned emit lines (refusal, human-stop, abort, prohibited-action) is chosen here; only the missing-model note "task N has no model — fix it in planning" is fixed verbatim by req 403.

- **Test service wiring.** `tests/Support/AiLibServices.php` builds the real `ai-lib` services from one in-memory SQLite PDO (no mocks, per AGENTS.md), reused by the loop tests and the later budgeted-procedure tests. The persistent-data-plane-failure test injects a real failure by `DROP TABLE grind_run` (table is singular), not a mock.

**Where:** `src/Grind/Orchestrator.php`, `src/Grind/LoopSignal.php`, `src/Grind/DataPlaneFailedException.php`, `src/Grind/BudgetedPhaseProcedure.php`, `src/Grind/BudgetedTaskProcedure.php`, `tests/Grind/OrchestratorTest.php`, `tests/Support/AiLibServices.php`.

---

## 2026-07-11 — Ticket 194 task 2337 — Budgeted-phase procedure and the shared kernel

**Decision:** Filled the `BudgetedPhaseProcedure` seam (the pass loop, unconditional attempts count, and every terminal case) and, to satisfy the task's "reuse the core's helpers rather than duplicating it" instruction, extracted the loop core's shared machinery into a new `AiToolset\Tm\Grind\GrindKernel`. Details:

- **`GrindKernel` (new).** Holds the data-plane retry wrapper (`retry()`, the same 3-attempt/1-second policy, `DataPlaneFailedException` on exhaustion), the worker dispatch (`dispatch()` = build prompt → run → stop → parse, `isCheck` = "a `CheckContext` was supplied"), the `emit()` sink, and the three deep→plain mappers. `Orchestrator` now builds one kernel and delegates to it (its private `retry`/`emit`/`toXOut` became one-line forwarders, its plain-task dispatch became a single `kernel->dispatch(..., null)` call); `BudgetedPhaseProcedure` takes the same kernel. This is the "core call-site change" the task allowed — behaviour is identical, so the existing `OrchestratorTest` cases pass unchanged. `phases`/`tasks` are public on the kernel so callers wrap their own narrow `set()` calls in the shared `retry()`; the retry wrapper is the shared piece, the field written is the caller's business.

- **Pass loop (reqs 459-466).** `process()` skips a phase whose own status reads `done` (req 414), then applies the exhausted-budget entry check — `attempts >= max_attempts` means the budget was spent on an earlier run, so it changes nothing and returns `AbortRun` (req 483). Otherwise it records any double-budget conflict once (below) and loops: reset every task to pending (human-actor tasks excepted — the walk stops the ticket there, reqs 420/460), walk the phase dispatching every task budget-less with `CheckContext::forPhase(round)` (reqs 459/461), then increment attempts unconditionally right after the pass (req 463). Decision: no fix + all done/review → `Proceed` (req 464); no fix + a failed task → `AbortRun` (req 465); a fix below the limit → another pass; a fix at the limit → explicit `phases->set(status: 'failed')` then `AbortRun` (req 466). A resumed/interrupted pass re-enters at the reset step (req 484) because the whole pass is one iteration of the `while` loop — there is no partial-pass resume state.

- **All budgeted-phase terminal stops are `AbortRun` (run-level), not `StopTicket`** — reqs 416/482 say a budgeted terminal case ends the whole run. The two ticket-level exceptions inside the phase (a human-actor task, req 420; a task with no model, req 403) return `StopTicket`, matching the plain-task path.

- **`fix_applied` reading (req 462).** Only `fix_applied: yes` counts as a fix. A check dispatch that produced no valid outcome (parse-failed or timed out — `TaskResult::$fixApplied` is null) counts as no fix and, being recorded `failed`, as a failed task. No re-prompt (req 388/question 25); the parser already fails such a reply.

- **Double-budget conflict recording (reqs 415/495).** `BudgetedPhaseProcedure` accumulates `DoubleBudgetConflict` records (new small readonly DTO: ticket id, phase id, phase max_attempts, overridden task ids) in a private list, exposed via `conflicts()`. The instance lives for the whole run (the `Orchestrator` holds one), so it collects across phases and tickets. Not written to `tm` (that would exceed req 475's narrow write set) — it is in-memory state for the final-report task to replay. The report/CLI-wiring task reads `BudgetedPhaseProcedure::conflicts()`.

- **Test-fixture note.** The grind tests seed projects with `autoStatus: false`, so ai-lib's rollup never derives phase/ticket status from tasks — a phase reads `done` only when explicitly set. The three skip-if-done tests therefore call `phases->set(status: 'done')`; the other budgeted tests leave the phase `pending` so the pass loop runs. The two `OrchestratorTest` reset cases that used to swallow the stub's `LogicException` now mark the phase done so the real procedure skips it, keeping their focus on the fresh-vs-resume attempts reset.

**Where:** `src/Grind/GrindKernel.php` (new), `src/Grind/BudgetedPhaseProcedure.php`, `src/Grind/DoubleBudgetConflict.php` (new), `src/Grind/Orchestrator.php`, `tests/Grind/BudgetedPhaseProcedureTest.php` (new), `tests/Grind/OrchestratorTest.php`.

---

## 2026-07-11 — Ticket 194 task 2339 — Final report: run-state ownership and rendering rules

**Decision:** Added a `RunReport` (new, plus small `TicketReport`/`BudgetedStop` value objects) as a public property on `GrindKernel` — `public RunReport $report`, constructed once per kernel — rather than threading a report object through every method signature. `Orchestrator`, `BudgetedPhaseProcedure`, and `BudgetedTaskProcedure` all already hold a reference to the shared kernel, so each `record*()` call happens at the exact point the loop already knows the fact (skip reason, dispatch outcome, budgeted stop, conflict), never re-derived from an emitted chat line. `Orchestrator::run()` calls `emitFinalReport()` — which pulls `BudgetedPhaseProcedure::conflicts()` into the report and renders it — at every point `run()` can end (normal completion, `AbortRun`, and the caught `DataPlaneFailedException`), but not on the early bail-outs (empty/invalid id list, unknown ticket) that predate the `grind started` marker, so those keep emitting exactly the one line the existing tests expect.

Three rendering rules not spelled out by requirements 485-495 that needed a call:
- **Distinct-task counting (req 493).** `RunReport::recordOutcome()` is keyed by task id and overwrites; a task dispatched twice by a budgeted-phase pass or a budgeted-task retry naturally collapses to one entry under its final status, with no separate dedup step.
- **"Zero pending tasks" vs. "never reached".** A ticket only gets a report block if `Orchestrator::processTicket()` called `report->openTicket()` on it — this fires as the very first line of that method, before any data-plane call. A ticket the run never got to (an earlier ticket aborted the whole run) is silently omitted from the per-ticket blocks, even though its id still appears in the opening summary's ticket list (req 486 asks for "which ticket ids were run", i.e. requested, not necessarily completed). A ticket that was opened but never produced a single skip/outcome event (a phase with no tasks) renders as `ticket <id>: no pending tasks` — the case req 493 actually names.
- **Budgeted force-fail asymmetry.** A budgeted-*phase* force-fail (`phases->set(status: 'failed')` on budget exhaustion with an unconfirmed fix) does not touch any individual task row, so the report leaves each task's last dispatch outcome as recorded. A budgeted-*task* force-fail does overwrite that task's own status to `failed`, so `BudgetedTaskProcedure` calls `report->recordOutcome(..., 'failed')` again right after that `tasks->set()` call, overriding the earlier `done` from the same dispatch.

`Orchestrator` gained a new constructor parameter, `QuestionService $questions` (question-by-kind counts, req 489, come from `QuestionService::list($ticketId, group: 'open')`). Nothing outside tests constructs `Orchestrator` yet (the CLI wiring is task 2340), so this is not a breaking public-interface change in practice; `tests/Support/AiLibServices.php` gained a matching `questions` service.

**Where:** `src/Grind/RunReport.php` (new), `src/Grind/TicketReport.php` (new), `src/Grind/BudgetedStop.php` (new), `src/Grind/GrindKernel.php`, `src/Grind/Orchestrator.php`, `src/Grind/BudgetedPhaseProcedure.php`, `src/Grind/BudgetedTaskProcedure.php`, `tests/Support/AiLibServices.php`, `tests/Grind/OrchestratorTest.php`.

---

## 2026-07-11 — Ticket 194 task 2340 — `grind:run` command: no JSON envelope, runner-selection placeholder, signal-driven cleanup

**Decision:** Added `AiToolset\Tm\Cli\Commands\GrindOrchestratorCommand` (CLI name `grind:run`), wired into `Application::wireCommands()` alongside the other commands, with the same `TM_DB`-only database resolution as every other entry point (req 391 — no database option on the command itself). Three judgment calls:

- **No `{ok, data}` / `{ok, error}` JSON envelope.** Every other `tm` command runs through `BaseCommand::success()`/`handleError()`, which JSON-encode the result. `GrindOrchestratorCommand` does not extend that pattern for its actual output: it writes the orchestrator's own plain-text lines (run-start marker, any stop lines, the final report — all already fully formed strings from `Orchestrator`/`RunReport`) straight to stdout via `$output->writeln()`, and its own CLI-usage errors (`unknown --runner value`, bad `--timeout`, the not-yet-available runner error) are likewise one plain line each, not a JSON error object. Reason: this command is not a data-plane verb returning a value for a caller to parse — it *is* the long-running orchestrator process, and requirements 405/497 require its stdout to be relayed to the user verbatim, unmodified. Wrapping it in the envelope would mean either JSON-encoding a multi-line human-readable report as a string field (nobody reads that more easily) or inventing a new envelope shape solely for this command, both worse than simply not using the envelope. `BaseCommand` is still the parent class (for `requireOption`/`requireIntOption`-style helpers if needed later), but this command overrides the output path entirely rather than calling `success()`/`handleError()`. This is a documented deviation from the "CLI is a contract" convention (AGENTS.md); the toolset-wide architecture doc gets a matching note in the later documentation task.

- **Runner-selection placeholder.** `--runner=tmux|process` (default `tmux`) is validated by the command itself against the known pair (its own "unknown --runner value" error), then resolved through a new `AiToolset\Tm\Grind\RunnerFactory::create(string $name): WorkerRunner`. Neither production runner (the ai-tmux runner for `tmux`, the `claude -p` runner for `process`) exists yet — they are tasks 2342/2343. Chose the "factory throws" option over "minimal placeholder classes implementing `WorkerRunner`": one small file, no dead classes sitting in `src/Grind/` implementing an interface they cannot yet honor, and no risk of a later task forgetting to delete a throwaway class. `RunnerFactory::create()` throws `RuntimeException` with a message naming which later task builds that runner (2342 for tmux, 2343 for process) — that message, not a class name, is where tasks 2342/2343 know to plug in: replace the `throw` for their branch with the real construction. The command injects a `?WorkerRunner $runnerOverride` constructor parameter (used in place of the factory when set) so tests inject `FakeRunner` directly regardless of `--runner`'s value; production wiring in `Application::wireCommands()` passes no override, so it always goes through the factory today.

- **Signal-driven cleanup via a tracking decorator, not orchestrator changes.** `Orchestrator`/`GrindKernel` (tasks 2336-2339) already call `WorkerRunner::stop()` right after every `run()` on the normal path, but expose no hook for external cancellation mid-run. Rather than adding one, the command wraps whichever `WorkerRunner` it uses in a new `AiToolset\Tm\Grind\TrackingWorkerRunner`, which records every session `run()` hands back and forgets it once `stop()` is called on it — so at any instant it knows exactly the sessions started but not yet stopped (in practice: zero or one, since the loop is single-threaded and sequential). `pcntl_async_signals(true)` plus `pcntl_signal()` handlers for `SIGTERM`/`SIGINT` call `TrackingWorkerRunner::stopAll()`, write one plain interruption line to stdout in place of the final report, and `exit(1)` — satisfying req 499's "no worker the orchestrator spawned outlives the run" without touching the already-committed orchestrator core. Handlers are restored to `SIG_DFL` in a `finally` after `Orchestrator::run()` returns normally. No PHPUnit test covers signal delivery (task brief: "no test harness for it") — partly because it cannot be driven deterministically inside a test process, partly because the only two runners this exists to interrupt (tmux, process) are not built yet, so there is nothing real to interrupt mid-run today. `composer.json` gained `"ext-pcntl": "*"` in `require`.

**Where:** `src/Cli/Commands/GrindOrchestratorCommand.php` (new), `src/Grind/RunnerFactory.php` (new), `src/Grind/TrackingWorkerRunner.php` (new), `src/Cli/Application.php` (wiring), `composer.json` (`ext-pcntl`), `tests/Cli/GrindOrchestratorCommandTest.php` (new).

---

## 2026-07-11 — Ticket 194 task 2342 — AiTmuxRunner: executor seam, prompt file, and cleanup timing

**Decision:** Added `AiToolset\Tm\Grind\AiTmuxRunner`, the default `WorkerRunner`, driving ai-tmux through one new seam, `CommandExecutor::run(list<string> $command, array<string,string> $env = []): CommandResult` (argv-style, no shell, `$env` additive on top of the ambient environment). `ProcessCommandExecutor` is the real implementation (`proc_open`, stdlib only, no new Composer dependency); `tests/Support/ScriptedCommandExecutor.php` is the test double, alongside `FakeRunner` per the existing convention. Several judgment calls beyond the task brief:

- **`TM_DB` resolution duplicates `Application::resolveDbPath()`'s one-line logic** (`AiTmuxRunner::fromEnvironment()`) instead of calling it: `deptrac.yaml` restricts the `Grind` layer to depending on `AiLib` only, so `Grind` code cannot import from `Cli`. The duplication is one ternary line, not worth a shared-utility class.

- **Exit code 6 (`open`'s "prompt delivery unconfirmed") is folded into the same no-response shape as a timeout** — `run()` stops the session immediately and returns `WorkerSession` with `answer: null`, rather than throwing. This lets the orchestrator's existing requirement-428 "no answer within timeout" handling cover it without a new outcome case; only genuinely unexpected non-zero `open` exits (2/3/4/other) throw `RuntimeException`, per the `WorkerRunner::run()` contract's "could not start the session at all".

- **Poll loop counts polls, not wall-clock elapsed time**: `maxPolls = ceil(timeoutSeconds / pollIntervalSeconds)`, one `ai-tmux status` call per poll, sleeping first via an injected `\Closure(int): void $sleeper` (defaults to real `sleep()`, same pattern as `Orchestrator`/`GrindKernel`). Keeps the timeout math exact and the tests fast and deterministic (no real sleeping).

- **`WorkerSession::$transcript` is the raw JSON text of the last `ai-tmux status` poll** (or of the `open` result, for the exit-6 case) rather than a separate `ai-tmux capture` call. This is the cheapest thing that satisfies requirement 428's "a partial transcript from a hung worker must still be inspectable afterward" without adding a second CLI round trip per poll; the persisted Claude transcript file itself is untouched by `stop()` regardless (ai-tmux's own guarantee).

- **Prompt-file cleanup happens per-session inside `stop()`**, not through a separate "end of run" hook — the `WorkerRunner` interface has no such hook, and `GrindKernel::dispatch()` already calls `stop()` unconditionally right after every `run()`. `stop()` is safe to call twice (the exit-6 and timeout paths already call it once internally before `run()` returns): the `ai-tmux stop` call is idempotent, and prompt-file removal is a no-op once the file is already gone. Each `run()`'s prompt file lives at `<tmpDir>/<sessionId>.txt`; `removePromptFile()` also attempts a best-effort `@rmdir($tmpDir)`, which only succeeds once the last file in that run's temp directory is gone.

- **Binary and poll-interval env vars**: `TM_AI_TMUX_BIN` (default `ai-tmux`, resolved via PATH) and `TM_AI_TMUX_POLL_INTERVAL` (default 10 seconds) — names not specified by the task brief, chosen to match the existing `TM_AI_TMUX_*`-prefix-free `TM_*` convention used for `TM_DB`.

**Where:** `src/Grind/AiTmuxRunner.php` (new), `src/Grind/CommandExecutor.php` (new), `src/Grind/CommandResult.php` (new), `src/Grind/ProcessCommandExecutor.php` (new), `src/Grind/RunnerFactory.php` (tmux branch wired), `tests/Support/ScriptedCommandExecutor.php` (new), `tests/Grind/AiTmuxRunnerTest.php` (new), `tests/Grind/ProcessCommandExecutorTest.php` (new), `tests/Cli/GrindOrchestratorCommandTest.php` (`it_uses_the_runner_factory_when_no_override_is_injected` switched from asserting `tmux`'s now-obsolete placeholder message to `process`'s, still-accurate placeholder message).

---

## 2026-07-11 — Ticket 194 task 2343 — ClaudeProcessRunner: `claude -p` fallback, `CommandExecutor` gains stdin/cwd/timeout

**Decision:** Added `AiToolset\Tm\Grind\ClaudeProcessRunner`, the `process` `WorkerRunner`: one `claude -p --session-id <uuid> --model <model> --permission-mode bypassPermissions` subprocess per `run()` call, cwd set to the worker's project path, `TM_DB` injected explicitly into the subprocess env (same requirement-391 reasoning as `AiTmuxRunner`'s `AI_TMUX_ENV`), the full prompt delivered on stdin, stdout taken verbatim as the answer. `claude --help` was run against the installed CLI to confirm flag spelling before coding: `-p`/`--print`, `--session-id <uuid>`, `--model <model>`, and `--permission-mode` with `bypassPermissions` as one of its listed choices — the task brief's flag spelling was exactly right, no deviation to record.

- **`CommandExecutor::run()` extended, not replaced.** `claude -p` needs three things `AiTmuxRunner`'s `ai-tmux open`/`status`/`stop` calls never needed: a multi-line prompt on stdin (the command line can't carry it), a specific `cwd`, and a per-call wall-clock timeout that kills the child on expiry. Rather than a second executor abstraction, `CommandExecutor::run()` gained three new optional, named, defaulted parameters — `?string $stdin = null, ?string $cwd = null, ?int $timeoutSeconds = null` — appended after the existing `$env`. `AiTmuxRunner`'s two-positional-argument calls (`$this->executor->run($command, $env)`) are unaffected; `ScriptedCommandExecutor` and `ProcessCommandExecutor` both implement the widened interface, and `CommandResult` gained a matching `timedOut: bool = false` field (`exitCode` is meaningless on a timeout: the process never exited on its own).
- **`ProcessCommandExecutor::run()` drives stdout/stderr/stdin through one `stream_select()` loop**, not the old two-`stream_get_contents()`-then-`proc_close()` sequence, for every call now (not just ones that pass `$stdin`/`$timeoutSeconds`) — this is what lets a `$timeoutSeconds` budget be enforced without a busy-wait, and, separately, lets a large `$stdin` payload (a worker prompt can be well over the ~64KB default pipe buffer) be written incrementally instead of risking a classic proc_open deadlock (child blocked writing to a full stdout pipe while the parent is still blocked writing the rest of stdin). The select loop polls in ≤1s slices even absent a timeout, so a child that closes its pipes without the parent noticing immediately is still revisited promptly; not covered — and out of scope for this task — is a child that closes its own stdout/stderr but never actually exits, which would block on the final `proc_close()` even under a timeout budget that has already been enforced by the select loop.
- **`ClaudeProcessRunner::stop()` is a no-op.** Unlike `ai-tmux`'s interactive tmux tab, `claude -p` is single-shot: by the time `CommandExecutor::run()` returns — whether the process exited on its own or was killed by the timeout — there is no live process left. `stop()` exists only to satisfy the `WorkerRunner` interface `GrindKernel::dispatch()` calls unconditionally.
- **Non-zero exit, an executor timeout, and empty (or whitespace-only) stdout all fold into the same "no response" `WorkerSession` shape** (`answer: null`, `timedOut(): true`) rather than three separate cases — matching the task brief ("Non-zero exit or empty stdout → no-response failure per 428") and letting `WorkerOutcomeParser`'s existing timeout handling cover all three without new branching there. `$transcript` is the subprocess's stderr; the fuller conversation `claude` itself keeps under `~/.claude/projects` is the retained record per the task brief, so this runner does not fetch or copy it.
- **`tests/Cli/GrindOrchestratorCommandTest::it_uses_the_runner_factory_when_no_override_is_injected` reworked.** It previously proved "the factory is actually consulted, not silently bypassed" by asserting `RunnerFactory::create('process')`'s now-obsolete "not yet available" error surfaced through the command. With `process` now a real runner, that assertion point disappeared; the test was changed to seed a ticket with a phase but zero tasks (a new `seedTicketWithNoTasks()` helper) so the walk never reaches `dispatch()` — `RunnerFactory::create('process')` still runs for real and must not throw, proving the wiring, while no subprocess is ever spawned inside the unit test.

**Where:** `src/Grind/ClaudeProcessRunner.php` (new), `src/Grind/CommandExecutor.php` (interface widened), `src/Grind/CommandResult.php` (`timedOut` field), `src/Grind/ProcessCommandExecutor.php` (stdin/cwd/timeout support), `src/Grind/RunnerFactory.php` (`process` branch wired), `tests/Support/ScriptedCommandExecutor.php` (widened signature, `timedOut` on `queueResult()`), `tests/Grind/ClaudeProcessRunnerTest.php` (new), `tests/Grind/ProcessCommandExecutorTest.php` (stdin/cwd/timeout-kill cases against real subprocesses), `tests/Cli/GrindOrchestratorCommandTest.php` (`seedTicketWithNoTasks()`, reworked factory-consultation test).

---

## 2026-07-11 — Ticket 194 task 2344 — `tm_grind` rewritten to the dispatch stub; `HookInstaller` needed no change

**Decision:** `GrindTool::protocol()` (req 389) now returns a short instruction block instead of the old interpreted loop: start `<absolute bin/tm path> grind:run <ticket-ids>` as a background process, the conversation contract for a running background orchestrator (req 390 — a user message mid-run is never a command to act on the run; stop only on the user's explicit instruction, via SIGTERM, never SIGKILL first; resume only on the user's explicit instruction, by re-running `grind:run`, which the stored `grind_run` record turns into a resume), relay the orchestrator's stdout verbatim once it ends (req 497), and replay stored questions verbatim on request (reqs 477, 496). The absolute `bin/tm` path is computed with `dirname(__DIR__, 3)` from the tool's own file location, matching the `dirname(__DIR__, N)` convention already used in `Application.php` and `McpServer.php`, so the instruction is correct regardless of the calling agent's current working directory or the MCP server's install location. `tests/Mcp/GrindToolTest.php` was rewritten from one large test asserting dozens of old-protocol string fragments to five focused tests, one per contract element (start command, conversation contract, verbatim relay, question replay, absence of old-protocol vocabulary).

- **`HookInstaller`'s grind prompt-hook text needed no change.** `GRIND_CONTEXT` reads "call mcp__tm__tm_grind before doing anything else and follow the protocol it returns" — this remains accurate: the tool is still named `tm_grind` and still returns text to follow; the hook text never described the old loop's internals ("drive the loop", per-task detail), so there was nothing tied to the removed inline protocol to update. Checked per requirement 393's code half and left untouched.

**Where:** `src/Mcp/Tools/GrindTool.php`, `tests/Mcp/GrindToolTest.php`.

---

## 2026-07-11 — Ticket 194 requirement 499 — WorkerRunner split into `start` / `awaitAnswer` / `stop`

**Decision:** Reshaped the `WorkerRunner` interface from one blocking `run()` (plus `stop()`) into an explicit `start()` / `awaitAnswer()` / `stop()` lifecycle (question 29, Option B), so the handle to a running worker exists *before* the blocking wait. A dogfood found requirement 499 was not actually met: `TrackingWorkerRunner` only registered a session in its active map *after* `run()` returned — i.e. after the worker had already answered — so a mid-run SIGTERM handler calling `stopAll()` found nothing to stop while a worker was in flight. The underlying stops always worked (`ai-tmux stop <id>` is instant; a `claude -p` subprocess is killable by pid); the gap was purely that the handle was not published until the blocking call finished.

- **`start()` returns a `SessionHandle` immediately** (new small readonly value object carrying the session id and, for the `claude -p` runner, a `ProcessHandle` for the live subprocess). It launches the session — opens the tmux tab / spawns the subprocess — but does not block for the answer. `awaitAnswer(SessionHandle, timeout)` does the blocking poll/read; `stop(SessionHandle)` terminates a session started by `start()`, usable before `awaitAnswer()` has returned. `TrackingWorkerRunner` registers the handle in its active map at `start()` and removes it at `stop()`, so `stopAll()` reaches an in-flight worker.
- **`GrindKernel::dispatch()`** now does `start` → (tracker holds the handle) → `awaitAnswer` → `stop` (in a `finally`) → parse. Outcome mapping and the requirement-428 no-response/timeout handling are unchanged.
- **`AiTmuxRunner`** keeps its per-session prompt-file map keyed by id; the exit-6 "prompt delivery unconfirmed" failed-start is now recorded in a `failedStarts` map at `start()` and surfaced as the no-response `WorkerSession` by `awaitAnswer()`, preserving the requirement-428 shape without a separate case.
- **`ClaudeProcessRunner`** is now stateless (`readonly`): `start()` spawns via the executor and carries the process on the `SessionHandle`; `awaitAnswer()` reads under the timeout; `stop()` terminates if the process is still running. This reverses the earlier task-2343 decision that `stop()` was a no-op — `stop()` now does the real mid-run kill, which is the whole point of requirement 499.
- **`CommandExecutor` seam extended, not replaced.** It gained a `start(command, env, stdin, cwd): ProcessHandle` / `await(ProcessHandle, timeout): CommandResult` / `terminate(ProcessHandle): void` lifecycle for a launched process held across calls (a `claude -p` worker killable mid-run), keeping the run-to-completion `run()` as the convenience `AiTmuxRunner` uses for its quick `ai-tmux` calls (`run()` = start → await → terminate). `ProcessCommandExecutor::await()` gets the exit code and reaps via `proc_close`; `terminate()` kills a still-running process and reaps, and is a no-op once `await()` has already reaped (a mutable `closed` flag on `ProcessHandle`). `ScriptedCommandExecutor` implements both paths.
- **Requirement-499 proof is structural.** `TrackingWorkerRunnerTest` asserts that after `start()` — before `awaitAnswer()` — the handle is in the active set and `stopAll()` invokes the inner runner's `stop()` on exactly that handle; `ClaudeProcessRunnerTest` asserts `stop()` terminates a subprocess started but not yet awaited; `ProcessCommandExecutorTest` asserts a real process launched with `start()` can be killed by `terminate()` without ever awaiting it (in ≤10s, not the process's 30s sleep). Real signal delivery is still not driven in a unit test — it cannot be timed deterministically inside a test process.

**Where:** `src/Grind/WorkerRunner.php`, `src/Grind/SessionHandle.php` (new), `src/Grind/ProcessHandle.php` (new), `src/Grind/AiTmuxRunner.php`, `src/Grind/ClaudeProcessRunner.php`, `src/Grind/CommandExecutor.php`, `src/Grind/ProcessCommandExecutor.php`, `src/Grind/TrackingWorkerRunner.php`, `src/Grind/GrindKernel.php`, `src/Cli/Commands/GrindOrchestratorCommand.php` (handler docblock), `tests/Support/FakeRunner.php`, `tests/Support/ScriptedCommandExecutor.php`, `tests/Grind/TrackingWorkerRunnerTest.php` (new), and the runner/parser tests updated to the lifecycle.

## 2026-07-11 — Ticket 205 — `tm_grind` gains an `engine` parameter; prompt engine restored

**Decision:** `GrindTool::protocol()` takes an `engine` parameter (default `php-tmux`) selecting one of three run modes, superseding the ticket-21 "`tm_grind` takes no parameters" decision above and re-adding the interpreted protocol that ticket 194 removed — now as a selectable engine, not the only behavior:
- `php-tmux` (default): the ticket-194 dispatch stub with `grind:run --runner tmux`. Unchanged default behavior.
- `php-claudep`: the same stub with `--runner process` (the headless `claude -p` runner).
- `prompt-original`: the pre-194 interpreted loop, restored byte-for-byte from tag `milestone/before-deterministic-grind`, which the agent runs in-session, one subagent per task.

An unknown `engine` value is rejected with an `InvalidArgumentException` rather than defaulting, so a typo surfaces instead of silently running the wrong mode.

**Reason:** to run the same ticket through each engine and compare cost (via ccusage) and output quality — to learn whether the deterministic PHP grind (ticket 194) was worth building, and to leave room for future engines. The three modes share the one `grind_run` record per ticket, so a fresh comparison of the same ticket through another mode requires deleting that record first, else it reads as a resume. The `engine` values name the concrete run modes rather than a generic "deterministic", because the PHP orchestrator has two runner variants the user compares separately.

**Where:** `src/Mcp/Tools/GrindTool.php`, `tests/Mcp/GrindToolTest.php`, `src/Mcp/Resources/overview.md`, `src/Mcp/Resources/anti-patterns.md`, `AGENTS.md`, `docs/architecture/architecture.md`, `docs/api/mcp.md`.

## 2026-07-13 — `tm_grind`'s default engine flipped to `prompt-original`

**Decision:** `GrindTool::protocol()`'s default `engine` changes from `php-tmux` to `prompt-original`. An omitted `engine` now runs the interpreted, AI-prompt-driven protocol in-session instead of dispatching the deterministic PHP orchestrator.

**Reason:** the deterministic orchestrator (`php-tmux`/`php-claudep`) is unverified on macOS. The user works across machines and does not want to remember to pass `engine: "prompt-original"` by hand on the machine where the new engine hasn't been proven yet. There is no per-machine setting today — the default is one shared value in this file — so this changes the default everywhere; a caller who wants the deterministic orchestrator asks for it by name (`engine: "php-tmux"`).

**Where:** `src/Mcp/Tools/GrindTool.php`, `tests/Mcp/GrindToolTest.php`, `src/Mcp/Resources/overview.md`, `AGENTS.md`, `docs/architecture/architecture.md`, `docs/api/mcp.md`.

## 2026-08-13 — Ticket 269 task 3648 — `hook:enable`/`hook:disable`

**Decision:** Four small calls made while building the two new commands and their collaborator, `Cli\HookSettingsInstaller`:

- **The `TM_NO_HOOKS` guard is dropped from the shipped commands.** `HookInstaller`'s three hook commands each start with `if [ -n "$TM_NO_HOOKS" ]; then exit 0; fi;` — a manual escape a grind worker session used to avoid double-firing the interactive hooks. `src/Grind/` (the only code that ever set `TM_NO_HOOKS`) is already removed from this branch by an earlier task on this ticket, so nothing produces the variable any more. Keeping a guard against a producer that no longer exists is speculative complexity carried forward for no reason; the new `hook:disable` command is itself now the durable, discoverable way to turn hooks off, which the old env-var check never was. If a manual per-session escape hatch turns out to be wanted later, it is one guard clause to add back.
- **The instruction and marker text is copied into `HookSettingsInstaller`, not imported from `HookInstaller` or `Mcp\Tools\StartHookTool`.** Deptrac's ruleset allows `Cli` to depend on `AiLib` only, not `Mcp` — importing either class would be a layering violation. `HookInstaller` is deleted by a later task on this ticket, so the duplication is a snapshot during the transition, not a permanent second copy; cross-checked verbatim against `HookInstaller.php`'s constants and the owner's live entries in `~/.claude/settings.local.json` before copying.
- **No MCP tool mirror.** §6 of the architecture document says every CLI command has a corresponding MCP tool, but `tm setup` and `tm migrate` — the two existing commands that configure the local machine rather than the data plane — already have none. `hook:enable`/`hook:disable` are the same shape (local machine config, not something a calling agent needs mid-session), so they follow that precedent instead of the general rule.
- **A malformed settings file maps to the existing `internal` error code**, not a new one. `HookSettingsInstaller::readSettings()` throws a plain `\RuntimeException`, which `BaseCommand::errorCode()`'s existing default case already maps to `internal` — adding a dedicated code would be a schema/error-code change, which belongs in `ai-lib` after a design discussion, not something this task should invent for one file-parse failure.

**Reason:** see each bullet; all four are small, reversible implementation choices the architecture document did not settle on its own, made to keep the new commands consistent with existing patterns (`HookInstaller`, `StyleInstaller`, `setup`/`migrate`) rather than inventing new ones.

**Where:** `src/Cli/HookSettingsInstaller.php` (new), `src/Cli/Commands/HookEnableCommand.php` (new), `src/Cli/Commands/HookDisableCommand.php` (new), `src/Cli/Application.php`, `tests/Cli/HookSettingsInstallerTest.php` (new), `tests/Cli/HookCommandsTest.php` (new), `docs/architecture/architecture.md`, `docs/pre-merge-checklist.md`, `docs/api/cli.md`.

## 2026-08-13 — Ticket 269 task 3647 — stripping the installers

**Decision:** Two small calls made while removing the automatic install path from `McpServer::boot()`:

- **`boot()` and `run()` drop their now-unused `$workingDir`/`$styleTarget`/`$hookScriptTarget`/`$workingRulesTarget`/`$reviewCommandTarget` parameters entirely**, rather than leaving them in place unused. Every one of the four Mcp test files that called `boot()` with a scratch `$workingDir` did so only to stop the now-deleted `HookInstaller` from touching a real directory; with that installer gone, the parameter has no reader, so keeping it would be a dead knob nobody could explain a use for. Updated all five call sites (`McpServerTest`, `McpToolSchemaStrictTest`, `McpServerResourcesTest`, `McpServerInstructionsTest`, `McpServerResourceReadTest`) to the two-argument form and dropped the temp-directory scaffolding those four files carried only for that one call.
- **`docs/plan.md` and `docs/decisions.md` are left untouched even though both still describe the deleted installers in the present tense** (for example `docs/plan.md`'s Phase-9 write-up of `HookInstaller`'s behavior). Both files are append-only build/decision logs — the pattern every other entry in this file already follows — not living specs; rewriting history to match the current code would make them useless as a record of what was actually built and when. `docs/architecture/architecture.md` (the living spec) and `docs/pre-merge-checklist.md` (an active check) were updated instead, since those two do describe current behavior.

**Reason:** both are small, reversible calls the task description did not settle on its own — the task said to strip installers and their callers but did not say whether to also trim now-meaningless parameters, or whether historical docs needed to move in lockstep with a living spec.

**Where:** `src/Mcp/McpServer.php`, `tests/Mcp/McpServerTest.php`, `tests/Mcp/McpToolSchemaStrictTest.php`, `tests/Mcp/McpServerResourcesTest.php`, `tests/Mcp/McpServerInstructionsTest.php`, `tests/Mcp/McpServerResourceReadTest.php`, `docs/architecture/architecture.md`, `docs/pre-merge-checklist.md`.

## 2026-08-13 — Ticket 269 task 3654 — `tm info`

**Decision:** Three small calls made while building the read-only `info` command (requirement 694).

- **`info` bypasses `Cli\Application` entirely and is dispatched from `bin/tm` on raw `argv` before `Application` is constructed.** `Application`'s constructor unconditionally runs `SchemaMigrator::migrate()` before opening the database — that is exactly the side effect a read-only diagnostic must not have on a machine with no `~/.ai-tm`. `bin/tm` checks `$argv[1] === 'info'` and, when it matches, builds a bare `Symfony\Component\Console\Application` carrying only `InfoCommand`, runs it, and exits — the normal `Application` path is never reached for this command. `InfoCommand` itself never opens a `PDO` connection unless `is_file($dbPath)` is already true, since SQLite's driver creates the file on connect otherwise.
- **The migration-level fact is the pre-migration state, not the post-self-migration state a normal command would report.** Since `info` never runs `SchemaMigrator`, `pending_migrations`/`unknown_migrations` reflect whatever `phinxlog` actually records at the moment `info` runs — read via `ai-lib`'s `SchemaChecker` directly against a manually opened `PDO`, never written to. This is the simpler and more honest of the two options the task description raised: it shows the real state a support case cares about (has this store actually been migrated?) rather than a state `info`'s own boot path manufactured.
- **`tm_version` is `"development checkout (<git short sha>)"` in a checkout layout, or the composer-resolved pretty version plus `@<short commit>` (via `Composer\InstalledVersions`) in a vendor install** — picked because there are no release tags yet, so neither a semantic version nor a fixed string would be honest; both are cheap (one `git rev-parse --short HEAD` shelled out with the package root as cwd, or a call into Composer's own installed-package metadata) and derived structurally rather than hand-maintained. Layout detection (`Application::isVendorInstall()`) mirrors `bin/tm`'s own two-candidate autoload probe: checkout layouts carry their own `vendor/autoload.php`, vendor installs do not.

**Reason:** all three are small, reversible implementation choices the architecture document did not settle on its own; the task description flagged the self-migration hazard explicitly as "the one subtle part" and asked that the choice be recorded either way.

**Where:** `src/Cli/Commands/InfoCommand.php` (new), `src/Cli/Application.php` (`packageRoot()`, `dataDir()`, `isVendorInstall()`), `bin/tm`, `tests/Cli/InfoCommandTest.php` (new), `tests/Cli/BinTmEntrypointTest.php`, `docs/api/cli.md`.

## 2026-08-13 — Ticket 269 task 3663 — The command is named `info`, not `doctor`

**Decision:** The command built by task 3654 was first called `doctor`. It is renamed to `info` before this ticket merges. Nothing it prints changes.

**Reason:** In `brew`, `flutter`, `npm` and `expo`, a `doctor` command judges: it runs a list of checks, prints a verdict per check, and names the fix for each failure. This command deliberately does none of that — it is a flat map of installation facts that the user pastes to whoever helps him, and the diagnosis happens on the other end. That is what `docker info`, `go env` and `npx envinfo` do, so `info` is the name that matches the behaviour. Adding verdicts instead, to earn the name `doctor`, was rejected: the flat-facts shape is what requirement 694 asks for.

**Where:** `src/Cli/Commands/InfoCommand.php` (renamed from `DoctorCommand.php`), `bin/tm`, `README.md`, `docs/api/cli.md`, `tests/Cli/InfoCommandTest.php` (renamed), `tests/Cli/BinTmEntrypointTest.php`.
