# `tm` — Implementation Plan

This document is the build plan for `tm`. Each phase below is a contiguous unit of work that ends in a working, tested artifact. Implementation runs in order — later phases depend on earlier ones.

For what `tm` is and what it does, see `../spec.md`. For the architecture, see `architecture/architecture.md`. For the data model, see `data-model.md`. For the public surfaces, see `api/cli.md` and `api/mcp.md`.

## Conventions

**TDD throughout.** For every aggregate or feature: write the service test first, watch it fail, then write the smallest implementation that passes. CLI and MCP adapter tests follow the same red-green-refactor loop.

**No mocks.** All tests run against a real SQLite database in memory. The schema is built directly in test fixtures (not via migrations) so tests stay fast and the migration runner can be built last.

**Layer discipline from day one.** Deptrac runs in CI from phase 0. Adapters never touch repositories or models. Services never expose models. DTOs at every public boundary.

**Each phase ends with a dogfood step.** After the layer is implemented and tested, exercise it manually (CLI invocation, MCP tool call, or service-level script) before moving on. This catches the gap between "tests pass" and "feature actually works".

## Phase 0 — Scaffold

Goal: a repository that runs PHPUnit, PHPStan, PHP CS Fixer, Deptrac, and Rector cleanly on an empty codebase, with `composer install` producing a working environment.

Tasks:

- `composer.json` with package name `ai-toolset/tm`, PHP 8.4+ requirement, autoload PSR-4 mapping, and dev dependencies for the five tools (PHPUnit, PHPStan, PHP CS Fixer, Deptrac, Rector) plus Phinx and `php-mcp/server`.
- Directory tree: `src/Domain/Models/`, `src/Repositories/`, `src/Services/`, `src/Schemas/`, `src/Cli/`, `src/Mcp/`, `migrations/`, `config/`, `tests/Service/`, `tests/Cli/`, `bin/`.
- Console-script entries for `tm` and `tm-mcp` in `composer.json`. Stubs in `bin/`.
- `phpunit.xml` configured for `tests/` with strict mode.
- `phpstan.neon` at level `max`, no baseline.
- `.php-cs-fixer.dist.php` with PER Coding Style 2.0.
- `deptrac.yaml` encoding the layer rules from architecture §2.
- `rector.php` targeting PHP 8.4.
- `phinx.php` configured for SQLite at `~/.ai-tm/store.db`.
- `composer scripts` shortcuts: `composer test`, `composer stan`, `composer fix`, `composer deptrac`, `composer rector`, `composer ci` running everything.

Dogfood: `composer install` clean, `composer ci` green on an empty codebase.

## Phase 1 — Project aggregate

Goal: `ProjectService` works end-to-end against in-memory SQLite. No CLI yet.

Tasks:

- Test fixtures: `BaseServiceTest` with helpers to spin up an in-memory PDO connection and create the `projects` table.
- Service tests: `it_creates_a_project`, `it_creates_a_project_with_auto_status_true_by_default`, `it_creates_a_project_with_auto_status_false_when_specified`, `it_lists_active_and_archived_projects`, `it_returns_a_project_by_id`, `it_updates_mutable_fields`, `it_archives_and_restores`, `it_rejects_duplicate_names`, `it_rejects_renaming_to_an_existing_name`, `it_allows_auto_status_true_to_false`, `it_refuses_auto_status_false_to_true_with_forbidden_auto_status_transition`.
- `Project` model.
- `ProjectRepository`.
- `ProjectIn`, `ProjectOut`, `ProjectRef` DTOs.
- `ProjectService`.
- Server-time provider (`Clock` interface + `SystemClock` + `FixedClock` for tests) so timestamps are testable.

Dogfood: a small PHP script that creates a project, lists projects, archives one, and prints the JSON output. Confirm the shape matches `cli.md` §2.

## Phase 2 — Ticket aggregate and StatusTransition infrastructure

Goal: `TicketService` works, and every ticket status change auto-records a `StatusTransition` row in the same transaction.

Tasks:

- `StatusTransition` model and `StatusTransitionRepository` (read methods only; writes go through a `TransitionRecorder` helper).
- `TransitionRecorder` service helper used by every aggregate that has status (Ticket, Phase, Task).
- Service tests for Ticket: `it_creates_a_ticket_with_pending_status_and_writes_initial_transition`, `it_lists_tickets_in_a_project`, `it_returns_a_ticket_by_id`, `it_updates_name_and_status_transactionally`, `it_writes_transition_on_status_change`, `it_archives_and_restores`, `it_rejects_invalid_status`.
- `Ticket` model.
- `TicketRepository`.
- `TicketIn`, `TicketOut`, `TicketRef` DTOs.
- `TicketService` using `TransitionRecorder` on every status-changing path.
- Config loader: TOML reader for `~/.ai-tm/config.toml`, exposing valid status sets and log types. Used by `TicketService` to validate `--status` values. (Historical: `ConfigLoader` and `config.toml` were removed in ticket 172; `ai-lib`'s `Config::default()` is now hardcoded and read directly — see `docs/architecture/architecture.md` §7.)

Dogfood: script creates a project, adds a ticket, changes its status three times, queries the StatusTransition rows, and confirms each change is recorded with correct `from_status` / `to_status`.

## Phase 3 — Phase aggregate with ordering

Goal: `PhaseService` works, ordering is contiguous and atomic.

Tasks:

- Ordering helper (`OrderingService` or a trait) for renumbering siblings on insert / move / delete. Used here, then by Task in phase 4.
- Service tests: `it_creates_a_phase_at_end_by_default`, `it_creates_a_phase_before_a_sibling`, `it_creates_a_phase_after_a_sibling`, `it_renumbers_siblings_on_insert`, `it_moves_a_phase_within_its_ticket`, `it_renumbers_on_move`, `it_renumbers_on_delete`, `it_refuses_delete_when_phase_has_tasks`, `it_refuses_delete_when_phase_has_logs`, `it_writes_initial_status_transition_on_create`, `it_writes_transition_on_status_change`.
- `Phase` model.
- `PhaseRepository`.
- DTOs.
- `PhaseService` using `TransitionRecorder` and the ordering helper.

Dogfood: script creates a ticket with five phases inserted in a non-sequential order, lists them, moves one, deletes another, and confirms the `order` column stays contiguous throughout.

## Phase 4 — Task aggregate with the forbidden-transition rule

Goal: `TaskService` works, including cross-phase reassignment and the one forbidden-transition rule.

Tasks:

- Service tests: ordering-shaped tests parallel to phase 3, plus `it_refuses_set_status_done_from_pending`, `it_refuses_set_status_done_from_blocked`, `it_allows_set_status_done_from_active`, `it_allows_pending_to_active_to_done`, `it_clears_nothing_on_status_change` (callers manage `result` and `ai_result`), `it_reassigns_task_to_another_phase_appending`, `it_renumbers_old_phase_on_cross_phase_reassignment`, `it_refuses_delete_when_task_has_logs`.
- `Task` model.
- `TaskRepository`.
- DTOs.
- `TaskService` using `TransitionRecorder`, ordering helper, and the single transition guard.

Dogfood: script walks a task through `pending → active → done` correctly, then attempts a `pending → done` transition and confirms it is refused with `forbidden_status_transition`.

## Phase 5 — LogEntry aggregate

Goal: `LogService` writes immutable log entries scoped correctly.

Tasks:

- Service tests: `it_writes_a_ticket_scoped_log`, `it_writes_a_phase_scoped_log`, `it_writes_a_task_scoped_log`, `it_populates_ticket_id_for_phase_scoped_logs`, `it_populates_ticket_and_phase_ids_for_task_scoped_logs`, `it_rejects_invalid_log_type`, `it_rejects_inconsistent_phase_task_combination`, `it_lists_logs_in_chosen_sort_order`, `it_lists_logs_under_a_ticket_across_all_scopes`.
- `LogEntry` model.
- `LogEntryRepository`.
- DTOs.
- `LogService` (writes and reads only; no update or delete paths exist).

Dogfood: script writes log entries at all three scopes for one ticket, then queries by each scope and confirms the right entries surface.

## Phase 6 — `auto_status` roll-up

Goal: when `Project.auto_status = true`, status changes on tasks roll up to phases, and changes on phases roll up to tickets, in the same transaction. When `auto_status = false`, no roll-up happens.

Tasks:

- Pure-function tests for the derivation rule: `it_returns_pending_for_empty`, `it_returns_blocked_when_any_child_blocked`, `it_returns_done_when_all_done`, `it_returns_skipped_when_all_skipped`, `it_returns_active_for_mixed_done_and_skipped`, `it_returns_active_for_other_mixes`.
- Trigger-point tests: `it_recomputes_phase_when_task_status_changes`, `it_recomputes_phase_when_task_added`, `it_recomputes_phase_when_task_deleted`, `it_recomputes_old_and_new_phase_on_cross_phase_reassignment`, `it_recomputes_ticket_when_phase_status_changes`, `it_recomputes_ticket_when_phase_added`, `it_recomputes_ticket_when_phase_deleted`, `it_does_not_recompute_when_auto_status_is_false`, `it_writes_status_transitions_for_auto_derived_changes`.
- `StatusDerivation` pure function.
- `RollupService` invoked from `TaskService` (after task changes) and `PhaseService` (after phase changes).
- Wiring: every trigger-point method in TaskService and PhaseService calls `RollupService` inside the transaction.

Dogfood: script creates a project with `auto_status = true`, adds a phase with three tasks, marks them done one by one, observes the phase status flip to `done` and the ticket status follow. Repeat with `auto_status = false` and confirm no changes propagate.

## Phase 7 — `--deep` query mode

Goal: every `show` service method has a deep variant that returns the entity plus its descendants and history in one call.

Tasks:

- Tests: `it_returns_a_project_with_summarized_tickets_when_deep`, `it_returns_a_ticket_with_phases_tasks_logs_and_transitions_when_deep`, `it_returns_a_phase_with_tasks_phase_logs_and_phase_transitions_when_deep`, `it_returns_a_task_with_task_logs_and_task_transitions_when_deep`.
- DTO additions: `*DeepOut` variants per aggregate.
- Service method additions: `showDeep` per aggregate, composing repository queries efficiently (avoid N+1 on tasks under a ticket).

Dogfood: script does `ticketShowDeep` on a ticket with several phases and tasks, prints the JSON, confirms structure matches `cli.md` §10.

## Phase 8 — CLI adapter

Goal: every command in `cli.md` works end-to-end against in-memory SQLite, producing the documented JSON envelope.

Tasks:

- CLI test base class: invokes Symfony Console commands against an in-memory SQLite, captures stdout/stderr/exit code, parses JSON.
- Symfony Console application wiring with all commands registered.
- One command class per CLI verb in `cli.md`. Each is a thin shell calling its service.
- Per-command tests: success case + at least one error case (`not_found`, `invalid_argument`, etc.). For `task set`, include the `forbidden_status_transition` case explicitly.
- Shared response-envelope serializer used by every command.
- `--human` flag rendering: minimal table output for read commands. Not exhaustive — primary path is JSON.

Dogfood: walk through a small project end-to-end via `tm` from the shell — create project, add ticket, add phase, add tasks, run them through statuses, write log entries, query with `--deep`. Confirm every JSON shape matches the docs.

## Phase 9 — MCP adapter

Goal: every tool in `mcp.md` works end-to-end.

Tasks:

- MCP test harness: spin up the `php-mcp/server` in-process, call tools, assert envelope shape.
- One tool handler per tool in `mcp.md` §2. Handlers are thin and delegate to services, sharing the CLI's argument parsing where possible.
- `tm-mcp` binary in `bin/`.
- Per-tool tests mirroring the CLI ones.
- Tool-call error handling distinct from domain error handling, per `mcp.md` §5.

Dogfood: register `tm-mcp` in the MCP client, exercise a few tools through the agent, confirm responses match. Compare a CLI invocation with the equivalent MCP call and verify identical envelopes.

## Phase 10 — Phinx migrations and `tm migrate`

Goal: schema is created via migrations on a fresh database, and every `tm` command refuses to run when migrations are pending.

Tasks:

- Initial migration: full schema as specified in `data-model.md` §3, plus indexes from §8.
- Schema-revision check: every database connection open compares the latest applied revision against the latest known revision, exits with `schema_outdated` and a message pointing at `tm migrate` if mismatched.
- `tm migrate` command: runs Phinx, idempotent, returns the list of applied migrations and the current revision per `cli.md` §9.
- Tests: `it_applies_pending_migrations`, `it_is_a_noop_when_up_to_date`, `it_blocks_other_commands_when_pending` (this last one needs a separate test fixture that uses the file-based migration runner against a temp DB rather than the in-memory schema fixture).

Dogfood: delete `~/.ai-tm/store.db`, run `tm migrate`, confirm the schema is created. Run `tm migrate` again, confirm it is a no-op. Run `tm project list`, confirm it works.

## Phase 11 — `tm setup` and default config

Goal: a fresh machine bootstraps with `tm setup` followed by `tm migrate`, with no further hand-editing required.

Tasks:

- Default `config.toml` content as specified in `data-model.md` §7.
- `tm setup` command: creates `~/.ai-tm/` if missing, writes default `config.toml` if absent, idempotent, never overwrites.
- Tests: `it_creates_data_directory_when_missing`, `it_writes_default_config_when_missing`, `it_does_not_overwrite_existing_config`, `it_does_not_touch_database_file`.

(Historical: ticket 172 deleted `config.toml`, `ConfigLoader`, and the `it_writes_default_config_when_missing` / `it_does_not_overwrite_existing_config` tests above. `tm setup` now only creates the data directory; `Config::default()` is hardcoded — see `docs/architecture/architecture.md` §7-8.)

Dogfood: on a fresh machine (or by removing `~/.ai-tm/`), run `tm setup` then `tm migrate`, confirm `tm project add` works without further setup.

## Phase 12 — Composer path link verification

Goal: `wf` and `dashboard` can consume `tm`'s service layer via a Composer path repository, with no publishing required.

Tasks:

- Verify `tm`'s `composer.json` exposes the right autoload mapping for path-repository consumption.
- Sample consumer project (a tiny PHP script outside `ai-tm/`) that requires `ai-toolset/tm` via path repository, instantiates `ProjectService`, calls a service method.
- README section: brief instructions for `wf` and `dashboard` on how to link `tm` via path repository.

Dogfood: link from a sibling directory, instantiate a service, perform a query, confirm it works.

## Phase 13 — Self-installing logging hook

Goal: when `tm-mcp` starts in a project, it ensures a Claude Code `Stop` hook is present in that project's `.claude/settings.local.json`, and refuses to finish starting — asking the user to restart — when it had to write it. The hook makes the agent record a `tm` log entry at the end of each turn for the ticket the session is working on. This reverses the earlier "hook installation" exclusion in `spec.md` §3.2.

Tasks:

- `HookInstaller` in `src/Mcp/`: given the project root, read `.claude/settings.local.json` (treat a missing or empty file as `{}`); if `tm`'s `Stop` logging hook — recognised by a stable marker substring in its command — is absent, add it to `hooks.Stop`, creating the `.claude/` directory and the array as needed and preserving every other key, then write the file back. Return whether it installed the hook. When the working directory is not a project root, do nothing. Decide and document the behaviour for a malformed settings file (refuse / leave alone / surface) — pick the least surprising option and write a test for it.
- The hook command, and the `tm_stop_hook` tool it delegates to (`Mcp/Tools/StopHookTool`). A `Stop` hook that, unless `stop_hook_active` is already set, blocks the stop with a one-line `reason` telling the agent to call `mcp__tm__tm_stop_hook`. That tool returns the instruction the agent follows: if this turn contained a decision or important fact, record it with `mcp__tm__tm_log_add` against the ticket the session is working on, supplying the ticket id from its own context; if the agent cannot confidently identify the ticket, do not guess — say so visibly and write nothing; if there is nothing worth logging, say so briefly. Never fail silently. The instruction lives in the tool, not in the hook command, so Claude Code's per-turn printout stays short. The hook references no file under `.claude/commands/`; it does depend on the `tm` MCP being up (when it is not, the agent reports the tool is unavailable and the turn ends without a log entry — acceptable because that state lasts only until the next restart).
- A new exception (for example `HookInstalledException`) thrown from `McpServer::run()` when `HookInstaller` reports it installed the hook, with a message telling the user to restart this Claude session so the hook takes effect. Add the matching case in `BaseTools::errorCode()` for consistency with `schema_outdated` (effectively unreachable from a tool call, like that one).
- Wire `HookInstaller` into `McpServer::run()` immediately after `SchemaChecker::assertUpToDate()`, before the tool container is built and the server starts listening.

Tests:

- `HookInstaller` (real temp directories, no mocks): `it_installs_the_hook_when_the_settings_file_is_missing`, `it_installs_the_hook_when_the_settings_file_exists_without_it`, `it_is_a_noop_when_the_hook_is_already_present`, `it_preserves_unrelated_keys_and_other_hooks`, `it_handles_a_malformed_settings_file` (assert the chosen behaviour), `it_does_nothing_when_the_working_directory_is_not_a_project_root`.
- Startup gate: `it_builds_the_server_when_the_hook_is_already_present`, `it_refuses_to_start_when_the_hook_had_to_be_installed` (and asserts the hook is now in the settings file). Use the same in-process pattern the existing `McpServer` tests use.

Dogfood: in a throwaway project with `tm-mcp` configured but no logging hook, start a Claude session — confirm `tm-mcp` wrote the hook to `.claude/settings.local.json` and the session reports the server failed to start with the restart message; restart the session, confirm `tm-mcp` starts and that a turn produces a `tm` log entry.

Out of scope for this phase, revisited only on demonstrated need: a per-project off switch; a `sessions` table or any per-session "active ticket" state stored in the database; the hook inspecting cwd, git branch, or edited files to guess the ticket.

## Phase 14 — Start-of-turn writing-style hook (retired, ticket 165)

This phase built a start-of-turn writing-style hook: every turn, before the agent wrote its reply, it was reminded to write in plain, simple English. Like the Phase 13 logging hook, `tm-mcp` installed it on startup, and the same startup gate refused to finish starting — asking for a restart — when it had to write any hook. The reminder was delivered as a tool call: the hook told the agent to call `mcp__tm__tm_style_hook` and follow what it returned, on the reasoning that agents weigh MCP tool output more heavily than injected context.

It was the third hook `tm` installed into `.claude/settings.local.json`, alongside the end-of-turn `Stop` logging hook (Phase 13) and a start-of-turn task-status hook. Both start-of-turn hooks lived under `UserPromptSubmit`. The task-status hook echoed its instruction straight into the agent's context; the writing-style hook injected an instruction to call the tool, so the writing rule arrived as tool output.

Ticket 165 retired this hook: writing-style guidance now lives in a Claude Code output style on the user's machine, not in a per-turn hook. `StyleHookTool` (`Mcp/Tools/StyleHookTool`) has been deleted along with its test. `HookInstaller` no longer installs the style hook; instead it recognises the old marker substring (`tm:style-hook`) and removes any leftover style-hook entry it finds under `UserPromptSubmit`, so an existing installation cleans itself up the next time `tm-mcp` starts. `HookInstaller` still installs and maintains the `Stop` logging hook, the task-status hook, and the grind hook — only the style hook was removed. Architecture, plan, and MCP API docs updated to match.

What was built and is now removed:

- ~~`StyleHookTool` (`Mcp/Tools/StyleHookTool`): a protocol tool, `tm_style_hook`, that returned one paragraph of plain-English writing guidance — lead with the answer, short simple sentences, everyday words, no jargon, no bullet lists unless asked, and no question whose only purpose is to keep the conversation going.~~ Deleted.
- ~~`HookInstaller`: a third hook under `UserPromptSubmit`, recognised by its own marker substring (`tm:style-hook`), whose one-line command injected an `additionalContext` telling the agent to call `mcp__tm__tm_style_hook`.~~ `HookInstaller` no longer writes this hook; it now detects and removes the marker instead.

Tests: `HookInstallerTest` no longer covers installing the style hook. It now covers removal — a leftover style-hook entry is stripped on the next `install()` call, `install()` reports it wrote the file when only a removal happened, and an installation with no leftover entry is left untouched (a noop) when the other three hooks are already present.

Out of scope: making the task-status start hook a tool call too (it stays echoed); a per-project off switch for any hook.

## Open items deferred to post-build

- Streaming or paginated responses for `*_show --deep` (`mcp.md` §10).
- MCP per-tool annotations as formal metadata.
- Any filtering or pagination on `log list`, `ticket list`, `project list` beyond what is in v1.
- Whether to add an explicit `external_ref` column on Ticket for JIRA-style identifiers.

These are revisited only when concrete need is demonstrated.

## Decisions to revisit if they turn out wrong

These are choices made during the architecture phase that may need revision once we have a working implementation:

- The single forbidden-transition rule on `task set --status done`. If agents work around it in surprising ways, or if it turns out to over-constrain legitimate flows, revisit.
- The `auto_status` roll-up rule's treatment of `skipped`. The current rule makes a phase with mixed `done` and `skipped` tasks resolve to `active`. If that feels wrong in the dashboard, refine.
- Server-generated timestamps with no caller override. If a use case for backdating appears, revisit.
- One log entry table with nullable `phase_id` and `task_id` versus separate tables per scope. The single-table design is simpler; if queries get awkward, revisit.
